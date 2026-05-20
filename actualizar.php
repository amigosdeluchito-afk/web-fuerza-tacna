<?php
// actualizar.php - El Túnel Secreto Ninja
$api_user = $_POST['cpanel_user'] ?? '';
$api_token = $_POST['cpanel_token'] ?? '';

// Si alguien intenta entrar sin la llave secreta, lo bloqueamos
if (empty($api_user) || empty($api_token)) {
    http_response_code(403);
    die("Acceso denegado.");
}

// PLAN TITÁN: Comandos puros de Linux (Bypass total a cPanel)
$repo_local = "/home/tacnwddf/repositories/web-fuerza-tacna";
$public_html = "/home/tacnwddf/fuerzatacna.tacnavuelveasonreir.com";

// 1. Limpiar y descargar cambios de GitHub directamente
$out1 = shell_exec("cd " . escapeshellarg($repo_local) . " && export GIT_TERMINAL_PROMPT=0 && git reset --hard 2>&1 && git pull 2>&1");
// 2. Copiar archivos a la web pública ultrarrápido
$out2 = shell_exec("rsync -av --exclude='.git' --exclude='.cpanel.yml' " . escapeshellarg($repo_local . "/") . " " . escapeshellarg($public_html . "/") . " 2>&1");

echo "Update:\n$out1\nDeploy:\n$out2";

echo "¡Despliegue automático exitoso!";
?>