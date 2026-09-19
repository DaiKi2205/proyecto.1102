<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

// Stats reales para la sección "seccion-stats" (antes eran números fijos con animación falsa).
$totalEgresados = (int)$pdo->query("SELECT COUNT(*) FROM egresados")->fetchColumn();
$totalVinculados = (int)$pdo->query(
    "SELECT COUNT(*) FROM egresados WHERE estudia_actualmente != 'No' OR trabaja_actualmente != 'No'"
)->fetchColumn();
$pctVinculacion = $totalEgresados > 0 ? round(($totalVinculados / $totalEgresados) * 100) : 0;
$aniosSeguimiento = (int)$pdo->query("SELECT COUNT(DISTINCT anio_graduacion) FROM egresados")->fetchColumn();

// Resumen por sectores: a qué se dedican los egresados (para la sección "sectores").
$sectores = ['Estudia' => [], 'Trabaja' => []];
foreach ($pdo->query(
    "SELECT 'Estudia' AS grupo, estudia_actualmente AS etiqueta, COUNT(*) AS total
     FROM egresados WHERE estudia_actualmente != 'No' GROUP BY estudia_actualmente
     UNION ALL
     SELECT 'Trabaja' AS grupo, trabaja_actualmente AS etiqueta, COUNT(*) AS total
     FROM egresados WHERE trabaja_actualmente != 'No' GROUP BY trabaja_actualmente"
) as $fila) {
    $sectores[$fila['grupo']][$fila['etiqueta']] = (int)$fila['total'];
}

