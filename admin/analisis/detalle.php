<?php
/**
 * motor_laboral/admin/analisis/detalle.php — Vista detallada de un análisis laboral.
 *
 * Recibe el UUID por GET: detalle.php?uuid=...
 * Muestra todos los campos del análisis decodificados:
 *   - Datos laborales (salario, antigüedad, provincia, etc.)
 *   - Documentación disponible (telegramas, recibos, testigos, etc.)
 *   - Situación actual (urgencia, intercambio epistolar)
 *   - Índice IRIL con desglose por dimensión y alertas activas
 *   - Exposición económica estimada por concepto
 *   - Los 4 escenarios estratégicos con VBP y VAE
 *   - Acción tomada por el usuario
 *
 * Lee desde DatabaseManager::obtenerAnalisisPorUUID().
 */

$page_title = 'Detalle Análisis';

// ─── header admin ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../header.php';

// ─── dependencias ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../config/DatabaseManager.php';

// ─────────────────────────────────────────────────────────────────────────────
// CARGAR ANÁLISIS
// ─────────────────────────────────────────────────────────────────────────────

$uuid = trim($_GET['uuid'] ?? '');

if (empty($uuid)) {
    echo '<div class="alert alert-danger">UUID no especificado.</div>';
    require_once __DIR__ . '/../../footer.php';
    exit;
}

try {
    $db = new DatabaseManager();
    $a = $db->obtenerAnalisisPorUUID($uuid);
} catch (Exception $e) {
    $a = null;
    $errorMsg = htmlspecialchars($e->getMessage());
}

if (empty($a)) {
    echo '<div class="alert alert-warning">Análisis no encontrado.</div>';
    require_once __DIR__ . '/../../footer.php';
    exit;
}

// ─── Decodificar JSON almacenados ─────────────────────────────────────────────
$datosLab = json_decode($a['datos_laborales'] ?? '{}', true) ?: [];
$docJson = json_decode($a['documentacion_json'] ?? '{}', true) ?: [];
$situJson = json_decode($a['situacion_json'] ?? '{}', true) ?: [];
$irilDet = json_decode($a['iril_detalle'] ?? '{}', true) ?: [];
$exposJson = json_decode($a['exposicion_json'] ?? '{}', true) ?: [];
$escenJson = json_decode($a['escenarios_json'] ?? '[]', true) ?: [];

$irilScore = floatval($a['iril_score'] ?? 0);
$escRec = strtoupper($a['escenario_recomendado'] ?? '');

// ─── Helpers ──────────────────────────────────────────────────────────────────

function detNivel(float $score): string
{
    if ($score < 2)
        return 'bajo';
    if ($score < 3)
        return 'moderado';
    if ($score < 4)
        return 'alto';
    return 'critico';
}

function detSiNo($valor): string
{
    return $valor ? '<span class="badge bg-success">Sí</span>'
        : '<span class="badge bg-secondary">No</span>';
}

function detMoneda($valor): string
{
    return '$&nbsp;' . number_format(floatval($valor), 2, ',', '.');
}

function detEscNombre(string $letra): string
{
    $n = ['A' => 'Negociación Directa', 'B' => 'Litigio Judicial', 'C' => 'Mixta', 'D' => 'Preventiva'];
    return $n[$letra] ?? "Escenario $letra";
}

$nivel = detNivel($irilScore);
$labelConflicto = function (string $t): string {
    $m = [
        'despido_injustificado' => 'Despido injustificado',
        'despido_discriminatorio' => 'Despido discriminatorio',
        'accidente_trabajo' => 'Accidente de trabajo',
        'enfermedad_profesional' => 'Enfermedad profesional',
        'diferencias_salariales' => 'Diferencias salariales',
        'trabajo_no_registrado' => 'Trabajo no registrado',
        'acoso_laboral' => 'Acoso laboral',
        'maternidad_licencias' => 'Maternidad / Licencias',
        'reduccion_categoria' => 'Reducción de categoría',
        'impugnacion_contrato' => 'Impugnación de contrato',
    ];
    return $m[$t] ?? ucfirst(str_replace('_', ' ', $t));
};
?>

<!-- ─────────────────────────────────────────────────────────────────────────
     CABECERA
