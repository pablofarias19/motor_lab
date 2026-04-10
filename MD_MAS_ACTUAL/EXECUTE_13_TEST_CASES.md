# EJECUTAR 13 TEST CASES — LEY 27.802
## Testing Motor Laboral v2.1+ | Marzo 14, 2026

**Estado**: 🟡 **LISTOS PARA EJECUTAR**  
**Tiempo estimado**: 2 horas (10 min por test + validación manual)  
**Herramientas**: cURL / Postman / JavaScript console

---

## 🎯 TEST SUITE COMPLETA

### GRUPO 1: ART. 23 LCT — PRESUNCIÓN LABORAL (4 Tests)

#### TEST 1: Presunción MÁXIMA (0/3 controles)

**Escenario**: Sin facturación, sin pago bancario, sin contrato escrito

**cURL**:
```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "despido_sin_causa",
    "datos_laborales": {
      "salario": 50000,
      "antiguedad_meses": 36,
      "provincia": "CABA",
      "categoria": "Administrativo",
      "cct": "CCT 161/75",
      "cantidad_empleados": 5
    },
    "documentacion": {
      "tiene_recibos": "si",
      "tiene_contrato": "no",
      "registrado_afip": "no",
      "tiene_testigos": "si",
      "auditoria_previa": "no"
    },
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "no",
      "ya_despedido": "si",
      "urgencia": "alta",
      "cantidad_empleados": 5,
      "fecha_despido": "2026-03-10",
      "tiene_facturacion": "no",
      "tiene_pago_bancario": "no",
      "tiene_contrato_escrito": "no"
    },
    "email": "test@example.com"
  }'
```

**Validación esperada**:
```json
{
  "ley_27802": {
    "presuncion_laboral": {
      "opera": true,
      "controles_presentes": 0,
      "estado": "presuncion_fuerte"
    }
  }
}
```

---

#### TEST 2: Presunción MODERADA (1/3 controles)

**Escenario**: Con pago bancario, sin facturación, sin contrato

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "despido_sin_causa",
    "datos_laborales": {"salario": 50000, "antiguedad_meses": 36, "provincia": "CABA", "cantidad_empleados": 5},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "no", "registrado_afip": "no", "tiene_testigos": "si", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "no",
      "ya_despedido": "si",
      "urgencia": "alta",
      "cantidad_empleados": 5,
      "fecha_despido": "2026-03-10",
      "tiene_facturacion": "no",
      "tiene_pago_bancario": "si",
      "tiene_contrato_escrito": "no"
    },
    "email": ""
  }'
```

**Validación esperada**: `controles_presentes: 1` | `presuncion_opera: true`

---

#### TEST 3: Presunción DÉBIL (2/3 controles)

**Escenario**: Con facturación + pago bancario, sin contrato

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {"salario": 75000, "antiguedad_meses": 60, "provincia": "CABA", "cantidad_empleados": 20},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "no", "registrado_afip": "si", "tiene_testigos": "no", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "no",
      "urgencia": "media",
      "cantidad_empleados": 20,
      "fecha_despido": "2026-03-20",
      "tiene_facturacion": "si",
      "tiene_pago_bancario": "si",
      "tiene_contrato_escrito": "no"
    },
    "email": ""
  }'
```

**Validación esperada**: `controles_presentes: 2` | `presuncion_opera: true`

---

#### TEST 4: Presunción NO OPERA (3/3 controles)

**Escenario**: Con los 3 elementos: facturación + pago bancario + contrato

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {"salario": 100000, "antiguedad_meses": 24, "provincia": "CABA", "cantidad_empleados": 50},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "no", "auditoria_previa": "si"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "no",
      "urgencia": "baja",
      "cantidad_empleados": 50,
      "fecha_despido": "2026-02-15",
      "tiene_facturacion": "si",
      "tiene_pago_bancario": "si",
      "tiene_contrato_escrito": "si"
    },
    "email": ""
  }'
```

**Validación esperada**: `controles_presentes: 3` | `presuncion_opera: false` | `estado: presuncion_debil_o_nula`

---

### GRUPO 2: ART. 30 LCT — SOLIDARIA (3 Tests)

#### TEST 5: Exención COMPLETA (5/5 controles)

**Escenario**: Principal validó TODOS los 5 controles

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {"salario": 80000, "antiguedad_meses": 48, "provincia": "CABA", "cantidad_empleados": 30},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "si", "auditoria_previa": "si"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "no",
      "urgencia": "media",
      "cantidad_empleados": 30,
      "fecha_despido": "2026-03-15",
      "valida_cuil": "si",
      "valida_aportes": "si",
      "valida_pago_directo": "si",
      "valida_cbu": "si",
      "valida_art": "si"
    },
    "email": ""
  }'
```

