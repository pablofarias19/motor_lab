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
    private const CIVIL_PROBABLE_THRESHOLD_MULTIPLIER = 1.35;
    private const CIVIL_AGGRESSIVE_THRESHOLD_MULTIPLIER = 1.75;
    private const MIN_APERTURA_CIVIL_FOR_RECOMMENDATION = 0.58;
    /**
     * Tasa pura anual de referencia para la proyección civil integral cuando
     * el motor no discrimina una tasa jurisdiccional específica.
     * Se mantiene en 6% anual como tasa pura orientativa ya documentada
     * en la estimación civil vigente del motor.
     */
    private const TASA_CIVIL_REFERENCIA_ANUAL = 0.06;
    /**
     * Piso funcional de daño moral/extrapatrimonial que la documentación
     * vigente del motor aplica a la vía civil.
     */
    private const DANIO_MORAL_CIVIL_PORCENTAJE = 0.20;

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
        $modeloDominio = [];
        $alertasCriticas = [];

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
            $provincia = (string) ($datosLaborales['provincia'] ?? 'CABA');
            $perfilJurisdiccional = $this->resolvePerfilJurisdiccional($provincia, $p);
            $alertasCriticas = $this->buildAlertasCriticasAccidente($documentacion, $situacion, $tieneArt);

            if ($incapacidad > 0 && $tieneArt) {
                $motorArt = new \App\Engines\ArtCalculationEngine();
                $analisisArt = $motorArt->calcular($datosLaborales, $situacion, $p);
                $montoLRT = floatval($analisisArt['monto_final'] ?? 0);
                $incapFinal = floatval($analisisArt['incapacidad_usada'] ?? $incapacidad);
                $tipoIncap = $situacion['incapacidad_tipo'] ?? 'permanente_definitiva';

                $notaLRT = "Fórmula LRT: {$analisisArt['formula_legal']} = " . ml_formato_moneda(floatval($analisisArt['monto_formula'] ?? 0)) . ".";
                $notaLRT .= " IBM/VIB: " . ml_formato_moneda(floatval($analisisArt['ibm'] ?? $salario)) . ".";
                if (!empty($analisisArt['fecha_siniestro'])) {
                    $notaLRT .= " PMI/siniestro: {$analisisArt['fecha_siniestro']}.";
                }
                if (!empty($analisisArt['piso_descripcion'])) {
                    $notaLRT .= " {$analisisArt['piso_descripcion']}" . (($analisisArt['piso_aplicado'] ?? false) ? ' aplicado' : ' controlado') . ".";
                }
                if (!empty($analisisArt['calculo_estimado'])) {
                    $notaLRT .= " Cálculo estimado por falta de serie RIPTE completa; se usó salario base como fallback.";
                }

                $conceptos['prestacion_art_tarifa'] = [
                    'descripcion' => "Prestación dineraria ART — Tarifa Ley 24.557 ({$incapFinal}% incap.)",
                    'monto' => round($montoLRT, 2),
                    'base_legal' => 'Art. 14.2.a Ley 24.557 + Ley 26.773 (pisos RIPTE)',
                    'nota' => $notaLRT,
                    'detalle_calculo' => $analisisArt,
                ];

                $analisisCivil = $this->buildCivilScenarios(
                    $salario,
                    $edad,
                    $incapacidad,
                    $montoLRT,
                    $p,
                    $perfilJurisdiccional,
                    true
                );
                $montoCivil = floatval($analisisCivil['escenarios']['probable']['monto_total'] ?? $montoLRT);

                $conceptos['estimacion_civil_mendez'] = [
                    'descripcion' => "Estimación acción civil integral — escenario probable",
                    'monto' => round($montoCivil, 2),
                    'base_legal' => 'Art. 4 Ley 26.773 — Opción excluyente + criterio de reparación integral',
                    'nota' => (string) ($analisisCivil['escenarios']['probable']['nota'] ?? '')
                ];

                // Adicional gran invalidez
                if (($analisisArt['adicional_gran_invalidez'] ?? 0) > 0) {
                    $conceptos['adicional_gran_invalidez'] = [
                        'descripcion' => 'Prestación adicional por gran invalidez (Art. 17 Ley 24.557)',
                        'monto' => round(floatval($analisisArt['adicional_gran_invalidez'] ?? $p['piso_gran_invalidez']), 2),
                        'base_legal' => 'Art. 17 Ley 24.557',
                        'nota' => 'Prestación de pago mensual adicional equivalente a 3 MOPRE para asistencia.'
                    ];
                }

                $conceptos['indicadores_art'] = [
                    'descripcion' => 'Indicadores del cálculo ART',
                    'monto' => 0.0,
                    'base_legal' => 'Trazabilidad interna del motor',
                    'nota' => sprintf(
                        'Fuente RIPTE: %s. Cálculo %s. Tratamiento: %s. %s',
                        $analisisArt['fuente_ripte'] ?? 'no_disponible',
                        !empty($analisisArt['calculo_estimado']) ? 'estimado' : 'completo',
                        $analisisArt['tratamiento'] ?? 'pago_unico',
                        !empty($analisisArt['necesita_comision_medica']) ? 'Falta dictamen firme de Comisión Médica.' : 'Dictamen/porcentaje consolidado.'
                    ),
                    'detalle_calculo' => $analisisArt,
                ];

                $modeloDominio = $this->buildAccidentDomainModel(
                    true,
                    $montoLRT,
                    $analisisArt,
                    $analisisCivil,
                    $perfilJurisdiccional,
                    $alertasCriticas,
                    $situacion
                );

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

                $analisisCivil = $this->buildCivilScenarios(
                    $salario,
                    $edad,
                    $incapacidad,
                    0.0,
                    $p,
                    $perfilJurisdiccional,
                    false,
                    $recargo
                );

                $modeloDominio = $this->buildAccidentDomainModel(
                    false,
                    0.0,
                    [],
                    $analisisCivil,
                    $perfilJurisdiccional,
                    $alertasCriticas,
                    $situacion
                );
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

                $analisisCivil = $this->buildCivilScenarios(
                    $salario,
                    $edad,
                    $incapacidad,
                    0.0,
                    $p,
                    $perfilJurisdiccional,
                    $tieneArt
                );

                $modeloDominio = $this->buildAccidentDomainModel(
                    $tieneArt,
                    0.0,
                    [],
                    $analisisCivil,
                    $perfilJurisdiccional,
                    $alertasCriticas,
                    $situacion
                );
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
            if ((($situacion['principal_verifica_aportes'] ?? ($situacion['principal_verifica_aaportes'] ?? 'no'))) === 'si') $controlesPresentes++;
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

        // Solo si no es accidente puro ni auditoría preventiva
        $casosSinMultas = ['auditoria_preventiva', 'accidente_laboral'];
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

        if ($tipoConflicto === 'accidente_laboral' && $modeloDominio !== []) {
            $resumenAccidente = $this->buildLegacyTotalsFromAccidentModel($modeloDominio);
            $totalBase = $resumenAccidente['total_base'];
            $totalConMultas = $resumenAccidente['total_con_multas'];
        }

        $resultado = [
            'conceptos' => $conceptos,
            'total_base' => round($totalBase, 2),
            'total_con_multas' => round($totalConMultas, 2),
            'total' => round($totalConMultas, 2),
            'salario_base' => $salario,
            'antiguedad_anos' => round($antiguedadA, 1),
            'nota_legal' => $tipoConflicto === 'accidente_laboral' && $modeloDominio !== []
                ? 'Modelo de decisión por accidente laboral con vías ART/civil excluyentes. Los montos se comparan por escenario; no se acumulan.'
                : 'Estimación estructural bajo LCT. No garantiza resultado. Sujeto a variables procesales y negociación.'
        ];

        if ($modeloDominio !== []) {
            $resultado += $modeloDominio;
        }

        if ($alertasCriticas !== []) {
            $resultado['alertas_criticas'] = $alertasCriticas;
        }

        return $resultado;
    }

    private function resolvePerfilJurisdiccional(string $provincia, array $accidentesConfig): array
    {
        $perfiles = $accidentesConfig['perfiles_jurisdiccionales'] ?? [];
        $perfil = $perfiles[$provincia] ?? ($perfiles['default'] ?? []);

        return [
            'jurisdiccion' => $provincia,
            'tendencia_danio_moral' => (string) ($perfil['tendencia_danio_moral'] ?? 'media'),
            'danio_moral_factor' => floatval($perfil['danio_moral_factor'] ?? 1.0),
            'interes_anual_base' => floatval($perfil['interes_anual_base'] ?? self::TASA_CIVIL_REFERENCIA_ANUAL),
            'apertura_accion_civil' => floatval($perfil['apertura_accion_civil'] ?? 0.55),
            'severidad_costas' => floatval($perfil['severidad_costas'] ?? 1.0),
        ];
    }

    private function buildCivilScenarios(
        float $salario,
        int $edad,
        float $incapacidad,
        float $pisoArtComparativo,
        array $accidentesConfig,
        array $perfilJurisdiccional,
        bool $tieneArt,
        float $recargoSinArt = 0.0
    ): array {
        $edadSegura = max($edad, 16);
        $capitalBase = ($salario * floatval($accidentesConfig['meses_año'] ?? 13) * ($incapacidad / 100) * floatval($accidentesConfig['factor_edad_limite'] ?? 65)) / $edadSegura;
        $civilConfig = $accidentesConfig['civil'] ?? [];
        $escenariosConfig = $civilConfig['escenarios'] ?? [];
        $costasConfig = $civilConfig['costas'] ?? [];
        $honorariosConfig = $civilConfig['honorarios'] ?? [];
        $temeridadTasa = max(0.0, floatval($civilConfig['temeridad_tasa'] ?? 0.0));
        $escenarios = [];

        foreach ($escenariosConfig as $nombre => $config) {
            $danioMoralPct = floatval($config['danio_moral'] ?? self::DANIO_MORAL_CIVIL_PORCENTAJE)
                * floatval($perfilJurisdiccional['danio_moral_factor'] ?? 1.0);
            $interesAnual = max(
                floatval($config['interes_anual'] ?? self::TASA_CIVIL_REFERENCIA_ANUAL),
                floatval($perfilJurisdiccional['interes_anual_base'] ?? self::TASA_CIVIL_REFERENCIA_ANUAL)
            );
            $duracionMeses = max(1, intval($config['duracion_meses'] ?? 48));
            $factorProbatorio = max(0.5, floatval($config['factor_probatorio'] ?? 1.0));
            $subtotalBase = $capitalBase * $factorProbatorio;
            $danioMoral = $subtotalBase * $danioMoralPct;
            $factorInteres = pow(1 + $interesAnual, $duracionMeses / 12);
            $montoSinCostas = ($subtotalBase + $danioMoral) * $factorInteres;
            $recargoFaltaArt = !$tieneArt && $recargoSinArt > 0 ? $montoSinCostas * $recargoSinArt : 0.0;
            $montoSinCostas += $recargoFaltaArt;

            if ($tieneArt && $montoSinCostas <= $pisoArtComparativo) {
                $montoSinCostas = $pisoArtComparativo;
            }

            $claveCostas = match ($nombre) {
                'conservador' => 'min',
                'agresivo' => 'max',
                default => 'probable',
            };
            $costasTasa = floatval($costasConfig[$claveCostas] ?? 0.20) * floatval($perfilJurisdiccional['severidad_costas'] ?? 1.0);
            $honorariosTasa = floatval($honorariosConfig[$claveCostas] ?? 0.22);
            $costas = $montoSinCostas * $costasTasa;
            $honorarios = $montoSinCostas * $honorariosTasa;
            $temeridad = !$tieneArt && $nombre === 'agresivo' ? $montoSinCostas * $temeridadTasa : 0.0;

            $escenarios[$nombre] = [
                'capital_base' => round($capitalBase, 2),
                'monto_sin_costas' => round($montoSinCostas, 2),
                'monto_total' => round($montoSinCostas + $costas + $honorarios + $temeridad, 2),
                'danio_moral_porcentaje' => round($danioMoralPct, 4),
                'interes_anual' => round($interesAnual, 4),
                'duracion_meses' => $duracionMeses,
                'factor_probatorio' => round($factorProbatorio, 2),
                'costas' => round($costas, 2),
                'honorarios' => round($honorarios, 2),
                'temeridad' => round($temeridad, 2),
                'recargo_falta_art' => round($recargoFaltaArt, 2),
                'apertura_accion_civil' => round(floatval($perfilJurisdiccional['apertura_accion_civil'] ?? 0.55), 2),
                'nota' => sprintf(
                    'Escenario %s: capital Méndez + daño moral %.0f%% + intereses %.0f%% anual por %d meses%s.',
                    $nombre,
                    $danioMoralPct * 100,
                    $interesAnual * 100,
                    $duracionMeses,
                    !$tieneArt && $recargoFaltaArt > 0 ? ' + recargo por falta de ART' : ''
                ),
            ];
        }

        return [
            'capital_base' => round($capitalBase, 2),
            'escenarios' => $escenarios,
            'costas_estimadas' => [
                'min' => round(($escenarios['conservador']['costas'] ?? 0) + ($escenarios['conservador']['honorarios'] ?? 0), 2),
                'probable' => round(($escenarios['probable']['costas'] ?? 0) + ($escenarios['probable']['honorarios'] ?? 0), 2),
                'max' => round(($escenarios['agresivo']['costas'] ?? 0) + ($escenarios['agresivo']['honorarios'] ?? 0) + ($escenarios['agresivo']['temeridad'] ?? 0), 2),
            ],
        ];
    }

    private function buildAccidentDomainModel(
        bool $tieneArt,
        float $montoArt,
        array $analisisArt,
        array $analisisCivil,
        array $perfilJurisdiccional,
        array $alertasCriticas,
        array $situacion
    ): array {
        $escenariosCiviles = $analisisCivil['escenarios'] ?? [];
        $civilConservador = floatval($escenariosCiviles['conservador']['monto_total'] ?? 0);
        $civilProbable = floatval($escenariosCiviles['probable']['monto_total'] ?? 0);
        $civilAgresivo = floatval($escenariosCiviles['agresivo']['monto_total'] ?? 0);
        $maximoReal = max($montoArt, $civilAgresivo);
        $viaRecomendada = $this->resolveViaRecomendada(
            $tieneArt,
            $montoArt,
            $civilProbable,
            $civilAgresivo,
            $perfilJurisdiccional,
            $alertasCriticas,
            $situacion
        );

        return [
            'perfil_jurisdiccional' => $perfilJurisdiccional,
            'scoring_juridico' => [
                'jurisdiccion' => $perfilJurisdiccional['jurisdiccion'],
                'apertura_accion_civil' => round(floatval($perfilJurisdiccional['apertura_accion_civil'] ?? 0.55), 2),
                'tendencia_danio_moral' => $perfilJurisdiccional['tendencia_danio_moral'],
                'alertas' => $alertasCriticas,
            ],
            'cuantificacion_economica' => [
                'via_art' => [
                    'disponible' => $tieneArt,
                    'tipo' => 'tarifado',
                    'monto_seguro' => round($montoArt, 2),
                    'riesgo_empresa' => $tieneArt ? 'bajo' : 'alto',
                    'detalle_calculo' => $analisisArt,
                ],
                'via_civil' => [
                    'disponible' => true,
                    'tipo' => 'integral_excluyente',
                    'escenarios' => [
                        'conservador' => round($civilConservador, 2),
                        'probable' => round($civilProbable, 2),
                        'agresivo' => round($civilAgresivo, 2),
                    ],
                    'detalle_escenarios' => $escenariosCiviles,
                    'riesgo_empresa' => 'alto',
                ],
                'comparativa' => [
                    'opcion_excluyente' => true,
                    'via_recomendada' => $viaRecomendada,
                    'diferencia_probable_vs_art' => round($civilProbable - $montoArt, 2),
                    'estrategia_sugerida' => $viaRecomendada === 'art'
                        ? 'Priorizar tarifa ART y reservar la vía civil para supuestos con prueba reforzada.'
                        : 'Analizar la vía civil porque la exposición probable supera materialmente a la tarifa ART y hay contexto jurídico favorable.',
                ],
                'costas' => $analisisCivil['costas_estimadas'] ?? ['min' => 0, 'probable' => 0, 'max' => 0],
                'resultado_final' => [
                    'exposicion_maxima_real' => round($maximoReal, 2),
                    'tipo' => $civilAgresivo >= $montoArt
                        ? 'escenario_civil_agresivo_con_costas'
                        : 'escenario_art_tarifado',
                ],
            ],
            'resultados_clave' => [
                'exposicion_art_segura' => round($montoArt, 2),
                'exposicion_civil_conservadora' => round($civilConservador, 2),
                'exposicion_civil_probable' => round($civilProbable, 2),
                'exposicion_civil_agresiva' => round($civilAgresivo, 2),
                'exposicion_maxima_real_con_costas' => round($maximoReal, 2),
                'estrategia_sugerida' => $viaRecomendada,
            ],
        ];
    }

    private function resolveViaRecomendada(
        bool $tieneArt,
        float $montoArt,
        float $civilProbable,
        float $civilAgresivo,
        array $perfilJurisdiccional,
        array $alertasCriticas,
        array $situacion
    ): string {
        if (!$tieneArt) {
            return 'civil';
        }

        $estadoCm = (string) ($situacion['comision_medica'] ?? 'no_iniciada');
        if ($estadoCm === 'homologado') {
            return 'civil';
        }

        $aperturaCivil = floatval($perfilJurisdiccional['apertura_accion_civil'] ?? 0.55);
        $hayNexoFuerte = in_array('alta_probabilidad_nexo', array_column($alertasCriticas, 'codigo'), true);
        $documentacionDeficitaria = in_array('documentacion_insuficiente_empresa', array_column($alertasCriticas, 'codigo'), true);

        if (($civilProbable > ($montoArt * self::CIVIL_PROBABLE_THRESHOLD_MULTIPLIER)
                || $civilAgresivo > ($montoArt * self::CIVIL_AGGRESSIVE_THRESHOLD_MULTIPLIER))
            && $aperturaCivil >= self::MIN_APERTURA_CIVIL_FOR_RECOMMENDATION
            && ($hayNexoFuerte || $documentacionDeficitaria)) {
            return 'civil';
        }

        return 'art';
    }

    private function buildAlertasCriticasAccidente(array $documentacion, array $situacion, bool $tieneArt): array
    {
        $alertas = [];

        if (!$tieneArt) {
            $alertas[] = ['codigo' => 'falta_art', 'nivel' => 'critico'];
        }

        if (($situacion['comision_medica'] ?? 'no_iniciada') === 'no_iniciada') {
            $alertas[] = ['codigo' => 'dictamen_cm_ausente', 'nivel' => 'alto'];
        }

        if (($situacion['denuncia_art'] ?? 'no') === 'si' && ($situacion['rechazo_art'] ?? 'no') === 'no') {
            $alertas[] = ['codigo' => 'alta_probabilidad_nexo', 'nivel' => 'alto'];
        }

        if (($documentacion['registrado_afip'] ?? 'no') === 'no' || ($documentacion['tiene_recibos'] ?? 'no') === 'no') {
            $alertas[] = ['codigo' => 'documentacion_insuficiente_empresa', 'nivel' => 'alto'];
        }

        if (intval($situacion['cantidad_empleados'] ?? 1) > 1) {
            $alertas[] = ['codigo' => 'riesgo_cascada', 'nivel' => 'medio'];
        }

        if (($situacion['rechazo_art'] ?? 'no') === 'si' || ($situacion['culpa_grave'] ?? 'no') === 'si') {
            $alertas[] = ['codigo' => 'via_civil_viable', 'nivel' => 'alto'];
        }

        return $alertas;
    }

    private function buildLegacyTotalsFromAccidentModel(array $modeloDominio): array
    {
        $resultadosClave = $modeloDominio['resultados_clave'] ?? [];

        return [
            'total_base' => floatval($resultadosClave['exposicion_art_segura'] ?? 0),
            'total_con_multas' => floatval($resultadosClave['exposicion_maxima_real_con_costas'] ?? 0),
        ];
    }
}
