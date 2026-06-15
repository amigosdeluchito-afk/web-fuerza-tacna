// =========================================================
// ===== BASE CARTOGRÁFICA ESTABLE (v118) =====
// PMTiles + Proxy PHP + Perfil Ciudadano y Servicios validado
// =========================================================

/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

window.redVialMapInstance = null;
window.isRedVialLoading = false; // Bloqueo para evitar doble inicialización

// Constante única para la ruta de la base de datos vectorial
const PMTILES_URL = '../data/pmtiles_proxy.php';

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
        'boundaries': false,
        'transit': false,
        'places-text': true,
        'ref-urbanas': true, // Reemplaza pois-text por Referencias Urbanas (Los Titanes)
        'srv-edu': false,
        'srv-salud': true,
        'srv-seguridad': false,
        'srv-gobierno': true,
        'srv-mercados': true,
        'srv-deporte': false,
        'srv-transporte': true
    }
};

const RV_THEMES = {
    tecnico: {
        bg: "#e9e5dc", water: "#a0c8f0", parks: "#d5d5d5",
        amenity_med: "#d5d5d5", amenity_edu: "#d5d5d5",
        road_main: "#cccccc", road_main_case: "#bbbbbb",
        road_minor: "#cccccc", road_minor_case: "#bbbbbb",
        transit: "#999999",
        building: "#d9d6ce", boundary: "#999999", 
        text: "#666666", poi: "#888888", road_text: "#666666",
        routeBg: "#ffffff"
    },
    ciudadano: {
        bg: "#F2EFE9", water: "#B9D9F7", parks: "#C2E2BA",
        amenity_med: "#F4C7C3", amenity_edu: "#F6E6A8",
        road_highway: "#B7C5D5", road_highway_case: "#A8BACB",
        road_main: "#CDD7E3", road_main_case: "#C1CDDA",
        road_secondary: "#DFE5EC", road_secondary_case: "#D3DCE5",
        road_minor: "#FFFFFF", road_minor_case: "#E6ECF1",
        transit: "#f87171", building: "#e6e4df", boundary: "#cbd5e1", 
        text: "#1e293b", poi: "#666666", road_text: "#3f3f46",
        routeBg: "#ffffff"
    },
    impacto: {
        bg: "#0f172a", water: "#1e293b", parks: "#064e3b",
        amenity_med: "#0f172a", amenity_edu: "#0f172a",
        road_main: "#334155", road_main_case: "#1e293b",
        road_minor: "#334155", road_minor_case: "#1e293b",
        transit: "#7f1d1d",
        building: "#1e293b", boundary: "#475569", 
        text: "#94a3b8", poi: "#cbd5e1", road_text: "#64748b",
        routeBg: "#000000"
    }
};

