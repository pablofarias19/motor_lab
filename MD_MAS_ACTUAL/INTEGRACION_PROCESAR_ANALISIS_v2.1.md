# INTEGRACIÓN EN procesar_analisis.php
## Pasos para Conectar RIPTE al Motor Existente

**Archivo**: `api/procesar_analisis.php` (MODIFICADO)

```php
<?php
/**
 * procesar_analisis.php — Endpoint principal del motor laboral
 * 
 * CAMBIOS v2.1:
 * - PASO 0: Validación Ley Bases (NEW)
 * - PASO 1: Obtención RIPTE para casos ART (NEW)
 * - PASO 2: Cálculo IBM dinámico en lugar de salario directo
 * - PASO 3: Aplicación de pisos mínimos ART
 * - PASO 4: Cálculo de multas condicionadas (NEW)
 * - PASO 5: Escenarios con intereses judiciales (MODIFICADO)
 */

// ═══════════════════════════════════════════════════════════════════════════
// SETUP INICIAL
// ═══════════════════════════════════════════════════════════════════════════

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// Importar dependencias
require_once __DIR__ . '/../config/DatabaseManager.php';
require_once __DIR__ . '/../config/IrilEngine.php';
require_once __DIR__ . '/../config/EscenariosEngine.php';
require_once __DIR__ . '/../config/ripte_functions.php';      // ← NEW

try {
    
    // Conexión a BD
    $dbManager = new DatabaseManager();
    $db = $dbManager->getConnection(); // mysqli o PDO
    
    // ─────────────────────────────────────────────────────────────────────
    // RECIBIR DATOS DEL WIZARD
    // ─────────────────────────────────────────────────────────────────────
    
    $datos_entrada = json_decode(file_get_contents('php://input'), true);
    
    // Validar entrada
    if (!$datos_entrada) {
        throw new Exception("No se recibieron datos válidos");
    }
    
    // Extract (POST parameters + JSON mixed)
    $tipo_calculo         = trim($datos_entrada['tipo_calculo'] ?? 'despido');
    $salario              = floatval($datos_entrada['salario'] ?? 0);
    $meses_antiguedad     = intval($datos_entrada['meses_antiguedad'] ?? 0);
    $fecha_extincion      = $datos_entrada['fecha_extincion'] ?? date('Y-m-d');
    
    // ········· NUEVOS CAMPOS v2.1 ·········
    $dia_despido          = intval($datos_entrada['dia_despido'] ?? 15);
    $check_inconstitucionalidad = filter_var(
        $datos_entrada['check_inconstitucionalidad'] ?? 'no',
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );
    $salarios_historicos  = $datos_entrada['salarios_historicos'] ?? []; // Array 12 meses
    $tipo_registro        = $datos_entrada['tipo_registro'] ?? 'registrado';
    $fecha_accidente      = $datos_entrada['fecha_accidente'] ?? null;
    $jurisdiccion         = $datos_entrada['jurisdiccion'] ?? 'CABA';
    
    // Normalizar fechas
    $fecha_ext_obj = new DateTime($fecha_extincion);
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 0: VALIDACIÓN LEY BASES (Nº 27.742)
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 0] Validación Ley Bases");
    
    $validacion_ley_bases = validar_ley_bases($fecha_ext_obj, $check_inconstitucionalidad);
    
    $estado_multas = $validacion_ley_bases['estado'];  // 'anterior'|'posterior'|'cuestionable'
    $aplica_multas = $validacion_ley_bases['aplica_multas'];
    
    error_log("  Estado Ley Bases: $estado_multas | Multas: " . ($aplica_multas ? 'SÍ' : 'NO'));
    
    // Alerta al usuario si es posterior sin check
    if ($estado_multas === 'posterior' && !$check_inconstitucionalidad) {
        error_log("  ⚠ ALERTA: Despido posterior a Ley Bases — multas suspendidas por defecto");
    }
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 1: OBTENCIÓN RIPTE Y TABLA HISTÓRICA
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 1] Obtención RIPTE");
    
    try {
        $ripte_vigente = obtener_ripte_vigente($db);
        $ripte_tabla = obtener_tabla_ripte_historica($db, 12);
        error_log("  ✓ RIPTE vigente: $" . number_format($ripte_vigente, 2));
    } catch (Exception $e) {
        error_log("  ⚠ BD no disponible — usando fallback: " . $e->getMessage());
        $ripte_vigente = 154800.78; // Fallback local
        $ripte_tabla = obtener_ripte_fallback();
    }
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 2: ASIGNAR IBM (DINÁMICO vs. DIRECTO)
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 2] Cálculo IBM");
    
    $ibm_final = $salario; // Default: salario directo
    $ibm_origin = 'directo';
    
    // Si hay 12 salarios históricos, usar IBM dinámico
    if (!empty($salarios_historicos) && count($salarios_historicos) >= 12) {
        
        // Convertir array de salarios a formato correcto
        $salarios_procesados = [];
        foreach ($salarios_historicos as $entrada) {
            $mes_ano = $entrada['mes_ano'] ?? $entrada['mes'] ?? null;
            $monto = floatval($entrada['monto'] ?? $entrada['salario'] ?? 0);
            if ($mes_ano && $monto > 0) {
                $salarios_procesados[$mes_ano] = $monto;
            }
        }
        
        if (count($salarios_procesados) >= 12) {
            $fecha_acc = $fecha_accidente ? new DateTime($fecha_accidente) : $fecha_ext_obj;
            $ibm_final = calcularIBMconRIPTE($salarios_procesados, $fecha_acc, $ripte_tabla);
            $ibm_origin = 'histórico_12m';
            error_log("  ✓ IBM histórico 12 meses: $" . number_format($ibm_final, 2));
        }
        else {
            error_log("  ⚠ Insuficientes meses históricos (" . count($salarios_procesados) . ") — usando salario directo");
        }
    }
    else {
        error_log("  → Usando salario directo (no hay histórico)");
    }
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 3: PISOS MÍNIMOS ART
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 3] Aplicación Pisos Mínimos");
    
    $piso_minimo_aplicado = null;
    
    if ($tipo_calculo === 'accidente' && in_array($tipo_registro, ['IPP', 'IPD', 'gran_invalidez'])) {
        
        try {
            $piso = obtener_piso_minimo($db, $tipo_registro);
            $resultado_piso = aplicar_piso_minimo($ibm_final, $tipo_registro, $piso);
            
            if ($resultado_piso['piso_aplicado']) {
                $ibm_final = $resultado_piso['monto_final'];
                error_log("  ✓ Piso $tipo_registro aplicado: +$" . 
                         number_format($resultado_piso['incremento'], 2));
                $piso_minimo_aplicado = $resultado_piso;
            }
        } catch (Exception $e) {
            error_log("  ⚠ Error obteniendo piso: " . $e->getMessage());
        }
    }
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 4: CÁLCULO CON IRIL ENGINE
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 4] Cálculo IRIL y Exposición");
    
    $iril_engine = new IrilEngine();
    
    // Preparar parámetros para IRIL
    $parametros_iril = [
        'salario' => $ibm_final,
        'antiguedad_meses' => $meses_antiguedad,
        'tipo_calculo' => $tipo_calculo,
        'fecha_extincion' => $fecha_extincion,
        'dia_despido' => $dia_despido,
        // Agregar parámetros IRIL existentes (saturación, probatoria, etc.)
        'saturacion' => $datos_entrada['saturacion'] ?? 0.5,
        'probatoria' => $datos_entrada['probatoria'] ?? 0.8,
        'volatilidad' => $datos_entrada['volatilidad'] ?? 0.3,
    ];
    
    $resultado_iril = $iril_engine->calcular($parametros_iril);
    
    error_log("  ✓ IRIL: " . $resultado_iril['iril_score']);
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 5: MULTAS CONDICIONADAS
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 5] Cálculo Multas Condicionadas");
    
    $multas_objeto = calcular_multas_condicionadas(
        $ibm_final,
        $meses_antiguedad,
        $fecha_extincion,
        $tipo_registro,
        $check_inconstitucionalidad
    );
    
    $suma_multas = $multas_objeto['total_multas'];
    
    error_log("  Multas (Ley Bases $estado_multas):");
    error_log("    24.013: $" . number_format($multas_objeto['multa_24013'], 2));
    error_log("    25.323: $" . number_format($multas_objeto['multa_25323'], 2));
    error_log("    Art.80: $" . number_format($multas_objeto['multa_80'], 2));
    error_log("    TOTAL:  $" . number_format($suma_multas, 2));
    
    // ─────────────────────────────────────────────────────────────────────
    // PASO 6: ESCENARIOS CON INTERESES
    // ─────────────────────────────────────────────────────────────────────
    
    error_log("[PASO 6] Generación de Escenarios");
    
    $escenarios_engine = new EscenariosEngine();
    
    // Parámetros para escenarios (MODIFICADO v2.1: agregar jurisdicción)
    $parametros_escenarios = [
        'iril' => $resultado_iril['iril_score'],
        'base_indemnizacion' => $ibm_final,
        'multas' => $suma_multas,
        'antiguedad' => $meses_antiguedad,
        'fecha_extincion' => $fecha_extincion,
        'dia_despido' => $dia_despido,
        'meses_litigio' => intval($datos_entrada['meses_litigio'] ?? 24),
        'jurisdiccion' => $jurisdiccion,  // ← NEW: para tasas de interés
    ];
    
    $escenarios = $escenarios_engine->generar($parametros_escenarios);
    
    error_log("  ✓ Escenarios generados: " . count($escenarios) . " caminos");
    
    // ─────────────────────────────────────────────────────────────────────
    // COMPILAR RESPUESTA FINAL
    // ─────────────────────────────────────────────────────────────────────
    
    $respuesta = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        
        // DATOS PROCESADOS
        'entrada' => [
            'tipo_calculo' => $tipo_calculo,
            'salario_bruto' => $salario,
            'ibm_final' => $ibm_final,
            'ibm_origen' => $ibm_origin,
            'meses_antiguedad' => $meses_antiguedad,
            'fecha_extincion' => $fecha_extincion,
        ],
        
        // LEY BASES
        'ley_bases' => [
            'estado' => $estado_multas,
            'aplica_multas' => $aplica_multas,
            'alerta' => substr($validacion_ley_bases['alerta'], 0, 200) . '...',
            'check_inconstitucionalidad' => $check_inconstitucionalidad,
        ],
        
        // RIPTE
        'ripte' => [
            'valor_vigente' => $ripte_vigente,
            'fecha_proximo_ajuste' => obtener_proximo_ajuste_ripte(),
            'ibm_utilizado' => $ibm_final,
        ],
        
        // PISOS MÍNIMOS
        'pisos' => $piso_minimo_aplicado ? [
            'aplicado' => true,
            'tipo' => $piso_minimo_aplicado['tipo_incapacidad'],
            'incremento' => $piso_minimo_aplicado['incremento'],
        ] : ['aplicado' => false],
        
        // IRIL
        'iril' => $resultado_iril,
        
        // MULTAS
        'multas' => [
            'ley_24013' => $multas_objeto['multa_24013'],
            'ley_25323' => $multas_objeto['multa_25323'],
            'art_80' => $multas_objeto['multa_80'],
            'total' => $suma_multas,
            'aplica' => $aplica_multas,
        ],
        
        // ESCENARIOS
        'escenarios' => $escenarios,
        
        // RESUMEN EJECUTIVO
        'resumen' => [
            'vae_promedio' => array_sum(array_column($escenarios, 'vae')) / count($escenarios),
            'mejor_escenario' => max(array_column($escenarios, 'vae')),
            'peor_escenario' => min(array_column($escenarios, 'vae')),
        ]
    ];
    
    // Enviar respuesta
    http_response_code(200);
    echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    
    // ERROR
    error_log("[ERROR] " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

?>
```

