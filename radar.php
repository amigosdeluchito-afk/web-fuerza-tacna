<?php
// Registrar todos los errores sin mostrarlos publicamente.
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/assets/panel-admin-universo/config.php';

if (!is_admin()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

echo "<h2 style='font-family:sans-serif;'>📡 Radar de Servidor</h2>";
echo "<p style='font-family:sans-serif;'><b>Versión de PHP:</b> " . phpversion() . "</p>";
$privateConfigStatus = (defined('PRIVATE_CONFIG_ACTIVE') && PRIVATE_CONFIG_ACTIVE) ? 'ACTIVA' : 'FALLBACK';
echo "<p style='font-family:sans-serif;'><b>Configuración privada:</b> " . $privateConfigStatus . "</p>";

echo "<h3 style='font-family:sans-serif;'>Carpetas encontradas aquí:</h3><ul>";
$dirs = array_filter(glob('*'), 'is_dir');
if (empty($dirs)) {
    echo "<li><i>No hay carpetas, solo archivos.</i></li>";
} else {
    foreach($dirs as $d) echo "<li style='font-family:sans-serif;'>📁 $d</li>";
}
echo "</ul>";

echo "<h3 style='font-family:sans-serif;'>Buscando tu panel...</h3>";
$rutas_posibles = [
    'assets/panel-admin-universo/login.php',
    'fuerza_tacna/assets/panel-admin-universo/login.php',
    'panel-admin-universo/login.php',
    'login.php'
];

$encontrado = false;
foreach($rutas_posibles as $r) {
    if(file_exists($r)) {
        echo "<p style='color:green; font-family:sans-serif; font-size: 18px;'><b>¡Panel encontrado!</b> Tu link exacto es:<br>👉 <a href='$r'>Haz clic aquí para entrar</a></p>";
        $encontrado = true;
        break;
    }
}
if(!$encontrado) echo "<p style='color:red; font-family:sans-serif;'><b>No se encontró el archivo login.php.</b> Revisa las carpetas de arriba para ver dónde se subieron tus archivos.</p>";
?>
