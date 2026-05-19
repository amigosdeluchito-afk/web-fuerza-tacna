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

// 1. Ejecutar Update from Remote
$ch1 = curl_init("https://tacnavuelveasonreir.com:2083/execute/VersionControl/update?repository_root=$repo_path");
curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);
curl_exec($ch1);
curl_close($ch1);

// 2. Ejecutar Deploy HEAD Commit
$ch2 = curl_init("https://tacnavuelveasonreir.com:2083/execute/VersionControl/deploy?repository_root=$repo_path");
curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
curl_exec($ch2);
curl_close($ch2);

echo "¡Despliegue automático exitoso!";
?>