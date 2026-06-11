<?php
require_once 'config.php';
require_login();
require_admin();

$users   = get_users();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $role     = $_POST['role'] ?? 'editor';
    $password = $_POST['password'] ?? '';

    if ($accion === 'guardar') {
        if ($username === '' || $password === '') {
            $mensaje = 'Usuario y contraseña son obligatorios.';
        } else {
            $users[$username] = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role'     => $role
            ];
            save_users($users);
            log_action('usuario_guardar', "Guardó usuario $username ($role)");
            $mensaje = 'Usuario guardado correctamente.';
        }
    } elseif ($accion === 'eliminar') {
        if ($username === 'admin') {
            $mensaje = 'No se puede eliminar al usuario admin.';
        } elseif (isset($users[$username])) {
            unset($users[$username]);
            save_users($users);
            log_action('usuario_eliminar', "Eliminó usuario $username");
            $mensaje = 'Usuario eliminado.';
        }
    }

    $users = get_users(); // recargar
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios – Panel Universo de Obras</title>
  <style>
    body { background:#050816; color:#fff; font-family:system-ui,sans-serif; margin:0; }
    header { padding:16px 24px; border-bottom:1px solid #111827; display:flex; justify-content:space-between; align-items:center;}
    nav a { color:#9ca3af; margin-right:16px; text-decoration:none; font-size:14px; }
    nav a.active { color:#ffffff; font-weight:600; }
    .user { font-size:13px; color:#9ca3af; }
    main { padding:24px; max-width:900px; margin:0 auto; }
    h1 { margin-top:0; }
    table { width:100%; border-collapse:collapse; margin-top:16px; font-size:14px; }
    th, td { padding:8px 10px; border-bottom:1px solid #1f2937; text-align:left; }
    th { background:#0b1020; }
    form.inline { display:inline; }
    input, select { background:#050816; border:1px solid #374151; color:#fff; border-radius:8px; padding:6px 8px; font-size:14px; }
    .btn { border:none; border-radius:999px; padding:6px 12px; font-size:13px; cursor:pointer; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-danger { background:#dc2626; color:#fff; }
    .msg { margin-top:10px; font-size:13px; color:#a5b4fc; }
    .card { background:#0b1020; padding:16px; border-radius:16px; margin-top:16px; }

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
  <style>.nav-scroll::-webkit-scrollbar { height: 4px; } .nav-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }</style>
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
  <h1>Usuarios del panel</h1>

  <?php if ($mensaje): ?>
    <div class="msg"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2 style="margin-top:0;font-size:16px;">Crear / actualizar usuario</h2>
    <form method="post">
      <input type="hidden" name="accion" value="guardar">
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <div>
          <div>Usuario</div>
          <input type="text" name="username" required>
        </div>
        <div>
          <div>Contraseña</div>
          <input type="password" name="password" required>
        </div>
        <div>
          <div>Rol</div>
          <select name="role">
            <option value="admin">Admin</option>
            <option value="editor" selected>Editor</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:10px;">Guardar usuario</button>
    </form>
  </div>

  <h2 style="margin-top:24px;font-size:16px;">Listado de usuarios</h2>
  <table>
    <thead>
      <tr>
        <th>Usuario</th>
        <th>Rol</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u => $info): ?>
        <tr>
          <td><?= htmlspecialchars($u) ?></td>
          <td><?= htmlspecialchars($info['role'] ?? 'editor') ?></td>
          <td>
            <?php if ($u !== 'admin'): ?>
              <form method="post" class="inline" onsubmit="return confirm('¿Eliminar usuario <?= htmlspecialchars($u) ?>?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="username" value="<?= htmlspecialchars($u) ?>">
                <button type="submit" class="btn btn-danger">Eliminar</button>
              </form>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" style="background:#4f46e5; margin-left: 4px;" onclick="editarUsuario('<?= htmlspecialchars($u) ?>', '<?= htmlspecialchars($info['role'] ?? 'editor') ?>')">Cambiar Clave</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</main>

<script>
function editarUsuario(username, role) {
    // Llena el formulario automáticamente con los datos del usuario
    document.querySelector('input[name="username"]').value = username;
    document.querySelector('select[name="role"]').value = role;
    document.querySelector('input[name="password"]').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' }); // Sube la pantalla suavemente
}
</script>
</body>
</html>
