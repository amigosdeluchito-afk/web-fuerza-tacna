<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

try {
    $db = get_db_connection();

    // Asegurar que la tabla existe (Previene fallas críticas si el administrador aún no abrió la web)
    $db->exec("CREATE TABLE IF NOT EXISTS panel_configuracion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(100) NOT NULL UNIQUE,
        valor MEDIUMTEXT NOT NULL,
        fecha_actualizacion DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmtC = $db->query("SELECT clave, valor FROM panel_configuracion WHERE clave IN ('sitio_privado_activo', 'sitio_privado_password')");
    $configs = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $activo = true;
    $password_real = 'FT666';

    foreach ($configs as $c) {
        if ($c['clave'] === 'sitio_privado_activo') $activo = ($c['valor'] === '1');
        if ($c['clave'] === 'sitio_privado_password') $password_real = $c['valor'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pass_ingresada = $_POST['pass'] ?? '';
        if ($pass_ingresada === $password_real) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Contraseña incorrecta']);
        }
        exit;
    }

    echo json_encode(['privado_activo' => $activo]);
} catch (Exception $e) {
    // Failsafe de seguridad: Si la base de datos se desconecta, desactivamos el escudo para no romper la web al público
    echo json_encode(['privado_activo' => false]);
}