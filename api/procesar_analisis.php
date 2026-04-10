<?php
/**
 * procesar_analisis.php — Endpoint principal del Motor de Riesgo Laboral
 *
 * Recibe los datos del wizard (POST JSON), invoca el controlador de la API
 * y devuelve la respuesta.
 */

require_once dirname(__DIR__) . '/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/ripte_functions.php';

$requestStart = microtime(true);
$logRequest = static function (string $estado, int $httpCode, array $contexto = []) use ($requestStart): void {
    ml_log_metric('api.procesar_analisis', [
        'estado' => $estado,
        'http_code' => $httpCode,
        'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'duracion_ms' => round((microtime(true) - $requestStart) * 1000, 2),
    ] + $contexto);
};

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $logRequest('metodo_no_permitido', 405);
    ml_respuesta(false, 'Método no permitido.', null, null, 405);
}

// Solo aceptar JSON
header('Content-Type: application/json; charset=UTF-8');

$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    $logRequest('payload_vacio', 400);
    ml_respuesta(false, 'No se recibieron datos.', null, null, 400);
}

if (strlen($rawInput) > 512000) {
    $logRequest('payload_grande', 413, ['payload_bytes' => strlen($rawInput)]);
    ml_respuesta(false, 'Payload demasiado grande.', null, null, 413);
}

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $logRequest('json_invalido', 400);
    ml_respuesta(false, 'JSON inválido: ' . json_last_error_msg(), null, null, 400);
}

try {
    $controller = new \App\Controllers\AnalisisController();
    $resultado = $controller->procesar($input);
    
    $logRequest('ok', 200, ['schema_version' => $resultado['schema_version'] ?? null]);
    ml_respuesta(true, 'Análisis procesado correctamente.', $resultado);
} catch (\Exception $e) {
    $logRequest('error', 500, ['error' => $e->getMessage()]);
    ml_respuesta(false, $e->getMessage(), null, null, 500);
}
