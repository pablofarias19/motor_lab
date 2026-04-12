/**
 * resultados.js — Motor de Riesgo Laboral | Renderizado de la Pantalla de Resultados
 *
 * Este script se encarga de toda la interactividad y renderizado visual de
 * la página resultado.php.
 *
 * Fuente de datos: window.analisisData
 * El objeto window.analisisData es inyectado por resultado.php en un bloque
 * <script> antes de cargar este archivo. Estructura esperada:
 *
 * window.analisisData = {
 *   uuid:          string,
 *   iril: {
 *     score:   number (1.0–5.0),
 *     nivel:   string ('Bajo' | 'Moderado' | 'Alto' | 'Crítico'),
 *     detalle: {
 *       saturacion_tribunalicia:  { valor, peso, descripcion },
 *       complejidad_probatoria:   { valor, peso, descripcion },
 *       volatilidad_normativa:    { valor, peso, descripcion },
 *       riesgo_costas:            { valor, peso, descripcion },
 *       riesgo_multiplicador:     { valor, peso, descripcion },
 *     },
 *     alertas: Array<{ tipo, urgencia, descripcion, fecha_vencimiento }>
 *   },
 *   exposicion: {
 *     conceptos:        Object.<string, { descripcion, monto, base_legal, condicion?, aplica? }>,
 *     total_base:       number,
 *     total_con_multas: number,
 *     salario_base:     number,
 *     antiguedad_anos:  number,
 *     nota_legal:       string
 *   },
 *   escenarios: {
 *     A: EscenarioObj,
 *     B: EscenarioObj,
 *     C: EscenarioObj,
 *     D: EscenarioObj,
 *   },
 *   recomendado:        string ('A' | 'B' | 'C' | 'D'),
 *   tabla_comparativa:  Array<TablaFila>,
 *   tipo_usuario:       string,
 *   tipo_conflicto:     string,
 * };
 *
 * Funciones exportadas (globales para uso desde HTML):
 *   - btnDescargarInforme()   → descarga el PDF del informe
 *
 * Dependencias: ninguna (vanilla JS puro, ES6+).
 * Canvas API para el gauge (nativo del browser).
 *
 * @author  Motor Laboral — Estudio Farias Ortiz
 * @version 1.0.0
 */

'use strict';

// =============================================================================
// CONSTANTES Y CONFIGURACIÓN
// =============================================================================

/**
 * Mapa de niveles IRIL con sus rangos, colores y descripciones.
 * Se usa para el gauge, badges y clases CSS.
 */
const NIVELES_IRIL = {
    bajo:     { min: 1.0, max: 2.4, color: '#28a745', etiqueta: 'Bajo',     clase: 'iril-bajo'     },
    moderado: { min: 2.5, max: 3.4, color: '#ffc107', etiqueta: 'Moderado', clase: 'iril-moderado' },
    alto:     { min: 3.5, max: 3.9, color: '#fd7e14', etiqueta: 'Alto',     clase: 'iril-alto'     },
    critico:  { min: 4.0, max: 5.0, color: '#dc3545', etiqueta: 'Crítico',  clase: 'iril-critico'  },
};

/**
 * Configuración visual del gauge canvas (semi-círculo).
 */
const GAUGE_CONFIG = {
    anchura:         220,    // px — ancho del canvas
    altura:          130,    // px — alto del canvas (semi-círculo)
    grosor:          22,     // px — grosor del arco
    radioFactor:     0.82,   // factor del radio respecto al ancho/2
    colorFondo:      '#e9ecef',
    colorTextoScore: '#212529',
    colorTextoLabel: '#6c757d',
};

/**
 * Configuración de íconos por tipo de alerta.
 * Usados en las tarjetas de alerta (alerta-card).
 */
const ICONOS_ALERTA = {
    prescripcion:          '⏳',
    respuesta_telegrama:   '📨',
    certificados_art80:    '📄',
    inspeccion_laboral:    '🔍',
    derivacion_profesional:'⚠️',
    default:               '🔔',
};

/**
 * Títulos legibles para cada tipo de alerta.
 */
const TITULOS_ALERTA = {
    prescripcion:          'Prescripción en Curso',
    respuesta_telegrama:   'Plazo de Respuesta Telegráfica',
    certificados_art80:    'Certificados de Trabajo (Art. 80 LCT)',
    inspeccion_laboral:    'Riesgo de Inspección Laboral',
    derivacion_profesional:'Derivación Profesional Urgente',
    default:               'Alerta Laboral',
};

// =============================================================================
// PUNTO DE ENTRADA PRINCIPAL
// =============================================================================

/**
 * Inicialización al cargar el DOM.
 *
 * Lee window.analisisData inyectado por PHP y llama a las funciones
 * de renderizado de cada sección de la pantalla de resultados.
 */
