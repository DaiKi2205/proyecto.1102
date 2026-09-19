<?php
require_once __DIR__ . '/_bootstrap.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

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
        'director_curso'      => trim($_POST['director_curso'] ?? ''),
        'edad_graduacion'     => !empty($_POST['edad_graduacion']) ? (int)$_POST['edad_graduacion'] : null,
        'info_adicional'      => trim($_POST['info_adicional'] ?? ''),
        'acepta_habeas_data'  => $_POST['acepta_habeas_data'] ?? '',
    ];

    $errores = validar_datos_egresado($datos);

    if (empty($errores)) {
        $dup = $pdo->prepare("SELECT id FROM egresados WHERE numero_documento = ?");
        $dup->execute([$datos['numero_documento']]);
        if ($dup->fetch()) {
            $errores[] = 'Ya existe un egresado registrado con ese número de documento.';
        }
    }

    if (empty($errores)) {
        $acepta = !empty($datos['acepta_habeas_data']) ? 1 : 0;
        $sql = "INSERT INTO egresados
                (tipo_documento, numero_documento, genero, nombres, apellidos, correo, telefono, tecnica, anio_graduacion,
                 estudia_actualmente, institucion_estudia, carrera_estudia, semestre_actual,
                 trabaja_actualmente, empresa_trabaja, cargo_trabaja, rango_salarial, trabajo_otro,
                 director_curso, edad_graduacion, info_adicional, acepta_habeas_data, fecha_acepta_habeas_data)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['tipo_documento'], $datos['numero_documento'], $datos['genero'], $datos['nombres'], $datos['apellidos'],
            $datos['correo'], $datos['telefono'], $datos['tecnica'], $datos['anio_graduacion'],
            $datos['estudia_actualmente'], $datos['institucion_estudia'], $datos['carrera_estudia'], $datos['semestre_actual'],
            $datos['trabaja_actualmente'], $datos['empresa_trabaja'], $datos['cargo_trabaja'], $datos['rango_salarial'],
            $datos['trabaja_otro'],
            $datos['director_curso'], $datos['edad_graduacion'], $datos['info_adicional'],
            $acepta, $acepta ? date('Y-m-d H:i:s') : null,
        ]);

        header("Location: panel.php?msg=agregado");
        exit;
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo egresado (manual)</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a href="panel.php" class="brand"><span class="brand-mark">🎓</span><span>Panel Egresados</span></a>
  </div>
</header>

