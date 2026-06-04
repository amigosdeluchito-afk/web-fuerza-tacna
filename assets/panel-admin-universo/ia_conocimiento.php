<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// 1. Crear tabla de conocimiento si no existe
$db->exec("CREATE TABLE IF NOT EXISTS panel_ia_conocimiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(100) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    palabras_clave TEXT,
    prioridad INT DEFAULT 10,
    estado TINYINT DEFAULT 1,
    fuente VARCHAR(100) DEFAULT 'Manual',
    url VARCHAR(255),
    fecha_actualizacion DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mensaje = '';
if (isset($_SESSION['ia_msg'])) {
    $mensaje = $_SESSION['ia_msg'];
    unset($_SESSION['ia_msg']);
}

// 2. Procesar Formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $categoria = trim($_POST['categoria'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $palabras_clave = trim($_POST['palabras_clave'] ?? '');
        $prioridad = (int)($_POST['prioridad'] ?? 10);
        $fuente = trim($_POST['fuente'] ?? 'Manual');
        $url = trim($_POST['url'] ?? '');
        $estado = isset($_POST['estado']) ? 1 : 0;

        if ($categoria && $titulo && $contenido) {
            if ($id) {
                $stmt = $db->prepare("UPDATE panel_ia_conocimiento SET categoria=?, titulo=?, contenido=?, palabras_clave=?, prioridad=?, estado=?, fuente=?, url=?, fecha_actualizacion=NOW() WHERE id=?");
                $stmt->execute([$categoria, $titulo, $contenido, $palabras_clave, $prioridad, $estado, $fuente, $url, $id]);
                $_SESSION['ia_msg'] = "Documento de conocimiento actualizado.";
            } else {
                $stmt = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, url, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$categoria, $titulo, $contenido, $palabras_clave, $prioridad, $estado, $fuente, $url]);
                $_SESSION['ia_msg'] = "Nuevo conocimiento agregado al sistema.";
            }
        } else {
            $_SESSION['ia_msg'] = "Error: Categoría, Título y Contenido son obligatorios.";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $db->prepare("DELETE FROM panel_ia_conocimiento WHERE id=?");
            $stmt->execute([$id]);
            $_SESSION['ia_msg'] = "Documento eliminado.";
        }
    } elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? '';
        $nuevo_estado = $_POST['nuevo_estado'] ?? 1;
        if ($id) {
            $stmt = $db->prepare("UPDATE panel_ia_conocimiento SET estado=?, fecha_actualizacion=NOW() WHERE id=?");
            $stmt->execute([$nuevo_estado, $id]);
            $_SESSION['ia_msg'] = "Estado actualizado.";
        }
    }
    
    header("Location: ia_conocimiento.php");
    exit;
}

