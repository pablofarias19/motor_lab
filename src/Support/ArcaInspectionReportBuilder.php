<?php
namespace App\Support;

final class ArcaInspectionReportBuilder
{
    public static function build(
        array $payload,
        array $irilResult,
        array $exposicion,
        array $escenariosResult,
        array $inspeccion
    ): array {
        $datos = is_array($payload['datos_laborales'] ?? null) ? $payload['datos_laborales'] : [];
        $documentacion = is_array($payload['documentacion'] ?? null) ? $payload['documentacion'] : [];
        $situacion = is_array($payload['situacion'] ?? null) ? $payload['situacion'] : [];

        $mesesNoRegistrados = max(0, intval($situacion['meses_no_registrados'] ?? 0));
        $salarioReal = floatval($datos['salario'] ?? 0);
        $salarioDeclarado = floatval($datos['salario_recibo'] ?? 0);
        $tipoRegistro = (string) ($datos['tipo_registro'] ?? 'registrado');
        $regularizacionActiva = self::boolish($situacion['aplica_blanco_laboral'] ?? 'no');
        $inspeccionPrevia = self::boolish($situacion['inspeccion_previa'] ?? 'no')
            || self::boolish($documentacion['auditoria_previa'] ?? 'no');
        $senalesFraude = self::hasFraudSignals($situacion);
        $regularizacion = is_array($inspeccion['regularizacion'] ?? null) ? $inspeccion['regularizacion'] : [];
        $alertasPenales = is_array($inspeccion['alertas_penales'] ?? null) ? $inspeccion['alertas_penales'] : [];
        $valuacionActivos = is_array($inspeccion['valuacion_activos'] ?? null) ? $inspeccion['valuacion_activos'] : [];

        $matriz = [
            'registral' => self::buildRegistralRisk($datos, $documentacion, $situacion, $mesesNoRegistrados, $tipoRegistro),
            'contributivo' => self::buildContributiveRisk($datos, $documentacion, $situacion, $mesesNoRegistrados, $tipoRegistro),
            'documental' => self::buildDocumentalRisk($documentacion, $situacion),
            'conductual' => self::buildConductualRisk($situacion, $documentacion, $inspeccionPrevia, $senalesFraude, $regularizacionActiva, $regularizacion),
        ];

        $overall = self::resolveOverallRisk($matriz, $inspeccion, $inspeccionPrevia, $senalesFraude);
        $irilScore = round(floatval($irilResult['score'] ?? 0), 1);
        $irilNivel = is_array($irilResult['nivel'] ?? null) ? $irilResult['nivel'] : ml_nivel_iril($irilScore);
        $probabilidadInspeccion = self::probabilidadInspeccion($overall['label'], $inspeccionPrevia, $senalesFraude, $mesesNoRegistrados);
        $recomendacion = self::resolveRecommendation($overall['label'], $inspeccionPrevia, $senalesFraude, $mesesNoRegistrados);
        $detalleMontos = self::buildAmountDetail($inspeccion, $regularizacion, $salarioReal, $salarioDeclarado, $mesesNoRegistrados);
        $consideracionesLegales = self::buildLegalConsiderations(
            $datos,
            $documentacion,
            $situacion,
            $regularizacion,
            $alertasPenales,
            $mesesNoRegistrados
        );

        return [
            'identificacion' => [
                'razon_social' => self::value($datos['razon_social'] ?? $datos['nombre_empresa'] ?? null),
                'cuit' => self::value($datos['cuit'] ?? null),
                'actividad' => self::value($datos['actividad'] ?? $datos['categoria'] ?? null),
                'convenio_colectivo' => self::value($datos['cct'] ?? null),
                'domicilio_fiscal' => self::value($datos['domicilio_fiscal'] ?? $datos['domicilio'] ?? null),
                'provincia_jurisdiccion' => self::value($datos['provincia'] ?? null),
                'cantidad_empleados' => max(1, intval($datos['cantidad_empleados'] ?? ($situacion['cantidad_empleados'] ?? 1))),
                'fecha_analisis' => date('Y-m-d'),
            ],
            'objeto' => [
                'Evaluar el riesgo de fiscalización por parte de ARCA (AFIP).',
                'Medir la exposición fiscal y previsional a partir de los datos cargados.',
                'Detectar contingencias documentales, registrales y conductuales.',
                'Priorizar acciones preventivas, correctivas o defensivas.',
                'Separar el análisis de moratoria (flujo) del eventual blanqueo/valuación de activos (stock).',
            ],
            'matriz_riesgo' => $matriz,
            'cuantificacion_contingencia' => [
                'base_calculo' => [
                    'formula' => 'Capital omitido = remuneración base imponible x (17% aportes + contribuciones patronales) x meses adeudados',
                    'salario_real' => round($salarioReal, 2),
                    'salario_declarado' => round($salarioDeclarado, 2),
                    'diferencia_mensual' => round(max(0, $salarioReal - $salarioDeclarado), 2),
                    'meses' => $mesesNoRegistrados,
                    'remuneracion_base_imponible' => round(floatval($inspeccion['desglose']['remuneracion_base_imponible'] ?? $salarioReal), 2),
                ],
                'aportes_omitidos' => [
                    'trabajador' => '~17%',
                    'empleador' => (string) ($inspeccion['desglose']['tasa_contrib_empleador'] ?? '~24%'),
                ],
                'intereses' => [
                    'regimen' => 'Ley 11.683',
                    'tasa_referencia' => 'Actualización ARCA / modelo preventivo interno',
                    'metodo' => 'Interés simple sobre capital original, sin capitalización.',
                ],
                'multas_posibles' => [
                    'Art. 38 Ley 11.683 - Omisión',
                    'Art. 45 Ley 11.683 - Evasión',
                    'Ley 25.212 - Infracciones laborales',
                ],
                'resultado' => [
                    'capital_omitido' => round(floatval($inspeccion['capital_omitido'] ?? 0), 2),
                    'intereses' => round(floatval($inspeccion['intereses'] ?? 0), 2),
                    'multas' => round(floatval($inspeccion['multas'] ?? 0), 2),
                    'exposicion_total' => round(floatval($inspeccion['deuda_total'] ?? 0), 2),
                ],
                'detalle_montos' => $detalleMontos,
            ],
            'moratoria_ley_27743' => self::buildMoratoriaSection($regularizacion, $situacion),
            'valuacion_regularizacion' => self::buildAssetRegularizationSection($valuacionActivos),
            'beneficios_bloqueo_fiscal' => self::buildFiscalShieldBenefits($regularizacion, $situacion),
            'alertas_penales' => self::buildPenalRiskSection($alertasPenales),
            'iril' => [
                'valor' => $irilScore,
                'nivel' => $irilNivel['nivel'] ?? 'Moderado',
                'descripcion' => $irilNivel['descripcion'] ?? '',
                'interpretacion' => self::interpretIril($irilScore),
            ],
            'diagnostico_juridico' => [
                'estado_cumplimiento' => self::estadoCumplimiento($overall['label']),
                'contingencias_previsionales' => floatval($inspeccion['capital_omitido'] ?? 0) > 0
                    ? 'Se verifican contingencias previsionales cuantificables.'
                    : 'No se detecta capital omitido relevante con la información cargada.',
                'riesgo_determinacion_oficio' => self::riesgoDeterminacion($matriz, $inspeccionPrevia, $senalesFraude),
                'riesgo_sancion_administrativa' => floatval($inspeccion['multas'] ?? 0) > 0
                    ? 'Existe riesgo cierto de sanción administrativa y multas accesorias.'
                    : 'Riesgo sancionatorio bajo en el escenario informado.',
                'riesgo_penal' => (string) ($alertasPenales['detalle'] ?? 'Sin alertas penales relevantes.'),
                'riesgo_conflicto_laboral' => $irilScore >= 3
                    ? 'El IRIL refuerza una potencial escalada del conflicto laboral.'
                    : 'La conflictividad laboral luce contenida, aunque requiere control documental.',
                'conclusion' => self::buildLegalConclusion($overall['label'], $probabilidadInspeccion, $recomendacion['label']),
            ],
            'escenarios_estrategicos' => self::buildStrategicScenarios($inspeccionPrevia, $senalesFraude, $recomendacion['label'], $escenariosResult),
            'checklist_inspeccion' => self::buildChecklist($documentacion, $situacion),
            'consideraciones_legales' => $consideracionesLegales,
            'conclusion_estrategica' => [
                'nivel_riesgo_general' => $overall['label'],
                'probabilidad_inspeccion' => $probabilidadInspeccion,
                'exposicion_economica' => round(floatval($inspeccion['deuda_total'] ?? 0), 2),
                'recomendacion_principal' => $recomendacion['label'],
                'riesgo_penal' => (string) ($alertasPenales['nivel'] ?? 'bajo'),
            ],
            'modelo_salida' => [
                'riesgo_arca' => [
                    'registral' => $matriz['registral']['indice_json'],
                    'contributivo' => $matriz['contributivo']['indice_json'],
                    'documental' => $matriz['documental']['indice_json'],
                    'conductual' => $matriz['conductual']['indice_json'],
                ],
                'deuda' => [
                    'capital' => round(floatval($inspeccion['capital_omitido'] ?? 0), 2),
                    'intereses' => round(floatval($inspeccion['intereses'] ?? 0), 2),
                    'multas' => round(floatval($inspeccion['multas'] ?? 0), 2),
                ],
                'moratoria' => [
                    'condonacion_intereses_pct' => intval($regularizacion['condonacion_intereses_pct'] ?? 0),
                    'condonacion_multas_pct' => intval($regularizacion['condonacion_multas_pct'] ?? 0),
                    'accion_penal' => (string) ($regularizacion['accion_penal'] ?? 'sin_movimiento'),
                ],
                'penal' => [
                    'nivel' => (string) ($alertasPenales['nivel'] ?? 'bajo'),
                    'tipologias' => array_values($alertasPenales['tipologias'] ?? []),
                ],
                'iril' => $irilScore,
                'nivel' => strtolower($overall['label']),
                'recomendacion' => $recomendacion['code'],
            ],
            'detalle_motor' => [
                'detalle' => (string) ($inspeccion['detalle'] ?? ''),
                'escenario_recomendado' => (string) ($escenariosResult['recomendado'] ?? 'D'),
                'regularizacion' => $regularizacion,
            ],
        ];
    }

