<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

verificar_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    // Al borrar el egresado, las solicitudes históricas que lo referenciaban
    // quedan con egresado_id = NULL automáticamente (ON DELETE SET NULL),
    // así que el historial no se rompe.
    $stmt = $pdo->prepare("DELETE FROM egresados WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: panel.php?msg=" . ($stmt->rowCount() > 0 ? 'eliminado' : ''));
} else {
    header("Location: panel.php");
}
exit;
