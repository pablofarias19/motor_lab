# 🔗 TRAZABILIDAD COMPLETA — Ley 27.802 → Código → Documentación
## Matriz de Correlación (Dónde aparece cada regla normativa)

**Fecha**: 14 de Marzo de 2026  
**Versión**: 2.1+ (Ley 27.802 integrada)  
**Propósito**: Auditoría + Debugging + Trazabilidad legal

---

## **📍 ART. 23 LCT — PRESUNCIÓN LABORAL**

### **Regla Normativa**
> Presunción de relación laboral NO OPERA cuando existen:
> 1. Facturación del trabajador
> 2. Pago bancario directo
> 3. Contrato escrito

### **Dónde Aparece Esta Regla**

| Ubicación | Tipo | Líneas | Detalles |
|-----------|------|--------|----------|
| **STATUS_DOCS_v2.1_vs_MARZO2026.md** | Arquitectura | L:150-180 | "Art. 23 LCT: Presunción condicionada" |
| **README.md** | Overview | L:45-65 | Sección "Art. 23: Presunción Laboral" |
| **ECUACIONES_MOTOR_LABORAL.md** | Fórmula | L:85-125 | Sección 0.2 "Evaluación Presunción (Art. 23)" |
| **GUIA_IMPLEMENTACION_v2.1.md** | Código | L:38-80 | Método `validarPresuncionLaboral()` |
| **INTEGRACION_PROCESAR_ANALISIS_v2.1.md** | Implementación | L:85-110 | PASO 0a (Validación Presunción) |
| **VALIDACION_LEGAL_PRODUCCION_v2.1.md** | Test Cases | L:175-220 | Sección 1.3 "VALIDACIÓN: Art. 23 LCT" |
| **RESUMEN_EJECUTIVO_v2.1.md** | Matrices | L:45-75 | "MATRIZ: Presunción Laboral (Art. 23)" |
| **CHEAT_SHEET_FORMULAS_v2.1.md** | Reference | L:15-35 | "Art. 23 LCT — Presunción Laboral" |
| **INDICE_DOCUMENTACION_v2.1.md** | Index | L:80-95 | Tabla cambios + referencia |

### **En el Código**

| Archivo PHP | Función | Líneas | Acción |
|------------|---------|--------|--------|
| `config/IrilEngine.php` | `calcularComplejidadProbatoria()` | ~202-245 | 🔴 IMPLEMENTAR: Validación Art. 23 si facturación+pago |
| `config/ripte_functions.php` | **(NEW)** `validar_presuncion_laboral()` | **(TBD)** | 🔴 CREAR: Función validación presunción |
| `api/procesar_analisis.php` | PASO 0a | ~85-110 | 🔴 INTEGRAR: Llamar validación presunción |
| `assets/js/wizard.js` | Input fields | **(TBD)** | 🔴 AGREGAR: Inputs "tiene_facturacion", "pago_bancario", "contrato_escrito" |

### **Test Cases (De VALIDACION_LEGAL)**

```
✓ Caso 1 (Art. 23-A): Facturación + Pago bancario + Contrato = NO presunción
✓ Caso 2 (Art. 23-B): Solo facturación = Presunción débil  
✓ Caso 3 (Art. 23-C): Sin ninguno de los 3 = Presunción OPERA
✓ Caso 4 (Art. 23-D): Facturación falsa (miscelánea) = Fraude
```

---

## **📍 ART. 30 LCT — RESPONSABILIDAD SOLIDARIA**

### **Regla Normativa**
> Principal EXENTO de responsabilidad solidaria si valida 5 controles:
> 1. CUIL contratista (AFIP)
> 2. Aportes a seguridad social
> 3. Pago directo sin intermediarios
> 4. CBU (liquidación bancaria)
> 5. ART vigente

### **Dónde Aparece Esta Regla**

