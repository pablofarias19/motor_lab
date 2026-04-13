<?php
namespace App\Support;

final class LaborInspectionAnalysisBuilder
{
    public static function build(array $datos, array $documentacion, array $situacion, array $exposicion, array $iril): array
    {
        $registracion = self::buildRegistracion($datos, $documentacion, $situacion);
        $condiciones = self::buildCondiciones($situacion);
        $remuneracion = self::buildRemuneracion($datos, $documentacion, $situacion);
        $documental = self::buildDocumentacion($documentacion, $situacion);
        $estructural = self::buildEstructural($datos, $situacion);

        $matriz = [
            'registracion' => $registracion,
            'condiciones' => $condiciones,
            'remuneracion' => $remuneracion,
            'documentacion' => $documental,
            'estructural' => $estructural,
        ];

        $puntajes = array_map(
            static fn(array $bloque): float => floatval($bloque['puntaje'] ?? 0),
            $matriz
        );

        $puntajeMaximo = max($puntajes ?: [0]);
        $promedio = round(array_sum($puntajes) / max(1, count($puntajes)), 1);
        $infraccion = self::resolveInfraccion($datos, $situacion, $puntajeMaximo);
        $probabilidad = self::resolveProbability($puntajeMaximo, $promedio, $situacion);
        $gradoExposicion = self::resolveLevel(max($promedio, floatval($iril['score'] ?? 0)));
        $recomendacion = self::resolveRecommendation($infraccion, $situacion);

        $observacionesClave = [];
        foreach ($matriz as $bloque) {
            foreach (($bloque['observaciones'] ?? []) as $observacion) {
                $observacionesClave[] = $observacion;
            }
        }
        $observacionesClave = array_slice(array_values(array_unique($observacionesClave)), 0, 5);

        $sancionAdministrativa = floatval($exposicion['conceptos']['sancion_administrativa']['monto'] ?? 0);
        $deudaArca = floatval($exposicion['analisis_empresa']['inspeccion']['deuda_total'] ?? 0);
        $contingenciaIndirecta = max(
            floatval($exposicion['total_con_multas'] ?? 0),
            $sancionAdministrativa,
            $deudaArca
        );
        $fundamentosMontos = self::buildAmountRationale(
            $datos,
            $documentacion,
            $situacion,
            $exposicion,
            $sancionAdministrativa,
            $deudaArca,
            $contingenciaIndirecta
        );
        $consideracionesLegales = self::buildLegalConsiderations(
            $datos,
            $documentacion,
            $situacion,
            $sancionAdministrativa
        );
        $contextoInspectivo = self::buildInspectionContext($datos, $documentacion, $situacion);

        return [
            'infraccion_laboral' => $infraccion,
            'iril_laboral' => round(floatval($iril['score'] ?? 0), 1),
            'nivel_laboral' => self::extractIrilLabel($iril),
            'probabilidad_inspeccion' => $probabilidad,
            'grado_exposicion' => $gradoExposicion,
            'recomendacion_final' => $recomendacion['label'],
            'observaciones_clave' => implode(' | ', $observacionesClave),
            'laboral' => [
                'identificacion' => [
                    'convenio_colectivo_aplicable' => self::orDefault($datos['cct'] ?? ''),
                    'categoria_relevada' => self::orDefault($datos['categoria'] ?? ''),
                    'provincia' => self::orDefault($datos['provincia'] ?? ''),
                    'cantidad_trabajadores' => max(1, intval($datos['cantidad_empleados'] ?? 1)),
                    'fecha_analisis' => date('Y-m-d'),
                    'tipo_registro_declarado' => self::formatRegistro($datos['tipo_registro'] ?? 'registrado'),
                ],
                'objeto' => [
                    'cumplimiento_normativa_laboral' => true,
                    'riesgo_sancion_administrativa' => true,
                    'contingencias_legales' => true,
                    'estrategias_preventivas_y_correctivas' => true,
                ],
                'objeto_detallado' => self::buildObjectDetail($contextoInspectivo),
                'contexto_inspectivo' => $contextoInspectivo,
                'matriz_riesgo' => $matriz,
                'tipificacion' => [
                    'infraccion' => $infraccion,
                    'fundamento' => self::describeInfraccion($infraccion),
                ],
                'cuantificacion' => [
                    'multas_administrativas_estimadas' => round($sancionAdministrativa, 2),
                    'riesgo_economico_indirecto' => round($contingenciaIndirecta, 2),
                    'efecto_multiplicador' => self::buildEffectMultiplier($datos, $situacion),
                    'fundamentos_montos' => $fundamentosMontos,
                ],
                'iril' => [
                    'valor' => round(floatval($iril['score'] ?? 0), 1),
                    'nivel' => self::extractIrilLabel($iril),
                ],
                'diagnostico' => [
                    'nivel_cumplimiento_laboral' => self::resolveComplianceLevel($promedio),
                    'riesgo_sancion_administrativa' => self::resolveLevel($puntajeMaximo),
                    'riesgo_judicializacion' => self::resolveLevel(max($promedio, floatval($situacion['probabilidad_condena'] ?? 0) * 5)),
                    'conclusion_juridica' => self::buildConclusion($infraccion, $observacionesClave, $situacion),
                ],
                'escenarios' => self::buildEscenarios($recomendacion['key'], $situacion),
                'checklist' => self::buildChecklist($datos, $documentacion, $situacion),
                'consideraciones_legales' => $consideracionesLegales,
                'conclusion_estrategica' => [
                    'nivel_riesgo_general' => self::resolveLevel($promedio),
                    'probabilidad_inspeccion' => $probabilidad,
                    'grado_exposicion' => $gradoExposicion,
                    'recomendacion' => $recomendacion['label'],
                    'recomendacion_final' => $recomendacion['label'],
                ],
            ],
            'modelo_sistema' => [
                'riesgo_laboral' => [
                    'registracion' => $registracion['puntaje'],
                    'condiciones' => $condiciones['puntaje'],
                    'remuneracion' => $remuneracion['puntaje'],
                    'documentacion' => $documental['puntaje'],
                    'estructural' => $estructural['puntaje'],
                ],
                'infraccion' => $infraccion,
                'iril' => round(floatval($iril['score'] ?? 0), 1),
                'nivel' => self::extractIrilKey($iril),
                'recomendacion' => $recomendacion['key'],
            ],
        ];
    }

