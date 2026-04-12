<?php
/**
 * EscenariosEngine.php — Generador de escenarios estratégicos comparativos
 *
 * Genera los 4 escenarios estratégicos del Motor de Riesgo Laboral:
 *
 *   A — Negociación Temprana:  cierre anticipado, costo controlado
 *   B — Litigio Completo:      mayor impacto potencial, mayor duración
 *   C — Estrategia Mixta:      intimación formal + intento conciliatorio
 *   D — Reconfiguración Preventiva: para empleadores, regularización
 *
 * Para cada escenario calcula:
 *   - Beneficio estimado y costo total posible
 *   - VBP = Beneficio − Costo
 *   - VAE = VBP / (duración_promedio × riesgo_institucional) — valor ajustado
 *   - Índice Estratégico (0-100) — combina retorno neto, costo, duración y riesgo
 *   - Duración estimada en meses
 *   - Riesgo institucional (1-5)
 *   - Nivel de intervención profesional
 *   - Ventajas y desventajas
 *
 * PRINCIPIO: el sistema NO dice cuál escenario es mejor.
 * Presenta datos estructurados para que el profesional y el cliente decidan.
 */

require_once __DIR__ . '/config.php';

class EscenariosEngine
{

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODO PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Provincias donde el organismo de conciliación previa al fuero laboral
     * se denomina SECLO (o similar con trámite equivalente).
     * En el resto de provincias se usa la denominación genérica.
     */
    private const PROVINCIAS_SECLO = ['CABA'];

    /**
     * Organismos de conciliación laboral previa por provincia.
     * Cuando la provincia no está listada se usa denominación genérica.
     */
    private const CONCILIACION_PROVINCIAL = [
        'CABA' => 'SECLO (Servicio de Conciliación Laboral Obligatoria)',
        'Buenos Aires' => 'SECLOBA (Servicio de Conciliación Laboral de la Prov. de Bs. As.)',
        'Córdoba' => 'instancia de conciliación laboral previa (fuero laboral cordobés)',
        'Santa Fe' => 'instancia de conciliación previa ante el MTESS provincial',
        'Mendoza' => 'audiencia de conciliación previa (art. 108 CPCC Mendoza laboral)',
    ];

    /**
     * generarEscenarios() — Genera los 4 escenarios comparativos.
     *
     * @param array  $exposicion     Resultado de IrilEngine::calcularExposicion()
     * @param float  $irilScore      Puntaje IRIL
     * @param string $tipoConflicto
     * @param string $tipoUsuario    'empleado' | 'empleador'
     * @param array  $situacion      Datos de la situación actual
     * @param string $provincia      Provincia del conflicto (para adaptar referencias territoriales)
     * @return array ['escenarios' => array, 'recomendado' => string, 'tabla_comparativa' => array]
     */
    public function generarEscenarios(
        array $exposicion,
        float $irilScore,
        string $tipoConflicto,
        string $tipoUsuario,
        array $situacion,
        string $provincia = 'CABA'
    ): array {

        $totalBase = $exposicion['total_base'] ?? 0;
        $totalConMultas = $exposicion['total_con_multas'] ?? 0;
        $salario = $exposicion['salario_base'] ?? 0;

        // Cargar parámetros estratégicos
        $params = require __DIR__ . '/parametros_motor.php';
        $cfg = $params['escenarios'];

        // ── Desvío ART: si es accidente con cobertura ART, generar escenarios específicos ──
        $esArtConCobertura = ($tipoConflicto === 'accidente_laboral')
            && (($situacion['tiene_art'] ?? 'no') === 'si');

        if ($esArtConCobertura) {
            return $this->generarEscenariosART($exposicion, $irilScore, $situacion, $provincia, $params);
        }

        // ── Flujo genérico (despidos, diferencias salariales, etc.) — sin cambios ──
        // Costo estimado de honorarios judiciales: 20-25% del reclamo
        $honorariosJudiciales = $totalBase * $cfg['global']['honorarios_judiciales_tasa'];

        // Costas estimadas si se pierde (20% del monto reclamado)
        $costasEstimadas = $totalBase * $cfg['global']['costas_judiciales_tasa'];

        // ── Escenario A — Negociación Temprana ───────────────────────────────
        $a = $this->escenarioNegociacion($totalBase, $totalConMultas, $honorariosJudiciales, $irilScore, $tipoUsuario);

        // ── Escenario B — Litigio Completo ────────────────────────────────────
        $b = $this->escenarioLitigio($totalBase, $totalConMultas, $honorariosJudiciales, $costasEstimadas, $irilScore, $tipoConflicto, $tipoUsuario);

        // ── Escenario C — Estrategia Mixta ────────────────────────────────────
        $c = $this->escenarioMixto($totalBase, $totalConMultas, $honorariosJudiciales, $irilScore, $tipoUsuario, $provincia);

        // ── Escenario D — Reconfiguración Preventiva ──────────────────────────
        $d = $this->escenarioPreventivo($exposicion, $irilScore, $tipoConflicto, $tipoUsuario, $situacion);

        $escenarios = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d];
        $escenarios = $this->agregarIndicesEstrategicos($escenarios);
        $recomendado = $this->determinarRecomendado($escenarios, $tipoUsuario, $irilScore, $tipoConflicto);

        // Tabla comparativa para visualización rápida
        $tablaComparativa = $this->construirTablaComparativa($escenarios, $recomendado);

        // ── ALERTAS DE MARZO 2026 ──────────────────────────────────────────────
        $alertasMarz2026 = $this->generarAlertasMarzo2026(
            $totalBase,
            $b,  // Escenario B para analizar tasas
            $provincia,
            $tipoConflicto,
            $situacion
        );

