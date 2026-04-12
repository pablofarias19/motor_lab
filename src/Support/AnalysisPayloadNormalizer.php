<?php
namespace App\Support;

final class AnalysisPayloadNormalizer
{
    private const TIPOS_USUARIO = ['empleado', 'empleador'];
    private const TIPOS_REGISTRO = ['registrado', 'no_registrado', 'deficiente_fecha', 'deficiente_salario'];
    private const TIPOS_CONFLICTO = [
        'despido_sin_causa',
        'despido_con_causa',
        'trabajo_no_registrado',
        'diferencias_salariales',
        'accidente_laboral',
        'responsabilidad_solidaria',
        'auditoria_preventiva',
        'riesgo_inspeccion',
    ];
    private const URGENCIAS = ['alta', 'media', 'baja'];
    private const JURISDICCIONES = ['CABA', 'PBA', 'CORDOBA', 'SANTA_FE', 'default'];

    public static function normalize(array $input): array
    {
        $tipoUsuario = self::string($input['tipo_usuario'] ?? '');
        $tipoConflicto = self::string($input['tipo_conflicto'] ?? '');
        $datosInput = is_array($input['datos_laborales'] ?? null) ? $input['datos_laborales'] : [];
        $documentacionInput = is_array($input['documentacion'] ?? null) ? $input['documentacion'] : [];
        $situacionInput = is_array($input['situacion'] ?? null) ? $input['situacion'] : [];
        $contactoInput = is_array($input['contacto'] ?? null) ? $input['contacto'] : [];
        $errors = [];

        $email = self::string($contactoInput['email'] ?? ($input['email'] ?? ''));
        if ($email !== '' && !ml_validar_email($email)) {
            $errors['contacto.email'] = 'El email informado no es válido.';
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

        [$datosLaborales, $documentacion] = self::normalizeRegistrationConsistency($datosLaborales, $documentacion);

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
            'motivo_diferencia' => self::string($situacionInput['motivo_diferencia'] ?? 'mala_categorizacion', 'mala_categorizacion'),
            'meses_adeudados' => self::int($situacionInput['meses_adeudados'] ?? 0),
            'dia_despido' => max(1, min(31, self::int($situacionInput['dia_despido'] ?? 15, 15))),
            'check_inconstitucionalidad' => self::flag($situacionInput['check_inconstitucionalidad'] ?? 'no'),
            'jurisdiccion' => self::string($situacionInput['jurisdiccion'] ?? 'CABA', 'CABA'),
            'salarios_historicos' => array_values(array_map('floatval', array_filter($salariosHistoricos, 'is_numeric'))),
            'tipo_contingencia' => self::string($situacionInput['tipo_contingencia'] ?? 'accidente_tipico', 'accidente_tipico'),
            'fecha_siniestro' => self::string($situacionInput['fecha_siniestro'] ?? ''),
            'porcentaje_incapacidad' => self::float($situacionInput['porcentaje_incapacidad'] ?? 0),
            'incapacidad_tipo' => self::string($situacionInput['incapacidad_tipo'] ?? 'permanente_definitiva', 'permanente_definitiva'),
            'estado_art' => self::string($situacionInput['estado_art'] ?? 'activa_valida', 'activa_valida'),
            'tiene_art' => self::flag($situacionInput['tiene_art'] ?? 'no'),
            'culpa_grave' => self::flag($situacionInput['culpa_grave'] ?? 'no'),
            'via_civil' => self::flag($situacionInput['via_civil'] ?? 'no'),
            'denuncia_art' => self::flag($situacionInput['denuncia_art'] ?? 'no'),
            'rechazo_art' => self::flag($situacionInput['rechazo_art'] ?? 'no'),
            'comision_medica' => self::string($situacionInput['comision_medica'] ?? 'no_iniciada', 'no_iniciada'),
            'dictamen_porcentaje' => self::float($situacionInput['dictamen_porcentaje'] ?? 0),
            'via_administrativa_agotada' => self::flag($situacionInput['via_administrativa_agotada'] ?? 'no'),
            'tiene_preexistencia' => self::flag($situacionInput['tiene_preexistencia'] ?? 'no'),
            'preexistencia_porcentaje' => self::float($situacionInput['preexistencia_porcentaje'] ?? 0),
            'licencia_activa' => self::flag($situacionInput['licencia_activa'] ?? 'no'),
            'tiene_facturacion' => self::flag($situacionInput['tiene_facturacion'] ?? 'no'),
            'tiene_pago_bancario' => self::flag($situacionInput['tiene_pago_bancario'] ?? 'no'),
            'tiene_contrato_escrito' => self::flag($situacionInput['tiene_contrato_escrito'] ?? 'no'),
            'cantidad_subcontratistas' => self::int($situacionInput['cantidad_subcontratistas'] ?? 1, 1),
            'valida_cuil' => self::flag($situacionInput['valida_cuil'] ?? ($situacionInput['principal_valida_cuil'] ?? 'no')),
            'valida_aportes' => self::flag(
                $situacionInput['valida_aportes']
                ?? ($situacionInput['principal_verifica_aportes']
                ?? ($situacionInput['principal_verifica_aaportes'] ?? 'no')) // compatibilidad con payloads antiguos
            ),
            'valida_pago_directo' => self::flag($situacionInput['valida_pago_directo'] ?? ($situacionInput['principal_paga_directo'] ?? 'no')),
            'valida_cbu' => self::flag($situacionInput['valida_cbu'] ?? ($situacionInput['principal_valida_cbu_trabajador'] ?? 'no')),
            'valida_art' => self::flag($situacionInput['valida_art'] ?? ($situacionInput['principal_cubre_art'] ?? 'no')),
            'nivel_cumplimiento' => self::string($situacionInput['nivel_cumplimiento'] ?? 'desconocido', 'desconocido'),
            'actividad_esencial' => self::flag($situacionInput['actividad_esencial'] ?? 'no'),
            'control_documental' => self::flag($situacionInput['control_documental'] ?? 'no'),
            'control_operativo' => self::flag($situacionInput['control_operativo'] ?? 'no'),
            'integracion_estructura' => self::flag($situacionInput['integracion_estructura'] ?? 'no'),
            'contrato_formal' => self::flag($situacionInput['contrato_formal'] ?? 'no'),
            'falta_f931_art' => self::flag($situacionInput['falta_f931_art'] ?? 'no'),
            'meses_no_registrados' => self::int($situacionInput['meses_no_registrados'] ?? 0),
            'meses_en_mora' => self::int($situacionInput['meses_en_mora'] ?? 0),
            'aplica_blanco_laboral' => self::flag($situacionInput['aplica_blanco_laboral'] ?? 'no'),
            'probabilidad_condena' => self::float($situacionInput['probabilidad_condena'] ?? 0.5, 0.5),
            'inspeccion_previa' => self::flag($situacionInput['inspeccion_previa'] ?? 'no'),
            'chk_alta_sipa' => self::flag($situacionInput['chk_alta_sipa'] ?? 'no'),
            'chk_libro_art52' => self::flag($situacionInput['chk_libro_art52'] ?? 'no'),
            'chk_recibos_cct' => self::flag($situacionInput['chk_recibos_cct'] ?? 'no'),
            'chk_art_vigente' => self::flag($situacionInput['chk_art_vigente'] ?? 'no'),
            'chk_examenes' => self::flag($situacionInput['chk_examenes'] ?? 'no'),
            'chk_epp_rgrl' => self::flag($situacionInput['chk_epp_rgrl'] ?? 'no'),
            'fraude_facturacion_desproporcionada' => self::flag($situacionInput['fraude_facturacion_desproporcionada'] ?? 'no'),
            'fraude_intermitencia_sospechosa' => self::flag($situacionInput['fraude_intermitencia_sospechosa'] ?? 'no'),
            'fraude_evasion_sistematica' => self::flag($situacionInput['fraude_evasion_sistematica'] ?? 'no'),
            'fraude_sobre_facturacion' => self::flag($situacionInput['fraude_sobre_facturacion'] ?? 'no'),
            'fraude_estructura_opaca' => self::flag($situacionInput['fraude_estructura_opaca'] ?? 'no'),
            'tipo_extincion' => self::string($situacionInput['tipo_extincion'] ?? 'despido', 'despido'),
            'fue_violenta' => self::flag($situacionInput['fue_violenta'] ?? 'no'),
            'meses_litigio' => self::int($situacionInput['meses_litigio'] ?? 36, 36),
        ]);