| Ubicación | Tipo | Líneas | Detalles |
|-----------|------|--------|----------|
| **STATUS_DOCS_v2.1_vs_MARZO2026.md** | Arquitectura | L:180-220 | "Art. 30 LCT: Solidaria condicionada" |
| **README.md** | Overview | L:65-85 | Sección "Art. 30: Responsabilidad Solidaria" |
| **ECUACIONES_MOTOR_LABORAL.md** | Fórmula | L:125-180 | Sección 0.3 "Evaluación Solidaria (Art. 30)" |
| **GUIA_IMPLEMENTACION_v2.1.md** | Código | L:80-180 | Método `validarResponsabilidadSolidaria()` |
| **INTEGRACION_PROCESAR_ANALISIS_v2.1.md** | Implementación | L:110-145 | PASO 0b (Validación Solidaria) |
| **VALIDACION_LEGAL_PRODUCCION_v2.1.md** | Test Cases | L:220-290 | Sección 1.4 "VALIDACIÓN: Art. 30 LCT" |
| **RESUMEN_EJECUTIVO_v2.1.md** | Matrices | L:75-110 | "MATRIZ: Solidaria (Art. 30)" |
| **CHEAT_SHEET_FORMULAS_v2.1.md** | Reference | L:35-70 | "Art. 30 LCT — Solidaria" |
| **INDICE_DOCUMENTACION_v2.1.md** | Index | L:95-110 | Tabla cambios |

### **En el Código**

| Archivo PHP | Función | Líneas | Acción |
|------------|---------|--------|--------|
| `config/IrilEngine.php` | `responsabilidad_solidaria()` | ~620-670 | 🔴 REESCRIBIR: 5-control framework (YA HECHO) |
| `config/ripte_functions.php` | **(NEW)** `validar_solidaria()` | **(TBD)** | 🔴 CREAR: Función validación solidaria |
| `api/procesar_analisis.php` | PASO 0b | ~110-145 | 🔴 INTEGRAR: Llamar validación solidaria |
| `assets/js/wizard.js` | Input fields | **(TBD)** | 🔴 AGREGAR: Inputs CUIL, aportes, pago, CBU, ART |

---

## **📍 FRAUDE LABORAL — DETECCIÓN AUTOMÁTICA**

### **Regla Normativa**
> Evaluar fraude cuando Art. 23/30 son cuestionables
> Patrones: Facturación sin pago real, roles divergentes, rotación sospechosa

### **Dónde Aparece Esta Regla**

| Ubicación | Tipo | Líneas |
|-----------|------|--------|
| **GUIA_IMPLEMENTACION_v2.1.md** | Código | L:180-250 | Método `detectarFraudeLaboral()` |
| **INTEGRACION_PROCESAR_ANALISIS_v2.1.md** | Implementación | L:145-175 | PASO 0c (Evaluación Fraude) |
| **VALIDACION_LEGAL_PRODUCCION_v2.1.md** | No test separado | -- | Incluido en validaciones 1.3-1.5 |
| **CHEAT_SHEET_FORMULAS_v2.1.md** | Reference | L:70-95 | "Fraude Laboral — Score" |

### **En el Código**

| Archivo PHP | Función | Acción |
|------------|---------|--------|
| `config/ripte_functions.php` | **(NEW)** `detectar_fraude_laboral()` | 🔴 CREAR |
| `api/procesar_analisis.php` | PASO 0c | 🔴 INTEGRAR |

---

## **📍 DEROGACIONES — LEY 24.013 + LEY 25.323**

### **Regla Normativa**
> Ley 24.013 (Art. 8/9/10) — DEROGADA desde 13-Mar-2026  
> Ley 25.323 — DEROGADA completamente desde 13-Mar-2026  
> Art. 80 LCT (Certificados) — AÚN VIGENTE

### **Dónde Aparece Esta Regla**