    private static function buildRegistralRisk(array $datos, array $documentacion, array $situacion, int $mesesNoRegistrados, string $tipoRegistro): array
    {
        $items = [
            self::stateItem('Alta temprana AFIP', self::state(
                self::boolish($documentacion['registrado_afip'] ?? 'no') && self::boolish($situacion['chk_alta_sipa'] ?? 'no'),
                self::boolish($documentacion['registrado_afip'] ?? 'no') ? 'sin_dato' : 'no_cumple'
            )),
            self::stateItem('Empleados correctamente registrados', self::state(
                $tipoRegistro === 'registrado' && $mesesNoRegistrados === 0 && self::boolish($documentacion['registrado_afip'] ?? 'no'),
                $tipoRegistro === 'registrado' ? 'sin_dato' : 'no_cumple'
            )),
            self::stateItem('Categoría laboral adecuada', self::state(
                !empty($datos['categoria']) && !empty($datos['cct']),
                'sin_dato'
            )),
            self::stateItem('Jornada declarada correcta', self::state(
                $tipoRegistro === 'registrado' && $mesesNoRegistrados === 0,
                'sin_dato'
            )),
        ];

        return self::compileRiskBlock($items, [
            $tipoRegistro !== 'registrado' ? 'Se detectó una registración no íntegra del vínculo laboral.' : null,
            $mesesNoRegistrados > 0 ? 'Hay meses sin registración informados en el formulario.' : null,
        ]);
    }