        $situacion = self::normalizeSituationConsistency($tipoUsuario, $tipoConflicto, $situacion);

        if (!in_array($tipoUsuario, self::TIPOS_USUARIO, true)) {
            $errors['tipo_usuario'] = 'Seleccioná un perfil válido.';
        }

        if (!in_array($tipoConflicto, self::TIPOS_CONFLICTO, true)) {
            $errors['tipo_conflicto'] = 'Seleccioná un tipo de conflicto válido.';
        }

        if ($datosLaborales['salario'] <= 0) {
            $errors['datos_laborales.salario'] = 'El salario debe ser mayor a cero.';
        }

        if ($datosLaborales['antiguedad_meses'] < 0 || $datosLaborales['antiguedad_meses'] > 600) {
            $errors['datos_laborales.antiguedad_meses'] = 'La antigüedad debe estar entre 0 y 600 meses.';
        }

        if ($datosLaborales['provincia'] === '') {
            $errors['datos_laborales.provincia'] = 'Seleccioná la provincia.';
        }

        if ($datosLaborales['cantidad_empleados'] < 1) {
            $errors['datos_laborales.cantidad_empleados'] = 'La cantidad de empleados debe ser al menos 1.';
        }

        if (!in_array($situacion['urgencia'], self::URGENCIAS, true)) {
            $errors['situacion.urgencia'] = 'Seleccioná un nivel de urgencia válido.';
        }