<main class="container container-narrow">
  <div class="form-card fade-in-up">
    <h1>Nuevo egresado (carga manual)</h1>
    <p class="muted small">Este alta queda guardada de inmediato, sin pasar por la cola de solicitudes (úsala solo para cargar datos históricos o corregir información).</p>

    <?php foreach ($errores as $err): ?>
      <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="grid-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <label class="field">
        <span>Tipo de documento</span>
        <select name="tipo_documento">
          <?php foreach (TIPOS_DOCUMENTO_VALIDOS as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['tipo_documento'] ?? 'CC') === $t) ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>Número de documento</span>
        <input type="text" name="numero_documento" required value="<?= htmlspecialchars($_POST['numero_documento'] ?? '') ?>" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
      </label>

      <label class="field">
        <span>Género</span>
        <select name="genero" required>
          <option value="">Seleccionar...</option>
          <?php foreach (GENEROS_VALIDOS as $g): ?>
            <option value="<?= $g ?>" <?= (($_POST['genero'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>Nombres</span>
        <input type="text" name="nombres" required value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>" oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s]/g,'')">
      </label>
      <label class="field">
        <span>Apellidos</span>
        <input type="text" name="apellidos" required value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>" oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s]/g,'')">
      </label>

      <label class="field">
        <span>Correo electrónico</span>
        <input type="email" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
      </label>
      <label class="field">
        <span>Teléfono</span>
        <input type="text" name="telefono" required value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
      </label>

      <label class="field">
        <span>Técnica</span>
        <select name="tecnica" required>
          <option value="">Seleccionar...</option>
          <?php foreach (TECNICAS_VALIDAS as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['tecnica'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>Año de graduación</span>
        <input type="number" name="anio_graduacion" min="<?= date('Y') ?>" max="2155" required value="<?= htmlspecialchars($_POST['anio_graduacion'] ?? '') ?>">
      </label>

      <label class="field">
        <span>¿Estudia actualmente?</span>
        <select name="estudia_actualmente">
          <?php foreach (ESTUDIA_VALIDOS as $v): ?>
            <option value="<?= $v ?>" <?= (($_POST['estudia_actualmente'] ?? 'No') === $v) ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="span-2 field-group" id="grupo-estudia-detalle" style="<?= (($_POST['estudia_actualmente'] ?? 'No') === 'No') ? 'display:none;' : '' ?>">
        <label class="field">
          <span>Institución / Universidad</span>
          <input type="text" name="institucion_estudia" maxlength="150" value="<?= htmlspecialchars($_POST['institucion_estudia'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Carrera / Programa</span>
          <input type="text" name="carrera_estudia" maxlength="150" value="<?= htmlspecialchars($_POST['carrera_estudia'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Semestre actual</span>
          <select name="semestre_actual">
            <option value="">Seleccionar...</option>
            <?php foreach (SEMESTRES_VALIDOS as $s): ?>
              <option value="<?= $s ?>" <?= (($_POST['semestre_actual'] ?? '') === $s) ? 'selected' : '' ?>>Semestre <?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="field">
        <span>¿Trabaja actualmente?</span>
        <select name="trabaja_actualmente">
          <?php foreach (TRABAJA_VALIDOS as $v): ?>
            <option value="<?= $v ?>" <?= (($_POST['trabaja_actualmente'] ?? 'No') === $v) ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="span-2 field-group" id="grupo-trabaja-detalle" style="<?= (($_POST['trabaja_actualmente'] ?? 'No') === 'No') ? 'display:none;' : '' ?>">
        <label class="field">
          <span>Empresa</span>
          <input type="text" name="empresa_trabaja" maxlength="150" value="<?= htmlspecialchars($_POST['empresa_trabaja'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Cargo</span>
          <input type="text" name="cargo_trabaja" maxlength="150" value="<?= htmlspecialchars($_POST['cargo_trabaja'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Rango salarial</span>
          <select name="rango_salarial">
            <option value="">Seleccionar...</option>
            <?php foreach (RANGOS_SALARIALES as $r): ?>
              <option value="<?= $r ?>" <?= (($_POST['rango_salarial'] ?? '') === $r) ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="field" id="campo-trabaja-otro" style="<?= (($_POST['trabaja_actualmente'] ?? 'No') === 'Otra Área') ? '' : 'display:none;' ?>">
        <span>Especifica en qué trabajas</span>
        <input type="text" name="trabaja_otro" maxlength="100" value="<?= htmlspecialchars($_POST['trabaja_otro'] ?? '') ?>">
      </label>

      <div class="span-2" style="border-top:1px solid var(--green-100); margin-top:6px; padding-top:16px;">
        <p class="muted small" style="margin-bottom:12px;">Datos institucionales (no vienen del formulario público):</p>
      </div>

      <label class="field">
        <span>Director de curso</span>
        <input type="text" name="director_curso" value="<?= htmlspecialchars($_POST['director_curso'] ?? '') ?>">
      </label>
      <label class="field">
        <span>Edad al graduarse</span>
        <input type="number" name="edad_graduacion" min="14" max="70" value="<?= htmlspecialchars($_POST['edad_graduacion'] ?? '') ?>">
      </label>

      <label class="field span-2">
        <span>Información adicional</span>
        <textarea name="info_adicional" rows="3"><?= htmlspecialchars($_POST['info_adicional'] ?? '') ?></textarea>
      </label>

      <label class="field span-2" style="flex-direction:row; align-items:flex-start; gap:10px;">
        <input type="checkbox" name="acepta_habeas_data" value="1" style="width:18px;height:18px;margin-top:3px;" <?= !empty($_POST['acepta_habeas_data']) ? 'checked' : '' ?>>
        <span style="font-weight:400;">Acepta la política de tratamiento de datos personales (Ley 1581 de 2012).</span>
      </label>

      <div class="span-2 form-actions">
        <button type="submit" class="btn btn-primary">Guardar egresado</button>
        <a href="panel.php" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>
  </div>
</main>
<script>
  const estudiaSel = document.querySelector('select[name="estudia_actualmente"]');
  const trabajaSel = document.querySelector('select[name="trabaja_actualmente"]');
  const grupoEstudia = document.getElementById('grupo-estudia-detalle');
  const grupoTrabaja = document.getElementById('grupo-trabaja-detalle');
  const trabajoOtro = document.getElementById('campo-trabaja-otro');
  function toggleCondicionales() {
    if (estudiaSel && grupoEstudia) grupoEstudia.style.display = estudiaSel.value === 'No' ? 'none' : '';
    if (trabajaSel && grupoTrabaja) grupoTrabaja.style.display = trabajaSel.value === 'No' ? 'none' : '';
    if (trabajaSel && trabajoOtro) trabajoOtro.style.display = trabajaSel.value === 'Otra Área' ? '' : 'none';
  }
  if (estudiaSel) estudiaSel.addEventListener('change', toggleCondicionales);
  if (trabajaSel) trabajaSel.addEventListener('change', toggleCondicionales);
</script>
</body>
</html>
