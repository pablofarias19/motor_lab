<?php
$page_title = 'Backend editable';

require_once __DIR__ . '/../header.php';

if (!function_exists('ml_admin_backend_text')) {
    function ml_admin_backend_text(?string $value): string
    {
        return trim(preg_replace("/\r\n?/", "\n", (string) $value));
    }
}

if (!function_exists('ml_admin_backend_float')) {
    function ml_admin_backend_float($value, float $fallback = 0.0): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? round((float) $normalized, 4) : $fallback;
    }
}

if (!function_exists('ml_admin_backend_int')) {
    function ml_admin_backend_int($value, int $fallback = 0): int
    {
        return is_numeric($value) ? intval($value) : $fallback;
    }
}

if (!function_exists('ml_admin_backend_color')) {
    function ml_admin_backend_color(?string $value, string $fallback): string
    {
        $candidate = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) ? strtolower($candidate) : $fallback;
    }
}

$tiposExtincion = [
    'despido' => 'Despido directo',
    'renuncia_previa' => 'Renuncia previa coercitiva',
    'constructivo' => 'Terminación constructiva',
    'suspensión' => 'Suspensión',
];

$runtime = ml_admin_runtime();
$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_backend_editable'])) {
    if (!ml_admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $mensajeError = 'La sesión expiró. Recargá la página e intentá nuevamente.';
    } else {
        $jsonOverridesRaw = trim((string) ($_POST['parametros_motor_overrides_json'] ?? ''));
        $jsonOverrides = [];

        if ($jsonOverridesRaw !== '') {
            $decoded = json_decode($jsonOverridesRaw, true);
            if (!is_array($decoded)) {
                $mensajeError = 'El bloque JSON avanzado no tiene un formato válido.';
            } else {
                $jsonOverrides = $decoded;
            }
        }

        if ($mensajeError === '') {
            $tiposSeleccionados = array_values(array_intersect(
                array_keys($tiposExtincion),
                array_map('strval', $_POST['reputacional_allowed_types'] ?? [])
            ));

            $overridesPreventivo = [
                'escenarios' => [
                    'preventivo' => [
                        'factor_costo_regularizacion' => ml_admin_backend_float($_POST['factor_costo_regularizacion'] ?? null, 1.5),
                        'meses_ahorro_litigio' => ml_admin_backend_int($_POST['meses_ahorro_litigio'] ?? null, 12),
                        'duracion_promedio' => max(1, ml_admin_backend_int($_POST['duracion_promedio'] ?? null, 2)),
                    ],
                ],
            ];

            $payload = [
                'ui' => [
                    'dano_complementario' => [
                        'intro' => ml_admin_backend_text($_POST['dano_intro'] ?? ''),
                        'reputacional_criterio' => ml_admin_backend_text($_POST['reputacional_criterio'] ?? ''),
                    ],
                    'escenario_preventivo' => [
                        'accent_color' => ml_admin_backend_color(
                            $_POST['escenario_preventivo_color'] ?? null,
                            '#0f766e'
                        ),
                        'badge_label' => ml_admin_backend_text($_POST['escenario_preventivo_badge'] ?? ''),
                        'clarification' => ml_admin_backend_text($_POST['escenario_preventivo_clarificacion'] ?? ''),
                        'economic_reading_empleador' => ml_admin_backend_text($_POST['escenario_preventivo_lectura_empleador'] ?? ''),
                        'economic_reading_general' => ml_admin_backend_text($_POST['escenario_preventivo_lectura_general'] ?? ''),
                    ],
                ],
                'calculation_rules' => [
                    'dano_complementario' => [
                        'reputacional' => [
                            'enabled' => isset($_POST['reputacional_enabled']),
                            'requires_violence' => isset($_POST['reputacional_requires_violence']),
                            'allowed_types' => $tiposSeleccionados,
                            'percentages' => [
                                'despido' => ml_admin_backend_float($_POST['reputacional_pct_despido'] ?? null, 0),
                                'renuncia_previa' => ml_admin_backend_float($_POST['reputacional_pct_renuncia_previa'] ?? null, 0),
                                'constructivo' => ml_admin_backend_float($_POST['reputacional_pct_constructivo'] ?? null, 0.15),
                                'suspensión' => ml_admin_backend_float($_POST['reputacional_pct_suspension'] ?? null, 0),
                                'default' => 0.0,
                            ],
                        ],
                    ],
                    'parametros_motor_overrides' => ml_array_deep_merge($jsonOverrides, $overridesPreventivo),
                ],
                'prompts' => [
                    'resumen_informativo' => ml_admin_backend_text($_POST['prompt_resumen_informativo'] ?? ''),
                    'analisis_preventivo' => ml_admin_backend_text($_POST['prompt_analisis_preventivo'] ?? ''),
                    'actualizacion_contenido' => ml_admin_backend_text($_POST['prompt_actualizacion_contenido'] ?? ''),
                ],
            ];

            try {
                ml_admin_runtime_save($payload);
                $runtime = ml_admin_runtime(true);
                $mensajeExito = 'Backend editable actualizado correctamente.';
            } catch (Throwable $e) {
                $mensajeError = 'No se pudo guardar la configuración: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

$preventivoUi = $runtime['ui']['escenario_preventivo'] ?? [];
$danoUi = $runtime['ui']['dano_complementario'] ?? [];
$reputacional = $runtime['calculation_rules']['dano_complementario']['reputacional'] ?? [];
$prompts = $runtime['prompts'] ?? [];
$parametrosOverrides = $runtime['calculation_rules']['parametros_motor_overrides'] ?? [];
$parametrosPreventivo = $parametrosOverrides['escenarios']['preventivo'] ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-4 admin-page-hero flex-wrap gap-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color: var(--primary);">
            <i class="bi bi-sliders me-2"></i>Backend editable
        </h4>
        <small class="text-muted">Capa paralela para cambiar textos, prompts y números sin tocar el motor base.</small>
    </div>
    <a href="<?php echo ML_BASE_URL; ?>/admin/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<?php if ($mensajeExito !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($mensajeError !== ''): ?>
    <div class="alert alert-danger"><?php echo $mensajeError; ?></div>
<?php endif; ?>

<div class="config-grid mb-4">
    <div class="card p-4 mb-0">
        <h5 class="fw-bold mb-3" style="color: var(--primary);">
            <i class="bi bi-diagram-3 me-2"></i>Cómo se alimenta ahora
        </h5>
        <div class="config-meta-list">
            <div class="config-meta-item">
                <strong>Archivo editable</strong>
                <pre class="admin-code-block mt-2"><?php echo htmlspecialchars(ml_admin_runtime_storage_path(), ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
            <div class="config-meta-item">
                <strong>Consumo interno</strong>
                <div class="admin-section-note mt-2">El sistema lee esta capa paralela antes de renderizar textos o aplicar overrides numéricos. Si el archivo no existe, sigue usando los valores originales del motor.</div>
            </div>
            <div class="config-meta-item">
                <strong>Alcance</strong>
                <div class="admin-section-note mt-2 mb-0">Podés cambiar contenidos informativos, criterios del daño reputacional, prompts y parámetros estratégicos del escenario preventivo sin exponer nada al usuario final.</div>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-0">
        <h5 class="fw-bold mb-3" style="color: var(--primary);">
            <i class="bi bi-shield-lock me-2"></i>Qué queda intacto
        </h5>
        <p class="admin-section-note">No reemplaza el flujo actual ni cambia la estructura del wizard. Solo suma una capa administrable que el backend consulta en paralelo.</p>
        <ul class="mb-0" style="color:#475569; font-size:13px; line-height:1.6;">
            <li>Si quitás un override, el sistema vuelve al valor por defecto.</li>
            <li>Los cambios impactan en resultados, PDF y textos explicativos conectados a esta capa.</li>
            <li>La edición avanzada JSON permite abrir más parámetros del motor cuando haga falta.</li>
        </ul>
    </div>
</div>

<form method="POST" class="card p-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(ml_admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="fw-bold mb-0" style="color: var(--primary);">
            <i class="bi bi-gear-wide-connected me-2"></i>Configuración editable
        </h5>
        <button type="submit" name="guardar_backend_editable" value="1" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Guardar backend
        </button>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <h6 class="fw-bold">Escenario preventivo</h6>
            <p class="admin-section-note">Se usa para diferenciar visualmente el escenario de regularización del resto de las estrategias.</p>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold">Color destacado</label>
            <input type="color" name="escenario_preventivo_color" class="form-control form-control-color" value="<?php echo htmlspecialchars((string) ($preventivoUi['accent_color'] ?? '#0f766e'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-9">
            <label class="form-label fw-semibold">Badge / título corto</label>
            <input type="text" name="escenario_preventivo_badge" class="form-control" value="<?php echo htmlspecialchars((string) ($preventivoUi['badge_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Aclaración visible</label>
            <textarea name="escenario_preventivo_clarificacion" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($preventivoUi['clarification'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Lectura económica (empleador)</label>
            <textarea name="escenario_preventivo_lectura_empleador" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($preventivoUi['economic_reading_empleador'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Lectura económica (otros casos)</label>
            <textarea name="escenario_preventivo_lectura_general" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($preventivoUi['economic_reading_general'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="col-12 pt-2">
            <hr>
            <h6 class="fw-bold">Daño complementario y criterio reputacional</h6>
            <p class="admin-section-note">Acá queda explícito de dónde sale el rubro reputacional y bajo qué condiciones se calcula.</p>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Introducción del bloque de daño complementario</label>
            <textarea name="dano_intro" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($danoUi['intro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Texto explicativo del criterio reputacional</label>
            <textarea name="reputacional_criterio" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($danoUi['reputacional_criterio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-4">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="reputacional_enabled" id="reputacional_enabled" <?php echo !empty($reputacional['enabled']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="reputacional_enabled">Habilitar rubro reputacional</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="reputacional_requires_violence" id="reputacional_requires_violence" <?php echo !empty($reputacional['requires_violence']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="reputacional_requires_violence">Exigir violencia / discriminación</label>
            </div>
        </div>
        <div class="col-md-4">
            <span class="admin-form-help">Si no se cumplen estas condiciones, el monto reputacional pasa a 0 automáticamente.</span>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Tipos de extinción habilitados</label>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($tiposExtincion as $tipo => $label): ?>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="reputacional_allowed_types[]"
                            id="tipo_<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>"
                            value="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo in_array($tipo, $reputacional['allowed_types'] ?? [], true) ? 'checked' : ''; ?>
                        >
                        <label class="form-check-label" for="tipo_<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ($tiposExtincion as $tipo => $label): ?>
            <?php
            $fieldName = $tipo === 'suspensión' ? 'suspension' : $tipo;
            $currentPct = $reputacional['percentages'][$tipo] ?? 0;
            ?>
            <div class="col-md-3">
                <label class="form-label fw-semibold"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="1"
                    name="reputacional_pct_<?php echo htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); ?>"
                    class="form-control"
                    value="<?php echo htmlspecialchars((string) $currentPct, ENT_QUOTES, 'UTF-8'); ?>"
                >
                <span class="admin-form-help">Ingresar en formato decimal. Ej.: 0.15 = 15%</span>
            </div>
        <?php endforeach; ?>

        <div class="col-12 pt-2">
            <hr>
            <h6 class="fw-bold">Números estratégicos del escenario preventivo</h6>
            <p class="admin-section-note">Estos tres valores se inyectan como override sobre <code>config/parametros_motor.php</code>.</p>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Factor costo regularización</label>
            <input type="number" step="0.01" min="0" name="factor_costo_regularizacion" class="form-control" value="<?php echo htmlspecialchars((string) ($parametrosPreventivo['factor_costo_regularizacion'] ?? 1.5), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Meses ahorro litigio</label>
            <input type="number" step="1" min="0" name="meses_ahorro_litigio" class="form-control" value="<?php echo htmlspecialchars((string) ($parametrosPreventivo['meses_ahorro_litigio'] ?? 12), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Duración promedio</label>
            <input type="number" step="1" min="1" name="duracion_promedio" class="form-control" value="<?php echo htmlspecialchars((string) ($parametrosPreventivo['duracion_promedio'] ?? 2), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 pt-2">
            <hr>
            <h6 class="fw-bold">Prompts cargados en el backend</h6>
            <p class="admin-section-note">Quedan listos para reutilizarse en generación de información futura aunque después se integre por edición de código.</p>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Prompt resumen informativo</label>
            <textarea name="prompt_resumen_informativo" class="form-control" rows="6"><?php echo htmlspecialchars((string) ($prompts['resumen_informativo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Prompt análisis preventivo</label>
            <textarea name="prompt_analisis_preventivo" class="form-control" rows="6"><?php echo htmlspecialchars((string) ($prompts['analisis_preventivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Prompt actualización de contenido</label>
            <textarea name="prompt_actualizacion_contenido" class="form-control" rows="6"><?php echo htmlspecialchars((string) ($prompts['actualizacion_contenido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="col-12 pt-2">
            <hr>
            <h6 class="fw-bold">Edición avanzada</h6>
            <p class="admin-section-note">JSON opcional para abrir más overrides del motor sin tocar las funciones base. Se fusiona con los valores estratégicos del escenario preventivo cargados arriba.</p>
            <textarea name="parametros_motor_overrides_json" class="form-control admin-code-block" rows="10"><?php echo htmlspecialchars(json_encode($parametrosOverrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../footer.php'; ?>
