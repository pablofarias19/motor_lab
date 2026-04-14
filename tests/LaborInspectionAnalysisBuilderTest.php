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
                'tiene_facturacion' => 'si',
                'tiene_pago_bancario' => 'si',
                'meses_no_registrados' => 12,
                'inspeccion_previa' => 'si',
                'hay_intercambio' => 'si',
                'fue_intimado' => 'si',
                'fraude_evasion_sistematica' => 'si',
                'fraude_estructura_opaca' => 'si',
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
        $this->assertSame('Defensa administrativa y discusión de base', $analysis['recomendacion_final']);
        $this->assertSame('defensa_administrativa_y_discusion_de_base', $analysis['modelo_sistema']['recomendacion']);
        $this->assertSame('acta_labrada', $analysis['estado_inspeccion']);
        $this->assertSame('acta', $analysis['evento_fiscal']);
        $this->assertSame('acta_labrada', $analysis['escenario_optimo']);
        $this->assertTrue(floatval($analysis['score'] ?? 0) >= 60);
        $this->assertSame('crítico', $analysis['laboral']['variables_criticas']['variables_juridicas']['impacto_prueba'] ?? null);
        $this->assertTrue(floatval($analysis['probabilidad_condena'] ?? 0) >= 0.8);
        $this->assertTrue(floatval($analysis['probabilidad_ajuste'] ?? 0) >= 0.9);
        $this->assertTrue(($analysis['laboral']['matriz_riesgo']['registracion']['puntaje'] ?? 0) >= 4.0);
        $this->assertTrue(($analysis['laboral']['matriz_riesgo']['condiciones']['puntaje'] ?? 0) >= 4.0);
        $this->assertSame(false, $analysis['laboral']['checklist']['art_vigente']);
        $this->assertTrue(str_contains((string) ($analysis['laboral']['diagnostico']['conclusion_juridica'] ?? ''), 'antecedentes de inspección'));
        $this->assertSame(
            'contagio_fiscal_por_operatoria_paralela',
            $analysis['laboral']['contexto_inspectivo']['codigo'] ?? null
        );
        $this->assertTrue(str_contains(
            strtolower((string) ($analysis['laboral']['contexto_inspectivo']['foco_probatorio'] ?? '')),
            'desvincular la nómina'
        ));
        $this->assertSame(2300000.0, floatval($analysis['laboral']['cuantificacion']['riesgo_economico_indirecto'] ?? 0));
        $this->assertSame('total_con_multas', $analysis['laboral']['cuantificacion']['fundamentos_montos']['riesgo_economico_indirecto']['componente_dominante'] ?? null);
        $this->assertTrue(str_contains(
            strtolower((string) ($analysis['laboral']['escenarios']['acta_labrada']['titulo'] ?? '')),
            'acta labrada'
        ));
        $this->assertTrue(str_contains(
            strtolower((string) ($analysis['laboral']['escenarios']['acta_labrada']['descripcion'] ?? '')),
            'defensa'
        ));
        $this->assertTrue(str_contains(
            strtolower((string) ($analysis['laboral']['consideraciones_legales'][1]['titulo'] ?? '')),
            '11.683'
        ));
        $this->assertTrue(($analysis['laboral']['consideraciones_legales'][3]['aplica'] ?? false));
        $this->assertSame(900000.0, floatval($analysis['laboral']['contingencia']['administrativa'] ?? 0));
        $this->assertSame('Escenario C — Acta labrada', $analysis['laboral']['escenario_optimo']['titulo'] ?? null);
    }
}
