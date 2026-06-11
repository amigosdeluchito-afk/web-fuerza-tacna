<?php
require_once 'config.php';

// Requerir que esté logueado y que sea admin
require_login();
require_admin();

$db = get_db_connection();

// Crear tabla de configuración si no existe
$db->exec("CREATE TABLE IF NOT EXISTS panel_configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor MEDIUMTEXT NOT NULL,
    fecha_actualizacion DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Valores por defecto
$stmtConf = $db->prepare("INSERT IGNORE INTO panel_configuracion (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())");
$stmtConf->execute(['sitio_privado_activo', '1']);
$stmtConf->execute(['sitio_privado_password', 'FT666']);

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_config') {
    $activo = isset($_POST['sitio_privado_activo']) ? '1' : '0';
    $password = trim($_POST['sitio_privado_password'] ?? 'FT666');
    if (empty($password)) $password = 'FT666';

    $stmt = $db->prepare("UPDATE panel_configuracion SET valor=?, fecha_actualizacion=NOW() WHERE clave=?");
    $stmt->execute([$activo, 'sitio_privado_activo']);
    $stmt->execute([$password, 'sitio_privado_password']);
    
    $mensaje = "Configuración del escudo temporal actualizada correctamente.";
}

// Obtener valores actuales
$stmtC = $db->query("SELECT clave, valor FROM panel_configuracion WHERE clave IN ('sitio_privado_activo', 'sitio_privado_password')");
$configs = $stmtC->fetchAll(PDO::FETCH_ASSOC);
$sitio_privado_activo = '1';
$sitio_privado_password = 'FT666';
foreach ($configs as $c) {
    if ($c['clave'] === 'sitio_privado_activo') $sitio_privado_activo = $c['valor'];
    if ($c['clave'] === 'sitio_privado_password') $sitio_privado_password = $c['valor'];
}

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

    /* Estilos del Menú Desplegable */
    .dropdown { position: relative; display: inline-block; margin-right: 16px; }
    .dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 15px; }
    .dropdown .dropbtn { background: transparent; border: none; color: #9ca3af; font-size: 14px; cursor: pointer; font-family: inherit; padding: 0; display: flex; align-items: center; outline: none; }
    .dropdown .dropbtn.active { color: #ffffff; font-weight: 600; }
    .dropdown:hover .dropbtn { color: #e5e7eb; }
    .dropdown-content { display: none; position: absolute; background-color: #0f172a; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5); z-index: 1; border-radius: 8px; border: 1px solid #1e293b; top: 100%; left: 0; padding: 8px 0; margin-top: 10px; }
    .dropdown-content a { color: #9ca3af !important; padding: 8px 16px !important; text-decoration: none; display: block; margin: 0 !important; font-size: 13px !important; }
    .dropdown-content a:hover { background-color: #1e293b; color: #fff !important; }
    .dropdown-content a.active { color: #3b82f6 !important; background-color: rgba(59,130,246,0.1); font-weight: 600; }
    .dropdown:hover .dropdown-content { display: block; }
  </style>
</head>
<body>

<header>
12  <style>.nav-scroll::-webkit-scrollbar { height: 4px; } .nav-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }</style>
  <nav class="nav-scroll" style="display:flex; align-items:center; overflow-x:auto; white-space:nowrap; width:100%; margin-right:15px; scrollbar-width:thin; scrollbar-color:#334155 transparent; padding-bottom: 4px;">
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">📷 Fotos</a>
    <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">➕ Agregar Obra</a>
    <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">✏️ Editar Obra</a>
    <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">👁️ Visibilidad</a>
    <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">🗂️ Segmentos</a>
    <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">⏳ Cronología</a>
    <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">👥 Candidatos</a>
    <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">🧠 Cerebro IA</a>
    <a href="ia_cerebro_obras.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_cerebro_obras.php' ? 'active' : '' ?>">🏗️ Obras IA</a>
    <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">📚 Base IA</a>
    <a href="ia_fuentes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_fuentes.php' ? 'active' : '' ?>">🔗 Fuentes IA</a>
    <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">📊 Stats IA</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
    <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
    <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>
  
<main>
  <h1>Registro de Accesos y Seguridad</h1>
  
  <?php if ($mensaje): ?>
    <div style="background: #10b981; color: white; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <div style="background: #0b1020; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
      <h2 style="margin-top: 0; font-size: 18px; color: #f8fafc;">🛡️ Escudo Temporal (Modo Privado)</h2>
      <p style="color: #94a3b8; font-size: 14px;">Aquí puedes activar o desactivar la pantalla de contraseña que aparece al entrar a la página web pública.</p>
      
      <form method="POST" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
          <input type="hidden" name="action" value="guardar_config">
          
          <div>
              <label style="display: block; color: #cbd5e1; font-size: 13px; margin-bottom: 8px;">Estado del Escudo:</label>
              <label style="display: flex; align-items: center; cursor: pointer; gap: 10px; background: #020617; padding: 10px 15px; border-radius: 6px; border: 1px solid #334155;">
                  <input type="checkbox" name="sitio_privado_activo" style="width: 18px; height: 18px; accent-color: #3b82f6;" <?= $sitio_privado_activo === '1' ? 'checked' : '' ?>>
                  <span style="color: <?= $sitio_privado_activo === '1' ? '#10b981' : '#ef4444' ?>; font-weight: bold;">
                      <?= $sitio_privado_activo === '1' ? 'ACTIVO (Pide Contraseña)' : 'DESACTIVADO (Público)' ?>
                  </span>
              </label>
          </div>
          
          <div style="flex: 1; min-width: 200px;">
              <label style="display: block; color: #cbd5e1; font-size: 13px; margin-bottom: 8px;">Contraseña de Acceso:</label>
              <input type="text" name="sitio_privado_password" value="<?= htmlspecialchars($sitio_privado_password) ?>" style="width: 100%; padding: 10px 15px; border-radius: 6px; border: 1px solid #334155; background: #020617; color: white; font-size: 14px;" required>
          </div>
          
          <div>
              <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 11px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s; height: 41px;">
                  Guardar Configuración
              </button>
          </div>
      </form>
  </div>

  <h2 style="font-size: 18px; color: #f8fafc; border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 0;">Historial de Ingresos Exitosos</h2>
  <p style="color: #94a3b8; font-size: 14px;">Esta lista muestra las direcciones IP de quienes han colocado la contraseña en la web.</p>

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