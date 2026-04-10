# VALIDACIÓN LEGAL & PRODUCCIÓN — Motor Laboral v2.1
## Checklist de Cumplimiento Normativo antes de Go-Live

**Archivo**: `VALIDACION_LEGAL_PRODUCCION_v2.1.md`

---

## **📋 SECCIÓN 1: Validaciones Legales Pre-Producción**

### **1.1 VALIDACIÓN: Ley Bases (Nº 27.742)**

**Requisito**: Multas aplicadas condicionalmente según fecha de despido

| Fecha Despido | Regla | Multas | Test Case | Status |
|---|---|---|---|---|
| Anterior 09/07/2024 | Derechos adquiridos | ✅ APLICAN (24.013, 25.323, 80) | `test_anterior_ley_bases.php` | ☐ |
| Posterior 09/07/2024 (sin check) | Ley Bases vigente | ❌ NO APLICAN ($0) | `test_posterior_sin_check.php` | ☐ |
| Posterior 09/07/2024 (con check) | Override manual | ✅ APLICAN (asume riesgo) | `test_posterior_con_check.php` | ☐ |

**Evidencia requerida:**
- [ ] Código valida `fecha_extincion > 2024-07-09`
- [ ] Base de datos registra fecha de validación
- [ ] Documentación expediente incluye decisión profesional
- [ ] Sistema genera alerta en aplicación posterior

**Función responsable**: `validar_ley_bases()` en `config/ripte_functions.php` (línea ~280)

---

### **1.3 VALIDACIÓN: Ley 27.802 — Art. 23 LCT (Presunción Laboral)**

**Requisito**: La presunción NO opera cuando existe facturación + pago bancario

| Situación | Facturación | Pago Bancario | Presunción Aplica | Test Case |
|---|:-:|:-:|:-:|---|
| Empleado clásico | ❌ | ❌ | ✅ SÍ | `test_presuncion_opera.php` |
| Prestador híbrido | ✅ | ✅ | ❌ NO | `test_presuncion_no_opera.php` |
| Caso mixto | ✅ | ❌ | ✅ PARCIAL | `test_presuncion_parcial.php` |

**Evidencia requerida:**
- [ ] Sistema evalúa presencia de facturación
- [ ] Sistema evalúa presencia de pago bancario
- [ ] Si ambos presentes: Reduce complejidad probatoria en IRIL (-0.5)
- [ ] Alerta generada si presunción NO opera
- [ ] Documentación indica "Requiere prueba reforzada de dependencia"

**Función responsable**: `calcularComplejidadProbatoria()` en `config/IrilEngine.php` (línea ~202)

---

### **1.4 VALIDACIÓN: Ley 27.802 — Art. 30 LCT (Responsabilidad Solidaria)**

**Requisito**: Principal exento de solidaria si valida 5 controles

| Controles Validados | CUIL | Aportes | Pago | CBU | ART | Riesgo Solidario | Test Case |
|---|:-:|:-:|:-:|:-:|:-:|---|---|
| 0/5 | ❌ | ❌ | ❌ | ❌ | ❌ | $600k | `test_solidaria_alto_riesgo.php` |
| 3/5 | ✅ | ✅ | ✅ | ❌ | ❌ | $240k | `test_solidaria_riesgo_medio.php` |
| 5/5 | ✅ | ✅ | ✅ | ✅ | ✅ | $0 (EXENTO) | `test_solidaria_exento.php` |

**Evidencia requerida:**
- [ ] Sistema recibe array de 5 controles validados
- [ ] Cálculo: `riesgo = salario × (5 - controles_presentes) × cantidad_subcontratistas`
- [ ] Si 5/5 controles: Riesgo = 0 (exención aplicada)
- [ ] Documentación detalla qué verificar para cada control
- [ ] DB registra fecha de validación de cada control

**Función responsable**: Línea ~620 en `config/IrilEngine.php`

---

### **1.5 VALIDACIÓN: Ley 27.802 — Derogaciones (Ley 24.013 / 25.323)**

**Requisito**: Leyes 24.013 Arts. 8/9/10 y Ley 25.323 NO aplican posterior 01/03/2026

