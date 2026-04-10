<?php
/**
 * motor_laboral/admin/analisis/lista.php — Listado filtrable de análisis laborales.
 *
 * Permite filtrar por:
 *   - tipo_usuario (empleado / empleador)
 *   - tipo_conflicto (10 opciones)
 *   - accion_tomada
 *   - iril_min (IRIL mínimo para filtrar casos graves)
 *
 * Muestra paginación de 20 en 20.
 * Cada fila tiene acceso al detalle del análisis.
 *
 * Lee desde DatabaseManager::listarAnalisis().
 */

$page_title = 'Análisis Laborales';

// ─── header admin (sesión + navbar + sidebar) ─────────────────────────────────
require_once __DIR__ . '/../../header.php';

// ─── dependencias ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../config/DatabaseManager.php';

// ─────────────────────────────────────────────────────────────────────────────
// PARÁMETROS DE FILTRO Y PAGINACIÓN
// ─────────────────────────────────────────────────────────────────────────────

$filtros = [];

// Filtros de texto/enum
foreach (['tipo_usuario', 'tipo_conflicto', 'accion_tomada'] as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[$campo] = trim($_GET[$campo]);
    }
}

// Filtro IRIL mínimo
if (!empty($_GET['iril_min']) && is_numeric($_GET['iril_min'])) {
    $filtros['iril_min'] = floatval($_GET['iril_min']);
}

$pagina  = max(1, intval($_GET['pagina'] ?? 1));
$limite  = 20;
$offset  = ($pagina - 1) * $limite;

// ─────────────────────────────────────────────────────────────────────────────
// CONSULTA A BD
// ─────────────────────────────────────────────────────────────────────────────

