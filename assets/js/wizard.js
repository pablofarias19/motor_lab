/**
 * wizard.js — Motor de Riesgo Laboral | Lógica del Formulario Wizard
 *
 * Clase principal: WizardMotorLaboral
 *
 * Gestiona el formulario de 5 pasos para recolectar todos los datos necesarios
 * para el análisis de riesgo laboral (IRIL + Escenarios).
 *
 * Pasos del wizard:
 *   1. perfil         — Tipo de usuario y tipo de conflicto
 *   2. datos_laborales — Salario, antigüedad, provincia, CCT, categoría
 *   3. documentacion  — Telegramas, recibos, contrato, registro ARCA, testigos
 *   4. situacion      — Estado actual del conflicto, urgencia, fechas
 *   5. contacto       — Email (opcional) + confirmación y envío
 *
 * Dependencias: ninguna (vanilla JS puro, ES6+).
 * PHP endpoint de destino: api/procesar_analisis.php (POST JSON)
 * Redirección en éxito:    resultado.php?uuid=XXX
 *
 * Uso:
 *   const wizard = new WizardMotorLaboral();
 *   wizard.init();
 *
 * @author  Motor Laboral — Estudio Farias Ortiz
 * @version 1.0.0
 */

'use strict';

// =============================================================================
// CLASE PRINCIPAL
// =============================================================================

class WizardMotorLaboral {

    /**
     * Constructor — inicializa las propiedades de estado.
     * No accede al DOM aquí; eso ocurre en init().
     */
    constructor() {
        /**
         * Número del paso actualmente visible (1-based, de 1 a 5).
         * @type {number}
         */
        this.pasoActual = 1;

        /**
         * Total de pasos del wizard.
         * @type {number}
         */
        this.totalPasos = 5;

        /**
         * Mapa de nombres de pasos (para accesibilidad y logs).
         * @type {Object.<number, string>}
         */
        this.nombresPasos = {
            1: 'Perfil',
            2: 'Datos Laborales',
            3: 'Documentación',
            4: 'Situación Actual',
            5: 'Contacto y Envío',
        };

        /**
         * Campos requeridos por paso.
         * Cada entrada mapea el paso a un array de { selector, mensaje }.
         * @type {Object.<number, Array<{selector: string, mensaje: string}>>}
         */
        this.camposRequeridos = {
            1: [
                { selector: '#tipo_usuario', mensaje: 'Seleccioná tu perfil (empleado o empleador).' },
                { selector: '#tipo_conflicto', mensaje: 'Seleccioná el tipo de conflicto.' },
            ],
            2: [
                { selector: '#salario', mensaje: 'Ingresá el salario mensual (debe ser mayor a 0).' },
                { selector: '#antiguedad_meses', mensaje: 'Ingresá la antigüedad en meses (0 o más).' },
                { selector: '#provincia', mensaje: 'Seleccioná la provincia.' },
                { selector: '#cantidad_empleados', mensaje: 'Ingresá la cantidad de empleados.' },
            ],
            3: [], // Paso 3: sin campos obligatorios (todo Si/No con valor por defecto)
            4: [
                { selector: '#urgencia', mensaje: 'Seleccioná el nivel de urgencia.' },
            ],
            5: [], // Paso 5: solo email opcional, se valida aparte si no está vacío
        };

        /**
         * Referencia al formulario principal.
         * @type {HTMLFormElement|null}
         */
        this.formulario = null;

        /**
         * Referencia al overlay de carga.
         * @type {HTMLElement|null}
         */
        this.loadingOverlay = null;

        /**
         * Indica si el formulario ya fue enviado (evita doble submit).
         * @type {boolean}
         */
        this.enviando = false;
    }

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    /**
     * init() — Punto de entrada principal.
     *
     * Busca el formulario en el DOM, adjunta listeners y muestra el primer paso.
     * Debe llamarse al cargar la página (DOMContentLoaded).
     */
    init() {
        // Buscar el formulario wizard en el DOM
        this.formulario = document.getElementById('form-motor-laboral');

        if (!this.formulario) {
            console.error('[WizardMotorLaboral] No se encontró #form-motor-laboral en el DOM.');
            return;
        }

        // Buscar el overlay de carga
        this.loadingOverlay = document.getElementById('motor-loading-overlay');

        // Adjuntar listener al botón "Siguiente"
        const btnSiguiente = document.getElementById('btn-siguiente');
        if (btnSiguiente) {
            btnSiguiente.addEventListener('click', () => this.siguiente());
        }

        // Adjuntar listener al botón "Anterior"
        const btnAnterior = document.getElementById('btn-anterior');
        if (btnAnterior) {
            btnAnterior.addEventListener('click', () => this.anterior());
        }

        // Adjuntar listener al botón final de envío
        const btnEnviar = document.getElementById('btn-enviar');
        if (btnEnviar) {
            btnEnviar.addEventListener('click', () => this.enviarFormulario());
        }

        // Adjuntar listener de formateo al campo salario
        const inputSalario = document.getElementById('salario');
        if (inputSalario) {
            inputSalario.addEventListener('input', (e) => this._formatearSalario(e.target));
            inputSalario.addEventListener('blur', (e) => this._formatearSalario(e.target));
        }

        // MARZO 2026: Adjuntar listener a fecha de despido para alertas post-reforma
        const inputFechaDespido = document.getElementById('fecha_despido');
        if (inputFechaDespido) {
            inputFechaDespido.addEventListener('change', (e) => {
                this._verificarAlertaDespidoPostReforma(e.target.value);
            });
        }

        // Adjuntar listeners a las tarjetas de opción Si/No (radio buttons estilizados)
        this._inicializarOpcionCards();

        // Mostrar el primer paso
        this.mostrarPaso(1);

        console.info('[WizardMotorLaboral] Wizard inicializado. Paso 1 activo.');
    }

