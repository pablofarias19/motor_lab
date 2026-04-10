# GUÍA DE IMPLEMENTACIÓN — Motor v2.1
## Cambios de Código para IrilEngine.php y EscenariosEngine.php

---

## **1. ACTUALIZACIÓN DE IrilEngine.php**

### **1.1 Agregar Método de Validación — Art. 23 LCT (Presunción Laboral)**

Validar si presunción de relación laboral opera según Ley 27.802 (Art. 23):

```php
/**
 * validarPresuncionLaboral() — Art. 23 LCT (Ley 27.802)
 * 
 * Presunción NO opera cuando co-existen 3 condiciones:
 * 1. Existe facturación de trabajador a empleador
 * 2. Existe pago bancario directo
 * 3. Existe contrato escrito
 * 
 * @param array $documento ['tiene_facturacion', 'pago_bancario', 'contrato_escrito']
 * @return array ['presuncion_opera' => bool, 'controles_presentes' => int, 'analisis' => string]
 */
private function validarPresuncionLaboral($documento) {
    $controles = 0;
    $facturacion = ($documento['tiene_facturacion'] ?? false) === 'si';
    $pago_bco = ($documento['pago_bancario'] ?? false) === 'si';
    $contrato = ($documento['contrato_escrito'] ?? false) === 'si';
    
    if ($facturacion) $controles++;
    if ($pago_bco) $controles++;
    if ($contrato) $controles++;
    
    return [
        'presuncion_opera' => $controles < 3,  // Opera si FALTA al menos 1 control
        'controles_presentes' => $controles,
        'facturacion' => $facturacion,
        'pago_bancario' => $pago_bco,
        'contrato_escrito' => $contrato,
        'analisis' => match($controles) {
            0 => '✓ Presunción OPERA (sin controles distorsionadores)',
            1 => '⚠ Presunción DEBILITADA (1 control presente)',
            2 => '⚠⚠ Presunción CUESTIONABLE (2 controles)',
            3 => '✗ Presunción NO OPERA (relación atípica)',
        }
    ];
}
```

### **1.2 Agregar Método — Art. 30 LCT (Responsabilidad Solidaria)**

Validar 5 controles para exención de responsabilidad solidaria:

```php
/**
 * validarResponsabilidadSolidaria() — Art. 30 LCT (Ley 27.802)
 * 
 * Principal EXENTO de solidaria si valida 5 controles:
 * 1. CUIL: Contratista registrado (verificable en AFIP)
 * 2. Aportes: Pagos periódicos de seguridad social
 * 3. Pago: Cancelación directa sin intermediarios
 * 4. CBU: Liquidación bancaria verificable
 * 5. ART: Seguro vigente contra accidentes
 * 
 * @param array $situacion Principal + contratista data
 * @return array ['exento' => bool, 'controles_validados' => int, 'riesgo_solidario' => float]
 */
private function validarResponsabilidadSolidaria($situacion) {
    $controles_validados = 0;
    $detalles = [];
    
    // Control 1: CUIL
    if (!empty($situacion['cuil_contratista']) 
        && preg_match('/^[0-9]{11}$/', $situacion['cuil_contratista'])) {
        $controles_validados++;
        $detalles['cuil'] = '✓ Registrado';
    } else {
        $detalles['cuil'] = '✗ No verificado';
    }
    
    // Control 2: Aportes a seg.soc
    if ($situacion['aportes_seg_social'] === 'si') {
        $controles_validados++;
        $detalles['aportes'] = '✓ Contribuciones activas';
    } else {
        $detalles['aportes'] = '✗ Sin registros';
    }
    
    // Control 3: Pago directo
    if ($situacion['pago_directo'] === 'si') {
        $controles_validados++;
        $detalles['pago'] = '✓ Cancelado por principal';
    } else {
        $detalles['pago'] = '✗ Intermediarios/transferencias';
    }
    
    // Control 4: CBU
    if (!empty($situacion['cbu_contratista']) && strlen($situacion['cbu_contratista']) === 22) {
        $controles_validados++;
        $detalles['cbu'] = '✓ Banco verificado';
    } else {
        $detalles['cbu'] = '✗ Sin verificación bancaria';
    }
    
    // Control 5: ART vigente
    if (!empty($situacion['art_vigente']) && $situacion['art_vigente'] === 'si') {
        $controles_validados++;
        $detalles['art'] = '✓ Cobertura activa';
    } else {
        $detalles['art'] = '✗ ART ausente o vencida';
    }
    
    // Factor de exención: Si todos = 5 controles, exención 100%
    $factor_exension = max(1, 6 - $controles_validados);  // [1..6]
    $riesgo_multiplicador = $factor_exension / 5;  // [0.2 ... 1.2]
    
    return [
        'exento' => $controles_validados === 5,
        'controles_validados' => $controles_validados,
        'factor_exension' => $factor_exension,
        'detalles' => $detalles,
        'analisis' => match($controles_validados) {
            5 => '✓ EXENTO TOTAL (todos controles presentes)',
            4 => '⚠ Riesgo reducido (1 control falta)',
            3 => '⚠⚠ Riesgo moderado (2 controles faltan)',
            default => '✗ SOLIDARIA COMPLETA (menos de 3 controles)',
        }
    ];
}
```