document.addEventListener('DOMContentLoaded', () => {

    // Verificar que los datos existan
    if (typeof window.analisisData === 'undefined' || !window.analisisData) {
        console.error('[Resultados] window.analisisData no está definido. ¿El PHP inyectó los datos?');
        _mostrarErrorCritico('No se pudieron cargar los datos del análisis. Por favor, volvé al formulario e intentá nuevamente.');
        return;
    }

    const datos = window.analisisData;

    console.info('[Resultados] Datos del análisis recibidos:', datos);

    // ── Renderizar cada sección ──────────────────────────────────────────────

    // 1. Gauge visual del IRIL
    if (datos.iril && typeof datos.iril.score === 'number') {
        renderGaugeIril(datos.iril.score);
        _renderDimensionesIril(datos.iril.detalle, datos.iril.score);
    }

    // 2. Tabla comparativa de escenarios
    if (datos.tabla_comparativa && datos.recomendado) {
        renderTablaComparativa(datos.tabla_comparativa, datos.recomendado);
    }

    // 3. Tarjetas de escenario (acordeón)
    if (datos.escenarios && datos.recomendado) {
        _renderEscenarioCards(datos.escenarios, datos.recomendado);
    }

    // 4. Tabla de exposición económica
    if (datos.exposicion) {
        _renderTablaExposicion(datos.exposicion);
    }

    // 5. Alertas activas
    if (datos.iril && datos.iril.alertas) {
        renderAlertasActivas(datos.iril.alertas);
    }

    // 6. Inicializar acordeones de detalles
    initAccordion();

    // 7. Guardar UUID para la descarga del informe
    if (datos.uuid) {
        window._analisisUUID = datos.uuid;
    }

    console.info('[Resultados] Renderizado completo.');
});

// =============================================================================
// 1. GAUGE IRIL — SEMI-CÍRCULO CON CANVAS
// =============================================================================

/**
 * renderGaugeIril(score) — Dibuja el gauge semi-circular del IRIL usando
 * la Canvas 2D API.
 *
 * El gauge es un arco de 180° (semi-círculo) que va de rojo a verde.
 * La aguja indica la posición del score (1.0 a 5.0).
 * Se dibuja en el elemento <canvas id="iril-gauge-canvas">.
 *
 * Paleta de colores del arco (de izquierda a derecha):
 *   Verde (#28a745) → Amarillo (#ffc107) → Naranja (#fd7e14) → Rojo (#dc3545)
 *
 * @param {number} score - Puntaje IRIL entre 1.0 y 5.0.
 */
function renderGaugeIril(score) {
    const canvas = document.getElementById('iril-gauge-canvas');
    if (!canvas) {
        console.warn('[Resultados] No se encontró #iril-gauge-canvas.');
        return;
    }

    const ctx    = canvas.getContext('2d');
    const cfg    = GAUGE_CONFIG;

    // Ajustar tamaño del canvas según configuración
    canvas.width  = cfg.anchura;
    canvas.height = cfg.altura;

    // Soporte para pantallas de alta densidad (retina)
    const dpr = window.devicePixelRatio || 1;
    canvas.width  = cfg.anchura * dpr;
    canvas.height = cfg.altura * dpr;
    canvas.style.width  = `${cfg.anchura}px`;
    canvas.style.height = `${cfg.altura}px`;
    ctx.scale(dpr, dpr);

    // Centro del semi-círculo y radio
    const cx = cfg.anchura / 2;
    const cy = cfg.altura - 10;    // base del semi-círculo
    const radio = (cfg.anchura / 2) * cfg.radioFactor;

    // ── Limpiar canvas ───────────────────────────────────────────────────────
    ctx.clearRect(0, 0, cfg.anchura, cfg.altura);

    // ── Arco de fondo (gris) ─────────────────────────────────────────────────
    ctx.beginPath();
    ctx.arc(cx, cy, radio, Math.PI, 0, false);
    ctx.lineWidth   = cfg.grosor;
    ctx.strokeStyle = cfg.colorFondo;
    ctx.lineCap     = 'round';
    ctx.stroke();

    // ── Arco de color (gradiente) ─────────────────────────────────────────────
    // Dividimos el semi-arco en 4 segmentos coloreados (bajo, moderado, alto, crítico)
    const segmentos = [
        { color: '#28a745', inicio: Math.PI,       fin: Math.PI * 1.25 },  // Bajo
        { color: '#ffc107', inicio: Math.PI * 1.25, fin: Math.PI * 1.50 }, // Moderado
        { color: '#fd7e14', inicio: Math.PI * 1.50, fin: Math.PI * 1.75 }, // Alto
        { color: '#dc3545', inicio: Math.PI * 1.75, fin: Math.PI * 2.0  }, // Crítico
    ];

    segmentos.forEach(seg => {
        ctx.beginPath();
        ctx.arc(cx, cy, radio, seg.inicio, seg.fin, false);
        ctx.lineWidth   = cfg.grosor;
        ctx.strokeStyle = seg.color;
        ctx.lineCap     = 'butt';
        ctx.stroke();
    });

    // ── Calcular ángulo de la aguja ───────────────────────────────────────────
    // score 1.0 → ángulo 180° (izquierda), score 5.0 → ángulo 0° (derecha)
    const scoreNormalizado = Math.max(1, Math.min(5, score));
    const anguloAguja = Math.PI + ((scoreNormalizado - 1) / 4) * Math.PI;

    // ── Dibujar aguja ─────────────────────────────────────────────────────────
    const largoAguja    = radio - cfg.grosor / 2 - 4;
    const agujaX        = cx + largoAguja * Math.cos(anguloAguja);
    const agujaY        = cy + largoAguja * Math.sin(anguloAguja);

    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(agujaX, agujaY);
    ctx.lineWidth   = 3;
    ctx.strokeStyle = '#212529';
    ctx.lineCap     = 'round';
    ctx.stroke();

    // Pivote central de la aguja (círculo pequeño)
    ctx.beginPath();
    ctx.arc(cx, cy, 5, 0, Math.PI * 2);
    ctx.fillStyle = '#212529';
    ctx.fill();

    // ── Texto del score en el centro ─────────────────────────────────────────
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'bottom';

    // Score grande
    ctx.font      = `bold ${cfg.anchura * 0.16}px 'Segoe UI', sans-serif`;
    ctx.fillStyle = cfg.colorTextoScore;
    ctx.fillText(score.toFixed(1), cx, cy - 12);

    // "/5" en gris claro
    ctx.font      = `${cfg.anchura * 0.08}px 'Segoe UI', sans-serif`;
    ctx.fillStyle = cfg.colorTextoLabel;
    ctx.fillText('/ 5.0', cx, cy + 4);

    // ── Etiquetas de escala (1 y 5) en los extremos ───────────────────────────
    ctx.font      = `bold ${cfg.anchura * 0.075}px 'Segoe UI', sans-serif`;
    ctx.fillStyle = cfg.colorTextoLabel;
    ctx.textAlign = 'left';
    ctx.fillText('1', 6, cy + 4);
    ctx.textAlign = 'right';
    ctx.fillText('5', cfg.anchura - 6, cy + 4);

    // ── Aplicar clase de color al contenedor ──────────────────────────────────
    const nivelInfo = _obtenerNivelIril(scoreNormalizado);
    const contenedorHeader = document.querySelector('.iril-resultado-header');
    if (contenedorHeader && nivelInfo) {
        // Remover clases de nivel previas
        Object.values(NIVELES_IRIL).forEach(n => contenedorHeader.classList.remove(n.clase));
        contenedorHeader.classList.add(nivelInfo.clase);
    }

    // Actualizar el badge de nivel textual
    const badgeNivel = document.getElementById('iril-nivel-badge');
    if (badgeNivel && nivelInfo) {
        badgeNivel.textContent = `IRIL ${nivelInfo.etiqueta}`;
        badgeNivel.className = `iril-nivel-badge iril-badge-${nivelInfo.clase.replace('iril-', '')}`;
    }
}

