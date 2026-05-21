<?php
// fotos_api.php – API para listar / eliminar / marcar principal / descargar ZIP
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

// Usamos la ruta base de fotos definida en config.php
global $FOTOS_BASE;

/**
 * Construye la ruta completa a la carpeta de una obra
 */
function get_obra_dir($segmento, $carpeta) {
    global $FOTOS_BASE;
    $segmento = trim($segmento);
    $carpeta  = trim($carpeta);

    if ($segmento === '' || $carpeta === '') {
        return false;
    }

    $dir = $FOTOS_BASE . '/' . $segmento . '/' . $carpeta;
    return $dir;
}

/**
 * Lista las imágenes de una obra
 */
function handle_listar($segmento, $carpeta) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        echo json_encode([
            'ok'        => true,
            'fotos'     => [],
            'total'     => 0,
            'totalSize' => 0
        ]);
        return;
    }

    $patron = $dir . '/*.{jpg,jpeg,png,webp,gif}';
    $files = glob($patron, GLOB_BRACE);

    if (!$files) {
        echo json_encode([
            'ok'        => true,
            'fotos'     => [],
            'total'     => 0,
            'totalSize' => 0
        ]);
        return;
    }

    // Orden natural (1.webp, 2.webp, etc.)
    natsort($files);
    $files = array_values($files);

    $totalSize = 0;
    $fotos = [];

    foreach ($files as $idx => $path) {
        // Excluir el archivo de miniatura
        if (strpos($path, '.thumb.') !== false) {
            continue;
        }

        $basename = basename($path);
        $size_kb  = round(filesize($path) / 1024, 1);
        $totalSize += $size_kb;

        // Principal: el que tiene nombre que empieza con "1."
        $es_principal = preg_match('/^1\./', $basename) === 1;

        // URL pública (ajustada a la raíz de tu dominio en Internet)
        $url = "/assets/universoobras/IMG/fotos-obras/" .
            rawurlencode($segmento) . "/" .
            rawurlencode($carpeta) . "/" .
            rawurlencode($basename) . "?v=" . time();

        // Buscar si existe su versión miniatura (.thumb.webp)
        $thumb_basename = preg_replace('/\.([a-zA-Z0-9]+)$/', '.thumb.$1', $basename);
        $thumb_url = $url; // Por defecto usamos la original como respaldo
        if (file_exists($dir . '/' . $thumb_basename)) {
            $thumb_url = "/assets/universoobras/IMG/fotos-obras/" .
                rawurlencode($segmento) . "/" .
                rawurlencode($carpeta) . "/" .
                rawurlencode($thumb_basename) . "?v=" . time();
        }

        $fotos[] = [
            'url'          => $url,
            'thumb_url'    => $thumb_url,
            'size_kb'      => $size_kb,
            'es_principal' => $es_principal,
        ];
    }

    echo json_encode([
        'ok'        => true,
        'fotos'     => $fotos,
        'total'     => count($fotos),
        'totalSize' => $totalSize
    ]);
}

/**
 * Eliminar una foto por número (1-based, según orden)
 */
function handle_eliminar($segmento, $carpeta, $numero) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'Carpeta no encontrada']);
        return;
    }

    $patron = $dir . '/*.{jpg,jpeg,png,webp,gif}';
    $files  = glob($patron, GLOB_BRACE);
    if (!$files) {
        echo json_encode(['ok' => false, 'error' => 'No hay fotos en la obra']);
        return;
    }

    // Excluir miniatura del procesamiento
    $files = array_filter($files, fn($f) => strpos($f, '.thumb.') === false);

    natsort($files);
    $files = array_values($files);

    $idx = $numero - 1;
    if (!isset($files[$idx])) {
        echo json_encode(['ok' => false, 'error' => 'Número de foto inválido']);
        return;
    }

    $file = $files[$idx];
    if (!unlink($file)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo eliminar el archivo']);
        return;
    }

    // Renumerar archivos restantes como 1,2,3,... manteniendo orden
    $restantes = glob($patron, GLOB_BRACE);
    $restantes = array_filter($restantes, fn($f) => strpos($f, '.thumb.') === false);
    natsort($restantes);
    $restantes = array_values($restantes);

    $tempMap = [];
    // Primero renombramos a nombres temporales para evitar colisiones
    foreach ($restantes as $p) {
        $dirName  = dirname($p);
        $baseName = basename($p);
        $tmpName  = $dirName . '/tmp_' . uniqid() . '_' . $baseName;
        if (rename($p, $tmpName)) {
            $tempMap[] = $tmpName;
        }
    }

    // Luego renombramos secuencialmente
    $i = 1;
    foreach ($tempMap as $tmpPath) {
        $ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
        $newPath = $dir . '/' . $i . '.' . $ext;
        rename($tmpPath, $newPath);
        $i++;
    }

    // Recrear la miniatura con la nueva imagen principal
    @unlink($dir . '/1.thumb.webp');
    clearstatcache(true, $dir . '/1.webp');
    if (file_exists($dir . '/1.webp')) {
        copy($dir . '/1.webp', $dir . '/1.thumb.webp');
    }

    echo json_encode(['ok' => true]);
}