### **1.3 Corregir Integración de Mes de Despido**

**Reemplazar en calcularExposicion()**:

```php
// ANTIGUO (INCORRECTO):
// $integracion = ($salario / 30) * 15;

// NUEVO (correcto Art. 233):
$dia_despido = intval($situacion['dia_despido'] ?? 15); // default: día 15
$integracion = ($salario / 30) * (30 - $dia_despido);

$conceptos['integracion_mes'] = [
    'descripcion' => "Integración mes de despido (día {$dia_despido} — {30 - $dia_despido} días)",
    'monto' => round($integracion, 2),
    'base_legal' => 'Art. 233 LCT',
    'formula_aplicada' => "(Salario / 30) × (30 - {$dia_despido})"
];
```

### **1.4 Agregar Validación Vizzotti para Indemnización**

```php
/**
 * aplicarVizzotti() — Valida que indemnización por antigüedad no sea inferior al 67%
 * del salario real, incluso con topes convencionales
 */
private function aplicarVizzotti($indemnizacion_calculada, $salario_real, $antiguedad_anos) {
    $piso_vizzotti = $salario_real * $antiguedad_anos * 0.67;
    
    return max($indemnizacion_calculada, $piso_vizzotti);
}

// En calcularExposicion():
$anosCompletos = max(1, (int) floor($antiguedadA));
$indemnizacion = $salario * $anosCompletos;

// NUEVO: Aplicar Vizzotti
$indemnizacion = $this->aplicarVizzotti($indemnizacion, $salario, $anosCompletos);

$conceptos['indemnizacion_antiguedad'] = [
    'descripcion' => "Indemnización por antigüedad (Art. 245) — {$anosCompletos} años",
    'monto' => round($indemnizacion, 2),
    'base_legal' => 'Art. 245 LCT + Fallo Vizzotti (67% mínimo)',
    'validacion' => 'Aplicado piso Vizzotti'
];
```

### **1.5 Implementar IBM Dinámico con RIPTE**

```php
/**
 * calcularIBMconRIPTE() — IBM ajustado por 12 meses históricos × coeficientes RIPTE
 * 
 * @param array $salarios_historicos [mes_id => monto]
 * @param DateTime $fecha_accidente
 * @param array $ripte_table [año-mes => coeficiente]
 * @return float IBM calculado
 */
public function calcularIBMconRIPTE($salarios_historicos, $fecha_accidente, $ripte_table) {
    $ripte_accidente = $ripte_table[$fecha_accidente->format('Y-m')] ?? 100.0;
    $salarios_ajustados = [];
    
    // Iterar 12 meses hacia atrás
    for ($i = 0; $i < 12; $i++) {
        $fecha_mes = clone $fecha_accidente;
        $fecha_mes->modify("-{$i} month");
        $clave_mes = $fecha_mes->format('Y-m');
        
        $ripte_mes = $ripte_table[$clave_mes] ?? 100.0;
        
        if (isset($salarios_historicos[$clave_mes])) {
            $salario_base = floatval($salarios_historicos[$clave_mes]);
            $salario_ajustado = $salario_base * ($ripte_accidente / $ripte_mes);
            array_push($salarios_ajustados, $salario_ajustado);
        }
    }
    
    // Si hay menos de 12 meses, promediar con los disponibles
    if (empty($salarios_ajustados)) {
        return 0.0; // Sin datos
    }
    
    $ibm = array_sum($salarios_ajustados) / count($salarios_ajustados);
    
    return round($ibm, 2);
}
```

