<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Engines\ArcaEngine;

final class ArcaEngineMoratoriaTest extends TestCase
{
    public function run(): void
    {
        $engine = new ArcaEngine();

        $result = $engine->calcularRiesgoArca(
            500000,
            8,
            true,
            false,
            [
                'salario_declarado' => 300000,
                'cantidad_empleados' => 10,
                'dias_desde_reglamentacion' => 45,
                'modalidad_pago_regularizacion' => 'plan_corto',
                'cuotas_regularizacion' => 3,
                'gratificaciones_habituales' => 50000,
                'activos_regularizables' => [
                    ['tipo' => 'dinero', 'moneda' => 'ARS', 'valor' => 200000000],
                ],
                'tipo_cambio_regularizacion' => 1000,
                'etapa_regularizacion' => 2,
            ]
        );

        $this->assertSame(1645600.0, floatval($result['capital_omitido']));
        $this->assertSame(131648.0, floatval($result['intereses']));
        $this->assertSame(0.0, floatval($result['multas']));
        $this->assertSame(60, intval($result['regularizacion']['condonacion_intereses_pct'] ?? 0));
        $this->assertSame('extinguida_por_cancelacion_total', $result['regularizacion']['accion_penal']);
        $this->assertSame('alto', $result['alertas_penales']['nivel']);
        $this->assertSame(10000.0, floatval($result['valuacion_activos']['impuesto_especial_usd'] ?? 0));
    }
}