**Validación esperada**: 
```json
{
  "exento": true,
  "controles_validados": 5,
  "factor_exención": 1,
  "estado": "exento_solidaria"
}
```

---

#### TEST 6: Responsabilidad COMPARTIDA (2/5 controles)

**Escenario**: Solo CUIL + aportes validados. 3 controles faltando

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {"salario": 60000, "antiguedad_meses": 36, "provincia": "Buenos Aires", "cantidad_empleados": 15},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "no", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "no",
      "ya_despedido": "no",
      "urgencia": "media",
      "cantidad_empleados": 15,
      "fecha_despido": "2026-03-10",
      "valida_cuil": "si",
      "valida_aportes": "si",
      "valida_pago_directo": "no",
      "valida_cbu": "no",
      "valida_art": "no"
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "exento": false,
  "controles_validados": 2,
  "factor_exención": 4,
  "estado": "responsable_factor_4"
}
```

---

#### TEST 7: NINGÚN Control Validado (0/5)

**Escenario**: Principal sin defensas técnicas. Responsable solidario COMPLETO

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {"salario": 45000, "antiguedad_meses": 12, "provincia": "Córdoba", "cantidad_empleados": 5},
    "documentacion": {"tiene_recibos": "no", "tiene_contrato": "no", "registrado_afip": "no", "tiene_testigos": "no", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "no",
      "fue_intimado": "no",
      "ya_despedido": "no",
      "urgencia": "baja",
      "cantidad_empleados": 5,
      "fecha_despido": "2026-01-20",
      "valida_cuil": "no",
      "valida_aportes": "no",
      "valida_pago_directo": "no",
      "valida_cbu": "no",
      "valida_art": "no"
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "exento": false,
  "controles_validados": 0,
  "factor_exención": 6,
  "responsabilidad_solidaria": true
}
```

---

### GRUPO 3: FRAUDE LABORAL (2 Tests)

#### TEST 8: Fraude CRÍTICO (4/5 indicadores)

**Escenario**: 4 patrones detectados → Nivel CRÍTICO

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "trabajo_no_registrado",
    "datos_laborales": {"salario": 35000, "antiguedad_meses": 60, "provincia": "CABA", "cantidad_empleados": 100},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "no", "registrado_afip": "no", "tiene_testigos": "no", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "si",
      "urgencia": "alta",
      "cantidad_empleados": 100,
      "fecha_despido": "2026-02-28",
      "fraude_facturacion_desproporcionada": "si",
      "fraude_intermitencia_sospechosa": "si",
      "fraude_evasion_sistematica": "si",
      "fraude_sobre_facturacion": "si",
      "fraude_estructura_opaca": "no"
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "nivel": "CRÍTICO",
  "indicadores_detectados": 4,
  "riesgo_detectado": true,
  "score": 80.0
}
```

---

#### TEST 9: Fraude BAJO (1/5 indicadores)

**Escenario**: 1 patrón aislado → No conclusivo

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "despido_sin_causa",
    "datos_laborales": {"salario": 70000, "antiguedad_meses": 24, "provincia": "CABA", "cantidad_empleados": 10},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "si", "auditoria_previa": "si"},
    "situacion": {
      "hay_intercambio": "no",
      "fue_intimado": "no",
      "ya_despedido": "si",
      "urgencia": "media",
      "cantidad_empleados": 10,
      "fecha_despido": "2026-03-12",
      "fraude_facturacion_desproporcionada": "no",
      "fraude_intermitencia_sospechosa": "si",
      "fraude_evasion_sistematica": "no",
      "fraude_sobre_facturacion": "no",
      "fraude_estructura_opaca": "no"
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "nivel": "BAJO",
  "indicadores_detectados": 1,
  "riesgo_detectado": false,
  "score": 20.0
}
```

---

### GRUPO 4: DAÑO COMPLEMENTARIO (3 Tests)

#### TEST 10: Daño por Despido Violento (Alto)

**Escenario**: Despido constructivo + violencia → Daño máximo

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "despido_sin_causa",
    "datos_laborales": {"salario": 80000, "antiguedad_meses": 120, "provincia": "CABA", "cantidad_empleados": 30},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "si", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "si",
      "urgencia": "alta",
      "cantidad_empleados": 30,
      "fecha_despido": "2026-03-01",
      "tipo_extincion": "constructivo",
      "fue_violenta": "si",
      "meses_litigio": 48
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "daño_moral": > 60000,
  "daño_patrimonial": > 300000,
  "daño_reputacional": > 15000,
  "total_daño_complementario": > 375000
}
```

---

#### TEST 11: Daño por Renuncia Coercitiva (Medio)

**Escenario**: Renuncia previa + sin violencia → Daño moderado

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "despido_sin_causa",
    "datos_laborales": {"salario": 50000, "antiguedad_meses": 36, "provincia": "Buenos Aires", "cantidad_empleados": 15},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "si", "registrado_afip": "si", "tiene_testigos": "no", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "no",
      "ya_despedido": "no",
      "urgencia": "media",
      "cantidad_empleados": 15,
      "fecha_despido": "2026-02-20",
      "tipo_extincion": "renuncia_previa",
      "fue_violenta": "no",
      "meses_litigio": 36
    },
    "email": ""
  }'
```

