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

// Cabeceras de control estricto (Sin lógica Range todavía)
header("Access-Control-Allow-Origin: *");
header("Accept-Ranges: bytes");
header("Content-Type: application/octet-stream");
header("Content-Length: " . $filesize);
header("Cache-Control: public, max-age=31536000, no-transform");

if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    // Entregar el archivo completo de un solo golpe
    readfile($file);
}