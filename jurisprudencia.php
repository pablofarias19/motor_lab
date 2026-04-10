<?php
/**
 * jurisprudencia.php — Explorador Público de Jurisprudencia
 *
 * Muestra el listado de fallos relevantes y permite buscar por texto.
 */

require_once __DIR__ . '/config/config.php';

try {
    $db = ml_conectar_bd();
} catch (Exception $e) {
    die("Error conectando a la base de datos.");
}

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($busqueda !== '') {
    $where_clauses[] = "(autos LIKE ? OR tribunal LIKE ? OR tema LIKE ? OR resumen LIKE ?)";
    $q_param = "%" . $busqueda . "%";
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $types .= "ssss";
}

$where_sql = implode(" AND ", $where_clauses);
// Ordenar por ID inverso o alguna lógica similar si fecha no es standard
$query = "SELECT autos, tribunal, fecha, tema, resumen, link FROM pro_jurisprudencia WHERE $where_sql ORDER BY jurisprudencia_id ASC";

$fallos = [];

$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $fallos[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurisprudencia Laboral — Estudio Farias Ortiz</title>
    <link rel="stylesheet" href="assets/css/motor.css">
    <link rel="stylesheet" href="assets/css/motor-ui-mejorado.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2rem; color: var(--text-primary); margin-bottom: 0.5rem; }
        .page-subtitle { color: var(--text-muted); font-size: 1.1rem; }
        .card { background: white; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); padding: 1.5rem; margin-bottom: 1rem; }
        .empty-state { padding: 4rem 2rem; text-align: center; color: var(--text-muted); }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 10px; display: inline-block; }
        .badge-leading { background: rgba(79,195,247,0.15); color: var(--accent-primary); }
        .badge-moderado { background: rgba(243,156,18,0.15); color: #f39c12; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; background-color: #f8f9fa; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 1rem; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--accent-primary); color: white; }
        .btn-secondary { background: #e2e8f0; color: var(--text-secondary); }
    </style>
</head>
<body class="motor-wrapper">
    <header class="motor-header">
        <div class="motor-header-inner">
            <div class="motor-logo">
                <a href="index.php" class="logo-link">
                    <img src="../document/image/logo1.png" alt="Estudio Farias Ortiz" class="logo-img">
                    <div class="logo-divider"></div>
                    <span class="motor-logo-modulo">Base de Conocimiento</span>
                </a>
            </div>
            <div class="motor-header-badge" style="display:flex; gap:1rem;">
                <a href="index.php" style="color:white; text-decoration:none;"><i class="bi bi-house"></i> Inicio</a>
                <a href="cct.php" style="color:white; text-decoration:none;"><i class="bi bi-list-columns-reverse"></i> CCTs</a>
                <a href="normativa.php" style="color:white; text-decoration:none;"><i class="bi bi-journal-check"></i> Normativa</a>
                <a href="jurisprudencia.php" style="color:white; text-decoration:none;"><i class="bi bi-bank"></i> Jurisprudencia</a>
            </div>
        </div>
    </header>

    <main class="motor-main">
        <div class="motor-container" style="max-width: 1000px;">
            <div class="page-header">
                <h1 class="page-title">🏛️ Jurisprudencia Destacada</h1>
                <p class="page-subtitle">Leading cases CSJN y fallos relevantes para la práctica laboral</p>
            </div>

            <div class="card" style="margin-bottom:24px;">
                <form method="GET" action="jurisprudencia.php" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1; margin-bottom:0; min-width:200px;">
                        <label for="q">Buscar jurisprudencia</label>
                        <input type="text" class="form-control" name="q" id="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej: Vizzoti, tope, inconstitucionalidad, accidente...">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-bottom:0;">🔍 Buscar</button>
                    <?php if ($busqueda): ?>
                    <a href="jurisprudencia.php" class="btn btn-secondary" style="margin-bottom:0;">✕ Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($fallos)): ?>
            <div class="section" style="margin-bottom: 2rem;">
                <?php foreach ($fallos as $fallo): ?>
                <?php 
                    $is_csjn = strpos(strtolower($fallo['tribunal']), 'csjn') !== false || strpos(strtolower($fallo['tribunal']), 'corte suprema') !== false;
                    $border_color = $is_csjn ? 'var(--accent-primary)' : 'var(--warning)';
                ?>
                <div class="card" style="margin-bottom:16px; border-left:4px solid <?= $border_color ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                        <div>
                            <div style="font-weight:700; font-size:16px; color:var(--accent-primary);"><?= htmlspecialchars($fallo['autos']) ?></div>
                            <div style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                                <?= htmlspecialchars($fallo['tribunal']) ?> — <?= htmlspecialchars($fallo['fecha']) ?>
                            </div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <?php if ($is_csjn): ?>
                            <span class="badge badge-leading">★ LEADING CASE</span>
                            <?php else: ?>
                            <span class="badge badge-moderado">Relevante</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom:12px;">
                        <span style="font-size:13px; font-weight:700; color:var(--accent-secondary); text-transform:uppercase; letter-spacing:0.5px;"><?= htmlspecialchars($fallo['tema']) ?></span>
                    </div>
                    
                    <p style="font-size:14px; color:var(--text-primary); line-height:1.6;"><?= htmlspecialchars($fallo['resumen']) ?></p>
                    
                    <?php if (!empty($fallo['link'])): ?>
                    <div style="margin-top: 12px;">
                        <a href="<?= htmlspecialchars($fallo['link']) ?>" target="_blank" style="font-size:13px; color:var(--accent-primary); text-decoration:none; font-weight:600;"><i class="bi bi-link-45deg"></i> Ver fallo completo</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-icon">🏛️</div>
                    <?php if ($busqueda): ?>
                    <div class="empty-text">No se encontraron fallos para "<?= htmlspecialchars($busqueda) ?>"</div>
                    <a href="jurisprudencia.php" class="btn btn-primary" style="margin-top:1rem;">Ver toda la jurisprudencia</a>
                    <?php else: ?>
                    <div class="empty-text">No hay jurisprudencia cargada.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