| Fecha Hecho | Ley 24.013 | Ley 25.323 | Comportamiento Sistema | Test Case |
|---|---|---|---|---|
| Anterior 01/03/2026 | ✅ Aplica | ✅ Aplica | Calcula multas (ALERTA) | `test_derogacion_anterior.php` |
| Posterior 01/03/2026 | ❌ NO aplica | ❌ NO aplica | $0 (genera ALERTA derogación) | `test_derogacion_posterior.php` |

**Evidencia requerida:**
- [ ] Multas Ley 24.013 = $0 post-01/03/2026
- [ ] Multas Ley 25.323 = $0 post-01/03/2026
- [ ] Sistema genera ALERTA: "Multas históricas derogadas"
- [ ] Nota recomendativa: "Evaluar fraude laboral en su lugar"
- [ ] Derecho transitorio: hechos anteriores siguen algunas reglas antiguas

**Función responsable**: `generarAlertasMarzo2026()` en `config/EscenariosEngine.php`

---

**Requisito**: Integración = (S/30) × (30 - día_despido)

| Día Despido | Fórmula | Resultado | Validación |
|---|---|---|---|
| 5 | (100k/30) × (30-5) = 3,333 × 25 | $83,333 | ☐ |
| 15 | (100k/30) × (30-15) = 3,333 × 15 | $50,000 | ☐ |
| 25 | (100k/30) × (30-25) = 3,333 × 5 | $16,667 | ☐ |
| 30 | (100k/30) × (30-30) = 3,333 × 0 | $0 | ☐ |

**Evidencia requerida:**
- [ ] Campo `dia_despido` (1-31) recibido del wizard
- [ ] Fórmula implementada en IrilEngine o procesar_analisis
- [ ] Test de los 4 casos extremos (5, 15, 25, 30)
- [ ] Log de cálculo incluido en respuesta JSON

**Cálculo**: En `INTEGRACION_PROCESAR_ANALISIS_v2.1.md` → PASO 2

---

### **1.3 VALIDACIÓN: IEEE/Vizzotti (Art. 245 LCT)**

**Requisito**: Indemnización ≥ Salario × Años × 0.67 (67% piso)

| Caso | Salario | Años | 67% Piso | Indemnización Calculada | Aplica | Test |
|---|---|---|---|---|---|---|
| CCT restrictivo | $100k | 4 | $268k | $240k | ✅ (piso: $268k) | `test_vizzotti_piso.php` | ☐ |
| Indemnización normal | $100k | 4 | $268k | $400k | ❌ (ya supera) | `test_vizzotti_no_aplica.php` | ☐ |

**Evidencia requerida:**
- [ ] Función `aplicar_vizzotti()` en IrilEngine
- [ ] Valida contra CCT vigente (si existe)
- [ ] Test de caso crítico (Distribuidor, etc.)
- [ ] Log de aplicación de piso

---

### **1.4 VALIDACIÓN: IBM Dinámico + RIPTE (Ley 26.773 Art. 9)**

**Requisito**: IBM = Promedio de 12 salarios × coeficientes RIPTE

| Mes | Salario | RIPTE | Coef | Ajustado | Test |
|---|---|---|---|---|---|
| 2025-02 | $80k | 100k | 1.548 | $123,840 | ☐ |
| 2026-01 | $93k | 152.1k | 1.017 | $94,581 | ☐ |
| Promedio (12 meses) | - | - | - | **$87,325** | ☐ |

**Evidencia requerida:**
- [ ] Tabla RIPTE histórica disponible (últimos 12 meses)
- [ ] Función `calcularIBMconRIPTE()` implementada
- [ ] Coeficiente = RIPTE_accidente / RIPTE_mes
- [ ] Promedio correcto de 12 salarios ajustados
- [ ] Test con datos reales (12 meses: Feb 2025-Ene 2026)

**Función**: `calcularIBMconRIPTE()` en `config/ripte_functions.php` (línea ~180)

**Test case**:
```php
$salarios = [
    '2025-02' => 80000, '2025-03' => 82000, '2025-04' => 84000,
    '2025-05' => 85000, '2025-06' => 86000, '2025-07' => 87000,
    '2025-08' => 88000, '2025-09' => 89000, '2025-10' => 90000,
    '2025-11' => 91000, '2025-12' => 92000, '2026-01' => 93000
];
$tabla_ripte = obtener_tabla_ripte_historica($db, 12);
$ibm = calcularIBMconRIPTE($salarios, new DateTime('2026-02-15'), $tabla_ripte);
// Esperado: ~$85,500-$90,000 (rango realista)
```

