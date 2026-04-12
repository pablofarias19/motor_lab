<?php
namespace App\Services;

use App\Database\DatabaseManager;
use App\Engines\ArcaEngine;
use App\Engines\ArtEmpresaEngine;
use App\Engines\AuditoriaEngine;
use App\Engines\SolidaridadEngine;
use App\Support\AnalysisPayloadNormalizer;
use App\Support\AnalysisSessionStore;
use App\Support\ComplementaryLegalAnalysisBuilder;
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

        $escenariosResult = $this->escenariosEngine->generarEscenarios(
            $exposicion,
            $irilResult['score'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario'],
            $payload['situacion'],
            $payload['datos_laborales']['provincia']
        );

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

            $resultado = $motor->calcularRiesgoSolidario([
                'actividad_esencial' => ml_boolish($situacion['actividad_esencial'] ?? false),
                'control_documental' => ml_boolish($situacion['control_documental'] ?? false),
                'control_operativo' => ml_boolish($situacion['control_operativo'] ?? false),
                'integracion_estructura' => ml_boolish($situacion['integracion_estructura'] ?? false),
                'contrato_formal' => ml_boolish($situacion['contrato_formal'] ?? false),
                'falta_f931_art' => ml_boolish($situacion['falta_f931_art'] ?? false),
            ], $liquidacionBase);

            $analisisEmpresa['solidaridad'] = $resultado;
            $exposicion['conceptos']['exposicion_solidaria_esperada'] = [
                'descripcion' => 'Exposición esperada por responsabilidad solidaria',
                'monto' => round($resultado['exposicion_esperada'] ?? 0, 2),
                'base_legal' => 'Art. 30 LCT',
                'nota' => $resultado['recomendacion'] ?? '',
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
                ml_boolish($situacion['fraude_evasion_sistematica'] ?? false)
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
}