    private static function buildRegistracion(array $datos, array $documentacion, array $situacion): array
    {
        $checks = [
            'alta_temprana' => ($situacion['chk_alta_sipa'] ?? 'no') === 'si',
            'registracion_completa' => ($documentacion['registrado_afip'] ?? 'no') === 'si'
                && ($datos['tipo_registro'] ?? 'registrado') === 'registrado',
            'categoria_correcta' => !empty($datos['categoria']),
            'jornada_real_declarada' => ($datos['tipo_registro'] ?? 'registrado') !== 'deficiente_fecha',
        ];

        $puntaje = 0.0;
        $observaciones = [];

        if (!$checks['alta_temprana']) {
            $puntaje += 1.5;
            $observaciones[] = 'No surge alta temprana regular para el personal relevado.';
        }
        if (!$checks['registracion_completa']) {
            $puntaje += 2.0;
            $observaciones[] = 'La registración declarada presenta inconsistencias frente al estándar de alta completa.';
        }
        if (!$checks['categoria_correcta']) {
            $puntaje += 1.0;
            $observaciones[] = 'No se informó categoría/CCT suficiente para validar encuadre convencional.';
        }
        if (!$checks['jornada_real_declarada']) {
            $puntaje += 1.5;
            $observaciones[] = 'La fecha o modalidad registral declarada exige revisión específica.';
        }
        if (intval($situacion['meses_no_registrados'] ?? 0) > 0) {
            $puntaje += 2.0;
            $observaciones[] = 'Se declaró un período pendiente de regularización registral.';
        }

        return self::makeBlock($puntaje, $checks, $observaciones);
    }

    private static function buildCondiciones(array $situacion): array
    {
        $checks = [
            'jornada_legal_respetada' => null,
            'descansos_y_pausas' => null,
            'seguridad_e_higiene' => ($situacion['chk_examenes'] ?? 'no') === 'si' && ($situacion['chk_epp_rgrl'] ?? 'no') === 'si',
            'elementos_proteccion_personal' => ($situacion['chk_epp_rgrl'] ?? 'no') === 'si',
            'art_vigente' => ($situacion['chk_art_vigente'] ?? 'no') === 'si',
        ];

        $puntaje = 0.0;
        $observaciones = [];

        if (($situacion['chk_art_vigente'] ?? 'no') !== 'si') {
            $puntaje += 2.0;
            $observaciones[] = 'No se reportó ART vigente y regular al momento del análisis.';
        }
        if (($situacion['chk_examenes'] ?? 'no') !== 'si') {
            $puntaje += 1.5;
            $observaciones[] = 'Falta respaldo suficiente sobre exámenes médicos obligatorios.';
        }
        if (($situacion['chk_epp_rgrl'] ?? 'no') !== 'si') {
            $puntaje += 1.5;
            $observaciones[] = 'La trazabilidad de EPP/RGRL aparece incompleta.';
        }
        if (empty($observaciones)) {
            $observaciones[] = 'La muestra cargada no evidencia desvíos críticos en seguridad e higiene.';
        }

        return self::makeBlock($puntaje, $checks, $observaciones);
    }

