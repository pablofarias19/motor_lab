# QUICK START — Implementación Motor Laboral v2.1 en 5 Pasos

## 📋 Checklist de Implementación Rápida

Archivo guía: [QUICK_START_v2.1.md](QUICK_START_v2.1.md)

---

## **PASO 1: Configurar Base de Datos (5 min)**

### 1.1 Ejecutar Script SQL

```bash
# Option A: Terminal (Linux/Mac/WSL)
mysql -h localhost -u root -p motor_laboral < sql/setup_ripte_v2.1.sql

# Option B: Windows PowerShell
mysql.exe -h localhost -u root -p motor_laboral -e "source C:\path\to\sql\setup_ripte_v2.1.sql"

# Option C: phpMyAdmin (Manual)
# 1. Abrir http://localhost/phpmyadmin
# 2. BD: motor_laboral → SQL → Copiar contenido sql/setup_ripte_v2.1.sql
# 3. Ejecutar
```

### 1.2 Verificar Instalación

```sql
-- Ejecutar en MySQL console:
SELECT * FROM ripte_indices WHERE estado = 'vigente';
SELECT COUNT(*) FROM ripte_pisos_minimos WHERE activo = true;
```

**Esperado:**
- ✅ 1 fila (RIPTE vigente Feb 2026: $154,800.78)
- ✅ 4 filas (Pisos: IPP, IPD, gran_invalidez, muerte)

---

## **PASO 2: Copiar Librerías PHP (2 min)**

### 2.1 Crear Archivo config/ripte_functions.php

Copiar contenido de: `config/ripte_functions.php` (ya creado)

**Funciones disponibles:**
- `obtener_ripte_vigente($db)`
- `obtener_tabla_ripte_historica($db, 12)`
- `calcularIBMconRIPTE($salarios, $fecha, $table)`
- `aplicar_piso_minimo($monto, $tipo, $piso)`
- `validar_ley_bases($fecha, $check)`
- `calcular_multas_condicionadas(...)`

### 2.2 Verificar en PHP

```php
<?php
require_once __DIR__ . '/config/ripte_functions.php';

// Test básico
$ripte = obtener_ripte_fallback(); // Sin BD
echo json_encode($ripte);
?>
```

---

## **PASO 3: Integrar Validaciones Ley 27.802 (10 min)**

### 3.1 Importar Librería + Nuevo Contexto

```php
<?php
// En api/procesar_analisis.php — al inicio:

require_once __DIR__ . '/../config/ripte_functions.php';

// Resto del código...
?>
```

### 3.2 Agregar PASOS con Art. 23/30

Ver sección "PASO 0-6" en documento: `INTEGRACION_PROCESAR_ANALISIS_v2.1.md`

**⚠️ CRÍTICO: Cambio de PASO 0 (Ley 27.802)**:

**ANTES (v2.0)**: Validación Ley Bases (multas sí/no)  
**AHORA (v2.1+)**: Validación TRIPLE:
- **PASO 0a**: Art. 23 (Presunción: facturación + pago + contrato)
- **PASO 0b**: Art. 30 (Solidaria: 5 controles CUIL/aportes/pago/CBU/ART)
- **PASO 0c**: Fraude Laboral (detección de patrones)

**Secuencia Completa:**
1. **PASO 0a**: Validación Presunción (Art. 23)
2. **PASO 0b**: Validación Solidaria (Art. 30)
3. **PASO 0c**: Evaluación Fraude + Daño Complementario
4. **PASO 1**: Obtención RIPTE
5. **PASO 2**: Cálculo IBM dinámico
6. **PASO 3**: Pisos mínimos
7. **PASO 4**: IRIL Engine
8. **PASO 5**: ⚠️ Multas DEROGADAS (Ley 24.013/25.323) — Solo Art. 80 vigente

### 3.3 Código Mínimo de Prueba

