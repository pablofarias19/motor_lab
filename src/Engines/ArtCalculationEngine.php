<?php
namespace App\Engines;

use DateTimeImmutable;
use Throwable;

final class ArtCalculationEngine
{
    public function calcular(array $datosLaborales, array $situacion, array $parametros): array
    {
        $edad = intval($datosLaborales['edad'] ?? 0);
        if ($edad < 16) {
            $edad = intval($parametros['edad_fallback'] ?? 35);
        }

        $salarioFallback = max(floatval($datosLaborales['salario'] ?? 0), 0);
        $fechaSiniestro = $this->parseFecha($situacion['fecha_siniestro'] ?? '');
        $salariosHistoricos = $this->normalizarSalariosHistoricos($situacion['salarios_historicos'] ?? []);
        $ripte = $this->cargarRipte($fechaSiniestro, count($salariosHistoricos));
        $salariosPorMes = $this->asignarMesesASalarios($salariosHistoricos, $fechaSiniestro);

        $ibmDetalle = $this->calcularIbmDetallado(
            $salariosPorMes,
            $fechaSiniestro,
            $ripte['tabla'],
            $salarioFallback
        );

        $incapacidadInformada = max(0.0, min(100.0, floatval($situacion['porcentaje_incapacidad'] ?? 0)));
        $dictamenPorcentaje = $this->resolveDictamen($situacion);
        $incapacidadBase = $dictamenPorcentaje ?? $incapacidadInformada;
        $preexistencia = max(0.0, min(100.0, floatval($situacion['preexistencia_porcentaje'] ?? 0)));
        $preexistenciaAplica = ($situacion['tiene_preexistencia'] ?? 'no') === 'si' && $preexistencia > 0 && $preexistencia < 100;
        $incapacidadUsada = $this->aplicarPreexistencia($incapacidadBase, $preexistenciaAplica ? $preexistencia : 0.0);

        $tipoOriginal = (string) ($situacion['incapacidad_tipo'] ?? 'permanente_definitiva');
        $clasificacion = $this->clasificarIncapacidad($tipoOriginal, $incapacidadUsada, $parametros);
        $formulaBase = floatval($parametros['coeficiente_lrt'] ?? 53)
            * $ibmDetalle['ibm']
            * (floatval($parametros['factor_edad_limite'] ?? 65) / max($edad, 16))
            * ($incapacidadUsada / 100);

        $piso = $this->cargarPisoMinimo($clasificacion['piso_tipo']);
        $pisoAplicado = aplicar_piso_minimo($formulaBase, $clasificacion['piso_tipo'], $piso['monto']);
        $adicionalGranInvalidez = $clasificacion['es_gran_invalidez']
            ? floatval($parametros['piso_gran_invalidez'] ?? $pisoAplicado['piso_minimo'])
            : 0.0;

        return [
            'vib' => round($ibmDetalle['ibm'], 2),
            'ibm' => round($ibmDetalle['ibm'], 2),
            'monto_formula' => round($formulaBase, 2),
            'monto_final' => round(floatval($pisoAplicado['monto_final'] ?? $formulaBase), 2),
            'piso_aplicado' => (bool) ($pisoAplicado['piso_aplicado'] ?? false),
            'piso_minimo' => round(floatval($pisoAplicado['piso_minimo'] ?? 0), 2),
            'piso_tipo' => $clasificacion['piso_tipo'],
            'piso_descripcion' => $clasificacion['piso_descripcion'],
            'fuente_piso' => $piso['fuente'],
            'fecha_siniestro' => $fechaSiniestro?->format('Y-m-d'),
            'ripte_referencia' => $ibmDetalle['ripte_referencia'],
            'fuente_ripte' => $ripte['fuente'],
            'calculo_estimado' => $ibmDetalle['calculo_estimado'],
            'usa_salario_fallback' => $ibmDetalle['usa_salario_fallback'],
            'salarios_considerados' => $ibmDetalle['salarios_considerados'],
            'cantidad_salarios' => count($ibmDetalle['salarios_considerados']),
            'formula_legal' => sprintf(
                '53 x %s x (%s/%d) x %.2f%%',
                'IBM',
                number_format(floatval($parametros['factor_edad_limite'] ?? 65), 0, ',', '.'),
                max($edad, 16),
                $incapacidadUsada
            ),
            'tipo_incapacidad_original' => $tipoOriginal,
            'tipo_incapacidad_calculada' => $clasificacion['tipo_incapacidad_calculada'],
            'tramo_incapacidad' => $clasificacion['tramo'],
            'tratamiento' => $clasificacion['tratamiento'],
            'incapacidad_informada' => round($incapacidadInformada, 2),
            'incapacidad_dictamen' => $dictamenPorcentaje !== null ? round($dictamenPorcentaje, 2) : null,
            'incapacidad_usada' => round($incapacidadUsada, 2),
            'preexistencia_aplicada' => $preexistenciaAplica,
            'preexistencia_porcentaje' => round($preexistenciaAplica ? $preexistencia : 0, 2),
            'adicional_gran_invalidez' => round($adicionalGranInvalidez, 2),
            'necesita_comision_medica' => $dictamenPorcentaje === null,
            'origen_incapacidad' => $dictamenPorcentaje === null ? 'estimada' : 'dictamen',
        ];
    }

