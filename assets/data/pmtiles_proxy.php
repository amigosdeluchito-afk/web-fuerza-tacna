<?php
// Desactivar compresión nativa de PHP de forma segura
@ini_set('zlib.output_compression', 'Off');

// Destruir cualquier buffer activo para evitar Transfer-Encoding: chunked
while (ob_get_level() > 0) {
    ob_end_clean();
}

$file = __DIR__ . '/tacna.pmtiles';

if (!file_exists($file)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$filesize = filesize($file);

$start = 0;
$end = $filesize - 1;
$is_range = false;

// Procesar la cabecera Range
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
    $is_range = true;
    if (isset($matches[1]) && is_numeric($matches[1])) $start = intval($matches[1]);
    if (isset($matches[2]) && is_numeric($matches[2])) $end = intval($matches[2]);
}

if ($start > $end || $start >= $filesize || $end >= $filesize) {
    header("HTTP/1.1 416 Range Not Satisfiable");
    header("Content-Range: bytes */$filesize");
    exit;
}

$length = $end - $start + 1;

// Cabeceras de control estricto
header("Access-Control-Allow-Origin: *");
header("Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges");
header("Accept-Ranges: bytes");
header("Content-Type: application/octet-stream");
header("Cache-Control: public, max-age=31536000, no-transform");

if ($is_range) {
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$filesize");
} else {
    header("HTTP/1.1 200 OK");
}

header("Content-Length: " . $length);

if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    $fp = @fopen($file, 'rb');
    if ($fp) {
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
    }
}