function handle_eliminar_todas($segmento, $carpeta) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'Carpeta no encontrada']);
        return;
    }

    $deleted = 0;

    $items = scandir($dir);
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;

        $path = $dir . '/' . $name;
        if (!is_file($path)) continue;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Borra imágenes normales + cualquier archivo que contenga ".thumb"
        $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
        $isThumb = (stripos($name, '.thumb') !== false);

        if ($isImage || $isThumb) {
            if (@unlink($path)) $deleted++;
        }
    }

    echo json_encode(['ok' => true, 'deleted' => $deleted]);
}


/**
 * Marcar como principal: reordena/renombra las fotos para que
 * la elegida sea la 1.* y las demás sigan en orden.
 */
function handle_principal($segmento, $carpeta, $numero) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'Carpeta no encontrada']);
        return;
    }

    $patron = $dir . '/*.{jpg,jpeg,png,webp,gif}';
    $files  = glob($patron, GLOB_BRACE);
    if (!$files) {
        echo json_encode(['ok' => false, 'error' => 'No hay fotos']);
        return;
    }

    // Excluir miniatura del procesamiento
    $files = array_filter($files, fn($f) => strpos($f, '.thumb.') === false);

    natsort($files);
    $files = array_values($files);

    $idx = $numero - 1;
    if (!isset($files[$idx])) {
        echo json_encode(['ok' => false, 'error' => 'Número de foto inválido']);
        return;
    }

    // Reordenamos: seleccionada primero, luego el resto
    $selected = $files[$idx];
    $ordered  = [$selected];

    foreach ($files as $k => $p) {
        if ($k === $idx) continue;
        $ordered[] = $p;
    }

    // Renombrar en dos pasos para evitar colisiones
    $tempMap = [];
    foreach ($ordered as $p) {
        $dirName  = dirname($p);
        $baseName = basename($p);
        $tmpName  = $dirName . '/tmp_' . uniqid() . '_' . $baseName;
        if (rename($p, $tmpName)) {
            $tempMap[] = $tmpName;
        }
    }

    $i = 1;
    foreach ($tempMap as $tmpPath) {
        $ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
        $newPath = $dir . '/' . $i . '.' . $ext;
        rename($tmpPath, $newPath);
        $i++;
    }

    // Recrear la miniatura con la nueva imagen principal
    @unlink($dir . '/1.thumb.webp');
    clearstatcache(true, $dir . '/1.webp');
    if (file_exists($dir . '/1.webp')) {
        copy($dir . '/1.webp', $dir . '/1.thumb.webp');
    }

    echo json_encode(['ok' => true]);
}

/**
 * Reordenar fotos mediante Drag & Drop
 * $orden es un array con los números antiguos en su nuevo orden. Ej: [3, 1, 2]
 */
function handle_reordenar($segmento, $carpeta, $orden) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'Carpeta no encontrada']);
        return;
    }

    if (!is_array($orden) || empty($orden)) {
        echo json_encode(['ok' => false, 'error' => 'Orden inválido']);
        return;
    }

    $patron = $dir . '/*.{jpg,jpeg,png,webp,gif}';
    $files  = glob($patron, GLOB_BRACE);
    if (!$files) {
        echo json_encode(['ok' => false, 'error' => 'No hay fotos']);
        return;
    }

    // Excluir miniatura del procesamiento
    $files = array_filter($files, fn($f) => strpos($f, '.thumb.') === false);

    natsort($files);
    $files = array_values($files);

    if (count($files) !== count($orden)) {
        echo json_encode(['ok' => false, 'error' => 'La cantidad de fotos no coincide']);
        return;
    }

    // Crear arreglo ordenado basado en los índices pasados
    $ordered = [];
    foreach ($orden as $numAnterior) {
        $idx = $numAnterior - 1;
        if (!isset($files[$idx])) {
            echo json_encode(['ok' => false, 'error' => 'Número de foto inválido en el orden']);
            return;
        }
        $ordered[] = $files[$idx];
    }

    // Renombrar en dos pasos para evitar colisiones
    $tempMap = [];
    foreach ($ordered as $p) {
        $dirName  = dirname($p);
        $baseName = basename($p);
        $tmpName  = $dirName . '/tmp_' . uniqid() . '_' . $baseName;
        if (rename($p, $tmpName)) {
            $tempMap[] = $tmpName;
        }
    }

    $i = 1;
    foreach ($tempMap as $tmpPath) {
        $ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
        $newPath = $dir . '/' . $i . '.' . $ext;
        rename($tmpPath, $newPath);
        $i++;
    }

    // Recrear la miniatura con la nueva imagen principal
    @unlink($dir . '/1.thumb.webp');
    clearstatcache(true, $dir . '/1.webp');
    if (file_exists($dir . '/1.webp')) {
        copy($dir . '/1.webp', $dir . '/1.thumb.webp');
    }

    echo json_encode(['ok' => true]);
}

