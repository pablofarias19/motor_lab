<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\LaborInspectionAnalysisBuilder;

final class LaborInspectionAnalysisBuilderOutputTest extends TestCase
{
    public function run(): void
    {
        $analysis = LaborInspectionAnalysisBuilder::build(
            [
                'salario' => 500000,
                'salario_recibo' => 250000,
                'provincia' => 'CABA',
                'categoria' => 'Administrativo',
                'cct' => '130/75',
                'cantidad_empleados' => 12,
                'tipo_registro' => 'deficiente_salario',
            ],
            [
                'registrado_afip' => 'si',
                'tiene_contrato' => 'si',
                'pago_bancario' => 'si',
                'tiene_telegramas' => 'si',
            ],
            [
                'meses_no_registrados' => 8,
                'inspeccion_previa' => 'si',
                'chk_alta_sipa' => 'si',
                'chk_libro_art52' => 'no',
                'chk_recibos_cct' => 'no',
                'chk_art_vigente' => 'si',
                'chk_examenes' => 'no',
                'chk_epp_rgrl' => 'no',
                'falta_f931_art' => 'si',
                'fraude_evasion_sistematica' => 'si',
                'aplica_blanco_laboral' => 'si',
                'probabilidad_condena' => 0.5,
            ],
            [
                'total_con_multas' => 7500000,
                'conceptos' => [
                    'sancion_administrativa' => ['monto' => 7500000],
                ],
                'analisis_empresa' => [
                    'inspeccion' => [
                        'deuda_total' => 4000000,
                    ],
                ],
            ],
            [
                'score' => 2.7,
                'nivel' => ['nivel' => 'Medio'],
            ]
        );

        $this->assertSame('iniciada', $analysis['estado_inspeccion']);
        $this->assertSame('estrategia_mixta', $analysis['escenario_optimo']);
        $this->assertSame('Control de daño y conciliación', $analysis['recomendacion_final']);
        $this->assertSame(7500000.0, floatval($analysis['laboral']['contingencia']['administrativa'] ?? 0));
        $this->assertSame('medio', $analysis['laboral']['variables_criticas']['variables_juridicas']['impacto_prueba'] ?? null);
        $this->assertTrue(isset($analysis['laboral']['documentacion_probatoria']['matriz_impacto_probatorio']));
    }
}
