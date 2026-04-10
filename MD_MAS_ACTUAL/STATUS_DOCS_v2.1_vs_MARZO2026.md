# � MOTOR LABORAL — ARQUITECTURA NORMATIVA v3.0
## Constitución Jurídica del Sistema (Actualizado Ley 27.802 — Marzo 2026)

**Base normativa del sistema:**

- Ley de Contrato de Trabajo (LCT) — Código laboral fundamental
- Ley 27.742 (Ley Bases) — julio 2024
- **Ley 27.802 (Modernización Laboral)** — marzo 2026 ⭐
- Jurisprudencia reciente (Ackerman, Lois — 2024-2026)

**Propósito de este documento:**

Define las reglas jurídicas que el Motor Laboral interpreta y ejecuta. Sirve como "constitución" del sistema, permitiendo:
- ✅ Justificar arquitectura ante clients/inversores
- ✅ Guiar a developers en el propósito del código
- ✅ Integrar futuras reformas sin romper el sistema
- ✅ Documentar evolución normativa

---

## 🔄 CAMBIO DE PARADIGMA — MARZO 2026

**El Motor Laboral ABANDONA el modelo histórico y adopta una arquitectura jurídica nueva:**

| Aspecto | Modelo Histórico (hasta 2024) | Modelo Nuevo (2026+) |
|--------|------|------|
| **Eje Principal** | Multas automáticas por no registración | Análisis de dependencia real + fraude laboral |
| **Régimen Legal** | LCT + Ley 24.013 + Ley 25.323 | LCT reformada + Ley 27.802 |
| **Presunción Laboral** | Automática por mera prestación servicios | Requiere análisis de facturación + pago + contrato |
| **Multas** | Art. 8/9/10 Ley 24.013 + duplicación 25.323 | Derogadas. Solo Art. 80 (certificados digitales) |
| **Estrategia** | Acumular agravantes indemnizatorios | Detectar fraude + cuantificar daños civiles + sanciones procesales |

**Implicación técnica:**
- **Eliminado**: Cálculo automático de multas por no registración
- **Nuevo**: Detector de fraude laboral (5 patrones)
- **Nuevo**: Módulo de daños complementarios (moral, patrimonial, previsional)
- **Nuevo**: Evaluador de sanciones procesales (conducta dilatoria, ocultamiento)

---

# 1️⃣ ARQUITECTURA DE TRES CAPAS

El Motor Laboral utiliza un modelo de análisis jurídico estructurado en tres capas:

```
┌─────────────────────────────────────────────────────────┐
│  CAPA 3: CÁLCULO ECONÓMICO                              │
│  (indemnizaciones, daños civiles, intereses)            │
│  [Multas históricas derogadas → Modelo de fraude]       │
└─────────────────────────────────────────────────────────┘
                            ↑
                            │
┌─────────────────────────────────────────────────────────┐
│  CAPA 2: LÓGICA JURÍDICA                                │
│  (reglas de inferencia, presunciones, exclusiones)      │
└─────────────────────────────────────────────────────────┘
                            ↑
                            │
┌─────────────────────────────────────────────────────────┐
│  CAPA 1: NORMATIVA                                      │
│  (LCT, Ley Bases, Ley 27.802, jurisprudencia)          │
└─────────────────────────────────────────────────────────┘
```

---

# 2️⃣ CAPA 1 — NORMATIVA

## Cambios Estructurales (Ley 27.802)

### 2.1 Ámbito de Aplicación LCT

**Exclusiones expresas del contrato de trabajo:**

Nuevas (Ley 27.802):
- Trabajadores independientes con colaboradores
- Prestadores de plataformas tecnológicas
- Contratos civiles de obra o servicios
- Personas privadas de libertad

**Impacto normativo:**

El motor debe **distinguir** entre:
- Relación laboral de dependencia (LCT aplica)
- Prestación independiente (civil)
- Contratación por plataforma (nueva)

---

### 2.2 Presunción de Relación Laboral — ART. 23 LCT (CRÍTICO)

**Reforma central de Ley 27.802.**

**REGLA GENERAL (presunción):**
```
Si existe:
  • prestación de servicios
  • dependencia (control, horario, integración)
  • remuneración

ENTONCES: Se presume CONTRATO DE TRABAJO
```

**EXCEPCIONES a la presunción (nuevas):**
```
La presunción NO opera cuando existe:
  ✓ Facturación (emite facturas)
  ✓ Pago bancarizado (CBU, transferencia)
  ✓ Contrato profesional documentado
  ✓ Estructura como prestación independiente
```

**Impacto en Motor Laboral:**

Módulo: `detector_relacion_laboral()`

Pseudocódigo:
```pseudocode
FUNCIÓN detectar_relacion_laboral(datos)

  // Verificar excepciones Art. 23
  SI facturacion == true AND pago_bancario == true ENTONCES
    RETORNAR "Relación independiente (presunción no opera)"
  FIN SI

  // Aplicar presunción general
  SI prestacion_servicios AND dependencia AND remuneracion ENTONCES
    RETORNAR "Presunción laboral (Art. 23 LCT)"
  FIN SI

  RETORNAR "Relación ambigua (requiere análisis"

FIN FUNCIÓN
```

---

### 2.3 Responsabilidad en Subcontratación — ART. 30 LCT (CRÍTICO)

**Reforma central de Ley 27.802.**

**REGLA GENERAL (antes):**
```
Empleador principal es responsable solidario
por obligaciones del subcontratista (Art. 30)
```

**EXCEPCIÓN (nueva — Ley 27.802):**
```
El principal queda EXIMIDO si controla:

1. CUIL de trabajadores (identificación)
2. Pago de aportes y seguridad social
3. Pago directo de salarios
4. Cuenta bancaria del trabajador
5. Cobertura ART con endoso

Si controla TODOS los 5 → NO responsable solidario
Si faltan algunos → Responsable solidario
```

**Impacto en Motor Laboral:**

Módulo NUEVO: `analisis_responsabilidad_solidaria()`

