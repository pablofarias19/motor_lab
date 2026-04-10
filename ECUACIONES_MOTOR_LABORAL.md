# ECUACIONES ESTRUCTURALES DEL MOTOR DE RIESGO LABORAL
## VERSIÓN 2.1 — Actualización Ley Bases + RIPTE Dinámico
## Análisis Completo de Cálculos — Despidos, ART e Integración Normativa

**ÚLTIMAS CORRECCIONES**: Ley N° 27.742 (Ley Bases), Fallo Vizzotti, IBM Dinámico RIPTE, Daño Moral

---

## 📊 **ESTRUCTURA GENERAL**

El motor opera en **4 fases secuenciales**:

```
ENTRADA (Wizard: datos + fecha extinción/accidente)
    ↓
FASE 0: Validación de Aplicabilidad Normativa (Ley Bases)
    ↓
FASE 1: Cálculo IRIL (Índice de Riesgo Institucional Laboral)
    ↓
FASE 2: Cálculo de Exposición Económica (LCT + Multas condicionadas)
    ↓
FASE 3: Generación de 4 Escenarios Estratégicos (con VAE + Intereses)
    ↓
SALIDA: Resultado con VAE, Alertas Normativas y RIPTE Vigente
```

---

# **FASE 0: VALIDACIÓN NORMATIVA POR LEY BASES (N° 27.742)**

## **0.1 Condicional de Aplicabilidad de Multas**

La Ley Bases deroga o modifica el alcance de:
- **Ley 24.013** (Arts. 8, 9, 10, 15) — Sanciones por falta de registro
- **Ley 25.323** (Arts. 1, 2) — Duplicación indemnización por antigüedad
- **Art. 80 LCT** — Certificados de trabajo

**Regla de Aplicabilidad**:

$$M_{aplicable} = \begin{cases}
M_{original} & \text{si } fecha\_extincion \leq \text{09/07/2024} \\
0 & \text{si } fecha\_extincion > \text{09/07/2024} \text{ AND check\_inconstitucionalidad = NO} \\
M_{original} & \text{si } fecha\_extincion > \text{09/07/2024} \text{ AND check\_inconstitucionalidad = SI}
\end{cases}$$

**Interpretación**:
- **Fechas previas a 9/7/2024**: Derechos adquiridos; se mantienen multas.
- **Fechas posteriores a 9/7/2024**: Multas quedan sujetas a decisión de inconstitucionalidad (default: $0).
- **Check manual**: Usuario/profesional puede activar manualmente si considera vigencia.

---

## **0.2 Tabla de Validación Automática**

El sistema debe generar una **alerta normativa**:

```
✓ Fecha de extinción: 15/06/2024 → Aplica Ley 24.013 y 25.323 normalmente
✗ Fecha de extinción: 25/08/2024 → Multas suspendidas por Ley Bases
! Fecha de extinción: 12/11/2024 → ADVERTENCIA: Usuario debe validar inconstitucionalidad
```

---

# **FASE 1: CÁLCULO DEL IRIL**

## **1.1 Fórmula Principal**

$$IRIL = (S \times w_s) + (P \times w_p) + (V \times w_v) + (C \times w_c) + (M \times w_m)$$

Donde:
- **S** = Saturación Tribunalicia (1.0 a 5.0)
- **P** = Complejidad Probatoria (1.0 a 5.0)
- **V** = Volatilidad Normativa (1.0 a 5.0)
- **C** = Riesgo de Costas (1.0 a 5.0)
- **M** = Riesgo Multiplicador (1.0 a 5.0)

| Peso | Dimensión | w |
|------|-----------|-----|
| Saturación Tribunalicia | % | **0.20** |
| Complejidad Probatoria | % | **0.25** |
| Volatilidad Normativa | % | **0.15** |
| Riesgo de Costas | % | **0.20** |
| Riesgo Multiplicador | % | **0.20** |

**Resultado Final**: $1.0 \leq IRIL \leq 5.0$

---

## **1.2 DIMENSIÓN 1: Saturación Tribunalicia (S)**

Tabla de provincias argentinas:
| Provincia | Valor S |
|-----------|---------|
| CABA | 5.0 |
| Buenos Aires | 4.5 |
| Córdoba | 3.5 |
| Santa Fe | 3.5 |
| Mendoza | 3.0 |
| Resto | 1.5-2.5 |

**Lógica**: Mayor saturación = más tiempo en fuero laboral.

---

## **1.3 DIMENSIÓN 2: Complejidad Probatoria (P)**

**Base**: P = 5.0 (máxima complejidad)

**Descuentos por documentación disponible**:

$$P = 5.0 - d_t - d_r - d_c - d_a - d_{test} - d_{aud}$$

Donde:
- $d_t$ = Tiene telegramas: **-0.8**
- $d_r$ = Tiene recibos firmados: **-1.0**
- $d_c$ = Tiene contrato escrito: **-0.7**
- $d_a$ = Registrado en ARCA: **-0.8**
- $d_{test}$ = Tiene testigos: **-0.5**
- $d_{aud}$ = Auditoría previa (empleador): **-0.5**

**Límites**: $1.0 \leq P \leq 5.0$

---

## **1.4 DIMENSIÓN 3: Volatilidad Normativa (V)**

Tabla por tipo de conflicto:

