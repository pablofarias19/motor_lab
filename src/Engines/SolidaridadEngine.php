<?php
namespace App\Engines;

/**
 * SolidaridadEngine.php — Calculadora de Riesgo de Responsabilidad Solidaria
 * 
 * Basada en el Art. 30 de la Ley de Contrato de Trabajo (LCT).
 * Aplica lógica de ponderación judicial para conflictos de tercerización,
 * subcontratación o franquicias. El quantum indemnizatorio se asume al 100%,
 * pero se calcula la 'Exposición Esperada' según la Probabilidad de Condena.
 */
class SolidaridadEngine {

    /**
     * Calcula la exposición económica frente a una demanda por solidaridad (Art. 30).
     * 
     * @param array $respuestas Array asociativo con respuestas booleanas:
     *      'actividad_esencial', 'control_documental', 'control_operativo',
     *      'integracion_estructura', 'contrato_formal', 'falta_f931_art'
     * @param float $liquidacionTotal Monto total de la liquidación laboral calculada
     * @return array Detalle del riesgo, score, probabilidad y exposición matemática
     */
    public function calcularRiesgoSolidario(array $respuestas, float $liquidacionTotal): array {
        
        $score = 0;
        $detalles = [];

        // 1. ANÁLISIS DE FACTORES POSITIVOS (Aumentan Probabilidad de Condena)
        if (!empty($respuestas['actividad_esencial'])) {
            $score += 3;
            $detalles[] = 'Actividad esencial y específica de la empresa delegada (+3).';
        }
        if (!empty($respuestas['control_operativo'])) {
            $score += 2;
            $detalles[] = 'Control operativo directo sobre el contratista (+2).';
        }
        if (!empty($respuestas['integracion_estructura'])) {
            $score += 2;
            $detalles[] = 'Integración del trabajador en la estructura/imagen del principal (+2).';
        }

        // 2. ANÁLISIS DE FACTORES NEGATIVOS PALIATIVOS (Reducen Probabilidad de Condena)
        if (!empty($respuestas['control_documental'])) {
            $score -= 3;
            $detalles[] = 'Cumplimiento de control documental estricto (Mitiga Responsabilidad) (-3).';
        }
        if (!empty($respuestas['contrato_formal'])) {
            $score -= 2;
            $detalles[] = 'Contrato comercial/civil formal existente (-2).';
        }

        // 3. DETERMINACIÓN DE CLASE DE RIESGO
        if ($score <= 0) {
            $riesgo = 'BAJO';
            $probabilidadCondena = 0.20;
        } elseif ($score <= 3) {
            $riesgo = 'MEDIO';
            $probabilidadCondena = 0.50;
        } else {
            $riesgo = 'ALTO';
            $probabilidadCondena = 0.80;
        }

        // 4. CAPA DOS: OVERRIDE JURISPRUDENCIAL DIRECTO
        // La falta comprobada de control de F931 o ART es condena casi segura.
        if (!empty($respuestas['falta_f931_art'])) {
            $riesgo = 'MUY ALTO (OVERRIDE)';
            $probabilidadCondena = 0.95; 
            $detalles[] = 'ALERTA CRÍTICA: Falta de control de F931 o ART. Riesgo de condena inminente por omisión de deberes de contralor legal.';
        }

        // 5. CÁLCULO DE EXPOSICIÓN ESPERADA
        // Se aplica el criterio del "Quantum total" por la "Probabilidad"
        $exposicionEsperada = $liquidacionTotal * $probabilidadCondena;

        return [
            'riesgo_calificacion' => $riesgo,
            'score_judicial' => $score,
            'probabilidad_condena' => ($probabilidadCondena * 100) . '%',
            'exposicion_maxima' => round($liquidacionTotal, 2),
            'exposicion_esperada' => round($exposicionEsperada, 2),
            'factores_detectados' => $detalles,
            'recomendacion' => $this->generarRecomendacion($riesgo)
        ];
    }

    private function generarRecomendacion(string $riesgo): string {
        switch ($riesgo) {
            case 'BAJO':
                return 'Riesgo contenido. Mantener protocolos de control documental.';
            case 'MEDIO':
                return 'Zona gris. Se recomienda ajustar cláusulas de contrato y auditar los pagos de la subcontratista.';
            case 'ALTO':
            case 'MUY ALTO (OVERRIDE)':
                return 'Intervención inmediata. Existe riesgo de condena solidaria plena. Considere renegociar el contrato comercial o exigir regularización urgente.';
            default:
                return '';
        }
    }
}