// =============================================================================
// 2. TABLA COMPARATIVA DE ESCENARIOS
// =============================================================================

/**
 * renderTablaComparativa(escenarios, recomendado) — Construye la tabla HTML
 * de comparación de escenarios y la inserta en el DOM.
 *
 * La tabla incluye: código, nombre, costo estimado, beneficio estimado,
 * VBP (valor bruto), duración, riesgo institucional e intervención requerida.
 * La fila del escenario recomendado recibe estilos especiales.
 *
 * Contenedor destino: #tabla-comparativa-container
 *
 * @param {Array<Object>} escenarios   - Array de filas de tabla_comparativa del PHP.
 * @param {string}        recomendado  - Código del escenario recomendado ('A'|'B'|'C'|'D').
 */
function renderTablaComparativa(escenarios, recomendado) {
    const contenedor = document.getElementById('tabla-comparativa-container');
    if (!contenedor) {
        console.warn('[Resultados] No se encontró #tabla-comparativa-container.');
        return;
    }

    if (!escenarios || !escenarios.length) {
        contenedor.innerHTML = '<p class="texto-ayuda">No hay escenarios disponibles.</p>';
        return;
    }

    // ── Construir el HTML de la tabla ─────────────────────────────────────────
    const filas = escenarios.map(esc => {
        const esRecomendado = esc.codigo === recomendado || esc.recomendado === true;

        // Determinar clase de color para el VBP (positivo/negativo)
        const vbpNum = parseFloat(esc.vbp?.toString().replace(/[^0-9,-]/g, '').replace('.', '').replace(',', '.')) || 0;
        const claseVbp = vbpNum >= 0 ? 'monto-positivo' : 'monto-negativo';

        // Badge "Sugerido" solo para el recomendado
        const badgeSugerido = esRecomendado
            ? '<span class="badge-sugerido" title="Escenario con mejor Índice Estratégico para este perfil">Sugerido</span>'
            : '';

        return `
            <tr class="${esRecomendado ? 'fila-recomendada' : ''}"
                data-escenario="${_escaparHTML(esc.codigo)}">
                <td>
                    <strong>${_escaparHTML(esc.codigo)}</strong>
                    ${badgeSugerido}
                </td>
                <td>${_escaparHTML(esc.nombre || '')}</td>
                <td>${_escaparHTML(esc.costo || '—')}</td>
                <td>${_escaparHTML(esc.beneficio || '—')}</td>
                <td class="${claseVbp}">${_escaparHTML(esc.vbp || '—')}</td>
                <td>${_escaparHTML(esc.duracion || '—')}</td>
                <td>${_escaparHTML(esc.riesgo || '—')}</td>
                <td>${_escaparHTML(esc.intervencion || '—')}</td>
            </tr>
        `;
    }).join('');

    const html = `
        <div class="tabla-comparativa-wrapper">
            <table class="tabla-comparativa" role="table"
                   aria-label="Comparativa de escenarios estratégicos">
                <thead>
                    <tr>
                        <th scope="col">Escenario</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Costo Est.</th>
                        <th scope="col">Beneficio Est.</th>
                        <th scope="col">VBP</th>
                        <th scope="col">Duración</th>
                        <th scope="col">Riesgo Inst.</th>
                        <th scope="col">Intervención</th>
                    </tr>
                </thead>
                <tbody>
                    ${filas}
                </tbody>
            </table>
        </div>
        <p class="texto-ayuda" style="margin-top:0.5rem;">
            VBP = Valor Bruto Proyectado (Beneficio − Costo). La fila resaltada en verde
            indica el escenario con mejor Índice Estratégico para este perfil de riesgo.
            Esta sugerencia es estructural y no reemplaza el asesoramiento profesional.
        </p>
    `;

    contenedor.innerHTML = html;
}

