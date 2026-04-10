# RESUMEN EJECUTIVO — MOTOR v2.1+
## Actualización Normativa Ley 27.802 + IBM Reforzada (Marzo 2026)

---

## 📊 **CAMBIOS NORMATIVOS CRÍTICOS (Ley 27.802)**

| Norma | Artículo | Cambio 2026 | Impacto en Motor | 
|-------|----------|------------|------------------|
| **Ley 27.802** | **Art. 23** | Presunción laboral NO opera con facturación + pago bancario | Aumenta complejidad probatoria |
| **Ley 27.802** | **Art. 30** | Principal exento solidaria si controla 5 elementos | Reduce exposición con 5 controles |
| **Ley 27.802** | **Art. 80** | Certificados válidos en 3 formas (físico, digital, seg.soc) | Mantiene penalty por defectos |
| **Derogaciones** | **Ley 24.013** | Arts. 8/9/10 DEROGADOS por Ley 27.802 | NO calcula multas (históricas) |
| **Derogaciones** | **Ley 25.323** | DEROGADA por Ley 27.802 | NO duplica indemnización |
| **Ley 27.742** | **Ley Bases** | Multas suspendidas posterior 09/07/2024 | Mantiene vigencia (pre-27.802) |
| **Ackerman 2026** | **IBM Reforzada** | IBM = MAX(RIPTE, IPC) | +20-50% en indemnizaciones ART |
| **Lois 2026** | **Daño Moral** | Ampliación a 50% (no 20%) en vía civil | Mayor exposición culpa grave |

---

## ⚖️ **MATRIZ: PRESUNCIÓN LABORAL (Art. 23 LCT)**

| Situación | Facturación | Pago Bancario | Contrato Prof | Resultado |
|-----------|:-----------:|:-------------:|:-------------:|-----------|
| Empleado clásico | ❌ | ❌ | ❌ | ✅ Presunción OPERA |
| Prestador híbrido | ✅ | ✅ | ✅ | ❌ Presunción NO opera → Requiere prueba |
| Contrato civil | ✅ | ✅ | ✅ | ❌ Excluído LCT |
| Monotributo dependiente | ✅ | ✅ | ✅ | 🔴 FRAUDE laboral (detectable) |

**Impacto**: Si cliente tiene facturación + pago bancario, complejidad probatoria +0.5 en IRIL

---

## 🛡️ **MATRIZ: RESPONSABILIDAD SOLIDARIA (Art. 30 LCT)**

| Control | Verificado | Validación | Peso |
|---------|:----------:|----------|------|
| CUIL de trabajadores | ✅ | Base de datos AFIP | 1/5 |
| Aportes seg.soc. actualizados | ✅ | AFIP + ART | 2/5 |
| Pago directo de salarios | ✅ | Cuenta bancaria del subcontratista | 3/5 |
| CBU del trabajador | ✅ | Pago bancarizado deducible | 4/5 |
| Cobertura ART endosada | ✅ | Póliza con principal como beneficiario | 5/5 |

**Regla**: Si cumple **5/5 controles** → Principal **EXENTO** solidaria (riesgo = 0)  
**Riesgo escala**: Cada control faltante eleva exposición

---

## 💰 **IMPACTO ECONÓMICO ESTIMADO**

### **PRESUNCIÓN LABORAL (Art. 23)**
```
Caso 1: Empleado sin facturación
  - Complejidad probatoria: 4.5
  - IRIL = función(probatoria) √ PRESUNCIÓN OPERA

Caso 2: Prestador con facturación + pago bancario  
  - Complejidad probatoria: 4.0 (menos favorable)
  - IRIL = función(probatoria) ✓ Requiere prueba reforzada
  - Diferencia: Caso más defensible para empleador
```

### **RESPONSABILIDAD SOLIDARIA (Art. 30)**
```
Escenario A: Principal sin ningún control
  - Riesgo solidaria: $600.000 × 1 contratista = $600.000
  - Exposición: MÁXIMA

Escenario B: Principal con 5 controles validados
  - Riesgo solidaria: $0 (EXENTO por Art. 30)
  - Exposición: CERO
  
Diferencia: Implementar 5 controles = -$600K de pasivo
```

### **IBM REFORZADA (Ackerman 2026)**
```
Accidente: CausaIPPal 65%, edad 45 años
  
Antes (IBM simple):
  - IBM: $82.000
  - Incapacidad 65%: 53 × $82K = $4.346.000

Ahora (IBM reforzada MAX(RIPTE,IPC)):
  - IBM: $98.400 (mayor)
  - Incapacidad 65%: 53 × $98.4K = $5.215.200
  
DIFERENCIA: +$869.200 (+20%)
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