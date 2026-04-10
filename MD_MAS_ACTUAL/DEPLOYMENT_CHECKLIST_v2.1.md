# 🚀 DEPLOYMENT CHECKLIST — Motor Laboral v2.1+ (Ley 27.802)
## Pre-Producción: 25-Item Verification List

**Date**: 14 de Marzo de 2026  
**System Version**: 2.1+ with Ley 27.802  
**Purpose**: Ensure production-readiness before go-live  
**Estimated Time**: 4-6 horas  

---

## **📋 CHECKLIST RÁPIDO (Para descargar/imprimir)**

### ✅ TIER 1: DOCUMENTACIÓN (30 min)

- [ ] ✓ README.md presente en `/MD_MAS_ACTUAL/` (v2.1+)
- [ ] ✓ STATUS_DOCS_v2.1_vs_MARZO2026.md con paradigma Ley 27.802
- [ ] ✓ ECUACIONES_MOTOR_LABORAL.md secciones 0.1-0.4 presentes
- [ ] ✓ GUIA_IMPLEMENTACION_v2.1.md con métodos 1.1-1.8
- [ ] ✓ INTEGRACION_PROCESAR_ANALISIS_v2.1.md con PASOS 0a/0b/0c
- [ ] ✓ VALIDACION_LEGAL_PRODUCCION_v2.1.md con test cases 1.3-1.5
- [ ] ✓ CHEAT_SHEET con Art. 23/30/derogaciones
- [ ] ✓ INVENTARIO_DOCUMENTACION_v2.1.md (validación archivos)
- [ ] ✓ TRAZABILIDAD_COMPLETA_LEY27802.md (correlación)

**Responsable**: Tech Lead / Project Manager  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 2: CÓDIGO PHP (90 min)

#### **config/ripte_functions.php**
- [ ] ✓ Función `validar_presuncion_laboral()` implementada (38-80 líneas)
  - Input: tiene_facturacion, pago_bancario, contrato_escrito
  - Output: presuncion_opera (bool), controles_presentes (int)
  - Test: Llamar con 3 combinaciones (0/3, 1/3, 3/3)

- [ ] ✓ Función `validar_responsabilidad_solidaria()` implementada (100-120 líneas)
  - Input: CUIL, aportes, pago, CBU, ART
  - Output: exento (bool), controles_validados (int), factor_exension (float)
  - Test: Verification loop para 5 controles

- [ ] ✓ Función `detectar_fraude_laboral()` implementada (80-100 líneas)
  - Input: Patrones (factura, rol, registro, horas, rotación)
  - Output: fraude_nivel (string), score (float), indicadores (array)
  - Test: Score calculation por caso

- [ ] ✓ Función `evaluar_dano_complementario()` implementada (60-80 líneas)
  - Input: salario, antigüedad, causa_extinción, fue_violenta
  - Output: daño_moral, daño_patrimonial, total_danos
  - Test: Casos con 2-4 años antigüedad

- [ ] ✓ Función `calcular_multas_condicionadas()` MODIFICADA
  - ✓ Ley 24.013: return $0 (con comentario "DEROGADA desde Ley 27.802")
  - ✓ Ley 25.323: return $0 (con comentario)
  - ✓ Art. 80 LCT: aún calcula $salario × 3 (VIGENTE)
  - Test: Verificar que mulatas debidas sean siempre $0 o solo art.80

- [ ] ✓ Funciones RIPTE existentes intactas
  - `obtener_ripte_vigente()`
  - `obtener_tabla_ripte_historica()`
  - `calcularIBMconRIPTE()`
  - `aplicar_piso_minimo()`

**Responsable**: PHP Developer  
**Sign-off**: _______________  
**Date**: _______________

---

#### **config/IrilEngine.php**
- [ ] ✓ Método `validarPresuncionLaboral()` AGREGADO o integrado
- [ ] ✓ Método `validarResponsabilidadSolidaria()` REESCRITO (5-control framework)
  - Líneas ~604-670: Lógica nueva con 5 controles
  - Factor exención: max(1, 6 - controles_presentes)
  - Test: Cualquier combinación de 0-5 controles

