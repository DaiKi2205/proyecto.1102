<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

if (usuario_autenticado()) {
    header('Location: admin/panel.php');
    exit;
}

$error = '';

const MAX_INTENTOS    = 5;
const MINUTOS_BLOQUEO = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    verificar_payload_maximo();

    if (!limite_peticiones('login_' . ip_cliente(), 15, 60)) {
        rechazar_peticiones();
    }

    $email    = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Ingresa correo y contraseña.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        $bloqueadoHasta = $usuario['bloqueado_hasta'] ?? null;

        if ($usuario && $bloqueadoHasta && strtotime($bloqueadoHasta) > time()) {
            $min = (int)ceil((strtotime($bloqueadoHasta) - time()) / 60);
            registrar_log('Intento de login con cuenta bloqueada (' . $email . ').', 'WARN');
            $error = "Cuenta bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en {$min} minuto(s).";
        } elseif ($usuario && password_verify($password, $usuario['password'])) {
            $pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")
                ->execute([$usuario['id']]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['nombre']  = $usuario['nombre'];
            $_SESSION['rol']     = $usuario['rol'];

            registrar_log('Login exitoso (' . $email . ').');
            header('Location: admin/panel.php');
            exit;
        } else {
            if ($usuario) {
                $intentos = $usuario['intentos_fallidos'] + 1;
                $bloqueo  = $intentos >= MAX_INTENTOS ? date('Y-m-d H:i:s', time() + MINUTOS_BLOQUEO * 60) : null;
                $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?")
                    ->execute([$intentos, $bloqueo, $usuario['id']]);
                registrar_log('Login fallido (' . $email . '), intento ' . $intentos . '.', 'WARN');
            } else {
                registrar_log('Login fallido: cuenta inexistente (' . $email . ').', 'WARN');
            }
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo | I.E.T. Francisco Manzanera Henríquez</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <header class="header">
        <div class="contenedor nav-contenedor">
            <div class="logo-box">
                <img src="img/logo.png" alt="Logo Colegio" class="logo-img" onerror="this.style.display='none'">
                <div class="logo">
                    <h2>I.E.T. Francisco Manzanera Henríquez</h2>
                    <span>Observatorio de Egresados</span>
                </div>
            </div>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="index.php#tecnicas">Especialidades</a></li>
                    <li><a href="index.php#registro" class="btn-destacado">Registro Egresados</a></li>
                    <li><a href="login.php" class="activo btn-admin">Acceso Admin</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="seccion-formulario">
        <div class="contenedor">
            <div class="form-card" style="max-width: 450px;">
                <div class="form-header" style="text-align: center;">
                    <h2>Panel de Control</h2>
                    <p>Acceso exclusivo para directivos y administradores del sistema.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alerta alerta-error" style="max-width: none; margin: 20px 40px 0;"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form class="form-body" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="usuario">Usuario o Correo Institucional</label>
                        <input type="email" id="usuario" name="usuario" placeholder="admin@manzanera.edu.co" class="form-input" required autofocus>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="password">Contraseña de Acceso</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" class="form-input" required>
                    </div>

                    <button type="submit" class="btn-submit">
                        Iniciar Sesión
                    </button>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="index.php" style="color: #6c757d; text-decoration: none; font-size: 0.85rem;">&larr; Volver al Portal Público</a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-copy">
            <p>&copy; 2026 Observatorio de Egresados. Desarrollado por el Grado 1102 - Técnica en Desarrollo de Software.</p>
        </div>
    </footer>

</body>
</html>
