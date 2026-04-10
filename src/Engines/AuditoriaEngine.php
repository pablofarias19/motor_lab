<?php
namespace App\Engines;

/**
 * AuditoriaEngine.php — Motor analítico de Auditoría Preventiva y Compliance
 * 
 * Compara económicamente el "Costo de Regularización Espontánea" (CRE) 
 * contra el "Costo de Litigio Latente" (CLL) para determinar si la empresa 
 * debe asumir la regularización impositiva o prepararse para una defensa.
 */
class AuditoriaEngine {

    /**
     * Calcula la exposición comparativa entre regularizar o litigar.
     * 
     * @param array $inputs Diccionario con los parámetros del empleado y la empresa:
     *        salario_mensual, meses_no_registrados, meses_en_mora,
     *        tasa_aportes (0.17), tasa_contribuciones (0.24), tasa_interes_afip,
     *        tasa_interes_judicial, probabilidad_condena, aplica_blanco_laboral,
     *        antiguedad_total, salario_base_indemnizacion, coef_multas_laborales
     * @return array Comparativa CRE vs CLL y recomendación estructurada
     */
    public function calcularCompliance(array $inputs): array {
        
        // 1. Extraer variables seguras con defaults
        $salarioMensual = floatval($inputs['salario_mensual'] ?? 0);
        $mesesNoRegistrados = intval($inputs['meses_no_registrados'] ?? 0);
        $mesesEnMora = intval($inputs['meses_en_mora'] ?? 0);
        
        $tasaAportes = floatval($inputs['tasa_aportes'] ?? 0.17);
        $tasaContrib = floatval($inputs['tasa_contribuciones'] ?? 0.24);
        $tasaInteresAfip = floatval($inputs['tasa_interes_afip'] ?? 0.05); // 5% mensual
        $tasaInteresJudicial = floatval($inputs['tasa_interes_judicial'] ?? 0.08); // 8% mensual
        
        $probabilidadCondena = floatval($inputs['probabilidad_condena'] ?? 0.6);
        $aplicaBlanco = !empty($inputs['aplica_blanco_laboral']);
        
        $antiguedadTotal = intval($inputs['antiguedad_total'] ?? 0);
        $salarioBaseIndem = floatval($inputs['salario_base_indemnizacion'] ?? $salarioMensual);
        $coefMultasLaborales = floatval($inputs['coef_multas_laborales'] ?? 1.5); // Ej: 150% extra

        // ==========================================
        // FASE A: COSTO REGULARIZACIÓN ESPONTÁNEA (CRE)
        // ==========================================
        
        // A.1 Capital omitido
        $cargasMensuales = $salarioMensual * ($tasaAportes + $tasaContrib);
        $capitalOmitido = $cargasMensuales * $mesesNoRegistrados;
        
        // A.2 Intereses AFIP (con posible reducción por Ley Bases)
        $interesFactor = $aplicaBlanco ? 0.3 : 1.0;
        $interesesRegularizacion = $capitalOmitido * $tasaInteresAfip * $mesesEnMora * $interesFactor;
        
        // A.3 Multas
        $multasRegularizacion = $aplicaBlanco ? 0 : ($capitalOmitido * 1.0);
        
        $cre = $capitalOmitido + $interesesRegularizacion + $multasRegularizacion;

        // ==========================================
        // FASE B: COSTO DE LITIGIO LATENTE (CLL)
        // ==========================================
        
        // B.1 Indemnización Base
        $indemnizacionAntiguedad = $salarioBaseIndem * $antiguedadTotal;
        
        // B.2 Multas Laborales (Leyes 24.013 / 25.323)
        $multasLaborales = $indemnizacionAntiguedad * $coefMultasLaborales;
        
        // B.3 Cargas Sociales Reclamables en juicio
        $cargasSocialesLitigio = $capitalOmitido; // El empleado lo denunciará igual
        
        // B.4 Intereses Judiciales
        $interesesLitigio = ($indemnizacionAntiguedad + $multasLaborales) * $tasaInteresJudicial * $mesesEnMora;
        
        // B.5 Total bruto
        $totalLitigioBruto = $indemnizacionAntiguedad + $multasLaborales + $cargasSocialesLitigio + $interesesLitigio;
        
        // B.6 Ajuste por Probabilidad
        $cll = $totalLitigioBruto * $probabilidadCondena;

        // ==========================================
        // FASE C: OUTPUTS ESPERADOS
        // ==========================================
        
        $diferencial = $cll - $cre;
        $ahorroEstimado = max(0, $diferencial);

        // Árbol de decisión de recomendación
        if ($cre < $cll && $probabilidadCondena >= 0.5) {
            $recomendacion = 'REGULARIZAR (Alta conveniencia)';
            $textoRecomendacion = 'El costo de regularizar espontáneamente es significativamente menor que enfrentar un litigio. Se sugiere adherir a planes de blanqueo vigentes inmediatamente.';
        } elseif ($cre < $cll) {
            $recomendacion = 'REGULARIZACION ESTRATÉGICA';
            $textoRecomendacion = 'A pesar de que la probabilidad de condena es media/baja, económicamente sigue siendo conveniente regularizar para eliminar deuda previsional.';
        } else {
            $recomendacion = 'ASUMIR RIESGO / ESTRATEGIA DEFENSIVA';
            $textoRecomendacion = 'La probabilidad de condena es baja y el costo de blanqueo supera el riesgo litigioso ajustado. Se sugiere preparar documentación defensiva ("Risk Controlled").';
        }

        // Categorización de riesgo formal
        if ($probabilidadCondena > 0.6) $riesgoFormal = 'ALTO';
        elseif ($probabilidadCondena >= 0.3) $riesgoFormal = 'MEDIO';
        else $riesgoFormal = 'BAJO';

        return [
            'cre_costo_regularizacion' => round($cre, 2),
            'cll_costo_litigio_esperado' => round($cll, 2),
            'cll_bruto_sin_probabilidad' => round($totalLitigioBruto, 2),
            'diferencial_cre_cll' => round($diferencial, 2),
            'ahorro_estimado_al_regularizar' => round($ahorroEstimado, 2),
            'recomendacion_accion' => $recomendacion,
            'texto_estrategico' => $textoRecomendacion,
            'desglose' => [
                'cre_capital_omitido' => round($capitalOmitido, 2),
                'cre_intereses' => round($interesesRegularizacion, 2),
                'cre_multas' => round($multasRegularizacion, 2),
                'cll_indem_base' => round($indemnizacionAntiguedad, 2),
                'cll_multas_lab' => round($multasLaborales, 2),
                'riesgo_probabilidad' => $riesgoFormal . ' (' . ($probabilidadCondena * 100) . '%)'
            ]
        ];
    }
}
