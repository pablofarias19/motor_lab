<?php
/**
 * motor_laboral/admin/header.php — Cabecera del panel admin del Motor Laboral
 *
 * Incluir en TODAS las páginas del panel admin del módulo motor_laboral.
 * Gestiona sesión, login con token simple, logout, e imprime el HTML completo
 * hasta la apertura del <main> (footer.php lo cierra).
 *
 * Adaptado de /admin/header.php del sistema principal.
 * NO modifica ni accede a las BDs del sistema de trámites.
 *
 * Uso en cada página admin:
 *   $page_title = 'Título de la página';
 *   require_once __DIR__ . '/../header.php';
 */

require_once __DIR__ . '/../../config/config.php';

// ─────────────────────────────────────────────────────────────────────────────
// GESTIÓN DE SESIÓN Y AUTENTICACIÓN
// ─────────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Processar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . ML_BASE_URL . '/admin/index.php');
    exit;
}

// Verificar si está logueado
$adminLogueado = isset($_SESSION['ml_admin_logged']) && $_SESSION['ml_admin_logged'] === true;

if (!$adminLogueado) {

    // Procesar intento de login
    $errorLogin = ML_ADMIN_TOKEN === '' ? 'Configure ML_ADMIN_TOKEN en el entorno para habilitar el acceso admin.' : '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ml_token'])) {
        if (ML_ADMIN_TOKEN === '') {
            $errorLogin = 'Configure ML_ADMIN_TOKEN en el entorno para habilitar el acceso admin.';
        } elseif ($_POST['ml_token'] === ML_ADMIN_TOKEN) {
            $_SESSION['ml_admin_logged'] = true;
            $_SESSION['ml_admin_user']   = ML_ADMIN_USER;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $errorLogin = 'Contraseña incorrecta. Intentá nuevamente.';
        }
    }

    // ─── PANTALLA DE LOGIN ────────────────────────────────────────────────────
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Motor Laboral — Acceso Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #2a64b6 0%, #1e4a8b 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .login-card {
                background: #fff;
                padding: 48px 40px;
                border-radius: 16px;
                box-shadow: 0 12px 40px rgba(0,0,0,0.25);
                width: 100%;
                max-width: 380px;
            }
            .login-icon {
                font-size: 48px;
                text-align: center;
                margin-bottom: 8px;
            }
            .login-title {
                color: #2a64b6;
                text-align: center;
                font-size: 22px;
                font-weight: 700;
                margin-bottom: 4px;
            }
            .login-subtitle {
                text-align: center;
                color: #777;
                font-size: 13px;
                margin-bottom: 28px;
            }
            .form-control:focus {
                border-color: #2a64b6;
                box-shadow: 0 0 0 3px rgba(42,100,182,0.2);
            }
            .btn-acceder {
                background: #2a64b6;
                color: #fff;
                border: none;
                width: 100%;
                padding: 12px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 15px;
                margin-top: 8px;
                transition: background 0.2s;
            }
            .btn-acceder:hover { background: #1e4a8b; }
            .login-footer {
                text-align: center;
                color: #aaa;
                font-size: 11px;
                margin-top: 24px;
                padding-top: 16px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="login-icon">⚖️</div>
            <h1 class="login-title">Motor Laboral</h1>
            <p class="login-subtitle">Panel de Administración</p>

            <?php if ($errorLogin): ?>
                <div class="alert alert-danger alert-sm py-2 px-3 mb-3" style="font-size:14px;">
                    <?php echo htmlspecialchars($errorLogin); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:14px;">
                        <i class="bi bi-lock"></i> Contraseña de acceso
                    </label>
                    <input
                        type="password"
                        name="ml_token"
                        class="form-control"
                        placeholder="Ingresá la contraseña"
                        autofocus
                        required
                    >
                </div>
                <button type="submit" class="btn-acceder">Ingresar al Panel</button>
            </form>

            <div class="login-footer">
                © 2026 Estudio Jurídico Farias Ortiz — Motor Laboral v1.0
            </div>
        </div>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// USUARIO AUTENTICADO — emitir HTML de la interfaz admin
// ─────────────────────────────────────────────────────────────────────────────

/** Detectar página activa para resaltar ítem del menú */
$uriActual = $_SERVER['REQUEST_URI'] ?? '';

function mlAdminMenuActivo(string $segmento, string $uri): string {
    return strpos($uri, $segmento) !== false ? ' active' : '';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Panel'); ?> — Motor Laboral Admin</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js para gráficos del dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- CSS del admin del motor laboral -->
    <link rel="stylesheet" href="<?php echo ML_BASE_URL; ?>/admin/assets/css/admin.css">

    <script>
        // Constante global para fetch calls
        const ML_BASE_URL = '<?php echo ML_BASE_URL; ?>';
    </script>
</head>
<body>

<!-- ─────────────────────────────────────────────────────────────────────────
     NAVBAR SUPERIOR
────────────────────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo ML_BASE_URL; ?>/admin/index.php">
            <i class="bi bi-briefcase-fill"></i>
            <span class="fw-bold">Motor Laboral</span>
            <span class="badge bg-light text-primary ms-1" style="font-size:10px;">ADMIN</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 d-none d-md-inline" style="font-size:13px;">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo htmlspecialchars($_SESSION['ml_admin_user'] ?? 'Admin'); ?>
            </span>
            <span class="text-white-50" style="font-size:12px;">
                <i class="bi bi-clock me-1"></i><?php echo date('H:i'); ?>
            </span>
            <a href="?logout=1" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right"></i> Salir
            </a>
        </div>
    </div>
</nav>

<!-- ─────────────────────────────────────────────────────────────────────────
     LAYOUT DOS COLUMNAS: SIDEBAR + MAIN
────────────────────────────────────────────────────────────────────────── -->
<div class="container-fluid">
    <div class="row">

        <!-- ── SIDEBAR ──────────────────────────────────────────────────── -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar pt-3 pb-4">

            <div class="px-3 mb-3">
                <small class="text-uppercase text-muted fw-semibold" style="font-size:10px; letter-spacing:1px;">
                    Gestión
                </small>
            </div>

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link<?php echo mlAdminMenuActivo('/admin/index', $uriActual); ?>"
                       href="<?php echo ML_BASE_URL; ?>/admin/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo mlAdminMenuActivo('/admin/analisis/lista', $uriActual); ?>"
                       href="<?php echo ML_BASE_URL; ?>/admin/analisis/lista.php">
                        <i class="bi bi-list-ul"></i> Análisis
                    </a>
                </li>
            </ul>

            <hr class="mx-3">

            <div class="px-3 mb-2">
                <small class="text-uppercase text-muted fw-semibold" style="font-size:10px; letter-spacing:1px;">
                    Accesos rápidos
                </small>
            </div>

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-muted" href="<?php echo ML_BASE_URL; ?>/index.php" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> Ir al Wizard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-muted" href="/dat/admin/index.php">
                        <i class="bi bi-arrow-left-circle"></i> Panel Principal
                    </a>
                </li>
            </ul>
        </nav>

        <!-- ── CONTENIDO PRINCIPAL ──────────────────────────────────────── -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
