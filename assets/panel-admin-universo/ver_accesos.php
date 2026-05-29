<?php
require_once 'config.php';

// Requerir que esté logueado y que sea admin
require_login();
require_admin();

$db = get_db_connection();
$db->exec("CREATE TABLE IF NOT EXISTS panel_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time DATETIME NOT NULL,
    ip VARCHAR(100) NULL,
    user_agent TEXT NULL
)");

$stmt = $db->query("SELECT * FROM panel_accesos ORDER BY time DESC LIMIT 500");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Accesos – Panel</title>
  <style>
    body {
      background:#050816; color:#fff;
      font-family: system-ui, -apple-system, sans-serif;
      margin: 0;
    }
    header { padding:16px 24px; border-bottom:1px solid #111827; display:flex; justify-content:space-between; align-items:center;}
    nav a { color:#9ca3af; margin-right:16px; text-decoration:none; font-size:14px; }
    nav a.active { color:#ffffff; font-weight:600; }
    .user { font-size:13px; color:#9ca3af; }
    main { padding: 30px; max-width: 1000px; margin: 0 auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #0b1020; border-radius: 8px; overflow: hidden; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #1e293b; text-align: left; }
    th { background: #1e3a8a; color: #93c5fd; font-weight: 600; }
    tr:hover { background: #172554; }
    .empty { padding: 20px; text-align: center; color: #94a3b8; }
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
    <a href="historial.php">🕒 Historial</a>
    <a href="ver_accesos.php" class="active">🕵️ Accesos IP</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>
  
<main>
  <h1>Registro de Accesos (Escudo Temporal)</h1>
  <p>Esta lista muestra las direcciones IP y dispositivos de las personas que han colocado la contraseña correctamente en la web.</p>

  <table>
    <thead>
      <tr>
        <th>Fecha y Hora</th>
        <th>Dirección IP</th>
        <th>Navegador / Dispositivo</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="3" class="empty">No hay accesos registrados todavía.</td></tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars($log['time']) ?></td>
            <td><?= htmlspecialchars($log['ip']) ?></td>
            <td style="font-size: 0.85em; color: #cbd5e1;"><?= htmlspecialchars($log['user_agent']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</main>

</body>
</html>