    private static function buildRemuneracion(array $datos, array $documentacion, array $situacion): array
    {
        $salarioReal = floatval($datos['salario'] ?? 0);
        $salarioRecibo = floatval($datos['salario_recibo'] ?? 0);
        $hayFacturacionParalela = self::hasParallelBillingSignals($documentacion, $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);

        $checks = [
            'salario_conforme_cct' => ($situacion['chk_recibos_cct'] ?? 'no') === 'si',
            'horas_extras_correctamente_abonadas' => null,
            'pagos_bancarizados' => ($documentacion['pago_bancario'] ?? 'no') === 'si',
            'sin_pagos_informales' => ($datos['tipo_registro'] ?? 'registrado') === 'registrado' && ($documentacion['registrado_afip'] ?? 'no') === 'si',
        ];

        $puntaje = 0.0;
        $observaciones = [];

        if (($situacion['chk_recibos_cct'] ?? 'no') !== 'si') {
            $puntaje += 2.0;
            $observaciones[] = 'Los recibos no aseguran alineación con remuneración real/CCT.';
        }
        if (($datos['tipo_registro'] ?? 'registrado') === 'deficiente_salario') {
            $puntaje += 1.5;
            $observaciones[] = 'La propia registración informada indica deficiencia salarial.';
        }
        if ($salarioRecibo > 0 && $salarioReal > $salarioRecibo) {
            $puntaje += 1.0;
            $observaciones[] = 'Existe brecha entre salario declarado y salario de recibo informado.';
        }
        if (($documentacion['pago_bancario'] ?? 'no') !== 'si') {
            $puntaje += 1.0;
            $observaciones[] = 'No hay dato afirmativo de pagos bancarizados para neutralizar observación inspectiva.';
        }
        if (($datos['tipo_registro'] ?? 'registrado') !== 'registrado' || ($documentacion['registrado_afip'] ?? 'no') !== 'si') {
            $puntaje += 2.0;
            $observaciones[] = 'Se detectan indicios de pagos marginales o registración salarial no íntegra.';
        }
        if ($hayFacturacionParalela) {
            $observaciones[] = 'La coexistencia de salario y facturación paralela exige blindar la trazabilidad para evitar que ARCA recaratule ingresos ajenos como remuneración no registrada.';
        }
        if ($hayRiesgoEstructuraOpaca) {
            $observaciones[] = 'Los indicadores de estructura opaca o sobrefacturación aumentan el riesgo de que la inspección fiscal proyecte la maniobra sobre la nómina de la empresa.';
        }

        return self::makeBlock($puntaje, $checks, $observaciones);
    }

    private static function buildDocumentacion(array $documentacion, array $situacion): array
    {
        $checks = [
            'recibos_firmados' => ($documentacion['tiene_recibos'] ?? 'no') === 'si',
            'libro_de_sueldos' => ($situacion['chk_libro_art52'] ?? 'no') === 'si',
            'contratos' => ($documentacion['tiene_contrato'] ?? 'no') === 'si',
            'legajo_completo' => ($documentacion['registrado_afip'] ?? 'no') === 'si'
                && ($documentacion['tiene_recibos'] ?? 'no') === 'si',
        ];

        $puntaje = 0.0;
        $observaciones = [];

        if (!$checks['recibos_firmados']) {
            $puntaje += 1.0;
            $observaciones[] = 'No se informaron recibos firmados suficientes para respaldo integral.';
        }
        if (!$checks['libro_de_sueldos']) {
            $puntaje += 2.0;
            $observaciones[] = 'El libro de sueldos Art. 52 LCT aparece desactualizado o no validado.';
        }
        if (!$checks['contratos']) {
            $puntaje += 1.0;
            $observaciones[] = 'No se acreditó contrato o soporte documental equivalente.';
        }
        if (!$checks['legajo_completo']) {
            $puntaje += 0.5;
            $observaciones[] = 'El legajo no surge completo con la información hoy disponible.';
        }

        return self::makeBlock($puntaje, $checks, $observaciones);
    }

    private static function buildEstructural(array $datos, array $situacion): array
    {
        $cantidad = max(1, intval($datos['cantidad_empleados'] ?? 1));
        $hayFacturacionParalela = self::hasParallelBillingSignals([], $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);
        $checks = [
            'antecedentes_inspecciones' => ($situacion['inspeccion_previa'] ?? 'no') === 'si',
            'juicios_o_intimaciones_activas' => ($situacion['hay_intercambio'] ?? 'no') === 'si' || ($situacion['fue_intimado'] ?? 'no') === 'si',
            'rotacion_personal' => null,
            'reclamos_sindicales' => null,
            'indicios_fraude' => ($situacion['fraude_evasion_sistematica'] ?? 'no') === 'si',
        ];

        $puntaje = 0.0;
        $observaciones = [];

        if ($checks['antecedentes_inspecciones']) {
            $puntaje += 2.0;
            $observaciones[] = 'Existe antecedente de inspección previa con efecto agravante.';
        }
        if ($checks['juicios_o_intimaciones_activas']) {
            $puntaje += 1.5;
            $observaciones[] = 'Ya existe conflictividad formal o intimación previa.';
        }
        if ($checks['indicios_fraude']) {
            $puntaje += 1.5;
            $observaciones[] = 'Se detectó un indicador de evasión sistemática.';
        }
        if ($cantidad >= 20) {
            $puntaje += 1.0;
            $observaciones[] = 'La dotación declarada amplifica el impacto institucional de una inspección.';
        }
        if ($hayFacturacionParalela) {
            $observaciones[] = 'La existencia de una operatoria paralela con facturación o cobros por fuera del recibo vuelve probable la recepción de oficios y pedidos de informes a RR.HH. y Legales.';
        }
        if ($hayRiesgoEstructuraOpaca) {
            $observaciones[] = 'Los indicadores de interposición o estructura opaca incrementan el riesgo de contagio fiscal y de medidas cautelares sobre haberes.';
        }
        if (empty($observaciones)) {
            $observaciones[] = 'No se advirtieron agravantes estructurales relevantes con los datos cargados.';
        }

        return self::makeBlock($puntaje, $checks, $observaciones);
    }

