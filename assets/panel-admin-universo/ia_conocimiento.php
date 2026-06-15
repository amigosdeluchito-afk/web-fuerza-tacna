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
        $actuales = trim($_POST['actuales'] ?? '');
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
        if ($actuales !== '') {
            $prompt .= " IMPORTANTE: NO incluyas ninguna de estas palabras que ya están seleccionadas o escritas manualmente: " . $actuales;
        }
        $ia_result = llamar_openai_responses('gpt-4o-mini', $prompt, mb_strimwidth($texto, 0, 3000, '...'), 0.3, 50, $decrypted_key);
        
        if ($ia_result['ok']) {
            echo json_encode(['ok' => true, 'keywords' => str_replace(['"', '.'], '', $ia_result['texto'])]);
        } else {
            echo json_encode(['ok' => false, 'error' => $ia_result['error']]);
        }
        exit;
    } elseif ($action === 'extract_url_ajax') {
        header('Content-Type: application/json');
        $url = trim($_POST['url'] ?? '');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) { echo json_encode(['ok' => false, 'error' => 'URL inválida']); exit; }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $html = curl_exec($ch); curl_close($ch);

        if (!$html) { echo json_encode(['ok' => false, 'error' => 'No se pudo descargar la web']); exit; }

        $html = preg_replace('@<(script|style|noscript|iframe|svg|canvas)[^>]*?>.*?</\1>@si', ' ', $html);
        $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')); libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        $nodosEliminar = $xpath->query("//nav | //footer | //header | //aside | //form | //*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'menu')] | //*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'sidebar')] | //*[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'menu')] | //*[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'sidebar')]");
        foreach ($nodosEliminar as $node) { if ($node->parentNode) $node->parentNode->removeChild($node); }

        $mainNode = $dom->getElementsByTagName('body')->item(0);

        if ($mainNode) {
            $htmlLimpio = $dom->saveHTML($mainNode);
            $htmlLimpio = str_replace(['</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</li>', '<br>', '<br/>', '</div>'], "\n", $htmlLimpio);
            $text = strip_tags($htmlLimpio);
        } else {
            $text = "";
        }
        
        $text = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/\n\s+/", "\n", preg_replace('/[ \t]+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')))));

        $titulo = "Extraído de Web";
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) { $titulo = mb_strimwidth(trim(strip_tags($matches[1])), 0, 100, '...'); }
        echo json_encode(['ok' => true, 'titulo' => $titulo, 'texto' => $text]); exit;
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
    } elseif ($action === 'move_to_obra') {
        $id_doc = $_POST['id_doc'] ?? '';
        $id_obra = $_POST['id_obra'] ?? '';
        
        if ($id_doc && $id_obra) {
            $stmtDoc = $db->prepare("SELECT titulo, contenido FROM panel_ia_conocimiento WHERE id = ?");
            $stmtDoc->execute([$id_doc]);
            $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
            
            $stmtObra = $db->prepare("SELECT id, contenido FROM panel_ia_conocimiento WHERE id = ?");
            $stmtObra->execute([$id_obra]);
            $obra = $stmtObra->fetch(PDO::FETCH_ASSOC);
            
            if ($doc && $obra) {
                $separador = "Contexto adicional:";
                $titulo_limpio = str_replace('---', '-', $doc['titulo']); // Evitar romper el separador de pestañas
                $tab_content = "--- " . $titulo_limpio . " ---\n" . $doc['contenido'];
                
                if (strpos($obra['contenido'], $separador) !== false) {
                    $nuevo_contenido = $obra['contenido'] . "\n\n" . $tab_content;
                } else {
                    $nuevo_contenido = trim($obra['contenido']) . " Contexto adicional: \n" . $tab_content;
                }
                
                $stmtUpdate = $db->prepare("UPDATE panel_ia_conocimiento SET contenido = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmtUpdate->execute([$nuevo_contenido, $id_obra]);
                
                $stmtDelete = $db->prepare("DELETE FROM panel_ia_conocimiento WHERE id = ?");
                $stmtDelete->execute([$id_doc]);
                
                $_SESSION['ia_msg'] = "Documento movido exitosamente como una pestaña a la obra seleccionada.";
            } else {
                $_SESSION['ia_msg'] = "Error: Documento u obra no encontrados.";
            }
        } else {
            $_SESSION['ia_msg'] = "Error: Faltan datos para mover.";
        }
    } elseif ($action === 'unificar_fragmentados') {
        header('Content-Type: application/json');
        
        $db->beginTransaction();
        try {
            // Buscar todos los documentos fragmentados y ordenarlos para que el texto se una en el orden correcto
            $stmt = $db->query("SELECT * FROM panel_ia_conocimiento WHERE titulo LIKE '% (Parte %' ORDER BY titulo ASC, id ASC");
            $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($parts) === 0) {
                echo json_encode(['ok' => false, 'error' => "No se encontraron documentos fragmentados."]);
                $db->rollBack();
                exit;
            }

            $groups = [];
            foreach ($parts as $p) {
                // Extraer el título original quitando el " (Parte X)"
                $base_title = preg_replace('/ \(Parte \d+\)$/', '', $p['titulo']);
                if (!isset($groups[$base_title])) {
                    $groups[$base_title] = [
                        'categoria' => $p['categoria'],
                        'titulo' => $base_title,
                        'contenido' => '',
                        'palabras_clave' => $p['palabras_clave'],
                        'prioridad' => $p['prioridad'],
                        'estado' => $p['estado'],
                        'fuente' => $p['fuente'],
                        'url' => $p['url'],
                        'ids' => []
                    ];
                }
                // Unir los textos
                $groups[$base_title]['contenido'] .= $p['contenido'] . "\n\n";
                $groups[$base_title]['ids'][] = $p['id'];
            }
            
            $stmtIns = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, url, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmtDel = $db->prepare("DELETE FROM panel_ia_conocimiento WHERE id = ?");
            
            foreach ($groups as $g) {
                $stmtIns->execute([$g['categoria'], $g['titulo'], trim($g['contenido']), $g['palabras_clave'], $g['prioridad'], $g['estado'], $g['fuente'], $g['url']]);
                foreach ($g['ids'] as $id) { $stmtDel->execute([$id]); }
            }
            $db->commit();
            echo json_encode(['ok'=>true, 'mensaje'=>"Limpieza exitosa. Se fusionaron " . count($parts) . " fragmentos en " . count($groups) . " documentos completos."]);
        } catch (Exception $e) { $db->rollBack(); echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]); }
        exit;
    }
    
    header("Location: ia_conocimiento.php");
    exit;
}