Uso en calcularExposicion():

```php
// En la sección de ACCIDENTE LABORAL:
$ripte_table = $this->obtenerTablasRIPTE(); // Cargar de BD o JSON
$fecha_accidente = new DateTime($situacion['fecha_accidente']);

// Si existe histórico de 12 meses
if (!empty($situacion['salarios_historicos'])) {
    $ibm = $this->calcularIBMconRIPTE(
        $situacion['salarios_historicos'],
        $fecha_accidente,
        $ripte_table
    );
} else {
    // Fallback: usar salario actual sin ajuste
    $ibm = $salario;
}

// Luego aplicar en fórmula LRT:
$montoLRT = $p['coeficiente_lrt'] * $ibm * ($p['factor_edad_limite'] / $edad) * ($incapacidad / 100);
```

### **1.6 Agregar Método — Detección de Fraude Laboral**

Implementar detección de fraude según patrones de Ley 27.802:

```php
/**
 * detectarFraudeLaboral() — Ley 27.802
 * 
 * Identifica patrones de fraude laboral:
 * - Facturación sin pago real (teórica)
 * - Contrato con roles divergentes
 * - Ausencia de registros vs. realidad laboral
 * - Rotación sospechosa de contratistas
 * 
 * @param array $documento
 * @return array ['fraude_nivel' => 'BAJO|MEDIO|ALTO', 'indicadores' => [...], 'score' => float]
 */
private function detectarFraudeLaboral($documento) {
    $score = 0;
    $indicadores = [];
    
    // Indicador 1: Facturación sin pago real
    if ($documento['tiene_facturacion'] === 'si' && $documento['pago_bancario'] !== 'si') {
        $score += 2;
        $indicadores[] = 'Facturación sin pago bancario traceable';
    }
    
    // Indicador 2: Contrato divergente (rol sospechoso)
    if (!empty($documento['contrato_rol']) && !empty($documento['actividad_realizada'])) {
        if ($documento['contrato_rol'] !== $documento['actividad_realizada']) {
            $score += 1.5;
            $indicadores[] = "Rol contratado '{$documento['contrato_rol']}' ≠ actividad real '{$documento['actividad_realizada']}'";
        }
    }
    
    // Indicador 3: Sin registros pero con tareas
    if ($documento['registro_trabajo'] === 'no' && !empty($documento['actividad_realizada'])) {
        $score += 2;
        $indicadores[] = 'Trabajo no registrado pero con evidencia de tareas';
    }
    
    // Indicador 4: Tiempo parcial declarado vs. jornadas reales
    if (!empty($documento['horas_declaradas']) && !empty($documento['horas_reales'])) {
        $diff_pct = abs($documento['horas_reales'] - $documento['horas_declaradas']) 
                  / max($documento['horas_reales'], $documento['horas_declaradas']);
        if ($diff_pct > 0.3) {  // Diferencia > 30%
            $score += 1.5;
            $indicadores[] = "Brecha de horas: declaradas {$documento['horas_declaradas']}h vs. reales {$documento['horas_reales']}h";
        }
    }
    
    // Indicador 5: Contratistas rotando frecuentemente
    if (!empty($documento['cambios_contratista_6m'])) {
        $cambios = intval($documento['cambios_contratista_6m']);
        if ($cambios > 3) {
            $score += 1 * min(2, $cambios - 3);  // Max +2 por rotación
            $indicadores[] = "Rotación sospechosa: {$cambios} cambios en 6 meses";
        }
    }
    
    // Clasificación final
    $nivel = match(true) {
        $score >= 4.5 => 'ALTO',
        $score >= 2.5 => 'MEDIO',
        default => 'BAJO'
    };
    
    return [
        'fraude_nivel' => $nivel,
        'score' => round($score, 2),
        'indicadores' => $indicadores,
        'recomendacion' => match($nivel) {
            'ALTO' => '🔴 Requiere denunciar fraude a DGI/SRT/Justicia',
            'MEDIO' => '🟡 Aumentar vigilancia + solicitar documentación',
            'BAJO' => '🟢 Presunción operante',
        }
    ];
}
```

