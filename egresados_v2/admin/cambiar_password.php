<?php
require_once __DIR__ . '/_bootstrap.php';

$exito  = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $actual   = $_POST['password_actual'] ?? '';
    $nueva    = $_POST['password_nueva'] ?? '';
    $repite   = $_POST['password_repite'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($actual, $usuario['password'])) {
        $errores[] = 'La contraseña actual no es correcta.';
    }
    if (strlen($nueva) < 8) {
        $errores[] = 'La contraseña nueva debe tener al menos 8 caracteres.';
    }
    if ($nueva !== $repite) {
        $errores[] = 'La confirmación no coincide con la contraseña nueva.';
    }

    if (empty($errores)) {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
            ->execute([$hash, $_SESSION['user_id']]);
        $exito = 'Contraseña actualizada correctamente.';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cambiar contraseña · Panel administrativo</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a href="panel.php" class="brand"><span class="brand-mark">🎓</span><span>Panel Egresados</span></a>
    <div class="user-chip">
      <span class="avatar"><?= strtoupper(substr($_SESSION['nombre'] ?? '?', 0, 1)) ?></span>
      <a href="panel.php" class="btn btn-ghost btn-sm">Volver al panel</a>
      <form method="POST" action="../logout.php" style="display:inline;margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-ghost btn-sm">Salir</button>
      </form>
    </div>
  </div>
</header>

<main class="container container-narrow">
  <div class="form-card fade-in-up">
    <h1>Cambiar mi contraseña</h1>
    <p class="muted small">Recomendado cambiarla al recibir la cuenta, sobre todo la cuenta de administrador por defecto.</p>

    <?php foreach ($errores as $err): ?>
      <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if ($exito): ?>
      <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <form method="POST" class="grid-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <label class="field span-2">
        <span>Contraseña actual</span>
        <input type="password" name="password_actual" required>
      </label>
      <label class="field">
        <span>Contraseña nueva (mín. 8 caracteres)</span>
        <input type="password" name="password_nueva" required minlength="8">
      </label>
      <label class="field">
        <span>Repite la contraseña nueva</span>
        <input type="password" name="password_repite" required minlength="8">
      </label>

      <div class="span-2 form-actions">
        <button type="submit" class="btn btn-primary">Guardar contraseña</button>
        <a href="panel.php" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
