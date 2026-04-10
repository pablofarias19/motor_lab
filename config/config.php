<?php
/**
 * config.php — Configuración central del Motor de Riesgo Laboral
 *
 * Define constantes de conexión a la base de datos exclusiva del módulo,
 * configuración de email, rutas del sistema, zona horaria y funciones
 * utilitarias compartidas por todos los archivos del módulo.
 *
 * IMPORTANTE: Esta BD es independiente. No modifica ni toca las bases
 * u580580751_tramites ni u580580751_expedientes.
 *
 * SQL para crear la BD (ejecutar en Hostinger phpMyAdmin):
 * ─────────────────────────────────────────────────────────
 * CREATE DATABASE u580580751_motor_laboral
 *   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 * USE u580580751_motor_laboral;
 *
 * CREATE TABLE analisis_laborales (
 *   id                  INT AUTO_INCREMENT PRIMARY KEY,
 *   uuid                VARCHAR(36)  UNIQUE NOT NULL,
 *   tipo_usuario        ENUM('empleado','empleador') NOT NULL,
 *   tipo_conflicto      VARCHAR(80)  NOT NULL,
 *   datos_laborales     LONGTEXT     NOT NULL COMMENT 'JSON: salario, antiguedad, provincia, categoria, cct',
 *   documentacion_json  LONGTEXT     NOT NULL COMMENT 'JSON: telegramas, recibos, testigos, arca',
 *   situacion_json      LONGTEXT     NOT NULL COMMENT 'JSON: plazo, intercambio, urgencia',
 *   iril_score          DECIMAL(3,1) NOT NULL DEFAULT 0,
 *   iril_detalle        LONGTEXT     NULL     COMMENT 'JSON: desglose por dimension',
 *   exposicion_json     LONGTEXT     NULL     COMMENT 'JSON: montos estimados por concepto',
 *   escenario_recomendado CHAR(1)    NULL,
 *   escenarios_json     LONGTEXT     NULL     COMMENT 'JSON: 4 escenarios A/B/C/D',
 *   accion_tomada       ENUM('ver_informe','contacto','tramite','ninguna') DEFAULT 'ninguna',
 *   tramite_uuid        VARCHAR(36)  NULL     COMMENT 'Referencia al sistema de tramites si se deriva',
 *   email               VARCHAR(200) NULL,
 *   ip                  VARCHAR(45)  NOT NULL,
 *   fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_uuid (uuid),
 *   INDEX idx_tipo (tipo_usuario, tipo_conflicto),
 *   INDEX idx_iril (iril_score),
 *   INDEX idx_fecha (fecha_creacion)
 * );
 *
 * CREATE TABLE alertas_laborales (
 *   id                INT AUTO_INCREMENT PRIMARY KEY,
 *   analisis_id       INT          NOT NULL,
 *   tipo_alerta       VARCHAR(80)  NOT NULL COMMENT 'prescripcion, telegrama, inspeccion, etc.',
 *   descripcion       TEXT         NOT NULL,
 *   fecha_vencimiento DATE         NULL,
 *   activa            TINYINT(1)   DEFAULT 1,
 *   fecha_creacion    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_analisis (analisis_id),
 *   INDEX idx_activa (activa)
 * );
 *
 * CREATE TABLE email_logs_motor (
 *   id             INT AUTO_INCREMENT PRIMARY KEY,
 *   analisis_id    INT          NOT NULL,
 *   destinatario   VARCHAR(200) NOT NULL,
 *   asunto         VARCHAR(300) NOT NULL,
 *   estado         ENUM('enviado','fallido') DEFAULT 'enviado',
 *   error_mensaje  TEXT         NULL,
 *   fecha          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_analisis (analisis_id)
 * );
 * ─────────────────────────────────────────────────────────
 */

// ─── Zona horaria ────────────────────────────────────────────────────────────
date_default_timezone_set('America/Argentina/Buenos_Aires');

// ─── Entorno ─────────────────────────────────────────────────────────────────
// Cambiar a false en producción para ocultar errores al público
define('ML_DEBUG', true);

