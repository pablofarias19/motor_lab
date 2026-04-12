<?php
/**
 * ripte_functions.php — Librería centralizada de funciones RIPTE
 * 
 * Incluir en proyecto con: require_once __DIR__ . '/ripte_functions.php';
 * 
 * Funciones disponibles:
 * - obtener_ripte_vigente()
 * - obtener_tabla_ripte_historica()
 * - calcularIBMconRIPTE()
 * - aplicar_piso_minimo()
 * - validar_ley_bases()
 * - calcular_multas_condicionadas()
 * 
 * Última actualización: 22/02/2026
 */

// ═══════════════════════════════════════════════════════════════════════════
// 1. RIPTE — OBTENCIÓN Y GESTIÓN
// ═══════════════════════════════════════════════════════════════════════════

/**
 * obtener_ripte_vigente() — Extrae el RIPTE vigente más reciente desde BD
 * 
 * @param PDO|mysqli $conexion  Conexión a base de datos
 * @return float                Valor del índice RIPTE actual
 * 
 * @throws Exception Si no hay datos disponibles
 * 
 * Ejemplo:
 * $ripte = obtener_ripte_vigente($db);
 * echo "RIPTE Actual: $" . number_format($ripte, 2);
 */
function obtener_ripte_vigente($conexion) {
    
    // Detectar tipo de conexión
    if ($conexion instanceof mysqli) {
        $query = "SELECT valor_indice FROM ripte_indices 
                  WHERE estado = 'vigente' 
                  ORDER BY mes_ano DESC LIMIT 1";
        
        $result = $conexion->query($query);
        if (!$result || $result->num_rows === 0) {
            throw new Exception("RIPTE vigente no disponible en BD");
        }
        
        $row = $result->fetch_assoc();
        return floatval($row['valor_indice']);
    }
    
    // PDO
    elseif ($conexion instanceof PDO) {
        $stmt = $conexion->prepare(
            "SELECT valor_indice FROM ripte_indices 
             WHERE estado = 'vigente' 
             ORDER BY mes_ano DESC LIMIT 1"
        );
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            throw new Exception("RIPTE vigente no disponible en BD");
        }
        
        return floatval($result['valor_indice']);
    }
    
    throw new Exception("Tipo de conexión no soportado");
}

/**
 * obtener_tabla_ripte_historica() — Obtiene tabla completa de RIPTE 12 últimos meses
 * 
 * @param PDO|mysqli $conexion
 * @param int $meses  Cantidad de meses a recuperar (default: 12)
 * @return array      ['YYYY-MM' => coeficiente, ...]
 * 
 * Ejemplo:
 * $tabla = obtener_tabla_ripte_historica($db, 12);
 * // Retorna: ['2026-02' => 154800.78, '2026-01' => 152100.45, ...]
 */
function obtener_tabla_ripte_historica($conexion, $meses = 12) {
    
    $ripte_tabla = [];
    
    try {
        if ($conexion instanceof mysqli) {
            $query = "SELECT mes_ano, valor_indice FROM ripte_indices 
                      WHERE estado IN ('vigente', 'histórico')
                      ORDER BY mes_ano DESC LIMIT " . intval($meses);
            
            $result = $conexion->query($query);
            while ($row = $result->fetch_assoc()) {
                $ripte_tabla[$row['mes_ano']] = floatval($row['valor_indice']);
            }
        }
        
        elseif ($conexion instanceof PDO) {
            $stmt = $conexion->prepare(
                "SELECT mes_ano, valor_indice FROM ripte_indices 
                 WHERE estado IN ('vigente', 'histórico')
                 ORDER BY mes_ano DESC LIMIT :meses"
            );
            $stmt->execute([':meses' => $meses]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ripte_tabla[$row['mes_ano']] = floatval($row['valor_indice']);
            }
        }
    } catch (Exception $e) {
        error_log("⚠ Error obteniendo RIPTE histórico: " . $e->getMessage());
        return obtener_ripte_fallback();
    }
    
    return $ripte_tabla;
}

/**
 * obtener_ripte_fallback() — Tabla RIPTE local para fallback
 * 
 * Usada si BD no está disponible
 */
