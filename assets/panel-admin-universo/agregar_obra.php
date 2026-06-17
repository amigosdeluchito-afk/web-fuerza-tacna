<?php
require_once __DIR__ . '/config.php';
require_login();

$mensaje = '';
if (isset($_GET['success'])) {
    $mensaje = '<div class="msg-success">¡Obra agregada con éxito en Google Sheets! Ya puedes verla en tu Excel y en la pestaña de Fotos.</div>';
} elseif (isset($_GET['error'])) {
    $mensaje = '<div class="msg-error">Error: ' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nueva Obra - Panel Admin</title>
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
        
        /* Botón para abrir el mapa */
        .btn-mapa { background: transparent; border: 1px solid #3b82f6; color: #60a5fa; padding: 8px 12px; border-radius: 8px; font-size: 13px; cursor: pointer; transition: all 0.2s; margin-top: 10px; width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-mapa:hover { background: rgba(59, 130, 246, 0.1); color: #93c5fd; }
        
        /* Botones de zoom */
        .btn-zoom { background: #1e293b; color: #f9fafb; border: 1px solid #334155; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        .btn-zoom:hover { background: #334155; }
        
        /* Ventana emergente (Modal) del Mapa */
        .map-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(2, 6, 23, 0.95); z-index: 1000; display: none; flex-direction: column; backdrop-filter: blur(5px); }
        .map-modal.is-open { display: flex; }
        .map-modal-header { padding: 15px 24px; background: #0f172a; border-bottom: 1px solid #1f2937; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.3); z-index: 10; }
        .map-modal-header h3 { margin: 0; font-size: 16px; color: #f9fafb; font-weight: 600; }
        .btn-close-map { background: #ef4444; color: white; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-close-map:hover { background: #dc2626; }
        
        /* Contenedor escroleable para la imagen gigante */
        .map-modal-body { flex: 1; overflow: auto; cursor: crosshair; position: relative; padding: 20px; text-align: center; }
        .map-modal-body::-webkit-scrollbar { width: 10px; height: 10px; }
        .map-modal-body::-webkit-scrollbar-track { background: #0f172a; }
        .map-modal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 5px; }
        
        .map-modal-body img {
            max-width: 100%;
            max-height: 80vh;
            width: auto;
            height: auto;
            display: block;
            border: 2px solid #334155;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
            border-radius: 8px;
            background: #fff;
        }
        
        /* Pines superpuestos de obras existentes */
        #pinesContainer { pointer-events: none; position: absolute; top: 0; left: 0; }
        .pin-existente {
            position: absolute;
            width: 12px; height: 12px;
            background: #ef4444; border: 2px solid #fff;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 5px rgba(0,0,0,0.8);
        }
        .pin-label {
            position: absolute; left: 12px; top: -8px;
            background: rgba(15, 23, 42, 0.9); color: #f9fafb;
            font-size: 10px; padding: 2px 6px; border-radius: 4px; white-space: nowrap; font-family: system-ui;
        }
        
        /* Estilos añadidos para la Galería de Fotos (Previsualización) */
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-top: 15px; }
        .foto-card { border-radius: 8px; border: 1px solid #1f2937; padding: 6px; background: #0f172a; position: relative; display: flex; flex-direction: column; gap: 6px; cursor: grab; transition: transform 0.2s; }
        .foto-card:active { cursor: grabbing; }
        .foto-card.drag-over { transform: scale(1.05); box-shadow: 0 0 0 2px #3b82f6; z-index: 10; }
        .foto-card img { width: 100%; height: 100px; object-fit: cover; border-radius: 6px; display: block; }
        .foto-meta { font-size: 11px; color: #9ca3af; display: flex; justify-content: space-between; align-items: center; }
        .badge-principal { position: absolute; top: 6px; left: 6px; background: #10b981; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 4px; font-weight: bold; }

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
            <h1>Agregar Nueva Obra</h1>
            <?= $mensaje ?>
        
            <form action="guardar_obra.php" method="POST" enctype="multipart/form-data">
                <label>Segmento (Hoja de Excel destino):</label>
                <select name="segmento" id="selectSegmento" required>
                    <option value="">Cargando segmentos activos...</option>
                </select>

                <label>Nombre de la Obra:</label>
                <input type="text" name="nombre" required placeholder="Ej. Creación de colegio en Viñani...">

                <label>Estado:</label>
                <select name="estado" required>
                    <option value="Entregado">Entregado</option>
                    <option value="En construcción">En construcción</option>
                    <option value="Paralizado">Paralizado</option>
                    <option value="Buena Pro">Buena Pro</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="En estudios">En estudios</option>
                </select>

                <div class="row">
                    <div style="flex: 2;">
                        <label>Monto Referencial:</label>
                        <input type="number" step="any" id="monto_base" placeholder="Ej. 1.5 o 500">
                    </div>
                    <div style="flex: 1;">
                        <label>Magnitud:</label>
                        <select id="monto_magnitud">
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
                        <select name="provincia" id="selectProvincia" required></select>
                    </div>
                    <div>
                        <label>Distrito:</label>
                        <select name="distrito" id="selectDistrito" required disabled><option value="">Primero elige provincia</option></select>
                    </div>
                </div>

                <div class="row">
                    <div><label>Coordenada X (Longitud):</label><input type="text" name="x" placeholder="Ej. 0.345"></div>
                    <div><label>Coordenada Y (Latitud):</label><input type="text" name="y" placeholder="Ej. 0.678"></div>
                </div>
                
                <label>Descripción de la Obra:</label>
                <textarea name="descripcion" rows="4" placeholder="Breve descripción de la obra..." style="resize: vertical;"></textarea>

                <button type="button" id="btnAbrirMapa" class="btn-mapa">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    Abrir Mapa para ubicar Coordenadas
                </button>

                <!-- NUEVO: Subida de fotos integrada -->
                <div style="margin-top: 25px; background: #0f172a; padding: 15px; border-radius: 10px; border: 1px dashed #334155;">
                    <label style="margin-top: 0;">Fotos de la Obra (Opcional, máx. 6):</label>
                    <input type="file" name="fotos[]" id="inputFotos" accept="image/*" multiple>
                    <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">Formatos soportados: JPG, PNG, WEBP.</div>
                    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #93c5fd; padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-top: 10px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">💡 <strong>Tip:</strong> Puedes cambiar el orden arrastrando las imágenes antes de guardar. La primera será la principal.</div>
                    <div id="previewContainer" class="galeria"></div>
                </div>

                <!-- NUEVO: SECCIÓN CEREBRO IA INTEGRADA -->
                <div id="iaSection" style="margin-top: 30px; border-top: 1px solid #1f2937; padding-top: 20px;">
                    <h2 style="font-size: 18px; margin-top:0; color: #f9fafb; display:flex; justify-content:space-between; align-items:center;">
                        🧠 Cerebro IA (Contexto)
                        <span id="aiStatusBadge" style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;">🔴 Nueva para IA</span>
                    </h2>
                    <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); color: #c4b5fd; padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-top: 10px; margin-bottom: 10px;">💡 <strong>Tip:</strong> Puedes agregar información extra (noticias, historia, detalles) para que la IA se aprenda esta obra al momento de guardarla.</div>
                    
                    <div class="tabs-container">
                        <div class="tabs-header">
                            <div id="tabsHeaderList" style="display: flex; flex: 0 0 auto;"></div>
                            <button type="button" class="btn-add-tab" onclick="agregarContexto(null, '', true)" title="Añadir nueva pestaña">➕ Nueva Pestaña</button>
                            <button type="button" class="btn-add-tab" onclick="abrirExtractorUrl()" title="Extraer texto de un enlace web" style="color: #3b82f6;">🌐 Extraer Link</button>
                        </div>
                        <div class="tabs-content" id="tabsContent"></div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Guardar Obra e IA en Excel</button>
            </form>
        </div>
    </main>

    <!-- VENTANA EMERGENTE DEL MAPA -->
    <div id="modalMapa" class="map-modal">
        <div class="map-modal-header">
            <h3>🎯 Haz clic en el lugar exacto de la obra</h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" id="btnZoomOut" class="btn-zoom">-</button>
                <span id="zoomNivel" style="color: #94a3b8; font-size: 14px; min-width: 45px; text-align: center; font-weight: bold;">100%</span>
                <button type="button" id="btnZoomIn" class="btn-zoom">+</button>
                <button type="button" id="btnCerrarMapa" class="btn-close-map" style="margin-left: 10px;">Cerrar</button>
            </div>
        </div>
        <div class="map-modal-body">
            <div id="mapWrapper" style="position: relative; display: inline-block;">
                <!-- Cargamos tu mapa base real -->
                <img id="imgMapaPuntos" src="../universoobras/IMG/mapa-base.png" alt="Mapa Base">
                <!-- Contenedor donde se dibujarán los puntos rojos -->
                <div id="pinesContainer"></div>
                <div id="loadingPines" style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.8); color: #ffc300; padding: 5px 10px; border-radius: 5px; font-size: 12px; display: none;">Cargando obras existentes...</div>
            </div>
        </div>
    </div>

    <script>
        const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq`;
        
        // Interceptar el guardado para enviarlo también a la IA
        document.querySelector('form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('.btn-submit');
            
            const base = parseFloat(document.getElementById('monto_base').value) || 0;
            const mag = parseFloat(document.getElementById('monto_magnitud').value) || 1;
            document.getElementById('hidden_monto').value = base > 0 ? (base * mag) : '';

            submitBtn.innerHTML = "⏳ Guardando Obra e IA...";
            submitBtn.disabled = true;

            // 1. Recopilar datos de las pestañas IA
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
            
            // 2. Construir el Super Párrafo de la IA
            const nombreObra = form.querySelector('input[name="nombre"]').value.trim();
            const nombreSeg = form.querySelector('#selectSegmento option:checked').text;
            const distrito = form.querySelector('#selectDistrito').value;
            const provincia = form.querySelector('#selectProvincia').value;
            const estado = form.querySelector('select[name="estado"]').value;
            const formDesc = form.querySelector('textarea[name="descripcion"]').value.trim();
            
            let texto = `La obra '${nombreObra}' pertenece al sector ${nombreSeg}. `;
            if (distrito || provincia) texto += `Ubicada en ${distrito}, ${provincia}. `;
            if (estado) texto += `Estado actual: '${estado}'. `;
            
            const realMonto = base > 0 ? (base * mag) : '';
            function formatMonto(num) {
                if (num === 0) return 'S/ 0';
                if (num >= 1000000) { let m = num / 1000000; return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Millones'; } 
                if (num >= 1000) { let m = num / 1000; return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Mil'; }
                return 'S/ ' + num.toLocaleString('en-US');
            }
            if (realMonto) texto += `Monto referencial: ${formatMonto(realMonto)}. `;
            
            if (formDesc) texto += `Descripción oficial: ${formDesc}. `;
            if (contextoManual) texto += `Contexto adicional: ${contextoManual}`;
            
            const fdIA = new FormData();
            fdIA.append('action', 'save_single');
            fdIA.append('titulo', "Obra: " + nombreObra);
            fdIA.append('contenido', texto.trim());
            fdIA.append('palabras', `${nombreObra}, ${distrito}, ${nombreSeg}`);

            try { await fetch('ia_cerebro_obras.php', { method: 'POST', body: fdIA }); } catch(err) { console.warn(err); }

            // 3. Continuar con el guardado nativo en Google Sheets
            HTMLFormElement.prototype.submit.call(form);
        });

        function parseGviz(text) {
            const m = text.match(/setResponse\(([\s\S]+)\);?/);
            if (!m) throw new Error("Error parseo");
            return JSON.parse(m[1]);
        }

        // Lógica para capturar las coordenadas con un clic
        const modal = document.getElementById('modalMapa');
        const imgMapa = document.getElementById('imgMapaPuntos');
        const inputX = document.querySelector('input[name="x"]');
        const inputY = document.querySelector('input[name="y"]');
        const pinesContainer = document.getElementById('pinesContainer');
        const loadingPines = document.getElementById('loadingPines');

        // Sincronizar perfectamente el contenedor de pines con el tamaño de la imagen en todo momento
        const resizeObserver = new ResizeObserver(() => {
            pinesContainer.style.width = imgMapa.offsetWidth + 'px';
            pinesContainer.style.height = imgMapa.offsetHeight + 'px';
            pinesContainer.style.left = imgMapa.offsetLeft + 'px';
            pinesContainer.style.top = imgMapa.offsetTop + 'px';
        });
        resizeObserver.observe(imgMapa);

        async function cargarPinesExistentes() {
            pinesContainer.innerHTML = '';
            loadingPines.style.display = 'block';
            const segmento = document.getElementById('selectSegmento').value;
            
            try {
                const tqBuster = encodeURIComponent(`select * offset 0`);
                const url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=${encodeURIComponent(segmento)}&range=A:J&headers=1`;
                const resp = await fetch(url);
                const txt = await resp.text();
                const json = parseGviz(txt);
                
                (json.table.rows || []).forEach(r => {
                    if (!r.c) return;
                    const nombre = r.c[0]?.v || '';
                    
                    // Reemplazamos coma por punto antes de leer el número para evitar que se vayan a la esquina (0,0)
                    const x = parseFloat(String(r.c[3]?.v || '').replace(',', '.'));
                    const y = parseFloat(String(r.c[4]?.v || '').replace(',', '.'));
                    
                    if (!isNaN(x) && !isNaN(y) && x >= 0 && x <= 1 && y >= 0 && y <= 1) {
                        const visualY = 1 - y; // Invertimos Y para que se dibuje correctamente de arriba hacia abajo
                        pinesContainer.innerHTML += `<div class="pin-existente" style="left: ${x * 100}%; top: ${visualY * 100}%;"><div class="pin-label">${nombre}</div></div>`;
                    }
                });
            } catch (e) { console.error(e); }
            loadingPines.style.display = 'none';
        }

        document.getElementById('btnAbrirMapa').addEventListener('click', () => { 
            modal.classList.add('is-open'); 
            currentZoom = 1;
            document.getElementById('zoomNivel').innerText = '100%';
            
            imgMapa.style.width = ''; 
            imgMapa.style.height = ''; 
            imgMapa.style.maxWidth = '100%'; 
            imgMapa.style.maxHeight = '80vh';
            modalBody.style.textAlign = 'center';
            
            cargarPinesExistentes(); 
        });
        document.getElementById('btnCerrarMapa').addEventListener('click', () => { modal.classList.remove('is-open'); });

        let dragDist = 0;

        imgMapa.addEventListener('click', function(e) {
            // Ignorar clic si hubo arrastre (pan) de la imagen
            if (dragDist > 10) return;

            const rect = imgMapa.getBoundingClientRect();
            // Calculamos el porcentaje exacto (0.000 a 1.000)
            const htmlX = (e.clientX - rect.left) / rect.width;
            const htmlY = (e.clientY - rect.top) / rect.height;
            
            // Invertimos la coordenada Y para que Leaflet (el mapa público) lo entienda
            const x = htmlX;
            const y = 1 - htmlY;
            
            // Pegamos los valores con 4 decimales
            inputX.value = x.toFixed(4);
            inputY.value = y.toFixed(4);
            
            // Efecto visual verde de éxito y cerramos
            inputX.style.borderColor = '#10b981'; inputY.style.borderColor = '#10b981';
            setTimeout(() => { inputX.style.borderColor = '#1f2937'; inputY.style.borderColor = '#1f2937'; }, 1500);
            
            modal.classList.remove('is-open');
        });

        // Lógica de Zoom
        let currentZoom = 1;
        const zoomStep = 0.2; // Más suave para el scroll
        const maxZoom = 4;
        const minZoom = 1;
        const modalBody = document.querySelector('.map-modal-body');
        
        let isDragging = false;
        let startX, startY, startScrollLeft, startScrollTop;
        let baseMapWidth = 0;
        let baseMapHeight = 0;

        // --- Arrastrar para mover (Pan) ---
        modalBody.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return; // Solo clic izquierdo
            isDragging = true;
            dragDist = 0;
            startX = e.clientX;
            startY = e.clientY;
            startScrollLeft = modalBody.scrollLeft;
            startScrollTop = modalBody.scrollTop;
            modalBody.style.cursor = 'grabbing';
            e.preventDefault(); // Evitar comportamientos por defecto como seleccionar
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            dragDist += Math.abs(dx) + Math.abs(dy); // Acumulamos distancia
            
            modalBody.scrollLeft = startScrollLeft - dx;
            modalBody.scrollTop = startScrollTop - dy;
            
            startX = e.clientX;
            startY = e.clientY;
            startScrollLeft = modalBody.scrollLeft;
            startScrollTop = modalBody.scrollTop;
        });

        window.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                modalBody.style.cursor = 'crosshair';
            }
        });

        document.getElementById('btnZoomIn').addEventListener('click', () => {
            applyZoom(currentZoom + 0.5);
        });
        document.getElementById('btnZoomOut').addEventListener('click', () => {
            applyZoom(currentZoom - 0.5);
        });

        // *** NUEVO: Zoom con la rueda del ratón ***
        modalBody.addEventListener('wheel', function(e) {
            e.preventDefault(); // Evita que la página de atrás se mueva

            if (e.deltaY < 0) { // Rueda hacia arriba -> Zoom In
                applyZoom(currentZoom + zoomStep, e.clientX, e.clientY);
            } else { // Rueda hacia abajo -> Zoom Out
                applyZoom(currentZoom - zoomStep, e.clientX, e.clientY);
            }
        }, { passive: false }); // passive:false es necesario para preventDefault

        function applyZoom(newZoom, mouseX, mouseY) {
            const oldZoom = currentZoom;
            currentZoom = Math.max(minZoom, Math.min(maxZoom, newZoom));
            
            if (currentZoom === oldZoom) return; // Sin cambios
            
            // Si estamos en 100% justo antes de hacer zoom, capturamos su tamaño real en pantalla
            if (oldZoom === 1) {
                baseMapWidth = imgMapa.clientWidth;
                baseMapHeight = imgMapa.clientHeight;
            }
            
            document.getElementById('zoomNivel').innerText = Math.round(currentZoom * 100) + '%';
            
            const rectBefore = imgMapa.getBoundingClientRect();
            const bodyRect = modalBody.getBoundingClientRect();

            // Si se usa botones (+ y -), hacemos zoom hacia el centro del contenedor
            if (mouseX === undefined) mouseX = bodyRect.left + bodyRect.width / 2;
            if (mouseY === undefined) mouseY = bodyRect.top + bodyRect.height / 2;

            // Porcentaje (0-1) del punto bajo el mouse en la imagen actual
            const pctX = (mouseX - rectBefore.left) / rectBefore.width;
            const pctY = (mouseY - rectBefore.top) / rectBefore.height;
            
            if (currentZoom === 1) {
                imgMapa.style.width = ''; imgMapa.style.height = ''; imgMapa.style.maxWidth = '100%'; imgMapa.style.maxHeight = '80vh';
                modalBody.style.textAlign = 'center';
            } else {
                // Fallback de seguridad por si el tamaño no cargó
                if (!baseMapWidth) { baseMapWidth = imgMapa.clientWidth || 800; baseMapHeight = imgMapa.clientHeight || 600; }
                
                imgMapa.style.maxWidth = 'none'; imgMapa.style.maxHeight = 'none';
                imgMapa.style.width = (baseMapWidth * currentZoom) + 'px';
                imgMapa.style.height = (baseMapHeight * currentZoom) + 'px';
                modalBody.style.textAlign = 'left';
            }

            // Forzamos al navegador a recalcular el tamaño
            void imgMapa.offsetWidth; 
            
            const rectAfter = imgMapa.getBoundingClientRect();
            
            if (currentZoom > 1) {
                // Cuánto se desfasa el punto que antes estaba bajo el mouse
                const diffX = mouseX - (rectAfter.left + (rectAfter.width * pctX));
                const diffY = mouseY - (rectAfter.top + (rectAfter.height * pctY));
                
                // Ajustamos el scroll para compensar y mantener el punto bajo el cursor
                modalBody.scrollLeft -= diffX;
                modalBody.scrollTop -= diffY;
            }
        }

        // --- LÓGICA DE PREVISUALIZACIÓN DE FOTOS ---
        let selectedFiles = [];

        document.getElementById('inputFotos').addEventListener('change', function() {
            selectedFiles = Array.from(this.files);
            
            if (selectedFiles.length > 6) {
                alert('Solo puedes subir un máximo de 6 fotos a la vez. Se guardarán las 6 primeras.');
                selectedFiles = selectedFiles.slice(0, 6);
                actualizarInputFiles();
            }

            renderPreview();
        });

        function actualizarInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            document.getElementById('inputFotos').files = dt.files;
        }

        function renderPreview() {
            const preview = document.getElementById('previewContainer');
            preview.innerHTML = '';
            
            selectedFiles.forEach((file, i) => {
                const url = URL.createObjectURL(file);
                const div = document.createElement('div');
                div.className = 'foto-card';
                div.draggable = true;
                div.dataset.index = i;
                
                div.innerHTML = `
                    <img src="${url}" alt="Previsualización">
                    ${i === 0 ? '<div class="badge-principal">Principal</div>' : ''}
                    <div class="foto-meta"><span style="color:#93c5fd">A subir...</span><span>${(file.size/1024).toFixed(1)} KB</span></div>
                `;
                
                div.addEventListener('dragstart', () => { div.style.opacity = '0.5'; div.classList.add('dragging'); });
                div.addEventListener('dragend', () => { div.style.opacity = '1'; div.classList.remove('dragging'); document.querySelectorAll('.foto-card').forEach(c => c.classList.remove('drag-over')); });
                div.addEventListener('dragover', (e) => e.preventDefault());
                div.addEventListener('dragenter', (e) => { e.preventDefault(); if (!div.classList.contains('dragging')) div.classList.add('drag-over'); });
                div.addEventListener('dragleave', () => div.classList.remove('drag-over'));
                div.addEventListener('drop', (e) => {
                    e.preventDefault(); div.classList.remove('drag-over');
                    const draggingCard = document.querySelector('.dragging');
                    if (draggingCard && draggingCard !== div) {
                        const fromIndex = parseInt(draggingCard.dataset.index);
                        const toIndex = parseInt(div.dataset.index);
                        
                        const [movedItem] = selectedFiles.splice(fromIndex, 1);
                        selectedFiles.splice(toIndex, 0, movedItem);
                        
                        actualizarInputFiles();
                        renderPreview();
                    }
                });

                preview.appendChild(div);
            });
        }

        // --- LÓGICA DE PESTAÑAS CEREBRO IA ---
        
        function autoExpandTextarea(el) {
            // Evitar saltos de scroll en la pantalla al recalcular
            const scrollPos = window.scrollY || document.documentElement.scrollTop;
            el.style.height = '0px'; // Forzar recalculo hacia abajo
            el.style.height = (el.scrollHeight) + 'px';
            window.scrollTo(0, scrollPos);
        }
        
        let tabCounter = 0;
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
        agregarContexto("Principal");

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

        // --- LÓGICA DE PROVINCIAS Y DISTRITOS DEPENDIENTES ---
        document.addEventListener('DOMContentLoaded', () => {
            const DISTRITOS_POR_PROVINCIA = {
                "Tacna": ["Tacna", "Alto de la Alianza", "Calana", "Ciudad Nueva", "Coronel Gregorio Albarracín Lanchipa", "Inclán", "Pachía", "Palca", "Pocollay", "Sama", "La Yarada-Los Palos"],
                "Tarata": ["Tarata", "Chucatamani", "Estique", "Estique-Pampa", "Sitajara", "Susapaya", "Tarucachi", "Ticaco"],
                "Candarave": ["Candarave", "Cairani", "Camilaca", "Curibaya", "Huanuara", "Quilahuani"],
                "Jorge Basadre": ["Locumba", "Ilabaya", "Ite"]
            };

            const selectProvincia = document.getElementById('selectProvincia');
            const selectDistrito = document.getElementById('selectDistrito');

            Object.keys(DISTRITOS_POR_PROVINCIA).forEach(prov => {
                const option = document.createElement('option');
                option.value = prov;
                option.textContent = prov;
                selectProvincia.appendChild(option);
            });

            selectProvincia.addEventListener('change', function() {
                const provinciaSeleccionada = this.value;
                const distritos = DISTRITOS_POR_PROVINCIA[provinciaSeleccionada] || [];
                selectDistrito.innerHTML = '<option value="">Selecciona un distrito...</option>';
                distritos.forEach(dist => {
                    const option = document.createElement('option');
                    option.value = dist;
                    option.textContent = dist;
                    selectDistrito.appendChild(option);
                });
                selectDistrito.disabled = false;
            });
            selectProvincia.dispatchEvent(new Event('change'));
        });
        
        // Carga dinámica de segmentos
        async function cargarSegmentosAdmin() {
            try {
                const tqBuster = encodeURIComponent(`select * offset 0`);
                const resp = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&tq=${tqBuster}&sheet=SEGMENTOS&range=A:D&headers=1`);
                const json = parseGviz(await resp.text());
                const sel = document.getElementById('selectSegmento');
                sel.innerHTML = '';
                (json.table.rows || []).forEach(r => {
                    if (!r.c) return;
                    const nom = r.c[1]?.v, tab = r.c[2]?.v, act = String(r.c[3]?.v||'').toUpperCase();
                    if (tab && (act === 'SI' || act === '1' || act === 'TRUE')) {
                        sel.innerHTML += `<option value="${tab}">${nom}</option>`;
                    }
                });
            } catch(e) { document.getElementById('selectSegmento').innerHTML = '<option value="">Error al cargar</option>'; }
        }
        cargarSegmentosAdmin();
    </script>
</body>
</html>