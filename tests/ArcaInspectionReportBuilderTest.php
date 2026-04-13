<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\ArcaInspectionReportBuilder;

final class ArcaInspectionReportBuilderTest extends TestCase
{
    public function run(): void
    {
        $report = ArcaInspectionReportBuilder::build(
            [
                'datos_laborales' => [
                    'salario' => 500000,
                    'salario_recibo' => 250000,
                    'provincia' => 'CABA',
                    'categoria' => 'Administrativo',
                    'cct' => '130/75',
                    'cantidad_empleados' => 12,
                    'tipo_registro' => 'deficiente_salario',
                ],
                'documentacion' => [
                    'registrado_afip' => 'si',
                    'tiene_contrato' => 'si',
                    'pago_bancario' => 'si',
                    'tiene_telegramas' => 'si',
                ],
                'situacion' => [
                    'meses_no_registrados' => 8,
                    'inspeccion_previa' => 'si',
                    'chk_alta_sipa' => 'si',
                    'chk_libro_art52' => 'no',
                    'chk_recibos_cct' => 'no',
                    'chk_art_vigente' => 'si',
                    'falta_f931_art' => 'si',
                    'fraude_evasion_sistematica' => 'si',
                ],
            ],
            [
                'score' => 3.6,
                'nivel' => ml_nivel_iril(3.6),
            ],
            [],
            [
                'recomendado' => 'D',
                'escenarios' => [
                    'D' => ['descripcion' => 'Regularización estructural y auditoría interna inmediata.'],
                ],
            ],
            [
                'capital_omitido' => 1640000,
                'intereses' => 410000,
                'multas' => 2100000,
                'deuda_total' => 4150000,
                'detalle' => 'Inspección oficiosa con aplicación plena de multas.',
            ]
        );

        $this->assertSame('Medio', $report['matriz_riesgo']['registral']['nivel']);
        $this->assertSame('Alta probabilidad de inspección', $report['iril']['interpretacion']);
        $this->assertSame('Alta', $report['conclusion_estrategica']['probabilidad_inspeccion']);
        $this->assertSame('Regularización inmediata', $report['conclusion_estrategica']['recomendacion_principal']);
        $this->assertSame('regularizacion_inmediata', $report['modelo_salida']['recomendacion']);
        $this->assertSame(2, $report['modelo_salida']['riesgo_arca']['conductual']);
        $this->assertSame(4, count($report['escenarios_estrategicos']), 'El informe ARCA debe exponer los 4 escenarios estratégicos.');
    }
}
