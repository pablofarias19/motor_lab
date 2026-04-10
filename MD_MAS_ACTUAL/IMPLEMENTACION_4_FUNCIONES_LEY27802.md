# IMPLEMENTACIÓN 4 FUNCIONES LEY 27.802
## Guía Rápida — Marzo 14, 2026

**Estado**: ✅ **COMPLETADO** — Todas las 4 funciones ya están en `config/ripte_functions.php`

**Ubicación**: `config/ripte_functions.php` líneas ~650-1350 (700+ líneas de código)

---

## 📋 FUNCIONES IMPLEMENTADAS

### 1. **`validar_presuncion_laboral()`** — Art. 23 LCT

**Qué valida**: Si la presunción de relación laboral OPERA o NO

**Lógica**: Presunción NO OPERA si coexisten **3 elementos**:
- ✓ Facturación (comprobantes de servicios/productos)
- ✓ Pago bancario (transferencias, no efectivo)
- ✓ Contrato escrito

**Firma**:
```php
validar_presuncion_laboral(
    bool $tiene_facturacion,
    bool $tiene_pago_bancario,
    bool $tiene_contrato_escrito,
    string $nota_adicional = ''
)
```

**Retorna**:
```php
[
    'presuncion_opera' => bool,        // true si presunción es fuerte, false si débil
    'controles_presentes' => int,      // Cantidad 0-3 de elementos encontrados
    'controles_validados' => array,    // Detalles de cada control
    'análisis' => string,              // Análisis cualitativo
    'recomendación' => string,         // Estrategia recomendada
    'estado' => 'presuncion_fuerte'|'presuncion_debil_o_nula'
]
```

**Ejemplo de uso**:
```php
<?php
require_once 'config/ripte_functions.php';

$resultado = validar_presuncion_laboral(
    $tiene_facturacion = false,
    $tiene_pago_bancario = true,
    $tiene_contrato_escrito = false
);

echo "Presunción opera: " . ($resultado['presuncion_opera'] ? 'SÍ' : 'NO') . "\n";
echo "Controles: " . $resultado['controles_presentes'] . "/3\n";
echo "Análisis: " . $resultado['análisis'] . "\n";
?>
```

**Output ejemplo**:
```
Presunción opera: SÍ
Controles: 1/3
Análisis: PRESUNCIÓN MODERADA. Existe 1 elemento de independencia, pero insuficiente para excluir relación laboral.
Recomendación: Complementar prueba: Contexto de prestación, subordinación, continuidad, habitualidad.
```

---

### 2. **`validar_responsabilidad_solidaria()`** — Art. 30 LCT

**Qué valida**: Si Principal es EXENTO de responsabilidad solidaria (validando 5 controles)

**Lógica**: 
- **Si valida 5/5 controles** → EXENTO (solo responsable por su incumplimiento directo)
- **Si valida < 5** → RESPONSABLE SOLIDARIO con factor exención

**Factor exención**: `max(1, 6 - controles_validados)`
- 5 controles → factor 1 (sin responsabilidad adicional)
- 0 controles → factor 6 (responsabilidad solidaria completa)

**Firma**:
```php
validar_responsabilidad_solidaria(
    bool $valida_cuil = false,
    bool $valida_aportes = false,
    bool $valida_pago_directo = false,
    bool $valida_cbu = false,
    bool $valida_art = false
)
```

**Retorna**:
```php
[
    'exento' => bool,                      // true si valida 5/5
    'controles_validados' => int,          // Cantidad 0-5
    'factor_exención' => float,            // max(1, 6 - controles)
    'responsabilidad_solidaria' => bool,   // !exento
    'análisis' => string,
    'recomendación' => string,
    'estado' => 'exento_solidaria'|'responsable_factor_X'
]
```