---

## **PASO 2: Actualizar EscenariosEngine.php**

Modificar método `generar()` para incluir **intereses judiciales por jurisdicción**:

```php
<?php
// En config/EscenariosEngine.php — Agregar al inicio:

class EscenariosEngine {
    
    // Tabla de tasas de interés por provincia
    private $tasas_interes = [
        'CABA' => 0.065,           // 6.5% anual
        'PBA' => 0.062,            // 6.2% anual
        'CORDOBA' => 0.063,        // 6.3% anual
        'SANTA_FE' => 0.061,       // 6.1% anual
        'BUENOS_AIRES_LITORAL' => 0.062, // 6.2% anual
        'default' => 0.064         // 6.4% promedio nacional
    ];
    
    public function generar($parametros) {
        
        $jurisdiccion = $parametros['jurisdiccion'] ?? 'default';
        $tasa_interes = $this->tasas_interes[$jurisdiccion] ?? $this->tasas_interes['default'];
        
        $meses_litigio = $parametros['meses_litigio'] ?? 24;
        
        // ... código existente ...
        
        // EN ESCENARIO JUDICIAL: Aplicar intereses
        foreach ($escenarios as &$escenario) {
            if ($escenario['nombre'] === 'Litigio') {
                $base = $escenario['vae'];
                // Fórmula: VFinal = VBase × (1 + tasa)^(meses/12)
                $vae_con_interes = $base * pow(1 + $tasa_interes, $meses_litigio / 12);
                
                $escenario['vae_original'] = $base;
                $escenario['vae'] = $vae_con_interes;
                $escenario['interes_acumulado'] = $vae_con_interes - $base;
                $escenario['tasa_interes'] = $tasa_interes * 100 . '%';
            }
        }
        
        return $escenarios;
    }
}

?>
```

