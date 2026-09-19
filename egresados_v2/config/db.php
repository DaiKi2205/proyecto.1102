<?php
/**
 * Conexión PDO a la base de datos.
 *
 * Las credenciales NO van hardcodeadas en el código: se leen del .env
 * (o de variables de entorno del hosting). Si faltan, el sitio falla de
 * forma controlada en vez de conectarse con credenciales por defecto.
 *
 * Variables requeridas (ver .env.example):
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS
 */
require_once __DIR__ . '/env.php';

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

if ($host === false || $db === false || $user === false || $pass === false
    || $host === '' || $db === '' || $user === '' || $pass === '') {
    if (!defined('APP_ENV') || APP_ENV === 'production') {
        http_response_code(500);
        exit('Error de configuración del servidor. Contacta al administrador.');
    }
    throw new RuntimeException('Faltan variables de entorno de la BD. Configura el archivo .env (usa .env.example como plantilla).');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reales del driver
        ]
    );
} catch (PDOException $e) {
    // No exponemos el mensaje real de PDO al usuario final (puede filtrar
    // usuario/host/estructura de la BD). Lo registramos en el log del server.
    error_log('Error de conexión BD: ' . $e->getMessage());
    http_response_code(500);
    exit('Error de conexión con la base de datos. Intenta más tarde.');
}