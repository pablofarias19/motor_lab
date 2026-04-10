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

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ml_respuesta(false, 'Método no permitido.', null, null, 405);
}

// Solo aceptar JSON
header('Content-Type: application/json; charset=UTF-8');

$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    ml_respuesta(false, 'No se recibieron datos.', null, null, 400);
}

if (strlen($rawInput) > 512000) {
    ml_respuesta(false, 'Payload demasiado grande.', null, null, 413);
}

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ml_respuesta(false, 'JSON inválido: ' . json_last_error_msg(), null, null, 400);
}

try {
    $controller = new \App\Controllers\AnalisisController();
    $resultado = $controller->procesar($input);
    
    ml_respuesta(true, 'Análisis procesado correctamente.', $resultado);
} catch (\Exception $e) {
    ml_respuesta(false, $e->getMessage(), null, null, 500);
}