| Tipo de Conflicto | Volatilidad (V) |
|-------------------|-----------------|
| Despido sin causa | 2.0 |
| Despido con causa | 3.5 |
| Diferencias salariales | 2.5 |
| Trabajo no registrado | 3.0 |
| **ACCIDENTE LABORAL** | **4.5** |
| Reclamo indemnizatorio | 3.0 |
| Responsabilidad solidaria | 4.0 |

---

## **1.5 DIMENSIÓN 4: Riesgo de Costas (C)**

**Base**: C = 2.5

**Ajustes adicionales**:

$$C = 2.5 + a_{int} + a_{emp} + a_{empl} + a_{urg}$$

Donde:
- $a_{int}$ = Hay intercambio telegráfico: **+0.5**
- $a_{emp}$ = Empleador fue intimado y no respondió: **+1.0**
- $a_{empl}$ = Empleado ya se consideró despedido: **+0.5**
- $a_{urg}$ = Urgencia alta: **+0.5**

**Límites**: $1.0 \leq C \leq 5.0$

---

## **1.6 DIMENSIÓN 5: Riesgo Multiplicador (M)**

Cantidad de empleados involucrados → riesgo de demandas en cascada:

$$M = \begin{cases}
1.0 & \text{si } n = 1 \\
2.0 & \text{si } 1 < n \leq 3 \\
3.0 & \text{si } 3 < n \leq 10 \\
4.0 & \text{si } 10 < n \leq 50 \\
5.0 & \text{si } n > 50
\end{cases}$$

Donde $n$ = cantidad de empleados

---

---

# **REFERENCIA RIPTE — TABLA DINÁMICA (Actualización Semestral)**

## **Estructura de Coeficientes RIPTE Vigentes**

El sistema debe consultar una tabla externa (JSON/API) actualizada por SRT en marzo y septiembre:

| Mes/Año | Índice RIPTE | Estado |
|---------|--------------|--------|
| Enero 2026 | 152.100,45 | Vigente |
| Febrero 2026 | 154.800,78 | Vigente |
| Marzo 2026 | 158.200,12 | Vigente |
| Abril 2025 | 145.230,12 | Histórico |
| Marzo 2025 | 142.100,00 | Histórico |
|  ... | ... | ... |

**NOTA**: Estos valores son ejemplos. El sistema debe conectar a la API de SRT (Superintendencia de Riesgos del Trabajo) o mantener una tabla actualizable por admin.

---

# **FASE 2: CÁLCULO DE EXPOSICIÓN ECONÓMICA (CON RIPTE Y LEY BASES)**

## **2.1 DESPIDOS (SIN CAUSA)**

### **2.1.1 Indemnización por Antigüedad (Art. 245 LCT) — VALIDACIÓN VIZZOTTI**

$$I_{antg} = S \times A_{años}$$

**Validación por Fallo Vizzotti**: La base salarial debe respetar el principio de que no puede ser inferior al 67% del salario real del trabajador, incluso cuando hay topes convencionales.

$$I_{antg}^{final} = \max(I_{antg}, S \times A_{años} \times 0.67)$$

Si existe tope convencional (CCT), se aplica así:

$$I_{antg}^{CCT} = \min(S_{CCT}, S \times A_{años})$$

Pero luego se valida:

$$I_{antg}^{final} = \max(I_{antg}^{CCT}, S \times A_{años} \times 0.67)$$

**Interpretación**: 
- Si el CCT establece un tope inferior al 67% de lo que corresponde, se debe pagar el 67%.
- Si el salario real es $200.000 y antigüedad 5 años, indemnización = $1.000.000 mínimo.

**Ejemplo con CCT restrictivo**:
- Salario real: $200.000
- Antigüedad: 5 años
- Tope CCT: $100.000
- Aplicación simple: $500.000 (por CCT)
- Validación Vizzotti: $\max(500.000, 200.000 \times 5 \times 0.67) = \max(500.000, 670.000) = \$670.000$

---

### **2.1.2 Falta de Preaviso (Art. 231 LCT)**

$$P_{aviso} = S \times m_{preaviso}$$

Donde:

$$m_{preaviso} = \begin{cases}
1 & \text{si } A_{años} < 5 \\
2 & \text{si } A_{años} \geq 5
\end{cases}$$

**Ejemplo**: Antigüedad 6 años
$$P_{aviso} = 100.000 \times 2 = 200.000$$

---

### **2.1.3 Integración de Mes de Despido (Art. 233 LCT) — CORREGIDA**

La integración debe ser dinámica según el día del mes en que ocurre la desvinculación:

$$I_{mes} = \frac{S}{30} \times (30 - d_{desp})$$

Donde:
- $S$ = Salario normal y habitual
- $d_{desp}$ = Día del mes en que se produce la desvinculación (1-30)

**Lógica**: Se indemnizan los días faltantes hasta completar el mes calendario. El Art. 233 LCT garantiza el salario íntegro del mes de despido.

**Ejemplos Prácticos**:
| Día Despido | Cálculo | Resultado |
|-------------|---------|-----------|
| 5 | (100.000/30) × (30-5) = 3.333,33 × 25 | **$83.333** |
| 15 | (100.000/30) × (30-15) = 3.333,33 × 15 | **$50.000** |
| 20 | (100.000/30) × (30-20) = 3.333,33 × 10 | **$33.333** |
| 30 | (100.000/30) × (30-30) = 3.333,33 × 0 | **$0** |

⚠️ **Nota Crítica**: Si se despide el día 15, no se pagan 15 días. Se pagan 15 días **faltantes** para completar 30 días del mes.

---

### **2.1.4 SAC Proporcional (Art. 123 LCT)**

