# ÍNDICE COMPLETO — Documentación Motor Laboral v2.1
## Guía de Navegación por Documentos

**Actualizado**: 14 de Marzo de 2026  
**Versión**: 2.1+ (con Ley 27.802)  
**Status**: ✅ PRODUCCIÓN LISTA + Ley 27.802 Integrada

---

## **🚨 CAMBIOS CRÍTICOS — LEY 27.802 (MARZO 2026)**

### **Qué Cambió en v2.1+**

| Elemento | Antes (v2.1) | Ahora (v2.1+) | Dónde |
|----------|------------|-------------|--------|
| **Presunción** | Aplicable siempre | Condicionada Art. 23 (no factura+pago+contrato) | README, ECUACIONES, CHEAT_SHEET |
| **Solidaria** | Principal siempre responsable | Exención si 5 controles (CUIL/aportes/pago/CBU/ART) | GUIA_IMPLEMENTACION 1.2 |
| **Multa No Registrado** | Ley 24.013 Art. 8 (25% × meses) | 🔴 DEROGADA — Evaluar fraude | CHEAT_SHEET, ripte_functions |
| **Multa Interrupción** | Ley 25.323 (Duplica antigüedad) | 🔴 DEROGADA — Daño complementario | ECUACIONES, INTEGRACION |
| **Multa Certificados** | Art. 80 LCT ($sal × 3) | 🟢 VIGENTE (única multa) | CHEAT_SHEET |
| **Daño Complementario** | No estandarizado | 3 categorías (moral/patrimonial/reputacional) | GUIA_IMPLEMENTACION 1.7 |
| **Fraude** | No evaluado | New detector (5 patrones) | GUIA_IMPLEMENTACION 1.6 |
| **PASO 0** | Solo Ley Bases | Triple: 0a Presunción + 0b Solidaria + 0c Fraude | INTEGRACION_PROCESAR_ANALISIS |

### **Dónde Leer Cada Cambio**

✅ **Implementación**: GUIA_IMPLEMENTACION secciones 1.1-1.8  
✅ **Integración**: INTEGRACION_PROCESAR_ANALISIS PASOS 0a/0b/0c  
✅ **Fórmulas**: CHEAT_SHEET nuevas secciones Art. 23/30  
✅ **Ecuaciones**: ECUACIONES_MOTOR_LABORAL secciones 0.2-0.3  
✅ **Validación**: VALIDACION_LEGAL secciones 1.3-1.5  

---

## **📚 ESTRUCTURA DE DOCUMENTACIÓN**

### **NIVEL 1: INICIACIÓN (Para empezar aquí)**

**🚀 QUICK_START_v2.1.md** — *5 pasos en 40 minutos*
- Descripción: Guía rápida de implementación sin teoría
- Audiencia: Desarrolladores en apuro, deployers
- Contenido:
  - ✅ Paso 1: Configurar BD (5 min)
  - ✅ Paso 2: Copiar librerías PHP (2 min)
  - ✅ Paso 3: Integrar en procesar_analisis.php (10 min)
  - ✅ Paso 4: Actualizar wizard.js (5 min)
  - ✅ Paso 5: Testing (10 min)
  - ✅ Checklist final + Preguntas frecuentes
- **COMIENZA AQUÍ** ← Si necesitas go-live rápido

**📖 README.md** — *Overview del proyecto*
- Descripción: Descripción general, requisitos, motivación
- Audiencia: Stakeholders, nuevos team members
- Contenido: Resumen ejecutivo, features v2.1, cambios normativas

---

### **NIVEL 2: APRENDIZAJE (Para entender el motor)**

**📐 ECUACIONES_MOTOR_LABORAL.md** — *Teoría + Fórmulas + Ejemplos*
- Descripción: **Documento central de ecuaciones matemáticas**
- Audiencia: Profesionales legales, analistas, auditores
- Secciones:
  - FASE 0: Validación Ley Bases (decisión fecha: 09/07/2024)
  - FASE 1: Cálculo IRIL (5 dimensiones ponderadas)
  - FASE 2: Exposición laboral (cobertura por tipo de incapacidad)
  - FASE 3: Índice ART (IBM dinámico + RIPTE)
  - FASE 4: Indemnización (Art. 233 integración, Vizzotti piso)
  - APÉNDICES: Tablas RIPTE, pisos, conversiones
