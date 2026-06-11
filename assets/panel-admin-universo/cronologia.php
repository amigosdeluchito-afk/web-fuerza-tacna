<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();
$mensaje = '';

// Directorio donde se guardarán las fotos de la cronología
$uploadDir = __DIR__ . '/../IMG/cronologia/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Manejo de los formularios (Agregar, Editar y Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fecha_texto = $_POST['fecha_texto'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $orden = (int)($_POST['orden'] ?? 0);
        
        $imagen = '';
        // Subir la imagen si se seleccionó una
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = 'historia_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagen);
        }
        
        $stmt = $db->prepare("INSERT INTO cronologia_historia (fecha_texto, titulo, descripcion, imagen, orden, estado) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$fecha_texto, $titulo, $descripcion, $imagen, $orden])) {
            $mensaje = '<div class="msg-success">¡Fecha agregada a la historia exitosamente!</div>';
        } else {
            $mensaje = '<div class="msg-error">Error al guardar la fecha.</div>';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $fecha_texto = $_POST['fecha_texto'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $orden = (int)($_POST['orden'] ?? 0);
        
        $update_query = "UPDATE cronologia_historia SET fecha_texto = ?, titulo = ?, descripcion = ?, orden = ?";
        $params = [$fecha_texto, $titulo, $descripcion, $orden];

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = 'historia_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagen)) {
                $update_query .= ", imagen = ?";
                $params[] = $imagen;
            }
        }
        $update_query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $db->prepare($update_query);
        if ($stmt->execute($params)) {
            $mensaje = '<div class="msg-success">¡Fecha editada exitosamente!</div>';
        } else {
            $mensaje = '<div class="msg-error">Error al actualizar la fecha.</div>';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Obtener nombre de imagen para borrarla del servidor
        $stmt = $db->prepare("SELECT imagen FROM cronologia_historia WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && !empty($item['imagen'])) {
            $imgPath = $uploadDir . $item['imagen'];
            if (file_exists($imgPath)) unlink($imgPath);
        }
        
        $stmt = $db->prepare("DELETE FROM cronologia_historia WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = '<div class="msg-success">Fecha eliminada correctamente.</div>';
    }
}

