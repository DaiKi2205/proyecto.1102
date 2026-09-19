<?php
/**
 * Helpers compartidos por todo el sitio (público y admin).
 */
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/limit.php';

date_default_timezone_set('America/Bogota');

/**
 * Registra una línea en logs/app.log (formato: fecha [nivel] mensaje — IP).
 * Nunca lanza excepciones: si el log no puede escribirse, se ignora.
 */
function registrar_log(string $mensaje, string $nivel = 'INFO'): void {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
    $ruta = $_SERVER['REQUEST_URI'] ?? '-';
    $linea = sprintf(
        "[%s] [%s] %s (IP %s, %s)%s",
        date('Y-m-d H:i:s'),
        strtoupper($nivel),
        $mensaje,
        $ip,
        $ruta,
        PHP_EOL
    );
    @file_put_contents($dir . '/app.log', $linea, FILE_APPEND | LOCK_EX);
}

// En producción, si ocurre un error fatal, garantiza un 500 genérico
// (el detalle jamás se imprime: display_errors está apagado).
if (APP_ENV === 'production') {
    register_shutdown_function(function (): void {
        $ultimo = error_get_last();
        if ($ultimo && in_array($ultimo['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) {
                http_response_code(500);
            }
        }
    });
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

const TECNICAS_VALIDAS = ['Desarrollo de Software', 'Contenidos Multimediales', 'Redes de Cómputo', 'Paneles Solares'];
const TIPOS_DOCUMENTO_VALIDOS = ['TI', 'CC', 'CE'];
const GENEROS_VALIDOS = ['Femenino', 'Masculino', 'Otro'];
const ESTUDIA_VALIDOS = ['No', 'SENA', 'Universidad', 'Otros'];
const TRABAJA_VALIDOS = ['No', 'Relacionado con la Técnica', 'Otra Área', 'Independiente'];
const SEMESTRES_VALIDOS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
const RANGOS_SALARIALES = ['Menos de 1 SMMLV', 'Entre 1 y 2 SMMLV', 'Entre 2 y 3 SMMLV', 'Entre 3 y 5 SMMLV', 'Más de 5 SMMLV'];

function usuario_autenticado(): bool {
    return isset($_SESSION['user_id']);
}

/** Cualquier cuenta logueada es staff: no hay niveles de permiso más granulares por ahora. */
function usuario_puede_editar(): bool {
    return usuario_autenticado();
}

/** Corta la ejecución y redirige al login si no hay sesión de staff. */
function requerir_login(string $loginPath = 'login.php'): void {
    if (!usuario_autenticado()) {
        header('Location: ' . $loginPath);
        exit;
    }
}

/** Genera (o reutiliza) un token CSRF para la sesión actual. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verifica el token CSRF recibido por POST; corta la ejecución si no coincide. */
function verificar_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        registrar_log('Token CSRF inválido.', 'WARN');
        http_response_code(403);
        die('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

function tecnica_valida(string $v): bool { return in_array($v, TECNICAS_VALIDAS, true); }
function tipo_documento_valido(string $v): bool { return in_array($v, TIPOS_DOCUMENTO_VALIDOS, true); }
function genero_valido(string $v): bool { return in_array($v, GENEROS_VALIDOS, true); }
function estudia_valido(string $v): bool { return in_array($v, ESTUDIA_VALIDOS, true); }
function trabaja_valido(string $v): bool { return in_array($v, TRABAJA_VALIDOS, true); }

/**
 * Valida el payload que llega del formulario público (registro o edición).
 * Se usa tanto en guardar_solicitud.php como al aprobar en el admin.
 */
function validar_datos_egresado(array $d): array {
    $errores = [];

    if (empty($d['tipo_documento']) || !tipo_documento_valido($d['tipo_documento'])) $errores[] = 'Tipo de documento inválido.';
    if (empty($d['numero_documento']) || !preg_match('/^[0-9]{6,15}$/', $d['numero_documento'])) $errores[] = 'Número de documento inválido.';
    if (empty($d['genero']) || !genero_valido($d['genero'])) $errores[] = 'El género no es válido.';
    if (empty($d['nombres'])) $errores[] = 'Los nombres son obligatorios.';
    if (empty($d['apellidos'])) $errores[] = 'Los apellidos son obligatorios.';
    if (empty($d['correo']) || !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo no es válido.';
    if (empty($d['telefono']) || !preg_match('/^[0-9]{7,10}$/', $d['telefono'])) $errores[] = 'El teléfono no es válido.';
    if (empty($d['tecnica']) || !tecnica_valida($d['tecnica'])) $errores[] = 'La técnica no es válida.';
    if (empty($d['anio_graduacion']) || $d['anio_graduacion'] < (int)date('Y') || $d['anio_graduacion'] > 2155) $errores[] = 'El año de graduación no es válido.';
    if (!empty($d['estudia_actualmente']) && !estudia_valido($d['estudia_actualmente'])) $errores[] = 'Valor de estudio actual inválido.';
    if (!empty($d['trabaja_actualmente']) && !trabaja_valido($d['trabaja_actualmente'])) $errores[] = 'Valor de trabajo actual inválido.';

    if (array_key_exists('trabaja_otro', $d) && ($d['trabaja_actualmente'] ?? '') === 'Otra Área' && empty(trim($d['trabaja_otro']))) $errores[] = 'Si trabajas en otra área, especifica en cuál.';
    if (!empty($d['trabaja_otro']) && strlen(trim($d['trabaja_otro'])) > 100) $errores[] = 'El detalle del trabajo no puede superar los 100 caracteres.';
    if (!empty($d['motivo']) && strlen(trim($d['motivo'])) > 500) $errores[] = 'El motivo de la solicitud no puede superar los 500 caracteres.';

    // Campos condicionales: solo aplican si el egresado estudia o trabaja.
    $estudia = $d['estudia_actualmente'] ?? 'No';
    $trabaja = $d['trabaja_actualmente'] ?? 'No';

    if ($estudia !== 'No') {
        if (empty(trim($d['institucion_estudia'] ?? ''))) $errores[] = 'Si estudias, indica la institución o universidad.';
        if (empty(trim($d['carrera_estudia'] ?? ''))) $errores[] = 'Si estudias, indica la carrera o programa que cursas.';
        if (empty($d['semestre_actual'] ?? '')) $errores[] = 'Si estudias, indica el semestre actual.';
    }
    if (!empty($d['institucion_estudia']) && strlen(trim($d['institucion_estudia'])) > 150) $errores[] = 'La institución no puede superar los 150 caracteres.';
    if (!empty($d['carrera_estudia']) && strlen(trim($d['carrera_estudia'])) > 150) $errores[] = 'La carrera no puede superar los 150 caracteres.';
    if (!empty($d['semestre_actual']) && !in_array((string)$d['semestre_actual'], SEMESTRES_VALIDOS, true)) $errores[] = 'El semestre no es válido.';

    if ($trabaja !== 'No') {
        if (empty(trim($d['empresa_trabaja'] ?? ''))) $errores[] = 'Si trabajas, indica la empresa donde trabajas.';
        if (empty(trim($d['cargo_trabaja'] ?? ''))) $errores[] = 'Si trabajas, indica tu cargo.';
        if (empty($d['rango_salarial'] ?? '')) $errores[] = 'Si trabajas, indica tu rango salarial aproximado.';
    }
    if (!empty($d['empresa_trabaja']) && strlen(trim($d['empresa_trabaja'])) > 150) $errores[] = 'La empresa no puede superar los 150 caracteres.';
    if (!empty($d['cargo_trabaja']) && strlen(trim($d['cargo_trabaja'])) > 150) $errores[] = 'El cargo no puede superar los 150 caracteres.';
    if (!empty($d['rango_salarial']) && !in_array($d['rango_salarial'], RANGOS_SALARIALES, true)) $errores[] = 'El rango salarial no es válido.';

    // Consentimiento de tratamiento de datos (Ley 1581 de 2012).
    if (empty($d['acepta_habeas_data'])) $errores[] = 'Debes aceptar la política de tratamiento de datos personales.';
    if (!empty($d['acepta_habeas_data']) && $d['acepta_habeas_data'] !== '1' && $d['acepta_habeas_data'] !== 1 && $d['acepta_habeas_data'] !== 'on') $errores[] = 'El consentimiento de datos no es válido.';

    if (isset($d['director_curso']) && strlen(trim($d['director_curso'])) > 100) $errores[] = 'El director de curso no puede superar los 100 caracteres.';
    if (isset($d['edad_graduacion']) && $d['edad_graduacion'] !== '' && $d['edad_graduacion'] !== null
        && ((int)$d['edad_graduacion'] < 14 || (int)$d['edad_graduacion'] > 70)) $errores[] = 'La edad al graduarse debe estar entre 14 y 70 años.';
    if (isset($d['info_adicional']) && strlen(trim($d['info_adicional'])) > 1000) $errores[] = 'La información adicional no puede superar los 1000 caracteres.';

    return $errores;
}

/** Cuenta cuántas solicitudes están pendientes (para el badge del panel admin). */
function contar_solicitudes_pendientes(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn();
}
