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
  </style>
</head>
<body>
<header>
  <nav>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
    <a href="usuarios.php" class="active">👤 Usuarios</a>
    <a href="historial.php">🕒 Historial</a>
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
