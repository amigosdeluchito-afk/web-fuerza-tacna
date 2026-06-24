<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = get_db_connection();

// --- AUTOCREACIÓN DE TABLA Y DATOS INICIALES ---
$db->exec("CREATE TABLE IF NOT EXISTS `panel_juegos_config` (
  `game_id` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT 'Un divertido minijuego para pasar el rato.',
  `status` enum('active','soon','disabled') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 10,
  `default_difficulty` varchar(50) DEFAULT 'medium',
  `icon` varchar(50) DEFAULT '🎮',
  `config_json` longtext,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

try {
    $db->exec("ALTER TABLE `panel_juegos_config` ADD COLUMN `config_json` longtext NULL");
} catch (PDOException $e) {
    // La columna ya existe en instalaciones actualizadas.
}

$stmtCheck = $db->query("SELECT COUNT(*) FROM panel_juegos_config");
if ($stmtCheck->fetchColumn() == 0) {
    $db->exec("INSERT INTO `panel_juegos_config` (`game_id`, `title`, `description`, `status`, `sort_order`, `default_difficulty`, `icon`, `config_json`) VALUES
    ('tictactoe', 'Tres en Raya', 'El clásico juego del michi. ¿Podrás ganarle a Luchito?', 'active', 10, 'medium', '❌⭕', NULL),
    ('memory', 'Memoria', 'Encuentra los pares de emojis de Fuerza Tacna. ¡A ejercitar la mente!', 'active', 20, 'medium', '🧠', NULL),
    ('trivia', 'Trivia Tacneña', 'Demuestra cuánto sabes sobre nuestra heroica ciudad y sus obras.', 'active', 30, 'medium', '❓', NULL),
    ('rock-paper-scissors', 'Piedra, Papel o Tijera', 'Reta a Luchito en este clásico juego de manos. ¡Suerte!', 'active', 40, 'medium', '✌️🤚', NULL),
    ('find-luchito', 'Encuentra a Luchito', 'Luchito se ha escondido entre la gente. ¡Encuéntralo antes de que se acabe el tiempo!', 'active', 50, 'medium', '🐻', NULL),
    ('puzzle', 'Rompecabezas', 'Arma las imágenes de las obras más importantes de Tacna.', 'soon', 60, 'medium', '🧩', NULL)");
}
// ------------------------------------------------

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM panel_juegos_config ORDER BY sort_order ASC");
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'games' => $games]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al consultar la base de datos.']);
    }
    exit;
}

if ($method === 'POST') {
    // Escudo: Solo Admins
    require_login();
    require_admin();

    if (($_POST['action'] ?? '') === 'upload_find_image') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'No llego la imagen.']);
            exit;
        }

        if ($_FILES['image']['size'] > (8 * 1024 * 1024)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'La imagen supera los 8 MB.']);
            exit;
        }

        $info = @getimagesize($_FILES['image']['tmp_name']);
        if (!$info || empty($info['mime'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El archivo no es una imagen valida.']);
            exit;
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$info['mime']])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Usa JPG, PNG, WEBP o GIF.']);
            exit;
        }

        $level = max(1, min(10, (int)($_POST['level'] ?? 1)));
        $ext = $allowed[$info['mime']];
        $destDir = realpath(__DIR__ . '/../universoobras');
        if ($destDir === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se encontro la carpeta publica.']);
            exit;
        }

        $uploadDir = $destDir . '/IMG/juegos/find-luchito';
        ensure_dir($uploadDir);

        $filename = 'nivel-' . $level . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'url' => '/assets/universoobras/IMG/juegos/find-luchito/' . rawurlencode($filename),
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['games']) || !is_array($input['games'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Datos inválidos.']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE panel_juegos_config SET title = :title, description = :description, status = :status, sort_order = :sort_order, default_difficulty = :default_difficulty, icon = :icon, config_json = COALESCE(:config_json, config_json) WHERE game_id = :game_id");
        foreach ($input['games'] as $game) {
            $configJson = $game['config_json'] ?? null;
            if ($configJson !== null) {
                $decodedConfig = json_decode($configJson, true);
                $configJson = json_last_error() === JSON_ERROR_NONE
                    ? json_encode($decodedConfig, JSON_UNESCAPED_UNICODE)
                    : null;
            }

            $stmt->execute([
                ':game_id' => $game['game_id'],
                ':title' => $game['title'],
                ':description' => $game['description'],
                ':status' => $game['status'],
                ':sort_order' => (int)$game['sort_order'],
                ':default_difficulty' => $game['default_difficulty'],
                ':icon' => $game['icon'],
                ':config_json' => $configJson
            ]);
        }
        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'Configuración de juegos guardada.']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
