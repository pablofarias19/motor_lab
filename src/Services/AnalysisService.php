<?php
namespace App\Services;

use App\Database\DatabaseManager;
use App\Engines\ArcaEngine;
use App\Engines\ArtEmpresaEngine;
use App\Engines\AuditoriaEngine;
use App\Engines\SolidaridadEngine;
use App\Support\ArcaInspectionReportBuilder;
use App\Support\AnalysisPayloadNormalizer;
use App\Support\AnalysisSessionStore;
use App\Support\ComplementaryLegalAnalysisBuilder;
use App\Support\LaborInspectionAnalysisBuilder;
use App\Support\LegacyEngineFactory;
use Exception;

class AnalysisService
{
    private ?DatabaseManager $db;
    private \IrilEngine $irilEngine;
    private \EscenariosEngine $escenariosEngine;
    private \ExposicionEngine $exposicionEngine;

    public function __construct(
        ?DatabaseManager $db = null,
        ?\IrilEngine $irilEngine = null,
        ?\EscenariosEngine $escenariosEngine = null,
        ?\ExposicionEngine $exposicionEngine = null
    ) {
        $this->db = $db;
        $this->irilEngine = $irilEngine ?? LegacyEngineFactory::createIrilEngine();
        $this->escenariosEngine = $escenariosEngine ?? LegacyEngineFactory::createEscenariosEngine();
        $this->exposicionEngine = $exposicionEngine ?? LegacyEngineFactory::createExposicionEngine();
    }

