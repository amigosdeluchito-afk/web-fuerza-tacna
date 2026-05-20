<?php
// upload.php – Subida de imágenes:
// - Guarda/actualiza 1.webp..6.webp (máx 6)
// - Redimensiona (WEB_MAX)
// - Convierte a WEBP
// - Crea miniaturas en subcarpeta _thumb (THUMB_MAX)

require_once __DIR__ . '/config.php';
$accion = $_POST['accion'] ?? $_POST['action'] ?? $_GET['accion'] ?? $_GET['action'] ?? 'subir';
if ($accion !== 'subir') {
    json_fail('Acción no soportada');
}



header('Content-Type: application/json; charset=utf-8');

function json_fail(string $msg, int $code = 200, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}


function mime_to_loader(string $mime): ?callable {
    return match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => (function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null),
        default      => null,
    };
}

function resize_to_max($srcImg, int $maxSide) {
    $w = imagesx($srcImg);
    $h = imagesy($srcImg);
    if ($w <= 0 || $h <= 0) return null;

    if (max($w, $h) <= $maxSide) {
        // ya está dentro del máximo, clonamos a truecolor para asegurar alpha/format
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopy($dst, $srcImg, 0, 0, 0, 0, $w, $h);
        return $dst;
    }

    if ($w >= $h) {
        $nw = $maxSide;
        $nh = (int) round(($h * $maxSide) / $w);
    } else {
        $nh = $maxSide;
        $nw = (int) round(($w * $maxSide) / $h);
    }

    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function save_webp($img, string $path, int $quality = 82): bool {
    if (!function_exists('imagewebp')) return false;
    return imagewebp($img, $path, $quality);
}

try {
    require_login();

 if ($accion !== 'subir') {
    json_fail('Acción no soportada');
}


    $segmento = trim($_POST['segmento'] ?? '');
    $carpeta  = trim($_POST['carpeta'] ?? '');

    if ($segmento === '' || $carpeta === '') {
        json_fail('Faltan datos: segmento/carpeta');
    }
    
    // Prevenir Path Traversal
    if (strpos($segmento, '..') !== false || strpos($carpeta, '..') !== false) {
        json_fail('Nombres de segmento o carpeta no válidos');
    }

$filesKey = null;
if (isset($_FILES['fotos'])) $filesKey = 'fotos';
elseif (isset($_FILES['files'])) $filesKey = 'files';

if ($filesKey === null) {
    json_fail('No llegaron archivos');
}

$FILES = $_FILES[$filesKey];


    // Carpeta destino: .../IMG/fotos-obras/<segmento>/<carpeta>
    $destDir = rtrim($GLOBALS['FOTOS_BASE'], '/\\') . '/' . $segmento . '/' . $carpeta;
    ensure_dir($destDir);

    



    // Detectar slots ocupados (1..6.webp)
    $ocupados = [];
    for ($i = 1; $i <= 6; $i++) {
        if (file_exists($destDir . "/$i.webp")) $ocupados[$i] = true;
    }

    $subidas = 0;
    $errores = [];


$names = $FILES['name'];
$tmps  = $FILES['tmp_name'];
$errs  = $FILES['error'];



    // Normalizar a array
    if (!is_array($names)) {
        $names = [$names];
        $tmps  = [$tmps];
        $errs  = [$errs];
    }

    if (!function_exists('imagecreatetruecolor')) {
        json_fail('Tu PHP no tiene GD habilitado (falta extensión gd).');
    }
    if (!function_exists('imagewebp')) {
        json_fail('Tu GD no soporta WEBP (imagewebp no existe).');
    }
$seenHashes = [];
    foreach ($names as $k => $originalName) {
        if (($errs[$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errores[] = "$originalName: error de subida";
            continue;
        }

        // Buscar siguiente slot libre 1..6
        $slot = null;
        for ($i = 1; $i <= 6; $i++) {
            if (empty($ocupados[$i])) { $slot = $i; break; }
        }
        if ($slot === null) {
            $errores[] = "$originalName: ya hay 6 fotos";
            continue;
        }

        $tmpPath = $tmps[$k];
        $info = @getimagesize($tmpPath);
        if (!$info || empty($info['mime'])) {
            $errores[] = "$originalName: archivo no es imagen válida";
            continue;
        }

        $hash = @md5_file($tmpPath);
if ($hash && isset($seenHashes[$hash])) {
    // mismo archivo enviado otra vez en el mismo request -> lo saltamos
    continue;
}
if ($hash) $seenHashes[$hash] = true;

        $loader = mime_to_loader($info['mime']);
        if (!$loader || !function_exists($loader)) {
            $errores[] = "$originalName: formato no soportado ({$info['mime']})";
            continue;
        }

        $src = @$loader($tmpPath);
        if (!$src) {
            $errores[] = "$originalName: no se pudo leer la imagen";
            continue;
        }

        // Redimensionar principal
        $maxWeb = defined('WEB_MAX') ? (int)WEB_MAX : 1600;
        $dstWeb = resize_to_max($src, $maxWeb);
        if (!$dstWeb) {
            imagedestroy($src);
            $errores[] = "$originalName: no se pudo redimensionar";
            continue;
        }

  $webPath = $destDir . "/$slot.webp";

// 1️⃣ Guardar la imagen principal
if (!save_webp($dstWeb, $webPath, 82)) {
    imagedestroy($dstWeb);
    $errores[] = "No se pudo guardar la imagen principal";
    continue;
}

// ✅ SOLO 1 thumb por obra: thumb.webp (se genera solo si se guardó el slot 1)
if ($slot === 1) {
    $thumbPath = $destDir . "/1.thumb.webp";

    $maxThumb = defined('THUMB_MAX') ? (int)THUMB_MAX : 480;
    $thumbImg = resize_to_max($dstWeb, $maxThumb);

    if ($thumbImg) {
        save_webp($thumbImg, $thumbPath, 80);
        imagedestroy($thumbImg);
    }
}




        imagedestroy($src);
        imagedestroy($dstWeb);

        $ocupados[$slot] = true;
        $subidas++;
    }

    echo json_encode([
        'ok'      => ($subidas > 0),
        'subidas' => $subidas,
        'errores' => $errores,
        'destino' => $destDir
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    json_fail('Error interno: ' . $e->getMessage(), 500);
}
