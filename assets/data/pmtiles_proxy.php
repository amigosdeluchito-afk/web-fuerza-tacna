<?php
// Desactivar cualquier compresión de PHP/Apache dinámicamente
@ini_set('zlib.output_compression', 'Off');
@apache_setenv('no-gzip', 1);
@apache_setenv('no-brotli', 1);

// Limpiar cualquier buffer de salida activo para evitar Transfer-Encoding: chunked
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Apuntar al archivo físico de PMTiles en la misma carpeta
$file = __DIR__ . '/tacna.pmtiles';

if (!file_exists($file)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$filesize = filesize($file);
$lastModified = filemtime($file);

// Cabeceras obligatorias para MapLibre / PMTiles
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
header("Access-Control-Allow-Headers: Range");
header("Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges");
header("Accept-Ranges: bytes");
header("Content-Type: application/octet-stream");
header("Cache-Control: public, max-age=31536000, no-transform");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$start = 0;
$end = $filesize - 1;
$is_range = false;

// Procesar la cabecera Range (ej: bytes=0-1023)
if (isset($_SERVER['HTTP_RANGE'])) {
    $is_range = true;
    preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
    if (isset($matches[1]) && is_numeric($matches[1])) {
        $start = intval($matches[1]);
    }
    if (isset($matches[2]) && is_numeric($matches[2])) {
        $end = intval($matches[2]);
    }
}

if ($start > $end || $start >= $filesize || $end >= $filesize) {
    header("HTTP/1.1 416 Range Not Satisfiable");
    header("Content-Range: bytes */$filesize");
    exit;
}

$length = $end - $start + 1;

// 🚨 LA CLAVE DE TODO: Forzar Status 206 y Content-Length Exacto
if ($is_range) {
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$filesize");
} else {
    header("HTTP/1.1 200 OK");
}

header("Content-Length: $length");

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

// Streaming binario exacto sin iteraciones que puedan modificar el header
$fp = @fopen($file, 'rb');
if ($fp) {
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
}