Pseudocódigo:
```pseudocode
FUNCIÓN analisis_responsabilidad_solidaria(principal, subcontratista)

  controles = 0

  SI verificar_CUIL(subcontratista) ENTONCES
    controles += 1
  FIN SI

  SI verificar_aporte_seguridad_social(principal, subcontratista) ENTONCES
    controles += 1
  FIN SI

  SI verificar_pago_directo_salarios(principal, subcontratista) ENTONCES
    controles += 1
  FIN SI

  SI verificar_cuenta_bancaria(subcontratista) ENTONCES
    controles += 1
  FIN SI

  SI verificar_cobertura_art_endosada(subcontratista) ENTONCES
    controles += 1
  FIN SI

  SI controles >= 5 ENTONCES
    RETORNAR "Principal EXIMIDO responsabilidad (Art. 30 nuevo)"
  SINO
    RETORNAR "Principal RESPONSABLE solidario (Art. 30)"
  FIN SI

FIN FUNCIÓN
```

---

### 2.4 Registro Laboral — ARCA Digital

**Cambio procedimental importante:**

ANTES: Registro en libros de empresa (físico)
AHORA: Registro ante ARCA (digital, válido legalmente)

**Impacto:**

Módulo: `validar_registracion_laboral()`

```pseudocode
FUNCIÓN validar_registracion_laboral(trabajador)

  // Verificar ARCA digital (nuevo)
  SI consultar_arca_digital(trabajador) ENTONCES
    RETORNAR "Registración válida (ARCA digital)"
  FIN SI

  // Fallback: libros físicos
  SI consultar_libros_empresa(trabajador) ENTONCES
    RETORNAR "Registración válida (libro físico)"
  FIN SI

  RETORNAR "Sin registración (alerta)"

FIN FUNCIÓN
```

---

### 2.5 Certificados Laborales — ART. 80 LCT

**Cambio menor pero relevante:**

La obligación de certificados se cumple mediante:
- Soporte físico (antiguo)
- Soporte digital (nuevo)
- Acceso a seguridad social (nuevo)

**Impacto en multas:**

Módulo: `calcular_multa_art_80()`

```pseudocode
FUNCIÓN calcular_multa_art_80(empleador, trabajador)

  // Verificar cumplimiento
  SI certificado_fisico(empleador, trabajador) OR
     certificado_digital(empleador, trabajador) OR
     acceso_seguridad_social(empleador, trabajador) ENTONCES
    RETORNAR multa = 0
  FIN SI

  // Incumplimiento
  RETORNAR multa = salario_base * 3

FIN FUNCIÓN
```

---

# 3️⃣ CAPA 2 — LÓGICA JURÍDICA

## Sistema de Reglas de Inferencia

El motor utiliza **lógica difusa** (fuzzy logic) para análisis de relación laboral:

### 3.1 Modelo básico de detección laboral

**Variables analizadas:**

```
Dependencia (40%):
  - Control de horario
  - Subordinación técnica
  - Integración organizacional
  - Exclusividad

Independencia (30%):
  - Facturación
  - Libertad de organización
  - Asunción de riesgos
  - Múltiples clientes

Remuneración (20%):
  - Salario fijo vs honorarios
  - Periodicidad
  - Cálculo

Control administrativo (10%):
  - Aportes
  - Impuestos
  - Registración
```

**Score de laboralidad (0-100):**

```
Si score >= 70 → Probable relación laboral
Si 30-70 → Ambigua (requiere análisis)
Si <= 30 → Probable independencia
```

---

### 3.2 Detector de fraude laboral

El motor detecta patrones típicos de fraude:

```pseudocode
fraude_laboral = false

// Patrón 1: Facturación ficticia
SI facturacion AND exclusividad AND subordinacion ENTONCES
  fraude_laboral = true
FIN SI

// Patrón 2: Monotributista dependiente
SI monotributista AND control_horario AND subordinacion ENTONCES
  fraude_laboral = true
FIN SI

// Patrón 3: Subcontratación irregular
SI subcontrato AND sin_control_cuil AND sin_seguridad_social ENTONCES
  fraude_laboral = true
FIN SI

// Patrón 4: Tercerizacion sin cobertura
SI tercerista AND sin_art AND sin_registracion ENTONCES
  fraude_laboral = true
FIN SI

SI fraude_laboral ENTONCES
  generar_alerta("Posible fraude laboral", riesgo="alto")
FIN SI
```

---

# 4️⃣ CAPA 3 — CÁLCULO ECONÓMICO

## Motor de Indemnizaciones

### 4.1 IBM Reforzada (Doctrinal — Ackerman 2026)

**Nueva interpretación jurisprudencial:**

```
IBM_simple = promedio_12_meses × (RIPTE_accidente / RIPTE_mes)

IBM_reforzada = promedio_12_meses × MAX(
  RIPTE_accidente / RIPTE_mes,
  RIPTE_accidente / IPC_mes
)
```

**Objetivo:** Evitar deterioro inflacionario

**Algoritmo:**

```pseudocode
FUNCIÓN calcular_ibm_reforzada(salarios_12m, fecha_accidente, ripte, ipc)

  ibm_actualizado = 0
  cantidad_meses = 0

  PARA cada mes IN salarios_12m HACER

    sal = salarios_12m[mes]
    ripte_mes = ripte[mes]
    ipc_mes = ipc[mes]
    ripte_accidente = ripte[fecha_accidente]

    coef_ripte = ripte_accidente / ripte_mes
    coef_ipc = ripte_accidente / ipc_mes

    // Ackerman: usar MÁXIMO
    coef = MAX(coef_ripte, coef_ipc)

    ibm_actualizado += sal * coef
    cantidad_meses += 1

  FIN PARA

  RETORNAR ibm_actualizado / cantidad_meses

FIN FUNCIÓN
```

---

## 4.2 Indemnización Base (LCT)

```
Indemnización = Salario × Años
               (antigüedad)

+ Integración mes = Salario / 30 × (30 - día_despido)
+ SAC proporcional
+ Vacaciones proporcionales
```

