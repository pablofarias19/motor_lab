<?php
/**
 * index.php — Formulario wizard público del Motor de Riesgo Laboral
 *
 * Presenta el formulario de 5 pasos al usuario (empleado o empleador).
 * No requiere login. Acceso libre.
 *
 * Pasos del wizard:
 *   1. Perfil:           tipo de usuario + tipo de conflicto
 *   2. Datos laborales:  salario, antigüedad, provincia, categoría, CCT
 *   3. Documentación:    telegramas, recibos, ARCA, testigos, contrato
 *   4. Situación actual: urgencia, intercambio epistolar, plazos
 *   5. Contacto:         email obligatorio para recibir informe
 *
 * El formulario envía JSON al endpoint api/procesar_analisis.php
 * y redirige a resultado.php?uuid=XXX
 */

require_once __DIR__ . '/config/config.php';

// Provincias argentinas (para el selector del paso 2)
$provincias = [
    'CABA',
    'Buenos Aires',
    'Catamarca',
    'Chaco',
    'Chubut',
    'Córdoba',
    'Corrientes',
    'Entre Ríos',
    'Formosa',
    'Jujuy',
    'La Pampa',
    'La Rioja',
    'Mendoza',
    'Misiones',
    'Neuquén',
    'Río Negro',
    'Salta',
    'San Juan',
    'San Luis',
    'Santa Cruz',
    'Santa Fe',
    'Santiago del Estero',
    'Tierra del Fuego',
    'Tucumán',
    'Internacional'
];