- Formato: Markdown con fórmulas LaTeX + tablas comparativas
- **ENTENDER LA TEORÍA** ← Si necesitas saber "por qué"

**📊 RESUMEN_EJECUTIVO_v2.1.md** — *Cambios y impacto*
- Descripción: Resumen ejecutivo para directivos/clientes
- Audiencia: Directores, clientes, auditoría
- Contenido:
  - Qué cambió en v2.1 (5 puntos principales)
  - Impacto en cálculos (tablas comparativas)
  - Riesgo legal (Ley Bases context)
  - Checklist de implementación (12 items)
- **PARA DIRECTIVOS** ← Vista rápida de cambios

---

### **NIVEL 3: IMPLEMENTACIÓN (Para codificar)**

**🔧 GUIA_IMPLEMENTACION_v2.1.md** — *Código + SQL + Setup*
- Descripción: **Documento central de implementación técnica**
- Audiencia: Desarrolladores PHP, DBAs, DevOps
- Secciones:
  - Arquitectura general (6 capas)
  - Código PHP de todas las funciones (pseudocódigo + real)
  - SQL (CREATE TABLE, indexes, procedures)
  - Integración en procesar_analisis.php
  - Test cases con valores esperados
  - Troubleshooting
- **IMPLEMENTAR FUNCIONES** ← Si necesitas código PHP

**⚙️ INTEGRACION_PROCESAR_ANALISIS_v2.1.md** — *Pasos de integración*
- Descripción: Cómo conectar librería RIPTE al módulo principal
- Audiencia: Desarrolladores, integración team
- Contenido:
  - Código completo de procesar_analisis.php modificado
  - PASO 0-6 detallados (Ley Bases → IRIL → Multas → Escenarios)
  - Cambios en EscenariosEngine.php (intereses)
  - Actualización wizard.js (nuevos campos)
- **INTEGRAR MÓDULOS** ← Cómo encajan las piezas

**🗄️ sql/setup_ripte_v2.1.sql** — *Script BD listo para ejecutar*
- Descripción: SQL completamente funcional
- Audiencia: DBAs, DevOps
- Contenido:
  - CREATE TABLE (3 tablas: indices, pisos, sincronizaciones)
  - INSERT datos iniciales (13 meses RIPTE + 8 pisos)
  - Índices optimizados
  - Procedures almacenados
  - Setup de usuarios con permisos
- **EJECUTAR EN BD** ← Copiar y pegar en MySQL

**📦 config/ripte_functions.php** — *Librería reutilizable*
- Descripción: 15+ funciones listas para usar
- Audiencia: Desarrolladores
- Funciones principales:
  - `obtener_ripte_vigente()` — Fetch RIPTE actual
  - `obtener_tabla_ripte_historica()` — Fetch 12 meses
  - `calcularIBMconRIPTE()` — CPU-intensive core
  - `aplicar_piso_minimo()` — Validation
  - `validar_ley_bases()` — Conditional logic
  - `calcular_multas_condicionadas()` — Rules engine
  - + Utilidades y fallbacks
- **USAR EN CÓDIGO** ← Importar y llamar funciones

---

### **NIVEL 4: AUTOMATIZACIÓN (Para cron + sync)**

**⚙️ AUTOMATIZACION_RIPTE_BD.md** — *Sincronización automática*
- Descripción: Base de datos + scripting + scheduling
- Audiencia: DevOps, arquitectos
- Secciones:
  - BD schema (3 tablas con audit trail)
  - Python ripte_sync.py (280+ líneas con argparse)
  - Cron scheduling (15 Mar + 15 Sep @ 10:00 UTC)
  - PHP validaciones (FASE 0 Ley Bases)
  - Admin dashboard template (HTML/CSS)
  - Integration flow (PASO 0-4 diagram)
- **SETUP AUTOMÁTICO** ← Si quieres cron jobs

---

### **NIVEL 5: VALIDACIÓN Y PRODUCCIÓN (Antes de go-live)**

