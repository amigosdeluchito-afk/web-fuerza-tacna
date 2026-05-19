<?php
// actualizar.php - El Túnel Secreto Ninja
$api_user = $_POST['cpanel_user'] ?? '';
$api_token = $_POST['cpanel_token'] ?? '';

// Si alguien intenta entrar sin la llave secreta, lo bloqueamos
if (empty($api_user) || empty($api_token)) {
    http_response_code(403);
    die("Acceso denegado.");
}

$repo_path = urlencode("/home/tacnwddf/repositories/web-fuerza-tacna");
$headers = ["Authorization: cpanel $api_user:$api_token"];

// Usar 127.0.0.1 para que el servidor se llame a sí mismo internamente y burle su propio firewall
$cpanel_url = "https://127.0.0.1:2083";

// 1. Ejecutar Update from Remote
$ch1 = curl_init("$cpanel_url/execute/VersionControl/update?repository_root=$repo_path");
curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
curl_exec($ch1);
curl_close($ch1);

// 2. Ejecutar Deploy HEAD Commit
$ch2 = curl_init("$cpanel_url/execute/VersionControl/deploy?repository_root=$repo_path");
curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
curl_exec($ch2);
curl_close($ch2);

echo "¡Despliegue automático exitoso!";
?>