### **1.7 Agregar Método — Evaluación de Daño Complementario**

Calcular daños más allá de indemnización por antigüedad (Art. 245 LCT):

```php
/**
 * evaluarDanoComplementario() — Ley 27.802
 * 
 * Calcula daños derivados de la extinción:
 * - Daño moral: Hasta 50% según jurisprudencia Lois
 * - Daño patrimonial: Lucro cesante + costo de búsqueda laboral
 * - Daño reputacional: Si extinción por causa deshonrosa
 * 
 * @param array $situacion
 * @return array ['daño_moral' => float, 'daño_patrimonial' => float, 'total' => float]
 */
private function evaluarDanoComplementario($situacion) {
    $base = floatval($situacion['salario'] ?? 0);
    $antiguedad = intval($situacion['antiguedad_meses'] ?? 0);
    $causa = $situacion['causa_extincion'] ?? 'otras';
    
    // 1. DAÑO MORAL: Hasta 50% (jurisprudencia Lois 2026), normalmente 25-35%
    $pct_daño_moral = match($causa) {
        'despido_discriminatorio' => 0.50,
        'despido_represalia' => 0.45,
        'despido_sin_justa_causa' => 0.35,
        'renuncia_forzada' => 0.25,
        default => 0.20  // Extinción acuerdo o período prueba
    };
    $daño_moral = $base * $pct_daño_moral;
    
    // 2. DAÑO PATRIMONIAL: Lucro cesante (tiempo búsqueda laboral)
    // Supuesto: 3 meses promedio de desempleo
    $meses_desempleo = 3;
    $daño_lucro_cesante = $base * $meses_desempleo;
    
    // 3. Costos asociados (abogado, psicólogo, reubicación)
    $costo_servicios = min($base * 0.15, 150000);  // Máximo $150k
    
    $daño_patrimonial = $daño_lucro_cesante + $costo_servicios;
    
    // 4. Majoración si extinción fue violenta o con pertsecución
    if (!empty($situacion['fue_violenta']) && $situacion['fue_violenta'] === 'si') {
        $daño_moral *= 1.25;
        $daño_patrimonial *= 1.25;
    }
    
    return [
        'daño_moral' => round($daño_moral, 2),
        'daño_patrimonial' => round($daño_patrimonial, 2),
        'total_danos' => round($daño_moral + $daño_patrimonial, 2),
        'detalles' => [
            'daño_moral_pct' => round($pct_daño_moral * 100, 0) . '%',
            'lucro_cesante_meses' => $meses_desempleo,
            'costo_servicios' => round($costo_servicios, 2)
        ]
    ];
}
```

### **1.8 Reemplazar Multas Derogadas por Alerta de Fraude**

En lugar de calcular multas (Ley 24.013/25.323 DEROGADAS), mostrar alerta de evaluación fraude:

