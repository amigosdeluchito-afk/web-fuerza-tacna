<?php
// 1. Cargar la librería de Google (Composer)
require_once __DIR__ . '/vendor/autoload.php';

// =========================================================================
// ¡ATENCIÓN! REEMPLAZA ESTO CON EL ID DE TU GOOGLE SHEETS
// Ej: https://docs.google.com/spreadsheets/d/1ABC123xyz.../edit -> ID es 1ABC123xyz...
// =========================================================================
$spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 2. Configurar el "Bot" usando tus credenciales
        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        
        // Ruta a tu llave maestra JSON
        $client->setAuthConfig(__DIR__ . '/data/credenciales.json');

        // 3. Iniciar el servicio de Google Sheets
        $service = new \Google_Service_Sheets($client);

        // 4. Recibir los datos del formulario (agregar_obra.php)
        $segmento = $_POST['segmento'] ?? 'educacion';
        $nombre   = $_POST['nombre'] ?? '';
        $estado   = $_POST['estado'] ?? '';
        $monto    = $_POST['monto'] ?? '';
        $distrito = $_POST['distrito'] ?? '';
        $provincia= $_POST['provincia'] ?? '';
        $x        = $_POST['x'] ?? '';
        $y        = $_POST['y'] ?? '';
        
        // Generamos la "carpeta" en blanco o automáticamente basándonos en el nombre si lo prefieres.
        // Por ahora lo dejamos en blanco o con un guion si no hay fotos aún.
        $carpeta = '-'; 
        $descripcion = '-';

        // 5. Preparar la "Fila" para el Excel. 
        // El ORDEN debe coincidir con tus columnas: nombre | estado | monto | distrito | provincia | x | y | carpeta | descripcion
        $values = [
            [$nombre, $estado, $monto, $distrito, $provincia, $x, $y, $carpeta, $descripcion]
        ];

        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'USER_ENTERED']; // Permite que Google detecte números correctamente

        // 6. ¡Enviar los datos! 
        // Insertará la fila al final de la hoja seleccionada (Ej: "educacion")
        $result = $service->spreadsheets_values->append($spreadsheetId, $segmento, $body, $params);

        echo "<div style='font-family: Arial; padding: 40px; text-align: center;'>";
        echo "<h2 style='color: green;'>¡Obra agregada con éxito!</h2>";
        echo "<p>Los datos ya se guardaron en tu archivo de Excel.</p>";
        echo "<a href='agregar_obra.php' style='padding: 10px 20px; background: #801039; color: #fff; text-decoration: none; border-radius: 5px;'>Agregar otra obra</a>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<h2 style='color: red; font-family: Arial;'>Error de conexión:</h2>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
}