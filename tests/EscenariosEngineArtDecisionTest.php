<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once __DIR__ . '/TestCase.php';

final class EscenariosEngineArtDecisionTest extends TestCase
{
    public function run(): void
    {
        $engine = new EscenariosEngine();
        $resultado = $engine->generarEscenarios(
            [
                'total_base' => 5800000,
                'total_con_multas' => 14200000,
                'salario_base' => 500000,
                'cuantificacion_economica' => [
                    'via_art' => [
                        'monto_seguro' => 5800000,
                    ],
                    'via_civil' => [
                        'monto_integral_referencial' => 12400000,
                        'escenarios' => [
                            'conservador' => 7200000,
                            'probable' => 9800000,
                            'agresivo' => 14200000,
                        ],
                    ],
                    'comparativa' => [
                        'via_recomendada' => 'civil',
                    ],
                    'costas' => [
                        'probable' => 2200000,
                    ],
                ],
                'conceptos' => [
                    'prestacion_art_tarifa' => ['monto' => 5800000],
                    'estimacion_civil_mendez' => ['monto' => 9800000],
                ],
            ],
            3.8,
            'accidente_laboral',
            'empleado',
            [
                'tiene_art' => 'si',
                'comision_medica' => 'dictamen_emitido',
                'porcentaje_incapacidad' => 35,
            ],
            'Córdoba'
        );

        $escenarioD = $resultado['escenarios']['D'] ?? [];
        $this->assertSame('D', $resultado['recomendado'] ?? null, 'La comparativa explícita debe poder forzar la recomendación civil.');
        $this->assertSame('civil', $escenarioD['via_juridica'] ?? null);
        $this->assertTrue(isset($escenarioD['exposicion_economica']['agresivo']), 'La acción civil debe exponerse por rango y no por un único monto.');
        $this->assertSame(12400000.0, floatval($escenarioD['beneficio_estimado'] ?? 0), 'La acción civil debe priorizar el reclamo Méndez referencial cuando está disponible.');
        $this->assertSame(12400000.0, floatval($escenarioD['exposicion_economica']['reclamo_mendez'] ?? 0), 'Debe conservar visible la referencia Méndez principal.');
        $this->assertTrue(($escenarioD['costo_estimado'] ?? 0) > 0, 'La acción civil debe incorporar costas probables.');
    }
}
