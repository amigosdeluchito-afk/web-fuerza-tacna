<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);

    $nombre = trim($input['nombre'] ?? '');
    $tipo = trim($input['tipo'] ?? 'Local');
    $estado = trim($input['estado'] ?? 'En estudios');
    $color = trim($input['color'] ?? '#ffffff');
    $coords = $input['coordenadas'] ?? [];

    if ($nombre === '' || !is_array($coords) || count($coords) < 2) {
        http_response_code(400); 
        echo json_encode(['error' => 'Datos inválidos o se requieren mínimo 2 puntos.']); 
        exit;
    }

    $string_id = 'tramo-' . uniqid();
    $json_coords = json_encode($coords);

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO panel_tramos_viales (string_id, nombre, tipo, estado, color, coordenadas, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$string_id, $nombre, $tipo, $estado, $color, $json_coords]);
        log_action('rv_crear', "Trazó tramo vial: $nombre");
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500); 
        echo json_encode(['error' => 'Error al guardar en BD: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'geojson') {
    try {
        $db = get_db_connection();
        
        // 1. Crear la tabla de tramos si no existe
        $db->exec("CREATE TABLE IF NOT EXISTS panel_tramos_viales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            string_id VARCHAR(50) NOT NULL UNIQUE,
            nombre VARCHAR(255) NOT NULL,
            tipo VARCHAR(100) DEFAULT 'Local',
            estado VARCHAR(100) DEFAULT 'En estudios',
            color VARCHAR(20) DEFAULT '#ffffff',
            coordenadas JSON NOT NULL,
            activo TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Sembrar los datos idénticos al archivo estático original (Si la tabla está vacía)
        $db->exec("INSERT IGNORE INTO panel_tramos_viales (string_id, nombre, tipo, estado, color, coordenadas) VALUES
            ('tramo-1', 'Avenida San Martín', 'Provincial', 'En ejecución', '#801039', '[[-70.2505, -18.0135], [-70.2548, -18.0156], [-70.2580, -18.0175]]'),
            ('tramo-2', 'Avenida Bolognesi', 'Local', 'Entregado', '#ffc300', '[[-70.2480, -18.0160], [-70.2520, -18.0185], [-70.2555, -18.0205]]'),
            ('tramo-3', 'Vía Regional Jorge Basadre', 'Regional', 'En estudios', '#1a73e8', '[[-70.2450, -18.0100], [-70.2400, -18.0050], [-70.2350, -18.0000]]')
        ");

        // 3. Obtener los tramos activos y empaquetarlos en GeoJSON
        $stmt = $db->query("SELECT * FROM panel_tramos_viales WHERE activo = 1 ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $row['string_id'],
                    'nombre' => $row['nombre'],
                    'tipo' => $row['tipo'],
                    'estado' => $row['estado'],
                    'color' => $row['color']
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => json_decode($row['coordenadas'], true)
                ]
            ];
        }

        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        // =========================================================
        // SISTEMA DE FALLBACK SEGURO:
        // Si la base de datos colapsa, leemos el archivo estático
        // =========================================================
        $fallback_file = __DIR__ . '/../universoobras/tramos-viales.geojson';
        if (file_exists($fallback_file)) {
            echo file_get_contents($fallback_file);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error de BD y fallback no encontrado.']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);