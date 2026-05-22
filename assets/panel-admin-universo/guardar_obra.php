<?php
// 1. Mostrar todos los errores para saber exactamente qué falla en el servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Seguridad e inicio de sesión
require_once __DIR__ . '/config.php';
require_login();

// 3. Verificaciones estrictas para evitar pantalla en blanco (Error 500)
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("<div style='background:#fff; padding:20px;'><h2 style='color:red;'>Error Crítico: No se encontró la carpeta 'vendor'</h2><p>Parece que la librería de Google no se subió a tu servidor. Esto suele pasar porque GitHub a veces ignora la carpeta 'vendor' por defecto. Sube la carpeta 'vendor' manualmente a tu servidor desde XAMPP.</p></div>");
}

$rutaCredenciales = __DIR__ . '/data/credenciales.json';
if (!file_exists($rutaCredenciales)) {
    die("<div style='background:#fff; padding:20px;'><h2 style='color:red;'>Error Crítico: No se encontró credenciales.json</h2><p>Debes subir este archivo manualmente a <b>assets/panel-admin-universo/data/</b> en tu servidor.</p></div>");
}

require_once __DIR__ . '/vendor/autoload.php';

$spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        
        $client->setAuthConfig($rutaCredenciales);
        $service = new \Google_Service_Sheets($client);

        $segmento = $_POST['segmento'] ?? 'EDUCACION';
        $nombre   = $_POST['nombre'] ?? '';
        $estado   = $_POST['estado'] ?? '';
        $monto    = $_POST['monto'] ?? '';
        $distrito = $_POST['distrito'] ?? '';
        $provincia= $_POST['provincia'] ?? '';
        
        // Forzar a decimal estricto para evitar que Google Sheets confunda el punto con separador de miles
        $x        = (float) str_replace(',', '.', $_POST['x'] ?? '0');
        $y        = (float) str_replace(',', '.', $_POST['y'] ?? '0');
        
        // ¡MAGIA DE AUTOMATIZACIÓN! Crea el nombre de la carpeta de forma limpia.
        // Ej: "Creación de Colegio" -> "creacion-de-colegio"
        $carpeta = slugify($nombre); 
        $descripcion = trim($_POST['descripcion'] ?? '');

        $values = [
            [$nombre, $estado, $monto, $x, $y, $provincia, $distrito, $carpeta, $descripcion]
        ];

        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'RAW'];

        $result = $service->spreadsheets_values->append($spreadsheetId, $segmento, $body, $params);

        log_action('obra_agregar', "Agregó nueva obra: $nombre en $segmento");

        // --- MAGIA NUEVA: PROCESAR FOTOS SI SE SUBIERON DESDE LA CREACIÓN ---
        if (!empty($_FILES['fotos']['name'][0])) {
            // Usar strtolower en segmento porque tus carpetas (ej. /educacion/) están en minúscula
            $destDir = rtrim($GLOBALS['FOTOS_BASE'], '/\\') . '/' . strtolower($segmento) . '/' . $carpeta;
            ensure_dir($destDir);

            // Funciones de procesamiento de imágenes
            if (!function_exists('mime_to_loader')) {
                function mime_to_loader(string $mime): ?callable {
                    return match ($mime) {
                        'image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
                        'image/gif' => 'imagecreatefromgif', 'image/webp' => (function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null),
                        default => null,
                    };
                }
                function resize_to_max($srcImg, int $maxSide) {
                    $w = imagesx($srcImg); $h = imagesy($srcImg);
                    if ($w <= 0 || $h <= 0) return null;
                    if (max($w, $h) <= $maxSide) {
                        $dst = imagecreatetruecolor($w, $h);
                        imagealphablending($dst, false); imagesavealpha($dst, true);
                        imagecopy($dst, $srcImg, 0, 0, 0, 0, $w, $h);
                        return $dst;
                    }
                    if ($w >= $h) { $nw = $maxSide; $nh = (int) round(($h * $maxSide) / $w); } 
                    else { $nh = $maxSide; $nw = (int) round(($w * $maxSide) / $h); }
                    $dst = imagecreatetruecolor($nw, $nh);
                    imagealphablending($dst, false); imagesavealpha($dst, true);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    return $dst;
                }
                function save_webp($img, string $path, int $quality = 82): bool {
                    if (!function_exists('imagewebp')) return false;
                    return imagewebp($img, $path, $quality);
                }
            }

            $names = $_FILES['fotos']['name']; $tmps = $_FILES['fotos']['tmp_name']; $errs = $_FILES['fotos']['error'];
            if (!is_array($names)) { $names = [$names]; $tmps = [$tmps]; $errs = [$errs]; }

            $slot = 1;
            foreach ($names as $k => $originalName) {
                if ($slot > 6) break; // Límite de 6 fotos
                if (($errs[$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

                $tmpPath = $tmps[$k]; $info = @getimagesize($tmpPath);
                if (!$info || empty($info['mime'])) continue;
                $loader = mime_to_loader($info['mime']); if (!$loader || !function_exists($loader)) continue;
                $src = @$loader($tmpPath); if (!$src) continue;

                $dstWeb = resize_to_max($src, defined('WEB_MAX') ? (int)WEB_MAX : 1600);
                if (!$dstWeb) { imagedestroy($src); continue; }

                if (save_webp($dstWeb, $destDir . "/$slot.webp", 82)) {
                    if ($slot === 1) { // Miniaura para la foto principal
                        $thumbImg = resize_to_max($dstWeb, defined('THUMB_MAX') ? (int)THUMB_MAX : 480);
                        if ($thumbImg) { save_webp($thumbImg, $destDir . "/1.thumb.webp", 80); imagedestroy($thumbImg); }
                    }
                    $slot++;
                }
                imagedestroy($src); imagedestroy($dstWeb);
            }
        }
        // --- FIN MAGIA NUEVA ---

        header("Location: agregar_obra.php?success=1");
        exit;

    } catch (Throwable $e) { // Throwable atrapa TODO (Errores 500, fatales, sintaxis, etc)
        die("<div style='background: #fff; padding: 20px; color: #333; font-family: Arial;'><h2 style='color: red;'>Error detectado en PHP:</h2><pre style='background:#f4f4f4; padding: 10px; border-left: 4px solid red;'>" . $e->getMessage() . " en la línea " . $e->getLine() . "</pre><br><a href='agregar_obra.php'>Volver atrás</a></div>");
    }
}