---

### **1.5 VALIDACIÓN: Pisos Mínimos ART (Ley 24.557 Arts. 14-16)**

**Requisito**: Indemnización ≥ piso mínimo vigente según tipo de incapacidad

| Tipo | Piso Sep 2025 | Piso Mar 2026 (est.) | Actualización |
|---|---|---|---|
| IPP (Incapacidad Parcial Permanente) | $2,260,000 | $2,470,000 | +9.3% |
| IPD (Incapacidad Permanente Deportiva) | $4,520,000 | $4,940,000 | +9.3% |
| Gran Invalidez | $9,040,000 | $9,880,000 | +9.3% |
| Muerte | $6,780,000 | $7,410,000 | +9.3% |

**Evidencia requerida:**
- [ ] Tabla `ripte_pisos_minimos` cargada en BD
- [ ] Función `obtener_piso_minimo()` valida vigencia
- [ ] Función `aplicar_piso_minimo()` compara monto < piso
- [ ] Test: Monto = $1.5M < Piso IPP = $2.26M → Aplica piso
- [ ] Actualización automática semestrales (15-Mar, 15-Sep)

**Test case**:
```php
$piso = obtener_piso_minimo($db, 'IPP', new DateTime('2026-02-15'));
// Esperado: 2260000 (vigente desde 15 Sep 2025)

$resultado = aplicar_piso_minimo(1500000, 'IPP', $piso);
// Esperado: ['monto_final' => 2260000, 'piso_aplicado' => true]
```

---

### **1.6 VALIDACIÓN: Daño Moral (Vía Civil - Ley 17.711 Art. 522)**

**Requisito**: En litigio civil, daño moral = +20% sobre incapacidad

| Escenario | Base | Daño Moral (20%) | Total |
|---|---|---|---|
| IPP civil | $2,500,000 | $500,000 | $3,000,000 | ☐ |
| Accidente solo ART | $2,500,000 | $0 | $2,500,000 | ☐ |

**Evidencia requerida:**
- [ ] Daño moral solo en `escenario_litigio_civil` (no en ART puro)
- [ ] Fórmula: `daño_moral = indemnizacion * 0.20`
- [ ] Documentado en escenarios generados
- [ ] Test: Litigio civil → +20% visible

---

### **1.7 VALIDACIÓN: Intereses Judiciales (CCCN Arts. 767-768)**

**Requisito**: VBP ajustado por tasa provincial durante litigio

| Provincia | Tasa Anual | 24 meses | 36 meses |
|---|---|---|---|
| CABA | 6.5% | +13.4% | +20.4% |
| PBA | 6.2% | +12.8% | +19.4% |
| Córdoba | 6.3% | +13.1% | +19.9% |
| Santa Fe | 6.1% | +12.5% | +19.0% |
| Promedio | 6.4% | +13.2% | +20.2% |

**Fórmula**: VF = VBase × (1 + tasa)^(meses/12)

**Evidencia requerida:**
- [ ] Tabla de tasas por jurisdicción en `EscenariosEngine.php`
- [ ] Meses de litigio recibido (default: 24)
- [ ] Cálculo de potencia: (1.06)^(36/12) = 1.202
- [ ] Test: VAE $1.5M × 1.202 = $1.803M

**Test case**:
```php
// VAE base: $1,500,000 | Litigio: 36 meses | CABA: 6.5%
vae_final = 1500000 * pow(1.065, 36/12);
// Esperado: 1,806,075
```

---

## **📋 SECCIÓN 2: Tests Funcionales Completos**

### **Test Suite A: Despido Laboral**

**Test A.1: Despido Anterior Ley Bases (Derechos Adquiridos)**

