<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\AnalysisPayloadNormalizer;
use App\Support\InvalidPayloadException;

final class AnalysisPayloadNormalizerTest extends TestCase
{
    public function run(): void
    {
        $payload = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'responsabilidad_solidaria',
            'datos_laborales' => [
                'salario' => '1500000.50',
                'antiguedad_meses' => '36',
                'provincia' => 'Buenos Aires',
            ],
            'situacion' => [
                'salarios_historicos' => ['100000', 'abc', 200000],
                // Compatibilidad con payloads legacy que traian el typo historico.
                'principal_verifica_aaportes' => 'si',
                'valida_cuil' => 'si',
                'estado_art' => 'inexistente',
                'dia_despido' => '40',
            ],
            'contacto' => [
                'email' => 'estudio@example.com',
            ],
        ]);

        $this->assertSame('empleador', $payload['tipo_usuario']);
        $this->assertSame('responsabilidad_solidaria', $payload['tipo_conflicto']);
        $this->assertEquals(1500000.50, $payload['datos_laborales']['salario']);
        $this->assertSame(36, $payload['datos_laborales']['antiguedad_meses']);
        $this->assertSame([100000.0, 200000.0], $payload['situacion']['salarios_historicos']);
        $this->assertSame('si', $payload['situacion']['valida_aportes']);
        $this->assertSame('si', $payload['situacion']['principal_verifica_aportes']);
        $this->assertSame('no', $payload['situacion']['tiene_art']);
        $this->assertSame(31, $payload['situacion']['dia_despido']);
        $this->assertSame('estudio@example.com', $payload['contacto']['email']);

        $payloadConFechas = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'diferencias_salariales',
            'datos_laborales' => [
                'salario' => '800000',
                'antiguedad_meses' => '12',
                'provincia' => 'CABA',
            ],
            'situacion' => [
                'urgencia' => 'alta',
                'fecha_despido' => '2026-03-01',
                'fecha_ultimo_telegrama' => '2026-03-15',
                'probabilidad_condena' => '0.7',
            ],
        ]);

        $this->assertSame('2026-03-01', $payloadConFechas['situacion']['fecha_despido']);
        $this->assertEquals(0.7, $payloadConFechas['situacion']['probabilidad_condena']);

        $payloadPreventivo = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'auditoria_preventiva',
            'datos_laborales' => [
                'salario' => '1250000',
                'antiguedad_meses' => '0',
                'provincia' => 'CABA',
                'cantidad_empleados' => '25',
            ],
            'situacion' => [
                'urgencia' => 'media',
                'probabilidad_condena' => '0.35',
            ],
        ]);

        $this->assertSame('auditoria_preventiva', $payloadPreventivo['tipo_conflicto']);
        $this->assertSame(0, $payloadPreventivo['datos_laborales']['antiguedad_meses']);
        $this->assertSame(25, $payloadPreventivo['datos_laborales']['cantidad_empleados']);

        try {
            AnalysisPayloadNormalizer::normalize([
                'tipo_usuario' => 'empleador',
                'tipo_conflicto' => 'accidente_laboral',
                'datos_laborales' => [
                    'salario' => '900000',
                    'antiguedad_meses' => '12',
                    'provincia' => 'Buenos Aires',
                    'edad' => '0',
                ],
                'situacion' => [
                    'fecha_siniestro' => '2026-02-10',
                ],
            ]);

            throw new \RuntimeException('Expected InvalidPayloadException for accident payload without valid age');
        } catch (InvalidPayloadException $e) {
            $errors = $e->getErrors();
            $this->assertSame('Para accidentes, la edad debe estar entre 16 y 90 años.', $errors['datos_laborales.edad']);
        }

        try {
            AnalysisPayloadNormalizer::normalize([
                'tipo_usuario' => 'otro',
                'tipo_conflicto' => 'desconocido',
                'datos_laborales' => [
                    'salario' => '0',
                    'antiguedad_meses' => '-1',
                    'provincia' => '',
                ],
                'situacion' => [
                    'urgencia' => 'critica',
                    'fecha_despido' => '2026-02-30',
                    'probabilidad_condena' => '2',
                ],
            ]);

            throw new \RuntimeException('Expected InvalidPayloadException');
        } catch (InvalidPayloadException $e) {
            $errors = $e->getErrors();
            $this->assertSame('Seleccioná un perfil válido.', $errors['tipo_usuario']);
            $this->assertSame('Seleccioná un tipo de conflicto válido.', $errors['tipo_conflicto']);
            $this->assertSame('El salario debe ser mayor a cero.', $errors['datos_laborales.salario']);
            $this->assertSame('La fecha de despido debe tener formato YYYY-MM-DD.', $errors['situacion.fecha_despido']);
            $this->assertSame('La probabilidad de condena debe estar entre 0 y 1.', $errors['situacion.probabilidad_condena']);
        }
    }
}
