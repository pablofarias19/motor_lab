<?php
/**
 * IrilEngine.php — Motor de cálculo del Índice de Riesgo Institucional Laboral
 *
 * El IRIL (Índice de Riesgo Institucional Laboral) es una puntuación entre
 * 1.0 y 5.0 que mide la complejidad estructural de un conflicto laboral.
 *
 * NO mide probabilidad de ganar o perder.
 * NO promete resultado judicial.
 * Mide fricción procesal, exposición económica y riesgo estructural.
 *
 * 5 dimensiones con pesos específicos:
 *   - Saturación tribunalicia    (20%) — según provincia
 *   - Complejidad probatoria     (25%) — según documentación disponible
 *   - Volatilidad normativa      (15%) — según tipo de conflicto
 *   - Riesgo de costas           (20%) — según posición procesal
 *   - Riesgo multiplicador       (20%) — según empleados afectados
 *
 * Además calcula la exposición económica estimada según LCT.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ripte_functions.php'; // ← Para validar_ley_bases()

class IrilEngine
{

    // ─────────────────────────────────────────────────────────────────────────
    // TABLAS DE REFERENCIA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Saturación tribunalicia por provincia (1=baja, 5=muy alta).
     * Basado en carga procesal histórica de los fueros laborales argentinos.
     */
    private const SATURACION_PROVINCIAL = [
        'CABA' => 5.0,  // Fuero Nacional del Trabajo — máxima saturación
        'Buenos Aires' => 4.5,
        'Córdoba' => 3.5,
        'Santa Fe' => 3.5,
        'Mendoza' => 3.0,
        'Tucumán' => 3.0,
        'Entre Ríos' => 2.5,
        'Salta' => 2.5,
        'Corrientes' => 2.0,
        'Misiones' => 2.0,
        'Chaco' => 2.0,
        'Santiago del Estero' => 1.5,
        'Jujuy' => 2.0,
        'San Juan' => 2.0,
        'San Luis' => 1.5,
        'Catamarca' => 1.5,
        'La Rioja' => 1.5,
        'Neuquén' => 2.5,
        'Río Negro' => 2.0,
        'Chubut' => 2.0,
        'Santa Cruz' => 1.5,
        'Tierra del Fuego' => 1.5,
        'Formosa' => 1.5,
        'La Pampa' => 2.0,
        'Internacional' => 3.0,  // Operaciones internacionales
    ];

    /**
     * Volatilidad normativa por tipo de conflicto (1=estable, 5=muy volátil).
     * Refleja cuánto varía la interpretación jurisprudencial del conflicto.
     */
    private const VOLATILIDAD_CONFLICTO = [
        'despido_sin_causa' => 2.0,  // Doctrina clara, LCT consolidada
        'despido_con_causa' => 3.5,  // Depende de la causal, más volatilidad
        'diferencias_salariales' => 2.5,
        'trabajo_no_registrado' => 3.0,  // Art. 23 LCT: presunción laboral variable según facturación
        'accidente_laboral' => 4.5,  // LRT + SCBA muy volátil, gran disparidad
        'reclamo_indemnizatorio' => 3.0,
        'multas_legales' => 2.5,  // Multas Ley 25.345/25.323 más predecibles
        'responsabilidad_solidaria' => 4.0,  // Muy variable por jurisprudencia
        'riesgo_inspeccion' => 2.0,
        'auditoria_preventiva' => 1.0,  // Sin litigio, sin volatilidad
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODO PRINCIPAL — calcular IRIL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * calcularIRIL() — Calcula el índice IRIL completo con desglose por dimensión.
     *
     * @param array $datosLaborales   Salario, antigüedad, provincia, categoría, CCT
     * @param array $documentacion    Telegramas, recibos, testigos, ARCA
     * @param array $situacion        Plazo, intercambio epistolar, urgencia, empleados
     * @param string $tipoConflicto   Clave del tipo de conflicto
     * @param string $tipoUsuario     'empleado' | 'empleador'
     * @return array ['score' => float, 'detalle' => array, 'alertas' => array]
     */
    public function calcularIRIL(
        array $datosLaborales,
        array $documentacion,
        array $situacion,
        string $tipoConflicto,
        string $tipoUsuario
    ): array {

        // Cargar parámetros estratégicos
        $params = require __DIR__ . '/parametros_motor.php';

        // ── Pesos IRIL: dinámicos para ART con cobertura, genéricos para el resto ──
        $esArtConCobertura = ($tipoConflicto === 'accidente_laboral')
            && (($situacion['tiene_art'] ?? 'no') === 'si');

        $pesos = $esArtConCobertura
            ? $params['calculos_especificos']['accidentes']['iril_pesos_art']
            : $params['iril_pesos'];
        $perfilJurisdiccionalArt = $esArtConCobertura
            ? $this->resolvePerfilJurisdiccionalART((string) ($datosLaborales['provincia'] ?? 'CABA'), $params)
            : [];

        // Calcular cada dimensión
        $saturacion = $this->calcularSaturacion($datosLaborales['provincia'] ?? 'CABA');
        $probatoria = $esArtConCobertura
            ? $this->calcularComplejidadProbatoriaART($documentacion, $situacion)
            : $this->calcularComplejidadProbatoria($documentacion, $tipoUsuario);
        $volatilidad = $esArtConCobertura
            ? $this->calcularVolatilidadART($situacion, $params, $perfilJurisdiccionalArt)
            : $this->calcularVolatilidad($tipoConflicto);
        $costas = $this->calcularRiesgoCostas($situacion, $tipoUsuario, $perfilJurisdiccionalArt);
        $multiplicador = $this->calcularRiesgoMultiplicador($situacion);

        // Aplicar pesos desde la configuración
        $score = ($saturacion * $pesos['saturacion'])
            + ($probatoria * $pesos['probatoria'])
            + ($volatilidad * $pesos['volatilidad'])
            + ($costas * $pesos['costas'])
            + ($multiplicador * $pesos['multiplicador']);

        // Redondear a 1 decimal, limitar entre 1.0 y 5.0
        $score = round(max(1.0, min(5.0, $score)), 1);

        // Generar alertas basadas en el análisis
        $alertas = $this->generarAlertas($datosLaborales, $documentacion, $situacion, $tipoConflicto, $score);

        // Descripciones adaptadas según si es ART con cobertura o genérico
        $descProbatoria = $esArtConCobertura
            ? 'Calidad de prueba médica, nexo causal y documentación ART'
            : 'Nivel de respaldo documental disponible';
        $descVolatilidad = $esArtConCobertura
            ? 'Variabilidad jurisprudencial según tipo de contingencia ART'
            : 'Variabilidad interpretativa del tipo de conflicto';

        $resultado = [
            'score' => $score,
            'nivel' => ml_nivel_iril($score),
            'detalle' => [
                'saturacion_tribunalicia' => [
                    'valor' => $saturacion,
                    'peso' => round($pesos['saturacion'] * 100) . '%',
                    'descripcion' => 'Carga procesal del fuero laboral en ' . ($datosLaborales['provincia'] ?? 'su provincia')
                ],
                'complejidad_probatoria' => [
                    'valor' => $probatoria,
                    'peso' => round($pesos['probatoria'] * 100) . '%',
                    'descripcion' => $descProbatoria
                ],
                'volatilidad_normativa' => [
                    'valor' => $volatilidad,
                    'peso' => round($pesos['volatilidad'] * 100) . '%',
                    'descripcion' => $descVolatilidad
                ],
                'riesgo_costas' => [
                    'valor' => $costas,
                    'peso' => round($pesos['costas'] * 100) . '%',
                    'descripcion' => 'Exposición a condena en costas según posición procesal'
                ],
                'riesgo_multiplicador' => [
                    'valor' => $multiplicador,
                    'peso' => round($pesos['multiplicador'] * 100) . '%',
                    'descripcion' => 'Potencial efecto cascada en otros empleados'
                ],
            ],
            'alertas' => $alertas,
        ];

        if ($esArtConCobertura) {
            $resultado['perfil_jurisdiccional'] = $perfilJurisdiccionalArt;
            $resultado['senales_art'] = $this->buildSenalesArt($documentacion, $situacion);
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DIMENSIONES IRIL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * calcularSaturacion() — Devuelve el índice de saturación del fuero laboral
     * de la provincia indicada.
     */
    private function calcularSaturacion(string $provincia): float
    {
        return self::SATURACION_PROVINCIAL[$provincia]
            ?? 2.5; // valor por defecto si la provincia no está en la tabla
    }

    /**
     * calcularComplejidadProbatoria() — Evalúa el riesgo probatorio según
     * la documentación disponible declarada por el usuario.
     *
     * A más documentación → menor complejidad probatoria → menor score.
     * Perspectiva: empleado sin documentos = alto riesgo.
     *              empleador sin documentos = también alto riesgo (puede ser demandado).
     */
    private function calcularComplejidadProbatoria(array $doc, string $tipoUsuario): float
    {
        $score = 5.0; // empezamos en máximo y descontamos según lo que tiene

        // ─── ART. 23 LCT (PRESUNCIÓN LABORAL) ────────────────────────────────
        // Presunción NO OPERA cuando existe: facturación + pago bancario + contrato
        if (!empty($doc['tiene_facturacion']) && $doc['tiene_facturacion'] === 'si'
            && !empty($doc['pago_bancario']) && $doc['pago_bancario'] === 'si') {
            // Reducción de complejidad: la presunción se mantiene pero requiere prueba reforzada de dependencia
            $score -= 0.5;  // Ligera reducción (la ausencia de presunción ≠ prueba ganada)
        }

        // Telegramas intercambiados (-0.8 si existen)
        if (!empty($doc['tiene_telegramas']) && $doc['tiene_telegramas'] === 'si') {
            $score -= 0.8;
        }

        // Recibos de sueldo firmados (-1.0 si existen)
        if (!empty($doc['tiene_recibos']) && $doc['tiene_recibos'] === 'si') {
            $score -= 1.0;
        }

        // Contrato de trabajo escrito (-0.7)
        if (!empty($doc['tiene_contrato']) && $doc['tiene_contrato'] === 'si') {
            $score -= 0.7;
        }

        // Registro en ARCA / aportes al día (-0.8)
        if (!empty($doc['registrado_afip']) && $doc['registrado_afip'] === 'si') {
            $score -= 0.8;
        }

        // Testigos disponibles (-0.5)
        if (!empty($doc['tiene_testigos']) && $doc['tiene_testigos'] === 'si') {
            $score -= 0.5;
        }

        // Si es empleador con todo en regla, reducción adicional
        if ($tipoUsuario === 'empleador' && !empty($doc['auditoria_previa']) && $doc['auditoria_previa'] === 'si') {
            $score -= 0.5;
        }

        return max(1.0, round($score, 1));
    }

    /**
     * calcularVolatilidad() — Volatilidad normativa según tipo de conflicto.
     */
    private function calcularVolatilidad(string $tipoConflicto): float
    {
        return self::VOLATILIDAD_CONFLICTO[$tipoConflicto] ?? 3.0;
    }

    /**
     * calcularComplejidadProbatoriaART() — Evalúa el riesgo probatorio específico
     * para casos ART con cobertura. La pericial médica y el nexo causal son centrales.
     */
    private function calcularComplejidadProbatoriaART(array $doc, array $situacion): float
    {
        $score = 5.0;

        // Denuncia ante ART aceptada → nexo causal reconocido (-1.5)
        if (($situacion['denuncia_art'] ?? 'no') === 'si' && ($situacion['rechazo_art'] ?? 'no') === 'no') {
            $score -= 1.5;
        }

        // Dictamen CM emitido → hay evaluación médica oficial (-1.0)
        $estadoCM = $situacion['comision_medica'] ?? 'no_iniciada';
        if (in_array($estadoCM, ['dictamen_emitido', 'homologado'])) {
            $score -= 1.0;
        }

        // Tiene recibos de sueldo → IBM documentado (-0.5)
        if (($doc['tiene_recibos'] ?? 'no') === 'si') {
            $score -= 0.5;
        }

        // Tiene testigos del accidente (-0.5)
        if (($doc['tiene_testigos'] ?? 'no') === 'si') {
            $score -= 0.5;
        }

        // Registrado en ARCA → relación laboral indiscutida (-0.5)
        if (($doc['registrado_afip'] ?? 'no') === 'si') {
            $score -= 0.5;
        }

        if (($situacion['calidad_prueba_medica'] ?? 'media') === 'alta') {
            $score -= 0.7;
        } elseif (($situacion['calidad_prueba_medica'] ?? 'media') === 'baja') {
            $score += 0.5;
        }

        if (($situacion['nexo_causal'] ?? 'medio') === 'alto') {
            $score -= 0.7;
        } elseif (($situacion['nexo_causal'] ?? 'medio') === 'bajo') {
            $score += 0.6;
        }

        if (($situacion['contingencia_similar_previa'] ?? 'no') === 'si') {
            $score += 0.4;
        }

        if (($doc['documentacion_empresa_completa'] ?? 'no') === 'no') {
            $score += 0.4;
        }

        // Rechazo de ART → sube complejidad (+0.5)
        if (($situacion['rechazo_art'] ?? 'no') === 'si') {
            $score += 0.5;
        }

        return max(1.0, min(5.0, round($score, 1)));
    }

    /**
     * calcularVolatilidadART() — Volatilidad normativa específica según tipo
     * de contingencia ART (accidente típico / in itinere / enfermedad profesional).
     */
    private function calcularVolatilidadART(array $situacion, array $params, array $perfilJurisdiccional = []): float
    {
        $tipo = $situacion['tipo_contingencia'] ?? 'accidente_tipico';
        $tabla = $params['calculos_especificos']['accidentes']['volatilidad_contingencia'] ?? [];
        $base = $tabla[$tipo] ?? 3.0;
        $aperturaCivil = floatval($perfilJurisdiccional['apertura_accion_civil'] ?? 0.55);
        if ($aperturaCivil >= 0.60) {
            $base += 0.3;
        }

        return max(1.0, min(5.0, round($base, 1)));
    }

    private function resolvePerfilJurisdiccionalART(string $provincia, array $params): array
    {
        $perfiles = $params['calculos_especificos']['accidentes']['perfiles_jurisdiccionales'] ?? [];
        $perfil = $perfiles[$provincia] ?? ($perfiles['default'] ?? []);

        return [
            'jurisdiccion' => $provincia,
            'tendencia_danio_moral' => (string) ($perfil['tendencia_danio_moral'] ?? 'media'),
            'interes_anual_base' => floatval($perfil['interes_anual_base'] ?? 0.10),
            'apertura_accion_civil' => floatval($perfil['apertura_accion_civil'] ?? 0.55),
            'severidad_costas' => floatval($perfil['severidad_costas'] ?? 1.0),
        ];
    }

    private function buildSenalesArt(array $documentacion, array $situacion): array
    {
        return [
            'calidad_prueba_medica' => (string) ($situacion['calidad_prueba_medica'] ?? 'media'),
            'nexo_causal' => (string) ($situacion['nexo_causal'] ?? (($situacion['denuncia_art'] ?? 'no') === 'si' ? 'alto' : 'medio')),
            'rechazo_art' => ($situacion['rechazo_art'] ?? 'no') === 'si',
            'dictamen_cm' => (string) ($situacion['comision_medica'] ?? 'no_iniciada'),
            'documentacion_empresa_completa' => ($documentacion['documentacion_empresa_completa'] ?? 'no') === 'si',
            'contingencia_similar_previa' => ($situacion['contingencia_similar_previa'] ?? 'no') === 'si',
        ];
    }

    /**
     * calcularRiesgoCostas() — Evalúa el riesgo de ser condenado en costas.
     * Considera si ya se inició intercambio epistolar y la posición procesal.
     */
    private function calcularRiesgoCostas(array $situacion, string $tipoUsuario, array $perfilJurisdiccional = []): float
    {
        $score = 2.5; // base neutral

        // Si ya hay intercambio telegráfico → riesgo aumenta (hay registro escrito de la disputa)
        if (!empty($situacion['hay_intercambio']) && $situacion['hay_intercambio'] === 'si') {
            $score += 0.5;
        }

        // Si el empleador ya fue intimado y no respondió → riesgo alto para él
        if ($tipoUsuario === 'empleador' && !empty($situacion['fue_intimado']) && $situacion['fue_intimado'] === 'si') {
            $score += 1.0;
        }

        // Si el empleado ya se consideró despedido → riesgo moderado (fecha de evento clara)
        if ($tipoUsuario === 'empleado' && !empty($situacion['ya_despedido']) && $situacion['ya_despedido'] === 'si') {
            $score += 0.5;
        }

        // Si hay urgencia declarada → el tiempo apremia, sube el riesgo estructural
        if (!empty($situacion['urgencia']) && $situacion['urgencia'] === 'alta') {
            $score += 0.5;
        }

        $severidadCostas = floatval($perfilJurisdiccional['severidad_costas'] ?? 1.0);
        if ($severidadCostas > 1.0) {
            $score += min(0.5, ($severidadCostas - 1.0) * 2);
        }

        return max(1.0, min(5.0, round($score, 1)));
    }

    /**
     * calcularRiesgoMultiplicador() — Evalúa si el conflicto puede contagiarse
     * a otros empleados (riesgo de demandas en cadena).
     */
    private function calcularRiesgoMultiplicador(array $situacion): float
    {
        $cantEmpleados = intval($situacion['cantidad_empleados'] ?? 1);

        if ($cantEmpleados === 1)
            return 1.0; // solo un empleado → sin multiplicación
        if ($cantEmpleados <= 3)
            return 2.0;
        if ($cantEmpleados <= 10)
            return 3.0;
        if ($cantEmpleados <= 50)
            return 4.0;
        return 5.0; // empresa grande → riesgo multiplicador máximo
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPOSICIÓN ECONÓMICA (Fachada para retrocompatibilidad)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * calcularExposicion() — Estima el impacto económico estructural del conflicto
     * Delega el cálculo a la nueva clase ExposicionEngine para mantener la responsabilidad única.
     */
    public function calcularExposicion(
        array $datosLaborales,
        array $documentacion,
        array $situacion,
        string $tipoConflicto,
        string $tipoUsuario
    ): array {
        require_once __DIR__ . '/ExposicionEngine.php';
        $engine = new ExposicionEngine();
        return $engine->calcularExposicion($datosLaborales, $documentacion, $situacion, $tipoConflicto, $tipoUsuario);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SISTEMA DE ALERTAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * generarAlertas() — Detecta alertas temporales y de riesgo según el análisis.
     *
     * @return array Lista de alertas con tipo, descripción y fecha si aplica
     */
    private function generarAlertas(
        array $datosLaborales,
        array $documentacion,
        array $situacion,
        string $tipoConflicto,
        float $irilScore
    ): array {
        $alertas = [];

        // ── Prescripción laboral (2 años — Art. 256 LCT) ─────────────────────
        if (!empty($situacion['fecha_despido'])) {
            $fechaDespido = strtotime($situacion['fecha_despido']);
            $diasTranscurridos = round((time() - $fechaDespido) / 86400);
            $diasRestantes = 730 - $diasTranscurridos; // 2 años = 730 días

            if ($diasRestantes <= 90 && $diasRestantes > 0) {
                $alertas[] = [
                    'tipo' => 'prescripcion',
                    'urgencia' => $diasRestantes <= 30 ? 'critica' : 'alta',
                    'descripcion' => "Prescripción laboral: quedan aproximadamente {$diasRestantes} días para reclamar (Art. 256 LCT — 2 años).",
                    'fecha_vencimiento' => date('Y-m-d', strtotime('+' . $diasRestantes . ' days'))
                ];
            } elseif ($diasRestantes <= 0) {
                $alertas[] = [
                    'tipo' => 'prescripcion',
                    'urgencia' => 'vencida',
                    'descripcion' => 'Atención: el plazo de prescripción de 2 años puede haber vencido. Consulte urgente con un profesional.',
                    'fecha_vencimiento' => null
                ];
            }
        }

        // ── Plazo de respuesta a telegrama (48/72 hs) ─────────────────────────
        if (
            !empty($situacion['hay_intercambio']) && $situacion['hay_intercambio'] === 'si' &&
            !empty($situacion['fecha_ultimo_telegrama'])
        ) {
            $fechaTelegrama = strtotime($situacion['fecha_ultimo_telegrama']);
            $diasDesde = round((time() - $fechaTelegrama) / 86400);
            if ($diasDesde <= 3) {
                $alertas[] = [
                    'tipo' => 'respuesta_telegrama',
                    'urgencia' => 'critica',
                    'descripcion' => 'Plazo de respuesta a telegrama recibido: 48/72 horas hábiles. Actúe de inmediato.',
                    'fecha_vencimiento' => date('Y-m-d', strtotime('+2 days'))
                ];
            }
        }

        // ── Multa Art. 80 LCT (certificados — 2 días hábiles) ────────────────
        if ($tipoConflicto === 'despido_sin_causa' || $tipoConflicto === 'despido_con_causa') {
            $alertas[] = [
                'tipo' => 'certificados_art80',
                'urgencia' => 'alta',
                'descripcion' => 'Certificados de trabajo (Art. 80 LCT): el empleado puede intimar al empleador. Si no los entrega en 2 días hábiles, se activa multa de 3 salarios.',
                'fecha_vencimiento' => null
            ];
        }

        // ── Riesgo de inspección laboral (empleador sin registro) ─────────────
        if (!empty($documentacion['registrado_afip']) && $documentacion['registrado_afip'] === 'no') {
            $alertas[] = [
                'tipo' => 'inspeccion_laboral',
                'urgencia' => 'alta',
                'descripcion' => 'Relación laboral sin registro en ARCA: alto riesgo de inspección laboral con multas administrativas y penales.',
                'fecha_vencimiento' => null
            ];
        }

        // ── Alertas específicas ART (solo si tiene_art = si) ──────────────────
        if ($tipoConflicto === 'accidente_laboral' && ($situacion['tiene_art'] ?? 'no') === 'si') {
            // Prescripción ART: 2 años desde fecha del siniestro
            if (!empty($situacion['fecha_siniestro'])) {
                $fechaSiniestro = strtotime($situacion['fecha_siniestro']);
                if ($fechaSiniestro) {
                    $diasDesde = round((time() - $fechaSiniestro) / 86400);
                    $diasRestantes = 730 - $diasDesde;
                    if ($diasRestantes <= 90 && $diasRestantes > 0) {
                        $alertas[] = [
                            'tipo' => 'prescripcion_art',
                            'urgencia' => $diasRestantes <= 30 ? 'critica' : 'alta',
                            'descripcion' => "Prescripción ART: quedan aproximadamente {$diasRestantes} días para reclamar (2 años desde la primera manifestación invalidante).",
                            'fecha_vencimiento' => date('Y-m-d', strtotime('+' . $diasRestantes . ' days'))
                        ];
                    } elseif ($diasRestantes <= 0) {
                        $alertas[] = [
                            'tipo' => 'prescripcion_art',
                            'urgencia' => 'vencida',
                            'descripcion' => 'Atención: el plazo de prescripción de 2 años desde el siniestro puede haber vencido.',
                            'fecha_vencimiento' => null
                        ];
                    }
                }
            }

            // Caducidad para impugnar dictamen CM
            $estadoCM = $situacion['comision_medica'] ?? 'no_iniciada';
            if ($estadoCM === 'dictamen_emitido') {
                $params = require __DIR__ . '/parametros_motor.php';
                $provincia = $datosLaborales['provincia'] ?? 'default';
                $caducidades = $params['calculos_especificos']['accidentes']['caducidad_impugnacion_cm_dias'];
                $diasCaduc = $caducidades[$provincia] ?? $caducidades['default'] ?? 90;
                $alertas[] = [
                    'tipo' => 'caducidad_cm',
                    'urgencia' => 'critica',
                    'descripcion' => "Dictamen de Comisión Médica emitido: tiene {$diasCaduc} días para impugnar en {$provincia}. Vencido el plazo, el dictamen queda firme.",
                    'fecha_vencimiento' => null
                ];
            }

            // Vía administrativa obligatoria
            if (($situacion['via_administrativa_agotada'] ?? 'no') === 'no' && $estadoCM !== 'homologado') {
                $alertas[] = [
                    'tipo' => 'via_administrativa',
                    'urgencia' => 'alta',
                    'descripcion' => 'Ley 27.348: Debe agotar la instancia ante Comisión Médica Jurisdiccional antes de iniciar acción judicial.',
                    'fecha_vencimiento' => null
                ];
            }

            // Rechazo de ART
            if (($situacion['rechazo_art'] ?? 'no') === 'si') {
                $alertas[] = [
                    'tipo' => 'rechazo_art',
                    'urgencia' => 'alta',
                    'descripcion' => 'La ART rechazó el siniestro. Debe iniciar trámite ante Comisión Médica Jurisdiccional para impugnar el rechazo.',
                    'fecha_vencimiento' => null
                ];
            }

            // Gran invalidez
            $incap = floatval($situacion['porcentaje_incapacidad'] ?? 0);
            $paramsAcc = (require __DIR__ . '/parametros_motor.php')['calculos_especificos']['accidentes'];
            if ($incap >= ($paramsAcc['umbral_gran_invalidez'] ?? 66)) {
                $alertas[] = [
                    'tipo' => 'gran_invalidez',
                    'urgencia' => 'alta',
                    'descripcion' => 'Posible GRAN INVALIDEZ (incapacidad >= 66%). Corresponde prestación adicional mensual (Art. 17 Ley 24.557).',
                    'fecha_vencimiento' => null
                ];
            }

            // Cosa juzgada administrativa
            if ($estadoCM === 'homologado') {
                $alertas[] = [
                    'tipo' => 'cosa_juzgada_cm',
                    'urgencia' => 'alta',
                    'descripcion' => 'El acuerdo homologado en Comisión Médica tiene efecto de cosa juzgada administrativa. Solo queda disponible la vía civil complementaria (Art. 4 Ley 26.773).',
                    'fecha_vencimiento' => null
                ];
            }
        }

        if ($tipoConflicto === 'accidente_laboral') {
            if (($situacion['tiene_art'] ?? 'no') !== 'si') {
                $alertas[] = [
                    'tipo' => 'falta_art',
                    'codigo' => 'falta_art',
                    'urgencia' => 'critica',
                    'descripcion' => 'No se informó cobertura ART activa: la empresa queda expuesta a responsabilidad directa.',
                    'fecha_vencimiento' => null,
                ];
            }

            if (($situacion['comision_medica'] ?? 'no_iniciada') === 'no_iniciada') {
                $alertas[] = [
                    'tipo' => 'dictamen_cm_ausente',
                    'codigo' => 'dictamen_cm_ausente',
                    'urgencia' => 'alta',
                    'descripcion' => 'Aún no existe dictamen firme de Comisión Médica; el porcentaje de incapacidad sigue abierto.',
                    'fecha_vencimiento' => null,
                ];
            }

            if (($situacion['denuncia_art'] ?? 'no') === 'si' && ($situacion['rechazo_art'] ?? 'no') === 'no') {
                $alertas[] = [
                    'tipo' => 'alta_probabilidad_nexo',
                    'codigo' => 'alta_probabilidad_nexo',
                    'urgencia' => 'alta',
                    'descripcion' => 'La denuncia ante ART no fue rechazada: existe una señal favorable de nexo causal.',
                    'fecha_vencimiento' => null,
                ];
            }

            if (($documentacion['registrado_afip'] ?? 'no') === 'no' || ($documentacion['documentacion_empresa_completa'] ?? 'no') === 'no') {
                $alertas[] = [
                    'tipo' => 'documentacion_insuficiente_empresa',
                    'codigo' => 'documentacion_insuficiente_empresa',
                    'urgencia' => 'alta',
                    'descripcion' => 'La documentación empresaria aparece incompleta para defender la contingencia.',
                    'fecha_vencimiento' => null,
                ];
            }

            if (intval($situacion['cantidad_empleados'] ?? 1) > 1) {
                $alertas[] = [
                    'tipo' => 'riesgo_cascada',
                    'codigo' => 'riesgo_cascada',
                    'urgencia' => 'media',
                    'descripcion' => 'El caso puede generar reclamos similares en otros trabajadores de la misma dotación.',
                    'fecha_vencimiento' => null,
                ];
            }

            if (($situacion['rechazo_art'] ?? 'no') === 'si' || ($situacion['culpa_grave'] ?? 'no') === 'si') {
                $alertas[] = [
                    'tipo' => 'via_civil_viable',
                    'codigo' => 'via_civil_viable',
                    'urgencia' => 'alta',
                    'descripcion' => 'Hay señales que justifican estudiar la vía civil integral como alternativa estratégica.',
                    'fecha_vencimiento' => null,
                ];
            }
        }

        // ── IRIL crítico → derivación profesional ─────────────────────────────
        if ($irilScore >= 4.0) {
            $alertas[] = [
                'tipo' => 'derivacion_profesional',
                'urgencia' => 'critica',
                'descripcion' => 'IRIL Crítico: la complejidad estructural de esta situación requiere intervención profesional inmediata.',
                'fecha_vencimiento' => null
            ];
        }

        // ── MARZO 2026: Avisos Post-Ley Bases ───────────────────────────────────
        $alertas_marzo2026 = $this->generarAvisosPostLeyBases($datosLaborales);
        if (!empty($alertas_marzo2026)) {
            $alertas = array_merge($alertas, $alertas_marzo2026);
        }

        return $alertas;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AVISOS MARZO 2026 — Post-Ley Bases
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * generarAvisosPostLeyBases() — Avisos sobre multas y Art. 132 bis
     * 
     * Según Ackerman (2026): aunque las multas estén suspendidas, la falta de
     * registro puede ser sancionable administrativamente vía Art. 132 bis
     * 
     * @param array $datosLaborales
     * @return array                  Avisos adicionales
     */
    private function generarAvisosPostLeyBases(array $datosLaborales): array {
        
        $avisos = [];
        $fecha_despido = $datosLaborales['fecha_despido'] ?? null;
        
        if (!$fecha_despido) {
            return $avisos;
        }
        
        $fecha_obj = is_string($fecha_despido) ? new DateTime($fecha_despido) : $fecha_despido;
        $fecha_ley_bases = new DateTime('2024-07-09');
        
        // CASO 1: Posterior a Ley Bases
        if ($fecha_obj > $fecha_ley_bases) {
            $tipo_registro = $datosLaborales['tipo_registro'] ?? 'registrado';
            
            if ($tipo_registro !== 'registrado') {
                $avisos[] = [
                    'tipo' => 'aviso_art_132bis',
                    'urgencia' => 'alta',
                    'titulo' => '📋 AVISO: Art. 132 bis — Sanción Administrativa Vigente',
                    'descripcion' => 'Aunque la Ley Bases suspendió multas legales, la falta de registro sigue siendo sancionable vía Art. 132 bis (Procedimiento administrativo ante Ministerio de Trabajo). No hay beneficio por Ley Bases en esta materia.',
                    'condicion' => 'Despido posterior a 09/07/2024 + falta de registro documentado',
                    'tipo_registro_presente' => $tipo_registro,
                    'riesgo' => 'MODERADO-ALTO',
                    'sugerencia' => '¿Desea calcular escenario de contingencia contemplando posible demanda ante Ministerio de Trabajo?',
                    'referencia' => 'Ackerman, Sergio (2026). "Aplicación de la Ley Bases en Contexto de Art. 132 bis". Doctrina Laboral Marzo 2026.',
                    'fecha_vencimiento' => null
                ];
            }
        }
        
        return $avisos;
    }
}
