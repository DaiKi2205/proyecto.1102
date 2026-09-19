<?php
require_once __DIR__ . '/_bootstrap.php';

$q       = trim($_GET['q'] ?? '');
$tecnica = trim($_GET['tecnica'] ?? '');
$estudia = trim($_GET['estudia'] ?? '');
$trabaja = trim($_GET['trabaja'] ?? '');
$anio    = trim($_GET['anio'] ?? '');

$sql    = "SELECT * FROM egresados WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (nombres LIKE ? OR apellidos LIKE ? OR numero_documento LIKE ? OR correo LIKE ? OR telefono LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($tecnica !== '' && tecnica_valida($tecnica)) {
    $sql .= " AND tecnica = ?";
    $params[] = $tecnica;
}
if ($estudia !== '' && estudia_valido($estudia)) {
    $sql .= " AND estudia_actualmente = ?";
    $params[] = $estudia;
}
if ($trabaja !== '' && trabaja_valido($trabaja)) {
    $sql .= " AND trabaja_actualmente = ?";
    $params[] = $trabaja;
}
if ($anio !== '' && preg_match('/^\d{4}$/', $anio)) {
    $sql .= " AND anio_graduacion = ?";
    $params[] = $anio;
}
$sql .= " ORDER BY anio_graduacion DESC, apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$egresados = $stmt->fetchAll();

// Años de graduación disponibles (para el filtro del panel).
$anios = $pdo->query("SELECT DISTINCT anio_graduacion FROM egresados ORDER BY anio_graduacion DESC")->fetchAll(PDO::FETCH_COLUMN);

// Datos para los gráficos del panel (exclusivos del admin). Se calculan
// sobre el listado YA filtrado, así los gráficos se actualizan con cada filtro.
$porTecnicaGenero = [];
$porAnioTecnica   = [];
foreach ($egresados as $e) {
    $t = $e['tecnica'];
    $g = $e['genero'] ?: 'Otro';
    $a = (string)$e['anio_graduacion'];
    if (!isset($porTecnicaGenero[$t])) $porTecnicaGenero[$t] = ['Femenino' => 0, 'Masculino' => 0, 'Otro' => 0];
    if (isset($porTecnicaGenero[$t][$g])) $porTecnicaGenero[$t][$g]++;
    if (!isset($porAnioTecnica[$a])) $porAnioTecnica[$a] = [];
    $porAnioTecnica[$a][$t] = ($porAnioTecnica[$a][$t] ?? 0) + 1;
}
krsort($porAnioTecnica);

// Técnica con más y con menos egresados por año de graduación.
$maxPorAnio = [];
$minPorAnio = [];
foreach ($porAnioTecnica as $a => $porTecnica) {
    $totalAnio = array_sum($porTecnica);
    $max = max($porTecnica);
    $min = min($porTecnica);
    $maxTecnicas = [];
    $minTecnicas = [];
    $ausentes = [];
    foreach (TECNICAS_VALIDAS as $t) {
        $c = $porTecnica[$t] ?? 0;
        if ($c === $max) $maxTecnicas[] = $t;
        if ($c === $min) $minTecnicas[] = $t;
        if ($c === 0) $ausentes[] = $t;
    }
    $maxPorAnio[$a] = ['tecnicas' => $maxTecnicas, 'valor' => $max, 'total' => $totalAnio];
    $minPorAnio[$a] = ['tecnicas' => $minTecnicas, 'valor' => $min, 'total' => $totalAnio, 'ausentes' => $ausentes];
}

$pendientes = contar_solicitudes_pendientes($pdo);
$csrf = csrf_token();