- [ ] ✓ Método `calcularComplejidadProbatoria()` MODIFICADO
  - Líneas ~202-245: Agregar sección Art. 23
  - Si facturación + pago_bancario → reduce score por 0.5
  - Test: Input con ambos = menos complejidad

- [ ] ✓ Multiplicador multas por defecto: `false` (not `true`)
  - Línea ~700: `'aplica_multas' => false`
  - Alerta al usuario si necesita reactivar

**Responsable**: PHP Developer  
**Sign-off**: _______________  
**Date**: _______________

---

#### **config/parametros_motor.php**
- [ ] ✓ REMOVIDAS líneas:
  - `'multa_ley_24013_porc' => 0.25` ❌ 
  - `'multa_ley_25323_art1' => 1.00` ❌

- [ ] ✓ AGREGADAS líneas (reemplazo):
  - `'facturacion_reduce_presuncion' => true`
  - `'pago_bancario_reduce_presuncion' => true`
  - `'art_30_controles_requeridos' => 5`

- [ ] ✓ Todas las otras configuraciones preservadas

**Responsable**: PHP Developer  
**Sign-off**: _______________  
**Date**: _______________

---

#### **api/procesar_analisis.php**
- [ ] ✓ PASO 0 COMPLETAMENTE REDEFINIDO
  - ✓ PASO 0a: Llamar `validar_presuncion_laboral()`
    - Recibir: tiene_facturacion, pago_bancario, contrato_escrito
    - Logging: error_log("  Presunción opera: SÍ/NO")
  
  - ✓ PASO 0b: Llamar `validar_responsabilidad_solidaria()`
    - Recibir: CUIL, aportes, pago, CBU, ART
    - Logging: error_log("  Controles validados: N/5")
  
  - ✓ PASO 0c: Llamar `detectar_fraude_laboral()`
    - Recibir: Patrones
    - Logging: error_log("  Fraude nivel: BAJO/MEDIO/ALTO")

- [ ] ✓ PASO 5 NUEVO: Evaluar Daño Complementario
  - Llamar `evaluar_dano_complementario()`
  - Retornar en respuesta JSON

- [ ] ✓ Respuesta JSON tiene nueva sección: `"ley_27802"`
  - presuncion_laboral object
  - responsabilidad_solidaria object
  - evaluacion_fraude object
  - danos_complementarios object
  - derogaciones object

- [ ] ✓ PASO 5 de MULTAS retorna: array con all zeros excepto art.80

**Responsable**: PHP Developer  
**Sign-off**: _______________  
**Date**: _______________

---

#### **assets/js/wizard.js**
- [ ] ✓ NUEVOS INPUT FIELDS (Art. 23):
  - tiene_facturacion (checkbox/select)
  - pago_bancario (checkbox/select)
  - contrato_escrito (checkbox/select)

- [ ] ✓ NUEVOS INPUT FIELDS (Art. 30):
  - cuil_contratista (text, pattern: /^[0-9]{11}$/)
  - aportes_seg_social (select: sí/no)
  - pago_directo (select: sí/no)
  - cbu_contratista (text, pattern: 22 dígitos)
  - art_vigente (select: sí/no)

- [ ] ✓ NUEVOS INPUT FIELDS (Daño complementario):
  - causa_extincion (select: despido_discriminatorio / represalia / etc)
  - fue_violenta (checkbox)

- [ ] ✓ Validación client-side:
  - Campos requeridos marcados (*)
  - CUIL: validar 11 dígitos
  - CBU: validar 22 dígitos
  - Alert si falta información critícal

**Responsable**: Frontend Developer  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 3: BASE DE DATOS (60 min)

#### **MySQL/MariaDB**
- [ ] ✓ Script `sql/setup_ripte_v2.1.sql` ejecutado sin errores
  - ✓ Tabla `ripte_indices` creada + 13 filas (Feb 2026-02 down to Sep 2025-09)
  - ✓ Tabla `ripte_pisos_minimos` creada + 4 pisos (IPP, IPD, gran_invalidez, muerte)
  - ✓ Tabla `ripte_sincronizaciones` creada (auditoría)