    private static function buildContributiveRisk(array $datos, array $documentacion, array $situacion, int $mesesNoRegistrados, string $tipoRegistro): array
    {
        $salarioReal = floatval($datos['salario'] ?? 0);
        $salarioDeclarado = floatval($datos['salario_recibo'] ?? 0);
        $hayPagoBancario = self::boolish($documentacion['pago_bancario'] ?? ($situacion['tiene_pago_bancario'] ?? 'no'));

        $items = [
            self::stateItem('Declaraciones F931 consistentes', self::state(
                $tipoRegistro === 'registrado' && $mesesNoRegistrados === 0 && !self::boolish($situacion['falta_f931_art'] ?? 'no'),
                $mesesNoRegistrados > 0 ? 'no_cumple' : 'sin_dato'
            )),
            self::stateItem('Remuneraciones correctamente declaradas', self::state(
                $tipoRegistro === 'registrado' && ($salarioDeclarado <= 0 || $salarioDeclarado >= $salarioReal),
                $tipoRegistro === 'deficiente_salario' ? 'no_cumple' : 'sin_dato'
            )),
            self::stateItem('Pagos bancarizados', self::state($hayPagoBancario, 'sin_dato')),
            self::stateItem('Sin pagos en negro', self::state(
                $tipoRegistro === 'registrado' && $mesesNoRegistrados === 0,
                'no_cumple'
            )),
        ];

        return self::compileRiskBlock($items, [
            $salarioDeclarado > 0 && $salarioDeclarado < $salarioReal
                ? 'El salario declarado es inferior al salario real informado.'
                : null,
            !$hayPagoBancario ? 'No surge evidencia suficiente de pagos íntegramente bancarizados.' : null,
        ]);
    }

