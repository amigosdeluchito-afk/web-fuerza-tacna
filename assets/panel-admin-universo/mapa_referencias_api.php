<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$db = get_db_connection();

// Crear la tabla automáticamente si no existe (para evitar errores 500)
$db->exec("CREATE TABLE IF NOT EXISTS panel_mapa_referencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    nombre_corto VARCHAR(100) NOT NULL,
    categoria VARCHAR(100) DEFAULT 'General',
    icon_type VARCHAR(50) DEFAULT 'hito',
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    min_zoom INT DEFAULT 11,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$action = $_GET['action'] ?? '';

if ($action === 'geojson') {
    // Endpoint Público: Devuelve las referencias activas en formato GeoJSON
    try {
        $stmt = $db->query("SELECT * FROM panel_mapa_referencias WHERE activo = 1 ORDER BY orden ASC, id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [ (float)$row['lng'], (float)$row['lat'] ] // GeoJSON requiere [Longitud, Latitud]
                ],
                'properties' => [
                    'id' => (int)$row['id'],
                    'name' => $row['nombre'],
                    'short_name' => $row['nombre_corto'],
                    'categoria' => $row['categoria'],
                    'icon_type' => $row['icon_type'],
                    'min_zoom' => (int)$row['min_zoom']
                ]
            ];
        }

        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de BD: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'create') {
    // Endpoint Privado: Requiere sesión de administrador
    require_admin();

    $input = json_decode(file_get_contents('php://input'), true);
    
    $nombre = trim($input['nombre'] ?? '');
    $nombre_corto = trim($input['nombre_corto'] ?? '');
    $categoria = trim($input['categoria'] ?? 'General');
    $icon_type = trim($input['icon_type'] ?? 'hito');
    $lat = isset($input['lat']) ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) ? (float)$input['lng'] : null;
    $min_zoom = isset($input['min_zoom']) ? (int)$input['min_zoom'] : 11;

    if ($nombre === '' || $lat === null || $lng === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos obligatorios (nombre, lat, lng)']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO panel_mapa_referencias (nombre, nombre_corto, categoria, icon_type, lat, lng, min_zoom, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $nombre_corto, $categoria, $icon_type, $lat, $lng, $min_zoom]);
        
        log_action('ref_crear', "Agregó Referencia Estratégica: $nombre", ['lat' => $lat, 'lng' => $lng]);
        echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);