<?php
/**
 * parametros_motor.php — Configuración de variables estratégicas y pesos del sistema.
 * 
 * Este archivo centraliza todas las variables numéricas (porcentajes, tasas y pesos)
 * que el sistema utiliza para calcular los riesgos e indicadores de los escenarios.
 */

return [
    // ─────────────────────────────────────────────────────────────────────────
    // DIMENSIONES IRIL (Índice de Riesgo Institucional Laboral)
    // ─────────────────────────────────────────────────────────────────────────
    'iril_pesos' => [
        'saturacion' => 0.20, // Peso de la Saturación Tribunalicia (provincial)
        'probatoria' => 0.25, // Peso de la Complejidad Probatoria (documentación)
        'volatilidad' => 0.15, // Peso de la Volatilidad Normativa (tipo de conflicto)
        'costas' => 0.20, // Peso del Riesgo de ser condenado en costas
        'multiplicador' => 0.20, // Peso del Riesgo Multiplicador (cantidad de empleados)
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // CÁLCULOS ESPECIALIZADOS POR TIPO DE CONFLICTO
    // ─────────────────────────────────────────────────────────────────────────
    'calculos_especificos' => [

        // GRUPO 1: ACCIDENTES LABORALES
        'accidentes' => [
            'meses_año' => 13,             // Se incluye SAC (Sueldo Anual Complementario)
            'factor_edad_limite' => 65,     // Edad límite estándar para lucro cesante
            'recargo_falta_art' => 0.20,    // Recargo si no tiene seguro (Gasto Legal + Sanción)
            'edad_fallback' => 35,          // Edad por defecto

            // ── Fórmula LRT (Art. 14.2.a Ley 24.557) — Solo vía ART ──
            'coeficiente_lrt' => 53,                // Coeficiente legal fijo
            'piso_minimo_ipp' => 2260000,           // Piso Inc. Permanente Parcial (RIPTE)
            'piso_minimo_ipd' => 4520000,           // Piso Inc. Permanente Definitiva (RIPTE)
            'piso_gran_invalidez' => 9040000,       // Adicional gran invalidez (RIPTE)
            'piso_muerte' => 6780000,               // Piso prestación por muerte (RIPTE)
            'umbral_gran_invalidez' => 66,          // % a partir del cual se considera gran invalidez
            'fecha_pisos_ripte' => '2025-09-01',    // Fecha de última actualización pisos RIPTE

            // ── Pesos IRIL específicos para ART (con tiene_art=si) ──
            'iril_pesos_art' => [
                'saturacion'    => 0.10,  // CM es administrativa, menos peso tribunalicio
                'probatoria'    => 0.30,  // Pericial médica y nexo causal son centrales
                'volatilidad'   => 0.25,  // Jurisprudencia ART muy inestable
                'costas'        => 0.15,  // En CM no hay costas; en judicial moderadas
                'multiplicador' => 0.20,  // Riesgo cascada si hay siniestralidad
            ],

            // ── Volatilidad por tipo de contingencia ART ──
            'volatilidad_contingencia' => [
                'accidente_tipico'        => 3.0,   // Nexo causal claro
                'in_itinere'              => 4.5,   // Nexo causal disputado
                'enfermedad_profesional'  => 5.0,   // Listado cerrado, máxima incertidumbre
            ],

            // ── Escenarios ART (reemplazan A/B/C/D cuando tiene_art=si) ──
            'escenarios_art' => [
                'aceptacion_cm' => [
                    'tasa_recupero' => 1.00,        // 100% tarifa LRT
                    'factor_honorarios' => 0.10,    // Honorarios mínimos
                    'duracion_promedio' => 3,       // Meses
                    'duracion_min' => 1,
                    'duracion_max' => 6,
                    'factor_riesgo' => 0.20,        // Riesgo bajo en aceptación
                ],
                'impugnacion_cm' => [
                    'tasa_recupero' => 1.15,        // 115% tarifa (mejora dictamen)
                    'factor_honorarios' => 0.40,
                    'duracion_promedio' => 12,
                    'duracion_min' => 6,
                    'duracion_max' => 18,
                    'factor_riesgo' => 0.60,
                ],
                'judicial_laboral' => [
                    'tasa_recupero' => 1.30,        // 130% tarifa + intereses
                    'factor_honorarios' => 0.60,
                    'duracion_promedio' => 36,
                    'duracion_min' => 24,
                    'duracion_max' => 48,
                    'factor_riesgo' => 0.85,
                ],
                'civil_complementaria' => [
                    'factor_civil_sobre_tarifa' => 2.50, // Méndez/Vuotto ~2.5x tarifa
                    'factor_honorarios' => 0.80,
                    'duracion_promedio' => 48,
                    'duracion_min' => 36,
                    'duracion_max' => 60,
                    'factor_riesgo' => 0.95,
                ],
            ],

            // ── Caducidades ART por provincia ──
            'caducidad_impugnacion_cm_dias' => [
                'Buenos Aires' => 90,
                'CABA'         => 30,
                'default'      => 90,
            ],
            'prescripcion_art_anos' => 2,
        ],

        // GRUPO 2: DIFERENCIAS SALARIALES (Deuda Histórica)
        'diferencias_salariales' => [
            'factor_mala_categorizacion' => 0.20, // 20% del sueldo por diferencia de CCT
            'factor_horas_extras' => 0.25,        // 25% del sueldo en horas no pagas
            'factor_falta_pago' => 1.00,          // 100% del sueldo (deuda total)
            'meses_fallback' => 12,               // Si no indica meses, estimamos 1 año
        ],

        // GRUPO 3: PREVENCIÓN Y EMPRESAS (ARCA / Inspecciones)
        'prevencion' => [
            'multa_base_inspeccion' => 10,       // Cantidad de sueldos ante inspección normal
            'multa_reincidencia_arca' => 15,     // Cantidad de sueldos si ya hubo inspecciones
            'factor_solidaridad_art30' => 6,      // 6 sueldos de riesgo por cada subcontratista

            'riesgo_auditoria_critico' => 20,     // Alto incumplimiento detectado
            'riesgo_auditoria_desconocido' => 10, // Falta de datos
            'riesgo_auditoria_estable' => 5,      // Cumplimiento total o parcial
        ],

        // GRUPO 4: REGISTRO LABORAL (Validaciones Art. 23/30 Ley 27.802)
        // [Nota: Multas Ley 24.013 y 25.323 fueron derogadas — véase ACTUALIZACION_MARZO_2026.md]
        'registro' => [
            'facturacion_reduce_presuncion' => true, // Art. 23: facturación ≠ presunción laboral
            'pago_bancario_reduce_presuncion' => true, // Art. 23: pago registrado ≠ presunción
            'art_30_controles_requeridos' => 5,       // Art. 30: 5 controles para exención solidaria
        ]
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // ESCENARIOS ESTRATÉGICOS
    // ─────────────────────────────────────────────────────────────────────────
    'escenarios' => [

        // ESCENARIO A: NEGOCIACIÓN TEMPRANA
        'negociacion_temprana' => [
            'tasa_recupero_base' => 0.70, // % del capital base que se logra en acuerdo (0.70 = 70%)
            'factor_honorarios' => 0.30, // Honorarios son el 30% de los judiciales plenos
            'duracion_promedio' => 3,    // Meses promedio de resolución
            'factor_riesgo' => 0.40, // El riesgo IRIL se reduce al 40% al no ir a juicio
        ],

        // ESCENARIO B: LITIGIO COMPLETO
        'litigio_completo' => [
            'tasa_recupero_total' => 1.00, // % que se busca (100% incluye multas legales)
            'factor_costas_riesgo' => 0.50, // % de las costas totales que se consideran riesgo real
            'duracion_promedio' => 36,   // Meses promedio (3 años en Argentina)
            'factor_riesgo' => 0.90, // El riesgo IRIL se aplica casi al 90% (máxima fricción)
        ],

        // ESCENARIO C: ESTRATEGIA MIXTA (SECLO / Conciliación)
        'estrategia_mixta' => [
            'tasa_recupero_base' => 0.82, // % del capital base intermedio (82%)
            'factor_honorarios' => 0.60, // Honorarios son el 60% de los judiciales plenos
            'duracion_promedio' => 10,   // Meses promedio (6-18 meses)
            'factor_riesgo' => 0.60, // Riesgo moderado por instancia administrativa
        ],

        // ESCENARIO D: RECONFIGURACIÓN PREVENTIVA (Empresas)
        'preventivo' => [
            'factor_costo_regularizacion' => 1.5, // Costo estimado en sueldos por empleado (1.5)
            'meses_ahorro_litigio' => 12,  // Ahorro estimado de 12 meses de litigio evitado
            'duracion_promedio' => 2,   // Meses implementación
        ],

        // CONFIGURACIÓN GLOBAL DE COSTOS
        'global' => [
            'honorarios_judiciales_tasa' => 0.22, // Tasa estándar de honorarios (22%)
            'costas_judiciales_tasa' => 0.20, // Tasa estándar de costas (20%)
        ]
    ]
];
