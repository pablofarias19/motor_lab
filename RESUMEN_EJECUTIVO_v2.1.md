# RESUMEN EJECUTIVO — MOTOR v2.1
## Actualización Normativa + RIPTE Dinámico (Feb 2026)

---

## 📊 **CAMBIOS ESTRUCTURALES CRÍTICOS**

| Cambio | Impacto | Ejemplos | Fechas |
|--------|--------|----------|--------|
| **Ley Bases (27.742)** | Multas condicionadas | Art. 80, 24.013, 25.323 pasan a $0 | Posterior a 09/07/2024 |
| **Integración Mes** | Dinámico (día_despido) | Antes: $50K fijo → Ahora: $33-83K | Afecta todos despi |
| **Vizzotti** | Piso 67% salario real | Valida contra tope CCT | Jurisprudencia activa |
| **IBM + RIPTE** | Promedio 12 meses ajustado | Antes: salario actual → Ahora: ~93.2K | Accidentes ART |
| **Daño Moral Civil** | +20% de incapacidad | Nuevo rubro automático | Vía civil (sin ART) |
| **Intereses Judiciales** | +19% sobre VBP Litigio | $1.3M → $1.55M en CABA | Escenario B (36m) |

---

## 💰 **IMPACTO ECONÓMICO ESTIMADO**

### **DESPIDO (sin registro deficiente)**
```
Antes v2.0:
  - Base: $1.002.000
  - Multa Art. 80: $300.000
  - TOTAL: $1.302.000

Después v2.1 (posterior 09/07/2024):
  - Base: $612.000 (integración corregida)
  - Multa Art. 80: $0 (Ley Bases)
  - TOTAL: $612.000
  - DIFERENCIA: -53% (sin check inconstitucionalidad)
```

### **ACCIDENTE LABORAL (con ART y RIPTE)**
```
Antes:
  - IBM (salario directo): $80.000
  - P_ART: $2.146.560

Después:
  - IBM (promedio RIPTE): $93.200
  - P_ART: $2.260.000 (piso aplicado)
  - DIFERENCIA: +5.3% por mejor IBM dinámico
```

### **LITIGIO (Intereses Judiciales)**
```
Antes (VAE):
  - VBP base: $1.301.400
  - VAE: $11.478/mes

Después:
  - VBP + intereses: $1.550.020
  - VAE: $13.660/mes (+19%)
  - DIFERENCIA: Escenario B más atractivo
```

---

## 🚨 **ALERTAS Y RIESGOS**

### **CRÍTICAS**
1. **Ley Bases judicialmente vulnerable**
   - Múltiples demandas por inconstitucionalidad en proceso
   - Sistema debe permitir override manual (check)
   - Validación final responsabilidad del profesional

2. **RIPTE sin acceso a histórico completo**
   - Si cliente no tiene 12 meses de documentación
   - Fallback: usar salario actual (IBM simple)
   - Diferencia máxima estimada: ±8%

### **MODERADAS**
3. **Cambios en integración mes (Art. 233)**
   - Afecta principalmente a despidos de fin de mes
   - Diferencia máxima: $50K
   - Requiere captura de "día_despido" en wizard

4. **Pisos RIPTE actualizables semestralmente**
   - Valor puede cambiar 15 en marzo/septiembre
   - Revalidar automáticamente casos pendientes
   - Admin debe sincronizar tabla RIPTE mensualmente

---

## ✅ **CHECKLIST DE IMPLEMENTACIÓN**

- [ ] Crear tabla `ripte_coeficientes` en BD
- [ ] Implementar `validarLeyBases()` en IrilEngine
- [ ] Corregir `calcularIBMconRIPTE()` con 12 meses
- [ ] Reemplazar integración sin factor fijo
- [ ] Aplicar piso Vizzotti (67%)
- [ ] Agregar descuento civil + daño moral 20%
- [ ] Calcular intereses judiciales por provincia en Escenario B
- [ ] Agregar campo "día_despido" en wizard
- [ ] Agregar checkbox "inconstitucionalidad"
- [ ] Pasar provincia a todos escenarios
- [ ] Generar alerta normativa (Fase 0)
- [ ] Testing con 5+ casos (anterior/posterior 09/07/2024)

---

## 📋 **CAMPOS NUEVOS EN WIZARD**

| Paso | Campo | Tipo | Requerido | Nota |
|------|-------|------|-----------|------|
| 2 | dia_despido | number | Opcional (default 15) | Integración Art. 233 |
| 4 | check_inconstitucionalidad | checkbox | No | Restaurar multas after 09/07/24 |
| 4 | salarios_historicos | array (JSON) | Solo si accidente ART | 12 últimos meses |

---

## 🔄 **FLUJO DE VALIDACIÓN NORMATIVA (Fase 0)**

```
┌─────────────────────────────────────────┐
│ Entrada: fecha_extincion                │
└─────────────────────────────────────────┘
              ↓
        ¿fecha > 09/07/2024?
           /           \
         NO             SI
          ↓              ↓
    Aplica multas   Check inconstitucionalidad
     (normalmente)    (/  \)
          ↓          SI   NO
       VBP alto    Multas  $0 multas
                    altas   (default)
          ↓          ↓      ↓
       Escenarios generados con VBP condicionado
```

---

## 📈 **TABLA DE REFERENCIA RIPTE (Actualización Semestral)**

```
Próxima sincronización: 15 de Marzo 2026
Coeficientes vigentes: Febrero 2026
─────────────────────────────────────────
Mes/Año      │ Índice RIPTE  │ Cambio
─────────────┼───────────────┼─────────
Feb 2026     │ 154.800,78    │ +1.8%
Ene 2026     │ 152.100,45    │ +1.5%
Dic 2025     │ 149.500,22    │ +0.9%
Sept 2025    │ 142.100,00    │ Ref. SRT
─────────────┴───────────────┴─────────
```

---

## 🎓 **CAPACITACIÓN DEL EQUIPO**

### **Puntos Críticos a Documentar**
1. **Ley Bases es contencioso**: abogado debe validar antes de cerrar
2. **IBM dinámico es más preciso**: requiere histórico, fallback simple
3. **Intereses judiciales cambian VAE**: Escenario B es más atractivo ahora
4. **Daño moral civil es automático**: 20% sobre monto principal

### **Casos de Uso Clave**
- Despido anterior vs posterior a 09/07/24
- Accidente con ART vs sin cobertura
- Litigio con actualización de interests
- Diferencias salariales con multas condicionadas

---

## 📞 **SOPORTE Y ESCALADO**

| Problema | Contacto | Urgencia |
|----------|----------|----------|
| Duda sobre Ley Bases | Abogado especialista | CRÍTICA |
| RIPTE desactualizado | Admin del sistema | MEDIA |
| Fallo en cálculo IBM | Desarrollador (tech) | CRÍTICA |
| Diferencias escenarios | Revisor análisis | BAJA |

---

**Fecha Distribución**: 22 de febrero 2026
**Versión**: 2.1 (Release Candidate)
**Próxima Revisión**: Septiembre 2026 (RIPTE + Jurisprudencia)
**Dueño del Cambio**: Sistema Motor Laboral + Equipo Legal