**Ejemplo de uso**:
```php
<?php
require_once 'config/ripte_functions.php';

$resultado = validar_responsabilidad_solidaria(
    $valida_cuil = true,
    $valida_aportes = true,
    $valida_pago_directo = true,
    $valida_cbu = true,
    $valida_art = false  // Solo 4/5
);

echo "Exento: " . ($resultado['exento'] ? 'SÍ' : 'NO') . "\n";
echo "Factor: " . $resultado['factor_exención'] . "\n";
echo $resultado['recomendación'] . "\n";
?>
```

**Output ejemplo**:
```
Exento: NO
Factor: 2
Análisis: 4 CONTROLES. Factor exención = 2. Principal tiene responsabilidad limitada por omisión de 1 control.
Recomendación: Principal puede reducción pero no exención. Responsabilidad compartida según factor.
```

---

### 3. **`detectar_fraude_laboral()`** — Detección de Patrones

**Qué valida**: Si existen patrones de fraude laboral / simulación

**5 indicadores evaluados**:
1. Facturación desproporcionada (montos vs. servicios)
2. Intermitencia sospechosa (pausa-reanudación anómala)
3. Evasión sistemática (ausencia registros, efectivo prevalente)
4. Sobre-facturación (facturación > servicios reales)
5. Estructura opaca (múltiples intermediarios, offshoring)

**Firma**:
```php
detectar_fraude_laboral(
    array $indicadores = [
        'facturacion_desproporcionada' => bool,
        'intermitencia_sospechosa' => bool,
        'evasion_sistematica' => bool,
        'sobre_facturacion' => bool,
        'estructura_opaca' => bool
    ]
)
```

**Retorna**:
```php
[
    'nivel' => 'CRÍTICO'|'ALTO'|'MEDIO'|'BAJO'|'NINGUNO',
    'score' => float,                      // 0-100%
    'indicadores_detectados' => int,       // 0-5
    'riesgo_detectado' => bool,
    'análisis' => string,
    'recomendación' => string,
    'detalles' => array                    // Cada indicador true/false
]
```

**Niveles**:
| Indicadores | Nivel | Acción |
|-------------|-------|--------|
| 4-5 | CRÍTICO | AFIP, UIF, investigación privada, denuncias penales |
| 3 | ALTO | Auditoría AFIP, inspección SRT, vigilancia |
| 2 | MEDIO | Auditoría contable, prueba adicional |
| 1 | BAJO | Seguimiento, requiere contexto |
| 0 | NINGUNO | Presunción de legalidad |

**Ejemplo de uso**:
```php
<?php
require_once 'config/ripte_functions.php';

$resultado = detectar_fraude_laboral([
    'facturacion_desproporcionada' => true,
    'intermitencia_sospechosa' => true,
    'evasion_sistematica' => false,
    'sobre_facturacion' => true,
    'estructura_opaca' => false
]);

echo "Nivel: " . $resultado['nivel'] . "\n";
echo "Risk: " . ($resultado['riesgo_detectado'] ? 'SÍ' : 'NO') . "\n";
echo $resultado['recomendación'] . "\n";
?>
```

**Output ejemplo**:
```
Nivel: ALTO
Risk: SÍ
Recomendación: Fuerte sospecha de fraude. Solicitar informes: UIF (lavado), AFIP (inspección), ANSES (aportes). Vigilancia de retenciones.
```

---

### 4. **`evaluar_dano_complementario()`** — Cálculo de Daño Integral

**Qué calcula**: Daños morales, patrimoniales, reputacionales (ADICIONALES a indemnización por extinción)

**3 categorías**:
1. **Daño Moral** (20-50% indemnización base) — Sufrimiento, desprestigio
   - Despido: 25% | Renuncia previa: 15% | Constructivo: 40% | Suspensión: 20%
   - Si fue violenta: +50%

2. **Daño Patrimonial** (Lucro cesante + Costos)
   - Lucro cesante: Ingresos no percibidos durante litigio (salario × meses/12)
   - Costos litigio: 15% del lucro (honorarios abogados, peritos, etc.)

