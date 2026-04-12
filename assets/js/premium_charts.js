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

    const labels = letters.map(l => l);
    const dataValues = letters.map(l => Number.parseFloat(escenarios[l].indice_estrategico || 0));

    const colors = letters.map(l => {
        if (l === recomendado) return '#1d4ed8';
        if (l === 'A') return '#60a5fa';
        if (l === 'B') return '#f87171';
        if (l === 'C') return '#34d399';
        return '#9ca3af';
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
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            return `Índice Estratégico: ${Number(context.parsed.y).toFixed(1)}`;
                        }
                    }
                }
            },
            maintainAspectRatio: false
        }
    });
}
