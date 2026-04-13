<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';
require_once __DIR__ . '/TestCase.php';

final class ExposicionEngineArtCivilEstimateTest extends TestCase
{
    public function run(): void
    {
        $engine = new ExposicionEngine();

        $resultado = $engine->calcularExposicion(
            [
                'salario' => 1000000,
                'antiguedad_meses' => 24,
                'edad' => 45,
            ],
            [
                'tiene_recibos' => 'si',
                'registrado_afip' => 'si',
            ],
            [
                'porcentaje_incapacidad' => 15,
                'tiene_art' => 'si',
                'incapacidad_tipo' => 'permanente_definitiva',
            ],
            'accidente_laboral',
            'empleado'
        );

        $tarifaArt = floatval($resultado['conceptos']['prestacion_art_tarifa']['monto'] ?? 0);
        $civil = $resultado['conceptos']['estimacion_civil_mendez'] ?? [];
        $montoCivil = floatval($civil['monto'] ?? 0);

        $this->assertTrue($tarifaArt > 0, 'La tarifa ART debe calcularse');
        $this->assertTrue($montoCivil >= $tarifaArt, 'La estimación civil integral no debe quedar por debajo de la tarifa ART');
        $this->assertTrue(($civil['componentes']['capital_base'] ?? 0) > 0, 'La estimación civil debe informar capital base');
        $this->assertTrue(($civil['componentes']['danio_moral'] ?? 0) > 0, 'La estimación civil debe informar daño moral');
        $this->assertTrue(($civil['componentes']['danio_vida_relacion'] ?? 0) > 0, 'La estimación civil debe informar daño de vida de relación o pérdida de chance');
        $this->assertTrue(($civil['componentes']['actualizacion_judicial'] ?? 0) > 0, 'La estimación civil debe informar actualización judicial orientativa');
        $this->assertTrue(array_key_exists('piso_aplicado', $civil['componentes'] ?? []), 'La estimación civil debe informar si aplicó piso comparativo');
        $this->assertEquals(
            round($montoCivil + floatval($resultado['conceptos']['multa_art80_lct']['monto'] ?? 0), 2),
            round(floatval($resultado['total_con_multas'] ?? 0), 2),
            'El total con multas debe tomar la mejor ruta disponible, no sumar ART y civil como si fueran acumulables'
        );
    }
}
