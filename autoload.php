<?php
/**
 * Autoloader PSR-4 para el Motor Laboral PRO
 * 
 * Este autoloader mapea el namespace "App\" al directorio "src/".
 */

spl_autoload_register(function ($class) {
    // Prefix del namespace del proyecto
    $prefix = 'App\\';

    // Directorio base para el prefix del namespace
    $base_dir = __DIR__ . '/src/';

    // Verifica si la clase utiliza el prefix del namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, pasa al siguiente autoloader registrado
        return;
    }

    // Obtiene el nombre relativo de la clase
    $relative_class = substr($class, $len);

    // Reemplaza el prefix del namespace con el directorio base, reemplaza los 
    // separadores de namespace con separadores de directorios
    // y añade la extensión .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, requerirlo
    if (file_exists($file)) {
        require $file;
    }
});
