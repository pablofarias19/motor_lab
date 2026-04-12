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
        $this->assertSame('civil', $sinArt['resultados_clave']['estrategia_sugerida'] ?? null, 'Sin ART la estrategia sugerida debe pasar a vía civil.');
        $this->assertTrue(
            ($sinArt['resultados_clave']['exposicion_maxima_real_con_costas'] ?? 0) > ($sinArt['resultados_clave']['exposicion_civil_probable'] ?? 0),
            'La exposición máxima debe integrar costas y accesorios.'
        );

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
        $this->assertTrue(isset($conArt['cuantificacion_economica']['via_art']), 'Debe exponer la vía ART explícita.');
        $this->assertTrue(isset($conArt['cuantificacion_economica']['via_civil']['escenarios']), 'Debe exponer escenarios civiles explícitos.');
        $this->assertTrue(($conArt['cuantificacion_economica']['comparativa']['opcion_excluyente'] ?? false), 'ART y civil deben figurar como vías excluyentes.');
        $this->assertSame(true, $conArt['cuantificacion_economica']['via_art']['disponible'] ?? null);
        $this->assertSame('tarifado', $conArt['cuantificacion_economica']['via_art']['tipo'] ?? null);
        $this->assertTrue(($conArt['cuantificacion_economica']['via_art']['monto_seguro'] ?? 0) > 0);
        $escenariosCiviles = $conArt['cuantificacion_economica']['via_civil']['escenarios'] ?? [];
        $this->assertTrue(isset($escenariosCiviles['conservador'], $escenariosCiviles['probable'], $escenariosCiviles['agresivo']));
        $this->assertTrue(($escenariosCiviles['conservador'] ?? 0) > 0);
        $detalleArt = $conArt['conceptos']['prestacion_art_tarifa']['detalle_calculo'] ?? [];
        $this->assertSame(false, $detalleArt['calculo_estimado'] ?? true, 'Con fecha y salarios históricos debería reconstruirse el IBM.');
        $this->assertTrue(($detalleArt['ibm'] ?? 0) > 440000, 'El IBM ajustado por RIPTE debe superar los salarios históricos nominales del ejemplo.');
        $this->assertSame(3, count($detalleArt['salarios_considerados'] ?? []), 'Debe conservar la trazabilidad de los salarios usados.');
        $this->assertTrue(
            ($conArt['conceptos']['estimacion_civil_mendez']['monto'] ?? 0) >= ($conArt['conceptos']['prestacion_art_tarifa']['monto'] ?? 0),
            'La estimación civil integral no debe quedar por debajo de la tarifa ART.'
        );
        $this->assertSame(
            round(floatval($conArt['resultados_clave']['exposicion_art_segura'] ?? 0), 2),
            round(floatval($conArt['total_base'] ?? 0), 2),
            'El total base legacy debe reflejar la exposición ART segura y no sumar vías.'
        );
        $this->assertTrue(
            ($conArt['resultados_clave']['exposicion_civil_agresiva'] ?? 0) >= ($conArt['resultados_clave']['exposicion_civil_probable'] ?? 0),
            'Los escenarios civiles deben escalar en tres niveles.'
        );
        $this->assertSame(
            round(floatval($conArt['resultados_clave']['exposicion_maxima_real_con_costas'] ?? 0), 2),
            round(floatval($conArt['total_con_multas'] ?? 0), 2),
            'La exposición máxima real debe gobernar el total con multas legacy.'
        );
    }
}