// =============================================================================
// 3. ALERTAS ACTIVAS
// =============================================================================

/**
 * renderAlertasActivas(alertas) — Renderiza las tarjetas de alerta en el DOM.
 *
 * Cada alerta del array (generadas por IrilEngine::generarAlertas) se
 * convierte en una .alerta-card con ícono, título, descripción y urgencia.
 *
 * Clases CSS de urgencia: .urgencia-critica, .urgencia-alta, .urgencia-media,
 *                          .urgencia-baja, .urgencia-vencida
 *
 * Contenedor destino: #alertas-container
 *
 * @param {Array<Object>} alertas - Array de objetos de alerta del PHP.
 */
function renderAlertasActivas(alertas) {
    const contenedor = document.getElementById('alertas-container');
    if (!contenedor) {
        console.warn('[Resultados] No se encontró #alertas-container.');
        return;
    }

    // Si no hay alertas, mostrar mensaje positivo
    if (!alertas || alertas.length === 0) {
        contenedor.innerHTML = `
            <div class="alertas-vacio" role="status">
                <span class="ui-emoji ui-emoji--status" aria-hidden="true">✅</span><br>
                No se detectaron alertas urgentes en este análisis.
            </div>
        `;
        return;
    }

    // ── Ordenar alertas por urgencia (crítica primero) ────────────────────────
    const ordenUrgencia = { critica: 0, alta: 1, media: 2, baja: 3, vencida: 4 };
    const alertasOrdenadas = [...alertas].sort((a, b) => {
        return (ordenUrgencia[a.urgencia] ?? 99) - (ordenUrgencia[b.urgencia] ?? 99);
    });

    // ── Construir tarjetas de alerta ──────────────────────────────────────────
    const cards = alertasOrdenadas.map(alerta => {
        const tipo     = alerta.tipo || 'default';
        const urgencia = alerta.urgencia || 'media';
        const icono    = ICONOS_ALERTA[tipo] || ICONOS_ALERTA.default;
        const titulo   = TITULOS_ALERTA[tipo] || TITULOS_ALERTA.default;

        // Formatear fecha de vencimiento si existe
        let textoFecha = '';
        if (alerta.fecha_vencimiento) {
            const fecha = _formatearFecha(alerta.fecha_vencimiento);
            textoFecha = `<p class="alerta-vencimiento"><span class="ui-emoji" aria-hidden="true">📅</span>Vencimiento: ${fecha}</p>`;
        }

        return `
            <div class="alerta-card urgencia-${_escaparHTML(urgencia)}"
                 role="alert"
                 aria-label="Alerta de urgencia ${urgencia}: ${titulo}">
                <span class="alerta-icon" aria-hidden="true">${icono}</span>
                <div class="alerta-contenido">
                    <p class="alerta-titulo">${_escaparHTML(titulo)}</p>
                    <p class="alerta-descripcion">${_escaparHTML(alerta.descripcion || '')}</p>
                    ${textoFecha}
                </div>
            </div>
        `;
    }).join('');

    contenedor.innerHTML = `<div class="alertas-container">${cards}</div>`;

    // Actualizar contador de alertas en el título de la sección
    const contadorEl = document.getElementById('alertas-count');
    if (contadorEl) {
        contadorEl.textContent = alertas.length;
    }
}

// =============================================================================
// 4. ACORDEÓN DE SECCIONES DE DETALLE
// =============================================================================