| Ubicación | Tipo | Líneas |
|-----------|------|--------|
| **STATUS_DOCS_v2.1_vs_MARZO2026.md** | Arquitectura | L:320-380 | Tabla derogaciones |
| **ECUACIONES_MOTOR_LABORAL.md** | Fórmula | L:50-85 | Sección 0.1 (cambio paradigma) |
| **GUIA_IMPLEMENTACION_v2.1.md** | Código | L:280-350 | Sección 1.8 (reemplazo de multas) |
| **INTEGRACION_PROCESAR_ANALISIS_v2.1.md** | Implementación | L:200-250 | PASO 5 (multas derogadas) |
| **VALIDACION_LEGAL_PRODUCCION_v2.1.md** | Test Cases | L:290-350 | Sección 1.5 (Derogaciones) |
| **CHEAT_SHEET_FORMULAS_v2.1.md** | Reference | L:95-120 | Tabla derogaciones |

### **En el Código**

| Archivo PHP | Función | Líneas | Acción |
|------------|---------|--------|--------|
| `config/ripte_functions.php` | `calcular_multas_condicionadas()` | ~180-220 | 🟢 COMPLETO: return $0 para Ley 24.013/25.323 |
| `config/parametros_motor.php` | Parámetros | ~85-105 | 🟢 COMPLETO: Parámetros derogados removidos |
| `config/IrilEngine.php` | `aplica_multas` default | ~700 | 🟢 COMPLETO: Changed to FALSE |

---

## **📍 DAÑO COMPLEMENTARIO**

### **Ubicaciones**

| Documento | Secciones |
|-----------|-----------|
| GUIA_IMPLEMENTACION_v2.1.md | Sección 1.7 `evaluarDanoComplementario()` |
| INTEGRACION_PROCESAR_ANALISIS_v2.1.md | PASO 5 (Evaluación Daño) |

### **Código a Crear**

```php
function evaluar_dano_complementario($situacion) {
    // Daño moral: 20-50% (Lois 2026)
    // Daño patrimonial: Lucro cesante + costos
    // Reputacional: Si extinción violenta/discriminatoria
}
```

---

## **🔄 FLUJO DE INTEGRACIÓN (DEL A FINAL)**

```
┌─────────────────────────────────────────────────────────┐
│ ↓ USUARIO INICIA CÁLCULO EN WIZARD                      │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ PASO 0a: validar_presuncion_laboral()                  │
│  INPUT: tiene_facturacion, pago_bancario, contrato     │
│  OUTPUT: presuncion_opera (bool), controles, análisis  │
│  DÓNDE: ripte_functions.php + procesar_analisis.php    │
│  DOC REF: GUIA 1.1, INTEGRACION 0a, VALIDACION 1.3    │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ PASO 0b: validar_responsabilidad_solidaria()           │
│  INPUT: CUIL, aportes, pago, CBU, ART                  │
│  OUTPUT: exento (bool), controles_validados, factor    │
│  DÓNDE: ripte_functions.php + procesar_analisis.php    │
│  DOC REF: GUIA 1.2, INTEGRACION 0b, VALIDACION 1.4    │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ PASO 0c: detectar_fraude_laboral()                     │
│  INPUT: Patrones (factura, rol, horas, rotación)       │
│  OUTPUT: nivel (BAJO/MEDIO/ALTO), score, indicadores  │
│  DÓNDE: ripte_functions.php + procesar_analisis.php    │
│  DOC REF: GUIA 1.6, INTEGRACION 0c                    │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ PASO 5: evaluar_dano_complementario()                  │
│  INPUT: salario, antigüedad, causa, fue_violenta       │
│  OUTPUT: daño_moral, daño_patrimonial, total           │
│  DÓNDE: ripte_functions.php + procesar_analisis.php    │
│  DOC REF: GUIA 1.7, INTEGRACION PASO 5                │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ RESPUESTA JSON COMPLETA                                │
│ {                                                       │
│   "ley_27802": {                                        │
│     "presuncion_laboral": { ... },                     │
│     "responsabilidad_solidaria": { ... },              │
│     "evaluacion_fraude": { ... },                      │
│     "danos_complementarios": { ... },                  │
│     "derogaciones": { ... }                            │
│   }                                                    │
│ }                                                      │
│  DOC REF: INTEGRACION "COMPILAR RESPUESTA FINAL"      │
└─────────────────────────────────────────────────────────┘
```

