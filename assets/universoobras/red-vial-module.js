/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

window.redVialMapInstance = null;
window.isRedVialLoading = false; // Bloqueo para evitar doble inicialización

// =========================================================
// ARQUITECTURA DE ESTILOS Y CAPAS (STUDIO)
// =========================================================
window.rvStyleConfig = {
    theme: 'ciudadano',
    toggles: {
        'roads': true,
        'buildings': true,
        'buildings3d': false,
        'water': true,
        'parks': true,
        'boundaries': true,
        'transit': true,
        'places-text': true,
        'pois-text': false
    }
};

const RV_THEMES = {
    tecnico: {
        bg: "#e9e5dc", water: "#a0c8f0", parks: "#d5d5d5",
        road: "#cccccc", transit: "#999999",
        building: "#d9d6ce", boundary: "#999999", 
        text: "#666666", poi: "#888888",
        routeBg: "#ffffff"
    },
    ciudadano: {
        bg: "#f4f1eb", water: "#a0c8f0", parks: "#c8e6c9",
        road: "#ffffff", transit: "#f87171",
        building: "#e0dcd2", boundary: "#cbd5e1", 
        text: "#475569", poi: "#64748b",
        routeBg: "#ffffff"
    },
    dashboard: {
        bg: "#0f172a", water: "#1e293b", parks: "#064e3b",
        road: "#334155", transit: "#7f1d1d",
        building: "#1e293b", boundary: "#475569", 
        text: "#94a3b8", poi: "#cbd5e1",
        routeBg: "#000000"
    }
};

