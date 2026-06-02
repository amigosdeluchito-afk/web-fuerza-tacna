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
        $categoria = trim($_POST['categoria_select'] ?? '');
        if ($categoria === 'NEW') {
            $categoria = trim($_POST['categoria_nueva'] ?? '');
        }
        
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
    elseif ($action === 'import_json') {
        $mode = $_POST['import_mode'] ?? 'append';
        $json_data = $_POST['json_data'] ?? '[]';
        $rules = json_decode($json_data, true);
        
        if (is_array($rules)) {
            if ($mode === 'replace') {
                $db->exec("DELETE FROM panel_ia_respuestas");
            }
            
            $stmt = $db->prepare("INSERT INTO panel_ia_respuestas (categoria, palabras_clave, respuestas, acciones, estado, orden) VALUES (?, ?, ?, ?, ?, ?)");
            $imported = 0;
            foreach ($rules as $r) {
                $stmt->execute([
                    $r['categoria'],
                    $r['palabras_clave'],
                    json_encode($r['respuestas'], JSON_UNESCAPED_UNICODE),
                    json_encode($r['acciones'], JSON_UNESCAPED_UNICODE),
                    $r['estado'],
                    $r['orden']
                ]);
                $imported++;
            }
            regenerar_json_ia($db);
            $mensaje = "Se importaron $imported respuestas masivamente con éxito.";
        } else {
            $mensaje = "Error al procesar el JSON en el servidor.";
        }
    }
}

// 4. Obtener listado para la tabla
$stmt = $db->query("SELECT * FROM panel_ia_respuestas ORDER BY orden ASC, id DESC");
$respuestas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Calcular Estadísticas y Extraer Categorías Dinámicas
$total_reglas = count($respuestas_list);
$total_respuestas = 0;
$total_botones = 0;
$activas = 0;
$inactivas = 0;
$cat_counts = [];

foreach ($respuestas_list as $r) {
    $resp_arr = json_decode($r['respuestas'], true) ?: [];
    $acc_arr = json_decode($r['acciones'], true) ?: [];
    
    $total_respuestas += count($resp_arr);
    $total_botones += count($acc_arr);
    if ($r['estado'] == 1) { $activas++; } else { $inactivas++; }
    
    $c = trim($r['categoria']);
    if ($c !== '') {
        if (!isset($cat_counts[$c])) $cat_counts[$c] = 0;
        $cat_counts[$c]++;
    }
}