        if (!in_array($situacion['jurisdiccion'], self::JURISDICCIONES, true)) {
            $errors['situacion.jurisdiccion'] = 'La jurisdicción informada no es válida.';
        }

        if (!self::isValidDateOrEmpty($situacion['fecha_despido'])) {
            $errors['situacion.fecha_despido'] = 'La fecha de despido debe tener formato YYYY-MM-DD.';
        }

        if (!self::isValidDateOrEmpty($situacion['fecha_ultimo_telegrama'])) {
            $errors['situacion.fecha_ultimo_telegrama'] = 'La fecha del último telegrama debe tener formato YYYY-MM-DD.';
        }

        if (!self::isValidDateOrEmpty($situacion['fecha_siniestro'])) {
            $errors['situacion.fecha_siniestro'] = 'La fecha del siniestro debe tener formato YYYY-MM-DD.';
        }

        if ($situacion['porcentaje_incapacidad'] < 0 || $situacion['porcentaje_incapacidad'] > 100) {
            $errors['situacion.porcentaje_incapacidad'] = 'El porcentaje de incapacidad debe estar entre 0 y 100.';
        }

        if ($situacion['preexistencia_porcentaje'] < 0 || $situacion['preexistencia_porcentaje'] > 100) {
            $errors['situacion.preexistencia_porcentaje'] = 'La preexistencia debe estar entre 0 y 100.';
        }

        if ($situacion['probabilidad_condena'] < 0 || $situacion['probabilidad_condena'] > 1) {
            $errors['situacion.probabilidad_condena'] = 'La probabilidad de condena debe estar entre 0 y 1.';
        }

        if ($situacion['meses_litigio'] < 1 || $situacion['meses_litigio'] > 240) {
            $errors['situacion.meses_litigio'] = 'La duración estimada del litigio debe estar entre 1 y 240 meses.';
        }

        if ($tipoConflicto === 'accidente_laboral' && ($datosLaborales['edad'] < 16 || $datosLaborales['edad'] > 90)) {
            $errors['datos_laborales.edad'] = 'Para accidentes, la edad debe estar entre 16 y 90 años.';
        }

        if (!empty($errors)) {
            throw new InvalidPayloadException('Hay datos del formulario incompletos o inconsistentes.', $errors);
        }

        $situacion['principal_valida_cuil'] = $situacion['valida_cuil'];
        $situacion['principal_verifica_aportes'] = $situacion['valida_aportes'];
        $situacion['principal_verifica_aaportes'] = $situacion['valida_aportes'];
        $situacion['principal_paga_directo'] = $situacion['valida_pago_directo'];
        $situacion['principal_valida_cbu_trabajador'] = $situacion['valida_cbu'];
        $situacion['principal_cubre_art'] = $situacion['valida_art'];
        $situacion['tiene_art'] = self::flag(
            $tipoUsuario === 'empleador'
                ? (($situacion['estado_art'] ?? 'activa_valida') === 'inexistente' ? 'no' : 'si')
                : ($situacionInput['tiene_art'] ?? 'no')
        );

