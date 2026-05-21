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
        input, select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #020617; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus, select:focus { border-color: #2563eb; }
        .btn-submit { margin-top: 25px; width: 100%; padding: 12px; background: #2563eb; color: #f9fafb; border: none; font-weight: 600; font-size: 14px; border-radius: 999px; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #1d4ed8; }
        .msg-success { background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #059669; }
        .msg-error { background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #dc2626; }
        .row { display: flex; gap: 15px; }
        .row > div { flex: 1; }
        
        /* Botón para abrir el mapa */
        .btn-mapa { background: transparent; border: 1px solid #3b82f6; color: #60a5fa; padding: 8px 12px; border-radius: 8px; font-size: 13px; cursor: pointer; transition: all 0.2s; margin-top: 10px; width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-mapa:hover { background: rgba(59, 130, 246, 0.1); color: #93c5fd; }
        
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
        #pinesContainer { pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
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
    </style>
</head>
<body>
    <header class="app-header">
      <nav>
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php" class="active">➕ Agregar Obra</a>
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
            <h1>Agregar Nueva Obra</h1>
            <?= $mensaje ?>
        
            <form action="guardar_obra.php" method="POST">
                <label>Segmento (Hoja de Excel destino):</label>
                <select name="segmento" id="selectSegmento" required>
                    <option value="EDUCACION">Educación</option>
                    <option value="AGUA Y SANEAMIENTO">Agua y Saneamiento</option>
                    <option value="TRANSPORTE">Transporte</option>
                    <option value="AGRICULTURA">Agricultura</option>
                    <option value="SOCIAL">Social</option>
                    <option value="VIAS">Vías y Caminos</option>
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

                <label>Monto Referencial (S/):</label>
                <input type="text" name="monto" placeholder="Ej. 1,500,000.00">

                <div class="row">
                    <div><label>Distrito:</label><input type="text" name="distrito" placeholder="Ej. Gregorio Albarracín"></div>
                    <div><label>Provincia:</label><input type="text" name="provincia" value="Tacna"></div>
                </div>

                <div class="row">
                    <div><label>Coordenada X (Longitud):</label><input type="text" name="x" placeholder="Ej. 0.345"></div>
                    <div><label>Coordenada Y (Latitud):</label><input type="text" name="y" placeholder="Ej. 0.678"></div>
                </div>
                
                <button type="button" id="btnAbrirMapa" class="btn-mapa">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    Abrir Mapa para ubicar Coordenadas
                </button>

                <button type="submit" class="btn-submit">Guardar Obra en Excel</button>
            </form>
        </div>
    </main>

    <!-- VENTANA EMERGENTE DEL MAPA -->
    <div id="modalMapa" class="map-modal">
        <div class="map-modal-header">
            <h3>🎯 Haz clic en el lugar exacto de la obra</h3>
            <button type="button" id="btnCerrarMapa" class="btn-close-map">Cerrar</button>
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
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:json&sheet=`;

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

        async function cargarPinesExistentes() {
            pinesContainer.innerHTML = '';
            loadingPines.style.display = 'block';
            const segmento = document.getElementById('selectSegmento').value;
            
            try {
                const resp = await fetch(SHEET_BASE_URL + encodeURIComponent(segmento));
                const txt = await resp.text();
                const json = parseGviz(txt);
                
                (json.table.rows || []).forEach(r => {
                    if (!r.c) return;
                    const nombre = r.c[0]?.v || '';
                    const x = parseFloat(r.c[3]?.v);
                    const y = parseFloat(r.c[4]?.v);
                    
                    if (!isNaN(x) && !isNaN(y) && x >= 0 && x <= 1 && y >= 0 && y <= 1) {
                        pinesContainer.innerHTML += `<div class="pin-existente" style="left: ${x * 100}%; top: ${y * 100}%;"><div class="pin-label">${nombre}</div></div>`;
                    }
                });
            } catch (e) { console.error(e); }
            loadingPines.style.display = 'none';
        }

        document.getElementById('btnAbrirMapa').addEventListener('click', () => { 
            modal.classList.add('is-open'); 
            cargarPinesExistentes(); 
        });
        document.getElementById('btnCerrarMapa').addEventListener('click', () => { modal.classList.remove('is-open'); });

        imgMapa.addEventListener('click', function(e) {
            const rect = imgMapa.getBoundingClientRect();
            // Calculamos el porcentaje exacto (0.000 a 1.000)
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            
            // Pegamos los valores con 4 decimales
            inputX.value = x.toFixed(4);
            inputY.value = y.toFixed(4);
            
            // Efecto visual verde de éxito y cerramos
            inputX.style.borderColor = '#10b981'; inputY.style.borderColor = '#10b981';
            setTimeout(() => { inputX.style.borderColor = '#1f2937'; inputY.style.borderColor = '#1f2937'; }, 1500);
            
            modal.classList.remove('is-open');
        });
    </script>
</body>
</html>