// Unir categorías existentes con las predefinidas
$default_cats = ["Saludos", "Despedidas", "Quién eres", "Eres IA", "Obras", "Candidatos", "Propuestas", "Contacto", "Súmate", "Ayuda", "Humor", "Chistes", "Curiosidades", "Preguntas Personales", "Insultos Suaves", "Política Nacional", "Fuera de Tema", "Navegación", "Espera", "Otros"];
$all_cats = array_unique(array_merge($default_cats, array_keys($cat_counts)));
sort($all_cats);
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
        
        /* Tabs de Navegación */
        .tab-btn { border-radius: 0; padding: 12px; font-size: 14px; font-weight: bold; border: none; transition: 0.3s; }
        .tab-btn.active { background-color: #801039 !important; color: #ffc300 !important; opacity: 1; }
        .tab-btn:not(.active) { background-color: #4a051d !important; color: #ffc300 !important; opacity: 0.6; }
        .tab-btn:hover:not(.active) { opacity: 0.8; }

        /* Estilos Header General */
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 2000; font-family: system-ui, sans-serif; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
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
    <a href="ia_respuestas.php" class="active">🧠 Cerebro IA</a>
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

<div class="container" style="margin-top: 80px;">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- PANEL DE ESTADÍSTICAS -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid rgba(128, 16, 57, 0.2);">
                <div class="card-body d-flex flex-wrap justify-content-around text-center py-3">
                    <div class="px-3">
                        <h4 class="mb-0 font-weight-bold" style="color: #801039;">🧠 <?= $total_reglas ?></h4><small class="text-muted font-weight-bold text-uppercase">Reglas</small>
                    </div>
                    <div class="px-3" style="border-left: 1px solid #eee;">
                        <h4 class="mb-0 font-weight-bold" style="color: #801039;">💬 <?= $total_respuestas ?></h4><small class="text-muted font-weight-bold text-uppercase">Respuestas</small>
                    </div>
                    <div class="px-3" style="border-left: 1px solid #eee;">
                        <h4 class="mb-0 font-weight-bold" style="color: #801039;">🔘 <?= $total_botones ?></h4><small class="text-muted font-weight-bold text-uppercase">Botones</small>
                    </div>
                    <div class="px-3" style="border-left: 1px solid #eee;">
                        <h4 class="mb-0 font-weight-bold text-success">✅ <?= $activas ?></h4><small class="text-muted font-weight-bold text-uppercase">Activas</small>
                    </div>
                    <div class="px-3" style="border-left: 1px solid #eee;">
                        <h4 class="mb-0 font-weight-bold text-danger">⏸ <?= $inactivas ?></h4><small class="text-muted font-weight-bold text-uppercase">Inactivas</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna Formulario -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex p-0" id="form-tabs-header">
                    <button type="button" class="btn flex-fill tab-btn active" id="tab-manual" onclick="switchTab('manual')">✍️ Manual</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-import" onclick="switchTab('import')">📦 Masivo</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-test" onclick="switchTab('test')">🧪 Probar</button>
                </div>
                <div class="card-body" id="body-manual">
                    <h6 class="mb-3 font-weight-bold" id="form-title" style="color:#801039;">Nueva Respuesta</h6>
                    <form method="POST" id="ia-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="input-id" value="">
                        
                        <div class="form-group">
                            <label>Categoría</label>
                            <select name="categoria_select" id="input-categoria-select" class="form-control" onchange="checkNewCategory()" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach($all_cats as $cat): ?>
                                    <?php $count = $cat_counts[$cat] ?? 0; ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?> (<?= $count ?>)</option>
                                <?php endforeach; ?>
                                <option value="NEW" style="font-weight: bold; color: #801039;">+ Nueva Categoría</option>
                            </select>
                            <input type="text" name="categoria_nueva" id="input-categoria-nueva" class="form-control mt-2" placeholder="Escribe el nombre de la categoría..." style="display:none;">
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

                <!-- TAB DE IMPORTACIÓN MASIVA -->
                <div class="card-body" id="body-import" style="display:none;">
                    <h6 class="mb-3 font-weight-bold" style="color:#801039;">Importación Masiva (JSON)</h6>
                    <form method="POST" id="import-form">
                        <input type="hidden" name="action" value="import_json">
                        <input type="hidden" name="json_data" id="input-final-json">
                        
                        <div class="form-group">
                            <textarea id="input-json-raw" class="form-control" rows="12" style="font-family: monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4;" placeholder='[\n  {\n    "categoria": "Saludos",\n    "palabras_clave": ["hola", "buenos dias"],\n    "respuestas": ["¡Hola vecino!"],\n    "acciones": [],\n    "estado": 1,\n    "orden": 10\n  }\n]'></textarea>
                            <small class="text-muted d-block mt-1">Pega aquí el arreglo JSON generado por IA.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-radio">
                              <input type="radio" id="modeAppend" name="import_mode" class="custom-control-input" value="append" checked>
                              <label class="custom-control-label" for="modeAppend">Añadir sin borrar (Sumar a las actuales)</label>
                            </div>
                            <div class="custom-control custom-radio mt-1">
                              <input type="radio" id="modeReplace" name="import_mode" class="custom-control-input" value="replace">
                              <label class="custom-control-label" for="modeReplace" style="color: #dc3545; font-weight: bold;">Reemplazar todas (Borrará las actuales)</label>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-outline-info btn-block" onclick="previewImport()">🔍 Previsualizar y Validar</button>
                        
                        <div id="preview-container" class="mt-3" style="display:none;">
                            <div id="preview-errors" class="alert alert-danger" style="display:none; font-size:12px; padding: 10px;"></div>
                            <div id="preview-success" class="alert alert-success" style="display:none; font-size:13px; padding: 10px;"></div>
                            <button type="submit" id="btn-confirm-import" class="btn btn-ft btn-block" style="display:none;">✅ Confirmar Importación</button>
                        </div>
                    </form>
                </div>

                <!-- TAB DE PRUEBA LUCHITO -->
                <div class="card-body" id="body-test" style="display:none; flex-direction:column; height: 620px;">
                    <h6 class="mb-3 font-weight-bold" style="color:#801039;">🧪 Simulador de Capa 1</h6>
                    <p style="font-size: 12px; color: #6c757d; margin-top: -10px;">Comprueba cómo responderá Luchito según las reglas actualmente activas.</p>
                    
                    <div id="test-chat-box" style="flex:1; border:1px solid #dee2e6; border-radius:8px; padding:15px; overflow-y:auto; background:#f4f6f9; margin-bottom:15px; display:flex; flex-direction:column; gap:12px;">
                        <div style="align-self:flex-start; background:#ffffff; padding:10px 14px; border-radius:15px 15px 15px 0; border:1px solid #e0e0e0; max-width:85%; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                            <strong style="color: #801039;">🤖 Luchito:</strong><br><span style="line-height: 1.4;">¡Hola! Prueba tus reglas escribiendo aquí abajo.</span>
                        </div>
                    </div>
                    <div class="d-flex">
                        <input type="text" id="test-chat-input" class="form-control" placeholder="Mensaje de prueba..." autocomplete="off" onkeypress="if(event.key==='Enter') testSendMessage()">
                        <button type="button" class="btn btn-ft ml-2" onclick="testSendMessage()">Enviar</button>
                    </div>
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
    // Reglas procesadas directamente desde PHP para el entorno de pruebas
    const testRules = <?php 
        $test_data = [];
        foreach ($respuestas_list as $r) {
            if ($r['estado'] != 1) continue;
            $palabras = array_filter(array_map('trim', explode(',', $r['palabras_clave'])));
            if (empty($palabras)) continue;
            $test_data[] = ['pattern_str' => '(' . implode('|', $palabras) . ')', 'responses' => json_decode($r['respuestas'], true) ?: [], 'actions' => json_decode($r['acciones'], true) ?: []];
        }
        echo json_encode($test_data);
    ?>;

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

    function checkNewCategory() {
        const sel = document.getElementById('input-categoria-select');
        const input = document.getElementById('input-categoria-nueva');
        if (sel.value === 'NEW') {
            input.style.display = 'block';
            input.required = true;
        } else {
            input.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function resetForm() {
        document.getElementById('form-title').innerText = "Nueva Respuesta";
        document.getElementById('input-id').value = "";
        document.getElementById('input-categoria-select').value = "";
        checkNewCategory();
        document.getElementById('input-palabras').value = "";
        document.getElementById('input-orden').value = "10";
        document.getElementById('input-estado').checked = true;
        
        document.getElementById('respuestas-container').innerHTML = `
            <div class="response-row"><textarea name="respuestas[]" class="form-control" rows="2" required></textarea></div>
        `;
        document.getElementById('acciones-container').innerHTML = "";
        document.getElementById('btn-cancelar').style.display = "none";
    }

    function switchTab(tab) {
        document.getElementById('body-manual').style.display = tab === 'manual' ? 'block' : 'none';
        document.getElementById('body-import').style.display = tab === 'import' ? 'block' : 'none';
        document.getElementById('body-test').style.display = tab === 'test' ? 'flex' : 'none';
        
        const btnManual = document.getElementById('tab-manual');
        const btnImport = document.getElementById('tab-import');
        const btnTest = document.getElementById('tab-test');
        
        if (tab === 'manual') {
            btnManual.classList.add('active'); btnImport.classList.remove('active'); btnTest.classList.remove('active');
            document.getElementById('preview-container').style.display = 'none';
        } else if (tab === 'import') {
            btnImport.classList.add('active'); btnManual.classList.remove('active'); btnTest.classList.remove('active');
        } else {
            btnTest.classList.add('active'); btnManual.classList.remove('active'); btnImport.classList.remove('active');
            document.getElementById('test-chat-box').scrollTop = document.getElementById('test-chat-box').scrollHeight;
        }
    }

    function previewImport() {
        const raw = document.getElementById('input-json-raw').value.trim();
        const errDiv = document.getElementById('preview-errors');
        const sucDiv = document.getElementById('preview-success');
        const btnConfirm = document.getElementById('btn-confirm-import');
        
        errDiv.style.display = 'none'; sucDiv.style.display = 'none'; btnConfirm.style.display = 'none';
        
        if(!raw) { errDiv.innerHTML = "El JSON está vacío."; errDiv.style.display = 'block'; return; }
        
        let parsed;
        try { parsed = JSON.parse(raw); } 
        catch(e) { errDiv.innerHTML = "Error de sintaxis JSON: " + e.message; errDiv.style.display = 'block'; return; }
        
        if(!Array.isArray(parsed)) { errDiv.innerHTML = "El JSON debe ser un arreglo [ ... ]"; errDiv.style.display = 'block'; return; }
        
        const allowedActions = ['ir_a_obras', 'ir_a_candidatos', 'ir_a_propuestas', 'ir_a_sumate', 'ir_a_contacto'];
        let errors = [];
        let validRules = [];
        
        parsed.forEach((rule, idx) => {
            let ruleName = rule.categoria || `Regla #${idx + 1}`;
            if(!rule.categoria) errors.push(`[Regla #${idx + 1}]: Falta 'categoria'.`);
            if(!rule.palabras_clave || !Array.isArray(rule.palabras_clave)) errors.push(`[${ruleName}]: 'palabras_clave' debe ser un arreglo de textos.`);
            if(!rule.respuestas || !Array.isArray(rule.respuestas) || rule.respuestas.length === 0) errors.push(`[${ruleName}]: 'respuestas' debe ser un arreglo con al menos un texto.`);
            
            if(rule.acciones && Array.isArray(rule.acciones)) {
                rule.acciones.forEach((acc, aIdx) => {
                    if(!acc.label || !acc.type) errors.push(`[${ruleName}] Acción #${aIdx + 1}: Faltan 'label' o 'type'.`);
                    else if(!allowedActions.includes(acc.type)) errors.push(`[${ruleName}] Acción #${aIdx + 1}: Type '${acc.type}' no permitido.`);
                });
            }
            
            if(errors.length === 0) {
                validRules.push({ categoria: rule.categoria, palabras_clave: rule.palabras_clave.join(', '), respuestas: rule.respuestas, acciones: rule.acciones || [], estado: (rule.estado !== undefined) ? (rule.estado ? 1 : 0) : 1, orden: (rule.orden !== undefined) ? parseInt(rule.orden) : 10 });
            }
        });
        
        if(errors.length > 0) {
            errDiv.innerHTML = "<strong>Errores encontrados:</strong><br>" + errors.join("<br>");
            errDiv.style.display = 'block';
        } else {
            document.getElementById('input-final-json').value = JSON.stringify(validRules);
            sucDiv.innerHTML = `<strong>JSON Válido.</strong><br>Se importarán ${validRules.length} reglas listas para usar.`;
            sucDiv.style.display = 'block'; btnConfirm.style.display = 'block';
        }
    }

    function editRow(id) {
        const dataSpan = document.getElementById('data-' + id);
        if(!dataSpan) return;
        
        switchTab('manual');
        
        document.getElementById('form-title').innerText = "Editar Respuesta #" + id;
        document.getElementById('input-id').value = id;
        
        const cat = dataSpan.getAttribute('data-cat');
        const sel = document.getElementById('input-categoria-select');
        let found = false;
        for(let i=0; i<sel.options.length; i++) {
            if(sel.options[i].value === cat) {
                sel.selectedIndex = i;
                found = true;
                break;
            }
        }
        if(!found) {
            sel.value = 'NEW';
            document.getElementById('input-categoria-nueva').value = cat;
        }
        checkNewCategory();
        
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

    // Funciones del Entorno de Pruebas (Simulador IA)
    function normalizeText(str) {
        return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    }

    function testSendMessage() {
        const input = document.getElementById('test-chat-input');
        const text = input.value.trim();
        if (!text) return;
        appendTestMessage('user', text);
        input.value = '';
        
        const normalizedText = normalizeText(text);
        let matchFound = false; let responseText = ""; let responseActions = [];
        
        for (const intent of testRules) {
            const regex = new RegExp(intent.pattern_str, "i");
            if (regex.test(normalizedText)) {
                matchFound = true;
                const randIndex = Math.floor(Math.random() * intent.responses.length);
                responseText = intent.responses[randIndex];
                if (intent.actions) responseActions = intent.actions;
                break;
            }
        }
        
        setTimeout(() => {
            if (matchFound) appendTestMessage('ai', responseText, responseActions);
            else appendTestMessage('ai', "Déjame revisar mis apuntes un ratito, vecino... 🤔<br><small style='color:#dc3545;'>*Ninguna regla local hizo match. En producción, esto derivará a OpenAI.*</small>");
        }, 400);
    }

    function appendTestMessage(type, text, actions = []) {
        const box = document.getElementById('test-chat-box');
        const div = document.createElement('div');
        const isUser = type === 'user';
        div.style.alignSelf = isUser ? 'flex-end' : 'flex-start'; div.style.background = isUser ? '#801039' : '#ffffff'; div.style.color = isUser ? '#ffffff' : '#333333'; div.style.padding = '10px 14px'; div.style.borderRadius = isUser ? '15px 15px 0 15px' : '15px 15px 15px 0'; div.style.border = isUser ? 'none' : '1px solid #e0e0e0'; div.style.maxWidth = '85%'; div.style.boxShadow = '0 2px 5px rgba(0,0,0,0.05)';
        let html = `<strong style="color: ${isUser ? '#ffc300' : '#801039'};">${isUser ? '👤 Tú' : '🤖 Luchito'}</strong><br><span style="line-height: 1.4;">${text}</span>`;
        if (actions && actions.length > 0) { html += `<div style="margin-top:10px; display:flex; gap:6px; flex-wrap:wrap;">`; actions.forEach(act => { html += `<span style="background:rgba(128,16,57,0.1); color:#801039; border:1px solid #801039; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold;">${act.label} <small>(${act.type})</small></span>`; }); html += `</div>`; }
        div.innerHTML = html; box.appendChild(div); box.scrollTop = box.scrollHeight;
    }
</script>
</body>
</html>