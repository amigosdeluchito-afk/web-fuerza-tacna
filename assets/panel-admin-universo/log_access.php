<?php
// log_access.php - Registra quién ingresa exitosamente mediante el escudo temporal
require_once __DIR__ . '/config.php';

// Obtener la IP real (incluso si pasas por proxies)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

// Obtener fecha y navegador
$date = date('Y-m-d H:i:s');
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS panel_accesos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time DATETIME NOT NULL,
        ip VARCHAR(100) NULL,
        user_agent TEXT NULL
    )");
    
    $stmt = $db->prepare("INSERT INTO panel_accesos (time, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$date, $ip, $userAgent]);
    
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('log_access failed: ' . get_class($e));
    echo json_encode(['ok' => false, 'error' => 'Error interno'], JSON_UNESCAPED_UNICODE);
    exit;
}
