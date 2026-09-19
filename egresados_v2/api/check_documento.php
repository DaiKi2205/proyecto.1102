<?php
/**
 * Endpoint AJAX: verifica si un número de documento ya existe en `egresados`.
 * Retorna SOLO { existe: bool }.
 *
 * Por Ley 1581 (Habeas Data) NO se expone ningún dato personal del egresado.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/limit.php';

if (!limite_peticiones('check_doc_' . ip_cliente(), 30, 60)) {
    http_response_code(429);
    exit;
}

$doc = trim($_GET['documento'] ?? '');
if ($doc === '' || strlen($doc) < 4) {
    echo json_encode(['existe' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM egresados WHERE numero_documento = ? LIMIT 1");
$stmt->execute([$doc]);

echo json_encode(['existe' => (bool)$stmt->fetchColumn()]);