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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Forzar la conversión de la tabla antigua y sus columnas al formato que soporta emojis
$db->exec("ALTER TABLE panel_ia_respuestas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Crear tabla de huérfanas si no se creó antes (Seguridad)
$db->exec("CREATE TABLE IF NOT EXISTS panel_preguntas_huerfanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta TEXT NOT NULL,
    normalizada VARCHAR(255) NOT NULL,
    categoria_detectada VARCHAR(100) NULL,
    fecha DATETIME NOT NULL,
    repeticiones INT DEFAULT 1,
    origen VARCHAR(50) DEFAULT 'router',
    estado VARCHAR(50) DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Crear tabla de configuración global (Paso 5B-1)
$db->exec("CREATE TABLE IF NOT EXISTS panel_configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor MEDIUMTEXT NOT NULL,
    fecha_actualizacion DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("ALTER TABLE panel_configuracion MODIFY COLUMN valor MEDIUMTEXT NOT NULL");

$default_prompt = "Eres Luchito, el asistente virtual y mascota oficial de Fuerza Tacna. Eres un osito andino amigable, un 'tío digital' con mucho cariño por Tacna. Respondes de forma coloquial, cercana y breve (máximo 2 o 3 oraciones). Nunca inventas información que no tienes. Si te preguntan sobre temas políticos nacionales (Presidentes, Congreso, Lima), respondes que tu labor es exclusivamente sobre Tacna y sus obras.";

$stmtConf = $db->prepare("INSERT IGNORE INTO panel_configuracion (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())");
$stmtConf->execute(['ia_prompt_maestro', $default_prompt]);
$stmtConf->execute(['ia_activa', '0']);
$stmtConf->execute(['ia_modo', 'simulador']);
$stmtConf->execute(['ia_modelo', 'gpt-4o-mini']);
$stmtConf->execute(['ia_temperatura', '0.7']);
$stmtConf->execute(['ia_max_tokens', '150']);
$stmtConf->execute(['ia_limite_global_diario', '1000']);
$stmtConf->execute(['ia_limite_ip_diario', '10']);
$stmtConf->execute(['ia_mensaje_fallback_openai', '😅 Mi cerebro digital está un poco saturado ahorita, vecino. ¿Qué tal si mientras tanto vemos el mapa de obras o a los candidatos?']);
$stmtConf->execute(['ia_api_key', '']);
$stmtConf->execute(['ia_debug_mode', '0']);

// 2. Función para generar el JSON Público
function regenerar_json_ia($db) {
    $stmt = $db->query("SELECT * FROM panel_ia_respuestas WHERE estado = 1 ORDER BY orden ASC, id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $json_data = [];
    foreach ($rows as $r) {
        $palabras = array_filter(array_map('trim', explode(',', $r['palabras_clave'])));
        if (empty($palabras)) continue;
        
        // Normalizar palabras clave para que coincidan sin tildes con el input de JS
        $unwanted = array('á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n', 'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ñ'=>'n');
        $palabras = array_map(function($p) use ($unwanted) { return strtr(mb_strtolower($p, 'UTF-8'), $unwanted); }, $palabras);
        
        // Patrón estricto: Máx 3 palabras y coincidencia exacta
        $pattern_str = '^(?=(?:\\S+\\s+){0,2}\\S+$).*?(?:^|[^a-z0-9])(' . implode('|', $palabras) . ')(?:[^a-z0-9]|$).*$';
        
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
if (isset($_SESSION['ia_mensaje'])) {
    $mensaje = $_SESSION['ia_mensaje'];
    unset($_SESSION['ia_mensaje']);
}

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
            
            // Marcar huérfana como convertida si venía de ahí
            $huerfana_id = $_POST['huerfana_id'] ?? '';
            if ($huerfana_id) {
                $stmtH = $db->prepare("UPDATE panel_preguntas_huerfanas SET estado='convertida' WHERE id=?");
                $stmtH->execute([$huerfana_id]);
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
    elseif ($action === 'ignorar_huerfana') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $db->prepare("UPDATE panel_preguntas_huerfanas SET estado='ignorada' WHERE id=?");
            $stmt->execute([$id]);
            $mensaje = "Pregunta huérfana ignorada correctamente.";
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
    elseif ($action === 'save_prompt') {
        $prompt = $_POST['prompt_maestro'] ?? '';
        $activa = isset($_POST['ia_activa']) ? '1' : '0';
        $modo = $_POST['ia_modo'] ?? 'simulador';
        $modelo = $_POST['ia_modelo'] ?? 'gpt-4o-mini';
        $temp = $_POST['ia_temperatura'] ?? '0.7';
        $max_tokens = $_POST['ia_max_tokens'] ?? '150';
        $lim_global = $_POST['ia_limite_global_diario'] ?? '1000';
        $lim_ip = $_POST['ia_limite_ip_diario'] ?? '10';
        $fallback = trim($_POST['ia_mensaje_fallback_openai'] ?? '');
        $raw_api_key = trim($_POST['ia_api_key'] ?? '');
        $debug_mode = isset($_POST['ia_debug_mode']) ? '1' : '0';

        if (trim($prompt) === '') {
            $mensaje = "Error: El prompt no puede estar vacío.";
        } elseif (mb_strlen($prompt, 'UTF-8') > 100000) {
            $mensaje = "Error: El prompt supera el límite de 100,000 caracteres.";
        } else {
            $stmt = $db->prepare("UPDATE panel_configuracion SET valor=?, fecha_actualizacion=NOW() WHERE clave=?");
            $stmt->execute([$prompt, 'ia_prompt_maestro']);
            $stmt->execute([$activa, 'ia_activa']);
            $stmt->execute([$modo, 'ia_modo']);
            $stmt->execute([$modelo, 'ia_modelo']);
            $stmt->execute([$temp, 'ia_temperatura']);
            $stmt->execute([$max_tokens, 'ia_max_tokens']);
            $stmt->execute([$lim_global, 'ia_limite_global_diario']);
            $stmt->execute([$lim_ip, 'ia_limite_ip_diario']);
            $stmt->execute([$fallback, 'ia_mensaje_fallback_openai']);
            $stmt->execute([$debug_mode, 'ia_debug_mode']);
            
            $mensaje = "Configuración de IA guardada correctamente.";

            if ($raw_api_key !== '') {
                $encrypted_key = encrypt_api_key($raw_api_key);
                if ($encrypted_key !== '') {
                    $stmt->execute([$encrypted_key, 'ia_api_key']);
                } else {
                    $mensaje .= " (Atención: No se guardó la API Key porque falta configurar OPENAI_KEY_ENCRYPTION_SECRET en el servidor).";
                }
            }
        }
    }
    elseif ($action === 'restore_prompt') {
        $stmt = $db->prepare("UPDATE panel_configuracion SET valor=?, fecha_actualizacion=NOW() WHERE clave=?");
        $stmt->execute([$default_prompt, 'ia_prompt_maestro']);
        $mensaje = "Prompt Maestro restaurado al valor por defecto.";
    }
    
    // Prevenir reenvío del formulario al actualizar la página (F5)
    $_SESSION['ia_mensaje'] = $mensaje;
    header("Location: ia_respuestas.php");
    exit;
}

// 4. Obtener listado para la tabla
$stmt = $db->query("SELECT * FROM panel_ia_respuestas ORDER BY orden ASC, id DESC");
$respuestas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener Huérfanas Pendientes
$stmtH = $db->query("SELECT * FROM panel_preguntas_huerfanas WHERE estado = 'pendiente' ORDER BY repeticiones DESC, fecha DESC");
$huerfanas_list = $stmtH->fetchAll(PDO::FETCH_ASSOC);

// Obtener Auditoría IA (Últimos 300 registros)
$auditoria_list = [];
try {
    $stmtAud = $db->query("SELECT * FROM panel_ia_auditoria ORDER BY fecha DESC LIMIT 300");
    if ($stmtAud) $auditoria_list = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Obtener Configuración de IA
$stmtC = $db->query("SELECT clave, valor, fecha_actualizacion FROM panel_configuracion");
$config_rows = $stmtC->fetchAll(PDO::FETCH_ASSOC);
$config_ia = [
    'ia_prompt_maestro' => '', 'ia_activa' => '0', 'fecha_actualizacion' => '',
    'ia_modo' => 'simulador', 'ia_modelo' => 'gpt-4o-mini', 'ia_temperatura' => '0.7',
    'ia_max_tokens' => '150', 'ia_limite_global_diario' => '1000', 'ia_limite_ip_diario' => '10',
    'ia_mensaje_fallback_openai' => '😅 Mi cerebro digital está un poco saturado ahorita, vecino. ¿Qué tal si mientras tanto vemos el mapa de obras o a los candidatos?',
    'ia_api_key' => '',
    'ia_debug_mode' => '0'
];
foreach ($config_rows as $row) {
    $config_ia[$row['clave']] = $row['valor'];
    if ($row['clave'] === 'ia_prompt_maestro') {
        $config_ia['fecha_actualizacion'] = $row['fecha_actualizacion'];
    }
}

$api_key_configured = !empty($config_ia['ia_api_key']) || (defined('OPENAI_API_KEY') && trim(OPENAI_API_KEY) !== '');

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
        
        /* Contenedor escroleable de respuestas */
        #respuestas-container { max-height: 380px; overflow-y: auto; padding: 10px; background: #f1f3f5; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 10px; }
        #respuestas-container::-webkit-scrollbar { width: 6px; }
        #respuestas-container::-webkit-scrollbar-track { background: transparent; }
        #respuestas-container::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 10px; }
        #respuestas-container::-webkit-scrollbar-thumb:hover { background: #6c757d; }
        
        .response-row { background: #ffffff; padding: 10px; border-radius: 8px; margin-bottom: 10px; position: relative; border: 1px solid #dee2e6; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .action-row { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px; position: relative; border: 1px solid #dee2e6; }
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

        /* Truncar palabras clave largas en la tabla */
        .celda-palabras-clave {
            max-width: 250px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Estilos del Menú Desplegable */
        .dropdown { position: relative; display: inline-block; margin-right: 16px; }
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
  <nav style="display:flex; align-items:center;">
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">📷 Fotos</a>
    <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">➕ Agregar Obra</a>
    <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">✏️ Editar Obra</a>
    <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">👁️ Visibilidad</a>
    <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">🗂️ Segmentos</a>
    <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">⏳ Cronología</a>
    <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">👥 Candidatos</a>
    
    <div class="dropdown">
      <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['ia_respuestas.php', 'ia_conocimiento.php', 'ia_fuentes.php', 'ia_estadisticas.php']) ? 'active' : '' ?>">🧠 IA y Conocimiento ▾</button>
      <div class="dropdown-content">
        <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">🧠 Cerebro IA</a>
        <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">📚 Base Conocimiento</a>
        <a href="ia_fuentes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_fuentes.php' ? 'active' : '' ?>">🔗 Fuentes Externas</a>
        <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">📊 Estadísticas IA</a>
      </div>
    </div>

    <?php if (is_admin()): ?>
    <div class="dropdown">
      <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['usuarios.php', 'historial.php', 'ver_accesos.php']) ? 'active' : '' ?>">⚙️ Admin ▾</button>
      <div class="dropdown-content">
        <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
        <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
        <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos IP</a>
      </div>
    </div>
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
        <div class="col-lg-6 col-xl-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex p-0" id="form-tabs-header">
                    <button type="button" class="btn flex-fill tab-btn active" id="tab-manual" onclick="switchTab('manual')">✍️ Manual</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-import" onclick="switchTab('import')">📦 Masivo</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-test" onclick="switchTab('test')">🧪 Probar</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-huerfanas" onclick="switchTab('huerfanas')">❓ Huérfanas <span class="badge badge-light ml-1"><?= count($huerfanas_list) ?></span></button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-prompt" onclick="switchTab('prompt')">🤖 Prompt IA</button>
                    <button type="button" class="btn flex-fill tab-btn" id="tab-auditoria" onclick="switchTab('auditoria')">📊 Auditoría</button>
                </div>
                <div class="card-body" id="body-manual">
                    <h6 class="mb-3 font-weight-bold" id="form-title" style="color:#801039;">Nueva Respuesta</h6>
                    <form method="POST" id="ia-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="input-id" value="">
                        <input type="hidden" name="huerfana_id" id="input-huerfana-id" value="">
                        
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
                            <label>¿Qué dirá Luchito? (Variantes) <small class="text-muted ml-2">💡 Tip: Usa <code>[btn:ir_a_obras|Ver Mapa]</code> en el texto para un botón exclusivo.</small></label>
                            <div id="respuestas-container">
                                <div class="response-row">
                                    <textarea name="respuestas[]" class="form-control" rows="2" required></textarea>
                                    <div class="mt-1 text-right">
                                        <select class="custom-select custom-select-sm d-inline-block w-auto" style="font-size: 11px; height: 24px; padding: 2px 20px 2px 8px;" onchange="insertShortcode(this)">
                                            <option value="">+ Añadir Botón Mágico</option>
                                            <option value="[btn:ir_a_obras|🗺️ Ver Mapa]">🗺️ Ver Mapa</option>
                                            <option value="[btn:ir_a_candidatos|👥 Ver Candidatos]">👥 Ver Candidatos</option>
                                            <option value="[btn:ir_a_propuestas|🚀 Ver Propuestas]">🚀 Ver Propuestas</option>
                                            <option value="[btn:ir_a_sumate|💪 Súmate]">💪 Súmate</option>
                                            <option value="[btn:ir_a_contacto|📞 Contacto]">📞 Contacto</option>
                                        </select>
                                    </div>
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
                            <label>Asignar lote a categoría (Opcional)</label>
                            <select id="input-import-categoria-select" class="form-control" onchange="checkNewImportCategory()">
                                <option value="">-- Mantener las categorías que vengan dentro del JSON --</option>
                                <?php foreach($all_cats as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                                <option value="NEW" style="font-weight: bold; color: #801039;">+ Nueva Categoría a todo el lote</option>
                            </select>
                            <input type="text" id="input-import-categoria-nueva" class="form-control mt-2" placeholder="Escribe el nombre de la categoría..." style="display:none;">
                        </div>

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

                <!-- TAB DE HUÉRFANAS -->
                <div class="card-body" id="body-huerfanas" style="display:none;">
                    <h6 class="mb-3 font-weight-bold" style="color:#801039;">Preguntas sin Respuesta</h6>
                    <p style="font-size: 12px; color: #6c757d; margin-top:-5px;">Consultas reales de usuarios que no encontraron respuesta. ¡Conviértelas en reglas!</p>
                    
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                        <table class="table table-sm table-hover mb-0" style="font-size: 12.5px;">
                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th class="text-center" style="width:10%;">Rep.</th>
                                    <th>Pregunta y Categoría</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($huerfanas_list)): ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">No hay preguntas huérfanas pendientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach($huerfanas_list as $h): ?>
                                    <tr>
                                        <td class="text-center align-middle"><span class="badge badge-primary" style="font-size:12px;"><?= $h['repeticiones'] ?></span></td>
                                        <td class="align-middle"><strong><?= htmlspecialchars($h['pregunta']) ?></strong><br><small class="badge badge-secondary mt-1"><?= htmlspecialchars($h['categoria_detectada'] ?: 'Sin Tema') ?></small></td>
                                        <td class="text-right align-middle" style="white-space: nowrap;">
                                            <button class="btn btn-sm btn-outline-success mr-1" title="Convertir en Regla" onclick="convertirHuerfana(<?= $h['id'] ?>, '<?= htmlspecialchars(addslashes($h['categoria_detectada'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($h['normalizada'])) ?>')">✨</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Ignorar esta pregunta? Ya no aparecerá en la lista.');"><input type="hidden" name="action" value="ignorar_huerfana"><input type="hidden" name="id" value="<?= $h['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Ignorar">❌</button></form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB DE PROMPT MAESTRO Y CONFIGURACIÓN -->
                <div class="card-body" id="body-prompt" style="display:none;">
                    <form method="POST" id="prompt-form">
                        <input type="hidden" name="action" value="save_prompt">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h6 class="mb-0 font-weight-bold" style="color:#801039;">Cerebro de Luchito y Configuración API</h6>
                                <p style="font-size: 12px; color: #6c757d; margin:0;">Configura la personalidad, parámetros técnicos y límites de consumo.</p>
                            </div>
                            <div class="d-flex align-items-center">
                                <?php if ($api_key_configured): ?>
                                    <span class="badge badge-success mr-4 p-2" style="font-size:12px;">✅ API Key Servidor: OK</span>
                                <?php else: ?>
                                    <span class="badge badge-danger mr-4 p-2" style="font-size:12px;">❌ API Key Faltante en config.php</span>
                                <?php endif; ?>

                                <div class="custom-control custom-switch" style="transform: scale(1.2);">
                                    <input type="checkbox" class="custom-control-input" id="switchIA" name="ia_activa" <?= $config_ia['ia_activa'] === '1' ? 'checked' : '' ?>>
                                    <label class="custom-control-label font-weight-bold" for="switchIA" style="cursor: pointer; color: <?= $config_ia['ia_activa'] === '1' ? '#28a745' : '#dc3545' ?>;" id="labelSwitchIA">
                                        <?= $config_ia['ia_activa'] === '1' ? 'IA ACTIVADA' : 'IA APAGADA' ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Columna Izquierda: Personalidad -->
                            <div class="col-xl-7 col-lg-12 mb-4 mb-xl-0 pr-xl-4">
                                <h6 class="font-weight-bold mb-3 text-secondary">🧠 Personalidad y Mensajes</h6>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Prompt Maestro</label>
                                    <textarea name="prompt_maestro" id="promptMaestroArea" class="form-control" rows="12" style="font-family: monospace; font-size: 12.5px; background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; line-height: 1.5; resize: vertical;" required maxlength="100000"><?= htmlspecialchars($config_ia['ia_prompt_maestro']) ?></textarea>
                                    <small class="text-muted d-block mt-1 text-right" id="promptCounter">0 / 100000 caracteres</small>
                                </div>

                                <div class="form-group mt-4">
                                    <label class="font-weight-bold">Mensaje Fallback (Si OpenAI falla)</label>
                                    <textarea name="ia_mensaje_fallback_openai" class="form-control" rows="2" required><?= htmlspecialchars($config_ia['ia_mensaje_fallback_openai']) ?></textarea>
                                    <small class="text-muted">Se mostrará si hay timeout, error de API Key o saldo insuficiente.</small>
                                </div>
                            </div>

                            <!-- Columna Derecha: Parámetros Técnicos -->
                            <div class="col-xl-5 col-lg-12 pl-xl-4">
                                <h6 class="font-weight-bold mb-3 text-secondary">⚙️ Parámetros Técnicos</h6>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">API Key de OpenAI</label>
                                    <input type="password" name="ia_api_key" class="form-control" value="" placeholder="<?= $api_key_configured ? '•••••••••••• (Cifrada. Deja en blanco para no cambiar)' : 'sk-proj-...' ?>" autocomplete="new-password">
                                    <small class="text-muted">Se guardará cifrada (AES-256) en la base de datos.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Modo de Ejecución</label>
                                    <select name="ia_modo" class="form-control">
                                        <option value="simulador" <?= $config_ia['ia_modo'] === 'simulador' ? 'selected' : '' ?>>🧪 Modo Simulador (Gratis - Sin cURL)</option>
                                        <option value="produccion" <?= $config_ia['ia_modo'] === 'produccion' ? 'selected' : '' ?>>🚀 Modo Producción (Conectar OpenAI)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">Modelo OpenAI</label>
                                    <select name="ia_modelo" class="form-control">
                                        <option value="gpt-4o-mini" <?= $config_ia['ia_modelo'] === 'gpt-4o-mini' ? 'selected' : '' ?>>gpt-4o-mini (Rápido y económico)</option>
                                        <option value="gpt-4o" <?= $config_ia['ia_modelo'] === 'gpt-4o' ? 'selected' : '' ?>>gpt-4o (Mayor razonamiento, más caro)</option>
                                        <option value="gpt-3.5-turbo" <?= $config_ia['ia_modelo'] === 'gpt-3.5-turbo' ? 'selected' : '' ?>>gpt-3.5-turbo (Legado)</option>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold">Temperatura</label>
                                        <input type="number" step="0.1" min="0" max="1" name="ia_temperatura" class="form-control" value="<?= htmlspecialchars($config_ia['ia_temperatura']) ?>" required>
                                        <small class="text-muted">0.0 a 1.0 (Rec. 0.7)</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold">Max Tokens Salida</label>
                                        <input type="number" step="10" min="10" max="1000" name="ia_max_tokens" class="form-control" value="<?= htmlspecialchars($config_ia['ia_max_tokens']) ?>" required>
                                        <small class="text-muted">Límite de respuesta</small>
                                    </div>
                                </div>

                                <h6 class="font-weight-bold mt-4 mb-3 text-secondary">🛡️ Límites Diarios y Costos</h6>
                                
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold">Límite Global</label>
                                        <input type="number" name="ia_limite_global_diario" class="form-control" value="<?= htmlspecialchars($config_ia['ia_limite_global_diario']) ?>" required>
                                        <small class="text-muted">Consultas de toda la web</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold">Límite por IP</label>
                                        <input type="number" name="ia_limite_ip_diario" class="form-control" value="<?= htmlspecialchars($config_ia['ia_limite_ip_diario']) ?>" required>
                                        <small class="text-muted">Consultas por persona</small>
                                    </div>
                                </div>

                                <h6 class="font-weight-bold mt-4 mb-3 text-secondary">🛠️ Herramientas de Desarrollo</h6>
                                <div class="form-group p-3" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px;">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="switchDebug" name="ia_debug_mode" <?= $config_ia['ia_debug_mode'] === '1' ? 'checked' : '' ?>>
                                        <label class="custom-control-label font-weight-bold text-danger" for="switchDebug">Activar Modo Debug</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Muestra la información inyectada a la IA en la pestaña "Network" del navegador. <b>¡Apágalo cuando termines de probar!</b></small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">Actualizado: <?= date('d/m/Y H:i', strtotime($config_ia['fecha_actualizacion'] ?: 'now')) ?></small>
                            <div>
                                <button type="button" class="btn btn-outline-danger btn-sm mr-2" onclick="if(confirm('¿Seguro que deseas restaurar el prompt por defecto? Perderás tus cambios actuales.')) { document.getElementById('restore-form').submit(); }">🔄 Restaurar prompt original</button>
                                <button type="submit" class="btn btn-ft">💾 Guardar Configuración</button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" id="restore-form" style="display:none;"><input type="hidden" name="action" value="restore_prompt"></form>
                </div>

                <!-- TAB DE AUDITORÍA -->
                <div class="card-body" id="body-auditoria" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0 font-weight-bold" style="color:#801039;">Auditoría y Consumo de IA</h6>
                            <p style="font-size: 12px; color: #6c757d; margin:0;">Registro en tiempo real de consultas, límites y errores.</p>
                        </div>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>IP (Hash)</th>
                                    <th>Interacción (Pregunta / Respuesta)</th>
                                    <th class="text-center">Tokens</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($auditoria_list)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No hay registros de auditoría aún.</td></tr>
                                <?php else: ?>
                                    <?php foreach($auditoria_list as $a): ?>
                                    <tr>
                                        <td class="align-middle" style="white-space: nowrap;"><?= date('d/m H:i', strtotime($a['fecha'])) ?></td>
                                        <td class="align-middle" title="<?= htmlspecialchars($a['ip_hash']) ?>"><span class="badge badge-light border"><?= substr($a['ip_hash'], 0, 8) ?></span></td>
                                        <td>
                                            <strong style="color: #020617;">U:</strong> <?= htmlspecialchars($a['pregunta']) ?><br>
                                            <span style="color: #801039;"><strong>IA:</strong> <?= htmlspecialchars(mb_strimwidth($a['respuesta'], 0, 80, '...')) ?></span>
                                            <?php if(!empty($a['motivo_error'])): ?>
                                                <br><small style="color:#dc3545;"><strong>Error:</strong> <?= htmlspecialchars($a['motivo_error']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if($a['tokens_input'] > 0 || $a['tokens_output'] > 0): ?>
                                                <span class="badge badge-info" title="Input: <?= $a['tokens_input'] ?> | Output: <?= $a['tokens_output'] ?>"><?= $a['tokens_input'] + $a['tokens_output'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php
                                            $badgeClass = 'badge-secondary';
                                            if ($a['estado'] === 'exito_openai') $badgeClass = 'badge-success';
                                            elseif ($a['estado'] === 'exito_simulador') $badgeClass = 'badge-primary';
                                            elseif (strpos($a['estado'], 'error') !== false) $badgeClass = 'badge-danger';
                                            elseif (strpos($a['estado'], 'limite') !== false) $badgeClass = 'badge-warning';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($a['estado']) ?></span>
                                            <br><small class="text-muted"><?= htmlspecialchars($a['modelo']) ?></small>
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

        <!-- Columna Listado -->
        <div class="col-lg-6 col-xl-5">
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
                                    <th style="width: 8%;">Orden</th>
                                    <th style="width: 25%;">Categoría / Palabras</th>
                                    <th style="width: 32%;">Respuestas</th>
                                    <th style="width: 10%;">Estado</th>
                                    <th style="width: 25%;">Acciones</th>
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
                                            <div class="text-muted celda-palabras-clave" title="<?= htmlspecialchars($row['palabras_clave']) ?>" style="font-size: 85%; margin-top: 4px;"><?= htmlspecialchars($row['palabras_clave']) ?></div>
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
        $unwanted = array('á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n', 'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ñ'=>'n');
        foreach ($respuestas_list as $r) {
            if ($r['estado'] != 1) continue;
            $palabras = array_filter(array_map('trim', explode(',', $r['palabras_clave'])));
            if (empty($palabras)) continue;
            
            $palabras = array_map(function($p) use ($unwanted) { return strtr(mb_strtolower($p, 'UTF-8'), $unwanted); }, $palabras);
            
            $pattern_str = '^(?=(?:\\S+\\s+){0,2}\\S+$).*?(?:^|[^a-z0-9])(' . implode('|', $palabras) . ')(?:[^a-z0-9]|$).*$';
            $test_data[] = ['pattern_str' => $pattern_str, 'responses' => json_decode($r['respuestas'], true) ?: [], 'actions' => json_decode($r['acciones'], true) ?: []];
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
            <div class="mt-1 text-right">
                <select class="custom-select custom-select-sm d-inline-block w-auto" style="font-size: 11px; height: 24px; padding: 2px 20px 2px 8px;" onchange="insertShortcode(this)">
                    <option value="">+ Añadir Botón Mágico</option>
                    <option value="[btn:ir_a_obras|🗺️ Ver Mapa]">🗺️ Ver Mapa</option>
                    <option value="[btn:ir_a_candidatos|👥 Ver Candidatos]">👥 Ver Candidatos</option>
                    <option value="[btn:ir_a_propuestas|🚀 Ver Propuestas]">🚀 Ver Propuestas</option>
                    <option value="[btn:ir_a_sumate|💪 Súmate]">💪 Súmate</option>
                    <option value="[btn:ir_a_contacto|📞 Contacto]">📞 Contacto</option>
                </select>
            </div>
        `;
        cont.appendChild(div);
        cont.scrollTop = cont.scrollHeight; // Auto-scroll al final al añadir
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

    function checkNewImportCategory() {
        const sel = document.getElementById('input-import-categoria-select');
        const input = document.getElementById('input-import-categoria-nueva');
        if (sel.value === 'NEW') {
            input.style.display = 'block';
            input.required = true;
        } else {
            input.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function insertShortcode(sel) {
        if (!sel.value) return;
        const textarea = sel.parentElement.previousElementSibling;
        textarea.value = textarea.value.trim() + (textarea.value.trim() !== "" ? " " : "") + sel.value;
        textarea.focus();
        sel.value = ""; // Resetear select tras usar
    }

    function resetForm() {
        document.getElementById('form-title').innerText = "Nueva Respuesta";
        document.getElementById('input-id').value = "";
        document.getElementById('input-huerfana-id').value = "";
        document.getElementById('input-categoria-select').value = "";
        checkNewCategory();
        document.getElementById('input-palabras').value = "";
        document.getElementById('input-orden').value = "10";
        document.getElementById('input-estado').checked = true;
        
        document.getElementById('respuestas-container').innerHTML = `
            <div class="response-row">
                <textarea name="respuestas[]" class="form-control" rows="2" required></textarea>
                <div class="mt-1 text-right">
                    <select class="custom-select custom-select-sm d-inline-block w-auto" style="font-size: 11px; height: 24px; padding: 2px 20px 2px 8px;" onchange="insertShortcode(this)">
                        <option value="">+ Añadir Botón Mágico</option>
                        <option value="[btn:ir_a_obras|🗺️ Ver Mapa]">🗺️ Ver Mapa</option>
                        <option value="[btn:ir_a_candidatos|👥 Ver Candidatos]">👥 Ver Candidatos</option>
                        <option value="[btn:ir_a_propuestas|🚀 Ver Propuestas]">🚀 Ver Propuestas</option>
                        <option value="[btn:ir_a_sumate|💪 Súmate]">💪 Súmate</option>
                        <option value="[btn:ir_a_contacto|📞 Contacto]">📞 Contacto</option>
                    </select>
                </div>
            </div>
        `;
        document.getElementById('acciones-container').innerHTML = "";
        document.getElementById('btn-cancelar').style.display = "none";
    }

    function switchTab(tab) {
        document.getElementById('body-manual').style.display = tab === 'manual' ? 'block' : 'none';
        document.getElementById('body-import').style.display = tab === 'import' ? 'block' : 'none';
        document.getElementById('body-test').style.display = tab === 'test' ? 'flex' : 'none';
        document.getElementById('body-huerfanas').style.display = tab === 'huerfanas' ? 'block' : 'none';
        document.getElementById('body-prompt').style.display = tab === 'prompt' ? 'block' : 'none';
        document.getElementById('body-auditoria').style.display = tab === 'auditoria' ? 'block' : 'none';
        
        const btnManual = document.getElementById('tab-manual');
        const btnImport = document.getElementById('tab-import');
        const btnTest = document.getElementById('tab-test');
        const btnHuerfanas = document.getElementById('tab-huerfanas');
        const btnPrompt = document.getElementById('tab-prompt');
        const btnAuditoria = document.getElementById('tab-auditoria');
        
        btnManual.classList.remove('active'); btnImport.classList.remove('active'); btnTest.classList.remove('active'); btnHuerfanas.classList.remove('active'); btnPrompt.classList.remove('active'); btnAuditoria.classList.remove('active');

        if (tab === 'manual') {
            btnManual.classList.add('active');
            document.getElementById('preview-container').style.display = 'none';
        } else if (tab === 'import') {
            btnImport.classList.add('active');
        } else if (tab === 'huerfanas') {
            btnHuerfanas.classList.add('active');
        } else if (tab === 'prompt') {
            btnPrompt.classList.add('active');
        } else if (tab === 'auditoria') {
            btnAuditoria.classList.add('active');
        } else {
            btnTest.classList.add('active');
            document.getElementById('test-chat-box').scrollTop = document.getElementById('test-chat-box').scrollHeight;
        }
    }

    function convertirHuerfana(id, categoria, pregunta) {
        switchTab('manual');
        document.getElementById('form-title').innerText = "Convertir Huérfana en Regla";
        document.getElementById('input-huerfana-id').value = id;
        
        const sel = document.getElementById('input-categoria-select');
        let found = false;
        for(let i=0; i<sel.options.length; i++) {
            if(sel.options[i].value === categoria) { sel.selectedIndex = i; found = true; break; }
        }
        if(!found && categoria && categoria !== 'Fuera de Tema') {
            sel.value = 'NEW';
            document.getElementById('input-categoria-nueva').value = categoria;
        } else if (!found) {
            sel.value = '';
        }
        checkNewCategory();
        
        document.getElementById('input-palabras').value = pregunta;
        document.getElementById('btn-cancelar').style.display = "block";
        window.scrollTo(0, 0);
        setTimeout(() => document.querySelector('textarea[name="respuestas[]"]').focus(), 100);
    }

    function previewImport() {
        const raw = document.getElementById('input-json-raw').value.trim();
        const errDiv = document.getElementById('preview-errors');
        const sucDiv = document.getElementById('preview-success');
        const btnConfirm = document.getElementById('btn-confirm-import');
        const previewContainer = document.getElementById('preview-container');
        
        errDiv.style.display = 'none'; sucDiv.style.display = 'none'; btnConfirm.style.display = 'none';
        previewContainer.style.display = 'block'; // Faltaba hacer visible el contenedor principal
        
        if(!raw) { errDiv.innerHTML = "El JSON está vacío."; errDiv.style.display = 'block'; return; }
        
        let parsed;
        try { parsed = JSON.parse(raw); } 
        catch(e) { errDiv.innerHTML = "Error de sintaxis JSON: " + e.message; errDiv.style.display = 'block'; return; }
        
        if(!Array.isArray(parsed)) { errDiv.innerHTML = "El JSON debe ser un arreglo [ ... ]"; errDiv.style.display = 'block'; return; }
        
        const allowedActions = ['ir_a_obras', 'ir_a_candidatos', 'ir_a_propuestas', 'ir_a_sumate', 'ir_a_contacto'];
        let errors = [];
        let validRules = [];
        
        let globalCat = document.getElementById('input-import-categoria-select').value;
        if (globalCat === 'NEW') globalCat = document.getElementById('input-import-categoria-nueva').value.trim();
        
        parsed.forEach((rule, idx) => {
            if (globalCat !== '') rule.categoria = globalCat;
            
            let ruleName = rule.categoria || `Regla #${idx + 1}`;
            if(!rule.categoria) errors.push(`[Regla #${idx + 1}]: Falta 'categoria'. Selecciónala en el menú de arriba o inclúyela en el JSON.`);
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

    // Animación visual del Switch de IA
    document.getElementById('switchIA').addEventListener('change', function() {
        const label = document.getElementById('labelSwitchIA');
        if(this.checked) {
            label.textContent = 'IA ACTIVADA';
            label.style.color = '#28a745';
        } else {
            label.textContent = 'IA APAGADA';
            label.style.color = '#dc3545';
        }
    });

    // Contador visual de caracteres para el Prompt Maestro
    const promptArea = document.getElementById('promptMaestroArea');
    const promptCounter = document.getElementById('promptCounter');
    if (promptArea && promptCounter) {
        const updateCounter = () => {
            promptCounter.textContent = promptArea.value.length + ' / 100000 caracteres';
        };
        promptArea.addEventListener('input', updateCounter);
        updateCounter(); // Inicializar estado
    }
</script>
</body>
</html>