    private static function buildChecklist(array $datos, array $documentacion, array $situacion): array
    {
        return [
            'alta_temprana_trabajadores' => ($situacion['chk_alta_sipa'] ?? 'no') === 'si',
            'registracion_correcta' => ($documentacion['registrado_afip'] ?? 'no') === 'si'
                && ($datos['tipo_registro'] ?? 'registrado') === 'registrado',
            'recibos_firmados' => ($documentacion['tiene_recibos'] ?? 'no') === 'si',
            'libro_sueldos_actualizado' => ($situacion['chk_libro_art52'] ?? 'no') === 'si',
            'art_vigente' => ($situacion['chk_art_vigente'] ?? 'no') === 'si',
            'condiciones_seguridad' => ($situacion['chk_examenes'] ?? 'no') === 'si'
                && ($situacion['chk_epp_rgrl'] ?? 'no') === 'si',
            'pago_conforme_cct' => ($situacion['chk_recibos_cct'] ?? 'no') === 'si',
            'jornada_legal_respetada' => null,
        ];
    }

    private static function buildEscenarios(string $recommendation, array $situacion): array
    {
        $hayFacturacionParalela = self::hasParallelBillingSignals([], $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);
        $hayRequerimientoActivo = ($situacion['hay_intercambio'] ?? 'no') === 'si'
            || ($situacion['fue_intimado'] ?? 'no') === 'si';
        $hayInspeccionPrevia = ($situacion['inspeccion_previa'] ?? 'no') === 'si';

        return [
            'regularizacion_inmediata' => [
                'aplica' => $recommendation === 'regularizacion_inmediata',
                'titulo' => 'Regularización y blindaje de trazabilidad',
                'descripcion' => $hayFacturacionParalela || $hayRiesgoEstructuraOpaca
                    ? 'Corregir registración, salario y F.931, y separar documentalmente la operatoria comercial o bancaria ajena a la relación laboral para impedir que la inspección la trate como salario marginal.'
                    : 'Corregir registración, adecuar salario y completar F.931/libro sueldo para bajar la contingencia administrativa antes de una constatación.',
                'gatillo' => $hayFacturacionParalela || $hayRiesgoEstructuraOpaca
                    ? 'Aplica cuando hay facturación paralela, depósitos o señales de interposición que pueden contaminar el análisis laboral.'
                    : 'Aplica cuando existen meses sin registrar, brecha salarial o inconsistencias formales relevantes.',
                'acciones' => array_values(array_filter([
                    'Auditar alta temprana, libro Art. 52 LCT, recibos y trazabilidad bancaria.',
                    $hayFacturacionParalela ? 'Documentar por escrito que la empresa no participa en la facturación ni en los cobros externos del dependiente.' : null,
                    $hayRiesgoEstructuraOpaca ? 'Separar accesos, herramientas y circuitos internos para evitar que se infiera una maniobra simulada desde la empresa.' : null,
                ])),
            ],
            'inspeccion_en_curso' => [
                'aplica' => $hayInspeccionPrevia,
                'titulo' => 'Inspección o reinspección con cruce fiscal',
                'descripcion' => $hayFacturacionParalela
                    ? 'La reinspección puede requerir no solo documentación laboral sino también explicación sobre depósitos, clientes o facturación externa del dependiente.'
                    : 'Puede existir acta previa o reiteración inspectiva con foco en registración, jornada, ART y F.931.',
                'gatillo' => 'Se activa frente a visitas previas, actas abiertas o reincidencia ante Ministerio de Trabajo/ARCA.',
                'acciones' => array_values(array_filter([
                    'Centralizar la respuesta en RR.HH./Legales y preservar legajo, recibos, libro sueldo y constancias de pago.',
                    $hayFacturacionParalela ? 'Preparar carpeta de deslinde para demostrar que los ingresos extra salariales no provienen del empleador.' : null,
                ])),
            ],
            'defensa_administrativa' => [
                'aplica' => $hayRequerimientoActivo || $recommendation === 'defensa_estructurada',
                'titulo' => 'Descargo técnico con enfoque laboral-fiscal',
                'descripcion' => $hayFacturacionParalela || $hayRiesgoEstructuraOpaca
                    ? 'El descargo debe desvincular la nómina del circuito comercial investigado, exhibiendo que la única contraprestación del empleador es la remuneración registrada.'
                    : 'Conviene preparar un descargo con respaldo registral, documental y previsional para reducir sanciones.',
                'gatillo' => $hayRequerimientoActivo
                    ? 'Existe requerimiento, intercambio formal u orden de informar.'
                    : 'Se recomienda cuando el riesgo estructural obliga a preparar defensa antes del acto administrativo.',
                'acciones' => array_values(array_filter([
                    'Acompañar F.931, transferencias, libro Art. 52 LCT, altas y recibos firmados.',
                    $hayFacturacionParalela ? 'Pedir que cualquier análisis sobre facturación externa se circunscriba al dependiente o a terceros y no se traslade automáticamente al salario.' : null,
                    $hayRiesgoEstructuraOpaca ? 'Responder de inmediato oficios, embargos o pedidos de información para evitar multas por obstrucción o incumplimiento.' : null,
                ])),
            ],
            'estrategia_preventiva' => [
                'aplica' => $recommendation === 'auditoria_preventiva',
                'titulo' => 'Auditoría preventiva con instrucción fiscal mínima',
                'descripcion' => $hayFacturacionParalela
                    ? 'La auditoría debe revisar legajo y pagos, pero también el riesgo de que una facturación externa del dependiente genere presunciones de salario no registrado o pedidos de informes.'
                    : 'Auditoría interna y ordenamiento de compliance laboral antes de una visita de inspección.',
                'gatillo' => 'Se sugiere cuando aún no existe acta, pero sí una exposición potencial que conviene ordenar.',
                'acciones' => array_values(array_filter([
                    'Verificar consistencia entre recibos, transferencias, cargas sociales y legajo.',
                    $hayFacturacionParalela ? 'Emitir política interna que prohíba usar medios o tiempo de trabajo para actividades facturadas ajenas al empleador.' : null,
                    'Definir protocolo para contestar requerimientos del Ministerio de Trabajo, ARCA u oficios judiciales.',
                ])),
            ],
        ];
    }

