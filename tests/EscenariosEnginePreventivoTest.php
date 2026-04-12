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

        $escenarioAudit = $auditoria['escenarios']['D'] ?? [];
        $this->assertSame('Beneficio (ahorro pot.)', $escenarioAudit['beneficio_label'] ?? null);
        $this->assertSame('Ahorro neto estimado', $escenarioAudit['vbp_label'] ?? null);
        $this->assertSame('alta', $escenarioAudit['aplicabilidad'] ?? null);
        $this->assertSame(3000000.0, floatval($escenarioAudit['beneficio_estimado'] ?? 0));
        $this->assertSame(375000.0, floatval($escenarioAudit['costo_estimado'] ?? 0));
        $this->assertTrue(!empty($escenarioAudit['definicion_sistema'] ?? null), 'El escenario preventivo debe explicar cómo se define.');
        $this->assertTrue(count($escenarioAudit['criterios_definidos'] ?? []) >= 6, 'El escenario preventivo debe exponer criterios explícitos.');

        $noRegistrado = $engine->generarEscenarios(
            [
                'total_base' => 12000000,
                'total_con_multas' => 18000000,
                'salario_base' => 600000,
            ],
            3.6,
            'trabajo_no_registrado',
            'empleado',
            [
                'hay_intercambio' => 'si',
                'ya_despedido' => 'si',
            ],
            'Buenos Aires'
        );

        $escenarioNoReg = $noRegistrado['escenarios']['D'] ?? [];
        $this->assertSame('referencial', $escenarioNoReg['aplicabilidad'] ?? null);
        $this->assertSame('Beneficio (referencial)', $escenarioNoReg['beneficio_label'] ?? null);
        $this->assertTrue(
            floatval($escenarioNoReg['beneficio_estimado'] ?? 0) < 18000000,
            'Con conflicto ya escalado, el beneficio preventivo debe reducirse frente al pasivo total.'
        );
        $this->assertTrue(
            str_contains((string) ($escenarioNoReg['definicion_sistema'] ?? ''), 'pasivo por registración omitida'),
            'La definición del sistema debe explicar la base específica del caso.'
        );
    }
}
