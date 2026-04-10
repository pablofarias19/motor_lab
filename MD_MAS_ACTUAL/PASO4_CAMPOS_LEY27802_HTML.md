# AGREGAR 10 CAMPOS LEY 27.802 AL WIZARD
## Paso 4 — Situación Actual | índex.php

**Estado**: ✅ **JavaScript actualizado** (wizard.js)  
**Próximo**: Agregar HTML en `index.php` paso 4

---

## 📋 UBICACIÓN DE CAMPOS

**Archivo**: `index.php`  
**Paso del Wizard**: **PASO 4 — Situación Actual**  
**Ubicación HTML**: Dentro de `<div id="step-4" class="step">`

---

## 🔧 10 CAMPOS A AGREGAR

### Sección 1: ART. 23 LCT — Presunción Laboral (3 campos)

```html
<!-- ─── LEY 27.802 — ART. 23: Presunción ─── -->
<fieldset class="fieldset-ley27802">
    <legend>🔍 <strong>Art. 23 LCT (Ley 27.802)</strong> — ¿Presunción de Relación Laboral?</legend>
    <p class="fieldset-hint">La presunción NO OPERA si coexisten los 3 elementos:</p>
    
    <div class="form-group">
        <label class="radio-group-label">
            ¿Tiene facturación de servicios/productos?
        </label>
        <div class="radio-options">
            <label><input type="radio" name="tiene_facturacion" value="si"> Sí</label>
            <label><input type="radio" name="tiene_facturacion" value="no" checked> No</label>
        </div>
        <small>Comprobantes de servicios o productos emitidos</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ¿Hay pagos bancarios (no efectivo)?
        </label>
        <div class="radio-options">
            <label><input type="radio" name="tiene_pago_bancario" value="si"> Sí</label>
            <label><input type="radio" name="tiene_pago_bancario" value="no" checked> No</label>
        </div>
        <small>Transferencias, depósitos, cheques (no efectivo)</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ¿Existe contrato escrito formal?
        </label>
        <div class="radio-options">
            <label><input type="radio" name="tiene_contrato_escrito" value="si"> Sí</label>
            <label><input type="radio" name="tiene_contrato_escrito" value="no" checked> No</label>
        </div>
        <small>Acuerdo formalizado, orden de compra o documento equivalente</small>
    </div>
</fieldset>
```

---

### Sección 2: ART. 30 LCT — Responsabilidad Solidaria (5 campos)

```html
<!-- ─── LEY 27.802 — ART. 30: Solidaria ─── -->
<fieldset class="fieldset-ley27802">
    <legend>⚖️ <strong>Art. 30 LCT (Ley 27.802)</strong> — Controles Exención Solidaria</legend>
    <p class="fieldset-hint">Principal es EXENTO si valida los 5 controles:</p>
    
    <div class="form-group">
        <label class="radio-group-label">
            ✓ CUIL registrado y actualizado
        </label>
        <div class="radio-options">
            <label><input type="radio" name="valida_cuil" value="si"> Sí</label>
            <label><input type="radio" name="valida_cuil" value="no" checked> No</label>
        </div>
        <small>Verificación en AFIP (C.U.I.L. activo)</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ✓ Aportes SRT pagados regularmente
        </label>
        <div class="radio-options">
            <label><input type="radio" name="valida_aportes" value="si"> Sí</label>
            <label><input type="radio" name="valida_aportes" value="no" checked> No</label>
        </div>
        <small>Contribuciones a la Superintendencia de Riesgos del Trabajo</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ✓ Pago remuneración directo al trabajador
        </label>
        <div class="radio-options">
            <label><input type="radio" name="valida_pago_directo" value="si"> Sí</label>
            <label><input type="radio" name="valida_pago_directo" value="no" checked> No</label>
        </div>
        <small>Nómina transferida directamente (no a intermediarios)</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ✓ Clave Bancaria Única (CBU) verificada
        </label>
        <div class="radio-options">
            <label><input type="radio" name="valida_cbu" value="si"> Sí</label>
            <label><input type="radio" name="valida_cbu" value="no" checked> No</label>
        </div>
        <small>Para transferencias bancarias de salarios</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ✓ ART vigente en función
        </label>
        <div class="radio-options">
            <label><input type="radio" name="valida_art" value="si"> Sí</label>
            <label><input type="radio" name="valida_art" value="no" checked> No</label>
        </div>
        <small>Cobertura de ART actualmente en vigencia</small>
    </div>
</fieldset>
```

---

### Sección 3: FRAUDE LABORAL — Detección de Patrones (5 campos BONUS)