    private static function buildEffectMultiplier(array $datos, array $situacion): string
    {
        $cantidad = max(1, intval($datos['cantidad_empleados'] ?? 1));
        $factores = [];

        if ($cantidad > 1) {
            $factores[] = 'potencial extensión del reclamo a otros ' . ($cantidad - 1) . ' trabajadores';
        }
        if (($situacion['inspeccion_previa'] ?? 'no') === 'si') {
            $factores[] = 'reiteración de inspección';
        }
        if (($situacion['fraude_evasion_sistematica'] ?? 'no') === 'si') {
            $factores[] = 'riesgo de denuncia por evasión sistemática';
        }

        return empty($factores) ? 'impacto acotado con la información disponible' : implode(', ', $factores);
    }

    private static function buildConclusion(string $infraccion, array $observaciones, array $situacion): string
    {
        $hayFacturacionParalela = self::hasParallelBillingSignals([], $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);
        $base = match ($infraccion) {
            'muy_grave' => 'El caso exhibe desvíos con aptitud sancionatoria severa y exige regularización prioritaria.',
            'grave' => 'Se observan incumplimientos materiales que elevan el riesgo de acta y multa administrativa.',
            default => 'Predominan observaciones formales o parciales, con margen para corrección preventiva.',
        };

        if (($situacion['inspeccion_previa'] ?? 'no') === 'si') {
            $base .= ' Los antecedentes de inspección aumentan la exposición por reincidencia.';
        }
        if ($hayFacturacionParalela) {
            $base .= ' Además, la coexistencia de salario y facturación externa exige preparar una defensa de trazabilidad para evitar recaracterizaciones fiscales.';
        }
        if ($hayRiesgoEstructuraOpaca) {
            $base .= ' La presencia de indicadores de interposición o estructura opaca justifica un enfoque coordinado entre laboral, contable y fiscal.';
        }

        if (!empty($observaciones)) {
            $base .= ' Hallazgos principales: ' . implode('; ', array_slice($observaciones, 0, 3)) . '.';
        }

        return $base;
    }