    public function procesar(array $input): array
    {
        $payload = AnalysisPayloadNormalizer::normalize($input);

        if ($payload['tipo_usuario'] === '' || $payload['tipo_conflicto'] === '') {
            throw new Exception('Faltan datos básicos del formulario.');
        }

        $uuid = ml_uuid();
        $exposicion = $this->exposicionEngine->calcularExposicion(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario']
        );
        $exposicion = $this->enriquecerAnalisisEmpresa($payload, $exposicion);
        $exposicion = $this->sincronizarModeloAccidente($exposicion);
        $exposicion['analisis_complementario'] = ComplementaryLegalAnalysisBuilder::build(
            $payload['datos_laborales'],
            $payload['situacion'],
            $exposicion,
            [
                'tipo_conflicto' => $payload['tipo_conflicto'],
                'documentacion' => $payload['documentacion'],
            ]
        );

        $irilResult = $this->irilEngine->calcularIRIL(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario']
        );
        $exposicion = $this->enriquecerDiagnosticoInspeccionLaboral($payload, $exposicion, $irilResult);

        $escenariosResult = $this->escenariosEngine->generarEscenarios(
            $exposicion,
            $irilResult['score'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario'],
            $payload['situacion'],
            $payload['datos_laborales']['provincia']
        );

        if (($payload['tipo_conflicto'] ?? '') === 'riesgo_inspeccion' && !empty($exposicion['analisis_empresa']['inspeccion'])) {
            $exposicion['analisis_empresa']['inspeccion']['informe_preventivo'] = ArcaInspectionReportBuilder::build(
                $payload,
                $irilResult,
                $exposicion,
                $escenariosResult,
                $exposicion['analisis_empresa']['inspeccion']
            );
        }

        AnalysisSessionStore::remember(
            AnalysisSessionStore::buildRecord($uuid, $payload, $irilResult, $exposicion, $escenariosResult)
        );

        try {
            $db = $this->getDb();
            $id = $db->insertarAnalisis(
                $uuid,
                $payload['tipo_usuario'],
                $payload['tipo_conflicto'],
                $payload['datos_laborales'],
                $payload['documentacion'],
                $payload['situacion'],
                $payload['contacto']['email']
            );

            $db->actualizarResultados(
                $id,
                $irilResult,
                $exposicion,
                $escenariosResult,
                $escenariosResult['recomendado']
            );
        } catch (Exception $e) {
            ml_logear(
                '[AnalysisService] Persistencia degradada, se usará respaldo temporal de sesión: ' . $e->getMessage(),
                'warning',
                'analisis.log'
            );
        }

        return [
            'uuid' => $uuid,
            'schema_version' => $payload['schema_version'],
        ];
    }

    private function getDb(): DatabaseManager
    {
        if (!$this->db instanceof DatabaseManager) {
            $this->db = new DatabaseManager();
        }

        return $this->db;
    }

    private function enriquecerAnalisisEmpresa(array $payload, array $exposicion): array
    {
        if (($payload['tipo_usuario'] ?? '') !== 'empleador') {
            return $exposicion;
        }

        $tipoConflicto = $payload['tipo_conflicto'] ?? '';
        $datos = $payload['datos_laborales'];
        $situacion = $payload['situacion'];
        $documentacion = $payload['documentacion'];

        $analisisEmpresa = $exposicion['analisis_empresa'] ?? [];

        if ($tipoConflicto === 'accidente_laboral') {
            $motor = new ArtEmpresaEngine();
            $basePrestacional = floatval($exposicion['conceptos']['prestacion_art_tarifa']['monto'] ?? ($exposicion['total_con_multas'] ?? 0));
            if ($basePrestacional <= 0) {
                $basePrestacional = max(floatval($datos['salario'] ?? 0), 0);
            }

            $resultado = $motor->calcularExposicionEmpresa($basePrestacional, [
                'estado_ART' => $situacion['estado_art'] ?? 'activa_valida',
                'culpa_grave' => ml_boolish($situacion['culpa_grave'] ?? false),
                'trabajador_no_registrado' => (($datos['tipo_registro'] ?? 'registrado') !== 'registrado')
                    || (($documentacion['registrado_afip'] ?? 'si') === 'no'),
                'via_civil' => ml_boolish($situacion['via_civil'] ?? false),
            ]);

            $analisisEmpresa['art_empresa'] = $resultado;
            $exposicion['conceptos']['empresa_exposicion_art'] = [
                'descripcion' => 'Exposición estimada de la empresa en contingencia ART',
                'monto' => round($resultado['exposicion_estimada_empresa'] ?? 0, 2),
                'base_legal' => 'Análisis empresa / Sistema LRT',
                'nota' => $resultado['responsable_principal'] ?? '',
            ];
            $exposicion['total_con_multas'] = max(
                floatval($exposicion['total_con_multas'] ?? 0),
                floatval($resultado['exposicion_estimada_empresa'] ?? 0)
            );
            $exposicion['total'] = $exposicion['total_con_multas'];
        }

        if ($tipoConflicto === 'responsabilidad_solidaria') {
            $motor = new SolidaridadEngine();
            $liquidacionBase = max(
                floatval($exposicion['total_con_multas'] ?? 0),
                floatval($datos['salario'] ?? 0) * max(1, intval($datos['antiguedad_meses'] ?? 0) / 12)
            );
            $cantidadTrabajadores = max(1, intval($datos['cantidad_empleados'] ?? ($situacion['cantidad_empleados'] ?? 1)));
            $cantidadSubcontratistas = max(1, intval($situacion['cantidad_subcontratistas'] ?? 1));

            $resultado = $motor->calcularRiesgoSolidario([
                'actividad_esencial' => ml_boolish($situacion['actividad_esencial'] ?? false),
                'control_documental' => ml_boolish($situacion['control_documental'] ?? false),
                'control_operativo' => ml_boolish($situacion['control_operativo'] ?? false),
                'integracion_estructura' => ml_boolish($situacion['integracion_estructura'] ?? false),
                'contrato_formal' => ml_boolish($situacion['contrato_formal'] ?? false),
                'falta_f931_art' => ml_boolish($situacion['falta_f931_art'] ?? false),
            ], $liquidacionBase, $cantidadTrabajadores, [
                'cantidad_subcontratistas' => $cantidadSubcontratistas,
            ]);

            $analisisEmpresa['solidaridad'] = $resultado;
            $notaSolidaridad = sprintf(
                'Monto por trabajador %s · universo %d · litigiosidad esperada %s.',
                ml_formato_moneda(floatval($resultado['monto_estimado_por_trabajador'] ?? 0)),
                intval($resultado['universo_trabajadores'] ?? 1),
                (string) ($resultado['tasa_litigiosidad_esperada'] ?? '0%')
            );
            $exposicion['conceptos']['responsabilidad_terceros'] = [
                'descripcion' => 'Exposición máxima solidaria si demanda todo el universo relevado',
                'monto' => round($resultado['exposicion_maxima'] ?? 0, 2),
                'base_legal' => 'Art. 30 LCT',
                'nota' => $notaSolidaridad,
            ];
            $exposicion['conceptos']['exposicion_solidaria_probable'] = [
                'descripcion' => 'Exposición solidaria probable por reclamos estimados',
                'monto' => round($resultado['exposicion_probable'] ?? 0, 2),
                'base_legal' => 'Art. 30 LCT',
                'nota' => sprintf(
                    '%d trabajador%s reclamarían bajo el escenario base.',
                    intval($resultado['trabajadores_reclamantes_estimados'] ?? 1),
                    intval($resultado['trabajadores_reclamantes_estimados'] ?? 1) === 1 ? '' : 'es'
                ),
            ];
            $exposicion['conceptos']['exposicion_solidaria_esperada'] = [
                'descripcion' => 'Exposición judicial esperada por responsabilidad solidaria',
                'monto' => round($resultado['exposicion_esperada'] ?? 0, 2),
                'base_legal' => 'Art. 30 LCT',
                'nota' => trim($notaSolidaridad . ' ' . ($resultado['recomendacion'] ?? '')),
            ];
            $exposicion['total_con_multas'] = max(
                floatval($exposicion['total_con_multas'] ?? 0),
                floatval($resultado['exposicion_esperada'] ?? 0)
            );
            $exposicion['total'] = $exposicion['total_con_multas'];
        }

        if ($tipoConflicto === 'auditoria_preventiva') {
            $motor = new AuditoriaEngine();
            $resultado = $motor->calcularCompliance([
                'salario_mensual' => floatval($datos['salario'] ?? 0),
                'meses_no_registrados' => intval($situacion['meses_no_registrados'] ?? 0),
                'meses_en_mora' => intval($situacion['meses_en_mora'] ?? 0),
                'probabilidad_condena' => floatval($situacion['probabilidad_condena'] ?? 0.5),
                'aplica_blanco_laboral' => ml_boolish($situacion['aplica_blanco_laboral'] ?? false),
                'antiguedad_total' => max(1, intval(($datos['antiguedad_meses'] ?? 0) / 12)),
                'salario_base_indemnizacion' => floatval($datos['salario'] ?? 0),
            ]);

            $analisisEmpresa['auditoria'] = $resultado;
            $exposicion['conceptos']['costo_regularizacion_espontanea'] = [
                'descripcion' => 'Costo de regularización espontánea (CRE)',
                'monto' => round($resultado['cre_costo_regularizacion'] ?? 0, 2),
                'base_legal' => 'Auditoría preventiva',
                'nota' => $resultado['texto_estrategico'] ?? '',
            ];
            $exposicion['conceptos']['costo_litigio_latente'] = [
                'descripcion' => 'Costo de litigio latente esperado (CLL)',
                'monto' => round($resultado['cll_costo_litigio_esperado'] ?? 0, 2),
                'base_legal' => 'Auditoría preventiva',
                'nota' => $resultado['recomendacion_accion'] ?? '',
            ];
            $exposicion['total_con_multas'] = max(
                floatval($exposicion['total_con_multas'] ?? 0),
                floatval($resultado['cll_costo_litigio_esperado'] ?? 0)
            );
            $exposicion['total'] = $exposicion['total_con_multas'];
        }

         if ($tipoConflicto === 'riesgo_inspeccion') {
             $motor = new ArcaEngine();
             $resultado = $motor->calcularRiesgoArca(
                 floatval($datos['salario'] ?? 0),
                 intval($situacion['meses_no_registrados'] ?? 0),
                 ml_boolish($situacion['aplica_blanco_laboral'] ?? false),
                 ml_boolish($situacion['fraude_evasion_sistematica'] ?? false),
                 [
                     'salario_declarado' => floatval($datos['salario_recibo'] ?? 0),
                     'cantidad_empleados' => intval($datos['cantidad_empleados'] ?? ($situacion['cantidad_empleados'] ?? 1)),
                     'dias_desde_reglamentacion' => intval($situacion['dias_desde_reglamentacion'] ?? 30),
                     'modalidad_pago_regularizacion' => $situacion['modalidad_pago_regularizacion'] ?? '',
                     'cuotas_regularizacion' => intval($situacion['cuotas_regularizacion'] ?? 1),
                     'sentencia_firme' => $situacion['sentencia_firme'] ?? 'no',
                     'plan_caducado' => $situacion['plan_caducado'] ?? 'no',
                     'obligacion_cancelada_antes_2024_03_31' => $situacion['obligacion_cancelada_antes_2024_03_31'] ?? 'no',
                     'obligaciones_aduaneras' => $situacion['obligaciones_aduaneras'] ?? 'no',
                     'usa_beneficios_fiscales' => $situacion['usa_beneficios_fiscales'] ?? 'no',
                     'hay_apropiacion_indebida' => $situacion['hay_apropiacion_indebida'] ?? 'no',
                     'honorarios_estimados' => floatval($situacion['honorarios_estimados'] ?? 0),
                     'gratificaciones_habituales' => floatval($situacion['gratificaciones_habituales'] ?? 0),
                     'propinas_habituales' => floatval($situacion['propinas_habituales'] ?? 0),
                     'suplementos_habituales' => floatval($situacion['suplementos_habituales'] ?? 0),
                     'remuneracion_en_especie' => floatval($situacion['remuneracion_en_especie'] ?? 0),
                     'activos_regularizables' => $situacion['activos_regularizables'] ?? [],
                     'tipo_cambio_regularizacion' => floatval($situacion['tipo_cambio_regularizacion'] ?? 1000),
                     'etapa_regularizacion' => intval($situacion['etapa_regularizacion'] ?? 1),
                     'dinero_en_caja_hasta_franquicia' => $situacion['dinero_en_caja_hasta_franquicia']
                         ?? ($situacion['dinero_en_cera_hasta_franquicia'] ?? 'no'),
                 ]
             );

            $analisisEmpresa['inspeccion'] = $resultado;
            $exposicion['conceptos']['capital_omitido_arca'] = [
                'descripcion' => 'Capital omitido estimado ante ARCA',
                'monto' => round($resultado['capital_omitido'] ?? 0, 2),
                'base_legal' => 'ARCA / Seguridad social',
            ];
            $exposicion['conceptos']['intereses_arca'] = [
                'descripcion' => 'Intereses resarcitorios estimados',
                'monto' => round($resultado['intereses'] ?? 0, 2),
                'base_legal' => 'ARCA / Seguridad social',
            ];
            $exposicion['conceptos']['multas_arca'] = [
                'descripcion' => 'Multas e infracciones estimadas',
                'monto' => round($resultado['multas'] ?? 0, 2),
                'base_legal' => 'Ley 11.683 / Fiscalización',
                'nota' => $resultado['detalle'] ?? '',
            ];
            $exposicion['total_con_multas'] = max(
                floatval($exposicion['total_con_multas'] ?? 0),
                floatval($resultado['deuda_total'] ?? 0)
            );
            $exposicion['total'] = $exposicion['total_con_multas'];
        }

        if (!empty($analisisEmpresa)) {
            $exposicion['analisis_empresa'] = $analisisEmpresa;
        }

        return $exposicion;
    }

    private function enriquecerDiagnosticoInspeccionLaboral(array $payload, array $exposicion, array $irilResult): array
    {
        if (($payload['tipo_usuario'] ?? '') !== 'empleador' || ($payload['tipo_conflicto'] ?? '') !== 'riesgo_inspeccion') {
            return $exposicion;
        }

        $inspeccion = is_array($exposicion['analisis_empresa']['inspeccion'] ?? null)
            ? $exposicion['analisis_empresa']['inspeccion']
            : [];

        $analisisLaboral = LaborInspectionAnalysisBuilder::build(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $exposicion,
            $irilResult
        );

        $exposicion['analisis_empresa']['inspeccion'] = array_merge($inspeccion, $analisisLaboral);

        return $exposicion;
    }

    private function sincronizarModeloAccidente(array $exposicion): array
    {
        if (!isset($exposicion['cuantificacion_economica']) || !is_array($exposicion['cuantificacion_economica'])) {
            return $exposicion;
        }

        $maxActual = floatval($exposicion['resultados_clave']['exposicion_maxima_real_con_costas'] ?? 0);
        $totalConMultas = floatval($exposicion['total_con_multas'] ?? 0);
        // El análisis empresa puede elevar la contingencia legacy después del cálculo
        // inicial; preservamos el máximo para no subestimar la exposición final.
        $maximo = max($maxActual, $totalConMultas);

        $exposicion['cuantificacion_economica']['resultado_final']['exposicion_maxima_real'] = round($maximo, 2);
        $exposicion['resultados_clave']['exposicion_maxima_real_con_costas'] = round($maximo, 2);
        $exposicion['total_con_multas'] = round($maximo, 2);
        $exposicion['total'] = round($maximo, 2);

        return $exposicion;
    }
}