window.rvApplyStyle = function() {
    const t = RV_THEMES[window.rvStyleConfig.theme];
    const toggles = window.rvStyleConfig.toggles;
    const isImpacto = window.rvStyleConfig.theme === 'impacto';

    const style = {
        version: 8,
        // Glifos gratuitos de Protomaps para poder renderizar las letras de los lugares
        glyphs: "https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf",
        sources: {
            "protomaps": {
                type: "vector",
                url: `pmtiles://${PMTILES_URL}`, // Ruta centralizada y apuntando al archivo real
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
    
    const landuseColors = ["match", ["get", "kind"]];
    if (toggles['srv-salud']) landuseColors.push("hospital", t.amenity_med, "clinic", t.amenity_med);
    if (toggles['srv-edu']) landuseColors.push("school", t.amenity_edu, "university", t.amenity_edu, "college", t.amenity_edu, "kindergarten", t.amenity_edu);
    if (toggles['srv-deporte']) landuseColors.push("stadium", t.parks, "pitch", t.parks);
    if (toggles['parks']) landuseColors.push("park", t.parks, "grass", t.parks, "recreation_ground", t.parks, "cemetery", t.parks, "forest", t.parks, "wood", t.parks);
    landuseColors.push("rgba(0,0,0,0)");
    
    if (toggles['parks'] || toggles['srv-salud'] || toggles['srv-edu'] || toggles['srv-deporte']) {
        style.layers.push({ id: "parks", type: "fill", source: "protomaps", "source-layer": "landuse", paint: { "fill-color": landuseColors } });
    }

    if (toggles['transit']) style.layers.push({ id: "transit", type: "line", source: "protomaps", "source-layer": "transit", paint: { "line-color": t.transit, "line-dasharray": [2,2] } });
    
    const isMajorRoad = ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"]];
    const isHighway = ["==", ["get", "kind"], "highway"];
    const isAvenida = ["all", ["has", "name"], ["any", ["in", "Avenida", ["get", "name"]], ["in", "Av.", ["get", "name"]], ["in", "Av ", ["get", "name"]]]];

    if (toggles['roads']) {
        style.layers.push({ id: "roads-casing", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": ["case", isHighway, t.road_highway_case, isMajorRoad, t.road_main_case, isAvenida, t.road_secondary_case, t.road_minor_case], "line-width": ["interpolate", ["linear"], ["zoom"], 12, ["case", isMajorRoad, 4, isAvenida, 2.5, 1.5], 16, ["case", isMajorRoad, 14, isAvenida, 8, 5]] } });
        style.layers.push({ id: "roads", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": ["case", isHighway, t.road_highway, isMajorRoad, t.road_main, isAvenida, t.road_secondary, t.road_minor], "line-width": ["interpolate", ["linear"], ["zoom"], 12, ["case", isMajorRoad, 2.5, isAvenida, 1.2, 0.5], 16, ["case", isMajorRoad, 10, isAvenida, 5, 3]] } });
    }
    
    if (toggles['buildings'] || toggles['buildings3d']) {
        if (toggles['buildings3d']) {
            style.layers.push({ id: "buildings", type: "fill-extrusion", source: "protomaps", "source-layer": "buildings", paint: { "fill-extrusion-color": t.building, "fill-extrusion-height": ["get", "height"], "fill-extrusion-base": 0, "fill-extrusion-opacity": 0.8 } });
        } else {
            style.layers.push({ id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": t.building, "fill-opacity": 0.6 } });
        }
    }
    if (toggles['boundaries']) style.layers.push({ id: "boundaries", type: "line", source: "protomaps", "source-layer": "boundaries", filter: ["in", "admin_level", 4, 6], paint: { "line-color": t.boundary, "line-dasharray": [4,4], "line-width": 1.5 } });
    
    // 2. Capas Vectoriales Textos
    const refPlacesFilter = ["any",
        ["==", ["get", "name"], "Calana"],
        ["==", ["get", "name"], "Monte Verde 2"]
    ];

    const refPoisFilter = ["any",
        ["==", ["get", "name"], "Municipalidad Provincial de Tacna"],
        ["==", ["get", "name"], "Municipalidad de Gregorio Albarracín Lanchipa"],
        ["==", ["get", "name"], "Gobierno Regional de Tacna"],
        ["==", ["get", "name"], "Universidad Nacional Jorge Basadre Grohmann"],
        ["==", ["get", "name"], "Hospital III Daniel Alcides Carrión"],
        ["==", ["get", "name"], "Hospital de la Solidaridad"],
        ["==", ["get", "name"], "Estadio Enrique Paillardelle"]
    ];

    // INYECTAR REFERENCIAS URBANAS PRIMERO PARA GARANTIZAR PRIORIDAD ANTI-COLISIONES
    if (toggles['ref-urbanas']) {
        style.layers.push({ id: "ref-urbanas-pois", type: "symbol", source: "protomaps", "source-layer": "pois", minzoom: 11, filter: ["all", ["has", "name"], refPoisFilter], layout: { "text-field": ["concat", ["match", ["get", "kind"], "hospital", "🏥 ", "university", "🎓 ", "stadium", "⚽ ", "townhall", "🏛️ ", "📍 "], ["match", ["get", "name"], "Municipalidad Provincial de Tacna", "Muni. Tacna", "Municipalidad de Gregorio Albarracín Lanchipa", "Muni. Albarracín", "Gobierno Regional de Tacna", "GORE Tacna", "Universidad Nacional Jorge Basadre Grohmann", "UNJBG", "Hospital III Daniel Alcides Carrión", "Hosp. Carrión", "Hospital de la Solidaridad", "Hosp. Solidaridad", "Estadio Enrique Paillardelle", "Estadio Paillardelle", ["get", "name"]]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 11, 13, 15, 16], "text-anchor": "bottom", "text-offset": [0, 0.5], "text-allow-overlap": true }, paint: { "text-color": t.text, "text-halo-color": "#FFFFFF", "text-halo-width": 3 } });
        style.layers.push({ id: "ref-urbanas-places", type: "symbol", source: "protomaps", "source-layer": "places", minzoom: 10, filter: ["all", ["has", "name"], refPlacesFilter], layout: { "text-field": ["upcase", ["get", "name"]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 10, 12, 14, 16], "text-letter-spacing": 0.1 }, paint: { "text-color": t.text, "text-halo-color": t.bg, "text-halo-width": 2.5 } });
    }

    if (toggles['places-text']) {
        const finalPlacesFilter = toggles['ref-urbanas'] ? ["all", ["has", "name"], ["!", refPlacesFilter]] : ["all", ["has", "name"]];
        style.layers.push({ id: "places-text", type: "symbol", source: "protomaps", "source-layer": "places", filter: finalPlacesFilter, layout: { "text-field": ["upcase", ["get", "name"]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 10, 12, 14, 16], "text-letter-spacing": 0.1 }, paint: { "text-color": t.text, "text-halo-color": t.bg, "text-halo-width": 2.5 } });
        if (toggles['roads']) style.layers.push({ id: "roads-text", type: "symbol", source: "protomaps", "source-layer": "roads", filter: ["all", ["has", "name"], ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"], ["==", ["get", "kind"], "minor_road"]]], layout: { "text-field": ["get", "name"], "symbol-placement": "line", "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 12, ["case", isMajorRoad, 12, 0], 14, ["case", isMajorRoad, 14, isAvenida, 12, 0], 16, ["case", isMajorRoad, 16, isAvenida, 14, 11]], "text-max-angle": 30, "text-pitch-alignment": "viewport" }, paint: { "text-color": t.road_text, "text-halo-color": "#FFFFFF", "text-halo-width": 2.5 } });
    }
    
    const poiFilters = [];
    if (toggles['srv-salud']) poiFilters.push(["==", ["get", "kind"], "hospital"], ["==", ["get", "kind"], "clinic"]);
    if (toggles['srv-edu']) poiFilters.push(["==", ["get", "kind"], "school"], ["==", ["get", "kind"], "university"], ["==", ["get", "kind"], "college"], ["==", ["get", "kind"], "kindergarten"]);
    if (toggles['srv-seguridad']) poiFilters.push(["==", ["get", "kind"], "police"], ["==", ["get", "kind"], "fire_station"]);
    if (toggles['srv-gobierno']) poiFilters.push(["==", ["get", "kind"], "townhall"], ["==", ["get", "kind"], "town_hall"]);
    if (toggles['srv-mercados']) poiFilters.push(["==", ["get", "kind"], "marketplace"], ["==", ["get", "kind"], "market"]);
    if (toggles['srv-deporte']) poiFilters.push(["==", ["get", "kind"], "stadium"], ["==", ["get", "kind"], "pitch"]);
    if (toggles['srv-transporte']) poiFilters.push(["==", ["get", "kind"], "bus_station"]);

    // Regla 7: Si no hay ningún servicio activo, no inyectamos la capa para evitar crashear el motor
    if (poiFilters.length > 0) {
        const finalPoiFilter = ["all", ["has", "name"], ["any", ...poiFilters]];
        if (toggles['ref-urbanas']) finalPoiFilter.push(["!", refPoisFilter]); // Excluye los titanes para no duplicarlos
        style.layers.push({ id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", minzoom: 15, filter: finalPoiFilter, layout: { "text-field": ["concat", ["match", ["get", "kind"], "hospital", "🏥 ", "clinic", "🏥 ", "school", "🏫 ", "university", "🎓 ", "college", "🎓 ", "kindergarten", "🧸 ", "police", "🚓 ", "fire_station", "🚒 ", "marketplace", "🛒 ", "market", "🛒 ", "stadium", "⚽ ", "pitch", "⚽ ", "bus_station", "🚌 ", "townhall", "🏛️ ", "town_hall", "🏛️ ", "📍 "], ["get", "name"]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 15, 10, 18, 12], "text-anchor": "bottom", "text-offset": [0, 0.5] }, paint: { "text-color": t.poi, "text-halo-color": "#FFFFFF", "text-halo-width": 1.5 } });
    }

    // 3. Capas Operativas (Efecto Normal vs Neón para el Modo Impacto)
    style.layers.push({
        'id': 'tramos-viales-bg',
        'type': 'line',
        'source': 'tramos-viales',
        'layout': { 'line-join': 'round', 'line-cap': 'round' },
        'paint': { 'line-color': isImpacto ? ['get', 'color'] : t.routeBg, 'line-width': isImpacto ? 14 : 8, 'line-blur': isImpacto ? 6 : 0, 'line-opacity': isImpacto ? 0.8 : 1 }
    });
    style.layers.push({
        'id': 'tramos-viales-layer',
        'type': 'line',
        'source': 'tramos-viales',
        'layout': { 'line-join': 'round', 'line-cap': 'round' },
        'paint': { 'line-color': isImpacto ? '#ffffff' : ['get', 'color'], 'line-width': isImpacto ? 3 : 4 }
    });

    // =========================================================
    // 4. CAPAS FANTASMA DE AUDITORÍA (Forzar carga en memoria RAM)
    style.layers.push({ id: "debug-pois", type: "circle", source: "protomaps", "source-layer": "pois", paint: { "circle-opacity": 0, "circle-radius": 0 } });
    style.layers.push({ id: "debug-places", type: "circle", source: "protomaps", "source-layer": "places", paint: { "circle-opacity": 0, "circle-radius": 0 } });

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

        // =========================================================
        // 🔍 MÓDULO DE DIAGNÓSTICO ESTRICTO (SOLO LECTURA)
        // =========================================================
        const inspectFeatures = (features, context) => {
            console.log(`\n=== 🔍 DIAGNÓSTICO: ${context} ===`);
            const protoFeatures = features.filter(f => f.source === 'protomaps');
            if (protoFeatures.length === 0) console.log("No se encontraron features vectoriales de Protomaps en este punto.");
            protoFeatures.forEach((f, i) => {
                console.log(`[${i+1}] Capa Origen (source-layer): ${f.sourceLayer} | Capa Visual (layer.id): ${f.layer.id} | Tipo Geom: ${f.geometry.type}`);
                console.log("Propiedades Reales:\n" + JSON.stringify(f.properties, null, 2));
            });
            console.log("===================================================\n");
        };

        // Diagnóstico Inicial (Centro de la pantalla tras 2 segundos de carga)
        setTimeout(() => {
            const centerPoint = window.redVialMapInstance.project(window.redVialMapInstance.getCenter());
            inspectFeatures(window.redVialMapInstance.queryRenderedFeatures(centerPoint), "CENTRO DEL MAPA (INICIO)");
        }, 2000);

        // Diagnóstico por Clic (Global y pasivo, NO rompe los paneles)
        window.redVialMapInstance.on('click', (e) => {
            inspectFeatures(window.redVialMapInstance.queryRenderedFeatures(e.point), "CLIC DEL USUARIO");
        });
        
        // =========================================================
        // 🔍 AUDITORÍA AUTOMÁTICA DE HITOS URBANOS (FASE D)
        // =========================================================
        const targetKeywords = ['aeropuerto', 'terminal', 'unanue', 'basadre', 'mercado', 'grau', 'cenepa', 'plaza', 'paseo', 'arco', 'universidad', 'privada', 'jorge basadre', 'essalud'];
        const auditedFeatures = new Set();
        let uniqueCount = 0;

        const scanFeatures = () => {
            // Consultar datos vectoriales crudos directamente de la fuente
            const features = window.redVialMapInstance.querySourceFeatures('protomaps');
            features.forEach(f => {
                if ((f.sourceLayer === 'pois' || f.sourceLayer === 'places') && f.properties && f.properties.name) {
                    const name = f.properties.name.toLowerCase();
                    const hasMatch = targetKeywords.some(kw => name.includes(kw));
                    if (hasMatch) {
                        const uniqueKey = `${f.sourceLayer}|${f.properties.kind}|${f.properties.name}`;
                        if (!auditedFeatures.has(uniqueKey)) {
                            auditedFeatures.add(uniqueKey);
                            uniqueCount++;
                            console.log(`\n[${uniqueCount}] 📍 HITO ENCONTRADO`);
                            console.log(`   - source-layer: ${f.sourceLayer}`);
                            console.log(`   - kind: ${f.properties.kind || 'N/A'}`);
                            console.log(`   - name: ${f.properties.name}`);
                            console.log(`   - min_zoom: ${f.properties.min_zoom !== undefined ? f.properties.min_zoom : 'N/A'}`);
                            console.log(`   - sort_rank: ${f.properties.sort_rank !== undefined ? f.properties.sort_rank : 'N/A'}`);
                        }
                    }
                }
            });
        };

        window.redVialMapInstance.on('moveend', scanFeatures);
        setTimeout(scanFeatures, 3000); // Primer escaneo automático

        window.isRedVialLoading = false;
    });
    
    // Prevención de cuelgues si el usuario cambia de página antes de cargar los tiles
    window.redVialMapInstance.on('error', (e) => { 
        console.error("[MapLibre Error Detalle]:", e.error || e);
        window.isRedVialLoading = false; 
    });
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
    panel.className = 'rv-panel';

    panel.innerHTML = `
        <div class="rv-panel-header" id="rv-panel-header-btn" title="Mostrar/Ocultar capas">
            <h3 class="rv-panel-title">🗺️ Vista del Mapa</h3>
            <button class="rv-panel-toggle">▼</button>
        </div>
        <div class="rv-panel-body">
            <!-- Pestañas Principales (UX Ciudadano) -->
            <div class="rv-profiles">
                <button class="rv-profile-btn is-active" data-profile="ciudadano">Ciudadano</button>
                <button class="rv-profile-btn" data-profile="tecnico">Técnico</button>
                <button class="rv-profile-btn" data-profile="impacto">Impacto</button>
            </div>
            
            <!-- Acordeón Opciones Avanzadas -->
            <div class="rv-advanced-header" id="rv-advanced-toggle">
                <span>⚙️ Opciones Avanzadas</span>
                <span>▼</span>
            </div>
            <div class="rv-advanced-content" id="rv-advanced-content">
                <div class="rv-panel-group">
                    <div class="rv-panel-group-title">Base</div>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="water"> 💧 Agua</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="parks"> 🌳 Parques</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="buildings"> 🏢 Edificios 2D</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="buildings3d"> 🏙️ Edificios 3D</label>
                </div>
                <div class="rv-panel-group">
                    <div class="rv-panel-group-title">Vías</div>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="roads"> 🛣️ Calles</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="transit"> 🚆 Transp. Férreo</label>
                </div>
                <div class="rv-panel-group">
                    <div class="rv-panel-group-title">Territorio</div>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="boundaries"> 🗺️ Límites Distr.</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="places-text"> 🏷️ Nombres</label>
                </div>
                <div class="rv-panel-group">
                    <div class="rv-panel-group-title">Referencias</div>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="ref-urbanas"> 📍 Referencias Urbanas</label>
                </div>
                <div class="rv-panel-group">
                    <div class="rv-panel-group-title">🏢 Servicios</div>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-edu"> 📚 Educación</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-salud"> 🏥 Salud</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-seguridad"> 🚓 Seguridad</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-gobierno"> 🏛️ Gobierno</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-mercados"> 🛒 Mercados</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-deporte"> ⚽ Deporte</label>
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-transporte"> 🚍 Transporte</label>
                </div>
            </div>
        </div>
    `;
    container.appendChild(panel);

    // Evento para colapsar/expandir el panel
    panel.querySelector('#rv-panel-header-btn').addEventListener('click', () => {
        panel.classList.toggle('is-collapsed');
    });
    
    // Evento Acordeón Avanzado
    panel.querySelector('#rv-advanced-toggle').addEventListener('click', () => {
        panel.querySelector('#rv-advanced-content').classList.toggle('is-open');
    });

    // Lógica de Perfiles Públicos
    panel.querySelectorAll('.rv-profile-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const profile = e.target.getAttribute('data-profile');
            window.rvStyleConfig.theme = profile;
            
            panel.querySelectorAll('.rv-profile-btn').forEach(b => b.classList.remove('is-active'));
            e.target.classList.add('is-active');
            
            const t = window.rvStyleConfig.toggles;
            if (profile === 'ciudadano') { Object.assign(t, { water: true, parks: true, buildings: true, buildings3d: false, boundaries: false, transit: false, 'places-text': true, 'ref-urbanas': true, roads: true, 'srv-edu': false, 'srv-salud': true, 'srv-seguridad': false, 'srv-gobierno': true, 'srv-mercados': true, 'srv-deporte': false, 'srv-transporte': true }); window.redVialMapInstance.easeTo({ pitch: 0, bearing: 0 }); }
            if (profile === 'tecnico') { Object.assign(t, { water: false, parks: false, buildings: false, buildings3d: false, boundaries: true, transit: true, 'places-text': true, 'ref-urbanas': false, roads: true, 'srv-edu': false, 'srv-salud': false, 'srv-seguridad': false, 'srv-gobierno': false, 'srv-mercados': false, 'srv-deporte': false, 'srv-transporte': false }); window.redVialMapInstance.easeTo({ pitch: 0, bearing: 0 }); }
            if (profile === 'impacto') { Object.assign(t, { water: true, parks: false, buildings: false, buildings3d: true, boundaries: true, transit: false, 'places-text': false, 'ref-urbanas': false, roads: true, 'srv-edu': false, 'srv-salud': false, 'srv-seguridad': false, 'srv-gobierno': false, 'srv-mercados': false, 'srv-deporte': false, 'srv-transporte': false }); window.redVialMapInstance.easeTo({ pitch: 60, bearing: -20 }); }
            
            // Sincronizar checkboxes avanzados
            Object.keys(t).forEach(key => { const chk = panel.querySelector(`input[data-layer="${key}"]`); if (chk) chk.checked = t[key]; });
            window.rvApplyStyle();
        });
    });

    // Lógica Controles Avanzados Manuales
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