**Validación esperada**:
```json
{
  "daño_moral": > 5000,
  "daño_patrimonial": > 100000,
  "daño_reputacional": > 2000,
  "total_daño_complementario": > 107000
}
```

---

#### TEST 12: Sin Daño Complementario (No aplica)

**Escenario**: Conflicto por diferencias salariales → No calcula daño

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleado",
    "tipo_conflicto": "diferencias_salariales",
    "datos_laborales": {"salario": 40000, "antiguedad_meses": 24, "provincia": "CABA", "cantidad_empleados": 5},
    "documentacion": {"tiene_recibos": "si", "tiene_contrato": "no", "registrado_afip": "no", "tiene_testigos": "si", "auditoria_previa": "no"},
    "situacion": {
      "hay_intercambio": "no",
      "fue_intimado": "no",
      "ya_despedido": "no",
      "urgencia": "media",
      "cantidad_empleados": 5,
      "fecha_despido": "",
      "motivo_diferencia": "Salario no ajustado",
      "meses_adeudados": 6
    },
    "email": ""
  }'
```

**Validación esperada**: `dano_complementario: null` (no aplica para diferencias salariales)

---

### GRUPO 5: INTEGRACIÓN COMPLETA (1 Test Final)

#### TEST 13: Análisis INTEGRAL Completo

**Escenario**: Caso real con TODOS los elementos: presunción débil + solidaria + fraude + daño

```bash
curl -X POST http://localhost:8000/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_usuario": "empleador",
    "tipo_conflicto": "responsabilidad_solidaria",
    "datos_laborales": {
      "salario": 90000,
      "antiguedad_meses": 72,
      "provincia": "CABA",
      "categoria": "Gerente",
      "cct": "CCT Comercio",
      "cantidad_empleados": 50
    },
    "documentacion": {
      "tiene_recibos": "si",
      "tiene_contrato": "si",
      "registrado_afip": "si",
      "tiene_testigos": "si",
      "auditoria_previa": "si"
    },
    "situacion": {
      "hay_intercambio": "si",
      "fue_intimado": "si",
      "ya_despedido": "no",
      "urgencia": "alta",
      "cantidad_empleados": 50,
      "fecha_despido": "2026-03-15",
      "tiene_facturacion": "si",
      "tiene_pago_bancario": "si",
      "tiene_contrato_escrito": "no",
      "valida_cuil": "si",
      "valida_aportes": "si",
      "valida_pago_directo": "si",
      "valida_cbu": "no",
      "valida_art": "si",
      "fraude_facturacion_desproporcionada": "no",
      "fraude_intermitencia_sospechosa": "no",
      "fraude_evasion_sistematica": "no",
      "fraude_sobre_facturacion": "no",
      "fraude_estructura_opaca": "no",
      "tipo_extincion": "despido",
      "fue_violenta": "no",
      "meses_litigio": 42
    },
    "email": "test.integral@example.com"
  }'
```

**Validación esperada**:
- ✓ Presunción: `opera=true`, `controles=2`
- ✓ Solidaria: `exento=false`, `factor=2`
- ✓ Fraude: `nivel=NINGUNO`, `riesgo=false`
- ✓ Daño: `total > 200000`
- ✓ Sección `ley_27802` con 4 objetos completos

---

## 📋 EJECUCIÓN RÁPIDA (Postman)

1. Crear nueva colección "Motor Laboral v2.1"
2. Importar 13 requests POST anteriores
3. Variables Postman:
   ```
   {{base_url}} = http://localhost:8000
   {{endpoint}} = /api/procesar_analisis.php
   ```
4. Runner → Ejecutar 13 tests secuencial
5. Generar reporte de resultados

---

## ✅ CRITERIOS DE ÉXITO

| Test | Criterio | Status |
|------|----------|--------|
| 1-4 | Presunción 0/3/2/3 controles | |
| 5-7 | Solidaria 5/2/0 controles | |
| 8-9 | Fraude CRÍTICO/BAJO detectado | |
| 10-12 | Daño calc./calc./null | |
| 13 | Todos los campos ley_27802 presentes | |

---

**Documento**: EXECUTE_13_TEST_CASES.md  
**Fecha**: Marzo 14, 2026  
**Próximos Pasos**: 
- ✅ A. Funciones PHP
- ✅ C. Integración  
- ✅ D. Campos Wizard
- ✓ E. Tests (Este)
- 🟠 B. SQL (Último)
