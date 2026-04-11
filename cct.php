<?php
/**
 * cct.php — Explorador Público de Convenios Colectivos de Trabajo
 *
 * Muestra el listado de CCTs, características especiales y escalas salariales
 * extrayendo los datos de MySQL (tablas pro_ccts y pro_escalas).
 */

require_once __DIR__ . '/config/config.php';

try {
    $db = ml_conectar_bd();
} catch (Exception $e) {
    die("Error conectando a la base de datos.");
}

$cct_seleccionado = $_GET['cct'] ?? null;
if ($cct_seleccionado) {
    $cct_seleccionado = ml_sanitizar($cct_seleccionado, $db);
}

// Obtener listado de CCTs (solo los que tienen códigos)
$query_ccts = "SELECT cct_codigo, cct_nombre FROM pro_ccts ORDER BY cct_nombre ASC";
$result_ccts = $db->query($query_ccts);
$ccts = [];
if ($result_ccts) {
    while ($row = $result_ccts->fetch_assoc()) {
        $ccts[] = $row;
    }
}

// Obtener detalles y escalas del CCT seleccionado
$cct_detalles = null;
$escalas = [];

if ($cct_seleccionado) {
    // 1. Obtener detalles
    $stmt_det = $db->prepare("SELECT caracteristicas_json FROM pro_ccts WHERE cct_codigo = ? LIMIT 1");
    if ($stmt_det) {
        $stmt_det->bind_param("s", $cct_seleccionado);
        $stmt_det->execute();
        $res_det = $stmt_det->get_result();
        if ($res_det->num_rows > 0) {
            $row_det = $res_det->fetch_assoc();
            if (!empty($row_det['caracteristicas_json'])) {
                $cct_detalles = json_decode($row_det['caracteristicas_json'], true);
            }
        }
    }

    // 2. Obtener escalas
    $stmt_esc = $db->prepare("SELECT categoria, vigencia_desde, basico, fuente FROM pro_escalas WHERE cct_codigo = ? ORDER BY vigencia_desde DESC, categoria ASC");
    if ($stmt_esc) {
        $stmt_esc->bind_param("s", $cct_seleccionado);
        $stmt_esc->execute();
        $res_esc = $stmt_esc->get_result();
        while ($row = $res_esc->fetch_assoc()) {
            $escalas[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorador de CCTs — Estudio Farias Ortiz</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor-ui-mejorado.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor-unified.css')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2rem; color: var(--text-primary); margin-bottom: 0.5rem; }
        .page-subtitle { color: var(--text-muted); font-size: 1.1rem; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .kpi-card { background: white; border-radius: var(--border-radius); padding: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: all 0.3s ease; text-decoration: none; display: block; }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-sm); border-color: var(--accent-primary); }
        .kpi-card.activo { border-color: var(--accent-primary); box-shadow: var(--shadow-glow); }
        .kpi-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .card { background: white; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 2rem;}
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: #f8f9fa; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8f9fa; }
        .empty-state { padding: 4rem 2rem; text-align: center; color: var(--text-muted); }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        .char-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .char-card { background: white; border-radius: var(--border-radius); padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); }
        .char-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .char-value { font-size: 15px; color: var(--text-primary); font-weight: 600; }
        .char-full { grid-column: 1 / -1; }
        .char-note { border-left: 4px solid var(--accent-primary); }
    </style>
</head>
<body class="motor-wrapper motor-shell knowledge-shell">
    <header class="motor-header">
        <div class="motor-header-inner">
            <div class="motor-logo">
                <a href="<?= htmlspecialchars(ml_url('index.php')) ?>" class="logo-link">
                    <img src="<?= htmlspecialchars(ml_logo_src()) ?>" alt="Estudio Farias Ortiz" class="logo-img">
                    <div class="logo-divider"></div>
                    <span class="motor-logo-modulo">Base de Conocimiento</span>
                </a>
            </div>
            <div class="motor-header-badge motor-nav-pills">
                <a href="<?= htmlspecialchars(ml_url('index.php')) ?>" style="color:white; text-decoration:none;"><i class="bi bi-house"></i> Inicio</a>
                <a href="<?= htmlspecialchars(ml_url('cct.php')) ?>" style="color:white; text-decoration:none;"><i class="bi bi-list-columns-reverse"></i> CCTs</a>
                <a href="<?= htmlspecialchars(ml_url('normativa.php')) ?>" style="color:white; text-decoration:none;"><i class="bi bi-journal-check"></i> Normativa</a>
                <a href="<?= htmlspecialchars(ml_url('jurisprudencia.php')) ?>" style="color:white; text-decoration:none;"><i class="bi bi-bank"></i> Jurisprudencia</a>
            </div>
        </div>
    </header>

    <main class="motor-main">
        <div class="motor-container" style="max-width: 1000px;">
            <section class="motor-hero motor-hero--compact">
                <div class="motor-hero-grid">
                    <div>
                        <span class="motor-hero-eyebrow"><i class="bi bi-list-columns-reverse"></i> Convenios</span>
                        <h1 class="motor-hero-title">Explorador de CCTs con navegación más clara</h1>
                        <p class="motor-hero-subtitle">Recorré convenios, características especiales y escalas salariales dentro del mismo lenguaje visual del wizard y del dashboard.</p>
                    </div>
                    <div class="motor-stat-grid">
                        <div class="motor-stat-chip">
                            <strong><?= count($ccts) ?></strong>
                            <span>convenios disponibles</span>
                        </div>
                        <div class="motor-stat-chip">
                            <strong><?= $cct_seleccionado ? htmlspecialchars($cct_seleccionado) : '—' ?></strong>
                            <span>convenio activo</span>
                        </div>
                    </div>
                </div>
            </section>
            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-list-columns-reverse"></i> Explorador de CCTs</h1>
                <p class="page-subtitle">Convenios Colectivos de Trabajo — Características y Escalas Salariales</p>
            </div>

            <!-- CCT Selector -->
            <div class="kpi-grid">
                <?php foreach ($ccts as $cct): ?>
                <a href="<?= htmlspecialchars(ml_url('cct.php')) ?>?cct=<?= urlencode($cct['cct_codigo']) ?>"
                   class="kpi-card <?= ($cct['cct_codigo'] === $cct_seleccionado) ? 'activo' : '' ?>">
                    <div class="kpi-icon">📋</div>
                    <div style="font-size:18px; font-weight:700; color:var(--accent-primary);"><?= htmlspecialchars($cct['cct_codigo']) ?></div>
                    <div style="font-size:13px; color:var(--text-secondary); margin-top:4px;"><?= htmlspecialchars($cct['cct_nombre']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($cct_seleccionado): ?>
                
                <?php if ($cct_detalles): ?>
                <h2 style="font-size: 1.5rem; color: var(--text-primary); margin-bottom: 1rem;"><i class="bi bi-stars"></i> Características Especiales — CCT <?= htmlspecialchars($cct_seleccionado) ?></h2>
                <div class="char-grid">
                    <?php if (!empty($cct_detalles['antiguedad'])): ?>
                    <div class="char-card">
                        <div class="char-label">Antigüedad</div>
                        <div class="char-value"><?= htmlspecialchars($cct_detalles['antiguedad']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cct_detalles['presentismo'])): ?>
                    <div class="char-card">
                        <div class="char-label">Presentismo</div>
                        <div class="char-value"><?= htmlspecialchars($cct_detalles['presentismo']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cct_detalles['adicionales'])): ?>
                    <div class="char-card char-full">
                        <div class="char-label">Adicionales Comunes</div>
                        <div class="char-value" style="font-weight:normal;"><?= htmlspecialchars($cct_detalles['adicionales']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cct_detalles['notas'])): ?>
                    <div class="char-card char-full char-note">
                        <div class="char-label">Notas Prácticas (Atención)</div>
                        <div class="char-value" style="font-weight:normal; font-size:14px; line-height:1.6;"><?= htmlspecialchars($cct_detalles['notas']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h2 style="font-size: 1.5rem; color: var(--text-primary); margin-bottom: 1rem;"><i class="bi bi-bar-chart"></i> Escalas Salariales — CCT <?= htmlspecialchars($cct_seleccionado) ?></h2>
                
                <?php if (!empty($escalas)): ?>
                <div class="card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Básico</th>
                                    <th>Vigencia Desde</th>
                                    <th>Fuente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($escalas as $e): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars($e['categoria']) ?></td>
                                    <td style="font-weight:700; color:var(--accent-primary);"><?= ml_formato_moneda($e['basico']) ?></td>
                                    <td><?= htmlspecialchars($e['vigencia_desde']) ?></td>
                                    <td style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($e['fuente']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-text">No hay escalas salariales cargadas para este CCT.</div>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif(empty($ccts)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-icon">📂</div>
                        <div class="empty-text">No hay CCTs en la base de datos.</div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
    <?= ml_render_floating_contact_buttons() ?>
</body>
</html>