- [ ] ✓ NEW: Tabla `certificados_trabajo_digitales` creada (Art. 80)
  - Campos: uuid, numero_certificado, cuil_trabajador, periodo, tipo_certificado, valido_segun_art80
  - Propósito: Registrar certificados expedidos en 3 formatos válidos
  - Script: Ver AUTOMATIZACION_RIPTE_BD.md sección 1.4

- [ ] ✓ Índices creados (idx_mes_ano, idx_estado, idx_vigencia, etc.)

- [ ] ✓ Verificación de integridad:
  ```sql
  SELECT * FROM ripte_indices WHERE estado = 'vigente';
  -- Esperado: 1 fila (febrero 2026, 154800.78)
  
  SELECT COUNT(*) FROM ripte_pisos_minimos WHERE activo = true;
  -- Esperado: 4 filas
  ```

- [ ] ✓ Respaldo (backup) creado ANTES de cualquier inserción

**Responsable**: DBA  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 4: VALIDACIÓN FUNCIONAL (60 min)

#### **Test Case Suite — Art. 23 (Presunción)**

**Caso 23-A: 0/3 controles (presunción OPERA)**
- Input: facturación=no, pago_bancario=no, contrato_escrito=no
- Expected: presuncion_opera=true, controles=0, análisis="✓ Presunción OPERA"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso 23-B: 1/3 controles (presunción DEBILITADA)**
- Input: facturación=sí, pago_bancario=no, contrato_escrito=no
- Expected: presuncion_opera=true, controles=1, análisis="⚠ Presunción DEBILITADA"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso 23-C: 2/3 controles (presunción CUESTIONABLE)**
- Input: facturación=sí, pago_bancario=sí, contrato_escrito=no
- Expected: presuncion_opera=true, controles=2, análisis="⚠⚠ Presunción CUESTIONABLE"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso 23-D: 3/3 controles (presunción NO OPERA)**
- Input: facturación=sí, pago_bancario=sí, contrato_escrito=sí
- Expected: presuncion_opera=false, controles=3, análisis="✗ Presunción NO OPERA"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

#### **Test Case Suite — Art. 30 (Solidaria)**

**Caso 30-A: 0/5 controles (SOLIDARIA COMPLETA)**
- Input: CUIL=vacío, aportes=no, pago=no, CBU=vacío, ART=no
- Expected: exento=false, controles=0, factor=6, análisis="✗ SOLIDARIA COMPLETA"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso 30-B: 2/5 controles (RIESGO MODERADO)**
- Input: CUIL=23123456789, aportes=sí, pago=no, CBU=vacío, ART=no
- Expected: exento=false, controles=2, factor=4
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso 30-C: 5/5 controles (EXENTO TOTAL)**
- Input: CUIL=23123456789, aportes=sí, pago=sí, CBU=1234567890123456789012, ART=sí
- Expected: exento=true, controles=5, factor=1, análisis="✓ EXENTO TOTAL"
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

#### **Test Case Suite — Derogaciones**

**Caso Derogación-A: Ley 24.013 retorna $0**
- Input: tipo_registro="no_registrado", fecha_extinción="2026-02-15"
- Expected: multa_24013 = $0 (NO excepción)
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso Derogación-B: Ley 25.323 retorna $0**
- Input: tipo_registro="interrupcion_injusta"
- Expected: multa_25323 = $0
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

**Caso Derogación-C: Art. 80 aún vigente**
- Input: salario=100000
- Expected: multa_art80 = 300000 (100K × 3)
- [ ] ✓ PASS / [ ] ✗ FAIL → Fecha: ___

#### **Test Case Suite — JSON Response**

- [ ] ✓ Respuesta incluye `"ley_27802"` section
- [ ] ✓ `ley_27802.presuncion_laboral` es object con: opera, controles, facturacion, pago_bancario, contrato, análisis
- [ ] ✓ `ley_27802.responsabilidad_solidaria` es object con: exento, controles_validados, factor, detalles, análisis
- [ ] ✓ `ley_27802.evaluacion_fraude` es object con: nivel, score, indicadores, recomendación
- [ ] ✓ `ley_27802.danos_complementarios` es object con: daño_moral, daño_patrimonial, total
- [ ] ✓ `ley_27802.derogaciones` es object con: ley_24013, ley_25323, art_80

**Responsable**: QA  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 5: SEGURIDAD (30 min)

