<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config.php';
require_login();

$mensaje = '';

// Obtenemos los textos de la IA para cruzarlos
$conocimiento_ia = [];
try {
    $db = get_db_connection();
    $stmt = $db->query("SELECT titulo, contenido FROM panel_ia_conocimiento WHERE fuente = 'Google Sheets - Obras'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $conocimiento_ia[trim($row['titulo'])] = $row['contenido'];
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/vendor/autoload.php';
    $rutaCredenciales = __DIR__ . '/data/credenciales.json';
    $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

    // --- INICIO: BOTÓN MÁGICO DE LIMPIEZA DE MONTOS (SOLO ADMIN) ---
    if (isset($_POST['action']) && $_POST['action'] === 'limpiar_montos') {
        header('Content-Type: application/json');
        if (!is_admin()) {
            echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado. Solo administradores.']);
            exit;
        }
        try {
            $client = new \Google_Client();
            $client->setApplicationName('Panel de Obras Fuerza Tacna');
            $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
            $client->setAuthConfig($rutaCredenciales);
            $service = new \Google_Service_Sheets($client);
            
            // 1. Obtener todas las pestañas activas
            $responseSeg = $service->spreadsheets_values->get($spreadsheetId, 'SEGMENTOS!A:D');
            $rowsSeg = $responseSeg->getValues() ?? [];
            $dataUpdates = [];
            $obrasLimpiadas = 0;

            // 2. Escanear y corregir cada hoja
            foreach ($rowsSeg as $i => $row) {
                if ($i === 0) continue;
                if (($row[2] ?? '') && strtoupper($row[3] ?? '') === 'SI') {
                    $seg = $row[2];
                    $response = $service->spreadsheets_values->get($spreadsheetId, $seg . '!A2:J');
                    $rows = $response->getValues() ?? [];
                    
                    foreach ($rows as $j => $obraRow) {
                        $montoRaw = $obraRow[2] ?? '';
                        if (trim($montoRaw) === '') continue;
                        
                        $str = strtolower(trim($montoRaw));
                        $has_millon = preg_match('/mill[oó]n/i', $str);
                        $has_mil = preg_match('/mil\b/i', $str) && !$has_millon;
                        $is_clean = is_numeric($montoRaw) && strpos($montoRaw, ' ') === false && strpos($montoRaw, ',') === false && stripos($montoRaw, 's') === false;
                        
                        // Si el monto no está 100% puro (tiene texto, comas o símbolos), lo procesamos
                        if (!$is_clean || $has_millon || $has_mil) {
                            $numStr = preg_replace('/[^\d.,]/', '', $str);
                            $numStr = str_replace(',', '', $numStr); // Asumimos que la coma es de miles (formato PE/US)
                            $val = (float)$numStr;
                            if ($has_millon) $val *= 1000000;
                            elseif ($has_mil) $val *= 1000;
                            
                            if ($val > 0) {
                                $dataUpdates[] = new \Google_Service_Sheets_ValueRange(['range' => $seg . '!C' . ($j + 2), 'values' => [[$val]]]);
                                $obrasLimpiadas++;
                            }
                        }
                    }
                }
            }
            // 3. Inyectar todo en un solo golpe a Excel
            if (count($dataUpdates) > 0) {
                $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateValuesRequest(['valueInputOption' => 'RAW', 'data' => $dataUpdates]);
                $service->spreadsheets_values->batchUpdate($spreadsheetId, $batchUpdateRequest);
                log_action('monto_limpieza', "Se limpiaron y estandarizaron $obrasLimpiadas montos en Excel.");
            }
            echo json_encode(['ok' => true, 'mensaje' => "¡Mantenimiento exitoso! Se han limpiado y estandarizado $obrasLimpiadas montos en todas tus pestañas."]);
        } catch (Throwable $e) { echo json_encode(['ok' => false, 'mensaje' => "Error API: " . $e->getMessage()]); }
        exit;
    }
    // --- FIN: BOTÓN MÁGICO ---

    try {
        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig($rutaCredenciales);
        $service = new \Google_Service_Sheets($client);

        $segmento = $_POST['segmento'] ?? '';
        $fila     = (int)($_POST['fila'] ?? 0);
        $nombre   = $_POST['nombre'] ?? '';
        $estado   = $_POST['estado'] ?? '';
        $monto    = $_POST['monto'] ?? '';
        $distrito = $_POST['distrito'] ?? '';
        $provincia= $_POST['provincia'] ?? '';
        
        // Convertimos a float para que Google Sheets lo interprete como número
        // y la API pública no lo oculte al mezclar tipos de datos.
        $x        = (float) str_replace(',', '.', $_POST['x'] ?? '0');
        $y        = (float) str_replace(',', '.', $_POST['y'] ?? '0');
        $carpeta  = trim($_POST['carpeta'] ?? '');
        $descripcion = $_POST['descripcion'] ?? '';

        if ($carpeta === '' || $carpeta === '-') {
            $carpeta = slugify($nombre);
        }

        if ($fila >= 2 && $segmento !== '') {
            $values = [
                [$nombre, $estado, $monto, $x, $y, $provincia, $distrito, $carpeta, $descripcion]
            ];
            $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
            $params = ['valueInputOption' => 'RAW'];
            
            // Actualizamos solo esa fila específica (ej. EDUCACION!A5:I5)
            $rango = $segmento . '!A' . $fila . ':I' . $fila;

            $service->spreadsheets_values->update($spreadsheetId, $rango, $body, $params);
            log_action('obra_editar', "Editó obra: $nombre en $segmento (Fila $fila)");
            
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'mensaje' => '¡Textos y mapa actualizados con éxito en tu Excel!', 'carpeta' => $carpeta]);
                exit;
            }
            $mensaje = '<div class="msg-success">¡Obra actualizada con éxito en Google Sheets! Puedes seguir editando otras o ir a la pestaña de Fotos.</div>';
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'mensaje' => 'Error: Fila o segmento no válido.']);
                exit;
            }
            $mensaje = '<div class="msg-error">Error: Fila o segmento no válido.</div>';
        }
    } catch (Throwable $e) {
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        $mensaje = '<div class="msg-error">Error: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Obra - Panel Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; padding-bottom: 40px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .app-main { margin-top: 72px; display: flex; justify-content: center; padding: 20px; }
        .card { width: 100%; max-width: 700px; background: #020617; border-radius: 18px; padding: 24px 28px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7), 0 0 0 1px rgba(148, 163, 184, 0.15); border: 1px solid rgba(148, 163, 184, 0.15); }
        h1 { margin-top: 0; font-size: 22px; color: #f9fafb; margin-bottom: 20px; }
        label { font-size: 13px; color: #e5e7eb; display: block; margin-top: 15px; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #020617; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus, select:focus, textarea:focus { border-color: #2563eb; }
        .btn-submit { margin-top: 25px; width: 100%; padding: 12px; background: #2563eb; color: #f9fafb; border: none; font-weight: 600; font-size: 14px; border-radius: 999px; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #1d4ed8; }
        .msg-success { background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #059669; }
        .msg-error { background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #dc2626; }
        .row { display: flex; gap: 15px; }
        .row > div { flex: 1; }
        
        .btn-mapa { background: transparent; border: 1px solid #3b82f6; color: #60a5fa; padding: 8px 12px; border-radius: 8px; font-size: 13px; cursor: pointer; transition: all 0.2s; margin-top: 10px; width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-mapa:hover { background: rgba(59, 130, 246, 0.1); color: #93c5fd; }
        
        .btn-zoom { background: #1e293b; color: #f9fafb; border: 1px solid #334155; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        .btn-zoom:hover { background: #334155; }
        
        .map-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(2, 6, 23, 0.95); z-index: 1000; display: none; flex-direction: column; backdrop-filter: blur(5px); }
        .map-modal.is-open { display: flex; }
        .map-modal-header { padding: 15px 24px; background: #0f172a; border-bottom: 1px solid #1f2937; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.3); z-index: 10; }
        .map-modal-header h3 { margin: 0; font-size: 16px; color: #f9fafb; font-weight: 600; }
        .btn-close-map { background: #ef4444; color: white; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-close-map:hover { background: #dc2626; }
        
        .map-modal-body { flex: 1; overflow: auto; cursor: crosshair; position: relative; padding: 20px; text-align: center; }
        .map-modal-body::-webkit-scrollbar { width: 10px; height: 10px; }
        .map-modal-body::-webkit-scrollbar-track { background: #0f172a; }
        .map-modal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 5px; }
        
        .map-modal-body img { max-width: 100%; max-height: 80vh; width: auto; height: auto; display: block; border: 2px solid #334155; box-shadow: 0 0 30px rgba(0,0,0,0.5); border-radius: 8px; background: #fff; }
        
        #pinesContainer { pointer-events: none; position: absolute; top: 0; left: 0; }
        .pin-existente { position: absolute; width: 12px; height: 12px; background: #ef4444; border: 2px solid #fff; border-radius: 50%; transform: translate(-50%, -50%); box-shadow: 0 0 5px rgba(0,0,0,0.8); }
        .pin-label { position: absolute; left: 12px; top: -8px; background: rgba(15, 23, 42, 0.9); color: #f9fafb; font-size: 10px; padding: 2px 6px; border-radius: 4px; white-space: nowrap; font-family: system-ui; }
        
        /* Estilos añadidos para la Galería de Fotos */
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-top: 15px; }
        .foto-card { border-radius: 8px; border: 1px solid #1f2937; padding: 6px; background: #0f172a; position: relative; display: flex; flex-direction: column; gap: 6px; cursor: grab; transition: transform 0.2s; }
        .foto-card:active { cursor: grabbing; }
        .foto-card.drag-over { transform: scale(1.05); box-shadow: 0 0 0 2px #3b82f6; z-index: 10; }
        .foto-card img { width: 100%; height: 100px; object-fit: cover; border-radius: 6px; display: block; }
        .foto-meta { font-size: 11px; color: #9ca3af; display: flex; justify-content: space-between; align-items: center; }
        .foto-actions { display: flex; gap: 4px; }
        .foto-actions button { flex: 1; padding: 4px 0; border-radius: 4px; border: none; font-size: 10px; cursor: pointer; font-weight: bold; }
        .btn-principal { background: transparent; color: #60a5fa; border: 1px solid #3b82f6; }
        .btn-eliminar { background: #ef4444; color: #fff; }
        .badge-principal { position: absolute; top: 6px; left: 6px; background: #10b981; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 4px; font-weight: bold; }

        /* Estilos visuales para obras ocultas en edición */
        .form-oculto input, .form-oculto select, .form-oculto textarea {
            border-color: rgba(239, 68, 68, 0.5) !important;
            background-color: rgba(239, 68, 68, 0.05) !important;
            color: #fca5a5 !important;
        }
        .form-oculto label {
            color: #fca5a5 !important;
        }
        .alerta-oculto {
            display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5;
            padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; font-size: 13px;
        }
        .form-oculto .alerta-oculto { display: block; }
        
        /* Estilos Pestañas Cerebro IA */
        .tabs-container { display: block; background: #0f172a; border: 1px solid #1f2937; border-radius: 8px; margin-top: 10px; }
        .tabs-header { display: flex; background: #020617; border-bottom: 1px solid #1f2937; overflow-x: auto; }
        .tabs-header::-webkit-scrollbar { height: 4px; }
        .tabs-header::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .tab-btn { flex: 0 0 auto; display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: transparent; border: none; border-right: 1px solid #1f2937; color: #94a3b8; font-size: 13px; font-weight: bold; cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; max-width: 180px; }
        .tab-btn span.ctx-titulo-display { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; pointer-events: none; }
        .tab-btn:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; background: rgba(59,130,246,0.1); }
        .tab-close { background: transparent; border: none; color: #ef4444; font-size: 16px; line-height: 1; cursor: pointer; padding: 0 4px; border-radius: 4px; opacity: 0.7; }
        .tab-close:hover { opacity: 1; background: rgba(239,68,68,0.2); }
        .btn-add-tab { flex: 0 0 auto; padding: 10px 15px; background: transparent; border: none; color: #10b981; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-add-tab:hover { background: rgba(16,185,129,0.1); }
        .tabs-content { display: block; position: relative; }
        .tab-pane { display: none; padding: 0; box-sizing: border-box; }
        .tab-pane.active { display: block; }
        .tab-pane textarea { display: block; width: 100%; min-height: 350px; background: transparent; border: none; color: #e2e8f0; font-size: 14px; resize: none; overflow-y: hidden; outline: none; line-height: 1.6; font-family: system-ui; padding: 15px; box-sizing: border-box; transition: none; }

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
        <a href="panel-juegos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'panel-juegos.php' ? 'active' : '' ?>">🎮 Panel de Juegos</a>
        <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">📍 Gestor Mapa</a>
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
        <div class="card">
            <h1>Editar Obra Existente</h1>
            <?= $mensaje ?>
        
            <div class="row" style="margin-bottom: 20px;">
                <div>
                    <label>Segmento:</label>
                    <select id="selectSegmento" disabled>
                        <option value="">Cargando datos de Google Sheets...</option>
                    </select>
                </div>
                <div>
                    <label>Obra a Editar:</label>
                    <select id="selectObra" disabled>
                        <option value="">Primero elige segmento...</option>
                    </select>
                </div>
            </div>

            <form action="editar_obra.php" method="POST" id="formEditar" style="display:none; padding-top: 15px; border-top: 1px solid #1f2937;">
                <!-- Campos ocultos para mantener la integridad de la fila y la carpeta de fotos -->
                <input type="hidden" name="segmento" id="formSegmento">
                <input type="hidden" name="fila" id="formFila">
                <input type="hidden" name="carpeta" id="formCarpeta">

                <div class="alerta-oculto">⚠️ ESTÁS EDITANDO UNA OBRA OCULTA.<br><span style="font-weight: normal;">Los cambios se guardarán correctamente, pero seguirá sin verse en el mapa público.</span></div>

                <label>Nombre de la Obra:</label>
                <input type="text" name="nombre" id="inputNombre" required>

                <label>Estado:</label>
                <select name="estado" id="inputEstado" required>
                    <option value="Entregado">Entregado</option>
                    <option value="En construcción">En construcción</option>
                    <option value="Paralizado">Paralizado</option>
                    <option value="Buena Pro">Buena Pro</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="En estudios">En estudios</option>
                    <option value="Oculto" style="color: #ef4444; font-weight: bold;">🚫 Oculto (No mostrar en mapa)</option>
                </select>

                <div class="row">
                    <div style="flex: 2;">
                        <label>Monto Referencial:</label>
                        <input type="number" step="any" id="inputMontoBase" placeholder="Ej. 1.5 o 500">
                    </div>
                    <div style="flex: 1;">
                        <label>Magnitud:</label>
                        <select id="inputMontoMagnitud">
                            <option value="1000000" selected>Millones</option>
                            <option value="1000">Mil</option>
                            <option value="1">Soles (S/)</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="monto" id="hidden_monto">

                <div class="row">
                    <div>
                        <label>Provincia:</label>
                        <select name="provincia" id="inputProvincia" required></select>
                    </div>
                    <div>
                        <label>Distrito:</label>
                        <select name="distrito" id="inputDistrito" required disabled><option value="">Primero elige provincia</option></select>
                    </div>
                </div>

                <div class="row">
                    <div><label>Coordenada X (Longitud):</label><input type="text" name="x" id="inputX"></div>
                    <div><label>Coordenada Y (Latitud):</label><input type="text" name="y" id="inputY"></div>
                </div>
                
                <label>Descripción de la Obra:</label>
                <textarea name="descripcion" id="inputDesc" rows="4" style="resize: vertical;"></textarea>

                <button type="button" id="btnAbrirMapa" class="btn-mapa">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    Abrir Mapa para Reubicar el Pin
                </button>
            </form>
            
            <!-- NUEVO: SECCIÓN DE GESTIÓN DE FOTOS INTEGRADA -->
            <div id="fotosSection" style="display:none; margin-top: 30px; border-top: 1px solid #1f2937; padding-top: 20px;">
                <h2 style="font-size: 18px; margin-top:0; color: #f9fafb; display:flex; justify-content:space-between; align-items:center;">
                    📸 Gestión de Fotos
                    <div style="display: flex; gap: 8px;">
                        <button type="button" id="btnEliminarTodo" disabled style="background:#ef4444; padding: 6px 12px; border-radius: 6px; border: none; color: white; cursor: pointer; font-size: 11px; font-weight: bold;">🗑 Eliminar todas</button>
                        <button type="button" id="btnDescargarZip" disabled style="background:#10b981; padding: 6px 12px; border-radius: 6px; border: none; color: white; cursor: pointer; font-size: 11px; font-weight: bold;">⬇ Descargar ZIP</button>
                    </div>
                </h2>
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #93c5fd; padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-top: 10px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">💡 <strong>Tip:</strong> Puedes cambiar el orden de las imágenes haciendo clic y arrastrándolas a una nueva posición. La primera foto siempre será la principal del mapa.</div>
                <div id="galeriaEmpty" style="font-size: 13px; color: #9ca3af; margin-top: 10px; display:none;">Esta obra aún no tiene fotos.</div>
                <div id="galeria" class="galeria"></div>

                <form id="uploadForm" enctype="multipart/form-data" style="margin-top:20px; background: #0f172a; padding: 15px; border-radius: 10px; border: 1px dashed #334155;">
                    <label style="margin-top:0;">Agregar nuevas fotos (máx. 6 por obra)</label>
                    <input type="file" id="files" name="fotos[]" accept="image/*" multiple>
                    <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">Formatos soportados: JPG, PNG, WEBP. Arrastra las fotos arriba para reordenarlas.</div>
                    <div id="previewContainerUpload" class="galeria"></div>
                    <div id="progressContainer" style="display:none; width: 100%; background: #1e293b; border-radius: 999px; height: 8px; margin-top: 10px; overflow: hidden;"><div id="progressBar" style="height: 100%; background: #3b82f6; width: 0%; transition: width 0.2s;"></div></div>
                    <button type="submit" id="btnSubirFotos" disabled class="btn-submit" style="margin-top: 15px; padding: 10px;">☁️ Subir imágenes seleccionadas</button>
                    <div id="status" style="font-size: 12px; margin-top: 8px; color: #93c5fd;"></div>
                </form>
            </div>
            
            <!-- NUEVO: SECCIÓN CEREBRO IA INTEGRADA -->
            <div id="iaSection" style="display:none; margin-top: 30px; border-top: 1px solid #1f2937; padding-top: 20px;">
                <h2 style="font-size: 18px; margin-top:0; color: #f9fafb; display:flex; justify-content:space-between; align-items:center;">
                    🧠 Cerebro IA (Contexto)
                    <span id="aiStatusBadge" style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;">🔴 Nuevo para IA</span>
                </h2>
                <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); color: #c4b5fd; padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-top: 10px; margin-bottom: 10px;">💡 <strong>Tip:</strong> Puedes agregar información extra (noticias, historia, detalles) que no está en el Excel para que la IA la aprenda y pueda responder mejor.</div>
                
                <div class="tabs-container">
                    <div class="tabs-header">
                        <div id="tabsHeaderList" style="display: flex; flex: 0 0 auto;"></div>
                        <button type="button" class="btn-add-tab" onclick="agregarContexto(null, '', true)" title="Añadir nueva pestaña">➕ Nueva Pestaña</button>
                        <button type="button" class="btn-add-tab" onclick="abrirExtractorUrl()" title="Extraer texto de un enlace web" style="color: #3b82f6;">🌐 Extraer Link</button>
                    </div>
                    <div class="tabs-content" id="tabsContent"></div>
                </div>
                
                <button type="button" id="btnSaveIA" class="btn-submit" style="background: #8b5cf6; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3); margin-top: 15px;">🧠 Actualizar Conocimiento en la IA</button>
            </div>

            <div id="globalSaveSection" style="display:none; margin-top: 30px; border-top: 1px solid #1f2937; padding-top: 20px;">
                <div id="formMsg"></div>
                <button type="button" id="btnGuardarGlobal" class="btn-submit" style="background: #10b981; font-size: 16px; padding: 16px; margin-top: 10px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">💾 Guardar Todos los Cambios</button>
            </div>
            
            <?php if (is_admin()): ?>
            <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); padding: 15px; border-radius: 10px; margin-top: 30px;">
                <h3 style="margin-top: 0; color: #fcd34d; font-size: 15px; display: flex; align-items: center; gap: 8px;">🛠️ Herramienta de Administrador</h3>
                <p style="font-size: 12px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">Limpia todos los montos escritos como texto en tu Excel (ej. <em>"15 Millones"</em> o <em>"S/ 200,000"</em>) y los convierte en números puros (ej. <em>15000000</em>) para que tu base de datos esté estandarizada. Esta acción escaneará <strong>todas tus pestañas</strong>.</p>
                <button type="button" id="btnLimpiarMontos" class="btn-submit" style="background: #d97706; padding: 10px 15px; font-size: 12px; width: auto; margin-top: 0; display: inline-block;">✨ Estandarizar todos los Montos en Excel</button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- VENTANA EMERGENTE DEL MAPA -->
    <div id="modalMapa" class="map-modal">
        <div class="map-modal-header">
            <h3>🎯 Haz clic en el nuevo lugar para esta obra</h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" id="btnZoomOut" class="btn-zoom">-</button>
                <span id="zoomNivel" style="color: #94a3b8; font-size: 14px; min-width: 45px; text-align: center; font-weight: bold;">100%</span>
                <button type="button" id="btnZoomIn" class="btn-zoom">+</button>
                <button type="button" id="btnCerrarMapa" class="btn-close-map" style="margin-left: 10px;">Cerrar</button>
            </div>
        </div>
        <div class="map-modal-body">
            <div id="mapWrapper" style="position: relative; display: inline-block;">
                <img id="imgMapaPuntos" src="../universoobras/IMG/mapa-base.png" alt="Mapa Base">
                <div id="pinesContainer"></div>
                <div id="loadingPines" style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.8); color: #ffc300; padding: 5px 10px; border-radius: 5px; font-size: 12px; display: none;">Cargando obras existentes...</div>
            </div>
        </div>
    </div>

    <script>
        const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq`;
        let SEGMENTOS = [];
        const DISTRITOS_POR_PROVINCIA = {
            "Tacna": ["Tacna", "Alto de la Alianza", "Calana", "Ciudad Nueva", "Coronel Gregorio Albarracín Lanchipa", "Inclán", "Pachía", "Palca", "Pocollay", "Sama", "La Yarada-Los Palos"],
            "Tarata": ["Tarata", "Chucatamani", "Estique", "Estique-Pampa", "Sitajara", "Susapaya", "Tarucachi", "Ticaco"],
            "Candarave": ["Candarave", "Cairani", "Camilaca", "Curibaya", "Huanuara", "Quilahuani"],
            "Jorge Basadre": ["Locumba", "Ilabaya", "Ite"]
        };

        // Conocimiento de IA local
        const CEREBRO_IA = <?php echo json_encode($conocimiento_ia ?? []); ?>;
        let tabCounter = 0;

        function formatMontoFriendly(val) {
            let str = String(val).trim();
            if (/[a-zA-Z]/.test(str) && !str.toLowerCase().startsWith('s/')) return str; 
            let num = parseFloat(str.replace(/[^\d.-]/g, '')) || 0;
            if (num === 0) return 'S/ 0';
            if (num >= 1000000) { let m = num / 1000000; return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Millones'; } 
            else if (num >= 1000) { let m = num / 1000; return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Mil'; }
            return 'S/ ' + num.toLocaleString('en-US');
        }

        function autoExpandTextarea(el) {
            // Evitar saltos de scroll en la pantalla al recalcular
            const scrollPos = window.scrollY || document.documentElement.scrollTop;
            el.style.height = '0px'; // Forzar recalculo hacia abajo
            el.style.height = (el.scrollHeight) + 'px';
            window.scrollTo(0, scrollPos);
        }

        function agregarContexto(titulo = "", texto = "", promptUser = false, url = "") {
            if (promptUser) {
                titulo = prompt("Ingresa un nombre para esta pestaña (Ej. 'Noticia', 'Expediente'):");
                if (titulo === null || titulo.trim() === "") return;
            }
            if (!titulo) titulo = "General";

            tabCounter++;
            const tabId = 'tab_' + tabCounter;

            const tabsHeaderList = document.getElementById('tabsHeaderList');
            const tabsContent = document.getElementById('tabsContent');

            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            const tabBtn = document.createElement('button');
            tabBtn.type = 'button';
            tabBtn.className = 'tab-btn active';
            tabBtn.dataset.target = tabId;
            tabBtn.innerHTML = `<span class="ctx-titulo-display">${titulo}</span><input type="hidden" class="ctx-titulo" value="${titulo}"><div class="tab-close" onclick="eliminarContexto(event, '${tabId}')" title="Cerrar pestaña">&times;</div>`;
            tabBtn.onclick = () => activarTab(tabId);
            tabsHeaderList.appendChild(tabBtn);

            const tabPane = document.createElement('div');
            tabPane.className = 'tab-pane active';
            tabPane.id = tabId;
            tabPane.innerHTML = `
                <input type="text" class="ctx-url" placeholder="Enlace web de referencia (Opcional)" value="${url}" style="width: 100%; margin-bottom: 10px; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid #1f2937; color: #93c5fd; border-radius: 6px; font-size: 12px; outline: none; box-sizing: border-box;">
                <textarea class="ctx-texto" placeholder="Pega aquí el contenido para '${titulo}'..." oninput="autoExpandTextarea(this)">${texto}</textarea>
            `;
            tabsContent.appendChild(tabPane);
            tabsHeaderList.parentElement.scrollLeft = tabsHeaderList.parentElement.scrollWidth;

            const textarea = tabPane.querySelector('.ctx-texto');
            if (textarea) {
                setTimeout(() => { autoExpandTextarea(textarea); }, 50);
            }
        }

        function activarTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.target === tabId));
            document.querySelectorAll('.tab-pane').forEach(pane => {
                const isActive = pane.id === tabId;
                pane.classList.toggle('active', isActive);
                if (isActive) {
                    const textarea = pane.querySelector('.ctx-texto');
                    if (textarea) { setTimeout(() => { autoExpandTextarea(textarea); }, 50); }
                }
            });
        }

        function eliminarContexto(event, tabId) {
            event.stopPropagation();
            if (!confirm("¿Seguro que deseas eliminar esta pestaña?")) return;
            const btn = document.querySelector(`.tab-btn[data-target="${tabId}"]`);
            const pane = document.getElementById(tabId);
            const wasActive = btn.classList.contains('active');
            btn.remove(); pane.remove();
            if (wasActive) {
                const remainingTabs = document.querySelectorAll('.tab-btn');
                if (remainingTabs.length > 0) activarTab(remainingTabs[remainingTabs.length - 1].dataset.target);
            }
        }

        async function abrirExtractorUrl() {
            const url = prompt("Pega aquí el enlace de la noticia o página web:");
            if (!url) return;
            
            const btn = document.querySelector('.btn-add-tab[onclick="abrirExtractorUrl()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = "⏳ Extrayendo...";
            btn.disabled = true;
            
            const fd = new FormData(); fd.append('action', 'extract_url_ajax'); fd.append('url', url);
            
            try {
                const resp = await fetch('ia_conocimiento.php', {method: 'POST', body: fd});
                const data = await resp.json();
                if (data.ok) {
                    agregarContexto(data.titulo, data.texto, false, url);
                } else { alert("Error: " + data.error); }
            } catch(e) {
                alert("Error de red al extraer el link."); 
            } finally { btn.innerHTML = originalText; btn.disabled = false; }
        }

        function cargarDatosIA(obra) {
            const tabsHeaderList = document.getElementById('tabsHeaderList');
            const tabsContent = document.getElementById('tabsContent');
            tabsHeaderList.innerHTML = '';
            tabsContent.innerHTML = '';
            const badge = document.getElementById('aiStatusBadge');
            
            const tituloClave = "Obra: " + obra.nombre;
            let textoIA = CEREBRO_IA[tituloClave];

            if (textoIA) {
                const separador = "Contexto adicional:";
                if (textoIA.includes(separador)) {
                    textoIA = textoIA.substring(textoIA.indexOf(separador) + separador.length).trim();
                    let tabAdded = false;

                    if (textoIA !== "") {
                        if (textoIA.includes('--- ')) {
                            const bloques = textoIA.split(/--- (.*?) ---/g);
                            for (let i = 1; i < bloques.length; i += 2) {
                                const t = bloques[i].trim();
                                let c = (bloques[i+1] || "").trim();
                                
                                let foundUrl = '';
                                const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                                if (urlMatch) {
                                    foundUrl = urlMatch[1];
                                    c = c.replace(urlMatch[0], '').trim();
                                }
                                
                                let normC = c.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                let normExcel = (obra.desc || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                if (normC !== normExcel && normC !== '') { agregarContexto(t, c, false, foundUrl); tabAdded = true; }
                            }
                        } else {
                            let c = textoIA;
                            let foundUrl = '';
                            const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                            if (urlMatch) {
                                foundUrl = urlMatch[1];
                                c = c.replace(urlMatch[0], '').trim();
                            }
                            let normC = c.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            let normExcel = (obra.desc || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            if (normC !== normExcel && normC !== '') { agregarContexto("Contexto General", c, false, foundUrl); tabAdded = true; }
                        }
                    }
                    if (!tabAdded) agregarContexto("Principal");
                } else {
                    agregarContexto("Principal");
                }
                badge.textContent = "🟢 Ya en Cerebro";
                badge.style.color = "#10b981";
                badge.style.background = "rgba(16, 185, 129, 0.1)";
                badge.style.borderColor = "#10b981";
            } else {
                agregarContexto("Principal");
                badge.textContent = "🔴 Nuevo para IA";
                badge.style.color = "#ef4444";
                badge.style.background = "rgba(239, 68, 68, 0.1)";
                badge.style.borderColor = "#ef4444";
            }
        }

        const obrasPorSegmento = {};

        function parseGviz(text) {
            const m = text.match(/setResponse\(([\s\S]+)\);?/);
            if (!m) return { table: { rows: [] } };
            try {
                const parsed = JSON.parse(m[1]);
                if (parsed.status === 'error') {
                    console.error("Error de Google Sheets:", parsed.errors);
                    return { table: { rows: [] } };
                }
                return parsed;
            } catch (e) {
                return { table: { rows: [] } };
            }
        }

        async function cargarDataInicial() {
            const segmentoEl = document.getElementById('selectSegmento');
            
            try {
                let respSeg = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&sheet=SEGMENTOS&range=A:D&headers=1`);
                let textSeg = await respSeg.text();
                
                if (textSeg.includes('"status":"error"')) {
                    respSeg = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&sheet=Segmentos&range=A:D&headers=1`);
                    textSeg = await respSeg.text();
                }
                
                const jsonSeg = parseGviz(textSeg);
                SEGMENTOS = (jsonSeg.table.rows || [])
                    .map(r => ({ id: r.c[0]?.v, nombre: r.c[1]?.v, key: r.c[2]?.v, activo: String(r.c[3]?.v||'').toUpperCase() }))
                    .filter(s => s.key && (s.activo === 'SI' || s.activo === '1' || s.activo === 'TRUE'));

            segmentoEl.innerHTML = '<option value="">Selecciona segmento...</option>';
            SEGMENTOS.forEach(seg => {
                const opt = document.createElement("option");
                opt.value = seg.key;
                opt.textContent = seg.nombre || seg.key;
                segmentoEl.appendChild(opt);
            });
            segmentoEl.disabled = false;
            } catch(e) { 
                console.error("Error al cargar SEGMENTOS", e); 
                segmentoEl.innerHTML = '<option value="">Error de conexión</option>';
            }
        }

        document.getElementById('selectSegmento').addEventListener('change', async function() {
            const obraEl = document.getElementById('selectObra');
            const segmento = this.value;
            
            document.getElementById('formEditar').style.display = 'none';
            document.getElementById('fotosSection').style.display = 'none';
            document.getElementById('iaSection').style.display = 'none';
            document.getElementById('globalSaveSection').style.display = 'none';

            if (!segmento) {
                obraEl.innerHTML = '<option value="">Primero elige segmento...</option>';
                obraEl.disabled = true;
                return;
            }

            // Si ya lo tenemos en caché, lo mostramos directo
            if (obrasPorSegmento[segmento]) {
                poblarSelectObras(segmento, obraEl);
                return;
            }

            // Descargamos de Google Sheets
            obraEl.innerHTML = '<option value="">Descargando obras del Excel...</option>';
            obraEl.disabled = true;

            try {
                const url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${new Date().getTime()}&sheet=${encodeURIComponent(segmento)}&range=A:J&headers=1`;
                const resp = await fetch(url);
                const txt = await resp.text();
                const json = parseGviz(txt);
                
                const rows = json.table?.rows || [];
                obrasPorSegmento[segmento] = rows.map(r => {
                    if(!r || !r.c) return {};
                    return {
                        nombre:    r.c[0]?.v || "",
                        estado:    r.c[1]?.v || "",
                        monto:     r.c[2]?.v || "",
                        x:         r.c[3] ? (r.c[3].v !== null && r.c[3].v !== undefined ? r.c[3].v : (r.c[3].f || "")) : "",
                        y:         r.c[4] ? (r.c[4].v !== null && r.c[4].v !== undefined ? r.c[4].v : (r.c[4].f || "")) : "",
                        provincia: r.c[5]?.v || "",
                        distrito:  r.c[6]?.v || "",
                        carpeta:   r.c[7]?.v || "",
                        desc:      r.c[8]?.v || r.c[8]?.f || ""
                    };
                });
                poblarSelectObras(segmento, obraEl);
            } catch (e) { 
                console.error("Error al cargar " + segmento, e); 
                obraEl.innerHTML = '<option value="">Error al cargar obras</option>';
            }
        });
        
        function poblarSelectObras(segmento, obraEl) {
            const lista = obrasPorSegmento[segmento] || [];
            obraEl.innerHTML = '<option value="">Selecciona obra a editar...</option>';
            
            lista.forEach((obra, idx) => {
                if (!obra.nombre) return; 
                const opt = document.createElement("option");
                opt.value = idx; 
                opt.textContent = obra.nombre;
                obraEl.appendChild(opt);
            });
            obraEl.disabled = false;
        }

        document.getElementById('selectObra').addEventListener('change', function() {
            const segmento = document.getElementById('selectSegmento').value;
            const idx = this.value;
            const formEditar = document.getElementById('formEditar');
            
            if (idx === "") { 
                formEditar.style.display = 'none'; 
                document.getElementById('fotosSection').style.display = 'none';
                document.getElementById('iaSection').style.display = 'none';
                document.getElementById('globalSaveSection').style.display = 'none';
                return; 
            }

            const item = obrasPorSegmento[segmento][idx];
            
            // Lógica visual para indicar si la obra está oculta
            if (item.estado && item.estado.includes('Oculto')) {
                formEditar.classList.add('form-oculto');
            } else {
                formEditar.classList.remove('form-oculto');
            }

            document.getElementById('formSegmento').value = segmento;
            document.getElementById('formFila').value = parseInt(idx) + 2; 
            document.getElementById('formCarpeta').value = item.carpeta;

            document.getElementById('inputNombre').value = item.nombre;
            document.getElementById('inputDesc').value = item.desc || '';
            
            const estadoSel = document.getElementById('inputEstado');
            let found = false;
            for(let i=0; i<estadoSel.options.length; i++) {
                if(estadoSel.options[i].value === item.estado) { estadoSel.selectedIndex = i; found = true; break; }
            }
            if(!found && item.estado) {
                const opt = document.createElement('option');
                opt.value = item.estado; opt.textContent = item.estado;
                estadoSel.appendChild(opt); estadoSel.value = item.estado;
            }

            // Traductor inteligente para obras antiguas o nuevas
            let rawStr = String(item.monto || '');
            let numMonto = parseFloat(rawStr.replace(/[^\d.-]/g, '')) || 0;
            let mag = 1;
            
            if (/mill[oó]n/i.test(rawStr)) {
                mag = 1000000;
            } else if (/mil/i.test(rawStr) && !/mill[oó]n/i.test(rawStr)) {
                mag = 1000;
            } else if (numMonto > 0) {
                if (numMonto >= 1000000 && numMonto % 100000 === 0) {
                    mag = 1000000; numMonto = numMonto / 1000000;
                } else if (numMonto >= 1000 && numMonto % 100 === 0) {
                    mag = 1000; numMonto = numMonto / 1000;
                }
            }
            document.getElementById('inputMontoBase').value = numMonto || '';
            document.getElementById('inputMontoMagnitud').value = mag;
            document.getElementById('hidden_monto').value = item.monto;
            
            const provinciaSel = document.getElementById('inputProvincia');
            const distritoSel = document.getElementById('inputDistrito');

            provinciaSel.value = item.provincia || "Tacna";
            provinciaSel.dispatchEvent(new Event('change'));

            setTimeout(() => {
                distritoSel.value = item.distrito;
                if (distritoSel.value !== item.distrito && item.distrito) {
                    const tempOption = document.createElement('option');
                    tempOption.value = item.distrito; tempOption.textContent = item.distrito + " (Dato antiguo)";
                    distritoSel.appendChild(tempOption);
                    distritoSel.value = item.distrito;
                }
            }, 50);

            document.getElementById('inputX').value = item.x ? String(item.x).replace(',', '.') : '';
            document.getElementById('inputY').value = item.y ? String(item.y).replace(',', '.') : '';

            formEditar.style.display = 'block';
            document.getElementById('fotosSection').style.display = 'block';
            document.getElementById('iaSection').style.display = 'block';
            document.getElementById('globalSaveSection').style.display = 'block';
            cargarFotosObra(); // Llamada automática a la galería integrada
            
            cargarDatosIA(item); // Llamada a los datos de la IA
        });

        // ==========================
        //  LÓGICA DEL MAPA (Exactamente igual que agregar_obra.php)
        // ==========================
        const modal = document.getElementById('modalMapa');
        const imgMapa = document.getElementById('imgMapaPuntos');
        const inputX = document.getElementById('inputX');
        const inputY = document.getElementById('inputY');
        const pinesContainer = document.getElementById('pinesContainer');

        const resizeObserver = new ResizeObserver(() => {
            pinesContainer.style.width = imgMapa.offsetWidth + 'px';
            pinesContainer.style.height = imgMapa.offsetHeight + 'px';
            pinesContainer.style.left = imgMapa.offsetLeft + 'px';
            pinesContainer.style.top = imgMapa.offsetTop + 'px';
        });
        resizeObserver.observe(imgMapa);

        function cargarPinesExistentesLocales() {
            pinesContainer.innerHTML = '';
            const segmento = document.getElementById('selectSegmento').value;
            const currentIdx = parseInt(document.getElementById('selectObra').value);
            const lista = obrasPorSegmento[segmento] || [];
            
            lista.forEach((obra, idx) => {
                if (!obra.nombre) return;
                const x = parseFloat(String(obra.x || '').replace(',', '.'));
                const y = parseFloat(String(obra.y || '').replace(',', '.'));
                
                if (!isNaN(x) && !isNaN(y) && x >= 0 && x <= 1 && y >= 0 && y <= 1) {
                    const visualY = 1 - y;
                    // Si es la obra actual que estamos editando, la pintamos azul, el resto rojas
                    const esActual = (idx === currentIdx);
                    const colorPin = esActual ? '#3b82f6' : '#ef4444';
                    const zIndex = esActual ? '100' : '1';
                    pinesContainer.innerHTML += `<div class="pin-existente" style="background: ${colorPin}; z-index: ${zIndex}; left: ${x * 100}%; top: ${visualY * 100}%;"><div class="pin-label">${obra.nombre}</div></div>`;
                }
            });
        }

        document.getElementById('btnAbrirMapa').addEventListener('click', () => { 
            modal.classList.add('is-open'); 
            currentZoom = 1;
            document.getElementById('zoomNivel').innerText = '100%';
            imgMapa.style.width = ''; imgMapa.style.height = ''; imgMapa.style.maxWidth = '100%'; imgMapa.style.maxHeight = '80vh';
            modalBody.style.textAlign = 'center';
            cargarPinesExistentesLocales(); 
        });
        document.getElementById('btnCerrarMapa').addEventListener('click', () => { modal.classList.remove('is-open'); });

        let dragDist = 0;
        imgMapa.addEventListener('click', function(e) {
            if (dragDist > 10) return;
            const rect = imgMapa.getBoundingClientRect();
            const htmlX = (e.clientX - rect.left) / rect.width;
            const htmlY = (e.clientY - rect.top) / rect.height;
            
            inputX.value = htmlX.toFixed(4);
            inputY.value = (1 - htmlY).toFixed(4);
            inputX.style.borderColor = '#10b981'; inputY.style.borderColor = '#10b981';
            setTimeout(() => { inputX.style.borderColor = '#1f2937'; inputY.style.borderColor = '#1f2937'; }, 1500);
            modal.classList.remove('is-open');
        });

        let currentZoom = 1, baseMapWidth = 0, baseMapHeight = 0;
        const zoomStep = 0.2, maxZoom = 4, minZoom = 1, modalBody = document.querySelector('.map-modal-body');
        let isDragging = false, startX, startY, startScrollLeft, startScrollTop;

        modalBody.addEventListener('mousedown', (e) => { if (e.button !== 0) return; isDragging = true; dragDist = 0; startX = e.clientX; startY = e.clientY; startScrollLeft = modalBody.scrollLeft; startScrollTop = modalBody.scrollTop; modalBody.style.cursor = 'grabbing'; e.preventDefault(); });
        window.addEventListener('mousemove', (e) => { if (!isDragging) return; const dx = e.clientX - startX, dy = e.clientY - startY; dragDist += Math.abs(dx) + Math.abs(dy); modalBody.scrollLeft = startScrollLeft - dx; modalBody.scrollTop = startScrollTop - dy; startX = e.clientX; startY = e.clientY; startScrollLeft = modalBody.scrollLeft; startScrollTop = modalBody.scrollTop; });
        window.addEventListener('mouseup', () => { if (isDragging) { isDragging = false; modalBody.style.cursor = 'crosshair'; }});
        document.getElementById('btnZoomIn').addEventListener('click', () => applyZoom(currentZoom + 0.5));
        document.getElementById('btnZoomOut').addEventListener('click', () => applyZoom(currentZoom - 0.5));
        modalBody.addEventListener('wheel', (e) => { e.preventDefault(); applyZoom(currentZoom + (e.deltaY < 0 ? zoomStep : -zoomStep), e.clientX, e.clientY); }, { passive: false });

        function applyZoom(newZoom, mouseX, mouseY) {
            const oldZoom = currentZoom; currentZoom = Math.max(minZoom, Math.min(maxZoom, newZoom));
            if (currentZoom === oldZoom) return;
            if (oldZoom === 1) { baseMapWidth = imgMapa.clientWidth; baseMapHeight = imgMapa.clientHeight; }
            document.getElementById('zoomNivel').innerText = Math.round(currentZoom * 100) + '%';
            
            const rectBefore = imgMapa.getBoundingClientRect(), bodyRect = modalBody.getBoundingClientRect();
            if (mouseX === undefined) mouseX = bodyRect.left + bodyRect.width / 2;
            if (mouseY === undefined) mouseY = bodyRect.top + bodyRect.height / 2;
            
            const pctX = (mouseX - rectBefore.left) / rectBefore.width, pctY = (mouseY - rectBefore.top) / rectBefore.height;
            
            if (currentZoom === 1) {
                imgMapa.style.width = ''; imgMapa.style.height = ''; imgMapa.style.maxWidth = '100%'; imgMapa.style.maxHeight = '80vh';
                modalBody.style.textAlign = 'center';
            } else {
                if (!baseMapWidth) { baseMapWidth = imgMapa.clientWidth || 800; baseMapHeight = imgMapa.clientHeight || 600; }
                imgMapa.style.maxWidth = 'none'; imgMapa.style.maxHeight = 'none'; imgMapa.style.width = (baseMapWidth * currentZoom) + 'px'; imgMapa.style.height = (baseMapHeight * currentZoom) + 'px';
                modalBody.style.textAlign = 'left';
            }
            void imgMapa.offsetWidth; 
            const rectAfter = imgMapa.getBoundingClientRect();
            if (currentZoom > 1) { modalBody.scrollLeft -= mouseX - (rectAfter.left + (rectAfter.width * pctX)); modalBody.scrollTop -= mouseY - (rectAfter.top + (rectAfter.height * pctY)); }
        }

        // ==========================
        //  NUEVA LÓGICA DE GESTIÓN DE FOTOS INTEGRADA
        // ==========================
        async function cargarFotosObra() {
            const segmento = document.getElementById('formSegmento').value;
            const carpeta = document.getElementById('formCarpeta').value;
            const galeriaEl = document.getElementById('galeria');
            const galeriaEmpty = document.getElementById('galeriaEmpty');
            const btnZip = document.getElementById('btnDescargarZip');
            const btnSubir = document.getElementById('btnSubirFotos');
            const filesInput = document.getElementById('files');
            const btnEliminarTodo = document.getElementById('btnEliminarTodo');

            galeriaEl.innerHTML = ''; document.getElementById('previewContainerUpload').innerHTML = '';
            filesInput.value = ''; document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('progressBar').style.width = '0%'; document.getElementById('status').textContent = '';

            if (!carpeta || carpeta === '-') {
                galeriaEmpty.style.display = 'block'; galeriaEmpty.innerHTML = "Esta obra no tiene carpeta configurada. <br><br> 👇 Haz clic en el botón verde <b>Guardar Todos los Cambios</b> aquí abajo para que el sistema le cree su carpeta automáticamente.<br><br><i>Una vez guardado, aparecerá aquí el botón para subir fotos.</i>";
                btnZip.disabled = true; btnSubir.disabled = true; btnEliminarTodo.disabled = true; 
                document.getElementById('uploadForm').style.display = 'none'; return;
            }
            document.getElementById('uploadForm').style.display = 'block';

            const fd = new FormData(); fd.append("action", "listar"); fd.append("segmento", segmento.toLowerCase()); fd.append("carpeta", carpeta);
            const resp = await fetch("fotos_api.php?_t=" + Date.now(), { method: "POST", body: fd });
            const data = await resp.json();

            if (!data.ok) { galeriaEmpty.style.display = "block"; galeriaEmpty.textContent = data.error || "Error al cargar la galería."; return; }

            btnEliminarTodo.disabled = !(data.fotos && data.fotos.length);
            btnZip.disabled = !(data.fotos && data.fotos.length);
            btnSubir.disabled = (data.fotos && data.fotos.length >= 6) || !filesInput.files.length;

            renderGaleria(data);
        }

        function renderGaleria(data) {
            const galeriaEl = document.getElementById("galeria"); const galeriaEmpty = document.getElementById("galeriaEmpty");
            galeriaEl.innerHTML = ""; galeriaEmpty.style.display = "none";
            if (!data.fotos || !data.fotos.length) { galeriaEmpty.style.display = "block"; galeriaEmpty.textContent = "Aún no hay fotos. Sube algunas abajo."; return; }

            data.fotos.forEach((foto, idx) => {
                const card = document.createElement("div"); card.className = "foto-card"; card.draggable = true; card.dataset.num = idx + 1;
                card.innerHTML = `
                    <img src="${foto.thumb_url || foto.url}" alt="Foto ${idx + 1}">
                    ${foto.es_principal ? '<div class="badge-principal">Principal</div>' : ''}
                    <div class="foto-meta"><span>Foto ${idx + 1}</span><span>${foto.size_kb} KB</span></div>
                    <div class="foto-actions">
                        <button type="button" class="btn-principal" onclick="marcarPrincipal(${idx + 1})">Principal</button>
                        <button type="button" class="btn-eliminar" onclick="eliminarFoto(${idx + 1})">Eliminar</button>
                    </div>
                `;
                galeriaEl.appendChild(card);
                card.addEventListener("dragstart", () => { card.style.opacity = "0.5"; card.classList.add("dragging"); });
                card.addEventListener("dragend", () => { card.style.opacity = "1"; card.classList.remove("dragging"); document.querySelectorAll(".foto-card").forEach(c => c.classList.remove("drag-over")); });
                card.addEventListener("dragover", (e) => e.preventDefault());
                card.addEventListener("dragenter", (e) => { e.preventDefault(); if (!card.classList.contains("dragging")) card.classList.add("drag-over"); });
                card.addEventListener("dragleave", () => card.classList.remove("drag-over"));
                card.addEventListener("drop", (e) => {
                    e.preventDefault(); card.classList.remove("drag-over");
                    const draggingCard = document.querySelector(".dragging");
                    if (draggingCard && draggingCard !== card) {
                        const allCards = [...galeriaEl.querySelectorAll(".foto-card")];
                        if (allCards.indexOf(draggingCard) < allCards.indexOf(card)) card.parentNode.insertBefore(draggingCard, card.nextSibling);
                        else card.parentNode.insertBefore(draggingCard, card);
                        guardarNuevoOrden();
                    }
                });
            });
        }

        async function guardarNuevoOrden() {
            const fd = new FormData(); fd.append("action", "reordenar"); fd.append("segmento", document.getElementById('formSegmento').value.toLowerCase()); fd.append("carpeta", document.getElementById('formCarpeta').value);
            fd.append("orden", JSON.stringify([...document.querySelectorAll("#galeria .foto-card")].map(c => parseInt(c.dataset.num, 10))));
            await fetch("fotos_api.php", { method: "POST", body: fd }); 
            cargarFotosObra();
            
            // Pequeño aviso visual para que el usuario sepa que su arrastre se guardó
            const statusEl = document.getElementById("status");
            statusEl.textContent = "✅ Nuevo orden guardado automáticamente.";
            setTimeout(() => { if(statusEl.textContent.includes("orden")) statusEl.textContent = ""; }, 3000);
        }
        async function marcarPrincipal(numFoto) {
            const fd = new FormData(); fd.append("action", "principal"); fd.append("segmento", document.getElementById('formSegmento').value.toLowerCase()); fd.append("carpeta", document.getElementById('formCarpeta').value); fd.append("numero", numFoto);
            await fetch("fotos_api.php", { method: "POST", body: fd }); cargarFotosObra();
        }
        async function eliminarFoto(numFoto) {
            if (!confirm("¿Seguro que deseas eliminar esta foto?")) return;
            const fd = new FormData(); fd.append("action", "eliminar"); fd.append("segmento", document.getElementById('formSegmento').value.toLowerCase()); fd.append("carpeta", document.getElementById('formCarpeta').value); fd.append("numero", numFoto);
            await fetch("fotos_api.php", { method: "POST", body: fd }); cargarFotosObra();
        }
        document.getElementById('btnEliminarTodo').addEventListener('click', async () => {
            if (!confirm("Vas a eliminar TODAS las fotos de esta obra. ¿Confirmas?")) return;
            const fd = new FormData(); fd.append("action", "eliminar_todas"); fd.append("segmento", document.getElementById('formSegmento').value.toLowerCase()); fd.append("carpeta", document.getElementById('formCarpeta').value);
            await fetch("fotos_api.php", { method: "POST", body: fd }); cargarFotosObra();
        });
        document.getElementById('btnDescargarZip').addEventListener('click', () => {
            window.location.href = "fotos_api.php?download_zip=1&" + new URLSearchParams({ segmento: document.getElementById('formSegmento').value.toLowerCase(), carpeta: document.getElementById('formCarpeta').value }).toString();
        });
        document.getElementById('files').addEventListener('change', function() {
            const preview = document.getElementById('previewContainerUpload'); preview.innerHTML = ''; document.getElementById('btnSubirFotos').disabled = this.files.length === 0;
            Array.from(this.files).forEach((file) => { preview.innerHTML += `<div class="foto-card" style="border-color:#3b82f6; opacity:0.8;"><img src="${URL.createObjectURL(file)}"><div class="foto-meta"><span style="color:#93c5fd">A subir...</span><span>${(file.size/1024).toFixed(1)} KB</span></div></div>`; });
        });
        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault(); const filesInput = document.getElementById("files"); if (!filesInput.files.length) return;
            const fd = new FormData(); fd.append("action", "subir"); fd.append("segmento", document.getElementById('formSegmento').value.toLowerCase()); fd.append("carpeta", document.getElementById('formCarpeta').value);
            for (const file of filesInput.files) fd.append("files[]", file);
            const statusEl = document.getElementById("status"); const btnSubir = document.getElementById("btnSubirFotos"); document.getElementById("progressContainer").style.display = "block"; btnSubir.disabled = true;
            try {
                const text = await new Promise((res, rej) => { const xhr = new XMLHttpRequest(); xhr.open("POST", "upload.php?_t=" + Date.now(), true); xhr.upload.addEventListener("progress", (e) => { if (e.lengthComputable) { const pct = (e.loaded/e.total)*100; document.getElementById("progressBar").style.width = pct + "%"; statusEl.textContent = `Subiendo (${Math.round(pct)}%)...`; }}); xhr.onload = () => res(xhr.responseText); xhr.onerror = () => rej(new Error("Error de red")); xhr.send(fd); });
                document.getElementById("progressContainer").style.display = "none";
                const data = JSON.parse(text); 
                if (data.ok) { statusEl.innerHTML = `✅ ¡Fotos subidas!`; cargarFotosObra(); } 
                else { statusEl.innerHTML = `<span style="color:#ef4444">❌ Error: ${data.error || (data.errores ? data.errores.join('<br>') : 'Error desconocido')}</span>`; btnSubir.disabled = false; }
            } catch (err) { statusEl.innerHTML = `<span style="color:#ef4444">❌ Error al procesar respuesta del servidor. ¿Quizás la foto es demasiado pesada? (${err.message})</span>`; btnSubir.disabled = false; }
        });

        // ==========================
        //  GUARDADO SILENCIOSO (AJAX) DEL EXCEL
        // ==========================
        document.getElementById('btnGuardarGlobal').addEventListener('click', () => {
            document.getElementById('formEditar').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });

        document.getElementById('formEditar').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const base = parseFloat(document.getElementById('inputMontoBase').value) || 0;
            const mag = parseFloat(document.getElementById('inputMontoMagnitud').value) || 1;
            document.getElementById('hidden_monto').value = base > 0 ? (base * mag) : '';
            
            const form = e.target;
            const btn = document.getElementById('btnGuardarGlobal');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Guardando Excel, Fotos e IA...';
            btn.disabled = true;
            
            // ==============================================
            // 1. INYECTAR A LA IA EN SEGUNDO PLANO
            // ==============================================
            const tabs = document.querySelectorAll('.tab-btn');
            let contextoManual = "";
            tabs.forEach(btnTab => {
                const tabId = btnTab.dataset.target;
                const tit = btnTab.querySelector('.ctx-titulo').value.trim();
                const pane = document.getElementById(tabId);
                if (pane) {
                    const txt = pane.querySelector('.ctx-texto').value.trim();
                    if (txt) contextoManual += `--- ${tit || 'Fragmento'} ---\n${txt}\n\n`;
                }
            });
            
            const formDesc = document.getElementById('inputDesc').value.trim();
            const tituloClave = "Obra: " + document.getElementById('inputNombre').value.trim();
            const nombreSeg = document.querySelector('#selectSegmento option:checked').text;
            let textoIA = `La obra '${document.getElementById('inputNombre').value.trim()}' pertenece al sector ${nombreSeg}. `;
            const dist = document.getElementById('inputDistrito').value;
            const prov = document.getElementById('inputProvincia').value;
            if (dist || prov) textoIA += `Ubicada en ${dist}, ${prov}. `;
            const est = document.getElementById('inputEstado').value;
            if (est) textoIA += `Estado actual: '${est}'. `;
            const realMonto = base > 0 ? (base * mag) : '';
            const displayMonto = formatMontoFriendly(realMonto);
            if (displayMonto && displayMonto !== 'S/ 0') textoIA += `Monto referencial: ${displayMonto}. `;
            if (formDesc) textoIA += `Descripción oficial: ${formDesc}. `;
            if (contextoManual) textoIA += `Contexto adicional: ${contextoManual.trim()}`;
            
            const fdIA = new FormData();
            fdIA.append('action', 'save_single');
            fdIA.append('titulo', tituloClave);
            fdIA.append('contenido', textoIA.trim());
            fdIA.append('palabras', `${document.getElementById('inputNombre').value.trim()}, ${dist}, ${nombreSeg}`);

            try { await fetch('ia_cerebro_obras.php', { method: 'POST', body: fdIA }); } catch(err) { console.warn("Error IA background", err); }
            
            // ==============================================
            // 2. PROCEDER CON EL GUARDADO NORMAL (EXCEL)
            // ==============================================

            const fd = new FormData(form);
            fd.append('ajax', '1'); // Le dice a PHP que no recargue la página

            try {
                const resp = await fetch('editar_obra.php', { method: 'POST', body: fd });
                const data = await resp.json();
                
                const msgDiv = document.getElementById('formMsg');
                msgDiv.innerHTML = `<div class="${data.ok ? 'msg-success' : 'msg-error'}" style="margin-top:15px; margin-bottom:0;">${data.mensaje}</div>`;
                if (data.ok && data.carpeta) {
                    document.getElementById('formCarpeta').value = data.carpeta;
                    const seg = document.getElementById('formSegmento').value;
                    const idx = document.getElementById('selectObra').value;
                    if (obrasPorSegmento[seg] && obrasPorSegmento[seg][idx]) {
                        obrasPorSegmento[seg][idx].carpeta = data.carpeta;
                    }
                }
                
                // Si el usuario seleccionó fotos, las subimos automáticamente
                const filesInput = document.getElementById("files");
                if (data.ok && filesInput && filesInput.files.length > 0) {
                    document.getElementById('status').innerHTML = '⏳ Creó carpeta... Subiendo fotos ahora...';
                    document.getElementById('uploadForm').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                } else if (data.ok) { cargarFotosObra(); }
                
                setTimeout(() => msgDiv.innerHTML = '', 4000);
            } catch (err) {
                alert('Error de conexión al guardar.');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

        // ==========================
        //  GUARDADO EN IA DIRECTO
        // ==========================
        document.getElementById('btnSaveIA').addEventListener('click', async () => {
            const btn = document.getElementById('btnSaveIA');
            const txtOriginal = btn.innerHTML;
            btn.innerHTML = "⏳ Inyectando a la IA...";
            btn.disabled = true;

            const idx = document.getElementById('selectObra').value;
            const segmento = document.getElementById('selectSegmento').value;
            
            const formDesc = document.getElementById('inputDesc').value.trim();

            const tabs = document.querySelectorAll('.tab-btn');
            let contextoManual = "";
            tabs.forEach(btnTab => {
                const tabId = btnTab.dataset.target;
                const tit = btnTab.querySelector('.ctx-titulo').value.trim();
                const pane = document.getElementById(tabId);
                if (pane) {
                    const txt = pane.querySelector('.ctx-texto').value.trim();
                    if (txt) contextoManual += `--- ${tit || 'Fragmento'} ---\n${txt}\n\n`;
                }
            });
            contextoManual = contextoManual.trim();
            
            const tituloClave = "Obra: " + document.getElementById('inputNombre').value.trim();
            const nombreSeg = document.querySelector('#selectSegmento option:checked').text;
            let texto = `La obra '${document.getElementById('inputNombre').value.trim()}' pertenece al sector ${nombreSeg}. `;
            const dist = document.getElementById('inputDistrito').value;
            const prov = document.getElementById('inputProvincia').value;
            if (dist || prov) texto += `Ubicada en ${dist}, ${prov}. `;
            
            const est = document.getElementById('inputEstado').value;
            if (est) texto += `Estado actual: '${est}'. `;
            
            const baseMonto = parseFloat(document.getElementById('inputMontoBase').value) || 0;
            const magMonto = parseFloat(document.getElementById('inputMontoMagnitud').value) || 1;
            const realMonto = baseMonto > 0 ? (baseMonto * magMonto) : '';
            const displayMonto = formatMontoFriendly(realMonto);
            if (displayMonto && displayMonto !== 'S/ 0') texto += `Monto referencial: ${displayMonto}. `;
            
            if (formDesc) texto += `Descripción oficial: ${formDesc}. `;
            if (contextoManual) texto += `Contexto adicional: ${contextoManual}`;
            texto = texto.trim();
            
            const palabrasClave = `${document.getElementById('inputNombre').value.trim()}, ${dist}, ${nombreSeg}`;

            const fd = new FormData();
            fd.append('action', 'save_single');
            fd.append('titulo', tituloClave);
            fd.append('contenido', texto);
            fd.append('palabras', palabrasClave);

            try {
                const resp = await fetch('ia_cerebro_obras.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if(data.ok) {
                    CEREBRO_IA[tituloClave] = texto; // Actualizar local
                    const badge = document.getElementById('aiStatusBadge');
                    badge.textContent = "🟢 Ya en Cerebro";
                    badge.style.color = "#10b981";
                    badge.style.background = "rgba(16, 185, 129, 0.1)";
                    badge.style.borderColor = "#10b981";
                    alert("🧠 ¡El cerebro de la IA fue actualizado con los datos de esta obra!");
                } else { alert("Error: " + data.error); }
            } catch(e) { alert("Error de conexión al alimentar a la IA."); } 
            finally { btn.innerHTML = txtOriginal; btn.disabled = false; }
        });

        // ==========================
        //  BOTÓN MÁGICO ADMIN (LIMPIEZA DE MONTOS)
        // ==========================
        const btnLimpiar = document.getElementById('btnLimpiarMontos');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', async function() {
                if (!confirm("Esto escaneará todas tus pestañas de Excel y convertirá los textos como '15 Millones' a números puros. ¿Deseas continuar?")) return;
                
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '⏳ Procesando Excel... (Puede tardar unos segundos)';
                btn.disabled = true;

                const fd = new FormData();
                fd.append('action', 'limpiar_montos');

                try {
                    const resp = await fetch('editar_obra.php', { method: 'POST', body: fd });
                    const data = await resp.json();
                    alert(data.mensaje);
                    if (data.ok) cargarDataInicial(); // Refrescar los datos locales si funcionó
                } catch (err) {
                    alert("Error de red al intentar limpiar los montos.");
                } finally {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }

        // Auto-inicializar y lógica de Provincias/Distritos
        document.addEventListener("DOMContentLoaded", () => {
            cargarDataInicial();

            const inputProvincia = document.getElementById('inputProvincia');
            const inputDistrito = document.getElementById('inputDistrito');

            Object.keys(DISTRITOS_POR_PROVINCIA).forEach(prov => {
                const option = document.createElement('option');
                option.value = prov; option.textContent = prov;
                inputProvincia.appendChild(option);
            });

            inputProvincia.addEventListener('change', function() {
                const provinciaSeleccionada = this.value;
                const distritos = DISTRITOS_POR_PROVINCIA[provinciaSeleccionada] || [];
                const currentDistritoValue = inputDistrito.value;
                inputDistrito.innerHTML = '<option value="">Selecciona un distrito...</option>';
                distritos.forEach(dist => {
                    const option = document.createElement('option');
                    option.value = dist; option.textContent = dist;
                    inputDistrito.appendChild(option);
                });
                if (distritos.includes(currentDistritoValue)) inputDistrito.value = currentDistritoValue;
                inputDistrito.disabled = false;
            });
        });
    </script>
</body>
</html>