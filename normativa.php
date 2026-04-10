<?php
/**
 * normativa.php — Explorador Público de Normativa Laboral
 *
 * Muestra el listado de normativa, permite buscar y filtrar por ley.
 */

require_once __DIR__ . '/config/config.php';

try {
    $db = ml_conectar_bd();
} catch (Exception $e) {
    die("Error conectando a la base de datos.");
}

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$ley_filtro = isset($_GET['ley']) ? trim($_GET['ley']) : '';

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($busqueda !== '') {
    $where_clauses[] = "(articulo LIKE ? OR texto LIKE ? OR notas LIKE ? OR ley LIKE ?)";
    $q_param = "%" . $busqueda . "%";
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $types .= "ssss";
}

if ($ley_filtro !== '') {
    if (strpos($ley_filtro, 'lct') !== false) {
        $where_clauses[] = "ley LIKE '%LCT%' OR ley LIKE '%20.744%'";
    } elseif (strpos($ley_filtro, '27802') !== false) {
        $where_clauses[] = "ley LIKE '%27.802%'";
    } elseif (strpos($ley_filtro, 'lrt') !== false) {
        $where_clauses[] = "ley LIKE '%LRT%' OR ley LIKE '%24.557%' OR ley LIKE '%26.773%'";
    } elseif (strpos($ley_filtro, 'bases') !== false) {
        $where_clauses[] = "ley LIKE '%Bases%' OR ley LIKE '%27.742%'";
    } elseif (strpos($ley_filtro, 'reglam') !== false) {
        $where_clauses[] = "ley LIKE '%Reglament%' OR ley LIKE '%CSJN%' OR ley LIKE '%Decreto%'";
    } else {
        $where_clauses[] = "ley = ?";
        $params[] = $ley_filtro;
        $types .= "s";
    }
}

$where_sql = implode(" AND ", $where_clauses);
$query = "SELECT ley, articulo, texto, estado, fecha_modificacion, notas FROM pro_normativa WHERE $where_sql ORDER BY ley ASC, CAST(REGEXP_SUBSTR(articulo, '[0-9]+') AS UNSIGNED) ASC";

$agrupados = [];
$total_resultados = 0;

$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ley = $row['ley'];
        if (!isset($agrupados[$ley])) {
            $agrupados[$ley] = [];
        }
        $agrupados[$ley][] = $row;
        $total_resultados++;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Normativa Laboral — Estudio Farias Ortiz</title>
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
        .badge-bajo { background: rgba(39,174,96,0.15); color: #27ae60; }
        .badge-moderado { background: rgba(243,156,18,0.15); color: #f39c12; }
        .badge-critico { background: rgba(231,76,60,0.15); color: #e74c3c; }
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
                    <img src="<?= htmlspecialchars(ml_logo_src()) ?>" alt="Estudio Farias Ortiz" class="logo-img">
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
                <h1 class="page-title">📜 Base Normativa Laboral</h1>
                <p class="page-subtitle"><?= $total_resultados ?> artículos — LCT, Ley 27.802, LRT, Ley Bases, Reglamentaciones</p>
            </div>

            <div class="card" style="margin-bottom:24px;">
                <form method="GET" action="normativa.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
                    <div class="form-group" style="flex:2; margin-bottom:0; min-width:200px;">
                        <label for="q">Buscar en normativa</label>
                        <input type="text" class="form-control" name="q" id="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej: indemnización, prescripción, Art. 245, solidaridad...">
                    </div>
                    <div class="form-group" style="flex:1; margin-bottom:0; min-width:160px;">
                        <label for="ley">Filtrar por ley</label>
                        <select class="form-control" name="ley" id="ley">
                            <option value="">Todas</option>
                            <option value="lct" <?= strpos($ley_filtro, 'lct') !== false ? 'selected' : '' ?>>LCT 20.744</option>
                            <option value="27802" <?= strpos($ley_filtro, '27802') !== false ? 'selected' : '' ?>>Ley 27.802</option>
                            <option value="lrt" <?= strpos($ley_filtro, 'lrt') !== false ? 'selected' : '' ?>>LRT / Ley 24.557</option>
                            <option value="bases" <?= strpos($ley_filtro, 'bases') !== false ? 'selected' : '' ?>>Ley Bases 27.742</option>
                            <option value="reglam" <?= strpos($ley_filtro, 'reglam') !== false ? 'selected' : '' ?>>Reglamentaciones / CSJN</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-bottom:0;">🔍 Buscar</button>
                    <?php if ($busqueda || $ley_filtro): ?>
                    <a href="normativa.php" class="btn btn-secondary" style="margin-bottom:0;">✕ Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($agrupados)): ?>
                <?php foreach ($agrupados as $ley => $articulos): ?>
                <div class="section" style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.5rem; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--accent-primary); display: inline-block;">
                        <?php 
                            if (strpos($ley, 'LCT') !== false) echo '⚖️';
                            elseif (strpos($ley, '27.802') !== false) echo '📜';
                            elseif (strpos($ley, '24.557') !== false || strpos($ley, 'LRT') !== false) echo '🏥';
                            elseif (strpos($ley, 'Bases') !== false) echo '📋';
                            elseif (strpos($ley, 'CSJN') !== false) echo '🏛️';
                            else echo '📄';
                        ?>
                        <?= htmlspecialchars($ley) ?>
                        <span style="font-size:12px; color:var(--text-muted); font-weight:400; margin-left:8px;">(<?= count($articulos) ?> artículos)</span>
                    </h2>

                    <?php foreach ($articulos as $art): ?>
                    <?php 
                        $estado_lower = strtolower($art['estado']);
                        $is_derogado = strpos($estado_lower, 'derogado') !== false;
                        $border_color = 'var(--border-subtle)';
                        if ($is_derogado) $border_color = 'var(--text-danger)';
                        // Relevancia was removed from pro_normativa, so styling is default here. 
                    ?>
                    <div class="card" style="margin-bottom:12px; border-left:4px solid <?= $border_color ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                            <div>
                                <span style="font-weight:700; color:var(--accent-primary); font-size:15px;"><?= htmlspecialchars($art['articulo'] ?? 'Artículo') ?></span>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <?php if (strpos($estado_lower, 'vigente') !== false): ?>
                                    <span class="badge badge-bajo"><?= htmlspecialchars(substr($art['estado'], 0, 30)) ?></span>
                                <?php elseif ($is_derogado): ?>
                                    <span class="badge badge-critico">DEROGADO</span>
                                <?php else: ?>
                                    <span class="badge badge-moderado"><?= htmlspecialchars(substr($art['estado'], 0, 30)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p style="font-size:14px; color:var(--text-primary); line-height:1.6; margin-bottom:12px; white-space: pre-wrap;"><?= htmlspecialchars($art['texto']) ?></p>
                        
                        <?php if (!empty($art['notas'])): ?>
                        <div style="font-size:13px; color:var(--text-secondary); background:rgba(0,0,0,0.02); padding:10px 14px; border-radius:6px; border-left:2px solid var(--accent-secondary);">
                            <strong style="color:var(--accent-secondary);">📌 Notas prácticas:</strong> <?= htmlspecialchars($art['notas']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($art['fecha_modificacion'])): ?>
                        <div style="font-size:11px; color:var(--text-muted); margin-top: 8px; text-align: right;">
                            Modificado: <?= htmlspecialchars($art['fecha_modificacion']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-icon">📜</div>
                        <div class="empty-text">No se encontraron artículos para "<?= htmlspecialchars($busqueda) ?>"</div>
                        <a href="normativa.php" class="btn btn-primary" style="margin-top:1rem;">Ver toda la normativa</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