- [ ] ✓ SQL Injection: Verificar que ALL queries usen prepared statements
- [ ] ✓ XSS Prevention: Inputs sanitized con htmlspecialchars() o similar
- [ ] ✓ CSRF Protection: Verificar tokens CSRF en formularios POST
- [ ] ✓ Rate Limiting: API endpoint `/api/procesar_analisis` tiene rate limit (100 req/min)
- [ ] ✓ Logging: Error_log() configurado en `/var/log/motor_laboral/` o similar
- [ ] ✓ Passwords: BD user con permisos mínimos (SELECT/INSERT/UPDATE solo en tablas requeridas)
- [ ] ✓ HTTPS: URL de producción usa HTTPS (no HTTP)
- [ ] ✓ Headers seguridad: Verificar X-Frame-Options, Content-Security-Policy, etc.

**Responsable**: Security Officer  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 6: PERFORMANCE (30 min)

- [ ] ✓ Índices BD: Verificar que índices estén creados (EXPLAIN queries)
- [ ] ✓ Query tiempo: procesar_analisis.php < 2 segundos
- [ ] ✓ Memoria: Script PHP usa < 128MB RAM
- [ ] ✓ Conexión BD: Pooling habilitado (si aplica)
- [ ] ✓ Caché: RIPTE tabla cacheada en memoria (1 día TTL)
- [ ] ✓ Load test: Simular 100 usuarios simultáneos (herramienta: Apache JMeter / Locust)

**Responsable**: DevOps / Performance Engineer  
**Sign-off**: _______________  
**Date**: _______________

---

### ✅ TIER 7: OPERACIONAL (30 min)

- [ ] ✓ Monitoring setup
  - [ ] ✓ Alertas para errores en error_log
  - [ ] ✓ Alertas para tiempo de respuesta > 5s
  - [ ] ✓ Alertas para BD connection down

- [ ] ✓ Backup automation
  - [ ] ✓ BD backup cada 4 horas (automático)
  - [ ] ✓ Archivos /MD_MAS_ACTUAL/ versionados (git)
  - [ ] ✓ Test restore process completado

- [ ] ✓ Logs rotation
  - [ ] ✓ error_log rotado diariamente
  - [ ] ✓ Retención: 30 días

- [ ] ✓ Runbook creado
  - [ ] ✓ Procedimiento de deployment
  - [ ] ✓ Procedimiento de rollback
  - [ ] ✓ Procedure de emergency contact

**Responsable**: DevOps  
**Sign-off**: _______________  
**Date**: _______________

---

## **🎯 GO-LIVE DECISION**

### **All Items Green?**

- [ ] ✓ ALL Tier 1-7 items checked
- [ ] ✓ ZERO critical failures
- [ ] ✓ 90%+ test cases passing
- [ ] ✓ Security review cleared
- [ ] ✓ Performance test passed
- [ ] ✓ Backup/restore verified

### **Approval Signatures**

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Tech Lead | _______________ | _______________ | _______________ |
| Project Manager | _______________ | _______________ | _______________ |
| QA Lead | _______________ | _______________ | _______________ |
| Security Officer | _______________ | _______________ | _______________ |
| DevOps Lead | _______________ | _______________ | _______________ |

### **FINAL DECISION**

- [ ] ✅ **GO** — Ready for production
- [ ] ⏸️ **HOLD** — Resolve items before go-live
- [ ] ⛔ **NO-GO** — Critical issues found

---

## **📊 QUICK REFERENCE**

**Files to Monitor in Production:**
```
/var/log/motor_laboral/error_log  ← Check daily
/var/log/motor_laboral/ripte_sync.log  ← Check weekly
/var/backups/motor_laboral/  ← Backup location
```

**Key URLs:**
```
API Endpoint: https://your-domain.com/api/procesar_analisis.php
Wizard URL: https://your-domain.com/index.php
DB: your-db-server:3306/motor_laboral
```

**Emergency Contact:** _____________________

**Rollback Procedure:** See RUNBOOK_ROLLBACK.md

---

**Generated**: 14-Mar-2026  
**Valid Until**: 30-Apr-2026 (then refresh)  
**Version**: v2.1+ with Ley 27.802  
