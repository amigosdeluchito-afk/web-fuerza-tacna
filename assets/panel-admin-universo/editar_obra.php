<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config.php';
require_login();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/vendor/autoload.php';
    $rutaCredenciales = __DIR__ . '/data/credenciales.json';
    $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

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
        $x        = (float) str_replace(',', '.', $_POST['x'] ?? '0');
        $y        = (float) str_replace(',', '.', $_POST['y'] ?? '0');
        $carpeta  = $_POST['carpeta'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';

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
            $mensaje = '<div class="msg-success">¡Obra actualizada con éxito en Google Sheets! Puedes seguir editando otras o ir a la pestaña de Fotos.</div>';
        } else {
            $mensaje = '<div class="msg-error">Error: Fila o segmento no válido.</div>';
        }
    } catch (Throwable $e) {
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
        input, select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #020617; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus, select:focus { border-color: #2563eb; }
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
    </style>
</head>
<body>
    <header class="app-header">
      <nav>
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php">➕ Agregar Obra</a>
        <a href="editar_obra.php" class="active">✏️ Editar Obra</a>
        <a href="usuarios.php">👤 Usuarios</a>
        <a href="historial.php">🕒 Historial</a>
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
                <input type="hidden" name="descripcion" id="formDesc">

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
                </select>

                <label>Monto Referencial (S/):</label>
                <input type="text" name="monto" id="inputMonto">

                <div class="row">
                    <div><label>Distrito:</label><input type="text" name="distrito" id="inputDistrito"></div>
                    <div><label>Provincia:</label><input type="text" name="provincia" id="inputProvincia"></div>
                </div>

                <div class="row">
                    <div><label>Coordenada X (Longitud):</label><input type="text" name="x" id="inputX"></div>
                    <div><label>Coordenada Y (Latitud):</label><input type="text" name="y" id="inputY"></div>
                </div>
                
                <button type="button" id="btnAbrirMapa" class="btn-mapa">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    Abrir Mapa para Reubicar el Pin
                </button>

                <button type="submit" class="btn-submit">💾 Guardar Cambios en Excel</button>
            </form>
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
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:json&sheet=`;
        const SEGMENTOS = [
            { key: "EDUCACION", nombre: "Educación" },
            { key: "VIAS",      nombre: "Vías y Caminos" },
            { key: "AGUA Y SANEAMIENTO", nombre: "Agua y Saneamiento" },
            { key: "TRANSPORTE", nombre: "Transporte" },
            { key: "AGRICULTURA", nombre: "Agricultura" },
            { key: "SOCIAL",      nombre: "Social" }
        ];
        const obrasPorSegmento = {};

        function parseGviz(text) {
            const m = text.match(/setResponse\(([\s\S]+)\);?/);
            if (!m) throw new Error("Error parseo");
            return JSON.parse(m[1]);
        }

        async function cargarDataInicial() {
            const segmentoEl = document.getElementById('selectSegmento');
            
            for (const seg of SEGMENTOS) {
                try {
                    const resp = await fetch(SHEET_BASE_URL + encodeURIComponent(seg.key));
                    const txt = await resp.text();
                    const json = parseGviz(txt);
                    
                    const rows = json.table.rows || [];
                    obrasPorSegmento[seg.key] = rows.map(r => {
                        if(!r || !r.c) return {};
                        return {
                            nombre:    r.c[0]?.v || "",
                            estado:    r.c[1]?.v || "",
                            monto:     r.c[2]?.v || "",
                            x:         r.c[3]?.v || "",
                            y:         r.c[4]?.v || "",
                            provincia: r.c[5]?.v || "",
                            distrito:  r.c[6]?.v || "",
                            carpeta:   r.c[7]?.v || "",
                            desc:      r.c[8]?.v || ""
                        };
                    });
                } catch (e) { console.error("Error al cargar " + seg.key, e); }
            }

            segmentoEl.innerHTML = '<option value="">Selecciona segmento...</option>';
            SEGMENTOS.forEach(seg => {
                const opt = document.createElement("option");
                opt.value = seg.key;
                opt.textContent = seg.nombre;
                segmentoEl.appendChild(opt);
            });
            segmentoEl.disabled = false;
        }

        document.getElementById('selectSegmento').addEventListener('change', function() {
            const obraEl = document.getElementById('selectObra');
            const segmento = this.value;
            document.getElementById('formEditar').style.display = 'none';

            if (!segmento) {
                obraEl.innerHTML = '<option value="">Primero elige segmento...</option>';
                obraEl.disabled = true;
                return;
            }

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
        });

        document.getElementById('selectObra').addEventListener('change', function() {
            const segmento = document.getElementById('selectSegmento').value;
            const idx = this.value;
            const formEditar = document.getElementById('formEditar');
            
            if (idx === "") { formEditar.style.display = 'none'; return; }

            const item = obrasPorSegmento[segmento][idx];
            
            document.getElementById('formSegmento').value = segmento;
            document.getElementById('formFila').value = parseInt(idx) + 2; 
            document.getElementById('formCarpeta').value = item.carpeta;
            document.getElementById('formDesc').value = item.desc;

            document.getElementById('inputNombre').value = item.nombre;
            
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

            document.getElementById('inputMonto').value = item.monto;
            document.getElementById('inputDistrito').value = item.distrito;
            document.getElementById('inputProvincia').value = item.provincia;
            document.getElementById('inputX').value = item.x ? String(item.x).replace(',', '.') : '';
            document.getElementById('inputY').value = item.y ? String(item.y).replace(',', '.') : '';

            formEditar.style.display = 'block';
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

        // Auto-inicializar 
        document.addEventListener("DOMContentLoaded", cargarDataInicial);
    </script>
</body>
</html>