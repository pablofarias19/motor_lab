<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';

use App\Support\AnalysisPayloadNormalizer;

final class ExposicionEngineDiferenciasSalarialesTest extends TestCase
{
    public function run(): void
    {
        $engine = new ExposicionEngine();

        $input = [
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'diferencias_salariales',
            'datos_laborales' => [
                'salario' => 560000,
                'antiguedad_meses' => 12,
                'provincia' => 'Córdoba',
            ],
            'documentacion' => [],
            'situacion' => [
                'motivo_diferencia' => 'falta_pago',
                'meses_adeudados' => 2,
                'ya_despedido' => 'si',
                'fecha_despido' => '2026-03-10',
                'check_inconstitucionalidad' => 'si',
            ],
            'contacto' => [
                'email' => 'diferencias@example.com',
            ],
        ];

        $this->assertSame('si', $input['situacion']['ya_despedido']);
        $this->assertSame('2026-03-10', $input['situacion']['fecha_despido']);

        $payload = AnalysisPayloadNormalizer::normalize($input);

        $resultado = $engine->calcularExposicion(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario']
        );

        $this->assertTrue(isset($resultado['conceptos']['deuda_salarial_historica']));
        $this->assertFalse(isset($resultado['conceptos']['sac_proporcional']), 'No debería sumar SAC por un reclamo puro de diferencias salariales.');
        $this->assertFalse(isset($resultado['conceptos']['vacaciones_proporcionales']), 'No debería sumar vacaciones por un reclamo puro de diferencias salariales.');
        $this->assertFalse(isset($resultado['conceptos']['multa_art80_lct']), 'No debería sumar Art. 80 sin desvinculación.');
        $this->assertSame('no', $payload['situacion']['ya_despedido']);
        $this->assertSame('', $payload['situacion']['fecha_despido']);
        $this->assertSame(1120000.0, floatval($resultado['total_base'] ?? 0));
        $this->assertSame(1120000.0, floatval($resultado['total_con_multas'] ?? 0));
    }
}