// 3. Obtener listado de conocimiento
$stmt = $db->query("SELECT * FROM panel_ia_conocimiento WHERE categoria != 'Obras' AND fuente != 'Google Sheets - Obras' ORDER BY prioridad ASC, id DESC");
$conocimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorías por defecto
$categorias_comunes = ['General', 'Candidatos', 'Obras', 'Propuestas', 'Contacto', 'Historia'];

// Obtener lista de obras para el modal de mover
$stmtObras = $db->query("SELECT id, titulo, palabras_clave FROM panel_ia_conocimiento WHERE categoria = 'Obras' AND fuente = 'Google Sheets - Obras' ORDER BY titulo ASC");
$obras_disponibles = $stmtObras->fetchAll(PDO::FETCH_ASSOC);

$todas_obras = [];
$segmentos_unicos = [];
foreach($obras_disponibles as $od) {
    $parts = explode(',', $od['palabras_clave']);
    $segmento = trim(end($parts));
    if (empty($segmento)) $segmento = "Otros";
    $segmentos_unicos[$segmento] = true;
    $todas_obras[] = array_merge($od, ['segmento' => $segmento]);
}
$segmentos_unicos = array_keys($segmentos_unicos);
sort($segmentos_unicos);
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
    <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">📍 Gestor Mapa</a>
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
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #020617;">
                    <span id="form-title">➕ Agregar Documento</span>
                    <button type="button" class="btn btn-sm btn-outline-info font-weight-bold" onclick="abrirExtractorUrl()">🌐 Extraer de Link</button>
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
                            <textarea name="contenido" id="input-contenido" class="form-control" rows="14" required placeholder="Fuerza Tacna es un movimiento regional fundado en..."></textarea>
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
        <div class="col-lg-7">
            
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>Documentos Cargados (<?= count($conocimientos) ?>)</span>
                </div>
                <div class="card-body p-0">
                    <!-- NUEVO: Buscador y Filtro Visual -->
                    <div style="padding: 12px; background: #0f172a; border-bottom: 1px solid #1e293b; display: flex; gap: 10px;">
                        <input type="text" id="searchListaDocs" class="form-control" placeholder="🔍 Buscar por título o contenido..." style="background: #020617; border: 1px solid #334155; color: #fff; width: 60%; padding: 6px 10px; border-radius: 6px; font-size: 13px; outline: none;" onkeyup="filtrarListaDocs()">
                        <select id="filtroCatDocs" class="form-control" style="background: #020617; border: 1px solid #334155; color: #94a3b8; width: 40%; padding: 6px 10px; border-radius: 6px; font-size: 13px; outline: none;" onchange="filtrarListaDocs()">
                            <option value="">📁 Todas las categorías</option>
                            <?php foreach($categorias_comunes as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-warning font-weight-bold" style="white-space: nowrap; font-size: 12px; padding: 6px 12px; color: #801039;" onclick="unificarFragmentados()" id="btnUnificar" title="Une todas las '(Parte X)' en un solo documento">🧩 Unificar Partes</button>
                    </div>
                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table id="tablaDocsCargados" class="table table-sm table-hover mb-0" style="font-size: 12px;">
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
                                            
                                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
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
                                                
                                                <button class="btn btn-sm btn-outline-warning py-0 px-2" onclick="abrirModalMover(<?= $row['id'] ?>)" style="width: 100%; border-color: #d97706; color: #fbbf24;">📦 Mover a Obra</button>
                                            </div>
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

<!-- Modal Mover a Obra -->
<div class="modal-overlay" id="modalMoverObra" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background: #0f172a; width: 100%; max-width: 450px; border-radius: 12px; border: 1px solid #1f2937; box-shadow: 0 25px 50px rgba(0,0,0,0.5); overflow: hidden;">
        <div style="padding: 15px 20px; border-bottom: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; color: #f8fafc; font-size: 16px; font-weight: bold;">📦 Mover a Cerebro Obras</h5>
            <button type="button" onclick="cerrarModalMover()" style="background: transparent; border: none; color: #ef4444; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <p style="color: #94a3b8; font-size: 13px; margin-top: 0; margin-bottom: 15px;">Este documento se convertirá en una nueva pestaña de la obra seleccionada y desaparecerá de esta lista.</p>
            <form method="POST" id="formMoverObra">
                <input type="hidden" name="action" value="move_to_obra">
                <input type="hidden" name="id_doc" id="mover_id_doc" value="">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="buscarObraMover" class="form-control mb-2" placeholder="🔍 Buscar por nombre de obra..." style="background: #020617; border: 1px solid #334155; color: #fff; width: 100%; padding: 8px 10px; border-radius: 6px; outline: none; font-size: 13px;" onkeyup="filtrarObrasMover()">
                    
                    <select id="filtroSegmentoMover" class="form-control mb-3" style="background: #0f172a; border: 1px solid #334155; color: #94a3b8; width: 100%; padding: 8px 10px; border-radius: 6px; outline: none; font-size: 13px;" onchange="filtrarObrasMover()">
                        <option value="">📁 Todos los segmentos</option>
                        <?php foreach($segmentos_unicos as $seg): ?>
                            <option value="<?= htmlspecialchars($seg) ?>"><?= htmlspecialchars($seg) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label style="color: #cbd5e1; font-weight: 600; font-size: 13px; margin-bottom: 8px; display: block;">Selecciona la Obra destino:</label>
                    <select name="id_obra" id="selectObraMover" class="form-control" style="background: #020617; border: 1px solid #334155; color: #fff; width: 100%; padding: 10px; border-radius: 6px; outline: none;" required size="6">
                        <?php if (empty($todas_obras)): ?>
                            <option value="" disabled>No hay obras en el Cerebro. Sincroniza desde el Excel primero.</option>
                        <?php else: ?>
                            <?php foreach($todas_obras as $od): ?>
                                <option value="<?= $od['id'] ?>" data-seg="<?= htmlspecialchars($od['segmento']) ?>" data-nom="<?= htmlspecialchars(strtolower(str_replace('Obra: ', '', $od['titulo']))) ?>"><?= htmlspecialchars(str_replace('Obra: ', '', $od['titulo'])) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>
        <div style="padding: 15px 20px; border-top: 1px solid #1e293b; display: flex; justify-content: flex-end; gap: 10px; background: #020617;">
            <button type="button" onclick="cerrarModalMover()" class="btn btn-sm" style="background: #334155; color: white; border: none; font-weight: bold; padding: 8px 15px; border-radius: 6px;">Cancelar</button>
            <button type="button" onclick="document.getElementById('formMoverObra').submit();" class="btn btn-sm" style="background: #3b82f6; color: white; border: none; font-weight: bold; padding: 8px 15px; border-radius: 6px;">Mover y Fusionar</button>
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

    function abrirModalMover(id) {
        document.getElementById('mover_id_doc').value = id;
        document.getElementById('modalMoverObra').style.display = 'flex';
        
        // Inicializar backup de opciones para el filtro estricto
        if (!window.allObrasOptions) {
            const select = document.getElementById('selectObraMover');
            window.allObrasOptions = Array.from(select.options).map(o => ({
                value: o.value, text: o.textContent, seg: o.getAttribute('data-seg'), nom: o.getAttribute('data-nom'), disabled: o.disabled
            }));
        }
    }
    
    function cerrarModalMover() {
        document.getElementById('modalMoverObra').style.display = 'none';
        document.getElementById('mover_id_doc').value = '';
        
        // Resetear filtros al cerrar
        document.getElementById('buscarObraMover').value = '';
        document.getElementById('filtroSegmentoMover').value = '';
        filtrarObrasMover();
    }

    function filtrarObrasMover() {
        const txt = document.getElementById('buscarObraMover').value.toLowerCase();
        const seg = document.getElementById('filtroSegmentoMover').value;
        const select = document.getElementById('selectObraMover');
        
        if (!window.allObrasOptions) return;
        
        select.innerHTML = '';
        let count = 0;
        
        window.allObrasOptions.forEach(optData => {
            if (optData.disabled) {
                if (window.allObrasOptions.length === 1) {
                    const opt = document.createElement('option');
                    opt.disabled = true; opt.textContent = optData.text; select.appendChild(opt);
                }
                return;
            }
            
            const matchTxt = (optData.nom || '').includes(txt);
            const matchSeg = seg === "" || optData.seg === seg;
            
            if (matchTxt && matchSeg) {
                const opt = document.createElement('option');
                opt.value = optData.value;
                opt.textContent = optData.text;
                opt.setAttribute('data-seg', optData.seg);
                opt.setAttribute('data-nom', optData.nom);
                select.appendChild(opt);
                count++;
            }
        });

        if (count === 0 && window.allObrasOptions.length > 1) {
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.textContent = "No hay obras que coincidan con la búsqueda.";
            select.appendChild(opt);
        }
    }

    async function abrirExtractorUrl() {
        const url = prompt("Pega aquí el enlace de la noticia o página web:");
        if (!url) return;
        
        const formTitle = document.getElementById('form-title');
        const originalText = formTitle.innerText;
        formTitle.innerText = "⏳ Extrayendo...";
        
        const fd = new FormData(); fd.append('action', 'extract_url_ajax'); fd.append('url', url);
        
        try {
            const resp = await fetch('ia_conocimiento.php', {method: 'POST', body: fd});
            const data = await resp.json();
            if (data.ok) {
                resetForm();
                document.getElementById('input-titulo').value = data.titulo;
                document.getElementById('input-contenido').value = data.texto;
                document.getElementById('input-fuente').value = "Web Extraída";
                document.getElementById('input-url').value = url;
                formTitle.innerText = "✨ ¡Extraído! Edita y guarda.";
                document.getElementById('btn-cancelar').style.display = "block";
            } else { alert("Error: " + data.error); formTitle.innerText = originalText; }
        } catch(e) {
            alert("Error de red"); formTitle.innerText = originalText;
        }
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
        fd.append('actuales', inputPalabras.value.trim());

        try {
            const resp = await fetch('ia_conocimiento.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                msg.innerHTML = '<span style="color:#10b981;">✅ Aquí tienes opciones nuevas:</span>';
                let words = data.keywords.split(',').map(w => w.trim()).filter(w => w.length > 0);
                listaSugerencias.innerHTML = '';
                
                words.forEach(word => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'btn btn-sm btn-outline-primary m-1 font-weight-bold';
                    chip.style.borderRadius = '20px';
                    chip.style.transition = 'all 0.2s ease';
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
                        
                        // Eliminar visualmente el chip sugerido
                        chip.style.transform = 'scale(0)';
                        chip.style.opacity = '0';
                        setTimeout(() => { chip.remove(); }, 200);
                        
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

    function filtrarListaDocs() {
        const txt = document.getElementById('searchListaDocs').value.toLowerCase();
        const cat = document.getElementById('filtroCatDocs').value.toLowerCase();
        const filas = document.querySelectorAll('#tablaDocsCargados tbody tr');

        filas.forEach(fila => {
            if (fila.children.length === 1) return; // Fila vacía
            
            const spanDatos = fila.querySelector('span[id^="data-"]');
            if (!spanDatos) return;

            const filaCat = spanDatos.getAttribute('data-cat').toLowerCase();
            const filaTit = spanDatos.getAttribute('data-tit').toLowerCase();
            const filaCon = spanDatos.getAttribute('data-con').toLowerCase();

            const matchTxt = filaTit.includes(txt) || filaCon.includes(txt);
            const matchCat = cat === "" || filaCat === cat;

            fila.style.display = (matchTxt && matchCat) ? "" : "none";
        });
    }

    async function unificarFragmentados() {
        if (!confirm('¿Fusionar todos los documentos que tienen "(Parte X)" en su título?\n\nEl sistema agrupará el texto y borrará los fragmentos antiguos. Esto dejará tu lista mucho más limpia y corta.')) return;
        const btn = document.getElementById('btnUnificar');
        const txtOriginal = btn.innerHTML;
        btn.innerHTML = '⏳ Unificando...'; btn.disabled = true;
        const fd = new FormData(); fd.append('action', 'unificar_fragmentados');
        try {
            const resp = await fetch('ia_conocimiento.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) { alert(data.mensaje); location.reload(); } else { alert('Error: ' + data.error); btn.innerHTML = txtOriginal; btn.disabled = false; }
        } catch (err) { alert('Error de conexión.'); btn.innerHTML = txtOriginal; btn.disabled = false; }
    }
</script>
</body>
</html>