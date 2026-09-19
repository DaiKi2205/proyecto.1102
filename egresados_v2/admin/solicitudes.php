<?php
require_once __DIR__ . '/_bootstrap.php';

$vista = ($_GET['vista'] ?? 'pendientes') === 'historial' ? 'historial' : 'pendientes';

if ($vista === 'pendientes') {
    $stmt = $pdo->query("SELECT * FROM solicitudes WHERE estado = 'pendiente' ORDER BY fecha_solicitud ASC");
} else {
    $stmt = $pdo->query(
        "SELECT s.*, u.nombre AS revisor_nombre FROM solicitudes s
         LEFT JOIN usuarios u ON u.id = s.revisado_por
         WHERE s.estado != 'pendiente' ORDER BY s.fecha_resolucion DESC LIMIT 100"
    );
}
$solicitudes = $stmt->fetchAll();

$pendientesCount = contar_solicitudes_pendientes($pdo);
$csrf = csrf_token();

// Etiquetas legibles para cada campo del payload (usado en el comparativo).
$etiquetas = [
    'tipo_documento'      => 'Tipo de documento',
    'numero_documento'    => 'Número de documento',
    'genero'              => 'Género',
    'nombres'             => 'Nombres',
    'apellidos'           => 'Apellidos',
    'correo'              => 'Correo',
    'telefono'            => 'Teléfono',
    'tecnica'             => 'Técnica',
    'anio_graduacion'     => 'Año de graduación',
    'estudia_actualmente' => 'Estudia actualmente',
    'institucion_estudia' => 'Institución / Universidad',
    'carrera_estudia'     => 'Carrera / Programa',
    'semestre_actual'     => 'Semestre actual',
    'trabaja_actualmente' => 'Trabaja actualmente',
    'empresa_trabaja'     => 'Empresa',
    'cargo_trabaja'       => 'Cargo',
    'rango_salarial'      => 'Rango salarial',
    'trabaja_otro'        => 'Detalle de trabajo (otra área)',
    'acepta_habeas_data'  => 'Acepta política de datos',
];

// Para las solicitudes de edición, traemos de una vez los egresados actuales
// involucrados, para poder mostrar "dato actual vs. dato propuesto".
$idsEgresados = array_filter(array_column($solicitudes, 'egresado_id'));
$egresadosActuales = [];
if ($idsEgresados) {
    $in = implode(',', array_fill(0, count($idsEgresados), '?'));
    $stmt2 = $pdo->prepare("SELECT * FROM egresados WHERE id IN ($in)");
    $stmt2->execute(array_values($idsEgresados));
    foreach ($stmt2->fetchAll() as $row) {
        $egresadosActuales[$row['id']] = $row;
    }
}

$msgs = [
    'aprobada'  => 'Solicitud aprobada: los datos ya quedaron guardados en el sistema.',
    'rechazada' => 'Solicitud rechazada.',
    'error'     => 'No se pudo procesar la solicitud (revisa si el documento ya está en uso).',
];
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes · Panel administrativo</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="panel.php" class="brand"><span class="brand-mark">🎓</span><span>Panel Egresados</span></a>
    <div class="user-chip">
      <span class="avatar"><?= strtoupper(substr($_SESSION['nombre'] ?? '?', 0, 1)) ?></span>
      <div class="user-meta">
        <strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></strong>
        <small><?= htmlspecialchars($_SESSION['rol'] ?? '') ?></small>
      </div>
      <a href="panel.php" class="btn btn-ghost btn-sm">Volver al panel</a>
      <form method="POST" action="../logout.php" style="display:inline;margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-ghost btn-sm">Salir</button>
      </form>
    </div>
  </div>
</header>

