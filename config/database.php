<?php
/**
 * Configuración de conexión a BD del módulo Motor Laboral.
 *
 * Soporta tanto variables ML_DB_* como DB_* para facilitar despliegues
 * compartidos y compatibilidad con otros proyectos.
 */

$env = static function (string $primaryKey, ?string $secondaryKey = null, $default = null) {
    $keys = array_values(array_filter([$primaryKey, $secondaryKey]));

    foreach ($keys as $key) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value !== false && $value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
};

return [
    'host' => $env('ML_DB_HOST', 'DB_HOST', '193.203.175.97'),
    'user' => $env('ML_DB_USER', 'DB_USER', ''),
    'password' => $env('ML_DB_PASS', 'DB_PASS', ''),
    'database' => $env('ML_DB_NAME', 'DB_NAME', 'u580580751_motor_laboral'),
    'port' => (int) $env('ML_DB_PORT', 'DB_PORT', 3306),
    'charset' => $env('ML_DB_CHARSET', 'DB_CHARSET', 'utf8mb4'),
    'collate' => $env('ML_DB_COLLATE', 'DB_COLLATE', 'utf8mb4_unicode_ci'),
];
