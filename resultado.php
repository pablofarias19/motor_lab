<?php
/**
 * resultado.php — Pantalla de resultados del análisis de riesgo laboral
 *
 * Recupera el análisis de la BD mediante el UUID pasado por GET,
 * inyecta los datos en el HTML y los renderiza con resultados.js.
 *
 * Muestra:
 *   - Gauge visual del IRIL con color según nivel
 *   - Desglose de las 5 dimensiones
 *   - Exposición económica estimada con tabla de conceptos
 *   - Tabla comparativa de los 4 escenarios A/B/C/D
 *   - Cards detalladas de cada escenario
 *   - Alertas laborales activas
 *   - Botones de acción: descargar informe PDF, solicitar consulta
 *
 * URL esperada: resultado.php?uuid=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/DatabaseManager.php';

// ─── Validar UUID ─────────────────────────────────────────────────────────────
$uuid = trim($_GET['uuid'] ?? '');

if (empty($uuid) || !preg_match('/^[a-f0-9\-]{36}$/', $uuid)) {
    header('Location: index.php');
    exit;
}

// ─── Recuperar análisis ───────────────────────────────────────────────────────
try {
    $db = new DatabaseManager();
    $analisis = $db->obtenerAnalisisPorUUID($uuid);

    if (!$analisis) {
        // UUID válido pero no encontrado → redirigir al inicio
        header('Location: index.php?error=no_encontrado');
        exit;
    }

} catch (Exception $e) {
    ml_logear('Error recuperando análisis en resultado.php: ' . $e->getMessage(), 'error', 'error.log');
    header('Location: index.php?error=bd_error');
    exit;
}

// ─── Decodificar datos almacenados ────────────────────────────────────────────
$datosLaborales = json_decode($analisis['datos_laborales'], true) ?? [];
$documentacion = json_decode($analisis['documentacion_json'], true) ?? [];
$situacion = json_decode($analisis['situacion_json'], true) ?? [];
$irilPayload = ml_parse_iril_payload(json_decode($analisis['iril_detalle'], true) ?? []);
$exposicion = json_decode($analisis['exposicion_json'], true) ?? [];
$escenariosPayload = ml_parse_escenarios_payload(
    json_decode($analisis['escenarios_json'], true) ?? [],
    $analisis['escenario_recomendado'] ?? 'C'
);

$irilDetalle = $irilPayload['detalle'];
$alertas = $irilPayload['alertas'];
$irilScore = $irilPayload['score'] > 0 ? $irilPayload['score'] : floatval($analisis['iril_score']);
$nivelIril = is_array($irilPayload['nivel']) ? $irilPayload['nivel'] : ml_nivel_iril($irilScore);
$escenarios = $escenariosPayload['escenarios'];
$escRecomendado = $escenariosPayload['recomendado'];
$tablaComparativa = $escenariosPayload['tabla_comparativa'];
$alertasMarzo2026 = $escenariosPayload['alertas_marzo_2026'];

$tipoConflictoLabel = ml_conflicto_label($analisis['tipo_conflicto']);

// ─── Registrar que el usuario vio el informe ──────────────────────────────────
try {
    $db->registrarAccion($uuid, 'ver_informe');
} catch (Exception $e) {
    // No interrumpir si falla el registro de acción
}

$analisisComplementario = is_array($exposicion['analisis_complementario'] ?? null)
    ? $exposicion['analisis_complementario']
    : [];

if (empty($analisisComplementario)) {
    try {
        $analisisComplementario = \App\Support\ComplementaryLegalAnalysisBuilder::build(
            $datosLaborales,
            $situacion,
            $exposicion
        );
    } catch (Exception $e) {
        ml_logear("[resultado.php] Error generando análisis complementario: " . $e->getMessage(), 'warning', 'analisis.log');
        $analisisComplementario = [];
    }
}

$ley27802 = $analisisComplementario['ley_27802'] ?? ['presuncion' => null, 'solidaria' => null, 'fraude' => null, 'dano' => null];
// ─── Lógica adicional para el dashboard premium ──────────────────────────────

// Resumen de documentación disponible
$docsDisponibles = [];
if (!empty($documentacion['tiene_telegramas']) && $documentacion['tiene_telegramas'] === 'si')
    $docsDisponibles[] = 'Telegramas';
if (!empty($documentacion['tiene_recibos']) && $documentacion['tiene_recibos'] === 'si')
    $docsDisponibles[] = 'Recibos de sueldo';
if (!empty($documentacion['tiene_contrato']) && $documentacion['tiene_contrato'] === 'si')
    $docsDisponibles[] = 'Contrato escrito';
if (!empty($documentacion['registrado_afip']) && $documentacion['registrado_afip'] === 'si')
    $docsDisponibles[] = 'Registro ARCA';
if (!empty($documentacion['tiene_testigos']) && $documentacion['tiene_testigos'] === 'si')
    $docsDisponibles[] = 'Testigos';
$docsStr = !empty($docsDisponibles) ? implode(' · ', $docsDisponibles) : 'Sin documentación declarada';

// Nivel de intervención sugerido
$nivelInterv = $irilScore >= 4.0 ? 'Urgente' : ($irilScore >= 3.0 ? 'Profesional' : ($irilScore >= 2.0 ? 'Preventiva' : 'Baja'));

// Configuración de visualización según IRIL
$badgeClass = $nivelIril['clase'] ?? 'iril-moderado';
$badgeIcon = $irilScore >= 4.0 ? 'bi-shield-x' : ($irilScore >= 3.0 ? 'bi-shield-exclamation' : 'bi-shield-check');
$badgeTexto = $irilScore >= 4.0
    ? 'Riesgo Crítico — Intervención profesional urgente'
    : ($irilScore >= 3.0
        ? 'Riesgo Alto — Se recomienda asesoría profesional'
        : ($irilScore >= 2.0
            ? 'Riesgo Moderado — Monitorear evolución'
            : 'Riesgo Bajo — Situación manejable'));

// Iconos para tipos de conflicto
$iconosConflicto = [
    'despido_sin_causa' => '<div class="conflicto-icon color-despido" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-person-x"></i></div>',
    'despido_con_causa' => '<div class="conflicto-icon color-despido" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-exclamation-octagon"></i></div>',
    'trabajo_no_registrado' => '<div class="conflicto-icon color-negro" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-person-workspace"></i></div>',
    'diferencias_salariales' => '<div class="conflicto-icon color-salarial" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-cash-stack"></i></div>',
    'accidente_laboral' => '<div class="conflicto-icon color-accidente" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-bandaid"></i></div>',
    'responsabilidad_solidaria' => '<div class="conflicto-icon color-empresa" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-diagram-3"></i></div>',
    'auditoria_preventiva' => '<div class="conflicto-icon color-empresa" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-clipboard-check"></i></div>',
    'riesgo_inspeccion' => '<div class="conflicto-icon color-arca" style="width: 28px; height: 28px; font-size: 0.9rem;"><i class="bi bi-bank"></i></div>',
];

$iconConflictoHtml = $iconosConflicto[$analisis['tipo_conflicto']] ?? '<i class="bi bi-file-earmark-text"></i>';

// Antigüedad formateada
$antiguedadMeses = intval($datosLaborales['antiguedad_meses'] ?? 0);
$antiguedadTexto = floor($antiguedadMeses / 12) . ' años ' . ($antiguedadMeses % 12 > 0 ? '(' . ($antiguedadMeses % 12) . ' meses)' : '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis de Riesgo Laboral — Resultado | Farias Ortiz</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- CSS del módulo -->
    <link rel="stylesheet" href="assets/css/motor.css">
    <!-- Nuevo Diseño Premium -->
    <link rel="stylesheet" href="assets/css/premium_dashboard.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Chart.js para gráficos premium -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="pagina-resultado">

    <!-- ═══════════════════════════════════════════════════════════
     ENCABEZADO
═══════════════════════════════════════════════════════════ -->
    <!-- El encabezado anterior ha sido reemplazado por el premium-header dentro de main -->

    <main class="premium-main">
        <div class="premium-header">
            <div class="premium-header-brand">
                <a href="https://fariasortiz.com.ar" target="_blank"
                    style="text-decoration: none; display: flex; align-items: center; gap: 1rem;">
                    <img src="<?= htmlspecialchars(ml_logo_src()) ?>" alt="Estudio Farias Ortiz"
                        style="height: 35px; width: auto;">
                </a>
                <div style="width: 1px; height: 20px; background-color: rgba(255,255,255,0.2); margin: 0 0.5rem;"></div>
                <div class="premium-tagline">Evaluador Estratégico Jurídico</div>
            </div>
            <div class="premium-user">
                <div aria-hidden="true" style="width:36px;height:36px;border-radius:999px;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">U</div>
                <span>Usuario</span>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>

        <div class="premium-container">
            <!-- Banner de Alerta de Riesgo -->
            <div class="premium-alert-banner">
                <div class="premium-alert-content">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= strtoupper($badgeTexto) ?></span>
                </div>
                <div class="premium-alert-note" style="font-size: 0.8rem; color: #92400e;">
                    Se recomienda Revisión Profesional Inmediata.
                </div>
            </div>

            <!-- Encabezado de Proyecto -->
            <div class="premium-project-header">
                <span class="premium-project-id">Proyecto #<?= strtoupper(substr($uuid, 0, 8)) ?></span>
                <div class="premium-project-meta">
                    <div class="premium-meta-item">Materia: <strong>Laboral</strong></div>
                    <div class="premium-meta-item">Subtipo: 
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; vertical-align: middle; margin-left: 5px;">
                            <?= $iconConflictoHtml ?>
                            <strong><?= htmlspecialchars($tipoConflictoLabel) ?></strong>
                        </div>
                    </div>
                    <div class="premium-meta-item">Jurisdicción:
                        <strong><?= htmlspecialchars($datosLaborales['provincia'] ?? 'Sin especificar') ?></strong>
                    </div>
                </div>
                <div class="premium-project-stats">
                    <div>Nivel de Complejidad:
                        <strong><?= $irilScore > 3.5 ? 'Alto' : ($irilScore > 2.5 ? 'Medio' : 'Bajo') ?></strong>
                    </div>
                    <div>Riesgo Judicial Institucional: <strong>
                            <?php
                            $stars = floor($irilScore);
                            for ($i = 1; $i <= 5; $i++)
                                echo $i <= $stars ? '●' : '○';
                            ?> (<?= number_format($irilScore, 1) ?>/5)</strong>
                    </div>
                </div>
            </div>

            <!-- Card: Resumen del Caso -->
            <div class="premium-card">
                <div class="premium-card-header">
                    <h3>Resumen del Caso</h3>
                </div>
                <div class="premium-card-body">
                    <div class="resumen-grid">
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-card-checklist"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Hechos Relevantes:</span>
                                <span class="resumen-value">Antigüedad: <?= $antiguedadTexto ?>, <?= $docsStr ?></span>
                            </div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-bullseye"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Objetivo:</span>
                                <span
                                    class="resumen-value"><?= $analisis['tipo_usuario'] === 'empleador' ? 'Mitigar Impacto Económico' : 'Maximizar Recuperación Económica' ?></span>
                            </div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-globe"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Horizonte Temporal:</span>
                                <span class="resumen-value">Estimado: 3 a 24 meses</span>
                            </div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-briefcase"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Intervención Profesional:</span>
                                <span class="resumen-value"><?= $nivelInterv ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
            // ── SECCIÓN LEY 27.802 ── Solo mostrar si hay datos ──
            $tienePresuncion = !empty($ley27802['presuncion']);
            $tieneSolidaria  = !empty($ley27802['solidaria']);
            $tieneFraude     = !empty($ley27802['fraude']);
            $tieneDano       = !empty($ley27802['dano']);
            
            if ($tienePresuncion || $tieneSolidaria || $tieneFraude || $tieneDano): ?>
            <div class="premium-card collapsible">
                <div class="premium-card-header" onclick="this.parentElement.classList.toggle('collapsed')" style="cursor:pointer;">
                    <h3><i class="bi bi-shield-check"></i> Análisis Ley 27.802 — Presunción, Solidaria, Fraude y Daño</h3>
                </div>
                <div class="premium-card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                        
                        <?php if ($tienePresuncion): 
                            $pres = $ley27802['presuncion'];
                            $presOpera = $pres['presuncion_opera'] ?? false;
                            $presRecomendacion = $pres['recomendación'] ?? ($pres['recomendacion'] ?? '');
                        ?>
                        <!-- Art. 23 — Presunción -->
                        <div style="border: 2px solid <?= $presOpera ? '#dc3545' : '#16a34a' ?>; border-radius: 10px; padding: 1rem; background: <?= $presOpera ? '#fff5f5' : '#f0fdf4' ?>;">
                            <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: <?= $presOpera ? '#dc3545' : '#16a34a' ?>;">
                                🔍 Art. 23 LCT — Presunción Laboral
                            </h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.5rem;"><?= $presOpera ? '⚠️' : '✅' ?></span>
                                <strong style="font-size: 1rem;"><?= $presOpera ? 'PRESUNCIÓN OPERA' : 'PRESUNCIÓN NO OPERA' ?></strong>
                            </div>
                            <div style="font-size: 0.8rem; color: #555;">
                                <div>Facturación: <?= ($situacion['tiene_facturacion'] ?? 'no') === 'si' ? '✅ Sí' : '❌ No' ?></div>
                                <div>Pago bancario: <?= ($situacion['tiene_pago_bancario'] ?? 'no') === 'si' ? '✅ Sí' : '❌ No' ?></div>
                                <div>Contrato escrito: <?= ($situacion['tiene_contrato_escrito'] ?? 'no') === 'si' ? '✅ Sí' : '❌ No' ?></div>
                            </div>
                            <?php if (!empty($presRecomendacion)): ?>
                            <p style="margin: 0.5rem 0 0; font-size: 0.75rem; font-style: italic; color: #666;">
                                <?= htmlspecialchars($presRecomendacion) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tieneSolidaria):
                            $sol = $ley27802['solidaria'];
                            $solExento = $sol['exento'] ?? false;
                            $solControles = intval($sol['controles_validados'] ?? 0);
                            $solFactor = floatval($sol['factor_exención'] ?? ($sol['factor_exencion'] ?? 0));
                        ?>
                        <!-- Art. 30 — Solidaria -->
                        <div style="border: 2px solid <?= $solExento ? '#16a34a' : '#f59e0b' ?>; border-radius: 10px; padding: 1rem; background: <?= $solExento ? '#f0fdf4' : '#fffbeb' ?>;">
                            <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: <?= $solExento ? '#16a34a' : '#f59e0b' ?>;">
                                ⚖️ Art. 30 LCT — Solidaria
                            </h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.5rem;"><?= $solExento ? '🛡️' : '⚠️' ?></span>
                                <strong style="font-size: 1rem;"><?= $solExento ? 'EXENTO' : 'NO EXENTO' ?></strong>
                            </div>
                            <div style="font-size: 0.8rem; color: #555;">
                                <div>Controles validados: <strong><?= $solControles ?>/5</strong></div>
                                <div>Factor de exposición: <strong><?= number_format($solFactor, 1) ?>x</strong></div>
                                <div>CUIL: <?= ($situacion['valida_cuil'] ?? 'no') === 'si' ? '✅' : '❌' ?>
                                     Aportes: <?= ($situacion['valida_aportes'] ?? 'no') === 'si' ? '✅' : '❌' ?>
                                     Pago: <?= ($situacion['valida_pago_directo'] ?? 'no') === 'si' ? '✅' : '❌' ?>
                                     CBU: <?= ($situacion['valida_cbu'] ?? 'no') === 'si' ? '✅' : '❌' ?>
                                     ART: <?= ($situacion['valida_art'] ?? 'no') === 'si' ? '✅' : '❌' ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tieneFraude):
                            $fr = $ley27802['fraude'];
                            $frNivel = $fr['nivel'] ?? 'NINGUNO';
                            $frScore = intval($fr['indicadores_detectados'] ?? 0);
                            $frRecomendacion = $fr['recomendación'] ?? ($fr['recomendacion'] ?? '');
                            $frColor = match($frNivel) {
                                'CRÍTICO' => '#dc2626',
                                'ALTO' => '#ea580c',
                                'MEDIO' => '#f59e0b',
                                'BAJO' => '#16a34a',
                                default => '#6b7280'
                            };
                        ?>
                        <!-- Fraude Laboral -->
                        <div style="border: 2px solid <?= $frColor ?>; border-radius: 10px; padding: 1rem; background: <?= $frNivel === 'NINGUNO' ? '#f8f9fa' : '#fff5f5' ?>;">
                            <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: <?= $frColor ?>;">
                                ⚠️ Fraude Laboral
                            </h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.5rem;"><?= $frNivel === 'NINGUNO' ? '✅' : '🚨' ?></span>
                                <strong style="font-size: 1rem;">Riesgo: <?= $frNivel ?></strong>
                            </div>
                            <div style="font-size: 0.8rem; color: #555;">
                                Indicadores detectados: <strong><?= $frScore ?>/5</strong>
                            </div>
                            <?php if (!empty($frRecomendacion)): ?>
                            <p style="margin: 0.5rem 0 0; font-size: 0.75rem; font-style: italic; color: #666;">
                                <?= htmlspecialchars($frRecomendacion) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tieneDano):
                            $dn = $ley27802['dano'];
                            $dnTotal = floatval($dn['total_daño_complementario'] ?? 0);
                        ?>
                        <!-- Daño Complementario -->
                        <div style="border: 2px solid #7c3aed; border-radius: 10px; padding: 1rem; background: #faf5ff;">
                            <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: #7c3aed;">
                                💔 Daño Complementario (Art. 527 CCCN)
                            </h4>
                            <div style="font-size: 0.85rem; color: #555;">
                                <div style="display:flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span>Moral:</span>
                                    <strong><?= ml_formato_moneda(floatval($dn['daño_moral'] ?? 0)) ?></strong>
                                </div>
                                <div style="display:flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span>Patrimonial:</span>
                                    <strong><?= ml_formato_moneda(floatval($dn['daño_patrimonial'] ?? 0)) ?></strong>
                                </div>
                                <div style="display:flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span>Reputacional:</span>
                                    <strong><?= ml_formato_moneda(floatval($dn['daño_reputacional'] ?? 0)) ?></strong>
                                </div>
                                <div style="display:flex; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 2px solid #7c3aed; font-size: 1rem;">
                                    <strong>TOTAL:</strong>
                                    <strong style="color: #7c3aed;"><?= ml_formato_moneda($dnTotal) ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $analisisEmpresa = $exposicion['analisis_empresa'] ?? [];
            if (!empty($analisisEmpresa)): ?>
            <div class="premium-card">
                <div class="premium-card-header">
                    <h3><i class="bi bi-building-check"></i> Diagnóstico específico para empresa</h3>
                </div>
                <div class="premium-card-body">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem;">
                        <?php if (!empty($analisisEmpresa['art_empresa'])):
                            $artEmp = $analisisEmpresa['art_empresa']; ?>
                            <div style="border:1px solid #f59e0b; border-radius:12px; padding:1rem; background:#fffaf0;">
                                <h4 style="margin:0 0 .75rem; font-size:.95rem; color:#b45309;">🏥 Contingencia ART empresa</h4>
                                <div><strong>Nivel:</strong> <?= htmlspecialchars($artEmp['nivel_riesgo'] ?? '-') ?></div>
                                <div><strong>Responsable principal:</strong> <?= htmlspecialchars($artEmp['responsable_principal'] ?? '-') ?></div>
                                <div><strong>Exposición estimada:</strong> <?= ml_formato_moneda(floatval($artEmp['exposicion_estimada_empresa'] ?? 0)) ?></div>
                                <?php if (!empty($artEmp['alertas_juridicas'])): ?>
                                    <ul style="margin:.75rem 0 0 1rem; font-size:.85rem; color:#6b7280;">
                                        <?php foreach ($artEmp['alertas_juridicas'] as $alerta): ?>
                                            <li><?= htmlspecialchars($alerta) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($analisisEmpresa['solidaridad'])):
                            $solEmp = $analisisEmpresa['solidaridad']; ?>
                            <div style="border:1px solid #2563eb; border-radius:12px; padding:1rem; background:#f8fbff;">
                                <h4 style="margin:0 0 .75rem; font-size:.95rem; color:#1d4ed8;">🤝 Responsabilidad solidaria</h4>
                                <div><strong>Riesgo:</strong> <?= htmlspecialchars($solEmp['riesgo_calificacion'] ?? '-') ?></div>
                                <div><strong>Probabilidad de condena:</strong> <?= htmlspecialchars($solEmp['probabilidad_condena'] ?? '-') ?></div>
                                <div><strong>Exposición esperada:</strong> <?= ml_formato_moneda(floatval($solEmp['exposicion_esperada'] ?? 0)) ?></div>
                                <p style="margin:.75rem 0 0; font-size:.85rem; color:#6b7280;"><?= htmlspecialchars($solEmp['recomendacion'] ?? '') ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($analisisEmpresa['auditoria'])):
                            $auditEmp = $analisisEmpresa['auditoria']; ?>
                            <div style="border:1px solid #7c3aed; border-radius:12px; padding:1rem; background:#faf5ff;">
                                <h4 style="margin:0 0 .75rem; font-size:.95rem; color:#6d28d9;">🧾 Auditoría preventiva</h4>
                                <div><strong>Regularizar:</strong> <?= ml_formato_moneda(floatval($auditEmp['cre_costo_regularizacion'] ?? 0)) ?></div>
                                <div><strong>Litigio esperado:</strong> <?= ml_formato_moneda(floatval($auditEmp['cll_costo_litigio_esperado'] ?? 0)) ?></div>
                                <div><strong>Acción sugerida:</strong> <?= htmlspecialchars($auditEmp['recomendacion_accion'] ?? '-') ?></div>
                                <p style="margin:.75rem 0 0; font-size:.85rem; color:#6b7280;"><?= htmlspecialchars($auditEmp['texto_estrategico'] ?? '') ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($analisisEmpresa['inspeccion'])):
                            $inspEmp = $analisisEmpresa['inspeccion']; ?>
                            <div style="border:1px solid #dc2626; border-radius:12px; padding:1rem; background:#fff5f5;">
                                <h4 style="margin:0 0 .75rem; font-size:.95rem; color:#dc2626;">🏛️ Inspección ARCA / Ministerio</h4>
                                <div><strong>Capital omitido:</strong> <?= ml_formato_moneda(floatval($inspEmp['capital_omitido'] ?? 0)) ?></div>
                                <div><strong>Intereses:</strong> <?= ml_formato_moneda(floatval($inspEmp['intereses'] ?? 0)) ?></div>
                                <div><strong>Deuda total:</strong> <?= ml_formato_moneda(floatval($inspEmp['deuda_total'] ?? 0)) ?></div>
                                <p style="margin:.75rem 0 0; font-size:.85rem; color:#6b7280;"><?= htmlspecialchars($inspEmp['detalle'] ?? '') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // ── SECCIÓN DIFERENCIADA ART (solo si accidente_laboral + tiene_art = si) ──
            $esArtConCobertura = ($analisis['tipo_conflicto'] === 'accidente_laboral')
                && (($situacion['tiene_art'] ?? 'no') === 'si');

            if ($esArtConCobertura):
                $montoTarifa = $exposicion['conceptos']['prestacion_art_tarifa']['monto'] ?? 0;
                $montoCivil = $exposicion['conceptos']['estimacion_civil_mendez']['monto'] ?? 0;
                $tipoContingencia = $situacion['tipo_contingencia'] ?? 'accidente_tipico';
                $estadoCM = $situacion['comision_medica'] ?? 'no_iniciada';
                $incapTipo = $situacion['incapacidad_tipo'] ?? 'permanente_definitiva';

                $etiquetasContingencia = [
                    'accidente_tipico' => 'Accidente de trabajo (típico)',
                    'in_itinere' => 'Accidente in itinere',
                    'enfermedad_profesional' => 'Enfermedad profesional',
                ];
                $etiquetasCM = [
                    'no_iniciada' => 'No iniciado',
                    'en_tramite' => 'En trámite',
                    'dictamen_emitido' => 'Dictamen emitido',
                    'homologado' => 'Homologado',
                ];

                // Etapas procesales ART para barra visual
                $etapas = ['Siniestro', 'Denuncia ART', 'Comisión Médica', 'Dictamen', 'Judicial'];
                $etapaActual = 0;
                if (($situacion['denuncia_art'] ?? 'no') === 'si') $etapaActual = 1;
                if (in_array($estadoCM, ['en_tramite'])) $etapaActual = 2;
                if (in_array($estadoCM, ['dictamen_emitido', 'homologado'])) $etapaActual = 3;
                if (($situacion['via_administrativa_agotada'] ?? 'no') === 'si') $etapaActual = 4;
            ?>

            <!-- Card: Análisis ART — Doble Vía -->
            <div class="premium-card">
                <div class="premium-card-header">
                    <h3><i class="bi bi-bandaid"></i> Análisis de Contingencia ART</h3>
                </div>
                <div class="premium-card-body">
                    <!-- Info siniestro -->
                    <div class="resumen-grid" style="margin-bottom: 1.5rem;">
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-lightning-charge"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Contingencia:</span>
                                <span class="resumen-value"><?= htmlspecialchars($etiquetasContingencia[$tipoContingencia] ?? $tipoContingencia) ?></span>
                            </div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-clipboard2-pulse"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Incapacidad:</span>
                                <span class="resumen-value"><?= floatval($situacion['porcentaje_incapacidad'] ?? 0) ?>% — <?= ucfirst(str_replace('_', ' ', $incapTipo)) ?></span>
                            </div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-icon"><i class="bi bi-building-check"></i></div>
                            <div class="resumen-content">
                                <span class="resumen-label">Estado CM:</span>
                                <span class="resumen-value"><?= htmlspecialchars($etiquetasCM[$estadoCM] ?? $estadoCM) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de etapa procesal -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.85rem; color: var(--premium-blue); margin-bottom: 0.5rem;">Etapa procesal actual</h4>
                        <div style="display: flex; align-items: center; gap: 0; width: 100%;">
                            <?php foreach ($etapas as $i => $etapa): ?>
                                <div style="flex: 1; text-align: center;">
                                    <div style="width: 24px; height: 24px; border-radius: 50%; margin: 0 auto 4px;
                                        background: <?= $i <= $etapaActual ? 'var(--premium-blue)' : '#e0e0e0' ?>;
                                        color: <?= $i <= $etapaActual ? '#fff' : '#999' ?>;
                                        display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
                                        <?= $i <= $etapaActual ? '&#10003;' : ($i) ?>
                                    </div>
                                    <span style="font-size: 0.65rem; color: <?= $i <= $etapaActual ? 'var(--premium-blue)' : '#999' ?>;"><?= $etapa ?></span>
                                </div>
                                <?php if ($i < count($etapas) - 1): ?>
                                    <div style="flex: 0.5; height: 2px; background: <?= $i < $etapaActual ? 'var(--premium-blue)' : '#e0e0e0' ?>;"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tabla comparativa ART vs Civil -->
                    <h4 style="font-size: 0.85rem; color: var(--premium-blue); margin-bottom: 0.5rem;">Comparativa: Tarifa ART vs Acción Civil</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: var(--premium-blue); color: #fff;">
                                <th style="padding: 8px; text-align: left;"></th>
                                <th style="padding: 8px; text-align: right;">Tarifa ART (Ley 24.557)</th>
                                <th style="padding: 8px; text-align: right;">Acción Civil (Méndez)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee;">Monto estimado</td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;"><?= ml_formato_moneda($montoTarifa) ?></td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;"><?= ml_formato_moneda($montoCivil) ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee;">Duración estimada</td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">3-12 meses</td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">36-60 meses</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee;">Riesgo procesal</td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">Bajo-Medio</td>
                                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">Alto</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 8px;">Diferencia</td>
                                <td colspan="2" style="padding: 6px 8px; text-align: right; font-weight: bold; color: <?= $montoCivil > $montoTarifa ? '#16a34a' : '#dc2626' ?>;">
                                    <?= $montoCivil > $montoTarifa ? '+' : '' ?><?= ml_formato_moneda($montoCivil - $montoTarifa) ?> vía civil
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ($estadoCM === 'homologado'): ?>
                    <div class="motor-aviso-legal" style="margin-top: 1rem; background: #fef2f2; border-color: #fecaca;">
                        <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626;"></i>
                        <span>El acuerdo homologado en Comisión Médica tiene <strong>efecto de cosa juzgada administrativa</strong>. Solo queda disponible la vía civil complementaria (Art. 4 Ley 26.773).</span>
                    </div>
                    <?php endif; ?>

                    <?php if (($situacion['via_administrativa_agotada'] ?? 'no') === 'no' && $estadoCM !== 'homologado'): ?>
                    <div class="motor-aviso-legal" style="margin-top: 1rem; background: #fff7ed; border-color: #fed7aa;">
                        <i class="bi bi-info-circle-fill" style="color: #ea580c;"></i>
                        <span><strong>Ley 27.348:</strong> Debe agotar la instancia ante Comisión Médica Jurisdiccional antes de iniciar acción judicial.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dimensiones del Índice IRIL (Diseño Premium) -->
            <div class="premium-card collapsible collapsed">
                <div class="premium-card-header" onclick="this.parentElement.classList.toggle('collapsed')">
                    <h3>Las 5 Dimensiones que componen el Índice</h3>
                </div>
                <div class="premium-card-body">
                    <div class="premium-dims-compact">
                        <!-- Saturación -->
                        <div class="dim-row-item" style="--row-color: var(--dim-saturacion);">
                            <div class="dim-icon-box"><i class="bi bi-bank"></i></div>
                            <div class="dim-info-main">
                                <div class="dim-titles">
                                    <span class="dim-name">Saturación tribunalicia</span>
                                    <span class="dim-weight">20% Peso</span>
                                </div>
                                <div class="dim-bar-wrapper">
                                    <div class="dim-bar-progress"
                                        style="width: <?= ($irilDetalle['saturacion_tribunalicia']['valor'] - 1) / 4 * 100 ?>%;">
                                    </div>
                                </div>
                                <p class="dim-hint">Carga procesal del fuero laboral según la provincia.</p>
                            </div>
                            <div class="dim-score-box">
                                <span
                                    class="dim-score-val"><?= number_format($irilDetalle['saturacion_tribunalicia']['valor'], 1) ?></span>
                                <span class="dim-score-total">/ 5.0</span>
                            </div>
                        </div>

                        <!-- Complejidad Probatoria -->
                        <div class="dim-row-item" style="--row-color: var(--dim-probatoria);">
                            <div class="dim-icon-box"><i class="bi bi-file-earmark-check"></i></div>
                            <div class="dim-info-main">
                                <div class="dim-titles">
                                    <span class="dim-name">Complejidad probatoria</span>
                                    <span class="dim-weight">25% Peso</span>
                                </div>
                                <div class="dim-bar-wrapper">
                                    <div class="dim-bar-progress"
                                        style="width: <?= ($irilDetalle['complejidad_probatoria']['valor'] - 1) / 4 * 100 ?>%;">
                                    </div>
                                </div>
                                <p class="dim-hint">Respaldo documental: recibos, telegramas, registración ARCA.</p>
                            </div>
                            <div class="dim-score-box">
                                <span
                                    class="dim-score-val"><?= number_format($irilDetalle['complejidad_probatoria']['valor'], 1) ?></span>
                                <span class="dim-score-total">/ 5.0</span>
                            </div>
                        </div>

                        <!-- Volatilidad Normativa -->
                        <div class="dim-row-item" style="--row-color: var(--dim-volatilidad);">
                            <div class="dim-icon-box"><i class="bi bi-lightning-charge"></i></div>
                            <div class="dim-info-main">
                                <div class="dim-titles">
                                    <span class="dim-name">Volatilidad normativa</span>
                                    <span class="dim-weight">15% Peso</span>
                                </div>
                                <div class="dim-bar-wrapper">
                                    <div class="dim-bar-progress"
                                        style="width: <?= ($irilDetalle['volatilidad_normativa']['valor'] - 1) / 4 * 100 ?>%;">
                                    </div>
                                </div>
                                <p class="dim-hint">Estabilidad de jurisprudencia (accidentes, solidaridad).</p>
                            </div>
                            <div class="dim-score-box">
                                <span
                                    class="dim-score-val"><?= number_format($irilDetalle['volatilidad_normativa']['valor'], 1) ?></span>
                                <span class="dim-score-total">/ 5.0</span>
                            </div>
                        </div>

                        <!-- Riesgo de Costas -->
                        <div class="dim-row-item" style="--row-color: var(--dim-costas);">
                            <div class="dim-icon-box"><i class="bi bi-shield-exclamation"></i></div>
                            <div class="dim-info-main">
                                <div class="dim-titles">
                                    <span class="dim-name">Riesgo de costas</span>
                                    <span class="dim-weight">20% Peso</span>
                                </div>
                                <div class="dim-bar-wrapper">
                                    <div class="dim-bar-progress"
                                        style="width: <?= ($irilDetalle['riesgo_costas']['valor'] - 1) / 4 * 100 ?>%;">
                                    </div>
                                </div>
                                <p class="dim-hint">Exposición a condena en costas e intimaciones formales.</p>
                            </div>
                            <div class="dim-score-box">
                                <span
                                    class="dim-score-val"><?= number_format($irilDetalle['riesgo_costas']['valor'], 1) ?></span>
                                <span class="dim-score-total">/ 5.0</span>
                            </div>
                        </div>

                        <!-- Riesgo Multiplicador -->
                        <div class="dim-row-item" style="--row-color: var(--dim-multiplicador);">
                            <div class="dim-icon-box"><i class="bi bi-diagram-3"></i></div>
                            <div class="dim-info-main">
                                <div class="dim-titles">
                                    <span class="dim-name">Riesgo multiplicador</span>
                                    <span class="dim-weight">20% Peso</span>
                                </div>
                                <div class="dim-bar-wrapper">
                                    <div class="dim-bar-progress"
                                        style="width: <?= ($irilDetalle['riesgo_multiplicador']['valor'] - 1) / 4 * 100 ?>%;">
                                    </div>
                                </div>
                                <p class="dim-hint">Efecto cascada y demandas en cadena.</p>
                            </div>
                            <div class="dim-score-box">
                                <span
                                    class="dim-score-val"><?= number_format($irilDetalle['riesgo_multiplicador']['valor'], 1) ?></span>
                                <span class="dim-score-total">/ 5.0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cuadrícula de Escenarios side-by-side -->
            <div class="escenarios-premium-grid">
                <?php foreach ($escenarios as $letra => $esc):
                    $vbpBase = !empty($escenarios['C']['vbp']) ? $escenarios['C']['vbp'] : (!empty($escenarios['A']['vbp']) ? $escenarios['A']['vbp'] : 1);
                    $scoreVal = round(($esc['vbp'] / max(1, $vbpBase)) * 75);
                    $scoreClass = ($esc['riesgo_institucional'] ?? 0) > 3.5 ? 'score-low' : (($esc['riesgo_institucional'] ?? 0) > 2.5 ? 'score-medium' : 'score-high');
                    ?>
                    <div class="escenario-premium-card">
                        <div class="escenario-premium-header">
                            <h4>Escenario <?= $letra ?> <span
                                    style="font-weight: 300; opacity: 0.8;"><?= htmlspecialchars($esc['nombre'] ?? '') ?></span>
                            </h4>
                        </div>
                        <div class="escenario-premium-body">
                            <ul class="escenario-metric-list">
                                <li class="escenario-metric-item">
                                    <span class="escenario-metric-label"><i class="bi bi-person-fill"></i> Beneficio:</span>
                                    <span
                                        class="escenario-metric-value"><?= ml_formato_moneda($esc['beneficio_estimado'] ?? 0) ?></span>
                                </li>
                                <li class="escenario-metric-item">
                                    <span class="escenario-metric-label"><i class="bi bi-currency-dollar"></i> Costo:</span>
                                    <span
                                        class="escenario-metric-value"><?= ml_formato_moneda($esc['costo_estimado'] ?? 0) ?></span>
                                </li>
                                <li class="escenario-metric-item">
                                    <span class="escenario-metric-label"><i class="bi bi-clock"></i> Duración:</span>
                                    <span
                                        class="escenario-metric-value"><?= ($esc['duracion_min_meses'] ?? 0) ?>-<?= ($esc['duracion_max_meses'] ?? 0) ?>
                                        meses</span>
                                </li>
                            </ul>
                            <div class="escenario-risk-row">
                                <div class="escenario-metric-label"><i class="bi bi-shield-lock"></i> Riesgo:
                                    <strong><?= $esc['riesgo_institucional'] > 3.5 ? 'Alto' : ($esc['riesgo_institucional'] > 2.5 ? 'Medio' : 'Bajo') ?></strong>
                                </div>
                                <div class="escenario-score-badge <?= $scoreClass ?>">
                                    <?= max(60, min(95, $scoreVal + 60)) ?>
                                </div>
                            </div>
                            <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--premium-blue);">
                                <u>Índice Estratégico:</u>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Fila de Comparativo Gráfico y Recomendación -->
            <div class="premium-footer-grid">
                <div class="premium-card" style="margin-bottom: 0;">
                    <div class="premium-card-header">
                        <h3>Análisis Comparativo</h3>
                    </div>
                    <div class="premium-card-body comparativo-chart-container" style="height: 300px;">
                        <div style="width: 200px; height: 100%;">
                            <canvas id="radarChart"></canvas>
                        </div>
                        <ul class="comparativo-factors">
                            <li class="factor-item">
                                <span class="factor-label"><i class="bi bi-check2-circle"></i> Volatilidad
                                    Jurisprudencial:</span>
                                <span
                                    class="factor-value"><?= $irilDetalle['volatilidad_normativa']['valor'] > 3 ? 'Alta' : 'Media' ?></span>
                            </li>
                            <li class="factor-item">
                                <span class="factor-label"><i class="bi bi-bank"></i> Congestión Tribunalicia:</span>
                                <span
                                    class="factor-value"><?= $irilDetalle['saturacion_tribunalicia']['valor'] > 3 ? 'Alta' : 'Media' ?></span>
                            </li>
                            <li class="factor-item">
                                <span class="factor-label"><i class="bi bi-file-earmark-ruled"></i> Complejidad
                                    Probatoria:</span>
                                <span
                                    class="factor-value"><?= $irilDetalle['complejidad_probatoria']['valor'] > 3 ? 'Alta' : 'Media' ?></span>
                            </li>
                            <li class="factor-item">
                                <span class="factor-label"><i class="bi bi-receipt"></i> Riesgo de Costas:</span>
                                <span
                                    class="factor-value"><?= $irilDetalle['riesgo_costas']['valor'] > 3 ? 'Alto' : 'Bajo' ?></span>
                            </li>
                            <li class="factor-item">
                                <span class="factor-label"><i class="bi bi-diagram-3"></i> Posible Dilación
                                    Procesal:</span>
                                <span
                                    class="factor-value"><?= $irilDetalle['riesgo_multiplicador']['valor'] > 3 ? 'Elevada' : 'Normal' ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="premium-right-panels">
                    <div class="premium-card" style="margin-bottom: 1rem;">
                        <div class="premium-card-header">
                            <h3>Índice Estratégico</h3>
                        </div>
                        <div class="premium-card-body" style="height: 200px;">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>

                    <div class="premium-card" id="cardRecomendacion">
                        <div class="premium-card-header"
                            style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;"
                            onclick="toggleRecomendacion()">
                            <h3 style="margin:0;"><i class="bi bi-lightbulb-fill"></i> Recomendación Estratégica</h3>
                            <i class="bi bi-chevron-up" id="toggleIconRec"></i>
                        </div>
                        <div class="premium-card-body" id="bodyRecomendacion">
                            <p class="recomendacion-text" style="margin:0;">
                                <?php
                                $recEsc = $escenarios[$escRecomendado] ?? [];
                                echo 'Se sugiere considerar el <strong>Escenario ' . $escRecomendado . '</strong> por su equilibrio entre beneficio proyectado y riesgo institucional bajo ' . htmlspecialchars($datosLaborales['provincia'] ?? 'la provincia') . '.';
                                ?>
                            </p>
                        </div>
                    </div>

                    <script>
                        function toggleRecomendacion() {
                            const body = document.getElementById('bodyRecomendacion');
                            const icon = document.getElementById('toggleIconRec');
                            if (body.style.display === 'none') {
                                body.style.display = 'block';
                                icon.className = 'bi bi-chevron-up';
                            } else {
                                body.style.display = 'none';
                                icon.className = 'bi bi-chevron-down';
                            }
                        }
                    </script>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="premium-actions">
                <button class="btn-premium btn-premium-white" onclick="location.href='index.php'">
                    <i class="bi bi-arrow-left"></i> Volver a Evaluar
                </button>
                <button class="btn-premium btn-premium-primary"
                    onclick="window.open('https://fariasortiz.com.ar', '_blank')">
                    Solicitar Evaluación Profesional
                </button>
                <button class="btn-premium btn-premium-primary" onclick="btnDescargarInforme()">
                    <i class="bi bi-file-earmark-pdf"></i> Descargar Informe en PDF
                </button>
                <button class="btn-premium btn-premium-primary"
                    onclick="window.open('https://fariasortiz.com.ar', '_blank')">
                    Agendar Consulta
                </button>
            </div>
        </div>
    </main>

    <div style="display:none">
        <!-- Contenido legado oculto pero disponible para scripts si es necesario -->
        <div id="alertas-container"></div>
        <div id="exposicion-table-container"></div>
        <div id="tabla-comparativa-container"></div>
        <div id="escenarios-cards-container"></div>
        <div id="iril-dimensiones-container"></div>
        <canvas id="iril-gauge-canvas"></canvas>
    </div>

    <footer class="premium-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="footer-logo">
                    <i class="bi bi-building-fill"></i> Estudio Farias Ortiz
                </div>
                <p class="footer-disclaimer">
                    © <?= date('Y') ?> Estudio Farias Ortiz — Asesores Legales. Especialistas en Derecho Laboral
                    Estratégico y Mitigación de Riesgos Corporativos.
                </p>
                <div class="footer-legal-note">
                    <strong>Aviso Legal:</strong> Este motor de análisis constituye una herramienta de orientación
                    diagnóstica basada en algoritmos de riesgo probabilístico. No constituye asesoramiento legal
                    personalizado ni garantiza resultados judiciales. La complejidad del sistema jurídico requiere una
                    revisión humana profesional.
                </div>
            </div>

            <div class="footer-contact-column">
                <h5 class="footer-contact-title">Consultas Profesionales</h5>
                <div class="footer-contact">
                    <a href="https://wa.me/5491168480793" class="contact-item" target="_blank">
                        <i class="bi bi-whatsapp"></i> +54 11 6848-0793
                    </a>
                    <a href="https://wa.me/5493512619599" class="contact-item" target="_blank">
                        <i class="bi bi-whatsapp"></i> +54 351 261-9599
                    </a>
                    <a href="mailto:estudio@fariasortiz.com.ar" class="contact-item">
                        <i class="bi bi-envelope-fill"></i> estudio@fariasortiz.com.ar
                    </a>
                    <a href="mailto:pablofarias19@gmail.com" class="contact-item">
                        <i class="bi bi-envelope"></i> pablofarias19@gmail.com
                    </a>
                    <a href="https://fariasortiz.com.ar" class="contact-item" target="_blank">
                        <i class="bi bi-globe"></i> www.fariasortiz.com.ar
                    </a>
                    <div class="contact-item">
                        <i class="bi bi-geo-alt-fill"></i> Dr. Pablo Nicolás Farías — MP 1-33775
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-info">
                Motor de Riesgo Laboral v<?= ML_VERSION ?> | Sistema de Inteligencia Jurídica
            </div>
            <div class="footer-social">
                <a href="#"><i class="bi bi-linkedin"></i></a>
                <a href="#"><i class="bi bi-instagram"></i></a>
                <a href="#"><i class="bi bi-twitter-x"></i></a>
            </div>
        </div>
    </footer>

    <!-- Inyectar datos para resultados.js (sin exponer datos sensibles) -->
    <script>
        window.analisisData = <?= json_encode([
            'uuid' => $uuid,
            // Estructura anidada que espera resultados.js
            'iril' => [
                'score' => $irilScore,
                'nivel' => $nivelIril,
                'detalle' => $irilDetalle,
                'alertas' => $alertas,
            ],
            'exposicion' => $exposicion,
            'escenarios' => $escenarios,
            'recomendado' => $escRecomendado,
            'tabla_comparativa' => $tablaComparativa,
            'alertas_marzo_2026' => $alertasMarzo2026,
            'tipo_conflicto' => $analisis['tipo_conflicto'],
            'tipo_usuario' => $analisis['tipo_usuario'],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>

    <!-- Script de renderizado de resultados -->
    <script src="assets/js/resultados.js"></script>
    <!-- Scripts de Gráficos Premium -->
    <script src="assets/js/premium_charts.js"></script>

    <script>
        // UUID disponible globalmente
        window.analisisUUID = <?= json_encode($uuid) ?>;
    </script>

</body>

</html>
