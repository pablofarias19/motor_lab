<?php
namespace App\Support;

use Exception;

final class AnalysisPayloadNormalizer
{
    public static function normalize(array $input): array
    {
        $datosInput = is_array($input['datos_laborales'] ?? null) ? $input['datos_laborales'] : [];
        $documentacionInput = is_array($input['documentacion'] ?? null) ? $input['documentacion'] : [];
        $situacionInput = is_array($input['situacion'] ?? null) ? $input['situacion'] : [];
        $contactoInput = is_array($input['contacto'] ?? null) ? $input['contacto'] : [];

        $email = self::string($contactoInput['email'] ?? ($input['email'] ?? ''));
        if ($email !== '' && !ml_validar_email($email)) {
            throw new Exception('El email informado no es válido.');
        }

        $datosLaborales = array_merge($datosInput, [
            'salario' => self::float($datosInput['salario'] ?? 0),
            'antiguedad_meses' => self::int($datosInput['antiguedad_meses'] ?? 0),
            'provincia' => self::string($datosInput['provincia'] ?? 'CABA', 'CABA'),
            'categoria' => self::string($datosInput['categoria'] ?? ''),
            'cct' => self::string($datosInput['cct'] ?? ''),
            'cantidad_empleados' => self::int($datosInput['cantidad_empleados'] ?? 1, 1),
            'edad' => self::int($datosInput['edad'] ?? 0),
            'tipo_registro' => self::string($datosInput['tipo_registro'] ?? 'registrado', 'registrado'),
            'salario_recibo' => self::float($datosInput['salario_recibo'] ?? 0),
            'antiguedad_recibo' => self::int($datosInput['antiguedad_recibo'] ?? 0),
        ]);

        $documentacion = array_merge([
            'tiene_telegramas' => 'no',
            'tiene_recibos' => 'no',
            'tiene_contrato' => 'no',
            'registrado_afip' => 'no',
            'tiene_testigos' => 'no',
            'auditoria_previa' => 'no',
            'tiene_facturacion' => $situacionInput['tiene_facturacion'] ?? 'no',
            'pago_bancario' => $situacionInput['tiene_pago_bancario'] ?? 'no',
            'contrato_escrito' => $situacionInput['tiene_contrato_escrito'] ?? 'no',
        ], $documentacionInput);

        foreach ([
            'tiene_telegramas',
            'tiene_recibos',
            'tiene_contrato',
            'registrado_afip',
            'tiene_testigos',
            'auditoria_previa',
            'tiene_facturacion',
            'pago_bancario',
            'contrato_escrito',
        ] as $flag) {
            $documentacion[$flag] = self::flag($documentacion[$flag] ?? 'no');
        }

        $salariosHistoricos = $situacionInput['salarios_historicos'] ?? [];
        if (!is_array($salariosHistoricos)) {
            $salariosHistoricos = [];
        }

        $situacion = array_merge($situacionInput, [
            'hay_intercambio' => self::flag($situacionInput['hay_intercambio'] ?? 'no'),
            'fue_intimado' => self::flag($situacionInput['fue_intimado'] ?? 'no'),
            'ya_despedido' => self::flag($situacionInput['ya_despedido'] ?? 'no'),
            'urgencia' => self::string($situacionInput['urgencia'] ?? 'media', 'media'),
            'cantidad_empleados' => self::int($situacionInput['cantidad_empleados'] ?? 1, 1),
            'fecha_despido' => self::string($situacionInput['fecha_despido'] ?? ''),
            'fecha_ultimo_telegrama' => self::string($situacionInput['fecha_ultimo_telegrama'] ?? ''),
            'dia_despido' => max(1, min(31, self::int($situacionInput['dia_despido'] ?? 15, 15))),
            'check_inconstitucionalidad' => self::flag($situacionInput['check_inconstitucionalidad'] ?? 'no'),
            'jurisdiccion' => self::string($situacionInput['jurisdiccion'] ?? 'CABA', 'CABA'),
            'salarios_historicos' => array_values(array_map('floatval', array_filter($salariosHistoricos, 'is_numeric'))),
            'tiene_facturacion' => self::flag($situacionInput['tiene_facturacion'] ?? 'no'),
            'tiene_pago_bancario' => self::flag($situacionInput['tiene_pago_bancario'] ?? 'no'),
            'tiene_contrato_escrito' => self::flag($situacionInput['tiene_contrato_escrito'] ?? 'no'),
            'cantidad_subcontratistas' => self::int($situacionInput['cantidad_subcontratistas'] ?? 1, 1),
            'valida_cuil' => self::flag($situacionInput['valida_cuil'] ?? ($situacionInput['principal_valida_cuil'] ?? 'no')),
            'valida_aportes' => self::flag($situacionInput['valida_aportes'] ?? ($situacionInput['principal_verifica_aaportes'] ?? 'no')),
            'valida_pago_directo' => self::flag($situacionInput['valida_pago_directo'] ?? ($situacionInput['principal_paga_directo'] ?? 'no')),
            'valida_cbu' => self::flag($situacionInput['valida_cbu'] ?? ($situacionInput['principal_valida_cbu_trabajador'] ?? 'no')),
            'valida_art' => self::flag($situacionInput['valida_art'] ?? ($situacionInput['principal_cubre_art'] ?? 'no')),
            'nivel_cumplimiento' => self::string($situacionInput['nivel_cumplimiento'] ?? 'desconocido', 'desconocido'),
            'fraude_facturacion_desproporcionada' => self::flag($situacionInput['fraude_facturacion_desproporcionada'] ?? 'no'),
            'fraude_intermitencia_sospechosa' => self::flag($situacionInput['fraude_intermitencia_sospechosa'] ?? 'no'),
            'fraude_evasion_sistematica' => self::flag($situacionInput['fraude_evasion_sistematica'] ?? 'no'),
            'fraude_sobre_facturacion' => self::flag($situacionInput['fraude_sobre_facturacion'] ?? 'no'),
            'fraude_estructura_opaca' => self::flag($situacionInput['fraude_estructura_opaca'] ?? 'no'),
            'tipo_extincion' => self::string($situacionInput['tipo_extincion'] ?? 'despido', 'despido'),
            'fue_violenta' => self::flag($situacionInput['fue_violenta'] ?? 'no'),
            'meses_litigio' => self::int($situacionInput['meses_litigio'] ?? 36, 36),
        ]);

        $situacion['principal_valida_cuil'] = $situacion['valida_cuil'];
        $situacion['principal_verifica_aaportes'] = $situacion['valida_aportes'];
        $situacion['principal_paga_directo'] = $situacion['valida_pago_directo'];
        $situacion['principal_valida_cbu_trabajador'] = $situacion['valida_cbu'];
        $situacion['principal_cubre_art'] = $situacion['valida_art'];

        return [
            'tipo_usuario' => self::string($input['tipo_usuario'] ?? ''),
            'tipo_conflicto' => self::string($input['tipo_conflicto'] ?? ''),
            'datos_laborales' => $datosLaborales,
            'documentacion' => $documentacion,
            'situacion' => $situacion,
            'contacto' => ['email' => $email],
            'schema_version' => self::string($input['schema_version'] ?? '2026-04', '2026-04'),
        ];
    }

    private static function string($value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $value = trim((string) $value);
        return $value === '' ? $default : $value;
    }

    private static function int($value, int $default = 0): int
    {
        return is_numeric($value) ? intval($value) : $default;
    }

    private static function float($value, float $default = 0): float
    {
        return is_numeric($value) ? floatval($value) : $default;
    }

    private static function flag($value, string $default = 'no'): string
    {
        $normalized = strtolower(self::string($value, $default));
        return in_array($normalized, ['si', 'no'], true) ? $normalized : $default;
    }
}
