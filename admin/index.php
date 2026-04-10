<?php
/**
 * motor_laboral/admin/index.php — Dashboard del panel admin del Motor Laboral.
 *
 * Muestra KPIs principales, distribución IRIL (doughnut), top conflictos (barras),
 * acciones tomadas y los últimos 10 análisis como tabla.
 *
 * Requiere sesión admin válida (gestionada por header.php).
 * Lee estadísticas desde DatabaseManager::obtenerEstadisticas().
 */

// ─── título de página para el <title> ────────────────────────────────────────
$page_title = 'Dashboard';

// ─── carga del panel (sesión + navbar + sidebar) ──────────────────────────────
require_once __DIR__ . '/../header.php';

// ─── motores y BD ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/DatabaseManager.php';

try {
    $db    = new DatabaseManager();
    $stats = $db->obtenerEstadisticas();
    // Últimos 10 análisis para la tabla
    $ultimos = $db->listarAnalisis([], 10, 0);
} catch (Exception $e) {
    $stats   = [];
    $ultimos = [];
    $errorMsg = 'Error al cargar estadísticas: ' . htmlspecialchars($e->getMessage());
}

// ─── helpers locales ──────────────────────────────────────────────────────────

/** Etiqueta legible para tipo_conflicto */
function labelConflicto(string $tipo): string {
    return ml_conflicto_label($tipo);
}

/** Retorna la clase CSS del nivel IRIL */
function claseBadgeIril(?float $score): string {
    if ($score === null || $score <= 0) return '';
    if ($score < 2) return 'bajo';
    if ($score < 3) return 'moderado';
    if ($score < 4) return 'alto';
    return 'critico';
}

// Distribución IRIL para el gráfico
$dist     = $stats['distribucion_iril'] ?? ['bajo'=>0,'moderado'=>0,'alto'=>0,'critico'=>0];
$topConfl = $stats['top_conflictos'] ?? [];
?>

<!-- ─────────────────────────────────────────────────────────────────────────
     CABECERA DE SECCIÓN
────────────────────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold" style="color: var(--primary);">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </h4>
        <small class="text-muted">
            Resumen global del Motor de Análisis Laboral —
            <?php echo date('d/m/Y H:i'); ?>
        </small>
    </div>
    <a href="<?php echo ML_BASE_URL; ?>/admin/analisis/lista.php"
       class="btn btn-sm btn-primary">
        <i class="bi bi-list-ul me-1"></i> Ver todos los análisis
    </a>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-warning"><?php echo $errorMsg; ?></div>
<?php endif; ?>

<!-- ─────────────────────────────────────────────────────────────────────────
     FILA 1 — KPIs
────────────────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total análisis -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3><?php echo intval($stats['total'] ?? 0); ?></h3>
                    <p>Total análisis</p>
                </div>
                <div class="icon-wrapper">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos 7 días -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3><?php echo intval($stats['ultimos_7_dias'] ?? 0); ?></h3>
                    <p>Últimos 7 días</p>
                </div>
                <div class="icon-wrapper">
                    <i class="bi bi-calendar-week"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- IRIL promedio -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3><?php echo number_format(floatval($stats['iril_promedio'] ?? 0), 1); ?></h3>
                    <p>IRIL promedio</p>
                </div>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Críticos -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 style="color: #dc3545;"><?php echo intval($dist['critico'] ?? 0); ?></h3>
                    <p>IRIL crítico (&gt;4)</p>
                </div>
                <div class="icon-wrapper" style="background:#f8d7da; color:#dc3545;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     FILA 2 — GRÁFICOS