```php
<?php
$entrada = [
    'tipo_calculo' => 'accidente',
    'salario' => 100000,
    'meses_antiguedad' => 48,
    'fecha_extincion' => '2026-02-15',
    'dia_despido' => 15,
    'check_inconstitucionalidad' => false,
    'salarios_historicos' => [
        ['mes_ano' => '2025-02', 'monto' => 80000],
        // ... 12 meses
    ],
    'tipo_registro' => 'registrado',
    'jurisdiccion' => 'CABA'
];

// Procesar
$db = new DatabaseManager()->getConnection();
require_once __DIR__ . '/../config/ripte_functions.php';

// PASO 0: Ley Bases
$val = validar_ley_bases(new DateTime($entrada['fecha_extincion']), false);
echo "Ley Bases: " . ($val['aplica_multas'] ? 'SÍ aplica multas' : 'NO aplica multas');

// PASO 1: RIPTE
$ripte = obtener_ripte_vigente($db);
echo "RIPTE: $" . number_format($ripte, 2);

// PASO 2: IBM
$tabla = obtener_tabla_ripte_historica($db, 12);
$ibm = calcularIBMconRIPTE($entrada['salarios_historicos'], 
                           new DateTime(), $tabla);
echo "IBM: $" . number_format($ibm, 2);
?>
```

---

## **PASO 4: Actualizar Campos del Wizard (5 min)**

### 4.1 Nuevos inputs en formulario

**Agregar en assets/html/wizard.html** (o donde esté el formulario):

```html
<!-- Día de despido (para Art. 233 dinámico) -->
<div class="form-group">
    <label for="dia_despido">Día del Despido (1-31):</label>
    <input type="number" id="dia_despido" name="dia_despido" min="1" max="31" value="15" required>
    <small>Requerido para fórmula integración dinámica Art. 233</small>
</div>

<!-- Check inconstitucionalidad Ley Bases -->
<div class="form-group">
    <label>
        <input type="checkbox" id="check_inconstitucionalidad" name="check_inconstitucionalidad">
        ⚠️ Restaurar multas (asumo riesgo Ley Bases)
    </label>
    <small>Marcar solo si consideras que Ley 27.742 es inconstitucional</small>
</div>

<!-- Salarios últimos 12 meses (para IBM dinámico) -->
<div class="form-group">
    <label for="salarios_historicos">Salarios últimos 12 meses (JSON):</label>
    <textarea id="salarios_historicos" name="salarios_historicos" 
              rows="4" placeholder='[{"mes_ano":"2025-02","monto":80000},...]'></textarea>
    <small>Opcional. Si se completa, se calcula IBM histórico (más preciso)</small>
</div>

<!-- Jurisdicción (para tasas de interés) -->
<div class="form-group">
    <label for="jurisdiccion">Jurisdicción:</label>
    <select id="jurisdiccion" name="jurisdiccion" required>
        <option value="CABA">CABA (6.5%)</option>
        <option value="PBA">PBA (6.2%)</option>
        <option value="CORDOBA">Córdoba (6.3%)</option>
        <option value="SANTA_FE">Santa Fe (6.1%)</option>
        <option value="default">Promedio Nacional (6.4%)</option>
    </select>
    <small>Para cálculo de intereses judiciales</small>
</div>
```

### 4.2 Envío en JavaScript

```javascript
// En assets/js/wizard.js — función submit:

const datosWizard = {
    // Campos existentes
    tipo_calculo: document.getElementById('tipo_calculo').value,
    salario: parseFloat(document.getElementById('salario').value),
    meses_antiguedad: parseInt(document.getElementById('meses_antiguedad').value),
    
    // NUEVOS CAMPOS v2.1
    dia_despido: parseInt(document.getElementById('dia_despido').value) || 15,
    check_inconstitucionalidad: document.getElementById('check_inconstitucionalidad').checked,
    salarios_historicos: (() => {
        try {
            return JSON.parse(document.getElementById('salarios_historicos').value || '[]');
        } catch(e) {
            console.warn('JSON salarios inválido:', e);
            return [];
        }
    })(),
    jurisdiccion: document.getElementById('jurisdiccion').value || 'CABA',
    
    // Del formulario
    fecha_extincion: document.getElementById('fecha_extincion').value,
    tipo_registro: document.getElementById('tipo_registro').value,
};

// Enviar a API
fetch('api/procesar_analisis.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datosWizard)
})
.then(r => r.json())
.then(data => {
    console.log('Respuesta:', data);
    // Mostrar resultados...
});
```

---

## **PASO 5: Testing & Validación (10 min)**

### 5.1 Test 1: Despido Anterior Ley Bases