---

## **📦 DEPENDENCIAS ENTRE DOCUMENTOS**

```
STATUS_DOCS (Arquitectura)
    ↓
    ├─→ README (Overview)
    ├─→ ECUACIONES (Fórmulas)
    ├─→ RESUMEN_EJECUTIVO (Matrices)
    └─→ CHEAT_SHEET (Quick ref)
         ↓
      GUIA_IMPLEMENTACION (Código PHP)
         ↓
      INTEGRACION_PROCESAR_ANALISIS (Integración API)
         ↓
      AUTOMATIZACION_RIPTE_BD (BD + Sync)
         ↓
      VALIDACION_LEGAL_PRODUCCION (TestCases)
         ↓
      QUICK_START (5 pasos de implementación)
         ↓
      ENTREGA_SISTEMA_COMPLETO (Checklist final)
         ↓
      INDICE_DOCUMENTACION (Navegación)
```

---

## **✅ CHECKLIST DE IMPLEMENTACIÓN**

### **Art. 23 (Presunción)**
- [ ] `validarPresuncionLaboral()` creado en ripte_functions.php
- [ ] Integrado en PASO 0a de procesar_analisis.php
- [ ] Inputs agregados al wizard (tiene_facturacion, pago_bancario, contrato_escrito)
- [ ] Test cases en VALIDACION_LEGAL 1.3
- [ ] Fórmula en ECUACIONES 0.2

### **Art. 30 (Solidaria)**
- [ ] `validarResponsabilidadSolidaria()` creado
- [ ] 5-control framework implementado (CUIL/aportes/pago/CBU/ART)
- [ ] Integrado en PASO 0b
- [ ] Inputs agregados al wizard
- [ ] Test cases en VALIDACION_LEGAL 1.4
- [ ] Fórmula con factor exención en ECUACIONES 0.3

### **Fraude Laboral**
- [ ] `detectarFraudeLaboral()` creado
- [ ] 5 patrones de detección codificados
- [ ] Integrado en PASO 0c
- [ ] Score calculation implementado

### **Derogaciones**
- [ ] Ley 24.013 → return $0 en ripte_functions.php
- [ ] Ley 25.323 → return $0
- [ ] Parámetros derogados removidos de parametros_motor.php
- [ ] Art. 80 aún devuelve $salario × 3
- [ ] Alertas generadas en respuesta JSON

### **Daño Complementario**
- [ ] `evaluarDanoComplementario()` creado
- [ ] 3 categorías implementadas (moral/patrimonial/reputacional)
- [ ] Integrado en PASO 5
- [ ] Incluido en respuesta JSON

---

## **🎯 VALIDACIÓN FINAL**

**Antes de go-live:**

```bash
# 1. Verificar que TODOS los métodos existan en ripte_functions.php
✓ validar_presuncion_laboral()
✓ validar_responsabilidad_solidaria()
✓ detectar_fraude_laboral()
✓ evaluar_dano_complementario()
✓ obtener_ripte_vigente()
✓ calcular_multas_condicionadas() [retorna $0 para 24.013/25.323]

# 2. Verificar que procesar_analisis.php tenga PASOS 0a/0b/0c
✓ PASO 0a: Presunción
✓ PASO 0b: Solidaria
✓ PASO 0c: Fraude
✓ PASO 5: Daño complementario

# 3. Verificar respuesta JSON incluya "ley_27802" section
✓ presuncion_laboral object
✓ responsabilidad_solidaria object
✓ evaluacion_fraude object
✓ danos_complementarios object
✓ derogaciones object

# 4. Verificar que wizard.js tenga nuevos inputs
✓ tiene_facturacion
✓ pago_bancario
✓ contrato_escrito
✓ cuil_contratista
✓ aportes_seg_social
✓ pago_directo
✓ cbu_contratista
✓ art_vigente
```

---

**Documento Creado**: 14-Mar-2026  
**Para Auditoría**: Usar como matriz de trazabilidad  
**Para Debugging**: Referencia cruzada art. → código → doc