window.rvApplyStyle = function() {
    const t = RV_THEMES[window.rvStyleConfig.theme];
    const toggles = window.rvStyleConfig.toggles;

    const style = {
        version: 8,
        // Glifos gratuitos de Protomaps para poder renderizar las letras de los lugares
        glyphs: "https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf",
        sources: {
            "protomaps": {
                type: "vector",
                url: "pmtiles://../data/tacna.pmtiles",
                attribution: "<a href='https://protomaps.com'>Protomaps</a> © <a href='https://openstreetmap.org'>OpenStreetMap</a>"
            },
            "tramos-viales": { 
                type: "geojson", 
                data: "tramos-viales.geojson" 
            }
        },
        layers: [
            { id: "bg", type: "background", paint: { "background-color": t.bg } }
        ]
    };

    // 1. Capas Vectoriales Base (Controlables por el Studio)
    if (toggles['water']) style.layers.push({ id: "water", type: "fill", source: "protomaps", "source-layer": "water", paint: { "fill-color": t.water } });
    if (toggles['parks']) style.layers.push({ id: "parks", type: "fill", source: "protomaps", "source-layer": "landuse", filter: ["any", ["==", "class", "park"], ["==", "class", "pitch"], ["==", "class", "cemetery"], ["==", "class", "hospital"]], paint: { "fill-color": t.parks } });
    if (toggles['transit']) style.layers.push({ id: "transit", type: "line", source: "protomaps", "source-layer": "transit", paint: { "line-color": t.transit, "line-dasharray": [2,2] } });
    if (toggles['roads']) style.layers.push({ id: "roads", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": t.road, "line-width": ["interpolate", ["linear"], ["zoom"], 12, 1, 16, 4] } });
    
    if (toggles['buildings'] || toggles['buildings3d']) {
        if (toggles['buildings3d']) {
            style.layers.push({ id: "buildings", type: "fill-extrusion", source: "protomaps", "source-layer": "buildings", paint: { "fill-extrusion-color": t.building, "fill-extrusion-height": ["get", "height"], "fill-extrusion-base": 0, "fill-extrusion-opacity": 0.8 } });
        } else {
            style.layers.push({ id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": t.building, "fill-opacity": 0.6 } });
        }
    }
    if (toggles['boundaries']) style.layers.push({ id: "boundaries", type: "line", source: "protomaps", "source-layer": "boundaries", filter: ["in", "admin_level", 4, 6], paint: { "line-color": t.boundary, "line-dasharray": [4,4], "line-width": 1.5 } });
    
    // 2. Capas Vectoriales Textos
    if (toggles['places-text']) style.layers.push({ id: "places-text", type: "symbol", source: "protomaps", "source-layer": "places", layout: { "text-field": ["get", "name"], "text-font": ["Noto Sans Regular"], "text-size": 14 }, paint: { "text-color": t.text, "text-halo-color": t.bg, "text-halo-width": 2 } });
    if (toggles['pois-text']) style.layers.push({ id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", layout: { "text-field": ["get", "name"], "text-font": ["Noto Sans Regular"], "text-size": 11 }, paint: { "text-color": t.poi, "text-halo-color": t.bg, "text-halo-width": 1 } });

    // 3. Capas Operativas (GeoJSON Obras inyectado directamente en el estilo)
    style.layers.push({
        'id': 'tramos-viales-bg',
        'type': 'line',
        'source': 'tramos-viales',
        'layout': { 'line-join': 'round', 'line-cap': 'round' },
        'paint': { 'line-color': t.routeBg, 'line-width': 8 }
    });
    style.layers.push({
        'id': 'tramos-viales-layer',
        'type': 'line',
        'source': 'tramos-viales',
        'layout': { 'line-join': 'round', 'line-cap': 'round' },
        'paint': { 'line-color': ['get', 'color'], 'line-width': 4 }
    });

    if (window.redVialMapInstance) {
        window.redVialMapInstance.setStyle(style);
        // Restaurar filtro espacial si estaba activo al cambiar la estética
        setTimeout(() => {
            const btn = document.querySelector('.rv-filter-btn.is-active');
            if (btn && btn.getAttribute('data-tipo') !== 'Todos') {
                const tipo = btn.getAttribute('data-tipo');
                if (window.redVialMapInstance.getLayer('tramos-viales-layer')) {
                    window.redVialMapInstance.setFilter('tramos-viales-layer', ['==', ['get', 'tipo'], tipo]);
                    window.redVialMapInstance.setFilter('tramos-viales-bg', ['==', ['get', 'tipo'], tipo]);
                }
            }
        }, 200);
    }
    return style;
};

window.initRedVial = async function() {
    // Guard Clause de seguridad
    if (window.redVialMapInstance || window.isRedVialLoading) return;
    window.isRedVialLoading = true;
    
    // Asegurar que MapLibre esté disponible (fallback por si no se cargó globalmente).
    if (typeof window.maplibregl === 'undefined') {
        if (typeof window.loadMapLibre === 'function') {
            await window.loadMapLibre();
        } else {
            console.error("[Red Vial] Error: MapLibre GL no está disponible.");
            window.isRedVialLoading = false;
            return;
        }
    }

    // 1. Cargar el lector de PMTiles dinámicamente si no existe
    if (typeof window.pmtiles === 'undefined') {
        console.log("[Red Vial] Cargando librería PMTiles...");
        try {
            window.pmtiles = await import('https://unpkg.com/pmtiles@3.0.6/dist/index.js');
        } catch (error) {
            console.error("[Red Vial] Error al cargar librería PMTiles:", error);
            window.isRedVialLoading = false;
            return;
        }
    }

    // 2. Registrar el protocolo de PMTiles en MapLibre
    if (!window.pmtilesProtocolRegistered) {
        const protocol = new window.pmtiles.Protocol();
        window.maplibregl.addProtocol('pmtiles', protocol.tile);
        window.pmtilesProtocolRegistered = true;
    }

    console.log("[Red Vial] Inicializando mapa Vectorial PMTiles Offline...");
    
    // 3. Instanciar mapa con la arquitectura de Estilos Dinámicos
    window.redVialMapInstance = new maplibregl.Map({
        container: 'red-vial-map-container',
        style: window.rvApplyStyle(),
        center: [-70.2528, -18.0146], // Centro de Tacna
        zoom: 14,
        attributionControl: false
    });

    window.redVialMapInstance.on('load', () => {
        window.redVialMapInstance.resize();

        // Asignar eventos de clic (las capas ya vienen integradas en rvApplyStyle)
        window.redVialMapInstance.on('mouseenter', 'tramos-viales-layer', () => { window.redVialMapInstance.getCanvas().style.cursor = 'pointer'; });
        window.redVialMapInstance.on('mouseleave', 'tramos-viales-layer', () => { window.redVialMapInstance.getCanvas().style.cursor = ''; });

        window.redVialMapInstance.on('click', 'tramos-viales-layer', (e) => {
            if (e.features.length > 0) abrirPanelRedVial(e.features[0].properties);
        });
        
        setupRedVialFilters();
        
        // Levantar el Panel de Control Visual (Studio)
        initRedVialStudio();

        window.isRedVialLoading = false;
    });
    
    // Prevención de cuelgues si el usuario cambia de página antes de cargar los tiles
    window.redVialMapInstance.on('error', () => { window.isRedVialLoading = false; });
};

function setupRedVialFilters() {
    const filterButtons = document.querySelectorAll('.rv-filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Actualizar apariencia del UI
            filterButtons.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            const tipoSeleccionado = btn.getAttribute('data-tipo');
            
            // Aplicar filtro espacial a las capas nativas
            if (tipoSeleccionado === 'Todos') {
                window.redVialMapInstance.setFilter('tramos-viales-layer', null);
                window.redVialMapInstance.setFilter('tramos-viales-bg', null);
            } else {
                window.redVialMapInstance.setFilter('tramos-viales-layer', ['==', ['get', 'tipo'], tipoSeleccionado]);
                window.redVialMapInstance.setFilter('tramos-viales-bg', ['==', ['get', 'tipo'], tipoSeleccionado]);
            }
        });
    });
}

// =========================================================
// RED VIAL STUDIO UI (PANEL FLOTANTE)
// =========================================================
function initRedVialStudio() {
    const container = document.getElementById('red-vial-map-container');
    if (!container || document.getElementById('rv-studio-panel')) return;

    const panel = document.createElement('div');
    panel.id = 'rv-studio-panel';
    panel.style.cssText = 'position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 15px; border-radius: 12px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,0.15); width: 230px; font-family: "Arial Black", Arial, sans-serif; color: #111; transition: all 0.3s ease; border: 1px solid rgba(255, 195, 0, 0.5);';

    panel.innerHTML = `
        <h3 style="margin: 0 0 12px 0; font-size: 14px; color: #801039; text-transform: uppercase;">🎨 Estudio Visual</h3>
        <label style="display: block; font-size: 10px; margin-bottom: 4px; font-weight: 900; color: #666; text-transform: uppercase;">Tema Base</label>
        <select id="rv-theme-select" style="width: 100%; padding: 6px; margin-bottom: 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 12px; font-family: system-ui, sans-serif; outline: none; background: #fff;">
            <option value="tecnico">Ingeniería / Técnico</option>
            <option value="ciudadano">Ciudadano / Público</option>
            <option value="dashboard">Dashboard Obras</option>
        </select>
        <hr style="border:0; border-top:1px solid #eee; margin: 10px 0;">
        <div style="font-size: 12px; display: flex; flex-direction: column; gap: 8px; font-family: system-ui, sans-serif; font-weight: 500;">
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="roads"> 🛣️ Vías y Calles</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="buildings"> 🏢 Edificios 2D</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="buildings3d"> 🏙️ Edificios 3D</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="water"> 💧 Cuerpos de Agua</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="parks"> 🌳 Parques y Áreas Verdes</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="boundaries"> 🗺️ Límites Distritales</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="transit"> 🚆 Transporte Férreo</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="places-text"> 🏷️ Nombres de Distritos</label>
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="checkbox" data-layer="pois-text"> 📍 Puntos de Interés</label>
        </div>
    `;
    container.appendChild(panel);

    // Sincronizar UI con el estado
    const select = panel.querySelector('#rv-theme-select');
    select.value = window.rvStyleConfig.theme;
    select.addEventListener('change', (e) => {
        window.rvStyleConfig.theme = e.target.value;
        if (e.target.value === 'dashboard') { window.rvStyleConfig.toggles['buildings3d'] = true; window.rvStyleConfig.toggles['buildings'] = false; panel.querySelector('[data-layer="buildings3d"]').checked = true; panel.querySelector('[data-layer="buildings"]').checked = false; window.redVialMapInstance.easeTo({ pitch: 60, bearing: -20 }); } else { window.redVialMapInstance.easeTo({ pitch: 0, bearing: 0 }); }
        window.rvApplyStyle();
    });

    panel.querySelectorAll('input[type="checkbox"]').forEach(chk => {
        const layerKey = chk.getAttribute('data-layer');
        chk.checked = window.rvStyleConfig.toggles[layerKey];
        chk.addEventListener('change', (e) => {
            if (layerKey === 'buildings3d' && e.target.checked) { window.rvStyleConfig.toggles['buildings'] = false; panel.querySelector('[data-layer="buildings"]').checked = false; }
            if (layerKey === 'buildings' && e.target.checked) { window.rvStyleConfig.toggles['buildings3d'] = false; panel.querySelector('[data-layer="buildings3d"]').checked = false; }
            window.rvStyleConfig.toggles[layerKey] = e.target.checked;
            window.rvApplyStyle();
        });
    });
}

function abrirPanelRedVial(props) {
    const panel = document.getElementById('redVialInfoPanel');
    if (!panel) return;
    
    // HTML interno simple y limpio para el panel
    panel.innerHTML = `
        <div style="padding: 24px; font-family: system-ui, sans-serif;">
            <button onclick="document.getElementById('redVialInfoPanel').classList.remove('is-active')" 
                    style="float: right; background: #801039; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 8px; font-weight: bold;">
                Cerrar ✖
            </button>
            <h2 style="color: #801039; margin-top: 0; font-family: 'Arial Black', sans-serif;">${props.nombre}</h2>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
            <p><strong>Tipo de Vía:</strong> <span style="background:#eee; padding:2px 8px; border-radius:4px;">${props.tipo}</span></p>
            <p><strong>Estado:</strong> ${props.estado}</p>
            <div style="margin-top: 30px; padding: 15px; background: #fff8e1; border-left: 4px solid #ffc300; border-radius: 4px;">
                <p style="color: #666; font-size: 0.85em; margin: 0;">
                    * Datos del MVP. En fases posteriores aquí se conectará la galería de fotos, el costo de inversión, metas y ficha técnica.
                </p>
            </div>
        </div>
    `;
    
    panel.classList.add('is-active');
}

window.activateRedVial = async function() {
    console.log("[Red Vial] Activando módulo...");
    
    const container = document.getElementById('red-vial-map-container');
    const svg = document.getElementById('synced-svg-container');
    const filters = document.getElementById('red-vial-filters');
    const baseCanvas = document.querySelector('#map canvas.maplibregl-canvas');
    
    // 🔥 FIX 1: Ocultar INMEDIATAMENTE el SVG y los pines del mapa base antes de cargar nada
    if (svg) svg.style.setProperty('opacity', '0', 'important');
    if (baseCanvas) baseCanvas.style.setProperty('opacity', '0', 'important');
    if (filters) {
        filters.style.opacity = '1';
        filters.style.pointerEvents = 'auto';
    }
    
    if (!window.redVialMapInstance) {
        await window.initRedVial();
    }
    
    if (container) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
    
    // 🔥 FIX: MapLibre necesita redibujarse al volverse visible para no quedar en 0x0
    if (window.redVialMapInstance) {
        setTimeout(() => {
            if(window.redVialMapInstance) window.redVialMapInstance.resize();
        }, 100);
        // Doble seguro por si la tarjeta de video tarda en asignar el ancho de la pantalla
        setTimeout(() => {
            if(window.redVialMapInstance) window.redVialMapInstance.resize();
        }, 600);
    }
};

window.deactivateRedVial = function() {
    const container = document.getElementById('red-vial-map-container');
    const svg = document.getElementById('synced-svg-container');
    const filters = document.getElementById('red-vial-filters');
    const baseCanvas = document.querySelector('#map canvas.maplibregl-canvas');
    
    if (container) {
        container.style.opacity = '0';
        container.style.pointerEvents = 'none';
    }
    
    // 🔥 FIX 2: Restaurar INMEDIATAMENTE el SVG y los pines del mapa base
    if (svg) svg.style.setProperty('opacity', '1', 'important');
    if (baseCanvas) baseCanvas.style.setProperty('opacity', '1', 'important');
    
    if (filters) {
        filters.style.opacity = '0';
        filters.style.pointerEvents = 'none';
    }

    // Cerrar el panel si estuviera abierto
    const panel = document.getElementById('redVialInfoPanel');
    if (panel) panel.classList.remove('is-active');
};

window.destroyRedVial = function() {
    if (window.redVialMapInstance) {
        window.redVialMapInstance.remove();
        window.redVialMapInstance = null;
        window.isRedVialLoading = false;
        console.log("[Red Vial] Mapa destruido para liberar memoria (Barba.js).");
    }
};