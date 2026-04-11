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

        /**
         * Último conflicto sincronizado para evitar cambios redundantes en el DOM.
         * @type {string}
         */
        this.conflictoSeleccionado = '';
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
        this.liveRegion = document.getElementById('wizard-live-region');

        if (!this.liveRegion) {
            this.liveRegion = document.createElement('div');
            this.liveRegion.id = 'wizard-live-region';
            this.liveRegion.setAttribute('aria-live', 'polite');
            this.liveRegion.setAttribute('aria-atomic', 'true');
            this.liveRegion.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;';
            document.body.appendChild(this.liveRegion);
        }

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
        this._inicializarConflictoCards();
        this._sincronizarPasoPerfil();

        this.formulario.addEventListener('input', () => {
            this._sincronizarPasoPerfil();
            if (this.pasoActual === this.totalPasos) {
                this._actualizarResumenPrevio();
            }
        });
        this.formulario.addEventListener('change', () => {
            this._sincronizarPasoPerfil();
            if (this.pasoActual === this.totalPasos) {
                this._actualizarResumenPrevio();
            }
        });

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

        this._actualizarGuiaVisual(n);

        // ── Scroll hacia el inicio del wizard (útil en móvil) ────────────────
        const wizardContainer = document.querySelector('.wizard-container');
        if (wizardContainer) {
            wizardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // ── Guardar estado ───────────────────────────────────────────────────
        this.pasoActual = n;

        if (n === this.totalPasos) {
            this._actualizarResumenPrevio();
        }
    }

    /**
     * siguiente() — Valida el paso actual y avanza al siguiente.
     *
     * Si la validación falla, muestra los errores y no avanza.
     */
    siguiente() {
        this._sincronizarPasoPerfil();

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
            if (!this._campoEsVisible(campo) || campo.disabled) {
                this._limpiarErrorCampo(campo);
                return;
            }

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
        return window.WizardValidation.validateStepLaborales(this.formulario, {
            tipoUsuario: this.formulario.querySelector('#tipo_usuario')?.value || '',
            tipoConflicto: this.formulario.querySelector('#tipo_conflicto')?.value || '',
            esVisible: (campo) => this._campoEsVisible(campo),
            mostrarError: (campo, mensaje) => this._mostrarError(campo, mensaje),
            limpiarErrorCampo: (campo) => this._limpiarErrorCampo(campo),
            actualizarCampoOculto: (nombre, valor) => this._actualizarCampoOculto(nombre, valor),
        });
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
        return window.WizardValidation.validateEmail(this.formulario, {
            mostrarError: (campo, mensaje) => this._mostrarError(campo, mensaje),
            limpiarErrorCampo: (campo) => this._limpiarErrorCampo(campo),
        });
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

        if (!this._validarEnvioCompleto()) {
            return;
        }

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

            const resultado = await respuesta.json().catch(() => null);

            if (!respuesta.ok) {
                if (resultado && resultado.errors) {
                    this._aplicarErroresServidor(resultado.errors);
                }
                throw new Error(
                    (resultado && resultado.message)
                    || `Error HTTP ${respuesta.status}: ${respuesta.statusText}`
                );
            }

            if (!resultado) {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }

            if (resultado.success && resultado.data && resultado.data.uuid) {
                // Éxito: redirigir a la página de resultados
                console.info(`[WizardMotorLaboral] Análisis creado. UUID: ${resultado.data.uuid}`);
                window.location.href = `resultado.php?uuid=${resultado.data.uuid}`;
            } else {
                // El servidor respondió con success: false
                if (resultado.errors) {
                    this._aplicarErroresServidor(resultado.errors);
                }
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
        return window.WizardPayloadBuilder.build(this.formulario);
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

    _actualizarGuiaVisual(pasoActivo = this.pasoActual) {
        const icono = document.getElementById('wizard-guide-icon');
        const eyebrow = document.getElementById('wizard-guide-eyebrow');
        const titulo = document.getElementById('wizard-guide-title');
        const descripcion = document.getElementById('wizard-guide-description');
        const puntos = document.getElementById('wizard-guide-points');

        if (!icono || !eyebrow || !titulo || !descripcion || !puntos) {
            return;
        }

        const perfil = this.formulario?.querySelector('#tipo_usuario')?.value || '';
        const conflicto = this.formulario?.querySelector('#tipo_conflicto')?.value || '';
        const esEmpleador = perfil === 'empleador';
        const esAccidente = conflicto === 'accidente_laboral';
        const esPrevencion = ['responsabilidad_solidaria', 'auditoria_preventiva', 'riesgo_inspeccion'].includes(conflicto);

        const guias = {
            1: {
                icon: 'bi-compass',
                eyebrowText: 'Inicio del análisis',
                title: 'Elegí la ruta correcta antes de cargar datos',
                description: esEmpleador
                    ? 'Marcá si el análisis es por contingencia activa o por prevención para mostrar solo los caminos útiles para la empresa.'
                    : 'Primero definimos perfil y motivo principal para evitar preguntas innecesarias y bajar la carga del formulario.',
                points: [
                    ['bi-person-check', 'Perfil', esEmpleador ? 'Empresa o empleador que necesita medir exposición.' : 'Trabajador o empleado con conflicto activo.'],
                    ['bi-signpost-split', 'Motivo', esPrevencion ? 'Auditoría, inspección o tercerización crítica.' : 'Despido, diferencias, accidente u otra contingencia.'],
                    ['bi-filter-circle', 'Filtro', 'Desde acá el wizard adapta textos, campos y prioridades.'],
                ],
            },
            2: {
                icon: 'bi-briefcase',
                eyebrowText: 'Base económica',
                title: esEmpleador ? 'Cargá la base del caso o de la empresa' : 'Cargá la base laboral del reclamo',
                description: esAccidente
                    ? 'Este tramo ordena salario, antigüedad y datos del siniestro para medir cobertura ART, incapacidad y riesgo civil.'
                    : esEmpleador && esPrevencion
                        ? 'Tomamos referencias del establecimiento o del sector involucrado para estimar exposición sin pedir información de más.'
                        : 'Con estos datos el motor estima indemnización, multas, intereses y encuadre inicial del conflicto.',
                points: [
                    ['bi-cash-stack', 'Monto base', esEmpleador ? 'Usá salario involucrado o referencia salarial del sector.' : 'Ingresá la mejor remuneración bruta para calcular montos.'],
                    ['bi-calendar-range', 'Antigüedad', esAccidente ? 'También sirve para ubicar el contexto del siniestro.' : 'Antigüedad y provincia ordenan plazos, tasas y escalas.'],
                    ['bi-journal-richtext', 'Contexto', 'Categoría, convenio y registro ayudan a afinar el análisis.'],
                ],
            },
            3: {
                icon: 'bi-folder2-open',
                eyebrowText: 'Soporte probatorio',
                title: esEmpleador ? 'Mostrá qué respaldo documental conserva la empresa' : 'Mostrá con qué documentación contás hoy',
                description: esEmpleador
                    ? 'Acá vemos si la empresa tiene legajo, registración y auditorías para defenderse o corregir rápido.'
                    : 'No hace falta tener todo: esta pantalla sirve para medir fortaleza probatoria y detectar faltantes relevantes.',
                points: [
                    ['bi-receipt', 'Recibos / legajo', esEmpleador ? 'Recibos firmados, contrato y respaldo interno.' : 'Recibos, contrato y constancias que acrediten la relación.'],
                    ['bi-people', 'Testigos', 'Indicá si hay personas que puedan confirmar la dinámica del caso.'],
                    ['bi-shield-check', 'Registro', esEmpleador ? 'ARCA, auditorías y checklist preventivo si corresponde.' : 'ARCA y registración impactan directo en el índice IRIL.'],
                ],
            },
            4: {
                icon: esAccidente ? 'bi-activity' : 'bi-clock-history',
                eyebrowText: 'Estado actual',
                title: esAccidente ? 'Ubicá el estado del siniestro y de la ART' : 'Ubicá el conflicto en el tiempo y la urgencia',
                description: esAccidente
                    ? 'Este paso ordena intercambio, fechas, incapacidad y trámite administrativo para medir urgencia real.'
                    : esEmpleador && esPrevencion
                        ? 'La urgencia define si conviene corregir, negociar o preparar respuesta frente a una inspección o reclamo.'
                        : 'Acá el motor detecta si el caso está verde, escaló a intimaciones o ya tiene plazos corriendo.',
                points: [
                    ['bi-envelope-paper', 'Intercambio', 'Telegramas, intimaciones o requerimientos previos.'],
                    ['bi-alarm', 'Urgencia', 'Alta, media o baja según plazos y necesidad de acción inmediata.'],
                    ['bi-diagram-3', esAccidente ? 'ART / comisión médica' : 'Escenario', esAccidente ? 'Rechazo, dictamen y vía administrativa.' : 'Sirve para leer si el conflicto ya escaló o sigue prevenible.'],
                ],
            },
            5: {
                icon: 'bi-envelope-check',
                eyebrowText: 'Cierre',
                title: 'Revisá el resumen y generá el análisis',
                description: 'El email sigue siendo opcional. Antes de enviar, el wizard te devuelve una síntesis corta de lo cargado para confirmar el recorrido.',
                points: [
                    ['bi-list-check', 'Resumen', 'Chequeá perfil, conflicto, base económica y respaldo declarado.'],
                    ['bi-envelope', 'Email opcional', 'Podés recibir el informe sin frenar el resultado en pantalla.'],
                    ['bi-graph-up-arrow', 'Salida', 'Generamos IRIL, exposición y escenarios en una sola lectura.'],
                ],
            },
        };

        const guia = guias[pasoActivo] || guias[1];

        icono.innerHTML = `<i class="bi ${guia.icon}"></i>`;
        eyebrow.textContent = guia.eyebrowText;
        titulo.textContent = guia.title;
        descripcion.textContent = guia.description;
        puntos.innerHTML = guia.points.map(([iconoPunto, tituloPunto, textoPunto]) => `
            <article class="wizard-guide-point">
                <div class="wizard-guide-point-icon" aria-hidden="true">
                    <i class="bi ${iconoPunto}"></i>
                </div>
                <div>
                    <strong>${this._escaparHTML(tituloPunto)}</strong>
                    <span>${this._escaparHTML(textoPunto)}</span>
                </div>
            </article>
        `).join('');
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

    _validarEnvioCompleto() {
        const pasosAValidar = [1, 2, 4, 5];
        let primerPasoInvalido = null;

        pasosAValidar.forEach((paso) => this._limpiarErrores(paso));

        pasosAValidar.forEach((paso) => {
            const esValido = this.validarPaso(paso);
            if (!esValido && primerPasoInvalido === null) {
                primerPasoInvalido = paso;
            }
        });

        if (primerPasoInvalido !== null) {
            this.mostrarPaso(primerPasoInvalido);
            const primerError = this.formulario.querySelector(
                `#paso-${primerPasoInvalido} .form-group.tiene-error`
            );
            if (primerError) {
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    _aplicarErroresServidor(errors) {
        if (!errors || typeof errors !== 'object') return;

        const pasosPorCampo = {
            tipo_usuario: 1,
            tipo_conflicto: 1,
            salario: 2,
            antiguedad_meses: 2,
            provincia: 2,
            cantidad_empleados: 2,
            edad: 2,
            email: 5,
            urgencia: 4,
            fecha_despido: 4,
            fecha_ultimo_telegrama: 4,
            fecha_siniestro: 4,
            porcentaje_incapacidad: 4,
            preexistencia_porcentaje: 4,
            probabilidad_condena: 4,
            meses_litigio: 4,
            jurisdiccion: 4,
        };

        let primerPaso = null;

        Object.entries(errors).forEach(([clave, mensaje]) => {
            const id = clave.split('.').pop();
            const campo = this.formulario.querySelector(`#${id}`);
            if (!campo) return;

            this._mostrarError(campo, String(mensaje));

            const paso = pasosPorCampo[id];
            if (primerPaso === null && paso) {
                primerPaso = paso;
            }
        });

        if (primerPaso !== null) {
            this.mostrarPaso(primerPaso);
        }
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
                btnEnviar.setAttribute('aria-busy', 'true');
                btnEnviar.innerHTML = '<span class="spinner-inline"></span> Analizando y validando...';
            }
            this._anunciarEstado('Procesando el análisis. Validando datos y calculando resultados.');
        } else {
            this.loadingOverlay.classList.remove('activo');
            document.body.style.overflow = '';

            // Restaurar botón de envío
            const btnEnviar = document.getElementById('btn-enviar');
            if (btnEnviar) {
                btnEnviar.disabled = false;
                btnEnviar.removeAttribute('aria-busy');
                btnEnviar.innerHTML = 'Generar Análisis';
            }
            this._anunciarEstado('');
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

    _inicializarConflictoCards() {
        const conflictoCards = this.formulario.querySelectorAll('.conflicto-card');

        conflictoCards.forEach(card => {
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-pressed', 'false');

            card.addEventListener('click', () => {
                const campoTipoConflicto = this.formulario.querySelector('#tipo_conflicto');
                if (campoTipoConflicto) {
                    campoTipoConflicto.value = card.dataset.valor || '';
                }
                this._sincronizarPasoPerfil();
            });

            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
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

    _sincronizarPasoPerfil() {
        if (!this.formulario) return;

        const campoTipoUsuario = this.formulario.querySelector('#tipo_usuario');
        const radioSeleccionado = this.formulario.querySelector('input[name="tipo_usuario_radio"]:checked');
        const campoTipoConflicto = this.formulario.querySelector('#tipo_conflicto');
        const cards = Array.from(this.formulario.querySelectorAll('.conflicto-card'));
        const tarjetaSeleccionada = cards.find(card => card.classList.contains('selected'));

        if (campoTipoUsuario && radioSeleccionado) {
            campoTipoUsuario.value = radioSeleccionado.value;
        }

        const valorConflictoCampo = campoTipoConflicto ? campoTipoConflicto.value : null;
        const conflictoActual = valorConflictoCampo && valorConflictoCampo !== ''
            ? valorConflictoCampo
            : (tarjetaSeleccionada?.dataset.valor || '');

        if (campoTipoConflicto) {
            campoTipoConflicto.value = conflictoActual;
        }

        const conflictoAnterior = this.conflictoSeleccionado;
        const conflictoCambio = conflictoActual !== conflictoAnterior;
        const cardAnterior = conflictoCambio
            ? cards.find(card => card.dataset.valor === conflictoAnterior)
            : null;
        const cardActual = cards.find(card => card.dataset.valor === conflictoActual);

        if (cardAnterior && cardAnterior !== cardActual) {
            cardAnterior.classList.remove('selected');
            cardAnterior.setAttribute('aria-pressed', 'false');
        }

        if (cardActual) {
            cardActual.classList.add('selected');
            cardActual.setAttribute('aria-pressed', 'true');
        }

        if (conflictoCambio) {
            // Limpieza defensiva por si otro listener o restauración del navegador
            // deja más de una tarjeta marcada al mismo tiempo.
            cards.forEach(card => {
                if (card !== cardActual && card.classList.contains('selected')) {
                    card.classList.remove('selected');
                    card.setAttribute('aria-pressed', 'false');
                }
            });
        }

        this.conflictoSeleccionado = conflictoActual;
        this._actualizarGuiaVisual(this.pasoActual);
    }

    _campoEsVisible(campo) {
        if (!campo) return false;

        const estilos = window.getComputedStyle(campo);
        if (estilos.display === 'none' || estilos.visibility === 'hidden') {
            return false;
        }

        // Los elementos fixed pueden no tener offsetParent aunque sigan visibles.
        return campo.offsetParent !== null || estilos.position === 'fixed';
    }

    _actualizarResumenPrevio() {
        const contenedor = document.getElementById('resumen-contenido');
        if (!contenedor) return;

        const payload = this._construirPayload();
        const perfilLabel = {
            empleado: 'Empleado / Trabajador',
            empleador: 'Empleador / Empresa',
        };
        const conflictoLabel = {
            despido_sin_causa: 'Despido sin causa',
            despido_con_causa: 'Despido con causa',
            trabajo_no_registrado: 'Trabajo en negro',
            diferencias_salariales: 'Diferencias / Deudas',
            accidente_laboral: 'Accidente / Enfermedad',
            responsabilidad_solidaria: 'Responsabilidad solidaria',
            auditoria_preventiva: 'Auditoría preventiva',
            riesgo_inspeccion: 'Inspección ARCA/Ministerio',
        };
        const urgenciaLabel = {
            alta: 'Alta',
            media: 'Media',
            baja: 'Baja',
        };
        const docs = [];
        const faltantes = [];

        if (payload.documentacion.tiene_recibos === 'si') docs.push('Recibos');
        if (payload.documentacion.tiene_contrato === 'si') docs.push('Contrato');
        if (payload.documentacion.registrado_afip === 'si') docs.push('Registro ARCA');
        if (payload.documentacion.tiene_testigos === 'si') docs.push('Testigos');
        if (payload.documentacion.auditoria_previa === 'si') docs.push('Auditoría previa');

        if (!payload.datos_laborales.salario) faltantes.push('Salario');
        if (!payload.datos_laborales.antiguedad_meses && payload.datos_laborales.antiguedad_meses !== 0) faltantes.push('Antigüedad');
        if (!payload.datos_laborales.provincia) faltantes.push('Provincia');
        if (!payload.situacion.urgencia) faltantes.push('Urgencia');

        const items = [
            ['Perfil', perfilLabel[payload.tipo_usuario] || 'Sin definir'],
            ['Conflicto', conflictoLabel[payload.tipo_conflicto] || 'Sin definir'],
            ['Salario base', payload.datos_laborales.salario > 0 ? this._formatearMoneda(payload.datos_laborales.salario) : 'Sin informar'],
            ['Antigüedad', `${payload.datos_laborales.antiguedad_meses || 0} meses`],
            ['Provincia', payload.datos_laborales.provincia || 'Sin informar'],
            ['Urgencia', urgenciaLabel[payload.situacion.urgencia] || 'Sin definir'],
            ['Documentación útil', docs.length ? docs.join(', ') : 'Sin respaldo declarado'],
            ['Email', payload.contacto.email || 'No informado'],
        ];

        contenedor.innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
                ${items.map(([label, value]) => `
                    <div style="padding:0.85rem;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                        <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">${this._escaparHTML(label)}</div>
                        <div style="font-weight:600;color:#111827;margin-top:0.2rem;">${this._escaparHTML(String(value))}</div>
                    </div>
                `).join('')}
            </div>
            <div style="margin-top:0.85rem;padding:0.85rem;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">
                <strong>Chequeo previo:</strong>
                ${faltantes.length
                    ? `Todavía conviene revisar: ${this._escaparHTML(faltantes.join(', '))}.`
                    : 'Los datos mínimos del flujo quedaron completos para generar el análisis.'}
            </div>
        `;
    }

    _formatearMoneda(valor) {
        try {
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS',
                maximumFractionDigits: 0,
            }).format(valor);
        } catch (_) {
            return `$${valor}`;
        }
    }

    _anunciarEstado(mensaje) {
        if (this.liveRegion) {
            this.liveRegion.textContent = mensaje;
        }
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