────────────────────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold" style="color: var(--primary);">
            <i class="bi bi-file-earmark-text me-2"></i>Análisis #<?php echo intval($a['id']); ?>
        </h4>
        <div class="mt-1">
            <span class="uuid-tag copiable" data-uuid="<?php echo htmlspecialchars($uuid); ?>">
                <?php echo htmlspecialchars($uuid); ?>
            </span>
            <small class="text-muted ms-2">
                <?php echo $a['fecha_creacion'] ? date('d/m/Y H:i', strtotime($a['fecha_creacion'])) : ''; ?>
            </small>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($irilScore > 0): ?>
            <a href="<?php echo ML_BASE_URL; ?>/api/generar_informe.php?uuid=<?php echo urlencode($uuid); ?>"
                target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-file-pdf me-1"></i> Ver PDF
            </a>
        <?php endif; ?>
        <a href="<?php echo ML_BASE_URL; ?>/resultado.php?uuid=<?php echo urlencode($uuid); ?>" target="_blank"
            class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-eye me-1"></i> Vista pública
        </a>
        <a href="<?php echo ML_BASE_URL; ?>/admin/analisis/lista.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-warning"><?php echo $errorMsg; ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- ─── COLUMNA IZQUIERDA (2/3) ──────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- PERFIL DEL CASO -->
        <div class="detalle-section">
            <h5><i class="bi bi-person-badge me-2"></i>Perfil del caso</h5>
            <div class="dato-row">
                <span class="label">Tipo de usuario</span>
                <span class="valor">
                    <?php if ($a['tipo_usuario'] === 'empleado'): ?>
                        <span class="badge bg-info text-dark">Empleado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Empleador</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="dato-row">
                <span class="label">Tipo de conflicto</span>
                <span class="valor"><?php echo htmlspecialchars($labelConflicto($a['tipo_conflicto'] ?? '')); ?></span>
            </div>
            <div class="dato-row">
                <span class="label">Acción tomada</span>
                <span class="valor"><?php echo htmlspecialchars($a['accion_tomada'] ?? 'sin acción'); ?></span>
            </div>
            <?php if (!empty($a['email'])): ?>
                <div class="dato-row">
                    <span class="label">Email</span>
                    <span class="valor">
                        <a href="mailto:<?php echo htmlspecialchars($a['email']); ?>">
                            <?php echo htmlspecialchars($a['email']); ?>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
            <div class="dato-row">
                <span class="label">IP</span>
                <span class="valor"><small
                        class="text-muted"><?php echo htmlspecialchars($a['ip'] ?? ''); ?></small></span>
            </div>
        </div>

        <!-- DATOS LABORALES -->
        <div class="detalle-section">
            <h5><i class="bi bi-briefcase me-2"></i>Datos laborales</h5>
            <?php
            $camposLab = [
                'salario' => 'Salario mensual',
                'antiguedad_meses' => 'Antigüedad (meses)',
                'provincia' => 'Provincia',
                'cantidad_empleados' => 'Cantidad empleados',
                'categoria' => 'Categoría / Puesto',
                'cct' => 'Convenio Colectivo (CCT)',
            ];
            foreach ($camposLab as $key => $label):
                $val = $datosLab[$key] ?? null;
                if ($val === null)
                    continue;
                ?>
                <div class="dato-row">
                    <span class="label"><?php echo $label; ?></span>
                    <span class="valor">
                        <?php
                        if ($key === 'salario') {
                            echo detMoneda($val);
                        } else {
                            echo htmlspecialchars($val);
                        }
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- DOCUMENTACIÓN DISPONIBLE -->
        <div class="detalle-section">
            <h5><i class="bi bi-folder2-open me-2"></i>Documentación disponible</h5>
            <?php
            $docLabels = [
                'tiene_telegramas' => 'Telegramas enviados/recibidos',
                'tiene_recibos' => 'Recibos de sueldo',
                'tiene_contrato' => 'Contrato laboral',
                'tiene_afip' => 'Alta ARCA / Registros',
                'tiene_testigos' => 'Testigos disponibles',
                'tiene_auditoria' => 'Auditoría o inspección previa',
            ];
            foreach ($docLabels as $key => $label):
                $val = $docJson[$key] ?? false;
                ?>
                <div class="dato-row">
                    <span class="label"><?php echo $label; ?></span>
                    <span class="valor"><?php echo detSiNo($val); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SITUACIÓN ACTUAL -->
        <div class="detalle-section">
            <h5><i class="bi bi-clock-history me-2"></i>Situación actual</h5>
            <?php
            $situLabels = [
                'hay_intercambio' => 'Hay intercambio epistolar',
                'fecha_ultimo_telegrama' => 'Último telegrama',
                'fue_intimado' => 'Fue intimado (empleador)',
                'ya_despedido' => 'Ya fue despedido (empleado)',
                'fecha_despido' => 'Fecha de despido',
                'urgencia' => 'Nivel de urgencia',
            ];
            foreach ($situLabels as $key => $label):
                $val = $situJson[$key] ?? null;
                if ($val === null || $val === '')
                    continue;
                ?>
                <div class="dato-row">
                    <span class="label"><?php echo $label; ?></span>
                    <span class="valor">
                        <?php
                        if (in_array($key, ['hay_intercambio', 'fue_intimado', 'ya_despedido'])) {
                            echo detSiNo($val);
                        } elseif (in_array($key, ['fecha_ultimo_telegrama', 'fecha_despido'])) {
                            echo htmlspecialchars($val);
                        } elseif ($key === 'urgencia') {
                            $clsUrgencia = ['alta' => 'danger', 'media' => 'warning', 'baja' => 'success'];
                            $cls = $clsUrgencia[$val] ?? 'secondary';
                            echo "<span class='badge bg-{$cls}'>" . htmlspecialchars(ucfirst($val)) . "</span>";
                        } else {
                            echo htmlspecialchars($val);
                        }
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- EXPOSICIÓN ECONÓMICA ESTIMADA -->
        <?php if (!empty($exposJson)): ?>
            <div class="detalle-section">
                <h5><i class="bi bi-cash-stack me-2"></i>Exposición económica estimada (LCT)</h5>
                <p class="text-muted" style="font-size:12px; margin-top:-8px; margin-bottom:12px;">
                    * Estimación orientativa. No constituye asesoramiento legal ni predice resultados judiciales.
                </p>
                <?php
                $conceptos = [
                    'indemnizacion' => 'Indemnización (Art. 245 LCT)',
                    'preaviso' => 'Preaviso (Art. 231/233 LCT)',
                    'sac_proporcional' => 'SAC proporcional (Art. 123 LCT)',
                    'vacaciones' => 'Vacaciones proporcionales (Art. 150 LCT)',
                    'multa_25323' => 'Ley 25.323 Art. 2 (DEROGADA)',
                    'multa_24013' => 'Ley 24.013 Art. 8 (DEROGADA — Ley 27.742)',
                    'art80_lct' => 'Art. 80 LCT (certificados)',
                ];
                $total = 0;
                foreach ($conceptos as $key => $label):
                    $val = floatval($exposJson[$key] ?? 0);
                    if ($val <= 0)
                        continue;
                    $total += $val;
                    ?>
                    <div class="dato-row">
                        <span class="label"><?php echo $label; ?></span>
                        <span class="valor"><?php echo detMoneda($val); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($total > 0): ?>
                    <div class="dato-row mt-2 pt-2" style="border-top: 2px solid var(--primary); font-weight:700;">
                        <span class="label" style="color: var(--primary);">TOTAL ESTIMADO</span>
                        <span class="valor"
                            style="color: var(--primary); font-size:15px;"><?php echo detMoneda($total); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ESCENARIOS ESTRATÉGICOS -->
        <?php if (!empty($escenJson)): ?>
            <div class="detalle-section">
                <h5><i class="bi bi-diagram-3 me-2"></i>Escenarios estratégicos</h5>
                <div class="row g-2">
                    <?php foreach ($escenJson as $esc):
                        $letra = strtoupper($esc['letra'] ?? '');
                        $esRec = $letra === $escRec;
                        ?>
                        <div class="col-md-6">
                            <div class="esc-card <?php echo $esRec ? 'recomendado' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="esc-letra badge-escenario <?php echo $letra; ?>">
                                            <?php echo htmlspecialchars($letra); ?>
                                        </div>
                                    </div>
                                    <?php if ($esRec): ?>
                                        <span class="badge bg-primary" style="font-size:10px;">Recomendado</span>
                                    <?php endif; ?>
                                </div>

                                <div class="fw-bold mb-1" style="font-size:13px;">
                                    <?php echo htmlspecialchars(detEscNombre($letra)); ?>
                                </div>
                                <div style="font-size:12px; color:#555; margin-bottom:8px;">
                                    <?php echo htmlspecialchars($esc['descripcion'] ?? ''); ?>
                                </div>

                                <div class="d-flex flex-column gap-1" style="font-size:12px;">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Beneficio est.</span>
                                        <span
                                            class="esc-beneficio"><?php echo detMoneda($esc['beneficio_estimado'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Costo est.</span>
                                        <span class="esc-costo"><?php echo detMoneda($esc['costo_estimado'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">VBP</span>
                                        <span class="esc-vbp"><?php echo detMoneda($esc['vbp'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Duración</span>
                                        <span><?php echo intval($esc['duracion_min_meses'] ?? 0); ?>–<?php
                                             echo intval($esc['duracion_max_meses'] ?? 0); ?> meses</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Nivel intervención</span>
                                        <span><?php echo htmlspecialchars(ucfirst($esc['nivel_intervencion'] ?? '')); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($esc['ventajas'])): ?>
                                    <div class="mt-2" style="font-size:11px;">
                                        <div class="fw-semibold text-success mb-1">Ventajas</div>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($esc['ventajas'] as $v): ?>
                                                <li><?php echo htmlspecialchars($v); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($esc['desventajas'])): ?>
                                    <div class="mt-2" style="font-size:11px;">
                                        <div class="fw-semibold text-danger mb-1">Desventajas</div>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($esc['desventajas'] as $d): ?>
                                                <li><?php echo htmlspecialchars($d); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /col-lg-8 -->

    <!-- ─── COLUMNA DERECHA (1/3) ──────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- IRIL SCORE -->
        <div class="detalle-section text-center">
            <h5 class="text-start"><i class="bi bi-speedometer me-2"></i>Índice IRIL</h5>

            <div style="font-size: 64px; font-weight: 900; line-height: 1;
                        color: var(--iril-<?php echo $nivel; ?>);">
                <?php echo number_format($irilScore, 1); ?>
            </div>
            <div class="mt-1 mb-3">
                <span class="badge-iril <?php echo $nivel; ?>">
                    <?php echo ucfirst($nivel); ?>
                </span>
            </div>

            <!-- Barra de nivel 1-5 -->
            <div class="iril-bar" style="height: 10px; border-radius: 5px; margin-bottom:6px;">
                <div class="iril-bar-fill <?php echo $nivel; ?>"
                    style="width: <?php echo round(($irilScore / 5) * 100); ?>%; height:100%;"></div>
            </div>
            <div class="d-flex justify-content-between" style="font-size: 10px; color:#aaa;">
                <span>1 Bajo</span><span>5 Crítico</span>
            </div>

            <!-- Desglose por dimensión -->
            <?php if (!empty($irilDet)): ?>
                <hr>
                <div class="text-start mt-2">
                    <small class="text-uppercase text-muted fw-semibold" style="font-size:10px; letter-spacing:1px;">
                        Desglose por dimensión
                    </small>
                    <?php
                    $dimLabels = [
                        'saturacion' => 'Saturación tribunalicia',
                        'probatoria' => 'Complejidad probatoria',
                        'normativa' => 'Volatilidad normativa',
                        'costas' => 'Riesgo de costas',
                        'multiplicador' => 'Riesgo multiplicador',
                    ];
                    foreach ($dimLabels as $key => $label):
                        $val = floatval($irilDet[$key] ?? 0);
                        if ($val <= 0)
                            continue;
                        $dimNivel = $val < 2 ? 'bajo' : ($val < 3 ? 'moderado' : ($val < 4 ? 'alto' : 'critico'));
                        ?>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size:11px;">
                                <span><?php echo $label; ?></span>
                                <span class="fw-bold" style="color: var(--iril-<?php echo $dimNivel; ?>);">
                                    <?php echo number_format($val, 1); ?>
                                </span>
                            </div>
                            <div class="iril-bar" style="height:4px;">
                                <div class="iril-bar-fill <?php echo $dimNivel; ?>"
                                    style="width:<?php echo round(($val / 5) * 100); ?>%; height:100%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ALERTAS -->
        <?php
        $alertas = $irilDet['alertas'] ?? [];
        if (!empty($alertas)):
            ?>
            <div class="detalle-section">
                <h5><i class="bi bi-bell-fill me-2" style="color:#fd7e14;"></i>Alertas activas</h5>
                <?php foreach ($alertas as $alerta):
                    $tipo = strtolower($alerta['tipo'] ?? 'general');
                    $icono = match ($tipo) {
                        'prescripcion' => '⏳',
                        'telegrama' => '📨',
                        'derivacion' => '⚖️',
                        default => '⚠️'
                    };
                    ?>
                    <div class="alerta-item <?php echo htmlspecialchars($tipo); ?>">
                        <span class="alerta-icon"><?php echo $icono; ?></span>
                        <div>
                            <div class="fw-semibold" style="font-size:12px;">
                                <?php echo htmlspecialchars($alerta['titulo'] ?? 'Alerta'); ?>
                            </div>
                            <div style="font-size:11px; color:#555;">
                                <?php echo htmlspecialchars($alerta['descripcion'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- TRAMITE DERIVADO -->
        <?php if (!empty($a['tramite_uuid'])): ?>
            <div class="detalle-section">
                <h5><i class="bi bi-link-45deg me-2"></i>Trámite derivado</h5>
                <div class="dato-row">
                    <span class="label">UUID trámite</span>
                    <span class="valor">
                        <span class="uuid-tag copiable" data-uuid="<?php echo htmlspecialchars($a['tramite_uuid']); ?>">
                            <?php echo htmlspecialchars(substr($a['tramite_uuid'], 0, 12)); ?>…
                        </span>
                    </span>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php require_once __DIR__ . '/../../footer.php'; ?>