```php
<?php
// File: test_despido_anterior_ley_bases.php

$test = [
    'nombre' => 'Despido Anterior 09/07/2024 — Multas Aplican',
    'entrada' => [
        'tipo_calculo' => 'despido',
        'salario' => 100000,
        'meses_antiguedad' => 48,
        'fecha_extincion' => '2024-05-15',
        'dia_despido' => 15,
        'check_inconstitucionalidad' => false,
        'tipo_registro' => 'no_registrado',
    ],
    'validaciones' => [
        'ley_bases' => ['estado' => 'anterior', 'aplica_multas' => true],
        'multas' => ['total_multas' => '> 1500000'], // Ley 24.013 + 25.323 + 80
        'integración' => '50000', // (100k/30) × 15
    ]
];
?>
```

**Test A.2: Despido Posterior Ley Bases (Sin Check)**

```php
<?php
$test = [
    'nombre' => 'Despido Posterior 09/07/2024 (Sin Check) — Multas NO Aplican',
    'entrada' => [
        'tipo_calculo' => 'despido',
        'salario' => 100000,
        'meses_antiguedad' => 48,
        'fecha_extincion' => '2025-10-15',
        'dia_despido' => 15,
        'check_inconstitucionalidad' => false,
        'tipo_registro' => 'no_registrado',
    ],
    'validaciones' => [
        'ley_bases' => ['estado' => 'posterior', 'aplica_multas' => false],
        'multas' => ['total_multas' => 0],
        'alerta' => 'contiene "Ley Bases"',
    ]
];
?>
```

**Test A.3: Despido Posterior con Check (Override Manual)**

```php
<?php
$test = [
    'nombre' => 'Despido Posterior (Con Check) — Multas RESTAURADAS',
    'entrada' => [
        'tipo_calculo' => 'despido',
        'salario' => 100000,
        'meses_antiguedad' => 48,
        'fecha_extincion' => '2025-10-15',
        'dia_despido' => 15,
        'check_inconstitucionalidad' => true, // ← OVERRIDE
        'tipo_registro' => 'no_registrado',
    ],
    'validaciones' => [
        'ley_bases' => ['estado' => 'posterior', 'aplica_multas' => true],
        'multas' => ['total_multas' => '> 1500000'],
        'alerta' => 'contiene "CHECK DE INCONSTITUCIONALIDAD"',
    ]
];
?>
```

---

### **Test Suite B: Accidente ART**

**Test B.1: Accidente con Pisos Mínimos**

```php
<?php
$test = [
    'nombre' => 'Accidente IPP — Aplicación Piso Mínimo',
    'entrada' => [
        'tipo_calculo' => 'accidente',
        'salario' => 100000,
        'meses_antiguedad' => 48,
        'fecha_extincion' => '2026-02-15',
        'tipo_registro' => 'IPP',
        'salarios_historicos' => [
            ['mes_ano' => '2025-02', 'monto' => 80000],
            // ... 12 meses totales
        ],
    ],
    'validaciones' => [
        'ibm' => ['>=80000', '<=95000'],  // Rango realista
        'pisos' => ['piso_aplicado' => true, 'monto_final' => '>=2260000'],
        'cobertura' => 'ART (sin multas)',
    ]
];
?>
```

**Test B.2: Accidente con IBM Dinámico**

```php
<?php
$test = [
    'nombre' => 'Accidente con Histórico 12 Meses — IBM Dinámico',
    'entrada' => [
        'tipo_calculo' => 'accidente',
        'salarios_historicos' => [
            '2025-02' => 80000, '2025-03' => 82000, '2025-04' => 84000,
            '2025-05' => 85000, '2025-06' => 86000, '2025-07' => 87000,
            '2025-08' => 88000, '2025-09' => 89000, '2025-10' => 90000,
            '2025-11' => 91000, '2025-12' => 92000, '2026-01' => 93000
        ],
        'fecha_accidente' => '2026-02-15',
    ],
    'validaciones' => [
        'ibm_origen' => 'histórico_12m',
        'ibm' => ['>=80000', '<=95000'],
        'nota' => 'Más preciso que salario directo',
    ]
];
?>
```

---

### **Test Suite C: Escenarios y Intereses**

**Test C.1: Escenario Judicial con Intereses (CABA)**

```php
<?php
$test = [
    'nombre' => 'Litigio CABA (6.5%) — 36 meses',
    'entrada' => [
        'tipo_calculo' => 'despido',
        'salario' => 100000,
        'meses_antiguedad' => 120,
        'jurisdiccion' => 'CABA',
        'meses_litigio' => 36,
    ],
    'validaciones' => [
        'escenario_litigio' => [
            'vae_base' => '~1500000',
            'interes_acumulado' => '~306000', // 20.4%
            'vae_final' => '~1806000',
        ]
    ]
];
?>
```

