<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once __DIR__ . '/TestCase.php';

final class EscenariosEngineStrategicIndexTest extends TestCase
{
    public function run(): void
    {
        $engine = new EscenariosEngine();
        $resultado = $engine->generarEscenarios(
            [
                'total_base' => 12000000,
                'total_con_multas' => 18000000,
                'salario_base' => 800000,
            ],
            3.2,
            'despido_sin_causa',
            'empleado',
            [],
            'CABA'
        );

        $escenarios = $resultado['escenarios'] ?? [];
        $this->assertSame(['A', 'B', 'C'], array_keys($escenarios), 'Para la parte reclamante solo deben mostrarse escenarios litigiosos aplicables');

        foreach ($escenarios as $codigo => $escenario) {
            $this->assertTrue(array_key_exists('indice_estrategico', $escenario), "Falta indice_estrategico en {$codigo}");
            $this->assertTrue(is_numeric($escenario['indice_estrategico']), "indice_estrategico inválido en {$codigo}");
            $this->assertTrue($escenario['indice_estrategico'] >= 0 && $escenario['indice_estrategico'] <= 100, "indice_estrategico fuera de rango en {$codigo}");
        }

        $mejorCodigoABC = null;
        $mejorIndice = -1.0;
        foreach (['A', 'B', 'C'] as $codigo) {
            $indice = floatval($escenarios[$codigo]['indice_estrategico']);
            if ($indice > $mejorIndice) {
                $mejorIndice = $indice;
                $mejorCodigoABC = $codigo;
            }
        }

        $this->assertSame($mejorCodigoABC, $resultado['recomendado'] ?? null, 'La recomendación debe seguir el mayor índice estratégico permitido');
        $this->assertTrue(
            floatval($escenarios['A']['indice_estrategico']) > floatval($escenarios['B']['indice_estrategico']),
            'La duración y el riesgo deben penalizar al litigio completo frente a negociación temprana'
        );
    }
}