**✅ VALIDACION_LEGAL_PRODUCCION_v2.1.md** — *Checklist legal + tests*
- Descripción: **Documento crítico: asegurar cumplimiento normativo**
- Audiencia: QA, auditoría, legal, DevOps
- Secciones:
  - 1. Validaciones legales (7 normativas con test cases)
  - 2. Tests funcionales (Suite A/B/C con ejemplos)
  - 3. Checklist producción (Infrastructure, DB, Code, Security, Performance)
  - 4. Go-Live checklist (7 días pre-launch)
  - 5. Plantilla resolución legal (Para expediente)
  - 6. Checklist post-go-live (24h monitoring)
  - 7. Rollback plan
- **VALIDAR ANTES DE PRODUCCIÓN** ← Documento crítico
- **DOCUMENTAR DECISIONES LEGALES** ← Plantillas de resolución

**📋 CHEAT_SHEET_FORMULAS_v2.1.md** — *Quick reference*
- Descripción: Tarjeta de referencia rápida
- Audiencia: Todos (laminar y colgar en pared)
- Contenido:
  - 40+ fórmulas compiladas
  - Tablas de conversión
  - Decision tree visual
  - Valores fallback
- **RAPID LOOKUP** ← Cuando necesitas un número rápido

---

## **🗂️ MAPA MENTAL DE NAVEGACIÓN**

```
┌─────────────────────────────────────────────────────────┐
│     ¿NUEVO EN EL PROYECTO?                               │
└─────────────────────────────────────────────────────────┘
         ↓
    START HERE:
    → README.md (2 min)
    → RESUMEN_EJECUTIVO_v2.1.md (5 min)
    ↓
┌─────────────────────────────────────────────────────────┐
│     ¿NECESITAS ENTENDER FÓRMULAS?                       │
└─────────────────────────────────────────────────────────┘
    → ECUACIONES_MOTOR_LABORAL.md (30 min)
    → CHEAT_SHEET_FORMULAS_v2.1.md (5 min)
    ↓
┌─────────────────────────────────────────────────────────┐
│     ¿NECESITAS CODIFICAR RÁPIDO?                        │
└─────────────────────────────────────────────────────────┘
    → QUICK_START_v2.1.md (40 min)
    ↓ Pasos 1-5 secuenciales
    ↓
   [BD configurada + código integrado]
    ↓
┌─────────────────────────────────────────────────────────┐
│     ¿NECESITAS DETALLES TÉCNICOS?                       │
└─────────────────────────────────────────────────────────┘
    → GUIA_IMPLEMENTACION_v2.1.md (60 min, referencia)
    → INTEGRACION_PROCESAR_ANALISIS_v2.1.md (20 min)
    → config/ripte_functions.php (read + copy)
    ↓
┌─────────────────────────────────────────────────────────┐
│     ¿NECESITAS AUTOMATIZAR RIPTE?                       │
└─────────────────────────────────────────────────────────┘
    → AUTOMATIZACION_RIPTE_BD.md (40 min)
    ↓
┌─────────────────────────────────────────────────────────┐
│     ¿NECESITAS IR A PRODUCCIÓN?                         │
└─────────────────────────────────────────────────────────┘
    → VALIDACION_LEGAL_PRODUCCION_v2.1.md (CRÍTICO)
    ↓ Completar 7 checklist sections
    ↓
   [Validación legal ✓ | Testing ✓ | Go-live ✓]
```

---

## **📍 POR ROL Y RESPONSABILIDAD**

| Rol | Documentos Principales | Tiempo | Prioridad |
|---|---|---|---|
| **Directivo/Cliente** | README + RESUMEN_EJECUTIVO | 10 min | ⭐⭐⭐ |
| **Profesional Legal** | ECUACIONES + VALIDACION_LEGAL + Resoluciones | 90 min | ⭐⭐⭐ |
| **Analista/QA** | ECUACIONES + VALIDACION_LEGAL + Test cases | 120 min | ⭐⭐⭐ |
| **Desarrollador PHP** | GUIA_IMPLEMENTACION + ripte_functions.php + INTEGRACION | 150 min | ⭐⭐⭐ |
| **DBA/DevOps** | setup_ripte_v2.1.sql + AUTOMATIZACION_RIPTE + Monitoring | 120 min | ⭐⭐⭐ |
| **SRE/Infrastructure** | QUICK_START + VALIDACION_LEGAL (checklist) + Rollback | 90 min | ⭐⭐⭐ |
| **Soporte/Help Desk** | QUICK_START + CHEAT_SHEET | 30 min | ⭐⭐ |

