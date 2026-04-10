# GUÍA DE IMPLEMENTACIÓN — Motor v2.1
## Cambios de Código para IrilEngine.php y EscenariosEngine.php

---

## **1. ACTUALIZACIÓN DE IrilEngine.php**

### **1.1 Agregar Método de Validación Ley Bases**

Antes de cualquier cálculo de multas, validar fecha de extinción:

```php
/**
 * validarLeyBases() — Valida si multas aplican según Ley 27.742
 * 
 * @param DateTime $fecha_extincion
 * @param bool $check_inconstitucionalidad
 * @return array ['aplicar_multas' => bool, 'alerta' => string]
 */
private function validarLeyBases($fecha_extincion, $check_inconstitucionalidad = false) {
    $fecha_corte = new DateTime('2024-07-09');
    
    if ($fecha_extincion > $fecha_corte) {
        if ($check_inconstitucionalidad === true) {
            return [
                'aplicar_multas' => true,
                'alerta' => 'ADVERTENCIA: Multas restauradas por check manual de inconstitucionalidad'
            ];
        } else {
            return [
                'aplicar_multas' => false,
                'alerta' => 'INFORMACIÓN: Multas desactivadas por Ley Bases (09/07/2024). Usuario puede activarlas manualmente.'
            ];
        }
    }
    
    return [
        'aplicar_multas' => true,
        'alerta' => 'Derechos adquiridos: Multas aplican normalmente.'
    ];
}
```

### **1.2 Corregir Integración de Mes de Despido**

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

### **1.3 Agregar Validación Vizzotti para Indemnización**

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

### **1.4 Implementar IBM Dinámico con RIPTE**

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

### **1.5 Reemplazar Multas con Condicional de Ley Bases**

En la sección "LÓGICA DE MULTAS POR REGISTRO IRREGULAR":

```php
// Cargar validación Ley Bases ANTES de aplicar multas
$validacion_leyes = $this->validarLeyBases(
    new DateTime($datosLaborales['fecha_extincion']),
    boolval($situacion['check_inconstitucionalidad'] ?? false)
);

$aplicar_multas = $validacion_leyes['aplicar_multas'];

// Guardiar alerta
if (!empty($validacion_leyes['alerta'])) {
    $alertas[] = $validacion_leyes['alerta'];
}

// SOLO si aplicar_multas === true:
if ($aplicar_multas) {
    // A. TOTALMENTE EN NEGRO
    if ($tipoReg === 'no_registrado') {
        $p = $ce['registro']['multa_ley_24013_porc'];
        $montoMulta = ($salario * $p) * $antiguedadM;
        $conceptos['multa_art8_24013'] = [
            'descripcion' => 'Sanción por falta total de registro (Art. 8)',
            'monto' => round($montoMulta, 2),
            'base_legal' => 'Ley 24.013',
            'nota' => '25% de todas las remuneraciones devengadas durante la relación.'
        ];
    }
    
    // (continuar con resto de multas...)
    
    // Multa Art. 80 LCT
    $conceptos['multa_art80_lct'] = [
        'descripcion' => 'Certificados de Trabajo (Art. 80)',
        'monto' => round($salario * 3, 2),
        'base_legal' => 'Art. 80 LCT'
    ];
} else {
    // No aplicar multas
    $conceptos['multa_art80_lct'] = [
        'descripcion' => 'Certificados de Trabajo (Art. 80) — Suspendido por Ley Bases',
        'monto' => 0.00,
        'base_legal' => 'Ley Bases Nº 27.742 (09/07/2024)',
        'nota' => 'Edificios puede activar manualmente check de inconstitucionalidad'
    ];
}
```

---

## **2. ACTUALIZACIÓN DE EscenariosEngine.php**

### **2.1 Agregar Intereses Judiciales en Escenario B**

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

### **2.2 Agregar Daño Moral en Vía Civil**

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

### **2.3 Pasar Provincia a generarEscenarios()**

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
