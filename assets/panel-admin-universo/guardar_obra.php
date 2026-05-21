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
        $descripcion = '';

        $values = [
            [$nombre, $estado, $monto, $x, $y, $provincia, $distrito, $carpeta, $descripcion]
        ];

        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'USER_ENTERED'];

        $result = $service->spreadsheets_values->append($spreadsheetId, $segmento, $body, $params);

        log_action('obra_agregar', "Agregó nueva obra: $nombre en $segmento");

        header("Location: agregar_obra.php?success=1");
        exit;

    } catch (Throwable $e) { // Throwable atrapa TODO (Errores 500, fatales, sintaxis, etc)
        die("<div style='background: #fff; padding: 20px; color: #333; font-family: Arial;'><h2 style='color: red;'>Error detectado en PHP:</h2><pre style='background:#f4f4f4; padding: 10px; border-left: 4px solid red;'>" . $e->getMessage() . " en la línea " . $e->getLine() . "</pre><br><a href='agregar_obra.php'>Volver atrás</a></div>");
    }
}