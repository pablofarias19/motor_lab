<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\LaborInspectionAnalysisBuilder;

final class LaborInspectionAnalysisBuilderThresholdsTest extends TestCase
{
    public function run(): void
    {
        $baseDatos = [
            'salario' => 400000,
            'salario_recibo' => 300000,
            'provincia' => 'Córdoba',
            'categoria' => 'Operario',
            'cct' => 'UOM',
            'cantidad_empleados' => 20,
            'tipo_registro' => 'registrado',
        ];
        $baseDoc = [
            'tiene_recibos' => 'si',
            'tiene_contrato' => 'si',
            'registrado_afip' => 'si',
            'pago_bancario' => 'no',
        ];
        $baseSituacion = [
            'chk_alta_sipa' => 'si',
            'chk_libro_art52' => 'si',
            'chk_recibos_cct' => 'si',
            'chk_art_vigente' => 'si',
            'chk_examenes' => 'si',
            'chk_epp_rgrl' => 'si',
            'meses_no_registrados' => 0,
            'inspeccion_previa' => 'no',
            'hay_intercambio' => 'no',
            'fue_intimado' => 'no',
            'fraude_evasion_sistematica' => 'no',
            'probabilidad_condena' => 0.2,
        ];

        $analysis = LaborInspectionAnalysisBuilder::build(
            $baseDatos,
            $baseDoc,
            $baseSituacion,
            ['total_con_multas' => 100000],
            ['score' => 2.0, 'nivel' => ['nivel' => 'Medio']]
        );

        $this->assertSame(1.0, floatval($analysis['laboral']['matriz_riesgo']['estructural']['puntaje'] ?? 0));
        $this->assertSame(2.0, floatval($analysis['laboral']['matriz_riesgo']['remuneracion']['puntaje'] ?? 0));
        $this->assertSame('auditoria_preventiva', $analysis['modelo_sistema']['recomendacion']);
    }
}