    private static function buildDocumentalRisk(array $documentacion, array $situacion): array
    {
        $items = [
            self::stateItem('Recibos firmados', self::state(self::boolish($situacion['chk_recibos_cct'] ?? 'no'), 'sin_dato')),
            self::stateItem('Libro de sueldos digital actualizado', self::state(self::boolish($situacion['chk_libro_art52'] ?? 'no'), 'sin_dato')),
            self::stateItem('Contratos laborales disponibles', self::state(
                self::boolish($documentacion['tiene_contrato'] ?? 'no')
                    || self::boolish($documentacion['contrato_escrito'] ?? ($situacion['tiene_contrato_escrito'] ?? 'no')),
                'sin_dato'
            )),
            self::stateItem('ART vigente', self::state(
                self::boolish($situacion['chk_art_vigente'] ?? 'no')
                    || (($situacion['estado_art'] ?? 'activa_valida') !== 'inexistente'),
                'sin_dato'
            )),
        ];

        return self::compileRiskBlock($items, [
            !self::boolish($situacion['chk_examenes'] ?? 'no') ? 'No se validó soporte completo de exámenes preocupacionales/periódicos.' : null,
            !self::boolish($situacion['chk_epp_rgrl'] ?? 'no') ? 'Falta confirmación de EPP y cumplimiento de higiene y seguridad.' : null,
        ]);
    }

    private static function buildConductualRisk(
        array $situacion,
        array $documentacion,
        bool $inspeccionPrevia,
        bool $senalesFraude,
        bool $regularizacionActiva,
        array $regularizacion
    ): array
    {
        $items = [
            self::stateItem('Sin antecedentes sancionatorios', self::state(!$inspeccionPrevia && !$senalesFraude, $senalesFraude ? 'no_cumple' : 'sin_dato')),
            self::stateItem('Sin inspecciones previas relevantes', self::state(!$inspeccionPrevia, $inspeccionPrevia ? 'no_cumple' : 'cumple')),
            self::stateItem('Sin juicios laborales activos significativos', self::state(
                !self::boolish($documentacion['tiene_telegramas'] ?? 'no') && !$senalesFraude,
                'sin_dato'
            )),
        ];

        return self::compileRiskBlock($items, [
            $inspeccionPrevia ? 'La empresa declaró inspecciones/auditorías previas, lo que incrementa exposición conductual.' : null,
            $senalesFraude ? 'Se activaron señales internas compatibles con evasión o maniobras irregulares.' : null,
            $regularizacionActiva ? 'La adhesión a Ley 27.743 no debe computarse como antecedente negativo autónomo.' : null,
            self::boolish($regularizacion['plan_caducado'] ?? false)
                ? 'La caducidad del plan reabre contingencia penal y prescriptiva.'
                : null,
            self::boolish($situacion['obligaciones_aduaneras'] ?? 'no')
                ? 'Si hay deuda aduanera, la novación exige pesificación previa a la regularización.'
                : null,
        ]);
    }