    private static function buildAmountRationale(
        array $datos,
        array $documentacion,
        array $situacion,
        array $exposicion,
        float $sancionAdministrativa,
        float $deudaArca,
        float $contingenciaIndirecta
    ): array {
        $totalConMultas = round(floatval($exposicion['total_con_multas'] ?? 0), 2);
        $salarioReal = round(floatval($datos['salario'] ?? 0), 2);
        $salarioRecibo = round(floatval($datos['salario_recibo'] ?? 0), 2);
        $mesesNoRegistrados = max(0, intval($situacion['meses_no_registrados'] ?? 0));
        $tipoRegistro = (string) ($datos['tipo_registro'] ?? 'registrado');
        $componenteDominante = self::resolveDominantEconomicDriver($totalConMultas, $sancionAdministrativa, $deudaArca, $contingenciaIndirecta);

        return [
            'multas_administrativas_estimadas' => [
                'aplica' => $sancionAdministrativa > 0,
                'monto' => round($sancionAdministrativa, 2),
                'motivo' => $sancionAdministrativa > 0
                    ? 'Se replica la sanción administrativa ya cuantificada en la exposición para no perder el agravante inspectivo.'
                    : 'No se agregó multa administrativa autónoma porque la exposición vigente no informó una sanción activa.',
                'hechos_relevantes' => array_values(array_filter([
                    $mesesNoRegistrados > 0 ? 'Meses sin registrar: ' . $mesesNoRegistrados : null,
                    $tipoRegistro !== 'registrado' ? 'Tipo de registro observado: ' . self::formatRegistro($tipoRegistro) : null,
                    ($documentacion['registrado_afip'] ?? 'no') !== 'si' ? 'No surge registración AFIP afirmativa.' : null,
                ])),
            ],
            'riesgo_economico_indirecto' => [
                'monto' => round($contingenciaIndirecta, 2),
                'criterio' => 'Se toma el mayor valor entre exposición total con multas, sanción administrativa puntual y deuda ARCA para evitar subestimar el frente económico combinado.',
                'componentes_considerados' => [
                    'total_con_multas' => $totalConMultas,
                    'sancion_administrativa' => round($sancionAdministrativa, 2),
                    'deuda_arca' => round($deudaArca, 2),
                ],
                'componente_dominante' => $componenteDominante,
                'lectura' => $salarioReal > 0 && $salarioRecibo > 0 && $salarioReal > $salarioRecibo
                    ? 'La brecha entre salario real y salario documentado refuerza la necesidad de leer el monto como contingencia integral.'
                    : 'El monto debe leerse como contingencia integral, aun sin brecha salarial explícita informada.',
            ],
        ];
    }

    private static function buildLegalConsiderations(
        array $datos,
        array $documentacion,
        array $situacion,
        float $sancionAdministrativa
    ): array {
        $hayDeficienciaRegistral = ($datos['tipo_registro'] ?? 'registrado') !== 'registrado'
            || ($documentacion['registrado_afip'] ?? 'no') !== 'si'
            || max(0, intval($situacion['meses_no_registrados'] ?? 0)) > 0;
        $hayFacturacionParalela = self::hasParallelBillingSignals($documentacion, $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);

        return [
            [
                'titulo' => 'Ley 25.212 — Pacto Federal del Trabajo',
                'aplica' => true,
                'estado' => 'Marco sancionatorio principal',
                'motivo' => 'Ordena la tipificación de infracciones leves, graves y muy graves ante falencias registrales, salariales, documentales o de seguridad.',
                'impacto_en_montos' => 'Las multas laborales del informe se leen dentro de esta matriz y se agravan si hay reiteración o pluralidad de trabajadores afectados.',
            ],
            [
                'titulo' => 'Arts. 35 y 39 Ley 11.683',
                'aplica' => true,
                'estado' => 'Deber de colaboración con ARCA',
                'motivo' => $hayFacturacionParalela || $hayRiesgoEstructuraOpaca
                    ? 'Si aparecen depósitos, facturación o señales de interposición, la empresa debe responder requerimientos sin mezclar la operatoria ajena con su nómina.'
                    : 'La empresa debe conservar y exhibir documentación registral, salarial y previsional ante requerimientos del Fisco.',
                'impacto_en_montos' => 'La falta de respuesta, la entrega incompleta o la obstrucción inspectiva pueden abrir multas autónomas aunque el fondo del reclamo siga en discusión.',
            ],
            [
                'titulo' => 'Art. 52 LCT y Libro Sueldo Digital',
                'aplica' => true,
                'estado' => 'Prueba central de defensa',
                'motivo' => 'La congruencia entre libro de sueldos, F.931, recibos firmados y cuenta sueldo es la pieza clave para rechazar presunciones de salario no registrado.',
                'impacto_en_montos' => 'Si la trazabilidad es consistente, ayuda a contener multas y a discutir la extensión de cualquier ajuste inspectivo.',
            ],
            [
                'titulo' => 'Art. 132 bis LCT',
                'aplica' => $hayDeficienciaRegistral,
                'estado' => $hayDeficienciaRegistral ? 'Alerta complementaria' : 'Sin activación aparente',
                'motivo' => $hayDeficienciaRegistral
                    ? 'La existencia de registración deficiente o meses omitidos obliga a revisar retenciones y aportes para evitar reclamos adicionales.'
                    : 'No surge un dato fuerte de apropiación o retención indebida que active esta vía en forma inmediata.',
                'impacto_en_montos' => $hayDeficienciaRegistral && $sancionAdministrativa > 0
                    ? 'Puede coexistir con sanciones administrativas ya cuantificadas si además se verifica incumplimiento previsional.'
                    : 'Se mantiene como alerta legal sin adicionar monto autónomo cuando no hay cuantificación expresa.',
            ],
            [
                'titulo' => 'Oficios, embargos y deber de retención',
                'aplica' => ($situacion['hay_intercambio'] ?? 'no') === 'si'
                    || ($situacion['fue_intimado'] ?? 'no') === 'si'
                    || ($situacion['inspeccion_previa'] ?? 'no') === 'si'
                    || $hayFacturacionParalela
                    || $hayRiesgoEstructuraOpaca,
                'estado' => 'Contingencia procedimental relevante',
                'motivo' => 'Ante investigaciones paralelas o maniobras atribuidas a terceros, la empresa puede quedar alcanzada por pedidos de informes y embargos sobre haberes dentro de los límites legales.',
                'impacto_en_montos' => 'No cambia por sí solo la base salarial, pero sí puede generar costos de cumplimiento, astreintes o responsabilidad por incumplimiento de órdenes judiciales.',
            ],
        ];
    }

