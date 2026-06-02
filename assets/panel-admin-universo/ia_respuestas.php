<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// 1. Crear tabla si no existe
$db->exec("CREATE TABLE IF NOT EXISTS panel_ia_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(255) NOT NULL,
    palabras_clave TEXT NOT NULL,
    respuestas TEXT NOT NULL,
    acciones TEXT,
    estado TINYINT DEFAULT 1,
    orden INT DEFAULT 0
)");

// 2. Función para generar el JSON Público
function regenerar_json_ia($db) {
    $stmt = $db->query("SELECT * FROM panel_ia_respuestas WHERE estado = 1 ORDER BY orden ASC, id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $json_data = [];
    foreach ($rows as $r) {
        $palabras = array_filter(array_map('trim', explode(',', $r['palabras_clave'])));
        if (empty($palabras)) continue;
        
        // Envolvemos las palabras en un grupo Regex
        $pattern_str = '(' . implode('|', $palabras) . ')';
        
        $item = [
            'categoria'   => $r['categoria'],
            'pattern_str' => $pattern_str,
            'responses'   => json_decode($r['respuestas'], true) ?: []
        ];
        
        $acciones = json_decode($r['acciones'], true);
        if (!empty($acciones)) {
            $item['actions'] = $acciones;
        }
        
        $json_data[] = $item;
    }
    
    // Asegurar que la carpeta de caché exista
    $ia_dir = __DIR__ . '/../ia_luchito/cache';
    if (!is_dir($ia_dir)) {
        mkdir($ia_dir, 0777, true);
    }
    
    file_put_contents($ia_dir . '/quick_responses.json', json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 3. Procesar Formularios (POST)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $categoria = trim($_POST['categoria'] ?? '');
        $palabras = trim($_POST['palabras_clave'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $estado = isset($_POST['estado']) ? 1 : 0;

        // Procesar respuestas
        $respuestas_raw = $_POST['respuestas'] ?? [];
        $respuestas = array_values(array_filter(array_map('trim', $respuestas_raw)));

        // Procesar acciones
        $act_labels = $_POST['action_labels'] ?? [];
        $act_types = $_POST['action_types'] ?? [];
        $acciones = [];
        foreach ($act_labels as $i => $lbl) {
            $lbl = trim($lbl);
            $type = trim($act_types[$i] ?? '');
            if ($lbl !== '' && $type !== '') {
                $acciones[] = ['label' => $lbl, 'type' => $type];
            }
        }

        if ($categoria && $palabras && !empty($respuestas)) {
            if ($id) {
                $stmt = $db->prepare("UPDATE panel_ia_respuestas SET categoria=?, palabras_clave=?, respuestas=?, acciones=?, estado=?, orden=? WHERE id=?");
                $stmt->execute([$categoria, $palabras, json_encode($respuestas), json_encode($acciones), $estado, $orden, $id]);
                $mensaje = "Respuesta actualizada correctamente.";
            } else {
                $stmt = $db->prepare("INSERT INTO panel_ia_respuestas (categoria, palabras_clave, respuestas, acciones, estado, orden) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoria, $palabras, json_encode($respuestas), json_encode($acciones), $estado, $orden]);
                $mensaje = "Nueva respuesta creada correctamente.";
            }
            regenerar_json_ia($db);
        } else {
            $mensaje = "Error: Categoría, palabras clave y al menos una respuesta son obligatorios.";
        }
    } 
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $db->prepare("DELETE FROM panel_ia_respuestas WHERE id=?");
            $stmt->execute([$id]);
            regenerar_json_ia($db);
            $mensaje = "Respuesta eliminada.";
        }
    }
    elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? '';
        $nuevo_estado = $_POST['nuevo_estado'] ?? 1;
        if ($id) {
            $stmt = $db->prepare("UPDATE panel_ia_respuestas SET estado=? WHERE id=?");
            $stmt->execute([$nuevo_estado, $id]);
            regenerar_json_ia($db);
            $mensaje = "Estado actualizado.";
        }
    }
}

// 4. Obtener listado para la tabla
$stmt = $db->query("SELECT * FROM panel_ia_respuestas ORDER BY orden ASC, id DESC");
$respuestas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cerebro Luchito - Fuerza Tacna</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-ft { background-color: #801039; }
        .navbar-ft .navbar-brand, .navbar-ft .nav-link { color: #ffc300 !important; font-weight: bold; }
        .card-header-ft { background-color: #801039; color: #ffc300; font-weight: bold; }
        .btn-ft { background-color: #ffc300; color: #801039; font-weight: bold; border: none; }
        .btn-ft:hover { background-color: #e6b000; }
        .status-badge.active { background-color: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .status-badge.inactive { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .response-row, .action-row { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px; position: relative; border: 1px solid #dee2e6; }
        .remove-row-btn { position: absolute; top: 10px; right: 10px; cursor: pointer; color: #dc3545; font-weight: bold; border: none; background: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-ft mb-4">
    <div class="container">
        <a class="navbar-brand" href="#">🧠 Cerebro Luchito (Capa 1)</a>
        <div class="ml-auto">
            <a href="index.php" class="btn btn-sm btn-light">Volver al Panel</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Columna Formulario -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-ft" id="form-title">Nueva Respuesta</div>
                <div class="card-body">
                    <form method="POST" id="ia-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="input-id" value="">
                        
                        <div class="form-group">
                            <label>Categoría (Interna)</label>
                            <input type="text" name="categoria" id="input-categoria" class="form-control" placeholder="Ej: Saludos" required>
                        </div>

                        <div class="form-group">
                            <label>Palabras Clave (Separadas por coma)</label>
                            <textarea name="palabras_clave" id="input-palabras" class="form-control" rows="2" placeholder="Ej: hola, buenos dias, que tal" required></textarea>
                            <small class="text-muted">Si el usuario escribe alguna de estas, Luchito responderá.</small>
                        </div>

                        <div class="form-group">
                            <label>¿Qué dirá Luchito? (Variantes)</label>
                            <div id="respuestas-container">
                                <div class="response-row">
                                    <textarea name="respuestas[]" class="form-control" rows="2" required></textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addResponseField()">+ Añadir variante</button>
                        </div>

                        <div class="form-group">
                            <label>Botones de Acción (Opcional)</label>
                            <div id="acciones-container"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addActionField()">+ Añadir botón</button>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Orden de Prioridad</label>
                                <input type="number" name="orden" id="input-orden" class="form-control" value="10">
                            </div>
                            <div class="form-group col-md-6 d-flex align-items-end">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="input-estado" name="estado" checked>
                                    <label class="custom-control-label" for="input-estado">Activo</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-ft btn-block">Guardar Regla</button>
                        <button type="button" class="btn btn-light btn-block" onclick="resetForm()" id="btn-cancelar" style="display:none;">Cancelar Edición</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna Listado -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>Reglas Actuales</span>
                    <span class="badge badge-light">JSON Actualizado Automáticamente</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Orden</th>
                                    <th>Categoría / Palabras</th>
                                    <th>Respuestas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($respuestas_list)): ?>
                                    <tr><td colspan="5" class="text-center py-4">No hay respuestas configuradas aún.</td></tr>
                                <?php else: ?>
                                    <?php foreach($respuestas_list as $row): 
                                        $resp_arr = json_decode($row['respuestas'], true) ?: [];
                                        $acc_arr = json_decode($row['acciones'], true) ?: [];
                                    ?>
                                    <tr>
                                        <td><?= $row['orden'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['categoria']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['palabras_clave']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= count($resp_arr) ?> variantes configuradas</small><br>
                                            <?php if(count($acc_arr) > 0): ?>
                                                <span class="badge badge-info"><?= count($acc_arr) ?> botones</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($row['estado'] == 1): ?>
                                                <span class="status-badge active">Activo</span>
                                            <?php else: ?>
                                                <span class="status-badge inactive">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Data para JS -->
                                            <span id="data-<?= $row['id'] ?>" class="d-none"
                                                  data-cat="<?= htmlspecialchars($row['categoria']) ?>"
                                                  data-palabras="<?= htmlspecialchars($row['palabras_clave']) ?>"
                                                  data-orden="<?= $row['orden'] ?>"
                                                  data-estado="<?= $row['estado'] ?>"
                                                  data-respuestas="<?= htmlspecialchars($row['respuestas']) ?>"
                                                  data-acciones="<?= htmlspecialchars($row['acciones']) ?>"></span>
                                            
                                            <button class="btn btn-sm btn-outline-primary" onclick="editRow(<?= $row['id'] ?>)">Editar</button>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cambiar estado?');">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?= $row['estado'] == 1 ? 0 : 1 ?>">
                                                <button class="btn btn-sm btn-outline-secondary">On/Off</button>
                                            </form>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar definitivamente?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">X</button>
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
    function addResponseField(val = '') {
        const cont = document.getElementById('respuestas-container');
        const div = document.createElement('div');
        div.className = 'response-row';
        div.innerHTML = `
            <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">✖</button>
            <textarea name="respuestas[]" class="form-control" rows="2" required>${val}</textarea>
        `;
        cont.appendChild(div);
    }

    function addActionField(label = '', type = 'ir_a_obras') {
        const cont = document.getElementById('acciones-container');
        const div = document.createElement('div');
        div.className = 'action-row';
        
        const options = [
            {val: 'ir_a_obras', text: 'Ir al Mapa de Obras'},
            {val: 'ir_a_candidatos', text: 'Ir a Candidatos'},
            {val: 'ir_a_propuestas', text: 'Ir a Propuestas'},
            {val: 'ir_a_sumate', text: 'Ir a Súmate'},
            {val: 'ir_a_contacto', text: 'Ir a Contacto'}
        ];
        
        let optsHtml = options.map(o => `<option value="${o.val}" ${o.val === type ? 'selected' : ''}>${o.text}</option>`).join('');

        div.innerHTML = `
            <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">✖</button>
            <div class="row">
                <div class="col-7">
                    <input type="text" name="action_labels[]" class="form-control form-control-sm" placeholder="Texto (Ej: 🏗️ Ver Obras)" value="${label}" required>
                </div>
                <div class="col-5">
                    <select name="action_types[]" class="form-control form-control-sm">
                        ${optsHtml}
                    </select>
                </div>
            </div>
        `;
        cont.appendChild(div);
    }

    function resetForm() {
        document.getElementById('form-title').innerText = "Nueva Respuesta";
        document.getElementById('input-id').value = "";
        document.getElementById('input-categoria').value = "";
        document.getElementById('input-palabras').value = "";
        document.getElementById('input-orden').value = "10";
        document.getElementById('input-estado').checked = true;
        
        document.getElementById('respuestas-container').innerHTML = `
            <div class="response-row"><textarea name="respuestas[]" class="form-control" rows="2" required></textarea></div>
        `;
        document.getElementById('acciones-container').innerHTML = "";
        document.getElementById('btn-cancelar').style.display = "none";
    }

    function editRow(id) {
        const dataSpan = document.getElementById('data-' + id);
        if(!dataSpan) return;
        
        document.getElementById('form-title').innerText = "Editar Respuesta #" + id;
        document.getElementById('input-id').value = id;
        document.getElementById('input-categoria').value = dataSpan.getAttribute('data-cat');
        document.getElementById('input-palabras').value = dataSpan.getAttribute('data-palabras');
        document.getElementById('input-orden').value = dataSpan.getAttribute('data-orden');
        document.getElementById('input-estado').checked = (dataSpan.getAttribute('data-estado') == "1");
        
        // Cargar respuestas
        document.getElementById('respuestas-container').innerHTML = "";
        const resps = JSON.parse(dataSpan.getAttribute('data-respuestas') || '[]');
        resps.forEach(r => addResponseField(r));
        if(resps.length === 0) addResponseField(); // Asegurar al menos uno
        
        // Cargar acciones
        document.getElementById('acciones-container').innerHTML = "";
        const acts = JSON.parse(dataSpan.getAttribute('data-acciones') || '[]');
        acts.forEach(a => addActionField(a.label, a.type));

        document.getElementById('btn-cancelar').style.display = "block";
        window.scrollTo(0, 0);
    }
</script>
</body>
</html>