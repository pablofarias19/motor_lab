<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/IrilEngine.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';
require_once __DIR__ . '/TestCase.php';

use App\Database\DatabaseManager;
use App\Services\AnalysisService;
use App\Support\AnalysisSessionStore;

final class AnalysisServiceSolidaridadTest extends TestCase
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
                        'score' => 2.5,
                        'detalle' => [],
                        'alertas' => [],
                    ];
                }
            },
            new class extends \EscenariosEngine {
                public function generarEscenarios(array $exposicion, float $irilScore, string $tipoConflicto, string $tipoUsuario, array $situacion, string $provincia = 'CABA'): array
                {
                    return [
                        'escenarios' => [],
                        'recomendado' => 'B',
                        'tabla_comparativa' => [],
                    ];
                }
            },
            new class extends \ExposicionEngine {
                public function calcularExposicion(array $datosLaborales, array $documentacion, array $situacion, string $tipoConflicto, string $tipoUsuario): array
                {
                    return [
                        'total' => 1000000,
                        'total_base' => 1000000,
                        'total_con_multas' => 1000000,
                        'salario_base' => 1000000,
                        'conceptos' => [
                            'responsabilidad_terceros' => [
                                'descripcion' => 'legacy',
                                'monto' => 500000,
                                'base_legal' => 'legacy',
                            ],
                        ],
                    ];
                }
            }
        );

        $resultado = $service->procesar([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'responsabilidad_solidaria',
            'datos_laborales' => [
                'salario' => '1000000',
                'antiguedad_meses' => '12',
                'provincia' => 'CABA',
                'cantidad_empleados' => '25',
            ],
            'situacion' => [
                'actividad_esencial' => 'si',
                'control_documental' => 'si',
                'contrato_formal' => 'si',
                'cantidad_subcontratistas' => '2',
            ],
            'contacto' => [
                'email' => 'solidaria@example.com',
            ],
        ]);

        $guardado = AnalysisSessionStore::fetch($resultado['uuid']);
        $exposicion = json_decode($guardado['exposicion_json'] ?? '[]', true) ?? [];
        $solidaridad = $exposicion['analisis_empresa']['solidaridad'] ?? [];

        $this->assertSame(25, $solidaridad['universo_trabajadores'] ?? null);
        $this->assertSame('18%', $solidaridad['tasa_litigiosidad_esperada'] ?? null);
        $this->assertSame(5, $solidaridad['trabajadores_reclamantes_estimados'] ?? null);
        $this->assertEquals(25000000.0, $exposicion['conceptos']['responsabilidad_terceros']['monto'] ?? null);
        $this->assertEquals(5000000.0, $exposicion['conceptos']['exposicion_solidaria_probable']['monto'] ?? null);
        $this->assertEquals(1000000.0, $exposicion['conceptos']['exposicion_solidaria_esperada']['monto'] ?? null);
        $this->assertEquals(1000000.0, $exposicion['total_con_multas'] ?? null);
    }
}
