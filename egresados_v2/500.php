<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Error del servidor (500) | I.E.T. Francisco Manzanera Henríquez</title>
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
        </div>
    </header>

    <section class="seccion seccion-gris">
        <div class="contenedor" style="max-width:620px; text-align:center;">
            <p style="font-size:4rem; margin:0; color:#1b5e20;">500</p>
            <h1 class="titulo-seccion" style="font-size:1.6rem;">Error interno del servidor</h1>
            <p class="subtitulo-seccion" style="margin-bottom:30px;">Algo salió mal al procesar tu solicitud. Intenta de nuevo en unos minutos; si persiste, contacta al administrador del sitio.</p>
            <p style="margin-top:10px;">
                <a href="index.php" class="btn btn-primario">Volver al inicio</a>
            </p>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-copy">
            <p>&copy; 2026 Observatorio de Egresados. Desarrollado por el Grado 1102 - Técnica en Desarrollo de Software.</p>
        </div>
    </footer>

</body>
</html>