---

## **📋 SECCIÓN 3: Checklist de Producción**

### **3.1 Infrastructure & Deployment**

- [ ] Base de datos MySQL/MariaDB 5.7+
- [ ] PHP 7.4 o superior
- [ ] PDO o mysqli instalados
- [ ] Carpeta `logs/` con permisos 755
- [ ] Carpeta `sql/` con backups semanales

**Comando verificación**:
```bash
php -v  # PHP >= 7.4.0
mysql --version  # MySQL/MariaDB 5.7+
php -r "echo extension_loaded('pdo') ? '✓ PDO OK' : '✗ PDO no instalado';"
```

### **3.2 Database Validation**

- [ ] Tablas `ripte_indices`, `ripte_pisos_minimos`, `ripte_sincronizaciones` creadas
- [ ] Datos RIPTE cargados (Feb 2025 - Feb 2026 + mínimo 1 vigente)
- [ ] Pisos mínimos vigentes para 4 tipos de incapacidad
- [ ] Índices optimizados (CREATE INDEX ejecutados)
- [ ] Backup automático configurado

```sql
-- Verificar
SELECT * FROM ripte_indices WHERE estado = 'vigente';
SELECT COUNT(*) FROM ripte_pisos_minimos WHERE activo = true;
SELECT * FROM ripte_sincronizaciones LIMIT 5;
```

### **3.3 Code Quality**

- [ ] `ripte_functions.php` sintaxis válida
  ```bash
  php -l config/ripte_functions.php
  ```
- [ ] `procesar_analisis.php` integrado sin errores
- [ ] `IrilEngine.php` compilado correctamente
- [ ] Todos los `require_once` resueltos
- [ ] No hay variables globales no inicializadas

### **3.4 Security Audit**

- [ ] SQL injection: $conexion→prepare() + bind_param
- [ ] XSS: json_encode() + header Content-Type
- [ ] Auth: Verificar permisos usuario 'ripte_sync'
- [ ] HTTPS: Certificado SSL válido
- [ ] Rate limiting: Implementar en procesar_analisis.php
- [ ] Error logs: No exponen rutas sensibles

**Ejemplo fix**:
```php
// ❌ INSEGURO
$query = "SELECT * FROM ripte where mes = '$mes'";

// ✅ SEGURO
$stmt = $db->prepare("SELECT * FROM ripte WHERE mes = ?");
$stmt->bind_param("s", $mes);
$stmt->execute();
```

### **3.5 Performance Testing**

| Métrica | Objetivo | Tolerancia | Test |
|---|---|---|---|
| Tiempo respuesta procesar_analisis | < 1 seg | ≤ 2 seg | ☐ |
| Queries RIPTE (sin cache) | < 100ms | ≤ 200ms | ☐ |
| Cálculo IBM 12 meses | < 50ms | ≤ 100ms | ☐ |
| Respuesta JSON (escenarios) | < 500ms | ≤ 1000ms | ☐ |
| Sincronización RIPTE (cron) | < 5 seg | ≤ 10 seg | ☐ |

**Test load**:
```bash
# Apache Bench
ab -n 100 -c 10 'http://localhost/motor_laboral/api/procesar_analisis.php' \
  -H "Content-Type: application/json" \
  -d '{"tipo_calculo":"despido","salario":100000}'
```

### **3.6 Logging & Monitoring**

- [ ] error_log() configurado → `/var/log/motor_laboral.log`
- [ ] DB queries loggeadas (audit trail en ripte_sincronizaciones)
- [ ] Alertas configuradas para errores críticos
- [ ] Dashboards: ripte actual, últimas sincronizaciones, errores

```php
// Setup logging
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/motor_laboral.log');
error_log("[PASO 0] Validación Ley Bases iniciada");
```

### **3.7 Documentation**

- [ ] ECUACIONES_MOTOR_LABORAL.md — Acceso profesional
- [ ] GUIA_IMPLEMENTACION_v2.1.md — Acceso dev team
- [ ] QUICK_START_v2.1.md — Acceso operadores
- [ ] README.md — Acceso público
- [ ] Comentarios código — Explicar lógica compleja

