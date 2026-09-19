-- bd_egresados.sql — v2: flujo de solicitudes con aprobación del admin
-- CAMBIOS DE FONDO respecto a la versión anterior:
--
-- 1) `usuarios` ya no tiene el rol 'egresado'. El único tipo de cuenta con
--    login es el staff (admin/rector/secretaria). El egresado nunca
--    inicia sesión: interactúa solo por el formulario público.
--
-- 2) `egresados` se reestructura para calzar con los campos reales del
--    formulario público (documento, nombres/apellidos, situación
--    académica/laboral). `numero_documento` es UNIQUE: es la clave que
--    usamos para saber si un envío del formulario es un registro nuevo
--    o una actualización de un egresado que ya existe.
--
-- 3) Tabla nueva `solicitudes`: cada envío del formulario público (nuevo
--    registro o edición) cae aquí como 'pendiente'. Los datos propuestos
--    se guardan en JSON. Un admin aprueba o rechaza; solo al aprobar se
--    escriben/actualizan los datos reales en `egresados`.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-05:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- usuarios: SOLO staff administrativo
-- --------------------------------------------------------

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','rector','secretaria') NOT NULL DEFAULT 'secretaria',
  `activo` tinyint(1) DEFAULT 1,
  `intentos_fallidos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_ultima_edicion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `activo`, `intentos_fallidos`, `bloqueado_hasta`, `fecha_registro`, `fecha_ultima_edicion`) VALUES
(1, 'Administrador', 'admin@inst.edu', '$2y$10$8iLEPPMVbfUTJs2gNsJOpOgdhTSeRceYdxSq8FHkLU0Rghqf8zqkC', 'admin', 1, 0, NULL, '2026-06-17 15:50:32', '2026-06-17 15:50:32'),
(2, 'Dilan Alexis Urueña Mateus', 'dilanuruenamateus@gmail.com', '$2y$10$RxiYqifkGNRtBfp1NmT1dOPVIhy/g/UE5yDLycm6MvOqTFm2yo5bS', 'admin', 1, 0, NULL, '2026-06-17 16:00:49', '2026-06-17 17:11:41');
-- NOTA: la cuenta por defecto admin@inst.edu usa la contraseña 'manzanera2026'
-- (cambiada el 12-ago-2026 porque la cuenta quedó bloqueada por intentos fallidos).
-- DEBES cambiarla apenas ingreses al panel (opción "Cambiar contraseña").
-- La cuenta dilanuruenamateus@gmail.com mantiene su contraseña original.

-- --------------------------------------------------------
-- egresados: datos reales, solo se escriben cuando una solicitud se aprueba
-- --------------------------------------------------------

CREATE TABLE `egresados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` enum('TI','CC','CE') NOT NULL DEFAULT 'CC',
  `numero_documento` varchar(20) NOT NULL,
  `genero` enum('Femenino','Masculino','Otro') NOT NULL DEFAULT 'Otro' COMMENT 'género autopercibido',
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `tecnica` enum('Desarrollo de Software','Contenidos Multimediales','Redes de Cómputo','Paneles Solares') NOT NULL,
  `anio_graduacion` year(4) NOT NULL,
  `estudia_actualmente` enum('No','SENA','Universidad','Otros') NOT NULL DEFAULT 'No',
  `institucion_estudia` varchar(150) DEFAULT NULL COMMENT 'institución/universidad donde estudia',
  `carrera_estudia` varchar(150) DEFAULT NULL COMMENT 'carrera o programa que cursa',
  `semestre_actual` varchar(30) DEFAULT NULL COMMENT 'semestre que cursa',
  `trabaja_actualmente` enum('No','Relacionado con la Técnica','Otra Área','Independiente') NOT NULL DEFAULT 'No',
  `empresa_trabaja` varchar(150) DEFAULT NULL COMMENT 'empresa donde trabaja',
  `cargo_trabaja` varchar(150) DEFAULT NULL COMMENT 'cargo que desempeña',
  `rango_salarial` varchar(50) DEFAULT NULL COMMENT 'rango salarial aproximado',
  `trabajo_otro` varchar(100) DEFAULT NULL COMMENT 'detalle cuando trabaja_actualmente = Otra Área',
  `director_curso` varchar(100) DEFAULT NULL,
  `edad_graduacion` tinyint(3) UNSIGNED DEFAULT NULL,
  `info_adicional` text DEFAULT NULL,
  `acepta_habeas_data` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'consentimiento Ley 1581 de 2012',
  `fecha_acepta_habeas_data` datetime DEFAULT NULL COMMENT 'fecha y hora del consentimiento',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_ultima_edicion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `idx_anio` (`anio_graduacion`),
  KEY `idx_tecnica` (`tecnica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `egresados` (`id`, `tipo_documento`, `numero_documento`, `genero`, `nombres`, `apellidos`, `correo`, `telefono`, `tecnica`, `anio_graduacion`, `estudia_actualmente`, `trabaja_actualmente`, `director_curso`, `edad_graduacion`, `info_adicional`) VALUES
(1, 'CC', '1012345678', 'Masculino', 'Nicolas', 'Rodriguez', 'Sebastianrondon@gmail.com', '893947199', 'Desarrollo de Software', '2026', 'No', 'Relacionado con la Técnica', 'Juan Pablol', 18, 'Trabajando en una empresa');

-- --------------------------------------------------------
-- solicitudes: cola de aprobación (registros nuevos y ediciones)
-- --------------------------------------------------------

CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('nueva','edicion') NOT NULL,
  `egresado_id` int(11) DEFAULT NULL COMMENT 'solo para tipo=edicion; NULL en tipo=nueva hasta que se aprueba',
  `numero_documento` varchar(20) NOT NULL COMMENT 'identifica al egresado entre distintos envíos del formulario',
  `datos` longtext NOT NULL COMMENT 'JSON con los campos propuestos por el egresado',
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `motivo` varchar(500) DEFAULT NULL COMMENT 'razón que dio el egresado al enviar/actualizar',
  `comentario_admin` varchar(300) DEFAULT NULL,
  `revisado_por` int(11) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_documento` (`numero_documento`),
  KEY `fk_solicitud_egresado` (`egresado_id`),
  KEY `fk_solicitud_revisor` (`revisado_por`),
  CONSTRAINT `fk_solicitud_egresado` FOREIGN KEY (`egresado_id`) REFERENCES `egresados` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitud_revisor` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

ALTER TABLE `egresados` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `usuarios` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `solicitudes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ==========================================================
-- Para crear una nueva cuenta de staff manualmente:
-- 1) genera un hash bcrypt en PHP:  echo password_hash('tu_clave', PASSWORD_BCRYPT);
-- 2) INSERT INTO usuarios (nombre, email, password, rol) VALUES
--    ('Nombre Apellido', 'correo@inst.edu', 'HASH_GENERADO', 'secretaria');
-- ==========================================================
