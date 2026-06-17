<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = get_db_connection();

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
    // --- Escudo de Seguridad: Solo Admins pueden guardar ---
    require_login();
    require_admin();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['games']) || !is_array($input['games'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Datos inválidos.']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "UPDATE panel_juegos_config SET 
                title = :title, 
                description = :description, 
                status = :status, 
                sort_order = :sort_order, 
                default_difficulty = :default_difficulty, 
                icon = :icon
            WHERE game_id = :game_id"
        );

        foreach ($input['games'] as $game) {
            $stmt->execute([
                ':game_id' => $game['game_id'],
                ':title' => $game['title'],
                ':description' => $game['description'],
                ':status' => $game['status'],
                ':sort_order' => (int)$game['sort_order'],
                ':default_difficulty' => $game['default_difficulty'],
                ':icon' => $game['icon']
            ]);
        }
        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'Configuración de juegos guardada.']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);