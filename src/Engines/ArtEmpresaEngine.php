<?php
namespace App\Engines;

/**
 * ArtEmpresaEngine.php — Motor analítico de contingencias ART (Perspectiva Empleador)
 * 
 * Calcula la exposición de una empresa ante un accidente o enfermedad laboral.
 * Discrimina entre riesgo protegido por el sistema LRT y el riesgo directo
 * por incumplimientos (falta de ART, trabajo en negro, culpa grave y vía civil).
 * 
 * Además, detecta oportunidades preventivas de compliance y seguros.
 */
class ArtEmpresaEngine {

    /**
     * Evalúa la exposición integral de la empresa en un caso médico-laboral.
     * 
     * @param float $indemnizacionLRT El cálculo base arrojado por el sistema tarifado
     * @param array $parametros Incluye: estado_ART, culpa_grave, trabajador_no_registrado, via_civil
     * @return array Resumen del riesgo, exposición multiplicada y alertas estratégicas
     */
    public function calcularExposicionEmpresa(float $indemnizacionLRT, array $parametros): array {
        
        $estadoART = $parametros['estado_ART'] ?? 'activa_valida';
        $culpaGrave = !empty($parametros['culpa_grave']);
        $trabajadorNoRegistrado = !empty($parametros['trabajador_no_registrado']);
        $viaCivil = !empty($parametros['via_civil']);
        
        $factor = 0;
        $responsable = 'ART';
        $alertas = [];
        $ofertaSeguro = null;

        // ==========================================
        // ÁRBOL DE DECISIÓN JURÍDICO - MULTIPLICADORES
        // ==========================================
        
        if ($estadoART === 'activa_valida') {
            $factor = 0.2; // Exposición residual
            $responsable = 'ART';
            $nivelRiesgo = 'BAJO';
            $alertas[] = 'Riesgo acotado. La ART cubre la prestación principal.';
            
            if ($culpaGrave) {
                $factor += 0.8;
                $responsable = 'COMPARTIDO';
                $nivelRiesgo = 'MEDIO';
                $alertas[] = 'Agravamiento por culpa grave. Riesgo de acción civil complementaria.';
            }
            if ($viaCivil) {
                $factor += 0.5;
            }
            
        } elseif ($estadoART === 'activa_incumplida') {
            $factor = 1.5;
            $responsable = 'COMPARTIDO';
            $nivelRiesgo = 'ALTO';
            $alertas[] = 'Peligro de Derecho de Repetición. La ART podría pagar y luego demandar al empleador por retención de aportes o fraude.';
            
            if ($culpaGrave) {
                $factor += 1.0;
                $nivelRiesgo = 'CRÍTICO';
            }
            
        } elseif ($estadoART === 'inexistente') {
            $factor = 2.5; // Empleador responde como autoasegurado
            $responsable = 'EMPRESA (Exposición Total)';
            $nivelRiesgo = 'CRÍTICO';
            $alertas[] = 'ALERTA: Sin cobertura de Sistema LRT. Empleador asume el 100% del pago prestacional más sanciones legales directas.';
            
            if ($trabajadorNoRegistrado) {
                $factor += 1.0; // Trabajo en negro duplica gravedad
                $alertas[] = 'Penalidad máxima por siniestro en clandestinidad laboral.';
            }
            if ($culpaGrave) {
                $factor += 1.5;
            }
            
            // CROSS-SELLING / CALL TO ACTION (Preventivo)
            $ofertaSeguro = [
                'aviso' => 'Esta contingencia se podría haber evitado mediante una póliza ART activa y legal, que representa un costo ínfimo comparado al riesgo asumido.',
                'contacto' => 'PABLO NICOLAS FARIAS - Abogado, Asesor y Productor de Seguros (Matrícula SSN 90691)',
                'accion' => 'Solicitar cotización urgente de Alta Temprana ART'
            ];
        }

        // ==========================================
        // CÁLCULO FINAL DE EXPOSICIÓN
        // ==========================================
        
        $exposicionTotal = $indemnizacionLRT * $factor;
        
        return [
            'modulo' => 'Manejo de Contingencia ART (Óptica Empresa)',
            'nivel_riesgo' => $nivelRiesgo,
            'responsable_principal' => $responsable,
            'factor_multiplicador_aplicado' => round($factor, 2) . 'x',
            'base_indemnizacion_LRT' => round($indemnizacionLRT, 2),
            'exposicion_estimada_empresa' => round($exposicionTotal, 2),
            'alertas_juridicas' => $alertas,
            'cross_selling_seguros' => $ofertaSeguro
        ];
    }
}