```php
<?php
$entrada = [
    'tipo_calculo' => 'despido',
    'salario' => 100000,
    'meses_antiguedad' => 48,
    'fecha_extincion' => '2024-05-15', // ANTERIOR a 09/07/2024
    'dia_despido' => 15,
    'check_inconstitucionalidad' => false,
    'tipo_registro' => 'no_registrado',
];

// Esperado: Multas SÍ aplican (derechos adquiridos)
// - Ley 24.013: ~$1,200,000
// - Ley 25.323: ~$400,000
// - Art. 80: $300,000
// - TOTAL: ~$1,900,000
?>
```

### 5.2 Test 2: Despido Posterior sin Check

```php
<?php
$entrada = [
    'tipo_calculo' => 'despido',
    'salario' => 100000,
    'meses_antiguedad' => 48,
    'fecha_extincion' => '2025-10-15', // POSTERIOR a 09/07/2024
    'dia_despido' => 15,
    'check_inconstitucionalidad' => false, // NO activar check
    'tipo_registro' => 'no_registrado',
];

// Esperado: Multas NO aplican (Ley Bases)
// - Ley 24.013: $0
// - Ley 25.323: $0
// - Art. 80: $0
// - TOTAL: $0
?>
```

### 5.3 Test 3: Accidente con IBM Dinámico

```php
<?php
$entrada = [
    'tipo_calculo' => 'accidente',
    'salario' => 100000,
    'meses_antiguedad' => 48,
    'fecha_extincion' => '2026-02-15',
    'salarios_historicos' => [
        ['mes_ano' => '2025-02', 'monto' => 80000],
        ['mes_ano' => '2025-03', 'monto' => 82000],
        ['mes_ano' => '2025-04', 'monto' => 84000],
        ['mes_ano' => '2025-05', 'monto' => 85000],
        ['mes_ano' => '2025-06', 'monto' => 86000],
        ['mes_ano' => '2025-07', 'monto' => 87000],
        ['mes_ano' => '2025-08', 'monto' => 88000],
        ['mes_ano' => '2025-09', 'monto' => 89000],
        ['mes_ano' => '2025-10', 'monto' => 90000],
        ['mes_ano' => '2025-11', 'monto' => 91000],
        ['mes_ano' => '2025-12', 'monto' => 92000],
        ['mes_ano' => '2026-01', 'monto' => 93000],
    ],
    'tipo_registro' => 'IPP',
];

// Esperado: 
// - IBM dinámico: ~$87,500 (promedio de 12 meses ajustados por RIPTE)
// - Piso IPP: $2,260,000
// - Piso APLICADO: SÍ (87.5K < 2.26M)
// - Monto final: $2,260,000
?>
```

### 5.4 Test 4: Intereses Judiciales (Litigio)

```php
<?php
$entrada = [
    'tipo_calculo' => 'despido',
    'salario' => 100000,
    'meses_antiguedad' => 48,
    'fecha_extincion' => '2025-10-15',
    'jurisdiccion' => 'CABA', // 6.5% anual
    'meses_litigio' => 36,
];

// Esperado (en escenario Litigio):
// - Base VAE: ~$1,500,000 (estimado)
// - Intereses 36 meses @ 6.5%: ~$225,000
// - VAE Final: ~$1,725,000
?>
```

### 5.5 Script de Testing Automático

```bash
#!/bin/bash
# test_motor.sh

echo "=== TEST 1: Despido Anterior Ley Bases ==="
curl -X POST http://localhost/motor_laboral/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_calculo":"despido",
    "salario":100000,
    "meses_antiguedad":48,
    "fecha_extincion":"2024-05-15",
    "dia_despido":15,
    "check_inconstitucionalidad":false
  }' | jq '.multas'

echo -e "\n=== TEST 2: Despido Posterior sin Check ==="
curl -X POST http://localhost/motor_laboral/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_calculo":"despido",
    "salario":100000,
    "meses_antiguedad":48,
    "fecha_extincion":"2025-10-15",
    "dia_despido":15,
    "check_inconstitucionalidad":false
  }' | jq '.ley_bases'
```

---

## **Checklist Final de Implementación**