// Mensaje de resultado tras enviar el formulario público (ver guardar_solicitud.php).
$enviado = $_GET['enviado'] ?? '';
$mensajes = [
    'ok'    => ['tipo' => 'exito', 'texto' => '¡Gracias! Tu información fue enviada y quedará pendiente de validación por la coordinación académica.'],
    'error' => ['tipo' => 'error', 'texto' => 'No pudimos procesar tu solicitud. Verifica los datos e inténtalo de nuevo.'],
];

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observatorio de Egresados | I.E.T. Francisco Manzanera Henríquez</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <?php if (usuario_autenticado()): ?>
        <div id="bannerAdmin" class="banner-admin">
            🔑 Modo Administración Activo — Sesión de <?= htmlspecialchars($_SESSION['nombre']) ?> (<?= htmlspecialchars($_SESSION['rol']) ?>).
            <a href="admin/panel.php" style="color:#1b5e20; font-weight:800;">Ir al panel &rarr;</a>
            ·
            <form method="POST" action="logout.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" style="background:none;border:none;padding:0;color:#1b5e20;font-weight:800;cursor:pointer;">Cerrar sesión</button>
            </form>
        </div>
    <?php endif; ?>

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
                    <li><a href="index.php" class="activo">Inicio</a></li>
                    <li><a href="#registro" class="btn-destacado">Registro Egresados</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <?php if (usuario_autenticado()): ?>
                        <li><a href="admin/panel.php" class="btn-admin">👤 <?= htmlspecialchars($_SESSION['nombre']) ?></a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn-admin">Acceso Admin</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="contenedor hero-contenido anim-fade-in">
            <h1>Impulsando el Futuro de Nuestros Graduados</h1>
            <p>Un espacio institucional para conectar con nuestros exalumnos, hacer seguimiento a su impacto académico y profesional, y brindar orientación a toda la comunidad educativa.</p>
            <div class="hero-botones">
                <a href="#registro" class="btn btn-primario">Actualizar mis Datos</a>
            </div>
        </div>
    </section>

    <section class="seccion-stats anim-scroll">
        <div class="contenedor grid-stats">
            <div class="stat-card">
                <span class="stat-numero" data-target="<?= $totalEgresados ?>">0</span><span class="stat-mas">+</span>
                <p class="stat-label">Egresados Registrados</p>
            </div>
            <div class="stat-card">
                <span class="stat-numero" data-target="<?= $pctVinculacion ?>">0</span><span class="stat-mas">%</span>
                <p class="stat-label">Vinculación Académica / Laboral</p>
            </div>
            <div class="stat-card">
                <span class="stat-numero" data-target="<?= $aniosSeguimiento ?>">0</span>
                <p class="stat-label">Años con Seguimiento</p>
            </div>
        </div>
    </section>

    <section id="sectores" class="seccion seccion-gris anim-scroll">
        <div class="contenedor">
            <h2 class="titulo-seccion anim-scroll">Nuestros Egresados, ¿a qué se dedican?</h2>
            <p class="subtitulo-seccion anim-scroll">Distribución actual de los egresados que ya reportaron su situación académica y laboral. Los datos se actualizan con cada solicitud validada.</p>

            <?php if ($totalEgresados > 0): ?>
                <?php
                $itemsEstudia = [
                    'Universidad' => ['label' => 'Universidad', 'ico' => '🎓', 'color' => '#1a7a4c'],
                    'SENA'        => ['label' => 'SENA', 'ico' => '⚙️', 'color' => '#2e9e6b'],
                    'Otros'       => ['label' => 'Otros estudios', 'ico' => '📚', 'color' => '#7ec8a3'],
                ];
                $itemsTrabaja = [
                    'Relacionado con la Técnica' => ['label' => 'Relacionado con la técnica', 'ico' => '💻', 'color' => '#2f80ed'],
                    'Independiente' => ['label' => 'Trabajo independiente', 'ico' => '💼', 'color' => '#f59e0b'],
                    'Otra Área'      => ['label' => 'Trabajo en otra área', 'ico' => '🏢', 'color' => '#64748b'],
                ];
                ?>
                <div class="sectores-grid">
                    <div class="panel-sector anim-scroll">
                        <h3>Educación Superior</h3>
                        <?php foreach ($itemsEstudia as $clave => $cfg): ?>
                            <?php $cant = $sectores['Estudia'][$clave] ?? 0; $pct = $totalEgresados > 0 ? round(($cant / $totalEgresados) * 100) : 0; ?>
                            <div class="barra<?= $cant === 0 ? ' barra-vacia' : '' ?>" style="--color: <?= $cfg['color'] ?>;">
                                <div class="barra-top">
                                    <span class="barra-label"><span class="barra-ico"><?= $cfg['ico'] ?></span><?= $cfg['label'] ?></span>
                                    <span class="barra-num"><strong class="barra-pct"><?= $pct ?>%</strong><span class="barra-cant"><?= $cant ?> egresados</span></span>
                                </div>
                                <div class="barra-fondo"><div class="barra-fill" style="--ancho: <?= $pct ?>%;"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="panel-sector anim-scroll">
                        <h3>Vinculación Laboral</h3>
                        <?php foreach ($itemsTrabaja as $clave => $cfg): ?>
                            <?php $cant = $sectores['Trabaja'][$clave] ?? 0; $pct = $totalEgresados > 0 ? round(($cant / $totalEgresados) * 100) : 0; ?>
                            <div class="barra<?= $cant === 0 ? ' barra-vacia' : '' ?>" style="--color: <?= $cfg['color'] ?>;">
                                <div class="barra-top">
                                    <span class="barra-label"><span class="barra-ico"><?= $cfg['ico'] ?></span><?= $cfg['label'] ?></span>
                                    <span class="barra-num"><strong class="barra-pct"><?= $pct ?>%</strong><span class="barra-cant"><?= $cant ?> egresados</span></span>
                                </div>
                                <div class="barra-fondo"><div class="barra-fill" style="--ancho: <?= $pct ?>%;"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel-sector sin-datos anim-scroll">
                    <p>Aún no tenemos egresados registrados. Si te graduaste de nuestra institución, <a href="#registro">registra tus datos</a> y comienza a aparecer aquí.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="registro" class="seccion-registro">
        <div class="contenedor">

            <?php if ($enviado && isset($mensajes[$enviado])): ?>
                <div class="alerta alerta-<?= $mensajes[$enviado]['tipo'] ?>">
                    <?= htmlspecialchars($mensajes[$enviado]['texto']) ?>
                </div>
            <?php endif; ?>

            <div class="form-card anim-scroll">
                <div class="form-header">
                    <h2>Formulario de Registro y Actualización</h2>
                    <p>Estimado egresado, diligencia tus datos para registrar o actualizar tu expediente institucional. Tu información será tratada confidencialmente según la política de protección de datos. Si tu documento ya está en el sistema, se creará una solicitud de <strong>actualización</strong>; si es tu primera vez, se creará un <strong>registro nuevo</strong>. Cada envío queda pendiente de validación por la coordinación académica. Puedes enviar una nueva solicitud cuando cambies de empleo, de estudios o de contacto, indicando el motivo.</p>
                </div>

                <form class="form-body" id="formRegistroPublico" method="POST" action="guardar_solicitud.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_documento">Tipo de Documento *</label>
                            <select id="tipo_documento" name="tipo_documento" class="form-input" required>
                                <option value="TI">Tarjeta de Identidad (TI)</option>
                                <option value="CC" selected>Cédula de Ciudadanía (CC)</option>
                                <option value="CE">Cédula de Extranjería (CE)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="numero_documento">Número de Documento *</label>
                            <input type="text" id="numero_documento" name="numero_documento" placeholder="Ej. 1012345678" class="form-input" required pattern="[0-9]{6,15}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <div id="doc-status" class="doc-status" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombres">Nombres Completos *</label>
                            <input type="text" id="nombres" name="nombres" placeholder="Ingresa tus nombres" class="form-input" required oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s]/g,'')">
                        </div>

                        <div class="form-group">
                            <label for="apellidos">Apellidos Completos *</label>
                            <input type="text" id="apellidos" name="apellidos" placeholder="Ingresa tus apellidos" class="form-input" required oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s]/g,'')">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="correo">Correo Electrónico *</label>
                            <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono / WhatsApp *</label>
                            <input type="tel" id="telefono" name="telefono" placeholder="Ej. 3001234567" class="form-input" required pattern="[0-9]{7,10}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tecnica">Especialidad Técnica Cursada *</label>
                            <select id="tecnica" name="tecnica" class="form-input" required>
                                <option value="Desarrollo de Software">Técnica en Desarrollo de Software</option>
                                <option value="Contenidos Multimediales">Técnica en Contenidos Digitales</option>
                                <option value="Redes de Cómputo">Técnica en Redes</option>
                                <option value="Paneles Solares">Técnica en Paneles Solares / Energías Limpias</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="anio_graduacion">Año de Graduación *</label>
                            <input type="number" id="anio_graduacion" name="anio_graduacion" placeholder="Ej. <?= date('Y') ?>" min="<?= date('Y') ?>" max="2155" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="genero">Género *</label>
                            <select id="genero" name="genero" class="form-input" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach (GENEROS_VALIDOS as $g): ?>
                                    <option value="<?= $g ?>"><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-divider">
                        <span>Situación Académica y Laboral Actual</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estudia_actualmente">¿Se encuentra estudiando actualmente?</label>
                            <select id="estudia_actualmente" name="estudia_actualmente" class="form-input">
                                <option value="No">No</option>
                                <option value="SENA">Sí (SENA - Tecnólogo / Técnico)</option>
                                <option value="Universidad">Sí (Universidad - Pregrado)</option>
                                <option value="Otros">Sí (Cursos / Educación Continua)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="trabaja_actualmente">¿Se encuentra laborando actualmente?</label>
                            <select id="trabaja_actualmente" name="trabaja_actualmente" class="form-input">
                                <option value="No">No</option>
                                <option value="Relacionado con la Técnica">Sí (En su especialidad técnica)</option>
                                <option value="Otra Área">Sí (En otra área laboral)</option>
                                <option value="Independiente">Sí (Emprendedor / Independiente)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" id="grupo-estudia-detalle" style="display:none;">
                        <div class="form-group">
                            <label for="institucion_estudia">Nombre de la Institución / Universidad *</label>
                            <input type="text" id="institucion_estudia" name="institucion_estudia" placeholder="Ej. SENA, Universidad del Tolima..." class="form-input" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="carrera_estudia">Carrera / Programa que cursa *</label>
                            <input type="text" id="carrera_estudia" name="carrera_estudia" placeholder="Ej. Tecnología en Análisis y Desarrollo de Software" class="form-input" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="semestre_actual">Semestre actual *</label>
                            <select id="semestre_actual" name="semestre_actual" class="form-input">
                                <option value="">Seleccionar...</option>
                                <?php foreach (SEMESTRES_VALIDOS as $s): ?>
                                    <option value="<?= $s ?>">Semestre <?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" id="grupo-trabaja-detalle" style="display:none;">
                        <div class="form-group">
                            <label for="empresa_trabaja">Nombre de la empresa *</label>
                            <input type="text" id="empresa_trabaja" name="empresa_trabaja" placeholder="Ej. Soluciones Tecnológicas S.A.S." class="form-input" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="cargo_trabaja">Cargo que desempeña *</label>
                            <input type="text" id="cargo_trabaja" name="cargo_trabaja" placeholder="Ej. Desarrollador Junior" class="form-input" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="rango_salarial">Rango salarial aproximado *</label>
                            <select id="rango_salarial" name="rango_salarial" class="form-input">
                                <option value="">Seleccionar...</option>
                                <?php foreach (RANGOS_SALARIALES as $r): ?>
                                    <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" id="grupo-trabaja-otro" style="display:none;">
                            <label for="trabaja_otro">Especifica en qué trabajas *</label>
                            <input type="text" id="trabaja_otro" name="trabaja_otro" placeholder="Ej. Administración, comercio, transporte..." class="form-input" maxlength="100">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="motivo">Motivo de la actualización</label>
                        <textarea id="motivo" name="motivo" rows="3" class="form-input" maxlength="500" placeholder="Si ya estás registrado y solo cambiaste algún dato (empleo, estudios, teléfono...), cuéntanos brevemente por qué envías esta solicitud. Ej.: Cambié de empleo y quiero actualizar mi situación laboral."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-acepta">
                            <input type="checkbox" id="acepta_habeas_data" name="acepta_habeas_data" value="1" required>
                            <span>Acepto la política de tratamiento de datos personales e institucionales. *</span>
                        </label>
                        <p class="ayuda-checkbox">Al marcar esta casilla autorizas a la I.E.T. Francisco Manzanera Henríquez a recopilar, almacenar y usar tus datos para el seguimiento académico y laboral, de acuerdo con la <a href="politica.php" target="_blank" rel="noopener">Política de Tratamiento de Datos</a> y la Ley 1581 de 2012 (Habeas Data).</p>
                    </div>

                    <button type="submit" class="btn-submit">
                        Enviar Información de Registro
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section id="faq" class="seccion seccion-gris">
        <div class="contenedor">
            <h2 class="titulo-seccion anim-scroll">Preguntas Frecuentes</h2>
            <p class="subtitulo-seccion anim-scroll">Resuelve tus dudas sobre el registro, el tratamiento de tus datos y los beneficios de la comunidad.</p>

            <div class="faq-contenedor anim-scroll">
                <div class="faq-item">
                    <button class="faq-pregunta">
                        <span>¿Para qué sirve el registro en el Observatorio de Egresados?</span>
                        <span class="faq-icono">+</span>
                    </button>
                    <div class="faq-respuesta">
                        <p>Servir como una base de datos centralizada que optimiza el manejo de la información de nuestros exalumnos, facilitando la gestión administrativa de la secretaría del colegio y permitiendo un seguimiento eficiente e institucional de nuestros egresados.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-pregunta">
                        <span>¿Cómo se protegen mis datos personales?</span>
                        <span class="faq-icono">+</span>
                    </button>
                    <div class="faq-respuesta">
                        <p>La información recopilada es de uso exclusivamente institucional bajo la Ley de Protección de Datos Personales (Habeas Data). Ningún dato de contacto privado será compartido públicamente sin tu previa autorización.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-pregunta">
                        <span>¿Cuánto tiempo tarda en aprobarse mi registro?</span>
                        <span class="faq-icono">+</span>
                    </button>
                    <div class="faq-respuesta">
                        <p>Una vez enviado el formulario, la coordinación académica valida tus datos en un lapso de 24 a 48 horas hábiles para actualizar tu estado dentro de la plataforma oficial.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-pregunta">
                        <span>¿Puedo actualizar mi información si cambio de empleo o de estudios?</span>
                        <span class="faq-icono">+</span>
                    </button>
                    <div class="faq-respuesta">
                        <p>¡Sí! Puedes diligenciar el formulario de registro nuevamente utilizando tu mismo número de documento. El sistema actualizará automáticamente tu perfil laboral y académico existente, previa validación de la coordinación.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="contenedor footer-contenido">
            <div class="footer-col">
                <h4>I.E.T. Francisco Manzanera Henríquez</h4>
                <p>Girardot, Cundinamarca</p>
                <p>Formando líderes en tecnología y desarrollo social.</p>
            </div>
            <div class="footer-col">
                <h4>Enlaces Rápidos</h4>
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="#registro">Formulario de Egresados</a></li>
                    <li><a href="politica.php">Política de Datos</a></li>
                    <li><a href="login.php">Acceso de Administrador</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contacto Institucional</h4>
                <p>Correo: contacto@manzanera.edu.co</p>
                <p>Teléfono: (601) XXX-XXXX</p>
            </div>
        </div>
        <div class="footer-copy">
            <p>&copy; 2026 Observatorio de Egresados. Desarrollado por el Grado 1102 - Técnica en Desarrollo de Software.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            let statsAnimadas = false;

            // Animaciones al hacer scroll (igual que antes).
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        if (entry.target.classList.contains('seccion-stats') && !statsAnimadas) {
                            animarContadores();
                            statsAnimadas = true;
                        }
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('.anim-scroll').forEach(el => observer.observe(el));

            function animarContadores() {
                document.querySelectorAll('.stat-numero').forEach(contador => {
                    const objetivo = +contador.getAttribute('data-target');
                    const velocidad = 150;
                    const actualizarConteo = () => {
                        const actual = +contador.innerText;
                        const incremento = Math.max(objetivo / velocidad, 1);
                        if (actual < objetivo) {
                            contador.innerText = Math.ceil(actual + incremento);
                            setTimeout(actualizarConteo, 15);
                        } else {
                            contador.innerText = objetivo;
                        }
                    };
                    actualizarConteo();
                });
            }

            // Si venimos de enviar el formulario, hacemos scroll directo a esa sección.
            <?php if ($enviado): ?>
                document.getElementById('registro').scrollIntoView({ behavior: 'smooth' });
            <?php endif; ?>

            // Acordeón de FAQ (igual que antes).
            document.querySelectorAll('.faq-pregunta').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = btn.parentElement;
                    const estabaActivo = item.classList.contains('activo');
                    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('activo'));
                    if (!estabaActivo) item.classList.add('activo');
                });
            });

            // Mostrar/ocultar campos condicionales (detalle de estudio y trabajo).
            const selectEstudia = document.getElementById('estudia_actualmente');
            const selectTrabaja = document.getElementById('trabaja_actualmente');
            const grupoEstudia = document.getElementById('grupo-estudia-detalle');
            const grupoTrabaja = document.getElementById('grupo-trabaja-detalle');
            const grupoTrabajaOtro = document.getElementById('grupo-trabaja-otro');

            function toggleGruposCondicionales() {
                if (selectEstudia && grupoEstudia) {
                    const visible = selectEstudia.value !== 'No';
                    grupoEstudia.style.display = visible ? '' : 'none';
                    grupoEstudia.querySelectorAll('input, select').forEach(el => {
                        if (visible) el.setAttribute('required', 'required');
                        else el.removeAttribute('required');
                    });
                }
                if (selectTrabaja && grupoTrabaja) {
                    const visible = selectTrabaja.value !== 'No';
                    grupoTrabaja.style.display = visible ? '' : 'none';
                    grupoTrabaja.querySelectorAll('input, select').forEach(el => {
                        if (visible) el.setAttribute('required', 'required');
                        else el.removeAttribute('required');
                    });
                }
                if (selectTrabaja && grupoTrabajaOtro) {
                    grupoTrabajaOtro.style.display = selectTrabaja.value === 'Otra Área' ? '' : 'none';
                }
            }
            if (selectEstudia) selectEstudia.addEventListener('change', toggleGruposCondicionales);
            if (selectTrabaja) selectTrabaja.addEventListener('change', toggleGruposCondicionales);

            // Verificación AJAX del documento: detecta si ya existe en egresados.
            // (La API solo responde existe: bool — no expone datos personales.)
            const inputDoc   = document.getElementById('numero_documento');
            const docStatus  = document.getElementById('doc-status');
            let   timerDoc   = null;

            if (inputDoc && docStatus) {
                inputDoc.addEventListener('input', () => {
                    clearTimeout(timerDoc);
                    const val = inputDoc.value.trim();
                    if (val.length < 6) { docStatus.style.display = 'none'; return; }
                    timerDoc = setTimeout(() => {
                        fetch('api/check_documento.php?documento=' + encodeURIComponent(val))
                            .then(r => r.json())
                            .then(data => {
                                if (data.existe) {
                                    docStatus.className = 'doc-status doc-existe';
                                    docStatus.innerHTML = '📋 <strong>Documento registrado</strong> — Se creará una solicitud de <em>actualización</em> de datos.';
                                    docStatus.style.display = '';
                                } else {
                                    docStatus.className = 'doc-status doc-nuevo';
                                    docStatus.innerHTML = '🆕 <strong>Documento nuevo</strong> — Se creará una solicitud de <em>registro</em>.';
                                    docStatus.style.display = '';
                                }
                            })
                            .catch(() => { docStatus.style.display = 'none'; });
                    }, 400);
                });
            }
        });
    </script>
</body>
</html>
