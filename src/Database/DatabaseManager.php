<?php
namespace App\Database;

use Exception;
use mysqli;
use mysqli_stmt;

require_once __DIR__ . '/../../config/config.php';

class DatabaseManager {
    private mysqli $db;

    public function __construct() {
        try {
            $this->db = new mysqli(ML_DB_HOST, ML_DB_USER, ML_DB_PASS, ML_DB_NAME, ML_DB_PORT);

            if ($this->db->connect_error) {
                throw new Exception('Error conexión: ' . $this->db->connect_error);
            }

            $this->db->set_charset(ML_DB_CHARSET);
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    public function getConnection(): mysqli {
        return $this->db;
    }

    public function insertarAnalisis(string $uuid, string $tipoUsuario, string $tipoConflicto, array $datosLaborales, array $documentacion, array $situacion, string $email = ''): int {
        $stmt = null;

        try {
            $sql = 'INSERT INTO analisis_laborales
                (uuid, tipo_usuario, tipo_conflicto, datos_laborales, documentacion_json, situacion_json, email, ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $this->prepare($sql);

            $datosJson = json_encode($datosLaborales, JSON_UNESCAPED_UNICODE);
            $docJson = json_encode($documentacion, JSON_UNESCAPED_UNICODE);
            $situJson = json_encode($situacion, JSON_UNESCAPED_UNICODE);
            $email = trim($email);
            $emailVal = $email !== '' ? $email : null;
            $ip = ml_ip();

            $stmt->bind_param('ssssssss', $uuid, $tipoUsuario, $tipoConflicto, $datosJson, $docJson, $situJson, $emailVal, $ip);
            $this->execute($stmt, 'Error INSERT análisis');

            $id = $this->db->insert_id;
            ml_logear("Análisis insertado: ID={$id} UUID={$uuid} tipo={$tipoUsuario}", 'info', 'analisis.log');
            return $id;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function actualizarResultados(int $id, array $irilResultado, array $exposicion, array $escenarios, string $escenarioRecomendado = ''): bool {
        $stmt = null;

        try {
            $sql = 'UPDATE analisis_laborales SET
                iril_score = ?,
                iril_detalle = ?,
                exposicion_json = ?,
                escenarios_json = ?,
                escenario_recomendado = ?
                WHERE id = ?';
            $stmt = $this->prepare($sql);

            $irilScore = floatval($irilResultado['score'] ?? 0);
            $irilDetJson = json_encode($irilResultado, JSON_UNESCAPED_UNICODE);
            $exposJson = json_encode($exposicion, JSON_UNESCAPED_UNICODE);
            $escenJson = json_encode($escenarios, JSON_UNESCAPED_UNICODE);
            $escRec = strtoupper($escenarioRecomendado ?: ($escenarios['recomendado'] ?? ''));
            $id = intval($id);

            $stmt->bind_param('dssssi', $irilScore, $irilDetJson, $exposJson, $escenJson, $escRec, $id);
            $this->execute($stmt, 'Error UPDATE resultados');

            ml_logear("Resultados actualizados: ID={$id} IRIL={$irilScore}", 'info', 'analisis.log');
            return true;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function obtenerAnalisisPorUUID(string $uuid): ?array {
        $stmt = null;
        try {
            $stmt = $this->prepare('SELECT * FROM analisis_laborales WHERE uuid = ? LIMIT 1');
            $stmt->bind_param('s', $uuid);
            $this->execute($stmt, 'Error SELECT por UUID');
            $res = $stmt->get_result();
            $fila = $res->fetch_assoc();
            return $fila ?: null;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function obtenerAnalisisPorId(int $id): ?array {
        $stmt = null;
        try {
            $stmt = $this->prepare('SELECT * FROM analisis_laborales WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $this->execute($stmt, 'Error SELECT por ID');
            $res = $stmt->get_result();
            $fila = $res->fetch_assoc();
            return $fila ?: null;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function listarAnalisis(array $filtros = [], int $limite = 50, int $offset = 0): array {
        $stmt = null;
        try {
            $sql = 'SELECT id, uuid, tipo_usuario, tipo_conflicto, iril_score, escenario_recomendado, accion_tomada, email, ip, fecha_creacion FROM analisis_laborales WHERE 1=1';
            $types = '';
            $values = [];
            $camposPermitidos = ['tipo_usuario', 'tipo_conflicto', 'accion_tomada'];

            foreach ($camposPermitidos as $campo) {
                if (!array_key_exists($campo, $filtros) || $filtros[$campo] === '' || $filtros[$campo] === null) {
                    continue;
                }

                $sql .= " AND {$campo} = ?";
                $types .= 's';
                $values[] = (string) $filtros[$campo];
            }

            if (isset($filtros['iril_min']) && $filtros['iril_min'] !== '') {
                $sql .= ' AND iril_score >= ?';
                $types .= 'd';
                $values[] = floatval($filtros['iril_min']);
            }

            $sql .= ' ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?';
            $types .= 'ii';
            $values[] = intval($limite);
            $values[] = intval($offset);

            $stmt = $this->prepare($sql);
            if ($values !== []) {
                $this->bindDynamicParams($stmt, $types, $values);
            }
            $this->execute($stmt, 'Error listar análisis');

            $res = $stmt->get_result();
            $lista = [];
            while ($fila = $res->fetch_assoc()) {
                $lista[] = $fila;
            }
            return $lista;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function registrarAccion(string $uuid, string $accion, string $tramiteUuid = ''): bool {
        $stmt = null;
        try {
            $stmt = $this->prepare('UPDATE analisis_laborales SET accion_tomada = ?, tramite_uuid = ? WHERE uuid = ?');
            $tramiteVal = $tramiteUuid !== '' ? $tramiteUuid : null;
            $stmt->bind_param('sss', $accion, $tramiteVal, $uuid);
            $this->execute($stmt, 'Error UPDATE acción');
            return true;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        } finally {
            $stmt?->close();
        }
    }

    public function obtenerEstadisticas(): array {
        try {
            $stats = [];
            $r = $this->db->query('SELECT COUNT(*) AS total FROM analisis_laborales');
            $stats['total'] = $r ? intval($r->fetch_assoc()['total']) : 0;
            $r = $this->db->query('SELECT tipo_usuario, COUNT(*) AS cantidad FROM analisis_laborales GROUP BY tipo_usuario');
            $stats['por_tipo_usuario'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['por_tipo_usuario'][$f['tipo_usuario']] = $f['cantidad'];
            $r = $this->db->query('SELECT AVG(iril_score) AS promedio FROM analisis_laborales WHERE iril_score > 0');
            $stats['iril_promedio'] = $r ? round($r->fetch_assoc()['promedio'], 1) : 0;
            $r = $this->db->query('SELECT SUM(iril_score < 2) AS bajo, SUM(iril_score >= 2 AND iril_score < 3) AS moderado, SUM(iril_score >= 3 AND iril_score < 4) AS alto, SUM(iril_score >= 4) AS critico FROM analisis_laborales WHERE iril_score > 0');
            $stats['distribucion_iril'] = $r ? $r->fetch_assoc() : [];
            $r = $this->db->query('SELECT tipo_conflicto, COUNT(*) AS cantidad FROM analisis_laborales GROUP BY tipo_conflicto ORDER BY cantidad DESC LIMIT 5');
            $stats['top_conflictos'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['top_conflictos'][] = $f;
            $r = $this->db->query('SELECT accion_tomada, COUNT(*) AS cantidad FROM analisis_laborales GROUP BY accion_tomada');
            $stats['acciones'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['acciones'][$f['accion_tomada']] = $f['cantidad'];
            $r = $this->db->query('SELECT COUNT(*) AS recientes FROM analisis_laborales WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            $stats['ultimos_7_dias'] = $r ? intval($r->fetch_assoc()['recientes']) : 0;
            return $stats;
        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            return [];
        }
    }

    public function logEmail(int $analisisId, string $destinatario, string $asunto, bool $enviado, string $error = ''): void {
        $stmt = null;
        try {
            $stmt = $this->prepare('INSERT INTO email_logs_motor (analisis_id, destinatario, asunto, estado, error_mensaje) VALUES (?, ?, ?, ?, ?)');
            $estado = $enviado ? 'enviado' : 'fallido';
            $errorVal = $error !== '' ? $error : null;
            $stmt->bind_param('issss', $analisisId, $destinatario, $asunto, $estado, $errorVal);
            $this->execute($stmt, 'Error logEmail');
        } catch (Exception $e) {
            ml_logear('Error logEmail: ' . $e->getMessage(), 'error', 'email.log');
        } finally {
            $stmt?->close();
        }
    }

    public function __destruct() {
        if (isset($this->db)) {
            $this->db->close();
        }
    }

    private function prepare(string $sql): mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $this->db->error);
        }

        return $stmt;
    }

    private function execute(mysqli_stmt $stmt, string $errorPrefix): void
    {
        if (!$stmt->execute()) {
            throw new Exception($errorPrefix . ': ' . $stmt->error);
        }
    }

    private function bindDynamicParams(mysqli_stmt $stmt, string $types, array $values): void
    {
        $params = [$types];
        foreach ($values as $index => $value) {
            $params[] = &$values[$index];
        }

        call_user_func_array([$stmt, 'bind_param'], $params);
    }
}
