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
            'contributivo' => self::buildContributiveRisk($datos, $documentacion, $situacion, $mesesNoRegistrados, $tipoRegistro, $alertasPenales),
            'conductual' => self::buildConductualRisk($situacion, $documentacion, $inspeccionPrevia, $senalesFraude, $regularizacionActiva, $regularizacion),
            'documental' => self::buildThirdPartyRisk($documentacion, $situacion),
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
            'titulo' => 'Informe preventivo de inspección ARCA / MTESS',
            'subtitulo' => 'Modelo estructurado para análisis legal, fiscal y estratégico a nivel operativo',
            'identificacion' => [
                'razon_social' => self::value($datos['razon_social'] ?? $datos['nombre_empresa'] ?? null),
                'cuit' => self::value($datos['cuit'] ?? null),
                'actividad_arca' => self::value($datos['actividad'] ?? $datos['categoria'] ?? null),
                'convenio_colectivo' => self::value($datos['cct'] ?? null),
                'domicilio_fiscal_establecimientos' => self::value($datos['domicilio_fiscal'] ?? $datos['domicilio'] ?? null),
                'cantidad_empleados' => max(1, intval($datos['cantidad_empleados'] ?? ($situacion['cantidad_empleados'] ?? 1))),
                'fecha_analisis' => date('Y-m-d'),
            ],
            'objeto' => [
                'Detección de riesgos por empleo no registrado o deficientemente registrado.',
                'Cuantificación de contingencias fiscales y penales (ARCA / Seguridad Social).',
                'Alternativas y soluciones preventivas frente a fiscalizaciones y tercerización.',
            ],
            'matriz_riesgo' => $matriz,
            'cuantificacion_contingencia' => [
                'base_calculo' => [
                    'formula' => 'Capital omitido = remuneración base imponible x (aportes + contribuciones) x meses adeudados',
                    'salario_real' => round($salarioReal, 2),
                    'salario_declarado' => round($salarioDeclarado, 2),
                    'diferencia_mensual' => round(max(0, $salarioReal - $salarioDeclarado), 2),
                    'meses' => $mesesNoRegistrados,
                    'remuneracion_base_imponible' => round(floatval($inspeccion['desglose']['remuneracion_base_imponible'] ?? $salarioReal), 2),
                ],
                'presunciones_tributarias' => [
                    'ganancias' => 'Se presume ganancia neta equivalente a las remuneraciones no declaradas más el 10% en concepto de renta dispuesta.',
                    'iva' => 'Se presumen ventas omitidas por el mismo importe (remuneraciones ocultas + 10%), sin generación de crédito fiscal.',
                ],
                'sanciones_administrativas' => [
                    'multas_empleo_no_registrado' => 'Multa graduable por ocupación de personal no registrado.',
                    'clausura_tributaria_previsional' => 'Puede proceder clausura del establecimiento ante informalidad relevante y reincidencia.',
                ],
                'sanciones_penales' => [
                    'apropiacion_indebida_rrss' => 'La omisión de depositar aportes retenidos puede configurar apropiación indebida de recursos de la seguridad social.',
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
                'tabla_interpretacion' => [
                    ['rango' => '0 - 2', 'descripcion' => 'Bajo riesgo fiscal y laboral'],
                    ['rango' => '2 - 3', 'descripcion' => 'Riesgo latente (Tercerización controlada)'],
                    ['rango' => '3 - 4', 'descripcion' => 'Alta probabilidad de ajustes y multas (Contingencia presunta)'],
                    ['rango' => '4 - 5', 'descripcion' => 'Contingencia crítica (Riesgo de clausura y denuncia penal)'],
                ],
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
            'checklist_operativo_rrhh' => self::buildChecklist($documentacion, $situacion),
            'consideraciones_legales' => $consideracionesLegales,
            'conclusion_estrategica' => [
                'nivel_riesgo_general' => $overall['label'],
                'probabilidad_inspeccion' => $probabilidadInspeccion,
                'exposicion_economica' => 'Muy severa',
                'impacto_economico_total_estimado' => round(floatval($inspeccion['deuda_total'] ?? 0), 2),
                'recomendacion_principal' => $recomendacion['label'],
                'riesgo_penal' => (string) ($alertasPenales['nivel'] ?? 'bajo'),
                'sintesis' => 'El trabajo no registrado o deficientemente registrado puede detonar multas, clausura y ajuste presuntivo sobre IVA/Ganancias.',
            ],
            'modelo_salida' => [
                'riesgo_arca' => [
                    'registral' => $matriz['registral']['indice_json'],
                    'contributivo' => $matriz['contributivo']['indice_json'],
                    'documental' => $matriz['documental']['indice_json'],
                    'conductual' => $matriz['conductual']['indice_json'],
                ],
                'deuda' => [
                    'capital' => 'Presunción Ganancias + IVA sobre salarios omitidos',
                    'intereses' => 'Resarcitorios vigentes',
                    'multas' => 'Multa graduable + posible clausura',
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
        $hayDatoMonotributo = array_key_exists('tiene_facturacion', $situacion)
            || array_key_exists('fraude_facturacion_desproporcionada', $situacion);
        $hayFacturacionParalela = self::boolish($situacion['tiene_facturacion'] ?? 'no')
            || self::boolish($situacion['fraude_facturacion_desproporcionada'] ?? 'no');
        $hayPagosInformales = $tipoRegistro !== 'registrado'
            || $mesesNoRegistrados > 0
            || floatval($datos['salario_recibo'] ?? 0) < floatval($datos['salario'] ?? 0);

        $items = [
            self::stateItem('Inexistencia de empleados encubiertos bajo prestadores / monotributistas', self::state(
                $hayDatoMonotributo && !$hayFacturacionParalela,
                'sin_dato'
            )),
            self::stateItem('Ausencia de pagos informales o diferencias salariales no declaradas', self::state(
                !$hayPagosInformales,
                'no_cumple'
            )),
        ];

        return self::compileRiskBlock($items, [
            'titulo' => 'Riesgo de Registración y "Realidad Económica"',
            'referencia' => 'Art. 2, Ley 11.683',
            'observaciones' => [
                $tipoRegistro !== 'registrado' ? 'Se detectó una registración no íntegra del vínculo laboral.' : null,
                $mesesNoRegistrados > 0 ? 'Hay meses sin registración informados en el formulario.' : null,
                'El Fisco puede prescindir de las formas jurídicas si la realidad subyacente exhibe dependencia no registrada.',
            ],
        ]);
    }

    private static function buildContributiveRisk(
        array $datos,
        array $documentacion,
        array $situacion,
        int $mesesNoRegistrados,
        string $tipoRegistro,
        array $alertasPenales
    ): array
    {
        $salarioReal = floatval($datos['salario'] ?? 0);
        $salarioDeclarado = floatval($datos['salario_recibo'] ?? 0);
        $retencionesCorrectas = !self::boolish($situacion['hay_apropiacion_indebida'] ?? 'no')
            && !self::boolish($situacion['falta_f931_art'] ?? 'no');
        $depositoEnTermino = $mesesNoRegistrados === 0
            && !self::boolish($situacion['falta_f931_art'] ?? 'no')
            && floatval($alertasPenales['aporte_retenido_mensual'] ?? 0) <= 0;

        $items = [
            self::stateItem('Retenciones de aportes salariales practicadas correctamente', self::state(
                $retencionesCorrectas,
                'no_cumple'
            )),
            self::stateItem('Depósito de aportes y contribuciones dentro de los 10 días hábiles', self::state(
                $depositoEnTermino,
                'no_cumple'
            )),
        ];

        return self::compileRiskBlock($items, [
            'titulo' => 'Riesgo Contributivo y Retenciones (Penal Tributario)',
            'referencia' => 'Art. 9, Régimen Penal Tributario',
            'observaciones' => [
                $salarioDeclarado > 0 && $salarioDeclarado < $salarioReal
                    ? 'El salario declarado es inferior al salario real informado.'
                    : null,
                'La omisión de depositar aportes retenidos puede configurar apropiación indebida de recursos de la seguridad social.',
            ],
        ]);
    }

    private static function buildThirdPartyRisk(array $documentacion, array $situacion): array
    {
        $hayDatoControl = array_key_exists('control_documental', $situacion)
            || array_key_exists('control_operativo', $situacion)
            || array_key_exists('tiene_contrato', $documentacion);
        $hayDatoArt = array_key_exists('chk_art_vigente', $situacion)
            || array_key_exists('falta_f931_art', $situacion);
        $controlMensual = self::boolish($situacion['control_documental'] ?? 'no')
            && self::boolish($situacion['control_operativo'] ?? 'no')
            && self::boolish($documentacion['tiene_contrato'] ?? 'no');
        $artTercerizados = self::boolish($situacion['chk_art_vigente'] ?? 'no')
            && !self::boolish($situacion['falta_f931_art'] ?? 'no');

        $items = [
            self::stateItem('Exigencia mensual de CUIL, recibos firmados, F.931 y pagos bancarizados', self::state(
                $hayDatoControl && $controlMensual,
                'sin_dato'
            )),
            self::stateItem('Verificación de póliza de ART de personal tercerizado', self::state(
                $hayDatoArt && $artTercerizados,
                'sin_dato'
            )),
        ];

        return self::compileRiskBlock($items, [
            'titulo' => 'Riesgo por Tercerización (Art. 30 LCT)',
            'referencia' => 'Art. 30 LCT',
            'observaciones' => [
                self::boolish($situacion['actividad_esencial'] ?? 'no') || self::boolish($situacion['integracion_estructura'] ?? 'no')
                    ? 'La actividad tercerizada aparece integrada al giro principal, elevando la solidaridad potencial.'
                    : null,
                'La obligación de control documental es irrenunciable e indelegable para la empresa principal.',
            ],
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
        $hayDatoRequerimientos = array_key_exists('incumplio_requerimientos_arca', $situacion);
        $items = [
            self::stateItem('Respuestas a requerimientos de AFIP / ARCA en plazo legal', self::state(
                $hayDatoRequerimientos && !self::boolish($situacion['incumplio_requerimientos_arca'] ?? 'no'),
                'sin_dato'
            )),
            self::stateItem('Personal instruido frente a encuestas o relevamientos in situ', self::state(
                self::boolish($situacion['personal_instruido_inspeccion'] ?? 'no'),
                'sin_dato'
            )),
        ];

        return self::compileRiskBlock($items, [
            'titulo' => 'Riesgo Operativo en Inspecciones (Conductual)',
            'referencia' => 'Arts. 35 y 39 Ley 11.683',
            'observaciones' => [
                $inspeccionPrevia ? 'La empresa declaró inspecciones previas, lo que incrementa exposición conductual.' : null,
                $senalesFraude ? 'Se activaron señales internas compatibles con evasión o maniobras irregulares.' : null,
                $regularizacionActiva ? 'La regularización espontánea atenúa riesgo pero no sustituye el protocolo de respuesta inspectiva.' : null,
                self::boolish($regularizacion['plan_caducado'] ?? false)
                    ? 'La caducidad del plan reabre contingencia penal y prescriptiva.'
                    : null,
                self::boolish($situacion['obligaciones_aduaneras'] ?? 'no')
                    ? 'Si hay deuda aduanera, la novación exige pesificación previa a la regularización.'
                    : null,
            ],
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
                'titulo' => 'Art. 2 Ley 11.683 - Realidad económica',
                'aplica' => $hayDeficienciaRegistral,
                'estado' => $hayDeficienciaRegistral ? 'Aplica' : 'Aplicación condicionada',
                'motivo' => $hayDeficienciaRegistral
                    ? 'La autoridad fiscal puede desestimar la forma jurídica cuando la operatoria encubre una relación de dependencia real.'
                    : 'Sin desajustes registrales materiales, la recaracterización por realidad económica pierde intensidad.',
                'impacto_en_montos' => 'Sostiene la presunción de Ganancias e IVA sobre remuneraciones omitidas.',
            ],
            [
                'titulo' => 'Art. 9 Régimen Penal Tributario',
                'aplica' => floatval($alertasPenales['aporte_retenido_mensual'] ?? 0) > 0 || self::boolish($situacion['hay_apropiacion_indebida'] ?? 'no'),
                'estado' => (floatval($alertasPenales['aporte_retenido_mensual'] ?? 0) > 0 || self::boolish($situacion['hay_apropiacion_indebida'] ?? 'no'))
                    ? 'Riesgo penal activo'
                    : 'Sin activación relevante',
                'motivo' => 'La omisión de depositar aportes retenidos dentro del plazo legal puede configurar apropiación indebida de recursos de la seguridad social.',
                'impacto_en_montos' => 'Eleva la exposición por sanción penal y exige reacción temprana de regularización o defensa.',
            ],
            [
                'titulo' => 'Arts. 35 y 39 Ley 11.683',
                'aplica' => true,
                'estado' => 'Aplica',
                'motivo' => 'Regulan facultades de fiscalización, requerimientos y sanciones ante resistencia o incumplimiento del inspeccionado.',
                'impacto_en_montos' => 'Explican el riesgo conductual y la necesidad de un protocolo interno ante encuestas y relevamientos.',
            ],
            [
                'titulo' => 'Art. 30 LCT',
                'aplica' => self::boolish($situacion['actividad_esencial'] ?? 'no')
                    || self::boolish($situacion['integracion_estructura'] ?? 'no')
                    || !self::boolish($situacion['control_documental'] ?? 'si'),
                'estado' => (
                    self::boolish($situacion['actividad_esencial'] ?? 'no')
                    || self::boolish($situacion['integracion_estructura'] ?? 'no')
                    || !self::boolish($situacion['control_documental'] ?? 'si')
                ) ? 'Aplica / requiere control estricto' : 'Aplicación preventiva',
                'motivo' => 'La empresa principal debe exigir mensualmente documentación del contratista y controlar ART, F.931 y pagos bancarizados.',
                'impacto_en_montos' => 'Puede proyectar solidaridad laboral y previsional sobre la principal si falla la barrera documental.',
            ],
            [
                'titulo' => 'Ley Bases Nº 27.742',
                'aplica' => $aplicaLeyBases,
                'estado' => $aplicaLeyBases ? 'Aplica de modo referencial' : 'No altera el núcleo del cálculo',
                'motivo' => $aplicaLeyBases
                    ? 'La regularización espontánea mejora la posición defensiva y atenúa sanciones laborales relacionadas.'
                    : 'El núcleo de este informe sigue siendo fiscal/previsional y no depende de multas indemnizatorias suspendidas.',
                'impacto_en_montos' => 'Opera como marco contextual de regularización, no como fuente principal del capital omitido.',
            ],
        ];
    }

    private static function compileRiskBlock(array $items, array $options = []): array
    {
        $weights = ['cumple' => 0.0, 'sin_dato' => 0.5, 'no_cumple' => 1.0];
        $score = 0.0;

        foreach ($items as $item) {
            $score += $weights[$item['estado']] ?? 0.5;
        }

        $avg = count($items) > 0 ? $score / count($items) : 0;
        [$label, $jsonIndex] = self::riskLabelAndIndex($avg);

        return [
            'titulo' => (string) ($options['titulo'] ?? ''),
            'referencia' => (string) ($options['referencia'] ?? ''),
            'nivel' => $label,
            'indice_json' => $jsonIndex,
            'items' => $items,
            'observaciones' => array_values(array_filter($options['observaciones'] ?? [])),
        ];
    }

    private static function buildStrategicScenarios(bool $inspeccionPrevia, bool $senalesFraude, string $recomendacion, array $escenariosResult): array
    {
        return [
            [
                'codigo' => 'A',
                'titulo' => 'Solución Preventiva: Auditoría de Contratación y Subcontratación',
                'detalle' => 'Revisar monotributistas exclusivos y desplazar estructuras jurídicas inadecuadas.',
                'acciones' => [
                    'Relevar contratos de locación o facturación paralela que puedan encubrir dependencia.',
                    'Implementar control mensual de F.931, pagos bancarizados y ART para contratistas.',
                ],
                'prioridad' => ($senalesFraude || $inspeccionPrevia) ? 'Alta' : 'Media',
            ],
            [
                'codigo' => 'B',
                'titulo' => 'Solución Correctiva: Regularización Espontánea',
                'detalle' => 'Registrar al personal detectado antes de la inspección para reducir sanciones y mejorar defensa.',
                'acciones' => [
                    'Regularizar íntegramente las relaciones laborales detectadas internamente.',
                    'Preparar documentación para audiencia de descargo y atenuación de multas.',
                ],
                'prioridad' => $recomendacion === 'Compliance Laboral-Tributario Inmediato' ? 'Muy alta' : 'Alta',
            ],
            [
                'codigo' => 'C',
                'titulo' => 'Solución Operativa ante Fiscalización in situ',
                'detalle' => 'Protocolo de recepción de inspectores, identificación de firmantes y producción inmediata de prueba propia.',
                'acciones' => [
                    'Centralizar respuestas ante requerimientos y encuestas de personal.',
                    'Recolectar asistencia, recibos, biometría y soportes internos para desvirtuar manifestaciones inexactas.',
                ],
                'prioridad' => ($inspeccionPrevia || $senalesFraude) ? 'Muy alta' : 'Alta',
            ],
        ];
    }

    private static function buildChecklist(array $documentacion, array $situacion): array
    {
        return [
            ['label' => 'Nómina 100% registrada en Sistema Registral', 'estado' => self::boolish($situacion['chk_alta_sipa'] ?? 'no') && self::boolish($documentacion['registrado_afip'] ?? 'no')],
            ['label' => 'Recibos de sueldo firmados y coincidentes con pagos bancarios', 'estado' => self::boolish($situacion['chk_recibos_cct'] ?? 'no') && self::boolish($documentacion['pago_bancario'] ?? ($situacion['tiene_pago_bancario'] ?? 'no'))],
            ['label' => 'Libro de Sueldos Digital al día', 'estado' => self::boolish($situacion['chk_libro_art52'] ?? 'no')],
            ['label' => 'Sin facturación correlativa de monotributistas internos', 'estado' => !self::boolish($situacion['tiene_facturacion'] ?? 'no')],
            ['label' => 'F.931 dentro de los 10 días para evitar riesgo penal', 'estado' => !self::boolish($situacion['falta_f931_art'] ?? 'no')],
            ['label' => 'Archivo documental mensual de contratistas / tercerizados', 'estado' => self::boolish($situacion['control_documental'] ?? 'no') || self::boolish($documentacion['tiene_contrato'] ?? 'no')],
        ];
    }

    private static function resolveOverallRisk(array $matriz, array $inspeccion, bool $inspeccionPrevia, bool $senalesFraude): array
    {
        $riskIndices = array_map(static fn(array $bloque): int => intval($bloque['indice_json'] ?? 1), $matriz);
        $capital = floatval($inspeccion['capital_omitido'] ?? 0);
        $multas = floatval($inspeccion['multas'] ?? 0);
        $riskCount = count($riskIndices);
        $promedio = $riskCount > 0 ? array_sum($riskIndices) / $riskCount : 1.0;

        if ($promedio >= 4.3 || ($promedio >= 4.0 && ($senalesFraude || $inspeccionPrevia) && $multas > 0)) {
            return ['label' => 'Crítico', 'indice' => 5];
        }

        if ($promedio >= 3.2 || $capital > 0 || $multas > 0 || $senalesFraude || $inspeccionPrevia) {
            return ['label' => 'Alto', 'indice' => 4];
        }

        if ($promedio >= 2.2) {
            return ['label' => 'Medio', 'indice' => 3];
        }

        return ['label' => 'Bajo', 'indice' => 1];
    }

    private static function probabilidadInspeccion(string $overallRisk, bool $inspeccionPrevia, bool $senalesFraude, int $mesesNoRegistrados): string
    {
        if ($overallRisk === 'Crítico') {
            return 'Muy alta';
        }

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
        if ($overallRisk === 'Crítico' || $overallRisk === 'Alto' || $inspeccionPrevia || $senalesFraude || $mesesNoRegistrados >= 6) {
            return [
                'label' => 'Compliance Laboral-Tributario Inmediato',
                'code' => 'compliance_laboral_y_regularizacion_espontanea',
            ];
        }

        if ($overallRisk === 'Medio' || $mesesNoRegistrados > 0) {
            return ['label' => 'Auditoría preventiva', 'code' => 'auditoria_preventiva'];
        }

        return ['label' => 'Monitoreo documentado', 'code' => 'monitoreo_documentado'];
    }

    private static function interpretIril(float $score): string
    {
        if ($score < 2.0) {
            return 'Bajo riesgo fiscal y laboral';
        }

        if ($score < 3.0) {
            return 'Riesgo latente (Tercerización controlada)';
        }

        if ($score < 4.0) {
            return 'Alta probabilidad de ajustes y multas (Contingencia presunta)';
        }

        return 'Contingencia crítica (Riesgo de clausura y denuncia penal)';
    }

    private static function estadoCumplimiento(string $overallRisk): string
    {
        return match ($overallRisk) {
            'Crítico' => 'Cumplimiento crítico / exposición severa',
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
            'El cuadro actual refleja un riesgo %s con probabilidad %s de fiscalización. La prioridad jurídica sugerida es: %s.',
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
        if ($avg <= 0.10) {
            return ['Bajo', 1];
        }

        if ($avg <= 0.35) {
            return ['Bajo', 2];
        }

        if ($avg <= 0.60) {
            return ['Medio', 3];
        }

        if ($avg <= 0.85) {
            return ['Alto', 4];
        }

        return ['Crítico', 5];
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
