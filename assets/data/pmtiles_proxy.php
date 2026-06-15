<?php
// Habilitar errores en pantalla temporalmente para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Desactivar compresión nativa de PHP de forma segura
@ini_set('zlib.output_compression', 'Off');

$file = __DIR__ . '/tacna.pmtiles';

echo "<h1>OK PMTILES PROXY</h1>";
echo "Ruta absoluta buscada: " . htmlspecialchars($file) . "<br><br>";

if (file_exists($file)) {
    echo "<strong style='color:green;'>ESTADO: El archivo tacna.pmtiles EXISTE.</strong><br>";
    echo "Peso del archivo: " . number_format(filesize($file) / 1048576, 2) . " MB";
} else {
    echo "<strong style='color:red;'>ERROR: El archivo tacna.pmtiles NO se encuentra en la carpeta.</strong>";
}
exit;