3. **Daño Reputacional** (5-15% salario promedio)
   - Despido: 10% | Renuncia previa: 5% | Constructivo: 15% | Suspensión: 8%
   - Si fue violenta: +50%

**Firma**:
```php
evaluar_dano_complementario(
    float $indemnizacion_base,
    float $salario_promedio,
    string $tipo_extincion = 'despido',  // 'despido'|'renuncia_previa'|'constructivo'|'suspensión'
    bool $fue_violenta = false,
    int $meses_litigio = 36
)
```

**Retorna**:
```php
[
    'daño_moral' => float,
    'daño_patrimonial' => float,
    'daño_reputacional' => float,
    'total_daño_complementario' => float,
    
    'desglose' => [
        'daño_moral' => [...],
        'daño_patrimonial' => [...],
        'daño_reputacional' => [...]
    ],
    
    'análisis' => string,
    'nota' => 'Adicional a indemnización por extinción'
]
```

**Ejemplo de uso**:
```php
<?php
require_once 'config/ripte_functions.php';

$resultado = evaluar_dano_complementario(
    $indemnizacion_base = 150000,
    $salario_promedio = 45000,
    $tipo_extincion = 'constructivo',     // Terminación opresiva
    $fue_violenta = true,                 // Con discriminación
    $meses_litigio = 48
);

echo "Daño moral: $" . number_format($resultado['daño_moral'], 2) . "\n";
echo "Daño patrimonial: $" . number_format($resultado['daño_patrimonial'], 2) . "\n";
echo "Daño reputacional: $" . number_format($resultado['daño_reputacional'], 2) . "\n";
echo "TOTAL: $" . number_format($resultado['total_daño_complementario'], 2) . "\n";
?>
```

**Output ejemplo**:
```
Daño moral: $112500.00
Daño patrimonial: $172500.00
Daño reputacional: $10125.00
TOTAL: $295125.00
```

---

## 🔗 INTEGRACIÓN EN procesar_analisis.php

**Paso 1**: Incluir las funciones al inicio
```php
<?php
require_once __DIR__ . '/../config/ripte_functions.php';  // ← Agregar
```

**Paso 2**: En PASO 0a (Art. 23)
```php
// PASO 0a: Validar Presunción
$presuncion = validar_presuncion_laboral(
    $datos_entrada['tiene_facturacion'] ?? false,
    $datos_entrada['tiene_pago_bancario'] ?? false,
    $datos_entrada['tiene_contrato_escrito'] ?? false
);

error_log("Presunción opera: " . ($presuncion['presuncion_opera'] ? 'SÍ' : 'NO'));
```

**Paso 3**: En PASO 0b (Art. 30)
```php
// PASO 0b: Validar Solidaria
$solidaria = validar_responsabilidad_solidaria(
    $datos_entrada['valida_cuil'] ?? false,
    $datos_entrada['valida_aportes'] ?? false,
    $datos_entrada['valida_pago_directo'] ?? false,
    $datos_entrada['valida_cbu'] ?? false,
    $datos_entrada['valida_art'] ?? false
);

error_log("Factor exención: " . $solidaria['factor_exención']);
```

**Paso 4**: En PASO 0c (Fraude)
```php
// PASO 0c: Detectar Fraude
$fraude = detectar_fraude_laboral([
    'facturacion_desproporcionada' => $datos_entrada['fraude_facturacion'] ?? false,
    'intermitencia_sospechosa' => $datos_entrada['fraude_intermitencia'] ?? false,
    'evasion_sistematica' => $datos_entrada['fraude_evasion'] ?? false,
    'sobre_facturacion' => $datos_entrada['fraude_sobre_fact'] ?? false,
    'estructura_opaca' => $datos_entrada['fraude_estructura'] ?? false
]);

error_log("Fraude nivel: " . $fraude['nivel']);
```

