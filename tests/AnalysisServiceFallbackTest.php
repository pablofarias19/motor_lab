<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/IrilEngine.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';
require_once __DIR__ . '/TestCase.php';

use App\Database\DatabaseManager;
use App\Services\AnalysisService;
use App\Support\AnalysisSessionStore;

final class AnalysisServiceFallbackTest extends TestCase
{
    public function run(): void
    {
        $service = new AnalysisService(
            new class extends DatabaseManager {
                public function __construct()
                {
                }

                public function insertarAnalisis(string $uuid, string $tipoUsuario, string $tipoConflicto, array $datosLaborales, array $documentacion, array $situacion, string $email = ''): int
                {
                    throw new RuntimeException('db offline');
                }
            },
            new class extends \IrilEngine {
                public function calcularIRIL(array $datosLaborales, array $documentacion, array $situacion, string $tipoConflicto, string $tipoUsuario): array
                {
                    return [
                        'score' => 2.4,
                        'detalle' => ['saturacion' => ['valor' => 2.0]],
                        'alertas' => [],
                    ];
                }
            },
            new class extends \EscenariosEngine {
                public function generarEscenarios(array $exposicion, float $irilScore, string $tipoConflicto, string $tipoUsuario, array $situacion, string $provincia = 'CABA'): array
                {
                    return [
                        'escenarios' => ['A' => ['label' => 'Negociación']],
                        'recomendado' => 'A',
                        'tabla_comparativa' => [],
                    ];
                }
            },
            new class extends \ExposicionEngine {
                public function calcularExposicion(array $datosLaborales, array $documentacion, array $situacion, string $tipoConflicto, string $tipoUsuario): array
                {
                    return [
                        'total' => 1000000,
                        'total_base' => 800000,
                        'total_con_multas' => 1000000,
                        'salario_base' => 350000,
                        'conceptos' => [],
                    ];
                }
            }
        );

        $resultado = $service->procesar([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'despido_sin_causa',
            'datos_laborales' => [
                'salario' => '350000',
                'antiguedad_meses' => '24',
                'provincia' => 'CABA',
                'cantidad_empleados' => '25',
            ],
            'situacion' => [
                'urgencia' => 'media',
            ],
        ]);

        $this->assertNotNull($resultado['uuid']);
        $this->assertSame('2026-04', $resultado['schema_version']);

        $guardado = AnalysisSessionStore::fetch($resultado['uuid']);
        $this->assertNotNull($guardado, 'Expected session fallback analysis to be available');
        $this->assertSame($resultado['uuid'], $guardado['uuid']);
        $this->assertSame('empleado', $guardado['tipo_usuario']);
        $this->assertSame('despido_sin_causa', $guardado['tipo_conflicto']);
        $this->assertSame(2.4, floatval($guardado['iril_score']));
        $this->assertSame('A', $guardado['escenario_recomendado']);
        $this->assertSame('media', (json_decode($guardado['situacion_json'], true) ?? [])['urgencia'] ?? null);
    }
}