Semestre en curso (enero-junio o julio-diciembre):

$$SAC = \frac{S}{2} \times \frac{m_{semestre}}{6}$$

Donde $m_{semestre}$ = meses transcurridos en el semestre actual

**Ejemplo**: Mes de abril
$$SAC = \frac{100.000}{2} \times \frac{4}{6} = 33.333$$

---

### **2.1.5 Vacaciones Proporcionales (Art. 150 LCT)**

Días de vacaciones según antigüedad:

$$d_{vac} = \begin{cases}
14 & \text{si } A_{años} < 5 \\
21 & \text{si } 5 \leq A_{años} < 10 \\
28 & \text{si } A_{años} \geq 10
\end{cases}$$

Vacaciones proporcionales:

$$V_{prop} = \frac{S}{25} \times d_{prop}$$

Donde $d_{prop} = d_{vac} \times \frac{m_{semestre}}{12}$

**Ejemplo**: Antigüedad 4 años, abril
$$d_{prop} = 14 \times \frac{4}{12} = 4.67 \text{ días}$$
$$V_{prop} = \frac{100.000}{25} \times 4.67 = 18.667$$

---

### **2.1.6 Multa Art. 80 LCT (Certificados)**

$$M_{80} = S \times 3$$

Siempre que no entregue certificado de desvinculación.

**Ejemplo**:
$$M_{80} = 100.000 \times 3 = 300.000$$

---

### **2.1.7 Total Base Despido (Sin Multas por Registro)**

$$T_{base} = I_{antg} + P_{aviso} + I_{mes} + SAC + V_{prop} + M_{80}$$

**Ejemplo completo (ACTUALIZADO con Ley Bases y Vizzotti)**:
```
DESPIDO SIN CAUSA — 12/10/2024 (posterior a Ley Bases 09/07/2024)

--- DATOS DE ENTRADA ---
Salario real: $100.000
Antigüedad: 4 años 2 meses (50 meses)
Provincia: CABA (Saturación = 5.0)
Día despido: 12 (octubre)
Registro: Registrado en ARCA
Documentación: Tiene telegramas, recibos, contrato
Fecha extinción: 12/10/2024 (POSTERIOR a 09/07/2024 → Ley Bases)
Check inconstitucionalidad: NO (default)

--- CÁLCULO INDEMNIZACIÓN ---

1. Indemnización por Antigüedad (Art. 245 con validación Vizzotti)
   - Años completos: 4
   - I_antg = 100.000 × 4 = 400.000
   - Validación Vizzotti: máx(400.000, 100.000 × 4 × 0.67) = máx(400.000, 268.000) = $400.000 ✓

2. Falta de Preaviso (Art. 231)
   - 4 años < 5 años → 1 mes de preaviso
   - P_aviso = 100.000 × 1 = $100.000

3. Integración Mes (Art. 233 - CORREGIDA)
   - Día despido: 12
   - I_mes = (100.000 / 30) × (30 - 12) = 3.333,33 × 18 = $60.000

4. SAC Proporcional (Art. 123)
   - Mes octubre: semestre julio-diciembre, 4 meses transcurridos
   - SAC = (100.000 / 2) × (4 / 6) = $33.333,33

5. Vacaciones Proporcionales (Art. 150)
   - Antigüedad 4 años → 14 días
   - Días proporcionados: 14 × (4 / 12) = 4,67 días
   - V_prop = (100.000 / 25) × 4,67 = $18.666,67

6. Multa Art. 80 - LEY BASES
   - Fecha posterior a 09/07/2024 y NO activó check inconstitucionalidad
   - M_80 = 0 (por defecto) ⚠️

SUBTOTAL BASE: 400.000 + 100.000 + 60.000 + 33.333 + 18.667 = $612.000

7. Multa Ley 24.013 - LEY BASES
   - Registro: completo en ARCA
   - Fecha posterior a 09/07/2024 → M_24013 = 0 ⚠️

8. Multa Ley 25.323 - LEY BASES
   - No aplica (no hay registro deficiente) → M_25323 = 0

TOTAL FINAL (Con Ley Bases): $612.000
TOTAL ALTERNATIVO (Sin Ley Bases / Inconstitucionalidad): $612.000 + $300.000 (Art.80) = $912.000
```

---

## **2.2 MULTAS POR TRABAJO NO REGISTRADO (Condicionadas por Ley Bases)**

### **2.2.1 Multa Ley 24.013 Art. 8 (Totalmente en negro) — LEY BASES**

$$M_{24013} = \begin{cases}
S \times 0.25 \times A_{meses} & \text{si } fecha\_ext \leq 09/07/2024 \\
0 & \text{si } fecha\_ext > 09/07/2024 \text{ (salvo inconstitucionalidad activa)}
\end{cases}$$

25% de todas las remuneraciones no registradas durante toda la relación.

**Ejemplo**: 
- Escenario A: Despido 15/06/2024 (anterior a Ley Bases)
  - Salario $100.000, antigüedad 24 meses, totalmente negro
  - $M_{24013} = 100.000 \times 0.25 \times 24 = 600.000$ ✅

- Escenario B: Despido 15/09/2024 (posterior a Ley Bases)
  - Mismos parámetros
  - $M_{24013} = 0$ (por defecto) ⚠️
  - Si usuario activa "Check Inconstitucionalidad": $M_{24013} = 600.000$

---

### **2.2.2 Multa Ley 25.323 (Duplica la indemnización) — LEY BASES**

