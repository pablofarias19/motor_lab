# CHEAT SHEET — FÓRMULAS CRÍTICAS v2.1+
## Referencia Rápida para Cálculos Comunes (Actualizado Ley 27.802)

---

## **⚠️ LEY 27.802 (MARZO 2026) — NUEVAS FÓRMULAS**

### **Art. 23 LCT — Presunción Laboral**

**Presunción NO opera** cuando co-existen 3 elementos:

$$Presunción = \begin{cases}
NO\,OPERA & \text{si } Facturación + Pago\_Bancario + Contrato \\
OPERA & \text{en otro caso}
\end{cases}$$

**3 Criterios Evaluados**:
| Elemento | Validación | Efecto |
|----------|-----------|--------|
| Facturación | CUIL ≠ empleador | Debilita |
| Pago Bancario | Transferencia verificable | Debilita |
| Contrato Escrito | Original firmado | Debilita |
| **3/3 presente** | **TODOS validados** | **🔴 NO OPERA** |

---

### **Art. 30 LCT — Responsabilidad Solidaria**

**Principal EXENTO** si valida 5 controles:

$$Factor\_Exención = max(1, 6 - Controles\_Validados)$$
$$Riesgo\_Solidario = Salario \times Factor$$

**5 Controles Requeridos**:
| Control | Validación | Efecto |
|---------|-----------|--------|
| CUIL | Registrado en AFIP (11 dígitos) | -1 riesgo |
| Aportes | Aportes.gob.ar activos | -1 riesgo |
| Pago | Sin intermediarios (directo) | -1 riesgo |
| CBU | 22 dígitos + banco | -1 riesgo |
| ART | Seguro vigente | -1 riesgo |
| **5/5 presente** | **TODOS validados** | **🟢 EXENTO 100%** |

---

### **Fraude Laboral — Score**

$$Score = 2×Fact.sin.pago + 1.5×Rol.divergente + 2×No.registrado + 1.5×Brecha.horas + Rotación$$

**Clasificación**:
- **BAJO** (< 2.5): Presunción operante
- **MEDIO** (2.5-4.5): Solicitar documentación
- **ALTO** (≥ 4.5): 🔴 Denunciar (DGI/SRT/Justicia)

---

### **Derogaciones — Estado Post-Ley 27.802**

| Ley | Artículos | Status | Efecto |
|-----|----------|--------|--------|
| Ley 24.013 | Art. 8,9,10 | 🔴 DEROGADA | NO calcular (= $0) |
| Ley 25.323 | Completa | 🔴 DEROGADA | NO calcular (= $0) |
| Art. 80 LCT | Certificados | 🟢 VIGENTE | SÍ calcular ($sal × 3) |

---

## **DESPIDOS**

### **Indemnización por Antigüedad (Art. 245 + Vizzotti)**
$$I_{antg} = \max(S \times A_{años}, S \times A_{años} \times 0.67)$$

**Ejemplo**: $100.000 × 4 años = $400.000 (o $268.000 piso mínimo, el mayor)

---

### **Integración Mes (Art. 233 — CORREGIDA)**
$$I_{mes} = \frac{S}{30} \times (30 - día)$$

| Día despido | Fórmula | Resultado |
|------------|---------|-----------|
| 5 | (100K/30) × 25 | $83.333 |
| 15 | (100K/30) × 15 | $50.000 |
| 20 | (100K/30) × 10 | $33.333 |

---

### **Falta de Preaviso (Art. 231)**
$$P = S \times \begin{cases} 1 & \text{si } A < 5 \text{ años} \\ 2 & \text{si } A \geq 5 \text{ años} \end{cases}$$

| Antigüedad | Meses | Cálculo |
|-----------|-------|---------|
| 3 años | 1 | $100.000 × 1 |
| 7 años | 2 | $100.000 × 2 |

---

### **SAC Proporcional (Art. 123)**
$$SAC = \frac{S}{2} \times \frac{meses\_semestre}{6}$$

**Julio-Diciembre (4 meses transcurridos)**:
$$SAC = \frac{100.000}{2} \times \frac{4}{6} = 33.333$$

---

### **Vacaciones Proporcionales (Art. 150)**
$$V = \frac{S}{25} \times \text{días\_proporcionados}$$

| Antigüedad | Días totales | Semestre (4m) | Resultado |
|-----------|-------------|-------|-----------|
| < 5 años | 14 | 4.67 | (100K/25)×4.67 = $18.667 |
| 5-10 años | 21 | 7 | (100K/25)×7 = $28.000 |

---

## **MULTAS (Condicionadas por Ley Bases)**

### **Validación Ley Bases (09/07/2024)**
$$M = \begin{cases}
M_{original} & \text{si } fecha \leq 09/07/2024 \\
0 & \text{si } fecha > 09/07/2024 \text{ (sin check)} \\
M_{original} & \text{si } fecha > 09/07/2024 \text{ + check}
\end{cases}$$

---

### **Ley 24.013 Art. 8 (Totalmente Negro)**
$$M = S \times 0.25 \times meses$$

**Ejemplo**: $100K × 0.25 × 24 = $600.000 (sólo si fecha ≤ 09/07/2024)

---

### **Ley 25.323 (Duplica Antigüedad)**
$$M = I_{antg} \times 1.0$$

**Ejemplo**: $400.000 × 1.0 = $400.000 (sólo si fecha ≤ 09/07/2024)

---

### **Art. 80 LCT (Certificados)**
$$M_{80} = S \times 3 = 100.000 \times 3 = 300.000$$

⚠️ **Suspendida por Ley Bases si fecha > 09/07/2024**

---

## **ACCIDENTES LABORALES (ART)**

### **IBM Dinámico (12 meses + RIPTE)**

