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

$requestId = substr(ml_uuid(), 0, 8);
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
    
    $logRequest('ok', 200, [
        'schema_version' => $resultado['schema_version'] ?? null,
        'tipo_usuario' => $input['tipo_usuario'] ?? null,
        'tipo_conflicto' => $input['tipo_conflicto'] ?? null,
        'request_id' => $requestId,
    ]);
    ml_respuesta(true, 'Análisis procesado correctamente.', $resultado);
} catch (\App\Support\InvalidPayloadException $e) {
    $errors = $e->getErrors();
    $logRequest('payload_invalido', 422, [
        'request_id' => $requestId,
        'tipo_usuario' => $input['tipo_usuario'] ?? null,
        'tipo_conflicto' => $input['tipo_conflicto'] ?? null,
        'errores' => array_keys($errors),
    ]);
    ml_respuesta(false, $e->getMessage(), null, $errors, 422);
} catch (\Throwable $e) {
    ml_logear(sprintf('[api/procesar_analisis][%s] %s', $requestId, $e->getMessage()), 'error', 'error.log');
    $logRequest('error', 500, [
        'request_id' => $requestId,
        'tipo_usuario' => $input['tipo_usuario'] ?? null,
        'tipo_conflicto' => $input['tipo_conflicto'] ?? null,
        'error_class' => get_class($e),
    ]);
    ml_respuesta(false, 'Ocurrió un error interno al procesar el análisis.', null, [
        'request_id' => $requestId,
    ], 500);
}
