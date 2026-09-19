<?php
/**
 * Recibe el POST del formulario público (index.php#registro) y crea un
 * registro en `solicitudes`. NO toca la tabla `egresados` directamente:
 * eso solo pasa cuando un admin aprueba desde admin/solicitudes.php.
 *
 * Regla para decidir el tipo de solicitud:
 * - Si numero_documento YA existe en `egresados` -> tipo = 'edicion'
 *   (se guarda egresado_id apuntando al registro existente).
 * - Si NO existe -> tipo = 'nueva' (egresado_id queda NULL hasta aprobar).
 */
require_once 'config/db.php';
require_once 'config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verificar_csrf();

verificar_payload_maximo();

if (!limite_peticiones('guardar_' . ip_cliente(), 5, 600)) {
    rechazar_peticiones();
}

$datos = [
    'tipo_documento'      => $_POST['tipo_documento'] ?? '',
    'numero_documento'    => trim($_POST['numero_documento'] ?? ''),
    'genero'              => $_POST['genero'] ?? '',
    'nombres'             => trim($_POST['nombres'] ?? ''),
    'apellidos'           => trim($_POST['apellidos'] ?? ''),
    'correo'              => trim($_POST['correo'] ?? ''),
    'telefono'            => trim($_POST['telefono'] ?? ''),
    'tecnica'             => $_POST['tecnica'] ?? '',
    'anio_graduacion'     => (int)($_POST['anio_graduacion'] ?? 0),
    'estudia_actualmente' => $_POST['estudia_actualmente'] ?? 'No',
    'institucion_estudia' => trim($_POST['institucion_estudia'] ?? ''),
    'carrera_estudia'     => trim($_POST['carrera_estudia'] ?? ''),
    'semestre_actual'     => $_POST['semestre_actual'] ?? '',
    'trabaja_actualmente' => $_POST['trabaja_actualmente'] ?? 'No',
    'empresa_trabaja'     => trim($_POST['empresa_trabaja'] ?? ''),
    'cargo_trabaja'       => trim($_POST['cargo_trabaja'] ?? ''),
    'rango_salarial'      => $_POST['rango_salarial'] ?? '',
    'trabaja_otro'        => trim($_POST['trabaja_otro'] ?? ''),
    'acepta_habeas_data'  => $_POST['acepta_habeas_data'] ?? '',
    'motivo'              => trim($_POST['motivo'] ?? ''),
];

$errores = validar_datos_egresado($datos);

if ($errores) {
    // Formulario público simple: si algo falla, devolvemos a la sección
    // de registro con un mensaje de error genérico (evita filtrar detalles).
    registrar_log('Solicitud rechazada por validación: ' . implode(' | ', $errores), 'WARN');
    header('Location: index.php?enviado=error#registro');
    exit;
}

// ¿Ya existe un egresado con ese documento?
$stmt = $pdo->prepare("SELECT id FROM egresados WHERE numero_documento = ?");
$stmt->execute([$datos['numero_documento']]);
$egresadoExistente = $stmt->fetch();

$tipo       = $egresadoExistente ? 'edicion' : 'nueva';
$egresadoId = $egresadoExistente ? (int)$egresadoExistente['id'] : null;

// Evitamos duplicar solicitudes: si ya hay una pendiente con el mismo
// documento, la reemplazamos (el egresado pudo haberse equivocado y
// reenviar) en lugar de acumular solicitudes pendientes repetidas.
$motivo = $datos['motivo'] ?: null;
unset($datos['motivo']);

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM solicitudes WHERE numero_documento = ? AND estado = 'pendiente'")
        ->execute([$datos['numero_documento']]);

    $stmt = $pdo->prepare(
        "INSERT INTO solicitudes (tipo, egresado_id, numero_documento, datos, motivo, estado)
         VALUES (?, ?, ?, ?, ?, 'pendiente')"
    );
    $stmt->execute([
        $tipo,
        $egresadoId,
        $datos['numero_documento'],
        json_encode($datos, JSON_UNESCAPED_UNICODE),
        $motivo,
    ]);
    $idSolicitud = (int)$pdo->lastInsertId();

    $pdo->commit();

    registrar_log('Solicitud creada: id=' . $idSolicitud . ' tipo=' . $tipo . '.');
} catch (PDOException $e) {
    $pdo->rollBack();
    registrar_log('Error al guardar solicitud: ' . $e->getMessage(), 'ERROR');
    error_log('Error al guardar solicitud: ' . $e->getMessage());
    header('Location: index.php?enviado=error#registro');
    exit;
}

header('Location: index.php?enviado=ok#registro');
exit;