$shareTitle = 'Motor de Riesgo Laboral | Estudio Farias Ortiz';
$shareDescription = 'Diagnóstico laboral profesional en 5 pasos para medir exposición económica, nivel de riesgo y escenarios de acción.';
$shareUrl = ml_absolute_url();
$shareImage = ml_absolute_url('assets/img/social-share-preview.png');
$shareImageAlt = 'Vista previa profesional del Motor de Riesgo Laboral de Estudio Farias Ortiz.';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($shareDescription, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#0B1628">
    <meta property="og:locale" content="es_AR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Estudio Farias Ortiz">
    <meta property="og:title" content="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($shareDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($shareImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= htmlspecialchars($shareImageAlt, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($shareDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($shareImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($shareImageAlt, ENT_QUOTES, 'UTF-8') ?>">

    <!-- CSS del módulo -->
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor-ui-mejorado.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(ml_asset('css/motor-unified.css')) ?>">

    <!-- Bootstrap Icons (mismo CDN que el resto del proyecto) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="motor-wrapper motor-shell">

    <!-- ═══════════════════════════════════════════════════════════
     ENCABEZADO
═══════════════════════════════════════════════════════════ -->
    <header class="motor-header">
        <div class="motor-header-inner">
            <div class="motor-logo">
                <a href="https://fariasortiz.com.ar" target="_blank" class="logo-link">
                    <img src="<?= htmlspecialchars(ml_logo_src()) ?>" alt="Estudio Farias Ortiz" class="logo-img">
                    <div class="logo-divider"></div>
                    <span class="motor-logo-modulo">Motor de Riesgo Laboral</span>
                </a>
            </div>
            <div class="motor-header-badge">
                <i class="bi bi-shield-check"></i> Análisis Estratégico
            </div>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════
     CONTENEDOR PRINCIPAL
═══════════════════════════════════════════════════════════ -->
    <main class="motor-main">
        <div class="motor-container">

            <!-- Intro -->
             <div class="motor-intro">
                 <span class="motor-intro-kicker">Diagnóstico inicial en 5 pasos</span>
                 <h1 class="motor-titulo">
                     <i class="bi bi-graph-up-arrow"></i>
                     Panorama claro de tu conflicto laboral
                </h1>
                <p class="motor-subtitulo">
                    Respondé lo esencial y te devolvemos una vista simple de tu caso:
                    <strong>exposición económica, nivel IRIL y escenarios posibles</strong>.
                </p>
                <div class="motor-intro-highlights" aria-label="Resumen del análisis">
                    <div class="motor-highlight-pill"><i class="bi bi-cash-coin"></i><span>Exposición económica</span></div>
                    <div class="motor-highlight-pill"><i class="bi bi-speedometer2"></i><span>Nivel de riesgo</span></div>
                    <div class="motor-highlight-pill"><i class="bi bi-signpost-split"></i><span>Escenarios de acción</span></div>
                </div>
                <!-- Aviso legal visible desde el inicio -->
                 <div class="motor-aviso-legal">
                     <i class="bi bi-info-circle"></i>
                     <span>Guía preventiva: no reemplaza asesoramiento legal profesional.</span>
                 </div>
                 <div class="motor-trust-grid" aria-label="Beneficios del sistema">
                     <div class="motor-trust-item">
                         <strong>Proceso guiado</strong>
                         <span>5 pasos claros para ordenar el caso sin fricción.</span>
                     </div>
                     <div class="motor-trust-item">
                         <strong>Lectura ejecutiva</strong>
                         <span>IRIL, exposición y escenarios en una sola vista.</span>
                     </div>
                     <div class="motor-trust-item">
                         <strong>Uso profesional</strong>
                         <span>Sirve tanto para trabajador como para empresa.</span>
                     </div>
                 </div>
                 <div class="motor-visual-board" aria-label="Mapa visual del análisis laboral">
                        <div class="motor-visual-scene">
                            <button type="button" class="motor-visual-card motor-quick-access"
                                data-quick-profile="empleador"
                                data-quick-conflict="auditoria_preventiva"
                                data-quick-step="2"
                                aria-label="Acceso rápido a empresa y auditoría preventiva">
                                <img src="<?= htmlspecialchars(ml_asset('img/icons/empresa.svg')) ?>" alt="" aria-hidden="true" class="motor-visual-icon">
                                <strong>Empresa</strong>
                                <span>Riesgo operativo, auditoría y contratistas.</span>
                                <small>Acceso rápido · auditoría preventiva</small>
                            </button>
                            <button type="button" class="motor-visual-card motor-quick-access"
                                data-quick-profile="empleado"
                                data-quick-conflict="despido_sin_causa"
                                data-quick-step="2"
                                aria-label="Acceso rápido a empleado y despido">
                                <img src="<?= htmlspecialchars(ml_asset('img/icons/despido.svg')) ?>" alt="" aria-hidden="true" class="motor-visual-icon">
                                <strong>Despido</strong>
                                <span>Indemnización, preaviso y escenarios.</span>
                                <small>Acceso rápido · empleado · despido</small>
                            </button>
                            <button type="button" class="motor-visual-card motor-quick-access"
                                data-quick-profile="empleado"
                                data-quick-conflict="accidente_laboral"
                                data-quick-step="2"
                                aria-label="Acceso rápido a empleado y accidente ART">
                                <img src="<?= htmlspecialchars(ml_asset('img/icons/accidente.svg')) ?>" alt="" aria-hidden="true" class="motor-visual-icon">
                                <strong>ART / Accidente</strong>
                                <span>Contingencia, cobertura y exposición civil.</span>
                                <small>Acceso rápido · empleado · ART</small>
                            </button>
                            <button type="button" class="motor-visual-card motor-quick-access"
                                data-quick-profile="empleador"
                                data-quick-conflict="riesgo_inspeccion"
                                data-quick-step="2"
                                aria-label="Acceso rápido a empresa e inspección ARCA">
                                <img src="<?= htmlspecialchars(ml_asset('img/icons/arca.svg')) ?>" alt="" aria-hidden="true" class="motor-visual-icon">
                                <strong>ARCA / Inspección</strong>
                                <span>Registro, fiscalización y documentación.</span>
                                <small>Acceso rápido · empresa · inspección</small>
                            </button>
                        </div>
                     <div class="motor-journey-strip" aria-label="Recorrido del wizard">
                         <div class="motor-journey-step">
                             <span>1</span>
                             <strong>Perfil</strong>
                         </div>
                         <div class="motor-journey-step">
                             <span>2</span>
                             <strong>Datos</strong>
                         </div>
                         <div class="motor-journey-step">
                             <span>3</span>
                             <strong>Docs</strong>
                         </div>
                         <div class="motor-journey-step">
                             <span>4</span>
                             <strong>Situación</strong>
                         </div>
                         <div class="motor-journey-step">
                             <span>5</span>
                              <strong>Contacto</strong>
                         </div>
                     </div>
                 </div>
              </div>

            <!-- ─────────────────────────────────────────────────
             WIZARD PRINCIPAL
        ───────────────────────────────────────────────── -->
            <div class="wizard-container" id="form-motor-laboral">

                <!-- Indicadores de progreso -->
                <div class="wizard-steps" id="wizard-steps">
                    <div class="wizard-step-item activo" data-paso="1">
                        <div class="paso-indicator">1</div>
                        <span class="paso-label">Perfil</span>
                    </div>
                    <div class="wizard-step-item" data-paso="2">
                        <div class="paso-indicator">2</div>
                        <span class="paso-label">Datos</span>
                    </div>
                    <div class="wizard-step-item" data-paso="3">
                        <div class="paso-indicator">3</div>
                        <span class="paso-label">Documentación</span>
                    </div>
                    <div class="wizard-step-item" data-paso="4">
                        <div class="paso-indicator">4</div>
                        <span class="paso-label">Situación</span>
                    </div>
                    <div class="wizard-step-item" data-paso="5">
                        <div class="paso-indicator">5</div>
                        <span class="paso-label">Contacto</span>
                    </div>
                    <div class="progreso-linea" id="progreso-linea"></div>
                </div>
                <div class="wizard-context-bar">
                    <div>
                        <span class="wizard-step-badge">
                            <i class="bi bi-compass"></i> Recorrido guiado
                        </span>
                        <p id="paso-progreso-texto">Paso 1 de 5: Perfil</p>
                    </div>
                    <div class="wizard-trust-strip">
                        <span class="wizard-trust-chip"><i class="bi bi-shield-check"></i> Datos mínimos</span>
                        <span class="wizard-trust-chip"><i class="bi bi-lightning-charge"></i> Resultado inmediato</span>
                        <span class="wizard-trust-chip"><i class="bi bi-journal-text"></i> Lectura estratégica</span>
                    </div>
                </div>
                <div class="wizard-visual-guide" id="wizard-visual-guide" aria-label="Guía visual del paso actual">
                    <div class="wizard-guide-header">
                        <div class="wizard-guide-icon" id="wizard-guide-icon" aria-hidden="true">
                            <i class="bi bi-compass"></i>
                        </div>
                        <div class="wizard-guide-copy">
                            <span class="wizard-guide-eyebrow" id="wizard-guide-eyebrow">Mapa del paso</span>
                            <h3 class="wizard-guide-title" id="wizard-guide-title">Definí el punto de partida</h3>
                            <p class="wizard-guide-description" id="wizard-guide-description">
                                Elegí perfil y conflicto para que el wizard te muestre una ruta más corta y útil.
                            </p>
                        </div>
                    </div>
                    <div class="wizard-guide-points" id="wizard-guide-points"></div>
                </div>

                <!-- ══════════════════════════════════════════
                 PASO 1 — Perfil del usuario
            ══════════════════════════════════════════ -->
                <div class="wizard-paso activo" id="paso-1">
                    <h2 class="paso-titulo">
                        <i class="bi bi-person-circle"></i> ¿Cuál es tu situación?
                    </h2>

                    <!-- Tipo de usuario -->
                    <div class="form-section">
                        <h3 class="form-section-titulo">Soy...</h3>
                        <!-- form-group necesario para que _mostrarError() del wizard pueda mostrar errores -->
                        <div class="form-group">
                            <!-- Campo oculto sincronizado con los radios — usado por wizard.js para validar #tipo_usuario -->
                            <input type="hidden" id="tipo_usuario" name="tipo_usuario" value="">

                            <div class="opcion-cards-group" id="grupo-tipo-usuario">
                                <label class="opcion-card opcion-grande" for="tipo-empleado">
                                    <input type="radio" name="tipo_usuario_radio" id="tipo-empleado" value="empleado">
                                    <div class="opcion-card-icono"><i class="bi bi-person-badge"></i></div>
                                    <div class="opcion-card-texto">
                                        <strong>Empleado / Trabajador</strong>
                                        <small>Fui despedido, tengo deudas salariales o quiero reclamar</small>
                                    </div>
                                </label>
                                <label class="opcion-card opcion-grande" for="tipo-empleador">
                                    <input type="radio" name="tipo_usuario_radio" id="tipo-empleador" value="empleador">
                                    <div class="opcion-card-icono"><i class="bi bi-building"></i></div>
                                    <div class="opcion-card-texto">
                                        <strong>Empleador / Empresa</strong>
                                        <small>Quiero prevenir riesgos o gestionar un conflicto activo</small>
                                    </div>
                                </label>
                            </div>
                            <span class="form-error" id="error-tipo_usuario"></span>
                        </div>
                    </div>

                    <!-- Tipo de conflicto -->
                    <div class="form-section">
                        <h3 class="form-section-titulo"><i class="bi bi-search"></i> Elegí el motivo principal
                        </h3>
                        <p class="form-section-desc">Ordenamos las opciones por bloques para que ubiques rápido tu caso.</p>

                        <div id="conflicto-role-helper" class="conflicto-role-helper">
                            <strong>Elegí primero tu perfil.</strong>
                            <span>Después te mostramos un mapa simple con las opciones más útiles para trabajador o empresa.</span>
                        </div>

                        <div class="form-group">
                            <!-- Campo oculto para el valor del conflicto -->
                            <input type="hidden" name="tipo_conflicto" id="tipo_conflicto" value="">

                            <div class="conflicto-groups" id="galeria-conflictos">
                                <section class="conflicto-grupo" data-seccion="desvinculacion">
                                    <div class="conflicto-grupo-head">
                                        <span class="conflicto-grupo-kicker">Salida del vínculo</span>
                                        <h4>Despidos y desvinculación</h4>
                                        <p>Cuando el eje del caso es el cese, la causa invocada o la forma de salida.</p>
                                    </div>
                                    <div class="conflictos-gallery">
                                        <div class="conflicto-card" data-valor="despido_sin_causa" data-grupo="ambos">
                                            <div class="conflicto-icon color-despido">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/despido.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Despido sin causa</strong>
                                                <span>Cese laboral sin motivo justificado.</span>
                                            </div>
                                        </div>
                                        <div class="conflicto-card" data-valor="despido_con_causa" data-grupo="ambos">
                                            <div class="conflicto-icon color-despido">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/despido.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Despido con causa</strong>
                                                <span>Notificación de cese por falta disciplinaria.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="conflicto-grupo" data-seccion="registracion">
                                    <div class="conflicto-grupo-head">
                                        <span class="conflicto-grupo-kicker">Dinero y registración</span>
                                        <h4>Sueldos, categoría o empleo no registrado</h4>
                                        <p>Para reclamos por deuda salarial, registración deficiente o diferencias.</p>
                                    </div>
                                    <div class="conflictos-gallery">
                                        <div class="conflicto-card" data-valor="trabajo_no_registrado" data-grupo="ambos">
                                            <div class="conflicto-icon color-negro">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/negro.svg')) ?>" alt="" aria-hidden="true"
                                                    class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Trabajo en negro</strong>
                                                <span>Relación laboral no registrada o irregular.</span>
                                            </div>
                                        </div>
                                        <div class="conflicto-card" data-valor="diferencias_salariales" data-grupo="ambos">
                                            <div class="conflicto-icon color-salarial">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/salarial.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Diferencias / Deudas</strong>
                                                <span>Sueldos impagos, horas extras o mala categoría.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="conflicto-grupo" data-seccion="accidentes">
                                    <div class="conflicto-grupo-head">
                                        <span class="conflicto-grupo-kicker">Daño o cobertura</span>
                                        <h4>Accidente o enfermedad laboral</h4>
                                        <p>Cuando el foco está en ART, incapacidad, secuelas o exposición civil.</p>
                                    </div>
                                    <div class="conflictos-gallery">
                                        <div class="conflicto-card" data-valor="accidente_laboral" data-grupo="ambos">
                                            <div class="conflicto-icon color-accidente">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/accidente.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Accidente / Enfermedad</strong>
                                                <span>Lesiones en el trabajo o licencias médicas.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="conflicto-grupo" data-seccion="empresa">
                                    <div class="conflicto-grupo-head">
                                        <span class="conflicto-grupo-kicker">Prevención empresarial</span>
                                        <h4>Riesgos de empresa y cumplimiento</h4>
                                        <p>Solo para empresa: contingencias por estructura, terceros o fiscalización.</p>
                                    </div>
                                    <div class="conflictos-gallery">
                                        <div class="conflicto-card" data-valor="responsabilidad_solidaria" data-grupo="empresa"
                                            style="display:none;">
                                            <div class="conflicto-icon color-empresa">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/empresa.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Responsabilidad Solidaria</strong>
                                                <span>Riesgos por contratistas o tercerizados.</span>
                                            </div>
                                        </div>
                                        <div class="conflicto-card" data-valor="auditoria_preventiva" data-grupo="empresa"
                                            style="display:none;">
                                            <div class="conflicto-icon color-empresa">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/empresa.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Auditoría Preventiva</strong>
                                                <span>Diagnóstico de cumplimiento y riesgos.</span>
                                            </div>
                                        </div>
                                        <div class="conflicto-card" data-valor="riesgo_inspeccion" data-grupo="empresa"
                                            style="display:none;">
                                            <div class="conflicto-icon color-arca">
                                                <img src="<?= htmlspecialchars(ml_asset('img/icons/arca.svg')) ?>" alt="" aria-hidden="true" class="conflicto-svg">
                                            </div>
                                            <div class="conflicto-info">
                                                <strong>Inspección ARCA/Ministerio</strong>
                                                <span>Acompañamiento ante controles oficiales.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <span class="form-error" id="error-tipo_conflicto"></span>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                 PASO 2 — Datos laborales
            ══════════════════════════════════════════ -->
                <div class="wizard-paso" id="paso-2" style="display:none;">
                    <h2 class="paso-titulo">
                        <i class="bi bi-briefcase"></i> Datos de la relación laboral
                    </h2>

                    <!-- TARJETA INFORMATIVA -->
                    <div class="paso-info-card">
                        <div class="paso-info-card-icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div class="paso-info-card-content">
                            <h4>¿Por qué pedimos esto?</h4>
                            <p>Los datos laborales son la base del cálculo. El motor usa tu salario, antigüedad y provincia para calcular montos de indemnización, multas y plazos legales.</p>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-grid-2">
                            <!-- Salario -->
                            <div class="form-group form-group-with-icon">
                                <label for="salario">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">💵</span>Mejor salario mensual bruto</span>
                                    <span class="req">*</span>
                                </label>
                                <div class="input-prefix-wrapper">
                                    <input type="text" id="salario" name="salario" placeholder="$350.000"
                                        autocomplete="off" inputmode="numeric" required
                                        data-tooltip="Salario más alto que cobraste en los últimos 12 meses">
                                    <span class="form-help-icon" data-tooltip="Incluir descuentos, son con salario BRUTO (antes de impuestos)">?</span>
                                </div>
                                <small class="form-ayuda">Es el salario más alto que cobraste en los <strong>últimos 12 meses</strong>. Incluye lo que aparece en el recibo.</small>
                                <div class="form-example" id="ej-salario">
                                    <strong><span class="ui-emoji" aria-hidden="true">📋</span>Ejemplos:</strong><br>
                                    ✓ $350.000 (debe ser sin puntos ni comas)<br>
                                    ✓ $85500 (números enteros)<br>
                                    ✗ $350.000,00 (no poner decimales)
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-salario').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">📌</span>Ver ejemplos
                                </span>
                                <span class="form-error" id="error-salario"></span>
                            </div>

                            <!-- Antigüedad -->
                            <div class="form-group form-group-with-icon">
                                <label for="antiguedad_meses">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">📅</span>¿Cuántos meses trabajaste?</span>
                                    <span class="req">*</span>
                                </label>
                                <input type="number" id="antiguedad_meses" name="antiguedad_meses" min="0" max="600"
                                    placeholder="Ej: 36" required
                                    data-tooltip="Meses totales de antigüedad">
                                <small class="form-ayuda">Convertí los años a meses. <strong>Si son 2 años y 3 meses = 27 meses</strong></small>
                                <div class="form-example" id="ej-antiguedad">
                                    <strong><span class="ui-emoji" aria-hidden="true">🧮</span>Cómo calcular:</strong><br>
                                    • 1 año = 12 meses<br>
                                    • 2 años = 24 meses<br>
                                    • 2 años 6 meses = 30 meses<br>
                                    • 3 años 3 meses = 39 meses
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-antiguedad').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">🧮</span>Calculadora
                                </span>
                                <span class="form-error" id="error-antiguedad_meses"></span>
                            </div>

                            <!-- Edad (Solo visible para Accidentes por ahora) -->
                            <div class="form-group form-group-with-icon solo-accidente" style="display:none;">
                                <label for="edad">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">🆔</span>¿Cuántos años tenías al accidentarte?</span>
                                    <span class="req">*</span>
                                </label>
                                <input type="number" id="edad" name="edad" min="16" max="90" placeholder="Ej: 45"
                                    data-tooltip="Tu edad completa en años">
                                <small class="form-ayuda">Dato necesario para calcular <strong>indemnización con incapacidad laboral</strong>. Afecta el monto final.</small>
                                <span class="form-error" id="error-edad"></span>
                            </div>

                            <!-- Tipo de registro (Solo para Despidos/Trabajo no registrado) -->
                            <div class="form-group solo-registro-irregular" style="display:none;">
                                <label for="tipo_registro">
                                    <span class="form-label-main">¿Cómo estabas registrado?</span>
                                </label>
                                <select id="tipo_registro" name="tipo_registro">
                                    <option value="registrado" selected>✓ Correctamente (en ARCA/AFIP)</option>
                                    <option value="no_registrado">✗ "En negro" (sin recibos)</option>
                                    <option value="deficiente_fecha">⚠ Fecha falsa en recibos</option>
                                    <option value="deficiente_salario">⚠ Parte del sueldo "en negro"</option>
                                </select>
                                <small class="form-ayuda">Esto <strong>impacta el cálculo</strong> de fraude laboral y daños complementarios (Ley 27.802).</small>
                                <div class="comparativa-rapida">
                                    <div class="comparativa-item si">
                                        <h5>✓ Registrado</h5>
                                        <p>Indemnización normal</p>
                                    </div>
                                    <div class="comparativa-item no">
                                        <h5>✗ En negro</h5>
                                        <p>Fraude + Daño complementario (Ley 27.802)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos condicionales de registro deficiente -->
                        <div class="form-grid-2 solo-registro-deficiente seccion-condicional" style="display:none;">
                            <h4><i class="bi bi-exclamation-triangle"></i> Detalles del registro incompleto</h4>
                            <div class="form-group">
                                <label for="salario_recibo">Sueldo que figura en el recibo ($)</label>
                                <input type="number" id="salario_recibo" name="salario_recibo" placeholder="Ej: 200000"
                                    data-tooltip="Monto declarado ante AFIP">
                                <small class="form-ayuda">Si cobrás <strong>más de lo que dice el papel</strong>, la diferencia es "en negro".</small>
                            </div>
                            <div class="form-group">
                                <label for="antiguedad_recibo">Meses según el recibo (diferente)</label>
                                <input type="number" id="antiguedad_recibo" name="antiguedad_recibo" min="0"
                                    placeholder="Meses en registro"
                                    data-tooltip="Antigüedad registrada, si es menor que la real">
                                <small class="form-ayuda">Si te registraron mucho después de que empezaste a trabajar.</small>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <!-- Provincia -->
                            <div class="form-group form-group-with-icon">
                                <label for="provincia">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">🗺️</span>¿En qué provincia trabajabas?</span>
                                    <span class="req">*</span>
                                </label>
                                <select id="provincia" name="provincia" required
                                    data-tooltip="Define qué leyes laborales aplican">
                                    <option value="">-- Seleccioná provincia --</option>
                                    <?php foreach ($provincias as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-ayuda">Donde trabajabas (no donde vivas). Afecta <strong>tasas de interés y plazos</strong>.</small>
                                <span class="form-error" id="error-provincia"></span>
                            </div>

                            <!-- Cantidad empleados -->
                            <div class="form-group form-group-with-icon">
                                    <label for="cantidad_empleados"><span class="ui-emoji" aria-hidden="true">👥</span>¿Cuántas personas trabajaban en la empresa?</label>
                                <select id="cantidad_empleados" name="cantidad_empleados"
                                    data-tooltip="Afecta procedimientos laborales">
                                    <option value="1">1 (Solo yo)</option>
                                    <option value="3">2 a 5 personas</option>
                                    <option value="7">6 a 10 personas</option>
                                    <option value="25">11 a 50 personas</option>
                                    <option value="100">Más de 50 personas</option>
                                </select>
                                <small class="form-ayuda">Importante para <strong>triplicación de indemnización</strong> (empresas grandes tienen límites diferentes).</small>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <!-- Categoría -->
                            <div class="form-group form-group-with-icon">
                                <label for="categoria">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">💼</span>¿Cuál era tu cargo/función?</span>
                                    <span class="opcional-badge">Opcional</span>
                                </label>
                                <input type="text" id="categoria" name="categoria"
                                    placeholder="Ej: Operario, Vendedor, Capataz, Encargado"
                                    data-tooltip="Tu puesto de trabajo">
                                <small class="form-ayuda">Ayuda a contextualizar tu caso. Escribí lo que figura en tu contrato o recibos.</small>
                                <div class="form-example" id="ej-categoria">
                                    <strong><span class="ui-emoji" aria-hidden="true">📝</span>Ejemplos:</strong><br>
                                    • Operario calificado<br>
                                    • Vendedor<br>
                                    • Capataz de obra<br>
                                    • Encargado de almacén<br>
                                    • Empleado administrativo
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-categoria').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">📋</span>Ver ejemplos
                                </span>
                            </div>

                            <!-- CCT -->
                            <div class="form-group form-group-with-icon">
                                <label for="cct">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">📜</span>¿Qué convenio aplica? (Opcional)</span>
                                    <span class="opcional-badge">Opcional</span>
                                </label>
                                <input type="text" id="cct" name="cct" 
                                    placeholder="Ej: UOCRA, UOM, Comercio, Construcción"
                                    data-tooltip="Convenio Colectivo de Trabajo">
                                <small class="form-ayuda">Si no sabés, <strong>no importa</strong> — dejá vacío. El motor puede funcionar sin esto.</small>
                                <div class="form-example" id="ej-cct">
                                    <strong><span class="ui-emoji" aria-hidden="true">🔍</span>Convenios comunes:</strong><br>
                                    • UOCRA (Construcción)<br>
                                    • UOM (Metalurgía)<br>
                                    • Comercio 130/75<br>
                                    • Textiles<br>
                                    • Si no sabés, dejá en blanco
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-cct').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">🔍</span>Ver convenios
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                 PASO 3 — Documentación disponible
            ══════════════════════════════════════════ -->
                <div class="wizard-paso" id="paso-3" style="display:none;">
                    <h2 class="paso-titulo">
                        <i class="bi bi-folder2-open"></i> Documentación disponible
                    </h2>
                    <p class="paso-descripcion">
                        La documentación disponible es clave para evaluar el riesgo probatorio.
                        Respondé con honestidad — esto impacta directamente en el IRIL.
                    </p>

                    <div class="form-section">
                        <!-- Recibos de sueldo -->
                        <div class="form-group form-group-si-no">
                            <label>¿Tenés recibos de sueldo firmados? <span class="req">*</span></label>
                            <div class="opcion-si-no" id="grupo-tiene_recibos">
                                <label class="opcion-pill" for="rec-si">
                                    <input type="radio" name="tiene_recibos" id="rec-si" value="si" required>
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="rec-no">
                                    <input type="radio" name="tiene_recibos" id="rec-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="form-error" id="error-tiene_recibos"></span>
                        </div>

                        <!-- Contrato -->
                        <div class="form-group form-group-si-no">
                            <label>¿Existe contrato de trabajo escrito? <span class="req">*</span></label>
                            <div class="opcion-si-no" id="grupo-tiene_contrato">
                                <label class="opcion-pill" for="con-si">
                                    <input type="radio" name="tiene_contrato" id="con-si" value="si" required>
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="con-no">
                                    <input type="radio" name="tiene_contrato" id="con-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="form-error" id="error-tiene_contrato"></span>
                        </div>

                        <!-- ARCA -->
                        <div class="form-group form-group-si-no">
                            <label>¿La relación laboral está registrada en ARCA? <span class="req">*</span></label>
                            <div class="opcion-si-no" id="grupo-registrado_afip">
                                <label class="opcion-pill" for="afip-si">
                                    <input type="radio" name="registrado_afip" id="afip-si" value="si" required>
                                    <span>Sí (en blanco)</span>
                                </label>
                                <label class="opcion-pill" for="afip-no">
                                    <input type="radio" name="registrado_afip" id="afip-no" value="no">
                                    <span>No (en negro)</span>
                                </label>
                            </div>
                            <span class="form-error" id="error-registrado_afip"></span>
                        </div>

                        <!-- Testigos -->
                        <div class="form-group form-group-si-no">
                            <label>¿Contás con testigos disponibles?</label>
                            <div class="opcion-si-no" id="grupo-tiene_testigos">
                                <label class="opcion-pill" for="test-si">
                                    <input type="radio" name="tiene_testigos" id="test-si" value="si">
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="test-no">
                                    <input type="radio" name="tiene_testigos" id="test-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                        </div>

                        <!-- Auditoría previa (empleadores) -->
                        <div class="form-group form-group-si-no solo-empleador" id="grupo-auditoria"
                            style="display:none;">
                            <label>¿Se realizó auditoría laboral preventiva en los últimos 2 años?</label>
                            <div class="opcion-si-no">
                                <label class="opcion-pill" for="aud-si">
                                    <input type="radio" name="auditoria_previa" id="aud-si" value="si">
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="aud-no">
                                    <input type="radio" name="auditoria_previa" id="aud-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                        </div>

                        <!-- Checklist MTEySS / SRT (Auditorías preventivas o Inspecciones) -->
                        <div class="form-group solo-auditoria" style="display:none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <h4 style="font-size: 0.95rem; color: var(--primary); margin-bottom: 1rem;">
                                <i class="bi bi-clipboard-check"></i> Checklist MTEySS / ARCA (Registral)
                            </h4>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Trabajadores con Alta Temprana (SIPA)?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="alta-si"><input type="radio" name="chk_alta_sipa" id="alta-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="alta-no"><input type="radio" name="chk_alta_sipa" id="alta-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Libro de Sueldos (Art. 52 LCT) al día?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="l52-si"><input type="radio" name="chk_libro_art52" id="l52-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="l52-no"><input type="radio" name="chk_libro_art52" id="l52-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Recibos reflejan remuneración real s/CCT?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="recibo-cct-si"><input type="radio" name="chk_recibos_cct" id="recibo-cct-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="recibo-cct-no"><input type="radio" name="chk_recibos_cct" id="recibo-cct-no" value="no"><span>No</span></label>
                                </div>
                            </div>

                            <h4 style="font-size: 0.95rem; color: var(--primary); margin: 1.5rem 0 1rem;">
                                <i class="bi bi-shield-check"></i> Checklist SRT (Higiene y Seguridad)
                            </h4>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Contrato de ART vigente y sin deuda?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="chk-art-si"><input type="radio" name="chk_art_vigente" id="chk-art-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="chk-art-no"><input type="radio" name="chk_art_vigente" id="chk-art-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Exámenes médicos pre/periódicos (Res 886/15)?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="chk-examen-si"><input type="radio" name="chk_examenes" id="chk-examen-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="chk-examen-no"><input type="radio" name="chk_examenes" id="chk-examen-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            <div class="form-group-si-no">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Entrega de EPP y cumplimiento RGRL?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="chk-epp-si"><input type="radio" name="chk_epp_rgrl" id="chk-epp-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="chk-epp-no"><input type="radio" name="chk_epp_rgrl" id="chk-epp-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            
                            <h4 style="font-size: 0.95rem; color: #16a085; margin: 1.5rem 0 1rem;">
                                <i class="bi bi-calculator"></i> Simulador Económico: Regularizar vs Litigar
                            </h4>
                            <div class="form-group" style="margin-bottom: 0.8rem;">
                                <label for="meses_no_registrados">Meses pendientes de regularización (no registrados o deficientes)</label>
                                <input type="number" id="meses_no_registrados" name="meses_no_registrados" min="0" max="120" placeholder="Ej: 24">
                            </div>
                            <div class="form-group" style="margin-bottom: 0.8rem;">
                                <label for="meses_en_mora">Antigüedad promedio de la deuda (meses en mora de aportes)</label>
                                <input type="number" id="meses_en_mora" name="meses_en_mora" min="0" max="120" placeholder="Ej: 12">
                            </div>
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label>¿Adherirá a régimen de blanqueo laboral (Ej: Ley Bases)?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="blanco-si"><input type="radio" name="aplica_blanco_laboral" id="blanco-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="blanco-no"><input type="radio" name="aplica_blanco_laboral" id="blanco-no" value="no" checked><span>No</span></label>
                                </div>
                                <small class="form-ayuda">Aplica condonación de multas y reducción de intereses de AFIP.</small>
                            </div>
                            <div class="form-group">
                                <label for="probabilidad_condena">Estimación de Probabilidad de Condena (Litigio)</label>
                                <select id="probabilidad_condena" name="probabilidad_condena">
                                    <option value="0.2">Baja (20%) - Relación encubierta débil</option>
                                    <option value="0.5" selected>Media (50%) - Zona de riesgo gris</option>
                                    <option value="0.8">Alta (80%) - Trabajo en negro evidente</option>
                                    <option value="0.95">Inminente (95%) - Con inspección y actas</option>
                                </select>
                            </div>
                        </div>

                        <!-- Panel de Responsabilidad Solidaria (Art. 30 LCT) -->
                        <div class="form-group solo-solidaridad" style="display:none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <h4 style="font-size: 0.95rem; color: var(--primary); margin-bottom: 1rem;">
                                <i class="bi bi-diagram-3"></i> Cuestionario de Responsabilidad Solidaria (Art. 30 LCT)
                            </h4>
                            <p style="font-size: 0.85rem; color: #555; margin-bottom: 1rem;">Este test predice el nivel de exposición de su empresa frente a las demandas de empleados de empresas contratistas, de acuerdo a la jurisprudencia actual.</p>

                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿La tarea tercerizada forma parte de la <strong>actividad normal y específica propia</strong> de su empresa?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-act-si"><input type="radio" name="actividad_esencial" id="sol-act-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="sol-act-no"><input type="radio" name="actividad_esencial" id="sol-act-no" value="no"><span>No</span></label>
                                </div>
                            </div>
                            
                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿La empresa controla periódicamente el alta temprana, F931 y ART del contratista?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-doc-si"><input type="radio" name="control_documental" id="sol-doc-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="sol-doc-no"><input type="radio" name="control_documental" id="sol-doc-no" value="no"><span>No</span></label>
                                </div>
                            </div>

                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿La empresa ejerce <strong>control directo sobre el trabajo</strong> (dar horarios, órdenes, supervisión directa)?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-ope-si"><input type="radio" name="control_operativo" id="sol-ope-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="sol-ope-no"><input type="radio" name="control_operativo" id="sol-ope-no" value="no"><span>No</span></label>
                                </div>
                            </div>

                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿El trabajador tercerizado utiliza <strong>instalaciones, herramientas o vehículos</strong> de su empresa?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-int-si"><input type="radio" name="integracion_estructura" id="sol-int-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="sol-int-no"><input type="radio" name="integracion_estructura" id="sol-int-no" value="no"><span>No</span></label>
                                </div>
                            </div>

                            <div class="form-group-si-no" style="margin-bottom: 0.8rem;">
                                <label style="font-weight:normal; font-size: 0.9rem;">¿Existe un <strong>contrato formal comercial</strong> con cláusulas de cumplimiento laboral y auditoría?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-con-si"><input type="radio" name="contrato_formal" id="sol-con-si" value="si"><span>Sí</span></label>
                                    <label class="opcion-pill" for="sol-con-no"><input type="radio" name="contrato_formal" id="sol-con-no" value="no"><span>No</span></label>
                                </div>
                            </div>

                            <div class="form-group-si-no" style="margin-bottom: 0.8rem; background: #fff5f5; padding: 10px; border-left: 4px solid #e74c3c;">
                                <label style="font-weight:normal; font-size: 0.9rem; color: #c0392b;"><strong>Alerta de Incumplimiento Grave:</strong> ¿Ha verificado formalmente la ausencia de presentación del Formulario 931 o póliza de ART por parte del contratista?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="sol-falt-si"><input type="radio" name="falta_f931_art" id="sol-falt-si" value="si"><span>Sí, faltan</span></label>
                                    <label class="opcion-pill" for="sol-falt-no"><input type="radio" name="falta_f931_art" id="sol-falt-no" value="no" checked><span>No (Todo en regla o se desconoce)</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                 PASO 4 — Situación actual
            ══════════════════════════════════════════ -->
                <div class="wizard-paso" id="paso-4" style="display:none;">
                    <h2 class="paso-titulo">
                        <i class="bi bi-clock-history"></i> Situación actual
                    </h2>

                    <div class="form-section">
                        <!-- Intercambio telegráfico -->
                        <div class="form-group form-group-si-no">
                            <label>¿Ya hubo intercambio de telegramas entre las partes? <span
                                    class="req">*</span></label>
                            <div class="opcion-si-no" id="grupo-hay_intercambio">
                                <label class="opcion-pill" for="int-si">
                                    <input type="radio" name="hay_intercambio" id="int-si" value="si" required>
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="int-no">
                                    <input type="radio" name="hay_intercambio" id="int-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="form-error" id="error-hay_intercambio"></span>
                        </div>

                        <!-- Fecha del último telegrama (condicional) -->
                        <div class="form-group" id="campo-fecha-telegrama" style="display:none;">
                            <label for="fecha_ultimo_telegrama">Fecha del último telegrama recibido</label>
                            <input type="date" id="fecha_ultimo_telegrama" name="fecha_ultimo_telegrama"
                                max="<?= date('Y-m-d') ?>">
                            <small class="form-ayuda">Importante para detectar plazos de respuesta urgentes (48/72
                                hs)</small>
                        </div>

                        <!-- Fue intimado (empleadores) -->
                        <div class="form-group form-group-si-no solo-empleador" id="grupo-fue_intimado"
                            style="display:none;">
                            <label>¿La empresa ya fue intimada formalmente por el empleado?</label>
                            <div class="opcion-si-no">
                                <label class="opcion-pill" for="intim-si">
                                    <input type="radio" name="fue_intimado" id="intim-si" value="si">
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="intim-no">
                                    <input type="radio" name="fue_intimado" id="intim-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                        </div>

                        <!-- Ya despedido / desvinculado (Ambos perfiles, Ocultar si es accidente) -->
                        <div class="form-group form-group-si-no no-accidente" id="grupo-ya_despedido"
                            style="display:none;">
                            <label>¿Ya se produjo el despido o notificación formal? <span class="req">*</span></label>
                            <div class="opcion-si-no" id="grupo-ya_despedido-opts">
                                <label class="opcion-pill" for="desp-si">
                                    <input type="radio" name="ya_despedido" id="desp-si" value="si">
                                    <span>Sí</span>
                                </label>
                                <label class="opcion-pill" for="desp-no">
                                    <input type="radio" name="ya_despedido" id="desp-no" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="form-error" id="error-ya_despedido"></span>
                        </div>

                        <!-- Fecha de despido (condicional / ocultar si es accidente) -->
                        <div class="form-group no-accidente" id="campo-fecha-despido" style="display:none;">
                            <label for="fecha_despido">Fecha de notificación del despido</label>
                            <input type="date" id="fecha_despido" name="fecha_despido" max="<?= date('Y-m-d') ?>">
                            <small class="form-ayuda">Necesario para calcular prescripción (2 años — Art. 256
                                LCT)</small>
                        </div>

                        <!-- DETALLES ESPECÍFICOS DE ACCIDENTE / ENFERMEDAD LABORAL -->
                        <div class="form-section solo-accidente"
                            style="display:none; border-top: 1px solid #eee; padding-top: 1rem;">
                            <h4 style="font-size: 0.9rem; color: var(--primary); margin-bottom: 1rem;">Datos del
                                Siniestro</h4>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="tipo_contingencia">Tipo de contingencia <span class="req">*</span></label>
                                    <select id="tipo_contingencia" name="tipo_contingencia">
                                        <option value="accidente_tipico">Accidente de trabajo (típico)</option>
                                        <option value="in_itinere">Accidente in itinere (trayecto)</option>
                                        <option value="enfermedad_profesional">Enfermedad profesional</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_siniestro">Fecha del siniestro</label>
                                    <input type="date" id="fecha_siniestro" name="fecha_siniestro"
                                        max="<?= date('Y-m-d') ?>">
                                    <small class="form-ayuda">Importante para calcular prescripción (2 años desde PMI)</small>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="porcentaje_incapacidad">Incapacidad estimada o dictaminada (%)</label>
                                    <input type="number" id="porcentaje_incapacidad" name="porcentaje_incapacidad"
                                        min="0" max="100" step="0.5" placeholder="Ej: 15">
                                    <small class="form-ayuda">Si tenés dictamen de CM o pericia, poné el % exacto</small>
                                </div>
                                <div class="form-group">
                                    <label for="incapacidad_tipo">Tipo de incapacidad</label>
                                    <select id="incapacidad_tipo" name="incapacidad_tipo">
                                        <option value="transitoria">Incapacidad Laboral Transitoria (ILT)</option>
                                        <option value="permanente_provisoria">Permanente Provisoria</option>
                                        <option value="permanente_definitiva" selected>Permanente Definitiva</option>
                                        <option value="gran_invalidez">Gran Invalidez (+66%)</option>
                                        <option value="muerte">Muerte del trabajador</option>
                                    </select>
                                </div>
                            </div>

                            <h4 style="font-size: 0.9rem; color: var(--primary); margin: 1rem 0 0.5rem;">Estado ante la ART</h4>

                            <div class="form-group form-group-si-no solo-empleado">
                                <label>¿Tiene cobertura de ART activa? <span class="req">*</span></label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="art-si">
                                        <input type="radio" name="tiene_art" id="art-si" value="si">
                                        <span>Sí</span>
                                    </label>
                                    <label class="opcion-pill" for="art-no">
                                        <input type="radio" name="tiene_art" id="art-no" value="no">
                                        <span>No / Desconozco</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Módulo Empresarial: Estado ART y Vía Civil (Solo Empleador) -->
                            <div class="solo-empleador form-section" style="display:none; background: #fffcf5; border-left: 4px solid #f39c12; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                                <h4 style="font-size: 0.9rem; color: #d35400; margin-bottom: 1rem;"><i class="bi bi-shield-exclamation"></i> Calificación de Cumplimiento ART y Riesgo Civil</h4>
                                
                                <div class="form-group">
                                    <label for="estado_art_empresa">Estado real de cobertura ART</label>
                                    <select id="estado_art_empresa" name="estado_art">
                                        <option value="activa_valida" selected>Activa y Válida (Registrado y al día)</option>
                                        <option value="activa_incumplida">Activa pero Incumplida (Falta registrar empleado, etc.)</option>
                                        <option value="inexistente">Inexistente (Sin contrato ART o en negro absoluto)</option>
                                    </select>
                                    <small class="form-ayuda">Define si la ART responderá, si habrá repetición, o si la empresa asume el 100%.</small>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group form-group-si-no">
                                        <label>¿Hubo Culpa Grave del Empleador?</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="culpa-si"><input type="radio" name="culpa_grave" id="culpa-si" value="si"><span>Sí</span></label>
                                            <label class="opcion-pill" for="culpa-no"><input type="radio" name="culpa_grave" id="culpa-no" value="no" checked><span>No</span></label>
                                        </div>
                                        <small class="form-ayuda">Falta de higiene/seguridad, negligencia comprobable.</small>
                                    </div>
                                    <div class="form-group form-group-si-no">
                                        <label>¿Existe reclamo por Vía Civil (Daño Moral/Psicológico)?</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="civil-si"><input type="radio" name="via_civil" id="civil-si" value="si"><span>Sí</span></label>
                                            <label class="opcion-pill" for="civil-no"><input type="radio" name="via_civil" id="civil-no" value="no" checked><span>No</span></label>
                                        </div>
                                        <small class="form-ayuda">Acción civil integral fuera de la tarifa de la LRT.</small>
                                    </div>
                                </div>
                            </div>


                            <!-- Campos adicionales ART (solo visibles si tiene_art = si) -->
                            <div class="solo-tiene-art" style="display:none;">
                                <div class="form-group form-group-si-no">
                                    <label>¿Denunció el siniestro ante la ART?</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="denuncia-art-si">
                                            <input type="radio" name="denuncia_art" id="denuncia-art-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="denuncia-art-no">
                                            <input type="radio" name="denuncia_art" id="denuncia-art-no" value="no">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group form-group-si-no">
                                    <label>¿La ART rechazó el siniestro?</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="rechazo-art-si">
                                            <input type="radio" name="rechazo_art" id="rechazo-art-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="rechazo-art-no">
                                            <input type="radio" name="rechazo_art" id="rechazo-art-no" value="no">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>

                                <h4 style="font-size: 0.9rem; color: var(--primary); margin: 1rem 0 0.5rem;">Trámite ante Comisión Médica</h4>

                                <div class="form-group">
                                    <label for="comision_medica">Estado del trámite en Comisión Médica</label>
                                    <select id="comision_medica" name="comision_medica">
                                        <option value="no_iniciada" selected>No iniciado</option>
                                        <option value="en_tramite">En trámite</option>
                                        <option value="dictamen_emitido">Dictamen emitido</option>
                                        <option value="homologado">Acuerdo homologado</option>
                                    </select>
                                    <small class="form-ayuda">La Ley 27.348 exige agotar esta vía antes de ir a juicio</small>
                                </div>

                                <div class="form-group" id="campo-dictamen-porcentaje" style="display:none;">
                                    <label for="dictamen_porcentaje">% de incapacidad dictaminado por CM</label>
                                    <input type="number" id="dictamen_porcentaje" name="dictamen_porcentaje"
                                        min="0" max="100" step="0.5" placeholder="Ej: 12.5">
                                </div>

                                <div class="form-group form-group-si-no">
                                    <label>¿Vía administrativa agotada?</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="via-admin-si">
                                            <input type="radio" name="via_administrativa_agotada" id="via-admin-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="via-admin-no">
                                            <input type="radio" name="via_administrativa_agotada" id="via-admin-no" value="no">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <h4 style="font-size: 0.9rem; color: var(--primary); margin: 1rem 0 0.5rem;">Información adicional</h4>

                            <div class="form-group form-group-si-no">
                                <label>¿Tiene incapacidades preexistentes?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="preex-si">
                                        <input type="radio" name="tiene_preexistencia" id="preex-si" value="si">
                                        <span>Sí</span>
                                    </label>
                                    <label class="opcion-pill" for="preex-no">
                                        <input type="radio" name="tiene_preexistencia" id="preex-no" value="no">
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="campo-preexistencia-porcentaje" style="display:none;">
                                <label for="preexistencia_porcentaje">% de incapacidad preexistente</label>
                                <input type="number" id="preexistencia_porcentaje" name="preexistencia_porcentaje"
                                    min="0" max="100" step="0.5" placeholder="Ej: 5">
                                <small class="form-ayuda">Se aplica fórmula de Balthazard para descontar preexistencia</small>
                            </div>

                            <div class="form-group form-group-si-no">
                                <label>¿Se encuentra con licencia médica actualmente?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="lic-si">
                                        <input type="radio" name="licencia_activa" id="lic-si" value="si">
                                        <span>Sí</span>
                                    </label>
                                    <label class="opcion-pill" for="lic-no">
                                        <input type="radio" name="licencia_activa" id="lic-no" value="no">
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 1.5rem;">
                                <label for="salarios_historicos">
                                    <span class="form-label-main">
                                        <i class="bi bi-graph-up"></i> Remuneraciones previas al accidente (hasta 12 meses)
                                    </span>
                                    <span class="req">*</span>
                                </label>
                                <textarea id="salarios_historicos" name="salarios_historicos"
                                    rows="5"
                                    placeholder='Un salario por línea (números sin puntos):'
                                    spellcheck="false"
                                    data-tooltip="Remuneraciones sujetas a aportes previas al accidente"></textarea>
                                <small class="form-ayuda">
                                    <strong>Dato clave para ART.</strong> El ingreso base se calcula con el promedio de las remuneraciones sujetas a aportes de los <strong>últimos 12 meses anteriores</strong> al accidente o primera manifestación invalidante.
                                    <br>Si trabajaste menos de 12 meses, cargá solo los meses efectivamente trabajados. Si hubo registración deficiente, luego puede reconstruirse el salario real con prueba.
                                </small>
                                <div class="form-example" id="ej-salarios-art">
                                    <strong><span class="ui-emoji" aria-hidden="true">📋</span>Cómo cargar la base ART:</strong><br><br>
                                    1. Tomá tus recibos o la constancia de ARCA del período previo al accidente.<br>
                                    2. Pegá un salario por línea, sin $ ni puntos.<br>
                                    3. Si no llegás a 12 meses, cargá solo los meses efectivamente trabajados.<br><br>
                                    <code style="background:#f0f0f0; padding:0.8rem; display:block; border-radius:4px; font-size:0.8rem; font-family:monospace;">
950000<br>
965000<br>
980000<br>
995000
                                    </code>
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-salarios-art').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">📋</span>Mostrar cómo ingresar la base ART
                                </span>
                            </div>
                        </div>

                        <!-- DETALLES ESPECÍFICOS DE DIFERENCIAS SALARIALES -->
                        <div class="form-section solo-diferencias"
                            style="display:none; border-top: 1px solid #eee; padding-top: 1rem;">
                            <h4 style="font-size: 0.9rem; color: var(--primary); margin-bottom: 1rem;">Detalles del
                                Reclamo Salarial</h4>

                            <div class="form-group">
                                <label for="motivo_diferencia">Principal motivo del reclamo</label>
                                <select id="motivo_diferencia" name="motivo_diferencia">
                                    <option value="mala_categorizacion">Diferencia por Categoría (CCT)</option>
                                    <option value="falta_pago">Sueldos adeudados (No pago)</option>
                                    <option value="horas_extras">Horas extras no liquidadas</option>
                                    <option value="escala_no_aplicada">Falta de aplicación de escalas/aumentos</option>
                                    <option value="otros">Otros conceptos no remunerativos</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="meses_adeudados">¿Cuántos meses abarca el reclamo? <span
                                        class="req">*</span></label>
                                <input type="number" id="meses_adeudados" name="meses_adeudados" min="1" max="24"
                                    placeholder="Ej: 12">
                                <small class="form-ayuda">El límite legal de reclamo retroactivo es de 24 meses
                                    (Prescripción).</small>
                            </div>
                        </div>

                        <!-- DETALLES ESPECÍFICOS DE PREVENCIÓN CORPORATIVA -->
                        <div class="form-section solo-prevencion"
                            style="display:none; border-top: 1px solid #eee; padding-top: 1rem;">
                            <h4 style="font-size: 0.9rem; color: var(--primary); margin-bottom: 1rem;">Contexto de
                                Prevención / Inspección</h4>

                            <div class="form-group form-group-si-no">
                                <label>¿Recibió inspecciones del Ministerio o ARCA recientemente?</label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="insp-si">
                                        <input type="radio" name="inspeccion_previa" id="insp-si" value="si">
                                        <span>Sí</span>
                                    </label>
                                    <label class="opcion-pill" for="insp-no">
                                        <input type="radio" name="inspeccion_previa" id="insp-no" value="no">
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cantidad_subcontratistas">Cantidad de contratistas / proveedores
                                    externos</label>
                                <input type="number" id="cantidad_subcontratistas" name="cantidad_subcontratistas"
                                    min="0" placeholder="Ej: 5">
                                <small class="form-ayuda">Dato clave para evaluar responsabilidad solidaria (Art. 30
                                    LCT).</small>
                            </div>

                            <div class="form-group">
                                <label for="nivel_cumplimiento">Nivel de cumplimiento detectado (Interno)</label>
                                <select id="nivel_cumplimiento" name="nivel_cumplimiento">
                                    <option value="total">Cumplimiento Total (Documentado)</option>
                                    <option value="parcial">Cumplimiento Parcial (Con observaciones)</option>
                                    <option value="critico">Riesgo Crítico (Falta de registro/deudas)</option>
                                    <option value="desconocido">No auditado actualmente</option>
                                </select>
                            </div>
                        </div>

                        <!-- Nivel de urgencia -->
                        <div class="form-group">
                            <label for="urgencia">Nivel de urgencia que sentís <span class="req">*</span></label>
                            <select id="urgencia" name="urgencia" required>
                                <option value="">-- Seleccioná --</option>
                                <option value="alta">Alta — hay plazos inminentes o ya vencidos</option>
                                <option value="media">Media — hay tiempo pero necesito orientación</option>
                                <option value="baja">Baja — evaluación preventiva sin urgencia</option>
                            </select>
                            <span class="form-error" id="error-urgencia"></span>
                        </div>

                        <!-- ═════════════════════════════════════════════════════════════════
                             RIPTE v2.1 — Cálculos especiales para Ley Bases 27.742
                        ════════════════════════════════════════════════════════════════ -->
                        <div class="form-section seccion-condicional solo-despido-avanzado" 
                            style="display:none; border-left: 3px solid var(--primary); background:var(--primary-xlight); padding: 1.5rem; margin-top: 1.5rem;">
                            
                            <!-- Header con explicación -->
                            <div class="paso-info-card">
                                <div class="paso-info-card-icon">
                                    <i class="bi bi-calculator"></i>
                                </div>
                                <div class="paso-info-card-content">
                                    <h4>Datos especiales para despido e intereses (v2.1)</h4>
                                    <p>Este bloque solo se usa en conflictos donde importa la fecha de despido, la Ley Bases o la jurisdicción de demanda. En accidentes ART y otros supuestos donde no aplica, se oculta y se reemplaza por la sección contextual correspondiente.</p>
                                </div>
                            </div>

                            <!-- Día del despido -->
                            <div class="form-group form-group-with-icon" style="margin-top: 1rem;">
                                <label for="dia_despido">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">📅</span>¿Qué día exacto fue el despido?</span>
                                    <span class="opcional-badge">Opcional</span>
                                </label>
                                <input type="number" id="dia_despido" name="dia_despido" 
                                    min="1" max="31" value="15" placeholder="Ej: 15"
                                    data-tooltip="Día del mes (1 al 31)">
                                <small class="form-ayuda">
                                    Escribí solo el <strong>número del día</strong> (1 a 31). Si no sabés exacto, dejá <strong>15</strong>.
                                    <br>Sirve para cálculos precisos de Art. 233 LCT.
                                </small>
                                <div class="form-example" id="ej-dia-despido">
                                    <strong><span class="ui-emoji" aria-hidden="true">📌</span>Ejemplos:</strong><br>
                                    • Te despidieron el 5 de marzo → escribí <strong>5</strong><br>
                                    • Te despidieron el 28 de junio → escribí <strong>28</strong><br>
                                    • No te acordás → dejá <strong>15</strong> (valor por defecto, funciona igual)
                                </div>
                                <span class="form-example-toggle" onclick="document.getElementById('ej-dia-despido').classList.toggle('active')">
                                    <span class="ui-emoji" aria-hidden="true">📋</span>Ver ejemplos
                                </span>
                            </div>

                            <!-- Check Ley Bases (IMPORTANTE) -->
                            <div class="form-group form-group-si-no" style="margin-top: 1.5rem;">
                                <label style="font-weight: 700; color: var(--alerta-alta); display:flex; align-items:center; gap:0.5rem;">
                                    <i class="bi bi-exclamation-triangle-fill"></i> 
                                    <span>¿Te despidieron ANTES del <strong>9 de Julio de 2024</strong>?</span>
                                </label>
                                <div class="opcion-si-no">
                                    <label class="opcion-pill" for="check-lb-si">
                                        <input type="radio" name="check_inconstitucionalidad" 
                                            id="check-lb-si" value="si">
                                        <span>✓ SÍ (Antes del 9/7/24)</span>
                                    </label>
                                    <label class="opcion-pill" for="check-lb-no">
                                        <input type="radio" name="check_inconstitucionalidad" 
                                            id="check-lb-no" value="no" checked>
                                        <span>✗ NO (Después del 9/7/24)</span>
                                    </label>
                                </div>
                                <div class="aviso-importante">
                                    <div class="aviso-importante-icono"><span class="ui-emoji ui-emoji--solo" aria-hidden="true">⚖️</span></div>
                                    <div class="aviso-importante-content">
                                        <strong>Esto cambió MUCHO con la Ley Bases:</strong>
                                        <p>Antes de julio 2024 → Te debemos multas por vulneración de derechos. Después de julio 2024 → Esas multas están suspendidas (pero hay casos en tribunales que pueden restaurarlas).</p>
                                    </div>
                                </div>
                                <small class="form-ayuda" style="margin-top:0.5rem;">
                                    <strong>Ejemplos:</strong> Despido 20/Junio/2024 = SÍ | Despido 20/Agosto/2024 = NO
                                </small>
                            </div>

                            <!-- Jurisdicción (tasas de interés) -->
                            <div class="form-group form-group-with-icon" style="margin-top: 1.5rem;">
                                <label for="jurisdiccion">
                                    <span class="form-label-main"><span class="ui-emoji" aria-hidden="true">🗺️</span>¿En qué provincia se demandaría?</span>
                                    <span class="opcional-badge">Opcional</span>
                                </label>
                                <select id="jurisdiccion" name="jurisdiccion"
                                    data-tooltip="Afecta tasas de interés">
                                    <option value="CABA">CABA / Juzgados Cap. Federal — Tasa 6.5%</option>
                                    <option value="PBA">Buenos Aires (PBA) — Tasa 6.2%</option>
                                    <option value="CORDOBA">Córdoba — Tasa 6.0%</option>
                                    <option value="SANTA_FE">Santa Fe — Tasa 5.8%</option>
                                    <option value="default" selected>Otra provincia — Tasa 5.5%</option>
                                </select>
                                <small class="form-ayuda">
                                    Si hay juicio, la <strong>provincia define cuántos intereses se acumulan</strong>.
                                    <br>CABA tiene mayor tasa → más dinero por demora.
                                </small>
                            </div>

                        </div>

                        <!-- ═════════════════════════════════════════════════════════════════
                             LEY 27.802 — Campos de análisis avanzado (Marzo 2026)
                        ════════════════════════════════════════════════════════════════ -->
                        <details id="ley27802-panel" class="form-section seccion-condicional"
                            style="border-left: 3px solid #1a5276; background: linear-gradient(135deg, #f0f7fb 0%, #e8f4f8 100%); padding: 1.5rem; margin-top: 1.5rem; border-radius: 0 8px 8px 0;">
                            <summary style="cursor:pointer; font-weight:700; color:#1a5276; list-style:none;">
                                <i class="bi bi-shield-check"></i> Ley 27.802 — Abrir análisis de presunción, solidaria, fraude y daño
                            </summary>
                            <div style="margin-top: 1rem;">
                                <div class="paso-info-card">
                                    <div class="paso-info-card-icon">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div class="paso-info-card-content">
                                        <h4>Ley 27.802 — Cargar solo si realmente corresponde</h4>
                                        <p>Usá este bloque cuando haya <strong>trabajo no registrado, registración deficiente, tercerización/solidaria o indicios concretos de fraude</strong>. Si la relación estuvo correctamente registrada y no querés analizar estos aspectos, dejalo cerrado.</p>
                                    </div>
                                </div>

                                <!-- ─── ART. 23 LCT: Presunción Laboral ─── -->
                                <fieldset class="fieldset-ley27802" style="border: 2px solid #e8f4f8; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #f8fafb;">
                                    <legend style="font-size: 1rem; font-weight: 600; color: #1a5276; padding: 0 10px;"><span class="ui-emoji" aria-hidden="true">🔍</span><strong>Art. 23 LCT (Ley 27.802)</strong> — ¿Presunción de Relación Laboral?</legend>
                                    <p style="color: #555; font-size: 0.85rem; margin: 5px 0 15px 0; font-style: italic;">La presunción NO OPERA si coexisten los 3 elementos:</p>
                                    
                                    <div class="form-group form-group-si-no">
                                        <label>¿Tiene facturación de servicios/productos?</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="fact-si">
                                                <input type="radio" name="tiene_facturacion" id="fact-si" value="si">
                                                <span>Sí</span>
                                            </label>
                                            <label class="opcion-pill" for="fact-no">
                                                <input type="radio" name="tiene_facturacion" id="fact-no" value="no" checked>
                                                <span>No</span>
                                            </label>
                                        </div>
                                        <small class="form-ayuda">Comprobantes de servicios o productos emitidos</small>
                                    </div>
                                    
                                    <div class="form-group form-group-si-no">
                                        <label>¿Hay pagos bancarios (no efectivo)?</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="pagob-si">
                                                <input type="radio" name="tiene_pago_bancario" id="pagob-si" value="si">
                                                <span>Sí</span>
                                            </label>
                                            <label class="opcion-pill" for="pagob-no">
                                                <input type="radio" name="tiene_pago_bancario" id="pagob-no" value="no" checked>
                                                <span>No</span>
                                            </label>
                                        </div>
                                        <small class="form-ayuda">Transferencias, depósitos, cheques (no efectivo)</small>
                                    </div>
                                    
                                    <div class="form-group form-group-si-no">
                                        <label>¿Existe contrato escrito formal?</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="contesc-si">
                                                <input type="radio" name="tiene_contrato_escrito" id="contesc-si" value="si">
                                                <span>Sí</span>
                                            </label>
                                            <label class="opcion-pill" for="contesc-no">
                                                <input type="radio" name="tiene_contrato_escrito" id="contesc-no" value="no" checked>
                                                <span>No</span>
                                            </label>
                                        </div>
                                        <small class="form-ayuda">Acuerdo formalizado, orden de compra o documento equivalente</small>
                                    </div>
                                </fieldset>

                                <!-- ─── ART. 30 LCT: Responsabilidad Solidaria ─── -->
                                <fieldset class="fieldset-ley27802" style="border: 2px solid #e8f4f8; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #f8fafb;">
                                    <legend style="font-size: 1rem; font-weight: 600; color: #1a5276; padding: 0 10px;"><span class="ui-emoji" aria-hidden="true">⚖️</span><strong>Art. 30 LCT (Ley 27.802)</strong> — Controles Exención Solidaria</legend>
                                    <p style="color: #555; font-size: 0.85rem; margin: 5px 0 15px 0; font-style: italic;">Principal es EXENTO si valida los 5 controles:</p>
                                    
                                    <div class="form-group form-group-si-no">
                                        <label>✓ CUIL registrado y actualizado</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="vcuil-si">
                                                <input type="radio" name="valida_cuil" id="vcuil-si" value="si">
                                                <span>Sí</span>
                                            </label>
                                            <label class="opcion-pill" for="vcuil-no">
                                                <input type="radio" name="valida_cuil" id="vcuil-no" value="no" checked>
                                                <span>No</span>
                                            </label>
                                        </div>
                                        <small class="form-ayuda">Verificación en AFIP (C.U.I.L. activo)</small>
                                    </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>✓ Aportes SRT pagados regularmente</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="vaportes-si">
                                            <input type="radio" name="valida_aportes" id="vaportes-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="vaportes-no">
                                            <input type="radio" name="valida_aportes" id="vaportes-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                    <small class="form-ayuda">Contribuciones a la Superintendencia de Riesgos del Trabajo</small>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>✓ Pago remuneración directo al trabajador</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="vpago-si">
                                            <input type="radio" name="valida_pago_directo" id="vpago-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="vpago-no">
                                            <input type="radio" name="valida_pago_directo" id="vpago-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                    <small class="form-ayuda">Nómina transferida directamente (no a intermediarios)</small>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>✓ Clave Bancaria Única (CBU) verificada</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="vcbu-si">
                                            <input type="radio" name="valida_cbu" id="vcbu-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="vcbu-no">
                                            <input type="radio" name="valida_cbu" id="vcbu-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                    <small class="form-ayuda">Para transferencias bancarias de salarios</small>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>✓ ART vigente en función</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="vart-si">
                                            <input type="radio" name="valida_art" id="vart-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="vart-no">
                                            <input type="radio" name="valida_art" id="vart-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                    <small class="form-ayuda">Cobertura de ART actualmente en vigencia</small>
                                </div>
                                </fieldset>

                                <!-- ─── FRAUDE LABORAL: Indicadores ─── -->
                                <fieldset class="fieldset-ley27802 fieldset-fraude" style="border: 2px solid #e8f4f8; border-left: 4px solid #dc3545; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #fff8f8;">
                                    <legend style="font-size: 1rem; font-weight: 600; color: #dc3545; padding: 0 10px;"><span class="ui-emoji" aria-hidden="true">⚠️</span><strong>Fraude Laboral</strong> — Indicadores de Riesgo</legend>
                                    <p style="color: #555; font-size: 0.85rem; margin: 5px 0 15px 0; font-style: italic;">Seleccionar si los siguientes patrones están presentes:</p>
                                    
                                    <div class="form-group form-group-si-no">
                                        <label>Facturación desproporcionada vs. servicios</label>
                                        <div class="opcion-si-no">
                                            <label class="opcion-pill" for="fr-factdesp-si">
                                                <input type="radio" name="fraude_facturacion_desproporcionada" id="fr-factdesp-si" value="si">
                                                <span>Sí</span>
                                            </label>
                                            <label class="opcion-pill" for="fr-factdesp-no">
                                                <input type="radio" name="fraude_facturacion_desproporcionada" id="fr-factdesp-no" value="no" checked>
                                                <span>No</span>
                                            </label>
                                        </div>
                                    </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>Intermitencia sospechosa (pausa-reanudación anómala)</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="fr-intsosp-si">
                                            <input type="radio" name="fraude_intermitencia_sospechosa" id="fr-intsosp-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="fr-intsosp-no">
                                            <input type="radio" name="fraude_intermitencia_sospechosa" id="fr-intsosp-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>Evasión sistemática de registros/documentación</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="fr-evasist-si">
                                            <input type="radio" name="fraude_evasion_sistematica" id="fr-evasist-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="fr-evasist-no">
                                            <input type="radio" name="fraude_evasion_sistematica" id="fr-evasist-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>Sobre-facturación (monto > servicios reales)</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="fr-sobrefact-si">
                                            <input type="radio" name="fraude_sobre_facturacion" id="fr-sobrefact-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="fr-sobrefact-no">
                                            <input type="radio" name="fraude_sobre_facturacion" id="fr-sobrefact-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group-si-no">
                                    <label>Estructura opaca (intermediarios múltiples, offshoring)</label>
                                    <div class="opcion-si-no">
                                        <label class="opcion-pill" for="fr-estopaca-si">
                                            <input type="radio" name="fraude_estructura_opaca" id="fr-estopaca-si" value="si">
                                            <span>Sí</span>
                                        </label>
                                        <label class="opcion-pill" for="fr-estopaca-no">
                                            <input type="radio" name="fraude_estructura_opaca" id="fr-estopaca-no" value="no" checked>
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                                </fieldset>

                                <details id="dano-complementario-panel" style="margin-top: 1rem;">
                                    <summary style="cursor:pointer; font-weight:600; color:#7c3aed;">
                                        <span class="ui-emoji" aria-hidden="true">💔</span> Abrir daño complementario (solo si hubo trabajo no registrado, registración deficiente o un agravamiento puntual)
                                    </summary>
                                    <fieldset class="fieldset-ley27802" style="border: 2px solid #e8f4f8; border-radius: 8px; padding: 20px; margin: 16px 0 0; background-color: #f8fafb;">
                                        <legend style="font-size: 1rem; font-weight: 600; color: #1a5276; padding: 0 10px;"><span class="ui-emoji" aria-hidden="true">💔</span><strong>Daño Complementario</strong> (Arts. 1738, 1740 y 1741 CCCN)</legend>
                                        <p style="color: #555; font-size: 0.85rem; margin: 5px 0 15px 0; font-style: italic;">Abrilo cuando quieras medir daños adicionales por afectación moral/patrimonial vinculada al trabajo no registrado o a una extinción especialmente gravosa.</p>
                                        
                                        <div class="form-group">
                                            <label for="tipo_extincion">Tipo de terminación de la relación:</label>
                                            <select id="tipo_extincion" name="tipo_extincion">
                                                <option value="despido" selected>Despido directo</option>
                                                <option value="renuncia_previa">Renuncia previa (coercitiva)</option>
                                                <option value="constructivo">Terminación constructiva (opresiva)</option>
                                                <option value="suspensión">Suspensión (incertidumbre laboral)</option>
                                            </select>
                                            <small class="form-ayuda">Afecta el cálculo del daño moral y patrimonial</small>
                                        </div>
                                        
                                        <div class="form-group form-group-si-no">
                                            <label>¿Fue violenta? (discriminación, acoso, violencia)</label>
                                            <div class="opcion-si-no">
                                                <label class="opcion-pill" for="violenta-si">
                                                    <input type="radio" name="fue_violenta" id="violenta-si" value="si">
                                                    <span>Sí</span>
                                                </label>
                                                <label class="opcion-pill" for="violenta-no">
                                                    <input type="radio" name="fue_violenta" id="violenta-no" value="no" checked>
                                                    <span>No</span>
                                                </label>
                                            </div>
                                            <small class="form-ayuda">Además puede habilitar el rubro reputacional cuando la configuración del backend así lo exige.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="meses_litigio">Duración estimada del litigio (meses):</label>
                                            <input type="number" id="meses_litigio" name="meses_litigio" min="12" max="120" value="36"
                                                data-tooltip="Meses estimados de proceso judicial">
                                            <small class="form-ayuda">Default: 36 meses. Afecta cálculo de lucro cesante</small>
                                        </div>
                                    </fieldset>
                                </details>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                 PASO 5 — Contacto obligatorio
            ══════════════════════════════════════════ -->
                <div class="wizard-paso" id="paso-5" style="display:none;">
                    <h2 class="paso-titulo">
                        <i class="bi bi-envelope-check"></i> Recibí tu análisis por email
                    </h2>
                    <p class="paso-descripcion">
                        Para generar y enviarte el informe, necesitás cargar un <strong>correo válido</strong>.
                        El análisis se envía al email que completes en este paso.
                    </p>

                    <div class="form-section">
                        <div class="aviso-importante" style="margin-bottom: 1.25rem;">
                            <div class="aviso-importante-icono"><i class="bi bi-person-plus-fill"></i></div>
                            <div class="aviso-importante-content">
                                <strong>¿Querés registro de usuario y lectura ampliada?</strong>
                                <p>Para consultas con mayor personalización, seguimiento y datos estratégicos adicionales, solicitá el registro al email <a href="mailto:estudio@fariasortiz.com.ar" aria-label="Enviar email a estudio@fariasortiz.com.ar">estudio@fariasortiz.com.ar</a> o al WhatsApp de referencia del estudio.</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Correo electrónico <span class="field-note">(obligatorio)</span></label>
                            <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com"
                                autocomplete="email" inputmode="email" required>
                            <small class="form-ayuda">
                                Es obligatorio para enviarte el informe. No enviamos spam.
                            </small>
                            <span class="form-error" id="error-email"></span>
                        </div>

                        <!-- Resumen antes de enviar -->
                        <div class="resumen-previo" id="resumen-previo">
                            <h4><i class="bi bi-list-check"></i> Resumen de tu análisis</h4>
                            <div id="resumen-contenido">
                                <!-- Se llena por JS desde wizard.js -->
                            </div>
                        </div>

                        <!-- Aviso legal final -->
                        <div class="motor-aviso-legal" style="margin-top: 20px;">
                            <i class="bi bi-shield-exclamation"></i>
                            Al procesar el análisis aceptás que los resultados son de carácter
                            <strong>estructural y preventivo</strong>, no constituyen asesoramiento legal
                            definitivo y no garantizan resultado judicial.
                        </div>
                    </div>
                </div>

                <!-- ─────────────────────────────────────────
                 NAVEGACIÓN DEL WIZARD
            ───────────────────────────────────────── -->
                <div class="wizard-nav">
                    <button type="button" id="btn-anterior" class="btn-wizard btn-anterior oculto">
                        <i class="bi bi-arrow-left"></i> Anterior
                    </button>
                    <button type="button" id="btn-siguiente" class="btn-wizard btn-siguiente">
                        Siguiente <i class="bi bi-arrow-right"></i>
                    </button>
                    <button type="button" id="btn-enviar" class="btn-wizard btn-procesar oculto">
                        <i class="bi bi-graph-up"></i> Generar Análisis
                    </button>
                </div>

            </div><!-- /wizard-container -->

            <!-- ─────────────────────────────────────────
             OVERLAY DE CARGA
        ───────────────────────────────────────── -->
            <!-- Visibilidad controlada por JS con clase .activo (opacity/visibility en motor.css) -->
            <div class="motor-loading-overlay" id="motor-loading-overlay">
                <div class="motor-loading-box">
                    <div class="motor-spinner"></div>
                    <p>Procesando tu análisis...</p>
                    <small>Calculando IRIL y escenarios estratégicos</small>
                </div>
            </div>

        </div><!-- /motor-container -->
    </main>

    <!-- ═══════════════════════════════════════════════════════════
     NOTA EXPLICATIVA DEL ÍNDICE IRIL
═══════════════════════════════════════════════════════════ -->
    <section class="iril-nota-pie">
        <div class="iril-nota-inner">

            <div class="iril-nota-encabezado">
                <div class="iril-nota-titulo-bloque">
                    <span class="iril-nota-badge">¿Qué es el IRIL?</span>
                    <h2 class="iril-nota-titulo">Índice de Riesgo Institucional Laboral</h2>
                    <p class="iril-nota-subtitulo">
                        Una herramienta de análisis estructural para empleados y empresas.
                        No mide probabilidad de ganar o perder: mide complejidad, exposición y fricción procesal.
                    </p>
                </div>
            </div>

            <!-- Escala visual -->
            <div class="iril-escala">
                <div class="iril-escala-item bajo">
                    <span class="iril-escala-num">1 – 2</span>
                    <span class="iril-escala-label">Bajo</span>
                    <span class="iril-escala-desc">Situación simple, resolución directa posible</span>
                </div>
                <div class="iril-escala-item moderado">
                    <span class="iril-escala-num">2 – 3</span>
                    <span class="iril-escala-label">Moderado</span>
                    <span class="iril-escala-desc">Consulta profesional preventiva recomendada</span>
                </div>
                <div class="iril-escala-item alto">
                    <span class="iril-escala-num">3 – 4</span>
                    <span class="iril-escala-label">Alto</span>
                    <span class="iril-escala-desc">Intervención profesional recomendada</span>
                </div>
                <div class="iril-escala-item critico">
                    <span class="iril-escala-num">4 – 5</span>
                    <span class="iril-escala-label">Crítico</span>
                    <span class="iril-escala-desc">Requiere abogado laboral de forma urgente</span>
                </div>
            </div>

            <!-- Dimensiones -->
            <div class="iril-dimensiones-nota">
                <h3 class="iril-dimensiones-titulo">Las 5 dimensiones que componen el índice</h3>
                <div class="iril-dims-grid">
                    <div class="iril-dim-item">
                        <span class="iril-dim-icono"><i class="bi bi-building-fill-exclamation"></i></span>
                        <div>
                            <strong>Saturación tribunalicia</strong> <em>(20%)</em>
                            <p>Carga procesal del fuero laboral según la provincia. CABA y Buenos Aires presentan los
                                índices más altos del país.</p>
                        </div>
                    </div>
                    <div class="iril-dim-item">
                        <span class="iril-dim-icono"><i class="bi bi-folder2-open"></i></span>
                        <div>
                            <strong>Complejidad probatoria</strong> <em>(25%)</em>
                            <p>Cuánta documentación respalda la situación: recibos de sueldo, telegramas, contrato
                                escrito, registración en ARCA, testigos.</p>
                        </div>
                    </div>
                    <div class="iril-dim-item">
                        <span class="iril-dim-icono"><i class="bi bi-journal-text"></i></span>
                        <div>
                            <strong>Volatilidad normativa</strong> <em>(15%)</em>
                            <p>Qué tan estable es la jurisprudencia para ese tipo de conflicto. Los accidentes laborales
                                y responsabilidades solidarias son los más volátiles.</p>
                        </div>
                    </div>
                    <div class="iril-dim-item">
                        <span class="iril-dim-icono"><i class="bi bi-cash-stack"></i></span>
                        <div>
                            <strong>Riesgo de costas</strong> <em>(20%)</em>
                            <p>Exposición a ser condenado al pago de costas del juicio. Aumenta si ya existe intercambio
                                telegráfico o intimaciones formales.</p>
                        </div>
                    </div>
                    <div class="iril-dim-item">
                        <span class="iril-dim-icono"><i class="bi bi-people-fill"></i></span>
                        <div>
                            <strong>Riesgo multiplicador</strong> <em>(20%)</em>
                            <p>Efecto cascada: en empresas con muchos empleados, un conflicto puede replicarse generando
                                demandas en cadena.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ejemplo empresa -->
            <div class="iril-ejemplo">
                <div class="iril-ejemplo-badge"><i class="bi bi-lightbulb-fill"></i> Ejemplo práctico para una empresa
                </div>
                <div class="iril-ejemplo-cuerpo">
                    <p>
                        Una <strong>empresa de 30 empleados en Buenos Aires</strong>, con un despido sin causa donde
                        el empleado no tiene recibos de sueldo firmados ni registración en ARCA, podría obtener un
                        <strong>IRIL entre 3.8 y 4.2</strong> — nivel <span
                            class="iril-badge-inline critico">Crítico</span>.
                    </p>
                    <p>
                        Eso implica: alta saturación tribunalicia (4.5), complejidad probatoria elevada para el
                        empleador
                        (sin documentación = difícil defenderse), riesgo por <strong>fraude laboral y daños
                            complementarios</strong>
                        (Ley 27.802 — trabajo no registrado), exposición multiplicadora por la cantidad de empleados, y posibilidad
                        de efecto contagio si otros empleados en la misma situación inician reclamos.
                    </p>
                    <p class="iril-ejemplo-conclusion">
                        <i class="bi bi-shield-check"></i>
                        Un IRIL alto no es una condena — es una señal de alerta temprana para actuar antes de que
                        el conflicto escale. La intervención profesional preventiva reduce drásticamente la exposición
                        económica.
                    </p>
                </div>
            </div>

        </div>
    </section>

    <?= ml_render_floating_contact_buttons() ?>

    <!-- Footer -->
    <footer class="motor-footer">
        <p>
            &copy; <?= date('Y') ?>
            <a href="https://fariasortiz.com.ar" target="_blank">Estudio Farias Ortiz</a>
            — Asesores Legales
            | Dr. Pablo Nicolás Farías — MP 1-33775
        </p>
        <p class="motor-footer-aviso">
            Este motor de análisis no constituye asesoramiento legal y no garantiza resultado judicial.
        </p>
    </footer>

    <!-- Script del wizard -->
    <script src="<?= htmlspecialchars(ml_asset('js/wizard-validation.js')) ?>"></script>
    <script src="<?= htmlspecialchars(ml_asset('js/wizard-payload.js')) ?>"></script>
    <script src="<?= htmlspecialchars(ml_asset('js/wizard.js')) ?>"></script>
    <script>
        // NOTA: wizard.js ya inicializa el wizard automáticamente (window.wizardMotor).
        // Solo se agregan aquí los listeners de campos condicionales.
        document.addEventListener('DOMContentLoaded', function () {
            const conflictoRoleHelper = document.getElementById('conflicto-role-helper');

            function actualizarAyudaConflictos(perfil) {
                if (!conflictoRoleHelper) return;

                if (perfil === 'empleador') {
                    conflictoRoleHelper.innerHTML = `
                        <strong>Vista empresa:</strong>
                        <span>Te mostramos primero contingencias operativas, accidentes y bloques preventivos para decidir rápido dónde está la mayor exposición.</span>
                    `;
                } else if (perfil === 'empleado') {
                    conflictoRoleHelper.innerHTML = `
                        <strong>Vista trabajador:</strong>
                        <span>Elegí el bloque que más se parece a tu reclamo y después seleccioná la opción puntual.</span>
                    `;
                } else {
                    conflictoRoleHelper.innerHTML = `
                        <strong>Elegí primero tu perfil.</strong>
                        <span>Después te mostramos un mapa simple con las opciones más útiles para trabajador o empresa.</span>
                    `;
                }
            }

            function actualizarVisibilidadGruposConflicto() {
                document.querySelectorAll('.conflicto-grupo').forEach(grupo => {
                    const visibles = Array.from(grupo.querySelectorAll('.conflicto-card'))
                        .some(card => card.style.display !== 'none');
                    grupo.style.display = visibles ? 'grid' : 'none';
                });
            }

            function actualizarTarjetasConflicto(perfil) {
                const employerPriority = {
                    accidente_laboral: 1,
                    responsabilidad_solidaria: 2,
                    auditoria_preventiva: 3,
                    riesgo_inspeccion: 4,
                    diferencias_salariales: 5,
                    despido_sin_causa: 6,
                    despido_con_causa: 7,
                    trabajo_no_registrado: 8,
                };

                const employerCopy = {
                    accidente_laboral: ['Accidente / ART empresa', 'Accidente, enfermedad profesional o reclamo civil contra la empresa.'],
                    responsabilidad_solidaria: ['Contratistas y tercerización', 'Riesgos por personal tercerizado, proveedores o control deficiente.'],
                    auditoria_preventiva: ['Auditoría de cumplimiento', 'Chequeo preventivo para ordenar registración, ART y documentación laboral.'],
                    riesgo_inspeccion: ['Inspección ARCA / Ministerio', 'Evaluación rápida del impacto económico ante fiscalización o acta.'],
                };

                document.querySelectorAll('.conflicto-card').forEach(card => {
                    const valor = card.getAttribute('data-valor');
                    const titleEl = card.querySelector('.conflicto-info strong');
                    const descEl = card.querySelector('.conflicto-info span');
                    if (!titleEl || !descEl) return;

                    if (!card.dataset.defaultTitle) {
                        card.dataset.defaultTitle = titleEl.textContent.trim();
                        card.dataset.defaultDesc = descEl.textContent.trim();
                    }

                    if (perfil === 'empleador' && employerCopy[valor]) {
                        titleEl.textContent = employerCopy[valor][0];
                        descEl.textContent = employerCopy[valor][1];
                    } else {
                        titleEl.textContent = card.dataset.defaultTitle;
                        descEl.textContent = card.dataset.defaultDesc;
                    }

                    card.style.order = perfil === 'empleador'
                        ? String(employerPriority[valor] ?? 20)
                        : '0';
                    card.classList.toggle('destacado', perfil === 'empleador' && (employerPriority[valor] ?? 20) <= 4);
                });
            }

            function actualizarTextoElemento(selector, html) {
                const el = document.querySelector(selector);
                if (!el) return;
                if (!el.dataset.defaultHtml) {
                    el.dataset.defaultHtml = el.innerHTML;
                }
                el.innerHTML = html;
            }

            function restaurarTextoElemento(selector) {
                const el = document.querySelector(selector);
                if (el && el.dataset.defaultHtml) {
                    el.innerHTML = el.dataset.defaultHtml;
                }
            }

            function actualizarPlaceholder(selector, valor) {
                const el = document.querySelector(selector);
                if (!el) return;
                if (!el.dataset.hasDefaultPlaceholder) {
                    const placeholderActual = el.getAttribute('placeholder');
                    if (placeholderActual !== null) {
                        el.dataset.defaultPlaceholder = placeholderActual;
                    }
                    el.dataset.hasDefaultPlaceholder = 'true';
                }
                el.setAttribute('placeholder', valor);
            }

            function restaurarPlaceholder(selector) {
                const el = document.querySelector(selector);
                if (el && el.dataset.hasDefaultPlaceholder) {
                    if (el.dataset.defaultPlaceholder !== undefined) {
                        el.setAttribute('placeholder', el.dataset.defaultPlaceholder);
                        return;
                    }
                    el.removeAttribute('placeholder');
                }
            }

            function actualizarAyudaCampo(selectorCampo, html) {
                const campo = document.querySelector(selectorCampo);
                const ayuda = campo?.closest('.form-group')?.querySelector('.form-ayuda');
                if (!ayuda) return;
                if (!ayuda.dataset.defaultHtml) {
                    ayuda.dataset.defaultHtml = ayuda.innerHTML;
                }
                ayuda.innerHTML = html;
            }

            function restaurarAyudaCampo(selectorCampo) {
                const campo = document.querySelector(selectorCampo);
                const ayuda = campo?.closest('.form-group')?.querySelector('.form-ayuda');
                if (ayuda && ayuda.dataset.defaultHtml) {
                    ayuda.innerHTML = ayuda.dataset.defaultHtml;
                }
            }

            function actualizarLabelGrupo(selectorGrupo, html) {
                const grupo = document.querySelector(selectorGrupo);
                const label = grupo?.closest('.form-group')?.querySelector('label');
                if (!label) return;
                if (!label.dataset.defaultHtml) {
                    label.dataset.defaultHtml = label.innerHTML;
                }
                label.innerHTML = html;
            }

            function restaurarLabelGrupo(selectorGrupo) {
                const grupo = document.querySelector(selectorGrupo);
                const label = grupo?.closest('.form-group')?.querySelector('label');
                if (label && label.dataset.defaultHtml) {
                    label.innerHTML = label.dataset.defaultHtml;
                }
            }

            function actualizarCopyContextual(perfil, conflicto) {
                const esEmpleador = perfil === 'empleador';
                const esPrevencion = ['responsabilidad_solidaria', 'auditoria_preventiva', 'riesgo_inspeccion'].includes(conflicto);
                const esAccidente = conflicto === 'accidente_laboral';
                const descripcionPaso2Empresa = esAccidente
                    ? 'Cargá los datos base del trabajador y del siniestro para estimar cobertura ART, riesgo civil y exposición directa de la empresa.'
                    : esPrevencion
                        ? 'Tomamos referencias generales de la empresa o del sector involucrado para estimar exposición, cumplimiento y urgencia de acción sin pedir datos irrelevantes.'
                        : 'Tomamos los datos del trabajador o del caso involucrado para medir la exposición de la empresa y organizar la respuesta estratégica.';

                if (!esEmpleador) {
                    [
                        '#paso-2 .paso-titulo',
                        '#paso-2 .paso-info-card-content h4',
                        '#paso-2 .paso-info-card-content p',
                        'label[for="salario"] .form-label-main',
                        'label[for="antiguedad_meses"] .form-label-main',
                        '#antiguedad_meses + .form-ayuda',
                        'label[for="edad"] .form-label-main',
                        'label[for="provincia"] .form-label-main',
                        '#provincia + .form-ayuda',
                        'label[for="cantidad_empleados"]',
                        '#cantidad_empleados + .form-ayuda',
                        'label[for="categoria"] .form-label-main',
                        '#categoria + .form-ayuda',
                    ].forEach(restaurarTextoElemento);
                    ['#salario'].forEach(restaurarAyudaCampo);

                    ['#salario', '#categoria'].forEach(restaurarPlaceholder);

                    [
                        '#grupo-tiene_recibos',
                        '#grupo-tiene_contrato',
                        '#grupo-registrado_afip',
                        '#grupo-tiene_testigos',
                        '#grupo-hay_intercambio',
                        '#grupo-ya_despedido-opts',
                    ].forEach(restaurarLabelGrupo);
                    return;
                }

                actualizarTextoElemento(
                    '#paso-2 .paso-titulo',
                    esPrevencion ? '<i class="bi bi-briefcase"></i> Datos base de la empresa' : '<i class="bi bi-briefcase"></i> Datos base del caso'
                );
                actualizarTextoElemento('#paso-2 .paso-info-card-content h4', '¿Qué necesitamos para orientar a la empresa?');
                actualizarTextoElemento('#paso-2 .paso-info-card-content p', descripcionPaso2Empresa);

                actualizarTextoElemento(
                    'label[for="salario"] .form-label-main',
                    esPrevencion
                        ? '<span class="ui-emoji" aria-hidden="true">💵</span>Masa salarial o salario promedio comprometido ($)'
                        : '<span class="ui-emoji" aria-hidden="true">💵</span>Mejor salario mensual bruto del trabajador involucrado'
                );
                actualizarAyudaCampo(
                    '#salario',
                    esPrevencion
                        ? 'Usalo como referencia del puesto, sector o universo afectado por la contingencia. No hace falta cargar datos de un trabajador si el análisis es preventivo.'
                        : 'Informá la mejor remuneración bruta del trabajador involucrado. Nos sirve para estimar la exposición potencial de la empresa.'
                );
                actualizarPlaceholder('#salario', esPrevencion ? '$1.200.000' : '$350.000');

                actualizarTextoElemento('label[for="antiguedad_meses"] .form-label-main', '<span class="ui-emoji" aria-hidden="true">📅</span>¿Cuántos meses trabajó el empleado involucrado?');
                actualizarTextoElemento(
                    '#antiguedad_meses + .form-ayuda',
                    'Solo se solicita cuando la antigüedad impacta el cálculo del caso. Si la pantalla la oculta, el análisis seguirá sin ese dato.'
                );
                actualizarTextoElemento('label[for="edad"] .form-label-main', '<span class="ui-emoji" aria-hidden="true">🆔</span>¿Cuántos años tenía el trabajador al momento del siniestro?');

                actualizarTextoElemento('label[for="provincia"] .form-label-main', '<span class="ui-emoji" aria-hidden="true">🗺️</span>¿En qué provincia ocurre el conflicto o la contingencia?');
                actualizarTextoElemento(
                    '#provincia + .form-ayuda',
                    'Definí la jurisdicción principal del caso o del establecimiento fiscalizado. Afecta criterios legales, tasas e inspecciones.'
                );

                actualizarTextoElemento('label[for="cantidad_empleados"]', '<span class="ui-emoji" aria-hidden="true">👥</span>¿Cuántas personas trabajan en la empresa o sector analizado?');
                actualizarTextoElemento(
                    '#cantidad_empleados + .form-ayuda',
                    'Ayuda a medir riesgo multiplicador, escala operativa e impacto potencial de la contingencia sobre la empresa.'
                );

                actualizarTextoElemento('label[for="categoria"] .form-label-main', '<span class="ui-emoji" aria-hidden="true">💼</span>¿Qué puesto, área o contratista está involucrado?');
                actualizarTextoElemento(
                    '#categoria + .form-ayuda',
                    esPrevencion
                        ? 'Podés indicar el área auditada, el tipo de tarea o el contratista crítico. Sirve para contextualizar el foco del análisis.'
                        : 'Indicá el puesto o función del trabajador involucrado para contextualizar mejor la contingencia.'
                );
                actualizarPlaceholder('#categoria', esPrevencion ? 'Ej: Logística, Administración, Contratista de limpieza' : 'Ej: Operario, Chofer, Encargado');

                actualizarLabelGrupo('#grupo-tiene_recibos', '¿La empresa conserva recibos de sueldo firmados? <span class="req">*</span>');
                actualizarLabelGrupo('#grupo-tiene_contrato', '¿Existe contrato, legajo o documentación laboral respaldatoria? <span class="req">*</span>');
                actualizarLabelGrupo('#grupo-registrado_afip', '¿El personal involucrado está correctamente registrado en ARCA? <span class="req">*</span>');
                actualizarLabelGrupo('#grupo-tiene_testigos', '¿Hay referentes, mandos o testigos que puedan respaldar los hechos?');
                actualizarLabelGrupo('#grupo-hay_intercambio', '¿Ya hubo intercambio formal, intimación o requerimiento? <span class="req">*</span>');
                actualizarLabelGrupo('#grupo-ya_despedido-opts', '¿Ya hubo despido o desvinculación formal del trabajador involucrado? <span class="req">*</span>');
            }

            actualizarAyudaConflictos('');
            actualizarTarjetasConflicto('');
            actualizarVisibilidadGruposConflicto();
            actualizarCopyContextual('', '');

            const resetFieldToDefault = function (field) {
                if (!field) {
                    return;
                }

                if (field.type === 'radio' || field.type === 'checkbox') {
                    field.checked = field.defaultChecked;
                    return;
                }

                if (field.tagName === 'SELECT') {
                    Array.from(field.options).forEach(option => {
                        option.selected = option.defaultSelected;
                    });
                    if (field.selectedIndex < 0 && field.options.length > 0) {
                        field.selectedIndex = 0;
                    }
                    return;
                }

                field.value = field.defaultValue || '';
            };

            const resetFields = function (fieldsOrIds) {
                fieldsOrIds.forEach(entry => {
                    const field = typeof entry === 'string' ? document.getElementById(entry) : entry;
                    resetFieldToDefault(field);
                });
            };

            const resetFieldsInContainer = function (container) {
                if (!container) {
                    return;
                }

                container.querySelectorAll('input, select, textarea').forEach(resetFieldToDefault);
            };

            const syncAdvancedAnalysisPanels = function () {
                const panelLey27802 = document.getElementById('ley27802-panel');
                const panelDano = document.getElementById('dano-complementario-panel');
                const conflicto = document.getElementById('tipo_conflicto')?.value || '';
                const registradoAfip = document.querySelector('input[name="registrado_afip"]:checked')?.value || '';
                const tipoRegistro = document.getElementById('tipo_registro')?.value || 'registrado';

                if (!panelLey27802) {
                    return;
                }

                const esSolidaridad = conflicto === 'responsabilidad_solidaria';
                // Mantener este criterio alineado con ComplementaryLegalAnalysisBuilder::isRegistroIrregular().
                const registroIrregular = conflicto === 'trabajo_no_registrado'
                    || registradoAfip === 'no'
                    || tipoRegistro !== 'registrado';

                panelLey27802.open = esSolidaridad || registroIrregular;

                if (panelDano) {
                    panelDano.open = registroIrregular;
                }
            };

            // Mostrar/ocultar campo de fecha del último telegrama según intercambio
            document.querySelectorAll('input[name="hay_intercambio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const campoFecha = document.getElementById('campo-fecha-telegrama');
                    if (campoFecha) {
                        campoFecha.style.display = this.value === 'si' ? 'block' : 'none';
                        if (this.value !== 'si') {
                            resetFields(['fecha_ultimo_telegrama']);
                        }
                    }
                });
            });

            // Gestión de click en tarjetas de conflicto
            document.querySelectorAll('.conflicto-card').forEach(card => {
                card.addEventListener('click', function () {
                    const valor = this.getAttribute('data-valor');
                    const hiddenConflicto = document.getElementById('tipo_conflicto');
                    if (hiddenConflicto) {
                        hiddenConflicto.value = valor;
                        // Disparar evento change manualmente para activar la lógica de campos condicionales
                        hiddenConflicto.dispatchEvent(new Event('change'));
                    }

                    // Estética de selección
                    document.querySelectorAll('.conflicto-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    // Quitar error si existía
                    const errorSpan = document.getElementById('error-tipo_conflicto');
                    if (errorSpan) errorSpan.style.display = 'none';
                });
            });

            // Sincronizar radio tipo_usuario con campo oculto #tipo_usuario + mostrar/ocultar secciones
            document.querySelectorAll('input[name="tipo_usuario_radio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const perfil = this.value;
                    const hiddenTipoUsuario = document.getElementById('tipo_usuario');
                    if (hiddenTipoUsuario) hiddenTipoUsuario.value = perfil;
                    actualizarAyudaConflictos(perfil);
                    actualizarTarjetasConflicto(perfil);

                    // 1. Filtrar galería de conflictos
                    document.querySelectorAll('.conflicto-card').forEach(card => {
                        const grupo = card.getAttribute('data-grupo');
                        if (perfil === 'empleado') {
                            card.style.display = (grupo === 'ambos') ? 'flex' : 'none';
                        } else {
                            card.style.display = (grupo === 'ambos' || grupo === 'empresa') ? 'flex' : 'none';
                        }

                        // Resetear selección si se oculta el card activo
                        if (card.style.display === 'none' && card.classList.contains('selected')) {
                            card.classList.remove('selected');
                            const hiddenConflicto = document.getElementById('tipo_conflicto');
                            if (hiddenConflicto) {
                                hiddenConflicto.value = '';
                                hiddenConflicto.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                    actualizarVisibilidadGruposConflicto();

                    // 2. Ocultar secciones específicas del paso 2 y 4
                    const soloEmpleador = document.querySelectorAll('.solo-empleador');
                    const soloEmpleado = document.querySelectorAll('.solo-empleado');
                    if (perfil === 'empleador') {
                        soloEmpleador.forEach(el => el.style.display = 'block');
                        soloEmpleado.forEach(el => {
                            el.style.display = 'none';
                            el.querySelectorAll('input, select, textarea').forEach(i => i.removeAttribute('required'));
                        });
                    } else {
                        soloEmpleador.forEach(el => {
                            el.style.display = 'none';
                            el.querySelectorAll('input, select, textarea').forEach(i => i.removeAttribute('required'));
                        });
                        soloEmpleado.forEach(el => el.style.display = 'block');
                    }

                    // 3. Ajustar copy contextual según perfil para evitar preguntas orientadas al actor equivocado
                    const conflictoActual = document.getElementById('tipo_conflicto')?.value || '';
                    actualizarCopyContextual(perfil, conflictoActual);

                    // BARRIDO GLOBAL ANTI-BLOQUEO:
                    setTimeout(() => {
                        document.querySelectorAll('#form-motor-laboral input, #form-motor-laboral select, #form-motor-laboral textarea').forEach(el => {
                            if (el.hasAttribute('required')) {
                                if (el.offsetWidth === 0 || el.offsetHeight === 0 || el.closest('[style*="display: none"]')) {
                                    el.removeAttribute('required');
                                    el.setAttribute('data-was-required', 'true');
                                }
                            }
                        });
                    }, 50);

                });
            });

            // Lógica de campos condicionales centralizada
            const hiddenConflictoInput = document.getElementById('tipo_conflicto');
            if (hiddenConflictoInput) {
                hiddenConflictoInput.addEventListener('change', function () {
                    const val = this.value;
                    const esAccidente = val === 'accidente_laboral';
                    const esDiferencia = val === 'diferencias_salariales';
                    const esPrevencion = ['responsabilidad_solidaria', 'multas_legales', 'riesgo_inspeccion', 'auditoria_preventiva'].includes(val);
                    const esRegistroIrregular = ['trabajo_no_registrado', 'despido_sin_causa', 'despido_con_causa'].includes(val);

                    // 1. Campos SOLO ACCIDENTE
                    document.querySelectorAll('.solo-accidente').forEach(el => {
                        el.style.display = esAccidente ? 'block' : 'none';
                        if (!esAccidente) {
                            resetFieldsInContainer(el);
                            document.querySelectorAll('.solo-tiene-art').forEach(subEl => {
                                subEl.style.display = 'none';
                                resetFieldsInContainer(subEl);
                            });
                            const campoDictamen = document.getElementById('campo-dictamen-porcentaje');
                            if (campoDictamen) {
                                campoDictamen.style.display = 'none';
                            }
                            const campoPreex = document.getElementById('campo-preexistencia-porcentaje');
                            if (campoPreex) {
                                campoPreex.style.display = 'none';
                            }
                        }
                        el.querySelectorAll('input, select').forEach(input => {
                            if (esAccidente && input.id !== 'porcentaje_incapacidad') {
                                input.setAttribute('required', 'required');
                            } else {
                                input.removeAttribute('required');
                            }
                        });
                    });

                    // 2. Campos SOLO DIFERENCIAS
                    document.querySelectorAll('.solo-diferencias').forEach(el => {
                        el.style.display = esDiferencia ? 'block' : 'none';
                        if (!esDiferencia) {
                            resetFieldsInContainer(el);
                        }
                        el.querySelectorAll('input, select').forEach(input => {
                            if (esDiferencia) {
                                input.setAttribute('required', 'required');
                            } else {
                                input.removeAttribute('required');
                            }
                        });
                    });

                    // 3. Campos SOLO PREVENCIÓN / AUDITORÍA / SOLIDARIDAD
                    document.querySelectorAll('.solo-prevencion').forEach(el => {
                        el.style.display = esPrevencion ? 'block' : 'none';
                        if (!esPrevencion) {
                            resetFieldsInContainer(el);
                        }
                    });
                    
                    const esAuditoria = val === 'auditoria_preventiva' || val === 'riesgo_inspeccion';
                    document.querySelectorAll('.solo-auditoria').forEach(el => {
                        el.style.display = esAuditoria ? 'block' : 'none';
                        if (!esAuditoria) {
                            resetFieldsInContainer(el);
                        }
                    });
                    const esSolidaridad = val === 'responsabilidad_solidaria';
                    document.querySelectorAll('.solo-solidaridad').forEach(el => {
                        el.style.display = esSolidaridad ? 'block' : 'none';
                        if (!esSolidaridad) {
                            resetFieldsInContainer(el);
                        }
                    });

                    const perfilActual = document.getElementById('tipo_usuario')?.value || '';
                    actualizarCopyContextual(perfilActual, val);

                    // Ocultar campo de antigüedad si es auditoría (no aplica)
                    const grpAntiguedad = document.getElementById('antiguedad_meses')?.closest('.form-group');
                    if (grpAntiguedad) {
                        if (esAuditoria || esSolidaridad) {
                            grpAntiguedad.style.display = 'none';
                            document.getElementById('antiguedad_meses').removeAttribute('required');
                            resetFields(['antiguedad_meses']);
                        } else {
                            grpAntiguedad.style.display = 'block';
                            document.getElementById('antiguedad_meses').setAttribute('required', 'required');
                        }
                    }

                    // 4. Campos registro irregular
                    document.querySelectorAll('.solo-registro-irregular').forEach(el => {
                        el.style.display = esRegistroIrregular ? 'block' : 'none';
                        if (!esRegistroIrregular) {
                            resetFields(['tipo_registro', 'salario_recibo', 'antiguedad_recibo']);
                            document.querySelectorAll('.solo-registro-deficiente').forEach(detalle => {
                                detalle.style.display = 'none';
                            });
                        }
                    });

                    // 5. Ocultar campos de DESPIDO que no aplican
                    document.querySelectorAll('.no-accidente').forEach(el => {
                        const esCasoEspecial = esAccidente || esDiferencia || (esPrevencion && val !== 'multas_legales'); // multas legales puede tener despido
                        
                        if (esCasoEspecial) {
                            el.style.display = 'none';
                            resetFieldsInContainer(el);
                            el.querySelectorAll('input, select').forEach(i => i.removeAttribute('required'));
                        } else {
                            // Mostrar a todos si no es un caso especial
                            el.style.display = 'block';
                            if (el.id === 'grupo-ya_despedido') {
                                el.querySelector('#desp-si').setAttribute('required', 'required');
                            }
                        }
                    });

                    document.querySelectorAll('.solo-despido-avanzado').forEach(el => {
                        const ocultar = esAccidente || esDiferencia || esPrevencion;
                        el.style.display = ocultar ? 'none' : 'block';
                        if (ocultar) {
                            resetFieldsInContainer(el);
                        }
                    });

                    syncAdvancedAnalysisPanels();

                    // BARRIDO GLOBAL ANTI-BLOQUEO: 
                    // Remover 'required' de todo elemento que esté oculto en el formulario para evitar 
                    // el error de validación silenciosa "invalid form control is not focusable" o validación del wizard js.
                    setTimeout(() => {
                        document.querySelectorAll('#form-motor-laboral input, #form-motor-laboral select, #form-motor-laboral textarea').forEach(el => {
                            if (el.hasAttribute('required')) {
                                // Si el elemento o algún contenedor padre tiene display none, o offsetHeight == 0
                                if (el.offsetWidth === 0 || el.offsetHeight === 0 || el.closest('[style*="display: none"]')) {
                                    el.removeAttribute('required');
                                    // Marcar como que fue removido para restaurar después si se muestra?
                                    el.setAttribute('data-was-required', 'true');
                                }
                            } else if (el.hasAttribute('data-was-required')) {
                                // Si volvió a ser visible, evaluar si se debe restaurar (cuidado con casos especiales, mejor dejar que la lógica superior lo imponga, o forzarlo).
                                if (el.offsetWidth > 0 && el.offsetHeight > 0 && !el.closest('[style*="display: none"]')) {
                                    // Se deja actuar a la lógica principal de arriba.
                                }
                            }
                        });
                    }, 50);

                });

            }

            // ── Lógica ART: mostrar/ocultar campos según tiene_art ──
            document.querySelectorAll('input[name="tiene_art"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const soloArt = document.querySelectorAll('.solo-tiene-art');
                    soloArt.forEach(el => {
                        el.style.display = this.value === 'si' ? 'block' : 'none';
                        if (this.value !== 'si') {
                            resetFieldsInContainer(el);
                        }
                    });
                    if (this.value !== 'si') {
                        const campoDictamen = document.getElementById('campo-dictamen-porcentaje');
                        if (campoDictamen) {
                            campoDictamen.style.display = 'none';
                        }
                    }
                });
            });

            // Mostrar campo dictamen_porcentaje si CM emitió dictamen
            const selectCM = document.getElementById('comision_medica');
            if (selectCM) {
                selectCM.addEventListener('change', function () {
                    const campoDictamen = document.getElementById('campo-dictamen-porcentaje');
                    if (campoDictamen) {
                        campoDictamen.style.display = ['dictamen_emitido', 'homologado'].includes(this.value) ? 'block' : 'none';
                        if (!['dictamen_emitido', 'homologado'].includes(this.value)) {
                            resetFields(['dictamen_porcentaje']);
                        }
                    }
                });
            }

            // Mostrar campo preexistencia_porcentaje si tiene preexistencia
            document.querySelectorAll('input[name="tiene_preexistencia"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const campoPreex = document.getElementById('campo-preexistencia-porcentaje');
                    if (campoPreex) {
                        campoPreex.style.display = this.value === 'si' ? 'block' : 'none';
                        if (this.value !== 'si') {
                            resetFields(['preexistencia_porcentaje']);
                        }
                    }
                });
            });

            // Lógica interna para registro deficiente (sueldo/fecha)
            const selectTipoRegistro = document.getElementById('tipo_registro');
            const TIPOS_REGISTRO_DEFICIENTE = ['deficiente_fecha', 'deficiente_salario'];
            const CAMPOS_REGISTRO_DEFICIENTE = {
                deficiente_fecha: ['antiguedad_recibo'],
                deficiente_salario: ['salario_recibo']
            };
            const syncCamposRegistroDeficiente = function () {
                if (!selectTipoRegistro) {
                    return;
                }

                const tipoRegistro = selectTipoRegistro.value;
                const esDeficiente = TIPOS_REGISTRO_DEFICIENTE.includes(tipoRegistro);
                document.querySelectorAll('.solo-registro-deficiente').forEach(el => {
                    el.style.display = esDeficiente ? 'grid' : 'none';
                });

                const camposVisibles = new Set(CAMPOS_REGISTRO_DEFICIENTE[tipoRegistro] || []);
                ['salario_recibo', 'antiguedad_recibo'].forEach(id => {
                    const input = document.getElementById(id);
                    const grupo = input?.closest('.form-group');
                    const visible = esDeficiente && camposVisibles.has(id);
                    if (grupo) {
                        grupo.style.display = visible ? 'block' : 'none';
                    }
                    if (!visible) {
                        resetFields([id]);
                    }
                });

                syncAdvancedAnalysisPanels();
            };

            if (selectTipoRegistro) {
                selectTipoRegistro.addEventListener('change', syncCamposRegistroDeficiente);
                syncCamposRegistroDeficiente();
            }

            document.querySelectorAll('input[name="registrado_afip"]').forEach(function (radio) {
                radio.addEventListener('change', syncAdvancedAnalysisPanels);
            });

            syncAdvancedAnalysisPanels();
        });
    </script>

</body>

</html>
