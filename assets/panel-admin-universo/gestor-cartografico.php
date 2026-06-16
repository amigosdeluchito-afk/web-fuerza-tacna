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
        
        .panel-lista { width: 350px; background: #0f172a; border-left: 1px solid #1e293b; padding: 25px; display: none; flex-direction: column; overflow-y: auto; box-shadow: -5px 0 25px rgba(0,0,0,0.5); z-index: 20; }
        .panel-lista.active { display: flex; }
        
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
        <!-- Control de Modos (Hitos vs Vías) -->
        <div style="position:absolute; top:20px; left:20px; z-index:10; display:flex; gap:5px; background:#0f172a; padding:5px; border-radius:8px; border:1px solid #334155; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
            <button id="btnModeHitos" class="btn btn-primary" style="padding:6px 12px; margin:0;" onclick="setMode('hitos')">📍 Hitos</button>
            <button id="btnModeVias" class="btn" style="padding:6px 12px; margin:0; background:transparent; color:#94a3b8;" onclick="setMode('vias')">🛣️ Vías</button>
        </div>

        <div class="instrucciones">📍 Haz clic en cualquier lugar de Tacna para anclar un nuevo Titán</div>
        <button id="btnVerLista" onclick="abrirListaActual()" style="position:absolute; top: 20px; right: 20px; z-index: 10; background: #0f172a; border: 1px solid #3b82f6; color: #93c5fd; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: 0.2s;">📋 Ver Lista de Puntos</button>
        <div id="map"></div>
        
        <!-- Panel Flotante de Dibujo RV -->
        <div id="rvDrawPanel" style="display:none; position:absolute; bottom:30px; left:50%; transform:translateX(-50%); z-index:10; background:rgba(15,23,42,0.9); padding:10px 15px; border-radius:8px; border:1px solid #3b82f6; box-shadow:0 4px 15px rgba(0,0,0,0.5); gap:10px; align-items:center;">
            <span style="color:#93c5fd; font-size:13px; font-weight:bold; margin-right:10px; min-width:60px;" id="rvDrawCount">0 puntos</span>
            <button class="btn btn-secondary" style="margin:0; padding:6px 12px; background:#475569;" onclick="rvUndo()">↩️ Deshacer</button>
            <button class="btn btn-secondary" style="margin:0; padding:6px 12px; background:#ef4444;" onclick="rvCancel()">❌ Cancelar</button>
            <button class="btn btn-primary" id="btnRvFinish" style="margin:0; padding:6px 12px; background:#10b981;" onclick="rvFinish()" disabled>✅ Finalizar Tramo</button>
        </div>

        <div class="panel-formulario" id="panelFormulario">
            <h3 id="formTitle" style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">➕ Agregar Referencia</h3>
            <p id="formSub" style="font-size: 12px; color: #38bdf8;">Las coordenadas han sido capturadas automáticamente desde el mapa.</p>
            
            <form id="refForm">
                <input type="hidden" id="refId" value="">
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
                    <select id="inpCat" class="form-control" required>
                        <option value="General">📍 General</option>
                        <option value="Salud">🏥 Salud</option>
                        <option value="Educación">🎓 Educación</option>
                        <option value="Gobierno">🏛️ Gobierno</option>
                        <option value="Deporte">⚽ Deporte</option>
                        <option value="Transporte">🚌 Transporte</option>
                        <option value="Mercado">🛒 Mercado</option>
                        <option value="Parque">🌳 Parque / Plaza</option>
                    </select>
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
                        <option value="parque">🌳 Parque / Plaza</option>
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
        
        <div class="panel-lista" id="panelLista">
            <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">📋 Referencias Guardadas</h3>
            <div id="listaReferenciasContainer" style="flex: 1; overflow-y: auto; margin-bottom: 15px;"></div>
            <button type="button" class="btn btn-secondary" onclick="cerrarLista()">❌ Volver al Mapa</button>
        </div>
        
        <div class="panel-lista" id="panelListaRV">
            <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">📋 Vías Guardadas</h3>
            <div id="listaRvContainer" style="flex: 1; overflow-y: auto; margin-bottom: 15px;"></div>
            <button type="button" class="btn btn-secondary" onclick="cerrarListaRV()">❌ Volver al Mapa</button>
        </div>
        
        <!-- Panel de Formulario Red Vial -->
        <div class="panel-formulario" id="panelFormularioRV">
            <h3 style="margin-top:0; color:#f8fafc; font-size:18px; border-bottom:1px solid #1e293b; padding-bottom:10px;">🛣️ Guardar Tramo Vial</h3>
            <form id="rvForm">
                <input type="hidden" id="rvId" value="">
                <div class="form-group"><label>Nombre de la Vía (Detectado)</label><input type="text" id="rvNombre" class="form-control" required></div>
                <div class="form-group"><label>Tipo de Vía</label><select id="rvTipo" class="form-control"><option value="Local">Local</option><option value="Provincial">Provincial</option><option value="Regional">Regional</option></select></div>
                <div class="form-group"><label>Estado Actual</label><select id="rvEstado" class="form-control"><option value="En estudios">En estudios</option><option value="Buena Pro">Buena Pro</option><option value="En ejecución">En ejecución</option><option value="Paralizado">Paralizado</option><option value="Transferencia">Transferencia</option><option value="Entregado">Entregado</option></select></div>
                <div class="form-group"><label>Color en Mapa</label><input type="color" id="rvColor" class="form-control" value="#616161" style="padding:0; height:40px;"></div>
                <div class="form-group"><label>Descripción / Observación (Opcional)</label><textarea id="rvDesc" class="form-control" rows="3" placeholder="Contexto de la obra..."></textarea></div>
                <button type="submit" class="btn btn-primary" id="btnGuardarRv" style="margin-top:10px;">💾 Guardar Tramo</button>
                <button type="button" class="btn btn-secondary" onclick="rvCancel()">❌ Cancelar y Descartar</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
    <script>
        let map;
        let activeMarker = null;
        let refsGeoJSON = null;
        let currentMode = 'hitos';
        let rvNodes = [];
        let rvGeoJSON = null;
        
        let isDraggingRVNode = false;
        let draggedRVNodeIndex = -1;
        let justDragged = false;
        let isDraggingControlNode = false;
        let draggedControlIndex = -1;
        let hoveredSegmentIndex = -1;

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
                    "referencias": { type: "geojson", data: "mapa_referencias_api.php?action=geojson" },
                    "tramos-viales": { type: "geojson", data: "mapa_redvial_api.php?action=geojson" },
                    "draw-source": { type: "geojson", data: { type: "Feature", geometry: { type: "LineString", coordinates: [] } } },
                    "draw-line-hit-source": { type: "geojson", data: { type: "FeatureCollection", features: [] } },
                    "draw-points": { type: "geojson", data: { type: "FeatureCollection", features: [] } },
                    "draw-control-source": { type: "geojson", data: { type: "FeatureCollection", features: [] } }
                },
                layers: [
                    { id: "bg", type: "background", paint: { "background-color": "#F2EFE9" } },
                    { id: "water", type: "fill", source: "protomaps", "source-layer": "water", paint: { "fill-color": "#B9D9F7" } },
                    { id: "parks", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["any", ["==", ["get", "kind"], "park"], ["==", ["get", "kind"], "school"], ["==", ["get", "kind"], "hospital"]], "paint": { "fill-color": ["match", ["get", "kind"], "hospital", "#F4C7C3", "school", "#F6E6A8", "university", "#F6E6A8", "#C2E2BA"] } },
                    { id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": "#e6e4df", "fill-opacity": 0.6 } },
                    { id: "roads-minor", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": "#FFFFFF", "line-width": 2 } },
                    { id: "roads-major", type: "line", source: "protomaps", "source-layer": "roads", filter: ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"]], paint: { "line-color": "#CDD7E3", "line-width": 4 } },
                    { id: "places-text", type: "symbol", source: "protomaps", "source-layer": "places", layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 14}, paint: {"text-color": "#1e293b", "text-halo-color": "#F2EFE9", "text-halo-width": 2} },
                    { id: "roads-text", type: "symbol", source: "protomaps", "source-layer": "roads", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 11, "symbol-placement": "line"}, paint: {"text-color": "#3f3f46", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 12}, paint: {"text-color": "#666666", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "tramos-viales-layer", type: "line", source: "tramos-viales", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": ["get", "color"], "line-width": 4 } },
                    { id: "ref-circles", type: "circle", source: "referencias", paint: { "circle-color": "#10b981", "circle-radius": 6, "circle-stroke-width": 2, "circle-stroke-color": "#020617" } },
                    { id: "ref-labels", type: "symbol", source: "referencias", layout: { "text-field": ["get", "short_name"], "text-font": ["Noto Sans Regular"], "text-size": 13, "text-offset": [0, 1], "text-anchor": "top" }, paint: { "text-color": "#1e293b", "text-halo-color": "#ffffff", "text-halo-width": 3 } },
                    { id: "draw-line-layer", type: "line", source: "draw-source", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": "#3b82f6", "line-width": 4, "line-dasharray": [2, 2] } },
                    { id: "draw-line-hit", type: "line", source: "draw-line-hit-source", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-width": 20, "line-color": "rgba(0,0,0,0)" } },
                    { id: "draw-points-layer", type: "circle", source: "draw-points", paint: { "circle-radius": 5, "circle-color": "#ffffff", "circle-stroke-width": 2, "circle-stroke-color": "#3b82f6" } },
                    { id: "draw-points-hit", type: "circle", source: "draw-points", paint: { "circle-radius": 15, "circle-color": "rgba(0,0,0,0)" } },
                    { id: "draw-control-layer", type: "circle", source: "draw-control-source", paint: { "circle-radius": 6, "circle-color": "#ffc300", "circle-stroke-width": 2, "circle-stroke-color": "#801039" } },
                    { id: "draw-control-hit", type: "circle", source: "draw-control-source", paint: { "circle-radius": 16, "circle-color": "rgba(0,0,0,0)" } }
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
                zoom: 12,
                dragRotate: false // Desactiva la manito/rotación del clic derecho
            });
            
            // Bloquear el menú nativo del navegador en el canvas para asegurar el clic derecho
            map.getCanvas().addEventListener('contextmenu', e => e.preventDefault());
            
            // Evento Click en el Mapa
            map.on('click', (e) => {
                if (justDragged) return; // Evita crear un punto nuevo justo después de soltar un arrastre
                
                document.getElementById('panelLista').classList.remove('active');
                document.getElementById('panelListaRV').classList.remove('active');
                
                if (currentMode === 'hitos') {
                const lat = e.lngLat.lat.toFixed(6);
                const lng = e.lngLat.lng.toFixed(6);
                
                if (activeMarker) activeMarker.remove();
                activeMarker = new maplibregl.Marker({ color: '#ef4444' }).setLngLat([lng, lat]).addTo(map);
                
                document.getElementById('refId').value = '';
                document.getElementById('formTitle').textContent = '➕ Agregar Referencia';
                document.getElementById('btnGuardar').textContent = '💾 Guardar Referencia';
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
                    } else if (['park', 'recreation_ground'].includes(k)) {
                        sugCat = 'Parque'; sugIcon = 'parque';
                    } else if (['marketplace', 'market'].includes(k)) {
                        sugCat = 'Mercado'; sugIcon = 'comercio';
                    } else if (['bus_station', 'aerodrome'].includes(k)) {
                        sugCat = 'Transporte'; sugIcon = 'transporte';
                    } else if (bestMatch.layer === 'roads') {
                        sugCat = 'General'; sugIcon = 'hito';
                    }
                }

                document.getElementById('inpNombre').value = sugName;
                document.getElementById('inpCorto').value = sugCorto;
                document.getElementById('inpCat').value = sugCat;
                document.getElementById('inpIcon').value = sugIcon;

                document.getElementById('inpNombre').focus();
                } 
                else if (currentMode === 'vias') {
                    document.getElementById('panelFormularioRV').classList.remove('active');
                    rvNodes.push({ nodo: [e.lngLat.lng, e.lngLat.lat], control: null });
                    
                    if (rvNodes.length === 1) {
                        document.getElementById('rvDrawPanel').style.display = 'flex';
                        // Captura inteligente de nombre con buffer de 8px
                        const bbox = [ [e.point.x - 8, e.point.y - 8], [e.point.x + 8, e.point.y + 8] ];
                        const features = map.queryRenderedFeatures(bbox, { layers: ['roads-major', 'roads-minor'] });
                        let detName = features.find(f => f.properties && f.properties.name)?.properties.name;
                        document.getElementById('rvNombre').value = detName || 'Tramo vial sin nombre';
                    }
                    
                    updateDrawLayer();
                }
            });
            
            // ==========================================
            // EVENTOS DE EDICIÓN AVANZADA (MOVER / ELIMINAR)
            // ==========================================
            

            map.on('mousedown', 'draw-points-hit', (e) => {
                const button = e.originalEvent ? e.originalEvent.button : 0;
                
                console.log('NODE MOUSEDOWN', {
                    eButton: e.button,
                    originalButton: e.originalEvent?.button,
                    currentMode,
                    features: e.features
                });

                if (currentMode !== 'vias' || button !== 0) return;
                e.preventDefault();
                if (e.originalEvent) {
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();
                }
                map.dragPan.disable();
                
                isDraggingRVNode = true;
                draggedRVNodeIndex = e.features[0].properties.index;
                map.getCanvas().style.cursor = 'grabbing';
            });
            
            map.on('mousedown', 'draw-control-hit', (e) => {
                const button = e.originalEvent ? e.originalEvent.button : 0;
                if (currentMode !== 'vias' || button !== 0) return;
                
                e.preventDefault();
                if (e.originalEvent) {
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();
                }
                map.dragPan.disable();
                
                isDraggingControlNode = true;
                draggedControlIndex = e.features[0].properties.segmentIndex;
                map.getCanvas().style.cursor = 'grabbing';
                
                console.log('CONTROL DRAG START', draggedControlIndex);
            });
            
            map.on('mousemove', (e) => {
                if (currentMode !== 'vias') return;
                if (isDraggingRVNode && draggedRVNodeIndex !== -1) {
                    rvNodes[draggedRVNodeIndex].nodo = [e.lngLat.lng, e.lngLat.lat];
                    updateDrawLayer();
                    if (hoveredSegmentIndex !== -1) updateControlPointLayer(hoveredSegmentIndex);
                } else if (isDraggingControlNode && draggedControlIndex !== -1) {
                    rvNodes[draggedControlIndex].control = [e.lngLat.lng, e.lngLat.lat];
                    updateControlPointLayer(draggedControlIndex);
                    updateDrawLayer();
                    console.log('CONTROL MOVING', e.lngLat.lng, e.lngLat.lat);
                } else {
                    const bbox = [[e.point.x - 8, e.point.y - 8], [e.point.x + 8, e.point.y + 8]];
                    const hitNodes = map.queryRenderedFeatures(bbox, { layers: ['draw-points-hit'] });
                    const hitControls = map.queryRenderedFeatures(bbox, { layers: ['draw-control-hit'] });
                    
                    if (hitNodes.length > 0 || hitControls.length > 0) {
                        map.getCanvas().style.cursor = 'move';
                    } else {
                        map.getCanvas().style.cursor = 'crosshair';
                        const hitSegments = map.queryRenderedFeatures(bbox, { layers: ['draw-line-hit'] });
                        if (hitSegments.length > 0) {
                            const idx = hitSegments[0].properties.segmentIndex;
                            if (hoveredSegmentIndex !== idx) {
                                hoveredSegmentIndex = idx;
                                updateControlPointLayer(idx);
                            }
                        } else {
                            if (hoveredSegmentIndex !== -1) {
                                hoveredSegmentIndex = -1;
                                hideControlPointLayer();
                            }
                        }
                    }
                }
            });
            
            window.addEventListener('mouseup', () => {
                if (isDraggingRVNode) {
                    isDraggingRVNode = false;
                    draggedRVNodeIndex = -1;
                    if (map) { map.getCanvas().style.cursor = 'crosshair'; map.dragPan.enable(); }
                    justDragged = true;
                    setTimeout(() => justDragged = false, 100);
                }
                if (isDraggingControlNode) {
                    console.log('CONTROL SAVED', rvNodes[draggedControlIndex].control);
                    isDraggingControlNode = false;
                    draggedControlIndex = -1;
                    if (map) { map.getCanvas().style.cursor = 'crosshair'; map.dragPan.enable(); }
                    justDragged = true;
                    setTimeout(() => justDragged = false, 100);
                }
            });
            
            map.on('contextmenu', 'draw-points-hit', (e) => {
                if (currentMode !== 'vias') return;
                if (confirm("🗑️ ¿Deseas eliminar este vértice del tramo?")) {
                    const idx = e.features[0].properties.index;
                    rvNodes.splice(idx, 1);
                    hoveredSegmentIndex = -1;
                    hideControlPointLayer();
                    updateDrawLayer();
                }
            });
            
            map.on('contextmenu', 'draw-control-hit', (e) => {
                if (currentMode !== 'vias') return;
                
                const idx = e.features[0].properties.segmentIndex;
                
                console.log('REMOVE CURVE', idx);
                console.log('BEFORE CONTROL', rvNodes[idx].control);
                
                rvNodes[idx].control = null;
                
                console.log('AFTER CONTROL', rvNodes[idx].control);
                
                updateControlPointLayer(idx);
                updateDrawLayer();
            });
        }
        
        initGestorCartografico();
        
        // ==========================================
        // LÓGICA DE DIBUJO Y MODO RED VIAL (RV2)
        // ==========================================
        function setMode(mode) {
            if (currentMode === 'vias' && rvNodes.length > 0 && mode === 'hitos') {
                if (!confirm("Tienes un trazo en progreso. ¿Descartarlo?")) return;
                rvCancel();
            }
            currentMode = mode;
            if (map) map.dragPan.enable(); // Seguro anti-bloqueo al cambiar de modo
            document.getElementById('btnModeHitos').className = mode === 'hitos' ? 'btn btn-primary' : 'btn';
            document.getElementById('btnModeHitos').style.background = mode === 'hitos' ? '' : 'transparent';
            document.getElementById('btnModeHitos').style.color = mode === 'hitos' ? '' : '#94a3b8';
            document.getElementById('btnModeVias').className = mode === 'vias' ? 'btn btn-primary' : 'btn';
            document.getElementById('btnModeVias').style.background = mode === 'vias' ? '' : 'transparent';
            document.getElementById('btnModeVias').style.color = mode === 'vias' ? '' : '#94a3b8';
            document.getElementById('btnVerLista').textContent = mode === 'hitos' ? '📋 Ver Lista de Puntos' : '📋 Ver Lista de Vías';
            
            document.getElementById('panelLista').classList.remove('active');
            document.getElementById('panelListaRV').classList.remove('active');
            rvCancel();
            
            document.querySelector('.instrucciones').textContent = mode === 'hitos' 
                ? "📍 Haz clic en el mapa para anclar un nuevo Titán" 
                : "🛣️ Clic para trazar • Arrastra nodos para mover • Clic derecho para borrar";
                
            map.getCanvas().style.cursor = mode === 'vias' ? 'crosshair' : '';
        }

        function getBakedSegment(p0, p1, p2) {
            if (!p1) return [p0, p2];
            let baked = [];
            const pasos = 20;
            for (let j = 0; j <= pasos; j++) {
                let t = j / pasos;
                let lng = Math.pow(1 - t, 2) * p0[0] + 2 * (1 - t) * t * p1[0] + Math.pow(t, 2) * p2[0];
                let lat = Math.pow(1 - t, 2) * p0[1] + 2 * (1 - t) * t * p1[1] + Math.pow(t, 2) * p2[1];
                baked.push([lng, lat]);
            }
            return baked;
        }

        function getBakedCoords(nodes) {
            if (nodes.length === 0) return [];
            if (nodes.length === 1) return [nodes[0].nodo];
            let baked = [];
            for (let i = 0; i < nodes.length - 1; i++) {
                let segment = getBakedSegment(nodes[i].nodo, nodes[i].control, nodes[i + 1].nodo);
                if (i > 0) segment.shift(); // Evitar duplicar el punto de unión
                baked.push(...segment);
            }
            return baked;
        }

        function updateControlPointLayer(idx) {
            if (idx < 0 || idx >= rvNodes.length - 1) return hideControlPointLayer();
            let p0 = rvNodes[idx].nodo;
            let p1 = rvNodes[idx].control;
            let p2 = rvNodes[idx+1].nodo;
            
            let controlPoint = p1 ? p1 : [(p0[0] + p2[0]) / 2, (p0[1] + p2[1]) / 2];
            
            console.log('RV2.8-B: Mostrando Punto Fantasma', { segmentIndex: idx, coord: controlPoint });
            
            if (map && map.getSource('draw-control-source')) {
                map.getSource('draw-control-source').setData({ type: "Feature", properties: { segmentIndex: idx }, geometry: { type: "Point", coordinates: controlPoint } });
            }
        }
        
        function hideControlPointLayer() {
            if (map && map.getSource('draw-control-source')) {
                map.getSource('draw-control-source').setData({ type: "FeatureCollection", features: [] });
            }
        }

        function updateDrawLayer() {
            if (map.getSource('draw-source')) {
                const bakedCoords = getBakedCoords(rvNodes);
                map.getSource('draw-source').setData({ type: "Feature", geometry: { type: "LineString", coordinates: bakedCoords } });
                
                const segmentFeatures = [];
                for (let i = 0; i < rvNodes.length - 1; i++) {
                    segmentFeatures.push({ type: "Feature", properties: { segmentIndex: i }, geometry: { type: "LineString", coordinates: getBakedSegment(rvNodes[i].nodo, rvNodes[i].control, rvNodes[i+1].nodo) } });
                }
                if (map.getSource('draw-line-hit-source')) {
                    map.getSource('draw-line-hit-source').setData({ type: "FeatureCollection", features: segmentFeatures });
                }
                
                map.getSource('draw-points').setData({ type: "FeatureCollection", features: rvNodes.map((n, i) => ({ type: "Feature", properties: { index: i }, geometry: { type: "Point", coordinates: n.nodo } })) });
            }
            document.getElementById('rvDrawCount').textContent = rvNodes.length + (rvNodes.length === 1 ? " punto" : " puntos");
            document.getElementById('btnRvFinish').disabled = rvNodes.length < 2;
        }

        function rvUndo() { 
            rvNodes.pop(); 
            hoveredSegmentIndex = -1;
            hideControlPointLayer();
            updateDrawLayer(); 
            if (rvNodes.length === 0) document.getElementById('rvDrawPanel').style.display = 'none'; 
        }
        
        function rvCancel() { 
            rvNodes = []; 
            hoveredSegmentIndex = -1;
            hideControlPointLayer();
            updateDrawLayer(); 
            document.getElementById('rvDrawPanel').style.display = 'none'; 
            document.getElementById('panelFormularioRV').classList.remove('active'); 
            document.getElementById('rvForm').reset(); 
            document.getElementById('rvId').value = '';
            document.getElementById('btnGuardarRv').textContent = '💾 Guardar Tramo';
        }
        
        function rvFinish() { 
            document.getElementById('rvDrawPanel').style.display = 'none'; 
            document.getElementById('panelFormularioRV').classList.add('active'); 
            document.getElementById('rvNombre').focus(); 
        }

        // Auto-asignación de color por estado idéntico al CSS público
        document.getElementById('rvEstado').addEventListener('change', (e) => {
            const coloresBD = { 'Entregado': '#1b5e20', 'En ejecución': '#1a73e8', 'Paralizado': '#c62828', 'Buena Pro': '#ef6c00', 'Transferencia': '#6d28d9', 'En estudios': '#616161' };
            document.getElementById('rvColor').value = coloresBD[e.target.value] || '#3b82f6';
        });

        function cerrarPanel() {
            document.getElementById('panelFormulario').classList.remove('active');
            if (activeMarker) activeMarker.remove();
            document.getElementById('refForm').reset();
            document.getElementById('refId').value = '';
            document.getElementById('inpZoom').value = 11;
            document.getElementById('inpCat').value = 'General';
            document.getElementById('inpIcon').value = 'hito';
        }

        async function fetchLista() {
            try {
                const res = await fetch('mapa_referencias_api.php?action=geojson');
                refsGeoJSON = await res.json();
                renderLista();
            } catch (e) { console.error('Error fetching list', e); }
        }

        function abrirListaActual() {
            if (currentMode === 'hitos') abrirLista();
            else abrirListaRV();
        }

        function abrirLista() {
            document.getElementById('panelFormulario').classList.remove('active');
            document.getElementById('panelLista').classList.add('active');
            fetchLista();
        }

        function cerrarLista() {
            document.getElementById('panelLista').classList.remove('active');
        }

        function renderLista() {
            const cont = document.getElementById('listaReferenciasContainer');
            if (!refsGeoJSON || !refsGeoJSON.features || refsGeoJSON.features.length === 0) {
                cont.innerHTML = '<p style="color:#64748b; font-size:12px;">No hay referencias guardadas.</p>';
                return;
            }
            let html = '';
            const iconMap = { 'salud':'🏥', 'educacion':'🎓', 'gobierno':'🏛️', 'deporte':'⚽', 'transporte':'🚌', 'comercio':'🛒', 'parque':'🌳', 'hito':'📍' };
            refsGeoJSON.features.forEach(f => {
                const p = f.properties;
                const emoji = iconMap[p.icon_type] || '📍';
                html += `<div style="background:#1e293b; margin-bottom:10px; padding:12px; border-radius:8px; border:1px solid #334155; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
                    <div><strong style="color:#f8fafc;">${emoji} ${p.name}</strong><br><span style="color:#94a3b8; font-size:11px;">Cat: ${p.categoria} | Zoom: ${p.min_zoom}</span></div>
                    <div style="display:flex; gap:6px;"><button type="button" class="btn" style="padding:6px; background:#3b82f6; color:white; min-width:30px;" onclick="editarRef(${p.id})" title="Editar">✏️</button><button type="button" class="btn" style="padding:6px; background:#ef4444; color:white; min-width:30px;" onclick="eliminarRef(${p.id})" title="Eliminar">🗑️</button></div>
                </div>`;
            });
            cont.innerHTML = html;
        }

        function editarRef(id) {
            const feature = refsGeoJSON.features.find(f => f.properties.id === id);
            if (!feature) return;
            
            const lng = feature.geometry.coordinates[0], lat = feature.geometry.coordinates[1];
            if (activeMarker) activeMarker.remove();
            activeMarker = new maplibregl.Marker({ color: '#3b82f6' }).setLngLat([lng, lat]).addTo(map);
            map.flyTo({ center: [lng, lat], zoom: 15 });

            document.getElementById('refId').value = id;
            document.getElementById('inpLat').value = lat; document.getElementById('inpLng').value = lng;
            document.getElementById('inpNombre').value = feature.properties.name; document.getElementById('inpCorto').value = feature.properties.short_name;
            document.getElementById('inpCat').value = feature.properties.categoria; document.getElementById('inpIcon').value = feature.properties.icon_type;
            document.getElementById('inpZoom').value = feature.properties.min_zoom;
            
            document.getElementById('panelLista').classList.remove('active');
            document.getElementById('panelFormulario').classList.add('active');
            document.getElementById('formTitle').textContent = '✏️ Editar Referencia';
            document.getElementById('btnGuardar').textContent = '💾 Actualizar Referencia';
        }

        async function eliminarRef(id) {
            if (!confirm('⚠️ ¿Estás seguro de eliminar este punto? Desaparecerá de todos los mapas públicos de inmediato.')) return;
            try {
                const res = await fetch('mapa_referencias_api.php?action=delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id}) });
                const data = await res.json();
                if (data.ok) { if (map && map.getSource('referencias')) map.getSource('referencias').setData('mapa_referencias_api.php?action=geojson'); fetchLista(); } 
                else { alert('Error: ' + data.error); }
            } catch(e) { alert('Error de conexión'); }
        }

        // ==========================================
        // LÓGICA DE LISTADO Y EDICIÓN RED VIAL
        // ==========================================
        async function fetchListaRV() {
            try {
                const res = await fetch('mapa_redvial_api.php?action=listar_admin');
                rvGeoJSON = await res.json();
                renderListaRV();
            } catch (e) { console.error('Error fetching list RV', e); }
        }

        function abrirListaRV() {
            document.getElementById('panelFormularioRV').classList.remove('active');
            document.getElementById('rvDrawPanel').style.display = 'none';
            document.getElementById('panelListaRV').classList.add('active');
            fetchListaRV();
        }

        function cerrarListaRV() {
            document.getElementById('panelListaRV').classList.remove('active');
        }

        function renderListaRV() {
            const cont = document.getElementById('listaRvContainer');
            if (!rvGeoJSON || !rvGeoJSON.features || rvGeoJSON.features.length === 0) {
                cont.innerHTML = '<p style="color:#64748b; font-size:12px;">No hay vías guardadas.</p>';
                return;
            }
            let html = '';
            rvGeoJSON.features.forEach(f => {
                const p = f.properties;
                const isActivo = p.activo === 1;
                const opacity = isActivo ? '1' : '0.6';
                const badgeActivo = isActivo ? '' : '<span style="background:#ef4444; color:white; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold; margin-left:8px;">INACTIVA</span>';
                const btnToggle = isActivo 
                    ? `<button type="button" class="btn" style="padding:6px; background:#ef4444; color:white; min-width:30px;" onclick="toggleActivoRV('${p.id}', 0)" title="Desactivar y ocultar">🚫</button>`
                    : `<button type="button" class="btn" style="padding:6px; background:#10b981; color:white; min-width:30px;" onclick="toggleActivoRV('${p.id}', 1)" title="Reactivar">✅</button>`;
                
                html += `<div style="background:#1e293b; margin-bottom:10px; padding:12px; border-radius:8px; border:1px solid #334155; font-size:13px; display:flex; justify-content:space-between; align-items:center; opacity:${opacity};">
                    <div><strong style="color:#f8fafc;">🛣️ ${p.nombre}</strong>${badgeActivo}<br><span style="color:#94a3b8; font-size:11px;">Tipo: ${p.tipo} | Estado: ${p.estado}</span></div>
                    <div style="display:flex; gap:6px;"><button type="button" class="btn" style="padding:6px; background:#3b82f6; color:white; min-width:30px;" onclick="editarRV('${p.id}')" title="Editar">✏️</button>${btnToggle}</div>
                </div>`;
            });
            cont.innerHTML = html;
        }

        function editarRV(id) {
            const feature = rvGeoJSON.features.find(f => f.properties.id === id);
            if (!feature) return;
            
            if (feature.properties.datos_edicion && Array.isArray(feature.properties.datos_edicion)) {
                rvNodes = feature.properties.datos_edicion;
            } else {
                rvNodes = feature.geometry.coordinates.map(c => ({ nodo: c, control: null }));
            }
            updateDrawLayer();
            if (rvNodes.length > 0) map.flyTo({ center: rvNodes[0].nodo, zoom: 15 });

            document.getElementById('rvId').value = id;
            document.getElementById('rvNombre').value = feature.properties.nombre || '';
            document.getElementById('rvTipo').value = feature.properties.tipo || 'Local';
            document.getElementById('rvEstado').value = feature.properties.estado || 'En estudios';
            document.getElementById('rvColor').value = feature.properties.color || '#3b82f6';
            document.getElementById('rvDesc').value = feature.properties.descripcion || '';
            
            document.getElementById('panelListaRV').classList.remove('active');
            document.getElementById('rvDrawPanel').style.display = 'flex';
            document.getElementById('panelFormularioRV').classList.add('active');
            document.getElementById('btnGuardarRv').textContent = '💾 Actualizar Tramo';
        }

        async function toggleActivoRV(id, activo) {
            const accionTexto = activo === 1 ? 'reactivar' : 'desactivar (ocultar del mapa público)';
            if (!confirm(`⚠️ ¿Estás seguro de ${accionTexto} este tramo vial?`)) return;
            try {
                const res = await fetch('mapa_redvial_api.php?action=toggle_activo', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: id, activo: activo}) });
                const data = await res.json();
                if (data.ok) { 
                    if (map && map.getSource('tramos-viales')) map.getSource('tramos-viales').setData('mapa_redvial_api.php?action=geojson'); 
                    fetchListaRV(); 
                    rvCancel();
                } else { alert('Error: ' + data.error); }
            } catch(e) { alert('Error de conexión'); }
        }

        // Enviar Formulario
        document.getElementById('refForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            const isEdit = document.getElementById('refId').value !== '';
            btn.disabled = true; btn.textContent = isEdit ? '⏳ Actualizando...' : '⏳ Guardando...';
            
            const payload = { lat: parseFloat(document.getElementById('inpLat').value), lng: parseFloat(document.getElementById('inpLng').value), nombre: document.getElementById('inpNombre').value, nombre_corto: document.getElementById('inpCorto').value, categoria: document.getElementById('inpCat').value, icon_type: document.getElementById('inpIcon').value, min_zoom: parseInt(document.getElementById('inpZoom').value) };
            if (isEdit) payload.id = parseInt(document.getElementById('refId').value);
            
            try {
                const res = await fetch(`mapa_referencias_api.php?action=${isEdit ? 'update' : 'create'}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.ok) {
                    if (map && map.getSource('referencias')) map.getSource('referencias').setData('mapa_referencias_api.php?action=geojson');
                    cerrarPanel();
                } else { alert('Error: ' + data.error); }
            } catch (err) { alert('Error de conexión'); } 
            finally { btn.disabled = false; btn.textContent = isEdit ? '💾 Actualizar Referencia' : '💾 Guardar Referencia'; }
        });

        // Enviar Formulario de Red Vial
        document.getElementById('rvForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (rvNodes.length < 2) return alert("Necesitas al menos 2 puntos para guardar una vía.");
            
            const btn = document.getElementById('btnGuardarRv');
            const isEdit = document.getElementById('rvId').value !== '';
            btn.disabled = true; btn.textContent = isEdit ? '⏳ Actualizando Tramo...' : '⏳ Guardando Tramo...';
            
            const bakedCoords = getBakedCoords(rvNodes);
            const payload = { nombre: document.getElementById('rvNombre').value, tipo: document.getElementById('rvTipo').value, estado: document.getElementById('rvEstado').value, color: document.getElementById('rvColor').value, descripcion: document.getElementById('rvDesc').value, coordenadas: bakedCoords, datos_edicion: rvNodes };
            if (isEdit) payload.id = document.getElementById('rvId').value;
            
            try {
                const res = await fetch(`mapa_redvial_api.php?action=${isEdit ? 'update' : 'create'}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.ok) {
                    if (map && map.getSource('tramos-viales')) map.getSource('tramos-viales').setData('mapa_redvial_api.php?action=geojson');
                    rvCancel();
                    alert(isEdit ? "🛣️ ¡Tramo actualizado con éxito!" : "🛣️ ¡Tramo guardado con éxito!");
                } else { alert('Error: ' + data.error); }
            } catch (err) { alert('Error de conexión al guardar.'); } 
            finally { btn.disabled = false; btn.textContent = isEdit ? '💾 Actualizar Tramo' : '💾 Guardar Tramo'; }
        });
    </script>
</body>
</html>