    private static function buildMoratoriaSection(array $regularizacion, array $situacion): array
    {
        return [
            'aplica' => self::boolish($regularizacion['aplica_regimen'] ?? false),
            'dias_desde_reglamentacion' => intval($regularizacion['dias_desde_reglamentacion'] ?? 0),
            'modalidad_pago' => (string) ($regularizacion['modalidad_pago'] ?? 'no_informada'),
            'cuotas' => intval($regularizacion['cuotas'] ?? 0),
            'condonacion_intereses_pct' => intval($regularizacion['condonacion_intereses_pct'] ?? 0),
            'condonacion_multas_pct' => intval($regularizacion['condonacion_multas_pct'] ?? 0),
            'accion_penal' => (string) ($regularizacion['accion_penal'] ?? 'sin_movimiento'),
            'riesgo_caducidad' => (string) ($regularizacion['riesgo_caducidad'] ?? 'No informado'),
            'conceptos_excluidos' => array_values($regularizacion['conceptos_excluidos_moratoria'] ?? []),
            'aclaracion' => self::boolish($situacion['obligacion_cancelada_antes_2024_03_31'] ?? 'no')
                ? 'Las obligaciones canceladas antes del 31/03/2024 pueden acceder a condonación plena de intereses.'
                : 'La condonación depende de tiempo de adhesión y modalidad de pago.',
        ];
    }

    private static function buildAssetRegularizationSection(array $valuacion): array
    {
        return [
            'activos_valorizados' => array_values($valuacion['activos'] ?? []),
            'tipo_cambio_regularizacion' => round(floatval($valuacion['tipo_cambio_regularizacion'] ?? 0), 4),
            'base_imponible_usd' => round(floatval($valuacion['base_imponible_usd'] ?? 0), 2),
            'franquicia_usd' => round(floatval($valuacion['franquicia_usd'] ?? 100000), 2),
            'excedente_gravado_usd' => round(floatval($valuacion['excedente_gravado_usd'] ?? 0), 2),
            'alicuota' => round(floatval($valuacion['alicuota'] ?? 0), 4),
            'impuesto_especial_usd' => round(floatval($valuacion['impuesto_especial_usd'] ?? 0), 2),
            'nota' => 'La valuación de stock se expresa en USD y queda separada de la moratoria previsional en pesos.',
        ];
    }

    private static function buildFiscalShieldBenefits(array $regularizacion, array $situacion): array
    {
        return [
            'liberacion_ganancias' => true,
            'liberacion_iva' => true,
            'liberacion_impuestos_internos' => true,
            'condonacion_intereses_obligaciones_canceladas_antes_31_03_2024' => self::boolish($situacion['obligacion_cancelada_antes_2024_03_31'] ?? 'no'),
            'condonacion_multas_pct' => intval($regularizacion['condonacion_multas_pct'] ?? 0),
            'antecedente_negativo' => false,
        ];
    }

    private static function buildPenalRiskSection(array $alertasPenales): array
    {
        return [
            'nivel' => (string) ($alertasPenales['nivel'] ?? 'bajo'),
            'tipologias' => array_values($alertasPenales['tipologias'] ?? []),
            'aporte_retenido_mensual' => round(floatval($alertasPenales['aporte_retenido_mensual'] ?? 0), 2),
            'capital_evadido_mensual' => round(floatval($alertasPenales['capital_evadido_mensual'] ?? 0), 2),
            'detalle' => (string) ($alertasPenales['detalle'] ?? 'Sin alertas relevantes.'),
        ];
    }

