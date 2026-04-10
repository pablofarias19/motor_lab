<?php
namespace App\Services;

use App\Database\DatabaseManager;
use App\Support\AnalysisPayloadNormalizer;
use App\Support\LegacyEngineFactory;
use Exception;

class AnalysisService
{
    private DatabaseManager $db;
    private \IrilEngine $irilEngine;
    private \EscenariosEngine $escenariosEngine;
    private \ExposicionEngine $exposicionEngine;

    public function __construct(
        ?DatabaseManager $db = null,
        ?\IrilEngine $irilEngine = null,
        ?\EscenariosEngine $escenariosEngine = null,
        ?\ExposicionEngine $exposicionEngine = null
    ) {
        $this->db = $db ?? new DatabaseManager();
        $this->irilEngine = $irilEngine ?? LegacyEngineFactory::createIrilEngine();
        $this->escenariosEngine = $escenariosEngine ?? LegacyEngineFactory::createEscenariosEngine();
        $this->exposicionEngine = $exposicionEngine ?? LegacyEngineFactory::createExposicionEngine();
    }

    public function procesar(array $input): array
    {
        $payload = AnalysisPayloadNormalizer::normalize($input);

        if ($payload['tipo_usuario'] === '' || $payload['tipo_conflicto'] === '') {
            throw new Exception('Faltan datos básicos del formulario.');
        }

        $uuid = ml_uuid();
        $id = $this->db->insertarAnalisis(
            $uuid,
            $payload['tipo_usuario'],
            $payload['tipo_conflicto'],
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['contacto']['email']
        );

        $exposicion = $this->exposicionEngine->calcularExposicion(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario']
        );

        $irilResult = $this->irilEngine->calcularIRIL(
            $payload['datos_laborales'],
            $payload['documentacion'],
            $payload['situacion'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario']
        );

        $escenariosResult = $this->escenariosEngine->generarEscenarios(
            $exposicion,
            $irilResult['score'],
            $payload['tipo_conflicto'],
            $payload['tipo_usuario'],
            $payload['situacion'],
            $payload['datos_laborales']['provincia']
        );

        $this->db->actualizarResultados(
            $id,
            $irilResult,
            $exposicion,
            $escenariosResult,
            $escenariosResult['recomendado']
        );

        return [
            'uuid' => $uuid,
            'schema_version' => $payload['schema_version'],
        ];
    }
}