// 3. Obtener listado de conocimiento
$stmt = $db->query("SELECT * FROM panel_ia_conocimiento ORDER BY prioridad ASC, id DESC");
$conocimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorías por defecto
$categorias_comunes = ['General', 'Candidatos', 'Obras', 'Propuestas', 'Contacto', 'Historia'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Base de Conocimiento IA - Fuerza Tacna</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 2000; font-family: system-ui, sans-serif; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .btn-ft { background-color: #801039; color: #ffc300; font-weight: bold; border: none; }
        .btn-ft:hover { background-color: #630c2c; color: white; }
        .status-badge.active { background-color: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .status-badge.inactive { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>

<header class="app-header">
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
    <a href="ia_conocimiento.php" class="active">📚 Base Conocimiento</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php">👤 Usuarios</a>
    <a href="historial.php">🕒 Historial</a>
    <a href="ver_accesos.php">🕵️ Accesos IP</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>

<div class="container-fluid px-4" style="margin-top: 80px;">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-12">
            <h4 style="color:#801039; font-weight:bold;">📚 Base de Conocimiento IA (RAG)</h4>
            <p class="text-muted">Aquí alimentas a Luchito con información real. Estos datos se le enviarán como contexto antes de responder.</p>
        </div>
    </div>

    <div class="row">
        <!-- Columna Formulario -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header text-white" style="background-color: #020617;" id="form-title">
                    ➕ Agregar Documento
                </div>
                <div class="card-body">
                    <form method="POST" id="conocimiento-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="input-id" value="">
                        
                        <div class="form-group">
                            <label class="font-weight-bold">Categoría</label>
                            <input type="text" name="categoria" id="input-categoria" class="form-control" list="cat-list" required placeholder="Ej. General, Obras...">
                            <datalist id="cat-list">
                                <?php foreach($categorias_comunes as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Título del Documento</label>
                            <input type="text" name="titulo" id="input-titulo" class="form-control" required placeholder="Ej. ¿Qué es Fuerza Tacna?">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Contenido (Lo que la IA leerá)</label>
                            <textarea name="contenido" id="input-contenido" class="form-control" rows="5" required placeholder="Fuerza Tacna es un movimiento regional fundado en..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Palabras Clave (Opcional para mejorar búsqueda)</label>
                            <input type="text" name="palabras_clave" id="input-palabras" class="form-control" placeholder="Ej. fundacion, historia, movimiento">
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold" title="1=Más importante, 10=Menos importante">Prioridad (1-10)</label>
                                <input type="number" min="1" max="10" name="prioridad" id="input-prioridad" class="form-control" value="5">
                            </div>
                            <div class="form-group col-md-6 d-flex align-items-end pb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input-estado" name="estado" checked>
                                    <label class="custom-control-label" for="input-estado">Habilitado</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Fuente</label>
                                <input type="text" name="fuente" id="input-fuente" class="form-control" value="Manual" placeholder="Ej. Manual, Excel, Web">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">URL Referencia (Opcional)</label>
                                <input type="text" name="url" id="input-url" class="form-control" placeholder="https://...">
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-ft btn-block">💾 Guardar Documento</button>
                        <button type="button" class="btn btn-light btn-block" onclick="resetForm()" id="btn-cancelar" style="display:none;">Cancelar Edición</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna Listado -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>Documentos Cargados (<?= count($conocimientos) ?>)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table class="table table-hover mb-0" style="font-size: 13px;">
                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="width: 5%;">Pri.</th>
                                    <th style="width: 25%;">Categoría / Título</th>
                                    <th style="width: 40%;">Contenido Extracto</th>
                                    <th style="width: 10%;">Estado</th>
                                    <th style="width: 20%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($conocimientos)): ?>
                                    <tr><td colspan="5" class="text-center py-4">Aún no has cargado conocimiento.</td></tr>
                                <?php else: ?>
                                    <?php foreach($conocimientos as $row): ?>
                                    <tr>
                                        <td class="align-middle text-center"><strong><?= $row['prioridad'] ?></strong></td>
                                        <td>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($row['categoria']) ?></span><br>
                                            <strong><?= htmlspecialchars($row['titulo']) ?></strong><br>
                                            <small class="text-muted">Fte: <?= htmlspecialchars($row['fuente']) ?></small>
                                        </td>
                                        <td class="align-middle text-muted">
                                            <?= htmlspecialchars(mb_strimwidth($row['contenido'], 0, 90, '...')) ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if($row['estado'] == 1): ?>
                                                <span class="status-badge active">Activo</span>
                                            <?php else: ?>
                                                <span class="status-badge inactive">Oculto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <span id="data-<?= $row['id'] ?>" class="d-none"
                                                  data-cat="<?= htmlspecialchars($row['categoria']) ?>"
                                                  data-tit="<?= htmlspecialchars($row['titulo']) ?>"
                                                  data-con="<?= htmlspecialchars($row['contenido']) ?>"
                                                  data-pal="<?= htmlspecialchars($row['palabras_clave']) ?>"
                                                  data-pri="<?= $row['prioridad'] ?>"
                                                  data-est="<?= $row['estado'] ?>"
                                                  data-fue="<?= htmlspecialchars($row['fuente']) ?>"
                                                  data-url="<?= htmlspecialchars($row['url']) ?>"></span>
                                            
                                            <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editRow(<?= $row['id'] ?>)">Editar</button>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cambiar estado de este documento?');">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?= $row['estado'] == 1 ? 0 : 1 ?>">
                                                <button class="btn btn-sm btn-outline-secondary py-0 px-2">On/Off</button>
                                            </form>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar definitivamente este documento de conocimiento?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2">X</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('form-title').innerText = "➕ Agregar Documento";
        document.getElementById('input-id').value = "";
        document.getElementById('input-categoria').value = "";
        document.getElementById('input-titulo').value = "";
        document.getElementById('input-contenido').value = "";
        document.getElementById('input-palabras').value = "";
        document.getElementById('input-prioridad').value = "5";
        document.getElementById('input-estado').checked = true;
        document.getElementById('input-fuente').value = "Manual";
        document.getElementById('input-url').value = "";
        document.getElementById('btn-cancelar').style.display = "none";
    }

    function editRow(id) {
        const dataSpan = document.getElementById('data-' + id);
        if(!dataSpan) return;
        
        document.getElementById('form-title').innerText = "✏️ Editar Documento #" + id;
        document.getElementById('input-id').value = id;
        document.getElementById('input-categoria').value = dataSpan.getAttribute('data-cat');
        document.getElementById('input-titulo').value = dataSpan.getAttribute('data-tit');
        document.getElementById('input-contenido').value = dataSpan.getAttribute('data-con');
        document.getElementById('input-palabras').value = dataSpan.getAttribute('data-pal');
        document.getElementById('input-prioridad').value = dataSpan.getAttribute('data-pri');
        document.getElementById('input-estado').checked = (dataSpan.getAttribute('data-est') == "1");
        document.getElementById('input-fuente').value = dataSpan.getAttribute('data-fue');
        document.getElementById('input-url').value = dataSpan.getAttribute('data-url');
        
        document.getElementById('btn-cancelar').style.display = "block";
        window.scrollTo(0, 0);
    }
</script>
</body>
</html>