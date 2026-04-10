<?php
namespace App\Engines;

/**
 * ArcaEngine.php — Motor de cálculo de contingencias fiscales ante ARCA (ex AFIP)
 * 
 * Implementa el modelo determinístico de Deuda de Cargas Sociales,
 * Intereses Resarcitorios (acumulativos), Multas por Omisión y Fallas de Registración,
 * contemplando las reducciones de la Ley Bases (2024).
 */
class ArcaEngine {
    
    // Parámetros base ARCA/AFIP (Configurables)
    private float $tasaAporte = 0.17; // 17% trabajador
    private float $tasaContribucion = 0.24; // 24% empleador promedio
    private float $tasaInteresMensual = 0.05; // 5% mensual (estimado penalidad)
    private float $coefMultaOmision = 1.0; // 100% del tributo omitido
    private float $coefMultaNoRegistracion = 0.5; // 50% de salarios omitidos

    /**
     * Calcula la exposición ante una fiscalización o regularización
     * 
     * @param float $salario Salario real de bolsillo
     * @param int $mesesEnNegro Meses no registrados
     * @param bool $regularizaVoluntariamente TRUE si adhiere a blanqueo (Ley Bases)
     * @param bool $hayDolo TRUE si hay maniobra evasiva detectada (agrava multas)
     * @return array Detalle completo de deuda, intereses y multas
     */
    public function calcularRiesgoArca(
        float $salario, 
        int $mesesEnNegro, 
        bool $regularizaVoluntariamente = false,
        bool $hayDolo = false
    ): array {
        
        if ($mesesEnNegro <= 0 || $salario <= 0) {
            return [
                'capital_omitido' => 0,
                'intereses' => 0,
                'multas' => 0,
                'deuda_total' => 0,
                'detalle' => 'Sin deuda previsional aparente.',
                'aplica_ley_bases' => false
            ];
        }

        // 1. DETERMINACIÓN DE DEUDA BASE
        // Capital = (Aportes + Contribuciones) * meses
        $cargasMensuales = $salario * ($this->tasaAporte + $this->tasaContribucion);
        $capitalOmitido = $cargasMensuales * $mesesEnNegro;

        // 2. INTERESES RESARCITORIOS (Modelo Acumulativo)
        // Se asume en promedio que la mora media es la mitad del plazo (mesesEnNegro / 2)
        // ya que la deuda se fue generando mes a mes.
        $mesesMoraMedia = ceil($mesesEnNegro / 2);
        
        $deudaActualizada = $capitalOmitido;
        for ($i = 0; $i < $mesesMoraMedia; $i++) {
            $deudaActualizada *= (1 + $this->tasaInteresMensual);
        }
        $interesTotal = $deudaActualizada - $capitalOmitido;

        // 3. MULTAS (Omisión 11.683 + Fallas Seg. Social)
        $multaTotal = 0;
        
        if ($regularizaVoluntariamente) {
            // LEY BASES: Condonación de multas y reducción de intereses
            $multaTotal = 0; 
            $interesTotal *= 0.3; // Reducción estimativa del 70% de intereses
            $detalle = "Regularización Voluntaria (Ley Bases): Condonación de multas y descuento en intereses.";
        } else {
            // RÉGIMEN PUNITIVO FUERTE (Sin Ley Bases)
            $coefOmisionEfectivo = $hayDolo ? ($this->coefMultaOmision * 2) : $this->coefMultaOmision;
            $multaBase = $capitalOmitido * $coefOmisionEfectivo;
            $multaNoRegistracion = $salario * $mesesEnNegro * $this->coefMultaNoRegistracion;
            
            $multaTotal = $multaBase + $multaNoRegistracion;
            $detalle = "Inspección Oficiosa: Aplicación plena de Multas Ley 11.683 e Intereses resarcitorios.";
        }

        $deudaTotal = $capitalOmitido + $interesTotal + $multaTotal;

        return [
            'capital_omitido' => round($capitalOmitido, 2),
            'intereses' => round($interesTotal, 2),
            'multas' => round($multaTotal, 2),
            'deuda_total' => round($deudaTotal, 2),
            'desglose' => [
                'tasa_aporte_empleado' => ($this->tasaAporte * 100) . '%',
                'tasa_contrib_empleador' => ($this->tasaContribucion * 100) . '%',
                'meses_adeudados' => $mesesEnNegro,
                'condicion_dolo' => $hayDolo,
                'aplica_ley_bases' => $regularizaVoluntariamente
            ],
            'detalle' => $detalle
        ];
    }
}