```php
// En calcularExposicion(), sección de MULTAS:

// ANTIGUO (INCORRECTO):
// if ($tipoReg === 'no_registrado') {
//     $montoMulta = ($salario * 0.25) * $antiguedadM;
//     $conceptos['multa_art8_24013'] = /* ... */;
// }

// NUEVO (Ley 27.802):
if ($tipoReg === 'no_registrado') {
    $evaluacion_fraude = $this->detectarFraudeLaboral([
        'tiene_facturacion' => $documento['tiene_facturacion'] ?? 'no',
        'pago_bancario' => $documento['pago_bancario'] ?? 'no',
        'contrato_rol' => $documento['contrato_rol'] ?? '',
        'actividad_realizada' => $documento['actividad_realizada'] ?? '',
        'registro_trabajo' => 'no'
    ]);
    
    // Alerta, NO multa
    $alertas[] = [
        'tipo' => 'DEROGACION_MULTA_24013',
        'level' => 'WARNING',
        'mensaje' => 'Ley 24.013 (multa trabajo no registrado) DEROGADA por Ley 27.802',
        'sugerencia' => 'Evaluar fraude laboral según patrones Art. 23/30',
        'fraude_evaluacion' => $evaluacion_fraude
    ];
}

// Multa Art. 80 (sigue activa):
$conceptos['multa_art80_lct'] = [
    'descripcion' => 'Certificados de Trabajo (Art. 80)',
    'monto' => round($salario * 3, 2),
    'base_legal' => 'Art. 80 LCT (AÚN VIGENTE)',
    'nota' => 'Única sanción pecuniaria post-Ley 27.802'
];
```

---

## **2. ACTUALIZACIÓN DE EscenariosEngine.php**

### **2.1 Agregar Contexto de Evaluación — Art. 23/30**

Pasar evaluaciones de presunción y solidaria a escenarios:

```php
/**
 * generarEscenarios() — ACTUALIZADO v2.1
 * 
 * Ahora inserta en cada escenario:
 * - Evaluación presunción (Art. 23)
 * - Evaluación solidaria (Art. 30)  
 * - Evaluación fraude
 * - Daños complementarios
 */
public function generar(array $parametros_entrada): array {
    // ... código existente ...
    
    // NUEVOS PARÁMETROS:
    $validacion_presuncion = $parametros_entrada['validacion_presuncion'] ?? [];
    $validacion_solidaria = $parametros_entrada['validacion_solidaria'] ?? [];
    $evaluacion_fraude = $parametros_entrada['evaluacion_fraude'] ?? [];
    
    $escenarios = [
        $this->escenarioAcuerdo($parametros_entrada),
        $this->escenarioLitigi($parametros_entrada),
        $this->escenarioCivil($parametros_entrada)
    ];
    
    // Agregar contexto Art. 23/30/fraude a cada escenario
    foreach ($escenarios as &$escenario) {
        $escenario['contexto_legal'] = [
            'presuncion_opera' => $validacion_presuncion['presuncion_opera'] ?? true,
            'solidaria_exento' => $validacion_solidaria['exento'] ?? false,
            'fraude_nivel' => $evaluacion_fraude['fraude_nivel'] ?? 'BAJO',
            'danos_complementarios' => $parametros_entrada['danos_complementarios'] ?? []
        ];
    }
    
    return $escenarios;
}
```

### **2.2 Agregar Intereses Judiciales en Escenario B**

```php
/**
 * calcularInteresesJudiciales() — Aplica tasa de interés según jurisdicción
 * 
 * @param float $monto_base
 * @param string $provincia
 * @param int $meses_duracion
 * @return float Monto con intereses acumulados
 */
private function calcularInteresesJudiciales($monto_base, $provincia, $meses_duracion) {
    // Mapeo de tasas por jurisdicción
    $tasas = [
        'CABA' => 0.065,        // Acta 2764 (variable, usamos 6.5% promedio)
        'Buenos Aires' => 0.062, // IPC + 2.5% puro
        'Córdoba' => 0.060,
        'Santa Fe' => 0.058,
        'default' => 0.060      // 6% anual base
    ];
    
    $tasa_anual = $tasas[$provincia] ?? $tasas['default'];
    
    // Cálculo exponencial: Monto × (1 + tasa)^(meses/12)
    $tasa_periódica = $tasa_anual / 12; // Tasa mensual
    $factor_acumulación = pow(1 + $tasa_periódica, $meses_duracion);
    
    return $monto_base * $factor_acumulacion;
}
```

Integrar en escenarioLitigio():

