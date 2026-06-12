/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

// Configuración opcional: Si obtienes un token de MapTiler, colócalo aquí 
// y cambia la variable 'mapStyle' dentro de initRedVial().
const MAPTILER_KEY = 'TU_TOKEN_AQUI';

window.redVialMapInstance = null;
window.isRedVialLoading = false; // Bloqueo para evitar doble inicialización

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

    console.log("[Red Vial] Inicializando mapa MapLibre (Modo MVP Seguro)...");
    
    // Estilo Raster libre (OpenStreetMap) para no depender de Tokens en el MVP.
    const mvpStyle = {
        version: 8,
        sources: {
            'osm': {
                type: 'raster',
                tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                tileSize: 256,
                attribution: '&copy; OpenStreetMap Contributors'
            }
        },
        layers: [{
            id: 'osm-layer',
            type: 'raster',
            source: 'osm',
            minzoom: 0,
            maxzoom: 19
        }]
    };

    // Si prefieres usar MapTiler (Vectorial) descomenta esta línea y pon tu Token arriba:
    // const mapStyle = `https://api.maptiler.com/maps/streets-v2/style.json?key=${MAPTILER_KEY}`;
    const mapStyle = mvpStyle;

    // 1. Instanciar mapa anclado a #red-vial-map-container
    window.redVialMapInstance = new maplibregl.Map({
        container: 'red-vial-map-container',
        style: mapStyle,
        center: [-70.2528, -18.0146], // Centro de Tacna
        zoom: 14,
        attributionControl: false
    });

    window.redVialMapInstance.on('load', () => {
        // 2. Datos GeoJSON Dummy (MVP)
        const dummyData = {
            "type": "FeatureCollection",
            "features": [
                {
                    "type": "Feature",
                    "properties": {
                        "id": "tramo-1",
                        "nombre": "Avenida San Martín",
                        "tipo": "Provincial",
                        "estado": "En ejecución",
                        "color": "#801039" // Granate institucional
                    },
                    "geometry": {
                        "type": "LineString",
                        "coordinates": [ [-70.2505, -18.0135], [-70.2548, -18.0156], [-70.2580, -18.0175] ]
                    }
                },
                {
                    "type": "Feature",
                    "properties": {
                        "id": "tramo-2",
                        "nombre": "Avenida Bolognesi",
                        "tipo": "Local",
                        "estado": "Entregado",
                        "color": "#ffc300" // Amarillo institucional
                    },
                    "geometry": {
                        "type": "LineString",
                        "coordinates": [ [-70.2480, -18.0160], [-70.2520, -18.0185], [-70.2555, -18.0205] ]
                    }
                }
            ]
        };

        window.redVialMapInstance.addSource('tramos-viales', { 'type': 'geojson', 'data': dummyData });

        // 3. Renderizar líneas
        window.redVialMapInstance.addLayer({
            'id': 'tramos-viales-bg',
            'type': 'line',
            'source': 'tramos-viales',
            'layout': { 'line-join': 'round', 'line-cap': 'round' },
            'paint': { 'line-color': '#ffffff', 'line-width': 8 } // Borde blanco (halo)
        });

        window.redVialMapInstance.addLayer({
            'id': 'tramos-viales-layer',
            'type': 'line',
            'source': 'tramos-viales',
            'layout': { 'line-join': 'round', 'line-cap': 'round' },
            'paint': { 'line-color': ['get', 'color'], 'line-width': 4 } // Color interior
        });

        // 4. Asignar eventos de clic
        window.redVialMapInstance.on('mouseenter', 'tramos-viales-layer', () => { window.redVialMapInstance.getCanvas().style.cursor = 'pointer'; });
        window.redVialMapInstance.on('mouseleave', 'tramos-viales-layer', () => { window.redVialMapInstance.getCanvas().style.cursor = ''; });

        window.redVialMapInstance.on('click', 'tramos-viales-layer', (e) => {
            if (e.features.length > 0) abrirPanelRedVial(e.features[0].properties);
        });
        
        window.isRedVialLoading = false;
    });
    
    // Prevención de cuelgues si el usuario cambia de página antes de cargar los tiles
    window.redVialMapInstance.on('error', () => { window.isRedVialLoading = false; });
};

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
    
    if (!window.redVialMapInstance) {
        await window.initRedVial();
    }
    
    if (container) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
    if (svg) svg.style.opacity = '0';
};

window.deactivateRedVial = function() {
    const container = document.getElementById('red-vial-map-container');
    const svg = document.getElementById('synced-svg-container');
    
    if (container) {
        container.style.opacity = '0';
        container.style.pointerEvents = 'none';
    }
    if (svg) svg.style.opacity = '1';

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