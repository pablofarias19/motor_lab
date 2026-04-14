<?php
namespace App\Support;

final class LaborInspectionAnalysisBuilder
{
    private const WEIGHT_ECONOMIC = 0.30;
    private const WEIGHT_JURIDICAL = 0.25;
    private const WEIGHT_SANCTION = 0.20;
    private const WEIGHT_MULTIPLIER = 0.15;
    private const WEIGHT_TIME = 0.10;

    public static function build(array $datos, array $documentacion, array $situacion, array $exposicion, array $iril): array
    {
        $registracion = self::buildRegistracion($datos, $documentacion, $situacion);
        $condiciones = self::buildCondiciones($situacion);
        $remuneracion = self::buildRemuneracion($datos, $documentacion, $situacion);
        $documental = self::buildDocumentacion($documentacion, $situacion);
        $estructural = self::buildEstructural($datos, $situacion);
        $estadoInspeccion = self::resolveInspectionState($situacion);
        $eventoFiscal = self::resolveFiscalEvent($situacion, $estadoInspeccion);
        $faseProcedimental = self::buildPhaseMatrix($eventoFiscal);

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
        $matrizProbatoria = self::buildProbatoryMatrix($datos, $documentacion, $situacion, $matriz);
        $riesgoProbatorio = self::resolveProbatoryRisk($matrizProbatoria);
        $probabilidadCondena = self::resolveCondemnProbability(
            $situacion,
            $documental,
            $registracion,
            $estructural,
            $condiciones
        );
        $contingencia = self::buildContingencyBreakdown($datos, $situacion, $exposicion, $sancionAdministrativa, $deudaArca);
        $probabilidadAjuste = self::resolveAdjustmentProbability($eventoFiscal, $matriz, $contingencia, $situacion);
        $probabilidadPenal = self::resolvePenalProbability($eventoFiscal, $situacion, $contingencia);
        $variablesCriticas = self::buildCriticalVariables(
            $eventoFiscal,
            $estadoInspeccion,
            $faseProcedimental,
            $matriz,
            $contingencia,
            $riesgoProbatorio,
            $probabilidadCondena,
            $probabilidadAjuste,
            $probabilidadPenal
        );
        $escenarios = self::buildEscenarios(
            $eventoFiscal,
            $estadoInspeccion,
            $faseProcedimental,
            $contingencia,
            $variablesCriticas['variables_juridicas'],
            $situacion
        );
        $escenarioOptimo = self::selectOptimalScenario($escenarios);
        $recomendacion = self::recommendationFromScenario($escenarioOptimo['key'] ?? null, $infraccion, $situacion);
        $documentacionProbatoria = self::buildDocumentationModule(
            $datos,
            $documentacion,
            $situacion,
            $matriz,
            $matrizProbatoria,
            $riesgoProbatorio,
            $probabilidadCondena
        );

        return [
            'estado_inspeccion' => $estadoInspeccion,
            'evento_fiscal' => $eventoFiscal,
            'fase_procedimental' => $faseProcedimental,
            'infraccion_laboral' => $infraccion,
            'iril_laboral' => round(floatval($iril['score'] ?? 0), 1),
            'nivel_laboral' => self::extractIrilLabel($iril),
            'probabilidad_inspeccion' => $probabilidad,
            'probabilidad_condena' => $probabilidadCondena,
            'probabilidad_ajuste' => $probabilidadAjuste,
            'probabilidad_penal' => $probabilidadPenal,
            'grado_exposicion' => $gradoExposicion,
            'recomendacion_final' => $recomendacion['label'],
            'escenario_optimo' => $escenarioOptimo['slug'] ?? 'riesgo_latente',
            'score' => round(floatval($escenarioOptimo['score'] ?? 0), 1),
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
                'documentacion_probatoria' => $documentacionProbatoria,
                'variables_criticas' => $variablesCriticas,
                'contexto_inspectivo' => $contextoInspectivo,
                'evento_fiscal' => [
                    'codigo' => $eventoFiscal,
                    'titulo' => self::formatFiscalEvent($eventoFiscal),
                    'fase' => $faseProcedimental,
                ],
                'matriz_riesgo' => $matriz,
                'tipificacion' => [
                    'infraccion' => $infraccion,
                    'fundamento' => self::describeInfraccion($infraccion),
                ],
                'contingencia' => $contingencia,
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
                'escenarios' => $escenarios,
                'escenario_optimo' => [
                    'codigo' => $escenarioOptimo['codigo'] ?? 'D',
                    'slug' => $escenarioOptimo['slug'] ?? 'riesgo_latente',
                    'titulo' => $escenarioOptimo['titulo'] ?? 'Escenario A — Situación invisible (riesgo latente)',
                    'score' => round(floatval($escenarioOptimo['score'] ?? 0), 1),
                    'evaluacion' => $escenarioOptimo['evaluacion'] ?? 'Óptimo',
                ],
                'checklist' => self::buildChecklist($datos, $documentacion, $situacion),
                'consideraciones_legales' => $consideracionesLegales,
                'modelo_operativo' => self::buildOperationalModel(
                    $datos,
                    $situacion,
                    $eventoFiscal,
                    $faseProcedimental,
                    $escenarioOptimo,
                    $gradoExposicion,
                    $probabilidadAjuste,
                    $probabilidadPenal,
                    $recomendacion
                ),
                'conclusion_estrategica' => [
                    'estado_inspeccion' => self::formatInspectionState($estadoInspeccion),
                    'evento_fiscal' => self::formatFiscalEvent($eventoFiscal),
                    'nivel_riesgo_general' => self::resolveLevel($promedio),
                    'probabilidad_inspeccion' => $probabilidad,
                    'probabilidad_condena' => round($probabilidadCondena, 2),
                    'probabilidad_ajuste' => round($probabilidadAjuste, 2),
                    'probabilidad_penal' => round($probabilidadPenal, 2),
                    'grado_exposicion' => $gradoExposicion,
                    'escenario_optimo' => $escenarioOptimo['titulo'] ?? 'Reconfiguración preventiva',
                    'score_escenario' => round(floatval($escenarioOptimo['score'] ?? 0), 1),
                    'recomendacion' => $recomendacion['label'],
                    'recomendacion_final' => $recomendacion['label'],
                ],
            ],
            'modelo_sistema' => [
                'estado_inspeccion' => $estadoInspeccion,
                'evento_fiscal' => $eventoFiscal,
                'fase' => $faseProcedimental,
                'riesgo_laboral' => [
                    'registracion' => $registracion['puntaje'],
                    'condiciones' => $condiciones['puntaje'],
                    'remuneracion' => $remuneracion['puntaje'],
                    'documentacion' => $documental['puntaje'],
                    'estructural' => $estructural['puntaje'],
                ],
                'variables_juridicas' => $variablesCriticas['variables_juridicas'],
                'contingencia' => $contingencia,
                'infraccion' => $infraccion,
                'iril' => round(floatval($iril['score'] ?? 0), 1),
                'nivel' => self::extractIrilKey($iril),
                'escenario_optimo' => $escenarioOptimo['slug'] ?? 'riesgo_latente',
                'score' => round(floatval($escenarioOptimo['score'] ?? 0), 1),
                'probabilidad_ajuste' => round($probabilidadAjuste, 2),
                'probabilidad_penal' => round($probabilidadPenal, 2),
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

    private static function buildEscenarios(
        string $eventoFiscal,
        string $estadoInspeccion,
        array $faseProcedimental,
        array $contingencia,
        array $variablesJuridicas,
        array $situacion
    ): array
    {
        $hayFacturacionParalela = self::hasParallelBillingSignals([], $situacion);
        $hayRiesgoEstructuraOpaca = self::hasOpaqueStructureSignals($situacion);
        $contingenciaTotal = array_sum(array_map('floatval', $contingencia));
        $economiaEscalada = $contingenciaTotal >= 10000000 ? 'alta' : ($contingenciaTotal >= 3000000 ? 'media' : 'acotada');

        $catalog = [
            'riesgo_latente' => [
                'codigo' => 'A',
                'slug' => 'riesgo_latente',
                'aplica' => true,
                'titulo' => 'Escenario A — Situación invisible (riesgo latente)',
                'descripcion' => 'Todavía no existe inspección formal, pero las inconsistencias ya permiten una reconstrucción integral si aparece un disparador.',
                'gatillo' => 'Opera cuando el evento fiscal todavía es inexistente y la prioridad es ganar control antes de denuncia, cruce sistémico o fiscalización sectorial.',
                'impacto_prueba' => 'bajo',
                'probabilidad_sancion' => 'media',
                'riesgo_multiplicador' => $variablesJuridicas['riesgo_multiplicador'] ?? 'medio',
                'riesgo_judicial' => 'bajo',
                'factor_economico' => match ($eventoFiscal) {
                    'ninguno' => 96.0,
                    'inspeccion' => 44.0,
                    'acta' => 22.0,
                    default => 12.0,
                },
                'tiempo' => $eventoFiscal === 'ninguno' ? 95.0 : 30.0,
                'lectura_estrategica' => 'Es el único escenario con control total del timing: si se regulariza antes del disparador, se reduce el margen de reconstrucción y mejora la defensa futura.',
                'acciones' => self::buildConditionalActions([
                    'Regularizar alta, salario, F.931, libro Art. 52 y trazabilidad bancaria antes de cualquier constatación.',
                    $hayFacturacionParalela ? 'Revisar de inmediato monotributistas o facturación interna porque ARCA mirará la realidad y no el contrato.' : null,
                    $economiaEscalada !== 'acotada' ? 'Priorizar la nómina con mayor derrame económico para cortar efecto multiplicador.' : null,
                ]),
            ],
            'inspeccion_en_curso' => [
                'codigo' => 'B',
                'slug' => 'inspeccion_en_curso',
                'aplica' => true,
                'titulo' => 'Escenario B — Inspección en curso',
                'descripcion' => 'La fiscalización ya empezó: las encuestas, relevamientos y requerimientos forman la base del ajuste y no admiten improvisación.',
                'gatillo' => 'Corresponde cuando existe inspección, encuesta o requerimiento activo, aun sin acta labrada.',
                'impacto_prueba' => $faseProcedimental['prueba'] ? 'alto' : 'medio',
                'probabilidad_sancion' => 'alta',
                'riesgo_multiplicador' => $variablesJuridicas['riesgo_multiplicador'] ?? 'medio',
                'riesgo_judicial' => 'medio',
                'factor_economico' => match ($eventoFiscal) {
                    'ninguno' => 48.0,
                    'inspeccion' => 95.0,
                    'acta' => 58.0,
                    default => 18.0,
                },
                'tiempo' => match ($eventoFiscal) {
                    'inspeccion' => 92.0,
                    'acta' => 55.0,
                    default => 22.0,
                },
                'lectura_estrategica' => 'El punto central ya no es describir el riesgo sino controlar daño: recolectar prueba inmediata, evitar contradicciones y ordenar el descargo técnico.',
                'acciones' => self::buildConditionalActions([
                    'Centralizar la respuesta al inspector y congelar una narrativa única para personal, RRHH y estudio.',
                    'Recolectar recibos, biometría, legajos, F.931, planillas y soportes de jornada antes de que aparezcan contradicciones.',
                    $hayRiesgoEstructuraOpaca ? 'Separar documentalmente toda estructura opaca o tercerización porque la encuesta puede activar contagio fiscal inmediato.' : null,
                ]),
            ],
            'acta_labrada' => [
                'codigo' => 'C',
                'slug' => 'acta_labrada',
                'aplica' => true,
                'titulo' => 'Escenario C — Acta labrada',
                'descripcion' => 'La infracción ya fue formalizada y la defensa debe concentrarse en hechos, base imponible y reducción sancionatoria.',
                'gatillo' => 'Se activa cuando existe acta formal o cuando el evento fiscal ya superó la etapa puramente inspectiva.',
                'impacto_prueba' => 'alto',
                'probabilidad_sancion' => 'alta',
                'riesgo_multiplicador' => 'alto',
                'riesgo_judicial' => 'alto',
                'factor_economico' => match ($eventoFiscal) {
                    'ninguno' => 18.0,
                    'inspeccion' => 72.0,
                    'acta' => 96.0,
                    default => 52.0,
                },
                'tiempo' => match ($eventoFiscal) {
                    'acta' => 88.0,
                    'determinacion' => 52.0,
                    default => 30.0,
                },
                'lectura_estrategica' => 'La multa ya dejó de ser una hipótesis abstracta: el frente útil es discutir hechos relevados, base imponible y graduación de sanción.',
                'acciones' => self::buildConditionalActions([
                    'Preparar descargo administrativo con foco en hechos, remuneración base y pluralidad de trabajadores afectados.',
                    'Separar lo laboral, lo previsional y lo fiscal para no convalidar una base de ajuste sobredimensionada.',
                    $variablesJuridicas['riesgo_multiplicador'] !== 'bajo' ? 'Dimensionar el efecto cascada sobre otros dependientes antes de contestar el acta.' : null,
                ]),
            ],
            'contingencia_completa' => [
                'codigo' => 'D',
                'slug' => 'contingencia_completa',
                'aplica' => true,
                'titulo' => 'Escenario D — Contingencia completa',
                'descripcion' => 'El ajuste ya está determinado o prácticamente consolidado; la salida pasa por negociar, regularizar y administrar el frente ejecutivo o penal.',
                'gatillo' => 'Corresponde cuando ya existe determinación de deuda, ejecución inminente o activación penal relevante.',
                'impacto_prueba' => 'alto',
                'probabilidad_sancion' => 'alta',
                'riesgo_multiplicador' => 'alto',
                'riesgo_judicial' => 'alto',
                'factor_economico' => match ($eventoFiscal) {
                    'ninguno' => 12.0,
                    'inspeccion' => 30.0,
                    'acta' => 76.0,
                    default => 97.0,
                },
                'tiempo' => match ($eventoFiscal) {
                    'determinacion' => 94.0,
                    'acta' => 58.0,
                    default => 24.0,
                },
                'lectura_estrategica' => 'A esta altura la prioridad es conservar caja, negociar plan o regularización y contener el frente penal/previsional sin negar el estadio alcanzado.',
                'acciones' => self::buildConditionalActions([
                    'Abrir mesa única laboral-contable-tributaria para negociación, facilidades de pago y estrategia penal.',
                    'Regularizar deuda corriente y evitar nuevas omisiones mientras se discute la contingencia histórica.',
                    $hayFacturacionParalela ? 'Blindar la documentación de pagos ajenos al salario para impedir nuevos ajustes sobre la misma base.' : null,
                ]),
            ],
        ];

        foreach ($catalog as $key => $scenario) {
            $catalog[$key]['score'] = self::scoreScenario($scenario);
            $catalog[$key]['evaluacion'] = self::interpretScenarioScore($catalog[$key]['score']);
            $catalog[$key]['variables'] = [
                'impacto_prueba' => $scenario['impacto_prueba'],
                'probabilidad_sancion' => $scenario['probabilidad_sancion'],
                'riesgo_multiplicador' => $scenario['riesgo_multiplicador'],
                'riesgo_judicial' => $scenario['riesgo_judicial'],
            ];
        }

        return $catalog;
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
        return self::isFlagEnabled($situacion['tiene_facturacion'] ?? ($documentacion['tiene_facturacion'] ?? 'no'))
            || self::isFlagEnabled($situacion['tiene_pago_bancario'] ?? ($documentacion['pago_bancario'] ?? 'no'));
    }

    private static function resolveInspectionState(array $situacion): string
    {
        $estado = trim((string) ($situacion['estado_inspeccion'] ?? ''));
        if (in_array($estado, ['previa', 'iniciada', 'acta_labrada'], true)) {
            return $estado;
        }

        if (($situacion['fue_intimado'] ?? 'no') === 'si') {
            return 'acta_labrada';
        }

        if (($situacion['inspeccion_previa'] ?? 'no') === 'si' || ($situacion['hay_intercambio'] ?? 'no') === 'si') {
            return 'iniciada';
        }

        return 'previa';
    }

    private static function resolveFiscalEvent(array $situacion, string $estadoInspeccion): string
    {
        $evento = trim((string) ($situacion['evento_fiscal'] ?? ''));
        if (in_array($evento, ['ninguno', 'inspeccion', 'acta', 'determinacion'], true)) {
            return $evento;
        }

        if (self::isFlagEnabled($situacion['sentencia_firme'] ?? 'no')) {
            return 'determinacion';
        }

        return match ($estadoInspeccion) {
            'acta_labrada' => 'acta',
            'iniciada' => 'inspeccion',
            default => 'ninguno',
        };
    }

    private static function buildPhaseMatrix(string $eventoFiscal): array
    {
        return match ($eventoFiscal) {
            'inspeccion' => ['deteccion' => true, 'prueba' => true, 'ajuste' => false, 'sancion' => false],
            'acta' => ['deteccion' => true, 'prueba' => true, 'ajuste' => true, 'sancion' => false],
            'determinacion' => ['deteccion' => true, 'prueba' => true, 'ajuste' => true, 'sancion' => true],
            default => ['deteccion' => false, 'prueba' => false, 'ajuste' => false, 'sancion' => false],
        };
    }

    private static function formatInspectionState(string $estadoInspeccion): string
    {
        return match ($estadoInspeccion) {
            'acta_labrada' => 'Acta labrada',
            'iniciada' => 'Iniciada',
            default => 'Previa',
        };
    }

    private static function formatFiscalEvent(string $eventoFiscal): string
    {
        return match ($eventoFiscal) {
            'inspeccion' => 'Inspección',
            'acta' => 'Acta',
            'determinacion' => 'Determinación',
            default => 'Ninguno',
        };
    }

    private static function buildDocumentationModule(
        array $datos,
        array $documentacion,
        array $situacion,
        array $matriz,
        array $matrizProbatoria,
        array $riesgoProbatorio,
        float $probabilidadCondena
    ): array {
        $mesesNoRegistrados = max(0, intval($situacion['meses_no_registrados'] ?? 0));

        return [
            'objetivo' => [
                'Evaluar la capacidad probatoria real del empleador.',
                'Medir el riesgo sancionatorio administrativo y judicial.',
                'Alimentar el cálculo integrado de IRIL y riesgo real.',
            ],
            'bloques' => [
                'prueba_laboral' => [
                    'titulo' => 'Bloque I — Prueba laboral',
                    'nivel' => $matriz['documentacion']['nivel'] ?? 'bajo',
                    'hallazgos' => $matriz['documentacion']['observaciones'] ?? [],
                ],
                'cumplimiento_registral' => [
                    'titulo' => 'Bloque II — Cumplimiento registral',
                    'nivel' => $matriz['registracion']['nivel'] ?? 'bajo',
                    'hallazgos' => $matriz['registracion']['observaciones'] ?? [],
                ],
                'higiene_seguridad' => [
                    'titulo' => 'Bloque III — Higiene y seguridad',
                    'nivel' => $matriz['condiciones']['nivel'] ?? 'bajo',
                    'hallazgos' => $matriz['condiciones']['observaciones'] ?? [],
                ],
                'auditoria_conducta' => [
                    'titulo' => 'Bloque IV — Auditoría y conducta empresarial',
                    'nivel' => $matriz['estructural']['nivel'] ?? 'bajo',
                    'hallazgos' => $matriz['estructural']['observaciones'] ?? [],
                ],
                'simulador_regularizacion' => [
                    'titulo' => 'Bloque V — Simulador de regularización',
                    'meses_sin_registrar' => $mesesNoRegistrados,
                    'aplica_regimen' => self::isFlagEnabled($situacion['aplica_blanco_laboral'] ?? 'no'),
                    'lectura' => $mesesNoRegistrados > 0
                        ? 'La regularización puede bajar sanciones, intereses y deterioro probatorio, aunque no extingue por sí sola la infracción ya verificada.'
                        : 'No se detectó un período pendiente de regularización con la información cargada.',
                ],
            ],
            'riesgo_probatorio' => [
                'nivel' => $riesgoProbatorio['nivel'],
                'score' => $riesgoProbatorio['score'],
            ],
            'probabilidad_condena' => round($probabilidadCondena, 2),
            'matriz_impacto_probatorio' => $matrizProbatoria,
        ];
    }

    private static function buildProbatoryMatrix(array $datos, array $documentacion, array $situacion, array $matriz): array
    {
        $testifical = 3.0;
        if (($situacion['hay_intercambio'] ?? 'no') === 'si' || ($situacion['fue_intimado'] ?? 'no') === 'si') {
            $testifical -= 1.0;
        }
        if (($documentacion['tiene_testigos'] ?? 'no') === 'si' || max(1, intval($datos['cantidad_empleados'] ?? 1)) >= 10) {
            $testifical += 1.0;
        }

        return [
            'documental' => round(max(0.0, 5.0 - floatval($matriz['documentacion']['puntaje'] ?? 0)), 1),
            'registral' => round(max(0.0, 5.0 - floatval($matriz['registracion']['puntaje'] ?? 0)), 1),
            'testifical' => round(min(5.0, max(0.0, $testifical)), 1),
            'tecnica' => round(max(0.0, 5.0 - floatval($matriz['condiciones']['puntaje'] ?? 0)), 1),
        ];
    }

    private static function resolveProbatoryRisk(array $matrizProbatoria): array
    {
        $promedio = array_sum($matrizProbatoria) / max(1, count($matrizProbatoria));
        $riesgo = 5.0 - $promedio;

        return [
            'score' => round($riesgo, 1),
            'nivel' => self::resolveLevel($riesgo),
        ];
    }

    private static function resolveCondemnProbability(
        array $situacion,
        array $documental,
        array $registracion,
        array $estructural,
        array $condiciones
    ): float {
        $base = floatval($situacion['probabilidad_condena'] ?? 0.5) * 0.5;
        $documentalImpacto = (floatval($documental['puntaje'] ?? 0) / 5) * 0.20;
        $registralImpacto = (floatval($registracion['puntaje'] ?? 0) / 5) * 0.15;
        $testificalImpacto = (floatval($estructural['puntaje'] ?? 0) / 5) * 0.10;
        $artImpacto = (($situacion['chk_art_vigente'] ?? 'no') !== 'si' ? 0.05 : 0.0)
            + (floatval($condiciones['puntaje'] ?? 0) / 5) * 0.02;
        $probabilidadCalculada = $base + $documentalImpacto + $registralImpacto + $testificalImpacto + $artImpacto;

        return round(min(0.95, max(0.05, $probabilidadCalculada)), 2);
    }

    private static function resolveAdjustmentProbability(
        string $eventoFiscal,
        array $matriz,
        array $contingencia,
        array $situacion
    ): float {
        $base = match ($eventoFiscal) {
            'inspeccion' => 0.72,
            'acta' => 0.88,
            'determinacion' => 0.97,
            default => 0.35,
        };

        $base += (floatval($matriz['registracion']['puntaje'] ?? 0) / 5) * 0.14;
        $base += (floatval($matriz['documentacion']['puntaje'] ?? 0) / 5) * 0.10;
        $base += max(0, intval($situacion['meses_no_registrados'] ?? 0)) > 0 ? 0.08 : 0.0;
        $base += self::hasOpaqueStructureSignals($situacion) ? 0.08 : 0.0;
        $base += floatval($contingencia['administrativa'] ?? 0) > 0 ? 0.05 : 0.0;

        return round(min(0.99, max(0.05, $base)), 2);
    }

    private static function resolvePenalProbability(string $eventoFiscal, array $situacion, array $contingencia): float
    {
        $base = match ($eventoFiscal) {
            'inspeccion' => 0.15,
            'acta' => 0.25,
            'determinacion' => 0.38,
            default => 0.05,
        };

        $base += self::isFlagEnabled($situacion['falta_f931_art'] ?? 'no') ? 0.20 : 0.0;
        $base += self::isFlagEnabled($situacion['hay_apropiacion_indebida'] ?? 'no') ? 0.20 : 0.0;
        $base += self::isFlagEnabled($situacion['fraude_evasion_sistematica'] ?? 'no') ? 0.15 : 0.0;
        $base += max(0, intval($situacion['meses_en_mora'] ?? 0)) >= 6 ? 0.08 : 0.0;
        $base += floatval($contingencia['administrativa'] ?? 0) >= 3000000 ? 0.05 : 0.0;

        return round(min(0.95, max(0.01, $base)), 2);
    }

    private static function buildContingencyBreakdown(
        array $datos,
        array $situacion,
        array $exposicion,
        float $sancionAdministrativa,
        float $deudaArca
    ): array {
        $multasArca = floatval($exposicion['conceptos']['multas_arca']['monto'] ?? 0);
        $total = floatval($exposicion['total_con_multas'] ?? 0);
        $multasLct = floatval($exposicion['conceptos']['multas_lct']['monto'] ?? 0);
        $administrativa = round(max($sancionAdministrativa, $multasArca), 2);
        $laboral = round(max(0.0, $total - $administrativa), 2);
        $indirecta = round(max(
            floatval($deudaArca),
            floatval($datos['salario'] ?? 0) * max(1, intval($situacion['meses_no_registrados'] ?? 0))
        ), 2);

        return [
            'administrativa' => $administrativa,
            'laboral' => $laboral,
            'multas_lct' => round($multasLct, 2),
            'indirecta' => $indirecta,
        ];
    }

    private static function buildCriticalVariables(
        string $eventoFiscal,
        string $estadoInspeccion,
        array $faseProcedimental,
        array $matriz,
        array $contingencia,
        array $riesgoProbatorio,
        float $probabilidadCondena,
        float $probabilidadAjuste,
        float $probabilidadPenal
    ): array {
        $riesgoMultiplicador = self::resolveMultiplierLevel($contingencia, $matriz);

        return [
            'evento_fiscal' => $eventoFiscal,
            'estado_inspeccion' => $estadoInspeccion,
            'fase' => $faseProcedimental,
            'riesgo_laboral' => [
                'registracion' => floatval($matriz['registracion']['puntaje'] ?? 0),
                'condiciones' => floatval($matriz['condiciones']['puntaje'] ?? 0),
                'remuneracion' => floatval($matriz['remuneracion']['puntaje'] ?? 0),
                'documentacion' => floatval($matriz['documentacion']['puntaje'] ?? 0),
                'estructural' => floatval($matriz['estructural']['puntaje'] ?? 0),
            ],
            'variables_juridicas' => [
                'probabilidad_sancion' => self::resolveLevel(max(
                    floatval($matriz['registracion']['puntaje'] ?? 0),
                    floatval($matriz['documentacion']['puntaje'] ?? 0),
                    floatval($contingencia['administrativa'] ?? 0) > 0 ? 3.0 : 1.0
                )),
                'impacto_prueba' => self::maxRiskLevel(
                    $riesgoProbatorio['nivel'],
                    match ($eventoFiscal) {
                        'inspeccion' => 'alto',
                        'acta', 'determinacion' => 'crítico',
                        default => 'medio',
                    }
                ),
                'riesgo_multiplicador' => $riesgoMultiplicador,
                'riesgo_judicial' => self::resolveLevel(max(($probabilidadCondena * 5), floatval($matriz['estructural']['puntaje'] ?? 0))),
                'probabilidad_ajuste' => round($probabilidadAjuste, 2),
                'probabilidad_penal' => round($probabilidadPenal, 2),
            ],
        ];
    }

    private static function buildOperationalModel(
        array $datos,
        array $situacion,
        string $eventoFiscal,
        array $faseProcedimental,
        array $escenarioOptimo,
        string $riesgo,
        float $probabilidadAjuste,
        float $probabilidadPenal,
        array $recomendacion
    ): array {
        return [
            'input' => [
                'empleados_registrados' => ($datos['tipo_registro'] ?? 'registrado') === 'registrado',
                'monotributistas' => self::isFlagEnabled($situacion['tiene_facturacion'] ?? 'no'),
                'pagos_en_negro' => floatval($datos['salario_recibo'] ?? 0) > 0
                    && floatval($datos['salario'] ?? 0) > floatval($datos['salario_recibo'] ?? 0),
                'cumplimiento_f931' => !self::isFlagEnabled($situacion['falta_f931_art'] ?? 'no'),
            ],
            'proceso' => [
                'detectar_inconsistencia',
                'evaluar_evento_fiscal',
                'estimar_contingencia',
                'definir_escenario',
                'recomendar_estrategia',
            ],
            'fase' => $faseProcedimental,
            'output' => [
                'escenario' => $escenarioOptimo['slug'] ?? 'riesgo_latente',
                'riesgo' => $riesgo,
                'probabilidad_ajuste' => round($probabilidadAjuste, 2),
                'probabilidad_penal' => round($probabilidadPenal, 2),
                'recomendacion' => $recomendacion['key'] ?? 'regularizacion_previa',
                'evento_fiscal' => $eventoFiscal,
            ],
        ];
    }

    private static function resolveMultiplierLevel(array $contingencia, array $matriz): string
    {
        $indirecta = floatval($contingencia['indirecta'] ?? 0);
        $estructural = floatval($matriz['estructural']['puntaje'] ?? 0);

        return match (true) {
            $indirecta >= 10000000 || $estructural >= 4.0 => 'alto',
            $indirecta >= 3000000 || $estructural >= 2.5 => 'medio',
            default => 'bajo',
        };
    }

    private static function maxRiskLevel(string $first, string $second): string
    {
        return self::riskLevelWeight($first) >= self::riskLevelWeight($second) ? $first : $second;
    }

    private static function riskLevelWeight(string $level): int
    {
        return match ($level) {
            'bajo' => 1,
            'medio' => 2,
            'alto' => 3,
            'crítico' => 4,
            default => 2,
        };
    }

    private static function scoreScenario(array $scenario): float
    {
        $riesgoJuridico = self::levelToScenarioValue($scenario['riesgo_judicial'] ?? 'medio');
        $probabilidadSancion = self::levelToScenarioValue($scenario['probabilidad_sancion'] ?? 'media');
        $riesgoMultiplicador = self::levelToScenarioValue($scenario['riesgo_multiplicador'] ?? 'medio');
        $factorEconomico = floatval($scenario['factor_economico'] ?? 0);
        $tiempo = floatval($scenario['tiempo'] ?? 0);
        $score =
            ($factorEconomico * self::WEIGHT_ECONOMIC)
            + ($riesgoJuridico * self::WEIGHT_JURIDICAL)
            + ($probabilidadSancion * self::WEIGHT_SANCTION)
            + ($riesgoMultiplicador * self::WEIGHT_MULTIPLIER)
            + ($tiempo * self::WEIGHT_TIME);

        return round($score, 1);
    }

    private static function levelToScenarioValue(string $level): float
    {
        return match (strtolower($level)) {
            'bajo', 'baja' => 90.0,
            'medio', 'media' => 70.0,
            'medio-alto' => 55.0,
            'alto', 'alta' => 30.0,
            default => 60.0,
        };
    }

    private static function interpretScenarioScore(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Óptimo',
            $score >= 60 => 'Bueno',
            $score >= 40 => 'Riesgoso',
            default => 'Crítico',
        };
    }

    private static function selectOptimalScenario(array $escenarios): array
    {
        $selected = null;

        foreach ($escenarios as $key => $scenario) {
            if (!is_array($scenario) || empty($scenario['aplica'])) {
                continue;
            }

            if ($selected === null || floatval($scenario['score'] ?? 0) > floatval($selected['score'] ?? 0)) {
                $scenario['key'] = $key;
                $selected = $scenario;
            }
        }

        if ($selected === null) {
            return [
                'key' => 'riesgo_latente',
                'codigo' => 'A',
                'slug' => 'riesgo_latente',
                'titulo' => 'Escenario A — Situación invisible (riesgo latente)',
                'score' => 0.0,
                'evaluacion' => 'Crítico',
            ];
        }

        return $selected;
    }

    private static function recommendationFromScenario(?string $scenarioKey, string $infraccion, array $situacion): array
    {
        return match ($scenarioKey) {
            'riesgo_latente' => [
                'key' => 'regularizacion_previa',
                'label' => 'Regularización previa',
            ],
            'inspeccion_en_curso' => [
                'key' => 'control_daño_y_prueba_inmediata',
                'label' => 'Control de daño y prueba inmediata',
            ],
            'acta_labrada' => [
                'key' => 'defensa_administrativa_y_discusion_de_base',
                'label' => 'Defensa administrativa y discusión de base',
            ],
            'contingencia_completa' => [
                'key' => 'negociacion_y_regularizacion',
                'label' => 'Negociación y regularización',
            ],
            default => self::resolveRecommendation($infraccion, $situacion),
        };
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
            if (self::isFlagEnabled($situacion[$flag] ?? 'no')) {
                return true;
            }
        }

        return false;
    }

    private static function isFlagEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['si', 'sí', 'true', '1', 'yes', 'on'], true);
    }

    private static function buildConditionalActions(array $actions): array
    {
        return array_values(array_filter($actions));
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