---

## **🔍 BÚSQUEDA RÁPIDA POR TEMA**

### **Ley Bases (Nº 27.742)**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → FASE 0
- **Implementación**: GUIA_IMPLEMENTACION_v2.1.md → Sección 5.1
- **Validación**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Sección 1.1
- **Código**: config/ripte_functions.php → `validar_ley_bases()`
- **Test**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Test A.1-A.3

### **Art. 233 LCT (Integración Dinámica)**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → Sección 2.1.3
- **Fórmula**: (S/30) × (30 - día_despido)
- **Implementación**: GUIA_IMPLEMENTACION_v2.1.md → Ejemplo código
- **Validación**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Sección 1.2

### **IBM + RIPTE**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → Sección 3.1
- **Fórmula**: Promedio 12m × (RIPTE_accidente / RIPTE_mes)
- **Código**: config/ripte_functions.php → `calcularIBMconRIPTE()`
- **BD**: sql/setup_ripte_v2.1.sql → tabla ripte_indices
- **Automatización**: AUTOMATIZACION_RIPTE_BD.md → Sección 2

### **Pisos Mínimos ART**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → Sección 3.4
- **Valores**: CHEAT_SHEET_FORMULAS_v2.1.md → Tabla pisos
- **Código**: config/ripte_functions.php → `obtener_piso_minimo()`
- **BD**: sql/setup_ripte_v2.1.sql → tabla ripte_pisos_minimos
- **Validación**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Sección 1.5

### **Multas Condicionadas**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → FASE 0
- **Cálculo**: GUIA_IMPLEMENTACION_v2.1.md → Sección 5.2
- **Código**: config/ripte_functions.php → `calcular_multas_condicionadas()`
- **Validación**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Sección 1.1

### **Intereses Judiciales**
- **Concepto**: ECUACIONES_MOTOR_LABORAL.md → Sección 4.3
- **Tasas por provincia**: CHEAT_SHEET_FORMULAS_v2.1.md → Tabla tasas
- **Implementación**: INTEGRACION_PROCESAR_ANALISIS_v2.1.md → EscenariosEngine
- **Validación**: VALIDACION_LEGAL_PRODUCCION_v2.1.md → Sección 1.7

### **BD Setup**
- **Script SQL**: sql/setup_ripte_v2.1.sql (copiar y ejecutar)
- **Tablas**: AUTOMATIZACION_RIPTE_BD.md → Sección 1
- **Procedures**: sql/setup_ripte_v2.1.sql → Sección "Procedimientos"

### **Automatización RIPTE**
- **Conceptos**: ECUACIONES_MOTOR_LABORAL.md → Apéndice: RIPTE
- **Python script**: AUTOMATIZACION_RIPTE_BD.md → Sección 2
- **Cron setup**: AUTOMATIZACION_RIPTE_BD.md → Sección 3
- **Monitoring**: AUTOMATIZACION_RIPTE_BD.md → Sección 6

---

## **📊 ESTADÍSTICAS DE DOCUMENTACIÓN**

| Documento | Líneas | Palabras | Secciones | Ejemplos | Código |
|---|---|---|---|---|---|
| ECUACIONES_MOTOR_LABORAL.md | 775 | 12,500 | 5+3 | 40+ | No |
| GUIA_IMPLEMENTACION_v2.1.md | 1,100 | 18,000 | 6 | 25+ | ✓✓ |
| INTEGRACION_PROCESAR_ANALISIS_v2.1.md | 350 | 5,500 | 3 | 10+ | ✓✓ |
| AUTOMATIZACION_RIPTE_BD.md | 1,900 | 28,000 | 7 | 50+ | ✓✓✓ |
| RESUMEN_EJECUTIVO_v2.1.md | 350 | 5,500 | 4 | 15+ | No |
| CHEAT_SHEET_FORMULAS_v2.1.md | 450 | 6,500 | 8 | 100+ | No |
| QUICK_START_v2.1.md | 550 | 8,500 | 5 | 20+ | ✓ |
| VALIDACION_LEGAL_PRODUCCION_v2.1.md | 850 | 14,000 | 7 | 30+ | ✓ |
| config/ripte_functions.php | 600 | 10,000 | 6 | 30+ | ✓✓✓ |
| sql/setup_ripte_v2.1.sql | 350 | 4,500 | 7 | 15+ | ✓✓ |
| **TOTAL** | **8,125** | **112,000** | **50+** | **240+** | **Compilado** |

