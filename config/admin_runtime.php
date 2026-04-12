<?php
/**
 * admin_runtime.php — Capa paralela y editable para textos, prompts y overrides.
 *
 * Guarda una configuración administrable en un archivo PHP separado del motor base,
 * para poder modificar contenidos y parámetros sin tocar la lógica original.
 */

if (!function_exists('ml_array_deep_merge')) {
    function ml_array_deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = ml_array_deep_merge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}

if (!function_exists('ml_admin_runtime_storage_path')) {
    function ml_admin_runtime_storage_path(): string
    {
        $configured = trim((string) ml_env('ML_ADMIN_RUNTIME_CONFIG_PATH', ''));
        if ($configured !== '') {
            return $configured;
        }

        return ML_ROOT . '/config/admin_runtime_store.php';
    }
}

if (!function_exists('ml_admin_runtime_defaults')) {
    function ml_admin_runtime_defaults(): array
    {
        return [
            'ui' => [
                'dano_complementario' => [
                    'intro' => 'Este cuadro refleja un extra potencial sobre la indemnización base por extinción y se desglosa en tres rubros para que el total no quede aislado ni sin contexto. Sirve para entender de dónde sale el monto complementario y qué parte responde a afectación personal, cuál a impacto económico indirecto y cuál a proyección profesional futura.',
                    'reputacional_criterio' => 'El rubro reputacional no nace por defecto. Solo se activa cuando el criterio configurado detecta una afectación proyectable sobre la posición profesional futura.',
                ],
                'escenario_preventivo' => [
                    'accent_color' => '#0f766e',
                    'badge_label' => 'Escenario preventivo',
                    'clarification' => 'Escenario exclusivo para empleadores y para contextos con margen real de regularización. El beneficio debe leerse como ahorro potencial o contingencia evitada, no como ingreso directo.',
                    'economic_reading_empleador' => 'En este escenario preventivo, el beneficio debe leerse como ahorro potencial para la parte empleadora: contingencias, sanciones y litigios evitados mediante regularización. No representa una ganancia inmediata, sino costo futuro evitado.',
                    'economic_reading_general' => 'En este escenario preventivo, el beneficio no representa una ganancia directa para la parte reclamante. El modelo lo muestra como referencia de ahorro o contingencia evitada para quien regulariza, por eso requiere una lectura especialmente cautelosa.',
                ],
            ],
            'calculation_rules' => [
                'dano_complementario' => [
                    'reputacional' => [
                        'enabled' => true,
                        'requires_violence' => true,
                        'allowed_types' => ['constructivo'],
                        'percentages' => [
                            'despido' => 0.00,
                            'renuncia_previa' => 0.00,
                            'constructivo' => 0.15,
                            'suspensión' => 0.00,
                            'default' => 0.00,
                        ],
                    ],
                ],
                'parametros_motor_overrides' => [],
            ],
            'prompts' => [
                'resumen_informativo' => 'Explicá el resultado del Motor Laboral en español claro, con foco en riesgos, montos y próximos pasos. Indicá siempre qué parte del análisis es orientativa y qué requiere validación profesional.',
                'analisis_preventivo' => 'Describí el escenario preventivo para empleadores como una herramienta de regularización y ahorro de contingencias, aclarando condiciones de aplicación, límites y prioridades de implementación.',
                'actualizacion_contenido' => 'Tomá nueva normativa, criterios de cálculo o textos explicativos y proponé una redacción operativa para integrarla al Motor Laboral manteniendo claridad, trazabilidad y lenguaje profesional.',
            ],
        ];
    }
}

if (!function_exists('ml_admin_runtime')) {
    function ml_admin_runtime(bool $forceReload = false): array
    {
        static $cache = null;

        if ($forceReload || $cache === null) {
            $defaults = ml_admin_runtime_defaults();
            $path = ml_admin_runtime_storage_path();
            $stored = [];

            if (is_file($path)) {
                $loaded = require $path;
                if (is_array($loaded)) {
                    $stored = $loaded;
                }
            }

            $cache = ml_array_deep_merge($defaults, $stored);
        }

        return $cache;
    }
}

if (!function_exists('ml_admin_runtime_reset_cache')) {
    function ml_admin_runtime_reset_cache(): void
    {
        ml_admin_runtime(true);
    }
}

if (!function_exists('ml_admin_runtime_get')) {
    function ml_admin_runtime_get(string $path, $default = null)
    {
        $segments = array_values(array_filter(explode('.', $path), 'strlen'));
        $current = ml_admin_runtime();

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}

if (!function_exists('ml_admin_runtime_save')) {
    function ml_admin_runtime_save(array $data): void
    {
        $path = ml_admin_runtime_storage_path();
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de configuración editable.');
        }

        $payload = "<?php\nreturn " . var_export($data, true) . ";\n";
        $tempPath = $path . '.tmp';

        if (file_put_contents($tempPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir la configuración editable.');
        }

        if (!rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new RuntimeException('No se pudo activar la nueva configuración editable.');
        }

        ml_admin_runtime(true);
    }
}
