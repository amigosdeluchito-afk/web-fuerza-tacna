<?php
// log_access.php - Registra quién ingresa exitosamente mediante el escudo temporal

// Asegurar que la carpeta 'data' existe
$logDir = __DIR__ . '/data';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/accesos_escudo.log';

// Obtener la IP real (incluso si pasas por proxies)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

// Obtener fecha y navegador
$date = date('Y-m-d H:i:s');
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

// Escribir en el archivo
$entry = "[$date] IP: $ip - Navegador: $userAgent" . PHP_EOL;
file_put_contents($logFile, $entry, FILE_APPEND);

echo json_encode(['ok' => true]);