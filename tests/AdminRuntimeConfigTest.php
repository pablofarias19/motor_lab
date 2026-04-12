<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

final class AdminRuntimeConfigTest extends TestCase
{
    public function run(): void
    {
        $previous = getenv('ML_ADMIN_RUNTIME_CONFIG_PATH');
        $tempPath = sys_get_temp_dir() . '/ml_admin_runtime_' . bin2hex(random_bytes(6)) . '.php';

        $_ENV['ML_ADMIN_RUNTIME_CONFIG_PATH'] = $tempPath;
        $_SERVER['ML_ADMIN_RUNTIME_CONFIG_PATH'] = $tempPath;
        putenv('ML_ADMIN_RUNTIME_CONFIG_PATH=' . $tempPath);
        @unlink($tempPath);

        try {
            $defaults = ml_admin_runtime(true);
            $this->assertTrue(is_array($defaults['prompts'] ?? null), 'Se esperaba bloque de prompts por defecto');

            $payload = ml_admin_runtime_defaults();
            $payload['calculation_rules']['dano_complementario']['reputacional']['allowed_types'] = ['despido'];
            $payload['calculation_rules']['dano_complementario']['reputacional']['requires_violence'] = true;
            $payload['calculation_rules']['dano_complementario']['reputacional']['percentages']['despido'] = 0.10;
            $payload['calculation_rules']['parametros_motor_overrides'] = [
                'escenarios' => [
                    'preventivo' => [
                        'duracion_promedio' => 4,
                    ],
                ],
            ];

            ml_admin_runtime_save($payload);

            $runtime = ml_admin_runtime(true);
            $this->assertSame(4, $runtime['calculation_rules']['parametros_motor_overrides']['escenarios']['preventivo']['duracion_promedio']);

            $parametros = require dirname(__DIR__) . '/config/parametros_motor.php';
            $this->assertSame(4, $parametros['escenarios']['preventivo']['duracion_promedio']);

            $sinViolencia = evaluar_dano_complementario(1000000, 200000, 'despido', false, 12);
            $this->assertSame(0.0, floatval($sinViolencia['daño_reputacional'] ?? -1), 'No debería calcular daño reputacional sin cumplir el criterio');

            $conViolencia = evaluar_dano_complementario(1000000, 200000, 'despido', true, 12);
            $this->assertTrue(floatval($conViolencia['daño_reputacional'] ?? 0) > 0, 'Debería calcular daño reputacional cuando se cumple el criterio');
        } finally {
            @unlink($tempPath);

            if ($previous === false) {
                unset($_ENV['ML_ADMIN_RUNTIME_CONFIG_PATH'], $_SERVER['ML_ADMIN_RUNTIME_CONFIG_PATH']);
                putenv('ML_ADMIN_RUNTIME_CONFIG_PATH');
            } else {
                $_ENV['ML_ADMIN_RUNTIME_CONFIG_PATH'] = $previous;
                $_SERVER['ML_ADMIN_RUNTIME_CONFIG_PATH'] = $previous;
                putenv('ML_ADMIN_RUNTIME_CONFIG_PATH=' . $previous);
            }

            ml_admin_runtime(true);
        }
    }
}