---

## 4.3 Impacto de Derogaciones Normativas — Cambio de Paradigma

**Estado conforme a Ley 27.802 (marzo 2026):**

La Ley 25.323 y los arts. 8/9/10/11 Ley 24.013 **han sido derogados**.

El sistema ABANDONA el modelo histórico basado en **multas automáticas por no registración** y adopta un nuevo paradigma:

1. **Análisis de dependencia real** (Art. 23 LCT reformado)
2. **Detección de fraude laboral** (monotributo dependiente, facturación simulada, falsa autonomía)
3. **Daños civiles complementarios** (daño moral, daño patrimonial, pérdida previsional)
4. **Sanciones procesales** (mala fe, conducta dilatoria, ocultamiento registral)

**Cálculo de certificados (Art. 80 — Único cargo por defectos formales):**

```
SI fecha_despido > 09/07/2024 ENTONCES
  multa_80 = salario × 3  (Certificados deficientes)
SINO SI fecha_despido <= 09/07/2024 ENTONCES
  multa_80 = salario × 3  (Art. 80 LCT histórico)
  [Multas Ley 24.013 / 25.323: DEROGADAS — NO APLICAN]
FIN SI
```

---

# 5️⃣ MOTOR DE ALERTAS JURÍDICAS — CATEGORÍAS NUEVAS (MARZO 2026)

## 5.1 Alertas de Derogación y Régimen Transitorio

```
⚠️ MULTAS LEY 25.323 DEROGADAS
   Detecta: Referencia a multa Art. 1 Ley 25.323
   Estado: DEROGADA
   Acción: No usar como cálculo activo

⚠️ MULTAS PARCIAL LEY 24.013 DEROGADAS
   Detecta: Referencia a arts. 8/9/10/11
   Estado: DEROGADOS (desde reforma laboral)
   Acción: Aplicar derecho transitorio (hechos previos)

⚠️ DERECHO TRANSITORIO APLICABLE
   Detecta: Hechos previos a reforma (fecha_despido < 01/03/2026)
   Acción: Revisar posible aplicación de régimen histórico
```

---

## 5.2 Alertas de Dependencia Real (Art. 23 LCT)

```
⚠️ RELACIÓN LABORAL DISCUTIBLE
   Detecta: Facturación + pago bancario pero dependencia real
   Nivel: MODERADO
   Acción: Aumentar complejidad probatoria en IRIL

⚠️ SUBORDINACIÓN TÉCNICA DETECTADA
   Detecta: Control horario + integración + exclusividad
   Nivel: MODERADO
   Acción: Reforzar presunción de laboralidad

⚠️ PRESUNCIÓN NO OPERA (Art. 23)
   Detecta: Contrato profesional documentado + CBU + facturas
   Nivel: BAJO
   Acción: Requiere prueba activa de dependencia real
```

---

## 5.3 Alertas de Fraude Laboral

```
⚠️ FRAUDE LABORAL DETECTADO
   Detecta: Monotributo + control centralizado + ajeneidad
   Nivel: CRÍTICO
   Acción: Derivación a especialista / litigio agresivo

⚠️ FALSA AUTONOMÍA
   Detecta: Facturación simulada + dependencia económica total
   Nivel: ALTO
   Acción: Reconstrucción probatoria reforzada

⚠️ TERCERIZACION FICTICIA
   Detecta: Subcontratación con relación sustancial directa
   Nivel: ALTO
   Acción: Plantear responsabilidad solidaria originaria

⚠️ FRAGMENTACIÓN CONTRACTUAL
   Detecta: Múltiples contratos para el mismo trabajo
   Nivel: MODERADO
   Acción: Unificar relación / recalcular indemnización
```

---

## 5.4 Alertas de Daños Complementarios

```
⚠️ DAÑO MORAL LABORAL
   Detecta: Trabajo no registrado + vejaciones + trato degradante
   Nivel: OPORTUNIDAD
   Acción: Até art civil + solicitar indemnización complementaria

⚠️ DAÑO PATRIMONIAL (Pérdida Previsional)
   Detecta: Falta de aportes + cobertura ART ausente
   Nivel: ALTO
   Acción: Cuantificar pérdida previsional adicional

⚠️ PERJUICIO PROBATORIO
   Detecta: Falta de registración digital (ARCA)
   Nivel: MODERADO
   Acción: Presunción a favor del trabajador
```

---

## 5.5 Alertas de Estrategia Procesal

```
⚠️ CONDUCTA DILATORIA DETECTADA
   Detecta: Resistencia a exhibición ARCA + ocultamiento registral
   Nivel: CRÍTICO
   Acción: Solicitar sanciones por mala fe / intereses agravados

⚠️ SANCIONES PROCESALES POSIBLES
   Detecta: Negativa infundada + actos disvaliosos
   Nivel: OPORTUNIDAD
   Acción: Intereses agravados / nulidad de actos

⚠️ RESPONSABILIDAD SOLIDARIA ORIGINARIA
   Detecta: Principal sin 5 controles (CUIL, seg soc, pago, CTA, ART)
   Nivel: ALTO
   Acción: Ampliar exposición económica
```

---

## 5.6 Alertas Doctrinales y Técnicas (Vigentes)

```
⚠️ IBM REFORZADA APLICABLE (Ackerman 2026)
   Detecta: Brecha RIPTE/IPC > 10%
   Acción: Usar MAX(RIPTE, IPC) en cálculo

⚠️ DAÑO MORAL AMPLIABLE (Lois 2026)
   Detecta: Culpa grave + vía civil complementaria
   Acción: Hasta 50% (no 20% histórico)
```

---

# 6️⃣ ARQUITECTURA MODULAR DEL SISTEMA

