/**
 * motor_laboral/admin/assets/js/admin.js
 * JavaScript del panel de administración del Motor de Riesgo Laboral.
 *
 * Funciones:
 *  - Confirmación de eliminación / acciones peligrosas
 *  - Copia de UUID al portapapeles con feedback visual
 *  - Auto-submit de filtros al cambiar selects
 *  - Resaltar filas de análisis crítico en tablas
 *  - Tooltips Bootstrap
 *  - Mini gráfico de barras en dashboard (si Chart.js está disponible)
 */

'use strict';

/* ─────────────────────────────────────────────────────────────────────────────
   INICIALIZACIÓN
───────────────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initTooltips();
    initCopiarUUID();
    initFiltrosAutosubmit();
    resaltarCriticos();
    initConfirmaciones();
    renderGraficoDashboard();
});

/* ─────────────────────────────────────────────────────────────────────────────
   TOOLTIPS BOOTSTRAP
───────────────────────────────────────────────────────────────────────────── */
function initTooltips() {
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(el => new bootstrap.Tooltip(el, { trigger: 'hover' }));
}

/* ─────────────────────────────────────────────────────────────────────────────
   COPIAR UUID AL PORTAPAPELES
   Uso: <span class="uuid-tag copiable" data-uuid="...">abc-123</span>
───────────────────────────────────────────────────────────────────────────── */
function initCopiarUUID() {
    document.querySelectorAll('.uuid-tag.copiable').forEach(el => {
        el.style.cursor = 'pointer';
        el.title = 'Clic para copiar UUID';

        el.addEventListener('click', () => {
            const texto = el.dataset.uuid || el.textContent.trim();
            navigator.clipboard.writeText(texto).then(() => {
                const original = el.textContent;
                el.textContent = '✓ Copiado';
                el.style.background = '#d4edda';
                el.style.color = '#155724';
                setTimeout(() => {
                    el.textContent = original;
                    el.style.background = '';
                    el.style.color = '';
                }, 1500);
            }).catch(() => {
                // Fallback para navegadores sin clipboard API
                const ta = document.createElement('textarea');
                ta.value = texto;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   AUTO-SUBMIT DE FILTROS
   Los <select> con clase .filtro-autosubmit dentro de un <form>
   disparan el submit al cambiar.
───────────────────────────────────────────────────────────────────────────── */
function initFiltrosAutosubmit() {
    document.querySelectorAll('.filtro-autosubmit').forEach(select => {
        select.addEventListener('change', () => {
            const form = select.closest('form');
            if (form) form.submit();
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   RESALTAR FILAS IRIL CRÍTICO
   Busca <tr data-iril-nivel="critico"> y añade clase de fondo suave.
───────────────────────────────────────────────────────────────────────────── */
function resaltarCriticos() {
    document.querySelectorAll('tr[data-iril-nivel="critico"]').forEach(tr => {
        tr.style.borderLeft = '3px solid #dc3545';
    });
    document.querySelectorAll('tr[data-iril-nivel="alto"]').forEach(tr => {
        tr.style.borderLeft = '3px solid #fd7e14';
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   CONFIRMACIONES PARA ACCIONES PELIGROSAS
   Uso: <button class="btn-confirmar" data-msg="¿Seguro?">Eliminar</button>
───────────────────────────────────────────────────────────────────────────── */
function initConfirmaciones() {
    document.querySelectorAll('.btn-confirmar').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const msg = btn.dataset.msg || '¿Confirmar esta acción?';
            if (!confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   GRÁFICO DISTRIBUCIÓN IRIL — Dashboard
   Busca el canvas #grafico-iril con atributos data-bajo, data-moderado,
   data-alto, data-critico y renderiza un doughnut Chart.js.
───────────────────────────────────────────────────────────────────────────── */
function renderGraficoDashboard() {
    const canvas = document.getElementById('grafico-iril');
    if (!canvas || typeof Chart === 'undefined') return;

    const bajo     = parseInt(canvas.dataset.bajo     || 0);
    const moderado = parseInt(canvas.dataset.moderado || 0);
    const alto     = parseInt(canvas.dataset.alto     || 0);
    const critico  = parseInt(canvas.dataset.critico  || 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Bajo', 'Moderado', 'Alto', 'Crítico'],
            datasets: [{
                data: [bajo, moderado, alto, critico],
                backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 12 }, padding: 14 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} análisis`
                    }
                }
            },
            cutout: '60%'
        }
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   GRÁFICO DE CONFLICTOS — Dashboard (barras horizontales)
   Canvas #grafico-conflictos con atributo data-conflictos (JSON array).
   Formato: [{ tipo_conflicto: "...", cantidad: N }, ...]
───────────────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('grafico-conflictos');
    if (!canvas || typeof Chart === 'undefined') return;

    let datos = [];
    try {
        datos = JSON.parse(canvas.dataset.conflictos || '[]');
    } catch (_) { return; }

    if (!datos.length) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: datos.map(d => d.tipo_conflicto),
            datasets: [{
                label: 'Análisis',
                data: datos.map(d => parseInt(d.cantidad)),
                backgroundColor: '#2a64b6',
                borderRadius: 4,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',     // barras horizontales
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { size: 11 } },
                    grid: { color: '#f0f0f0' }
                },
                y: {
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
});
