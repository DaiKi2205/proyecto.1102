<?php
/**
 * Bootstrap de entorno (PHP plano, sin framework).
 *
 * - Carga variables desde el archivo .env de la raíz del proyecto.
 * - Define APP_ENV (development | production).
 * - Controla display_errors en producción (no filtrar detalles al público).
 * - Quita la cabecera X-Powered-By.
 *
 * Incluir como PRIMER require_once en config/db.php y en config/helpers.php.
 * Es idempotente: si ya se cargó, no hace nada.
 */
if (defined('ENV_PHP_CARGADO')) {
    return;
}
define('ENV_PHP_CARGADO', true);

// 1) Cargar .env (si existe y no lo ha sobreescrito getenv del servidor).
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }
        [$clave, $valor] = array_pad(explode('=', $linea, 2), 2, '');
        $clave = trim($clave);
        $valor = trim($valor);
        if ($clave !== '' && getenv($clave) === false) {
            putenv($clave . '=' . $valor);
            $_ENV[$clave] = $valor;
        }
    }
}

// 2) Entorno de ejecución.
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// 3) Manejo de errores: en producción jamás se muestran al visitante.
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// 4) Logs de PHP en archivo (no depender del log del proveedor de hosting).
$dirLogs = dirname(__DIR__) . '/logs';
if (!is_dir($dirLogs)) {
    @mkdir($dirLogs, 0750, true);
}
if (is_dir($dirLogs) && is_writable($dirLogs)) {
    ini_set('log_errors', '1');
    ini_set('error_log', $dirLogs . '/php-error.log');
}

// 5) Oculta la versión de PHP en las respuestas.
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

// 6) Prevención del ataque Host Header: aprueba solo hosts conocidos.
//    Los hosts se listan en .env (ALLOWED_HOSTS) o usan el nombre del servidor.
if (isset($_SERVER['HTTP_HOST'])) {
    $hostPermitido = (string)getenv('ALLOWED_HOSTS');
    $permitidos    = $hostPermitido !== '' ? array_map('trim', explode(',', $hostPermitido)) : [];
    $actual        = strtolower(trim((string)$_SERVER['HTTP_HOST']));
    $actualSinPort = strtolower(explode(':', $actual)[0]);
    if ($permitidos && !in_array($actual, $permitidos, true) && !in_array($actualSinPort, $permitidos, true)) {
        http_response_code(421);
        exit('Solicitud rechazada.');
    }
}