/**
 * initAccordion() — Inicializa el comportamiento de acordeón para las
 * secciones de detalle de la pantalla de resultados.
 *
 * Busca todos los elementos .acordeon-header y les adjunta un listener
 * de click que muestra/oculta el .acordeon-body correspondiente.
 *
 * También inicializa el acordeón de las tarjetas de escenario (.escenario-card).
 *
 * Soporta teclado: Enter y Espacio para activar la cabecera.
 */
function initAccordion() {

    // ── Acordeón genérico (.acordeon-seccion) ────────────────────────────────
    const headers = document.querySelectorAll('.acordeon-header');

    headers.forEach(header => {
        const seccion = header.closest('.acordeon-seccion');
        if (!seccion) return;

        // Accesibilidad
        header.setAttribute('tabindex', '0');
        header.setAttribute('role', 'button');
        const estaAbierta = seccion.classList.contains('abierta');
        header.setAttribute('aria-expanded', estaAbierta ? 'true' : 'false');

        const _toggle = () => {
            const abierta = seccion.classList.toggle('abierta');
            header.setAttribute('aria-expanded', abierta ? 'true' : 'false');
        };

        header.addEventListener('click', _toggle);
        header.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                _toggle();
            }
        });
    });

    // ── Acordeón de tarjetas de escenario (.escenario-card-header) ───────────
    const escenarioHeaders = document.querySelectorAll('.escenario-card-header');

    escenarioHeaders.forEach(header => {
        const card = header.closest('.escenario-card');
        if (!card) return;

        header.setAttribute('tabindex', '0');
        header.setAttribute('role', 'button');
        header.setAttribute('aria-expanded', 'false');

        const _toggleCard = () => {
            // Cerrar las demás cards (acordeón exclusivo opcional)
            // Si se prefiere múltiples abiertos simultáneamente, comentar las 4 líneas siguientes:
            const todasLasCards = document.querySelectorAll('.escenario-card');
            todasLasCards.forEach(otraCard => {
                if (otraCard !== card && otraCard.classList.contains('expandida')) {
                    otraCard.classList.remove('expandida');
                    const otraHeader = otraCard.querySelector('.escenario-card-header');
                    if (otraHeader) otraHeader.setAttribute('aria-expanded', 'false');
                }
            });

            // Alternar la card actual
            const expandida = card.classList.toggle('expandida');
            header.setAttribute('aria-expanded', expandida ? 'true' : 'false');
        };

        header.addEventListener('click', _toggleCard);
        header.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                _toggleCard();
            }
        });
    });

    // ── Expandir automáticamente el escenario recomendado ────────────────────
    const cardRecomendada = document.querySelector('.escenario-card.escenario-recomendado');
    if (cardRecomendada) {
        cardRecomendada.classList.add('expandida');
        const headerRecomendado = cardRecomendada.querySelector('.escenario-card-header');
        if (headerRecomendado) {
            headerRecomendado.setAttribute('aria-expanded', 'true');
        }
    }

    console.info('[Resultados] Acordeones inicializados.');
}

// =============================================================================
// 5. DESCARGA DEL INFORME
// =============================================================================

/**
 * btnDescargarInforme() — Genera y dispara la descarga del informe PDF.
 *
 * Construye la URL hacia api/generar_informe.php con el UUID del análisis
 * como parámetro GET y simula un click en un enlace temporal para descargar.
 *
 * El UUID se lee de window._analisisUUID (seteado al cargar la página)
 * o como fallback de window.analisisData.uuid.
 *
 * Se puede llamar desde un botón en el HTML:
 *   <button onclick="btnDescargarInforme()">Descargar Informe PDF</button>
 */
function btnDescargarInforme() {
    // Obtener el UUID del análisis
    const uuid = window._analisisUUID
        || (window.analisisData && window.analisisData.uuid)
        || null;

    if (!uuid) {
        alert('No se puede generar el informe: UUID del análisis no disponible.');
        console.error('[Resultados] UUID no disponible para descargar el informe.');
        return;
    }

    // Construir la URL de descarga
    const urlDescarga = `api/generar_informe.php?uuid=${encodeURIComponent(uuid)}`;

    console.info(`[Resultados] Iniciando descarga del informe. URL: ${urlDescarga}`);

    // Crear un enlace temporal invisible y hacer click
    const enlace = document.createElement('a');
    enlace.href     = urlDescarga;
    enlace.download = `informe_riesgo_laboral_${uuid.slice(0, 8)}.pdf`;
    enlace.rel      = 'noopener noreferrer';
    enlace.style.display = 'none';

    document.body.appendChild(enlace);
    enlace.click();

    // Limpiar el enlace temporal del DOM
    setTimeout(() => {
        document.body.removeChild(enlace);
    }, 200);
}

// =============================================================================
// FUNCIONES INTERNAS DE RENDERIZADO
// =============================================================================

/**
 * _renderDimensionesIril(detalle, scoreTotal) — Renderiza las tarjetas de
 * desglose de las 5 dimensiones IRIL.
 *
 * Cada dimensión se muestra con su valor numérico, peso porcentual,
 * descripción y una barra mini de progreso.
 *
 * Contenedor destino: #iril-dimensiones-container
 *
 * @param {Object} detalle     - Objeto detalle del IRIL (IrilEngine).
 * @param {number} scoreTotal  - Score IRIL total para referencia visual.
 */