/**
 * Cuenta las fotos de todas las carpetas dentro de un segmento
 */
function handle_contar_segmento($segmento) {
    global $FOTOS_BASE;
    $segmento = trim($segmento);
    if ($segmento === '') {
        echo json_encode(['ok' => false, 'error' => 'Segmento inválido']);
        return;
    }

    $dir = $FOTOS_BASE . '/' . $segmento;
    $conteos = [];

    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $obraDir = $dir . '/' . $item;
            if (is_dir($obraDir)) {
                $patron = $obraDir . '/*.{jpg,jpeg,png,webp,gif}';
                $files = glob($patron, GLOB_BRACE);
                if ($files) {
                    // Excluir miniaturas del conteo
                    $files = array_filter($files, fn($f) => strpos($f, '.thumb.') === false);
                    $conteos[$item] = count($files);
                } else {
                    $conteos[$item] = 0;
                }
            }
        }
    }

    echo json_encode(['ok' => true, 'conteos' => $conteos]);
}


/**
 * Descargar ZIP con todas las fotos
 */
function handle_download_zip($segmento, $carpeta) {
    $dir = get_obra_dir($segmento, $carpeta);
    if (!$dir || !is_dir($dir)) {
        http_response_code(404);
        echo "Carpeta no encontrada";
        return;
    }

    $patron = $dir . '/*.{jpg,jpeg,png,webp,gif}';
    $files  = glob($patron, GLOB_BRACE);
    if (!$files) {
        http_response_code(404);
        echo "No hay fotos para descargar";
        return;
    }

    // Excluir miniatura del ZIP
    $files = array_filter($files, fn($f) => strpos($f, '.thumb.') === false);

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo "ZipArchive no está disponible en este servidor";
        return;
    }

    $zip = new ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), 'obra_zip_');

    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo "No se pudo crear el ZIP";
        return;
    }

    natsort($files);
    foreach ($files as $path) {
        $zip->addFile($path, basename($path));
    }

    $zip->close();

    $nombreZip = $segmento . '_' . $carpeta . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nombreZip . '"');
    header('Content-Length: ' . filesize($tmpZip));

    readfile($tmpZip);
    unlink($tmpZip);
    exit;
}

// =========================
//   ENRUTADOR
// =========================

// Descarga ZIP por GET
if (isset($_GET['download_zip'])) {
    $segmento = $_GET['segmento'] ?? '';
    $carpeta  = $_GET['carpeta'] ?? '';
    handle_download_zip($segmento, $carpeta);
    exit;
}

// Resto de acciones por POST
$action   = $_POST['action']   ?? '';
$segmento = $_POST['segmento'] ?? '';
$carpeta  = $_POST['carpeta']  ?? '';



switch ($action) {
  case 'listar':
    handle_listar($segmento, $carpeta);
    break;

  case 'eliminar':
    $numero = isset($_POST['numero']) ? (int)$_POST['numero'] : 0;
    handle_eliminar($segmento, $carpeta, $numero);
    break;

  case 'principal':
    $numero = isset($_POST['numero']) ? (int)$_POST['numero'] : 0;
    handle_principal($segmento, $carpeta, $numero);
    break;

  case 'reordenar':
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : [];
    handle_reordenar($segmento, $carpeta, $orden);
    break;

  case 'eliminar_todas':
    handle_eliminar_todas($segmento, $carpeta);
    break;

  case 'contar_segmento':
    handle_contar_segmento($segmento);
    break;

  default:
    echo json_encode(['ok' => false, 'error' => 'Acción no soportada']);
    break;
}
