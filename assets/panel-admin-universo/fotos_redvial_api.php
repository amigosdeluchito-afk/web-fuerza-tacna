<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================
// 1. AUTO-CREACIÓN DE LA TABLA (Con Restricciones Seguras)
// =========================================================
try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS panel_tramos_viales_fotos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tramo_string_id VARCHAR(50) NOT NULL,
        tipo ENUM('portada', 'galeria', 'antes', 'despues') DEFAULT 'galeria',
        archivo VARCHAR(255) NOT NULL,
        archivo_thumb VARCHAR(255) NOT NULL,
        titulo VARCHAR(150) NULL,
        descripcion_corta TEXT NULL,
        orden INT DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tramo (tramo_string_id),
        CONSTRAINT fk_tramo_foto FOREIGN KEY (tramo_string_id) REFERENCES panel_tramos_viales(string_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Error estructural en BD: ' . $e->getMessage()], 500);
}

// =========================================================
// HELPER: RE-RENDERIZADO Y CONVERSIÓN A WEBP CON GD
// =========================================================
function render_to_webp($tmp_name, $mime, $dest_path, $max_width) {
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($tmp_name); break;
        case 'image/png':  $img = @imagecreatefrompng($tmp_name); break;
        case 'image/webp': $img = @imagecreatefromwebp($tmp_name); break;
        default: return false;
    }
    if (!$img) return false;

    $width = imagesx($img);
    $height = imagesy($img);

    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = floor($height * ($max_width / $width));
        $new_img = imagecreatetruecolor($new_width, $new_height);
        
        // Manejar transparencia para PNG/WebP
        if ($mime == 'image/png' || $mime == 'image/webp') {
            imagealphablending($new_img, false);
            imagesavealpha($new_img, true);
            $transparent = imagecolorallocatealpha($new_img, 255, 255, 255, 127);
            imagefilledrectangle($new_img, 0, 0, $new_width, $new_height, $transparent);
        }
        
        imagecopyresampled($new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($img);
        $img = $new_img;
    } else {
        if ($mime == 'image/png' || $mime == 'image/webp') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
    }

    $success = imagewebp($img, $dest_path, 80); // 80% de compresión óptima
    imagedestroy($img);
    return $success;
}

// =========================================================
// ENDPOINT: UPLOAD (Subida estricta)
// =========================================================
if ($action === 'upload') {
    require_admin();
    
    $raw_tramo = $_POST['tramo_string_id'] ?? '';
    $tramo_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw_tramo); // Sanitización Path Traversal
    $tipo = in_array($_POST['tipo'] ?? '', ['portada', 'galeria', 'antes', 'despues']) ? $_POST['tipo'] : 'galeria';

    if (empty($tramo_id) || !isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'error' => 'Datos faltantes o error en la subida.'], 400);
    }

    // 1. Validar existencia del tramo en BD
    $stmt = $db->prepare("SELECT id FROM panel_tramos_viales WHERE string_id = ?");
    $stmt->execute([$tramo_id]);
    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'El tramo vial especificado no existe.'], 404);
    }

    // 2. Validaciones de Seguridad del Archivo
    if ($_FILES['foto']['size'] > (8 * 1024 * 1024)) {
        json_response(['ok' => false, 'error' => 'El archivo supera los 8MB.'], 400);
    }

    $tmp_name = $_FILES['foto']['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        json_response(['ok' => false, 'error' => 'Formato no permitido. Solo JPG, PNG o WebP. MIME detectado: ' . $mime], 400);
    }

    // 3. Crear carpetas seguras
    global $PROJECT_ROOT;
    $target_dir = $PROJECT_ROOT . '/IMG/red-vial/' . $tramo_id;
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            json_response(['ok' => false, 'error' => 'No se pudo crear la carpeta de destino.'], 500);
        }
    }

    // 4. Nombres criptográficos
    $base_name = uniqid('rv_');
    $file_main = $base_name . '.webp';
    $file_thumb = $base_name . '_thumb.webp';
    
    $path_main = $target_dir . '/' . $file_main;
    $path_thumb = $target_dir . '/' . $file_thumb;

    // 5. Re-renderizado seguro con GD
    if (!render_to_webp($tmp_name, $mime, $path_main, 1600)) {
        json_response(['ok' => false, 'error' => 'Error al procesar la imagen principal.'], 500);
    }
    if (!render_to_webp($tmp_name, $mime, $path_thumb, 400)) {
        unlink($path_main); // Rollback
        json_response(['ok' => false, 'error' => 'Error al generar la miniatura.'], 500);
    }

    // 6. Lógica de Portada Única
    if ($tipo === 'portada') {
        $db->prepare("UPDATE panel_tramos_viales_fotos SET tipo = 'galeria' WHERE tramo_string_id = ? AND tipo = 'portada'")->execute([$tramo_id]);
    }

    // 7. Determinar orden e Insertar
    $stmtO = $db->prepare("SELECT IFNULL(MAX(orden), 0) + 1 FROM panel_tramos_viales_fotos WHERE tramo_string_id = ?");
    $stmtO->execute([$tramo_id]);
    $orden = $stmtO->fetchColumn();

    $stmt = $db->prepare("INSERT INTO panel_tramos_viales_fotos (tramo_string_id, tipo, archivo, archivo_thumb, orden) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$tramo_id, $tipo, $file_main, $file_thumb, $orden]);
    $new_id = $db->lastInsertId();

    log_action('rv_foto', "Subió foto ($tipo) para el tramo: $tramo_id");
    json_response(['ok' => true, 'id' => $new_id, 'archivo' => $file_main, 'archivo_thumb' => $file_thumb]);
}

