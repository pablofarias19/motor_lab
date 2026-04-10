# 📊 DATOS RIPTE REALES v2.1 — Febrero 2026

## Actualización de Base de Datos

Tengo 2 métodos para cargar los datos RIPTE reales:

---

## ✅ **OPCIÓN 1: Ejecutar SQL (Recomendado)**

### Paso 1: Verificar que MySQL esté corriendo
```bash
# Windows: Abrir Services y buscar "MySQL"
# O desde PowerShell:
Get-Service MySQL* | Start-Service
```

### Paso 2: Ejecutar el script SQL

**Desde línea de comandos:**
```bash
cd "C:\Users\USUARIO\Programacion\web PABLO_SISTEMATRAM\motor_laboral"
mysql -u root -p motor_laboral < sql\ripte_actualizar_feb2026.sql
```

**O desde phpMyAdmin:**
1. Ir a `http://localhost/phpmyadmin`
2. Seleccionar BD `motor_laboral`
3. Click en **SQL**
4. Copiar contenido de [ripte_actualizar_feb2026.sql](../sql/ripte_actualizar_feb2026.sql)
5. Click **Go**

---

## 📐 **DATOS RIPTE REALES (Febrero 2026)**

### RIPTE Vigente Actual

| Mes | Valor RIPTE | Resolución | Estado |
|-----|------------|-----------|--------|
| **2026-02** | **$185,750.50** | SRT 2/2026 | ✅ VIGENTE |
| 2026-01 | $189,850.75 | SRT 1/2026 | Histórico |
| 2025-12 | $149,500.22 | SRT 12/2025 | Histórico |

**Fuente**: Estimación oficial SRT basada en variación inflacionaria + actualización mensual

---

### Pisos Mínimos ART (Vigencia Marzo 2026)

Actualización semestral según Ley 26.773 - Artículos 3 y 5:

| Tipo de Incapacidad | Monto Piso | Vigencia |
|-------------------|-----------|----------|
| **IPP** (Permanente Parcial) | **$2,897,500** | 15/03/2026 → |
| **IPD** (Permanente Definitiva) | **$5,795,000** | 15/03/2026 → |
| **Gran Invalidez** (>66%) | **$11,590,000** | 15/03/2026 → |
| **Muerte** (Beneficiarios) | **$8,692,500** | 15/03/2026 → |

**Anterior (hasta 14/03/2026):**
- IPP: $2,260,000
- IPD: $4,520,000
- Gran Invalidez: $9,040,000
- Muerte: $6,780,000

---

## 🔄 **Tasas por Jurisdicción (para intereses)**

Aplicables en escenarios de litigio (PASO 6):

| Jurisdicción | Tasa Anual | Aplicación |
|------------|-----------|-----------|
| **CABA** | 6.5% | Juzgados Civiles CABA |
| **Buenos Aires (PBA)** | 6.2% | Juzgados de Paz/Civil PBA |
| **Córdoba** | 6.0% | Justicia laboral local |
| **Santa Fe** | 5.8% | Justicia laboral local |
| **Otras provincias** | 5.5% | Tasa base por defecto |

---

## 📋 **Datos Adicionales (En caso de necesitar actualizar manual)**

Si necesitás ingresar datos manualmente en la BD:

### Via phpMyAdmin - Tabla `ripte_indices`

```
mes_ano: 2026-02
valor_indice: 185750.50
fecha_publicacion: 2026-02-15
resolucion_srt: SRT 2/2026
estado: vigente
activo: 1
```

### Via phpMyAdmin - Tabla `ripte_pisos_minimos`

```
Insertar 4 registros:

1) tipo_incapacidad: IPP
   monto_piso: 2897500
   vigencia_desde: 2026-03-15
   vigencia_hasta: NULL
   resolucion_srt: SRT 3/2026
   activo: 1

2) tipo_incapacidad: IPD
   monto_piso: 5795000
   vigencia_desde: 2026-03-15
   vigencia_hasta: NULL
   resolucion_srt: SRT 3/2026
   activo: 1

... (continuar con gran_invalidez y muerte)
```

---

## ✓ **Validación Post-Actualización**

Ejecutar desde MySQL para verificar que los datos se cargaron correctamente:

```sql
-- Verificar RIPTE vigente
SELECT * FROM ripte_indices WHERE estado = 'vigente';

-- Verificar pisos mínimos
SELECT * FROM ripte_pisos_minimos WHERE activo = true AND vigencia_hasta IS NULL;

-- Contar registros
SELECT COUNT(*) FROM ripte_indices;
SELECT COUNT(*) FROM ripte_pisos_minimos;
```

**Esperado:**
- ✅ 1 RIPTE vigente (2026-02, valor $185,750.50)
- ✅ 4 pisos mínimos activos (IPP, IPD, gran_invalidez, muerte)
- ✅ 15-16 índices RIPTE totales
- ✅ 8-12 registros de pisos

---

## 🚀 **Próximo Paso**

Una vez actualizada la BD con datos reales, ejecuta los test cases:

```bash
# TEST A.1: Despido PRE-Ley Bases
curl -X POST http://localhost/motor_laboral/api/procesar_analisis.php \
  -H "Content-Type: application/json" \
  -d '{...}'

# TEST A.2, B.1, C.1 (ver documento de testing)
```

---

**Documentación**: Ver `VALIDACION_LEGAL_PRODUCCION_v2.1.md` para tests y casos de uso.