```
Motor Laboral v3.0
│
├── [CAPA 1] DATOS NORMATIVOS
│   ├── ripte_functions.php (RIPTE, IPC, pisos)
│   ├── ley_bases.php (validaciones 27.742)
│   └── ley_27802.php (nuevos Art. 23, 30, 80)
│
├── [CAPA 2] LÓGICA JURÍDICA
│   ├── IrilEngine.php (detector_relacion_laboral, alertas)
│   ├── responsabilidad_solidaria.php (Art. 30)
│   └── detector_fraude.php (patrones)
│
└── [CAPA 3] CÁLCULO ECONÓMICO
    ├── EscenariosEngine.php (indemnizaciones)
    ├── multas.php (Art. 80)
    └── alertas_economicas.php (daño moral, RIPTE/IPC)
```

---

# 7️⃣ MATRIZ NORMATIVA — DOCUMENTACIÓN vs LEGISLACIÓN

---

# 1️⃣ CAMBIOS NORMATIVOS RELEVANTES (Ley 27.802)

## Nuevo Ámbito de Exclusión LCT

Se incorporan nuevas exclusiones expresas:

- Trabajadores independientes con colaboradores (Ley 27.742)
- Prestadores de plataformas tecnológicas
- Personas privadas de libertad
- Ampliación de exclusiones de contratos civiles

**Impacto directo en Motor Laboral**:
- Módulo: `detector_relacion_laboral()`
- Validaciones nuevas: facturación, forma de pago, naturaleza del contrato
- Afecta: IRIL Score (complejidad probatoria aumenta)

---

## Presunción de Contrato Laboral (Art. 23 LCT) — CAMBIO CRÍTICO

**La presunción laboral NO opera cuando existe:**

- Contrato profesional documentado
- Facturación del prestador
- Pago mediante sistema bancario reglamentado
- Estructura contractual como prestación independiente

**Impacto en Sistema**:

```
ANTES:  prestacion_servicio → presuncion_automatica_laboral ✓
AHORA:  prestacion_servicio + facturacion + pago_bancario → NO presunción
```

Regla nueva:
```php
if ($facturacion && $pago_bancario && $contrato_profesional) {
    $presuncion_laboral = false;  // NO aplica presunción
    $relacion = 'INDEPENDIENTE';  // Por defecto
}
```

**Documentos que necesitan actualizar**:
- ECUACIONES_MOTOR_LABORAL.md (FASE 1 IRIL)
- GUIA_IMPLEMENTACION_v2.1.md (detector_relacion_laboral)
- VALIDACION_LEGAL_PRODUCCION_v2.1.md (test cases)

---

## Subcontratación y Responsabilidad Solidaria (Art. 30 LCT) — CAMBIO CRÍTICO

**Empleador principal se libera de responsabilidad solidaria si controla:**

- CUIL de trabajadores
- Pago de seguridad social
- Pago directo de salarios
- Cuenta bancaria del trabajador
- Cobertura ART con endoso

**Impacto en Sistema**:

```
ANTES: Principal responsable por solidaridad (Art. 30)
AHORA: Principal responsable SOLO si NO controla los 5 elementos
```

Nuevo módulo necesario:
```php
analisis_responsabilidad_solidaria($principal, $subcontratista)
  - Verifica CUIL
  - Verifica seguridad social
  - Verifica pago directo
  - Verifica cuenta bancaria
  - Verifica ART endosado
```

**Documentos que necesitan actualizar**:
- INTEGRACION_PROCESAR_ANALISIS_v2.1.md (PASO nuevo)
- VALIDACION_LEGAL_PRODUCCION_v2.1.md (test cases Art. 30)

---

## Registro Laboral Digital (ARCA)

**El registro laboral:**

- Se realiza ante ARCA (Administración Federal de Ingresos Públicos)
- Puede digitalizarse con validez legal equivalente
- No requiere soporte físico

**Impacto en Sistema**:

```php
validar_registracion_laboral()
  - Verificar ARCA digital
  - NO exigir soporte físico
  - Actualizar presunciones procesales
```

**Documentos que necesitan actualizar**:
- AUTOMATIZACION_RIPTE_BD.md (integración ARCA)
- VALIDACION_LEGAL_PRODUCCION_v2.1.md (validación registración)

---

## Certificados Laborales (Art. 80) — CAMBIO RELEVANTE

**Entrega válida mediante:**

- Soporte físico (antiguo)
- Soporte digital (nuevo)
- Acceso mediante sistema de seguridad social (nuevo)

**Impacto en Sistema**:

```php
multa_art_80()
  // Antes: Exigía soporte físico para eximirse
  // Ahora: Acepta digital o acceso seguridad social
  
  if ($digital || $seguridad_social_acceso) {
      $multa = 0;  // NO aplica multa
  }
```

**Documentos que necesitan actualizar**:
- GUIA_IMPLEMENTACION_v2.1.md (funciones multas)
- VALIDACION_LEGAL_PRODUCCION_v2.1.md (test cases)

---

# 2️⃣ CAMBIOS DOCTRINALES Y JURISPRUDENCIALES

### IBM Reforzada (Ackerman, 2026)

Nueva fórmula doctrinal:

$$IBM = \frac{\sum (Salario_i \times \max(\frac{RIPTE}{RIPTE_i}, \frac{RIPTE}{IPC_i}))}{12}$$

**Impacto**: +21% a +50% en indemnizaciones de accidentes laborales

**Documentos que ya incluyen esto**:
- ECUACIONES_MOTOR_LABORAL.md ✅
- ACTUALIZACION_MARZO_2026.md ✅

---

### Jurisprudencia Ackerman (2026)

- Interpretación expansiva de actualización indemnizatoria
- Protección del crédito laboral con IPC como piso

**Ya documentado en**: ACTUALIZACION_MARZO_2026.md ✅

---

# 3️⃣ IMPACTO EN MÓDULOS DEL MOTOR LABORAL