if (ML_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ─── Base de datos exclusiva del módulo ──────────────────────────────────────
define('ML_DB_HOST', 'localhost');
define('ML_DB_USER', 'u580580751_1905');     // usuario con permisos sobre la BD nueva
define('ML_DB_PASS', 'Lucia1319+');           // ⚠️ mover a .env antes de producción
define('ML_DB_NAME', 'u580580751_motor_laboral');
define('ML_DB_CHARSET', 'utf8mb4');
define('ML_DB_PORT', 3306);

// ─── Email (mismo servidor que el resto del proyecto) ────────────────────────
define('ML_SMTP_FROM', 'estudio@fariasortiz.com.ar');
define('ML_SMTP_FROM_NAME', 'Estudio Farias Ortiz');
define('ML_EMAIL_ADMIN', 'pablofarias19@gmail.com');

// ─── Aplicación ──────────────────────────────────────────────────────────────
define('ML_VERSION', '1.0.0');
define('ML_APP_NAME', 'Motor de Riesgo Laboral');
define('ML_APP_URL', 'https://fariasortiz.com.ar/motor_laboral');

// ─── Rutas absolutas del módulo ──────────────────────────────────────────────
// __DIR__ apunta a la carpeta /config — subimos un nivel para la raíz del módulo
define('ML_ROOT', dirname(__DIR__));
define('ML_LOG_PATH', ML_ROOT . '/logs');

// Ruta a FPDF (se reutiliza la librería del sistema /document sin copiarla)
define('ML_FPDF_PATH', dirname(ML_ROOT) . '/document/fpdf.php');

// ─── Admin ───────────────────────────────────────────────────────────────────
// Token de acceso al panel admin — cambiar antes de producción
define('ML_ADMIN_TOKEN', 'motor2024admin');

// ─── Cabeceras de seguridad ───────────────────────────────────────────────────
// Solo se aplican en respuestas no-PDF
function ml_security_headers(): void
{
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}
ml_security_headers();

// ─────────────────────────────────────────────────────────────────────────────
// FUNCIONES UTILITARIAS
// Adaptadas del patrón en /config/config.php del sistema principal
// ─────────────────────────────────────────────────────────────────────────────

/**
 * ml_logear() — Escribe una línea en el archivo de log correspondiente.
 *
 * @param string $mensaje  Texto a registrar
 * @param string $nivel    'info' | 'error' | 'warning'
 * @param string $archivo  Nombre del archivo de log (dentro de /logs)
 */
function ml_logear(string $mensaje, string $nivel = 'info', string $archivo = 'analisis.log'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $entrada = "[{$timestamp}] [{$nivel}] {$mensaje}\n";
    $rutaLog = ML_LOG_PATH . '/' . $archivo;
    error_log($entrada, 3, $rutaLog);
}

/**
 * ml_respuesta() — Devuelve una respuesta JSON estandarizada y termina la ejecución.
 *
 * @param bool        $success   true = éxito, false = error
 * @param string      $message   Mensaje legible
 * @param mixed|null  $data      Datos opcionales a incluir
 * @param mixed|null  $errors    Errores opcionales
 * @param int         $httpCode  Código HTTP (200, 400, 500...)
 */
function ml_respuesta(bool $success, string $message, $data = null, $errors = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');

    $response = [
        'success' => $success,
        'message' => $message,
        'version' => ML_VERSION
    ];

    if ($data !== null)
        $response['data'] = $data;
    if ($errors !== null)
        $response['errors'] = $errors;

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * ml_uuid() — Genera un UUID v4 único para identificar cada análisis.
 *
 * @return string UUID en formato xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 */
function ml_uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * ml_ip() — Obtiene la IP real del cliente considerando proxies.
 *
 * @return string Dirección IP
 */
function ml_ip(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

/**
 * ml_sanitizar() — Limpia un string para uso seguro en HTML o SQL.
 *
 * @param string        $input  Valor a limpiar
 * @param mysqli|null   $db     Si se pasa la conexión, usa real_escape_string
 * @return string
 */
function ml_sanitizar(string $input, ?mysqli $db = null): string
{
    $input = trim($input);
    if ($db)
        return $db->real_escape_string($input);
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * ml_validar_email() — Valida formato de email.
 *
 * @param string $email
 * @return bool
 */
function ml_validar_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * ml_conectar_bd() — Crea y devuelve una conexión mysqli a la BD del módulo.
 * Lanza excepción si no puede conectar.
 *
 * @return mysqli
 * @throws Exception
 */
function ml_conectar_bd(): mysqli
{
    try {
        $db = new mysqli(ML_DB_HOST, ML_DB_USER, ML_DB_PASS, ML_DB_NAME, ML_DB_PORT);

        if ($db->connect_error) {
            throw new Exception('Error conexión BD Motor Laboral: ' . $db->connect_error);
        }

        $db->set_charset(ML_DB_CHARSET);
        return $db;

    } catch (Exception $e) {
        ml_logear($e->getMessage(), 'error', 'error.log');
        throw $e;
    }
}

/**
 * ml_formato_moneda() — Formatea un número como moneda argentina.
 * Ejemplo: 125000.50 → "$ 125.000,50"
 *
 * @param float $monto
 * @return string
 */
function ml_formato_moneda(float $monto): string
{
    return '$ ' . number_format($monto, 2, ',', '.');
}

/**
 * ml_nivel_iril() — Devuelve etiqueta y color CSS según el score IRIL.
 *
 * @param float $score  Valor entre 1.0 y 5.0
 * @return array ['nivel' => string, 'color' => string, 'descripcion' => string]
 */
function ml_nivel_iril(float $score): array
{
    if ($score < 2.0)
        return [
            'nivel' => 'Bajo',
            'color' => '#27ae60',
            'clase' => 'iril-bajo',
            'descripcion' => 'Situación de baja complejidad procesal. Gestión autónoma posible.'
        ];
    if ($score < 3.0)
        return [
            'nivel' => 'Moderado',
            'color' => '#f39c12',
            'clase' => 'iril-moderado',
            'descripcion' => 'Complejidad media. Se recomienda consulta profesional preventiva.'
        ];
    if ($score < 4.0)
        return [
            'nivel' => 'Alto',
            'color' => '#e67e22',
            'clase' => 'iril-alto',
            'descripcion' => 'Alto riesgo estructural. Intervención profesional recomendada.'
        ];
    return [
        'nivel' => 'Crítico',
        'color' => '#e74c3c',
        'clase' => 'iril-critico',
        'descripcion' => 'Situación crítica. Requiere intervención profesional urgente.'
    ];
}