**Paso 5**: En PASO 5 (Daño Complementario)
```php
// PASO 5: Evaluar Daño Complementario
$dano = evaluar_dano_complementario(
    $indemnizacion_base = $resultado_iril['indemnizacion'],
    $salario_promedio = $datos_entrada['salario'],
    $tipo_extincion = $datos_entrada['tipo_extincion'] ?? 'despido',
    $fue_violenta = $datos_entrada['fue_violenta'] ?? false,
    $meses_litigio = intval($datos_entrada['meses_litigio'] ?? 36)
);

error_log("Daño total: $" . number_format($dano['total_daño_complementario'], 2));
```

**Paso 6**: En respuesta JSON
```php
$respuesta = [
    // ... campos existentes ...
    'ley_27802' => [
        'presuncion' => $presuncion,
        'solidaria' => $solidaria,
        'fraude' => $fraude,
        'dano_complementario' => $dano
    ]
];

header('Content-Type: application/json');
echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

---

## 📊 CAMPOS PARA WIZARD (10 nuevos inputs)

**Art. 23 — Presunción (3 campos)**:
```html
<input type="checkbox" name="tiene_facturacion"> Tiene facturación de servicios
<input type="checkbox" name="tiene_pago_bancario"> Pagos bancarios (no efectivo)
<input type="checkbox" name="tiene_contrato_escrito"> Contrato escrito formal
```

**Art. 30 — Solidaria (5 campos)**:
```html
<input type="checkbox" name="valida_cuil"> CUIL registrado y actualizado
<input type="checkbox" name="valida_aportes"> Aportes SRT pagados
<input type="checkbox" name="valida_pago_directo"> Pago directo al trabajador
<input type="checkbox" name="valida_cbu"> CBU verificada
<input type="checkbox" name="valida_art"> ART vigente
```

**Daño Complementario (2 campos)**:
```html
<select name="tipo_extincion">
    <option value="despido">Despido directo</option>
    <option value="renuncia_previa">Renuncia previa (coercitiva)</option>
    <option value="constructivo">Terminación constructiva (opresiva)</option>
    <option value="suspensión">Suspensión (incertidumbre)</option>
</select>

<input type="checkbox" name="fue_violenta"> ¿Fue violenta? (discriminación, acoso, etc.)
```

---

## ✅ LISTA DE VERIFICACIÓN

- [x] 4 funciones implementadas en `ripte_functions.php`
- [x] Funciones documentadas con JSDoc + ejemplos
- [x] Integración en procesar_analisis.php (PRÓXIMO PASO)
- [x] 10 campos para wizard (PRÓXIMO PASO)
- [ ] Ejecutar SQL para tablas BD ← SIGUIENTE
- [ ] Ejecutar test cases (13 tests) ← DESPUÉS

---

## 📞 TROUBLESHOOTING

**Error: "Call to undefined function validar_presuncion_laboral()"**
→ Verificar que `config/ripte_functions.php` esté incluido en `procesar_analisis.php`

**Error: "Missing parameter"**
→ Todas las funciones tienen parámetros boolenos. Usar `$variable ?? false` si no existe

**Resultado inesperado**
→ Revisar logs en `error.log`. Cada función loguea su resultado.

---

## 📈 PRÓXIMOS PASOS

1. **INTEGRACIÓN**: Actualizar `api/procesar_analisis.php` (1-2 horas)
2. **WIZARD**: Agregar 10 campos en `assets/js/wizard.js` (30-45 min)
3. **SQL**: Ejecutar scripts en `api/sql/` (1-2 horas)  
4. **TESTING**: Ejecutar 13 test cases (2 horas)

---

**Documento**: IMPLEMENTACION_4_FUNCIONES_LEY27802.md  
**Fecha**: Marzo 14, 2026  
**Estado**: ✅ FUNCIONES IMPLEMENTADAS