---

## **📋 SECCIÓN 4: Go-Live Checklist**

| Actividad | Owner | Deadline | Status |
|---|---|---|---|
| ☐ Completar Test Suite A (Despido) | QA | -2 días | ☐ |
| ☐ Completar Test Suite B (Accidente) | QA | -2 días | ☐ |
| ☐ Completar Test Suite C (Escenarios) | QA | -1 día | ☐ |
| ☐ Security audit (SQLi, XSS, Auth) | Security | -1 día | ☐ |
| ☐ Performance testing (load) | DevOps | -1 día | ☐ |
| ☐ Backup BD configurado | DevOps | -1 día | ☐ |
| ☐ Cron ripte_sync.py programado | DevOps | -1 día | ☐ |
| ☐ Monitoring alerts configurados | DevOps | -1 día | ☐ |
| ☐ Capacitacion equipo legal | Legal | -3 días | ☐ |
| ☐ Capacitacion equipo soporte | Support | -2 días | ☐ |
| ☐ **GO-LIVE** | Project Manager | **HOY** | ☐ |
| ☐ Post-go-live monitoring (24h) | DevOps | +1 día | ☐ |
| ☐ User feedback review | Product | +3 días | ☐ |

---

## **📋 SECCIÓN 5: Documentación de Decisiones Legales**

### **5.1 Plantilla de Resolución (Expediente)**

Para cada cálculo posterior a 09/07/2024 → Documentar decisión:

```
RESOLUCIÓN PROFESIONAL — VALIDACIÓN LEY BASES
═══════════════════════════════════════════════════════════════

Caso: [Nombre demandante]
Fecha cálculo: [YYYY-MM-DD]
Profesional: [Nombre + Matrícula]

─────────────────────────────────────────────────────────────────

VALIDACIÓN LEY BASES (Nº 27.742)

Fecha de extinción del contrato: [YYYY-MM-DD]

   ✓ ANTERIOR a 09/07/2024
      → DERECHOS ADQUIRIDOS: Multas aplican normalmente
      → Ley 24.013, 25.323, Art. 80 LCT = SÍ
      
   ✗ POSTERIOR a 09/07/2024
      → Ley Bases vigente: Multas suspendidas por defecto
      → Check inconstitucionalidad: [SÍ / NO]
      
          Si NO:
          Multas = $0 (posición conservadora)
          Fundamentación: Respeto a normativa vigente
          
          Si SÍ:
          Multas = Aplican (assume riesgo)
          Fundamentación: [Citar juicios inconstitucionalidad]
                          [Referencias jurisprudenciales]

─────────────────────────────────────────────────────────────────

CÁLCULO REALIZADO:

Salario: $[cantidad]
Multa 24.013: $[cantidad]  [✓ Aplica / ✗ Suspendida]
Multa 25.323: $[cantidad]  [✓ Aplica / ✗ Suspendida]
Multa 80: $[cantidad]      [✓ Aplica / ✗ Suspendida]

TOTAL MULTAS: $[cantidad]

─────────────────────────────────────────────────────────────────

DECISIÓN PROFESIONAL:

He considerado y evaluado los siguientes aspectos:

1. Vigencia normativa de Ley 27.742 desde 09/07/2024
2. Múltiples juicios por inconstitucionalidad en proceso
3. Derechos y garantías del trabajador
4. Responsabilidad profesional y riesgo litigioso

Conclusión: [Descripción de decisión adoptada]
           [Riesgo legal si corresponde]
           [Recomendaciones al cliente]

─────────────────────────────────────────────────────────────────

Firma profesional: ________________
Fecha: [YYYY-MM-DD]
Matrícula: [######]

ADJUNTOS:
- Cálculo detallado motor laboral
- Jurisprudencia relevante
- Análisis de inconstitucionalidad (si aplica)
```

### **5.2 Ejemplos de Resolución**

**Ejemplo 1: Despido Anterior (Derechos Adquiridos)**