function _renderDimensionesIril(detalle, scoreTotal) {
    const contenedor = document.getElementById('iril-dimensiones-container');
    if (!contenedor || !detalle) return;

    // Nombres legibles para las dimensiones
    const nombresDimension = {
        saturacion_tribunalicia: 'Saturación Tribunalicia',
        complejidad_probatoria:  'Complejidad Probatoria',
        volatilidad_normativa:   'Volatilidad Normativa',
        riesgo_costas:           'Riesgo de Costas',
        riesgo_multiplicador:    'Riesgo Multiplicador',
    };

    const cards = Object.entries(detalle).map(([clave, dim]) => {
        const nombre = nombresDimension[clave] || clave;
        const valor  = parseFloat(dim.valor) || 0;
        const porcBarra = ((valor - 1) / 4) * 100; // 1→0%, 5→100%
        const colorBarra = _colorPorValor(valor);

        return `
            <div class="iril-dimension-card">
                <div class="iril-dimension-nombre">${_escaparHTML(nombre)}</div>
                <div class="iril-dimension-valor">${valor.toFixed(1)}</div>
                <div class="iril-mini-bar" title="${valor.toFixed(1)} / 5.0">
                    <div class="iril-mini-bar-fill"
                         style="width:${porcBarra}%; background-color:${colorBarra};"
                         role="progressbar"
                         aria-valuenow="${valor}"
                         aria-valuemin="1"
                         aria-valuemax="5">
                    </div>
                </div>
                <div class="iril-dimension-peso">Peso: ${_escaparHTML(dim.peso || '—')}</div>
                <div class="iril-dimension-desc">${_escaparHTML(dim.descripcion || '')}</div>
            </div>
        `;
    }).join('');

    contenedor.innerHTML = cards;
}

/**
 * _renderEscenarioCards(escenarios, recomendado) — Renderiza las tarjetas
 * acordeón de cada escenario estratégico (A, B, C, D).
 *
 * Contenedor destino: #escenarios-cards-container
 *
 * @param {Object} escenarios   - Objeto con claves A, B, C, D.
 * @param {string} recomendado  - Código del escenario recomendado.
 */
function _renderEscenarioCards(escenarios, recomendado) {
    const contenedor = document.getElementById('escenarios-cards-container');
    if (!contenedor) {
        console.warn('[Resultados] No se encontró #escenarios-cards-container.');
        return;
    }

    const cards = Object.entries(escenarios).map(([codigo, esc]) => {
        const esRecomendado = codigo === recomendado;

        // Badge de escenario recomendado
        const badgeRecomendado = esRecomendado
            ? `<span class="escenario-recomendado-badge">Sugerido</span>`
            : '';

        // Ventajas
        const ventajasHTML = (esc.ventajas || [])
            .map(v => `<li>${_escaparHTML(v)}</li>`)
            .join('');

        // Desventajas
        const desventajasHTML = (esc.desventajas || [])
            .map(d => `<li>${_escaparHTML(d)}</li>`)
            .join('');

        // Nota al pie
        const notaHTML = esc.nota
            ? `<div class="escenario-nota"><span class="ui-emoji" aria-hidden="true">📌</span>${_escaparHTML(esc.nota)}</div>`
            : '';

        // Métricas
        const beneficioFmt = _formatearMoneda(esc.beneficio_estimado);
        const costoFmt     = _formatearMoneda(esc.costo_estimado);
        const vbpFmt       = _formatearMoneda(esc.vbp);
        const vbpPositivo  = (esc.vbp || 0) >= 0;

        return `
            <div class="escenario-card ${esRecomendado ? 'escenario-recomendado' : ''}"
                 id="escenario-card-${_escaparHTML(codigo)}"
                 data-codigo="${_escaparHTML(codigo)}">

                <div class="escenario-card-header" tabindex="0" role="button"
                     aria-expanded="false"
                     aria-controls="escenario-body-${_escaparHTML(codigo)}">
                    <div class="escenario-codigo" aria-hidden="true">${_escaparHTML(codigo)}</div>
                    <div class="escenario-card-header-info">
                        <p class="escenario-nombre">${_escaparHTML(esc.nombre || '')} ${badgeRecomendado}</p>
                        <p class="escenario-duracion">
                            ⏱ ${_escaparHTML(String(esc.duracion_min_meses || '?'))}–${_escaparHTML(String(esc.duracion_max_meses || '?'))} meses
                            · Riesgo: ${_escaparHTML(String(esc.riesgo_institucional || '—'))}/5
                        </p>
                    </div>
                    <span class="escenario-toggle-icon" aria-hidden="true">▼</span>
                </div>

                <div class="escenario-card-body"
                     id="escenario-body-${_escaparHTML(codigo)}"
                     role="region"
                     aria-label="Detalle del escenario ${_escaparHTML(codigo)}">

                    <p class="escenario-descripcion">${_escaparHTML(esc.descripcion || '')}</p>

                    <div class="escenario-metricas">
                        <div class="metrica-item">
                            <span class="metrica-label">Beneficio Est.</span>
                            <span class="metrica-valor monto-positivo">${beneficioFmt}</span>
                        </div>
                        <div class="metrica-item">
                            <span class="metrica-label">Costo Est.</span>
                            <span class="metrica-valor monto-negativo">${costoFmt}</span>
                        </div>
                        <div class="metrica-item">
                            <span class="metrica-label">VBP (Neto)</span>
                            <span class="metrica-valor ${vbpPositivo ? 'monto-positivo' : 'monto-negativo'}">${vbpFmt}</span>
                        </div>
                        <div class="metrica-item">
                            <span class="metrica-label">Intervención</span>
                            <span class="metrica-valor" style="font-size:0.88rem;">
                                ${_escaparHTML(esc.intervencion_desc || esc.nivel_intervencion || '—')}
                            </span>
                        </div>
                    </div>

                    <div class="escenario-ventajas">
                        <h4>Ventajas</h4>
                        <ul>${ventajasHTML}</ul>
                    </div>

                    <div class="escenario-desventajas">
                        <h4>Desventajas</h4>
                        <ul>${desventajasHTML}</ul>
                    </div>

                    ${notaHTML}
                </div>
            </div>
        `;
    }).join('');

    contenedor.innerHTML = cards;
}

