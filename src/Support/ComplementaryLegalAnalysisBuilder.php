<?php
namespace App\Support;

final class ComplementaryLegalAnalysisBuilder
{
    private static bool $loaded = false;
    private const CONFLICTOS_CON_PRESUNCION = [
        'trabajo_no_registrado',
        'despido_sin_causa',
        'despido_con_causa',
    ];

    public static function build(array $datosLaborales, array $situacion, array $exposicion, array $context = []): array
    {
        self::loadFunctions();

        $tipoConflicto = (string) ($context['tipo_conflicto'] ?? '');
        $documentacion = is_array($context['documentacion'] ?? null) ? $context['documentacion'] : [];
        $aplicabilidad = self::resolveApplicability($tipoConflicto, $datosLaborales, $situacion, $documentacion);

        $ley27802 = [
            'presuncion' => $aplicabilidad['presuncion'] ? self::buildPresuncion($situacion) : null,
            'solidaria' => $aplicabilidad['solidaria'] ? self::buildSolidaria($situacion) : null,
            'fraude' => $aplicabilidad['fraude'] ? self::buildFraude($situacion) : null,
            'dano' => $aplicabilidad['dano'] ? self::buildDano($datosLaborales, $situacion, $exposicion) : null,
        ];

        return [
            'ley_27802' => $ley27802,
            'generado_en' => date('c'),
        ];
    }

    /**
     * Determina qué bloques del análisis Ley 27.802 corresponde calcular
     * según el tipo de conflicto, la situación registral y los datos efectivamente cargados.
     *
     * @return array{presuncion: bool, solidaria: bool, fraude: bool, dano: bool}
     */
    private static function resolveApplicability(
        string $tipoConflicto,
        array $datosLaborales,
        array $situacion,
        array $documentacion
    ): array {
        $registroIrregular = self::isRegistroIrregular($datosLaborales, $documentacion);
        $presuncionConDatos = self::anyPositive([
            $situacion['tiene_facturacion'] ?? 'no',
            $situacion['tiene_pago_bancario'] ?? 'no',
            $situacion['tiene_contrato_escrito'] ?? 'no',
        ]);
        $solidariaConDatos = self::anyPositive([
            $situacion['valida_cuil'] ?? 'no',
            $situacion['valida_aportes'] ?? 'no',
            $situacion['valida_pago_directo'] ?? 'no',
            $situacion['valida_cbu'] ?? 'no',
            $situacion['valida_art'] ?? 'no',
        ]);
        $fraudeConDatos = self::anyPositive([
            $situacion['fraude_facturacion_desproporcionada'] ?? 'no',
            $situacion['fraude_intermitencia_sospechosa'] ?? 'no',
            $situacion['fraude_evasion_sistematica'] ?? 'no',
            $situacion['fraude_sobre_facturacion'] ?? 'no',
            $situacion['fraude_estructura_opaca'] ?? 'no',
        ]);
        $danoConDatos = self::hasMeaningfulDamageContext($situacion);

        return [
            'presuncion' => $registroIrregular
                || $presuncionConDatos
                || in_array($tipoConflicto, self::CONFLICTOS_CON_PRESUNCION, true),
            'solidaria' => $solidariaConDatos || $tipoConflicto === 'responsabilidad_solidaria',
            'fraude' => $registroIrregular || $fraudeConDatos,
            'dano' => $registroIrregular || $danoConDatos,
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
            $situacion['valida_aportes']
                ?? ($situacion['principal_verifica_aportes']
                ?? ($situacion['principal_verifica_aaportes'] ?? false)),
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

    private static function isRegistroIrregular(array $datosLaborales, array $documentacion): bool
    {
        $tipoRegistro = (string) ($datosLaborales['tipo_registro'] ?? 'registrado');
        if ($tipoRegistro !== 'registrado') {
            return true;
        }

        return ($documentacion['registrado_afip'] ?? 'si') === 'no';
    }

    /**
     * Devuelve true cuando alguno de los valores informados se interpreta como afirmativo
     * usando la normalización booleana estándar del sistema.
     */
    private static function anyPositive(array $values): bool
    {
        foreach ($values as $value) {
            if (ml_boolish($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta un contexto mínimo para daño complementario cuando se informó
     * una extinción no estándar o un supuesto violento.
     */
    private static function hasMeaningfulDamageContext(array $situacion): bool
    {
        if (ml_boolish($situacion['fue_violenta'] ?? false)) {
            return true;
        }

        if (($situacion['tipo_extincion'] ?? 'despido') !== 'despido') {
            return true;
        }

        return false;
    }
}