<main class="container">

  <?php if ($msg && isset($msgs[$msg])): ?>
    <div class="alert <?= $msg === 'error' ? 'alert-error' : 'alert-success' ?> fade-in-up"><?= htmlspecialchars($msgs[$msg]) ?></div>
  <?php endif; ?>

  <div class="page-head fade-in-up">
    <div>
      <h1>Solicitudes de egresados</h1>
      <p class="muted">Registros nuevos y ediciones enviadas desde el formulario público.</p>
    </div>
  </div>

  <div class="tabs fade-in-up">
    <a href="?vista=pendientes" class="tab <?= $vista === 'pendientes' ? 'active' : '' ?>">
      Pendientes <?php if ($pendientesCount > 0): ?><span class="count-pill"><?= $pendientesCount ?></span><?php endif; ?>
    </a>
    <a href="?vista=historial" class="tab <?= $vista === 'historial' ? 'active' : '' ?>">Historial</a>
  </div>

  <?php if (empty($solicitudes)): ?>
    <div class="empty-state fade-in-up">
      <div class="empty-icon"><?= $vista === 'pendientes' ? '✅' : '🗂️' ?></div>
      <p><?= $vista === 'pendientes' ? 'No hay solicitudes pendientes por revisar.' : 'Todavía no hay solicitudes resueltas.' ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($solicitudes as $s): ?>
    <?php
      $datos = json_decode($s['datos'], true) ?: [];
      $actual = $s['egresado_id'] ? ($egresadosActuales[$s['egresado_id']] ?? null) : null;
      $nombreCompleto = trim(($datos['nombres'] ?? '') . ' ' . ($datos['apellidos'] ?? ''));

      // Cuenta cuántos campos cambiaron (solo para solicitudes de edición).
      $numCambios = 0;
      if ($actual) {
          foreach ($etiquetas as $campo => $etiqueta) {
              $vn = $datos[$campo] ?? '';
              $va = $actual[$campo] ?? null;
              if ($campo === 'acepta_habeas_data') {
                  $vn = !empty($vn) ? 'Sí' : 'No';
                  $va = $va !== null ? (!empty($va) ? 'Sí' : 'No') : null;
              }
              if ($va !== null && (string)$va !== (string)$vn) $numCambios++;
          }
      }
    ?>
    <div class="solicitud-card fade-in-up">
      <div class="solicitud-head">
        <h3>
          <?= htmlspecialchars($nombreCompleto ?: 'Sin nombre') ?>
          <span class="badge <?= $s['tipo'] === 'nueva' ? 'badge-tipo-nueva' : 'badge-tipo-edicion' ?>">
            <?= $s['tipo'] === 'nueva' ? 'Registro nuevo' : 'Edición de datos' ?>
          </span>
          <?php if ($vista === 'historial'): ?>
            <span class="badge badge-estado-<?= $s['estado'] ?>"><?= ucfirst($s['estado']) ?></span>
          <?php endif; ?>
        </h3>
      </div>
      <p class="solicitud-meta">
        Documento: <?= htmlspecialchars($datos['numero_documento'] ?? $s['numero_documento']) ?>
        · Enviada el <?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?>
        <?php if ($vista === 'historial'): ?>
          · Resuelta el <?= $s['fecha_resolucion'] ? date('d/m/Y H:i', strtotime($s['fecha_resolucion'])) : '—' ?>
          por <?= htmlspecialchars($s['revisor_nombre'] ?? '—') ?>
          <?php if ($s['comentario_admin']): ?> · Motivo: "<?= htmlspecialchars($s['comentario_admin']) ?>"<?php endif; ?>
        <?php endif; ?>
      </p>

      <?php if ($s['motivo']): ?>
        <p class="solicitud-motivo"><strong>Motivo del egresado:</strong> "<?= htmlspecialchars($s['motivo']) ?>"</p>
      <?php endif; ?>

      <?php if ($actual): ?>
        <p class="solicitud-meta" style="margin: 6px 0 12px;">
          <span class="badge badge-cambios">
            <?= $numCambios ?> campo<?= $numCambios === 1 ? '' : 's' ?> modificado<?= $numCambios === 1 ? '' : 's' ?>
          </span>
          <span class="muted small">Los campos en amarillo con → son los que cambiaron.</span>
        </p>
      <?php endif; ?>

      <table class="diff-table">
        <thead>
          <tr>
            <th>Campo</th>
            <?php if ($actual): ?><th>Dato actual</th><?php endif; ?>
            <th><?= $actual ? 'Dato propuesto' : 'Dato enviado' ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($etiquetas as $campo => $etiqueta): ?>
            <?php
              $valorNuevo = $datos[$campo] ?? '';
              $valorActual = $actual[$campo] ?? null;
              if ($campo === 'acepta_habeas_data') {
                  $valorNuevo  = !empty($valorNuevo) ? 'Sí' : 'No';
                  $valorActual = $actual !== null ? (!empty($valorActual) ? 'Sí' : 'No') : null;
              }
              $cambio = $actual && (string)$valorActual !== (string)$valorNuevo;
            ?>
            <tr>
              <td><?= $etiqueta ?></td>
              <?php if ($actual): ?><td><?= htmlspecialchars((string)$valorActual) ?></td><?php endif; ?>
              <td class="<?= $cambio ? 'diff-changed' : '' ?>"><?= $cambio ? '→ ' : '' ?><?= htmlspecialchars((string)$valorNuevo) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($vista === 'pendientes'): ?>
        <div class="solicitud-actions">
          <form method="POST" action="resolver_solicitud.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="accion" value="aprobar">
            <button type="submit" class="btn btn-primary btn-sm">✔ Aprobar</button>
          </form>
          <form method="POST" action="resolver_solicitud.php" onsubmit="return confirm('¿Rechazar esta solicitud?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="accion" value="rechazar">
            <input type="text" name="motivo" class="motivo-input" placeholder="Motivo (opcional)">
            <button type="submit" class="btn btn-danger btn-sm">✘ Rechazar</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</main>
</body>
</html>