    private static function buildInspectionContext(array $datos, array $documentacion, array $situacion): array
    {
        $hayFacturacionParalela = self::hasParallelBillingSignals($documentacion, $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);
        $hayDeficienciaRegistral = ($datos['tipo_registro'] ?? 'registrado') !== 'registrado'
            || ($documentacion['registrado_afip'] ?? 'no') !== 'si'
            || max(0, intval($situacion['meses_no_registrados'] ?? 0)) > 0;

        if ($hayFacturacionParalela || $hayRiesgoEstructuraOpaca) {
            return [
                'codigo' => 'contagio_fiscal_por_operatoria_paralela',
                'titulo' => 'Contagio fiscal por operatoria paralela del dependiente',
                'descripcion' => 'El análisis no se limita a la registración laboral: también debe prever que ARCA o la autoridad laboral intenten vincular depósitos, facturación o estructuras opacas del dependiente con salario no registrado o con pagos marginales del empleador.',
                'foco_probatorio' => 'Desvincular la nómina, las transferencias salariales y la infraestructura de la empresa de cualquier flujo comercial o bancario ajeno al contrato de trabajo.',
            ];
        }

        if ($hayDeficienciaRegistral) {
            return [
                'codigo' => 'subregistracion_con_derrame_previsional',
                'titulo' => 'Subregistración con proyección previsional',
                'descripcion' => 'La contingencia principal nace de la registración o remuneración deficiente y puede escalar hacia determinaciones previsionales, multas administrativas y mayores exigencias documentales.',
                'foco_probatorio' => 'Alinear alta, recibos, F.931, libro sueldo y jornada con la realidad del vínculo.',
            ];
        }

        return [
            'codigo' => 'cumplimiento_preventivo_con_vigilancia_fiscal',
            'titulo' => 'Cumplimiento preventivo con vigilancia fiscal mínima',
            'descripcion' => 'Aun cuando la documentación laboral no exhiba un incumplimiento grave, conviene mantener una defensa preparada frente a cruces sistémicos y pedidos de informes del Ministerio de Trabajo o ARCA.',
            'foco_probatorio' => 'Sostener trazabilidad íntegra entre legajo, cuenta sueldo, cargas sociales y políticas internas de uso de recursos.',
        ];
    }

    private static function buildObjectDetail(array $contextoInspectivo): array
    {
        return [
            'Evaluar la exposición laboral y previsional específica del empleador según el foco inspectivo detectado.',
            'Determinar qué documentación y qué trazabilidad probatoria debe preservarse para responder a Ministerio de Trabajo y ARCA.',
            'Medir el riesgo de que hechos externos al recibo de sueldo sean reetiquetados como pagos no registrados o maniobras simuladas.',
            'Priorizar acciones preventivas, correctivas o defensivas con criterio laboral-fiscal.',
            'Hipótesis principal del caso: ' . ($contextoInspectivo['titulo'] ?? 'Sin foco específico'),
        ];
    }

    private static function resolveDominantEconomicDriver(
        float $totalConMultas,
        float $sancionAdministrativa,
        float $deudaArca,
        float $contingenciaIndirecta
    ): string {
        if ($contingenciaIndirecta === $deudaArca && $deudaArca > 0) {
            return 'deuda_arca';
        }

        if ($contingenciaIndirecta === $sancionAdministrativa && $sancionAdministrativa > 0) {
            return 'sancion_administrativa';
        }

        if ($contingenciaIndirecta === $totalConMultas && $totalConMultas > 0) {
            return 'total_con_multas';
        }

        return 'sin_componente_dominante';
    }

