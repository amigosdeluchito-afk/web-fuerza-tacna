<?php
// api_candidatos.php - API para gestionar a los candidatos
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function method_not_allowed(string $allow): void {
    header('Allow: ' . $allow);
    json_response(['ok' => false, 'error' => 'Metodo no permitido'], 405);
}

function is_session_present(): bool {
    return !empty($_SESSION['user']);
}

class CandidateUploadException extends RuntimeException {
    private int $responseStatus;

    public function __construct(string $message, int $responseStatus = 422) {
        parent::__construct($message);
        $this->responseStatus = $responseStatus;
    }

    public function responseStatus(): int {
        return $this->responseStatus;
    }
}

function save_candidate_image_upload(string $field, array &$createdFiles): ?string {
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new CandidateUploadException('La imagen no se recibio correctamente', 422);
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new CandidateUploadException('La imagen no se recibio correctamente', 422);
    }

    $reportedSize = (int)($file['size'] ?? 0);
    if ($reportedSize <= 0) {
        throw new CandidateUploadException('La imagen supera el limite permitido', 422);
    }

    $originalName = (string)($file['name'] ?? '');
    $baseName = basename($originalName);
    if ($originalName !== '' && ($baseName !== $originalName || strpos($baseName, "\0") !== false)) {
        throw new CandidateUploadException('Nombre de imagen no permitido', 422);
    }

    $realSize = @filesize($tmpPath);
    if ($realSize === false) {
        throw new CandidateUploadException('No se pudo validar la imagen', 422);
    }

    $maxBytes = 8 * 1024 * 1024;
    if ($realSize <= 0 || $realSize > $maxBytes) {
        throw new CandidateUploadException('La imagen supera el limite permitido', 422);
    }

    if (!extension_loaded('gd') || !function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        error_log('Candidate image upload failed: GD or imagewebp unavailable');
        throw new CandidateUploadException('No se pudo procesar la imagen', 500);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        error_log('Candidate image upload failed: finfo unavailable');
        throw new CandidateUploadException('No se pudo procesar la imagen', 500);
    }

    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
        throw new CandidateUploadException('Formato de imagen no permitido', 422);
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo || empty($imageInfo['mime']) || !in_array($imageInfo['mime'], $allowedMimes, true) || $imageInfo['mime'] !== $mime) {
        throw new CandidateUploadException('La imagen no es valida', 422);
    }

    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);
    if ($width <= 0 || $height <= 0 || $width > 6000 || $height > 6000 || ($width * $height) > 20000000) {
        throw new CandidateUploadException('Las dimensiones de la imagen no son validas', 422);
    }

    $loader = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        default => null,
    };

    if ($loader === null || !function_exists($loader)) {
        error_log('Candidate image upload failed: missing loader for MIME ' . $mime);
        throw new CandidateUploadException('No se pudo procesar la imagen', 500);
    }

    $src = @$loader($tmpPath);
    if (!$src) {
        throw new CandidateUploadException('La imagen no se pudo decodificar', 422);
    }

    $dst = null;
    $destPath = null;

    try {
        $dst = imagecreatetruecolor($width, $height);
        if (!$dst) {
            throw new CandidateUploadException('No se pudo procesar la imagen', 500);
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

        if (!imagecopy($dst, $src, 0, 0, 0, 0, $width, $height)) {
            throw new CandidateUploadException('No se pudo procesar la imagen', 500);
        }

        $uploadDir = __DIR__ . '/../universoobras/IMG/candidatos/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            error_log('Candidate image upload failed: destination directory could not be created');
            throw new CandidateUploadException('No se pudo guardar la imagen', 500);
        }

        $baseDir = realpath($uploadDir);
        if ($baseDir === false || !is_dir($baseDir)) {
            error_log('Candidate image upload failed: destination directory unavailable');
            throw new CandidateUploadException('No se pudo guardar la imagen', 500);
        }

        do {
            $filename = bin2hex(random_bytes(16)) . '.webp';
            $destPath = $baseDir . DIRECTORY_SEPARATOR . $filename;
        } while (file_exists($destPath));

        $normalizedBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalizedDest = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destPath);
        if (strncmp($normalizedDest, $normalizedBase, strlen($normalizedBase)) !== 0) {
            error_log('Candidate image upload failed: destination path escaped upload directory');
            throw new CandidateUploadException('No se pudo guardar la imagen', 500);
        }

        if (!imagewebp($dst, $destPath, 85)) {
            if (is_string($destPath) && file_exists($destPath)) {
                @unlink($destPath);
            }
            throw new CandidateUploadException('No se pudo guardar la imagen', 500);
        }

        if (!@chmod($destPath, 0644)) {
            error_log('Candidate image upload warning: file permissions could not be applied');
        }

        $createdFiles[] = $destPath;
        return $filename;
    } finally {
        if ($dst) {
            imagedestroy($dst);
        }
        imagedestroy($src);
    }
}