```php
private function escenarioLitigio(
    float $totalBase,
    float $totalConMultas,
    float $honorarios,
    float $costas,
    float $iril,
    string $tipoConflicto,
    string $tipoUsuario,
    string $provincia = 'CABA'  // NUEVO PARÁMETRO
): array {
    // ... cálculos originales ...
    
    $beneficio = $totalConMultas * $cfg['tasa_recupero_total'];
    $costo = $honorarios + ($costas * $cfg['factor_costas_riesgo']);
    $vbp = $beneficio - $costo;
    
    // NUEVO: Aplicar intereses judiciales
    $duracionPromedio = $cfg['duracion_promedio']; // 36 meses
    $vbp_con_intereses = $this->calcularInteresesJudiciales($vbp, $provincia, $duracionPromedio);
    
    $riesgoInst = min(5.0, $iril * $cfg['factor_riesgo']);
    
    $vae = $duracionPromedio > 0 && $riesgoInst > 0
        ? round($vbp_con_intereses / ($duracionPromedio * $riesgoInst), 0)
        : 0;
    
    return [
        'codigo' => 'B',
        'nombre' => 'Litigio Completo',
        'descripcion' => 'Proceso judicial completo ante el fuero laboral correspondiente.',
        'beneficio_estimado' => round($beneficio, 2),
        'beneficio_con_intereses' => round($vbp_con_intereses, 2), // NUEVO CAMPO
        'costo_estimado' => round($costo, 2),
        'vbp' => round($vbp, 2),
        'vbp_con_intereses' => round($vbp_con_intereses, 2), // NUEVO
        'vae' => $vae,
        'vae_ajustado_intereses' => round($vae, 0), // Para comparación
        'duracion_min_meses' => 24,
        'duracion_max_meses' => 60,
        'duracion_promedio' => $duracionPromedio,
        'riesgo_institucional' => round($riesgoInst, 1),
        'tasa_interes_anual' => round(($iril > 0 ? 
            ($tasas[$provincia] ?? 0.060) : 0.060) * 100, 2) . '%',
        'provincia' => $provincia,
        // ... resto de campos ...
    ];
}
```

### **2.3 Agregar Daño Moral en Vía Civil**

En EscenariosEngine, dentro del escenario de civil complementaria:

```php
/**
 * Calcular daño moral como 20% de la incapacidad
 */
private function calcularDañoMoral($monto_incapacidad) {
    return $monto_incapacidad * 0.20;
}

// Uso en generarEscenarios() o método específico ART:
if (!$tieneArt && $incapacidad > 0) {
    $monto_civil = /* ... cálculo Méndez ... */;
    
    // Aplicar descuento anticipado
    $tasa_desc = match(true) {
        $meses_anticipado <= 12 => 0.04,
        $meses_anticipado <= 36 => 0.05,
        default => 0.06
    };
    
    $descuento = $tasa_desc * ($meses_anticipado / 12);
    $monto_con_descuento = $monto_civil * (1 - $descuento);
    
    // Agregar daño moral
    $daño_moral = $this->calcularDañoMoral($monto_con_descuento);
    $monto_final = $monto_con_descuento + $daño_moral;
    
    // Registrar en conceptos
    $conceptos['via_civil_descuento'] = [
        'monto_bruto' => round($monto_civil, 2),
        'tasa_descuento' => round($descuento * 100, 2) . '%',
        'monto_con_descuento' => round($monto_con_descuento, 2),
        'daño_moral_20pct' => round($daño_moral, 2),
        'monto_final' => round($monto_final, 2)
    ];
}
```

### **2.4 Pasar Provincia a generarEscenarios()**

En procesar_analisis.php:

```php
// ANTES:
// $escenarios = $scenEngine->generarEscenarios(
//     $exposicion, $irilScore, $tipoConflicto, $tipoUsuario, $situacion
// );

// AHORA:
$provincia = $datosLaborales['provincia'] ?? 'CABA';
$escenarios = $scenEngine->generarEscenarios(
    $exposicion, 
    $irilScore, 
    $tipoConflicto, 
    $tipoUsuario, 
    $situacion,
    $provincia  // NUEVO PARÁMETRO
);
```