/**
 * _renderTablaExposicion(exposicion) — Renderiza la tabla de conceptos de
 * exposición económica estimada.
 *
 * Separa los conceptos base de las multas y muestra totales parciales.
 * Contenedor destino: #exposicion-table-container
 *
 * @param {Object} exposicion - Objeto exposicion de IrilEngine::calcularExposicion().
 */
function _renderTablaExposicion(exposicion) {
    const contenedor = document.getElementById('exposicion-table-container');
    if (!contenedor) {
        console.warn('[Resultados] No se encontró #exposicion-table-container.');
        return;
    }

    const conceptos = exposicion.conceptos || {};
    const claves = Object.keys(conceptos);

    if (!claves.length) {
        contenedor.innerHTML = `
            <p class="texto-ayuda">
                No se calcularon conceptos de exposición económica para este tipo de conflicto.
            </p>
        `;
        return;
    }

    // ── Construir filas de la tabla ───────────────────────────────────────────
    const filas = claves.map(clave => {
        const concepto = conceptos[clave];

        // Si el concepto tiene aplica === false, no lo mostramos
        if (concepto.aplica === false) return '';

        const esMulta = clave.includes('multa');
        const monto   = _formatearMoneda(concepto.monto || 0);

        const condicion = concepto.condicion
            ? `<span class="exposicion-condicion">${_escaparHTML(concepto.condicion)}</span>`
            : '';

        const baseLegal = concepto.base_legal
            ? `<span class="base-legal-tag">${_escaparHTML(concepto.base_legal)}</span>`
            : '';

        return `
            <tr class="${esMulta ? 'fila-multa' : ''}">
                <td>
                    ${_escaparHTML(concepto.descripcion || clave)}
                    ${condicion}
                </td>
                <td>
                    ${baseLegal}
                </td>
                <td>${monto}</td>
            </tr>
        `;
    }).join('');

    const resultadosClave = exposicion.resultados_clave || {};
    const resumenFooter = resultadosClave.exposicion_maxima_real_con_costas
        ? `
            <tr class="total-base">
                <td colspan="2"><strong>Exposición ART segura</strong></td>
                <td><strong>${_formatearMoneda(resultadosClave.exposicion_art_segura || 0)}</strong></td>
            </tr>
            <tr class="total-base">
                <td colspan="2"><strong>Exposición civil probable</strong></td>
                <td><strong>${_formatearMoneda(resultadosClave.exposicion_civil_probable || 0)}</strong></td>
            </tr>
            <tr class="total-multas">
                <td colspan="2"><strong>Exposición máxima real con costas</strong></td>
                <td><strong>${_formatearMoneda(resultadosClave.exposicion_maxima_real_con_costas || 0)}</strong></td>
            </tr>
            <tr class="total-base">
                <td colspan="2"><strong>Estrategia sugerida</strong></td>
                <td><strong>${_escaparHTML(String(resultadosClave.estrategia_sugerida || 'art').toUpperCase())}</strong></td>
            </tr>
        `
        : `
            <tr class="total-base">
                <td colspan="2"><strong>Total Base (sin multas)</strong></td>
                <td><strong>${_formatearMoneda(exposicion.total_base || 0)}</strong></td>
            </tr>
            <tr class="total-multas">
                <td colspan="2"><strong>Total con Multas Posibles</strong></td>
                <td><strong>${_formatearMoneda(exposicion.total_con_multas || 0)}</strong></td>
            </tr>
        `;

    const html = `
        <table class="exposicion-table"
               role="table"
               aria-label="Tabla de exposición económica estimada">
            <thead>
                <tr>
                    <th scope="col">Concepto</th>
                    <th scope="col">Base Legal</th>
                    <th scope="col">Monto Est.</th>
                </tr>
            </thead>
            <tbody>
                ${filas}
            </tbody>
            <tfoot>
                ${resumenFooter}
            </tfoot>
        </table>
        <p class="exposicion-nota-legal">
            <span class="ui-emoji" aria-hidden="true">⚖️</span>${_escaparHTML(exposicion.nota_legal || 'Estimación estructural bajo LCT. No garantiza resultado.')}
        </p>
    `;

    contenedor.innerHTML = html;
}

