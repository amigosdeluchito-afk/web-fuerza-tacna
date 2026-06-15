<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Cartográfico – Panel Admin</title>
    <link href="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css" rel="stylesheet" />
    <style>
        body { margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #fff; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .app-header { flex-shrink: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        
        .main-container { flex: 1; display: flex; position: relative; }
        #map { flex: 1; background: #0f172a; }
        
        .instrucciones { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(15, 23, 42, 0.9); padding: 10px 20px; border-radius: 8px; z-index: 10; font-size: 14px; border: 1px solid #3b82f6; color: #93c5fd; pointer-events: none; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        
        .panel-formulario { width: 350px; background: #0f172a; border-left: 1px solid #1e293b; padding: 25px; display: none; flex-direction: column; overflow-y: auto; box-shadow: -5px 0 25px rgba(0,0,0,0.5); z-index: 20; }
        .panel-formulario.active { display: flex; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .form-control { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #334155; background: #020617; color: #fff; box-sizing: border-box; font-size: 14px; outline: none; }
        .form-control:focus { border-color: #3b82f6; }
        .form-control[readonly] { color: #3b82f6; font-family: monospace; font-weight: bold; }
        
        .btn { padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 14px; transition: 0.2s; }
        .btn-primary { background: #10b981; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-secondary { background: #334155; color: white; margin-top: 10px; }
        .btn-secondary:hover { background: #475569; }
    </style>
</head>
<body>
    <header class="app-header">
      <style>.nav-scroll::-webkit-scrollbar { height: 4px; } .nav-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }</style>
      <nav class="nav-scroll" style="display:flex; align-items:center; overflow-x:auto; white-space:nowrap; width:100%; margin-right:15px; scrollbar-width:thin; scrollbar-color:#334155 transparent; padding-bottom: 4px;">
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php">➕ Agregar Obra</a>
        <a href="ia_respuestas.php">🧠 Cerebro IA</a>
        <a href="gestor-cartografico.php" class="active">📍 Gestor Mapa</a>
      </nav>
      <div class="user">
        <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af; text-decoration:none;">Salir</a>
      </div>
    </header>
    
    <div class="main-container">
        <div class="instrucciones">📍 Haz clic en cualquier lugar de Tacna para anclar un nuevo Titán</div>
        <div id="map"></div>
        
        <div class="panel-formulario" id="panelFormulario">
            <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">➕ Agregar Referencia</h3>
            <p style="font-size: 12px; color: #38bdf8;">Las coordenadas han sido capturadas automáticamente desde el mapa.</p>
            
            <form id="refForm">
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Latitud</label>
                        <input type="text" id="inpLat" class="form-control" readonly>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Longitud</label>
                        <input type="text" id="inpLng" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nombre Oficial (Completo)</label>
                    <input type="text" id="inpNombre" class="form-control" required placeholder="Ej: Hospital Regional Hipólito Unanue">
                </div>
                <div class="form-group">
                    <label>Nombre Corto (Visual)</label>
                    <input type="text" id="inpCorto" class="form-control" required placeholder="Ej: Hosp. Unanue">
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <input type="text" id="inpCat" class="form-control" required value="General">
                </div>
                <div class="form-group">
                    <label>Icono</label>
                    <select id="inpIcon" class="form-control">
                        <option value="hito">📍 Hito General</option>
                        <option value="salud">🏥 Salud</option>
                        <option value="educacion">🎓 Educación Superior</option>
                        <option value="gobierno">🏛️ Gobierno / Cívico</option>
                        <option value="deporte">⚽ Deporte</option>
                        <option value="transporte">🚌 Transporte</option>
                        <option value="comercio">🛒 Comercio / Mercado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Zoom Mínimo de Aparición</label>
                    <input type="number" id="inpZoom" class="form-control" value="11" min="8" max="18">
                    <small style="color: #64748b; font-size: 11px;">10 = Muy Lejos, 13 = Cerca, 15 = Barrio</small>
                </div>
                
                <button type="submit" class="btn btn-primary" id="btnGuardar" style="margin-top: 10px;">💾 Guardar Referencia</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarPanel()">❌ Cancelar</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
    <script>
        let map;
        let activeMarker = null;

        async function initGestorCartografico() {
            // Importar PMTiles dinámicamente (compatible con ES Modules)
            const pmtiles = await import('https://unpkg.com/pmtiles@3.0.6/dist/index.js');
            window.pmtiles = pmtiles;
            console.log("typeof pmtiles:", typeof window.pmtiles);

            // Configuración PMTiles y MapLibre
            const protocol = new pmtiles.Protocol();
            maplibregl.addProtocol('pmtiles', protocol.tile);
            
            const mapStyle = {
                version: 8,
                glyphs: "https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf",
                sources: {
                    "protomaps": { type: "vector", url: "pmtiles://../data/pmtiles_proxy.php" },
                    "referencias": { type: "geojson", data: "mapa_referencias_api.php?action=geojson" }
                },
                layers: [
                    { id: "bg", type: "background", paint: { "background-color": "#F2EFE9" } },
                    { id: "water", type: "fill", source: "protomaps", "source-layer": "water", paint: { "fill-color": "#B9D9F7" } },
                    { id: "parks", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["any", ["==", ["get", "kind"], "park"], ["==", ["get", "kind"], "school"], ["==", ["get", "kind"], "hospital"]], "paint": { "fill-color": ["match", ["get", "kind"], "hospital", "#F4C7C3", "school", "#F6E6A8", "university", "#F6E6A8", "#C2E2BA"] } },
                    { id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": "#e6e4df", "fill-opacity": 0.6 } },
                    { id: "roads-minor", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": "#FFFFFF", "line-width": 2 } },
                    { id: "roads-major", type: "line", source: "protomaps", "source-layer": "roads", filter: ["in", ["get", "kind"], "highway", "major_road"], paint: { "line-color": "#CDD7E3", "line-width": 4 } },
                    { id: "places-text", type: "symbol", source: "protomaps", "source-layer": "places", layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 14}, paint: {"text-color": "#1e293b", "text-halo-color": "#F2EFE9", "text-halo-width": 2} },
                    { id: "roads-text", type: "symbol", source: "protomaps", "source-layer": "roads", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 11, "symbol-placement": "line"}, paint: {"text-color": "#3f3f46", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 12}, paint: {"text-color": "#666666", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "ref-circles", type: "circle", source: "referencias", paint: { "circle-color": "#10b981", "circle-radius": 6, "circle-stroke-width": 2, "circle-stroke-color": "#020617" } },
                    { id: "ref-labels", type: "symbol", source: "referencias", layout: { "text-field": ["get", "short_name"], "text-font": ["Noto Sans Regular"], "text-size": 13, "text-offset": [0, 1], "text-anchor": "top" }, paint: { "text-color": "#1e293b", "text-halo-color": "#ffffff", "text-halo-width": 3 } }
                ]
            };

            console.log("--- INICIO AUDITORÍA DE CAPAS ---");
            mapStyle.layers.forEach((layerConfig, index) => {
                console.log(`\nCapa Index: [${index}]`);
                console.log(`ID: ${layerConfig.id}`);
                console.log(`Source-layer: ${layerConfig['source-layer'] || 'N/A'}`);
                if (layerConfig.filter) console.log(`Filter: ${JSON.stringify(layerConfig.filter)}`);
                console.log(`Objeto Completo:\n`, JSON.stringify(layerConfig, null, 2));
            });
            console.log("--- FIN AUDITORÍA DE CAPAS ---\n");

            map = new maplibregl.Map({
                container: 'map',
                style: mapStyle,
                center: [-70.2528, -18.0146],
                zoom: 12
            });
            
            // Evento Click en el Mapa
            map.on('click', (e) => {
                const lat = e.lngLat.lat.toFixed(6);
                const lng = e.lngLat.lng.toFixed(6);
                
                if (activeMarker) activeMarker.remove();
                activeMarker = new maplibregl.Marker({ color: '#ef4444' }).setLngLat([lng, lat]).addTo(map);
                
                document.getElementById('inpLat').value = lat;
                document.getElementById('inpLng').value = lng;
                document.getElementById('panelFormulario').classList.add('active');
                
                // =========================================================
                // 🔍 AUTOCOMPLETADO INTELIGENTE (Priorización Vectorial)
                // =========================================================
                const features = map.queryRenderedFeatures(e.point);
                let bestMatch = null;
                let priority = 99;

                for (let f of features) {
                    const layer = f.sourceLayer;
                    const props = f.properties || {};
                    const name = props.name || '';
                    const kind = props.kind || '';

                    if (layer === 'pois' && name && priority > 1) { bestMatch = { name, kind, layer }; priority = 1; } 
                    else if (layer === 'places' && name && priority > 2) { bestMatch = { name, kind, layer }; priority = 2; } 
                    else if (layer === 'landuse' && kind && priority > 3) { bestMatch = { name: name || kind.toUpperCase(), kind, layer }; priority = 3; } 
                    else if (layer === 'roads' && name && priority > 4) { bestMatch = { name, kind, layer }; priority = 4; }
                }

                let sugName = '', sugCorto = '', sugCat = 'General', sugIcon = 'hito';

                if (bestMatch) {
                    sugName = bestMatch.name;
                    sugCorto = bestMatch.name;
                    const k = bestMatch.kind;

                    if (['hospital', 'clinic'].includes(k)) {
                        sugCat = 'Salud'; sugIcon = 'salud';
                        sugCorto = 'Hosp. ' + (sugName.replace(/(hospital|clinica|centro de salud|puesto de salud)\s+/i, '').split(' ')[0] || 'Salud');
                    } else if (['school', 'university', 'college', 'kindergarten'].includes(k)) {
                        sugCat = 'Educación'; sugIcon = 'educacion';
                    } else if (['townhall', 'town_hall', 'police', 'fire_station'].includes(k)) {
                        sugCat = 'Gobierno'; sugIcon = 'gobierno';
                        if (k === 'townhall' || k === 'town_hall') sugCorto = 'Muni. ' + (sugName.split(' ')[0] || '');
                    } else if (['stadium', 'pitch', 'park', 'sports_centre'].includes(k)) {
                        sugCat = 'Deporte'; sugIcon = 'deporte';
                    } else if (['marketplace', 'market'].includes(k)) {
                        sugCat = 'Mercado'; sugIcon = 'comercio';
                    } else if (['bus_station', 'aerodrome'].includes(k)) {
                        sugCat = 'Transporte'; sugIcon = 'transporte';
                    } else if (bestMatch.layer === 'roads') {
                        sugCat = 'Vía'; sugIcon = 'hito';
                    }
                }

                document.getElementById('inpNombre').value = sugName;
                document.getElementById('inpCorto').value = sugCorto;
                document.getElementById('inpCat').value = sugCat;
                document.getElementById('inpIcon').value = sugIcon;

                document.getElementById('inpNombre').focus();
            });
        }
        
        initGestorCartografico();

        function cerrarPanel() {
            document.getElementById('panelFormulario').classList.remove('active');
            if (activeMarker) activeMarker.remove();
            document.getElementById('refForm').reset();
            document.getElementById('inpZoom').value = 11;
            document.getElementById('inpCat').value = 'General';
            document.getElementById('inpIcon').value = 'hito';
        }

        // Enviar Formulario
        document.getElementById('refForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            btn.disabled = true; btn.textContent = '⏳ Guardando...';
            
            const payload = { lat: parseFloat(document.getElementById('inpLat').value), lng: parseFloat(document.getElementById('inpLng').value), nombre: document.getElementById('inpNombre').value, nombre_corto: document.getElementById('inpCorto').value, categoria: document.getElementById('inpCat').value, icon_type: document.getElementById('inpIcon').value, min_zoom: parseInt(document.getElementById('inpZoom').value) };
            
            try {
                const res = await fetch('mapa_referencias_api.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.ok) {
                    if (map && map.getSource('referencias')) map.getSource('referencias').setData('mapa_referencias_api.php?action=geojson');
                    cerrarPanel();
                } else { alert('Error: ' + data.error); }
            } catch (err) { alert('Error de conexión'); } 
            finally { btn.disabled = false; btn.textContent = '💾 Guardar Referencia'; }
        });
    </script>
</body>
</html>