```
CASO: García, Juan Manuel vs. Empresa XYZ S.A.
FECHA EXTINCIÓN: 2024-05-15

DECISIÓN: Multas APLICAN (derechos adquiridos)

Fundamentación:
La extinción ocurrió con anterioridad a la entrada en vigencia 
de la Ley Bases (09/07/2024). Por tanto, al trabajador le asisten
derechos adquiridos y corresponde el pago de todas las multas
previstas en Ley 24.013, 25.323 y Art. 80 LCT.

TOTAL: $1,900,000
```

**Ejemplo 2: Despido Posterior (Sin Override)**

```
CASO: López, María José vs. Industrias ABC
FECHA EXTINCIÓN: 2025-10-15

DECISIÓN: Multas NO aplican (Ley Bases vigente)

Fundamentación:
La extinción es posterior a 09/07/2024 (vigencia Ley Bases).
Aunque existen cuestionamientos de inconstitucionalidad en litigio,
la norma vigente suspende las multas. Adoptamos la posición más
conservadora respetando la legislación actual.

Riesgo legal: BAJO (posición defensiva)

TOTAL MULTAS: $0
```

**Ejemplo 3: Despido Posterior (Con Override)**

```
CASO: Martínez, Carlos Andrés vs. Comercio S.A.
FECHA EXTINCIÓN: 2025-12-20

DECISIÓN: Multas RESTAURADAS (asumiendo riesgo)

Fundamentación:
Sin perjuicio de la vigencia de Ley Bases, esta firma considera
que la suspensión de multas en contra de derechos adquiridos
representa una violación de garantías constitucionales (Art. 17,
26 CN). Basamos opinión en:

1. Jurisprudencia de Cámara Federal (Caso X vs. Estado)
2. Opinión consultiva OIT sobre flexibilización laboral
3. Doctrina de jueces locales críticos con Ley Bases

Documentación: Todos los fallos citados adjuntos.

Riesgo legal: MODERADO a ALTO (posición activa)

Recomendación: Considerar esta posición solo si cliente
acepta riesgo de litigio prolongado.

TOTAL MULTAS: $1,750,000
```

---

## **📋 SECCIÓN 6: Checklist Post-Go-Live (24 Horas)**

| Actividad | Responsable | Frecuencia | Status |
|---|---|---|---|
| ☐ Verificar error_log sin errores críticos | DevOps | Cada 4h | ☐ |
| ☐ Procesar_analisis responde dentro de 2s | DevOps | Cada 2h | ☐ |
| ☐ BD ripte_sincronizaciones vacía/limpia | DBA | 24h | ☐ |
| ☐ Responder 5 consultas usuario | Support | Ad-hoc | ☐ |
| ☐ Validar 5 cálculos aleatorios (precisión) | QA | Ad-hoc | ☐ |
| ☐ Monitorear uso de recursos CPU/RAM | DevOps | Cada 2h | ☐ |
| ☐ Backup automático funcionando | DBA | 24h | ☐ |

---

## **📋 SECCIÓN 7: Rollback Plan**

**Si ocurre error crítico en primeras 24h:**

1. **Detener motor**
   ```bash
   # Desactivar endpoint
   mv api/procesar_analisis.php api/procesar_analisis.php.disabled
   ```

2. **Restaurar versión anterior**
   ```bash
   git checkout HEAD~1 api/procesar_analisis.php
   git checkout HEAD~1 config/ripte_functions.php
   ```

3. **Restaurar BD**
   ```bash
   mysql motor_laboral < backup_pre_v2.1.sql
   ```

4. **Notificar**
   - [ ] Equipo soporte
   - [ ] Clientes (si afecta)
   - [ ] Legal (si es Ley Bases)

5. **Post-mortem**
   - [ ] Identificar causa raíz
   - [ ] Documentar en issues
   - [ ] Corregir y re-testing

---

## **Contactos Críticos**

```
SOPORTE TÉCNICO:
- DevOps Lead: ___________ | Tel: ___________
- DBA: ___________ | Tel: ___________
- Security Lead: ___________ | Tel: ___________

LEGAL/NORMATIVO:
- Asesor Legal: ___________ | Tel: ___________
- Especialista Laboral: ___________ | Tel: ___________

ESCALATION:
- CTO: ___________ | Tel: ___________
- Project Manager: ___________ | Tel: ___________
```

---

**Versión**: 2.1
**Última actualización**: 22/02/2026
**Status**: Ready for Validation ✅
