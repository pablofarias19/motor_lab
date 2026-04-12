<?php
namespace App\Support;

final class AnalysisSessionStore
{
    private const SESSION_KEY = 'ml_analisis_temporales';
    private const TTL_SECONDS = 7200;
    private static array $memoryStore = [];

    public static function remember(array $record): void
    {
        $uuid = trim((string) ($record['uuid'] ?? ''));
        if ($uuid === '') {
            return;
        }

        $record['__stored_at'] = time();

        if (!self::bootSession()) {
            self::purgeExpiredMemory();
            self::$memoryStore[$uuid] = $record;
            return;
        }

        self::purgeExpiredSession();
        $_SESSION[self::SESSION_KEY][$uuid] = $record;
    }

    public static function fetch(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        if (!self::bootSession()) {
            self::purgeExpiredMemory();
            $record = self::$memoryStore[$uuid] ?? null;
            if (!is_array($record)) {
                return null;
            }

            unset($record['__stored_at']);
            return $record;
        }

        self::purgeExpiredSession();
        $record = $_SESSION[self::SESSION_KEY][$uuid] ?? null;
        if (!is_array($record)) {
            return null;
        }

        unset($record['__stored_at']);
        return $record;
    }

    public static function buildRecord(
        string $uuid,
        array $payload,
        array $irilResult,
        array $exposicion,
        array $escenariosResult
    ): array {
        return [
            'id' => 0,
            'uuid' => $uuid,
            'tipo_usuario' => (string) ($payload['tipo_usuario'] ?? ''),
            'tipo_conflicto' => (string) ($payload['tipo_conflicto'] ?? ''),
            'datos_laborales' => self::encode($payload['datos_laborales'] ?? []),
            'documentacion_json' => self::encode($payload['documentacion'] ?? []),
            'situacion_json' => self::encode($payload['situacion'] ?? []),
            'iril_score' => floatval($irilResult['score'] ?? 0),
            'iril_detalle' => self::encode($irilResult),
            'exposicion_json' => self::encode($exposicion),
            'escenario_recomendado' => strtoupper((string) ($escenariosResult['recomendado'] ?? '')),
            'escenarios_json' => self::encode($escenariosResult),
            'accion_tomada' => 'ninguna',
            'tramite_uuid' => null,
            'email' => (string) (($payload['contacto']['email'] ?? '')),
            'ip' => function_exists('ml_ip') ? ml_ip() : '',
            'fecha_creacion' => date('Y-m-d H:i:s'),
            'session_fallback' => true,
        ];
    }

    private static function bootSession(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        if (PHP_SAPI !== 'cli' && headers_sent()) {
            return false;
        }

        $options = PHP_SAPI === 'cli'
            ? ['use_cookies' => 0, 'use_only_cookies' => 0, 'cache_limiter' => '']
            : [];

        return $options === [] ? @session_start() : @session_start($options);
    }

    private static function purgeExpiredSession(): void
    {
        $records = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($records)) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }

        self::purgeExpiredRecords($_SESSION[self::SESSION_KEY]);
    }

    private static function purgeExpiredMemory(): void
    {
        self::purgeExpiredRecords(self::$memoryStore);
    }

    private static function purgeExpiredRecords(array &$records): void
    {
        $now = time();
        foreach ($records as $uuid => $record) {
            $storedAt = intval($record['__stored_at'] ?? 0);
            if ($storedAt <= 0 || ($now - $storedAt) > self::TTL_SECONDS) {
                unset($records[$uuid]);
            }
        }
    }

    private static function encode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $json === false ? 'null' : $json;
    }
}
