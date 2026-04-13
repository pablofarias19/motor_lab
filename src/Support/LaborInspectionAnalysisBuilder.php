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
                'matriz_riesgo' => $matriz,
                'tipificacion' => [
                    'infraccion' => $infraccion,
                    'fundamento' => self::describeInfraccion($infraccion),
                ],
                'cuantificacion' => [
                    'multas_administrativas_estimadas' => round($sancionAdministrativa, 2),
                    'riesgo_economico_indirecto' => round($contingenciaIndirecta, 2),
                    'efecto_multiplicador' => self::buildEffectMultiplier($datos, $situacion),
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
            $puntaje += 0.5;
            $observaciones[] = 'No se informó categoría/CCT suficiente para validar encuadre convencional.';
        }
        if (!$checks['jornada_real_declarada']) {
            $puntaje += 1.0;
            $observaciones[] = 'La fecha o modalidad registral declarada exige revisión específica.';
        }
        if (intval($situacion['meses_no_registrados'] ?? 0) > 0) {
            $puntaje += min(1.5, intval($situacion['meses_no_registrados']) / 24);
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
            $puntaje += 0.5;
            $observaciones[] = 'No hay dato afirmativo de pagos bancarizados para neutralizar observación inspectiva.';
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
            $puntaje += 1.5;
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
            $puntaje += 1.0;
            $observaciones[] = 'Ya existe conflictividad formal o intimación previa.';
        }
        if ($checks['indicios_fraude']) {
            $puntaje += 1.5;
            $observaciones[] = 'Se detectó un indicador de evasión sistemática.';
        }
        if ($cantidad >= 20) {
            $puntaje += 1.0;
            $observaciones[] = 'La dotación declarada amplifica el impacto institucional de una inspección.';
        } elseif ($cantidad >= 5) {
            $puntaje += 0.5;
            $observaciones[] = 'La cantidad de trabajadores incrementa el efecto multiplicador del caso.';
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
        return [
            'regularizacion_inmediata' => [
                'aplica' => $recommendation === 'regularizacion_inmediata',
                'descripcion' => 'Corrección de registración, adecuación salarial y baja de contingencia administrativa.',
            ],
            'inspeccion_en_curso' => [
                'aplica' => ($situacion['inspeccion_previa'] ?? 'no') === 'si',
                'descripcion' => 'Puede existir acta previa o reiteración inspectiva con multa potencial.',
            ],
            'defensa_administrativa' => [
                'aplica' => ($situacion['hay_intercambio'] ?? 'no') === 'si' || ($situacion['fue_intimado'] ?? 'no') === 'si',
                'descripcion' => 'Conviene preparar descargo, prueba documental y estrategia de reducción de sanción.',
            ],
            'estrategia_preventiva' => [
                'aplica' => $recommendation === 'auditoria_preventiva',
                'descripcion' => 'Auditoría interna y ordenamiento de compliance laboral antes de una visita inspectiva.',
            ],
        ];
    }

    private static function buildEffectMultiplier(array $datos, array $situacion): string
    {
        $cantidad = max(1, intval($datos['cantidad_empleados'] ?? 1));
        $factores = [];

        if ($cantidad > 1) {
            $factores[] = 'potencial réplica a otros ' . ($cantidad - 1) . ' trabajadores';
        }
        if (($situacion['inspeccion_previa'] ?? 'no') === 'si') {
            $factores[] = 'reiteración inspectiva';
        }
        if (($situacion['fraude_evasion_sistematica'] ?? 'no') === 'si') {
            $factores[] = 'riesgo de denuncia por evasión sistemática';
        }

        return empty($factores) ? 'impacto acotado con la información disponible' : implode(', ', $factores);
    }

    private static function buildConclusion(string $infraccion, array $observaciones, array $situacion): string
    {
        $base = match ($infraccion) {
            'muy_grave' => 'El caso exhibe desvíos con aptitud sancionatoria severa y exige regularización prioritaria.',
            'grave' => 'Se observan incumplimientos materiales que elevan el riesgo de acta y multa administrativa.',
            default => 'Predominan observaciones formales o parciales, con margen para corrección preventiva.',
        };

        if (($situacion['inspeccion_previa'] ?? 'no') === 'si') {
            $base .= ' El antecedente inspectivo aumenta la exposición por reincidencia.';
        }

        if (!empty($observaciones)) {
            $base .= ' Hallazgos principales: ' . implode('; ', array_slice($observaciones, 0, 3)) . '.';
        }

        return $base;
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
            $promedio >= 3.5 => 'critico',
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
