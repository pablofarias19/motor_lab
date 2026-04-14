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

    private const LITIGIOSIDAD_BASE = 0.12;

    /**
     * Calcula la exposición económica frente a una demanda por solidaridad (Art. 30).
     * 
     * @param array $respuestas Array asociativo con respuestas booleanas:
     *      'actividad_esencial', 'control_documental', 'control_operativo',
     *      'integracion_estructura', 'contrato_formal', 'falta_f931_art'
     * @param float $montoPorTrabajador Monto estimado de la contingencia por trabajador
     * @param int $cantidadTrabajadores Universo estimado de trabajadores expuestos
     * @param array $contexto Datos complementarios del caso
     * @return array Detalle del riesgo, score, probabilidad y exposición matemática
     */
    public function calcularRiesgoSolidario(
        array $respuestas,
        float $montoPorTrabajador,
        int $cantidadTrabajadores = 1,
        array $contexto = []
    ): array {
        
        $score = 0;
        $detalles = [];
        $cantidadTrabajadores = max(1, $cantidadTrabajadores);
        $montoPorTrabajador = max(0, $montoPorTrabajador);

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

        // 5. LITIGIOSIDAD ESPERADA SOBRE EL UNIVERSO DE TRABAJADORES
        $tasaLitigiosidad = $this->calcularTasaLitigiosidadEsperada($respuestas, $cantidadTrabajadores, $contexto);
        $trabajadoresReclamantes = $this->calcularTrabajadoresReclamantes($cantidadTrabajadores, $tasaLitigiosidad);
        $exposicionMaxima = $montoPorTrabajador * $cantidadTrabajadores;
        $exposicionProbable = $montoPorTrabajador * $trabajadoresReclamantes;
        $exposicionEsperada = $exposicionProbable * $probabilidadCondena;

        $detalles[] = sprintf(
            'Universo considerado: %d trabajador%s. Litigiosidad esperada: %s (%d reclamo%s probables).',
            $cantidadTrabajadores,
            $cantidadTrabajadores === 1 ? '' : 'es',
            $this->formatPercentage($tasaLitigiosidad),
            $trabajadoresReclamantes,
            $trabajadoresReclamantes === 1 ? '' : 's'
        );

        return [
            'riesgo_calificacion' => $riesgo,
            'score_judicial' => $score,
            'probabilidad_condena' => ($probabilidadCondena * 100) . '%',
            'universo_trabajadores' => $cantidadTrabajadores,
            'monto_estimado_por_trabajador' => round($montoPorTrabajador, 2),
            'tasa_litigiosidad_esperada' => $this->formatPercentage($tasaLitigiosidad),
            'trabajadores_reclamantes_estimados' => $trabajadoresReclamantes,
            'exposicion_maxima' => round($exposicionMaxima, 2),
            'exposicion_probable' => round($exposicionProbable, 2),
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

    private function calcularTasaLitigiosidadEsperada(array $respuestas, int $cantidadTrabajadores, array $contexto): float
    {
        $tasa = self::LITIGIOSIDAD_BASE;
        $cantidadSubcontratistas = max(1, intval($contexto['cantidad_subcontratistas'] ?? 1));

        if (!empty($respuestas['actividad_esencial'])) {
            $tasa += 0.04;
        }

        if (!empty($respuestas['control_operativo'])) {
            $tasa += 0.03;
        }

        if (!empty($respuestas['integracion_estructura'])) {
            $tasa += 0.03;
        }

        if (!empty($respuestas['control_documental'])) {
            $tasa -= 0.02;
        } else {
            $tasa += 0.03;
        }

        if (!empty($respuestas['contrato_formal'])) {
            $tasa -= 0.01;
        } else {
            $tasa += 0.02;
        }

        if ($cantidadTrabajadores >= 10) {
            $tasa += 0.02;
        }

        if ($cantidadTrabajadores >= 25) {
            $tasa += 0.03;
        }

        if ($cantidadSubcontratistas >= 3) {
            $tasa += 0.02;
        }

        if (!empty($respuestas['falta_f931_art'])) {
            $tasa = max($tasa, 0.45);
        }

        return max(0.05, min(0.75, round($tasa, 4)));
    }

    private function calcularTrabajadoresReclamantes(int $cantidadTrabajadores, float $tasaLitigiosidad): int
    {
        if ($cantidadTrabajadores <= 1) {
            return 1;
        }

        return max(1, (int) ceil($cantidadTrabajadores * $tasaLitigiosidad));
    }

    private function formatPercentage(float $value): string
    {
        return rtrim(rtrim(number_format($value * 100, 2, '.', ''), '0'), '.') . '%';
    }
}