function cleanup_candidate_files(array $paths): void {
    foreach ($paths as $path) {
        if (is_string($path) && file_exists($path)) {
            @unlink($path);
        }
    }
}

function ensure_candidate_tiktok_columns(PDO $db) {
    $columns = [
        'tiktok_titulo' => "ALTER TABLE panel_candidatos ADD COLUMN tiktok_titulo VARCHAR(255) NULL",
        'tiktok_descripcion' => "ALTER TABLE panel_candidatos ADD COLUMN tiktok_descripcion VARCHAR(500) NULL",
        'tiktok_url_perfil' => "ALTER TABLE panel_candidatos ADD COLUMN tiktok_url_perfil VARCHAR(500) NULL"
    ];

    foreach ($columns as $column => $sql) {
        $stmt = $db->prepare("SHOW COLUMNS FROM panel_candidatos LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec($sql);
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';
} else {
    method_not_allowed('GET, POST');
}

if (!in_array($action, ['listar', 'obtener', 'guardar'], true)) {
    json_response(['ok' => false, 'error' => 'Accion invalida'], 400);
}

if ($action === 'listar' && $method !== 'GET') {
    method_not_allowed('GET');
}

if ($action === 'obtener' && $method !== 'GET') {
    method_not_allowed('GET');
}

if ($action === 'guardar') {
    if ($method !== 'POST') {
        method_not_allowed('POST');
    }

    if (!is_session_present()) {
        json_response(['ok' => false, 'error' => 'Sesion requerida'], 401);
    }

    require_login();
    if (!csrf_validate()) {
        json_response(['ok' => false, 'error' => 'Solicitud no válida'], 403);
    }
}

try {
    $db = get_db_connection();
    $authenticated = is_session_present();

    // 1. Obtener lista rapida
    if ($action === 'listar') {
        if ($authenticated) {
            $stmt = $db->query("SELECT id, nombres, cargo_flotante, foto_perfil, foto_portada, COALESCE(estado, 1) AS estado FROM panel_candidatos ORDER BY orden ASC, id DESC");
        } else {
            $stmt = $db->query("SELECT id, nombres, cargo_flotante, foto_perfil, foto_portada, COALESCE(estado, 1) AS estado FROM panel_candidatos WHERE COALESCE(estado, 1) = 1 ORDER BY orden ASC, id DESC");
        }

        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(['ok' => true, 'candidatos' => $candidatos]);
    }

    // 2. Obtener informacion de un candidato especifico
    if ($action === 'obtener') {
        $id_raw = $_GET['id'] ?? '';
        if (!ctype_digit((string)$id_raw) || (int)$id_raw <= 0) {
            json_response(['ok' => false, 'error' => 'ID invalido'], 400);
        }

        $id = (int)$id_raw;

        if ($authenticated) {
            $stmt = $db->prepare("SELECT * FROM panel_candidatos WHERE id = ?");
        } else {
            $stmt = $db->prepare("SELECT id, nombres, cargo_flotante, frase_cita, biografia, fb_titulo, fb_descripcion, fb_url_perfil, tiktok_titulo, tiktok_descripcion, tiktok_url_perfil, foto_perfil, foto_portada, COALESCE(estado, 1) AS estado FROM panel_candidatos WHERE id = ? AND COALESCE(estado, 1) = 1");
        }

        $stmt->execute([$id]);
        $candidato = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$candidato) {
            json_response(['ok' => false, 'error' => 'Candidato no encontrado'], 404);
        }

        $stmt = $db->prepare("SELECT * FROM panel_candidato_etiquetas WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['etiquetas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM panel_candidato_trayectoria WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['trayectoria'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM panel_candidato_propuestas WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['propuestas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(['ok' => true, 'candidato' => $candidato]);
    }

    // 3. Guardar Candidato (Crear o Actualizar) y Procesar Imagenes
    if ($action === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombres = trim($_POST['nombres'] ?? '');
        $cargo_flotante = trim($_POST['cargo_flotante'] ?? '');
        $frase_cita = trim($_POST['frase_cita'] ?? '');
        $biografia = trim($_POST['biografia'] ?? '');
        $fb_titulo = trim($_POST['fb_titulo'] ?? '');
        $fb_descripcion = trim($_POST['fb_descripcion'] ?? '');
        $fb_url_perfil = trim($_POST['fb_url_perfil'] ?? '');
        $tiktok_titulo = trim($_POST['tiktok_titulo'] ?? '');
        $tiktok_descripcion = trim($_POST['tiktok_descripcion'] ?? '');
        $tiktok_url_perfil = trim($_POST['tiktok_url_perfil'] ?? '');
        $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

        if ($nombres === '') {
            json_response(['ok' => false, 'error' => 'El nombre es obligatorio'], 422);
        }

        ensure_candidate_tiktok_columns($db);
        $db->beginTransaction();
        $createdImageFiles = [];

        try {
            $foto_perfil = save_candidate_image_upload('foto_perfil', $createdImageFiles);
            $foto_portada = save_candidate_image_upload('foto_portada', $createdImageFiles);

            if ($id > 0) {
                // Actualizar candidato existente
                $sql = "UPDATE panel_candidatos SET nombres=?, cargo_flotante=?, frase_cita=?, biografia=?, fb_titulo=?, fb_descripcion=?, fb_url_perfil=?, tiktok_titulo=?, tiktok_descripcion=?, tiktok_url_perfil=?, estado=?";
                $params = [$nombres, $cargo_flotante, $frase_cita, $biografia, $fb_titulo, $fb_descripcion, $fb_url_perfil, $tiktok_titulo, $tiktok_descripcion, $tiktok_url_perfil, $estado];

                if ($foto_perfil) {
                    $sql .= ", foto_perfil=?";
                    $params[] = $foto_perfil;
                }
                if ($foto_portada) {
                    $sql .= ", foto_portada=?";
                    $params[] = $foto_portada;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insertar nuevo candidato
                $stmtOrden = $db->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM panel_candidatos");
                $orden = (int)$stmtOrden->fetchColumn();

                $sql = "INSERT INTO panel_candidatos (nombres, cargo_flotante, frase_cita, biografia, fb_titulo, fb_descripcion, fb_url_perfil, tiktok_titulo, tiktok_descripcion, tiktok_url_perfil, foto_perfil, foto_portada, estado, orden) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nombres, $cargo_flotante, $frase_cita, $biografia, $fb_titulo, $fb_descripcion, $fb_url_perfil, $tiktok_titulo, $tiktok_descripcion, $tiktok_url_perfil, $foto_perfil, $foto_portada, $estado, $orden]);
                $id = $db->lastInsertId();
            }

            // -- Procesar Relaciones Dinamicas (Eliminar viejas e insertar nuevas para mantener la sincronia) --

            // 1. Etiquetas (Badges)
            $db->prepare("DELETE FROM panel_candidato_etiquetas WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['etiquetas']) && is_array($_POST['etiquetas'])) {
                $stmtEt = $db->prepare("INSERT INTO panel_candidato_etiquetas (candidato_id, icono, texto, orden) VALUES (?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['etiquetas'] as $et) {
                    $icono = trim($et['icono'] ?? '');
                    $texto = trim($et['texto'] ?? '');
                    if ($texto !== '' || $icono !== '') {
                        $stmtEt->execute([$id, $icono, $texto, $ord++]);
                    }
                }
            }

            // 2. Trayectoria
            $db->prepare("DELETE FROM panel_candidato_trayectoria WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['trayectoria']) && is_array($_POST['trayectoria'])) {
                $stmtTr = $db->prepare("INSERT INTO panel_candidato_trayectoria (candidato_id, periodo, descripcion, orden) VALUES (?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['trayectoria'] as $tr) {
                    $periodo = trim($tr['periodo'] ?? '');
                    $descripcion = trim($tr['descripcion'] ?? '');
                    if ($periodo !== '' || $descripcion !== '') {
                        $stmtTr->execute([$id, $periodo, $descripcion, $ord++]);
                    }
                }
            }

            // 3. Propuestas
            $db->prepare("DELETE FROM panel_candidato_propuestas WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['propuestas']) && is_array($_POST['propuestas'])) {
                $stmtPr = $db->prepare("INSERT INTO panel_candidato_propuestas (candidato_id, icono, titulo, descripcion, orden) VALUES (?, ?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['propuestas'] as $pr) {
                    $icono = trim($pr['icono'] ?? '');
                    $titulo = trim($pr['titulo'] ?? '');
                    $descripcion = trim($pr['descripcion'] ?? '');
                    if ($titulo !== '' || $descripcion !== '') {
                        $stmtPr->execute([$id, $icono, $titulo, $descripcion, $ord++]);
                    }
                }
            }

            $db->commit();
            json_response(['ok' => true, 'id' => $id]);
        } catch (CandidateUploadException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            cleanup_candidate_files($createdImageFiles);
            if ($e->responseStatus() >= 500) {
                error_log('Candidate image upload failed with internal status');
                json_response(['ok' => false, 'error' => 'Error interno al procesar la imagen'], 500);
            }
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            cleanup_candidate_files($createdImageFiles);
            error_log('Candidate save failed after image validation: ' . get_class($e));
            json_response(['ok' => false, 'error' => 'Error interno al guardar el candidato'], 500);
        }
    }
} catch (Throwable $e) {
    error_log('Candidate API failed: ' . get_class($e));
    json_response(['ok' => false, 'error' => 'Error interno'], 500);
}
