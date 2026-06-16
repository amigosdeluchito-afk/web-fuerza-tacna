<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// Auto-reparación de esquema para RV3-C2 y RV3-C3 (Se ejecuta antes de Insert/Update)
function ensure_rv_columns($db) {
    try { $db->exec("ALTER TABLE panel_tramos_viales ADD COLUMN descripcion TEXT NULL AFTER color"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE panel_tramos_viales ADD COLUMN datos_edicion JSON NULL AFTER coordenadas"); } catch (Exception $e) {}
    try { 
        $db->exec("ALTER TABLE panel_tramos_viales 
            ADD COLUMN mensaje_principal VARCHAR(255) NULL AFTER descripcion,
            ADD COLUMN distrito VARCHAR(120) NULL AFTER sector,
            ADD COLUMN sector VARCHAR(150) NULL AFTER mensaje_principal,
            ADD COLUMN tramo_desde VARCHAR(150) NULL AFTER sector,
            ADD COLUMN tramo_hasta VARCHAR(150) NULL AFTER tramo_desde,
            ADD COLUMN longitud VARCHAR(50) NULL AFTER tramo_hasta,
            ADD COLUMN longitud_valor DECIMAL(10,2) NULL AFTER longitud,
            ADD COLUMN longitud_unidad VARCHAR(30) NULL AFTER longitud_valor,
            ADD COLUMN longitud_cuadras DECIMAL(10,1) NULL AFTER longitud_unidad,
            ADD COLUMN beneficiarios VARCHAR(100) NULL AFTER longitud,
            ADD COLUMN situacion_antes TEXT NULL AFTER beneficiarios,
            ADD COLUMN situacion_ahora TEXT NULL AFTER situacion_antes,
            ADD COLUMN avance_fisico TINYINT(3) NULL AFTER situacion_ahora,
            ADD COLUMN monto_inversion DECIMAL(15,2) NULL AFTER avance_fisico,
            ADD COLUMN fecha_inicio DATE NULL AFTER monto_inversion,
            ADD COLUMN fecha_entrega DATE NULL AFTER fecha_inicio;"); 
    } catch (Exception $e) {}
}

if ($action === 'create') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);

    $nombre = trim($input['nombre'] ?? '');
    $tipo = trim($input['tipo'] ?? 'Local');
    $estado = trim($input['estado'] ?? 'En estudios');
    $color = trim($input['color'] ?? '#ffffff');
    $descripcion = trim($input['descripcion'] ?? '');
    $coords = $input['coordenadas'] ?? [];
    $datos_edicion = $input['datos_edicion'] ?? null;
    
    // RV3-C2: Nuevos campos estratégicos
    $mensaje_principal = isset($input['mensaje_principal']) && $input['mensaje_principal'] !== '' ? trim($input['mensaje_principal']) : null;
    $distrito = isset($input['distrito']) && $input['distrito'] !== '' ? trim($input['distrito']) : null;
    $sector = isset($input['sector']) && $input['sector'] !== '' ? trim($input['sector']) : null;
    $tramo_desde = isset($input['tramo_desde']) && $input['tramo_desde'] !== '' ? trim($input['tramo_desde']) : null;
    $tramo_hasta = isset($input['tramo_hasta']) && $input['tramo_hasta'] !== '' ? trim($input['tramo_hasta']) : null;
    $longitud = isset($input['longitud']) && $input['longitud'] !== '' ? trim($input['longitud']) : null;
    $beneficiarios = isset($input['beneficiarios']) && $input['beneficiarios'] !== '' ? trim($input['beneficiarios']) : null;
    $situacion_antes = isset($input['situacion_antes']) && $input['situacion_antes'] !== '' ? trim($input['situacion_antes']) : null;
    $situacion_ahora = isset($input['situacion_ahora']) && $input['situacion_ahora'] !== '' ? trim($input['situacion_ahora']) : null;
    
    $longitud_valor = isset($input['longitud_valor']) && $input['longitud_valor'] !== '' ? (float)$input['longitud_valor'] : null;
    $longitud_unidad = isset($input['longitud_unidad']) && in_array($input['longitud_unidad'], ['metros', 'km', 'cuadras']) ? $input['longitud_unidad'] : null;
    $longitud_cuadras = isset($input['longitud_cuadras']) && $input['longitud_cuadras'] !== '' ? (float)$input['longitud_cuadras'] : null;

    $avance_fisico = isset($input['avance_fisico']) && $input['avance_fisico'] !== '' ? (int)$input['avance_fisico'] : null;
    if ($avance_fisico !== null && ($avance_fisico < 0 || $avance_fisico > 100)) $avance_fisico = 0;
    $monto_inversion = isset($input['monto_inversion']) && $input['monto_inversion'] !== '' ? (float)$input['monto_inversion'] : null;
    $fecha_inicio = isset($input['fecha_inicio']) && $input['fecha_inicio'] !== '' ? trim($input['fecha_inicio']) : null;
    $fecha_entrega = isset($input['fecha_entrega']) && $input['fecha_entrega'] !== '' ? trim($input['fecha_entrega']) : null;

    if ($nombre === '' || !is_array($coords) || count($coords) < 2) {
        http_response_code(400); 
        echo json_encode(['error' => 'Datos inválidos o se requieren mínimo 2 puntos.']); 
        exit;
    }

    $string_id = 'tramo-' . uniqid();
    $json_coords = json_encode($coords);
    $json_edicion = $datos_edicion ? json_encode($datos_edicion) : null;

    try {
        $db = get_db_connection();
        ensure_rv_columns($db); // Garantiza que las columnas existan antes de guardar
        $stmt = $db->prepare("INSERT INTO panel_tramos_viales (string_id, nombre, tipo, estado, color, descripcion, coordenadas, datos_edicion, activo, mensaje_principal, distrito, sector, tramo_desde, tramo_hasta, longitud, longitud_valor, longitud_unidad, longitud_cuadras, beneficiarios, situacion_antes, situacion_ahora, avance_fisico, monto_inversion, fecha_inicio, fecha_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $string_id, $nombre, $tipo, $estado, $color, $descripcion, $json_coords, $json_edicion,
            $mensaje_principal, $distrito, $sector, $tramo_desde, $tramo_hasta, $longitud, $longitud_valor, $longitud_unidad, $longitud_cuadras, $beneficiarios, $situacion_antes, $situacion_ahora, $avance_fisico, $monto_inversion, $fecha_inicio, $fecha_entrega
        ]);
        log_action('rv_crear', "Trazó tramo vial: $nombre");
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500); 
        echo json_encode(['error' => 'Error al guardar en BD: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $string_id = trim($input['id'] ?? '');
    $nombre = trim($input['nombre'] ?? '');
    $tipo = trim($input['tipo'] ?? 'Local');
    $estado = trim($input['estado'] ?? 'En estudios');
    $color = trim($input['color'] ?? '#ffffff');
    $descripcion = trim($input['descripcion'] ?? '');
    $coords = $input['coordenadas'] ?? [];
    $datos_edicion = $input['datos_edicion'] ?? null;
    
    // RV3-C2: Nuevos campos estratégicos
    $mensaje_principal = isset($input['mensaje_principal']) && $input['mensaje_principal'] !== '' ? trim($input['mensaje_principal']) : null;
    $distrito = isset($input['distrito']) && $input['distrito'] !== '' ? trim($input['distrito']) : null;
    $sector = isset($input['sector']) && $input['sector'] !== '' ? trim($input['sector']) : null;
    $tramo_desde = isset($input['tramo_desde']) && $input['tramo_desde'] !== '' ? trim($input['tramo_desde']) : null;
    $tramo_hasta = isset($input['tramo_hasta']) && $input['tramo_hasta'] !== '' ? trim($input['tramo_hasta']) : null;
    $longitud = isset($input['longitud']) && $input['longitud'] !== '' ? trim($input['longitud']) : null;
    $beneficiarios = isset($input['beneficiarios']) && $input['beneficiarios'] !== '' ? trim($input['beneficiarios']) : null;
    $situacion_antes = isset($input['situacion_antes']) && $input['situacion_antes'] !== '' ? trim($input['situacion_antes']) : null;
    $situacion_ahora = isset($input['situacion_ahora']) && $input['situacion_ahora'] !== '' ? trim($input['situacion_ahora']) : null;
    
    $longitud_valor = isset($input['longitud_valor']) && $input['longitud_valor'] !== '' ? (float)$input['longitud_valor'] : null;
    $longitud_unidad = isset($input['longitud_unidad']) && in_array($input['longitud_unidad'], ['metros', 'km', 'cuadras']) ? $input['longitud_unidad'] : null;
    $longitud_cuadras = isset($input['longitud_cuadras']) && $input['longitud_cuadras'] !== '' ? (float)$input['longitud_cuadras'] : null;

    $avance_fisico = isset($input['avance_fisico']) && $input['avance_fisico'] !== '' ? (int)$input['avance_fisico'] : null;
    if ($avance_fisico !== null && ($avance_fisico < 0 || $avance_fisico > 100)) $avance_fisico = 0;
    $monto_inversion = isset($input['monto_inversion']) && $input['monto_inversion'] !== '' ? (float)$input['monto_inversion'] : null;
    $fecha_inicio = isset($input['fecha_inicio']) && $input['fecha_inicio'] !== '' ? trim($input['fecha_inicio']) : null;
    $fecha_entrega = isset($input['fecha_entrega']) && $input['fecha_entrega'] !== '' ? trim($input['fecha_entrega']) : null;

    if ($string_id === '' || $nombre === '' || !is_array($coords) || count($coords) < 2) {
        http_response_code(400); echo json_encode(['error' => 'Datos inválidos']); exit;
    }

    $json_coords = json_encode($coords);
    $json_edicion = $datos_edicion ? json_encode($datos_edicion) : null;

    try {
        $db = get_db_connection();
        ensure_rv_columns($db); // Garantiza que las columnas existan antes de actualizar
        $stmt = $db->prepare("UPDATE panel_tramos_viales SET nombre=?, tipo=?, estado=?, color=?, descripcion=?, coordenadas=?, datos_edicion=?, mensaje_principal=?, distrito=?, sector=?, tramo_desde=?, tramo_hasta=?, longitud=?, longitud_valor=?, longitud_unidad=?, longitud_cuadras=?, beneficiarios=?, situacion_antes=?, situacion_ahora=?, avance_fisico=?, monto_inversion=?, fecha_inicio=?, fecha_entrega=? WHERE string_id=?");
        $stmt->execute([
            $nombre, $tipo, $estado, $color, $descripcion, $json_coords, $json_edicion,
            $mensaje_principal, $distrito, $sector, $tramo_desde, $tramo_hasta, $longitud, $longitud_valor, $longitud_unidad, $longitud_cuadras, $beneficiarios, $situacion_antes, $situacion_ahora, $avance_fisico, $monto_inversion, $fecha_inicio, $fecha_entrega,
            $string_id
        ]);
        log_action('rv_editar', "Editó tramo vial: $nombre");
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => 'Error al actualizar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    $string_id = trim($input['id'] ?? '');
    
    if ($string_id === '') { http_response_code(400); echo json_encode(['error' => 'ID inválido']); exit; }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("DELETE FROM panel_tramos_viales WHERE string_id=?");
        $stmt->execute([$string_id]);
        log_action('rv_eliminar', "Eliminó tramo vial ID: $string_id");
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'toggle_activo') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    $string_id = trim($input['id'] ?? '');
    $activo = isset($input['activo']) ? (int)$input['activo'] : 1;
    
    if ($string_id === '') { http_response_code(400); echo json_encode(['error' => 'ID inválido']); exit; }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("UPDATE panel_tramos_viales SET activo=?, updated_at=NOW() WHERE string_id=?");
        $stmt->execute([$activo, $string_id]);
        $accion_log = $activo === 1 ? 'Reactivo' : 'Desactivo';
        log_action('rv_estado', "$accion_log tramo vial ID: $string_id");
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => 'Error al cambiar estado: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'listar_admin') {
    require_admin();
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT * FROM panel_tramos_viales ORDER BY id DESC");
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
                    'color' => $row['color'],
                    'descripcion' => $row['descripcion'] ?? '',
                    'mensaje_principal' => $row['mensaje_principal'] ?? null,
                    'distrito' => $row['distrito'] ?? null,
                    'sector' => $row['sector'] ?? null,
                    'tramo_desde' => $row['tramo_desde'] ?? null,
                    'tramo_hasta' => $row['tramo_hasta'] ?? null,
                    'longitud' => $row['longitud'] ?? null,
                    'longitud_valor' => $row['longitud_valor'] ?? null,
                    'longitud_unidad' => $row['longitud_unidad'] ?? null,
                    'longitud_cuadras' => $row['longitud_cuadras'] ?? null,
                    'beneficiarios' => $row['beneficiarios'] ?? null,
                    'situacion_antes' => $row['situacion_antes'] ?? null,
                    'situacion_ahora' => $row['situacion_ahora'] ?? null,
                    'avance_fisico' => $row['avance_fisico'] ?? null,
                    'monto_inversion' => $row['monto_inversion'] ?? null,
                    'fecha_inicio' => $row['fecha_inicio'] ?? null,
                    'fecha_entrega' => $row['fecha_entrega'] ?? null,
                    'datos_edicion' => $row['datos_edicion'] ? json_decode($row['datos_edicion'], true) : null,
                    'activo' => (int)$row['activo']
                ],
                'geometry' => [ 'type' => 'LineString', 'coordinates' => json_decode($row['coordenadas'], true) ]
            ];
        }
        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => 'Error: ' . $e->getMessage()]); }
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
            descripcion TEXT NULL,
            coordenadas JSON NOT NULL,
            datos_edicion JSON NULL,
            activo TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Migración silenciosa por si la tabla ya existía sin la columna descripcion
        try { $db->exec("ALTER TABLE panel_tramos_viales ADD COLUMN descripcion TEXT NULL AFTER color"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE panel_tramos_viales ADD COLUMN datos_edicion JSON NULL AFTER coordenadas"); } catch (Exception $e) {}
        
        // Migración silenciosa RV3-C2
        try { 
            $db->exec("ALTER TABLE panel_tramos_viales 
                ADD COLUMN mensaje_principal VARCHAR(255) NULL AFTER descripcion,
                ADD COLUMN distrito VARCHAR(120) NULL AFTER sector,
                ADD COLUMN sector VARCHAR(150) NULL AFTER mensaje_principal,
                ADD COLUMN tramo_desde VARCHAR(150) NULL AFTER sector,
                ADD COLUMN tramo_hasta VARCHAR(150) NULL AFTER tramo_desde,
                ADD COLUMN longitud VARCHAR(50) NULL AFTER tramo_hasta,
                ADD COLUMN longitud_valor DECIMAL(10,2) NULL AFTER longitud,
                ADD COLUMN longitud_unidad VARCHAR(30) NULL AFTER longitud_valor,
                ADD COLUMN longitud_cuadras DECIMAL(10,1) NULL AFTER longitud_unidad,
                ADD COLUMN beneficiarios VARCHAR(100) NULL AFTER longitud,
                ADD COLUMN situacion_antes TEXT NULL AFTER beneficiarios,
                ADD COLUMN situacion_ahora TEXT NULL AFTER situacion_antes,
                ADD COLUMN avance_fisico TINYINT(3) NULL AFTER situacion_ahora,
                ADD COLUMN monto_inversion DECIMAL(15,2) NULL AFTER avance_fisico,
                ADD COLUMN fecha_inicio DATE NULL AFTER monto_inversion,
                ADD COLUMN fecha_entrega DATE NULL AFTER fecha_inicio;"); 
        } catch (Exception $e) {}

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
                    'color' => $row['color'],
                    'descripcion' => $row['descripcion'] ?? '',
                    'mensaje_principal' => $row['mensaje_principal'] ?? null,
                    'distrito' => $row['distrito'] ?? null,
                    'sector' => $row['sector'] ?? null,
                    'tramo_desde' => $row['tramo_desde'] ?? null,
                    'tramo_hasta' => $row['tramo_hasta'] ?? null,
                    'longitud' => $row['longitud'] ?? null,
                    'longitud_valor' => $row['longitud_valor'] ?? null,
                    'longitud_unidad' => $row['longitud_unidad'] ?? null,
                    'longitud_cuadras' => $row['longitud_cuadras'] ?? null,
                    'beneficiarios' => $row['beneficiarios'] ?? null,
                    'situacion_antes' => $row['situacion_antes'] ?? null,
                    'situacion_ahora' => $row['situacion_ahora'] ?? null,
                    'avance_fisico' => $row['avance_fisico'] ?? null,
                    'monto_inversion' => $row['monto_inversion'] ?? null,
                    'fecha_inicio' => $row['fecha_inicio'] ?? null,
                    'fecha_entrega' => $row['fecha_entrega'] ?? null,
                    'datos_edicion' => $row['datos_edicion'] ? json_decode($row['datos_edicion'], true) : null
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