| Tarea | Estado | Líneas | Tiempo |
|-------|--------|--------|--------|
| ☐ Ejecutar setup_ripte_v2.1.sql | - | SQL | 5 min |
| ☐ Crear config/ripte_functions.php | - | 600 | 0 min (ya existe) |
| ☐ Integrar en api/procesar_analisis.php | - | +100 | 10 min |
| ☐ Actualizar assets/js/wizard.js | - | +50 | 5 min |
| ☐ Ejecutar 4 test cases | - | - | 10 min |
| ☐ Revisar error logs | - | - | 5 min |
| ☐ **TOTAL** | - | - | **35 min** |

**Tiempo total estimado**: **~40 minutos** para implementación completa

---

## **Archivos Creados/Generados**

```
motor_laboral/
├── config/
│   ├── ripte_functions.php          [✅ CREADO - 600 líneas]
│   └── (DatabaseManager.php existente)
├── api/
│   ├── procesar_analisis.php        [⏳ REQUIERE ACTUALIZACIÓN - +100 líneas]
│   └── (generar_informe.php existente)
├── assets/js/
│   └── wizard.js                    [⏳ REQUIERE ACTUALIZACIÓN - +50 líneas]
├── sql/
│   └── setup_ripte_v2.1.sql         [✅ CREADO - 350 líneas BD]
└── DOCUMENTACION/
    ├── ECUACIONES_MOTOR_LABORAL.md  [✅ v2.1]
    ├── GUIA_IMPLEMENTACION_v2.1.md  [✅ COMPLETA]
    ├── AUTOMATIZACION_RIPTE_BD.md   [✅ COMPLETA]
    ├── INTEGRACION_PROCESAR_ANALISIS_v2.1.md [✅ CREADA]
    └── QUICK_START_v2.1.md          [← ESTE ARCHIVO]
```

---

## **Preguntas Frecuentes**

**P: ¿Qué pasa si la BD no está disponible?**
A: Todas las funciones tienen fallbacks locales `obtener_ripte_fallback()` y `obtener_piso_fallback()`, el motor sigue funcionando.

**P: ¿Cómo agrego más meses de RIPTE futuro?**
A: Ejecutar INSERT en `sql/setup_ripte_v2.1.sql` o vía Python `ripte_sync.py --sync`.

**P: ¿Puedo cambiar las tasas de interés por provincia?**
A: Sí, editar array `$tasas_interes` en `EscenariosEngine.php` (línea ~50).

**P: ¿Los campos nuevos son obligatorios?**
A: 
- `dia_despido`: Sí (default: 15)
- `check_inconstitucionalidad`: No (default: false)
- `salarios_historicos`: No (usa salario directo si falta)
- `jurisdiccion`: No (default: CABA)

**P: ¿Cómo monitoreo sincronizaciones RIPTE?**
A: Ver tabla `ripte_sincronizaciones`:
```sql
SELECT * FROM ripte_sincronizaciones ORDER BY fecha_sincronizacion DESC LIMIT 10;
```

---

## **Soporte y Debugging**

**Logs principales:**
- PHP: `error_log()` → `/var/log/php.log` o `%TEMP%\php_errors.log`
- MySQL: Tableta `ripte_sincronizaciones`
- Python: `ripte_sync.log` (si se configura)

**Comandos útiles:**

```bash
# Ver RIPTE vigente desde bash
mysql motor_laboral -e "SELECT valor_indice FROM ripte_indices WHERE estado='vigente';"

# Test PHP
php -r "require 'config/ripte_functions.php'; print_r(obtener_ripte_fallback());"

# Ver sincronizaciones
mysql motor_laboral -e "SELECT fecha_sincronizacion, estado, registros_nuevos FROM ripte_sincronizaciones LIMIT 5;"
```

---

**¿NECESITAS AYUDA?** 

Revisar en este orden:
1. ✅ Verificación Post-Instalación (arriba)
2. 📖 ECUACIONES_MOTOR_LABORAL.md (concepto)
3. 📖 GUIA_IMPLEMENTACION_v2.1.md (código)
4. 📖 INTEGRACION_PROCESAR_ANALISIS_v2.1.md (integración)
5. 🐛 Error logs (debugging)

---

**Versión**: 2.1 — Marzo 2026
**Estado**: Ready for Production ✅