// =========================================================
// ENDPOINT: ACTUALIZAR METADATOS Y TIPO
// =========================================================
if ($action === 'update_meta') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $tramo_id = $input['tramo_string_id'] ?? '';
    $tipo = in_array($input['tipo'] ?? '', ['portada', 'galeria', 'antes', 'despues']) ? $input['tipo'] : 'galeria';
    $titulo = trim($input['titulo'] ?? '');
    $desc = trim($input['descripcion_corta'] ?? '');
    
    if ($id <= 0 || empty($tramo_id)) json_response(['ok' => false, 'error' => 'Datos inválidos'], 400);

    // Garantizar portada única
    if ($tipo === 'portada') {
        $db->prepare("UPDATE panel_tramos_viales_fotos SET tipo = 'galeria' WHERE tramo_string_id = ? AND tipo = 'portada' AND id != ?")->execute([$tramo_id, $id]);
    }

    $stmt = $db->prepare("UPDATE panel_tramos_viales_fotos SET tipo = ?, titulo = ?, descripcion_corta = ? WHERE id = ?");
    $stmt->execute([$tipo, $titulo, $desc, $id]);
    json_response(['ok' => true]);
}

// =========================================================
// ENDPOINT: TOGGLE ACTIVO (Borrado lógico)
// =========================================================
if ($action === 'toggle_activo') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $activo = isset($input['activo']) ? (int)$input['activo'] : 1;
    
    if ($id <= 0) json_response(['ok' => false, 'error' => 'ID inválido'], 400);
    $db->prepare("UPDATE panel_tramos_viales_fotos SET activo = ? WHERE id = ?")->execute([$activo, $id]);
    json_response(['ok' => true]);
}

// =========================================================
// ENDPOINT: LISTAR_ADMIN (Muestra activas e inactivas)
// =========================================================
if ($action === 'listar_admin') {
    require_admin();
    $tramo_id = $_GET['tramo_id'] ?? '';
    $stmt = $db->prepare("SELECT * FROM panel_tramos_viales_fotos WHERE tramo_string_id = ? ORDER BY orden ASC, id DESC");
    $stmt->execute([$tramo_id]);
    json_response(['ok' => true, 'fotos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// =========================================================
// ENDPOINT: LISTAR_PUBLICO (Solo datos esenciales y activas)
// =========================================================
if ($action === 'listar_publico') {
    $tramo_id = $_GET['tramo_id'] ?? '';
    $stmt = $db->prepare("SELECT id, tipo, archivo, archivo_thumb, titulo, descripcion_corta, orden FROM panel_tramos_viales_fotos WHERE tramo_string_id = ? AND activo = 1 ORDER BY orden ASC, id DESC");
    $stmt->execute([$tramo_id]);
    json_response(['ok' => true, 'fotos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

json_response(['ok' => false, 'error' => 'Acción no válida'], 400);