| Módulo | Impacto | Cambio Normativo | Acción |
|--------|--------|------------------|--------|
| `detector_relacion_laboral()` | 🔴 ALTO | Art. 23 (presunción) | Agregar validación facturación + pago + contrato |
| `analisis_responsabilidad_solidaria()` | 🔴 ALTO | Art. 30 (solidaria) | Nueva lógica 5 controles |
| `calculo_indemnizaciones()` | 🟠 MEDIO | Ackerman 2026 | Integrar IBM reforzada |
| `validar_registracion_laboral()` | 🟠 MEDIO | Registro digital | ARCA digital válido |
| `calcular_multa_art_80()` | 🟠 MEDIO | Art. 80 (certificados) | Aceptar digital/seguridad social |
| `generador_alertas()` | 🟠 MEDIO | Nuevos escenarios | Alertas sobre presunción |

---

# 4️⃣ NUEVAS ALERTAS DEL SISTEMA

El Motor deberá generar alertas cuando detecte:

```
⚠️ PRESUNCIÓN LABORAL CUESTIONADA
   Condición: Facturación + pago bancario + contrato profesional
   Alerta: "La presunción laboral puede no operar (Art. 23 LCT)"
   Acción: Análisis riesgo probatorio aumenta

⚠️ RESPONSABILIDAD SOLIDARIA CUESTIONADA
   Condición: Principal sin control de CUIL/seg.soc/pago/CTA/ART
   Alerta: "Principal podría no ser responsable de subcontratista"
   Acción: Análisis estratégico cambia

⚠️ BRECHA RIPTE vs IPC DETECTADA
   Condición: Gap > 10%
   Alerta: "IBM simple insuficiente, usar IBM reforzada"
   Acción: +20-30% indemnización

⚠️ FALTA VALIDACIÓN ARCA DIGITAL
   Condición: Sin acceso a registro ARCA
   Alerta: "No se puede verificar registración digital"
   Acción: Presunción de falta de registro activa
```

---

# 5️⃣ ANÁLISIS DE DOCUMENTOS A ACTUALIZAR

## Matriz: Qué documento necesita qué cambio normativo

| Documento | Art. 23 | Art. 30 | ARCA | Art. 80 | IBM | Frecuencia |
|-----------|---------|---------|------|---------|-----|-----------|
| README.md | ✓ Mención | ✓ Mención | — | — | ✓ | Crítica |
| RESUMEN_EJECUTIVO | ✓ Tabla | ✓ Tabla | — | — | ✓ | Crítica |
| ECUACIONES_MOTOR | ✓ Integración | — | — | — | ✓ | Crítica |
| VALIDACION_LEGAL | ✓ Tests | ✓ Tests | ✓ Tests | ✓ Tests | ✓ Tests | Crítica |
| GUIA_IMPLEMENTACION | ✓ Funciones | ✓ Función Nueva | — | ✓ Función | ✓ | Alta |
| INTEGRACION_ANALISIS | — | ✓ PASO nuevo | ✓ Validación | — | ✓ | Alta |
| AUTOMATIZACION_BD | — | — | ✓ ARCA integration | — | — | Media |
| QUICK_START | ✓ Mención | — | — | — | ✓ | Media |

---

# 6️⃣ PLAN DE ACTUALIZACIÓN POR FASE

## 🔴 FASE 1 — CRÍTICA (Hoy, 3h)

**CAMBIO DE PARADIGMA**: Abandonar modelo de multas automáticas → Adoptar análisis de fraude + daños complementarios.

Actualizar documentos que afectan decisiones legales y estratégicas:

```
✓ README.md (20 min)
  - Cambio de paradigma: de multas a análisis de fraude
  - Mención Ley 27.802 + derogaciones 24.013/25.323
  - Nuevos ejes: dependencia real, fraude laboral, daños complementarios

✓ RESUMEN_EJECUTIVO_v2.1.md (45 min)
  - Tabla: Art. 23 presunción laboral (cambios)
  - Tabla: Art. 30 responsabilidad solidaria (exención 5 controles)
  - Tabla: Derogaciones 24.013/25.323 (NO aplican)
  - Tabla: Nuevos módulos (fraude, daños, sanciones procesales)

✓ ECUACIONES_MOTOR_LABORAL.md (60 min)
  - ELIMINAR: Cálculos de multas Ley 24.013 y 25.323
  - AGREGAR: Score de dependencia real (Art. 23)
  - AGREGAR: Score de fraude laboral
  - AGREGAR: Módulo daños complementarios (moral, patrimonial)
  - APÉNDICE: Tabla cambios LCT vs 27.802

✓ VALIDACION_LEGAL_PRODUCCION_v2.1.md (60 min)
  - Test cases para Art. 23 (facturación ≠ presunción)
  - Test cases para Art. 30 (solidaria con 5 controles)
  - Test cases para fraude laboral (monotributo dependiente)
  - Test cases para daño moral laboral
  - Test cases para AUSENCIA de multas 24.013/25.323
```

**Resultado**: Legal y estrategia alineados con Ley 27.802. Modelo actualizado: dependencia + fraude + daños.

---

## 🟠 FASE 2 — ALTA (Semana 1, 3h)

**NUEVOS MÓDULOS DE LÓGICA JURÍDICA**: Agregar detectores de fraude, daños, y sanciones procesales.

Actualizar documentación técnica para implementación:

```
✓ GUIA_IMPLEMENTACION_v2.1.md (90 min)
  - Función: detector_dependencia_real() — Score Art. 23
  - Función: analisis_responsabilidad_solidaria() — 5 controles Art. 30
  - Función: detector_fraude_laboral() — Monotributo dependiente, falsa autonomía
  - Función: evaluar_dano_complementario() — Moral, patrimonial, pérdida previsional
  - Función: validar_registracion_arca_digital()
  - Función: evaluar_sanciones_procesales() — Conducta dilatoria, ocultamiento

✓ INTEGRACION_PROCESAR_ANALISIS_v2.1.md (60 min)
  - PASO 0a: Verificación Art. 23 presunción (facturación + pago)
  - PASO 0b: Análisis Art. 30 solidaria (5 controles)
  - PASO 0c: Evaluación de fraude laboral (nueva)
  - PASO 0d: Cálculo daños complementarios (nueva)
  - PASO X: Validación ARCA digital
  - Alertas: Derogaciones, fraude, daños, sanciones procesales

✓ AUTOMATIZACION_RIPTE_BD.md (30 min)
  - Tabla ARCA_registros_digitales (opcional)
  - Admin dashboard: validar ARCA
  - Cron: sincronización ARCA
```

