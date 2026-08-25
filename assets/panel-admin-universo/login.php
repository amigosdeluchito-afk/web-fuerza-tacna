<?php
// Registrar todos los errores sin mostrarlos publicamente.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

require_once 'config.php';

// Si ya está logueado, lo mandamos al panel
if (!empty($_SESSION['user'])) {
    header('Location: editar_obra.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (check_login($user, $pass)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        header('Location: editar_obra.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login – Panel de fotos</title>
  <style>
    body {
      background:#050816;
      color:#fff;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display:flex;
      justify-content:center;
      align-items:center;
      height:100vh;
      margin:0;
    }
    .card {
      background:#0b1020;
      padding:24px 28px;
      border-radius:16px;
      width:320px;
      box-shadow:0 0 25px rgba(0,0,0,0.6);
      border:1px solid rgba(148,163,184,0.25);
    }
    h1 {
      font-size:20px;
      margin:0 0 16px;
    }
    label {
      display:block;
      font-size:14px;
      margin-top:10px;
    }
    input {
      width:100%;
      padding:8px 10px;
      border-radius:8px;
      border:1px solid #2b3960;
      background:#050816;
      color:#fff;
      margin-top:4px;
      box-sizing:border-box;
    }
    button {
      margin-top:16px;
      width:100%;
      padding:10px;
      border-radius:999px;
      border:none;
      background:#2563eb;
      color:#fff;
      font-weight:600;
      cursor:pointer;
    }
    button:hover { background:#1d4ed8; }
    .error {
      margin-top:10px;
      color:#f87171;
      font-size:13px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Iniciar sesión</h1>
    <form method="post">
      <?= csrf_field() ?>
      <label>Usuario
        <input type="text" name="username" autocomplete="username">
      </label>
      <label>Contraseña
        <input type="password" name="password" autocomplete="current-password">
      </label>
      <button type="submit">Entrar al panel</button>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
