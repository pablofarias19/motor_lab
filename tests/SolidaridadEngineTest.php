<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Engines\SolidaridadEngine;

final class SolidaridadEngineTest extends TestCase
{
    public function run(): void
    {
        $engine = new SolidaridadEngine();

        $resultadoBase = $engine->calcularRiesgoSolidario([
            'actividad_esencial' => true,
            'control_documental' => true,
            'control_operativo' => false,
            'integracion_estructura' => false,
            'contrato_formal' => true,
            'falta_f931_art' => false,
        ], 1000000, 25, [
            'cantidad_subcontratistas' => 2,
        ]);

        $this->assertSame('BAJO', $resultadoBase['riesgo_calificacion']);
        $this->assertSame(25, $resultadoBase['universo_trabajadores']);
        $this->assertEquals(1000000.0, $resultadoBase['monto_estimado_por_trabajador']);
        $this->assertSame('18%', $resultadoBase['tasa_litigiosidad_esperada']);
        $this->assertSame(5, $resultadoBase['trabajadores_reclamantes_estimados']);
        $this->assertEquals(25000000.0, $resultadoBase['exposicion_maxima']);
        $this->assertEquals(5000000.0, $resultadoBase['exposicion_probable']);
        $this->assertEquals(1000000.0, $resultadoBase['exposicion_esperada']);

        $resultadoCritico = $engine->calcularRiesgoSolidario([
            'actividad_esencial' => true,
            'control_documental' => false,
            'control_operativo' => true,
            'integracion_estructura' => true,
            'contrato_formal' => false,
            'falta_f931_art' => true,
        ], 500000, 10, [
            'cantidad_subcontratistas' => 4,
        ]);

        $this->assertSame('MUY ALTO (OVERRIDE)', $resultadoCritico['riesgo_calificacion']);
        $this->assertSame('45%', $resultadoCritico['tasa_litigiosidad_esperada']);
        $this->assertSame(5, $resultadoCritico['trabajadores_reclamantes_estimados']);
        $this->assertEquals(5000000.0, $resultadoCritico['exposicion_maxima']);
        $this->assertEquals(2500000.0, $resultadoCritico['exposicion_probable']);
        $this->assertEquals(2375000.0, $resultadoCritico['exposicion_esperada']);
    }
}