**Fórmula General**:
$$IBM = \frac{1}{12} \sum_{i=1}^{12} \left( S_i \times \frac{RIPTE_{accidente}}{RIPTE_i} \right)$$

**Paso a Paso**:
1. Recolectar salarios de 12 meses
2. Para cada mes: $Salario \times \frac{RIPTE_{actual}}{RIPTE_{mes}}$
3. Promediar los 12 valores

**Tabla RIPTE (Referencia)**:
| Mes/Año | Coeficiente |
|---------|------------|
| Feb 2026 | 154.800,78 |
| Ene 2026 | 152.100,45 |
| Dic 2025 | 149.500,22 |

---

### **Fórmula LRT (Art. 14.2.a Ley 24.557)**
$$P = 53 \times IBM \times \frac{65}{Edad} \times \frac{\% Incap}{100}$$

**Ejemplo**:
$$P = 53 \times 93.200 \times \frac{65}{45} \times \frac{35}{100}$$
$$P = 53 \times 93.200 \times 1.444 \times 0.35 = 2.117.920$$

**Aplicar piso mínimo después**:
$$P_{final} = \max(P, Piso\_Mínimo)$$

---

### **Pisos Mínimos RIPTE (Sept 2025)**

| Tipo Incapacidad | Piso Mínimo |
|-----------------|------------|
| IPP | $2.260.000 |
| IPD | $4.520.000 |
| Gran Invalidez | $9.040.000 |
| Muerte | $6.780.000 |

---

### **Ajuste Preexistencias (Balthazard)**
$$\% Inc_{atribuible} = \left(1 - \frac{1 - \% Inc_{total}/100}{1 - \% Preex/100}\right) \times 100$$

**Ejemplo**: 
- Incapacidad total: 50%
- Preexistencia: 20%
- Inc. Atribuible: $\left(1 - \frac{0.5}{0.8}\right) \times 100 = 37.5\%$

---

## **VÍA CIVIL (Sin ART)**

### **Fórmula Méndez Base**
$$P = IBM \times 13 \times \frac{\% Incap}{100} \times \frac{65}{Edad}$$

---

### **Descuento por Anticipado (4-6%)**
$$Desc = \begin{cases}
0.04 & \text{si } meses \leq 12 \\
0.05 & \text{si } 12 < meses \leq 36 \\
0.06 & \text{si } meses > 36
\end{cases}$$

$$P_{final} = P \times (1 - Desc \times \frac{meses}{12})$$

---

### **Daño Moral (+20%)**
$$D_{moral} = P_{final} \times 0.20$$

$$P_{total} = P_{final} + D_{moral}$$

**Ejemplo**: $500K × (1 - 0.0233) = $488.35K + $97.67K = $586.02K

---

## **ESCENARIOS ESTRATÉGICOS**

### **Valor Bruto Posible (VBP)**
$$VBP = Beneficio - Costo$$

| Escenario | Beneficio | Costo | VBP |
|-----------|-----------|-------|-----|
| A (Negociación) | Base × 0.70 | Honor. × 0.30 | B - C |
| B (Litigio) | (Base + Multas) × 1.00 | Honor. + Costas × 0.50 | B - C |
| C (Mixta) | Base × 0.82 | Honor. × 0.50 | B - C |
| D (Preventiva) | Ahorro × 36m | Regularización × 2m | B - C |

---

### **Valor Ajustado Estratégico (VAE)**
$$VAE = \frac{VBP}{duración \times riesgo\_institucional}$$

**Duración Promedio**:
- Escenario A: 3 meses
- Escenario B: 36 meses
- Escenario C: 12 meses
- Escenario D: 2 meses

---

### **Intereses Judiciales (Escenario B)**
$$VBP_{final} = VBP \times (1 + tasa)^{meses/12}$$

| Provincia | Tasa | Cálculo (36m) |
|-----------|------|---------------|
| CABA | 6.5% | VBP × (1.065)^3 × 1.206 |
| PBA | 6.2% | VBP × (1.062)^3 × 1.199 |
| Resto | 6.0% | VBP × (1.060)^3 = × 1.191 |

**Ejemplo**: $1.3M × 1.191 = $1.55M

---

## **MATRIZ DE DECISIÓN RÁPIDA**

| Situación | Escenario Recomendado | Por qué |
|-----------|----------------------|---------|
| Empleado, predisposición empleador | **A** | VAE alto, rápido |
| Empleado, principios importantes | **B** | $$$ mayor, precedente |
| Balance riesgo-tiempo | **C** | Mixto optimizado |
| Empleador preventivo | **D** | Máximo VAE |
| Accidente alta incapacidad | Vía ART | Piso mínimo RIPTE |
| Accidente sin ART | Vía Civil | +20% daño moral |

---

## **CONVERSIÓN RÁPIDA**

| Concepto | Fórmula Rápida |
|----------|----------------|
| 1 año laboral | 12 meses = salario ÷ 12 |
| 1 mes calendario | 30 días (para LCT) |
| SAC semestral | Salario ÷ 2 |
| Días vacaciones (base) | 14 días (< 5 años) |
| Honorarios judiciales | ~20-25% del reclamo |
| Costas estimadas | ~20% del monto base |
| Tasa descuento civil | 4-6% anual |

---

## **ALERTAS CRÍTICAS**

🔴 **CRÍTICA**: Validar Ley Bases con abogado (posterior 09/07/24)
🟠 **FUERTE**: RIPTE debe sincronizarse mensualmente
🟡 **MEDIA**: Día despido afecta integración ±$50K
🟢 **LEVE**: Preexistencias requieren documentación médica

---

**Última Actualización**: 22/02/2026
**Versión**: 2.1 (Normativa Vigente)
**Próxima Revisión**: Septiembre 2026
