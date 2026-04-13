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
                'regularizacion' => [
                    'aplica_regimen' => true,
                    'dias_desde_reglamentacion' => 45,
                    'modalidad_pago' => 'plan_corto',
                    'cuotas' => 3,
                    'condonacion_intereses_pct' => 60,
                    'condonacion_multas_pct' => 100,
                    'accion_penal' => 'extinguida_por_cancelacion_total',
                    'riesgo_caducidad' => 'La caducidad del plan reactiva la acción penal y el cómputo de la prescripción.',
                ],
                'alertas_penales' => [
                    'nivel' => 'alto',
                    'tipologias' => ['evasion_previsional'],
                    'capital_evadido_mensual' => 205700,
                    'aporte_retenido_mensual' => 85000,
                    'detalle' => 'Existen indicadores de riesgo penal tributario/previsional por montos mensuales.',
                ],
                'valuacion_activos' => [
                    'activos' => [
                        ['tipo' => 'dinero', 'ubicacion' => 'argentina', 'valor_usd' => 200000],
                    ],
                    'base_imponible_usd' => 200000,
                    'franquicia_usd' => 100000,
                    'excedente_gravado_usd' => 100000,
                    'alicuota' => 0.1,
                    'impuesto_especial_usd' => 10000,
                    'tipo_cambio_regularizacion' => 1000,
                ],
            ]
        );

        $this->assertSame('Medio', $report['matriz_riesgo']['registral']['nivel']);
        $this->assertSame('Alta probabilidad de inspección', $report['iril']['interpretacion']);
        $this->assertSame('Alta', $report['conclusion_estrategica']['probabilidad_inspeccion']);
        $this->assertSame('Regularización inmediata', $report['conclusion_estrategica']['recomendacion_principal']);
        $this->assertSame('regularizacion_inmediata', $report['modelo_salida']['recomendacion']);
        $this->assertSame(2, $report['modelo_salida']['riesgo_arca']['conductual']);
        $this->assertSame(4, count($report['escenarios_estrategicos']), 'El informe ARCA debe exponer los 4 escenarios estratégicos.');
        $this->assertSame(60, $report['moratoria_ley_27743']['condonacion_intereses_pct']);
        $this->assertSame('alto', strtolower((string) $report['alertas_penales']['nivel']));
        $this->assertSame(10000.0, floatval($report['valuacion_regularizacion']['impuesto_especial_usd']));
        $this->assertSame(false, $report['beneficios_bloqueo_fiscal']['antecedente_negativo']);
    }
}