Si hay registro deficiente pero no total:

$$M_{25323} = \begin{cases}
I_{antg} \times 1.0 & \text{si } fecha\_ext \leq 09/07/2024 \\
0 & \text{si } fecha\_ext > 09/07/2024 \text{ (salvo inconstitucionalidad activa)}
\end{cases}$$

(Suma de nuevo la indemnización completa como castigo)

---

### **2.2.3 Multa Art. 80 LCT (Certificados) — LEY BASES**

$$M_{80} = \begin{cases}
S \times 3 & \text{si } fecha\_ext \leq 09/07/2024 \\
0 & \text{si } fecha\_ext > 09/07/2024 \text{ (salvo inconstitucionalidad activa)}
\end{cases}$$

Siempre que no entregue certificado de desvinculación en tiempo.

---

### **2.2.4 Total con Multas Condicionadas**

$$T_{multas} = T_{base} + M_{24013}^{condicionada} + M_{25323}^{condicionada} + M_{80}^{condicionada}$$

**Algoritmo en el código PHP**:
```php
$fecha_ext = new DateTime($extincion);
$ley_bases_date = new DateTime('2024-07-09');

if ($fecha_ext <= $ley_bases_date) {
    $M_24013 = $salario * 0.25 * $meses;
    $M_25323 = $I_antg;
    $M_80 = $salario * 3;
} else {
    if ($check_inconstitucionalidad === true) {
        ($M_24013, $M_25323, $M_80) = valores_originales;
    } else {
        $M_24013 = 0;
        $M_25323 = 0;
        $M_80 = 0;
    }
}
```

---

---

# **FASE 3: ACCIDENTES LABORALES (ART) — CON IBM DINÁMICO Y RIPTE**

## **3.1 Cálculo del IBM (Ingreso Base Mensual) — MÉTODO RIPTE (Ley 27.348)**

Para un cálculo real de contingencia ART, el IBM debe ser dinámico y ajustado por los índices RIPTE:

$$IBM = \frac{\sum_{i=1}^{12} \left( Salario_i \times Coeficiente\_RIPTE_i \right)}{12}$$

Donde:
- **Salario_i** = Remuneración sujeta a aportes de cada uno de los **12 meses anteriores** al hecho
- **Coeficiente_RIPTE_i** = $\frac{RIPTE_{mes\_accidente}}{RIPTE_{mes\_salario\_i}}$

### **Paso 1: Recolectar Salarios Históricos**

Obtener los sueldos declarados de cada mes (desde mes_accidente - 12 hasta mes_accidente):

$$Salarios = [S_1, S_2, ..., S_{12}]$$

donde cada $S_i$ está sujeto a aportes y contribuciones obligatorias.

### **Paso 2: Obtener Índices RIPTE**

Tabla mensual de coeficientes RIPTE publicados por SRT:

| Mes/Año | Índice RIPTE | Fuente |
|---------|--------------|--------|
| Feb 2026 | 154.800,78 | SRT |
| Ene 2026 | 152.100,45 | SRT |
| Dic 2025 | 149.500,22 | SRT |
| ... | ... | ... |

### **Paso 3: Calcular Coeficiente para Cada Mes**

Para cada salario mensual, aplicar:

$$Coeficiente_i = \frac{RIPTE_{mes\_accidente}}{RIPTE_{mes\_i}}$$

**Ejemplo concreto**:
| Mes Salario | Salario Bruto | RIPTE Mes | Coef. (Feb26/Mes) | Salario Actualizado |
|-------------|---------------|-----------|-------------------|-------------------|
| Feb 2025 | $80.000 | 142.100,00 | 154.800,78/142.100 = 1.0897 | $80.000 × 1.0897 = $87.176 |
| Mar 2025 | $82.000 | 142.500,00 | 154.800,78/142.500 = 1.0866 | $82.000 × 1.0866 = $89.101 |
| ... | ... | ... | ... | ... |
| Ene 2026 | $95.000 | 152.100,45 | 154.800,78/152.100,45 = 1.0177 | $95.000 × 1.0177 = $96.682 |

### **Paso 4: Promediar los 12 Salarios Actualizados**

$$IBM = \frac{1}{12} \sum_{i=1}^{12} Salario\_actualizado_i$$

$$IBM = \frac{87.176 + 89.101 + ... + 96.682}{12} = \approx 93.200$$

**Resultado**: IBM = $93.200 (más preciso que el salario directo de $95.000)

---

## **3.2 Fórmula LRT (Art. 14.2.a Ley 24.557) — Con IBM Dinámico**

$$P_{ART} = 53 \times IBM \times \frac{65}{Edad} \times \frac{\% Incapacidad}{100}$$

Donde:
- **53** = Coeficiente legal fijo LRT
- **IBM** = Ingreso Base Mensual (calculado dinámicamente con RIPTE)
- **65** = Edad límite estándar para lucro cesante
- **Edad** = Edad actual del trabajador
- **% Incapacidad** = Porcentaje de incapacidad (0-100)

---

## **3.3 Ajuste por Preexistencias (Fórmula Balthazard)**

Si ya tenía una incapacidad previa:

$$\% Incap_{atribuible} = \left(1 - \frac{(1 - \% Incap_{total}/100)}{(1 - \% Preex/100)}\right) \times 100$$

Se aplica el porcentaje atribuible al accidente actual, no la preexistencia.

---

## **3.4 Pisos Mínimos RIPTE (Actualización Semestral — Ley 27.348)**