// Obtener la lista actual para mostrarla en la tabla
$stmt = $db->query("SELECT * FROM cronologia_historia ORDER BY orden ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica para preparar la Edición si se solicita por la URL (?edit=X)
$is_editing = false;
$edit_data = [
    'id' => '', 'fecha_texto' => '', 'titulo' => '', 'descripcion' => '', 'orden' => count($items) + 1, 'imagen' => ''
];

if (isset($_GET['edit'])) {
    $is_editing = true;
    $stmt = $db->prepare("SELECT * FROM cronologia_historia WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $edit_data = $found;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cronología - Panel Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; padding-bottom: 40px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .app-main { margin-top: 72px; display: flex; flex-direction: column; align-items: center; padding: 20px; gap: 20px; }
        .card { width: 100%; max-width: 800px; background: #020617; border-radius: 18px; padding: 24px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7); border: 1px solid rgba(148, 163, 184, 0.15); }
        h1, h2 { margin-top: 0; color: #f9fafb; margin-bottom: 20px; }
        label { font-size: 13px; color: #e5e7eb; display: block; margin-top: 15px; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #0f172a; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus, textarea:focus { border-color: #2563eb; }
        .btn-submit { margin-top: 25px; width: 100%; padding: 12px; background: #2563eb; color: #f9fafb; border: none; font-weight: 600; font-size: 14px; border-radius: 999px; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #1d4ed8; }
        .btn-delete { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: bold; height: 30px; box-sizing: border-box; }
        .btn-delete:hover { background: #dc2626; }
        .btn-edit { background: #eab308; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; transition: background 0.3s; height: 30px; box-sizing: border-box; }
        .btn-edit:hover { background: #ca8a04; }
        .msg-success { background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #059669; }
        .msg-error { background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #dc2626; }
        .row { display: flex; gap: 15px; }
        .row > div { flex: 1; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #1f2937; }
        th { background: #0f172a; color: #9ca3af; font-weight: 600; }
        td { vertical-align: middle; }
        .thumb { width: 60px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #334155; }

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
    <header class="app-header">
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
        <?= htmlspecialchars(current_user() ?? '') ?> ·
        <a href="logout.php" style="color:#9ca3af;">Salir</a>
      </div>
    </header>

    <main class="app-main">
        <!-- Tabla de registros existentes -->
        <div class="card">
            <h1>Cronología de Fuerza Tacna</h1>
            <?= $mensaje ?>
            <h2>Fechas Registradas</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Foto</th>
                            <th>Año</th>
                            <th>Título</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td><?= $item['orden'] ?></td>
                            <td><?= !empty($item['imagen']) ? '<img src="../IMG/cronologia/'.$item['imagen'].'" class="thumb">' : 'Sin foto' ?></td>
                            <td><strong><?= htmlspecialchars($item['fecha_texto']) ?></strong></td>
                            <td><?= htmlspecialchars($item['titulo']) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <a href="cronologia.php?edit=<?= $item['id'] ?>#formulario-edicion" class="btn-edit">✏️ Editar</a>
                                    <form action="cronologia.php" method="POST" onsubmit="return confirm('¿Borrar esta fecha para siempre?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn-delete">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Formulario para agregar / editar -->
        <div class="card" id="formulario-edicion">
            <h2><?= $is_editing ? '✏️ Editar Punto de la Historia' : '➕ Agregar Nuevo Punto' ?></h2>
            
            <form action="cronologia.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $is_editing ? 'edit' : 'add' ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div>
                        <label>Año / Fecha (Ej: "2019", "Actualidad"):</label>
                        <input type="text" name="fecha_texto" value="<?= htmlspecialchars($edit_data['fecha_texto']) ?>" required>
                    </div>
                    <div>
                        <label>Orden (1 va primero, 2 después...):</label>
                        <input type="number" name="orden" value="<?= htmlspecialchars($edit_data['orden']) ?>" required>
                    </div>
                </div>

                <label>Título (El encabezado amarillo en el popup):</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($edit_data['titulo']) ?>" required>
                
                <label>Descripción completa (La historia):</label>
                <textarea name="descripcion" rows="4" required><?= htmlspecialchars($edit_data['descripcion']) ?></textarea>
                
                <label>Foto representativa <?= $is_editing ? '<small style="color:#9ca3af;">(Sube una nueva solo si quieres cambiar la actual)</small>' : '' ?>:</label>
                <?php if($is_editing && !empty($edit_data['imagen'])): ?>
                    <div style="margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px; display: inline-block;">
                        <img src="../IMG/cronologia/<?= $edit_data['imagen'] ?>" style="height: 60px; border-radius: 6px; border: 1px solid #334155; vertical-align: middle;">
                        <span style="font-size: 12px; color: #9ca3af; margin-left: 10px;">Foto actual</span>
                    </div>
                <?php endif; ?>
                <input type="file" name="imagen" id="inputImagen" accept="image/*">
                <div id="previewContainer" style="display: none; margin-top: 10px; padding: 10px; background: rgba(59, 130, 246, 0.05); border-radius: 8px; border: 1px dashed #3b82f6; width: fit-content;">
                    <p style="font-size: 12px; color: #93c5fd; margin: 0 0 8px 0;">📸 Previsualización de la nueva foto:</p>
                    <img id="previewImagen" src="" style="height: 80px; border-radius: 6px; border: 1px solid #334155; display: block; object-fit: cover;">
                </div>
                
                <div class="row">
                    <div><button type="submit" class="btn-submit">✅ <?= $is_editing ? 'Guardar Cambios' : 'Agregar a la Cronología' ?></button></div>
                    <?php if ($is_editing): ?>
                    <div><a href="cronologia.php" class="btn-submit" style="display:block; text-align:center; background:#4b5563; text-decoration:none;">❌ Cancelar Edición</a></div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Script para previsualizar la imagen antes de subirla
        document.getElementById('inputImagen').addEventListener('change', function(event) {
            const previewContainer = document.getElementById('previewContainer');
            const previewImagen = document.getElementById('previewImagen');
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImagen.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                previewImagen.src = '';
                previewContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>