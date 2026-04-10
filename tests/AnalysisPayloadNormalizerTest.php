<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\AnalysisPayloadNormalizer;

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
                // Compatibilidad con payloads legacy que traían el typo histórico.
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
    }
}