Los montos mínimos se actualizan obligatoriamente por SRT en **marzo** y **septiembre** según las resoluciones publicadas. El sistema debe extraer estos valores de tabla centralizada:

| Tipo de Incapacidad | Piso Mínimo (Vigencia Sept 2025) | Próxima Actualización | Aplicación |
|---------------------|----------------------------------|----------------------|-----------|
| **IPP** (Inc. Permanente Parcial) | $2.260.000 | Marzo 2026 | Incapacidad 1-66% |
| **IPD** (Inc. Permanente Definitiva) | $4.520.000 | Marzo 2026 | Incapacidad 67-99% |
| **Gran Invalidez** | $9.040.000 | Marzo 2026 | Incapacidad ≥ 66% + asistencia requerida |
| **Muerte** | $6.780.000 | Marzo 2026 | Fallecimiento por contingencia ART |

### **3.4.1 Fórmula de Actualización Semestral**

Los pisos se actualizan aplicando coeficiente RIPTE desde la fecha anterior:

$$Piso\_nuevo = Piso_{anterior} \times \frac{RIPTE_{nueva\_vigencia}}{RIPTE_{vigencia\_anterior}}$$

**Ejemplo**: Si piso IPP en Sept 2025 = $2.260.000 con RIPTE 142.100, y en Marzo 2026 RIPTE sube a 154.800,78:

$$Piso\_IPP_{Marzo2026} = 2.260.000 \times \frac{154.800,78}{142.100,00} = 2.467.300$$

### **3.4.2 Cálculo Final con Piso Mínimo**

$$P_{ART}^{final} = \max(P_{ART}^{calculado}, Piso\_Mínimo\_Vigente)$$

Si el cálculo por fórmula LRT da $2.146.560 pero el piso IPP es $2.260.000:

$$P_{ART}^{final} = \max(2.146.560, 2.260.000) = 2.260.000$$

⚠️ **Nota Crítica**: Se aplica siempre el piso VIGENTE al momento del accidente, no al momento de la sentencia.

---

## **3.5 Ejemplo Completo ART con IBM Dinámico**

**Datos**:
- IBM (calculado dinámicamente): $93.200
- Edad: 45 años
- Incapacidad: 35% (permanente parcial)
- Preexistencia: No
- Accidente: 15/02/2026

**Cálculo**:
$$P_{ART} = 53 \times 93.200 \times \frac{65}{45} \times \frac{35}{100}$$
$$P_{ART} = 53 \times 93.200 \times 1.444 \times 0.35$$
$$P_{ART} = 2.117.920$$

**Aplicar piso mínimo IPP ($2.260.000)**:
$$P_{ART}^{final} = \max(2.117.920, 2.260.000) = 2.260.000$$

---

## **3.6 Vía Civil Complementaria (Fórmula Méndez/Accuati) — CON DESCUENTO Y DAÑO MORAL**

Cuando NO hay cobertura ART o se opta por acción civil exclusiva:

$$P_{Civil}^{bruto} = IBM \times 13 \times \frac{\% Incap}{100} \times \frac{65}{Edad}$$

Donde 13 = meses del año con SAC

### **3.6.1 Factor de Descuento por Pago Anticipado**

Incorporar la tasa de descuento del **4% a 6% anual** para evitar enriquecimiento sin causa:

$$Tasa\_descuento = \begin{cases}
0.04 & \text{si } d_{meses} \leq 12 \\
0.05 & \text{si } 12 < d_{meses} \leq 36 \\
0.06 & \text{si } d_{meses} > 36
\end{cases}$$

$$Descuento = Tasa\_descuento \times \frac{d_{meses}}{12}$$

$$P_{Civil}^{descuento} = P_{Civil}^{bruto} \times (1 - Descuento)$$

Donde $d_{meses}$ = meses desde el accidente hasta el pago

**Ejemplo**: Accidente febrero 2026, pago en septiembre 2026 (7 meses)
$$Descuento = 0.04 \times \frac{7}{12} = 0.0233 \text{ (2,33%)}$$
$$P_{Civil} = 525.120 \times (1 - 0.0233) = 512.930$$

---

### **3.6.2 Rubro de Daño Moral/Extrapatrimonial**

Agregar automáticamente un **20% del valor de la incapacidad** como daño moral:

$$D_{moral} = P_{Civil}^{descuento} \times 0.20$$

$$P_{Civil}^{final} = P_{Civil}^{descuento} + D_{moral}$$

**Ejemplo continuado**:
$$D_{moral} = 512.930 \times 0.20 = 102.586$$
$$P_{Civil}^{final} = 512.930 + 102.586 = 615.516$$

---

## **3.7 Pesos IRIL Específicos para ART (Con Cobertura)**

Cuando tiene_art = "si", los pesos cambian:

| Dimensión | Peso Genérico | Peso ART |
|-----------|---------------|----------|
| Saturación Tribunalicia | 0.20 | **0.10** |
| Complejidad Probatoria | 0.25 | **0.30** |
| Volatilidad Normativa | 0.15 | **0.25** |
| Riesgo de Costas | 0.20 | **0.15** |
| Riesgo Multiplicador | 0.20 | 0.20 |

**Justificación**: En ART el procedimiento es administrativo (Comisión Médica), no judicial, por lo que:
- Saturación ↓ (menos tribunales)
- Probatoria ↑ (pericia médica es central)
- Volatilidad ↑ (jurisprudencia ART es inestable)
- Costas ↓ (no hay costas en CM)

---

---

