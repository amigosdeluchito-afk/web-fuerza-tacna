/* =========================================================
   RED-VIAL-MODULE.JS - Sandbox Independiente
   ========================================================= */

window.redVialMapInstance = null;

window.initRedVial = async function() {
    // Guard Clause de seguridad
    if (window.redVialMapInstance) return;
    
    // Asegurar que MapLibre esté disponible (fallback por si no se cargó globalmente).
    if (typeof window.maplibregl === 'undefined') {
        if (typeof window.loadMapLibre === 'function') {
            await window.loadMapLibre();
        } else {
            console.error("[Red Vial] Error: MapLibre GL no está disponible.");
            return;
        }
    }

    console.log("[Red Vial] Inicializando mapa MapLibre bajo demanda...");
    
    // 1. Instanciar mapa anclado a #red-vial-map-container
    // 2. Cargar assets/data/tramos-viales.geojson
    // 3. Renderizar capas y asignar eventos de clic
};

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
        console.log("[Red Vial] Mapa destruido para liberar memoria (Barba.js).");
    }
};