**Resultado**: Devs pueden implementar Art. 23, 30 sin dudas

---

## 🟡 FASE 3 — MEDIA (Semana 2, 1.5h)

Actualizar referencia rápida (para usuarios, no developers):

```
✓ QUICK_START_v2.1.md (20 min)
  - Mencionar cambio de paradigma (multas → fraude)
  - Art. 23: cuando facturación ≠ presunción
  - Art. 30: exención con 5 controles

✓ CHEAT_SHEET_FORMULAS_v2.1.md (25 min)
  - Tabla: Score dependencia (Art. 23)
  - Tabla: Detección fraude laboral
  - Tabla: AUSENCIA de multas 24.013/25.323
  - Fórmula IBM reforzada
  - Checklist Art. 30

✓ INDICE_DOCUMENTACION_v2.1.md (25 min)
  - Sección nueva: "Paradigma 2026: De Multas a Fraude"
  - Links a secciones en otros docs
  - Resumen cambios normativos vs doctrinales
```

**Resultado**: Usuarios pueden encontrar info rápidamente. Entienden cambio conceptual.

---

# 7️⃣ COMPATIBILIDAD

✅ El sistema mantiene **100% compatibilidad con v2.1**

Las actualizaciones se implementan como:
- Nuevos validadores (Art. 23, 30)
- Nuevas alertas
- Funciones opcionales (IBM reforzada)

**Sin breaking changes**

---

# 8️⃣ DOCUMENTOS CENTRALES

## ACTUALIZACION_MARZO_2026.md

Ya existe, pero DEBE incluir:
- ✅ IBM reforzada (Ackerman) — YA incluido
- ✅ Alertas jurisprudenciales — YA incluido
- ⚠️ Art. 23 presunción — FALTA agregar
- ⚠️ Art. 30 solidaria — FALTA agregar
- ⚠️ ARCA digital — FALTA agregar
- ⚠️ Art. 80 certificados — FALTA agregar

## STATUS_DOCS_v2.1_vs_MARZO2026.md

Este archivo (REEMPLAZADO):
- ❌ Estaba enfocado en "actualización documental"
- ❌ NO reflejaba cambios normativos reales
- ✅ Ahora es MATRIZ NORMATIVA (esta versión)

---

# 9️⃣ CONCLUSIÓN

**La Ley 27.802 impacta directamente en:**

1. **Detección de relación laboral** (Art. 23 — Presunción)
2. **Análisis de responsabilidad** (Art. 30 — Solidaria)
3. **Validación de registración** (Registro digital ARCA)
4. **Cálculo de multas** (Art. 80 — Certificados digitales)
5. **Indemnizaciones** (Ackerman + IBM reforzada — doctrinal)

**Prioritario actualizar FASE 1** (3h):
- README
- RESUMEN_EJECUTIVO
- ECUACIONES
- VALIDACION_LEGAL

Pues son los que circulan hacia directivos y legal antes de implementación.

| # | DOCUMENTO | TIPO | STATUS | PRIORIDAD | REQUIERE ACTUALIZACIÓN | SECCIONES A ACTUALIZAR |
|---|-----------|------|--------|-----------|----------------------|------------------------|
| 1 | **README.md** | BASE | ✅ v2.1 | 🔴 CRÍTICA | ✅ SÍ | • Overview + cambios v2.1+ • Nuevas características • Breaking changes (NINGUNO) |
| 2 | **RESUMEN_EJECUTIVO_v2.1.md** | RESUMEN | ✅ v2.1 | 🔴 CRÍTICA | ✅ SÍ | • Tabla de cambios: agregar fila IBM Reforzada • Impacto económico: +30-50% en vía civil • Riesgos: Art. 132 bis |
| 3 | **ECUACIONES_MOTOR_LABORAL.md** | TEORÍA | ✅ v2.1 | 🟠 ALTA | ✅ SÍ | • FASE 3 IBM: incluir fórmula Ackerman • Apéndices: tabla IPC histórica • Referencias: Ackerman (2026) |
| 4 | **GUIA_IMPLEMENTACION_v2.1.md** | CÓDIGO | ✅ v2.1 | 🟠 ALTA | ✅ SÍ | • Sección IBM: funciones nuevas + ejemplos • EscenariosEngine: métodos de alertas • Test cases marzo |
| 5 | **INTEGRACION_PROCESAR_ANALISIS_v2.1.md** | INTEGRACIÓN | ✅ v2.1 | 🟠 ALTA | ✅ SÍ | • PASO 0: verificación Ley Bases (Art. 132 bis) • IBM Reforzada: integración en cálculo ART • Alertas: captura en response |
| 6 | **AUTOMATIZACION_RIPTE_BD.md** | BD + AUTOMATION | ✅ v2.1 | 🟡 MEDIA | ✅ SÍ | • Setup: agregar tabla IPC_históricos (opcional) • Admin dashboard: mostrar alertas de gap • Cron: incluir check GAP |
| 7 | **VALIDACION_LEGAL_PRODUCCION_v2.1.md** | TESTING + LEGAL | ✅ v2.1 | 🟠 ALTA | ✅ SÍ | • Test Suite: agregar 4 casos marzo (IBM, alertas, daño moral, avisos) • Checklist legal: Art. 132 bis • Resolución legal: template actualizado |
| 8 | **QUICK_START_v2.1.md** | IMPLEMENTACIÓN RÁPIDA | ✅ v2.1 | 🟡 MEDIA | ⚠️ OPCIONAL | • PASO 3: mención breve de calcularIBMReforzada • FAQ: preguntas sobre alertas popup • Testing rápido: IBM Reforzada |
| 9 | **CHEAT_SHEET_FORMULAS_v2.1.md** | REFERENCIA RÁPIDA | ✅ v2.1 | 🟡 MEDIA | ⚠️ OPCIONAL | • Agregar formula IBM Reforzada • Valores fallback IPC • Tabla de alertas por provincia |
| 10 | **INDICE_DOCUMENTACION_v2.1.md** | ÍNDICE/NAVEGACIÓN | ✅ v2.1 | 🟡 MEDIA | ⚠️ OPCIONAL | • Nivel 2A: "Jurisprudencia Ackerman (Marzo 2026)" • Nivel 3B: " IBM Reforzada paso a paso" • Links a ACTUALIZACION_MARZO_2026.md |
| **⭐ NUEVO** | **ACTUALIZACION_MARZO_2026.md** | PARCHES | 🆕 Creado | 🔴 CRÍTICA | ✅ INCLUIR | • Punto de entrada para todos los cambios • Index para otros docs |

