<?php
// cron_sync_obras.php
// Script para ejecución automática (Cron Job) de la sincronización de obras

require_once __DIR__ . '/config.php';

// Protección: Solo permitir ejecución desde la consola del servidor (Cron) 
// o mediante una URL con token secreto para evitar ataques.
$is_cli = (php_sapi_name() === 'cli');
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';

if (!$is_cli && !hash_equals(CRON_SYNC_TOKEN, $token)) {
    http_response_code(403);
    die("Acceso denegado. Este script solo corre automáticamente en el servidor.");
}

$db = get_db_connection();

try {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception("Falta la carpeta 'vendor' de Google API.");
    }
    require_once __DIR__ . '/vendor/autoload.php';
    
    $rutaCredenciales = __DIR__ . '/data/credenciales.json';
    $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

    $client = new \Google_Client();
    $client->setApplicationName('Panel de Obras Fuerza Tacna (Cron)');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
    $client->setAuthConfig($rutaCredenciales);
    $service = new \Google_Service_Sheets($client);

    // 1. Obtener pestañas activas desde SEGMENTOS
    $responseSeg = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:D');
    $rowsSeg = $responseSeg->getValues() ?? [];
    $segmentosActivos = [];
    foreach ($rowsSeg as $i => $row) {
        if ($i === 0) continue;
        if (!empty($row[2]) && strtoupper($row[3] ?? '') === 'SI') {
            $segmentosActivos[$row[2]] = $row[1] ?? $row[2];
        }
    }

    // 2. Extraer datos de Obras
    $obrasAProcesar = [];
    foreach ($segmentosActivos as $tab => $nombreSegmento) {
        try {
            $responseObras = $service->spreadsheets_values->get($spreadsheetId, $tab . '!A2:I');
            $rowsObras = $responseObras->getValues() ?? [];
            foreach ($rowsObras as $r) {
                $nombre = trim($r[0] ?? '');
                if (empty($nombre)) continue;

                $estado = trim($r[1] ?? '');
                if (stripos($estado, 'Oculto') !== false) continue;
                $monto = trim($r[2] ?? '');
                $provincia = trim($r[5] ?? ''); $distrito = trim($r[6] ?? '');
                $carpeta = trim($r[7] ?? ''); $descripcion = trim($r[8] ?? '');

                $titulo = "Obra: " . $nombre;
                $palabras = implode(', ', array_filter([$nombre, $distrito, $nombreSegmento]));
                $contenido = "La obra '$nombre' pertenece al sector $nombreSegmento. ";
                if ($distrito || $provincia) $contenido .= "Ubicada en $distrito, $provincia. ";
                if ($estado) $contenido .= "Estado actual: '$estado'. ";
                if ($monto) $contenido .= "Monto referencial: $monto. ";
                if ($descripcion) $contenido .= "Descripción: $descripcion.";
                if ($carpeta && $carpeta !== '-') $contenido .= " (Tiene galería de fotos).";

                $obrasAProcesar[] = ['categoria' => 'Obras', 'titulo' => $titulo, 'contenido' => trim($contenido), 'palabras_clave' => $palabras, 'prioridad' => 5, 'estado' => 1, 'fuente' => 'Google Sheets - Obras'];
            }
        } catch (\Exception $e) { continue; }
    }

    // 3. Sincronizar en Base de Datos de manera segura
    $db->beginTransaction();
    $db->exec("DELETE FROM panel_ia_conocimiento WHERE fuente = 'Google Sheets - Obras'");
    $stmtIns = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($obrasAProcesar as $obra) {
        $stmtIns->execute([$obra['categoria'], $obra['titulo'], $obra['contenido'], $obra['palabras_clave'], $obra['prioridad'], $obra['estado'], $obra['fuente']]);
    }
    $db->commit();
    
    echo "OK: Sincronización automática exitosa. Se insertaron " . count($obrasAProcesar) . " obras para Luchito.\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
