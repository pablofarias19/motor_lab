<?php
namespace App\Engines;

/**
 * ArcaEngine.php — Motor preventivo ARCA / seguridad social
 *
 * Separa el análisis de flujo (moratoria de obligaciones) de la valuación
 * referencial de stock (regularización de activos), manteniendo compatibilidad
 * con la salida histórica del motor.
 */
class ArcaEngine
{
    private float $tasaAporte = 0.17; // 11% jubilación + 3% INSSBPyP + 3% obra social
    private float $tasaContribucionGeneral = 0.24;
    private float $tasaContribucionReducida = 0.204;
    private float $tasaInteresMensual = 0.05; // Modelo preventivo interno, interés simple
    private float $coefMultaOmision = 1.0;
    private float $coefMultaNoRegistracion = 0.5;
    private int $umbralApropiacionIndebida = 100000;
    private int $umbralEvasionPrevisional = 200000;
    private int $umbralCriticoBeneficios = 400000;

    /**
     * @param array<string,mixed> $contexto
     */
    public function calcularRiesgoArca(
        float $salario,
        int $mesesEnNegro,
        bool $regularizaVoluntariamente = false,
        bool $hayDolo = false,
        array $contexto = []
    ): array {
        $remuneracionBase = $this->resolverRemuneracionBase($salario, $contexto);
        $cantidadEmpleados = max(1, intval($contexto['cantidad_empleados'] ?? 1));
        $tasaContribucion = $this->resolverTasaContribucion($cantidadEmpleados);
        $plan = $this->resolverPlanRegularizacion($regularizaVoluntariamente, $contexto);
        $beneficios = $this->resolverBeneficiosMoratoria($plan, $contexto);

        if ($mesesEnNegro <= 0 || $remuneracionBase <= 0) {
            return [
                'capital_omitido' => 0.0,
                'intereses' => 0.0,
                'multas' => 0.0,
                'deuda_total' => 0.0,
                'detalle' => 'Sin deuda previsional aparente.',
                'aplica_ley_bases' => false,
                'regularizacion' => $beneficios,
                'alertas_penales' => $this->buildPenalAlerts(0.0, 0.0, false, false, $contexto),
                'valuacion_activos' => $this->calcularValuacionActivos($contexto),
            ];
        }

        $aportesMensuales = $remuneracionBase * $this->tasaAporte;
        $contribucionesMensuales = $remuneracionBase * $tasaContribucion;
        $cargasMensuales = $aportesMensuales + $contribucionesMensuales;
        $capitalOmitido = $cargasMensuales * $mesesEnNegro;

        $mesesMoraMedia = max(1, (int) ceil($mesesEnNegro / 2));
        $interesBase = $capitalOmitido * $this->tasaInteresMensual * $mesesMoraMedia;
        $interesTotal = $interesBase * (1 - ($beneficios['condonacion_intereses_pct'] / 100));

        $multaBase = $capitalOmitido * ($hayDolo ? ($this->coefMultaOmision * 2) : $this->coefMultaOmision);
        $multaNoRegistracion = $remuneracionBase * $mesesEnNegro * $this->coefMultaNoRegistracion;
        $multaTotal = ($multaBase + $multaNoRegistracion) * (1 - ($beneficios['condonacion_multas_pct'] / 100));

        $deudaTotal = $capitalOmitido + $interesTotal + $multaTotal;
        $alertasPenales = $this->buildPenalAlerts(
            $cargasMensuales,
            $aportesMensuales,
            $hayDolo,
            $plan['usa_beneficios_fiscales'],
            $contexto
        );

        return [
            'capital_omitido' => round($capitalOmitido, 2),
            'intereses' => round($interesTotal, 2),
            'multas' => round($multaTotal, 2),
            'deuda_total' => round($deudaTotal, 2),
            'desglose' => [
                'tasa_aporte_empleado' => round($this->tasaAporte * 100, 1) . '%',
                'tasa_contrib_empleador' => round($tasaContribucion * 100, 1) . '%',
                'segmento_contribuciones' => $cantidadEmpleados <= 25 ? 'micro_pequena' : 'general',
                'meses_adeudados' => $mesesEnNegro,
                'condicion_dolo' => $hayDolo,
                'aplica_ley_bases' => $regularizaVoluntariamente,
                'remuneracion_base_imponible' => round($remuneracionBase, 2),
                'aportes_mensuales' => round($aportesMensuales, 2),
                'contribuciones_mensuales' => round($contribucionesMensuales, 2),
                'interes_simple_mensual_referencia' => round($this->tasaInteresMensual * 100, 2) . '%',
            ],
            'detalle' => $this->buildDetalle($plan, $beneficios, $alertasPenales),
            'regularizacion' => $beneficios,
            'alertas_penales' => $alertasPenales,
            'valuacion_activos' => $this->calcularValuacionActivos($contexto),
        ];
    }