function obtener_ripte_fallback() {
    return [
        '2026-02' => 185750.50,
        '2026-01' => 152100.45,
        '2025-12' => 149500.22,
        '2025-11' => 147800.90,
        '2025-10' => 145230.12,
        '2025-09' => 142100.00,
        '2025-08' => 141200.50,
        '2025-07' => 140500.30,
        '2025-06' => 138900.75,
        '2025-05' => 137200.25,
        '2025-04' => 135600.90,
        '2025-03' => 134100.45,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. CÁLCULO IBM CON RIPTE
// ═══════════════════════════════════════════════════════════════════════════

/**
 * calcularIBMconRIPTE() — Calcula IBM dinámico ajustado por 12 meses con RIPTE
 * 
 * @param array $salarios_historicos  ['YYYY-MM' => monto, ...]
 * @param DateTime $fecha_accidente   Fecha del siniestro
 * @param array $ripte_tabla         ['YYYY-MM' => coeficiente, ...]
 * @return float                      IBM calculado
 */
function calcularIBMconRIPTE($salarios_historicos, DateTime $fecha_accidente, $ripte_tabla) {
    
    if (empty($salarios_historicos) || empty($ripte_tabla)) {
        return 0.0;
    }
    
    $salarios_actualizados = [];
    $ripte_accidente = $ripte_tabla[$fecha_accidente->format('Y-m')] ?? null;
    
    if (!$ripte_accidente) {
        error_log("⚠ RIPTE del mes de accidente no disponible");
        return 0.0;
    }
    
    // Procesar cada salario mensual
    foreach ($salarios_historicos as $mes_ano => $monto) {
        
        $monto = floatval($monto);
        if ($monto <= 0) continue;
        
        // Extraer RIPTE del mes correspondiente
        $ripte_mes = $ripte_tabla[$mes_ano] ?? null;
        
        if (!$ripte_mes) {
            error_log("⚠ RIPTE no disponible para mes: $mes_ano (usando fallback)");
            $ripte_mes = 100.0;
        }
        
        // Coeficiente = RIPTE accidente / RIPTE mes
        $coeficiente = $ripte_accidente / $ripte_mes;
        
        // Salario actualizado
        $salario_actualizado = $monto * $coeficiente;
        
        array_push($salarios_actualizados, $salario_actualizado);
    }
    
    if (empty($salarios_actualizados)) {
        return 0.0;
    }
    
    // IBM = Promedio de 12 salarios actualizados
    $ibm = array_sum($salarios_actualizados) / count($salarios_actualizados);
    
    return round($ibm, 2);
}

/**
 * calcular_ibm_simplificado() — IBM rápido sin histórico (fallback)
 */
function calcular_ibm_simplificado($salario_actual, $ripte_actual, $ripte_12_meses_atras) {
    $coeficiente = $ripte_actual / $ripte_12_meses_atras;
    return $salario_actual * $coeficiente;
}

// ═══════════════════════════════════════════════════════════════════════════
// 2.5 IBM REFORZADA (MARZO 2026) — Ackerman + Jurisprudencia
// ═══════════════════════════════════════════════════════════════════════════

/**
 * obtener_tabla_ipc_historica() — Tabla IPC mensual para fallback (Marzo 2026)
 * 
 * Según Ackerman: Si RIPTE < IPC núcleo, usar IPC para evitar pulverización
 * Datos referenciales febrero 2026
 */
function obtener_tabla_ipc_historica() {
    return [
        '2026-02' => 189200.00,  // IPC Feb 2026 (Base: enero 100)
        '2026-01' => 185230.50,
        '2025-12' => 182100.75,
        '2025-11' => 179500.25,
        '2025-10' => 176800.90,
        '2025-09' => 173200.00,
        '2025-08' => 171100.30,
        '2025-07' => 170200.50,
        '2025-06' => 168500.75,
        '2025-05' => 166700.25,
        '2025-04' => 165000.90,
        '2025-03' => 163200.45,
    ];
}

/**
 * calcularIBMReforzada() — IBM mejorada según doctrina Ackerman (Marzo 2026)
 * 
 * Utiliza MAX(RIPTE, IPC) para evitar pérdida de poder adquisitivo
 * Implementa la recomendación de la jurisprudencia de marzo 2026
 * 
 * @param array $salarios_historicos   ['YYYY-MM' => monto, ...]
 * @param DateTime $fecha_accidente    Fecha del siniestro
 * @param array $ripte_tabla          ['YYYY-MM' => coeficiente, ...]
 * @param array $ipc_tabla             ['YYYY-MM' => coeficiente, ...] (opcional)
 * @return array                       ['ibm' => float, 'ajuste_aplicado' => string, 'detalles' => array]
 */
function calcularIBMReforzada($salarios_historicos, DateTime $fecha_accidente, $ripte_tabla, $ipc_tabla = null) {
    
    if (!$ipc_tabla) {
        $ipc_tabla = obtener_tabla_ipc_historica();
    }
    
    if (empty($salarios_historicos) || empty($ripte_tabla)) {
        return [
            'ibm' => 0.0,
            'ajuste_aplicado' => 'sin_datos',
            'detalles' => []
        ];
    }
    
    $salarios_actualizados = [];
    $detalles_ajuste = [];
    $ripte_accidente = $ripte_tabla[$fecha_accidente->format('Y-m')] ?? null;
    
    if (!$ripte_accidente) {
        error_log("⚠ RIPTE del mes de accidente no disponible");
        return [
            'ibm' => 0.0,
            'ajuste_aplicado' => 'sin_ripte_accidente',
            'detalles' => []
        ];
    }
    
    // Procesar cada salario mensual con lógica Ackerman
    foreach ($salarios_historicos as $mes_ano => $monto) {
        
        $monto = floatval($monto);
        if ($monto <= 0) continue;
        
        // Extraer índices del mes
        $ripte_mes = $ripte_tabla[$mes_ano] ?? null;
        $ipc_mes = $ipc_tabla[$mes_ano] ?? null;
        
        if (!$ripte_mes) {
            error_log("⚠ RIPTE no disponible para mes: $mes_ano");
            $ripte_mes = 100.0;
        }
        if (!$ipc_mes) {
            error_log("⚠ IPC no disponible para mes: $mes_ano");
            $ipc_mes = 100.0;
        }
        
        // Coeficientes por cada índice
        $coef_ripte = $ripte_accidente / $ripte_mes;
        $coef_ipc = $ripte_accidente / $ipc_mes;  // Usar RIPTE accidente como referencia
        
        // Ackerman: Usar el MÁXIMO para evitar pérdida de poder adquisitivo
        $coeficiente = max($coef_ripte, $coef_ipc);
        
        // Salario actualizado
        $salario_actualizado = $monto * $coeficiente;
        array_push($salarios_actualizados, $salario_actualizado);
        
        // Registrar detalles del ajuste
        $ajuste_tipo = ($coef_ipc > $coef_ripte) ? 'IPC' : 'RIPTE';
        $detalles_ajuste[] = [
            'mes' => $mes_ano,
            'salario' => $monto,
            'coef_ripte' => round($coef_ripte, 4),
            'coef_ipc' => round($coef_ipc, 4),
            'ajuste_tipo' => $ajuste_tipo,
            'salario_final' => round($salario_actualizado, 2)
        ];
    }
    
    if (empty($salarios_actualizados)) {
        return [
            'ibm' => 0.0,
            'ajuste_aplicado' => 'sin_salarios',
            'detalles' => []
        ];
    }
    
    // IBM = Promedio de 12 salarios ajustados
    $ibm = array_sum($salarios_actualizados) / count($salarios_actualizados);
    
    return [
        'ibm' => round($ibm, 2),
        'ajuste_aplicado' => 'ackerman_max_ripte_ipc',
        'detalles' => $detalles_ajuste,
        'cantidad_meses' => count($salarios_actualizados),
        'nota' => 'Según doctrina Ackerman (Marzo 2026): máximo entre RIPTE e IPC para evitar pulverización'
    ];
}

/**
 * check_ripte_gap() — Detecta desviación entre RIPTE e IPC (Alerta Marzo 2026)
 * 
 * Si RIPTE se desvía más de 10% del IPC, genera aviso de precaución
 * 
 * @param array $ripte_tabla    ['YYYY-MM' => valor, ...]
 * @param array $ipc_tabla      ['YYYY-MM' => valor, ...]
 * @return array                ['gap_detectado' => bool, 'porcentaje' => float, 'alerta' => string]
 */
function check_ripte_gap($ripte_tabla, $ipc_tabla = null) {
    
    if (!$ipc_tabla) {
        $ipc_tabla = obtener_tabla_ipc_historica();
    }
    
    if (empty($ripte_tabla)) {
        return [
            'gap_detectado' => false,
            'porcentaje' => 0.0,
            'alerta' => ''
        ];
    }
    
    // Usar el RIPTE vigente más reciente para comparación
    $ripte_vigente = reset($ripte_tabla);
    $ipc_vigente = reset($ipc_tabla);
    
    if (!$ripte_vigente || !$ipc_vigente) {
        return [
            'gap_detectado' => false,
            'porcentaje' => 0.0,
            'alerta' => ''
        ];
    }
    
    // Calcular desviación porcentual
    $gap = abs($ripte_vigente - $ipc_vigente) / $ipc_vigente * 100;
    
    $resultado = [
        'gap_detectado' => $gap > 10,
        'porcentaje' => round($gap, 2),
        'ripte_vigente' => $ripte_vigente,
        'ipc_vigente' => $ipc_vigente,
        'alerta' => ''
    ];
    
    if ($gap > 10) {
        $direccion = ($ripte_vigente > $ipc_vigente) ? 'superior' : 'inferior';
        $resultado['alerta'] = "⚠️ ALERTA: RIPTE está {$direccion} al IPC por {$gap}%. Considere usar IBM Reforzada.";
    }
    
    return $resultado;
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. PISOS MÍNIMOS ART
// ═══════════════════════════════════════════════════════════════════════════

/**
 * obtener_piso_minimo() — Obtiene piso mínimo vigente según tipo de incapacidad
 * 
 * @param PDO|mysqli $conexion
 * @param string $tipo_incapacidad  'IPP', 'IPD', 'gran_invalidez', 'muerte'
 * @param DateTime $fecha_referencia Fecha para validar vigencia (default: hoy)
 * @return float                     Monto del piso mínimo
 */
function obtener_piso_minimo($conexion, $tipo_incapacidad, DateTime $fecha_referencia = null) {
    
    if (!$fecha_referencia) {
        $fecha_referencia = new DateTime();
    }
    
    $fecha_str = $fecha_referencia->format('Y-m-d');
    
    try {
        if ($conexion instanceof mysqli) {
            $tipo = $conexion->real_escape_string($tipo_incapacidad);
            $query = "SELECT monto_piso FROM ripte_pisos_minimos 
                      WHERE tipo_incapacidad = '$tipo'
                      AND vigencia_desde <= '$fecha_str'
                      AND (vigencia_hasta IS NULL OR vigencia_hasta >= '$fecha_str')
                      AND activo = true
                      LIMIT 1";
            
            $result = $conexion->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return floatval($row['monto_piso']);
            }
        }
        
        elseif ($conexion instanceof PDO) {
            $stmt = $conexion->prepare(
                "SELECT monto_piso FROM ripte_pisos_minimos 
                 WHERE tipo_incapacidad = :tipo
                 AND vigencia_desde <= :fecha
                 AND (vigencia_hasta IS NULL OR vigencia_hasta >= :fecha)
                 AND activo = true
                 LIMIT 1"
            );
            $stmt->execute([
                ':tipo' => $tipo_incapacidad,
                ':fecha' => $fecha_str
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return floatval($result['monto_piso']);
            }
        }
    } catch (Exception $e) {
        error_log("⚠ Error obteniendo piso mínimo: " . $e->getMessage());
        return obtener_piso_fallback($tipo_incapacidad);
    }
    
    return obtener_piso_fallback($tipo_incapacidad);
}

/**
 * obtener_piso_fallback() — Pisos locales para fallback
 */
function obtener_piso_fallback($tipo) {
    $pisos = [
        'IPP' => 2897500,
        'IPD' => 5795000,
        'gran_invalidez' => 11590000,
        'muerte' => 8692500
    ];
    return floatval($pisos[$tipo] ?? 0);
}

/**
 * aplicar_piso_minimo() — Aplica piso mínimo al monto calculado
 * 
 * @param float $monto_calculado        Resultado de fórmula LRT
 * @param string $tipo_incapacidad      'IPP', 'IPD', etc.
 * @param float $piso_minimo            Monto del piso
 * @return array                        ['monto_final' => float, 'piso_aplicado' => bool]
 */
function aplicar_piso_minimo($monto_calculado, $tipo_incapacidad, $piso_minimo) {
    
    $monto_calculado = floatval($monto_calculado);
    $piso_minimo = floatval($piso_minimo);
    
    $monto_final = max($monto_calculado, $piso_minimo);
    $piso_aplicado = ($monto_final === $piso_minimo && $monto_final > $monto_calculado);
    
    return [
        'monto_final' => $monto_final,
        'monto_calculado' => $monto_calculado,
        'piso_minimo' => $piso_minimo,
        'piso_aplicado' => $piso_aplicado,
        'incremento' => $monto_final - $monto_calculado,
        'tipo_incapacidad' => $tipo_incapacidad,
        'nota' => $piso_aplicado ? "Piso mínimo $tipo_incapacidad aplicado" : "Cálculo supera piso mínimo"
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. VALIDACIÓN LEY BASES (Nº 27.742)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * validar_ley_bases() — Valida si multas aplican según Ley 27.742
 * 
 * @param DateTime $fecha_extincion           Fecha de desvinculación
 * @param bool $check_inconstitucionalidad    Usuario activa override
 * @return array                              Resultado de validación
 */
function validar_ley_bases(DateTime $fecha_extincion, $check_inconstitucionalidad = false) {
    
    $fecha_corte = new DateTime('2024-07-09');
    
    $resultado = [
        'aplica_multas' => true,
        'alerta' => '',
        'riesgo' => false,
        'fecha_corte' => '2024-07-09',
        'estado' => 'anterior',
        'fecha_comparacion' => $fecha_extincion->format('Y-m-d'),
        'check_inconstitucionalidad' => $check_inconstitucionalidad
    ];
    
    // CASO 1: Fecha ANTERIOR a Ley Bases
    if ($fecha_extincion <= $fecha_corte) {
        $resultado['estado'] = 'anterior';
        $resultado['aplica_multas'] = true;
        $resultado['alerta'] = 'Derechos adquiridos: Multas aplican normalmente';
        return $resultado;
    }
    
    // CASO 2: Fecha POSTERIOR a Ley Bases
    $resultado['estado'] = 'posterior';
    $resultado['riesgo'] = true;
    
    if ($check_inconstitucionalidad === false || $check_inconstitucionalidad === 'no') {
        $resultado['aplica_multas'] = false;
        $resultado['alerta'] = 'Ley Bases posterior a 09/07/2024: Multas suspendidas por defecto';
        return $resultado;
    }
    
    if ($check_inconstitucionalidad === true || $check_inconstitucionalidad === 'si') {
        $resultado['aplica_multas'] = true;
        $resultado['alerta'] = 'Check de inconstitucionalidad activado: Multas restauradas';
        return $resultado;
    }
    
    $resultado['aplica_multas'] = false;
    return $resultado;
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. CÁLCULO DE MULTAS CONDICIONADAS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * calcular_multas_condicionadas() — Calcula multas según Ley Bases
 * 
 * @param float $salario
 * @param int $meses_antiguedad
 * @param DateTime|string $fecha_despido
 * @param string $tipo_registro  'registrado', 'no_registrado', 'deficiente_fecha', 'deficiente_salario'
 * @param bool $check_inconstit
 * @return array ['multa_24013' => float, 'multa_25323' => float, 'multa_80' => float, 'alerta' => string]
 */
function calcular_multas_condicionadas(
    $salario,
    $meses_antiguedad,
    $fecha_despido,
    $tipo_registro = 'registrado',
    $check_inconstit = false
) {
    
    // Normalizar fecha
    if (is_string($fecha_despido)) {
        $fecha_despido = new DateTime($fecha_despido);
    }
    
    // Validar Ley Bases
    $validacion = validar_ley_bases($fecha_despido, $check_inconstit);
    
    $multas = [
        'multa_24013' => 0.00,
        'multa_25323' => 0.00,
        'multa_80' => 0.00,
        'total_multas' => 0.00,
        'alerta' => $validacion['alerta'],
        'ley_bases' => $validacion['estado'],
        'aplica' => $validacion['aplica_multas']
    ];
    
    // Si no aplica, retorna ceros
    if (!$validacion['aplica_multas']) {
        return $multas;
    }
    
    // CALCULAR MULTAS
    $salario = floatval($salario);
    $meses = intval($meses_antiguedad);
    $anos = max(1, intdiv($meses, 12));
    
    // A. Ley 24.013 Art. 8 (DEROGADA desde Ley 27.802 — 2026)
    // $multas['multa_24013'] = 0.00;  [No aplicar]
    
    // B. Ley 25.323 (DEROGADA desde Ley 27.802 — 2026)
    // $multas['multa_25323'] = 0.00;  [No aplicar]
    
    // C. Art. 80 LCT (Certificados — Vigente)
    $multas['multa_80'] = $salario * 3;
    
    // Total
    $multas['total_multas'] = $multas['multa_24013'] + $multas['multa_25323'] + $multas['multa_80'];
    
    return $multas;
}

// ═══════════════════════════════════════════════════════════════════════════
// 6. UTILIDADES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * obtener_proximo_ajuste_ripte() — Calcula próxima fecha de ajuste oficial
 * 
 * @return string Fecha formateada
 */
function obtener_proximo_ajuste_ripte() {
    $ahora = new DateTime();
    $mes = intval($ahora->format('m'));
    
    // Próximas fechas fijas: 15 de marzo y 15 de septiembre
    if ($mes < 3 || ($mes === 3 && intval($ahora->format('d')) < 15)) {
        $proxima = new DateTime('15 March ' . $ahora->format('Y'));
    } elseif ($mes < 9 || ($mes === 9 && intval($ahora->format('d')) < 15)) {
        $proxima = new DateTime('15 September ' . $ahora->format('Y'));
    } else {
        $proxima = new DateTime('15 March ' . ($ahora->format('Y') + 1));
    }
    
    return $proxima->format('d \de F \de Y');
}

/**
 * convertir_mes_a_ripte_key() — Convierte fecha a clave RIPTE 'YYYY-MM'
 */
function convertir_mes_a_ripte_key(DateTime $fecha) {
    return $fecha->format('Y-m');
}

// ═══════════════════════════════════════════════════════════════════════════
// 7. LEY 27.802 (MARZO 2026) — VALIDACIÓN PRESUNCIÓN Y SOLIDARIA
// ═══════════════════════════════════════════════════════════════════════════

/**
 * validar_presuncion_laboral() — Valida si presunción de relación laboral OPERA
 * 
 * Según Art. 23 LCT (Ley 27.802 - Marzo 2026):
 * La presunción NO OPERA cuando coexisten 3 elementos:
 * 1. Facturación (comprobantes de servicios/productos)
 * 2. Pago bancario (transferencias, depósitos, no efectivo)
 * 3. Contrato escrito (acuerdo formalizado)
 * 
 * @param bool $tiene_facturacion
 * @param bool $tiene_pago_bancario
 * @param bool $tiene_contrato_escrito
 * @param string $nota_adicional  Observaciones (opcional)
 * @return array ['presuncion_opera' => bool, 'controles_presentes' => int, 'análisis' => string, 'recomendación' => string]
 */
function validar_presuncion_laboral($tiene_facturacion, $tiene_pago_bancario, $tiene_contrato_escrito, $nota_adicional = '') {
    $tiene_facturacion = ml_boolish($tiene_facturacion);
    $tiene_pago_bancario = ml_boolish($tiene_pago_bancario);
    $tiene_contrato_escrito = ml_boolish($tiene_contrato_escrito);
    
    // Contar controles presentes
    $controles_presente = 0;
    $controles_detalles = [];
    
    if ($tiene_facturacion) {
        $controles_presente++;
        $controles_detalles[] = "✓ Facturación (Comprobantes de servicios/productos)";
    } else {
        $controles_detalles[] = "✗ Sin facturación";
    }
    
    if ($tiene_pago_bancario) {
        $controles_presente++;
        $controles_detalles[] = "✓ Pago Bancario (Transferencias, depósitos, cheques)";
    } else {
        $controles_detalles[] = "✗ Pagos en efectivo/informalidad";
    }
    
    if ($tiene_contrato_escrito) {
        $controles_presente++;
        $controles_detalles[] = "✓ Contrato Escrito (Acuerdo formalizado)";
    } else {
        $controles_detalles[] = "✗ Sin contrato formal";
    }
    
    // LÓGICA: Presunción Opera si NO están presentes TODOS 3 controles
    $presuncion_opera = ($controles_presente < 3);
    
    // Análisis textual
    switch ($controles_presente) {
        case 0:
            $analisis = "PRESUNCIÓN MÁXIMA. Sin documentación contradictoria. Relación presumida laboral.";
            $recomendacion = "Enfoque: Establecer hechos o confesión mediante testigos o prueba documental.";
            break;
        case 1:
            $analisis = "PRESUNCIÓN MODERADA. Existe 1 elemento de independencia, pero insuficiente para excluir relación laboral.";
            $recomendacion = "Complementar prueba: Contexto de prestación, subordinación, continuidad, habitualidad.";
            break;
        case 2:
            $analisis = "PRESUNCIÓN DÉBIL. Existen 2 elementos de independencia. Margen litigable.";
            $recomendacion = "Requiere análisis contextual fuerte de subordinación jurídica para afirmar relación laboral.";
            break;
        case 3:
            $analisis = "PRESUNCIÓN NO OPERA. Presentes los 3 elementos: Facturación + Pago Bancario + Contrato Escrito. Relación DUDA.";
            $recomendacion = "Enfoque: Demostrar subordinación sustancial (órdenes, horarios, lugar fijo, dependencia funcional). Art. 23 LCT contra demandante.";
            break;
        default:
            $analisis = "Cálculo de controles incorrecto.";
            $recomendacion = "Verificar datos de entrada.";
    }
    
    return [
        'presuncion_opera' => $presuncion_opera,
        'controles_presentes' => $controles_presente,
        'controles_validados' => $controles_detalles,
        'análisis' => $analisis,
        'recomendación' => $recomendacion,
        'facturacion' => $tiene_facturacion,
        'pago_bancario' => $tiene_pago_bancario,
        'contrato_escrito' => $tiene_contrato_escrito,
        'nota_adicional' => $nota_adicional,
        'base_legal' => 'Art. 23 LCT (Ley 27.802)',
        'estado' => $presuncion_opera ? 'presuncion_fuerte' : 'presuncion_debil_o_nula'
    ];
}

/**
 * validar_responsabilidad_solidaria() — Valida exención principal de responsabilidad solidaria
 * 
 * Según Art. 30 LCT (Ley 27.802 - Marzo 2026):
 * Principal es EXENTO de responsabilidad solidaria si valida 5 CONTROLES:
 * 1. CUIL: Mantiene registro actualizado
 * 2. Aportes: Paga aportes SRT regularmente
 * 3. Pago: Paga remuneración directamente al trabajador
 * 4. CBU: Tiene clave bancaria única para transferencias
 * 5. ART: Tiene cobertura vigente ART
 * 
 * EXENCIÓN = Principal solo es responsable por su incumplimiento directo, NO por el contratista
 * 
 * @param bool $valida_cuil            CUIL registrado y actualizado
 * @param bool $valida_aportes         Aportes SRT pagados regularmente
 * @param bool $valida_pago_directo    Pago nómina directo al trabajador (no al contratista)
 * @param bool $valida_cbu             Clave bancaria para transferencias verificada
 * @param bool $valida_art             ART vigente y en función
 * @return array   ['exento' => bool, 'controles_validados' => int, 'factor_exención' => float, 'análisis' => string]
 */
function validar_responsabilidad_solidaria(
    $valida_cuil = false,
    $valida_aportes = false,
    $valida_pago_directo = false,
    $valida_cbu = false,
    $valida_art = false
) {
    $valida_cuil = ml_boolish($valida_cuil);
    $valida_aportes = ml_boolish($valida_aportes);
    $valida_pago_directo = ml_boolish($valida_pago_directo);
    $valida_cbu = ml_boolish($valida_cbu);
    $valida_art = ml_boolish($valida_art);
    
    // Contar controles validados
    $controles_count = 0;
    $controles_detalles = [];
    
    if ($valida_cuil) {
        $controles_count++;
        $controles_detalles[] = "✓ CUIL: Registro actualizado";
    } else {
        $controles_detalles[] = "✗ CUIL: No verificado o desactualizado";
    }
    
    if ($valida_aportes) {
        $controles_count++;
        $controles_detalles[] = "✓ Aportes: SRT pagados regularmente";
    } else {
        $controles_detalles[] = "✗ Aportes: Mora o incumplimiento";
    }
    
    if ($valida_pago_directo) {
        $controles_count++;
        $controles_detalles[] = "✓ Pago: Nómina directa al trabajador";
    } else {
        $controles_detalles[] = "✗ Pago: Indirecto o a terceros";
    }
    
    if ($valida_cbu) {
        $controles_count++;
        $controles_detalles[] = "✓ CBU: Clave bancaria verificada";
    } else {
        $controles_detalles[] = "✗ CBU: No verificada o no existe";
    }
    
    if ($valida_art) {
        $controles_count++;
        $controles_detalles[] = "✓ ART: Cobertura vigente";
    } else {
        $controles_detalles[] = "✗ ART: Vencida o sin cobertura";
    }
    
    // LÓGICA: Exento si valida 5/5 controles
    $exento = ($controles_count === 5);
    
    // Factor exención (Art. 30): max(1, 6 - controles_validados)
    // Si valida 5 → factor = max(1, 6-5) = 1 (sin responsabilidad adicional)
    // Si valida 0 → factor = max(1, 6-0) = 6 (responsabilidad solidaria completa)
    $factor_exención = max(1, 6 - $controles_count);
    
    // Análisis contextual
    switch ($controles_count) {
        case 5:
            $analisis = "EXENTO. Principal validó los 5 controles. Responsabilidad limitada a su incumplimiento directo.";
            $recomendacion = "Demandado (principal) tiene exención de solidaria. Perseguir contratista.";
            break;
        case 4:
            $analisis = "3 CONTROLES. Factor exención = 2. Principal tiene responsabilidad limitada por omisión de 1 control.";
            $recomendacion = "Principal puede reducción pero no exención. Responsabilidad compartida según factor.";
            break;
        case 3:
            $analisis = "3 CONTROLES. Factor exención = 3. Principal es responsable solidario pero con mitigación.";
            $recomendacion = "Principal responsable solidario pero controlaba malamente. Argumento de atenuación.";
            break;
        case 2:
            $analisis = "2 CONTROLES. Factor exención = 4. Principal con responsabilidad solidaria significativa.";
            $recomendacion = "Principal es responsable solidario con pocas defensas. Establecer fraude si aplica.";
            break;
        case 1:
            $analisis = "1 CONTROL. Factor exención = 5. Principal casi sin defensa frente a responsabilidad solidaria.";
            $recomendacion = "Principal responsable solidario prácticamente sin atenuante. Búsqueda de compensatio.";
            break;
        case 0:
            $analisis = "NINGÚN CONTROL. Factor exención = 6. Principal completamente responsable solidario. Sin defensas técnicas.";
            $recomendacion = "Principal afrontará responsabilidad solidaria completa. Defensa: fraude laboral o culpa exclusiva trabajador.";
            break;
        default:
            $analisis = "Error en cálculo de controles.";
            $recomendacion = "Verificar datos de entrada.";
    }
    
    return [
        'exento' => $exento,
        'controles_validados' => $controles_count,
        'controles_detalles' => $controles_detalles,
        'factor_exención' => $factor_exención,
        'responsabilidad_solidaria' => !$exento,
        'análisis' => $analisis,
        'recomendación' => $recomendacion,
        'base_legal' => 'Art. 30 LCT (Ley 27.802)',
        'estado' => $exento ? 'exento_solidaria' : "responsable_factor_{$factor_exención}"
    ];
}

/**
 * detectar_fraude_laboral() — Detecta patrones de fraude laboral
 * 
 * Evalúa 5 indicadores de fraude:
 * 1. Facturación desproporcionada (montos vs. servicios)
 * 2. Intermitencia laboral (pausa-reanudación sospechosa)
 * 3. Evasión sistemática (ausencia registros, efectivo prevalente)
 * 4. Sobre-facturación (facturación > servicios reales)
 * 5. Estructura opaca (múltiples intermediarios, offshoring)
 * 
 * @param array $indicadores  Booleanos o scores [
 *     'facturacion_desproporcionada' => bool,
 *     'intermitencia_sospechosa' => bool,
 *     'evasion_sistematica' => bool,
 *     'sobre_facturacion' => bool,
 *     'estructura_opaca' => bool
 * ]
 * @return array ['nivel' => string, 'score' => float, 'riesgo_detectado' => bool, 'recomendación' => string]
 */
function detectar_fraude_laboral($indicadores = []) {
    
    // Normalizar entrada
    $indicadores = array_merge([
        'facturacion_desproporcionada' => false,
        'intermitencia_sospechosa' => false,
        'evasion_sistematica' => false,
        'sobre_facturacion' => false,
        'estructura_opaca' => false
    ], $indicadores);
    
    $indicadores = array_map('ml_boolish', $indicadores);

    // Contar indicadores presentes (cada uno vale 1 punto)
    $presentes = array_sum(array_map('intval', array_values($indicadores)));
    
    // Score: 0-5 (cantidad de indicadores detectados)
    $score = $presentes / 5 * 100;  // Porcentaje
    
    // Clasificar intensidad
    if ($presentes >= 4) {
        $nivel = 'CRÍTICO';
        $riesgo = true;
        $recomendacion = "Alto riesgo de simulación contractual. Requiere revisión integral: análisis RIIBD, denuncias AFIP, investigación privada. Considere acciones penales Art. 139 CP (defraudación).";
    } elseif ($presentes >= 3) {
        $nivel = 'ALTO';
        $riesgo = true;
        $recomendacion = "Fuerte sospecha de fraude. Solicitar informes: UIF (lavado), AFIP (inspección), ANSES (aportes). Vigilancia de retenciones.";
    } elseif ($presentes >= 2) {
        $nivel = 'MEDIO';
        $riesgo = true;
        $recomendacion = "Indicadores sospechosos presentes. Solicitar prueba adicional, auditoría contable, verificaciones puntuales de terceros.";
    } elseif ($presentes === 1) {
        $nivel = 'BAJO';
        $riesgo = false;
        $recomendacion = "Patrón aislado. No conclusivo para fraude, pero requiere seguimiento y contexto adicional.";
    } else {
        $nivel = 'NINGUNO';
        $riesgo = false;
        $recomendacion = "No detectados indicadores de fraude. Relación formal, presunción de legalidad.";
    }
    
    $indicadores_presentes = [];
    foreach ($indicadores as $indicador => $presente) {
        if ($presente) {
            $indicadores_presentes[] = str_replace('_', ' ', ucfirst($indicador));
        }
    }
    
    return [
        'nivel' => $nivel,
        'score' => round($score, 1),
        'indicadores_detectados' => $presentes,
        'indicadores_listado' => $indicadores_presentes,
        'riesgo_detectado' => $riesgo,
        'análisis' => "Patrón con $presentes/5 indicadores. Nivel: $nivel.",
        'recomendación' => $recomendacion,
        'base_legal' => 'Art. 139 CP (Defraudación), Art. 23 LCT (Presunción)',
        'detalles' => [
            'facturacion_desproporcionada' => $indicadores['facturacion_desproporcionada'],
            'intermitencia_sospechosa' => $indicadores['intermitencia_sospechosa'],
            'evasion_sistematica' => $indicadores['evasion_sistematica'],
            'sobre_facturacion' => $indicadores['sobre_facturacion'],
            'estructura_opaca' => $indicadores['estructura_opaca']
        ]
    ];
}

/**
 * evaluar_dano_complementario() — Calcula daños morales, patrimoniales y reputacionales
 * 
 * Art. 527 CCCN (Daño): Reparable cuando causa "menoscabo patrimonial o extrapatrimonial"
 * 
 * 3 categorías de daño complementario (adicional a indemnización por extinción):
 * 1. DAÑO MORAL (20-50% de indemnización base): Sufrimiento, angustia, desprestigio
 * 2. DAÑO PATRIMONIAL (lucro causado + costos litigio): Pérdida económica indirecta
 * 3. DAÑO REPUTACIONAL (5-15% de salario medio): Afectación profesional futura
 * 
 * @param float $indemnizacion_base       Monto base (indemnización por extinción)
 * @param float $salario_promedio         Salario promedio para cálculos
 * @param string $tipo_extincion          'despido', 'renuncia_previa', 'constructivo', 'suspensión'
 * @param bool $fue_violenta              Si extinción fue violenta (discriminación, acoso)
 * @param int $meses_litigio              Meses de proceso judicial (default: 36)
 * @return array  ['daño_moral' => float, 'daño_patrimonial' => float, 'daño_reputacional' => float, 'total' => float]
 */
function evaluar_dano_complementario(
    $indemnizacion_base,
    $salario_promedio,
    $tipo_extincion = 'despido',
    $fue_violenta = false,
    $meses_litigio = 36
) {
    
    $indemnizacion_base = floatval($indemnizacion_base);
    $salario_promedio = floatval($salario_promedio);
    $meses_litigio = intval($meses_litigio);
    $fue_violenta = ml_boolish($fue_violenta);
    
    // ─────────────────────────────────────────────────────────────────────────
    // 1. DAÑO MORAL (20-50% indemnización base)
    // ─────────────────────────────────────────────────────────────────────────
    
    $porcentaje_moral = match ($tipo_extincion) {
        'despido' => 0.25,           // 25% — despido directo
        'renuncia_previa' => 0.15,   // 15% — presión psicológica
        'constructivo' => 0.40,      // 40% — terminación injusta por actos
        'suspensión' => 0.20,        // 20% — incertidumbre
        default => 0.20
    };
    
    // Multiplicador si fue violenta (discriminación, acoso, etc.)
    if ($fue_violenta) {
        $porcentaje_moral = min(0.50, $porcentaje_moral * 1.5);  // Hasta 50% máximo
    }
    
    $dano_moral = $indemnizacion_base * $porcentaje_moral;
    
    // ─────────────────────────────────────────────────────────────────────────
    // 2. DAÑO PATRIMONIAL (Lucro causado + Costos)
    // ─────────────────────────────────────────────────────────────────────────
    
    // Lucro cesante: Ingresos no percibidos durante litigio (salario × meses / 12)
    $lucro_cesante = ($salario_promedio / 12) * $meses_litigio;
    
    // Costos litigio (honorarios abogado, peritos, etc.) ≈ 15-20% de lucro
    $costos_litigio = $lucro_cesante * 0.15;
    
    $dano_patrimonial = $lucro_cesante + $costos_litigio;
    
    // ─────────────────────────────────────────────────────────────────────────
    // 3. DAÑO REPUTACIONAL (5-15% salario promedio)
    // ─────────────────────────────────────────────────────────────────────────
    
    $criterioReputacional = function_exists('ml_admin_runtime_get')
        ? ml_admin_runtime_get('calculation_rules.dano_complementario.reputacional', [])
        : [];
    $porcentajesReputacionales = is_array($criterioReputacional['percentages'] ?? null)
        ? $criterioReputacional['percentages']
        : [];
    $tiposHabilitados = is_array($criterioReputacional['allowed_types'] ?? null)
        ? $criterioReputacional['allowed_types']
        : [];
    $reputacionalHabilitado = ($criterioReputacional['enabled'] ?? true) !== false;
    $reputacionalRequiereViolencia = ($criterioReputacional['requires_violence'] ?? false) === true;

    $porcentaje_reputacional = floatval(
        $porcentajesReputacionales[$tipo_extincion]
        ?? $porcentajesReputacionales['default']
        ?? 0
    );

    $aplicaReputacional = $reputacionalHabilitado
        && (!$reputacionalRequiereViolencia || $fue_violenta)
        && (empty($tiposHabilitados) || in_array($tipo_extincion, $tiposHabilitados, true))
        && $porcentaje_reputacional > 0;

    if ($aplicaReputacional && $fue_violenta) {
        $porcentaje_reputacional = min(0.20, $porcentaje_reputacional * 1.5);
    }

    if (!$aplicaReputacional) {
        $porcentaje_reputacional = 0.0;
    }

    $dano_reputacional = $salario_promedio * $porcentaje_reputacional;
    $criterioReputacionalTexto = function_exists('ml_admin_runtime_get')
        ? (string) ml_admin_runtime_get('ui.dano_complementario.reputacional_criterio', '')
        : '';
    
    // ─────────────────────────────────────────────────────────────────────────
    // TOTAL DAÑO COMPLEMENTARIO
    // ─────────────────────────────────────────────────────────────────────────
    
    $total = $dano_moral + $dano_patrimonial + $dano_reputacional;
    
    return [
        'daño_moral' => round($dano_moral, 2),
        'daño_patrimonial' => round($dano_patrimonial, 2),
        'daño_reputacional' => round($dano_reputacional, 2),
        'total_daño_complementario' => round($total, 2),
        
        'desglose' => [
            'daño_moral' => [
                'monto' => round($dano_moral, 2),
                'porcentaje_indemnizacion' => round($porcentaje_moral * 100, 1) . '%',
                'base_legal' => 'Art. 527 CCCN (Daño Moral)'
            ],
            'daño_patrimonial' => [
                'lucro_cesante' => round($lucro_cesante, 2),
                'costos_litigio' => round($costos_litigio, 2),
                'subtotal' => round($dano_patrimonial, 2),
                'meses_litigio' => $meses_litigio,
                'base_legal' => 'Art. 1738 CCCN (Lucro cesante)'
            ],
            'daño_reputacional' => [
                'monto' => round($dano_reputacional, 2),
                'porcentaje_salario' => round($porcentaje_reputacional * 100, 1) . '%',
                'base_legal' => 'Art. 527 CCCN (Daño patrimonial futuro)',
                'aplica' => $aplicaReputacional,
                'criterio' => $criterioReputacionalTexto,
            ]
        ],
        
        'análisis' => "Extinción: $tipo_extincion. " . ($fue_violenta ? "Con violencia/discriminación. " : "") . "Daño total: \$$" . number_format($total, 2),
        'nota' => 'Adicional a indemnización por extinción (Art. 245 LCT)'
    ];
}

?>
