<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\LaborInspectionAnalysisBuilder;

final class LaborInspectionAnalysisBuilderTest extends TestCase
{
    public function run(): void
    {
        $analysis = LaborInspectionAnalysisBuilder::build(
            [
                'salario' => 500000,
                'salario_recibo' => 350000,
                'provincia' => 'CABA',
                'categoria' => 'Administrativo B',
                'cct' => '130/75',
                'cantidad_empleados' => 18,
                'tipo_registro' => 'deficiente_salario',
            ],
            [
                'tiene_recibos' => 'no',
                'tiene_contrato' => 'no',
                'registrado_afip' => 'no',
                'pago_bancario' => 'no',
            ],
            [
                'chk_alta_sipa' => 'no',
                'chk_libro_art52' => 'no',
                'chk_recibos_cct' => 'no',
                'chk_art_vigente' => 'no',
                'chk_examenes' => 'no',
                'chk_epp_rgrl' => 'no',
                'meses_no_registrados' => 12,
                'inspeccion_previa' => 'si',
                'hay_intercambio' => 'si',
                'fue_intimado' => 'si',
                'fraude_evasion_sistematica' => 'si',
                'probabilidad_condena' => 0.8,
            ],
            [
                'total_con_multas' => 2300000,
                'conceptos' => [
                    'sancion_administrativa' => ['monto' => 900000],
                ],
                'analisis_empresa' => [
                    'inspeccion' => [
                        'deuda_total' => 1200000,
                    ],
                ],
            ],
            [
                'score' => 3.8,
                'nivel' => ['nivel' => 'Alto'],
            ]
        );

        $this->assertSame('muy_grave', $analysis['infraccion_laboral']);
        $this->assertSame(3.8, $analysis['iril_laboral']);
        $this->assertSame('Alto', $analysis['nivel_laboral']);
        $this->assertSame('crítico', $analysis['probabilidad_inspeccion']);
        $this->assertSame('Defensa estructurada', $analysis['recomendacion_final']);
        $this->assertSame('defensa_estructurada', $analysis['modelo_sistema']['recomendacion']);
        $this->assertTrue(($analysis['laboral']['matriz_riesgo']['registracion']['puntaje'] ?? 0) >= 4.0);
        $this->assertTrue(($analysis['laboral']['matriz_riesgo']['condiciones']['puntaje'] ?? 0) >= 4.0);
        $this->assertSame(false, $analysis['laboral']['checklist']['art_vigente']);
        $this->assertTrue(str_contains((string) ($analysis['laboral']['diagnostico']['conclusion_juridica'] ?? ''), 'antecedente inspectivo'));
        $this->assertSame(2300000.0, floatval($analysis['laboral']['cuantificacion']['riesgo_economico_indirecto'] ?? 0));
    }
}
