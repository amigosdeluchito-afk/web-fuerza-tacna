<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// 1. Crear tabla de fuentes aprobadas si no existe
$db->exec("CREATE TABLE IF NOT EXISTS panel_fuentes_aprobadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('link', 'texto_manual', 'pdf', 'word') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    url_original VARCHAR(500) DEFAULT NULL,
    contenido_extraido MEDIUMTEXT,
    contenido_aprobado MEDIUMTEXT,
    palabras_clave TEXT,
    fuente VARCHAR(100) NOT NULL,
    prioridad INT DEFAULT 5,
    estado ENUM('borrador', 'pendiente_revision', 'aprobado', 'inactivo') DEFAULT 'borrador',
    fecha_creacion DATETIME NOT NULL,
    fecha_lectura DATETIME DEFAULT NULL,
    fecha_aprobacion DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mensaje = '';
if (isset($_SESSION['ia_msg'])) {
    $mensaje = $_SESSION['ia_msg'];
    unset($_SESSION['ia_msg']);
}

// Función Helper de Fragmentación Inteligente (Chunking)
function chunk_text_rag($text, $max_len = 1200, $min_len = 800) {
    $chunks = [];
    $text = trim($text);
    while (mb_strlen($text, 'UTF-8') > $max_len) {
        $substr = mb_substr($text, 0, $max_len, 'UTF-8');
        $pos = mb_strrpos($substr, '. ', 0, 'UTF-8');
        
        if ($pos !== false && $pos > $min_len) {
            $cut = $pos + 1; // Corte ideal en un punto seguido
        } else {
            $pos = mb_strrpos($substr, ' ', 0, 'UTF-8');
            if ($pos !== false && $pos > $min_len) {
                $cut = $pos; // Corte alternativo en un espacio
            } else {
                $cut = $max_len; // Corte abrupto (palabras excesivamente largas)
            }
        }
        $chunks[] = trim(mb_substr($text, 0, $cut, 'UTF-8'));
        $text = trim(mb_substr($text, $cut, null, 'UTF-8'));
    }
    if (mb_strlen($text, 'UTF-8') > 0) { $chunks[] = $text; }
    return $chunks;
}

// 2. Procesar Formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // GUARDAR TEXTO MANUAL
    if ($action === 'save_manual') {
        $id = $_POST['id'] ?? '';
        $titulo = trim($_POST['titulo'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $contenido = trim($_POST['contenido_aprobado'] ?? '');
        $palabras_clave = trim($_POST['palabras_clave'] ?? '');
        $estado = $_POST['estado'] ?? 'borrador';
        $prioridad = (int)($_POST['prioridad'] ?? 5);

        if ($titulo && $categoria && $contenido) {
            if ($id) {
                $stmt = $db->prepare("UPDATE panel_fuentes_aprobadas SET titulo=?, categoria=?, contenido_aprobado=?, palabras_clave=?, estado=?, prioridad=?, fecha_aprobacion = CASE WHEN ? = 'aprobado' THEN NOW() ELSE fecha_aprobacion END WHERE id=?");
                $stmt->execute([$titulo, $categoria, $contenido, $palabras_clave, $estado, $prioridad, $estado, $id]);
                $_SESSION['ia_msg'] = "Fuente de texto actualizada correctamente.";
            } else {
                $stmt = $db->prepare("INSERT INTO panel_fuentes_aprobadas (tipo, titulo, categoria, contenido_aprobado, palabras_clave, fuente, prioridad, estado, fecha_creacion, fecha_aprobacion) VALUES ('texto_manual', ?, ?, ?, ?, 'Fuente Aprobada - Texto', ?, ?, NOW(), CASE WHEN ? = 'aprobado' THEN NOW() ELSE NULL END)");
                $stmt->execute([$titulo, $categoria, $contenido, $palabras_clave, $prioridad, $estado, $estado]);
                $_SESSION['ia_msg'] = "Nueva fuente de texto registrada.";
            }
        } else {
            $_SESSION['ia_msg'] = "Error: Título, Categoría y Contenido son obligatorios.";
        }
    } elseif ($action === 'sync_sources') {
        $db->beginTransaction();
        
        // 1. DELETE seguro de textos manuales Y links aprobados
        $db->exec("DELETE FROM panel_ia_conocimiento WHERE fuente IN ('Fuente Aprobada - Texto', 'Fuente Aprobada - Link')");
        
        // 2. Seleccionar fuentes aprobadas (ambos tipos)
        $stmt = $db->query("SELECT * FROM panel_fuentes_aprobadas WHERE tipo IN ('texto_manual', 'link') AND estado = 'aprobado'");
        $fuentesAprobadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertados = 0;
        $stmtIns = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, url, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())");
        
        foreach ($fuentesAprobadas as $fte) {
            $chunks = chunk_text_rag($fte['contenido_aprobado'], 1200, 800);
            $total_chunks = count($chunks);
            
            // Determinar fuente exacta según el tipo
            $fuente_exacta = ($fte['tipo'] === 'link') ? 'Fuente Aprobada - Link' : 'Fuente Aprobada - Texto';
            $url_insert = ($fte['tipo'] === 'link') ? $fte['url_original'] : null;
            
            foreach ($chunks as $i => $chunk) {
                $titulo = $fte['titulo'];
                if ($total_chunks > 1) {
                    $titulo .= " (Parte " . ($i + 1) . ")";
                }
                
                $stmtIns->execute([$fte['categoria'], $titulo, $chunk, $fte['palabras_clave'], $fte['prioridad'], $fuente_exacta, $url_insert]);
                $insertados++;
            }
        }
        $db->commit();
        $_SESSION['ia_msg'] = "Sincronización exitosa: Se inyectaron $insertados fragmentos (chunks) como conocimiento a la IA.";
        
    } elseif ($action === 'extract_link') {
        $url = trim($_POST['url_link'] ?? '');
        $categoria = trim($_POST['categoria_link'] ?? '');
        $prioridad = (int)($_POST['prioridad_link'] ?? 5);

        if ($url && $categoria) {
            $parsed = parse_url($url);
            if (!$parsed || !isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
                $_SESSION['ia_msg'] = "Error: Solo se permiten URLs válidas (http o https).";
            } else {
                $host = $parsed['host'] ?? '';
                
                // Protección SSRF pre-DNS
                if (in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0'])) {
                    $_SESSION['ia_msg'] = "Error SSRF: Host local bloqueado por seguridad.";
                } elseif (filter_var(gethostbyname($host), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    $_SESSION['ia_msg'] = "Error SSRF: URL bloqueada por seguridad. No se pueden extraer datos de redes privadas.";
                } else {
                    $stmtCheck = $db->prepare("SELECT id FROM panel_fuentes_aprobadas WHERE url_original = ?");
                    $stmtCheck->execute([$url]);
                    if ($stmtCheck->fetch()) {
                        $_SESSION['ia_msg'] = "Error: Este enlace ya fue extraído anteriormente. Puedes buscarlo en la lista y editarlo.";
                    } else {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Límite de 10 segundos
                        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS); // Bloquea file://, ftp://, php://
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                        $html = curl_exec($ch);
                        $curl_err = curl_error($ch);
                        curl_close($ch);

                        if (!$html) {
                            $_SESSION['ia_msg'] = "Error: No se pudo descargar el contenido de la URL o tardó demasiado. ($curl_err)";
                        } else {
                            // Limpieza profunda de HTML
                            $html = preg_replace('@<(script|style|nav|footer|aside|header|noscript|iframe)[^>]*?>.*?</\1>@si', ' ', $html);
                            // Forzar punto y espacio tras bloques para evitar que las palabras se peguen
                            $html = str_replace(['</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</li>', '<br>', '<br/>'], '. ', $html);
                            $text = strip_tags($html);
                            
                            // Decodificar entidades (&nbsp;, &aacute;, etc)
                            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            
                            // Limpiar espacios y puntos múltiples
                            $text = preg_replace('/\s+/', ' ', $text);
                            $text = preg_replace('/\.(\s*\.)+/', '.', $text);
                            $text = trim($text);
                            
                            if (empty($text)) {
                                $_SESSION['ia_msg'] = "Error: No se encontró texto útil en la página.";
                            } else {
                                $titulo = "Link Extraído: " . mb_strimwidth($host, 0, 40, '...');
                                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                                    $titulo = mb_strimwidth(trim(strip_tags($matches[1])), 0, 100, '...');
                                }
                                $stmt = $db->prepare("INSERT INTO panel_fuentes_aprobadas (tipo, titulo, categoria, url_original, contenido_extraido, contenido_aprobado, fuente, prioridad, estado, fecha_creacion, fecha_lectura) VALUES ('link', ?, ?, ?, ?, ?, 'Fuente Aprobada - Link', ?, 'pendiente_revision', NOW(), NOW())");
                                $stmt->execute([$titulo, $categoria, $url, $text, $text, $prioridad]);
                                $_SESSION['ia_msg'] = "Link extraído. ¡Haz clic en 'Revisar' para limpiar el texto y aprobarlo!";
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $db->prepare("DELETE FROM panel_fuentes_aprobadas WHERE id=?")->execute([$id]);
            $_SESSION['ia_msg'] = "Fuente eliminada permanentemente.";
        }
    }
    header("Location: ia_fuentes.php");
    exit;
}

// Por ahora solo leemos la tabla, las acciones de INSERT vendrán en los Bloques 2 y 3.
$stmt = $db->query("SELECT * FROM panel_fuentes_aprobadas ORDER BY id DESC");
$fuentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fuentes Externas - Fuerza Tacna</title>
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
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-aprobado { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .st-borrador { background: #f3f4f6; color: #4b5563; border: 1px solid #9ca3af; }
        .st-revision { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
        .st-inactivo { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .tipo-badge { font-family: monospace; font-size: 11px; padding: 2px 6px; background: #1e293b; color: #93c5fd; border-radius: 4px; }
    </style>
</head>
<body>

<header class="app-header">
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
    <a href="ia_conocimiento.php">📚 Base Conocimiento</a>
    <a href="ia_fuentes.php" class="active">🔗 Fuentes Externas</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php">👤 Usuarios</a>
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
        <div class="col-md-8">
            <h4 style="color:#801039; font-weight:bold;">🔗 Fuentes Externas Aprobadas</h4>
            <p class="text-muted">Espacio seguro de revisión (Human-in-the-Loop). Inyecta textos, noticias o links aprobados hacia la memoria de la IA.</p>
        </div>
        <div class="col-md-4 text-right">
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Sincronizar fuentes aprobadas hacia el cerebro de Luchito?\n\nSolo se sincronizarán las que estén en estado Aprobado. Las demás serán ignoradas.');">
                <input type="hidden" name="action" value="sync_sources">
                <button type="submit" class="btn btn-outline-success font-weight-bold mb-2">🔄 Sincronizar Hacia Conocimiento</button>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid #e2e8f0;">
                <div class="card-body bg-light rounded d-flex gap-3">
                    <button class="btn btn-ft mr-2" onclick="abrirModalTexto()">➕ Nuevo Texto Manual</button>
                    <button class="btn btn-primary" onclick="abrirModalLink()">🌐 Extraer desde Link</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Fuentes -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>Listado de Fuentes (<?= count($fuentes) ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 13px;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 8%;">Tipo</th>
                            <th style="width: 25%;">Título / Categoría</th>
                            <th style="width: 32%;">URL Original / Fuente</th>
                            <th style="width: 10%;">Estado</th>
                            <th style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($fuentes)): ?>
                            <tr><td colspan="6" class="text-center py-4">No hay fuentes registradas. Inicia agregando un texto o extrayendo un link.</td></tr>
                        <?php else: ?>
                            <?php foreach($fuentes as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><span class="tipo-badge"><?= strtoupper($row['tipo']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['titulo']) ?></strong><br>
                                    <span class="text-muted">Cat: <?= htmlspecialchars($row['categoria']) ?></span>
                                </td>
                                <td>
                                    <?php if($row['url_original']): ?>
                                        <a href="<?= htmlspecialchars($row['url_original']) ?>" target="_blank" style="font-size:11px;"><?= htmlspecialchars(mb_strimwidth($row['url_original'], 0, 40, '...')) ?></a><br>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">(Sin URL externa)</span><br>
                                    <?php endif; ?>
                                    <small class="text-muted">Fte: <?= htmlspecialchars($row['fuente']) ?></small>
                                </td>
                                <td>
                                    <?php
                                        $clase = 'st-inactivo';
                                        if($row['estado'] == 'aprobado') $clase = 'st-aprobado';
                                        elseif($row['estado'] == 'borrador') $clase = 'st-borrador';
                                        elseif($row['estado'] == 'pendiente_revision') $clase = 'st-revision';
                                    ?>
                                    <span class="status-badge <?= $clase ?>"><?= str_replace('_', ' ', $row['estado']) ?></span>
                                </td>
                                <td class="align-middle">
                                    <!-- Datos para JS -->
                                    <span id="fte-<?= $row['id'] ?>" class="d-none"
                                          data-tit="<?= htmlspecialchars($row['titulo']) ?>"
                                          data-cat="<?= htmlspecialchars($row['categoria']) ?>"
                                          data-con="<?= htmlspecialchars($row['contenido_aprobado'] ?? '') ?>"
                                          data-pal="<?= htmlspecialchars($row['palabras_clave'] ?? '') ?>"
                                          data-est="<?= $row['estado'] ?>"
                                          data-pri="<?= $row['prioridad'] ?>"
                                    ></span>
                                    
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editTexto(<?= $row['id'] ?>, '<?= $row['tipo'] ?>')">
                                        <?= $row['tipo'] === 'texto_manual' ? 'Editar' : 'Revisar' ?>
                                    </button>
                                    
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar fuente permanentemente?');">
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

<!-- MODAL: TEXTO MANUAL -->
<div class="modal-overlay" id="modalTextoManual" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:2050; align-items:center; justify-content:center;">
    <div class="card shadow-lg" style="width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0" id="modalTextoTitle">➕ Nuevo Texto Manual</h5>
            <button type="button" class="close text-white" onclick="cerrarModalTexto()">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_manual">
                <input type="hidden" name="id" id="texto-id" value="">
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">Título</label>
                        <input type="text" name="titulo" id="texto-titulo" class="form-control" required placeholder="Ej. Contacto Oficial">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">Categoría</label>
                        <input type="text" name="categoria" id="texto-categoria" class="form-control" required placeholder="Ej. Contacto, Historia...">
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Contenido Aprobado (Limpio para la IA)</label>
                    <textarea name="contenido_aprobado" id="texto-contenido" class="form-control" rows="8" required placeholder="Escribe o pega aquí la información aprobada..."></textarea>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Palabras Clave (Separadas por comas)</label>
                    <input type="text" name="palabras_clave" id="texto-palabras" class="form-control" placeholder="Ej. contacto, whatsapp, direccion">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">Estado</label>
                        <select name="estado" id="texto-estado" class="form-control">
                            <option value="borrador">📝 Borrador (No usar aún)</option>
                            <option value="aprobado">✅ Aprobado (Listo para sincronizar)</option>
                            <option value="inactivo">⏸️ Inactivo (Pausado)</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">Prioridad (1 más alta, 10 más baja)</label>
                        <input type="number" name="prioridad" id="texto-prioridad" class="form-control" value="5" min="1" max="10">
                    </div>
                </div>
                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalTexto()">Cancelar</button>
                    <button type="submit" class="btn btn-success font-weight-bold">💾 Guardar Texto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EXTRAER LINK -->
<div class="modal-overlay" id="modalLink" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:2050; align-items:center; justify-content:center;">
    <div class="card shadow-lg" style="width: 100%; max-width: 500px;">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">🌐 Extraer desde Link</h5>
            <button type="button" class="close text-white" onclick="cerrarModalLink()">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="extract_link">
                <div class="form-group">
                    <label class="font-weight-bold">URL a Extraer</label>
                    <input type="url" name="url_link" class="form-control" required placeholder="https://ejemplo.com/noticia">
                    <small class="text-muted">El servidor descargará y limpiará el texto de esta página.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Categoría Sugerida</label>
                    <input type="text" name="categoria_link" class="form-control" required placeholder="Ej. Noticias, Propuestas...">
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Prioridad (1 más alta, 10 más baja)</label>
                    <input type="number" name="prioridad_link" class="form-control" value="5" min="1" max="10">
                </div>
                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalLink()">Cancelar</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" onclick="this.innerHTML='⏳ Extrayendo...'; this.style.pointerEvents='none'; this.form.submit();">🔍 Extraer Texto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModalTexto() {
        document.getElementById('modalTextoTitle').innerText = '➕ Nuevo Texto Manual';
        document.getElementById('texto-id').value = '';
        document.getElementById('texto-titulo').value = '';
        document.getElementById('texto-categoria').value = '';
        document.getElementById('texto-contenido').value = '';
        document.getElementById('texto-palabras').value = '';
        document.getElementById('texto-estado').value = 'borrador';
        document.getElementById('texto-prioridad').value = '5';
        document.getElementById('modalTextoManual').style.display = 'flex';
    }
    
    function cerrarModalTexto() {
        document.getElementById('modalTextoManual').style.display = 'none';
    }

    function editTexto(id, tipo = 'texto_manual') {
        const span = document.getElementById('fte-' + id);
        if(!span) return;
        
        document.getElementById('modalTextoTitle').innerText = (tipo === 'link') ? '✏️ Revisar y Aprobar Link' : '✏️ Editar Texto Manual';
        document.getElementById('texto-id').value = id;
        document.getElementById('texto-titulo').value = span.getAttribute('data-tit');
        document.getElementById('texto-categoria').value = span.getAttribute('data-cat');
        document.getElementById('texto-contenido').value = span.getAttribute('data-con');
        document.getElementById('texto-palabras').value = span.getAttribute('data-pal');
        document.getElementById('texto-estado').value = span.getAttribute('data-est');
        document.getElementById('texto-prioridad').value = span.getAttribute('data-pri');
        
        document.getElementById('modalTextoManual').style.display = 'flex';
    }
    
    function abrirModalLink() {
        document.getElementById('modalLink').style.display = 'flex';
    }
    
    function cerrarModalLink() {
        document.getElementById('modalLink').style.display = 'none';
    }
</script>
</body>
</html>