---

## **PASO 3: Actualizar wizard.js**

Agregar nuevos campos en el formulario:

```javascript
// En assets/js/wizard.js

// Agregar al formulario del wizard:

<div class="form-group">
    <label for="dia_despido">Día Despido (1-31):</label>
    <input type="number" id="dia_despido" name="dia_despido" min="1" max="31" value="15" required>
    <small>Para cálculo dinámico Art. 233</small>
</div>

<div class="form-group">
    <label for="check_inconstitucionalidad">
        <input type="checkbox" id="check_inconstitucionalidad" name="check_inconstitucionalidad">
        Restaurar multas (Asumir riesgo Ley Bases)
    </label>
    <small>Solo si consideras que Ley 27.742 es inconstitucional</small>
</div>

<div class="form-group">
    <label for="salarios_historicos">Salarios últimos 12 meses (JSON):</label>
    <textarea id="salarios_historicos" name="salarios_historicos" rows="4" placeholder='[{"mes_ano":"2025-02","monto":80000}, ...]'></textarea>
    <small>Para cálculo IBM dinámico (opcional)</small>
</div>

// En JavaScript envío:
const datos = {
    ...datosExistentes,
    dia_despido: parseInt(document.getElementById('dia_despido').value),
    check_inconstitucionalidad: document.getElementById('check_inconstitucionalidad').checked,
    salarios_historicos: JSON.parse(document.getElementById('salarios_historicos').value || '[]'),
};

```

---

**Integración Completa: ✅ LISTA PARA IMPLEMENTACIÓN**