try {
    $db      = new DatabaseManager();
    $analisis = $db->listarAnalisis($filtros, $limite, $offset);
    // Para contar el total con los mismos filtros, pedimos uno más
    $hayMas  = count($analisis) === $limite;
} catch (Exception $e) {
    $analisis = [];
    $hayMas   = false;
    $errorMsg = htmlspecialchars($e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function listaLabelConflicto(string $tipo): string {
    return ml_conflicto_label($tipo);
}

function listaNivelIril(?float $score): string {
    if ($score === null || $score <= 0) return '';
    if ($score < 2) return 'bajo';
    if ($score < 3) return 'moderado';
    if ($score < 4) return 'alto';
    return 'critico';
}

/** Construye la URL de paginación preservando los filtros actuales */
function urlPagina(int $p): string {
    $params = $_GET;
    $params['pagina'] = $p;
    return '?' . http_build_query($params);
}
?>

<!-- ─────────────────────────────────────────────────────────────────────────
     CABECERA
────────────────────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 admin-page-hero flex-wrap gap-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color: var(--primary);">
            <i class="bi bi-list-ul me-2"></i>Análisis Laborales
        </h4>
        <small class="text-muted">Listado filtrable de todos los análisis registrados</small>
    </div>
    <a href="<?php echo ML_BASE_URL; ?>/admin/index.php"
       class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-warning"><?php echo $errorMsg; ?></div>
<?php endif; ?>

<!-- ─────────────────────────────────────────────────────────────────────────
     FILTROS
────────────────────────────────────────────────────────────────────────── -->
<div class="filtros-card mb-4">
    <form method="GET" action="" id="form-filtros">
        <div class="row g-2 align-items-end">

            <!-- Tipo de usuario -->
            <div class="col-6 col-md-2">
                <label>Tipo usuario</label>
                <select name="tipo_usuario" class="form-select form-select-sm filtro-autosubmit">
                    <option value="">Todos</option>
                    <option value="empleado"  <?php echo ($filtros['tipo_usuario'] ?? '') === 'empleado'  ? 'selected' : ''; ?>>Empleado</option>
                    <option value="empleador" <?php echo ($filtros['tipo_usuario'] ?? '') === 'empleador' ? 'selected' : ''; ?>>Empleador</option>
                </select>
            </div>

            <!-- Tipo de conflicto -->
            <div class="col-12 col-md-3">
                <label>Conflicto</label>
                <select name="tipo_conflicto" class="form-select form-select-sm filtro-autosubmit">
                    <option value="">Todos</option>
                    <?php
                    $conflictos = ml_conflicto_labels();
                    foreach ($conflictos as $val => $lbl):
                        $sel = ($filtros['tipo_conflicto'] ?? '') === $val ? 'selected' : '';
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $sel; ?>>
                            <?php echo $lbl; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Acción tomada -->
            <div class="col-6 col-md-2">
                <label>Acción</label>
                <select name="accion_tomada" class="form-select form-select-sm filtro-autosubmit">
                    <option value="">Todas</option>
                    <option value="ver_informe" <?php echo ($filtros['accion_tomada'] ?? '') === 'ver_informe' ? 'selected' : ''; ?>>Ver informe</option>
                    <option value="contacto"    <?php echo ($filtros['accion_tomada'] ?? '') === 'contacto'    ? 'selected' : ''; ?>>Contacto</option>
                    <option value="tramite"     <?php echo ($filtros['accion_tomada'] ?? '') === 'tramite'     ? 'selected' : ''; ?>>Trámite</option>
                    <option value="ninguna"     <?php echo ($filtros['accion_tomada'] ?? '') === 'ninguna'     ? 'selected' : ''; ?>>Ninguna</option>
                </select>
            </div>

            <!-- IRIL mínimo -->
            <div class="col-6 col-md-2">
                <label>IRIL mínimo</label>
                <select name="iril_min" class="form-select form-select-sm filtro-autosubmit">
                    <option value="">Sin filtro</option>
                    <option value="2" <?php echo ($filtros['iril_min'] ?? '') == 2 ? 'selected' : ''; ?>>≥ 2 (moderado)</option>
                    <option value="3" <?php echo ($filtros['iril_min'] ?? '') == 3 ? 'selected' : ''; ?>>≥ 3 (alto)</option>
                    <option value="4" <?php echo ($filtros['iril_min'] ?? '') == 4 ? 'selected' : ''; ?>>≥ 4 (crítico)</option>
                </select>
            </div>

            <!-- Botones -->
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="?" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     TABLA DE RESULTADOS
────────────────────────────────────────────────────────────────────────── -->
<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>UUID</th>
                    <th>Usuario</th>
                    <th>Conflicto</th>
                    <th>IRIL</th>
                    <th>Nivel</th>
                    <th>Escenario</th>
                    <th>Acción</th>
                    <th>Email</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($analisis)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No se encontraron análisis con los filtros seleccionados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($analisis as $a):
                    $score  = floatval($a['iril_score'] ?? 0);
                    $nivel  = listaNivelIril($score);
                    $escRec = strtoupper($a['escenario_recomendado'] ?? '');
                    $uuid   = $a['uuid'] ?? '';
                    $fecha  = $a['fecha_creacion'] ?? '';
                ?>
                <tr data-iril-nivel="<?php echo htmlspecialchars($nivel); ?>">
                    <td><small class="text-muted">#<?php echo intval($a['id']); ?></small></td>
                    <td>
                        <span class="uuid-tag copiable" data-uuid="<?php echo htmlspecialchars($uuid); ?>">
                            <?php echo htmlspecialchars(substr($uuid, 0, 8)); ?>…
                        </span>
                    </td>
                    <td>
                        <?php if ($a['tipo_usuario'] === 'empleado'): ?>
                            <span class="badge bg-info text-dark">Empleado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Empleador</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <small title="<?php echo htmlspecialchars(listaLabelConflicto($a['tipo_conflicto'] ?? '')); ?>">
                            <?php echo htmlspecialchars(listaLabelConflicto($a['tipo_conflicto'] ?? '')); ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($score > 0): ?>
                        <div class="iril-bar-wrap" style="min-width: 80px;">
                            <div class="iril-bar">
                                <div class="iril-bar-fill <?php echo $nivel; ?>"
                                     style="width:<?php echo round(($score/5)*100); ?>%"></div>
                            </div>
                            <span class="iril-bar-val" style="color: var(--iril-<?php echo $nivel; ?>);">
                                <?php echo number_format($score, 1); ?>
                            </span>
                        </div>
                        <?php else: ?><small class="text-muted">—</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($nivel): ?>
                            <span class="badge-iril <?php echo $nivel; ?>">
                                <?php echo ucfirst($nivel); ?>
                            </span>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($escRec): ?>
                            <span class="badge-escenario <?php echo htmlspecialchars($escRec); ?>">
                                <?php echo htmlspecialchars($escRec); ?>
                            </span>
                        <?php else: ?><small class="text-muted">—</small><?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($a['accion_tomada'] ?? 'sin acción'); ?>
                        </small>
                    </td>
                    <td>
                        <?php if (!empty($a['email'])): ?>
                            <small><a href="mailto:<?php echo htmlspecialchars($a['email']); ?>">
                                <?php echo htmlspecialchars($a['email']); ?>
                            </a></small>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?php echo $fecha ? date('d/m/y H:i', strtotime($fecha)) : '—'; ?>
                        </small>
                    </td>
                    <td>
                        <a href="<?php echo ML_BASE_URL; ?>/admin/analisis/detalle.php?uuid=<?php
                            echo urlencode($uuid); ?>"
                           class="btn btn-sm btn-outline-primary" style="font-size:11px;">
                            Ver <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     PAGINACIÓN
────────────────────────────────────────────────────────────────────────── -->
<?php if ($pagina > 1 || $hayMas): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo urlPagina($pagina - 1); ?>">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
        </li>
        <?php endif; ?>

        <li class="page-item disabled">
            <span class="page-link">Página <?php echo $pagina; ?></span>
        </li>

        <?php if ($hayMas): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo urlPagina($pagina + 1); ?>">
                Siguiente <i class="bi bi-chevron-right"></i>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