    private static function buildAmountDetail(
        array $inspeccion,
        array $regularizacion,
        float $salarioReal,
        float $salarioDeclarado,
        int $mesesNoRegistrados
    ): array {
        $capital = round(floatval($inspeccion['capital_omitido'] ?? 0), 2);
        $intereses = round(floatval($inspeccion['intereses'] ?? 0), 2);
        $multas = round(floatval($inspeccion['multas'] ?? 0), 2);
        $deudaTotal = round(floatval($inspeccion['deuda_total'] ?? 0), 2);
        $condonacionIntereses = intval($regularizacion['condonacion_intereses_pct'] ?? 0);
        $condonacionMultas = intval($regularizacion['condonacion_multas_pct'] ?? 0);

        return [
            'capital_omitido' => [
                'monto' => $capital,
                'motivo' => $capital > 0
                    ? 'Se calcula sobre remuneración imponible, aportes del trabajador, contribuciones patronales y meses omitidos declarados.'
                    : 'No surge capital omitido porque no se informó deuda previsional relevante.',
                'hechos_relevantes' => array_values(array_filter([
                    $salarioReal > 0 ? 'Salario real informado: ' . round($salarioReal, 2) : null,
                    $salarioDeclarado > 0 ? 'Salario declarado/recibo: ' . round($salarioDeclarado, 2) : null,
                    $mesesNoRegistrados > 0 ? 'Meses no registrados: ' . $mesesNoRegistrados : null,
                ])),
            ],
            'intereses' => [
                'monto' => $intereses,
                'motivo' => $intereses > 0
                    ? 'Se aplica interés simple sobre el capital omitido, con eventual reducción por acogimiento a regularización.'
                    : 'No se adicionaron intereses por ausencia de deuda o por neutralización completa del escenario informado.',
                'impacto_regularizacion' => $condonacionIntereses > 0
                    ? 'La regularización reduce intereses en ' . $condonacionIntereses . '%.'
                    : 'No se informó condonación de intereses aplicable.',
            ],
            'multas' => [
                'monto' => $multas,
                'motivo' => $multas > 0
                    ? 'Se contemplan multas por omisión/no registración y agravantes preventivos del escenario oficioso.'
                    : 'No se adicionaron multas relevantes por la información disponible o por neutralización del régimen de regularización.',
                'impacto_regularizacion' => $condonacionMultas > 0
                    ? 'La regularización reduce multas en ' . $condonacionMultas . '%.'
                    : 'No se informó condonación de multas aplicable.',
            ],
            'exposicion_total' => [
                'monto' => $deudaTotal,
                'motivo' => 'La exposición total consolida capital omitido, intereses y multas luego de aplicar las reducciones del escenario declarado.',
            ],
        ];
    }

    private static function buildLegalConsiderations(
        array $datos,
        array $documentacion,
        array $situacion,
        array $regularizacion,
        array $alertasPenales,
        int $mesesNoRegistrados
    ): array {
        $hayDeficienciaRegistral = ((string) ($datos['tipo_registro'] ?? 'registrado')) !== 'registrado'
            || !self::boolish($documentacion['registrado_afip'] ?? 'no')
            || $mesesNoRegistrados > 0;
        $aplicaLeyBases = self::boolish($situacion['aplica_blanco_laboral'] ?? 'no')
            || self::boolish($regularizacion['aplica_regimen'] ?? false);

        return [
            [
                'titulo' => 'Ley Bases Nº 27.742',
                'aplica' => $aplicaLeyBases,
                'estado' => $aplicaLeyBases ? 'Aplica de modo referencial' : 'No aplica directamente',
                'motivo' => $aplicaLeyBases
                    ? 'Se informó adhesión/regularización laboral, por lo que el informe aclara que no suma multas laborales suspendidas ajenas a la deuda previsional.'
                    : 'La deuda ARCA aquí cuantificada responde a seguridad social y fiscalización previsional; no depende de las multas indemnizatorias suspendidas por Ley Bases.',
                'impacto_en_montos' => 'Los montos del informe ARCA provienen de capital, intereses y multas previsionales; no se agregan multas laborales de despido por esta vía.',
            ],
            [
                'titulo' => 'Art. 132 bis LCT',
                'aplica' => $hayDeficienciaRegistral,
                'estado' => $hayDeficienciaRegistral ? 'Puede coexistir' : 'No surge aplicación directa',
                'motivo' => $hayDeficienciaRegistral
                    ? 'La deficiencia registral puede abrir una contingencia laboral paralela, aunque distinta de la deuda previsional cuantificada por ARCA.'
                    : 'Sin deficiencia registral material, no aparece una vía laboral autónoma relevante por este artículo.',
                'impacto_en_montos' => 'No se suma dentro de capital/intereses/multas ARCA; debe leerse como contingencia laboral complementaria.',
            ],
            [
                'titulo' => 'Jurisprudencia Ackerman (Marzo 2026)',
                'aplica' => false,
                'estado' => 'No aplica directamente',
                'motivo' => 'La doctrina Ackerman corrige bases indemnizatorias ligadas a RIPTE/IPC y no altera el cálculo de deuda previsional aquí usado.',
                'impacto_en_montos' => 'No modificó capital omitido, intereses ni multas del informe ARCA.',
            ],
            [
                'titulo' => 'Baremo Lois (Marzo 2026)',
                'aplica' => false,
                'estado' => 'No aplica directamente',
                'motivo' => 'El Baremo Lois se vincula con daño moral y culpa grave en vía civil, no con deuda previsional o fiscalización ARCA.',
                'impacto_en_montos' => 'No se adicionó agravamiento civil sobre los importes previsionales estimados.',
            ],
        ];
    }

