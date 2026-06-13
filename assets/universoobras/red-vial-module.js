/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

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

    // 1. Cargar el lector de PMTiles dinámicamente si no existe
    if (typeof pmtiles === 'undefined') {
        console.log("[Red Vial] Cargando librería PMTiles...");
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/pmtiles@3.0.6/dist/index.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // 2. Registrar el protocolo de PMTiles en MapLibre
    if (!window.pmtilesProtocolRegistered) {
        const protocol = new pmtiles.Protocol();
        window.maplibregl.addProtocol('pmtiles', protocol.tile);
        window.pmtilesProtocolRegistered = true;
    }

    console.log("[Red Vial] Inicializando mapa Vectorial PMTiles Offline...");
    
    // 3. Estilo Vectorial (Style.json adaptado a código)
    // Ocultamos a propósito POIs (Negocios), Parques y Nombres de lugares.
    const mapStyle = {
        version: 8,
        // Fuente vectorial base desde nuestro archivo local
        sources: {
            "protomaps": {
                type: "vector",
                url: "pmtiles://../data/tacna.pmtiles", // Lee desde XAMPP
                attribution: "<a href='https://protomaps.com'>Protomaps</a> © <a href='https://openstreetmap.org'>OpenStreetMap</a>"
            }
        },
        layers: [
            // Fondo base gris
            { id: "background", type: "background", paint: { "background-color": "#e9e5dc" } },
            // Cuerpos de agua (Ríos, mar)
            { id: "water", type: "fill", source: "protomaps", "source-layer": "water", paint: { "fill-color": "#a0c8f0" } },
            // Las Vías y Calles (Mantenemos un color gris sutil para que resalten tus obras)
            { id: "roads", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": "#cccccc", "line-width": 1.5 } },
            // Edificios y manzanas (Para darle volumen a la ciudad)
            { id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": "#d9d6ce", "fill-opacity": 0.6 } }
            // 🛑 ¡MAGIA!: Como NO hemos declarado capas de textos (places), ni parques (landuse), ni negocios (pois), NO SE MOSTRARÁN.
        ]
    };

    // 4. Instanciar mapa anclado a #red-vial-map-container
    window.redVialMapInstance = new maplibregl.Map({
        container: 'red-vial-map-container',
        style: mapStyle,
        center: [-70.2528, -18.0146], // Centro de Tacna
        zoom: 14,
        attributionControl: false
    });

    window.redVialMapInstance.on('load', () => {
        // 🔥 FIX: Forzar redibujado apenas el motor termine de cargar internamente
        window.redVialMapInstance.resize();

        // 2. Cargar GeoJSON de forma nativa y asíncrona desde el archivo local
        window.redVialMapInstance.addSource('tramos-viales', { 
            'type': 'geojson', 
            'data': 'tramos-viales.geojson' 
        });

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
        
        // 5. Inicializar la lógica de Filtros UI
        setupRedVialFilters();

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