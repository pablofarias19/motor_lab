<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/DatabaseManager.php';

use App\Database\DatabaseManager;

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

/**
 * diagnóstico rápido de la conexión y escritura en la BD del módulo.
 * Uso:
 *   - Navegador: /diagnostico_bd.php
 *   - CLI: php diagnostico_bd.php
 */

function diag_print(string $label, string $value): void
{
    echo str_pad($label, 30, ' ') . ': ' . $value . PHP_EOL;
}

function diag_bool(bool $value): string
{
    return $value ? 'SI' : 'NO';
}

function diag_mask_secret(string $value): string
{
    if ($value === '') {
        return 'VACIO';
    }

    $visible = min(2, strlen($value));
    return str_repeat('*', max(0, strlen($value) - $visible)) . substr($value, -$visible);
}

$exitCode = 0;
$dbManager = null;
$conexion = null;
$insertIdTemporal = null;
$transactionStarted = false;

echo '=== DIAGNOSTICO BASE DE DATOS / MOTOR LABORAL ===' . PHP_EOL;
diag_print('Fecha', date('Y-m-d H:i:s'));
diag_print('PHP', PHP_VERSION);
diag_print('SAPI', PHP_SAPI);
diag_print('Archivo .env', diag_bool(file_exists(__DIR__ . '/.env')));
diag_print('Extension mysqli', diag_bool(extension_loaded('mysqli')));
diag_print('ML_DB_HOST', ML_DB_HOST);
diag_print('ML_DB_PORT', (string) ML_DB_PORT);
diag_print('ML_DB_NAME', ML_DB_NAME);
diag_print('ML_DB_USER', ML_DB_USER);
diag_print('ML_DB_PASS', diag_mask_secret(ML_DB_PASS));
diag_print('ML_DB_CHARSET', ML_DB_CHARSET);
echo PHP_EOL;

try {
    $dbManager = new DatabaseManager();
    $conexion = $dbManager->getConnection();

    diag_print('Conexion inicial', 'OK');

    $infoServidor = $conexion->query('SELECT DATABASE() AS db_actual, VERSION() AS version_mysql');
    if ($infoServidor instanceof mysqli_result) {
        $filaServidor = $infoServidor->fetch_assoc() ?: [];
        diag_print('DB conectada', (string) ($filaServidor['db_actual'] ?? '(sin nombre)'));
        diag_print('Version MySQL', (string) ($filaServidor['version_mysql'] ?? '(sin dato)'));
        $infoServidor->free();
    }

    $tablaExiste = false;
    $stmtTabla = $conexion->prepare('SHOW TABLES LIKE ?');
    if ($stmtTabla === false) {
        throw new RuntimeException('No se pudo preparar la verificación de la tabla analisis_laborales.');
    }

    $tabla = 'analisis_laborales';
    $stmtTabla->bind_param('s', $tabla);
    $stmtTabla->execute();
    $resultadoTabla = $stmtTabla->get_result();
    $tablaExiste = $resultadoTabla instanceof mysqli_result && $resultadoTabla->num_rows > 0;
    $resultadoTabla?->free();
    $stmtTabla->close();

    diag_print('Tabla analisis_laborales', $tablaExiste ? 'OK' : 'NO ENCONTRADA');

    if (!$tablaExiste) {
        throw new RuntimeException('La tabla analisis_laborales no existe en la base configurada.');
    }

    $columnas = [];
    $resultadoColumnas = $conexion->query('SHOW COLUMNS FROM analisis_laborales');
    if (!$resultadoColumnas instanceof mysqli_result) {
        throw new RuntimeException('No se pudieron leer las columnas de analisis_laborales.');
    }

    while ($columna = $resultadoColumnas->fetch_assoc()) {
        $nombre = (string) ($columna['Field'] ?? '');
        if ($nombre !== '') {
            $columnas[] = $nombre;
        }
    }
    $resultadoColumnas->free();

    $columnasRequeridas = [
        'id',
        'uuid',
        'tipo_usuario',
        'tipo_conflicto',
        'datos_laborales',
        'documentacion_json',
        'situacion_json',
        'iril_score',
        'iril_detalle',
        'exposicion_json',
        'escenarios_json',
        'escenario_recomendado',
        'email',
        'ip',
    ];

    $faltantes = array_values(array_diff($columnasRequeridas, $columnas));
    diag_print(
        'Columnas requeridas',
        $faltantes === [] ? 'OK' : 'FALTAN: ' . implode(', ', $faltantes)
    );

    if ($faltantes !== []) {
        throw new RuntimeException('La tabla analisis_laborales no tiene todas las columnas que usa el módulo.');
    }

    $conexion->begin_transaction();
    $transactionStarted = true;

    $uuidTemporal = 'diag-' . bin2hex(random_bytes(14));
    $insertIdTemporal = $dbManager->insertarAnalisis(
        $uuidTemporal,
        'empleado',
        'despido_sin_causa',
        [
            'salario' => 100000,
            'antiguedad_meses' => 12,
            'provincia' => 'CABA',
            'fecha_despido' => date('Y-m-d'),
        ],
        [
            'recibos' => 'si',
            'telegramas' => 'no',
        ],
        [
            'urgencia' => 'media',
        ],
        ''
    );

    $dbManager->actualizarResultados(
        $insertIdTemporal,
        [
            'score' => 2.5,
            'detalle' => ['diagnostico' => true],
        ],
        [
            'total' => 100000,
            'conceptos' => [],
        ],
        [
            'recomendado' => 'B',
            'escenarios' => [],
        ],
        'B'
    );

    diag_print('Prueba INSERT/UPDATE', 'OK');
    diag_print('Registro temporal', 'ID ' . (string) $insertIdTemporal . ' (rollback pendiente)');
} catch (Throwable $e) {
    $exitCode = 1;
    diag_print('Estado general', 'ERROR');
    diag_print('Clase error', get_class($e));
    diag_print('Mensaje error', $e->getMessage());
} finally {
    if ($conexion instanceof mysqli) {
        if ($transactionStarted) {
            try {
                $conexion->rollback();
                diag_print('Rollback', 'OK');
            } catch (Throwable $rollbackError) {
                diag_print('Rollback', 'ERROR: ' . $rollbackError->getMessage());
                $exitCode = 1;
            }
        } elseif ($insertIdTemporal !== null) {
            $conexion->query('DELETE FROM analisis_laborales WHERE id = ' . intval($insertIdTemporal));
        }
    }
}

if ($exitCode === 0) {
    diag_print('Estado general', 'OK');
    echo PHP_EOL . 'Diagnóstico finalizado sin errores de conexión ni escritura.' . PHP_EOL;
} else {
    echo PHP_EOL . 'Diagnóstico finalizado con errores. Revisá los mensajes anteriores y logs/error.log.' . PHP_EOL;
}

exit($exitCode);
