<?php
namespace App\Support;

final class ComplementaryLegalAnalysisBuilder
{
    private static bool $loaded = false;

    public static function build(array $datosLaborales, array $situacion, array $exposicion): array
    {
        self::loadFunctions();

        $ley27802 = [
            'presuncion' => self::buildPresuncion($situacion),
            'solidaria' => self::buildSolidaria($situacion),
            'fraude' => self::buildFraude($situacion),
            'dano' => self::buildDano($datosLaborales, $situacion, $exposicion),
        ];

        return [
            'ley_27802' => $ley27802,
            'generado_en' => date('c'),
        ];
    }

    private static function buildPresuncion(array $situacion): ?array
    {
        return validar_presuncion_laboral(
            $situacion['tiene_facturacion'] ?? false,
            $situacion['tiene_pago_bancario'] ?? false,
            $situacion['tiene_contrato_escrito'] ?? false
        );
    }

    private static function buildSolidaria(array $situacion): ?array
    {
        return validar_responsabilidad_solidaria(
            $situacion['valida_cuil'] ?? false,
            $situacion['valida_aportes'] ?? false,
            $situacion['valida_pago_directo'] ?? false,
            $situacion['valida_cbu'] ?? false,
            $situacion['valida_art'] ?? false
        );
    }

    private static function buildFraude(array $situacion): ?array
    {
        return detectar_fraude_laboral([
            'facturacion_desproporcionada' => $situacion['fraude_facturacion_desproporcionada'] ?? false,
            'intermitencia_sospechosa' => $situacion['fraude_intermitencia_sospechosa'] ?? false,
            'evasion_sistematica' => $situacion['fraude_evasion_sistematica'] ?? false,
            'sobre_facturacion' => $situacion['fraude_sobre_facturacion'] ?? false,
            'estructura_opaca' => $situacion['fraude_estructura_opaca'] ?? false,
        ]);
    }

    private static function buildDano(array $datosLaborales, array $situacion, array $exposicion): ?array
    {
        $salarioBase = floatval($datosLaborales['salario'] ?? 0);
        if ($salarioBase <= 0) {
            return null;
        }

        $indemnizacionBase = floatval($exposicion['total'] ?? ($exposicion['total_con_multas'] ?? $salarioBase));

        return evaluar_dano_complementario(
            $indemnizacionBase,
            $salarioBase,
            $situacion['tipo_extincion'] ?? 'despido',
            $situacion['fue_violenta'] ?? false,
            intval($situacion['meses_litigio'] ?? 36)
        );
    }

    private static function loadFunctions(): void
    {
        if (self::$loaded) {
            return;
        }

        $root = dirname(__DIR__, 2);
        require_once $root . '/config/ripte_functions.php';
        self::$loaded = true;
    }
}