$msgs = [
    'agregado'    => 'Egresado agregado correctamente.',
    'actualizado' => 'Egresado actualizado correctamente.',
    'eliminado'   => 'Egresado eliminado correctamente.',
];
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel administrativo · Egresados</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <span class="brand-mark">🎓</span>
      <span>Panel Egresados</span>
    </div>
    <div class="user-chip">
      <a href="solicitudes.php" class="btn btn-outline btn-sm">
        Solicitudes
        <?php if ($pendientes > 0): ?><span class="badge" style="background:var(--danger);color:#fff;margin-left:4px;"><?= $pendientes ?></span><?php endif; ?>
      </a>
      <span class="avatar"><?= strtoupper(substr($_SESSION['nombre'] ?? '?', 0, 1)) ?></span>
      <div class="user-meta">
        <strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></strong>
        <small><?= htmlspecialchars($_SESSION['rol'] ?? '') ?></small>
      </div>
      <a href="../index.php" class="btn btn-ghost btn-sm">Ver portal público</a>
      <a href="cambiar_password.php" class="btn btn-ghost btn-sm">Cambiar contraseña</a>
      <form method="POST" action="../logout.php" style="display:inline;margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-ghost btn-sm">Salir</button>
      </form>
    </div>
  </div>
</header>

<main class="container">

  <?php if ($msg && isset($msgs[$msg])): ?>
    <div class="alert alert-success fade-in-up"><?= htmlspecialchars($msgs[$msg]) ?></div>
  <?php endif; ?>

  <?php if ($pendientes > 0): ?>
    <div class="alert fade-in-up" style="background:#fff8e1; color:#8a6a00;">
      Tienes <strong><?= $pendientes ?></strong> solicitud<?= $pendientes === 1 ? '' : 'es' ?> pendiente<?= $pendientes === 1 ? '' : 's' ?> de revisión.
      <a href="solicitudes.php" style="color:#8a6a00; font-weight:800;">Revisar ahora &rarr;</a>
    </div>
  <?php endif; ?>

  <div class="page-head fade-in-up">
    <div>
      <h1>Egresados registrados</h1>
      <p class="muted"><?= count($egresados) ?> resultado<?= count($egresados) === 1 ? '' : 's' ?></p>
    </div>
    <a href="crear.php" class="btn btn-primary">
      <span class="plus">+</span> Agregar manualmente
    </a>
  </div>

  <form method="GET" class="filter-bar fade-in-up">
    <input type="text" name="q" placeholder="Buscar por nombre, documento, correo o teléfono..." value="<?= htmlspecialchars($q) ?>">
    <select name="tecnica">
      <option value="">Técnica: Todas</option>
      <?php foreach (TECNICAS_VALIDAS as $t): ?>
        <option value="<?= $t ?>" <?= $tecnica === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <select name="estudia">
      <option value="">Estudia: Todos</option>
      <?php foreach (ESTUDIA_VALIDOS as $v): ?>
        <option value="<?= $v ?>" <?= $estudia === $v ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select name="trabaja">
      <option value="">Trabaja: Todos</option>
      <?php foreach (TRABAJA_VALIDOS as $v): ?>
        <option value="<?= $v ?>" <?= $trabaja === $v ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select name="anio">
      <option value="">Año: Todos</option>
      <?php foreach ($anios as $a): ?>
        <option value="<?= $a ?>" <?= $anio === (string)$a ? 'selected' : '' ?>><?= $a ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if ($q || $tecnica || $estudia || $trabaja || $anio): ?>
      <a href="panel.php" class="btn btn-ghost">Limpiar</a>
    <?php endif; ?>
  </form>

  <div class="charts-section fade-in-up">
    <div class="charts-head">
      <div>
        <h1>📊 Estadísticas e indicadores</h1>
        <p class="charts-head-sub">Exclusivo del panel · Se recalcula con los filtros aplicados arriba.</p>
      </div>
    </div>

    <?php if (!empty($egresados)): ?>
      <div class="charts-grid">

        <article class="chart-card">
          <header class="chart-card-head">
            <span class="chart-ico chart-ico-genero">👥</span>
            <div>
              <h3>Género por técnica</h3>
              <p class="chart-sub">Preferencia de género dentro de cada especialidad técnica.</p>
            </div>
          </header>
          <div class="chart-legend">
            <span><i class="dot dot-f"></i>Femenino</span>
            <span><i class="dot dot-m"></i>Masculino</span>
            <span><i class="dot dot-o"></i>Otro</span>
          </div>

          <?php $hayGenero = false; $iG = 0; ?>
          <?php foreach (TECNICAS_VALIDAS as $t): ?>
            <?php $g = $porTecnicaGenero[$t] ?? ['Femenino' => 0, 'Masculino' => 0, 'Otro' => 0]; $tot = array_sum($g); ?>
            <?php if ($tot === 0) continue; $hayGenero = true; ?>
            <div class="chart-stack">
              <div class="chart-stack-top">
                <span class="chart-stack-name"><?= htmlspecialchars($t) ?></span>
                <strong class="chart-stack-total"><?= $tot ?></strong>
              </div>
              <div class="chart-stack-bar">
                <div class="seg-f" style="width: <?= $tot ? round(($g['Femenino'] / $tot) * 100, 1) : 0 ?>%; animation-delay: <?= $iG * 0.09 ?>s;" title="Femenino: <?= $g['Femenino'] ?>"></div>
                <div class="seg-m" style="width: <?= $tot ? round(($g['Masculino'] / $tot) * 100, 1) : 0 ?>%; animation-delay: <?= $iG * 0.09 + 0.12 ?>s;" title="Masculino: <?= $g['Masculino'] ?>"></div>
                <div class="seg-o" style="width: <?= $tot ? round(($g['Otro'] / $tot) * 100, 1) : 0 ?>%; animation-delay: <?= $iG * 0.09 + 0.24 ?>s;" title="Otro: <?= $g['Otro'] ?>"></div>
              </div>
              <div class="chart-stack-mini">
                <span class="mini-f">Fem <b><?= $g['Femenino'] ?></b></span>
                <span class="mini-m">Masc <b><?= $g['Masculino'] ?></b></span>
                <span class="mini-o">Otro <b><?= $g['Otro'] ?></b></span>
              </div>
            </div>
            <?php $iG++; ?>
          <?php endforeach; ?>
          <?php if (!$hayGenero): ?>
            <p class="chart-sindatos">Sin datos de género en los resultados actuales.</p>
          <?php endif; ?>
        </article>

        <article class="chart-card">
          <header class="chart-card-head">
            <span class="chart-ico chart-ico-mas">🏅</span>
            <div>
              <h3>Técnica con más egresados</h3>
              <p class="chart-sub">La especialidad que más graduados aportó en cada promoción.</p>
            </div>
          </header>

          <?php $iM = 0; ?>
          <?php foreach ($maxPorAnio as $a => $info): ?>
            <div class="chart-row">
              <div class="chart-row-head">
                <span class="year"><?= htmlspecialchars($a) ?></span>
                <strong><?= htmlspecialchars(implode(', ', $info['tecnicas'])) ?> <em><?= $info['valor'] ?></em></strong>
              </div>
              <div class="barra-linea barra-mas">
                <div style="width: <?= $info['total'] ? round(($info['valor'] / $info['total']) * 100, 1) : 0 ?>%; animation-delay: <?= $iM * 0.1 ?>s;"></div>
              </div>
            </div>
            <?php $iM++; ?>
          <?php endforeach; ?>
        </article>

        <article class="chart-card">
          <header class="chart-card-head">
            <span class="chart-ico chart-ico-menos">📉</span>
            <div>
              <h3>Técnica con menos egresados</h3>
              <p class="chart-sub">Las especialidades con menor participación por promoción.</p>
            </div>
          </header>

          <?php foreach ($minPorAnio as $a => $info): ?>
            <?php if (!empty($info['ausentes'])): ?>
              <div class="chart-row chart-row-vacio">
                <div class="chart-row-head">
                  <span class="year"><?= htmlspecialchars($a) ?></span>
                  <span class="pill-aviso"><i>ℹ️</i> Sin egresados: <?= htmlspecialchars(implode(', ', $info['ausentes'])) ?></span>
                </div>
              </div>
            <?php else: ?>
              <div class="chart-row chart-row-min">
                <div class="chart-row-head">
                  <span class="year"><?= htmlspecialchars($a) ?></span>
                  <strong><?= htmlspecialchars(implode(', ', $info['tecnicas'])) ?> <em><?= $info['valor'] ?></em></strong>
                </div>
                <div class="barra-linea barra-menos">
                  <div style="width: <?= $info['total'] ? round(($info['valor'] / $info['total']) * 100, 1) : 0 ?>%;"></div>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </article>

      </div>
    <?php else: ?>
      <div class="chart-card chart-empty">
        <div class="chart-empty-ico">📊</div>
        <p>No hay egresados para graficar con los filtros actuales.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid">
    <?php foreach ($egresados as $i => $e): ?>
      <article class="card fade-in-up" style="animation-delay: <?= min($i * 0.05, 0.6) ?>s">
        <div class="card-top">
          <span class="badge"><?= htmlspecialchars($e['tecnica']) ?></span>
          <span class="year"><?= htmlspecialchars($e['anio_graduacion']) ?></span>
        </div>
        <h3><?= htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?></h3>
        <p class="muted small"><?= htmlspecialchars($e['tipo_documento']) ?> <?= htmlspecialchars($e['numero_documento']) ?></p>

        <div class="card-details">
          <?php if ($e['correo']): ?><div class="detail"><span>✉️</span><?= htmlspecialchars($e['correo']) ?></div><?php endif; ?>
          <?php if ($e['telefono']): ?><div class="detail"><span>📱</span><?= htmlspecialchars($e['telefono']) ?></div><?php endif; ?>
          <div class="detail"><span>👤</span>Género: <?= htmlspecialchars($e['genero'] ?? 'Otro') ?></div>
          <div class="detail"><span>📚</span>Estudia: <?= htmlspecialchars($e['estudia_actualmente']) ?></div>
          <?php if (!empty($e['institucion_estudia'])): ?><div class="detail"><span>🎓</span><?= htmlspecialchars($e['institucion_estudia']) ?> · <?= htmlspecialchars($e['carrera_estudia']) ?></div><?php endif; ?>
          <div class="detail"><span>💼</span>Trabaja: <?= htmlspecialchars($e['trabaja_actualmente']) ?></div>
          <?php if (!empty($e['empresa_trabaja'])): ?><div class="detail"><span>🏢</span><?= htmlspecialchars($e['empresa_trabaja']) ?> · <?= htmlspecialchars($e['cargo_trabaja']) ?> (<?= htmlspecialchars($e['rango_salarial']) ?>)</div><?php endif; ?>
          <?php if (!empty($e['trabajo_otro'])): ?><div class="detail"><span>💼</span>Área: <?= htmlspecialchars($e['trabajo_otro']) ?></div><?php endif; ?>
          <?php if (!empty($e['acepta_habeas_data'])): ?>
            <div class="detail"><span>✅</span>Política de datos aceptada<?php if (!empty($e['fecha_acepta_habeas_data'])): ?> (<?= date('d/m/Y', strtotime($e['fecha_acepta_habeas_data'])) ?>)<?php endif; ?></div>
          <?php else: ?>
            <div class="detail"><span>⚠️</span>Sin aceptación de política de datos</div>
          <?php endif; ?>
        </div>

        <div class="card-actions">
          <a href="editar.php?id=<?= (int)$e['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
          <button type="button" class="btn btn-danger btn-sm"
                  onclick="confirmarEliminar(<?= (int)$e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nombres'] . ' ' . $e['apellidos'])) ?>')">
            Eliminar
          </button>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if (empty($egresados)): ?>
      <div class="empty-state fade-in-up">
        <div class="empty-icon">🔍</div>
        <p>No se encontraron egresados con esos criterios.</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<form id="form-eliminar" method="POST" action="eliminar.php" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="id" id="eliminar-id">
</form>

<div class="modal-overlay" id="modal-overlay">
  <div class="modal fade-in-up">
    <h3>¿Eliminar egresado?</h3>
    <p id="modal-nombre" class="muted"></p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button type="button" class="btn btn-danger" onclick="ejecutarEliminar()">Sí, eliminar</button>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script>
</body>
</html>
