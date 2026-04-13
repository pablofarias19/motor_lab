<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once __DIR__ . '/TestCase.php';

final class EscenariosEnginePreventivoTest extends TestCase
{
    public function run(): void
    {
        $engine = new EscenariosEngine();

        $auditoria = $engine->generarEscenarios(
            [
                'total_base' => 3000000,
                'total_con_multas' => 3000000,
                'salario_base' => 250000,
            ],
            1.8,
            'auditoria_preventiva',
            'empleador',
            [
                'nivel_cumplimiento' => 'critico',
            ],
            'CABA'
        );

        $this->assertTrue(isset($auditoria['escenarios']['D']), 'La parte empleadora debe conservar el escenario preventivo.');
        $escenarioAudit = $auditoria['escenarios']['D'];
        $this->assertSame('Beneficio (ahorro pot.)', $escenarioAudit['beneficio_label'] ?? null);
        $this->assertSame('Ahorro neto estimado', $escenarioAudit['vbp_label'] ?? null);
        $this->assertSame('alta', $escenarioAudit['aplicabilidad'] ?? null);
        $this->assertTrue($escenarioAudit['es_preventivo'] ?? false, 'El escenario D preventivo debe identificarse explícitamente.');
        $this->assertSame(3000000.0, floatval($escenarioAudit['beneficio_estimado'] ?? 0));
        $this->assertSame(375000.0, floatval($escenarioAudit['costo_estimado'] ?? 0));
        $this->assertTrue(str_contains((string) ($escenarioAudit['descripcion'] ?? ''), 'SEGUROS COMPLEMENTARIOS'));
        $this->assertTrue(str_contains((string) ($escenarioAudit['descripcion'] ?? ''), 'Estudio Farias Ortiz'));
        $this->assertTrue(str_contains((string) ($escenarioAudit['lectura_beneficio'] ?? ''), 'SEGUROS COMPLEMENTARIOS'));
        $this->assertTrue(!empty($escenarioAudit['definicion_sistema']), 'El escenario preventivo debe explicar cómo se define.');
        $this->assertTrue(count($escenarioAudit['criterios_definidos'] ?? []) >= 6, 'El escenario preventivo debe exponer criterios explícitos.');

        $diferenciasEmpleado = $engine->generarEscenarios(
            [
                'total_base' => 3000000,
                'total_con_multas' => 4200000,
                'salario_base' => 500000,
            ],
            3.0,
            'diferencias_salariales',
            'empleado',
            [
                'meses_adeudados' => 6,
            ],
            'Córdoba'
        );

        $this->assertTrue(
            !isset($diferenciasEmpleado['escenarios']['D']),
            'La parte reclamante no debe ver un escenario preventivo exclusivo del empleador.'
        );
    }
}