    private static function compileRiskBlock(array $items, array $observaciones = []): array
    {
        $weights = ['cumple' => 0.0, 'sin_dato' => 0.5, 'no_cumple' => 1.0];
        $score = 0.0;

        foreach ($items as $item) {
            $score += $weights[$item['estado']] ?? 0.5;
        }

        $avg = count($items) > 0 ? $score / count($items) : 0;
        [$label, $jsonIndex] = self::riskLabelAndIndex($avg);

        return [
            'nivel' => $label,
            'indice_json' => $jsonIndex,
            'items' => $items,
            'observaciones' => array_values(array_filter($observaciones)),
        ];
    }

    private static function buildStrategicScenarios(bool $inspeccionPrevia, bool $senalesFraude, string $recomendacion, array $escenariosResult): array
    {
        $escenarioD = is_array($escenariosResult['escenarios']['D'] ?? null) ? $escenariosResult['escenarios']['D'] : [];

        return [
            [
                'codigo' => 'A',
                'titulo' => 'Regularización espontánea',
                'detalle' => 'Antes de la inspección y con foco en reducir sanciones, intereses y exposición penal tributaria.',
                'prioridad' => $inspeccionPrevia ? 'Media' : 'Alta',
            ],
            [
                'codigo' => 'B',
                'titulo' => 'Fiscalización activa',
                'detalle' => 'Supuesto de determinación de oficio, multas plenas e intereses con menor margen de corrección.',
                'prioridad' => ($inspeccionPrevia || $senalesFraude) ? 'Alta' : 'Media',
            ],
            [
                'codigo' => 'C',
                'titulo' => 'Defensa administrativa',
                'detalle' => 'Preparación de descargo, trazabilidad documental y producción de prueba frente a requerimientos.',
                'prioridad' => $inspeccionPrevia ? 'Alta' : 'Media',
            ],
            [
                'codigo' => 'D',
                'titulo' => 'Estrategia preventiva (compliance)',
                'detalle' => !empty($escenarioD['descripcion'])
                    ? (string) $escenarioD['descripcion']
                    : 'Auditoría interna, regularización estructural y reducción de contingencias futuras.',
                'prioridad' => $recomendacion === 'Regularización inmediata' ? 'Alta' : 'Muy alta',
            ],
        ];
    }

    private static function buildChecklist(array $documentacion, array $situacion): array
    {
        return [
            ['label' => 'Alta AFIP correcta', 'estado' => self::boolish($situacion['chk_alta_sipa'] ?? 'no')],
            ['label' => 'F931 presentados', 'estado' => !self::boolish($situacion['falta_f931_art'] ?? 'no')],
            ['label' => 'Recibos firmados', 'estado' => self::boolish($situacion['chk_recibos_cct'] ?? 'no')],
            ['label' => 'Libro de sueldos digital', 'estado' => self::boolish($situacion['chk_libro_art52'] ?? 'no')],
            ['label' => 'ART vigente', 'estado' => self::boolish($situacion['chk_art_vigente'] ?? 'no')],
            ['label' => 'Pagos bancarizados', 'estado' => self::boolish($documentacion['pago_bancario'] ?? ($situacion['tiene_pago_bancario'] ?? 'no'))],
            ['label' => 'Contratos laborales', 'estado' => self::boolish($documentacion['tiene_contrato'] ?? ($documentacion['contrato_escrito'] ?? 'no'))],
            ['label' => 'Certificaciones de servicios', 'estado' => self::boolish($documentacion['tiene_telegramas'] ?? 'no')],
        ];
    }

    private static function resolveOverallRisk(array $matriz, array $inspeccion, bool $inspeccionPrevia, bool $senalesFraude): array
    {
        $levels = array_column($matriz, 'nivel');
        $capital = floatval($inspeccion['capital_omitido'] ?? 0);
        $multas = floatval($inspeccion['multas'] ?? 0);

        if (in_array('Alto', $levels, true) || $senalesFraude || $inspeccionPrevia || $multas > 0) {
            return ['label' => 'Alto'];
        }

        if (in_array('Medio', $levels, true) || $capital > 0) {
            return ['label' => 'Medio'];
        }

        return ['label' => 'Bajo'];
    }