---

## 📋 TABLA DE DECISIÓN DE PRIORIDAD

### 🔴 CRÍTICAS (Actualizar ya)

| Doc | Razón | Tiempo Est. |
|-----|-------|------------|
| README.md | Frontal del proyecto | 20 min |
| RESUMEN_EJECUTIVO_v2.1.md | Ejecutivos leen esto primero | 30 min |
| ECUACIONES_MOTOR_LABORAL.md | Teoría base de IBM Reforzada | 45 min |
| VALIDACION_LEGAL_PRODUCCION_v2.1.md | Testing produc es mandatorio | 60 min |

**Subtotal**: 2.5 horas

---

### 🟠 ALTAS (Actualizar en 1 semana)

| Doc | Razón | Tiempo Est. |
|-----|-------|------------|
| GUIA_IMPLEMENTACION_v2.1.md | Devs necesitan código nuevo | 60 min |
| INTEGRACION_PROCESAR_ANALISIS_v2.1.md | Step-by-step de integración | 45 min |
| AUTOMATIZACION_RIPTE_BD.md | DBAs necesitan tabla IPC (opcional) | 30 min |

**Subtotal**: 2.25 horas

---

### 🟡 MEDIA (Actualizar en 2 semanas)

| Doc | Razón | Tiempo Est. |
|-----|-------|------------|
| QUICK_START_v2.1.md | Solo menciones breves | 20 min |
| CHEAT_SHEET_FORMULAS_v2.1.md | Referencia rápida, actualizar fórmulas | 25 min |
| INDICE_DOCUMENTACION_v2.1.md | Index, agregar links | 15 min |

**Subtotal**: 60 minutos

---

## 📋 DESGLOSE POR TIPO DE ARCHIVO

### 📖 DOCUMENTOS BASE (Fundamentos)

| Archivo | Cubre | Necesita | Impacto |
|---------|-------|----------|---------|
| ECUACIONES_MOTOR_LABORAL.md | Fórmulas teóricas | Fórmula IBM Reforzada (MAX RIPTE/IPC) | Doctrinal |
| README.md | Overview | Mención Ackerman 2026 + compatibilidad backward | Comunicativo |
| RESUMEN_EJECUTIVO_v2.1.md | Cambios v2.1 | Nueva tabla con parches Marzo | Ejecutivo |

---

### 💻 DOCUMENTOS TÉCNICOS (Código)

| Archivo | Cubre | Necesita | Impacto |
|---------|-------|----------|---------|
| GUIA_IMPLEMENTACION_v2.1.md | Código PHP + SQL | Funciones IBM Reforzada + alertas + métodos | High |
| INTEGRACION_PROCESAR_ANALISIS_v2.1.md | Integración modular | Dónde llamar IBM Reforzada, cómo capturar alertas | High |
| AUTOMATIZACION_RIPTE_BD.md | Cron + BD | Tabla IPC (opcional), admin dashboard con alertas | Medium |

---

### 📚 DOCUMENTOS DE REFERENCIA (Consulta)

| Archivo | Cubre | Necesita | Impacto |
|---------|-------|----------|---------|
| QUICK_START_v2.1.md | 5 pasos rápidos | Mención IBM Reforzada en PASO 3 | Low |
| CHEAT_SHEET_FORMULAS_v2.1.md | Fórmulas compiladas | Agregar formula IBM Reforzada + tabla IPC | Low |
| INDICE_DOCUMENTACION_v2.1.md | Índice navegación | Agregar sección "Jurisprudencia Ackerman" | Low |

---

### ✅ DOCUMENTOS DE VALIDACIÓN

| Archivo | Cubre | Necesita | Impacto |
|---------|-------|----------|---------|
| VALIDACION_LEGAL_PRODUCCION_v2.1.md | Testing + legal | 4 test cases Marzo + alerta Art. 132 bis | High |

---

## 🎯 PLAN DE ACTUALIZACIÓN RECOMENDADO

### **FASE 1 — 14 Marzo (INMEDIATO, 2.5h)**

Actualizar archivos **CRÍTICOS** para que la documentación sea coherente:

```
1. README.md (20 min)
   ✅ Agregar: "Parches Marzo 2026 — IBM Reforzada, Alertas, Daño Moral"
   ✅ Nota: "100% backward compatible con v2.1"

2. RESUMEN_EJECUTIVO_v2.1.md (30 min)
   ✅ Tabla: agregar 4 filas (IBM, alertas, daño moral, Art. 132)
   ✅ Impacto económico: +30-50% en vía civil

3. ECUACIONES_MOTOR_LABORAL.md (45 min)
   ✅ FASE 3 IBM: agregar fórmula MAX(RIPTE, IPC)
   ✅ Apéndice: tabla IPC histórica
   ✅ Referencias: Ackerman (2026) página 23

4. VALIDACION_LEGAL_PRODUCCION_v2.1.md (60 min)
   ✅ Test Suite: agregar TEST_MARZO_01 a TEST_MARZO_04
   ✅ Checklist legal: Art. 132 bis
   ✅ Resolución: template con alertas
```

---

### **FASE 2 — Semana del 17 Marzo (NECESARIO, 2.25h)**

Actualizar archivos técnicos para **implementadores**:

