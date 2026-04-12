<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once dirname(__DIR__) . '/config/IrilEngine.php';

final class IrilEngineAccidenteSignalsTest extends TestCase
{
    public function run(): void
    {
        $engine = new IrilEngine();

        $resultado = $engine->calcularIRIL(
            [
                'provincia' => 'Córdoba',
            ],
            [
                'tiene_recibos' => 'si',
                'tiene_testigos' => 'si',
                'registrado_afip' => 'si',
                'documentacion_empresa_completa' => 'no',
            ],
            [
                'tiene_art' => 'si',
                'denuncia_art' => 'si',
                'rechazo_art' => 'no',
                'comision_medica' => 'no_iniciada',
                'tipo_contingencia' => 'enfermedad_profesional',
                'calidad_prueba_medica' => 'alta',
                'nexo_causal' => 'alto',
                'cantidad_empleados' => 4,
            ],
            'accidente_laboral',
            'empleado'
        );

        $this->assertSame('Córdoba', $resultado['perfil_jurisdiccional']['jurisdiccion'] ?? null);
        $this->assertSame('alta', $resultado['senales_art']['calidad_prueba_medica'] ?? null);
        $this->assertTrue(($resultado['detalle']['volatilidad_normativa']['valor'] ?? 0) >= 4.5, 'La jurisdicción y contingencia deben sostener una volatilidad alta.');

        $tiposAlerta = array_column($resultado['alertas'] ?? [], 'tipo');
        $this->assertTrue(in_array('dictamen_cm_ausente', $tiposAlerta, true), 'Debe alertar la falta de dictamen de Comisión Médica.');
        $this->assertTrue(in_array('alta_probabilidad_nexo', $tiposAlerta, true), 'Debe alertar una señal favorable de nexo causal.');
        $this->assertTrue(in_array('documentacion_insuficiente_empresa', $tiposAlerta, true), 'Debe alertar documentación empresaria insuficiente.');
        $this->assertTrue(in_array('riesgo_cascada', $tiposAlerta, true), 'Debe alertar riesgo cascada con más de un empleado.');
    }
}
