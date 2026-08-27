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

function radar_header_status(string $key): string {
    return isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '' ? 'PRESENTE' : 'AUSENTE';
}

function radar_mask_ip(string $ip): string {
    $ip = trim($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $prefix = implode(':', array_slice($parts, 0, 2));
        return $prefix . ':xxxx:xxxx:xxxx';
    }

    return 'formato no reconocido';
}

function radar_compare_header_to_remote(string $key): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $header = trim((string)($_SERVER[$key] ?? ''));

    if ($remote === '' || $header === '') {
        return 'NO COMPARABLE';
    }

    if ($key === 'HTTP_X_FORWARDED_FOR') {
        $parts = array_map('trim', explode(',', $header));
        $header = $parts[0] ?? '';
        if (count($parts) !== 1) {
            return 'NO COMPARABLE';
        }
    }

    return hash_equals($remote, $header) ? 'IGUALES' : 'DIFERENTES';
}

$remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$remoteAddrMasked = $remoteAddr !== '' ? radar_mask_ip($remoteAddr) : 'AUSENTE';
$cfComparison = radar_compare_header_to_remote('HTTP_CF_CONNECTING_IP');
$xffComparison = radar_compare_header_to_remote('HTTP_X_FORWARDED_FOR');

echo "<h3 style='font-family:sans-serif;'>Diagnostico de proxy/IP</h3>";
echo "<ul style='font-family:sans-serif;'>";
echo "<li><b>REMOTE_ADDR:</b> " . radar_header_status('REMOTE_ADDR') . " (" . htmlspecialchars($remoteAddrMasked, ENT_QUOTES, 'UTF-8') . ")</li>";
echo "<li><b>CF-Connecting-IP:</b> " . radar_header_status('HTTP_CF_CONNECTING_IP') . "</li>";
echo "<li><b>X-Forwarded-For:</b> " . radar_header_status('HTTP_X_FORWARDED_FOR') . "</li>";
echo "<li><b>X-Real-IP:</b> " . radar_header_status('HTTP_X_REAL_IP') . "</li>";
echo "<li><b>REMOTE_ADDR y CF-Connecting-IP son:</b> " . $cfComparison . "</li>";
echo "<li><b>REMOTE_ADDR y X-Forwarded-For simple son:</b> " . $xffComparison . "</li>";
echo "</ul>";
echo "<p style='font-family:sans-serif;'>Si REMOTE_ADDR coincide con la IP real del cliente, puede utilizarse para rate limiting. Si REMOTE_ADDR corresponde al proxy/CDN y otro header contiene al cliente, necesitaremos un resolver de proxy confiable.</p>";
echo "<p style='font-family:sans-serif;'><i>No se afirma automaticamente que una IP pertenezca a Cloudflare sin validarla contra rangos oficiales.</i></p>";

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
