<?php
namespace App\Support;

final class LegacyEngineFactory
{
    private static bool $loaded = false;

    public static function createIrilEngine(): \IrilEngine
    {
        self::loadLegacyEngines();
        return new \IrilEngine();
    }

    public static function createEscenariosEngine(): \EscenariosEngine
    {
        self::loadLegacyEngines();
        return new \EscenariosEngine();
    }

    public static function createExposicionEngine(): \ExposicionEngine
    {
        self::loadLegacyEngines();
        return new \ExposicionEngine();
    }

    private static function loadLegacyEngines(): void
    {
        if (self::$loaded) {
            return;
        }

        $root = dirname(__DIR__, 2);
        require_once $root . '/config/IrilEngine.php';
        require_once $root . '/config/EscenariosEngine.php';
        require_once $root . '/config/ExposicionEngine.php';

        self::$loaded = true;
    }
}
