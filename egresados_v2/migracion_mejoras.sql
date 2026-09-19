-- migracion_mejoras.sql
-- Ejecutar UNA sola vez sobre la base de datos existente (bd_egresados)
-- para habilitar las nuevas funciones:
--   1) columna trabajo_otro (detalle cuando se elige "Otra Área" en trabajo)
--   2) columna motivo en solicitudes (razón que da el egresado al actualizar)
--   3) campos condicionales de estudio/trabajo en detalle
--   4) consentimiento de tratamiento de datos (Ley 1581 de 2012)
--   5) nueva contraseña para la cuenta admin por defecto (Manzanera.2026)
--      => cámbiala desde el panel con la opción "Cambiar contraseña"
--   6) columna genero (para los gráficos del panel administrativo)

ALTER TABLE `egresados`
    ADD COLUMN `trabajo_otro` varchar(100) DEFAULT NULL COMMENT 'detalle cuando trabaja_actualmente = Otra Área' AFTER `trabaja_actualmente`;

-- Campos condicionales del formulario (estudio y trabajo en detalle)
ALTER TABLE `egresados`
    ADD COLUMN `institucion_estudia` varchar(150) DEFAULT NULL COMMENT 'institución/universidad donde estudia' AFTER `estudia_actualmente`,
    ADD COLUMN `carrera_estudia` varchar(150) DEFAULT NULL COMMENT 'carrera o programa que cursa' AFTER `institucion_estudia`,
    ADD COLUMN `semestre_actual` varchar(30) DEFAULT NULL COMMENT 'semestre que cursa' AFTER `carrera_estudia`,
    ADD COLUMN `empresa_trabaja` varchar(150) DEFAULT NULL COMMENT 'empresa donde trabaja' AFTER `trabaja_actualmente`,
    ADD COLUMN `cargo_trabaja` varchar(150) DEFAULT NULL COMMENT 'cargo que desempeña' AFTER `empresa_trabaja`,
    ADD COLUMN `rango_salarial` varchar(50) DEFAULT NULL COMMENT 'rango salarial aproximado' AFTER `cargo_trabaja`;

ALTER TABLE `solicitudes`
    ADD COLUMN `motivo` varchar(500) DEFAULT NULL COMMENT 'razón que dio el egresado al enviar/actualizar' AFTER `estado`;

-- Consentimiento de tratamiento de datos personales (Ley 1581 de 2012)
ALTER TABLE `egresados`
    ADD COLUMN `acepta_habeas_data` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'consentimiento Ley 1581 de 2012' AFTER `info_adicional`,
    ADD COLUMN `fecha_acepta_habeas_data` datetime DEFAULT NULL COMMENT 'fecha y hora del consentimiento' AFTER `acepta_habeas_data`;

UPDATE `usuarios`
    SET `password` = '$2y$10$cTsZBTSTzqPe0.9khpelpuLXL8u4W7TRzgM0j4d0qBoBBTdxUy.O2'
    WHERE `email` = 'admin@inst.edu';

-- Género autopercibido (usado en los gráficos del panel: género por técnica,
-- técnica más/menos elegida por año). Los registros existentes quedan en 'Otro'.
ALTER TABLE `egresados`
    ADD COLUMN `genero` enum('Femenino','Masculino','Otro') NOT NULL DEFAULT 'Otro' COMMENT 'género autopercibido' AFTER `numero_documento`;
