<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Engines\ArtCalculationEngine;

final class ArtCalculationEngineFallbackRipteTest extends TestCase
{
    public function run(): void
    {
        $params = require dirname(__DIR__) . '/config/parametros_motor.php';
        $engine = new ArtCalculationEngine();

        $resultado = $engine->calcular(
            [
                'salario' => 500000,
                'edad' => 35,
            ],
            [
                'porcentaje_incapacidad' => 20,
                'incapacidad_tipo' => 'permanente_definitiva',
                'fecha_siniestro' => '2026-02-10',
            ],
            $params['calculos_especificos']['accidentes']
        );

        $this->assertTrue(($resultado['ibm'] ?? 0) > 500000, 'Sin histórico igual debe estimar IBM con ajuste RIPTE.');
        $this->assertSame(true, $resultado['calculo_estimado'] ?? false, 'Sin serie completa debe seguir marcado como estimado.');
        $this->assertNotNull($resultado['ripte_referencia'] ?? null, 'Debe conservar la referencia RIPTE usada.');
        $this->assertSame(
            'fallback_simplificado_ripte',
            $resultado['salarios_considerados'][0]['metodo'] ?? null,
            'Debe dejar trazabilidad del fallback simplificado con RIPTE.'
        );
    }
}
