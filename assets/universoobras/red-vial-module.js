// =========================================================
// ===== BASE CARTOGRÁFICA ESTABLE (v118) =====
// PMTiles + Proxy PHP + Perfil Ciudadano y Servicios validado
// =========================================================

/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

window.redVialMapInstance = null;
window.isRedVialLoading = false; // Bloqueo para evitar doble inicialización

const DEBUG_RV = false; // Flag para silenciar bucles y diagnósticos ruidosos
const PERF_RV = false;  // Apagado para producción (RV5-PERF-A2)

// Constante única para la ruta de la base de datos vectorial
const PMTILES_URL = '../data/pmtiles_proxy_departamento.php';
const RED_VIAL_SCRIPT_URL = document.currentScript?.src || document.querySelector('script[src*="red-vial-module.js"]')?.src || window.location.href;
const PMTILES_LIB_URL = new URL('../vendor/pmtiles/pmtiles-3.0.6.js', RED_VIAL_SCRIPT_URL).href;

// =========================================================
// GEOJSON DE ETIQUETAS ESTRATÉGICAS (RV6-MAP-B2.2)
// =========================================================
const regionalLabelsGeoJSON = {
    "type": "FeatureCollection",
    "features": [
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.2528, -18.0146] }, "properties": { "name": "Tacna", "type": "capital_regional", "priority": 1, "minzoom": 8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.0325, -17.4744] }, "properties": { "name": "Tarata", "type": "capital_provincial", "priority": 2, "minzoom": 8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.2500, -17.2681] }, "properties": { "name": "Candarave", "type": "capital_provincial", "priority": 2, "minzoom": 8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.7633, -17.6136] }, "properties": { "name": "Locumba", "type": "capital_provincial", "priority": 2, "minzoom": 8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.5144, -17.4208] }, "properties": { "name": "Ilabaya", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.9631, -17.8361] }, "properties": { "name": "Ite", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.5361, -17.8967] }, "properties": { "name": "Sama", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.1539, -17.8931] }, "properties": { "name": "Pachía", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-69.9575, -17.7817] }, "properties": { "name": "Palca", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.4357, -18.1565] }, "properties": { "name": "La Yarada", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.1878, -17.9542] }, "properties": { "name": "Calana", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.0461, -17.4428] }, "properties": { "name": "Ticaco", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.2444, -17.3197] }, "properties": { "name": "Quilahuani", "type": "distrito_principal", "priority": 3, "minzoom": 8.8 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.6756, -18.1636] }, "properties": { "name": "Boca del Río", "type": "centro_poblado", "priority": 4, "minzoom": 9.5 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.6133, -17.2475] }, "properties": { "name": "Toquepala", "type": "centro_poblado", "priority": 4, "minzoom": 9.5 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.1067, -17.8744] }, "properties": { "name": "Miculla", "type": "centro_poblado", "priority": 4, "minzoom": 9.5 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.7850, -17.6711] }, "properties": { "name": "Camiara", "type": "centro_poblado", "priority": 4, "minzoom": 9.5 } },
        { "type": "Feature", "geometry": { "type": "Point", "coordinates": [-70.6186, -17.3828] }, "properties": { "name": "Mirave", "type": "centro_poblado", "priority": 4, "minzoom": 9.5 } }
    ]
};

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
        'ref-urbanas': true, // Ahora controlan a tus Titanes de la BD y están visibles por defecto
        'srv-edu': true,
        'srv-salud': true,
        'srv-seguridad': true,
        'srv-gobierno': true,
        'srv-mercados': true,
        'srv-deporte': true,
        'srv-transporte': true,
        'srv-negocios': true
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
        road_highway: "#89A5BE", road_highway_case: "#7893AA",
        road_main: "#94AEC4", road_main_case: "#819BB1",
        road_secondary: "#C7D6E1", road_secondary_case: "#B5C7D5",
        road_minor: "#667885", road_minor_case: "#A9BBC7",
        transit: "#f87171", building: "#e6e4df", boundary: "#cbd5e1", 
        text: "#1e293b", poi: "#666666", road_text: "#3f3f46", places_text: "#64748b",
        routeBg: "#ffffff"
    },
    impacto: {
        bg: "#0f172a", water: "#1e293b", parks: "#064e3b",
        amenity_med: "#0f172a", amenity_edu: "#0f172a",
        road_main: "#334155", road_main_case: "#1e293b",
        road_minor: "#334155", road_minor_case: "#1e293b",
        transit: "#7f1d1d",
        building: "#1e293b", boundary: "#475569", 
        text: "#94a3b8", poi: "#cbd5e1", road_text: "#64748b", places_text: "#475569",
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
                data: "../panel-admin-universo/mapa_redvial_api.php?action=geojson" 
            },
            "referencias-estrategicas": {
                type: "geojson",
                data: "../panel-admin-universo/mapa_referencias_api.php?action=geojson"
            },
            "regional-labels": {
                type: "geojson",
                data: regionalLabelsGeoJSON
            },
            // TEMP RV6-MAP-C1: delimitación regional desactivada hasta crear assets/data/tacna_region.geojson
            /*
            "tacna-region": {
                type: "geojson",
                data: "../data/tacna_region.geojson"
            }
            */
        },
        layers: [
            { id: "bg", type: "background", paint: { "background-color": t.bg } }
        ]
    };

    // =========================================================
    // DELIMITACIÓN VISUAL DE LA REGIÓN TACNA (RV6-MAP-C1)
    // =========================================================
    // TEMP RV6-MAP-C1: delimitación regional desactivada hasta crear assets/data/tacna_region.geojson
    /*
    style.layers.push({
        id: "tacna-region-fill",
        type: "fill",
        source: "tacna-region",
        paint: { 
            "fill-color": "#ffc300", 
            "fill-opacity": 0.03 
        }
    });
    */
    
    // TEMP RV6-MAP-C1: delimitación regional desactivada hasta crear assets/data/tacna_region.geojson
    /*
    style.layers.push({
        id: "tacna-region-outline",
        type: "line",
        source: "tacna-region",
        paint: { 
            "line-color": "#801039", 
            "line-width": ["interpolate", ["linear"], ["zoom"], 7, 1.5, 10, 2.5, 13, 3.5],
            "line-opacity": 0.8,
            "line-dasharray": [3, 2] // Efecto de línea institucional punteada
        }
    });
    */

    // 1. Capas Vectoriales Base (Controlables por el Studio)
    if (toggles['water']) {
        // Masas de agua: océano, lagos, lagunas
        style.layers.push({
            id: "water",
            type: "fill",
            source: "protomaps",
            "source-layer": "water",
            filter: ["==", ["geometry-type"], "Polygon"],
            paint: { "fill-color": t.water, "fill-opacity": 0.75 }
        });
    
        // Ríos, canales y cauces lineales (Brillo / Halo base)
        style.layers.push({
            id: "water-line-glow",
            type: "line",
            source: "protomaps",
            "source-layer": "water",
            filter: ["==", ["geometry-type"], "LineString"],
            paint: { "line-color": "#9FD8FF", "line-width": ["interpolate", ["linear"], ["zoom"], 9, 1.5, 12, 2.5, 15, 4.5], "line-opacity": 0.28, "line-blur": 0.8 }
        });

        // Ríos, canales y cauces lineales (Línea central)
        style.layers.push({
            id: "water-line",
            type: "line",
            source: "protomaps",
            "source-layer": "water",
            filter: ["==", ["geometry-type"], "LineString"],
            paint: { "line-color": "#5FAFEF", "line-width": ["interpolate", ["linear"], ["zoom"], 9, 0.8, 11, 1.2, 13, 1.8, 15, 3.0], "line-opacity": 0.8, "line-blur": 0.1 }
        });
    }
    
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
    const isRegionalRoad = ["any", isHighway, ["has", "ref"], ["has", "shield_text"]];
    const isAvenida = ["all", ["has", "name"], ["any", ["in", "Avenida", ["get", "name"]], ["in", "Av.", ["get", "name"]], ["in", "Av ", ["get", "name"]]]];

    if (toggles['roads']) {
        style.layers.push({ id: "roads-casing", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": ["case", isHighway, t.road_highway_case, isMajorRoad, t.road_main_case, isAvenida, t.road_secondary_case, t.road_minor_case], "line-width": ["interpolate", ["linear"], ["zoom"], 8, ["case", isRegionalRoad, 3.8, 0], 9.5, ["case", isRegionalRoad, 5.0, isMajorRoad, 2.0, 0], 11, ["case", isRegionalRoad, 6.4, isMajorRoad, 3.0, 0], 13, ["case", isRegionalRoad, 8.4, isMajorRoad, 4.5, isAvenida, 3.0, 1.2], 16, ["case", isRegionalRoad, 20.0, isMajorRoad, 16.0, isAvenida, 10.0, 4.8]] } });
        style.layers.push({ id: "roads", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": ["case", isHighway, t.road_highway, isMajorRoad, t.road_main, isAvenida, t.road_secondary, t.road_minor], "line-width": ["interpolate", ["linear"], ["zoom"], 8, ["case", isRegionalRoad, 2.2, 0], 9.5, ["case", isRegionalRoad, 3.0, isMajorRoad, 1.0, 0], 11, ["case", isRegionalRoad, 4.1, isMajorRoad, 1.8, 0], 13, ["case", isRegionalRoad, 5.6, isMajorRoad, 2.5, isAvenida, 1.5, 0.55], 16, ["case", isRegionalRoad, 15.0, isMajorRoad, 12.0, isAvenida, 7.0, 3.0]] } });
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
    if (toggles['places-text']) {
        // RV6-MAP-B2: Adaptación basada en 'locality' y 'min_zoom' de Protomaps local
        const isLocality = ["==", ["get", "kind"], "locality"];
        
        // Exclusión para evitar duplicados de nuestras etiquetas estratégicas en el texto base
        const isCustomLabel = ["match", ["get", "name"], ["Tacna", "Tarata", "Candarave", "Locumba", "Ilabaya", "Ite", "Sama", "Pachía", "Palca", "La Yarada", "Calana", "Ticaco", "Quilahuani", "Boca del Río", "Toquepala", "Miculla", "Camiara", "Mirave"], true, false];
        
        const isMajorPlace = ["all", isLocality, ["<=", ["coalesce", ["get", "min_zoom"], 99], 8], ["!", isCustomLabel]];
        const isVillagePlace = ["all", isLocality, [">", ["coalesce", ["get", "min_zoom"], 99], 8], ["<=", ["coalesce", ["get", "min_zoom"], 99], 11], ["!", isCustomLabel]];
        const isMinorPlace = ["all", isLocality, [">", ["coalesce", ["get", "min_zoom"], 99], 11], ["!", isCustomLabel]];
        
        const isTacna = ["==", ["get", "name"], "Tacna"];
        const textFieldName = ["coalesce", ["get", "name:es"], ["get", "name"]];
        
        const sortKeyField = ["coalesce", ["get", "sort_key"], 999999];
        
        // =========================================================
        // ETIQUETAS REGIONALES ESTRATÉGICAS (Divididas por jerarquía)
        // =========================================================
        
        // 1. Capital Regional (Tacna)
        style.layers.push({
            id: "regional-labels-capital",
            type: "symbol",
            source: "regional-labels",
            filter: ["==", ["get", "priority"], 1],
            layout: {
                "text-field": ["get", "name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 8, 18, 10, 22, 12, 16, 14, 12, 16, 10],
                "text-transform": "uppercase",
                "text-letter-spacing": 0.05,
                "text-anchor": "center",
                "text-allow-overlap": true,
                "text-ignore-placement": true,
                "symbol-sort-key": ["get", "priority"]
            },
            paint: {
                "text-color": "#111827",
                "text-halo-color": "#ffffff",
                "text-halo-width": ["interpolate", ["linear"], ["zoom"], 8, 3, 12, 2, 14, 1.4],
                "text-halo-blur": 0.5
            }
        });

        // 2. Capitales Provinciales (Tarata, Candarave, Locumba)
        style.layers.push({
            id: "regional-labels-provincial",
            type: "symbol",
            source: "regional-labels",
            filter: ["==", ["get", "priority"], 2],
            layout: {
                "text-field": ["get", "name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 8, 13, 10, 16, 13, 20],
                "text-letter-spacing": 0.05,
                "text-anchor": "center",
                "text-allow-overlap": false,
                "text-ignore-placement": false,
                "symbol-sort-key": ["get", "priority"]
            },
            paint: {
                "text-color": "#2f3a4a",
                "text-halo-color": "#ffffff",
                "text-halo-width": 3,
                "text-halo-blur": 0.5
            }
        });

        // 3. Centros Poblados y Distritos (Ilabaya, Ite, Sama, etc.)
        style.layers.push({
            id: "regional-labels-district",
            type: "symbol",
            source: "regional-labels",
            filter: ["==", ["get", "priority"], 3],
            layout: {
                "text-field": ["get", "name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 8.8, 10, 11, 12, 14, 15],
                "text-letter-spacing": 0.05,
                "text-anchor": "center",
                "text-allow-overlap": false,
                "text-ignore-placement": false,
                "symbol-sort-key": ["get", "priority"]
            },
            paint: {
                "text-color": "#2f3a4a",
                "text-halo-color": "#ffffff",
                "text-halo-width": 2,
                "text-halo-blur": 0.5
            }
        });

        // 4. Centros Poblados Secundarios (Boca del Río, Toquepala, Miculla, etc.)
        style.layers.push({
            id: "regional-labels-minor",
            type: "symbol",
            source: "regional-labels",
            filter: ["==", ["get", "priority"], 4],
            layout: {
                "text-field": ["get", "name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 9.5, 9, 12, 12, 15, 14],
                "text-letter-spacing": 0.05,
                "text-anchor": "center",
                "text-allow-overlap": false,
                "text-ignore-placement": false,
                "symbol-sort-key": ["get", "priority"]
            },
            paint: {
                "text-color": "#475569",
                "text-halo-color": "#ffffff",
                "text-halo-width": 1.5,
                "text-halo-blur": 0.5
            }
        });

        // Ciudades y Capitales Provinciales (Tacna, Tarata, Candarave, Locumba)
        style.layers.push({ 
            id: "places-major-text", 
            type: "symbol", 
            source: "protomaps", 
            "source-layer": "places", 
            filter: ["all", ["has", "name"], isMajorPlace], 
            layout: { 
                "text-field": textFieldName, 
                "text-font": ["Noto Sans Regular"], 
                "text-size": ["interpolate", ["linear"], ["zoom"], 8, ["case", isTacna, 18, 14], 10, ["case", isTacna, 22, 16], 14, ["case", isTacna, 26, 18]], 
                "text-letter-spacing": 0.05,
                "text-transform": ["case", isTacna, "uppercase", "none"],
                "text-allow-overlap": true,
                "text-ignore-placement": true,
                "symbol-sort-key": sortKeyField
            }, 
            paint: { 
                "text-color": ["case", isTacna, "#000000", t.text], 
                "text-halo-color": "#ffffff", 
                "text-halo-width": ["case", isTacna, 4, 3] 
            } 
        });
        
        // Centros Poblados y Capitales Distritales (Ilabaya, Sama, Ticaco, etc.)
        style.layers.push({ 
            id: "places-village-text", 
            type: "symbol", 
            source: "protomaps", 
            "source-layer": "places", 
            minzoom: 9.5, 
            filter: ["all", ["has", "name"], isVillagePlace], 
            layout: { 
                "text-field": textFieldName, 
                "text-font": ["Noto Sans Regular"], 
                "text-size": ["interpolate", ["linear"], ["zoom"], 9.5, 11, 14, 13], 
                "text-letter-spacing": 0.05, 
                "symbol-sort-key": sortKeyField 
            }, 
            paint: { "text-color": t.places_text || t.text, "text-halo-color": "#ffffff", "text-halo-width": 2.5 } 
        });

        // Barrios urbanos, anexos menores y vecindarios (Viñani, Para Chico, etc.)
        style.layers.push({ 
            id: "places-minor-text", 
            type: "symbol", 
            source: "protomaps", 
            "source-layer": "places", 
            minzoom: 13, 
            filter: ["all", ["has", "name"], isMinorPlace], 
            layout: { 
                "text-field": textFieldName, 
                "text-font": ["Noto Sans Regular"], 
                "text-size": ["interpolate", ["linear"], ["zoom"], 13, 10, 15, 12, 17, 14], 
                "text-letter-spacing": 0.05, 
                "symbol-sort-key": sortKeyField 
            }, 
            paint: { "text-color": t.places_text || t.text, "text-halo-color": "#ffffff", "text-halo-width": 2 } 
        });

        if (toggles['roads']) {
            style.layers.push({ id: "roads-shields", type: "symbol", source: "protomaps", "source-layer": "roads", minzoom: 8.5, filter: ["all", ["any", ["has", "ref"], ["has", "shield_text"]], ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"]]], layout: { "symbol-placement": "line", "text-field": ["coalesce", ["get", "shield_text"], ["get", "ref"]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 8.5, 9, 14, 11], "text-rotation-alignment": "viewport", "text-pitch-alignment": "viewport", "symbol-spacing": 600 }, paint: { "text-color": "#475569", "text-halo-color": "#ffffff", "text-halo-width": 2.5 } });
            style.layers.push({ id: "roads-text", type: "symbol", source: "protomaps", "source-layer": "roads", filter: ["all", ["has", "name"], ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"], ["==", ["get", "kind"], "minor_road"]]], layout: { "text-field": ["get", "name"], "symbol-placement": "line", "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 11, ["case", isMajorRoad, 11, 0], 13, ["case", isMajorRoad, 13, isAvenida, 11, 0], 15, ["case", isMajorRoad, 14, isAvenida, 13, 10], 17, ["case", isMajorRoad, 16, isAvenida, 14, 12]], "text-max-angle": 30, "text-pitch-alignment": "viewport" }, paint: { "text-color": t.road_text, "text-halo-color": "#FFFFFF", "text-halo-width": 2.5 } });
        }
    }
    
    const poiFilters = [];
    if (toggles['srv-salud']) poiFilters.push(["==", ["get", "kind"], "hospital"], ["==", ["get", "kind"], "clinic"]);
    if (toggles['srv-edu']) poiFilters.push(["==", ["get", "kind"], "school"], ["==", ["get", "kind"], "university"], ["==", ["get", "kind"], "college"], ["==", ["get", "kind"], "kindergarten"]);
    if (toggles['srv-seguridad']) poiFilters.push(["==", ["get", "kind"], "police"], ["==", ["get", "kind"], "fire_station"]);
    if (toggles['srv-gobierno']) poiFilters.push(["==", ["get", "kind"], "townhall"], ["==", ["get", "kind"], "town_hall"]);
    if (toggles['srv-mercados']) poiFilters.push(["==", ["get", "kind"], "marketplace"], ["==", ["get", "kind"], "market"]);
    if (toggles['srv-deporte']) poiFilters.push(["==", ["get", "kind"], "stadium"], ["==", ["get", "kind"], "pitch"]);
    if (toggles['srv-transporte']) poiFilters.push(["==", ["get", "kind"], "bus_station"]);
    if (toggles['parks']) poiFilters.push(["==", ["get", "kind"], "park"], ["==", ["get", "kind"], "recreation_ground"]);

    if (poiFilters.length > 0) {
        style.layers.push({ id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", minzoom: 15, filter: ["all", ["has", "name"], ["any", ...poiFilters]], layout: { "text-field": ["concat", ["match", ["get", "kind"], "hospital", "🏥 ", "clinic", "🏥 ", "school", "🏫 ", "university", "🎓 ", "college", "🎓 ", "kindergarten", "🧸 ", "police", "🚓 ", "fire_station", "🚒 ", "marketplace", "🛒 ", "market", "🛒 ", "stadium", "⚽ ", "pitch", "⚽ ", "bus_station", "🚌 ", "townhall", "🏛️ ", "town_hall", "🏛️ ", "park", "🌳 ", "recreation_ground", "🌳 ", "📍 "], ["get", "name"]], "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 15, 10, 18, 12], "text-anchor": "bottom", "text-offset": [0, 0.5] }, paint: { "text-color": t.poi, "text-halo-color": "#FFFFFF", "text-halo-width": 1.5 } });
    }

    // CAPA DE NEGOCIOS PRIVADOS (Aparece solo con muchísimo zoom)
    if (toggles['srv-negocios']) {
        style.layers.push({ 
            id: "negocios-text", type: "symbol", source: "protomaps", "source-layer": "pois", 
            minzoom: 16.5, 
            filter: ["all", 
                ["has", "name"], 
                ["!", ["any", 
                    ["==", ["get", "kind"], "hospital"], ["==", ["get", "kind"], "clinic"],
                    ["==", ["get", "kind"], "school"], ["==", ["get", "kind"], "university"], ["==", ["get", "kind"], "college"], ["==", ["get", "kind"], "kindergarten"],
                    ["==", ["get", "kind"], "police"], ["==", ["get", "kind"], "fire_station"],
                    ["==", ["get", "kind"], "townhall"], ["==", ["get", "kind"], "town_hall"],
                    ["==", ["get", "kind"], "marketplace"], ["==", ["get", "kind"], "market"],
                    ["==", ["get", "kind"], "stadium"], ["==", ["get", "kind"], "pitch"],
                    ["==", ["get", "kind"], "bus_station"],
                    ["==", ["get", "kind"], "park"], ["==", ["get", "kind"], "recreation_ground"]
                ]]
            ], 
            layout: { 
                "text-field": ["concat", ["match", ["get", "kind"], 
                    "restaurant", "🍽️ ", "cafe", "☕ ", "fast_food", "🍔 ", "bar", "🍺 ", "pub", "🍻 ",
                    "pharmacy", "💊 ", "dentist", "🦷 ", "doctors", "🩺 ", "veterinary", "🐕 ",
                    "bakery", "🥐 ", "supermarket", "🛒 ", "convenience", "🏪 ", "butcher", "🥩 ",
                    "bank", "🏦 ", "atm", "🏧 ", 
                    "hotel", "🏨 ", "motel", "🛏️ ",
                    "gas_station", "⛽ ", "car_wash", "🚗 ", "parking", "🅿️ ",
                    "hairdresser", "✂️ ", "clothes", "👕 ", "shoes", "👟 ",
                    "cinema", "🍿 ", "theatre", "🎭 ", "gym", "🏋️ ", "sports_centre", "🏋️ ",
                    "🏪 " // Fallback universal
                ], ["get", "name"]], 
                "text-font": ["Noto Sans Regular"], "text-size": ["interpolate", ["linear"], ["zoom"], 16.5, 9, 19, 12], "text-anchor": "bottom", "text-offset": [0, 0.5] 
            }, 
            paint: { "text-color": t.places_text || t.poi, "text-halo-color": "#FFFFFF", "text-halo-width": 1.5 } 
        });
    }

    // =========================================================
    // CAPA DE REFERENCIAS ESTRATÉGICAS (TITANES DESDE BD)
    // =========================================================
    if (toggles['ref-urbanas']) {
        style.layers.push({ 
            id: "ref-estrategicas-pois", 
            type: "symbol", 
            source: "referencias-estrategicas", 
            minzoom: 10, 
            filter: ["<=", ["get", "min_zoom"], ["zoom"]],
            layout: {
                "icon-image": ["concat", "icon-ref-", ["get", "icon_type"]],
                "icon-size": ["interpolate", ["linear"], ["zoom"], 11, 0.45, 14, 0.8],
                "icon-allow-overlap": true,
                "icon-ignore-placement": true,
                "text-field": ["get", "short_name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 12, 10, 15, 12],
                "text-anchor": "top",
                "text-offset": [0, 0.8],
                "text-allow-overlap": true,
                "text-ignore-placement": true
            }, 
            paint: { 
                "text-color": t.text, 
                "text-halo-color": "#FFFFFF", 
                "text-halo-width": 2.5
            } 
        });
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
    if (DEBUG_RV) {
        style.layers.push({ id: "debug-pois", type: "circle", source: "protomaps", "source-layer": "pois", paint: { "circle-opacity": 0, "circle-radius": 0 } });
        style.layers.push({ id: "debug-places", type: "circle", source: "protomaps", "source-layer": "places", paint: { "circle-opacity": 0, "circle-radius": 0 } });
    }

    if (window.redVialMapInstance) {
        const map = window.redVialMapInstance;
        map.once('style.load', () => window.rvScheduleTerritorialLayers(map));
        map.setStyle(style);
        // Restaurar filtro espacial si estaba activo al cambiar la estética
        setTimeout(() => {
            const btn = document.querySelector('.rv-filter-btn.is-active');
            if (btn && btn.getAttribute('data-tipo') !== 'Todos') {
                const tipo = btn.getAttribute('data-tipo');
                if (map.getLayer('tramos-viales-layer')) {
                    map.setFilter('tramos-viales-layer', ['==', ['get', 'tipo'], tipo]);
                    map.setFilter('tramos-viales-bg', ['==', ['get', 'tipo'], tipo]);
                }
            }
        }, 200);
    }
    return style;
};

function rv_addLayerBefore(map, layer, beforeIds = []) {
    const beforeId = beforeIds.find(id => map.getLayer(id));
    if (beforeId) map.addLayer(layer, beforeId);
    else map.addLayer(layer);
}

window.rvAddTerritorialLayers = function(map = window.redVialMapInstance) {
    if (!map || !map.isStyleLoaded()) return;

    if (!map.getSource('tacna-region')) {
        map.addSource('tacna-region', {
            type: 'geojson',
            data: '../data/tacna_region.geojson'
        });
    }

    if (!map.getSource('tacna-provincias')) {
        map.addSource('tacna-provincias', {
            type: 'geojson',
            data: '../data/tacna_provincias.geojson'
        });
    }

    const beforeRoads = ['transit', 'roads-casing', 'roads', 'buildings'];

    if (!map.getLayer('tacna-region-fill')) {
        rv_addLayerBefore(map, {
            id: 'tacna-region-fill',
            type: 'fill',
            source: 'tacna-region',
            paint: {
                'fill-color': '#8A1538',
                'fill-opacity': 0.01
            }
        }, beforeRoads);
    }

    if (!map.getLayer('tacna-provincias-outline')) {
        rv_addLayerBefore(map, {
            id: 'tacna-provincias-outline',
            type: 'line',
            source: 'tacna-provincias',
            layout: {
                'line-join': 'miter',
                'line-cap': 'butt'
            },
            paint: {
                'line-color': '#8A1538',
                'line-width': ['interpolate', ['linear'], ['zoom'], 7, 0.45, 9, 0.7, 11, 1],
                'line-opacity': 0.35,
                'line-dasharray': [2, 3]
            }
        }, beforeRoads);
    }

    if (!map.getLayer('tacna-region-outline')) {
        rv_addLayerBefore(map, {
            id: 'tacna-region-outline',
            type: 'line',
            source: 'tacna-region',
            layout: {
                'line-join': 'miter',
                'line-cap': 'butt'
            },
            paint: {
                'line-color': '#8A1538',
                'line-width': ['interpolate', ['linear'], ['zoom'], 7, 0.8, 9, 1.1, 11, 1.4],
                'line-opacity': 0.55,
                'line-dasharray': [3, 2]
            }
        }, beforeRoads);
    }
};

window.rvScheduleTerritorialLayers = function(map = window.redVialMapInstance) {
    if (!map) return;

    const addLayers = () => {
        if (map && map.isStyleLoaded()) {
            window.rvAddTerritorialLayers(map);
        }
    };

    map.once('idle', addLayers);
    window.setTimeout(addLayers, 3000);
};

window.initRedVial = async function() {
    // Guard Clause de seguridad
    if (window.redVialMapInstance || window.isRedVialLoading) return;
    window.isRedVialLoading = true;
    
    if (PERF_RV) performance.mark('rv_inicio');
    
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
            window.pmtiles = await import(PMTILES_LIB_URL);
        } catch (error) {
            console.warn("[Red Vial] No se pudo cargar PMTiles local, intentando CDN:", error);
            try {
                window.pmtiles = await import('https://unpkg.com/pmtiles@3.0.6/dist/index.js');
            } catch (fallbackError) {
                console.error("[Red Vial] Error al cargar libreria PMTiles:", fallbackError);
                window.isRedVialLoading = false;
                return;
            }
        }
    }

    // 2. Registrar el protocolo de PMTiles en MapLibre
    if (!window.pmtilesProtocolRegistered) {
        const protocol = new window.pmtiles.Protocol();
        window.maplibregl.addProtocol('pmtiles', protocol.tile);
        window.pmtilesProtocolRegistered = true;
    }

    if (PERF_RV) performance.mark('rv_librerias_listas');

    console.log("[Red Vial] Inicializando mapa Vectorial PMTiles Offline...");
    
    // 3. Instanciar mapa con la arquitectura de Estilos Dinámicos
    window.redVialMapInstance = new maplibregl.Map({
        container: 'red-vial-map-container',
        style: window.rvApplyStyle(),
        center: [-70.30, -17.65],
        zoom: 8.5,
        attributionControl: false
    });

    const rvBlankRecoveryTimer = window.setTimeout(() => {
        const map = window.redVialMapInstance;
        if (!map || map.loaded()) return;
        console.warn("[Red Vial] Reforzando render inicial del mapa.");
        map.resize();
        map.triggerRepaint();
    }, 3500);

    if (PERF_RV) performance.mark('rv_mapa_creado');

    if (PERF_RV) {
        window.redVialMapInstance.once('style.load', () => performance.mark('rv_style_load'));
        window.redVialMapInstance.once('data', () => { if (!window._rv_first_data) { window._rv_first_data = true; performance.mark('rv_first_data'); }});
        window.redVialMapInstance.once('render', () => { if (!window._rv_first_render) { window._rv_first_render = true; performance.mark('rv_first_render'); }});
    }

    window.redVialMapInstance.on('load', () => {
        window.redVialMapInstance.resize();
        window.redVialMapInstance.once('idle', () => window.clearTimeout(rvBlankRecoveryTimer));
        window.rvScheduleTerritorialLayers(window.redVialMapInstance);
        if (PERF_RV) performance.mark('rv_mapa_load');

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
        // 🚀 REGISTRO DE ICONOS PERSONALIZADOS (FASE D)
        // =========================================================
        const createAndAddIcon = (id, emoji, size = 64) => {
            if (window.redVialMapInstance.hasImage(id)) return;

            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            
            // Suavizado de bordes activado
            ctx.imageSmoothingEnabled = true;
            
            ctx.font = `${size * 0.85}px sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(emoji, size / 2, size / 2 + 4);

            // pixelRatio: 2 empaqueta los 64px en un espacio de 32px (Efecto Retina HD)
            window.redVialMapInstance.addImage(id, ctx.getImageData(0, 0, size, size), { pixelRatio: 2 });
        };
        createAndAddIcon('icon-ref-salud', '🏥'); createAndAddIcon('icon-ref-edu', '🎓');
        createAndAddIcon('icon-ref-gob', '🏛️'); createAndAddIcon('icon-ref-deporte', '⚽');
        createAndAddIcon('icon-ref-transporte', '🚌'); createAndAddIcon('icon-ref-comercio', '🛒');
        createAndAddIcon('icon-ref-parque', '🌳');
        createAndAddIcon('icon-ref-hito', '📍');

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

        // =========================================================
        // 💧 DEBUG DE AGUA (TEMPORAL)
        // =========================================================
        window.redVialMapInstance.on('click', (e) => {
            const waterFeatures = window.redVialMapInstance.queryRenderedFeatures(e.point)
                .filter(f => f.sourceLayer === 'water' || f.sourceLayer === 'waterway' || f.layer.id === 'water');
            
            if (waterFeatures.length > 0) {
                console.log(`\n💧 === CLIC EN ZONA DE AGUA ===`);
                waterFeatures.forEach((f, i) => {
                    console.log(`[${i+1}] Layer ID: ${f.layer.id} | Source-Layer: ${f.sourceLayer}`);
                    console.log(`    Kind: ${f.properties.kind || 'N/A'} | Detail: ${f.properties.kind_detail || 'N/A'} | Name: ${f.properties.name || 'N/A'}`);
                    console.log(`    Geometry Type: ${f.geometry.type}`);
                });
            }
        });

        if (DEBUG_RV) {
            // Diagnóstico Inicial (Centro de la pantalla tras 2 segundos de carga)
            setTimeout(() => {
                const centerPoint = window.redVialMapInstance.project(window.redVialMapInstance.getCenter());
                inspectFeatures(window.redVialMapInstance.queryRenderedFeatures(centerPoint), "CENTRO DEL MAPA (INICIO)");
            }, 2000);

            // Diagnóstico por Clic (Global y pasivo, NO rompe los paneles)
            window.redVialMapInstance.on('click', (e) => {
                inspectFeatures(window.redVialMapInstance.queryRenderedFeatures(e.point), "CLIC DEL USUARIO");
            });
        }
        
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

        if (DEBUG_RV) {
            window.redVialMapInstance.on('moveend', scanFeatures);
            setTimeout(scanFeatures, 3000); // Primer escaneo automático
        }
        
        // =========================================================
        // 🔍 AUDITORÍA OBJETIVA FASE D: REFERENCIAS URBANAS
        // =========================================================
        if (DEBUG_RV) {
            setTimeout(() => {
                console.log("\n=== AUDITORÍA OBJETIVA: REFERENCIAS URBANAS ===");
                const layers = window.redVialMapInstance.getStyle().layers;
                console.log("Captura 1: Stack de capas completo", layers);
    
                const refLayerId = 'ref-urbanas-pois';
                const refLayer = layers.find(l => l.id === refLayerId);
                
                if (!refLayer) {
                    console.log(`1. ¿La capa existe?: NO SE ENCONTRÓ '${refLayerId}'`);
                    return;
                }
    
                console.log("1. ¿La capa existe?: SÍ");
                console.log("2. ID exacto:", refLayer.id);
                console.log("3. source-layer exacto:", refLayer['source-layer']);
            }, 3500);
        }

        // =========================================================
        // ⏱️ LÓGICA DE MEDICIÓN (RV5-PERF-A1)
        // =========================================================
        
        if (PERF_RV) {
            window.redVialMapInstance.on('sourcedata', (e) => {
                if (e.isSourceLoaded) {
                    if (e.sourceId === 'tramos-viales' && !window._rv_perf_vias) {
                        window._rv_perf_vias = true;
                        performance.mark('rv_api_vias_ok');
                    }
                    if (e.sourceId === 'referencias-estrategicas' && !window._rv_perf_ref) {
                        window._rv_perf_ref = true;
                        performance.mark('rv_api_ref_ok');
                    }
                    if (e.sourceId === 'protomaps' && !window._rv_perf_pmtiles) {
                        window._rv_perf_pmtiles = true;
                        performance.mark('rv_pmtiles_source_ok');
                    }
                }
            });

            window.redVialMapInstance.once('idle', () => {
                try {
                    performance.mark('rv_mapa_idle');
                    console.log('\n=== 📊 AUDITORÍA FINA DE CARGA (RV5-PERF-A1.1) ===');
                    
                    // 1. Network Waterfall
                    const resources = performance.getEntriesByType('resource')
                        .filter(r => r.name.match(/pmtiles|maplibre|php|pbf|json|png|webp|css|js|font/i))
                        .map(r => ({
                            Recurso: r.name.split('/').pop().split('?')[0].substring(0, 30) || r.name.substring(0, 30),
                            Duracion_ms: parseFloat(r.duration.toFixed(2)),
                            Transfer_KB: parseFloat((r.transferSize / 1024).toFixed(1)),
                            Tipo: r.initiatorType
                        }))
                        .sort((a, b) => b.Duracion_ms - a.Duracion_ms)
                        .slice(0, 15);
                    
                    console.log('Top 15 Recursos más pesados/lentos:');
                    console.table(resources);

                    // 2. Fases Granulares
                    performance.measure('A. Mapa Creado -> Style Load', 'rv_mapa_creado', 'rv_style_load');
                    performance.measure('B. Style Load -> First Data', 'rv_style_load', 'rv_first_data');
                    performance.measure('C. First Data -> First Render', 'rv_first_data', 'rv_first_render');
                    performance.measure('D. First Render -> Event Load', 'rv_first_render', 'rv_mapa_load');
                    if (window._rv_perf_pmtiles) performance.measure('E. Carga Base PMTiles Source', 'rv_mapa_creado', 'rv_pmtiles_source_ok');
                    
                    const measures = performance.getEntriesByType('measure').filter(m => m.name.match(/^[A-E]\./));
                    console.table(measures.map(m => ({
                        'Fase Interna MapLibre': m.name,
                        'Duración (ms)': parseFloat(m.duration.toFixed(2))
                    })));
                } catch(e) { 
                    console.log('Esperando métricas...', e); 
                }
            });
        }

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
                    <label class="rv-panel-item"><input type="checkbox" data-layer="ref-urbanas"> 📍 Referencias Clave</label>
                    <div id="ref-urbanas-list" class="rv-ref-list" style="display: none;">
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="font-size: 11px; color: #64748b; padding: 4px 0;">⏳ Cargando referencias...</li>
                        </ul>
                    </div>
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
                    <label class="rv-panel-item"><input type="checkbox" data-layer="srv-negocios"> 🏪 Negocios Locales</label>
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
            if (profile === 'ciudadano') { Object.assign(t, { water: true, parks: true, buildings: true, buildings3d: false, boundaries: false, transit: false, 'places-text': true, 'ref-urbanas': true, roads: true, 'srv-edu': true, 'srv-salud': true, 'srv-seguridad': true, 'srv-gobierno': true, 'srv-mercados': true, 'srv-deporte': true, 'srv-transporte': true, 'srv-negocios': true }); window.redVialMapInstance.easeTo({ pitch: 0, bearing: 0 }); }
            if (profile === 'tecnico') { Object.assign(t, { water: false, parks: false, buildings: false, buildings3d: false, boundaries: true, transit: true, 'places-text': true, 'ref-urbanas': true, roads: true, 'srv-edu': false, 'srv-salud': false, 'srv-seguridad': false, 'srv-gobierno': false, 'srv-mercados': false, 'srv-deporte': false, 'srv-transporte': false, 'srv-negocios': false }); window.redVialMapInstance.easeTo({ pitch: 0, bearing: 0 }); }
            if (profile === 'impacto') { Object.assign(t, { water: true, parks: false, buildings: false, buildings3d: true, boundaries: true, transit: false, 'places-text': false, 'ref-urbanas': true, roads: true, 'srv-edu': false, 'srv-salud': false, 'srv-seguridad': false, 'srv-gobierno': false, 'srv-mercados': false, 'srv-deporte': false, 'srv-transporte': false, 'srv-negocios': false }); window.redVialMapInstance.easeTo({ pitch: 60, bearing: -20 }); }
            
            // Sincronizar checkboxes avanzados
            Object.keys(t).forEach(key => { const chk = panel.querySelector(`input[data-layer="${key}"]`); if (chk) chk.checked = t[key]; });

            // Sincronizar lista de referencias al cambiar de perfil
            const refList = document.getElementById('ref-urbanas-list');
            if (refList) {
                refList.style.display = t['ref-urbanas'] ? 'block' : 'none';
            }

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

            // Lógica para mostrar/ocultar lista de Referencias Urbanas
            if (layerKey === 'ref-urbanas') {
                const list = document.getElementById('ref-urbanas-list');
                if (list) {
                    list.style.display = e.target.checked ? 'block' : 'none';
                }
            }

            window.rvApplyStyle();
        });
    });

    // Sincronización inicial de la lista de referencias
    const refList = document.getElementById('ref-urbanas-list');
    if (refList) {
        refList.style.display = window.rvStyleConfig.toggles['ref-urbanas'] ? 'block' : 'none';
        
        // Cargar desde la BD y dibujar la lista dinámica con vuelos de cámara
        fetch('../panel-admin-universo/mapa_referencias_api.php?action=geojson')
            .then(res => res.json())
            .then(data => {
                const ul = refList.querySelector('ul');
                if (!ul) return;
                ul.innerHTML = '';
                if (!data.features || data.features.length === 0) {
                    ul.innerHTML = '<li style="font-size: 11px; color: #64748b; padding: 4px 0;">No hay referencias guardadas.</li>';
                    return;
                }
                const iconMap = { 'salud':'🏥', 'educacion':'🎓', 'gobierno':'🏛️', 'deporte':'⚽', 'transporte':'🚌', 'comercio':'🛒', 'hito':'📍' };
                data.features.forEach(f => {
                    const li = document.createElement('li');
                    li.style.cssText = 'padding: 6px 0; cursor: pointer; border-bottom: 1px solid rgba(0,0,0,0.05); transition: color 0.2s; font-size: 12px; color: #334155; display: flex; align-items: center; gap: 6px;';
                    li.onmouseover = () => li.style.color = '#801039';
                    li.onmouseout = () => li.style.color = '#334155';
                    li.innerHTML = `<span>${iconMap[f.properties.icon_type] || '📍'}</span> <span>${f.properties.name}</span>`;
                    li.addEventListener('click', () => {
                        if (window.redVialMapInstance) {
                            window.redVialMapInstance.flyTo({ center: f.geometry.coordinates, zoom: Math.max(window.redVialMapInstance.getZoom(), 14), speed: 1.2 });
                        }
                    });
                    ul.appendChild(li);
                });
            })
            .catch(err => console.error('Error cargando referencias:', err));
    }
}

function rv_escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function rv_getSafeColor(colorStr, fallback = '#801039') {
    if (!colorStr) return fallback;
    const hexRegex = /^#([0-9A-Fa-f]{3}){1,2}$/;
    return hexRegex.test(colorStr) ? colorStr : fallback;
}

window.rv_compartirViaSeguro = function(idEncoded, nombreRaw) {
    const shareUrl = new URL(window.location.href);
    if (idEncoded) {
        shareUrl.searchParams.set('via', idEncoded);
    }
    const url = shareUrl.toString();

    if (navigator.share) {
        navigator.share({
            title: nombreRaw + ' - Fuerza Tacna',
            text: 'Conoce los detalles de esta vía en el mapa interactivo:',
            url: url
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(url).then(() => {
            const btn = document.getElementById('btnCompartirRV');
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ ¡Enlace copiado!';
                setTimeout(() => { if(btn) btn.innerHTML = originalText; }, 2500);
            }
        });
    }
}

// Helpers de Renderizado Condicional y Formato
function rv_hasValue(val) {
    return val !== null && val !== undefined && String(val).trim() !== '';
}

function rv_formatMoney(val) {
    const num = parseFloat(val);
    if (isNaN(num) || num <= 0) return '';
    if (num >= 1000000) {
        let m = num / 1000000;
        return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' millones';
    }
    return 'S/ ' + num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function rv_formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return dateStr;
}

function rv_formatBeneficiarios(val) {
    if (!val) return '';
    const str = String(val).trim();
    if (/^\d+$/.test(str)) return parseInt(str, 10).toLocaleString('en-US') + ' beneficiarios';
    return str;
}

function rv_formatLongitud(props) {
    const val = props.longitud_valor;
    const unit = props.longitud_unidad;
    if (rv_hasValue(val) && rv_hasValue(unit)) {
        return `${val} ${unit}`;
    }
    // Fallback retrocompatibilidad
    return rv_hasValue(props.longitud) ? props.longitud : '';
}

function abrirPanelRedVial(props = {}) {
    const panel = document.getElementById('redVialInfoPanel');
    if (!panel) return;
    
    const nombreSafe = rv_escapeHTML(props.nombre);
    const tipoSafe = rv_escapeHTML(props.tipo || 'Local');
    const estadoSafe = rv_escapeHTML(props.estado || 'En estudios');
    const colorSafe = rv_getSafeColor(props.color, '#801039');
    const rawId = props.id || props.string_id || '';
    const idEncoded = rawId ? encodeURIComponent(rawId) : '';
    const descSafe = rv_escapeHTML(props.descripcion || '').replace(/\n/g, '<br>');

    // RV3-C3: Extracción Segura de Nuevos Campos Estratégicos
    const mensajeSafe = rv_escapeHTML(props.mensaje_principal);
    const distritoSafe = rv_escapeHTML(props.distrito);
    const sectorSafe = rv_escapeHTML(props.sector);
    const desdeSafe = rv_escapeHTML(props.tramo_desde);
    const hastaSafe = rv_escapeHTML(props.tramo_hasta);
    const longitudSafe = rv_escapeHTML(rv_formatLongitud(props));
    const benefSafe = rv_escapeHTML(rv_formatBeneficiarios(props.beneficiarios));
    const antesSafe = rv_escapeHTML(props.situacion_antes).replace(/\n/g, '<br>');
    const ahoraSafe = rv_escapeHTML(props.situacion_ahora).replace(/\n/g, '<br>');
    const inicioSafe = rv_formatDate(props.fecha_inicio);
    const entregaSafe = rv_formatDate(props.fecha_entrega);

    const avance = props.avance_fisico;
    const hasAvance = rv_hasValue(avance) && !isNaN(avance) && avance >= 0 && avance <= 100;
    const montoSafe = rv_formatMoney(props.monto_inversion);

    let estadoClass = 'pill--estudios';
    const estLow = estadoSafe.toLowerCase();
    if (estLow.includes('entregado')) estadoClass = 'pill--entregado';
    else if (estLow.includes('ejecución') || estLow.includes('construccion')) estadoClass = 'pill--construccion';
    else if (estLow.includes('paralizado')) estadoClass = 'pill--paralizado';
    else if (estLow.includes('buena pro')) estadoClass = 'pill--buenapro';
    else if (estLow.includes('transferencia')) estadoClass = 'pill--transferencia';

    // ==========================================
    // CONSTRUCCIÓN CONDICIONAL DE BLOQUES
    // ==========================================
    let htmlMensaje = '';
    if (rv_hasValue(mensajeSafe)) htmlMensaje = `<div class="rd-rv-hook" style="color: ${colorSafe};">${mensajeSafe}</div>`;

    // Ubicación (Debajo del título)
    let locParts = [];
    if (rv_hasValue(distritoSafe)) locParts.push(`<strong>${distritoSafe}</strong>`);
    if (rv_hasValue(sectorSafe)) locParts.push(`Sector ${sectorSafe}`);
    let htmlUbicacion = locParts.length > 0 ? `<div class="rd-rv-location"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg> ${locParts.join(' &middot; ')}</div>` : '';

    // ==========================================
    // JERARQUÍA DE MÉTRICAS (RV3-C3.2)
    // ==========================================
    
    // NIVEL 1: PROTAGONISTAS (Inversión y Longitud)
    let htmlNivel1 = '';
    if (rv_hasValue(montoSafe) || rv_hasValue(longitudSafe)) {
        htmlNivel1 += `<div class="rd-rv-metrics-primary">`;
        if (rv_hasValue(montoSafe)) {
            htmlNivel1 += `<div class="rd-rv-metric-card"><span class="rd-rv-metric-val">${montoSafe}</span><span class="rd-rv-metric-lbl">💰 Inversión</span></div>`;
        }
        if (rv_hasValue(longitudSafe)) {
            let cuadrasHtml = '';
            const cVal = props.longitud_cuadras;
            if (rv_hasValue(cVal) && parseFloat(cVal) > 0) cuadrasHtml = `<span class="rd-rv-metric-sub">Equivale aprox. a ${cVal} cuadras</span>`;
            htmlNivel1 += `<div class="rd-rv-metric-card"><span class="rd-rv-metric-val">${longitudSafe}</span><span class="rd-rv-metric-lbl">📏 Longitud</span>${cuadrasHtml}</div>`;
        }
        htmlNivel1 += `</div>`;
    }

    // NIVEL 2: AVANCE FÍSICO (Secundario pero altamente visible)
    let htmlNivel2 = '';
    if (hasAvance) {
        let avanceTxt = `${avance}%`;
        if (estLow.includes('entregado') && parseInt(avance) === 100) avanceTxt = '100%';
        htmlNivel2 = `
        <div class="rd-rv-metrics-secondary">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <span class="rd-rv-metric-lbl">📊 Avance</span>
                <span style="color:${colorSafe}; font-size:14px; font-weight:900; line-height:1;">${avanceTxt}</span>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:999px; overflow:hidden;">
                <div style="height:100%; width:${avance}%; background-color:${colorSafe}; border-radius:999px;"></div>
            </div>
        </div>`;
    }

    // NIVEL 3: DATOS SECUNDARIOS Y CONTEXTO
    let secItems = '';
    if (rv_hasValue(benefSafe)) secItems += `<div class="rd-rv-sec-item"><span>👥</span> <div><strong>Beneficiarios:</strong> ${benefSafe}</div></div>`;
    if (rv_hasValue(inicioSafe)) secItems += `<div class="rd-rv-sec-item"><span>📅</span> <div><strong>Inicio:</strong> ${inicioSafe}</div></div>`;
    if (rv_hasValue(entregaSafe)) secItems += `<div class="rd-rv-sec-item"><span>🏁</span> <div><strong>Entrega:</strong> ${entregaSafe}</div></div>`;
    let htmlSecondary = secItems ? `<div class="rd-rv-sec-list">${secItems}</div>` : '';

    let htmlTramo = '';
    let tramoTxt = '';
    if (rv_hasValue(desdeSafe) && rv_hasValue(hastaSafe)) tramoTxt = `Desde <strong>${desdeSafe}</strong> hasta <strong>${hastaSafe}</strong>`;
    if (rv_hasValue(sectorSafe)) {
        if (tramoTxt) tramoTxt += ` &middot; Sector <strong>${sectorSafe}</strong>`;
        else tramoTxt = `Sector: <strong>${sectorSafe}</strong>`;
    }
    if (tramoTxt) htmlTramo = `<div class="rd-rv-tramo-compact"><span>📍</span> <div>${tramoTxt}</div></div>`;

    // NIVEL 3: NARRATIVA (Descripción y Antes/Ahora)
    let htmlAntesAhora = '';
    if (rv_hasValue(antesSafe) || rv_hasValue(ahoraSafe)) {
        htmlAntesAhora = `<div class="rd-rv-antes-ahora">`;
        if (rv_hasValue(antesSafe)) htmlAntesAhora += `<div class="rd-rv-aa-card rd-rv-antes"><span class="rd-rv-aa-badge">Antes</span><p>${antesSafe}</p></div>`;
        if (rv_hasValue(ahoraSafe)) htmlAntesAhora += `<div class="rd-rv-aa-card rd-rv-ahora"><span class="rd-rv-aa-badge" style="background:${colorSafe};">Ahora</span><p>${ahoraSafe}</p></div>`;
        htmlAntesAhora += `</div>`;
    }

    let htmlDesc = '';
    if (rv_hasValue(descSafe)) {
        htmlDesc = `
        <div class="rd-rv-desc-block">
            <div id="rvDescText" class="rd-rv-desc-clamp"><p>${descSafe}</p></div>
            <button id="rvBtnMoreDesc" class="rd-rv-btn-more" style="display: none;">Ver más</button>
        </div>`;
    }

    let htmlNoData = '';
    if (!htmlNivel1 && !htmlNivel2 && !htmlSecondary && !htmlTramo && !htmlDesc && !htmlAntesAhora) {
        htmlNoData = `<p style="color:#94a3b8; font-style:italic; font-size:13px; margin-top:5px;">No hay información adicional disponible para este tramo.</p>`;
    }

    panel.innerHTML = `
        <div class="rd-rv-panel" style="position: relative;">
            <button id="btnCerrarRV" class="sheet-close" title="Cerrar panel" style="z-index: 10;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div id="rv-hero-container" class="rd-rv-hero" style="display: none;">
                <div class="rd-rv-hero-gradient"></div>
            </div>
            <div class="rd-rv-header" style="border-left-color: ${colorSafe};">
                <div class="rd-rv-pills">
                    <span class="pill ${estadoClass}">${estadoSafe}</span>
                    <span class="pill pill--tag">${tipoSafe}</span>
                </div>
                <h2 class="rd-rv-title" style="color: ${colorSafe};">${nombreSafe}</h2>
                ${htmlUbicacion}
                ${htmlMensaje}
            </div>
            <div class="rd-rv-body">
                ${htmlNivel1}
                ${htmlNivel2}
                ${htmlSecondary}
                ${htmlTramo}
                ${htmlDesc}
                ${htmlAntesAhora}
                ${htmlNoData}
            </div>
            <div id="rv-gallery-container" style="display: none; padding: 0 15px 12px 15px;"></div>
            <div class="rd-rv-footer">
                <button id="btnCompartirRV" class="btn-share">🔗 Compartir Vía</button>
            </div>
        </div>
    `;
    
    panel.querySelector('#btnCerrarRV').addEventListener('click', () => {
        panel.classList.remove('is-active');
    });

    panel.querySelector('#btnCompartirRV').addEventListener('click', () => {
        window.rv_compartirViaSeguro(idEncoded, props.nombre || 'Tramo Vial'); 
    });
    
    // Lógica "Ver más" de la descripción
    const btnMore = panel.querySelector('#rvBtnMoreDesc');
    const descText = panel.querySelector('#rvDescText');
    if (btnMore && descText) {
        setTimeout(() => {
            if (descText.scrollHeight > descText.clientHeight) {
                btnMore.style.display = 'block';
            }
        }, 20); // Retraso mínimo para asegurar render del DOM
        
        btnMore.addEventListener('click', () => {
            descText.classList.toggle('rd-rv-desc-clamp');
            btnMore.textContent = descText.classList.contains('rd-rv-desc-clamp') ? 'Ver más' : 'Ver menos';
        });
    }
    
    // Petición Asíncrona (Lazy Load) de la Galería Pública
    if (rawId) {
        fetch(`../panel-admin-universo/fotos_redvial_api.php?action=listar_publico&tramo_id=${idEncoded}`)
            .then(res => res.json())
            .then(data => {
                if (data.ok && data.fotos && data.fotos.length > 0) {
                    // Buscar la foto marcada como portada, o usar la primera como fallback
                    const portada = data.fotos.find(f => f.tipo === 'portada') || data.fotos[0];
                    const heroEl = document.getElementById('rv-hero-container');
                    
                    if (portada) {
                        if (heroEl) {
                            const imgUrl = `IMG/red-vial/${idEncoded}/${encodeURIComponent(portada.archivo)}`;
                            heroEl.style.backgroundImage = `url('${imgUrl}')`;
                            heroEl.style.display = 'block';
                        }

                        // Mostrar mini galería si hay más de 1 foto
                        if (data.fotos.length > 1) {
                            const galContainer = document.getElementById('rv-gallery-container');
                            if (galContainer) {
                                let galHtml = '<div class="rd-rv-gallery">';
                                data.fotos.forEach((f) => {
                                    const thumbUrl = `IMG/red-vial/${idEncoded}/${encodeURIComponent(f.archivo_thumb)}`;
                                    const fullUrl = `IMG/red-vial/${idEncoded}/${encodeURIComponent(f.archivo)}`;
                                    const isActive = f.id === portada.id;
                                    const borderStyle = isActive ? `border: 2px solid ${colorSafe}; opacity: 1;` : `border: 2px solid transparent; opacity: 0.6;`;
                                    galHtml += `<div class="rd-rv-thumb" data-full="${fullUrl}" style="background-image: url('${thumbUrl}'); ${borderStyle}"></div>`;
                                });
                                galHtml += '</div>';
                                galContainer.innerHTML = galHtml;
                                galContainer.style.display = 'block';

                                const thumbs = galContainer.querySelectorAll('.rd-rv-thumb');
                                thumbs.forEach(th => {
                                    th.addEventListener('click', function() {
                                        const fullSrc = this.getAttribute('data-full');
                                        if (heroEl) heroEl.style.backgroundImage = `url('${fullSrc}')`;
                                        thumbs.forEach(t => { t.style.border = '2px solid transparent'; t.style.opacity = '0.6'; });
                                        this.style.border = `2px solid ${colorSafe}`;
                                        this.style.opacity = '1';
                                    });
                                });
                            }
                        }
                    }
                }
            })
            .catch(err => {
                console.warn("[Red Vial] Ocurrió un error al consultar la galería pública:", err);
            });
    }

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