```html
<!-- ─── LEY 27.802 — FRAUDE LABORAL: Indicadores ─── -->
<fieldset class="fieldset-ley27802 fieldset-fraude">
    <legend>⚠️ <strong>Fraude Laboral</strong> — Indicadores de Riesgo</legend>
    <p class="fieldset-hint">Seleccionar si los siguientes patrones están presentes:</p>
    
    <div class="form-group">
        <label class="radio-group-label">
            Facturación desproporcionada vs. servicios
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fraude_facturacion_desproporcionada" value="si"> Sí</label>
            <label><input type="radio" name="fraude_facturacion_desproporcionada" value="no" checked> No</label>
        </div>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            Intermitencia sospechosa (pausa-reanudación anómala)
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fraude_intermitencia_sospechosa" value="si"> Sí</label>
            <label><input type="radio" name="fraude_intermitencia_sospechosa" value="no" checked> No</label>
        </div>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            Evasión sistemática de registros/documentación
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fraude_evasion_sistematica" value="si"> Sí</label>
            <label><input type="radio" name="fraude_evasion_sistematica" value="no" checked> No</label>
        </div>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            Sobre-facturación (monto > servicios reales)
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fraude_sobre_facturacion" value="si"> Sí</label>
            <label><input type="radio" name="fraude_sobre_facturacion" value="no" checked> No</label>
        </div>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            Estructura opaca (intermediarios múltiples, offshoring)
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fraude_estructura_opaca" value="si"> Sí</label>
            <label><input type="radio" name="fraude_estructura_opaca" value="no" checked> No</label>
        </div>
    </div>
</fieldset>
```

---

### Sección 4: DAÑO COMPLEMENTARIO — Tipo de Extinción (2 campos)

```html
<!-- ─── LEY 27.802 — DAÑO COMPLEMENTARIO: Análisis ─── -->
<fieldset class="fieldset-ley27802">
    <legend>💔 <strong>Daño Complementario</strong> (Art. 527 CCCN)</legend>
    <p class="fieldset-hint">Para evaluar daños morales, patrimoniales y reputacionales:</p>
    
    <div class="form-group">
        <label for="tipo_extincion">Tipo de terminación de la relación:</label>
        <select id="tipo_extincion" name="tipo_extincion">
            <option value="">-- Seleccionar --</option>
            <option value="despido">Despido directo</option>
            <option value="renuncia_previa">Renuncia previa (coercitiva)</option>
            <option value="constructivo">Terminación constructiva (opresiva)</option>
            <option value="suspensión">Suspensión (incertidumbre laboral)</option>
        </select>
        <small>Afecta el cálculo del daño moral y patrimonial</small>
    </div>
    
    <div class="form-group">
        <label class="radio-group-label">
            ¿Fue violenta? (discriminación, acoso, violencia)
        </label>
        <div class="radio-options">
            <label><input type="radio" name="fue_violenta" value="si"> Sí</label>
            <label><input type="radio" name="fue_violenta" value="no" checked> No</label>
        </div>
        <small>Incrementa multiplicador de daño moral hasta 50%</small>
    </div>
    
    <div class="form-group">
        <label for="meses_litigio">Duración estimada del litigio (meses):</label>
        <input type="number" id="meses_litigio" name="meses_litigio" min="12" max="120" value="36">
        <small>Default: 36 meses. Afecta cálculo de lucro cesante</small>
    </div>
</fieldset>
```

---

## 🎨 CSS RECOMENDADO

Agregar a `assets/css/motor.css`:

```css
/* Fieldsets para Ley 27.802 */
.fieldset-ley27802 {
    border: 2px solid #e8f4f8;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    background-color: #f8fafb;
}

.fieldset-ley27802 legend {
    font-size: 16px;
    font-weight: 600;
    color: #1a5276;
    padding: 0 10px;
}

.fieldset-ley27802 .fieldset-hint {
    color: #555;
    font-size: 14px;
    margin: 5px 0 15px 0;
    font-style: italic;
}

.fieldset-fraude {
    border-left: 4px solid #dc3545;
}

.fieldset-fraude legend {
    color: #dc3545;
}

.radio-options {
    display: flex;
    gap: 20px;
    margin: 10px 0;
}

.radio-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    cursor: pointer;
}

.radio-options input[type="radio"] {
    margin: 0;
    cursor: pointer;
}
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [x] Campos agregados a `wizard.js` función `_construirPayload()`
- [ ] HTML agregado en `index.php` paso 4
- [ ] CSS agregado en `assets/css/motor.css`
- [ ] Validación en frontend (opcional pero recomendado)
- [ ] Prueba en navegador (llenar formulario → verificar JSON enviado)

---

## 🧪 VALIDACIÓN

Después de agregar los campos, abrir navegador y:

1. Cargar `index.php`
2. Ir a PASO 4
3. Llenar campos nuevos
4. Ir a PASO 5
5. Click "Analizar"
6. Abrir DevTools → Network → procesar_analisis.php (POST)
7. Verificar JSON incluya todas las claves nuevas:
   - `situation.tiene_facturacion`
   - `situation.valida_cuil`
   - `situation.tipo_extincion`
   - etc.

---

## 📞 TROUBLESHOOTING

**Error**: "undefined en JavaScript"
→ Revisar ID de inputs en HTML coincide con wizard.js

**Error**: "Campos no enviándose al backend"  
→ Verificar leerRadio() en wizard.js retorna 'si'/'no', no true/false

**Error**: "Validación fallando"
→ Los campos son opcionales (defaults: 'no' / 'despido' / false)

---

**Documento**: PASO4_CAMPOS_LEY27802_HTML.md  
**Fecha**: Marzo 14, 2026  
**Próximo**: D. Integración ✅ | E. Testing | B. SQL (último)