---

## **✅ Checklist de Lectura Recomendada**

### **Implementación Express (4 horas)**
- [ ] README.md (10 min)
- [ ] QUICK_START_v2.1.md (40 min)
- [ ] Ejecutar pasos 1-5 (120 min)
- [ ] CHEAT_SHEET (10 min)

### **Implementación Completa (2 días)**
- [ ] RESUMEN_EJECUTIVO (15 min)
- [ ] ECUACIONES_MOTOR_LABORAL (60 min)
- [ ] GUIA_IMPLEMENTACION (120 min)
- [ ] INTEGRACION_PROCESAR_ANALISIS (40 min)
- [ ] Ejecutar QUICK_START (40 min)
- [ ] VALIDACION_LEGAL (90 min)

### **Producción (3 días)**
- [ ] Todo de "Implementación Completa"
- [ ] AUTOMATIZACION_RIPTE_BD (80 min)
- [ ] Completar VALIDACION_LEGAL checklist (240 min)
- [ ] Post-go-live monitoring (24h)

---

## **🚀 QUICK LINKS POR TAREA**

| Tarea | Documento | Sección |
|---|---|---|
| Comenzar implementación | QUICK_START_v2.1.md | Paso 1 |
| Entender fórmulas | ECUACIONES_MOTOR_LABORAL.md | FASE 1-4 |
| Escribir código PHP | GUIA_IMPLEMENTACION_v2.1.md | Sección 5 |
| Ejecutar SQL | sql/setup_ripte_v2.1.sql | Copiar todo |
| Integrar módulos | INTEGRACION_PROCESAR_ANALISIS_v2.1.md | PASO 1-6 |
| Automatizar RIPTE | AUTOMATIZACION_RIPTE_BD.md | Sección 2-3 |
| Validar producción | VALIDACION_LEGAL_PRODUCCION_v2.1.md | Sección 3-6 |
| Referencia rápida | CHEAT_SHEET_FORMULAS_v2.1.md | Todas |

---

## **📞 SOPORTE Y CONTACTO**

Para problemas con:
- **Fórmulas/Normativa**: Ver ECUACIONES_MOTOR_LABORAL.md → correo legal@
- **Código PHP**: Ver GUIA_IMPLEMENTACION_v2.1.md → correo dev@
- **BD/SQL**: Ver sql/setup_ripte_v2.1.sql → correo dba@
- **Integración**: Ver INTEGRACION_PROCESAR_ANALISIS_v2.1.md → correo integration@
- **Producción**: Ver VALIDACION_LEGAL_PRODUCCION_v2.1.md → correo production@

---

## **📅 Control de Versiones**

| Versión | Fecha | Cambios | Estado |
|---|---|---|---|
| 1.0 | Ene 2025 | Versión inicial | Deprecated |
| 2.0 | Jun 2025 | Ley Bases integrada | Active |
| 2.1 | Feb 2026 | RIPTE automation + v2.1 completa | **✅ ACTUAL** |
| 2.2 | (Pendiente) | Integración blockchain (futuro) | Planned |

---

**Documentación Completada: 22 de Febrero de 2026 ✅**

**Todos los documentos listos para PRODUCCIÓN.**

¿Necesitas empezar? → Ve a **QUICK_START_v2.1.md**

¿Necesitas profundizar? → Ve a **ECUACIONES_MOTOR_LABORAL.md**

¿Necesitas ir a producción? → Ve a **VALIDACION_LEGAL_PRODUCCION_v2.1.md**
