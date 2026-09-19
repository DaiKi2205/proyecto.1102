<?php
/**
 * API REST de egresados (uso interno del panel / futuras integraciones).
 * CAMBIO respecto a v1: ahora TODOS los métodos, incluido GET, exigen
 * sesión de staff. Antes el listado era público; pero como el esquema
 * nuevo incluye el número de documento de identidad (dato sensible bajo
 * Habeas Data), ya no tiene sentido exponerlo sin autenticación.
 */
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$path   = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$id     = (!empty($path) && is_numeric($path)) ? (int)$path : null;

function responder_error(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(["error" => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM egresados WHERE id = ?");
            $stmt->execute([$id]);
            $egresado = $stmt->fetch();
            $egresado ? print(json_encode($egresado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) : responder_error(404, "Egresado no encontrado");
        } else {
            $stmt = $pdo->query("SELECT * FROM egresados ORDER BY anio_graduacion DESC, apellidos ASC");
            echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        break;

    case 'DELETE':
        if (!$id) responder_error(400, "ID requerido para eliminar");
        $checkStmt = $pdo->prepare("SELECT id FROM egresados WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) responder_error(404, "Egresado no encontrado");

        $pdo->prepare("DELETE FROM egresados WHERE id = ?")->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Egresado eliminado correctamente"], JSON_UNESCAPED_UNICODE);
        break;

    default:
        // POST/PUT ya no existen aquí a propósito: toda alta o edición debe
        // pasar por el flujo de solicitudes (guardar_solicitud.php +
        // admin/resolver_solicitud.php), o por admin/crear.php y
        // admin/editar.php para cargas manuales del staff.
        responder_error(405, "Método no permitido. Usa el flujo de solicitudes o el panel admin para crear/editar.");
}
