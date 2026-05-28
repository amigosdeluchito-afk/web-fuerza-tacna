<?php
require_once 'config.php';
require_login();
require_admin(); // si quieres que solo admin vea el historial

$entradas = [];
if (file_exists(LOG_FILE)) {
    $lineas = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lineas) as $line) { // las más recientes primero
        $row = json_decode($line, true);
        if (!$row) continue;
        $entradas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial – Panel Universo de Obras</title>
  <style>
    body { background:#050816; color:#fff; font-family:system-ui,sans-serif; margin:0; }
    header { padding:16px 24px; border-bottom:1px solid #111827; display:flex; justify-content:space-between; align-items:center;}
    nav a { color:#9ca3af; margin-right:16px; text-decoration:none; font-size:14px; }
    nav a.active { color:#ffffff; font-weight:600; }
    .user { font-size:13px; color:#9ca3af; }
    main { padding:24px; max-width:1000px; margin:0 auto; }
    h1 { margin-top:0; }
    table { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; }
    th, td { padding:6px 8px; border-bottom:1px solid #1f2937; text-align:left; }
    th { background:#0b1020; position:sticky; top:0; }
    code { font-size:12px; }
  </style>
</head>
<body>
<header>
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php">👤 Usuarios</a>
    <a href="historial.php" class="active">🕒 Historial</a>
    <a href="ver_accesos.php">🕵️ Accesos IP</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>

<main>
  <h1>Historial de actividad</h1>
  <table>
    <thead>
      <tr>
        <th>Fecha/hora</th>
        <th>Usuario</th>
        <th>Acción</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$entradas): ?>
      <tr><td colspan="4">Aún no hay registros.</td></tr>
    <?php else: ?>
      <?php foreach ($entradas as $e): ?>
        <tr>
          <td><?= htmlspecialchars($e['time'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['user'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['tipo'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['detalle'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</main>
</body>
</html>