────────────────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Doughnut distribución IRIL -->
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <h6 class="fw-bold mb-3" style="color: var(--primary);">
                <i class="bi bi-pie-chart me-1"></i> Distribución IRIL
            </h6>
            <div style="height: 220px; position: relative;">
                <canvas id="grafico-iril"
                    data-bajo="<?php echo intval($dist['bajo'] ?? 0); ?>"
                    data-moderado="<?php echo intval($dist['moderado'] ?? 0); ?>"
                    data-alto="<?php echo intval($dist['alto'] ?? 0); ?>"
                    data-critico="<?php echo intval($dist['critico'] ?? 0); ?>">
                </canvas>
            </div>
        </div>
    </div>

    <!-- Barras top conflictos -->
    <div class="col-md-5">
        <div class="card p-3 h-100">
            <h6 class="fw-bold mb-3" style="color: var(--primary);">
                <i class="bi bi-bar-chart-horizontal me-1"></i> Top conflictos
            </h6>
            <div style="height: 220px; position: relative;">
                <canvas id="grafico-conflictos"
                    data-conflictos="<?php echo htmlspecialchars(json_encode($topConfl)); ?>">
                </canvas>
            </div>
        </div>
    </div>

    <!-- Tipo usuario + acciones -->
    <div class="col-md-3">
        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3" style="color: var(--primary);">
                <i class="bi bi-people me-1"></i> Tipo de usuario
            </h6>
            <?php
            $porTipo = $stats['por_tipo_usuario'] ?? [];
            $total   = max(1, intval($stats['total'] ?? 1));
            foreach (['empleado' => 'Empleado', 'empleador' => 'Empleador'] as $k => $label):
                $qty = intval($porTipo[$k] ?? 0);
                $pct = round($qty / $total * 100);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
                    <span><?php echo $label; ?></span>
                    <span class="fw-bold"><?php echo $qty; ?> (<?php echo $pct; ?>%)</span>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar" style="width:<?php echo $pct; ?>%; background:var(--primary);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card p-3">
            <h6 class="fw-bold mb-3" style="color: var(--primary);">
                <i class="bi bi-cursor me-1"></i> Acciones tomadas
            </h6>
            <?php
            $acciones = $stats['acciones'] ?? [];
            $labelsAcc = [
                'ver_informe' => 'Ver informe',
                'contacto'    => 'Contacto',
                'tramite'     => 'Trámite',
                'ninguna'     => 'Ninguna',
                ''            => 'Sin acción',
            ];
            foreach ($acciones as $accion => $qty):
                $label = $labelsAcc[$accion] ?? ucfirst($accion ?: 'Sin acción');
            ?>
            <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
                <span><?php echo htmlspecialchars($label); ?></span>
                <span class="badge bg-secondary"><?php echo intval($qty); ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($acciones)): ?>
                <small class="text-muted">Sin datos aún</small>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     TABLA — ÚLTIMOS 10 ANÁLISIS
────────────────────────────────────────────────────────────────────────── -->
<div class="card p-0">
    <div class="card-header d-flex justify-content-between align-items-center py-2 px-3"
         style="background:var(--bg-light); border-bottom: 1px solid var(--border);">
        <h6 class="mb-0 fw-bold" style="color:var(--primary);">
            <i class="bi bi-clock-history me-1"></i> Últimos análisis
        </h6>
        <a href="<?php echo ML_BASE_URL; ?>/admin/analisis/lista.php"
           class="btn btn-sm btn-outline-primary" style="font-size:12px;">
            Ver todos <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Conflicto</th>
                    <th>IRIL</th>
                    <th>Escenario</th>
                    <th>Acción</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($ultimos)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                        No hay análisis registrados aún.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ultimos as $a):
                    $nivel  = claseBadgeIril(floatval($a['iril_score'] ?? 0));
                    $score  = floatval($a['iril_score'] ?? 0);
                    $escRec = strtoupper($a['escenario_recomendado'] ?? '');
                    $accion = $a['accion_tomada'] ?? '';
                ?>
                <tr data-iril-nivel="<?php echo htmlspecialchars($nivel); ?>">
                    <td><small class="text-muted">#<?php echo intval($a['id']); ?></small></td>
                    <td>
                        <?php if ($a['tipo_usuario'] === 'empleado'): ?>
                            <span class="badge bg-info text-dark">Empleado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Empleador</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <small><?php echo htmlspecialchars(labelConflicto($a['tipo_conflicto'] ?? '')); ?></small>
                    </td>
                    <td>
                        <?php if ($score > 0): ?>
                        <div class="iril-bar-wrap">
                            <div class="iril-bar">
                                <div class="iril-bar-fill <?php echo $nivel; ?>"
                                     style="width: <?php echo round(($score/5)*100); ?>%"></div>
                            </div>
                            <span class="iril-bar-val" style="color: var(--iril-<?php echo $nivel; ?>);">
                                <?php echo number_format($score, 1); ?>
                            </span>
                        </div>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($escRec): ?>
                            <span class="badge-escenario <?php echo htmlspecialchars($escRec); ?>">
                                <?php echo htmlspecialchars($escRec); ?>
                            </span>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted"><?php echo htmlspecialchars($accion ?: 'sin acción'); ?></small>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?php
                            $fecha = $a['fecha_creacion'] ?? '';
                            echo $fecha ? date('d/m/y H:i', strtotime($fecha)) : '—';
                            ?>
                        </small>
                    </td>
                    <td>
                        <a href="<?php echo ML_BASE_URL; ?>/admin/analisis/detalle.php?uuid=<?php
                            echo urlencode($a['uuid'] ?? ''); ?>"
                           class="btn btn-sm btn-outline-primary" style="font-size:11px;">
                            Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
