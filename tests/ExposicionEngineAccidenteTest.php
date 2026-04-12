<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';

final class ExposicionEngineAccidenteTest extends TestCase
{
    public function run(): void
    {
        $engine = new ExposicionEngine();

        $sinArt = $engine->calcularExposicion(
            [
                'salario' => 500000,
                'antiguedad_meses' => 24,
            ],
            [],
            [
                'porcentaje_incapacidad' => 20,
                'tiene_art' => 'no',
                'edad' => 35,
            ],
            'accidente_laboral',
            'empleado'
        );

        $this->assertTrue(isset($sinArt['conceptos']['exposicion_accidente']));
        $this->assertTrue(isset($sinArt['conceptos']['riesgo_falta_art']));
        $this->assertFalse(isset($sinArt['conceptos']['multa_art80_lct']), 'No debería incluir Art. 80 en accidente laboral');

        $conArt = $engine->calcularExposicion(
            [
                'salario' => 500000,
                'antiguedad_meses' => 24,
                'edad' => 35,
            ],
            [],
            [
                'porcentaje_incapacidad' => 20,
                'tiene_art' => 'si',
                'edad' => 35,
                'incapacidad_tipo' => 'permanente_definitiva',
                'fecha_siniestro' => '2026-02-10',
                'salarios_historicos' => [
                    400000,
                    420000,
                    440000,
                ],
            ],
            'accidente_laboral',
            'empleado'
        );

        $this->assertTrue(isset($conArt['conceptos']['prestacion_art_tarifa']));
        $this->assertTrue(isset($conArt['conceptos']['estimacion_civil_mendez']));
        $this->assertFalse(isset($conArt['conceptos']['multa_art80_lct']), 'No debería incluir Art. 80 aun con ART vigente');
        $this->assertTrue(!empty($conArt['conceptos']['prestacion_art_tarifa']['nota'] ?? null), 'La tarifa ART debe conservar su análisis');
        $this->assertTrue(!empty($conArt['conceptos']['estimacion_civil_mendez']['nota'] ?? null), 'La acción civil Méndez debe conservar su análisis');
        $detalleArt = $conArt['conceptos']['prestacion_art_tarifa']['detalle_calculo'] ?? [];
        $this->assertSame(false, $detalleArt['calculo_estimado'] ?? true, 'Con fecha y salarios históricos debería reconstruirse el IBM.');
        $this->assertTrue(($detalleArt['ibm'] ?? 0) > 440000, 'El IBM ajustado por RIPTE debe superar los salarios históricos nominales del ejemplo.');
        $this->assertSame(3, count($detalleArt['salarios_considerados'] ?? []), 'Debe conservar la trazabilidad de los salarios usados.');
        $this->assertTrue(
            ($conArt['conceptos']['estimacion_civil_mendez']['monto'] ?? 0) >= ($conArt['conceptos']['prestacion_art_tarifa']['monto'] ?? 0),
            'La estimación civil integral no debe quedar por debajo de la tarifa ART.'
        );
    }
}
