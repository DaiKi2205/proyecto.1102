<?php
/**
 * Incluir esto al inicio de CUALQUIER archivo dentro de /admin.
 * Centraliza las rutas (evita repetir '../config/...' en cada archivo)
 * y corta la ejecución si no hay una sesión de staff activa.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login('../login.php');
