<?php
/**
 * ExposicionEngine.php — Motor de cálculo de la Exposición Económica
 *
 * Extraído de IrilEngine para mantener la responsabilidad única,
 * conservando estrictamente toda la lógica y parches legales (Marzo 2026).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ripte_functions.php';

class ExposicionEngine
{
    /**
     * calcularExposicion() — Estima el impacto económico estructural del conflicto
     * según la Ley de Contrato de Trabajo (LCT) argentina.
     *
     * NO garantiza montos. Son estimaciones estructurales bajo LCT.
     *
     * @param array  $datosLaborales  Salario, antigüedad en meses, tipo de conflicto
     * @param array  $documentacion   Para determinar si aplican multas
     * @param string $tipoConflicto
     * @param string $tipoUsuario
     * @return array Conceptos con montos estimados y total
     */
    public function calcularExposicion(
        array $datosLaborales,
        array $documentacion,
        array $situacion,
        string $tipoConflicto,
        string $tipoUsuario
    ): array {
        // Cargar parámetros estratégicos
        $params = require __DIR__ . '/parametros_motor.php';
        $ce = $params['calculos_especificos'];

        $salario = floatval($datosLaborales['salario'] ?? 0);
        $antiguedadM = intval($datosLaborales['antiguedad_meses'] ?? 0);
        $antiguedadA = $antiguedadM / 12;

        // Mes actual para SAC proporcional
        $mesActual = intval(date('n'));

        $conceptos = [];

        // ─────────────────────────────────────────────────────────────────────
        // 1. CASOS DE DESVINCULACIÓN (LCT pura)
        // ─────────────────────────────────────────────────────────────────────
        $conflictosDespido = ['despido_sin_causa', 'despido_con_causa', 'trabajo_no_registrado', 'reclamo_indemnizatorio'];

        if (in_array($tipoConflicto, $conflictosDespido)) {
            // Indemnización por antigüedad (Art. 245 LCT)
            $anosCompletos = max(1, (int) floor($antiguedadA));
            $indemnizacion = $salario * $anosCompletos;
            $conceptos['indemnizacion_antiguedad'] = [
                'descripcion' => "Indemnización por antigüedad (Art. 245) — {$anosCompletos} años",
                'monto' => round($indemnizacion, 2),
                'base_legal' => 'Art. 245 LCT'
            ];

            // Preaviso (Art. 231 LCT)
            $mesesPreavis = $antiguedadA >= 5 ? 2 : 1;
            $preaviso = $salario * $mesesPreavis;
            $conceptos['preaviso'] = [
                'descripcion' => "Falta de Preaviso ({$mesesPreavis} meses)",
                'monto' => round($preaviso, 2),
                'base_legal' => 'Art. 231 LCT'
            ];

            // Integración mes de despido (Art. 233 LCT)
            $integracion = ($salario / 30) * 15;
            $conceptos['integracion_mes'] = [
                'descripcion' => 'Integración mes de despido (estimado)',
                'monto' => round($integracion, 2),
                'base_legal' => 'Art. 233 LCT'
            ];
        }

        // ─────────────────────────────────────────────────────────────────────
        // 2. CONCEPTOS ADICIONALES Y MULTAS (Según caso)
        // ─────────────────────────────────────────────────────────────────────

        // SAC y Vacaciones proporcionales (Aplican a casi todo conflicto activo de desvinculación o duda)
        if (in_array($tipoConflicto, array_merge($conflictosDespido, ['diferencias_salariales']))) {
            $mesesSemestre = ($mesActual <= 6) ? $mesActual : $mesActual - 6;
            $sac = ($salario / 2) * ($mesesSemestre / 6);
            $conceptos['sac_proporcional'] = [
                'descripcion' => 'SAC proporcional del semestre',
                'monto' => round($sac, 2),
                'base_legal' => 'Art. 123 LCT'
            ];

            $diasVac = 14;
            if ($antiguedadA >= 5)
                $diasVac = 21;
            if ($antiguedadA >= 10)
                $diasVac = 28;
            $diasProp = round($diasVac * ($mesesSemestre / 12));
            $vacaciones = ($salario / 25) * $diasProp;
            $conceptos['vacaciones_proporcionales'] = [
                'descripcion' => "Vacaciones proporcionales ({$diasProp} días)",
                'monto' => round($vacaciones, 2),
                'base_legal' => 'Art. 150 LCT'
            ];
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. LÓGICA ESPECÍFICA POR TIPO DE CONFLICTO (No Despido)
        // ─────────────────────────────────────────────────────────────────────

        // A. ACCIDENTE LABORAL
        if ($tipoConflicto === 'accidente_laboral') {
            $edad = intval($datosLaborales['edad'] ?? 0);
            if ($edad < 16) $edad = $ce['accidentes']['edad_fallback'];

            $incapacidad = floatval($situacion['porcentaje_incapacidad'] ?? 0);
            $tieneArt = ($situacion['tiene_art'] ?? 'no') === 'si';
            $p = $ce['accidentes'];

            if ($incapacidad > 0 && $tieneArt) {
                // ═══ VÍA ART: Fórmula LRT (Art. 14.2.a Ley 24.557) ═══
                // Fórmula: 53 × IBM × (65 / Edad) × (%Incapacidad / 100)
                $ibm = $salario; // Simplificación: IBM ≈ salario declarado
                $montoLRT = $p['coeficiente_lrt'] * $ibm * ($p['factor_edad_limite'] / $edad) * ($incapacidad / 100);

                // Aplicar pisos mínimos RIPTE según tipo de incapacidad
                $tipoIncap = $situacion['incapacidad_tipo'] ?? 'permanente_definitiva';
                $pisoAplicado = '';
                if ($tipoIncap === 'gran_invalidez' || $incapacidad >= $p['umbral_gran_invalidez']) {
                    $montoLRT = max($montoLRT, $p['piso_gran_invalidez']);
                    $pisoAplicado = 'Piso gran invalidez RIPTE';
                } elseif ($tipoIncap === 'muerte') {
                    $montoLRT = max($montoLRT, $p['piso_muerte']);
                    $pisoAplicado = 'Piso muerte RIPTE';
                } elseif ($tipoIncap === 'permanente_definitiva') {
                    $montoLRT = max($montoLRT, $p['piso_minimo_ipd']);
                    $pisoAplicado = 'Piso IPD RIPTE';
                } else {
                    $montoLRT = max($montoLRT, $p['piso_minimo_ipp']);
                    $pisoAplicado = 'Piso IPP RIPTE';
                }

                // Preexistencias (Balthazard simplificado)
                $incapFinal = $incapacidad;
                if (($situacion['tiene_preexistencia'] ?? 'no') === 'si') {
                    $preex = floatval($situacion['preexistencia_porcentaje'] ?? 0);
                    if ($preex > 0 && $preex < 100) {
                        // Incapacidad atribuible = total - preexistencia (Balthazard)
                        $incapFinal = (1 - (1 - $incapacidad / 100) / (1 - $preex / 100)) * 100;
                        $incapFinal = max(0, $incapFinal);
                        $montoLRT = $p['coeficiente_lrt'] * $ibm * ($p['factor_edad_limite'] / $edad) * ($incapFinal / 100);
                    }
                }

                $notaLRT = "Fórmula LRT: 53 x IBM x (65/{$edad}) x {$incapFinal}%.";
                if (!empty($pisoAplicado)) $notaLRT .= " {$pisoAplicado} aplicado (vigencia: {$p['fecha_pisos_ripte']}).";

                $conceptos['prestacion_art_tarifa'] = [
                    'descripcion' => "Prestación dineraria ART — Tarifa Ley 24.557 ({$incapFinal}% incap.)",
                    'monto' => round($montoLRT, 2),
                    'base_legal' => 'Art. 14.2.a Ley 24.557 + Ley 26.773 (pisos RIPTE)',
                    'nota' => $notaLRT
                ];

                // ═══ VÍA CIVIL: Fórmula Méndez (estimación comparativa) ═══
                $montoCivil = ($salario * $p['meses_año'] * ($incapacidad / 100) * $p['factor_edad_limite']) / $edad;

                $conceptos['estimacion_civil_mendez'] = [
                    'descripcion' => "Estimación acción civil complementaria (Méndez/Vuotto)",
                    'monto' => round($montoCivil, 2),
                    'base_legal' => 'Art. 4 Ley 26.773 — Opción excluyente',
                    'nota' => "Fórmula Méndez: (Salario x 13 x {$incapacidad}% x 65) / {$edad}. ADVERTENCIA: Optar por vía civil excluye cobro de tarifa ART."
                ];

                // Adicional gran invalidez
                if ($tipoIncap === 'gran_invalidez' || $incapacidad >= $p['umbral_gran_invalidez']) {
                    $conceptos['adicional_gran_invalidez'] = [
                        'descripcion' => 'Prestación adicional por gran invalidez (Art. 17 Ley 24.557)',
                        'monto' => round($p['piso_gran_invalidez'], 2),
                        'base_legal' => 'Art. 17 Ley 24.557',
                        'nota' => 'Prestación de pago mensual adicional equivalente a 3 MOPRE para asistencia.'
                    ];
                }

            } elseif ($incapacidad > 0 && !$tieneArt) {
                // ═══ SIN ART: Mantener lógica original (acción civil directa) ═══
                $expoAccidente = ($salario * $p['meses_año'] * ($incapacidad / 100) * $p['factor_edad_limite']) / $edad;
                $conceptos['exposicion_accidente'] = [
                    'descripcion' => 'Riesgo estructural por incapacidad/daño',
                    'monto' => round($expoAccidente, 2),
                    'base_legal' => 'Ley 24.557 (LRT) - Fórmulas Mendez/Vuotto',
                    'nota' => "Basado en fórmula judicial estimada por {$incapacidad}% de incapacidad y edad del trabajador ({$edad} años)."
                ];
                $recargo = $p['recargo_falta_art'];
                $conceptos['riesgo_falta_art'] = [
                    'descripcion' => 'Exposición directa por falta de cobertura ART',
                    'monto' => round($expoAccidente * $recargo, 2),
                    'base_legal' => 'Responsabilidad Civil / Art. 28 LRT',
                    'nota' => 'El empleador responde con su patrimonio personal al no tener seguro activo.'
                ];
            } else {
                // Sin datos de incapacidad — estimación estructural genérica
                $expoAccidente = $salario * 45;
                $conceptos['exposicion_accidente'] = [
                    'descripcion' => 'Riesgo estructural por incapacidad/daño',
                    'monto' => round($expoAccidente, 2),
                    'base_legal' => 'Ley 24.557 (LRT) - Estimación estructural',
                    'nota' => 'Basado en estimación de riesgo estructural por daño (45 sueldos promedio) ante falta de pericia médica.'
                ];
                if (!$tieneArt) {
                    $conceptos['riesgo_falta_art'] = [
                        'descripcion' => 'Exposición directa por falta de cobertura ART',
                        'monto' => round($expoAccidente * $p['recargo_falta_art'], 2),
                        'base_legal' => 'Responsabilidad Civil / Art. 28 LRT',
                        'nota' => 'El empleador responde con su patrimonio personal al no tener seguro activo.'
                    ];
                }
            }
        }

        // B. DIFERENCIAS SALARIALES (Deuda histórica personalizada)
        if ($tipoConflicto === 'diferencias_salariales') {
            $mesesDeuda = intval($situacion['meses_adeudados'] ?? 0);
            if ($mesesDeuda <= 0)
                $mesesDeuda = 12; // Fallback razonable

            $motivo = $situacion['motivo_diferencia'] ?? 'mala_categorizacion';

            // Ponderación según motivo configurado
            $p = $ce['diferencias_salariales'];
            $factorDiferencia = $p['factor_mala_categorizacion'];
            if ($motivo === 'horas_extras')
                $factorDiferencia = $p['factor_horas_extras'];
            if ($motivo === 'falta_pago')
                $factorDiferencia = $p['factor_falta_pago'];

            $deudaEstimada = ($salario * $factorDiferencia) * $mesesDeuda;

            $conceptos['deuda_salarial_historica'] = [
                'descripcion' => "Deuda estimada por diferencias ({$mesesDeuda} meses acumulados)",
                'monto' => round($deudaEstimada, 2),
                'base_legal' => 'Convenio Colectivo Aplicable / Art. 260 LCT',
                'nota' => "Estimación basada en {$mesesDeuda} meses de desviación por " . str_replace('_', ' ', $motivo) . "."
            ];
        }

        // C. MULTAS ADMINISTRATIVAS / INSPECCIÓN
        if (in_array($tipoConflicto, ['multas_legales', 'riesgo_inspeccion'])) {
            $esReincidente = ($situacion['inspeccion_previa'] ?? 'no') === 'si';
            $p = $ce['prevencion'];
            $baseMulta = $esReincidente ? $p['multa_reincidencia_arca'] : $p['multa_base_inspeccion'];

            $multaAdmin = $salario * $baseMulta;
            $conceptos['sancion_administrativa'] = [
                'descripcion' => 'Riesgo de sanción administrativa (Multa Ministerio/ARCA)',
                'monto' => round($multaAdmin, 2),
                'base_legal' => 'Ley 25.212 (Pacto Federal del Trabajo)',
                'nota' => $esReincidente
                    ? 'Riesgo elevado por antecedentes de inspección (Agravante por reincidencia).'
                    : 'Las sanciones varían según la gravedad (Leve, Grave, Muy Grave).'
            ];
        }

        // D. RESPONSABILIDAD SOLIDARIA (Art. 30 LCT — Ley 27.802)
        // Nuevo régimen: Principal se EXIME si controla explícitamente 5 elementos
        if ($tipoConflicto === 'responsabilidad_solidaria') {
            $cantidadContratistas = intval($situacion['cantidad_subcontratistas'] ?? 1);
            if ($cantidadContratistas <= 0)
                $cantidadContratistas = 1;

            // ANÁLISIS Art. 30: Verificación de los 5 controles
            // 1. CUIL de trabajadores validado
            // 2. Aportes a seguridad social actualizados
            // 3. Pago de salarios realizado directamente (no efectivo)
            // 4. Cuenta bancaria del trabajador (CBU vinculado)
            // 5. Cobertura ART con endoso a favor del principal
            
            // Conteo de controles presentes (cada uno reduce riesgo)
            $controlesPresentes = 0;
            if (($situacion['principal_valida_cuil'] ?? 'no') === 'si') $controlesPresentes++;
            if (($situacion['principal_verifica_aaportes'] ?? 'no') === 'si') $controlesPresentes++;
            if (($situacion['principal_paga_directo'] ?? 'no') === 'si') $controlesPresentes++;
            if (($situacion['principal_valida_cbu_trabajador'] ?? 'no') === 'si') $controlesPresentes++;
            if (($situacion['principal_cubre_art'] ?? 'no') === 'si') $controlesPresentes++;

            // Factor de riesgo: Si cumple 5 controles, riesgo mínimo
            $factorExención = max(1, 6 - $controlesPresentes);  // De 6 (sin controles) a 1 (con todos)
            $riesgoSolidario = ($salario * $factorExención) * $cantidadContratistas;

            $conceptos['responsabilidad_terceros'] = [
                'descripcion' => "Riesgo solidaria Art. 30 LCT ({$cantidadContratistas} contratistas, {$controlesPresentes}/5 controles)",
                'monto' => round($riesgoSolidario, 2),
                'base_legal' => 'Art. 30 LCT (Ley 27.802)',
                'nota' => "Principal controlando {$controlesPresentes} de 5 elementos clave. " . 
                          ($controlesPresentes >= 5 ? "EXENTO de solidaridad (todos los controles presentes)." : "Sin exención (faltan " . (5-$controlesPresentes) . " controles).")
            ];
        }

        // E. AUDITORÍA PREVENTIVA (Cálculo de contingencia latente)
        if ($tipoConflicto === 'auditoria_preventiva') {
            $nivel = $situacion['nivel_cumplimiento'] ?? 'desconocido';
            $p = $ce['prevencion'];
            $factorRiesgo = $p['riesgo_auditoria_estable'];

            if ($nivel === 'critico')
                $factorRiesgo = $p['riesgo_auditoria_critico'];
            if ($nivel === 'desconocido')
                $factorRiesgo = $p['riesgo_auditoria_desconocido'];

            $contingenciaLatente = $salario * $factorRiesgo;

            $conceptos['contingencia_latente'] = [
                'descripcion' => 'Contingencia económica latente (Diagnóstico)',
                'monto' => round($contingenciaLatente, 2),
                'base_legal' => 'Análisis Preventivo / Auditoría',
                'nota' => "Basado en nivel de cumplimiento {$nivel}. Representa la masa salarial en riesgo por incumplimientos normativos."
            ];
        }

        // ─────────────────────────────────────────────────────────────────────
        // 4. MULTAS LEGALES (ART. 80, 24.013, 25.323)
        // ─────────────────────────────────────────────────────────────────────

        // Solo si no es accidente puro o auditoria preventiva
        $casosSinMultas = ['auditoria_preventiva'];
        if (!in_array($tipoConflicto, $casosSinMultas)) {
            // Multa Art. 80 LCT (SÍ APLICA siempre — no fue derogada por Ley Bases)
            $conceptos['multa_art80_lct'] = [
                'descripcion' => 'Certificados de Trabajo (Art. 80)',
                'monto' => round($salario * 3, 2),
                'base_legal' => 'Art. 80 LCT',
                'nota_ley_bases' => 'No fue derogada por Ley Bases 27.742'
            ];

            // ─ VALIDACIÓN ÚNICA DE LEY BASES 27.742 PARA MULTAS 24.013 Y 25.323 ─
            // NOTA NORMATIVA (Ley 27.802 — Marzo 2026):
            // La Ley 25.323 fue DEROGADA COMPLETAMENTE
            // La Ley 24.013 arts. 8/9/10/11 fueron DEROGADOS (PARCIAL)
            // No se utilizan como cálculo activo en este motor
            // Se mantiene referencia histórica para litigios pre-reforma únicamente
            
            $validacionLeyBases = ['aplica_multas' => false];  // DEFAULT: multas NO aplican (post-Ley 27.742)
            
            if (!empty($situacion['fecha_despido'])) {
                try {
                    $fechaDespido = new DateTime($situacion['fecha_despido']);
                    $checkInconst = $situacion['check_inconstitucionalidad'] ?? false;
                    $validacionLeyBases = validar_ley_bases($fechaDespido, $checkInconst);
                } catch (Exception $e) {
                    // Si hay error de fecha, asumir default: multas NO aplican
                    $validacionLeyBases = ['aplica_multas' => false];
                }
            }

            // ── ESTADO NORMATIVO POST-LEY 27.802 (MARZO 2026) ──
            // Leyes 24.013 arts. 8/9/10 y 25.323 están DEROGADAS
            // Se generan alertas únicamente para referencia histórica en hechos previos a reforma
            
            $tipoReg = $datosLaborales['tipo_registro'] ?? 'registrado';

            // ALERT: Si hay trabajo no registrado, GENERAR ALERTA de derogación
            if ($tipoReg === 'no_registrado' || $tipoConflicto === 'trabajo_no_registrado') {
                $conceptos['alerta_derogacion_multas_historicas'] = [
                    'descripcion' => 'Trabajo no registrado — Régimen de multas DEROGADO',
                    'monto' => 0.00,
                    'base_legal' => 'Ley 27.802 (2026)',
                    'nota' => 'Las multas de Ley 24.013 (arts. 8/9/10) y Ley 25.323 fueron derogadas. ' .
                              'El motor evalúa FRAUDE LABORAL y DAÑOS COMPLEMENTARIOS en su lugar. ' .
                              'Para hechos previos a la reforma, consultar derecho transitorio.'
                ];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // 5. CÁLCULO DE TOTALES  
        // ─────────────────────────────────────────────────────────────────────
        $totalBase = 0;
        $totalConMultas = 0;
        foreach ($conceptos as $key => $concepto) {
            $totalConMultas += $concepto['monto'];
            if (strpos($key, 'multa') === false && strpos($key, 'sancion') === false) {
                $totalBase += $concepto['monto'];
            }
        }

        return [
            'conceptos' => $conceptos,
            'total_base' => round($totalBase, 2),
            'total_con_multas' => round($totalConMultas, 2),
            'total' => round($totalConMultas, 2),
            'salario_base' => $salario,
            'antiguedad_anos' => round($antiguedadA, 1),
            'nota_legal' => 'Estimación estructural bajo LCT. No garantiza resultado. Sujeto a variables procesales y negociación.'
        ];
    }
}