    // =========================================================================
    // NAVEGACIÓN ENTRE PASOS
    // =========================================================================

    /**
     * mostrarPaso(n) — Muestra el paso indicado y oculta el resto.
     *
     * Actualiza la barra de progreso, los indicadores de pasos, y los botones
     * de navegación (anterior/siguiente/enviar).
     *
     * @param {number} n - Número de paso a mostrar (1-based).
     */
    mostrarPaso(n) {
        // Asegurarse de que el número sea válido
        if (n < 1 || n > this.totalPasos) {
            console.warn(`[WizardMotorLaboral] Paso inválido: ${n}`);
            return;
        }

        // ── Ocultar todos los pasos ──────────────────────────────────────────
        const todosPasos = this.formulario.querySelectorAll('.wizard-paso');
        todosPasos.forEach(paso => {
            paso.classList.add('oculto');
            paso.setAttribute('aria-hidden', 'true');
        });

        // ── Mostrar el paso solicitado ───────────────────────────────────────
        const pasoEl = this.formulario.querySelector(`#paso-${n}`);
        if (pasoEl) {
            pasoEl.style.display = '';   // eliminar inline display:none si existe
            pasoEl.classList.remove('oculto');
            pasoEl.setAttribute('aria-hidden', 'false');

            // Enfocar el primer campo del paso para accesibilidad
            const primerCampo = pasoEl.querySelector('input, select, textarea');
            if (primerCampo) {
                // Pequeño delay para que la transición CSS no interfiera
                setTimeout(() => primerCampo.focus(), 80);
            }
        }

        // ── Actualizar barra de progreso ─────────────────────────────────────
        this._actualizarBarraProgreso(n);

        // ── Actualizar botones de navegación ─────────────────────────────────
        this._actualizarBotones(n);

        // ── Actualizar indicador de texto (ej: "Paso 2 de 5") ────────────────
        const indicadorTexto = document.getElementById('paso-progreso-texto');
        if (indicadorTexto) {
            indicadorTexto.textContent = `Paso ${n} de ${this.totalPasos}: ${this.nombresPasos[n]}`;
        }

        // ── Scroll hacia el inicio del wizard (útil en móvil) ────────────────
        const wizardContainer = document.querySelector('.wizard-container');
        if (wizardContainer) {
            wizardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // ── Guardar estado ───────────────────────────────────────────────────
        this.pasoActual = n;
    }

    /**
     * siguiente() — Valida el paso actual y avanza al siguiente.
     *
     * Si la validación falla, muestra los errores y no avanza.
     */
    siguiente() {
        // Limpiar errores previos del paso actual
        this._limpiarErrores(this.pasoActual);

        // Validar el paso actual antes de avanzar
        const esValido = this.validarPaso(this.pasoActual);

        if (!esValido) {
            // Hacer scroll al primer error visible
            const primerError = this.formulario.querySelector(
                `#paso-${this.pasoActual} .form-group.tiene-error`
            );
            if (primerError) {
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        // Avanzar si no es el último paso
        if (this.pasoActual < this.totalPasos) {
            // Marcar el paso actual como completado en la barra de progreso
            this._marcarPasoCompletado(this.pasoActual);
            this.mostrarPaso(this.pasoActual + 1);
        }
    }

    /**
     * anterior() — Retrocede al paso previo sin validar.
     *
     * No limpia los datos del paso actual; solo navega hacia atrás.
     */
    anterior() {
        if (this.pasoActual > 1) {
            this.mostrarPaso(this.pasoActual - 1);
        }
    }

    // =========================================================================
    // VALIDACIÓN DE PASOS
    // =========================================================================

    /**
     * validarPaso(n) — Valida todos los campos requeridos del paso indicado.
     *
     * Muestra mensajes de error inline junto a cada campo inválido.
     * Retorna true solo si todos los campos son válidos.
     *
     * @param {number} n - Número de paso a validar (1-based).
     * @returns {boolean} true si el paso es válido, false si hay errores.
     */
    validarPaso(n) {
        let esValido = true;
        const campos = this.camposRequeridos[n] || [];

        // ── Validar campos requeridos del paso ───────────────────────────────
        campos.forEach(({ selector, mensaje }) => {
            const campo = this.formulario.querySelector(selector);
            if (!campo) return;

            const valor = campo.value.trim();
            const esVacio = !valor || valor === '' || valor === '0';

            if (esVacio) {
                this._mostrarError(campo, mensaje);
                esValido = false;
            } else {
                this._limpiarErrorCampo(campo);
            }
        });

        // ── Validaciones específicas por paso ────────────────────────────────

        if (n === 2) {
            esValido = this._validarPasoLaborales() && esValido;
        }

        if (n === 5) {
            esValido = this._validarEmail() && esValido;
        }

        return esValido;
    }

    /**
     * _validarPasoLaborales() — Validaciones adicionales para el paso 2.
     *
     * Verifica rangos numéricos del salario y la antigüedad.
     *
     * @returns {boolean}
     */
    _validarPasoLaborales() {
        let valido = true;

        // Salario: debe ser mayor a 0
        const campoSalario = this.formulario.querySelector('#salario');
        if (campoSalario) {
            // El valor puede venir formateado con puntos como separadores de miles
            const valorRaw = campoSalario.value.replace(/\./g, '').replace(',', '.');
            const salario = parseFloat(valorRaw);

            if (isNaN(salario) || salario <= 0) {
                this._mostrarError(campoSalario, 'El salario debe ser un número mayor a cero.');
                valido = false;
            } else {
                // Guardar el valor numérico limpio en un campo oculto para el submit
                this._actualizarCampoOculto('salario_raw', salario.toString());
                this._limpiarErrorCampo(campoSalario);
            }
        }

        // Antigüedad: no puede ser negativa
        const campoAntiguedad = this.formulario.querySelector('#antiguedad_meses');
        if (campoAntiguedad) {
            const antiguedad = parseInt(campoAntiguedad.value, 10);
            if (isNaN(antiguedad) || antiguedad < 0) {
                this._mostrarError(campoAntiguedad, 'La antigüedad no puede ser negativa (0 o más meses).');
                valido = false;
            } else {
                this._limpiarErrorCampo(campoAntiguedad);
            }
        }

        // Cantidad de empleados: debe ser al menos 1
        const campoCantidad = this.formulario.querySelector('#cantidad_empleados');
        if (campoCantidad) {
            const cantidad = parseInt(campoCantidad.value, 10);
            if (isNaN(cantidad) || cantidad < 1) {
                this._mostrarError(campoCantidad, 'La cantidad de empleados debe ser al menos 1.');
                valido = false;
            } else {
                this._limpiarErrorCampo(campoCantidad);
            }
        }

        return valido;
    }

    /**
     * _validarEmail() — Valida el email del paso 5 si el campo no está vacío.
     *
     * El email es opcional: si está vacío, pasa la validación.
     * Si tiene contenido, debe ser un formato válido.
     *
     * @returns {boolean}
     */
    _validarEmail() {
        const campoEmail = this.formulario.querySelector('#email');
        if (!campoEmail) return true;

        const valor = campoEmail.value.trim();

        // Si está vacío, es válido (campo opcional)
        if (!valor) {
            this._limpiarErrorCampo(campoEmail);
            return true;
        }

        // Regex de validación básica de email
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!regexEmail.test(valor)) {
            this._mostrarError(campoEmail, 'El email no tiene un formato válido (ej: nombre@dominio.com).');
            return false;
        }

        this._limpiarErrorCampo(campoEmail);
        return true;
    }

    // =========================================================================
    // ENVÍO DEL FORMULARIO
    // =========================================================================

    /**
     * enviarFormulario() — Valida el paso final, construye el JSON y hace POST.
     *
     * Flujo:
     *   1. Valida el paso 5 (email)
     *   2. Muestra el spinner de carga
     *   3. Construye el objeto JSON con todos los datos del formulario
     *   4. Hace fetch POST a api/procesar_analisis.php
     *   5. En éxito: redirige a resultado.php?uuid=XXX
     *   6. En error: oculta el spinner y muestra el mensaje de error
     */
    async enviarFormulario() {
        // Evitar doble submit
        if (this.enviando) return;

        // Validar el paso 5 antes de enviar
        this._limpiarErrores(5);
        if (!this.validarPaso(5)) return;

        // Bloquear envío y mostrar spinner
        this.enviando = true;
        this._mostrarSpinner(true);

        // Construir el objeto de datos
        const datos = this._construirPayload();

        console.info('[WizardMotorLaboral] Enviando análisis...', datos);

        try {
            const respuesta = await fetch('api/procesar_analisis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                body: JSON.stringify(datos),
            });

            // Verificar que la respuesta HTTP sea exitosa
            if (!respuesta.ok) {
                throw new Error(`Error HTTP ${respuesta.status}: ${respuesta.statusText}`);
            }

            // Parsear la respuesta JSON
            const resultado = await respuesta.json();

            if (resultado.success && resultado.data && resultado.data.uuid) {
                // Éxito: redirigir a la página de resultados
                console.info(`[WizardMotorLaboral] Análisis creado. UUID: ${resultado.data.uuid}`);
                window.location.href = `resultado.php?uuid=${resultado.data.uuid}`;
            } else {
                // El servidor respondió con success: false
                const mensaje = resultado.message || 'Error desconocido al procesar el análisis.';
                throw new Error(mensaje);
            }

        } catch (error) {
            // Error de red o de lógica — ocultar spinner y mostrar mensaje
            console.error('[WizardMotorLaboral] Error al enviar:', error);
            this._mostrarSpinner(false);
            this.enviando = false;
            this._mostrarErrorGlobal(error.message);
        }
    }

    /**
     * _construirPayload() — Recolecta todos los datos del formulario
     * y los ensambla en el objeto JSON que espera el endpoint PHP.
     *
     * Estructura exacta documentada en procesar_analisis.php:
     * {
     *   tipo_usuario,
     *   tipo_conflicto,
     *   datos_laborales: { salario, antiguedad_meses, provincia, categoria, cct, cantidad_empleados },
     *   documentacion:   { tiene_recibos, tiene_contrato, registrado_afip, tiene_testigos, auditoria_previa },
     *   situacion:       { hay_intercambio, fue_intimado, ya_despedido, urgencia, cantidad_empleados, fecha_despido, fecha_ultimo_telegrama },
     *   contacto:        { email },
     *   schema_version
     * }
     *
     * @returns {Object} Payload listo para JSON.stringify
     */
    _construirPayload() {
        const f = this.formulario;

        // Función auxiliar para leer un campo de texto
        const leer = (selector) => {
            const el = f.querySelector(selector);
            return el ? el.value.trim() : '';
        };

        // Función auxiliar para leer un radio button marcado
        const leerRadio = (name) => {
            const marcado = f.querySelector(`input[name="${name}"]:checked`);
            return marcado ? marcado.value : 'no';
        };

        // Función auxiliar sin valor por defecto 'no' (retorna null si no está marcado)
        const leerRadioRaw = (name) => {
            const marcado = f.querySelector(`input[name="${name}"]:checked`);
            return marcado ? marcado.value : null;
        };

        // ── Paso 1: Perfil ───────────────────────────────────────────────────
        const tipoUsuario = leer('#tipo_usuario');
        const tipoConflicto = leer('#tipo_conflicto');

        // ── Paso 2: Datos laborales ──────────────────────────────────────────
        // El salario puede estar formateado con puntos de miles — lo limpiamos
        const salarioTexto = leer('#salario').replace(/\./g, '').replace(',', '.');
        const salario = parseFloat(salarioTexto) || 0;

        const datosLaborales = {
            salario: salario,
            antiguedad_meses: parseInt(leer('#antiguedad_meses'), 10) || 0,
            provincia: leer('#provincia'),
            categoria: leer('#categoria'),
            cct: leer('#cct'),
            cantidad_empleados: parseInt(leer('#cantidad_empleados'), 10) || 1,
            edad: parseInt(leer('#edad'), 10) || 0,
            tipo_registro: leer('#tipo_registro') || 'registrado',
            salario_recibo: parseFloat(leer('#salario_recibo')) || 0,
            antiguedad_recibo: parseInt(leer('#antiguedad_recibo'), 10) || 0,
        };

        // ── Paso 3: Documentación (todos Si/No) ──────────────────────────────
        const documentacion = {
            tiene_recibos: leerRadio('tiene_recibos'),
            tiene_contrato: leerRadio('tiene_contrato'),
            registrado_afip: leerRadio('registrado_afip'),
            tiene_testigos: leerRadio('tiene_testigos'),
            auditoria_previa: leerRadio('auditoria_previa'),
        };

        // ── Paso 4: Situación actual ─────────────────────────────────────────
        const situacion = {
            hay_intercambio: leerRadio('hay_intercambio'),
            fue_intimado: leerRadio('fue_intimado'),
            ya_despedido: leerRadio('ya_despedido'),
            urgencia: leer('#urgencia') || 'media',
            cantidad_empleados: parseInt(leer('#cantidad_empleados_sit'), 10) || 1,
            fecha_despido: leer('#fecha_despido'),
            fecha_ultimo_telegrama: leer('#fecha_ultimo_telegrama'),
            motivo_diferencia: leer('#motivo_diferencia') || 'mala_categorizacion',
            meses_adeudados: parseInt(leer('#meses_adeudados'), 10) || 0,
            
            // NEW v2.1: RIPTE campos dinámicos
            dia_despido: parseInt(leer('#dia_despido'), 10) || 15,
            check_inconstitucionalidad: leerRadio('check_inconstitucionalidad'),
            jurisdiccion: leer('#jurisdiccion') || 'CABA',
            // Procesar salarios_historicos: frombreak/comma-separated string a array of numbers
            salarios_historicos: (() => {
                const txtAreas = leer('#salarios_historicos').trim();
                if (!txtAreas) return [];
                // Split por salto de línea o coma
                const arr = txtAreas
                    .split(/[\n,]+/)
                    .map(s => parseFloat(s.trim()))
                    .filter(n => !isNaN(n));
                return arr;
            })(),
            
            // NEW v2.1+: LEY 27.802 — Art. 23 (Presunción Laboral)
            tiene_facturacion: leerRadio('tiene_facturacion'),
            tiene_pago_bancario: leerRadio('tiene_pago_bancario'),
            tiene_contrato_escrito: leerRadio('tiene_contrato_escrito'),
            
            // NEW v2.1+: LEY 27.802 — Art. 30 (Responsabilidad Solidaria)
            cantidad_subcontratistas: parseInt(leer('#cantidad_subcontratistas'), 10) || 1,
            principal_valida_cuil: leerRadio('valida_cuil'),
            principal_verifica_aaportes: leerRadio('valida_aportes'),
            principal_paga_directo: leerRadio('valida_pago_directo'),
            principal_valida_cbu_trabajador: leerRadio('valida_cbu'),
            principal_cubre_art: leerRadio('valida_art'),
            actividad_esencial: leerRadio('actividad_esencial'),
            control_documental: leerRadio('control_documental'),
            control_operativo: leerRadio('control_operativo'),
            integracion_estructura: leerRadio('integracion_estructura'),
            contrato_formal: leerRadio('contrato_formal'),
            falta_f931_art: leerRadio('falta_f931_art'),
            
            // NEW v2.2+: Nivel de cumplimiento Auditoría MTEySS/SRT
            nivel_cumplimiento: (() => {
                const chks = ['chk_alta_sipa', 'chk_libro_art52', 'chk_recibos_cct', 'chk_art_vigente', 'chk_examenes', 'chk_epp_rgrl'];
                let noCount = 0;
                let answered = 0;
                chks.forEach(chk => {
                    const val = leerRadioRaw(chk);
                    if (val === 'no') noCount++;
                    if (val === 'si' || val === 'no') answered++;
                });
                if (answered === 0) return 'desconocido';
                if (noCount >= 3) return 'critico'; // 3 o más incumplimientos = Riesgo Crítico MTEySS/SRT
                return 'estable'; // Menos de 3 incumplimientos = Riesgo Estable
            })(),
            meses_no_registrados: parseInt(leer('#meses_no_registrados'), 10) || 0,
            meses_en_mora: parseInt(leer('#meses_en_mora'), 10) || 0,
            aplica_blanco_laboral: leerRadio('aplica_blanco_laboral'),
            probabilidad_condena: parseFloat(leer('#probabilidad_condena')) || 0.5,
            inspeccion_previa: leerRadio('inspeccion_previa'),
            chk_alta_sipa: leerRadio('chk_alta_sipa'),
            chk_libro_art52: leerRadio('chk_libro_art52'),
            chk_recibos_cct: leerRadio('chk_recibos_cct'),
            chk_art_vigente: leerRadio('chk_art_vigente'),
            chk_examenes: leerRadio('chk_examenes'),
            chk_epp_rgrl: leerRadio('chk_epp_rgrl'),

            // Accidentes / ART
            tipo_contingencia: leer('#tipo_contingencia') || 'accidente_tipico',
            fecha_siniestro: leer('#fecha_siniestro'),
            porcentaje_incapacidad: parseFloat(leer('#porcentaje_incapacidad')) || 0,
            incapacidad_tipo: leer('#incapacidad_tipo') || 'permanente_definitiva',
            tiene_art: (() => {
                const tieneArtRadio = leerRadioRaw('tiene_art');
                if (tieneArtRadio) return tieneArtRadio;
                const estadoArtEmpresa = leer('#estado_art_empresa') || 'activa_valida';
                return estadoArtEmpresa === 'inexistente' ? 'no' : 'si';
            })(),
            estado_art: leer('#estado_art_empresa') || 'activa_valida',
            culpa_grave: leerRadio('culpa_grave'),
            via_civil: leerRadio('via_civil'),
            denuncia_art: leerRadio('denuncia_art'),
            rechazo_art: leerRadio('rechazo_art'),
            comision_medica: leer('#comision_medica') || 'no_iniciada',
            dictamen_porcentaje: parseFloat(leer('#dictamen_porcentaje')) || 0,
            via_administrativa_agotada: leerRadio('via_administrativa_agotada'),
            tiene_preexistencia: leerRadio('tiene_preexistencia'),
            preexistencia_porcentaje: parseFloat(leer('#preexistencia_porcentaje')) || 0,
            licencia_activa: leerRadio('licencia_activa'),
            
            // NEW v2.1+: LEY 27.802 — Fraude Laboral (5 indicadores)
            fraude_facturacion_desproporcionada: leerRadio('fraude_facturacion_desproporcionada'),
            fraude_intermitencia_sospechosa: leerRadio('fraude_intermitencia_sospechosa'),
            fraude_evasion_sistematica: leerRadio('fraude_evasion_sistematica'),
            fraude_sobre_facturacion: leerRadio('fraude_sobre_facturacion'),
            fraude_estructura_opaca: leerRadio('fraude_estructura_opaca'),
            
            // NEW v2.1+: LEY 27.802 — Daño Complementario
            tipo_extincion: leer('#tipo_extincion') || 'despido',
            fue_violenta: leerRadio('fue_violenta'),
            meses_litigio: parseInt(leer('#meses_litigio'), 10) || 36,
        };

        // ── Paso 5: Contacto ─────────────────────────────────────────────────
        const email = leer('#email');

        // ── Armar payload completo ───────────────────────────────────────────
        return {
            tipo_usuario: tipoUsuario,
            tipo_conflicto: tipoConflicto,
            datos_laborales: datosLaborales,
            documentacion: documentacion,
            situacion: situacion,
            contacto: {
                email: email,
            },
            schema_version: '2026-04',
        };
    }

    // =========================================================================
    // ACTUALIZACIÓN DE UI
    // =========================================================================

    /**
     * _actualizarBarraProgreso(pasoActivo) — Actualiza los indicadores visuales
     * de la barra de progreso superior del wizard.
     *
     * Aplica clases CSS: .activo, .completado según el paso.
     * También actualiza el ancho de la línea de progreso animada.
     *
     * @param {number} pasoActivo - Número del paso actualmente visible.
     */
    _actualizarBarraProgreso(pasoActivo) {
        const items = document.querySelectorAll('.wizard-step-item');

        items.forEach(item => {
            const numeroPaso = parseInt(item.dataset.paso, 10);

            // Limpiar clases de estado previas
            item.classList.remove('activo', 'completado');

            if (numeroPaso === pasoActivo) {
                item.classList.add('activo');
                item.setAttribute('aria-current', 'step');
            } else if (numeroPaso < pasoActivo) {
                item.classList.add('completado');
                item.removeAttribute('aria-current');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        // Actualizar ancho de la línea de progreso animada
        const lineaProgreso = document.querySelector('.progreso-linea');
        if (lineaProgreso && this.totalPasos > 1) {
            // El porcentaje recorrido: 0% en paso 1, 100% en paso N
            const porcentaje = ((pasoActivo - 1) / (this.totalPasos - 1)) * 100;
            lineaProgreso.style.width = `${porcentaje}%`;
        }
    }

    /**
     * _marcarPasoCompletado(n) — Fuerza la clase .completado en el paso n.
     *
     * Se llama al avanzar, para asegurarse de que el indicador quede verde
     * aunque el usuario vuelva atrás y adelante.
     *
     * @param {number} n
     */
    _marcarPasoCompletado(n) {
        const item = document.querySelector(`.wizard-step-item[data-paso="${n}"]`);
        if (item) {
            item.classList.remove('activo');
            item.classList.add('completado');
        }
    }

    /**
     * _actualizarBotones(pasoActivo) — Muestra u oculta los botones de
     * navegación según el paso actual.
     *
     * - Paso 1: solo "Siguiente" (oculta "Anterior")
     * - Pasos 2-4: "Anterior" + "Siguiente"
     * - Paso 5: "Anterior" + "Analizar" (oculta "Siguiente")
     *
     * @param {number} pasoActivo
     */
    _actualizarBotones(pasoActivo) {
        const btnAnterior = document.getElementById('btn-anterior');
        const btnSiguiente = document.getElementById('btn-siguiente');
        const btnEnviar = document.getElementById('btn-enviar');

        if (btnAnterior) {
            btnAnterior.classList.toggle('oculto', pasoActivo === 1);
        }

        if (btnSiguiente) {
            btnSiguiente.classList.toggle('oculto', pasoActivo === this.totalPasos);
        }

        if (btnEnviar) {
            btnEnviar.classList.toggle('oculto', pasoActivo !== this.totalPasos);
        }
    }

    // =========================================================================
    // MANEJO DE ERRORES
    // =========================================================================

    /**
     * _mostrarError(campo, mensaje) — Muestra un mensaje de error inline
     * debajo del campo indicado.
     *
     * Agrega la clase .tiene-error al .form-group contenedor para activar los
     * estilos de error definidos en motor.css.
     *
     * @param {HTMLElement} campo   - El elemento input/select/textarea con error.
     * @param {string}      mensaje - Texto del error a mostrar al usuario.
     */
    _mostrarError(campo, mensaje) {
        const grupo = campo.closest('.form-group');
        if (!grupo) return;

        grupo.classList.add('tiene-error');

        // Buscar o crear el elemento de mensaje de error
        let errorEl = grupo.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'form-error';
            errorEl.setAttribute('role', 'alert');
            grupo.appendChild(errorEl);
        }

        errorEl.textContent = mensaje;
        errorEl.style.display = 'block';

        // Asociar el error al campo para accesibilidad (aria-describedby)
        const errorId = `error-${campo.id || Math.random().toString(36).slice(2)}`;
        errorEl.id = errorId;
        campo.setAttribute('aria-describedby', errorId);
        campo.setAttribute('aria-invalid', 'true');
    }

    /**
     * _limpiarErrorCampo(campo) — Limpia el error de un campo específico.
     *
     * @param {HTMLElement} campo
     */
    _limpiarErrorCampo(campo) {
        const grupo = campo.closest('.form-group');
        if (!grupo) return;

        grupo.classList.remove('tiene-error');

        const errorEl = grupo.querySelector('.form-error');
        if (errorEl) {
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }

        campo.removeAttribute('aria-describedby');
        campo.removeAttribute('aria-invalid');
    }

    /**
     * _limpiarErrores(n) — Limpia todos los errores del paso n.
     *
     * @param {number} n
     */
    _limpiarErrores(n) {
        const paso = this.formulario.querySelector(`#paso-${n}`);
        if (!paso) return;

        const gruposConError = paso.querySelectorAll('.form-group.tiene-error');
        gruposConError.forEach(grupo => {
            grupo.classList.remove('tiene-error');
            const errorEl = grupo.querySelector('.form-error');
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }
        });

        // Limpiar atributos aria
        const camposConError = paso.querySelectorAll('[aria-invalid="true"]');
        camposConError.forEach(campo => {
            campo.removeAttribute('aria-describedby');
            campo.removeAttribute('aria-invalid');
        });
    }

    /**
     * _mostrarErrorGlobal(mensaje) — Muestra un error prominente al usuario
     * cuando falla el envío al servidor (errores de red, servidor, etc.).
     *
     * @param {string} mensaje
     */
    _mostrarErrorGlobal(mensaje) {
        // Buscar o crear el contenedor de error global
        let contenedorError = document.getElementById('error-global-wizard');

        if (!contenedorError) {
            contenedorError = document.createElement('div');
            contenedorError.id = 'error-global-wizard';
            contenedorError.setAttribute('role', 'alert');
            contenedorError.style.cssText = `
                margin: 1rem 0;
                padding: 1rem 1.25rem;
                background-color: #fff5f5;
                border-left: 4px solid #dc3545;
                border-radius: 6px;
                color: #721c24;
                font-size: 0.9rem;
                line-height: 1.5;
            `;

            // Insertar antes del footer del wizard
            const wizardFooter = document.querySelector('.wizard-footer');
            if (wizardFooter) {
                wizardFooter.parentNode.insertBefore(contenedorError, wizardFooter);
            } else {
                this.formulario.appendChild(contenedorError);
            }
        }

        contenedorError.innerHTML = `
            <strong>No se pudo procesar el análisis.</strong><br>
            ${this._escaparHTML(mensaje)}<br>
            <small style="opacity:0.75;">Revisá tu conexión e intentá nuevamente. Si el problema persiste, contactanos.</small>
        `;
        contenedorError.style.display = 'block';

        // Scroll hacia el error
        contenedorError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // =========================================================================
    // SPINNER DE CARGA
    // =========================================================================

    /**
     * _mostrarSpinner(visible) — Muestra u oculta el overlay de carga.
     *
     * El overlay es el elemento #motor-loading-overlay que cubre la pantalla
     * con el spinner animado durante el proceso de análisis.
     *
     * @param {boolean} visible - true para mostrar, false para ocultar.
     */
    _mostrarSpinner(visible) {
        if (!this.loadingOverlay) return;

        if (visible) {
            this.loadingOverlay.classList.add('activo');

            // Bloquear el scroll del body mientras carga
            document.body.style.overflow = 'hidden';

            // Cambiar texto del botón de envío para indicar proceso
            const btnEnviar = document.getElementById('btn-enviar');
            if (btnEnviar) {
                btnEnviar.disabled = true;
                btnEnviar.innerHTML = '<span class="spinner-inline"></span> Analizando...';
            }
        } else {
            this.loadingOverlay.classList.remove('activo');
            document.body.style.overflow = '';

            // Restaurar botón de envío
            const btnEnviar = document.getElementById('btn-enviar');
            if (btnEnviar) {
                btnEnviar.disabled = false;
                btnEnviar.innerHTML = 'Generar Análisis';
            }
        }
    }

    // =========================================================================
    // FORMATEO DE INPUTS
    // =========================================================================

    /**
     * _formatearSalario(input) — Formatea el campo de salario con separadores
     * de miles mientras el usuario escribe.
     *
     * Ejemplo: el usuario escribe "150000" → se muestra "150.000"
     * El valor real sin formato se lee al construir el payload (se limpian los puntos).
     *
     * @param {HTMLInputElement} input - El campo de salario.
     */
    _formatearSalario(input) {
        // Obtener solo los dígitos y comas del valor actual
        let valor = input.value.replace(/[^0-9,]/g, '');

        // Separar parte entera y decimal (si hay coma)
        const partes = valor.split(',');
        let parteEntera = partes[0];
        const parteDecimal = partes[1] !== undefined ? ',' + partes[1].slice(0, 2) : '';

        // Agregar separadores de miles con puntos
        if (parteEntera.length > 3) {
            parteEntera = parteEntera.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Actualizar el valor del input solo si cambió (evitar loops)
        const nuevoValor = parteEntera + parteDecimal;
        if (input.value !== nuevoValor) {
            // Guardar posición del cursor para no perderla al reasignar .value
            const pos = input.selectionStart;
            input.value = nuevoValor;
            // Intentar restaurar posición (puede variar por el formateo)
            try { input.setSelectionRange(pos, pos); } catch (_) { /* ignorar */ }
        }
    }

    // =========================================================================
    // MARZO 2026: ALERTAS POST-REFORMA
    // =========================================================================

    /**
     * _verificarAlertaDespidoPostReforma() — Muestra alerta si despido es posterior a Ley Bases
     * 
     * Si fecha > 09/07/2024, muestra popup informativo sobre cambios de multas
     * 
     * @param {string} fechaString  Fecha del despido (YYYY-MM-DD)
     */
    _verificarAlertaDespidoPostReforma(fechaString) {
        if (!fechaString) return;

        try {
            const fecha = new Date(fechaString);
            const fechaLeyBases = new Date('2024-07-09');

            if (fecha > fechaLeyBases) {
                this._mostrarAlertaPopupReforma();
            }
        } catch (e) {
            console.warn('[WizardMotorLaboral] Error al parsear fecha:', e);
        }
    }

    /**
     * _mostrarAlertaPopupReforma() — Popup informativo sobre cambios por Ley Bases (Marzo 2026)
     */
    _mostrarAlertaPopupReforma() {
        // Evitar mostrar múltiples veces
        if (this.alertaReformaYaMostrada) return;
        this.alertaReformaYaMostrada = true;

        const mensaje = `
            <div style="text-align: left; font-family: Arial, sans-serif;">
                <h3 style="color: #d9534f; margin-bottom: 15px;">
                    📢 Cambios por Ley Bases (27.742) — Posterior a 09/07/2024
                </h3>
                
                <p style="margin-bottom: 10px;">
                    <strong>✓ Multas Derogadas:</strong> Ley 24.013 arts. 8/9/10 y Ley 25.323 (derogadas por Ley 27.742/27.802)
                </p>
                
                <p style="margin-bottom: 10px;">
                    <strong>⚠️ Aún Vigente:</strong> Art. 80 LCT (certificados, 3 salarios) + Art. 132 bis (sanción administrativa por falta de registro)
                </p>
                
                <p style="margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <strong>💡 Nota:</strong> El sistema calculará sin multas por defecto. Puede activar "override" para analizar escenarios alternativos.
                </p>
                
                <p style="color: #666; font-size: 12px;">
                    Referencia: Ackerman, S. (2026). "Jurisprudencia Marzo 2026"
                </p>
            </div>
        `;

        if (confirm(mensaje.replace(/(<[^>]+>)/g, ' ').trim() + '\n\n¿Entendido?')) {
            // Usuario confirmó
            console.info('[WizardMotorLaboral] Usuario confirmó alerta de reforma');
        }
    }

    // =========================================================================
    // OPCIONES CARD (Si/No)
    // =========================================================================

    /**
     * _inicializarOpcionCards() — Agrega comportamiento visual a las tarjetas
     * de opción Si/No del wizard.
     *
     * Las tarjetas .opcion-card contienen un <input type="radio"> oculto.
     * Al hacer click en la tarjeta, se marca el radio y se aplica la clase
     * .seleccionada a esa tarjeta (quitándola de las demás del mismo grupo).
     */
    _inicializarOpcionCards() {
        const opcionCards = this.formulario.querySelectorAll('.opcion-card');

        opcionCards.forEach(card => {
            card.addEventListener('click', () => {
                const input = card.querySelector('input[type="radio"]');
                if (!input) return;

                // Desmarcar todas las tarjetas del mismo grupo (mismo name)
                const grupoName = input.name;
                const todasDelGrupo = this.formulario.querySelectorAll(
                    `.opcion-card input[name="${grupoName}"]`
                );
                todasDelGrupo.forEach(radio => {
                    radio.closest('.opcion-card')?.classList.remove('seleccionada');
                });

                // Marcar el radio y la tarjeta clickeada
                input.checked = true;
                card.classList.add('seleccionada');

                // Disparar evento change para cualquier listener externo
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Soporte de teclado: Enter y Espacio activan la tarjeta
            card.setAttribute('tabindex', '0');
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });

        // Marcar las tarjetas que ya tienen el radio marcado al cargar la página
        // (útil si el browser restaura valores del formulario)
        opcionCards.forEach(card => {
            const input = card.querySelector('input[type="radio"]');
            if (input && input.checked) {
                card.classList.add('seleccionada');
            }
        });
    }

    // =========================================================================
    // UTILIDADES PRIVADAS
    // =========================================================================

    /**
     * _actualizarCampoOculto(nombre, valor) — Crea o actualiza un campo
     * hidden dentro del formulario con el nombre y valor indicados.
     *
     * Se usa para guardar valores procesados (ej: salario sin formato).
     *
     * @param {string} nombre
     * @param {string} valor
     */
    _actualizarCampoOculto(nombre, valor) {
        let campo = this.formulario.querySelector(`input[name="${nombre}"][type="hidden"]`);
        if (!campo) {
            campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = nombre;
            this.formulario.appendChild(campo);
        }
        campo.value = valor;
    }

    /**
     * _escaparHTML(texto) — Escapa caracteres especiales HTML para evitar
     * XSS al insertar texto del servidor en el DOM.
     *
     * @param {string} texto
     * @returns {string}
     */
    _escaparHTML(texto) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(texto));
        return div.innerHTML;
    }
}


// =============================================================================
// ARRANQUE AUTOMÁTICO
// =============================================================================

/**
 * Inicializar el wizard automáticamente al cargar el DOM.
 * La instancia queda disponible en window.wizardMotor para debugging.
 */
document.addEventListener('DOMContentLoaded', () => {
    window.wizardMotor = new WizardMotorLaboral();
    window.wizardMotor.init();
});
