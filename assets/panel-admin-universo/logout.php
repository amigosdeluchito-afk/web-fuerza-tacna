<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_csrf();

$params = session_get_cookie_params();
if ((bool)ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $params['secure'] ?? false,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_unset();     // borra variables de sesion
session_destroy();   // destruye la sesion

header('Location: login.php');
exit;
