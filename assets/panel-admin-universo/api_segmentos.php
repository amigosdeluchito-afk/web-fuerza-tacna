<?php
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';
$rutaCredenciales = __DIR__ . '/data/credenciales.json';
$spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';
$reserved_names = ['LISTAS', 'SEGMENTOS', 'PLANTILLA_SEGMENTO'];

// Función para limpiar y generar el nombre de pestaña técnico
function generar_nombre_pestana($str) {
    $str = str_replace(
        ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'],
        ['a','e','i','o','u','A','E','I','O','U','N','N'],
        $str
    );
    $str = preg_replace('/[^a-zA-Z0-9]+/', '_', $str);
    return strtoupper(trim($str, '_'));
}

try {
    $client = new \Google_Client();
    $client->setApplicationName('Panel de Obras Fuerza Tacna');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($rutaCredenciales);
    $service = new \Google_Service_Sheets($client);

    // ==========================================================
    // ACCIÓN: LISTAR
    // ==========================================================
    if ($action === 'listar') {
        $response = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:E');
        $rows = $response->getValues() ?? [];
        $segmentos = [];
        
        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // Saltar cabecera
            if (!empty($row[0])) {
                $segmentos[] = [
                    'id_segmento' => $row[0],
                    'nombre_visible' => $row[1] ?? '',
                    'nombre_pestana' => $row[2] ?? '',
                    'activo' => $row[3] ?? 'NO',
                    'orden' => $row[4] ?? 0
                ];
            }
        }
        echo json_encode(['ok' => true, 'segmentos' => $segmentos]);
        exit;
    }

    // ==========================================================
    // ACCIÓN: CREAR
    // ==========================================================
    if ($action === 'crear') {
        $nombre_visible = trim($_POST['nombre_visible'] ?? '');
        if (empty($nombre_visible)) throw new Exception('El nombre visible es obligatorio.');
        
        $nombre_pestana = generar_nombre_pestana($nombre_visible);
        if (in_array($nombre_pestana, $reserved_names)) throw new Exception('Nombre reservado por el sistema.');

        // 1. Leer SEGMENTOS para generar el nuevo ID (seg_XXX)
        $response = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:E');
        $rows = $response->getValues() ?? [];
        $maxIdNum = 0;
        $maxOrden = 0;
        
        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            $id = $row[0] ?? '';
            $orden = (int)($row[4] ?? 0);
            if (preg_match('/^seg_(\d+)$/', $id, $m)) {
                if ((int)$m[1] > $maxIdNum) $maxIdNum = (int)$m[1];
            }
            if ($orden > $maxOrden) $maxOrden = $orden;
        }
        
        $new_id = sprintf("seg_%03d", $maxIdNum + 1);
        $new_orden = $maxOrden + 1;

        // 2. Validar que la pestaña no exista y buscar la plantilla
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheets = $spreadsheet->getSheets();
        $templateSheetId = null;

        foreach ($sheets as $sheet) {
            $title = strtoupper($sheet->getProperties()->getTitle());
            if ($title === $nombre_pestana) throw new Exception("Ya existe una pestaña con el nombre $nombre_pestana.");
            if ($title === 'PLANTILLA_SEGMENTO') $templateSheetId = $sheet->getProperties()->getSheetId();
        }

        if ($templateSheetId === null) throw new Exception('No se encontró PLANTILLA_SEGMENTO en tu Excel.');

        // 3. Duplicar la pestaña
        $duplicateRequest = new \Google_Service_Sheets_Request([
            'duplicateSheet' => [
                'sourceSheetId' => $templateSheetId,
                'insertSheetIndex' => count($sheets),
                'newSheetName' => $nombre_pestana
            ]
        ]);
        $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => [$duplicateRequest]]);
        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

        // 4. Limpiar datos viejos de la copia (Preservando validaciones de la plantilla)
        $clearRequest = new \Google_Service_Sheets_ClearValuesRequest();
        $service->spreadsheets_values->clear($spreadsheetId, $nombre_pestana . '!A2:Z1000', $clearRequest);

        // 5. Insertar fila en SEGMENTOS
        $values = [[$new_id, $nombre_visible, $nombre_pestana, 'SI', $new_orden]];
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->append($spreadsheetId, 'SEGMENTOS!A:E', $body, $params);

        echo json_encode(['ok' => true, 'mensaje' => "Segmento '$nombre_visible' ($new_id) creado con éxito."]);
        exit;
    }

    // ==========================================================
    // ACCIÓN: REORDENAR
    // ==========================================================
    if ($action === 'reordenar') {
        $orden_ids = json_decode($_POST['orden_ids'] ?? '[]', true);
        if (empty($orden_ids) || !is_array($orden_ids)) {
            throw new Exception('No se recibió un orden válido.');
        }

        // 1. Leer la hoja SEGMENTOS completa para mapear IDs a sus filas
        $response = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:E');
        $rows = $response->getValues() ?? [];
        
        if (count($rows) <= 1) {
            echo json_encode(['ok' => true, 'mensaje' => 'Nada que reordenar.']);
            exit;
        }

        array_shift($rows); // Quitar cabecera
        $id_map = [];
        foreach ($rows as $i => $row) {
            if (!empty($row[0])) $id_map[$row[0]] = ['rowIndex' => $i + 2, 'currentOrden' => (int)($row[4] ?? 0)];
        }

        // 2. Obtener el ID numérico de la hoja "SEGMENTOS"
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheets = $spreadsheet->getSheets();
        $segmentosSheetId = null;
        foreach ($sheets as $sheet) {
            if (strtoupper($sheet->getProperties()->getTitle()) === 'SEGMENTOS') {
                $segmentosSheetId = $sheet->getProperties()->getSheetId();
                break;
            }
        }
        if ($segmentosSheetId === null) throw new Exception('No se encontró la hoja "SEGMENTOS".');

        // 3. Preparar el batch update para cambiar solo la columna 'orden'
        $updateRequests = [];
        foreach ($orden_ids as $new_order_index => $id_segmento) {
            if (isset($id_map[$id_segmento])) {
                $rowIndex = $id_map[$id_segmento]['rowIndex'];
                $new_orden_value = $new_order_index + 1;

                if ($id_map[$id_segmento]['currentOrden'] !== $new_orden_value) {
                    $updateRequests[] = new \Google_Service_Sheets_Request([
                        'updateCells' => [
                            'rows' => [['values' => [['userEnteredValue' => ['numberValue' => $new_orden_value]]]]],
                            'start' => ['sheetId' => $segmentosSheetId, 'rowIndex' => $rowIndex - 1, 'columnIndex' => 4],
                            'fields' => 'userEnteredValue'
                        ]
                    ]);
                }
            }
        }

        if (!empty($updateRequests)) {
            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => [$updateRequests]]);
            $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Orden de segmentos actualizado con éxito.']);
        exit;
    }

    // ==========================================================
    // ACCIÓN: EDITAR
    // ==========================================================
    if ($action === 'editar') {
        $id_segmento = trim($_POST['id_segmento'] ?? '');
        $nombre_visible = trim($_POST['nombre_visible'] ?? '');
        
        if (empty($id_segmento) || empty($nombre_visible)) throw new Exception('Faltan datos obligatorios.');
        
        $nuevo_nombre_pestana = generar_nombre_pestana($nombre_visible);
        if (in_array($nuevo_nombre_pestana, $reserved_names)) throw new Exception('Nombre reservado por el sistema.');

        // 1. Buscar la fila en SEGMENTOS
        $response = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:E');
        $rows = $response->getValues() ?? [];
        $rowIndex = -1;
        $viejo_nombre_pestana = '';
        $activo = 'SI';
        $orden = 0;

        foreach ($rows as $i => $row) {
            if (($row[0] ?? '') === $id_segmento) {
                $rowIndex = $i + 1; // Google Sheets es base 1
                $viejo_nombre_pestana = $row[2] ?? '';
                $activo = $row[3] ?? 'SI';
                $orden = $row[4] ?? 0;
                break;
            }
        }

        if ($rowIndex === -1) throw new Exception("Segmento $id_segmento no encontrado en Excel.");

        // 2. Si el nombre de pestaña cambió, renombrar la hoja real
        if ($nuevo_nombre_pestana !== $viejo_nombre_pestana) {
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            $sheets = $spreadsheet->getSheets();
            $sheetIdToRename = null;
            
            foreach ($sheets as $sheet) {
                $title = strtoupper($sheet->getProperties()->getTitle());
                if ($title === $nuevo_nombre_pestana) throw new Exception("Ya existe otra pestaña llamada $nuevo_nombre_pestana.");
                if ($title === $viejo_nombre_pestana) $sheetIdToRename = $sheet->getProperties()->getSheetId();
            }
            
            if ($sheetIdToRename !== null) {
                $renameRequest = new \Google_Service_Sheets_Request([
                    'updateSheetProperties' => [
                        'properties' => [ 'sheetId' => $sheetIdToRename, 'title' => $nuevo_nombre_pestana ],
                        'fields' => 'title'
                    ]
                ]);
                $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => [$renameRequest]]);
                $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            }
        }

        // 3. Actualizar la fila en SEGMENTOS
        $values = [[$id_segmento, $nombre_visible, $nuevo_nombre_pestana, $activo, $orden]];
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->update($spreadsheetId, "SEGMENTOS!A{$rowIndex}:E{$rowIndex}", $body, $params);

        echo json_encode(['ok' => true, 'mensaje' => "Segmento actualizado con éxito."]);
        exit;
    }

    throw new Exception('Acción inválida.');

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error de Google API: ' . $e->getMessage()]);
}