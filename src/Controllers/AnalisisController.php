<?php
namespace App\Controllers;

use App\Database\DatabaseManager;
use App\Engines\IrilEngine;
use App\Engines\EscenariosEngine;
use App\Engines\ExposicionEngine;
use Exception;

class AnalisisController {
    
    private DatabaseManager $db;
    private $irilMotor;
    private $escenariosMotor;
    private $exposicionMotor;

    public function __construct() {
        $this->db = new DatabaseManager();
        // Fallback a los engines locales de /config/ ya que no generamos los 3 en src
        require_once dirname(__DIR__, 2) . '/config/IrilEngine.php';
        require_once dirname(__DIR__, 2) . '/config/EscenariosEngine.php';
        require_once dirname(__DIR__, 2) . '/config/ExposicionEngine.php';
        $this->irilMotor = new \IrilEngine();
        $this->escenariosMotor = new \EscenariosEngine();
        $this->exposicionMotor = new \ExposicionEngine();
    }

    public function procesar(array $input): array {
        // Validación básica
        if (empty($input['tipo_usuario']) || empty($input['tipo_conflicto'])) {
            throw new Exception("Faltan datos básicos del formulario.");
        }

        $uuid = ml_uuid();
        
        // Formatear datos
        $datosLaborales = [
            'salario' => floatval($input['datos_laborales']['salario'] ?? 0),
            'antiguedad_meses' => intval($input['datos_laborales']['antiguedad_meses'] ?? 0),
            'provincia' => trim($input['datos_laborales']['provincia'] ?? 'CABA'),
            'categoria' => trim($input['datos_laborales']['categoria'] ?? ''),
            'cct' => trim($input['datos_laborales']['cct'] ?? ''),
            'cantidad_empleados' => intval($input['datos_laborales']['cantidad_empleados'] ?? 1),
            'edad' => intval($input['datos_laborales']['edad'] ?? 0),
            'tipo_registro' => trim($input['datos_laborales']['tipo_registro'] ?? 'registrado'),
            'salario_recibo' => floatval($input['datos_laborales']['salario_recibo'] ?? 0),
            'antiguedad_recibo' => intval($input['datos_laborales']['antiguedad_recibo'] ?? 0),
        ];

        // 1. Guardar Análisis Inicial
        $id = $this->db->insertarAnalisis(
            $uuid,
            $input['tipo_usuario'],
            $input['tipo_conflicto'],
            $datosLaborales,
            $input['documentacion'] ?? [],
            $input['situacion'] ?? [],
            $input['contacto']['email'] ?? ''
        );

        // 2. Calcular Exposición Económica
        $exposicion = $this->exposicionMotor->calcularExposicion(
            $datosLaborales,
            $input['documentacion'] ?? [],
            $input['situacion'] ?? [],
            $input['tipo_conflicto'],
            $input['tipo_usuario']
        );

        // 3. Calcular IRIL
        $irilResult = $this->irilMotor->calcularIRIL(
            $datosLaborales,
            $input['documentacion'] ?? [],
            $input['situacion'] ?? [],
            $input['tipo_conflicto'],
            $input['tipo_usuario']
        );

        // 4. Generar Escenarios Estratégicos
        $provincia = $datosLaborales['provincia'];
        $escenariosResult = $this->escenariosMotor->generarEscenarios(
            $exposicion,
            $irilResult['score'],
            $input['tipo_conflicto'],
            $input['tipo_usuario'],
            $input['situacion'] ?? [],
            $provincia
        );

        // 5. Guardar Resultados Finales
        $this->db->actualizarResultados(
            $id,
            $irilResult['score'],
            $irilResult['detalle'],
            $exposicion,
            $escenariosResult['escenarios'],
            $escenariosResult['recomendado']
        );

        return ['uuid' => $uuid];
    }
}