# **FASE 4: ESCENARIOS ESTRATÉGICOS**

## **4.1 Métricas Comunes a Todos**

Para cada escenario se calcula:

$$VBP = B - C$$

$$VAE = \frac{VBP}{d \times r}$$

Donde:
- **VBP** = Valor Bruto Posible (Beneficio - Costo)
- **B** = Beneficio estimado
- **C** = Costo estimado
- **VAE** = Valor Ajustado Estratégico
- **d** = Duración promedio (meses)
- **r** = Riesgo institucional (IRIL ajustado)

# **FASE 4: ESCENARIOS ESTRATÉGICOS (CON INTERESES JUDICIALES)**

## **4.1 Métricas Comunes a Todos**

Para cada escenario se calcula:

$$VBP = B - C$$

$$VAE = \frac{VBP}{d \times r}$$

Donde:
- **VBP** = Valor Bruto Posible (Beneficio - Costo)
- **B** = Beneficio estimado
- **C** = Costo estimado
- **VAE** = Valor Ajustado Estratégico
- **d** = Duración promedio (meses)
- **r** = Riesgo institucional (IRIL ajustado)

---

## **4.2 ESCENARIO B: Litigio Judicial — ACTUALIZACIÓN CON INTERESES**

En el Escenario B (Litigio), el VBP debe incluir **intereses judiciales** para reflejar el crecimiento exponencial del capital durante los 36 meses de vigencia según la jurisdicción.

### **4.2.1 Tasa de Interés Según Jurisdicción**

| Jurisdicción | Tasa Anual | Fórmula |
|--------------|-----------|---------|
| **CABA** | Acta 2764 (TNA) | Tasas mensuales acumulativas |
| **PBA** | IPC + 4% anual | Indexación por inflación + pura |
| **Córdoba** | Tasa activa BCRA | Variable según período |
| **Resto** | 6% anual (tasa pura) | Fija |

### **4.2.2 Cálculo de Intereses Acumulados**

Para el Escenario B, el VBP se actualiza considerando intereses:

$$I_{judiciales} = VBP \times (1 + tasa)^{d/12}$$

Donde:
- $tasa$ = Tasa anual según jurisdicción (ej. 0.06 para 6%)
- $d$ = Duración en meses (36 para litigio)

**Ejemplo**: VBP de litigio = $1.301.400, CABA, tasa anual 6%

$$I_{judiciales} = 1.301.400 \times (1 + 0.06)^{36/12}$$
$$I_{judiciales} = 1.301.400 \times (1.06)^3$$
$$I_{judiciales} = 1.301.400 \times 1.1910$$
$$I_{judiciales} = 1.550.020$$

**VAE con Intereses**:
$$VAE_{ajustado} = \frac{1.550.020}{36 \times (3.5 \times 0.90)} = \frac{1.550.020}{113.4} = 13.660/mes$$

(Comparar con VAE sin intereses: 11.478/mes)

---

## **4.3 ESCENARIO A: Negociación Temprana

### **Beneficio estimado**:
$$B_A = T_{base} \times 0.70$$

Se recupera el 70% del total base (sin multas legales).

### **Costo estimado**:
$$C_A = H_{judicial} \times 0.30$$

Donde $H_{judicial} = T_{base} \times 0.20$ (20-25% honorarios judiciales)

$$C_A = T_{base} \times 0.20 \times 0.30 = T_{base} \times 0.06$$

### **VBP**:
$$VBP_A = B_A - C_A = (T_{base} \times 0.70) - (T_{base} \times 0.06) = T_{base} \times 0.64$$

### **VAE**:
$$VAE_A = \frac{T_{base} \times 0.64}{3 \times (IRIL \times 0.40)}$$

Donde:
- d = 3 meses (duración promedio)
- r = IRIL × 0.40 (El riesgo se reduce en negociación)

**Ejemplo** (continuando con despido de $1.002.000, IRIL = 3.5):
$$B_A = 1.002.000 \times 0.70 = 701.400$$
$$C_A = 1.002.000 \times 0.06 = 60.120$$
$$VBP_A = 641.280$$
$$VAE_A = \frac{641.280}{3 \times (3.5 \times 0.40)} = \frac{641.280}{4.2} = 152.686/mes$$

---

## **4.3 ESCENARIO B: Litigio Judicial Completo**

### **Beneficio estimado**:
$$B_B = T_{multas} \times 1.00$$

Se busca obtener el 100% del total con multas legales.

### **Costo estimado**:
$$C_B = H_{judicial} + (C_{estimadas} \times 0.50)$$

Donde:
- $H_{judicial} = T_{base} \times 0.20$
- $C_{estimadas} = T_{base} \times 0.20$ (estimación de costas)

$$C_B = (T_{base} \times 0.20) + (T_{base} \times 0.20 \times 0.50) = T_{base} \times 0.30$$

### **VAE**:
$$VAE_B = \frac{(T_{multas} - (T_{base} \times 0.30))}{36 \times (IRIL \times 0.90)}$$

Donde:
- d = 36 meses (3 años promedio en Argentina)
- r = IRIL × 0.90 (máxima fricción institucional)

**Ejemplo** (despido + multa 24.013 = $1.602.000 total):
$$B_B = 1.602.000$$
$$C_B = 1.002.000 \times 0.30 = 300.600$$
$$VBP_B = 1.301.400$$
$$VAE_B = \frac{1.301.400}{36 \times (3.5 \times 0.90)} = \frac{1.301.400}{113.4} = 11.478/mes$$

