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
    ini_set('session.use_strict_mode', '1');
    session_name('ml_admin_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

if (!function_exists('ml_admin_csrf_token')) {
    function ml_admin_csrf_token(): string {
        if (empty($_SESSION['ml_admin_csrf'])) {
            $_SESSION['ml_admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['ml_admin_csrf'];
    }
}

if (!function_exists('ml_admin_csrf_is_valid')) {
    function ml_admin_csrf_is_valid(?string $token): bool {
        $sessionToken = $_SESSION['ml_admin_csrf'] ?? '';
        return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('ml_admin_login_limited')) {
    function ml_admin_login_limited(): bool {
        $state = $_SESSION['ml_admin_login_rate_limit'] ?? ['attempts' => 0, 'locked_until' => 0];
        return intval($state['locked_until'] ?? 0) > time();
    }
}

if (!function_exists('ml_admin_register_failed_login')) {
    function ml_admin_register_failed_login(): void {
        $state = $_SESSION['ml_admin_login_rate_limit'] ?? ['attempts' => 0, 'locked_until' => 0];
        $state['attempts'] = intval($state['attempts'] ?? 0) + 1;

        if ($state['attempts'] >= 5) {
            $state['locked_until'] = time() + (15 * 60);
            $state['attempts'] = 0;
        }

        $_SESSION['ml_admin_login_rate_limit'] = $state;
    }
}

if (!function_exists('ml_admin_reset_failed_logins')) {
    function ml_admin_reset_failed_logins(): void {
        $_SESSION['ml_admin_login_rate_limit'] = ['attempts' => 0, 'locked_until' => 0];
    }
}

// Processar logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    if (!ml_admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $_SESSION = [];
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
        $postedToken = $_POST['ml_token'] ?? null;
        if (ML_ADMIN_TOKEN === '') {
            $errorLogin = 'Configure ML_ADMIN_TOKEN en el entorno para habilitar el acceso admin.';
        } elseif (!ml_admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
            $errorLogin = 'La sesión expiró. Recargá la página e intentá nuevamente.';
        } elseif (ml_admin_login_limited()) {
            $errorLogin = 'Demasiados intentos fallidos. Esperá 15 minutos antes de volver a intentar.';
        } elseif (!is_string($postedToken)) {
            $errorLogin = 'Solicitud inválida.';
        } elseif (hash_equals(ML_ADMIN_TOKEN, $postedToken)) {
            session_regenerate_id(true);
            $_SESSION['ml_admin_logged'] = true;
            $_SESSION['ml_admin_user']   = ML_ADMIN_USER;
            $_SESSION['ml_admin_last_auth'] = time();
            ml_admin_reset_failed_logins();
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            ml_admin_register_failed_login();
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(ml_admin_csrf_token()); ?>">
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
            <form method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(ml_admin_csrf_token()); ?>">
                <button type="submit" name="logout" value="1" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </button>
            </form>
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