        return [
            'escenarios' => $escenarios,
            'recomendado' => $recomendado,
            'tabla_comparativa' => $tablaComparativa,
            'alertas_marzo_2026' => $alertasMarz2026,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESCENARIOS INDIVIDUALES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * escenarioNegociacion() — Escenario A: Negociación temprana extrajudicial.
     * Típicamente resulta en acuerdo por 60-80% del valor total.
     */
    private function escenarioNegociacion(
        float $totalBase,
        float $totalConMultas,
        float $honorarios,
        float $iril,
        string $tipoUsuario
    ): array {
        // Cargar parámetros específicos
        $params = require __DIR__ . '/parametros_motor.php';
        $cfg = $params['escenarios']['negociacion_temprana'];

        // En negociación se recupera un porcentaje del total base (sin multas)
        $beneficio = $totalBase * $cfg['tasa_recupero_base'];

        // Costo: honorarios de asesoramiento extrajudicial (menor que judicial)
        $costo = $honorarios * $cfg['factor_honorarios'];

        $vbp = $beneficio - $costo;
        $duracionPromedio = $cfg['duracion_promedio'];
        $riesgoInst = max(1.0, $iril * $cfg['factor_riesgo']);

        $vae = $duracionPromedio > 0 && $riesgoInst > 0
            ? round($vbp / ($duracionPromedio * $riesgoInst), 0)
            : 0;

        return [
            'codigo' => 'A',
            'nombre' => 'Negociación Temprana',
            'descripcion' => 'Cierre extrajudicial anticipado mediante acuerdo directo entre las partes, con o sin mediación formal.',
            'beneficio_estimado' => round($beneficio, 2),
            'costo_estimado' => round($costo, 2),
            'vbp' => round($vbp, 2),
            'vae' => $vae,
            'duracion_min_meses' => 1,
            'duracion_max_meses' => 4,
            'duracion_promedio' => $duracionPromedio,
            'riesgo_institucional' => round($riesgoInst, 1),
            'nivel_intervencion' => 'bajo',
            'intervencion_desc' => 'Asesoramiento profesional extrajudicial',
            'ventajas' => [
                'Resolución rápida (1-4 meses)',
                'Costo reducido de honorarios',
                'Sin fricción procesal',
                'Confidencialidad del acuerdo',
                'Sin riesgo de costas',
                'Preserva relación laboral si es posible',
            ],
            'desventajas' => [
                'Recuperación generalmente menor al 100% del reclamo',
                'Requiere voluntad de ambas partes',
                'El acuerdo puede no incluir multas legales',
                'Sin precedente ni valor declarativo',
            ],
            'nota' => 'El porcentaje de recuperación varía según el tipo de conflicto y la predisposición del empleador.'
        ];
    }

    /**
     * escenarioLitigio() — Escenario B: Litigio judicial completo.
     * Mayor potencial de recuperación pero máxima fricción y duración.
     */
    private function escenarioLitigio(
        float $totalBase,
        float $totalConMultas,
        float $honorarios,
        float $costas,
        float $iril,
        string $tipoConflicto,
        string $tipoUsuario
    ): array {
        // Cargar parámetros específicos
        $params = require __DIR__ . '/parametros_motor.php';
        $cfg = $params['escenarios']['litigio_completo'];

        // En litigio se puede reclamar el total con multas
        $beneficio = $totalConMultas * $cfg['tasa_recupero_total'];

        // Costo total: honorarios judiciales + posibles costas + tiempo
        $costo = $honorarios + ($costas * $cfg['factor_costas_riesgo']);

        $vbp = $beneficio - $costo;
        $duracionPromedio = $cfg['duracion_promedio'];

        // En litigio el riesgo institucional es alto
        $riesgoInst = min(5.0, $iril * $cfg['factor_riesgo']);

        $vae = $duracionPromedio > 0 && $riesgoInst > 0
            ? round($vbp / ($duracionPromedio * $riesgoInst), 0)
            : 0;

        return [
            'codigo' => 'B',
            'nombre' => 'Litigio Completo',
            'descripcion' => 'Proceso judicial completo ante el fuero laboral correspondiente, con todas las instancias procesales.',
            'beneficio_estimado' => round($beneficio, 2),
            'costo_estimado' => round($costo, 2),
            'vbp' => round($vbp, 2),
            'vae' => $vae,
            'duracion_min_meses' => 24,
            'duracion_max_meses' => 60,
            'duracion_promedio' => $duracionPromedio,
            'riesgo_institucional' => round($riesgoInst, 1),
            'nivel_intervencion' => 'profesional',
            'intervencion_desc' => 'Representación letrada obligatoria en todo el proceso',
            'ventajas' => [
                'Potencial de recuperación máxima (incluyendo multas)',
                'Posibilidad de establecer precedente',
                'Acceso a medidas cautelares',
                'Mayor presión sobre el empleador',
                'Intereses desde la mora (Art. 768 CCCN — tasa activa)',
            ],
            'desventajas' => [
                'Duración prolongada (2 a 5 años en Argentina)',
                'Alta fricción procesal e institucional',
                'Costo de honorarios judiciales más elevado',
                'Riesgo de condena en costas',
                'Saturación del fuero laboral',
                'Incertidumbre sobre actualización de montos',
            ],
            'nota' => 'Los montos reclamados pueden actualizarse por intereses pero también verse reducidos por acuerdos conciliatorios en audiencia.'
        ];
    }

    /**
     * escenarioMixto() — Escenario C: Estrategia Mixta (intimación + conciliación).
     * Intimación formal, intento conciliatorio, activación judicial condicionada.
     *
     * @param string $provincia  Para adaptar referencias territoriales (SECLO vs otros organismos)
     */
    private function escenarioMixto(
        float $totalBase,
        float $totalConMultas,
        float $honorarios,
        float $iril,
        string $tipoUsuario,
        string $provincia = 'CABA'
    ): array {
        // Cargar parámetros específicos
        $params = require __DIR__ . '/parametros_motor.php';
        $cfg = $params['escenarios']['estrategia_mixta'];

        // En estrategia mixta: se recupera entre el 75% y 90% del total base
        $beneficio = $totalBase * $cfg['tasa_recupero_base'];

        // Costo: honorarios intermedios (más que extrajudicial, menos que litigio)
        $costo = $honorarios * $cfg['factor_honorarios'];

        $vbp = $beneficio - $costo;
        $duracionPromedio = $cfg['duracion_promedio'];

        $riesgoInst = min(5.0, $iril * $cfg['factor_riesgo']);

        $vae = $duracionPromedio > 0 && $riesgoInst > 0
            ? round($vbp / ($duracionPromedio * $riesgoInst), 0)
            : 0;

        // ── Adaptar terminología y referencia al organismo según provincia ────
        $esCABA = ($provincia === 'CABA');
        $orgConcil = self::CONCILIACION_PROVINCIAL[$provincia]
            ?? 'instancia de conciliación laboral previa obligatoria';

        $descripcion = $esCABA
            ? 'Intimación telegráfica formal, instancia de conciliación ante el SECLO (Servicio de Conciliación Laboral Obligatoria — CABA), con activación judicial condicionada al resultado.'
            : "Intimación telegráfica formal, {$orgConcil}, con activación judicial condicionada al resultado. La estructura conciliatoria varía según el fuero local de {$provincia}.";

        $ventajaConcil = $esCABA
            ? 'SECLO obliga al empleador a comparecer (Art. 2 Ley 24.635)'
            : "La conciliación previa ({$orgConcil}) obliga al empleador a comparecer";

        $notaFinal = $esCABA
            ? 'El SECLO (Ley 24.635) es obligatorio en CABA antes de iniciar juicio laboral. Un acuerdo homologado tiene fuerza de sentencia.'
            : "En {$provincia} rige la instancia conciliatoria laboral local. Consulte con el profesional la modalidad y plazos específicos del fuero de trabajo provincial.";

        return [
            'codigo' => 'C',
            'nombre' => 'Estrategia Mixta',
            'descripcion' => $descripcion,
            'beneficio_estimado' => round($beneficio, 2),
            'costo_estimado' => round($costo, 2),
            'vbp' => round($vbp, 2),
            'vae' => $vae,
            'duracion_min_meses' => 6,
            'duracion_max_meses' => 18,
            'duracion_promedio' => $duracionPromedio,
            'riesgo_institucional' => round($riesgoInst, 1),
            'nivel_intervencion' => 'profesional',
            'intervencion_desc' => 'Asesoramiento profesional con posible representación en audiencia',
            'ventajas' => [
                'Mejor balance entre recuperación y duración',
                'La intimacion constituye en mora al empleador (Art. 243 LCT)',
                $ventajaConcil,
                'Posibilidad de acuerdo homologado con fuerza ejecutoria',
                'Menor duración que litigio completo',
                'Preserva la opción judicial si fracasa',
            ],
            'desventajas' => [
                'Requiere intervención profesional desde el inicio',
                'Mayor costo que negociación directa',
                'Puede derivar en litigio si el empleador es reticente',
                'Requiere coordinar plazos procesales',
            ],
            'nota' => $notaFinal,
        ];
    }

    /**
     * escenarioPreventivo() — Escenario D: Reconfiguración Preventiva.
     * Orientado principalmente a empleadores. Regularización y mitigación.
     */
    private function escenarioPreventivo(
        array $exposicion,
        float $iril,
        string $tipoConflicto,
        string $tipoUsuario,
        array $situacion
    ): array {
        // Cargar parámetros específicos
        $params = require __DIR__ . '/parametros_motor.php';
        $cfg = $params['escenarios']['preventivo'];

        $salario = max(0.0, floatval($exposicion['salario_base'] ?? 0));
        $totalBase = max(0.0, floatval($exposicion['total_base'] ?? 0));
        $totalConMultas = max($totalBase, floatval($exposicion['total_con_multas'] ?? $totalBase));
        $perfil = $this->resolverPerfilPreventivo($exposicion, $situacion, $tipoConflicto, $tipoUsuario, $cfg);

        $bases = [
            'salario' => $salario,
            'salario_trimestral' => $salario * 3,
            'salario_semestral' => $salario * 6,
            'salario_anual' => $salario * max(1.0, floatval($cfg['meses_ahorro_litigio'] ?? 12)),
            'total_base' => $totalBase,
            'total_con_multas' => $totalConMultas,
        ];
        $baseCalculo = max(0.0, floatval($bases[$perfil['base_fuente']] ?? 0.0));
        if ($baseCalculo <= 0) {
            $baseCalculo = max($salario * max(1.0, floatval($cfg['meses_ahorro_litigio'] ?? 12)), $totalBase, $totalConMultas);
        }

        $ahorroEstimado = round($baseCalculo * floatval($perfil['factor_beneficio'] ?? 0), 2);
        $costoBase = $salario > 0
            ? $salario * floatval($cfg['factor_costo_regularizacion'] ?? 1.5)
            : $baseCalculo * 0.08;
        $costoRegularizacion = round($costoBase * floatval($perfil['factor_costo'] ?? 1.0), 2);

        $vbp = $ahorroEstimado - $costoRegularizacion;
        $duracionPromedio = max(1, intval($perfil['duracion_promedio'] ?? $cfg['duracion_promedio'] ?? 2));
        $riesgoInst = max(1.0, floatval($perfil['riesgo_institucional'] ?? 1.0));

        $vae = ($duracionPromedio > 0 && $riesgoInst > 0)
            ? round($vbp / ($duracionPromedio * $riesgoInst), 0)
            : 0;

        $criterios = [
            [
                'titulo' => 'Perfil evaluado',
                'valor' => $perfil['perfil_label'],
            ],
            [
                'titulo' => 'Estado del caso',
                'valor' => $perfil['estado_caso'],
            ],
            [
                'titulo' => 'Base económica',
                'valor' => sprintf('%s (%s)', $perfil['base_label'], ml_formato_moneda($baseCalculo)),
            ],
            [
                'titulo' => 'Criterio de beneficio',
                'valor' => sprintf(
                    '%s de la base económica seleccionada.',
                    $this->formatearPorcentaje(floatval($perfil['factor_beneficio'] ?? 0))
                ),
            ],
            [
                'titulo' => 'Criterio de costo',
                'valor' => sprintf(
                    '%s sobre el costo base de regularización (%s).',
                    $this->formatearPorcentaje(floatval($perfil['factor_costo'] ?? 0)),
                    ml_formato_moneda($costoBase)
                ),
            ],
            [
                'titulo' => 'Aplicabilidad',
                'valor' => ucfirst($perfil['aplicabilidad']) . ' — ' . $perfil['aplicabilidad_detalle'],
            ],
        ];
        foreach ($perfil['criterios_contexto'] as $criterioContextual) {
            $criterios[] = [
                'titulo' => 'Criterio contextual',
                'valor' => $criterioContextual,
            ];
        }

        $definicionSistema = sprintf(
            'El sistema toma %s como base, aplica un factor de beneficio del %s y un costo de regularización del %s. La lectura económica del resultado es "%s".',
            strtolower($perfil['base_label']),
            $this->formatearPorcentaje(floatval($perfil['factor_beneficio'] ?? 0)),
            $this->formatearPorcentaje(floatval($perfil['factor_costo'] ?? 0)),
            $perfil['lectura_beneficio']
        );

        return [
            'codigo' => 'D',
            'nombre' => 'Reconfiguración Preventiva',
            'descripcion' => $perfil['descripcion'],
            'beneficio_estimado' => round($ahorroEstimado, 2),
            'costo_estimado' => round($costoRegularizacion, 2),
            'vbp' => round($vbp, 2),
            'vae' => $vae,
            'duracion_min_meses' => 1,
            'duracion_max_meses' => max(3, $duracionPromedio),
            'duracion_promedio' => $duracionPromedio,
            'riesgo_institucional' => $riesgoInst,
            'nivel_intervencion' => 'bajo',
            'intervencion_desc' => 'Auditoría laboral preventiva y asesoramiento en regularización',
            'ventajas' => [
                'Elimina el riesgo antes de que se materialice',
                'Costo menor que cualquier litigio',
                'Mejora el clima laboral interno',
                'Reduce riesgo de inspección laboral',
                'Previene el efecto multiplicador en otros empleados',
                'Diseño de procedimientos de cumplimiento normativo',
            ],
            'desventajas' => [
                'Solo aplica antes de que el conflicto escale',
                'Requiere voluntad de regularización del empleador',
                'Puede implicar pago de diferencias salariales',
                'No elimina derechos adquiridos del empleado',
            ],
            'nota' => $perfil['nota'],
            'beneficio_label' => $perfil['beneficio_label'],
            'vbp_label' => $perfil['vbp_label'],
            'lectura_beneficio' => $perfil['lectura_beneficio'],
            'aplicabilidad' => $perfil['aplicabilidad'],
            'aplicabilidad_detalle' => $perfil['aplicabilidad_detalle'],
            'definicion_sistema' => $definicionSistema,
            'criterios_definidos' => $criterios,
            'criterios_contexto' => $perfil['criterios_contexto'],
        ];
    }

    private function resolverPerfilPreventivo(
        array $exposicion,
        array $situacion,
        string $tipoConflicto,
        string $tipoUsuario,
        array $cfg
    ): array {
        $hayIntercambio = ($situacion['hay_intercambio'] ?? 'no') === 'si'
            || ($situacion['fue_intimado'] ?? 'no') === 'si'
            || ($situacion['tiene_telegramas'] ?? 'no') === 'si';
        $yaDespedido = ($situacion['ya_despedido'] ?? 'no') === 'si';
        $inspeccionPrevia = ($situacion['inspeccion_previa'] ?? 'no') === 'si';
        $estadoCm = (string) ($situacion['comision_medica'] ?? 'no_iniciada');
        $tipoUsuarioNormalizado = strtolower(trim($tipoUsuario));
        $perfil = [
            'perfil_label' => $tipoUsuarioNormalizado === 'empleador'
                ? 'Empleador con margen de regularización'
                : 'Lectura referencial para la parte reclamante',
            'estado_caso' => $hayIntercambio || $yaDespedido || $inspeccionPrevia || $estadoCm !== 'no_iniciada'
                ? 'Conflicto activo o escalado'
                : 'Ventana preventiva abierta',
            'base_fuente' => 'salario_anual',
            'base_label' => '12 meses de salario de referencia',
            'factor_beneficio' => 1.0,
            'factor_costo' => 1.0,
            'duracion_promedio' => max(1, intval($cfg['duracion_promedio'] ?? 2)),
            'riesgo_institucional' => 1.0,
            'beneficio_label' => $tipoUsuarioNormalizado === 'empleador'
                ? 'Beneficio (ahorro pot.)'
                : 'Beneficio (referencial)',
            'vbp_label' => $tipoUsuarioNormalizado === 'empleador'
                ? 'Ahorro neto estimado'
                : 'Balance neto referencial',
            'lectura_beneficio' => $tipoUsuarioNormalizado === 'empleador'
                ? 'ahorro potencial o contingencia evitada'
                : 'referencia de ahorro potencial para la parte que regulariza',
            'aplicabilidad' => $tipoUsuarioNormalizado === 'empleador' ? 'media' : 'referencial',
            'aplicabilidad_detalle' => $tipoUsuarioNormalizado === 'empleador'
                ? 'Conviene cuando todavía existe margen real para corregir registros, procesos o coberturas antes de un cierre litigioso.'
                : 'Se muestra solo para explicar qué ahorro podría capturar la parte empleadora si regulariza.',
            'descripcion' => 'Escenario exclusivo para empleadores y para contextos con margen real de regularización. El beneficio debe leerse como ahorro potencial o contingencia evitada, no como ingreso directo.',
            'nota' => 'Este escenario es exclusivamente preventivo. Si ya hay conflicto activo con telegramas intercambiados, su aplicabilidad es limitada.',
            'criterios_contexto' => [],
        ];

        switch ($tipoConflicto) {
            case 'auditoria_preventiva':
                $nivel = (string) ($situacion['nivel_cumplimiento'] ?? 'desconocido');
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_anual';
                $perfil['base_label'] = 'contingencia latente diagnosticada';
                $perfil['factor_beneficio'] = 1.0;
                $perfil['factor_costo'] = 1.0;
                $perfil['duracion_promedio'] = 2;
                $perfil['riesgo_institucional'] = 1.0;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador' ? 'alta' : 'referencial';
                $perfil['aplicabilidad_detalle'] = 'Aplica cuando la empresa todavía puede corregir procesos, registrar personal y ajustar documentación antes de una contingencia formal.';
                $perfil['descripcion'] = 'Escenario preventivo para empleadores con margen real de regularización: auditoría laboral, ajuste contractual, revisión de cumplimiento normativo y diseño de cobertura preventiva.';
                $perfil['criterios_contexto'][] = 'Nivel de cumplimiento declarado: ' . $nivel . '.';
                break;

            case 'riesgo_inspeccion':
            case 'multas_legales':
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_semestral';
                $perfil['base_label'] = 'contingencia inspectiva y sancionatoria';
                $perfil['factor_beneficio'] = $inspeccionPrevia ? 0.45 : 0.75;
                $perfil['factor_costo'] = $inspeccionPrevia ? 1.2 : 1.0;
                $perfil['duracion_promedio'] = $inspeccionPrevia ? 3 : 2;
                $perfil['riesgo_institucional'] = $inspeccionPrevia ? 1.4 : 1.1;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador'
                    ? ($inspeccionPrevia ? 'media' : 'alta')
                    : 'referencial';
                $perfil['aplicabilidad_detalle'] = $inspeccionPrevia
                    ? 'Ya existe antecedente inspectivo: la regularización todavía reduce exposición futura, pero el ahorro posible es menor.'
                    : 'La regularización temprana puede bajar multas, recargos y riesgo de nuevas inspecciones.';
                $perfil['criterios_contexto'][] = $inspeccionPrevia
                    ? 'Se detectó inspección previa, por eso el motor reduce el porcentaje de ahorro potencial.'
                    : 'Sin inspección previa registrada, el motor asume mayor margen para evitar sanciones.';
                break;

            case 'responsabilidad_solidaria':
                $controles = $this->contarControlesSolidarios($situacion);
                $faltantes = max(0, 5 - $controles);
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_semestral';
                $perfil['base_label'] = 'exposición por solidaridad de contratistas';
                $perfil['factor_beneficio'] = min(0.85, 0.35 + ($faltantes * 0.10));
                $perfil['factor_costo'] = 0.9 + ($faltantes * 0.08);
                $perfil['duracion_promedio'] = min(4, 2 + (int) ceil($faltantes / 2));
                $perfil['riesgo_institucional'] = $faltantes >= 3 ? 1.5 : 1.2;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador'
                    ? ($faltantes === 0 ? 'baja' : 'alta')
                    : 'referencial';
                $perfil['aplicabilidad_detalle'] = $faltantes === 0
                    ? 'La empresa ya cumple los cinco controles del art. 30 LCT; el margen preventivo adicional es acotado.'
                    : 'El ahorro depende de cuántos controles del art. 30 LCT falten implementar.';
                $perfil['criterios_contexto'][] = sprintf('Controles art. 30 LCT presentes: %d/5.', $controles);
                break;

            case 'diferencias_salariales':
                $mesesAdeudados = max(1, intval($situacion['meses_adeudados'] ?? 12));
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_semestral';
                $perfil['base_label'] = 'deuda regularizable y reclamo asociado';
                $perfil['factor_beneficio'] = $hayIntercambio ? 0.35 : 0.60;
                $perfil['factor_costo'] = $hayIntercambio ? 1.15 : 0.95;
                $perfil['duracion_promedio'] = $hayIntercambio ? 3 : 2;
                $perfil['riesgo_institucional'] = $hayIntercambio ? 1.7 : 1.3;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador'
                    ? ($hayIntercambio ? 'media' : 'alta')
                    : 'referencial';
                $perfil['aplicabilidad_detalle'] = $hayIntercambio
                    ? 'La regularización todavía puede acotar escalada, pero ya no evita todo el reclamo.'
                    : 'Si se corrige temprano, el motor asume mayor capacidad de contener deuda acumulada y fricción futura.';
                $perfil['criterios_contexto'][] = sprintf('Meses adeudados considerados: %d.', $mesesAdeudados);
                break;

            case 'trabajo_no_registrado':
                $perfil['base_fuente'] = floatval($exposicion['total_con_multas'] ?? 0) > 0 ? 'total_con_multas' : 'total_base';
                $perfil['base_label'] = 'pasivo por registración omitida y multas asociadas';
                $perfil['factor_beneficio'] = ($hayIntercambio || $yaDespedido) ? 0.25 : 0.55;
                $perfil['factor_costo'] = ($hayIntercambio || $yaDespedido) ? 1.35 : 1.15;
                $perfil['duracion_promedio'] = 3;
                $perfil['riesgo_institucional'] = ($hayIntercambio || $yaDespedido) ? 1.8 : 1.5;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador'
                    ? (($hayIntercambio || $yaDespedido) ? 'baja' : 'media')
                    : 'referencial';
                $perfil['aplicabilidad_detalle'] = ($hayIntercambio || $yaDespedido)
                    ? 'Con intercambio o ruptura activa, el escenario solo sirve como referencia de daño controlable hacia adelante.'
                    : 'Regularizar antes del conflicto abierto permite contener parte relevante del pasivo laboral y administrativo.';
                $perfil['criterios_contexto'][] = ($hayIntercambio || $yaDespedido)
                    ? 'Existe escalada formal, por eso el motor reduce la evitabilidad del beneficio.'
                    : 'No hay ruptura formal detectada, por eso el motor reconoce margen preventivo intermedio.';
                break;

            case 'despido_sin_causa':
            case 'despido_con_causa':
            case 'reclamo_indemnizatorio':
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_semestral';
                $perfil['base_label'] = 'exposición indemnizatoria ya configurada';
                $perfil['factor_beneficio'] = ($hayIntercambio || $yaDespedido) ? 0.15 : 0.35;
                $perfil['factor_costo'] = ($hayIntercambio || $yaDespedido) ? 1.1 : 0.95;
                $perfil['duracion_promedio'] = ($hayIntercambio || $yaDespedido) ? 3 : 2;
                $perfil['riesgo_institucional'] = ($hayIntercambio || $yaDespedido) ? 1.6 : 1.2;
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador'
                    ? (($hayIntercambio || $yaDespedido) ? 'baja' : 'media')
                    : 'referencial';
                $perfil['aplicabilidad_detalle'] = ($hayIntercambio || $yaDespedido)
                    ? 'Con despido o intimación activa, el ahorro se limita a contener derivaciones futuras y ordenar la salida.'
                    : 'Solo tiene sentido si aún hay margen para rediseñar la salida o reconducir la relación sin litigio pleno.';
                $perfil['criterios_contexto'][] = ($hayIntercambio || $yaDespedido)
                    ? 'El caso ya aparece como conflicto activo, por eso el motor rebaja la aplicabilidad preventiva.'
                    : 'No hay escalada formal detectada, por eso el motor conserva una ventana preventiva acotada.';
                break;

            case 'accidente_laboral':
                $tieneArt = ($situacion['tiene_art'] ?? 'no') === 'si';
                $perfil['base_fuente'] = floatval($exposicion['total_base'] ?? 0) > 0 ? 'total_base' : 'salario_anual';
                $perfil['base_label'] = 'contingencia futura y costos de cobertura';
                $perfil['factor_beneficio'] = $tieneArt ? 0.20 : 0.12;
                $perfil['factor_costo'] = $tieneArt ? 1.1 : 1.3;
                $perfil['duracion_promedio'] = 3;
                $perfil['riesgo_institucional'] = 1.4;
                $perfil['beneficio_label'] = 'Beneficio (contención futura)';
                $perfil['vbp_label'] = 'Ahorro neto futuro';
                $perfil['aplicabilidad'] = $tipoUsuarioNormalizado === 'empleador' ? 'baja' : 'referencial';
                $perfil['aplicabilidad_detalle'] = 'No corrige el siniestro ya ocurrido: solo ordena cobertura y prevención hacia adelante.';
                $perfil['criterios_contexto'][] = $tieneArt
                    ? 'Hay ART vigente: la lectura es de mejora de cumplimiento y prevención futura.'
                    : 'No hay ART: el motor limita el ahorro a contingencia futura, no al daño ya causado.';
                break;
        }

        return $perfil;
    }

    private function contarControlesSolidarios(array $situacion): int
    {
        $controles = 0;
        if (($situacion['principal_valida_cuil'] ?? 'no') === 'si') {
            $controles++;
        }
        // Se mantiene la clave legacy `principal_verifica_aaportes` por compatibilidad
        // con payloads históricos ya persistidos.
        if ((($situacion['principal_verifica_aportes'] ?? ($situacion['principal_verifica_aaportes'] ?? 'no'))) === 'si') {
            $controles++;
        }
        if (($situacion['principal_paga_directo'] ?? 'no') === 'si') {
            $controles++;
        }
        if (($situacion['principal_valida_cbu_trabajador'] ?? 'no') === 'si') {
            $controles++;
        }
        if (($situacion['principal_cubre_art'] ?? 'no') === 'si') {
            $controles++;
        }

        return $controles;
    }

    private function formatearPorcentaje(float $valor): string
    {
        return number_format($valor * 100, 1, ',', '.') . '%';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESCENARIOS ESPECÍFICOS ART (Solo cuando tiene_art = si)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * generarEscenariosART() — Genera los 4 escenarios específicos para
     * accidentes/enfermedades laborales con cobertura ART.
     *
     * A — Aceptación Administrativa (CM)
     * B — Impugnación de Dictamen CM
     * C — Acción Judicial Laboral
     * D — Acción Civil Complementaria (Art. 4 Ley 26.773)
     */
    private function generarEscenariosART(
        array $exposicion,
        float $irilScore,
        array $situacion,
        string $provincia,
        array $params
    ): array {

        $conceptos = $exposicion['conceptos'] ?? [];
        $cuantificacion = $exposicion['cuantificacion_economica'] ?? [];
        $viaArt = is_array($cuantificacion['via_art'] ?? null) ? $cuantificacion['via_art'] : [];
        $viaCivil = is_array($cuantificacion['via_civil'] ?? null) ? $cuantificacion['via_civil'] : [];
        $comparativa = is_array($cuantificacion['comparativa'] ?? null) ? $cuantificacion['comparativa'] : [];

        $montoTarifaART = floatval($viaArt['monto_seguro'] ?? ($conceptos['prestacion_art_tarifa']['monto'] ?? ($exposicion['total_base'] ?? 0)));
        $civilEscenarios = is_array($viaCivil['escenarios'] ?? null) ? $viaCivil['escenarios'] : [];
        $montoCivilConservador = floatval($civilEscenarios['conservador'] ?? ($montoTarifaART * 1.1));
        $montoCivilProbable = floatval($civilEscenarios['probable'] ?? ($conceptos['estimacion_civil_mendez']['monto'] ?? ($montoTarifaART * 2.5)));
        $montoCivilAgresivo = floatval($civilEscenarios['agresivo'] ?? max($montoCivilProbable, $montoTarifaART * 2.8));
        $honorariosBase = $montoTarifaART * ($params['escenarios']['global']['honorarios_judiciales_tasa'] ?? 0.22);

        $cfgArt = $params['calculos_especificos']['accidentes']['escenarios_art'];
        $estadoCM = $situacion['comision_medica'] ?? 'no_iniciada';

        // ── Escenario A — Aceptación Administrativa ─────────────────────────
        $cfgA = $cfgArt['aceptacion_cm'];
        $benefA = $montoTarifaART * $cfgA['tasa_recupero'];
        $costoA = $honorariosBase * $cfgA['factor_honorarios'];
        $vbpA = $benefA - $costoA;
        $riesgoA = max(1.0, $irilScore * $cfgA['factor_riesgo']);
        $vaeA = ($cfgA['duracion_promedio'] > 0 && $riesgoA > 0)
            ? round($vbpA / ($cfgA['duracion_promedio'] * $riesgoA), 0) : 0;

        $a = [
            'codigo' => 'A',
            'nombre' => 'Aceptación Administrativa',
            'descripcion' => 'Aceptar el dictamen de la Comisión Médica Jurisdiccional. La ART abona la prestación dineraria tarifada según Ley 24.557. Resolución rápida sin litigio.',
            'beneficio_estimado' => round($benefA, 2),
            'costo_estimado' => round($costoA, 2),
            'vbp' => round($vbpA, 2),
            'vae' => $vaeA,
            'via_juridica' => 'art',
            'probabilidad_cierre' => 0.85,
            'exposicion_economica' => ['art' => round($montoTarifaART, 2)],
            'duracion_min_meses' => $cfgA['duracion_min'],
            'duracion_max_meses' => $cfgA['duracion_max'],
            'duracion_promedio' => $cfgA['duracion_promedio'],
            'riesgo_institucional' => round($riesgoA, 1),
            'nivel_intervencion' => 'bajo',
            'intervencion_desc' => 'Asesoramiento profesional para evaluar dictamen CM',
            'ventajas' => [
                'Resolución rápida (1-6 meses)',
                'Cobro directo de la ART sin juicio',
                'Sin riesgo de costas',
                'Certeza del monto a percibir',
                'Sin desgaste procesal',
            ],
            'desventajas' => [
                'El monto tarifado puede ser inferior a la reparación civil integral',
                'El dictamen de CM puede subestimar la incapacidad',
                'La aceptación tiene efecto de cosa juzgada administrativa',
                'No incluye daño moral ni reparación extrapatrimonial',
            ],
            'nota' => 'Recomendado cuando el dictamen reconoce la incapacidad reclamada y el monto tarifado es razonable. El acuerdo homologado cierra la vía administrativa.',
        ];

        // ── Escenario B — Impugnación de Dictamen CM ────────────────────────
        $cfgB = $cfgArt['impugnacion_cm'];
        $benefB = $montoTarifaART * $cfgB['tasa_recupero'];
        $costoB = $honorariosBase * $cfgB['factor_honorarios'];
        $vbpB = $benefB - $costoB;
        $riesgoB = min(5.0, $irilScore * $cfgB['factor_riesgo']);
        $vaeB = ($cfgB['duracion_promedio'] > 0 && $riesgoB > 0)
            ? round($vbpB / ($cfgB['duracion_promedio'] * $riesgoB), 0) : 0;

        $b = [
            'codigo' => 'B',
            'nombre' => 'Impugnación Dictamen CM',
            'descripcion' => 'Recurrir el dictamen de la Comisión Médica ante el Juzgado Federal o la Cámara Federal de la Seguridad Social, buscando un mayor porcentaje de incapacidad o corrección del monto.',
            'beneficio_estimado' => round($benefB, 2),
            'costo_estimado' => round($costoB, 2),
            'vbp' => round($vbpB, 2),
            'vae' => $vaeB,
            'via_juridica' => 'art',
            'probabilidad_cierre' => 0.60,
            'exposicion_economica' => ['art' => round($benefB, 2)],
            'duracion_min_meses' => $cfgB['duracion_min'],
            'duracion_max_meses' => $cfgB['duracion_max'],
            'duracion_promedio' => $cfgB['duracion_promedio'],
            'riesgo_institucional' => round($riesgoB, 1),
            'nivel_intervencion' => 'profesional',
            'intervencion_desc' => 'Representación letrada para recurso ante Juzgado Federal',
            'ventajas' => [
                'Posibilidad de mejorar el porcentaje de incapacidad dictaminado',
                'Revisión judicial del dictamen administrativo',
                'Puede incluir actualización del IBM y pisos RIPTE',
                'Acceso a pericial médica judicial independiente',
            ],
            'desventajas' => [
                'Mayor duración que la aceptación (6-18 meses)',
                'Costos de honorarios profesionales',
                'Riesgo de confirmación del dictamen original',
                'Plazos de caducidad estrictos para recurrir',
            ],
            'nota' => 'Plazo de caducidad en ' . $provincia . ': ' . ($params['calculos_especificos']['accidentes']['caducidad_impugnacion_cm_dias'][$provincia] ?? $params['calculos_especificos']['accidentes']['caducidad_impugnacion_cm_dias']['default'] ?? 90) . ' días desde la notificación del dictamen.',
        ];

        // ── Escenario C — Acción Judicial Laboral ───────────────────────────
        $cfgC = $cfgArt['judicial_laboral'];
        $benefC = $montoTarifaART * $cfgC['tasa_recupero'];
        $costoC = $honorariosBase * $cfgC['factor_honorarios'];
        $vbpC = $benefC - $costoC;
        $riesgoC = min(5.0, $irilScore * $cfgC['factor_riesgo']);
        $vaeC = ($cfgC['duracion_promedio'] > 0 && $riesgoC > 0)
            ? round($vbpC / ($cfgC['duracion_promedio'] * $riesgoC), 0) : 0;

        $c = [
            'codigo' => 'C',
            'nombre' => 'Acción Judicial Laboral',
            'descripcion' => 'Demanda judicial ante el fuero laboral de ' . $provincia . ' por diferencias en prestaciones, incumplimiento de ART o inconstitucionalidad de topes. Requiere vía administrativa agotada (Ley 27.348).',
            'beneficio_estimado' => round($benefC, 2),
            'costo_estimado' => round($costoC, 2),
            'vbp' => round($vbpC, 2),
            'vae' => $vaeC,
            'via_juridica' => 'art_judicial',
            'probabilidad_cierre' => 0.45,
            'exposicion_economica' => ['art_judicial' => round($benefC, 2)],
            'duracion_min_meses' => $cfgC['duracion_min'],
            'duracion_max_meses' => $cfgC['duracion_max'],
            'duracion_promedio' => $cfgC['duracion_promedio'],
            'riesgo_institucional' => round($riesgoC, 1),
            'nivel_intervencion' => 'profesional',
            'intervencion_desc' => 'Representación letrada obligatoria en proceso judicial',
            'ventajas' => [
                'Posibilidad de declarar inconstitucionalidad de topes',
                'Intereses judiciales desde la mora',
                'Pericial médica judicial amplia',
                'Mayor presión sobre la ART para conciliar',
            ],
            'desventajas' => [
                'Duración prolongada (2 a 4 años)',
                'Requiere agotar vía administrativa previa (Ley 27.348)',
                'Honorarios judiciales elevados',
                'Riesgo de costas si el reclamo es rechazado',
                'Saturación del fuero laboral en ' . $provincia,
            ],
            'nota' => 'Requisito previo: vía administrativa ante Comisión Médica agotada. Sin esto, la demanda puede ser rechazada in limine.',
        ];

        // ── Escenario D — Acción Civil Complementaria ───────────────────────
        $cfgD = $cfgArt['civil_complementaria'];
        $benefD = $montoCivilProbable;
        $costasCivilProbables = floatval(
            $cuantificacion['costas']['probable']
            ?? ($benefD * ($params['calculos_especificos']['accidentes']['civil']['honorarios']['probable'] ?? 0.22))
        );
        $costoD = ($honorariosBase * $cfgD['factor_honorarios']) + $costasCivilProbables;
        $vbpD = $benefD - $costoD;
        $riesgoD = min(5.0, $irilScore * $cfgD['factor_riesgo']);
        $vaeD = ($cfgD['duracion_promedio'] > 0 && $riesgoD > 0)
            ? round($vbpD / ($cfgD['duracion_promedio'] * $riesgoD), 0) : 0;
        // Base de 35% con mejora gradual según IRIL, acotada a +25% para no sobreprometer cierre.
        $probabilidadCierreD = round(0.35 + min(0.25, $irilScore / 20), 2);

        $d = [
            'codigo' => 'D',
            'nombre' => 'Acción Civil Complementaria',
            'descripcion' => 'Opción del Art. 4 Ley 26.773: renunciar a la prestación ART tarifada y accionar por la vía civil buscando reparación integral (fórmulas Méndez/Vuotto). ADVERTENCIA: Esta opción es EXCLUYENTE — no se puede cobrar tarifa ART y acción civil simultáneamente.',
            'beneficio_estimado' => round($benefD, 2),
            'costo_estimado' => round($costoD, 2),
            'vbp' => round($vbpD, 2),
            'vae' => $vaeD,
            'via_juridica' => 'civil',
            'probabilidad_cierre' => $probabilidadCierreD,
            'exposicion_economica' => [
                'conservador' => round($montoCivilConservador, 2),
                'probable' => round($montoCivilProbable, 2),
                'agresivo' => round($montoCivilAgresivo, 2),
            ],
            'duracion_min_meses' => $cfgD['duracion_min'],
            'duracion_max_meses' => $cfgD['duracion_max'],
            'duracion_promedio' => $cfgD['duracion_promedio'],
            'riesgo_institucional' => round($riesgoD, 1),
            'nivel_intervencion' => 'profesional',
            'intervencion_desc' => 'Representación letrada especializada en daños y perjuicios',
            'ventajas' => [
                'Monto potencialmente superior a la tarifa ART',
                'Incluye daño moral y reparación integral',
                'Actualización por fórmulas civiles (Méndez/Vuotto)',
                'No está sujeta a topes tarifarios del sistema LRT',
            ],
            'desventajas' => [
                'Opción EXCLUYENTE: pierde derecho a tarifa ART (Art. 4 Ley 26.773)',
                'Mayor duración (3 a 5 años)',
                'Mayor carga probatoria (debe probar culpa o dolo del empleador)',
                'Honorarios más elevados',
                'Resultado incierto: depende de pericial y jurisprudencia',
            ],
            'nota' => 'IMPORTANTE: Elegir esta vía implica renunciar definitivamente al cobro de la prestación tarifada ART. El motor la valora por rango (conservador/probable/agresivo) e integra costas probables, no por una suma acumulada con ART.',
        ];

        // ── Determinar recomendación ART ─────────────────────────────────────
        $escenarios = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d];
        $escenarios = $this->agregarIndicesEstrategicos($escenarios);
        $recomendado = $this->determinarRecomendadoART($escenarios, $estadoCM, $irilScore, $montoTarifaART, $montoCivilProbable, $situacion, $comparativa);

        $tablaComparativa = $this->construirTablaComparativa($escenarios, $recomendado);

        return [
            'escenarios' => $escenarios,
            'recomendado' => $recomendado,
            'tabla_comparativa' => $tablaComparativa,
            'via_recomendada' => $comparativa['via_recomendada'] ?? null,
        ];
    }

    /**
     * determinarRecomendadoART() — Lógica de recomendación específica ART.
     */
    private function determinarRecomendadoART(
        array $escenarios,
        string $estadoCM,
        float $iril,
        float $montoTarifa,
        float $montoCivil,
        array $situacion,
        array $comparativa = []
    ): string {
        if (($comparativa['via_recomendada'] ?? '') === 'civil') {
            return 'D';
        }

        if (($comparativa['via_recomendada'] ?? '') === 'art' && $estadoCM !== 'homologado') {
            return 'A';
        }

        // Si el dictamen CM ya fue homologado → solo D es viable (cosa juzgada administrativa)
        if ($estadoCM === 'homologado') {
            return 'D';
        }

        // Si la ART rechazó el siniestro → B (impugnar) o C (judicial)
        if (($situacion['rechazo_art'] ?? 'no') === 'si') {
            return $iril >= 3.5 ? 'C' : 'B';
        }

        // Si la vía civil vale más del doble y la incapacidad es alta → D
        $incap = floatval($situacion['porcentaje_incapacidad'] ?? 0);
        if ($montoCivil > $montoTarifa * 2 && $incap >= 30) {
            return 'D';
        }

        // Si IRIL alto → C (judicial)
        if ($iril >= 4.0) {
            return 'C';
        }

        // Default: evaluar por índice estratégico
        $mejorEscenario = 'A';
        $mejorIndice = -1.0;
        foreach ($escenarios as $codigo => $esc) {
            $indice = floatval($esc['indice_estrategico'] ?? 0);
            if ($indice > $mejorIndice) {
                $mejorIndice = $indice;
                $mejorEscenario = $codigo;
            }
        }
        return $mejorEscenario;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LÓGICA DE RECOMENDACIÓN Y TABLA COMPARATIVA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * determinarRecomendado() — Sugiere el escenario con mejor índice estratégico
     * disponible para el perfil del usuario.
     *
     * IMPORTANTE: Esta sugerencia es estructural, NO es asesoramiento legal.
     * La decisión final siempre corresponde al profesional y al cliente.
     */
    private function determinarRecomendado(
        array $escenarios,
        string $tipoUsuario,
        float $iril,
        string $tipoConflicto
    ): string {
        // Ordenar por índice estratégico y devolver el mayor
        $mejorEscenario = 'C';
        $mejorIndice = -1.0;

        foreach ($escenarios as $codigo => $escenario) {
            // No recomendar D para empleados
            if ($codigo === 'D' && $tipoUsuario === 'empleado')
                continue;

            $indice = floatval($escenario['indice_estrategico'] ?? 0);
            if ($indice > $mejorIndice) {
                $mejorIndice = $indice;
                $mejorEscenario = $codigo;
            }
        }

        return $mejorEscenario;
    }

    /**
     * construirTablaComparativa() — Genera un array simplificado para
     * la tabla comparativa visual en la pantalla de resultados.
     */
    private function construirTablaComparativa(array $escenarios, string $recomendado): array
    {
        $tabla = [];
        foreach ($escenarios as $codigo => $esc) {
            $tabla[] = [
                'codigo' => $codigo,
                'nombre' => $esc['nombre'],
                'costo' => ml_formato_moneda($esc['costo_estimado']),
                'beneficio' => ml_formato_moneda($esc['beneficio_estimado']),
                'vbp' => ml_formato_moneda($esc['vbp']),
                'indice_estrategico' => round(floatval($esc['indice_estrategico'] ?? 0), 1),
                'duracion' => $esc['duracion_min_meses'] . '-' . $esc['duracion_max_meses'] . ' meses',
                'riesgo' => $esc['riesgo_institucional'] . '/5',
                'intervencion' => ucfirst($esc['nivel_intervencion']),
                'recomendado' => ($codigo === $recomendado),
            ];
        }
        return $tabla;
    }

    private function agregarIndicesEstrategicos(array $escenarios): array
    {
        if ($escenarios === []) {
            return $escenarios;
        }

        $vbpValores = array_map(static fn(array $esc): float => floatval($esc['vbp'] ?? 0), $escenarios);
        $costos = array_map(static fn(array $esc): float => floatval($esc['costo_estimado'] ?? 0), $escenarios);
        $duraciones = array_map(
            static fn(array $esc): float => isset($esc['duracion_promedio'])
                ? floatval($esc['duracion_promedio'])
                : ((floatval($esc['duracion_min_meses'] ?? 0) + floatval($esc['duracion_max_meses'] ?? 0)) / 2),
            $escenarios
        );
        $riesgos = array_map(static fn(array $esc): float => floatval($esc['riesgo_institucional'] ?? 0), $escenarios);

        $minVbp = min($vbpValores);
        $maxVbp = max($vbpValores);
        $minCosto = min($costos);
        $maxCosto = max($costos);
        $minDuracion = min($duraciones);
        $maxDuracion = max($duraciones);
        $minRiesgo = min($riesgos);
        $maxRiesgo = max($riesgos);

        foreach ($escenarios as $codigo => $escenario) {
            $scoreRetorno = $this->normalizarMayorMejor(floatval($escenario['vbp'] ?? 0), $minVbp, $maxVbp);
            $scoreCosto = $this->normalizarMenorMejor(floatval($escenario['costo_estimado'] ?? 0), $minCosto, $maxCosto);
            $duracionValor = isset($escenario['duracion_promedio'])
                ? floatval($escenario['duracion_promedio'])
                : (floatval($escenario['duracion_min_meses'] ?? 0) + floatval($escenario['duracion_max_meses'] ?? 0)) / 2;
            $scoreDuracion = $this->normalizarMenorMejor($duracionValor, $minDuracion, $maxDuracion);
            $scoreRiesgo = $this->normalizarMenorMejor(floatval($escenario['riesgo_institucional'] ?? 0), $minRiesgo, $maxRiesgo);

            $indice = ($scoreRetorno * 0.40)
                + ($scoreCosto * 0.15)
                + ($scoreDuracion * 0.25)
                + ($scoreRiesgo * 0.20);

            $escenarios[$codigo]['indice_estrategico'] = round($indice, 1);
            $escenarios[$codigo]['indice_estrategico_componentes'] = [
                'retorno_neto' => round($scoreRetorno, 1),
                'costo' => round($scoreCosto, 1),
                'duracion' => round($scoreDuracion, 1),
                'riesgo' => round($scoreRiesgo, 1),
            ];
        }

        return $escenarios;
    }

    private function normalizarMayorMejor(float $valor, float $minimo, float $maximo): float
    {
        if ($maximo <= $minimo) {
            return 50.0;
        }

        return (($valor - $minimo) / ($maximo - $minimo)) * 100;
    }

    private function normalizarMenorMejor(float $valor, float $minimo, float $maximo): float
    {
        if ($maximo <= $minimo) {
            return 50.0;
        }

        return (($maximo - $valor) / ($maximo - $minimo)) * 100;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ALERTAS MARZO 2026 — Doctrina Ackerman + Jurisprudencia Reciente
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * generarAlertasMarzo2026() — Genera flags/avisos basados en conflictos detectados
     * 
     * @param float $totalBase
     * @param array $escenarioB       Escenario B para analizar tasas
     * @param string $provincia
     * @param string $tipoConflicto
     * @param array $situacion        Datos de situación incluyendo cct y fecha_despido
     * @return array                  ['tasa_negativa' => [alerta], 'fondo_cese' => [alerta], ...]
     */
    private function generarAlertasMarzo2026(
        float $totalBase,
        array $escenarioB,
        string $provincia,
        string $tipoConflicto,
        array $situacion
    ): array {
        $alertas = [];

        // ── ALERTA 1: Riesgo de Tasa Negativa (CABA) ────────────────────────────
        if ($provincia === 'CABA' && $escenarioB) {
            $tasa_aplicada = ($escenarioB['vae'] / max(1, $escenarioB['vbp'])) * 100;
            $inflacion_minima = 110;  // Referencia mínima

            if ($tasa_aplicada < $inflacion_minima) {
                $alertas['tasa_negativa'] = [
                    'tipo' => 'CRÍTICA',
                    'titulo' => '⚠️ RIESGO: Tasa Negativa — Acta 2764/CNAT Cuestionada',
                    'condicion' => 'La tasa aplicada en Escenario B está por debajo del 110% de inflación',
                    'valor_actual' => $tasa_aplicada,
                    'minimo_recomendado' => $inflacion_minima,
                    'aviso' => 'Atención: La tasa del Acta 2764/CNAT está siendo cuestionada por "insuficiencia reparatoria" en jurisprudencia reciente. Se sugiere análisis de reserva de caso federal por vulneración del derecho de propiedad.',
                    'jurisdiccion' => 'CABA',
                    'referencia' => 'Ackerman, Sergio (2026). "Protección de Derechos Patrimoniales", en Doctrina Laboral Marzo 2026.'
                ];
            }
        }

        // ── ALERTA 2: Inaplicabilidad de Fondo de Cese (Ley Bases) ──────────────
        $usa_fondo_cese = ($situacion['fondo_cese'] ?? 'no') === 'si';
        $cct = $situacion['cct'] ?? '';

        if ($usa_fondo_cese && !empty($cct)) {
            // Simulación: verificar si CCT tiene reglamentado Fondo de Cese a marzo 2026
            $ccts_con_fondo = ['comercio', 'construcción', 'automotriz', 'metalúrgico'];
            $tiene_fondo_reglamentado = false;

            foreach ($ccts_con_fondo as $cct_conocido) {
                if (stripos($cct, $cct_conocido) !== false) {
                    $tiene_fondo_reglamentado = true;
                    break;
                }
            }

            if (!$tiene_fondo_reglamentado) {
                $alertas['fondo_cese_inaplicable'] = [
                    'tipo' => 'SEGURIDAD',
                    'titulo' => '🛡️ Mejora de Seguridad: Fondo de Cese No Reglamentado',
                    'condicion' => "El CCT '{$cct}' no tiene ratificado acuerdo de Fondo de Cese ante Secretaría de Trabajo (marzo 2026)",
                    'cct_seleccionado' => $cct,
                    'aviso' => 'El sistema detecta que el CCT seleccionado no tiene reglamentado el Fondo de Cese. El cálculo se revierte automáticamente a la fórmula del Art. 245 LCT (Antigüedad tradicional) para evitar nulidad de liquidación.',
                    'formula_aplicada' => 'Art. 245 LCT (Antigüedad × Salario)',
                    'referencia' => 'Ley Bases Nº 27.742 — Ratificación de acuerdos ante MTESS'
                ];
            }
        }

        // ── ALERTA 3: Daño Moral Potencialmente Ampliable ─────────────────────
        $es_via_civil = ($tipoConflicto === 'accidente_laboral' || $tipoConflicto === 'daño_moral');
        
        if ($es_via_civil) {
            $alertas['danio_moral_ampliable'] = [
                'tipo' => 'OPORTUNIDAD',
                'titulo' => '📈 Jurisprudencia Marzo 2026: Daño Moral Ampliable',
                'condicion' => 'Vía civil con potencial de acreditar culpa grave o dolo del empleador',
                'piso_actual' => '20% sobre incapacidad',
                'techo_con_agravamiento' => '50% (150% de la base)',
                'aviso' => 'La jurisprudencia de marzo 2026 (Baremo Lois) permite superar el tope del art. 17 bis si se acredita falta de medidas de seguridad o dolo. Consulte si aplica agravamiento por culpa grave.',
                'check_disponible' => 'agravamiento_por_culpa_grave',
                'referencia' => 'Lois, Sergio (2026). "Baremo de Daño Moral en Accidentes Laborales". Doctrina Laboral Marzo 2026.'
            ];
        }

        // ── ALERTA 4: Eficacia de Multas Post-Ley Bases ────────────────────────
        $fecha_despido = isset($situacion['fecha_despido']) 
            ? new DateTime($situacion['fecha_despido'])
            : new DateTime();
        
        $fecha_ley_bases = new DateTime('2024-07-09');
        $es_posterior_ley_bases = $fecha_despido > $fecha_ley_bases;

        if ($es_posterior_ley_bases) {
            $alertas['multas_post_ley_bases'] = [
                'tipo' => 'INFORMACIÓN',
                'titulo' => '📢 Avisos: Multas Suspendidas pero Art. 132 bis sigue activo',
                'condicion' => 'Despido posterior a Ley Bases 27.742 (09/07/2024)',
                'estado_multas_legales' => 'Suspendidas por defecto',
                'disponible_override' => 'Check de inconstitucionalidad',
                'alerta_adicional' => 'Nota: Aunque las multas están suspendidas, la falta de registro sigue siendo sancionable vía Art. 132 bis (procedimiento administrativo ante Ministerio de Trabajo). Se sugiere calcular escenario de contingencia.',
                'opcion' => '¿Desea calcular escenario alternativo con riesgo de sanción administrativa?',
                'referencia' => 'Ackerman, Sergio (2026). "Aplicación de la Ley Bases en Materia de Multas Laborales". Ciclo de Actualizaciones Marzo 2026.'
            ];
        }

        return $alertas;
    }
}