**Análisis**: Litigio tiene VBP mayor pero VAE menor por duración y fricción.

---

## **4.4 ESCENARIO C: Estrategia Mixta (SECLO/Conciliación)**

### **Beneficio estimado**:
$$B_C = T_{base} \times 0.82$$

En estrategia mixta se recupera ~82% (intermedio entre negociación y litigio).

### **Costo estimado**:
$$C_C = H_{judicial} \times 0.50 = T_{base} \times 0.20 \times 0.50 = T_{base} \times 0.10$$

### **VAE**:
$$VAE_C = \frac{(B_C - C_C)}{12 \times (IRIL \times 0.65)}$$

Donde:
- d = 12 meses (1 año promedio SECLO + conciliación)
- r = IRIL × 0.65 (fricción media)

**Ejemplo**:
$$B_C = 1.002.000 \times 0.82 = 821.640$$
$$C_C = 1.002.000 \times 0.10 = 100.200$$
$$VBP_C = 721.440$$
$$VAE_C = \frac{721.440}{12 \times (3.5 \times 0.65)} = \frac{721.440}{27.3} = 26.416/mes$$

---

## **4.5 ESCENARIO D: Reconfiguración Preventiva (Empleadores)**

### **Beneficio estimado** (ahorro evitado):
$$B_D = S \times m_{ahorro}$$

Donde $m_{ahorro}$ = meses de sueldo que se ahorraría no litigando (~36 meses)

$$B_D = 100.000 \times 36 = 3.600.000$$

### **Costo estimado** (regularización):
$$C_D = S \times 2$$

Factor: 1-2 meses de sueldo para auditoría y regularización.

$$C_D = 100.000 \times 2 = 200.000$$

### **VAE**:
$$VAE_D = \frac{(3.600.000 - 200.000)}{2 \times 1.0} = \frac{3.400.000}{2} = 1.700.000/mes$$

**Este es el escenario de mayor VAE para empleadores.**

---

---

# **CASOS ESPECIALES**

## **5.1 Diferencias Salariales (Deuda Histórica)**

### **Deuda estimada por mala categorización**:

$$D = (S \times f_{cat}) \times m_{deuda}$$

Donde:
- $f_{cat}$ = Factor según tipo (0.20 = mala categorización, 0.25 = horas extras, 1.00 = falta de pago)
- $m_{deuda}$ = Meses adeudados

**Ejemplo**: Salario $80.000, mala categorización, 12 meses
$$D = (80.000 \times 0.20) \times 12 = 192.000$$

Se suma a los cálculos de exposición como concepto adicional.

---

## **5.2 Responsabilidad Solidaria (Art. 30 LCT)**

### **Riesgo potencial por subcontratistas**:

$$R_{solidario} = S \times 6 \times n_{subcontr}$$

Donde:
- 6 = Factor de riesgo por contratista
- $n_{subcontr}$ = Cantidad de subcontratistas involucrados

**Ejemplo**: Salario $100.000, 3 subcontratistas
$$R_{solidario} = 100.000 \times 6 \times 3 = 1.800.000$$

---

---

# **TABLA COMPARATIVA FINAL DE VAE**

| Escenario | Duración | VBP | VAE | Riesgo | Recomendado |
|-----------|----------|-----|-----|--------|------------|
| **A** Negociación | 3 meses | $641.280 | $152.686 | Bajo | Empleado, predisposición |
| **B** Litigio | 36 meses | $1.301.400 | $11.478 | Alto | Principios, precedente |
| **C** Mixta | 12 meses | $721.440 | $26.416 | Medio | Balance óptimo |
| **D** Preventiva | 2 meses | $3.400.000 | $1.700.000 | Muy bajo | Empleador (preventivo) |

---

---

# **ALERTAS AUTOMÁTICAS GENERADAS**

El sistema genera alertas según:

| Concepto | Fórmula de Disparo |
|----------|------------------|
| Prescripción | Si más de 2 años desde el evento (LRT) |
| Plazo SECLO | Si CABA, menos de 90 días SECLO caducó |
| Falta de intimación | Si no se inició telegrama, urgencia sube |
| Efecto multiplicador | Si cantidad empleados > 10 |
| Insolvencia ART | Si tiene accidente documentado y no ART activo |

---

# **RESUMEN DE VARIABLES DE ENTRADA**

```
Paso 1 — Perfil:
  - Eres (empleado/empleador)
  - Tipo conflicto (despido, accidente, etc.)

Paso 2 — Datos Laborales:
  - Salario normal y habitual
  - Antigüedad (años y meses)
  - Provincia
  - Edad (si accidente)
  - Registro (registrado/negro/parcial)
  - Categoría / CCT

Paso 3 — Documentación:
  - ¿Telegramas? (Art. 274 CCCN)
  - ¿Recibos de sueldo?
  - ¿Contrato escrito?
  - ¿ARCA actualizado?
  - ¿Testigos?

Paso 4 — Situación Actual:
  - ¿Ya hay intercambio epistolar?
  - ¿Ya fue despedido/accidentado?
  - ¿Urgencia?
  - ¿Cantidad de empleados afectados?
  - (Si accidente) % incapacidad, tipo contingencia, ART

Paso 5 — Contacto:
  - Email para informe
```

---

# **APÉNDICE I: GESTIÓN DINÁMICA DEL RIPTE**

## **A.1 Estructura de Datos para RIPTE**

Para que el sistema no quede obsoleto cada mes, integra el RIPTE mediante una tabla centralizada (JSON/BD):