    /**
     * @param array<string,mixed> $contexto
     * @return array<string,mixed>
     */
    private function resolverPlanRegularizacion(bool $regularizaVoluntariamente, array $contexto): array
    {
        $dias = max(0, intval($contexto['dias_desde_reglamentacion'] ?? ($regularizaVoluntariamente ? 30 : 999)));
        $cuotas = max(1, intval($contexto['cuotas_regularizacion'] ?? 1));
        $modalidad = strtolower(trim((string) ($contexto['modalidad_pago_regularizacion'] ?? '')));
        $usaBeneficios = $this->boolish($contexto['usa_beneficios_fiscales'] ?? $contexto['utiliza_exenciones_fraudulentas'] ?? false);

        if ($modalidad === '') {
            $modalidad = $cuotas <= 3 ? 'plan_corto' : 'plan_largo';
        }

        if ($modalidad === 'contado') {
            $cuotas = 1;
        }

        return [
            'adhiere' => $regularizaVoluntariamente,
            'dias_desde_reglamentacion' => $dias,
            'modalidad_pago' => $modalidad,
            'cuotas' => $cuotas,
            'sentencia_firme' => $this->boolish($contexto['sentencia_firme'] ?? false),
            'plan_caducado' => $this->boolish($contexto['plan_caducado'] ?? false),
            'obligacion_cancelada_antes_corte' => $this->boolish($contexto['obligacion_cancelada_antes_2024_03_31'] ?? false),
            'deuda_aduanera' => $this->boolish($contexto['obligaciones_aduaneras'] ?? false),
            'usa_beneficios_fiscales' => $usaBeneficios,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $contexto
     * @return array<string,mixed>
     */
    private function resolverBeneficiosMoratoria(array $plan, array $contexto): array
    {
        $condonacionIntereses = 0;
        $condonacionMultas = 0;

        if ($plan['obligacion_cancelada_antes_corte']) {
            $condonacionIntereses = 100;
            $condonacionMultas = 100;
        } elseif ($plan['adhiere']) {
            $condonacionIntereses = $this->resolverCondonacionIntereses(
                intval($plan['dias_desde_reglamentacion']),
                (string) $plan['modalidad_pago'],
                intval($plan['cuotas'])
            );
            $condonacionMultas = 100;
        }

        $accionPenal = 'sin_movimiento';
        if ($plan['adhiere']) {
            if ($plan['sentencia_firme']) {
                $accionPenal = 'sin_extincion_por_sentencia_firme';
            } elseif ($plan['plan_caducado']) {
                $accionPenal = 'reanudacion_por_caducidad';
            } elseif (in_array($plan['modalidad_pago'], ['contado', 'plan_corto', 'plan_largo'], true)) {
                $accionPenal = 'extinguida_por_cancelacion_total';
            } else {
                $accionPenal = 'suspendida_por_acogimiento';
            }
        }

        $honorariosBase = max(0, floatval($contexto['honorarios_estimados'] ?? 0));
        $honorariosReducidos = $plan['adhiere'] ? round($honorariosBase * 0.5, 2) : round($honorariosBase, 2);

        return [
            'aplica_regimen' => $plan['adhiere'],
            'dias_desde_reglamentacion' => intval($plan['dias_desde_reglamentacion']),
            'modalidad_pago' => (string) $plan['modalidad_pago'],
            'cuotas' => intval($plan['cuotas']),
            'condonacion_intereses_pct' => $condonacionIntereses,
            'condonacion_multas_pct' => $condonacionMultas,
            'accion_penal' => $accionPenal,
            'riesgo_caducidad' => $plan['adhiere']
                ? 'La caducidad del plan reactiva la acción penal y el cómputo de la prescripción.'
                : 'Sin plan informado.',
            'antecedente_negativo' => false,
            'novacion_aduanera' => $plan['deuda_aduanera']
                ? 'La deuda aduanera se novará y se pesificará al tipo comprador BNA del día anterior a la adhesión.'
                : 'No informada',
            'reduccion_honorarios_pct' => $plan['adhiere'] ? 50 : 0,
            'honorarios_estimados_reducidos' => $honorariosReducidos,
            'conceptos_excluidos_moratoria' => [
                'Aportes y contribuciones a Obras Sociales',
                'Deudas de ART',
                'Aportes de casas particulares',
                'Monotributo',
                'Seguro de Vida Obligatorio',
            ],
        ];
    }

    private function resolverCondonacionIntereses(int $dias, string $modalidad, int $cuotas): int
    {
        $modalidad = $modalidad === 'contado' ? 'contado' : $modalidad;
        $esPlanCorto = $modalidad === 'contado' || ($modalidad === 'plan_corto') || $cuotas <= 3;

        if ($dias <= 30 && $esPlanCorto) {
            return 70;
        }
        if ($dias <= 60 && $esPlanCorto) {
            return 60;
        }
        if ($dias <= 90 && $esPlanCorto) {
            return 50;
        }
        if ($dias <= 90) {
            return 40;
        }

        return 20;
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function resolverRemuneracionBase(float $salario, array $contexto): float
    {
        $componentes = $contexto['componentes_remunerativos'] ?? [];
        $extras = 0.0;

        if (is_array($componentes)) {
            foreach ($componentes as $monto) {
                $extras += max(0, floatval($monto));
            }
        }

        foreach ([
            'gratificaciones_habituales',
            'propinas_habituales',
            'suplementos_habituales',
            'remuneracion_en_especie',
        ] as $campo) {
            $extras += max(0, floatval($contexto[$campo] ?? 0));
        }

        return round(max(0, $salario) + $extras, 2);
    }

    private function resolverTasaContribucion(int $cantidadEmpleados): float
    {
        return $cantidadEmpleados <= 25
            ? $this->tasaContribucionReducida
            : $this->tasaContribucionGeneral;
    }

    /**
     * @param array<string,mixed> $contexto
     * @return array<string,mixed>
     */
    private function buildPenalAlerts(
        float $cargasMensuales,
        float $aportesMensuales,
        bool $hayDolo,
        bool $usaBeneficiosFiscales,
        array $contexto
    ): array {
        $apropiacionIndebida = $this->boolish($contexto['hay_apropiacion_indebida'] ?? false)
            || ($aportesMensuales > $this->umbralApropiacionIndebida && floatval($contexto['salario_declarado'] ?? 0) > 0);
        $evasionPrevisional = $cargasMensuales > $this->umbralEvasionPrevisional;
        $criticoBeneficios = ($hayDolo || $usaBeneficiosFiscales) && $cargasMensuales > $this->umbralCriticoBeneficios;

        $nivel = 'bajo';
        if ($criticoBeneficios) {
            $nivel = 'critico';
        } elseif ($apropiacionIndebida || $evasionPrevisional) {
            $nivel = 'alto';
        }

        $tipologias = [];
        if ($apropiacionIndebida) {
            $tipologias[] = 'apropiacion_indebida';
        }
        if ($evasionPrevisional) {
            $tipologias[] = 'evasion_previsional';
        }
        if ($criticoBeneficios) {
            $tipologias[] = 'agravante_beneficios_fiscales';
        }

        return [
            'nivel' => $nivel,
            'tipologias' => $tipologias,
            'umbral_apropiacion_indebida' => $this->umbralApropiacionIndebida,
            'umbral_evasion_previsional' => $this->umbralEvasionPrevisional,
            'umbral_agravante_beneficios' => $this->umbralCriticoBeneficios,
            'aporte_retenido_mensual' => round($aportesMensuales, 2),
            'capital_evadido_mensual' => round($cargasMensuales, 2),
            'detalle' => $criticoBeneficios
                ? 'Riesgo penal crítico por superar umbral agravado con beneficios/exenciones cuestionables.'
                : ($apropiacionIndebida || $evasionPrevisional
                    ? 'Existen indicadores de riesgo penal tributario/previsional por montos mensuales.'
                    : 'No se activan umbrales penales con la información disponible.'),
        ];
    }

    /**
     * @param array<string,mixed> $contexto
     * @return array<string,mixed>
     */
    private function calcularValuacionActivos(array $contexto): array
    {
        $activos = $contexto['activos_regularizables'] ?? [];
        if (!is_array($activos) || $activos === []) {
            return [
                'activos' => [],
                'base_imponible_usd' => 0.0,
                'franquicia_usd' => 100000.0,
                'excedente_gravado_usd' => 0.0,
                'alicuota' => 0.0,
                'impuesto_especial_usd' => 0.0,
                'tipo_cambio_regularizacion' => round(floatval($contexto['tipo_cambio_regularizacion'] ?? 1), 4),
            ];
        }

        $tipoCambio = max(0.0001, floatval($contexto['tipo_cambio_regularizacion'] ?? 1000));
        $etapa = max(1, min(3, intval($contexto['etapa_regularizacion'] ?? 1)));
        $alicuotas = [1 => 0.05, 2 => 0.10, 3 => 0.15];
        $alicuota = $alicuotas[$etapa];
        $franquicia = 100000.0;
        $activosValuados = [];
        $totalUsd = 0.0;

        foreach ($activos as $activo) {
            if (!is_array($activo)) {
                continue;
            }

            $valorUsd = $this->valuarActivoUsd($activo, $tipoCambio);
            $activosValuados[] = [
                'tipo' => (string) ($activo['tipo'] ?? 'otro'),
                'ubicacion' => (string) ($activo['ubicacion'] ?? 'argentina'),
                'valor_usd' => round($valorUsd, 2),
            ];
            $totalUsd += $valorUsd;
        }

        $excedente = max(0, $totalUsd - $franquicia);
        $dineroEspecial = $this->boolish(
            $contexto['dinero_en_caja_hasta_franquicia']
            ?? ($contexto['dinero_en_cera_hasta_franquicia'] ?? false)
        );
        $impuesto = ($dineroEspecial && $totalUsd <= $franquicia) ? 0.0 : ($excedente * $alicuota);

        return [
            'activos' => $activosValuados,
            'base_imponible_usd' => round($totalUsd, 2),
            'franquicia_usd' => $franquicia,
            'excedente_gravado_usd' => round($excedente, 2),
            'alicuota' => $alicuota,
            'impuesto_especial_usd' => round($impuesto, 2),
            'tipo_cambio_regularizacion' => round($tipoCambio, 4),
            'etapa_regularizacion' => $etapa,
        ];
    }

    /**
     * @param array<string,mixed> $activo
     */
    private function valuarActivoUsd(array $activo, float $tipoCambio): float
    {
        $tipo = strtolower(trim((string) ($activo['tipo'] ?? 'otro')));
        $moneda = strtoupper(trim((string) ($activo['moneda'] ?? 'USD')));
        $valorNominal = floatval($activo['valor'] ?? 0);

        $valorBase = match ($tipo) {
            'inmueble', 'inmuebles' => max(floatval($activo['valor_mercado'] ?? 0), floatval($activo['valor_minimo_reglamentario'] ?? 0), $valorNominal),
            'acciones', 'participaciones' => max(floatval($activo['vpp'] ?? 0), $valorNominal),
            'credito', 'creditos' => floatval($activo['capital'] ?? 0) + floatval($activo['actualizaciones'] ?? 0) + floatval($activo['intereses_devengados'] ?? 0),
            'intangible', 'intangibles' => floatval($activo['costo_adquisicion'] ?? $valorNominal) * max(1, floatval($activo['factor_ipc'] ?? 1)),
            default => $valorNominal,
        };

        return $this->convertirAUsd($valorBase, $moneda, $tipoCambio, $activo);
    }

    /**
     * @param array<string,mixed> $activo
     */
    private function convertirAUsd(float $monto, string $moneda, float $tipoCambio, array $activo): float
    {
        if ($monto <= 0) {
            return 0.0;
        }

        if ($moneda === 'USD') {
            return $monto;
        }

        if ($moneda === 'ARS') {
            return $monto / $tipoCambio;
        }

        $cotizacion = max(0.0001, floatval($activo['cotizacion_moneda_usd'] ?? 0));
        return $cotizacion > 0 ? ($monto * $cotizacion) : $monto;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $beneficios
     * @param array<string,mixed> $alertasPenales
     */
    private function buildDetalle(array $plan, array $beneficios, array $alertasPenales): string
    {
        if (!$plan['adhiere']) {
            return $alertasPenales['nivel'] === 'alto' || $alertasPenales['nivel'] === 'critico'
                ? 'Inspección oficiosa: intereses simples, multas plenas y alertas penales activas.'
                : 'Inspección oficiosa: aplicación plena de multas Ley 11.683 e intereses resarcitorios simples.';
        }

        return sprintf(
            'Regularización Ley 27.743: condonación de intereses del %d%%, multas 100%% y estado penal "%s".',
            intval($beneficios['condonacion_intereses_pct'] ?? 0),
            str_replace('_', ' ', (string) ($beneficios['accion_penal'] ?? 'sin_movimiento'))
        );
    }

    private function boolish($value): bool
    {
        return function_exists('ml_boolish')
            ? ml_boolish($value)
            : in_array(strtolower((string) $value), ['si', 'yes', 'y', 'on', '1', 'true'], true);
    }
}