    private static function resolveInfraccion(array $datos, array $situacion, float $puntajeMaximo): string
    {
        if (($datos['tipo_registro'] ?? 'registrado') === 'no_registrado'
            || ($situacion['fraude_evasion_sistematica'] ?? 'no') === 'si'
            || (($situacion['chk_art_vigente'] ?? 'no') !== 'si' && ($situacion['chk_epp_rgrl'] ?? 'no') !== 'si')) {
            return 'muy_grave';
        }

        if ($puntajeMaximo >= 3.0 || intval($situacion['meses_no_registrados'] ?? 0) > 0) {
            return 'grave';
        }

        return 'leve';
    }

    private static function describeInfraccion(string $infraccion): string
    {
        return match ($infraccion) {
            'muy_grave' => 'Trabajo no registrado, simulación o desprotección relevante en seguridad e higiene.',
            'grave' => 'Deficiente registración, irregularidad salarial o incumplimiento material de deberes laborales.',
            default => 'Desvíos formales o administrativos corregibles sin núcleo clandestino aparente.',
        };
    }

    private static function resolveRecommendation(string $infraccion, array $situacion): array
    {
        if (($situacion['inspeccion_previa'] ?? 'no') === 'si' || ($situacion['hay_intercambio'] ?? 'no') === 'si') {
            return [
                'key' => 'defensa_estructurada',
                'label' => 'Defensa estructurada',
            ];
        }

        if ($infraccion === 'muy_grave' || intval($situacion['meses_no_registrados'] ?? 0) > 0) {
            return [
                'key' => 'regularizacion_inmediata',
                'label' => 'Regularización inmediata',
            ];
        }

        return [
            'key' => 'auditoria_preventiva',
            'label' => 'Auditoría preventiva',
        ];
    }

    private static function resolveProbability(float $puntajeMaximo, float $promedio, array $situacion): string
    {
        $valor = max($puntajeMaximo, $promedio);
        if (($situacion['inspeccion_previa'] ?? 'no') === 'si') {
            $valor += 0.8;
        }

        return self::resolveLevel($valor);
    }

    private static function resolveComplianceLevel(float $promedio): string
    {
        return match (true) {
            $promedio >= 3.5 => 'crítico',
            $promedio >= 2.5 => 'alto',
            $promedio >= 1.5 => 'medio',
            default => 'bajo',
        };
    }

    private static function resolveLevel(float $score): string
    {
        return match (true) {
            $score >= 4.0 => 'crítico',
            $score >= 3.0 => 'alto',
            $score >= 2.0 => 'medio',
            default => 'bajo',
        };
    }

    private static function hasParallelBillingSignals(array $documentacion, array $situacion): bool
    {
        return self::flag($situacion['tiene_facturacion'] ?? ($documentacion['tiene_facturacion'] ?? 'no'))
            || self::flag($situacion['tiene_pago_bancario'] ?? ($documentacion['pago_bancario'] ?? 'no'))
            || self::flag($situacion['tiene_contrato_escrito'] ?? ($documentacion['contrato_escrito'] ?? 'no'));
    }

    private static function hasOpaqueStructureSignals(array $situacion): bool
    {
        foreach ([
            'fraude_facturacion_desproporcionada',
            'fraude_intermitencia_sospechosa',
            'fraude_evasion_sistematica',
            'fraude_sobre_facturacion',
            'fraude_estructura_opaca',
        ] as $flag) {
            if (self::flag($situacion[$flag] ?? 'no')) {
                return true;
            }
        }

        return false;
    }

    private static function flag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['si', 'sí', 'true', '1', 'yes', 'on'], true);
    }

    private static function makeBlock(float $puntaje, array $checks, array $observaciones): array
    {
        return [
            'puntaje' => round(min(5.0, $puntaje), 1),
            'nivel' => self::resolveLevel($puntaje),
            'checks' => $checks,
            'observaciones' => array_values(array_unique($observaciones)),
        ];
    }

    private static function formatRegistro(string $tipoRegistro): string
    {
        return match ($tipoRegistro) {
            'no_registrado' => 'No registrado',
            'deficiente_fecha' => 'Deficiente por fecha',
            'deficiente_salario' => 'Deficiente por salario',
            default => 'Registrado',
        };
    }

    private static function orDefault(string $value): string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? 'No informado' : $trimmed;
    }

    private static function extractIrilLabel(array $iril): string
    {
        $nivel = $iril['nivel'] ?? null;
        if (is_array($nivel)) {
            return (string) ($nivel['nivel'] ?? '');
        }

        return is_string($nivel) ? $nivel : '';
    }

    private static function extractIrilKey(array $iril): string
    {
        return strtolower(str_replace(' ', '_', self::extractIrilLabel($iril)));
    }
}
