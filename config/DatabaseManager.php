<?php
/**
 * DatabaseManager.php — Gestor de base de datos del Motor de Riesgo Laboral
 *
 * Encapsula todas las operaciones CRUD sobre la base de datos exclusiva
 * u580580751_motor_laboral. Cada método usa real_escape_string para
 * prevención de SQL injection y registra operaciones en los logs.
 *
 * Patrón adaptado de /config/DatabaseManager.php del sistema principal.
 * No modifica ni accede a las BDs tramites ni expedientes.
 */

require_once __DIR__ . '/config.php';

class DatabaseManager {

    /** @var mysqli Conexión activa a la BD del módulo */
    private mysqli $db;

    // ─────────────────────────────────────────────────────────────────────────
    // CONSTRUCTOR — conecta al instanciar
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Constructor: establece la conexión a la BD exclusiva del módulo.
     * Lanza excepción si no puede conectar.
     *
     * @throws Exception si falla la conexión
     */
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

    // ─────────────────────────────────────────────────────────────────────────
    // Getter de conexión — para funciones externas que necesiten mysqli
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * getConnection() — Retorna la conexión mysqli activa
     * Usada por ripte_functions.php y otras librerías
     *
     * @return mysqli
     */
    public function getConnection(): mysqli {
        return $this->db;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ANÁLISIS — operaciones sobre la tabla analisis_laborales
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * insertarAnalisis() — Guarda un nuevo análisis de riesgo laboral.
     *
     * @param string $uuid              UUID generado previamente
     * @param string $tipoUsuario       'empleado' o 'empleador'
     * @param string $tipoConflicto     Tipo de conflicto laboral
     * @param array  $datosLaborales    Salario, antigüedad, provincia, etc.
     * @param array  $documentacion     Telegramas, recibos, testigos, etc.
     * @param array  $situacion         Plazo, intercambio, urgencia
     * @param string $email             Email del usuario (puede estar vacío)
     * @return int ID del registro insertado
     * @throws Exception si falla el INSERT
     */
    public function insertarAnalisis(
        string $uuid,
        string $tipoUsuario,
        string $tipoConflicto,
        array  $datosLaborales,
        array  $documentacion,
        array  $situacion,
        string $email = ''
    ): int {
        try {
            $uuid          = $this->db->real_escape_string($uuid);
            $tipoUsuario   = $this->db->real_escape_string($tipoUsuario);
            $tipoConflicto = $this->db->real_escape_string($tipoConflicto);
            $datosJson     = $this->db->real_escape_string(json_encode($datosLaborales, JSON_UNESCAPED_UNICODE));
            $docJson       = $this->db->real_escape_string(json_encode($documentacion, JSON_UNESCAPED_UNICODE));
            $situJson      = $this->db->real_escape_string(json_encode($situacion, JSON_UNESCAPED_UNICODE));
            $email         = $this->db->real_escape_string(trim($email));
            $ip            = $this->db->real_escape_string(ml_ip());

            $emailVal = !empty($email) ? "'{$email}'" : 'NULL';

            $sql = "INSERT INTO analisis_laborales
                        (uuid, tipo_usuario, tipo_conflicto,
                         datos_laborales, documentacion_json, situacion_json,
                         email, ip)
                    VALUES
                        ('{$uuid}', '{$tipoUsuario}', '{$tipoConflicto}',
                         '{$datosJson}', '{$docJson}', '{$situJson}',
                         {$emailVal}, '{$ip}')";

            if (!$this->db->query($sql)) {
                throw new Exception('Error INSERT análisis: ' . $this->db->error);
            }

            $id = $this->db->insert_id;
            ml_logear("Análisis insertado: ID={$id} UUID={$uuid} tipo={$tipoUsuario}", 'info', 'analisis.log');
            return $id;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    /**
     * actualizarResultados() — Guarda el IRIL, escenarios y exposición calculados.
     * Se llama después de insertar, una vez que los motores calculan los resultados.
     *
     * @param int    $id                    ID del análisis
     * @param float  $irilScore             Puntaje IRIL (1.0 - 5.0)
     * @param array  $irilDetalle           Desglose por dimensión
     * @param array  $exposicion            Montos estimados por concepto
     * @param array  $escenarios            Array con los 4 escenarios
     * @param string $escenarioRecomendado  'A' | 'B' | 'C' | 'D'
     * @return bool
     * @throws Exception
     */
    public function actualizarResultados(
        int    $id,
        float  $irilScore,
        array  $irilDetalle,
        array  $exposicion,
        array  $escenarios,
        string $escenarioRecomendado
    ): bool {
        try {
            $id          = intval($id);
            $irilScore   = floatval($irilScore);
            $irilDetJson = $this->db->real_escape_string(json_encode($irilDetalle, JSON_UNESCAPED_UNICODE));
            $exposJson   = $this->db->real_escape_string(json_encode($exposicion, JSON_UNESCAPED_UNICODE));
            $escenJson   = $this->db->real_escape_string(json_encode($escenarios, JSON_UNESCAPED_UNICODE));
            $escRec      = $this->db->real_escape_string(strtoupper($escenarioRecomendado));

            $sql = "UPDATE analisis_laborales SET
                        iril_score            = {$irilScore},
                        iril_detalle          = '{$irilDetJson}',
                        exposicion_json       = '{$exposJson}',
                        escenarios_json       = '{$escenJson}',
                        escenario_recomendado = '{$escRec}'
                    WHERE id = {$id}";

            if (!$this->db->query($sql)) {
                throw new Exception('Error UPDATE resultados: ' . $this->db->error);
            }

            ml_logear("Resultados actualizados: ID={$id} IRIL={$irilScore}", 'info', 'analisis.log');
            return true;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    /**
     * obtenerAnalisisPorUUID() — Recupera un análisis completo por su UUID.
     *
     * @param string $uuid
     * @return array|null  Fila como array asociativo, o null si no existe
     */
    public function obtenerAnalisisPorUUID(string $uuid): ?array {
        try {
            $uuid = $this->db->real_escape_string($uuid);
            $sql  = "SELECT * FROM analisis_laborales WHERE uuid = '{$uuid}' LIMIT 1";
            $res  = $this->db->query($sql);

            if (!$res) throw new Exception('Error SELECT por UUID: ' . $this->db->error);

            $fila = $res->fetch_assoc();
            return $fila ?: null;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    /**
     * obtenerAnalisisPorId() — Recupera un análisis por su ID numérico.
     *
     * @param int $id
     * @return array|null
     */
    public function obtenerAnalisisPorId(int $id): ?array {
        try {
            $id  = intval($id);
            $sql = "SELECT * FROM analisis_laborales WHERE id = {$id} LIMIT 1";
            $res = $this->db->query($sql);

            if (!$res) throw new Exception('Error SELECT por ID: ' . $this->db->error);

            $fila = $res->fetch_assoc();
            return $fila ?: null;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    /**
     * listarAnalisis() — Lista análisis con filtros y paginación para el admin.
     *
     * @param array $filtros  Pares campo => valor (tipo_usuario, tipo_conflicto, etc.)
     * @param int   $limite   Máximo de registros
     * @param int   $offset   Para paginación
     * @return array
     */
    public function listarAnalisis(array $filtros = [], int $limite = 50, int $offset = 0): array {
        try {
            $sql = "SELECT id, uuid, tipo_usuario, tipo_conflicto,
                           iril_score, escenario_recomendado,
                           accion_tomada, email, ip, fecha_creacion
                    FROM analisis_laborales WHERE 1=1";

            // Filtros seguros: solo campos permitidos
            $camposPermitidos = ['tipo_usuario', 'tipo_conflicto', 'accion_tomada'];
            foreach ($filtros as $campo => $valor) {
                if (!in_array($campo, $camposPermitidos)) continue;
                $campo = $this->db->real_escape_string($campo);
                $valor = $this->db->real_escape_string($valor);
                $sql  .= " AND {$campo} = '{$valor}'";
            }

            // Filtro por rango IRIL
            if (!empty($filtros['iril_min'])) {
                $sql .= " AND iril_score >= " . floatval($filtros['iril_min']);
            }

            $sql .= " ORDER BY fecha_creacion DESC";
            $sql .= " LIMIT " . intval($limite) . " OFFSET " . intval($offset);

            $res = $this->db->query($sql);
            if (!$res) throw new Exception('Error listar análisis: ' . $this->db->error);

            $lista = [];
            while ($fila = $res->fetch_assoc()) {
                $lista[] = $fila;
            }
            return $lista;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    /**
     * registrarAccion() — Actualiza la acción tomada por el usuario tras ver resultados.
     *
     * @param string $uuid
     * @param string $accion  'ver_informe' | 'contacto' | 'tramite' | 'ninguna'
     * @param string $tramiteUuid  UUID del trámite si se derivó al sistema principal
     * @return bool
     */
    public function registrarAccion(string $uuid, string $accion, string $tramiteUuid = ''): bool {
        try {
            $uuid    = $this->db->real_escape_string($uuid);
            $accion  = $this->db->real_escape_string($accion);
            $tramVal = !empty($tramiteUuid)
                ? "'" . $this->db->real_escape_string($tramiteUuid) . "'"
                : 'NULL';

            $sql = "UPDATE analisis_laborales SET
                        accion_tomada = '{$accion}',
                        tramite_uuid  = {$tramVal}
                    WHERE uuid = '{$uuid}'";

            if (!$this->db->query($sql)) {
                throw new Exception('Error UPDATE acción: ' . $this->db->error);
            }

            return true;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESTADÍSTICAS — para el dashboard admin
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * obtenerEstadisticas() — KPIs para el dashboard del panel admin.
     *
     * @return array Totales, distribución por tipo, promedios IRIL, acciones
     */
    public function obtenerEstadisticas(): array {
        try {
            $stats = [];

            // Total de análisis
            $r = $this->db->query("SELECT COUNT(*) AS total FROM analisis_laborales");
            $stats['total'] = $r ? intval($r->fetch_assoc()['total']) : 0;

            // Por tipo de usuario
            $r = $this->db->query(
                "SELECT tipo_usuario, COUNT(*) AS cantidad
                 FROM analisis_laborales GROUP BY tipo_usuario"
            );
            $stats['por_tipo_usuario'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['por_tipo_usuario'][$f['tipo_usuario']] = $f['cantidad'];

            // IRIL promedio
            $r = $this->db->query("SELECT AVG(iril_score) AS promedio FROM analisis_laborales WHERE iril_score > 0");
            $stats['iril_promedio'] = $r ? round($r->fetch_assoc()['promedio'], 1) : 0;

            // Distribución por nivel IRIL
            $r = $this->db->query(
                "SELECT
                    SUM(iril_score < 2)                     AS bajo,
                    SUM(iril_score >= 2 AND iril_score < 3) AS moderado,
                    SUM(iril_score >= 3 AND iril_score < 4) AS alto,
                    SUM(iril_score >= 4)                    AS critico
                 FROM analisis_laborales WHERE iril_score > 0"
            );
            $stats['distribucion_iril'] = $r ? $r->fetch_assoc() : [];

            // Por conflicto más frecuente
            $r = $this->db->query(
                "SELECT tipo_conflicto, COUNT(*) AS cantidad
                 FROM analisis_laborales
                 GROUP BY tipo_conflicto ORDER BY cantidad DESC LIMIT 5"
            );
            $stats['top_conflictos'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['top_conflictos'][] = $f;

            // Acciones tomadas
            $r = $this->db->query(
                "SELECT accion_tomada, COUNT(*) AS cantidad
                 FROM analisis_laborales GROUP BY accion_tomada"
            );
            $stats['acciones'] = [];
            if ($r) while ($f = $r->fetch_assoc()) $stats['acciones'][$f['accion_tomada']] = $f['cantidad'];

            // Análisis recientes (últimos 7 días)
            $r = $this->db->query(
                "SELECT COUNT(*) AS recientes
                 FROM analisis_laborales
                 WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $stats['ultimos_7_dias'] = $r ? intval($r->fetch_assoc()['recientes']) : 0;

            return $stats;

        } catch (Exception $e) {
            ml_logear($e->getMessage(), 'error', 'error.log');
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMAIL LOGS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * logEmail() — Registra el resultado del envío de un email.
     *
     * @param int    $analisisId
     * @param string $destinatario
     * @param string $asunto
     * @param bool   $enviado
     * @param string $error  Mensaje de error si falló
     */
    public function logEmail(int $analisisId, string $destinatario, string $asunto, bool $enviado, string $error = ''): void {
        try {
            $analisisId   = intval($analisisId);
            $destinatario = $this->db->real_escape_string($destinatario);
            $asunto       = $this->db->real_escape_string($asunto);
            $estado       = $enviado ? 'enviado' : 'fallido';
            $errorEsc     = $this->db->real_escape_string($error);
            $errorVal     = !empty($error) ? "'{$errorEsc}'" : 'NULL';

            $sql = "INSERT INTO email_logs_motor
                        (analisis_id, destinatario, asunto, estado, error_mensaje)
                    VALUES
                        ({$analisisId}, '{$destinatario}', '{$asunto}', '{$estado}', {$errorVal})";

            $this->db->query($sql);

        } catch (Exception $e) {
            ml_logear('Error logEmail: ' . $e->getMessage(), 'error', 'email.log');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTRUCTOR
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Destructor: cierra la conexión al liberar el objeto.
     */
    public function __destruct() {
        if (isset($this->db)) {
            $this->db->close();
        }
    }
}
