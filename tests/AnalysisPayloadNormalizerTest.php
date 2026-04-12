<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

use App\Support\AnalysisPayloadNormalizer;
use App\Support\InvalidPayloadException;

final class AnalysisPayloadNormalizerTest extends TestCase
{
    public function run(): void
    {
        $payload = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'responsabilidad_solidaria',
            'datos_laborales' => [
                'salario' => '1500000.50',
                'antiguedad_meses' => '36',
                'provincia' => 'Buenos Aires',
            ],
            'situacion' => [
                'salarios_historicos' => ['100000', 'abc', 200000],
                // Compatibilidad con payloads legacy que traian el typo historico.
                'principal_verifica_aaportes' => 'si',
                'valida_cuil' => 'si',
                'estado_art' => 'inexistente',
                'dia_despido' => '40',
            ],
            'contacto' => [
                'email' => 'estudio@example.com',
            ],
        ]);

        $this->assertSame('empleador', $payload['tipo_usuario']);
        $this->assertSame('responsabilidad_solidaria', $payload['tipo_conflicto']);
        $this->assertEquals(1500000.50, $payload['datos_laborales']['salario']);
        $this->assertSame(36, $payload['datos_laborales']['antiguedad_meses']);
        $this->assertSame([100000.0, 200000.0], $payload['situacion']['salarios_historicos']);
        $this->assertSame('si', $payload['situacion']['valida_aportes']);
        $this->assertSame('si', $payload['situacion']['principal_verifica_aportes']);
        $this->assertSame('no', $payload['situacion']['tiene_art']);
        $this->assertSame(31, $payload['situacion']['dia_despido']);
        $this->assertSame('estudio@example.com', $payload['contacto']['email']);

        $payloadConFechas = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'diferencias_salariales',
            'datos_laborales' => [
                'salario' => '800000',
                'antiguedad_meses' => '12',
                'provincia' => 'CABA',
            ],
            'situacion' => [
                'urgencia' => 'alta',
                'fecha_despido' => '2026-03-01',
                'fecha_ultimo_telegrama' => '2026-03-15',
                'probabilidad_condena' => '0.7',
            ],
            'contacto' => [
                'email' => 'empleado@example.com',
            ],
        ]);

        $this->assertSame('2026-03-01', $payloadConFechas['situacion']['fecha_despido']);
        $this->assertEquals(0.7, $payloadConFechas['situacion']['probabilidad_condena']);

        $payloadPreventivo = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'auditoria_preventiva',
            'datos_laborales' => [
                'salario' => '1250000',
                'antiguedad_meses' => '0',
                'provincia' => 'CABA',
                'cantidad_empleados' => '25',
            ],
            'situacion' => [
                'urgencia' => 'media',
                'probabilidad_condena' => '0.35',
            ],
            'contacto' => [
                'email' => 'preventivo@example.com',
            ],
        ]);

        $this->assertSame('auditoria_preventiva', $payloadPreventivo['tipo_conflicto']);
        $this->assertSame(0, $payloadPreventivo['datos_laborales']['antiguedad_meses']);
        $this->assertSame(25, $payloadPreventivo['datos_laborales']['cantidad_empleados']);

        $payloadRegistroNoCorrespondiente = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'despido_sin_causa',
            'datos_laborales' => [
                'salario' => '950000',
                'antiguedad_meses' => '36',
                'provincia' => 'CABA',
                'tipo_registro' => 'no_registrado',
                'salario_recibo' => '500000',
                'antiguedad_recibo' => '18',
            ],
            'documentacion' => [
                'tiene_recibos' => 'si',
                'registrado_afip' => 'si',
            ],
            'contacto' => [
                'email' => 'registro@example.com',
            ],
        ]);

        $this->assertSame(0.0, $payloadRegistroNoCorrespondiente['datos_laborales']['salario_recibo']);
        $this->assertSame(0, $payloadRegistroNoCorrespondiente['datos_laborales']['antiguedad_recibo']);
        $this->assertSame('no', $payloadRegistroNoCorrespondiente['documentacion']['tiene_recibos']);
        $this->assertSame('no', $payloadRegistroNoCorrespondiente['documentacion']['registrado_afip']);

        $payloadFechaDeficiente = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'despido_sin_causa',
            'datos_laborales' => [
                'salario' => '950000',
                'antiguedad_meses' => '36',
                'provincia' => 'CABA',
                'tipo_registro' => 'deficiente_fecha',
                'salario_recibo' => '500000',
                'antiguedad_recibo' => '18',
            ],
            'contacto' => [
                'email' => 'fecha@example.com',
            ],
        ]);

        $this->assertSame(0.0, $payloadFechaDeficiente['datos_laborales']['salario_recibo']);
        $this->assertSame(18, $payloadFechaDeficiente['datos_laborales']['antiguedad_recibo']);

        $payloadSalarioDeficiente = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'despido_sin_causa',
            'datos_laborales' => [
                'salario' => '950000',
                'antiguedad_meses' => '36',
                'provincia' => 'CABA',
                'tipo_registro' => 'deficiente_salario',
                'salario_recibo' => '500000',
                'antiguedad_recibo' => '18',
            ],
            'contacto' => [
                'email' => 'salario@example.com',
            ],
        ]);

        $this->assertSame(500000.0, $payloadSalarioDeficiente['datos_laborales']['salario_recibo']);
        $this->assertSame(0, $payloadSalarioDeficiente['datos_laborales']['antiguedad_recibo']);

        $payloadOcultoPorConflicto = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'diferencias_salariales',
            'datos_laborales' => [
                'salario' => '950000',
                'antiguedad_meses' => '36',
                'provincia' => 'CABA',
            ],
            'situacion' => [
                'ya_despedido' => 'si',
                'fecha_despido' => '2026-03-10',
                'check_inconstitucionalidad' => 'si',
                'dia_despido' => '9',
                'fecha_siniestro' => '2026-01-10',
                'porcentaje_incapacidad' => '15',
                'meses_no_registrados' => '8',
                'actividad_esencial' => 'si',
                'motivo_diferencia' => 'horas_extras',
                'meses_adeudados' => '6',
            ],
            'contacto' => [
                'email' => 'conflicto@example.com',
            ],
        ]);

        $this->assertSame('', $payloadOcultoPorConflicto['situacion']['fecha_siniestro']);
        $this->assertSame(0.0, $payloadOcultoPorConflicto['situacion']['porcentaje_incapacidad']);
        $this->assertSame(0, $payloadOcultoPorConflicto['situacion']['meses_no_registrados']);
        $this->assertSame('no', $payloadOcultoPorConflicto['situacion']['actividad_esencial']);
        $this->assertSame('horas_extras', $payloadOcultoPorConflicto['situacion']['motivo_diferencia']);
        $this->assertSame(6, $payloadOcultoPorConflicto['situacion']['meses_adeudados']);

        $payloadTelegramaNo = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'despido_sin_causa',
            'datos_laborales' => [
                'salario' => '950000',
                'antiguedad_meses' => '36',
                'provincia' => 'CABA',
            ],
            'situacion' => [
                'hay_intercambio' => 'no',
                'fecha_ultimo_telegrama' => '2026-03-15',
                'ya_despedido' => 'si',
                'fecha_despido' => '2026-03-10',
            ],
            'contacto' => [
                'email' => 'telegrama@example.com',
            ],
        ]);

        $this->assertSame('', $payloadTelegramaNo['situacion']['fecha_ultimo_telegrama']);

        $payloadAccidenteSinArt = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleado',
            'tipo_conflicto' => 'accidente_laboral',
            'datos_laborales' => [
                'salario' => '900000',
                'antiguedad_meses' => '12',
                'provincia' => 'Buenos Aires',
                'edad' => 30,
            ],
            'situacion' => [
                'tiene_art' => 'no',
                'denuncia_art' => 'si',
                'rechazo_art' => 'si',
                'comision_medica' => 'dictamen_emitido',
                'dictamen_porcentaje' => '18',
                'via_administrativa_agotada' => 'si',
                'tiene_preexistencia' => 'no',
                'preexistencia_porcentaje' => '12',
            ],
            'contacto' => [
                'email' => 'accidente-sin-art@example.com',
            ],
        ]);

        $this->assertSame('no', $payloadAccidenteSinArt['situacion']['denuncia_art']);
        $this->assertSame('no', $payloadAccidenteSinArt['situacion']['rechazo_art']);
        $this->assertSame('no_iniciada', $payloadAccidenteSinArt['situacion']['comision_medica']);
        $this->assertSame(0.0, $payloadAccidenteSinArt['situacion']['dictamen_porcentaje']);
        $this->assertSame('no', $payloadAccidenteSinArt['situacion']['via_administrativa_agotada']);
        $this->assertSame(0.0, $payloadAccidenteSinArt['situacion']['preexistencia_porcentaje']);

        $payloadAccidenteValido = AnalysisPayloadNormalizer::normalize([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'accidente_laboral',
            'datos_laborales' => [
                'salario' => '900000',
                'antiguedad_meses' => '12',
                'provincia' => 'Buenos Aires',
                'edad' => 30,
            ],
            'situacion' => [
                'fecha_siniestro' => '2026-02-10',
            ],
            'contacto' => [
                'email' => 'accidente-con-art@example.com',
            ],
        ]);

        $this->assertSame(30, $payloadAccidenteValido['datos_laborales']['edad']);

        try {
            AnalysisPayloadNormalizer::normalize([
                'tipo_usuario' => 'empleador',
                'tipo_conflicto' => 'accidente_laboral',
                'datos_laborales' => [
                    'salario' => '900000',
                    'antiguedad_meses' => '12',
                    'provincia' => 'Buenos Aires',
                    'edad' => 0,
                ],
                'situacion' => [
                    'fecha_siniestro' => '2026-02-10',
                ],
            ]);

            throw new \RuntimeException('Expected InvalidPayloadException for accident payload without valid age');
        } catch (InvalidPayloadException $e) {
            $errors = $e->getErrors();
            $this->assertSame('Para accidentes, la edad debe estar entre 16 y 90 años.', $errors['datos_laborales.edad']);
        }

        try {
            AnalysisPayloadNormalizer::normalize([
                'tipo_usuario' => 'otro',
                'tipo_conflicto' => 'desconocido',
                'datos_laborales' => [
                    'salario' => '0',
                    'antiguedad_meses' => '-1',
                    'provincia' => '',
                ],
                'situacion' => [
                    'urgencia' => 'critica',
                    'fecha_despido' => '2026-02-30',
                    'probabilidad_condena' => '2',
                ],
            ]);

            throw new \RuntimeException('Expected InvalidPayloadException');
        } catch (InvalidPayloadException $e) {
            $errors = $e->getErrors();
            $this->assertSame('Seleccioná un perfil válido.', $errors['tipo_usuario']);
            $this->assertSame('Seleccioná un tipo de conflicto válido.', $errors['tipo_conflicto']);
            $this->assertSame('El salario debe ser mayor a cero.', $errors['datos_laborales.salario']);
            $this->assertSame('La fecha de despido debe tener formato YYYY-MM-DD.', $errors['situacion.fecha_despido']);
            $this->assertSame('La probabilidad de condena debe estar entre 0 y 1.', $errors['situacion.probabilidad_condena']);
        }
    }
}