        return [
            'tipo_usuario' => $tipoUsuario,
            'tipo_conflicto' => $tipoConflicto,
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

    private static function isValidDateOrEmpty(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function normalizeRegistrationConsistency(array $datosLaborales, array $documentacion): array
    {
        $tipoRegistro = self::string($datosLaborales['tipo_registro'] ?? 'registrado', 'registrado');
        if (!in_array($tipoRegistro, self::TIPOS_REGISTRO, true)) {
            $tipoRegistro = 'registrado';
        }

        $datosLaborales['tipo_registro'] = $tipoRegistro;

        switch ($tipoRegistro) {
            case 'no_registrado':
                $datosLaborales['salario_recibo'] = 0.0;
                $datosLaborales['antiguedad_recibo'] = 0;
                $documentacion['tiene_recibos'] = 'no';
                $documentacion['registrado_afip'] = 'no';
                break;

            case 'deficiente_fecha':
                $datosLaborales['salario_recibo'] = 0.0;
                break;

            case 'deficiente_salario':
                $datosLaborales['antiguedad_recibo'] = 0;
                break;

            case 'registrado':
                $datosLaborales['salario_recibo'] = 0.0;
                $datosLaborales['antiguedad_recibo'] = 0;
                break;
        }

        return [$datosLaborales, $documentacion];
    }

    private static function normalizeSituationConsistency(string $tipoUsuario, string $tipoConflicto, array $situacion): array
    {
        $esAccidente = $tipoConflicto === 'accidente_laboral';
        $esDiferencia = $tipoConflicto === 'diferencias_salariales';
        $esPrevencion = in_array($tipoConflicto, ['responsabilidad_solidaria', 'riesgo_inspeccion', 'auditoria_preventiva'], true);
        $esAuditoria = in_array($tipoConflicto, ['auditoria_preventiva', 'riesgo_inspeccion'], true);
        $esSolidaridad = $tipoConflicto === 'responsabilidad_solidaria';

        if (($situacion['hay_intercambio'] ?? 'no') !== 'si') {
            $situacion['fecha_ultimo_telegrama'] = '';
        }

        if (!$esAccidente) {
            $situacion['tipo_contingencia'] = 'accidente_tipico';
            $situacion['fecha_siniestro'] = '';
            $situacion['porcentaje_incapacidad'] = 0.0;
            $situacion['incapacidad_tipo'] = 'permanente_definitiva';
            $situacion['culpa_grave'] = 'no';
            $situacion['via_civil'] = 'no';
            $situacion['denuncia_art'] = 'no';
            $situacion['rechazo_art'] = 'no';
            $situacion['comision_medica'] = 'no_iniciada';
            $situacion['dictamen_porcentaje'] = 0.0;
            $situacion['via_administrativa_agotada'] = 'no';
            $situacion['tiene_preexistencia'] = 'no';
            $situacion['preexistencia_porcentaje'] = 0.0;
            $situacion['licencia_activa'] = 'no';
        } else {
            if (($situacion['tiene_preexistencia'] ?? 'no') !== 'si') {
                $situacion['preexistencia_porcentaje'] = 0.0;
            }

            if (!in_array($situacion['comision_medica'] ?? 'no_iniciada', ['dictamen_emitido', 'homologado'], true)) {
                $situacion['dictamen_porcentaje'] = 0.0;
            }

            $tieneArt = self::flag(
                $tipoUsuario === 'empleador'
                    ? (($situacion['estado_art'] ?? 'activa_valida') === 'inexistente' ? 'no' : 'si')
                    : ($situacion['tiene_art'] ?? 'no')
            );

            if ($tieneArt !== 'si') {
                $situacion['denuncia_art'] = 'no';
                $situacion['rechazo_art'] = 'no';
                $situacion['comision_medica'] = 'no_iniciada';
                $situacion['dictamen_porcentaje'] = 0.0;
                $situacion['via_administrativa_agotada'] = 'no';
            }
        }

        if (!$esDiferencia) {
            $situacion['motivo_diferencia'] = 'mala_categorizacion';
            $situacion['meses_adeudados'] = 0;
        }

        if (!$esPrevencion) {
            $situacion['inspeccion_previa'] = 'no';
            $situacion['cantidad_subcontratistas'] = 1;
        }

        if (!$esAuditoria) {
            $situacion['meses_no_registrados'] = 0;
            $situacion['meses_en_mora'] = 0;
            $situacion['aplica_blanco_laboral'] = 'no';
            $situacion['chk_alta_sipa'] = 'no';
            $situacion['chk_libro_art52'] = 'no';
            $situacion['chk_recibos_cct'] = 'no';
            $situacion['chk_art_vigente'] = 'no';
            $situacion['chk_examenes'] = 'no';
            $situacion['chk_epp_rgrl'] = 'no';
        }

        if ($tipoConflicto !== 'auditoria_preventiva') {
            $situacion['nivel_cumplimiento'] = 'desconocido';
        }

        if (!$esSolidaridad) {
            $situacion['actividad_esencial'] = 'no';
            $situacion['control_documental'] = 'no';
            $situacion['control_operativo'] = 'no';
            $situacion['integracion_estructura'] = 'no';
            $situacion['contrato_formal'] = 'no';
            $situacion['falta_f931_art'] = 'no';
        }

        return $situacion;
    }
}
