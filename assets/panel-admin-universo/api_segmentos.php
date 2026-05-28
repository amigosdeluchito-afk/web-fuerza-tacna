<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin(); // Solo el admin debería poder crear pestañas estructurales
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$nombre_publico = trim($_POST['nombre_publico'] ?? '');

if (empty($nombre_publico)) {
    echo json_encode(['ok' => false, 'error' => 'El nombre del segmento es obligatorio']);
    exit;
}

// 1. Generar variables limpias (Sin espacios ni caracteres problemáticos)
$slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $nombre_publico));
$id_segmento = trim($slug, '-');
$nombre_tecnico = strtoupper($id_segmento); // Ej: SEGURIDAD-CIUDADANA

$rutaCredenciales = __DIR__ . '/data/credenciales.json';
$spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

try {
    $client = new \Google_Client();
    $client->setApplicationName('Panel de Obras Fuerza Tacna');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($rutaCredenciales);
    $service = new \Google_Service_Sheets($client);

    // 2. Validar que no exista y buscar la PLANTILLA_SEGMENTO
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();
    
    $templateSheetId = null;
    $exists = false;

    foreach ($sheets as $sheet) {
        $title = strtoupper($sheet->getProperties()->getTitle());
        if ($title === $nombre_tecnico) {
            $exists = true;
        }
        if ($title === 'PLANTILLA_SEGMENTO') {
            $templateSheetId = $sheet->getProperties()->getSheetId();
            break; 
        }
    }

    if ($exists) {
        echo json_encode(['ok' => false, 'error' => 'Ya existe una pestaña con el código: ' . $nombre_tecnico]);
        exit;
    }
    if ($templateSheetId === null) {
        echo json_encode(['ok' => false, 'error' => 'No se encontró la pestaña PLANTILLA_SEGMENTO en tu Excel. Debes crearla primero.']);
        exit;
    }

    // 3 y 4. Copiar plantilla y nombrarla
    $duplicateRequest = new \Google_Service_Sheets_Request([
        'duplicateSheet' => [
            'sourceSheetId' => $templateSheetId,
            'insertSheetIndex' => count($sheets),
            'newSheetName' => $nombre_tecnico
        ]
    ]);
    
    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => [$duplicateRequest]]);
    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    // Limpiar contenido basura (dejando solo la cabecera en fila 1)
    $clearRequest = new \Google_Service_Sheets_ClearValuesRequest();
    $service->spreadsheets_values->clear($spreadsheetId, $nombre_tecnico . '!A2:Z1000', $clearRequest);

    // 5. Registrar el segmento en la hoja "SEGMENTOS" (id_segmento, nombre_visible, nombre_pestana, activo, orden)
    $values = [[$id_segmento, $nombre_publico, $nombre_tecnico, 'SI', 10]];
    $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
    $params = ['valueInputOption' => 'RAW'];
    $service->spreadsheets_values->append($spreadsheetId, 'SEGMENTOS!A:E', $body, $params);

    // 6. Devolver respuesta JSON al panel admin
    echo json_encode(['ok' => true, 'mensaje' => "Segmento '$nombre_publico' creado y registrado con éxito.", 'tecnico' => $nombre_tecnico]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error de Google API: ' . $e->getMessage()]);
}