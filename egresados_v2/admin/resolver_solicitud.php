<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

verificar_csrf();

$id     = (int)($_POST['solicitud_id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$motivo = trim($_POST['motivo'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND estado = 'pendiente'");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    header('Location: solicitudes.php?msg=error');
    exit;
}

if ($accion === 'rechazar') {
    $pdo->prepare(
        "UPDATE solicitudes SET estado = 'rechazada', comentario_admin = ?, revisado_por = ?, fecha_resolucion = NOW() WHERE id = ?"
    )->execute([$motivo ?: null, $_SESSION['user_id'], $id]);

    header('Location: solicitudes.php?msg=rechazada');
    exit;
}

if ($accion === 'aprobar') {
    $datos = json_decode($solicitud['datos'], true) ?: [];
    $errores = validar_datos_egresado($datos);

    if ($errores) {
        header('Location: solicitudes.php?msg=error');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $trabajaOtro      = trim($datos['trabaja_otro'] ?? '') ?: null;
        $genero           = $datos['genero'] ?? 'Otro';
        $institucionEstudia = trim($datos['institucion_estudia'] ?? '') ?: null;
        $carreraEstudia     = trim($datos['carrera_estudia'] ?? '') ?: null;
        $semestreActual     = trim($datos['semestre_actual'] ?? '') ?: null;
        $empresaTrabaja     = trim($datos['empresa_trabaja'] ?? '') ?: null;
        $cargoTrabaja       = trim($datos['cargo_trabaja'] ?? '') ?: null;
        $rangoSalarial      = trim($datos['rango_salarial'] ?? '') ?: null;
        $aceptaHabeas       = !empty($datos['acepta_habeas_data']) ? 1 : 0;
        $fechaHabeas        = $aceptaHabeas ? date('Y-m-d H:i:s') : null;

        if ($solicitud['tipo'] === 'nueva') {
            $stmt = $pdo->prepare(
                "INSERT INTO egresados
                    (tipo_documento, numero_documento, genero, nombres, apellidos, correo, telefono, tecnica, anio_graduacion,
                     estudia_actualmente, institucion_estudia, carrera_estudia, semestre_actual,
                     trabaja_actualmente, empresa_trabaja, cargo_trabaja, rango_salarial, trabajo_otro,
                     acepta_habeas_data, fecha_acepta_habeas_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $datos['tipo_documento'], $datos['numero_documento'], $genero, $datos['nombres'], $datos['apellidos'],
                $datos['correo'], $datos['telefono'], $datos['tecnica'], (int)$datos['anio_graduacion'],
                $datos['estudia_actualmente'] ?? 'No', $institucionEstudia, $carreraEstudia, $semestreActual,
                $datos['trabaja_actualmente'] ?? 'No', $empresaTrabaja, $cargoTrabaja, $rangoSalarial, $trabajaOtro,
                $aceptaHabeas, $fechaHabeas,
            ]);
            $egresadoId = (int)$pdo->lastInsertId();
        } else {
            $egresadoId = (int)$solicitud['egresado_id'];
            $stmt = $pdo->prepare(
                "UPDATE egresados SET
                    tipo_documento = ?, numero_documento = ?, genero = ?, nombres = ?, apellidos = ?,
                    correo = ?, telefono = ?, tecnica = ?, anio_graduacion = ?,
                    estudia_actualmente = ?, institucion_estudia = ?, carrera_estudia = ?, semestre_actual = ?,
                    trabaja_actualmente = ?, empresa_trabaja = ?, cargo_trabaja = ?, rango_salarial = ?, trabajo_otro = ?,
                    acepta_habeas_data = ?, fecha_acepta_habeas_data = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $datos['tipo_documento'], $datos['numero_documento'], $genero, $datos['nombres'], $datos['apellidos'],
                $datos['correo'], $datos['telefono'], $datos['tecnica'], (int)$datos['anio_graduacion'],
                $datos['estudia_actualmente'] ?? 'No', $institucionEstudia, $carreraEstudia, $semestreActual,
                $datos['trabaja_actualmente'] ?? 'No', $empresaTrabaja, $cargoTrabaja, $rangoSalarial, $trabajaOtro,
                $aceptaHabeas, $fechaHabeas,
                $egresadoId,
            ]);
            if ($stmt->rowCount() === 0) {
                // El egresado fue borrado (o el documento cambiado) después de
                // enviarse la solicitud: no guardar nada como "aprobado".
                $pdo->rollBack();
                header('Location: solicitudes.php?msg=error');
                exit;
            }
        }

        $pdo->prepare(
            "UPDATE solicitudes SET estado = 'aprobada', egresado_id = ?, revisado_por = ?, fecha_resolucion = NOW() WHERE id = ?"
        )->execute([$egresadoId, $_SESSION['user_id'], $id]);

        $pdo->commit();
        header('Location: solicitudes.php?msg=aprobada');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Caso típico: el número de documento ya quedó tomado por otro
        // registro entre que se envió la solicitud y se aprobó.
        error_log('Error al aprobar solicitud: ' . $e->getMessage());
        header('Location: solicitudes.php?msg=error');
        exit;
    }
}

header('Location: solicitudes.php');
