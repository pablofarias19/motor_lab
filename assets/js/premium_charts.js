/**
 * premium_charts.js — Generación de gráficos Radar y Barras para el dashboard premium
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.analisisData === 'undefined' || !window.analisisData) return;

    const data = window.analisisData;

    // 1. Inicializar Gráfico de Radar (Análisis Comparativo)
    initRadarChart(data.iril.detalle);

    // 2. Inicializar Gráfico de Barras (Índice Estratégico)
    initBarChart(data.escenarios, data.recomendado);
});

function initRadarChart(detalle) {
    const ctx = document.getElementById('radarChart');
    if (!ctx) return;

    const labels = [
        'Saturación',
        'Complejidad',
        'Volatilidad',
        'Costo/Riesgo',
        'Multiplicador'
    ];

    const values = [
        parseFloat(detalle.saturacion_tribunalicia?.valor) || 0,
        parseFloat(detalle.complejidad_probatoria?.valor) || 0,
        parseFloat(detalle.volatilidad_normativa?.valor) || 0,
        parseFloat(detalle.riesgo_costas?.valor) || 0,
        parseFloat(detalle.riesgo_multiplicador?.valor) || 0
    ];

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Dimensiones de Riesgo',
                data: values,
                fill: true,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgb(59, 130, 246)',
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgb(59, 130, 246)',
                borderWidth: 2
            }]
        },
        options: {
            scales: {
                r: {
                    min: 0,
                    max: 5,
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        display: false
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    angleLines: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    pointLabels: {
                        font: {
                            size: 10,
                            family: "'Inter', sans-serif",
                            weight: '500'
                        },
                        color: '#64748b'
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            maintainAspectRatio: false,
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            }
        }
    });
}

function initBarChart(escenarios, recomendado) {
    const ctx = document.getElementById('barChart');
    if (!ctx) return;

    const letters = ['A', 'B', 'C', 'D'].filter(l => escenarios[l]);

    // Calcular score relativo (VBP / maxVBP * 100)
    const maxVbp = Math.max(...Object.values(escenarios).map(e => Math.abs(e.vbp || 0)), 1);

    const labels = letters.map(l => l);
    const dataValues = letters.map(l => {
        const vbp = Math.abs(escenarios[l].vbp || 0);
        return Math.round((vbp / maxVbp) * 100);
    });

    const colors = letters.map(l => {
        if (l === 'A') return '#3b82f6'; // Azul
        if (l === 'B') return '#ef4444'; // Rojo
        if (l === 'C') return '#10b981'; // Verde
        return '#6b7280'; // Gris
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: colors,
                borderRadius: 4,
                barThickness: 30
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { display: false },
                    grid: { display: false }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            },
            maintainAspectRatio: false
        }
    });
}
