<?php
require_once 'config.php';
require_login();
require_admin(); // si quieres que solo admin vea el historial

$db = get_db_connection();
$db->exec("CREATE TABLE IF NOT EXISTS panel_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time DATETIME NOT NULL,
    user VARCHAR(50) NULL,
    tipo VARCHAR(50) NULL,
    detalle TEXT NULL,
    extra TEXT NULL
)");

$search = $_GET['q'] ?? '';
$user_filter = $_GET['u'] ?? '';

$query = "SELECT * FROM panel_historial WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (detalle LIKE ? OR tipo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($user_filter !== '') {
    $query .= " AND user = ?";
    $params[] = $user_filter;
}

$query .= " ORDER BY time DESC LIMIT 500";
$stmt = $db->prepare($query);
$stmt->execute($params);
$entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios para el filtro
$users_stmt = $db->query("SELECT DISTINCT user FROM panel_historial WHERE user IS NOT NULL ORDER BY user");
$distinct_users = $users_stmt->fetchAll(PDO::FETCH_COLUMN);
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
    .search-box { background: #0b1020; padding: 16px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; border: 1px solid #1f2937;}
    .search-box input[type="text"], .search-box select { background: #020617; border: 1px solid #374151; color: #fff; border-radius: 8px; padding: 8px 12px; font-size: 14px; outline: none; }
    .search-box input[type="text"] { flex: 1; }
    .search-box button { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: bold; }
    .search-box button:hover { background: #1d4ed8; }
    .search-box a.clear { color: #9ca3af; text-decoration: none; font-size: 13px; margin-left: 10px; }
    .search-box a.clear:hover { color: #fff; }
  </style>
</head>
<body>
<header>
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
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
  
  <div class="search-box">
    <form method="GET" style="display:flex; width:100%; gap:10px; align-items:center;">
      <input type="text" name="q" placeholder="Buscar acción o detalle (ej. eliminó, foto...)" value="<?= htmlspecialchars($search) ?>">
      <select name="u">
        <option value="">Todos los usuarios</option>
        <?php foreach ($distinct_users as $du): ?>
          <option value="<?= htmlspecialchars($du) ?>" <?= $user_filter === $du ? 'selected' : '' ?>><?= htmlspecialchars($du) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Buscar</button>
      <?php if ($search || $user_filter): ?>
        <a href="historial.php" class="clear">Limpiar filtros</a>
      <?php endif; ?>
    </form>
  </div>

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
