<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// --- INICIO FASE 8.3: MOTOR AJAX DE SINCRONIZACIÓN DE OBRAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['preview_sync_obras', 'confirm_sync_obras'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            throw new Exception("Falta la carpeta 'vendor' de Google API.");
        }
        require_once __DIR__ . '/vendor/autoload.php';
        $rutaCredenciales = __DIR__ . '/data/credenciales.json';
        $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig($rutaCredenciales);
        $service = new \Google_Service_Sheets($client);

        // 1. Obtener pestañas activas desde SEGMENTOS
        $responseSeg = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:D');
        $rowsSeg = $responseSeg->getValues() ?? [];
        $segmentosActivos = [];
        foreach ($rowsSeg as $i => $row) {
            if ($i === 0) continue;
            if (!empty($row[2]) && strtoupper($row[3] ?? '') === 'SI') {
                $segmentosActivos[$row[2]] = $row[1] ?? $row[2]; // tab => nombre_visible
            }
        }

        // 2. Extraer datos reales
        $obrasAProcesar = [];
        foreach ($segmentosActivos as $tab => $nombreSegmento) {
            try {
                $responseObras = $service->spreadsheets_values->get($spreadsheetId, $tab . '!A2:I');
                $rowsObras = $responseObras->getValues() ?? [];
                foreach ($rowsObras as $r) {
                    $nombre = trim($r[0] ?? '');
                    if (empty($nombre)) continue; // Regla 3: Ignorar si columna A está vacía

                    $estado = trim($r[1] ?? '');
                    $monto = trim($r[2] ?? '');
                    $provincia = trim($r[5] ?? '');
                    $distrito = trim($r[6] ?? '');
                    $carpeta = trim($r[7] ?? '');
                    $descripcion = trim($r[8] ?? '');

                    // Regla 4: Redacción usando datos reales, sin inventar nada.
                    $titulo = "Obra: " . $nombre;
                    $palabras = implode(', ', array_filter([$nombre, $distrito, $nombreSegmento]));
                    $contenido = "La obra '$nombre' pertenece al sector $nombreSegmento. ";
                    if ($distrito || $provincia) $contenido .= "Ubicada en $distrito, $provincia. ";
                    if ($estado) $contenido .= "Estado actual: '$estado'. ";
                    if ($monto) $contenido .= "Monto referencial: $monto. ";
                    if ($descripcion) $contenido .= "Descripción: $descripcion.";
                    if ($carpeta && $carpeta !== '-') $contenido .= " (Tiene galería de fotos).";

                    $obrasAProcesar[] = [
                        'categoria' => 'Obras', 'titulo' => $titulo, 'contenido' => trim($contenido),
                        'palabras_clave' => $palabras, 'prioridad' => 5, 'estado' => 1,
                        'fuente' => 'Google Sheets - Obras' // Regla 1
                    ];
                }
            } catch (\Exception $e) { continue; } // Ignorar si la pestaña no existe aún
        }

        if ($_POST['action'] === 'preview_sync_obras') { // Regla 5: Modo previsualización
            $stmtDel = $db->query("SELECT COUNT(*) FROM panel_ia_conocimiento WHERE fuente = 'Google Sheets - Obras'");
            echo json_encode([
                'ok' => true, 'count_new' => count($obrasAProcesar), 
                'count_delete' => (int)$stmtDel->fetchColumn(), 'samples' => array_slice($obrasAProcesar, 0, 3)
            ]);
        } elseif ($_POST['action'] === 'confirm_sync_obras') { // Regla 5: Modo Confirmación
            $db->beginTransaction();
            // Regla 2: DELETE SEGURO
            $db->exec("DELETE FROM panel_ia_conocimiento WHERE fuente = 'Google Sheets - Obras'");
            $stmtIns = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            foreach ($obrasAProcesar as $obra) {
                $stmtIns->execute([$obra['categoria'], $obra['titulo'], $obra['contenido'], $obra['palabras_clave'], $obra['prioridad'], $obra['estado'], $obra['fuente']]);
            }
            $db->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Sincronización exitosa. Se insertaron ' . count($obrasAProcesar) . ' obras para Luchito.']);
        }
        exit;
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
// --- FIN FASE 8.3 ---

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
    } elseif ($action === 'suggest_keywords') {
        header('Content-Type: application/json');
        $texto = trim($_POST['texto'] ?? '');
        if (!$texto) {
            echo json_encode(['ok' => false, 'error' => 'No hay texto para analizar.']);
            exit;
        }
        
        $stmtC = $db->query("SELECT valor FROM panel_configuracion WHERE clave = 'ia_api_key'");
        $api_key_db = $stmtC->fetchColumn();
        $decrypted_key = '';
        if ($api_key_db && function_exists('decrypt_api_key')) {
            $decrypted_key = decrypt_api_key($api_key_db);
        }
        
        require_once __DIR__ . '/../ia_luchito/openai_client.php';
        $prompt = "Actúa como un experto en extracción de datos. Lee el texto y devuelve ÚNICAMENTE una lista de 10 palabras clave separadas por comas. NO devuelvas viñetas ni explicaciones, solo las palabras clave en minúsculas.";
        $ia_result = llamar_openai_responses('gpt-4o-mini', $prompt, mb_strimwidth($texto, 0, 3000, '...'), 0.3, 50, $decrypted_key);
        
        if ($ia_result['ok']) {
            echo json_encode(['ok' => true, 'keywords' => str_replace(['"', '.'], '', $ia_result['texto'])]);
        } else {
            echo json_encode(['ok' => false, 'error' => $ia_result['error']]);
        }
        exit;
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
    <a href="ia_fuentes.php">🔗 Fuentes Externas</a>
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
                            <textarea name="palabras_clave" id="input-palabras" class="form-control" rows="3" placeholder="Ej. fundacion, historia, movimiento"></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted" id="msg-sugerencia">La IA leerá tu texto y te sugerirá etiquetas.</small>
                                <button class="btn btn-sm btn-outline-info font-weight-bold" type="button" onclick="sugerirPalabrasClave(this)">✨ Sugerir Palabras con IA</button>
                            </div>
                            <div id="sugerencias-container" style="display:none; margin-top: 10px; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px dashed #bee3f8;">
                                <span style="font-size:12px; color:#0369a1; font-weight:bold; display:block; margin-bottom:5px;">Haz clic en las sugerencias para agregarlas:</span>
                                <div id="lista-sugerencias"></div>
                            </div>
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
            
            <!-- Nueva Card de Sincronización Automática -->
            <div class="card shadow-sm mb-4" style="border: 1px solid #10b981;">
                <div class="card-header text-white" style="background-color: #10b981;">
                    🔄 Sincronizar Obras desde Excel
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:13px;">Esto conectará con tu Google Sheets, leerá todas las pestañas activas y generará un documento de conocimiento por cada obra encontrada. <strong class="text-danger">Nunca borrará los documentos ingresados manualmente.</strong></p>
                    <button type="button" class="btn btn-outline-success font-weight-bold" onclick="previewSyncObras()" id="btn-preview-sync">🔍 Previsualizar Sincronización de Obras</button>
                    
                    <div id="sync-preview-container" style="display:none; margin-top:15px; padding:15px; background:#f0fdf4; border-radius:8px; border:1px solid #a7f3d0;">
                        <div id="sync-preview-content" style="font-size:13px; color:#064e3b;"></div>
                        <button type="button" class="btn btn-success font-weight-bold mt-3" onclick="confirmSyncObras()" id="btn-confirm-sync">✅ Confirmar y Sincronizar</button>
                    </div>
                </div>
            </div>

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
        document.getElementById('sugerencias-container').style.display = 'none';
        document.getElementById('lista-sugerencias').innerHTML = '';
        document.getElementById('msg-sugerencia').innerText = 'La IA leerá tu texto y te sugerirá etiquetas.';
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
        
        document.getElementById('sugerencias-container').style.display = 'none';
        document.getElementById('lista-sugerencias').innerHTML = '';
        document.getElementById('msg-sugerencia').innerText = 'La IA leerá tu texto y te sugerirá etiquetas.';

        document.getElementById('btn-cancelar').style.display = "block";
        window.scrollTo(0, 0);
    }

    async function sugerirPalabrasClave(btn) {
        const contenido = document.getElementById('input-contenido').value.trim();
        const msg = document.getElementById('msg-sugerencia');
        const inputPalabras = document.getElementById('input-palabras');
        const listaSugerencias = document.getElementById('lista-sugerencias');
        const containerSugerencias = document.getElementById('sugerencias-container');

        if (!contenido) {
            alert('Primero debes escribir algún contenido para poder sugerir palabras.');
            return;
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Analizando...';
        btn.disabled = true;
        msg.innerHTML = '<span style="color:#d97706;">Analizando texto con OpenAI...</span>';
        containerSugerencias.style.display = 'none';

        const fd = new FormData();
        fd.append('action', 'suggest_keywords');
        fd.append('texto', contenido);

        try {
            const resp = await fetch('ia_conocimiento.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                msg.innerHTML = '<span style="color:#10b981;">✅ Aquí tienes 10 opciones:</span>';
                let words = data.keywords.split(',').map(w => w.trim()).filter(w => w.length > 0);
                listaSugerencias.innerHTML = '';
                
                words.forEach(word => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'btn btn-sm btn-outline-primary m-1 font-weight-bold';
                    chip.style.borderRadius = '20px';
                    chip.innerText = '+' + word;
                    chip.onclick = function() {
                        let curr = inputPalabras.value.trim();
                        if (curr.endsWith(',')) curr = curr.slice(0, -1).trim();
                        
                        if (curr === '') {
                            inputPalabras.value = word + ', ';
                        } else {
                            const regex = new RegExp('\\b' + word + '\\b', 'i');
                            if (!regex.test(curr)) {
                                inputPalabras.value = curr + ', ' + word + ', ';
                            }
                        }
                        chip.classList.remove('btn-outline-primary');
                        chip.classList.add('btn-primary');
                        setTimeout(() => { chip.classList.remove('btn-primary'); chip.classList.add('btn-outline-primary'); }, 300);
                        inputPalabras.focus();
                    };
                    listaSugerencias.appendChild(chip);
                });
                containerSugerencias.style.display = 'block';
            } else {
                msg.innerHTML = `<span style="color:#ef4444;">❌ Error: ${data.error}</span>`;
            }
        } catch (e) {
            msg.innerHTML = `<span style="color:#ef4444;">❌ Error de conexión.</span>`;
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Funciones de Sincronización (Fase 8.3)
    async function previewSyncObras() {
        const btn = document.getElementById('btn-preview-sync');
        const container = document.getElementById('sync-preview-container');
        const content = document.getElementById('sync-preview-content');
        const btnConfirm = document.getElementById('btn-confirm-sync');
        
        btn.disabled = true; btn.innerText = '⏳ Conectando con Google Sheets...';
        container.style.display = 'none';

        const fd = new FormData(); fd.append('action', 'preview_sync_obras');
        try {
            const resp = await fetch('ia_conocimiento.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                let html = `<p class="mb-2"><strong>📊 Resultados de lectura en Excel:</strong></p><ul>`;
                html += `<li>Obras válidas encontradas: <strong class="text-success">${data.count_new}</strong></li>`;
                html += `<li>Registros antiguos que se reemplazarán: <strong class="text-danger">${data.count_delete}</strong></li></ul>`;
                if (data.samples.length > 0) {
                    html += `<p class="mb-1 mt-3"><strong>👀 Ejemplos de lo que aprenderá Luchito:</strong></p>`;
                    data.samples.forEach(s => {
                        html += `<div style="background:#ffffff; padding:8px; border-radius:4px; border:1px solid #d1fae5; margin-bottom:8px; font-size:12px;"><strong>${s.titulo}</strong><br><span class="text-muted">${s.contenido}</span></div>`;
                    });
                } else { html += `<p class="text-danger">No se encontraron obras válidas.</p>`; btnConfirm.style.display = 'none'; }
                content.innerHTML = html; container.style.display = 'block';
                if (data.count_new > 0) btnConfirm.style.display = 'inline-block';
            } else { alert('Error: ' + data.error); }
        } catch (err) { alert('Error de conexión.'); } 
        finally { btn.disabled = false; btn.innerText = '🔍 Previsualizar Sincronización de Obras'; }
    }

    async function confirmSyncObras() {
        const btn = document.getElementById('btn-confirm-sync');
        btn.disabled = true; btn.innerText = '⏳ Sincronizando e Inyectando Conocimiento...';
        const fd = new FormData(); fd.append('action', 'confirm_sync_obras');
        try {
            const resp = await fetch('ia_conocimiento.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) { alert(data.mensaje); location.reload(); } 
            else { alert('Error: ' + data.error); }
        } catch (err) { alert('Error de conexión al guardar.'); } 
        finally { btn.disabled = false; btn.innerText = '✅ Confirmar y Sincronizar'; }
    }
</script>
</body>
</html>