    private function parseFecha(string $fecha): ?DateTimeImmutable
    {
        if ($fecha === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        return $parsed !== false ? $parsed : null;
    }

    private function resolveDictamen(array $situacion): ?float
    {
        $estado = (string) ($situacion['comision_medica'] ?? 'no_iniciada');
        if (!in_array($estado, ['dictamen_emitido', 'homologado'], true)) {
            return null;
        }

        $dictamen = floatval($situacion['dictamen_porcentaje'] ?? 0);
        if ($dictamen <= 0 || $dictamen > 100) {
            return null;
        }

        return $dictamen;
    }

    private function normalizarSalariosHistoricos($salarios): array
    {
        if (!is_array($salarios)) {
            return [];
        }

        $normalizados = [];
        foreach ($salarios as $mes => $monto) {
            if (!is_numeric($monto)) {
                continue;
            }

            $monto = floatval($monto);
            if ($monto <= 0) {
                continue;
            }

            if (is_string($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $normalizados[$mes] = $monto;
                continue;
            }

            $normalizados[] = $monto;
        }

        return $normalizados;
    }

    private function asignarMesesASalarios(array $salariosHistoricos, ?DateTimeImmutable $fechaSiniestro): array
    {
        if ($fechaSiniestro === null || $salariosHistoricos === []) {
            return [];
        }

        $asociativos = [];
        $secuenciales = [];

        foreach ($salariosHistoricos as $mes => $monto) {
            if (is_string($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $asociativos[$mes] = floatval($monto);
            } else {
                $secuenciales[] = floatval($monto);
            }
        }

        if ($asociativos !== []) {
            ksort($asociativos);
            return $asociativos;
        }

        $cantidad = count($secuenciales);
        if ($cantidad === 0) {
            return [];
        }

        $inicio = $fechaSiniestro
            ->modify('first day of this month')
            ->modify(sprintf('-%d month', $cantidad));

        $serie = [];
        foreach (array_values($secuenciales) as $index => $monto) {
            $mes = $inicio->modify(sprintf('+%d month', $index))->format('Y-m');
            $serie[$mes] = $monto;
        }

        return $serie;
    }

    private function cargarRipte(?DateTimeImmutable $fechaSiniestro, int $cantidadSalarios): array
    {
        $cantidadMeses = max(12, $cantidadSalarios + 1);

        try {
            $db = ml_conectar_bd();
            $tabla = obtener_tabla_ripte_historica($db, $cantidadMeses);
            if ($tabla !== []) {
                return [
                    'tabla' => $tabla,
                    'fuente' => 'bd',
                ];
            }
        } catch (Throwable $e) {
            ml_logear('[ArtCalculationEngine] RIPTE BD no disponible: ' . $e->getMessage(), 'warning', 'analisis.log');
        }

        $fallback = obtener_ripte_fallback();
        if ($fechaSiniestro !== null) {
            $mesReferencia = $fechaSiniestro->format('Y-m');
            if (!isset($fallback[$mesReferencia])) {
                return [
                    'tabla' => $fallback,
                    'fuente' => 'fallback_parcial',
                ];
            }
        }

        return [
            'tabla' => $fallback,
            'fuente' => 'fallback_local',
        ];
    }

    private function calcularIbmDetallado(
        array $salariosPorMes,
        ?DateTimeImmutable $fechaSiniestro,
        array $tablaRipte,
        float $salarioFallback
    ): array {
        if ($fechaSiniestro === null || $salariosPorMes === []) {
            return $this->fallbackIbm($salarioFallback, 'Faltan fecha de siniestro o salarios históricos para reconstruir IBM.');
        }

        $mesReferencia = $fechaSiniestro->format('Y-m');
        $ripteReferencia = isset($tablaRipte[$mesReferencia]) ? floatval($tablaRipte[$mesReferencia]) : 0.0;
        if ($ripteReferencia <= 0) {
            return $this->fallbackIbm($salarioFallback, 'No hay índice RIPTE para el mes del siniestro.');
        }

        $salariosActualizados = [];
        $detalle = [];

        foreach ($salariosPorMes as $mes => $salario) {
            $ripteMes = isset($tablaRipte[$mes]) ? floatval($tablaRipte[$mes]) : 0.0;
            if ($ripteMes <= 0) {
                return $this->fallbackIbm($salarioFallback, "Falta RIPTE para el salario del mes {$mes}.");
            }

            $coeficiente = $ripteReferencia / $ripteMes;
            $salarioActualizado = floatval($salario) * $coeficiente;
            $salariosActualizados[] = $salarioActualizado;
            $detalle[] = [
                'mes' => $mes,
                'salario_original' => round(floatval($salario), 2),
                'ripte_mes' => round($ripteMes, 2),
                'ripte_referencia' => round($ripteReferencia, 2),
                'coeficiente' => round($coeficiente, 6),
                'salario_actualizado' => round($salarioActualizado, 2),
            ];
        }

        if ($salariosActualizados === []) {
            return $this->fallbackIbm($salarioFallback, 'No fue posible actualizar salarios históricos.');
        }

        return [
            'ibm' => round(array_sum($salariosActualizados) / count($salariosActualizados), 2),
            'salarios_considerados' => $detalle,
            'calculo_estimado' => false,
            'usa_salario_fallback' => false,
            'ripte_referencia' => round($ripteReferencia, 2),
            'nota' => 'IBM reconstruido con salarios históricos ajustados por RIPTE hasta el mes del siniestro.',
        ];
    }

    private function fallbackIbm(float $salarioFallback, string $motivo): array
    {
        return [
            'ibm' => round($salarioFallback, 2),
            'salarios_considerados' => [],
            'calculo_estimado' => true,
            'usa_salario_fallback' => true,
            'ripte_referencia' => null,
            'nota' => $motivo,
        ];
    }

    private function aplicarPreexistencia(float $incapacidad, float $preexistencia): float
    {
        if ($preexistencia <= 0 || $preexistencia >= 100) {
            return $incapacidad;
        }

        $resultado = (1 - (1 - $incapacidad / 100) / (1 - $preexistencia / 100)) * 100;
        return max(0.0, min(100.0, $resultado));
    }

    private function clasificarIncapacidad(string $tipoOriginal, float $incapacidad, array $parametros): array
    {
        $umbralGranInvalidez = floatval($parametros['umbral_gran_invalidez'] ?? 66);
        $tipoNormalizado = strtolower(trim($tipoOriginal));
        $tipoCalculado = 'IPP';
        $pisoDescripcion = 'Piso IPP RIPTE';

        if ($tipoNormalizado === 'muerte') {
            $tipoCalculado = 'muerte';
            $pisoDescripcion = 'Piso muerte RIPTE';
        } elseif ($tipoNormalizado === 'gran_invalidez' || $incapacidad >= $umbralGranInvalidez) {
            $tipoCalculado = 'gran_invalidez';
            $pisoDescripcion = 'Piso gran invalidez RIPTE';
        } elseif ($tipoNormalizado === 'permanente_definitiva') {
            $tipoCalculado = 'IPD';
            $pisoDescripcion = 'Piso IPD RIPTE';
        } elseif (in_array($tipoNormalizado, ['transitoria', 'permanente_provisoria'], true)) {
            $tipoCalculado = 'IPP';
            $pisoDescripcion = 'Piso IPP RIPTE';
        }

        return [
            'tipo_incapacidad_calculada' => $tipoCalculado,
            'piso_tipo' => $tipoCalculado,
            'piso_descripcion' => $pisoDescripcion,
            'es_gran_invalidez' => $tipoCalculado === 'gran_invalidez',
            'tramo' => $this->resolveTramo($incapacidad),
            'tratamiento' => $this->resolveTratamiento($incapacidad),
        ];
    }

    private function resolveTramo(float $incapacidad): string
    {
        if ($incapacidad < 20) {
            return 'menor_20';
        }

        if ($incapacidad < 50) {
            return '20_a_50';
        }

        if ($incapacidad <= 66) {
            return '50_a_66';
        }

        return 'mayor_66';
    }

    private function resolveTratamiento(float $incapacidad): string
    {
        if ($incapacidad < 50) {
            return 'pago_unico';
        }

        if ($incapacidad <= 66) {
            return 'pago_unico_con_posible_renta';
        }

        return 'renta_y_capital';
    }

    private function cargarPisoMinimo(string $tipoIncapacidad): array
    {
        try {
            $db = ml_conectar_bd();
            return [
                'monto' => obtener_piso_minimo($db, $tipoIncapacidad),
                'fuente' => 'bd',
            ];
        } catch (Throwable $e) {
            ml_logear('[ArtCalculationEngine] Piso RIPTE BD no disponible: ' . $e->getMessage(), 'warning', 'analisis.log');
        }

        return [
            'monto' => obtener_piso_fallback($tipoIncapacidad),
            'fuente' => 'fallback_local',
        ];
    }
}
