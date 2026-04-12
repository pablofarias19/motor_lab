<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';

final class ExposicionEngineDiferenciasSalarialesTest extends TestCase
{
    public function run(): void
    {
        $engine = new ExposicionEngine();

        $resultado = $engine->calcularExposicion(
            [
                'salario' => 560000,
                'antiguedad_meses' => 12,
                'provincia' => 'Córdoba',
            ],
            [],
            [
                'motivo_diferencia' => 'falta_pago',
                'meses_adeudados' => 2,
                'ya_despedido' => 'no',
                'fecha_despido' => '',
            ],
            'diferencias_salariales',
            'empleado'
        );

        $this->assertTrue(isset($resultado['conceptos']['deuda_salarial_historica']));
        $this->assertFalse(isset($resultado['conceptos']['sac_proporcional']), 'No debería sumar SAC por un reclamo puro de diferencias salariales.');
        $this->assertFalse(isset($resultado['conceptos']['vacaciones_proporcionales']), 'No debería sumar vacaciones por un reclamo puro de diferencias salariales.');
        $this->assertFalse(isset($resultado['conceptos']['multa_art80_lct']), 'No debería sumar Art. 80 sin desvinculación.');
        $this->assertSame(1120000.0, floatval($resultado['total_base'] ?? 0));
        $this->assertSame(1120000.0, floatval($resultado['total_con_multas'] ?? 0));
    }
}
