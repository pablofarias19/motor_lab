<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\ComplementaryLegalAnalysisBuilder;

final class ComplementaryLegalAnalysisBuilderTest extends TestCase
{
    public function run(): void
    {
        $analysis = ComplementaryLegalAnalysisBuilder::build(
            ['salario' => 500000],
            [
                'tiene_facturacion' => 'si',
                'tiene_pago_bancario' => 'si',
                'tiene_contrato_escrito' => 'si',
                'valida_cuil' => 'si',
                'valida_aportes' => 'si',
                'valida_pago_directo' => 'si',
                'valida_cbu' => 'si',
                'valida_art' => 'si',
                'fraude_evasion_sistematica' => 'si',
                'tipo_extincion' => 'constructivo',
                'fue_violenta' => 'si',
                'meses_litigio' => 24,
            ],
            ['total' => 2500000]
        );

        $ley = $analysis['ley_27802'];
        $this->assertFalse($ley['presuncion']['presuncion_opera']);
        $this->assertTrue($ley['solidaria']['exento']);
        $this->assertTrue($ley['fraude']['score'] > 0);
        $this->assertNotNull($ley['dano']);
        $this->assertTrue($ley['dano']['total_daño_complementario'] > 0);
    }
}
