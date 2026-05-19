<?php
// actualizar.php - El Túnel Secreto Ninja
$api_user = $_POST['cpanel_user'] ?? '';
$api_token = $_POST['cpanel_token'] ?? '';

// Si alguien intenta entrar sin la llave secreta, lo bloqueamos
if (empty($api_user) || empty($api_token)) {
    http_response_code(403);
    die("Acceso denegado.");
}

// PLAN OMEGA: Comando nativo interno del servidor
$repo_local = "/home/tacnwddf/repositories/web-fuerza-tacna";
$out1 = shell_exec("/usr/bin/uapi VersionControl update repository_root=" . escapeshellarg($repo_local) . " 2>&1");
$out2 = shell_exec("/usr/bin/uapi VersionControl deploy repository_root=" . escapeshellarg($repo_local) . " 2>&1");

echo "Update:\n$out1\nDeploy:\n$out2";

echo "¡Despliegue automático exitoso!";
?>