```json
{
  "ripte_actualizado": "2026-02-22",
  "próxima_actualización": "2026-03-15",
  "coeficientes": {
    "2026-02": 154.800,78,
    "2026-01": 152.100,45,
    "2025-12": 149.500,22,
    "2025-09": 142.100,00,
    "2025-08": 141.200,50,
    ...
  }
}
```

## **A.2 Fórmula de Coeficiente de Actualización**

Para cada mes histórico, se calcula:

$$Salario\_ajustado_i = Salario_i \times \frac{RIPTE_{actual}}{RIPTE_i}$$

### **Pseudocódigo PHP**

```php
function calcularIBM($salarios_array, $fecha_accidente, $ripte_table) {
    $ripte_accidente = $ripte_table[$fecha_accidente->format('Y-m')];
    $salarios_ajustados = [];
    
    for ($i = 0; $i < 12; $i++) {
        $mes = $fecha_accidente->sub(new DateInterval("P{$i}M"))->format('Y-m');
        $ripte_mes = $ripte_table[$mes] ?? 100.0; // fallback
        
        $salario_ajustado = $salarios_array[$i] * ($ripte_accidente / $ripte_mes);
        array_push($salarios_ajustados, $salario_ajustado);
    }
    
    $ibm = array_sum($salarios_ajustados) / 12;
    return $ibm;
}
```

## **A.3 Automatización de Actualizaciones**

1. **Fuente oficial**: SRT publica coeficientes RIPTE en:
   - 15 de marzo (para accidentes Q1)
   - 15 de septiembre (para accidentes Q3)

2. **Integración recomendada**:
   - Sincronizar vía CURL/API con servidor SRT
   - Si falla, usar último valor conocido + 3% estimado
   - Log de cambios: mantener histórico para auditoría

3. **Validación de pisos**:
   - Después de actualizar RIPTE, recalcular automáticamente todos los casos ART sin resolver
   - Generar alertas si hay diferencias > 5%

---

# **APÉNDICE II: MATRIZ DE DECISIÓN POR ESCENARIO**

## **Tabla Comparativa Final (ACTUALIZADA CON INTERESES)**

| Escenario | Duración | VBP Base | VAE Base | Con Intereses | VAE Ajustado | Recomendado |
|-----------|----------|----------|----------|---------------|--------------|------------|
| **A** Negociación | 3 meses | $641.280 | $152.686 | N/A | $152.686 | Empleado, predisposición |
| **B** Litigio | 36 meses | $1.301.400 | $11.478 | $1.550.020 | **$13.660** | Principios, precedente |
| **C** Mixta | 12 meses | $721.440 | $26.416 | ~$790.000* | ~$28.900* | Balance óptimo |
| **D** Preventiva | 2 meses | $3.400.000 | $1.700.000 | N/A | $1.700.000 | Empleador (preventivo) |

*Escenario C aplica intereses moderados (menor velocidad que B)

---

# **APÉNDICE III: ALERTAS NORMATIVAS AUTOMÁTICAS (LEY BASES)**

El sistema debe generar alertas contextuales:

```
═══════════════════════════════════════════════════════════════
ALERTA NORMATIVA: LEY BASES (Nº 27.742)
═══════════════════════════════════════════════════════════════

Fecha de Extinción: 15/10/2024
Resultado: Posterior a 09/07/2024 ✓

MULTAS AFECTADAS:
 • Ley 24.013 (Arts. 8, 9, 10, 15) ........................... $0 por defecto
 • Ley 25.323 (Arts. 1, 2 - Duplicación) .................... $0 por defecto
 • Art. 80 LCT (Certificados) ............................... $0 por defecto

OPCIÓN DE USUARIO:
 ☐ Mantener valores por defecto ($0)
 ☑ Activar "Check de Inconstitucionalidad" y restaurar multas

MONTO EN DISPUTA: $300.000 - $600.000 (según disponibilidad de rubros)

Recomendación Legal: El profesional debe validar la aplicabilidad 
según cada jurisdicción local y fallo reciente (posibles impugnaciones).
═══════════════════════════════════════════════════════════════
```

---

# **RESUMEN DE CAMBIOS v2.1 vs v2.0**

| Aspecto | v2.0 | v2.1 (NUEVA) |
|--------|------|-------------|
| **Integración Mes** | Factor fijo 15 días | Dinámica: 30 - día_despido |
| **Indemnización** | Básica Art. 245 | Con validación Vizzotti (67% mínimo) |
| **Multas** | Siempre aplicadas | Condicionadas por Ley Bases (09/07/2024) |
| **IBM** | Salario actual | Promedio 12 meses ajustados por RIPTE |
| **Pisos RIPTE** | Valores fijos | Variables, actualizables semestralmente |
| **Fórmula Méndez** | Bruto | Con descuento 4-6% anual + daño moral 20% |
| **VAE en Litigio** | Sin actualización | Con intereses judiciales según jurisdicción |
| **Control Normativo** | N/A | Fase 0: Validación de aplicabilidad normativa |

---

**Versión**: 2.1 (Febrero 2026)
**Última Actualización**: 22 de febrero 2026
**Autor**: Sistema Motor de Riesgo Laboral — Revisión Normativa + RIPTE Dinámico
**Jurisdicción**: Argentina (LCT, Ley 24.557, Ley 26.773, Ley Bases Nº 27.742)
**Próxima Revisión**: Septiembre 2026 (Actualización RIPTE semestral)