// =============================================================================
// UTILIDADES PRIVADAS
// =============================================================================

/**
 * _obtenerNivelIril(score) — Devuelve el objeto de nivel IRIL
 * correspondiente al score indicado.
 *
 * @param {number} score
 * @returns {{ min, max, color, etiqueta, clase }|null}
 */
function _obtenerNivelIril(score) {
    for (const nivel of Object.values(NIVELES_IRIL)) {
        if (score >= nivel.min && score <= nivel.max) return nivel;
    }
    return NIVELES_IRIL.critico; // fallback para scores fuera de rango
}

/**
 * _colorPorValor(valor) — Retorna un color hex interpolado para la mini
 * barra de progreso de las dimensiones IRIL (1=verde, 5=rojo).
 *
 * @param {number} valor - Entre 1 y 5.
 * @returns {string} Color hex.
 */
function _colorPorValor(valor) {
    if (valor <= 2.4) return '#28a745';
    if (valor <= 3.4) return '#ffc107';
    if (valor <= 3.9) return '#fd7e14';
    return '#dc3545';
}

/**
 * _formatearMoneda(numero) — Formatea un número como moneda en pesos
 * argentinos con separadores de miles y dos decimales.
 *
 * Ejemplo: 152300.5 → "$ 152.300,50"
 *
 * @param {number} numero
 * @returns {string}
 */
function _formatearMoneda(numero) {
    if (numero === null || numero === undefined || isNaN(numero)) return '$ —';

    const abs = Math.abs(numero);
    const signo = numero < 0 ? '−' : '';

    // Formatear usando Intl si está disponible (mejor soporte)
    try {
        const fmt = new Intl.NumberFormat('es-AR', {
            style:                 'currency',
            currency:              'ARS',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(abs);
        return signo + fmt;
    } catch (_) {
        // Fallback manual si Intl no está disponible
        const partes = abs.toFixed(2).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `${signo}$ ${partes[0]},${partes[1]}`;
    }
}

/**
 * _formatearFecha(fechaISO) — Convierte una fecha ISO (YYYY-MM-DD) a formato
 * legible en español (DD/MM/YYYY).
 *
 * @param {string} fechaISO
 * @returns {string}
 */
function _formatearFecha(fechaISO) {
    if (!fechaISO) return '—';
    const partes = fechaISO.split('-');
    if (partes.length !== 3) return fechaISO;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

/**
 * _escaparHTML(texto) — Escapa caracteres especiales HTML para prevenir XSS
 * al insertar datos del servidor directamente en el DOM.
 *
 * @param {string} texto
 * @returns {string}
 */
function _escaparHTML(texto) {
    if (texto === null || texto === undefined) return '';
    const str = String(texto);
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

/**
 * _mostrarErrorCritico(mensaje) — Muestra un mensaje de error prominente
 * en el cuerpo de la página cuando no se pueden cargar los datos.
 *
 * @param {string} mensaje
 */
function _mostrarErrorCritico(mensaje) {
    const cuerpo = document.getElementById('resultados-body') || document.body;
    const div = document.createElement('div');
    div.setAttribute('role', 'alert');
    div.style.cssText = `
        max-width: 640px;
        margin: 3rem auto;
        padding: 1.5rem 2rem;
        background: #fff5f5;
        border: 2px solid #dc3545;
        border-radius: 10px;
        text-align: center;
        font-family: 'Segoe UI', sans-serif;
        color: #721c24;
    `;
    div.innerHTML = `
        <div style="margin-bottom:0.75rem;"><span class="ui-emoji ui-emoji--status" aria-hidden="true">⚠️</span></div>
        <h2 style="margin:0 0 0.5rem;font-size:1.2rem;">Error al cargar el análisis</h2>
        <p style="margin:0;font-size:0.9rem;">${_escaparHTML(mensaje)}</p>
        <a href="index.php" style="display:inline-block;margin-top:1.25rem;padding:0.5rem 1.25rem;
           background:#2a64b6;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">
            Volver al formulario
        </a>
    `;
    cuerpo.prepend(div);
}

// =============================================================================
// EXPOSICIÓN PÚBLICA
// =============================================================================

/**
 * Exponer las funciones que el HTML puede llamar directamente (onclick, etc.)
 * y las que se llaman desde PHP o desde otros scripts.
 */
window.renderGaugeIril         = renderGaugeIril;
window.renderTablaComparativa  = renderTablaComparativa;
window.renderAlertasActivas    = renderAlertasActivas;
window.initAccordion           = initAccordion;
window.btnDescargarInforme     = btnDescargarInforme;
