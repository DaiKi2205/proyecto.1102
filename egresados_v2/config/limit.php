<?php
/**
 * Rate limiting simple basado en IP (para endpoints sensibles).
 * Usa el directorio temporal del sistema; no requiere BD ni extensiones.
 *
 * Uso:
 *   if (!limite_peticiones('login-' . ip_cliente(), 15, 60)) { rechazar_peticiones(); }
 */

function ip_cliente(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

/**
 * Registra una petición y devuelve true si el cliente aún está dentro del
 * límite (max peticiones en la ventana de ventanaSeg segundos).
 */
function limite_peticiones(string $clave, int $max, int $ventanaSeg): bool {
    $dir = sys_get_temp_dir() . '/egresados_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $archivo = $dir . '/' . hash('sha256', $clave) . '.json';
    $ahora   = time();

    $eventos = [];
    if (is_file($archivo)) {
        $contenido  = file_get_contents($archivo);
        $decodificado = $contenido !== false ? json_decode($contenido, true) : null;
        $eventos = is_array($decodificado) ? $decodificado : [];
    }

    $eventos = array_values(array_filter(
        $eventos,
        fn($t) => is_int($t) && $t > $ahora - $ventanaSeg
    ));

    if (count($eventos) >= $max) {
        return false;
    }

    $eventos[] = $ahora;
    @file_put_contents($archivo, json_encode($eventos), LOCK_EX);

    return true;
}

/** Corta la ejecución con HTTP 429 sin exponer detalles internos. */
function rechazar_peticiones(): void {
    if (function_exists('registrar_log')) {
        registrar_log('Rate limit superado (IP ' . ip_cliente() . ').', 'WARN');
    }
    http_response_code(429);
    header('Retry-After: 60');
    exit('Demasiadas solicitudes. Inténtalo de nuevo en unos minutos.');
}

/**
 * Protege los endpoints POST públicos:
 * - Rechaza con 413 cualquier payload mayor a $maxBytes (anti-DoS).
 * - Rechaza con 413 cualquier intento de subir archivos (este formulario
 *   no tiene subidas de archivo: ninguno debe llegar).
 */
function verificar_payload_maximo(int $maxBytes = 2097152): void {
    $longitud = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;

    if ($longitud > $maxBytes) {
        if (function_exists('registrar_log')) {
            registrar_log('Payload rechazado: ' . $longitud . ' bytes (max ' . $maxBytes . ').', 'WARN');
        }
        http_response_code(413);
        exit('El contenido enviado supera el tamaño permitido.');
    }

    if (!empty($_FILES)) {
        if (function_exists('registrar_log')) {
            registrar_log('Intento de subida de archivo no permitido en este formulario.', 'WARN');
        }
        http_response_code(413);
        exit('La subida de archivos no está habilitada en este formulario.');
    }
}