---

## **3. TABLA RIPTE — ESTRUCTURA EN BD**

### **3.1 Crear Tabla en MySQL**

```sql
CREATE TABLE ripte_coeficientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes_ano VARCHAR(7) NOT NULL UNIQUE,  -- Formato: YYYY-MM
    coeficiente DECIMAL(12, 2) NOT NULL,
    fecha_publicacion DATE,
    fuente VARCHAR(100) DEFAULT 'SRT',
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Datos iniciales (ejemplos):
INSERT INTO ripte_coeficientes (mes_ano, coeficiente, fecha_publicacion) VALUES
('2026-02', 154800.78, '2026-02-15'),
('2026-01', 152100.45, '2026-01-15'),
('2025-12', 149500.22, '2025-12-15'),
('2025-09', 142100.00, '2025-09-15'),
('2025-08', 141200.50, '2025-08-15');
```

### **3.2 Función para Obtener Tabla RIPTE**

```php
private function obtenerTablasRIPTE() {
    // Conectar a BD
    $db = new DatabaseManager();
    $resultado = $db->query("
        SELECT mes_ano, coeficiente 
        FROM ripte_coeficientes 
        WHERE activo = true 
        ORDER BY mes_ano DESC 
        LIMIT 36
    ");
    
    $ripte_table = [];
    foreach ($resultado as $row) {
        $ripte_table[$row['mes_ano']] = floatval($row['coeficiente']);
    }
    
    return $ripte_table;
}
```

---

## **4. PUNTOS DE INTEGRACIÓN EN WIZARD**

### **4.1 Paso 2 — Agregar Campo "Día de Despido"**

```html
<!-- En index.php wizard -->
<div class="step" id="step-2">
    <h3>Datos Laborales</h3>
    
    <!-- ... campos existentes ... -->
    
    <!-- NUEVO -->
    <label for="dia_despido">¿Qué día del mes fue comunicado el despido?</label>
    <input type="number" id="dia_despido" name="dia_despido" min="1" max="31" value="15" required>
    <small>Ejm: 5, 15, 28. Afecta cálculo de integración (Art. 233)</small>
    
</div>
```

En wizard.js:

```javascript
// Agregar al JSON de envío:
var datos = {
    // ... campos existentes ...
    dia_despido: document.getElementById('dia_despido').value,
    check_inconstitucionalidad: document.getElementById('check_inconstit')?.checked ?? false
};
```

### **4.2 Paso 2 — Verificar "Check de Inconstitucionalidad"**

```html
<!-- En step-2 o step-4 -->
<label class="checkbox-custom">
    <input type="checkbox" id="check_inconstit" name="check_inconstitucionalidad">
    <span>✓ Activar Check de Inconstitucionalidad (Ley Bases)</span>
    <small>Si marca, las multas (Art. 80, Ley 24.013, 25.323) se aplican incluso posterior a 09/07/2024</small>
</label>
```

---

## **5. VERIFICACIÓN DE CAMBIOS**

Después de implementar, ejecutar casos de prueba:

```
TEST 1: Despido anterior a 09/07/2024
- Input: 15/06/2024
- Esperado: Multas aplicadas normalmente
- Verificar: $600K+ (Ley 24.013)

TEST 2: Despido posterior a 09/07/2024, sin check
- Input: 15/10/2024, check_inconstit = false
- Esperado: Multas = $0
- Verificar: Total base = $612K aproximadamente

TEST 3: Accidente con RIPTE ajustado
- Input: 12 meses históricos, RIPTE variante
- Esperado: IBM promediado y ajustado > salario actual
- Verificar: P_ART_final ≥ piso mínimo

TEST 4: Intereses judiciales en Litigio
- Input: VBP $1.3M, CABA, 36 meses
- Esperado: VBP final ~$1.55M (+19%)
- Verificar: VAE aumenta de 11K a 13.6K
```

---

**Implementación**: 2-3 semanas de desarrollo + testing
**Criticidad**: ALTA — Cambios normativos vinculantes
**Rollback**: Mantener v2.0 como fallback en branch "legacy"