```
5. GUIA_IMPLEMENTACION_v2.1.md (60 min)
   ✅ Sección 2.5: IBM Reforzada con ejemplo
   ✅ Sección 3: generarAlertasMarzo2026()
   ✅ Código: cómo integrar en procesar_analisis.php

6. INTEGRACION_PROCESAR_ANALISIS_v2.1.md (45 min)
   ✅ PASO 1.5: Verificación Ley Bases + Art. 132 bis
   ✅ PASO 3.5: IBM Reforzada en accidentes
   ✅ PASO 5: Captura de alertas en respuesta

7. AUTOMATIZACION_RIPTE_BD.md (30 min)
   ✅ Schema: tabla ripte_ipc_historica (OPCIONAL)
   ✅ Admin: mostrar GAP detector
   ✅ Cron: job para verificar gap
```

---

### **FASE 3 — Semana del 24 Marzo (OPCIONAL, 1h)**

Actualizar archivos de **referencia** (menos críticos):

```
8. QUICK_START_v2.1.md (20 min)
   ✅ PASO 3: breve mención de IBM Reforzada
   ✅ FAQ: ¿Qué es el "gap" RIPTE/IPC?

9. CHEAT_SHEET_FORMULAS_v2.1.md (25 min)
   ✅ Tabla 2: fórmula IBM Reforzada
   ✅ Fallbacks: valores IPC

10. INDICE_DOCUMENTACION_v2.1.md (15 min)
    ✅ Nivel 2A: "Jurisprudencia Ackerman (Marzo 2026)"
    ✅ Link a ACTUALIZACION_MARZO_2026.md
```

---

## 🗂️ FLUJO RECOMENDADO POR USUARIO

### 👨‍⚖️ **Abogados/Directivos**
```
1. Leo: README.md (Overview)
2. Leo: RESUMEN_EJECUTIVO_v2.1.md (Cambios + impacto)
3. Consulto: ACTUALIZACION_MARZO_2026.md (Detalles legales)
```

### 👨‍💻 **Desarrolladores**
```
1. Leo: ECUACIONES_MOTOR_LABORAL.md (Teoría IBM Reforzada)
2. Leo: GUIA_IMPLEMENTACION_v2.1.md (Código nuevo)
3. Leo: INTEGRACION_PROCESAR_ANALISIS_v2.1.md (Integración)
4. Consulto: ACTUALIZACION_MARZO_2026.md (Referencias)
```

### 🧪 **QA/Testers**
```
1. Leo: VALIDACION_LEGAL_PRODUCCION_v2.1.md (Test cases)
2. Ejecuto: Test cases MARZO_01-04
3. Consulto: ACTUALIZACION_MARZO_2026.md (Criterios de aceptación)
```

### 🗄️ **DBAs/DevOps**
```
1. Consulto: AUTOMATIZACION_RIPTE_BD.md (Tabla IPC opcional)
2. Leo: INTEGRACION_PROCESAR_ANALISIS_v2.1.md (Setup)
3. Consulto: ACTUALIZACION_MARZO_2026.md (Parámetros)
```

---

## 📊 ESTIMACIÓN DE TIEMPO TOTAL

| Fase | Docs | Tiempo | Criticidad |
|------|------|--------|-----------|
| **1 — Inmediato** | 4 docs | **2h 45min** | 🔴 CRÍTICA |
| **2 — Semana 1** | 3 docs | **2h 15min** | 🟠 ALTA |
| **3 — Semana 2** | 3 docs | **1h 00min** | 🟡 MEDIA |
| **TOTAL** | **10 docs** | **6 horas** | Escalonada |

---

## ✅ CHECKLIST DE ACTUALIZACIÓN

### Si actualizo FASE 1 (Mínimo viable):
- [ ] README.md — Mención nueva documentación
- [ ] RESUMEN_EJECUTIVO_v2.1.md — Tabla con parches
- [ ] ECUACIONES_MOTOR_LABORAL.md — Fórmula Ackerman
- [ ] VALIDACION_LEGAL_PRODUCCION_v2.1.md — Test cases marzo
- [ ] ✅ **RESULTADO**: Sistema 80% documentado para Marzo 2026

### Si actualizo FASE 1 + FASE 2 (Recomendado):
- [ ] FASE 1 (arriba)
- [ ] GUIA_IMPLEMENTACION_v2.1.md — Código nuevo
- [ ] INTEGRACION_PROCESAR_ANALISIS_v2.1.md — Paso a paso
- [ ] AUTOMATIZACION_RIPTE_BD.md — tabla IPC
- [ ] ✅ **RESULTADO**: Sistema 100% documentado y listo para producción

### Si actualizo TODO (Opcional):
- [ ] FASE 1 + FASE 2
- [ ] QUICK_START_v2.1.md
- [ ] CHEAT_SHEET_FORMULAS_v2.1.md
- [ ] INDICE_DOCUMENTACION_v2.1.md
- [ ] ✅ **RESULTADO**: Documentación perfecta, sin nada sin actualizar

---

## 🔗 REFERENCIA CRUZADA

### Documento Central
→ **ACTUALIZACION_MARZO_2026.md** (YA CREADO)

### Referencias desde otros docs:
```
README.md
  → "Ver Sección 'Cambios v2.1+' en RESUMEN_EJECUTIVO_v2.1.md"
  → "Detalles técnicos: ACTUALIZACION_MARZO_2026.md"

ECUACIONES_MOTOR_LABORAL.md
  → "Fórmula detallada: ACTUALIZACION_MARZO_2026.md § IBM Reforzada"

GUIA_IMPLEMENTACION_v2.1.md
  → "Nuevas funciones: ver ACTUALIZACION_MARZO_2026.md"

VALIDACION_LEGAL_PRODUCCION_v2.1.md
  → "Test cases: ACTUALIZACION_MARZO_2026.md § Test Cases"
```

---

**Status General**: 🎯 **LISTO PARA ACTUALIZACIÓN FASE 1**  
**Próximo Paso**: ¿Deseas que actualice FASE 1 (4 docs críticos)?