    private static function probabilidadInspeccion(string $overallRisk, bool $inspeccionPrevia, bool $senalesFraude, int $mesesNoRegistrados): string
    {
        if ($overallRisk === 'Alto' || $inspeccionPrevia || $senalesFraude || $mesesNoRegistrados >= 6) {
            return 'Alta';
        }

        if ($overallRisk === 'Medio' || $mesesNoRegistrados > 0) {
            return 'Media';
        }

        return 'Baja';
    }

    private static function resolveRecommendation(string $overallRisk, bool $inspeccionPrevia, bool $senalesFraude, int $mesesNoRegistrados): array
    {
        if ($overallRisk === 'Alto' || $inspeccionPrevia || $senalesFraude || $mesesNoRegistrados >= 6) {
            return ['label' => 'Regularización inmediata', 'code' => 'regularizacion_inmediata'];
        }

        if ($overallRisk === 'Medio' || $mesesNoRegistrados > 0) {
            return ['label' => 'Auditoría preventiva', 'code' => 'auditoria_preventiva'];
        }

        return ['label' => 'Defensa estructurada', 'code' => 'defensa_estructurada'];
    }

    private static function interpretIril(float $score): string
    {
        if ($score < 2.0) {
            return 'Bajo riesgo fiscal';
        }

        if ($score < 3.0) {
            return 'Riesgo latente';
        }

        if ($score < 4.0) {
            return 'Alta probabilidad de inspección';
        }

        return 'Contingencia crítica';
    }

    private static function estadoCumplimiento(string $overallRisk): string
    {
        return match ($overallRisk) {
            'Alto' => 'Cumplimiento crítico / irregular',
            'Medio' => 'Cumplimiento parcial con observaciones',
            default => 'Cumplimiento formal aceptable',
        };
    }

    private static function riesgoDeterminacion(array $matriz, bool $inspeccionPrevia, bool $senalesFraude): string
    {
        if (($matriz['contributivo']['nivel'] ?? 'Bajo') === 'Alto' || $inspeccionPrevia || $senalesFraude) {
            return 'Alto riesgo de determinación de oficio.';
        }

        if (($matriz['contributivo']['nivel'] ?? 'Bajo') === 'Medio') {
            return 'Riesgo medio de determinación de oficio, sujeto a fiscalización documental.';
        }

        return 'Riesgo bajo de determinación de oficio con la información disponible.';
    }

    private static function buildLegalConclusion(string $overallRisk, string $probabilidadInspeccion, string $recomendacion): string
    {
        return sprintf(
            'El cuadro actual refleja un riesgo %s con probabilidad %s de inspección. La prioridad jurídica sugerida es: %s.',
            strtolower($overallRisk),
            strtolower($probabilidadInspeccion),
            $recomendacion
        );
    }

    private static function hasFraudSignals(array $situacion): bool
    {
        foreach ([
            'fraude_facturacion_desproporcionada',
            'fraude_intermitencia_sospechosa',
            'fraude_evasion_sistematica',
            'fraude_sobre_facturacion',
            'fraude_estructura_opaca',
        ] as $flag) {
            if (self::boolish($situacion[$flag] ?? 'no')) {
                return true;
            }
        }

        return false;
    }

    private static function state(bool $condition, string $fallback = 'sin_dato'): array
    {
        return [
            'estado' => $condition ? 'cumple' : $fallback,
            'label' => $condition ? 'Cumple' : match ($fallback) {
                'no_cumple' => 'No cumple',
                default => 'Sin dato',
            },
        ];
    }

    private static function stateItem(string $label, array $state): array
    {
        return ['label' => $label] + $state;
    }

    private static function riskLabelAndIndex(float $avg): array
    {
        if ($avg <= 0.25) {
            return ['Bajo', 0];
        }

        if ($avg <= 0.60) {
            return ['Medio', 1];
        }

        return ['Alto', 2];
    }

    private static function boolish($value): bool
    {
        return function_exists('ml_boolish')
            ? ml_boolish($value)
            : in_array(strtolower((string) $value), ['si', 'yes', 'y', 'on', '1', 'true'], true);
    }

    private static function value($value): string
    {
        if (!is_scalar($value)) {
            return 'No informado';
        }

        $text = trim((string) $value);
        return $text !== '' ? $text : 'No informado';
    }
}
