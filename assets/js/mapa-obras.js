/* =========================================================
   MAPA-OBRAS.JS - CONFIGURACIÓN GLOBAL Y NÚCLEO DEL MAPA
   ========================================================= */

async function loadMapLibre() {
    if (window.maplibregl) return;
    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css';
        document.head.appendChild(link);
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js';
        script.onload = resolve;
        script.onerror = resolve; // Failsafe para redes inestables
        document.head.appendChild(script);
    });
}

window.initMapEngine = async function(container) {
    const target = container || document;
    const mapEl = target.querySelector('#map') || document.getElementById('map');
    if (!mapEl) return;

    // =================================================================================
    // RESTAURADOR DE TEMÁTICA VHS / HOLOGRAMA (ALCALDE PROVINCIAL)
    // Reactiva los estilos que fueron comentados en el CSS por "lag gráfico", 
    // inyectándolos dinámicamente SOLO en este segmento para cuidar el rendimiento.
    // =================================================================================
    if (!document.getElementById('theme-alcalde-vhs')) {
        const vhsStyle = document.createElement('style');
        vhsStyle.id = 'theme-alcalde-vhs';
        vhsStyle.innerHTML = `
            /* Tipografía y efecto retro/VHS para el botón */
            .chip[data-map="alcalde_provincial"] {
                font-family: "Courier New", monospace !important;
                letter-spacing: 1px;
                text-shadow: 1px 0px 2px rgba(255,0,0,0.8), -1px 0px 2px rgba(0,0,255,0.8);
                background: rgba(20, 20, 20, 0.8) !important;
                border: 1px solid rgba(255, 195, 0, 0.5) !important;
            }
            .chip[data-map="alcalde_provincial"].is-active {
                background: #801039 !important;
                color: #ffc300 !important;
                box-shadow: 0 0 15px rgba(255,195,0,0.6);
                animation: vhs-glitch-text 4s infinite;
            }
            @keyframes vhs-glitch-text {
                0%, 100% { transform: skewX(0deg); }
                2% { transform: skewX(4deg); }
                4% { transform: skewX(-4deg); }
                6% { transform: skewX(0deg); }
            }
            /* Reactivación del Mapa Holograma (Animación Autocad / VHS) */
            body.segmento-alcalde_provincial #synced-svg-container svg path {
                stroke-dasharray: 200 30 !important;
                animation: hologram-draw 6s linear infinite !important;
                filter: drop-shadow(0 0 10px rgba(255, 195, 0, 0.8));
            }
        `;
        document.head.appendChild(vhsStyle);
    }

    // =================================================================================
    // FIX CRÍTICO: RESTAURACIÓN DE VARIABLES DE ENTORNO
    // Al faltar estas variables, la función revealUI lanzaba un error fatal invisible 
    // y el menú jamás llegaba a aparecer.
    // =================================================================================
    let currentKey = 'base';
    let isSwapping = false;
    let pendingKey = null;
    let isAutoCenterBlocked = false;


    // Limpieza de mapa previo para que no choque con Barba.js
    if (window.mapInstance) {
        window.mapInstance.remove();
        window.mapInstance = null;
    }

    // Función auxiliar para buscar elementos solo dentro del nuevo contenedor
    const getEl = (id) => target.querySelector('#' + id) || document.getElementById(id);


    const revealUI = () => {
        const chips = target.querySelector('.chips') || document.querySelector('.chips');
        const dock = getEl('filtersDock');
        const mapEl = getEl('map');
        const footer = getEl('home-footer');
        
        if (chips) { 
            chips.style.opacity = '1'; 
            chips.style.visibility = 'visible'; 
        }
        if (footer && currentKey === 'base') {
            footer.classList.add('is-visible');
        }
        if (dock) {
            if (currentKey === 'base') {
                dock.style.opacity = '0';
                dock.style.visibility = 'hidden';
            } else {
                dock.style.opacity = '1';
                dock.style.visibility = 'visible';
            }
        }
        if (mapEl) {
            mapEl.style.opacity = '1';
            mapEl.style.visibility = 'visible';
            mapEl.style.pointerEvents = 'auto';
        }

        console.log("UI Revelada con máxima prioridad absoluta");
    };

    // Configuraciones
    const stepsBack = () => (window.innerWidth <= 900 ? 4 : 3);

    const FOCUS = { base: [0.50, 0.50], educacion: [0.50, 0.50], agua: [0.50, 0.50], transporte: [0.50, 0.50], agricultura: [0.50, 0.50], social: [0.50, 0.50], vias: [0.50, 0.50] };

    function updateLegendVisibility(key){
        const el = getEl('legend');
        if (el) el.style.display = (key === 'base' || key === 'alcalde_provincial') ? 'none' : 'block';
    }

    function padBounds(b, pct){
        const [[t,l],[bttm,r]] = b;
        const w = r - l, h = bttm - t;
        return [[t - h*pct, l - w*pct],[bttm + h*pct, r + w*pct]];
    }

    function setBackgroundFor(key){
        const bg = target.querySelector('.app-bg');
        if (bg) {
            bg.classList.add("show");
            
            // INYECCIÓN FÍSICA DEL PATRÓN
            if (!bg.querySelector('.map-pattern-layer')) {
                const pattern = document.createElement('div');
                pattern.className = 'map-pattern-layer';
                
                // FIX: Se calcula la ruta al fondo 'pattern.svg' dinámicamente.
                // Esto evita errores 404 al usar una única ruta correcta en lugar de fallbacks.
                const basePath = window.location.pathname.includes('/assets/') ? '../../' : '';
                const paths = `url('${basePath}assets/img/pattern.svg')`;
                
                pattern.style.cssText = `
                    position: absolute;
                    top: 0; left: 0; width: 100%; height: 100%;
                    background-image: ${paths};
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    opacity: 0.35; /* <-- Bájalo a 0.15 si lo ves muy fuerte */
                    /* mix-blend-mode: multiply; OPTIMIZADO: Asfixiaba el Main Thread en combinación con el video */
                    pointer-events: none; /* No bloquea los clics del mapa */
                    z-index: 1;
                `;
                
                if (window.getComputedStyle(bg).position === 'static') {
                    bg.style.position = 'relative';
                }
                bg.appendChild(pattern);
            }
        }
    }

    let lastHudLabel = 'Inicio';
    function updateHud(label){
        if (label) lastHudLabel = label;
        const hud = getEl('zoomHud');
        if (hud) hud.textContent = `${lastHudLabel} | zoom: ${map.getZoom().toFixed(2)}`;
    }

    await loadMapLibre();

    if (typeof maplibregl === 'undefined') {
        console.error("[Mapa] Error crítico: MapLibre GL no pudo cargarse desde el CDN. Verifica tu conexión a internet o bloqueadores de anuncios.");
        return;
    }

    // =================================================================================
    // FIX SUPREMO: CROSS-ORIGIN WORKER (SECURITY ERROR)
    // Los navegadores bloquean la creación directa de Workers desde dominios externos (unpkg).
    // Creamos un archivo "virtual" local en la memoria del navegador (Blob) 
    // que importe el código externo de forma 100% legal y permitida por el navegador.
    // =================================================================================
    /*
    if (window.maplibregl) {
        try {
            const workerCode = "importScripts('https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl-worker.js');";
            const workerBlob = new Blob([workerCode], { type: 'text/javascript' });
            maplibregl.workerUrl = URL.createObjectURL(workerBlob);
        } catch (e) {
            console.warn("[Mapa] Fallo al crear el Blob Worker. Dependiendo del motor interno.");
        }
    }
    */

    const map = new maplibregl.Map({
        container: mapEl,
        style: { 
            version: 8, 
            sources: {}, 
            layers: [],
            glyphs: "https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf" 
        },
        center: [0, 0],
        zoom: 0,
        minZoom: 2,
        maxZoom: 12, /* Bloquea el colapso de precisión de WebGL en acercamientos extremos */
        maxPitch: 0,
        dragRotate: false,
        attributionControl: false
    });
    
    mapEl.style.background = 'transparent';
    window.mapInstance = map;
    
    const IS_MOBILE = window.matchMedia('(max-width: 600px)').matches;
    // if (!IS_MOBILE) map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');

    // NUEVO: Actualizar el contador en tiempo real al hacer scroll
    map.on('zoom', () => updateHud());

    // =================================================================================
    // FASE 2: INYECCIÓN DEL MAPA SVG VECTORIAL (HACKER SHORTCUT)
    // =================================================================================
    const canvasContainer = map.getCanvasContainer();
    const svgContainer = document.createElement('div');
    svgContainer.id = 'synced-svg-container';
    svgContainer.style.position = 'absolute';
    svgContainer.style.top = '0';
    svgContainer.style.left = '0';
    svgContainer.style.transformOrigin = 'top left';
    svgContainer.style.pointerEvents = 'none'; // Deja pasar el scroll y clics hacia el mapa
    canvasContainer.insertBefore(svgContainer, canvasContainer.firstChild);

    // Cargamos tu archivo SVG original con la ruta relativa correcta
    fetch('../img/MAPA%20TACNA.svg')
        .then(response => response.text())
        .then(svgText => {
            svgContainer.innerHTML = svgText;
            const svg = svgContainer.querySelector('svg');
            if(svg) {
                // AUTO-REPARADOR: Aseguramos que la computadora sepa la matemática del archivo
                if(!svg.getAttribute('viewBox')) svg.setAttribute('viewBox', '0 0 1093.78 1035.32');
                svg.style.width = '1093.78px';
                svg.style.height = '1035.32px';
                svg.style.display = 'block';
                
                // --- NUEVO: Etiquetado espacial inteligente (Geometría) ---
                // Detectamos matemáticamente cuál es la provincia más al sur (abajo) 
                // sin depender de los IDs ocultos del SVG, que pueden estar desordenados.
                setTimeout(() => {
                    let maxCenterY = -1;
                    let bottomElement = null;
                    const elements = svg.querySelectorAll('path, polygon');
                    
                    elements.forEach(el => {
                        try {
                            const bbox = el.getBBox();
                            const centerY = bbox.y + (bbox.height / 2);
                            if (centerY > maxCenterY) { maxCenterY = centerY; bottomElement = el; }
                        } catch(e) {}
                    });
                    
                    elements.forEach(el => {
                        if (el === bottomElement) el.classList.add('provincia-tacna');
                        else el.classList.add('provincia-secundaria');
                    });
                }, 100);
            }
        }).catch(e => console.error("Error al cargar el archivo SVG:", e));

    // El motor que sincroniza el DOM con la cámara 3D a 60FPS
    function syncSVG() {
        if(!svgContainer.querySelector('svg')) return;
        const lon = 1093.78 * 0.005; 
        const lat = 1035.32 * 0.005;
        const topLeft = map.project([0, lat]);
        const bottomRight = map.project([lon, 0]);
        const w = bottomRight.x - topLeft.x;
        const h = bottomRight.y - topLeft.y;
        svgContainer.style.transform = `translate(${topLeft.x}px, ${topLeft.y}px) scale(${w / 1093.78}, ${h / 1035.32})`;
    }
    map.on('render', syncSVG);
    // =================================================================================

    // =================================================================================
    // FASE 3: INTERACTIVIDAD DE LOS PINES (CLICS Y TARJETAS FANTASMA)
    // Usamos el motor GPU de MapLibre para atrapar eventos sin lag
    // =================================================================================
    const ghostTooltip = new maplibregl.Popup({
        closeButton: false, closeOnClick: false, className: 'ghost-card-popup', offset: [0, -10], maxWidth: '300px', anchor: 'bottom'
    });

    // Generamos un único timestamp por sesión para no asfixiar la red al pasar el ratón
    const sessionTs = new Date().getTime();
    let currentHoverKey = null; // Memoria para no redibujar la misma tarjeta inútilmente
    let currentHoverId = null;  // ID numérico estricto para WebGL feature-state
    let hoverIntentTimer = null; // NUEVO: Temporizador anti-colapso
    let hoverRAF = null;
    let lastMouseEvent = null;
    
    // VARIABLES PARA EL EFECTO RADAR (LATIDO EN GPU)
    let isPulsing = false;
    let pulsePhase = 0;

    function renderPulse() {
        if (!currentHoverKey || !map.getLayer('obras-pulse-layer')) {
            isPulsing = false;
            return; // Detenemos la animación si no hay hover para ahorrar batería
        }
        pulsePhase = (pulsePhase + 0.008) % 1; // Latido extremadamente lento (antes 0.02)
        
        // Efecto "ease-out" para que la onda empiece fuerte y frene al desaparecer
        const easedPhase = 1 - Math.pow(1 - pulsePhase, 3);
        
        map.setPaintProperty('obras-pulse-layer', 'circle-radius', [
            'case', ['==', ['get', 'id'], currentHoverKey],
            12 + (easedPhase * 8), // Expansión súper cortita y pegada al pin (solo 8px extra)
            0
        ]);
        map.setPaintProperty('obras-pulse-layer', 'circle-opacity', [
            'case', ['==', ['get', 'id'], currentHoverKey],
            (1 - pulsePhase) * 0.8, // Inicia un poco más visible (80%) para notar la onda pequeña
            0
        ]);
        
        map.triggerRepaint(); // Forzar el renderizado a 60 FPS

        if (isPulsing) requestAnimationFrame(renderPulse);
    }

    const clearHoverState = () => {
        if (currentHoverId !== null && map.getSource('obras-source')) {
            map.setFeatureState({ source: 'obras-source', id: currentHoverId }, { hover: false });
        }
        if (currentHoverKey !== null) {
            currentHoverKey = null;
            currentHoverId = null;
            if (map.getLayer('obras-pulse-layer')) {
                map.setPaintProperty('obras-pulse-layer', 'circle-radius', 0);
                map.setPaintProperty('obras-pulse-layer', 'circle-opacity', 0);
            }
        }
        map.getCanvas().style.cursor = '';
    };

    // FASE 3: Limpiador automático del brillo del SVG
    const clearSVGHover = () => {
        const svg = document.querySelector('#synced-svg-container svg');
        if(svg) svg.querySelectorAll('.is-hovered').forEach(p => p.classList.remove('is-hovered'));
    };

    // ESCUDO DE RENDIMIENTO 1: Ocultar tarjetas y limpiar hover si el usuario hace zoom o arrastra
    map.on('zoomstart', () => { clearSVGHover(); clearTimeout(hoverIntentTimer); ghostTooltip.remove(); clearHoverState(); });
    map.on('dragstart', () => { clearSVGHover(); clearTimeout(hoverIntentTimer); ghostTooltip.remove(); clearHoverState(); });
    map.on('mouseout', () => { clearSVGHover(); clearTimeout(hoverIntentTimer); ghostTooltip.remove(); clearHoverState(); });

    // SOLUCIÓN DEFINITIVA DE RENDIMIENTO (Reemplazo de mouseenter/mouseleave)
    // Evita que MapLibre ejecute queryRenderedFeatures miles de veces de forma interna
    map.on('mousemove', (e) => {
        if (map.isZooming() || map.isMoving() || map.isRotating()) return;
        if (window.matchMedia('(max-width: 600px)').matches) return; // En móvil no hay hover
        
        lastMouseEvent = e;
        if (hoverRAF) return;
        
        hoverRAF = requestAnimationFrame(() => {
            hoverRAF = null;
            
            // =====================================================================
            // FASE 3: RAYCASTING - ILUMINACIÓN DE PROVINCIAS (HOVER)
            // =====================================================================
            const svgContainer = document.getElementById('synced-svg-container');
            if (svgContainer && svgContainer.style.opacity === '1') {
                const svg = svgContainer.querySelector('svg');
                if (svg) {
                    const pt = svg.createSVGPoint();
                    pt.x = lastMouseEvent.originalEvent.clientX;
                    pt.y = lastMouseEvent.originalEvent.clientY;
                    const matrix = svg.getScreenCTM();
                    if (matrix) {
                        const svgPt = pt.matrixTransform(matrix.inverse());
                        let foundPath = null;
                        svg.querySelectorAll('path, polygon, g').forEach(p => {
                            p.classList.remove('is-hovered');
                            if (p.isPointInFill && p.isPointInFill(svgPt)) foundPath = p;
                        });
                        if (foundPath) foundPath.classList.add('is-hovered');
                    }
                }
            }
            // =====================================================================

            // EVITA ERROR EN CONSOLA: Verificamos que la capa de obras exista antes de intentar leerla.
            // Esto es crucial porque en la vista "Inicio" (base) esta capa es eliminada del mapa.
            if (!map.getLayer('obras-layer')) {
                if (currentHoverKey !== null) {
                    clearTimeout(hoverIntentTimer);
                    clearHoverState();
                    ghostTooltip.remove();
                }
                return;
            }

            // Realizamos la consulta solo 1 vez por frame, y limitada solo a los pines
            const features = map.queryRenderedFeatures(lastMouseEvent.point, { layers: ['obras-layer'] });
            
            if (!features.length) {
                if (currentHoverKey !== null) {
                    clearTimeout(hoverIntentTimer);
                    clearHoverState();
                    ghostTooltip.remove();
                }
                return;
            }
            
            map.getCanvas().style.cursor = 'pointer';
            
            const feature = features[0];
            const key = feature.properties.id;
            const numId = feature.id; // El ID número entero exigido por WebGL
            
            if (currentHoverKey === key) return;
            
            clearTimeout(hoverIntentTimer);
            
            clearHoverState(); // Apagamos el pin anterior correctamente
            
            currentHoverKey = key;
            currentHoverId = numId;
            
            if (map.getSource('obras-source')) {
                map.setFeatureState({ source: 'obras-source', id: numId }, { hover: true });
            }

            if (!isPulsing) {
                isPulsing = true;
                pulsePhase = 0;
                renderPulse();
            }

            hoverIntentTimer = setTimeout(() => {
                const data = window.__OBRA_DATA.get(key);
                
                if (data && data.o) {
                    const o = data.o;
                    const nombre = o.nombre || '';
                    const estado = o.estado || '';
                    
                    let monto = '';
                    if (window.PO_Utils && typeof window.PO_Utils.formatMoney === 'function') {
                        monto = window.PO_Utils.formatMoney(o.monto);
                    } else {
                        const rawMonto = (o.monto || '').trim();
                        monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';
                    }
                    
                    const pill = typeof window.estadoToPill === 'function' ? window.estadoToPill(estado) : {cls:'', txt:estado};
                    
                    // FIX: Enlace directo al nombre de pestaña en Excel para extraer la foto de la carpeta correcta
                    const realTab = window.SHEETS ? window.SHEETS[currentKey] : currentKey;
                    const dirFotos = String(realTab || currentKey).toLowerCase();
                    const imgUrl = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}/1.thumb.webp?v=${sessionTs}` : 'https://via.placeholder.com/300x150/801039/ffc300?text=Fuerza+Tacna';
                    let fragHTML = `<div class="ghost-card__img" style="width: 100%; height: 140px; overflow: hidden; border-radius: 8px 8px 0 0;"><img src="${imgUrl}" alt="${nombre}" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.onerror=null; this.src='https://via.placeholder.com/300x150/801039/ffc300?text=Sin+Foto';"></div>`;
                    
                    const ghostHTML = `<div class="ghost-card" style="margin: 0;">${fragHTML}<div class="ghost-card__body"><div class="ghost-card__kicker">Obra <span class="pill ${pill.cls}"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4Z"/></svg> ${pill.txt}</span></div><div class="ghost-card__title">${nombre}</div><div class="ghost-card__meta">${monto}</div><div class="ghost-card__divider"></div><div class="meta-row">${(o.distrito||'-')} · ${(o.provincia||'-')}</div></div></div>`;
                    
                    ghostTooltip.setLngLat([data.lng, data.lat]).setHTML(ghostHTML).addTo(map);
                }
            }, 50);
        });
    });

    map.on('click', 'obras-layer', (e) => {
        if (!e.features.length) return;
        ghostTooltip.remove(); // Cerramos tarjeta fantasma
        
        const feature = e.features[0];
        const key = feature.properties.id;
        const data = window.__OBRA_DATA.get(key);
        
        if (data && data.o) {
            const o = data.o;
            const realTab = window.SHEETS ? window.SHEETS[currentKey] : currentKey;
            const dirFotos = String(realTab || currentKey).toLowerCase();
            const base  = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}` : null;
            const dinBuster = "?v=" + new Date().getTime();
            const rawMonto = (o.monto || '').trim();
            const monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';

            // =====================================================================
            // AUTO-CENTRAR EL MAPA AL HACER CLIC EN EL PIN
            // =====================================================================
            map.flyTo({ 
                center: [data.lng, data.lat], 
                zoom: Math.max(map.getZoom(), 8), // Acerca la cámara para poner el pin en primer plano
                speed: 1.2, 
                curve: 1.42 
            });

            if (window.PanelObra && typeof window.PanelObra.open === 'function') {
                window.PanelObra.open({ key: o.carpeta || `${o.nombre}|${o.x}|${o.y}`, nombre: o.nombre, estado: o.estado, monto: monto, distrito: o.distrito, provincia: o.provincia, descripcion: o.descripcion || '', portada: base ? `${base}/1.thumb.webp${dinBuster}` : null, fotos: base ? Array.from({length:6}, (_,i)=> `${base}/${i+1}.webp${dinBuster}`) : [], onCenter: () => { map.flyTo({ center: [data.lng, data.lat], zoom: Math.max(map.getZoom(), 8), speed: 1.2, curve: 1.42 }); } });
                if (typeof window.recordVisit === 'function') window.recordVisit(key, currentKey);
            }
        }
    });

    window.__OBRA_MARKERS = new Map();
    window.__OBRA_DATA    = new Map();
    window.SHEET_CACHE = window.SHEET_CACHE || Object.create(null);
    let PINS_LOADING = new Set();

    async function cargarPinesDesdeSheet(segmento, mapLat, mapLon){
        console.time("⏱️ DIAGNOSTICO: Fetch_Excel_" + segmento);
        if (PINS_LOADING.has(segmento)) return;
        PINS_LOADING.add(segmento);

        // ==============================================================================
        // FIX DEFINITIVO Y PROFUNDO: LECTURA DINÁMICA DE LA PESTAÑA "SEGMENTOS"
        // Como indicaste: "es en la pestaña de SEGMENTOS donde está el ID".
        // Le enseñamos a la web a leer este índice primero para que sepa traducir
        // el botón presionado (ej: "educacion") a su pestaña real en Excel (ej: "ID_001")
        // ==============================================================================
        if (!window.SHEETS_MAPPED && window.SHEET_ID) {
            try {
                console.log("[Mapa] 🔍 Leyendo la pestaña maestra 'SEGMENTOS' (Directo por columnas)...");
                const tqBuster = encodeURIComponent(`select * offset 0`);
                let mapUrl = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=SEGMENTOS&range=A:E`;
                let resp = await fetch(mapUrl);
                let txt = await resp.text();
                let match = txt.match(/setResponse\(([\s\S]+)\);?/);
                
                if (!match) {
                    mapUrl = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=Segmentos&range=A:E`;
                    resp = await fetch(mapUrl);
                    txt = await resp.text();
                    match = txt.match(/setResponse\(([\s\S]+)\);?/);
                }

                if (match) {
                    const data = JSON.parse(match[1]);
                    const rows = data.table.rows || [];
                    
                    window.SHEETS = { base: '' };
                    window.SHEETS_FALLBACK = window.SHEETS_FALLBACK || {};
                    
                    rows.forEach((row, index) => {
                        if (!row || !row.c) return;
                        if (index === 0 && row.c[0] && String(row.c[0].v).toLowerCase().includes('id')) return;
                        
                        const v0 = row.c[0] && row.c[0].v ? String(row.c[0].v).trim() : ''; // ID (Ej: seg_001)
                        const v1 = row.c[1] && row.c[1].v ? String(row.c[1].v).trim() : ''; // Nombre Visible
                        const v2 = row.c[2] && row.c[2].v ? String(row.c[2].v).trim() : ''; // Nombre Pestaña
                        
                        let idHtml = v0 ? v0 : (v1 ? String(v1).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim().replace(/\s+/g, "_") : '');

                        // FIX ESTÉTICO (VHS): Restaurar ID legacy para Alcalde Provincial
                        const nomLow = v1 ? String(v1).toLowerCase() : '';
                        if (nomLow.includes('alcalde') || nomLow.includes('provincial')) {
                            idHtml = 'alcalde_provincial';
                        }

                        if (idHtml && v2) {
                            window.SHEETS[idHtml] = v2;
                        }
                        if (v1 && v2 && v1 !== idHtml) {
                            window.SHEETS[String(v1).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim()] = v2;
                        }
                        if (v1 && v0) window.SHEETS_FALLBACK[v1] = v0;
                        if (idHtml === 'alcalde_provincial' && v0) window.SHEETS_FALLBACK[idHtml] = v0;
                    });
                    window.SHEETS_MAPPED = true;
                    console.log("[Mapa] ✅ Índice de SEGMENTOS reconstruido limpiamente. Traductor:", window.SHEETS, "Fallbacks:", window.SHEETS_FALLBACK);
                }
            } catch (e) {
                console.warn("[Mapa] ⚠️ Error al intentar leer pestaña maestra 'SEGMENTOS'.", e);
            }
        }

        // ==============================================================================
        // FIX ABSOLUTO: RESOLUCIÓN DE NOMBRES DE PESTAÑA (TABS)
        // Atiende la regla: "Para pestaña el código es el verdadero nombre del segmento"
        // ==============================================================================
        let TAB = window.SHEETS ? window.SHEETS[segmento] : undefined;
        let FALLBACK_TAB = window.SHEETS_FALLBACK ? window.SHEETS_FALLBACK[segmento] : undefined;

        if (!TAB && window.SHEETS) {
            const norm = s => String(s).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
            const reqNorm = norm(segmento);
            
            // 1. Buscar si el botón coincide con alguna llave de la configuración
            const foundKey = Object.keys(window.SHEETS).find(k => norm(k) === reqNorm);
            if (foundKey) {
                TAB = window.SHEETS[foundKey];
                FALLBACK_TAB = window.SHEETS_FALLBACK[foundKey];
            }
            
            // 2. Si el botón en el HTML ya está enviando el código ("ID_001"), usarlo.
            if (!TAB) {
                const isValue = Object.values(window.SHEETS).find(v => norm(v) === reqNorm);
                if (isValue) TAB = isValue;
            }
        }
        
        // 3. Fallback Supremo: Usar la solicitud cruda como el nombre exacto de la pestaña.
        if (!TAB) TAB = segmento;

        console.log(`[Mapa] Solicitando pines... Botón: "${segmento}" -> Intento 1: "${TAB}" | Intento 2: "${FALLBACK_TAB}"`);

        try{
            window.SHEET_FETCH_PROMISES = window.SHEET_FETCH_PROMISES || {};
            if (!window.SHEET_CACHE[segmento] && !window.SHEET_FETCH_PROMISES[segmento]){
                window.SHEET_FETCH_PROMISES[segmento] = (async () => {
                    const doFetch = async (tabName) => {
                        const tqBuster = encodeURIComponent(`select * offset 0`);
                        const url = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=${encodeURIComponent(tabName)}&range=A:Z&headers=1`;
                        const res = await fetch(url);
                        const txt = await res.text();
                        const match = txt.match(/setResponse\(([\s\S]+)\);?/);
                        if (!match) throw new Error("Error GViz HTML");
                        const data = JSON.parse(match[1]);
                        if (data.status === 'error') throw new Error(data.errors[0]?.message || 'Error en Google Sheets');
                        return window.gvizToObjects(data);
                    };
                    
                    try {
                        window.SHEET_CACHE[segmento] = await doFetch(TAB);
                    } catch (e) {
                        console.warn(`[Mapa] ⚠️ La pestaña '${TAB}' falló. Motivo:`, e.message);
                        if (FALLBACK_TAB && FALLBACK_TAB !== TAB) {
                            console.log(`[Mapa] 🔄 Intentando con código (ID) de pestaña: '${FALLBACK_TAB}'...`);
                            window.SHEET_CACHE[segmento] = await doFetch(FALLBACK_TAB);
                        } else {
                            throw e;
                        }
                    }
                })();
            }
            if (window.SHEET_FETCH_PROMISES[segmento]) {
                await window.SHEET_FETCH_PROMISES[segmento];
            }
        }catch(err){
            console.error(`[Mapa] ❌ ERROR CRÍTICO: No se encontraron datos para '${segmento}'.`, err);
            PINS_LOADING.delete(segmento);
            return;
        }
        console.timeEnd("⏱️ DIAGNOSTICO: Fetch_Excel_" + segmento);
        console.time("⏱️ DIAGNOSTICO: Dibujo_Pines_" + segmento);

        if (currentKey !== segmento) {
            PINS_LOADING.delete(segmento);
            return;
        }

        const obras = window.SHEET_CACHE[segmento] || [];
        const toNum = v => { 
            if (v == null || v === '') return NaN; 
            const str = String(v).trim().replace(',', '.');
            let n = parseFloat(str.replace('%','')); 
            if (!Number.isFinite(n)) return NaN;
            if (str.includes('%') || Math.abs(n) > 5) n = n / 100;
            return n; 
        };
        
        window.__OBRA_DATA.clear();
        const validas = [];
        
        console.log(`[Mapa] Procesando ${obras.length} filas del Excel para el segmento: ${segmento}`);

        for (const o of obras){
            // FIX: Soporte extendido a prueba de fallos para nombres de columnas (Dashboard)
            const nombre = String(o.nombre ?? o.Nombre ?? o.NOMBRE ?? o.obra ?? o.Obra ?? o.titulo ?? o.Titulo ?? '').trim();
            const rawX = o.x ?? o.X ?? o.lng ?? o.Lng ?? o.longitud ?? o.Longitud ?? o.coord_x ?? o.COORD_X ?? o['COORD X'] ?? o['coord x'];
            const rawY = o.y ?? o.Y ?? o.lat ?? o.Lat ?? o.latitud ?? o.Latitud ?? o.coord_y ?? o.COORD_Y ?? o['COORD Y'] ?? o['coord y'];
            const x = toNum(rawX), y = toNum(rawY);
            
            if (!nombre) {
                console.warn("[Mapa] Fila omitida (Falta columna de nombre):", o);
                continue;
            }
            if (isNaN(x) || isNaN(y)) {
                console.warn(`[Mapa] Fila omitida (Coordenadas X/Y inválidas o vacías): ${nombre} -> X: ${rawX}, Y: ${rawY}`, o);
                continue;
            }
            
            const estado = String(o.estado ?? o.Estado ?? o.ESTADO ?? '').trim();
            if (estado.toLowerCase().includes('oculto')) continue;

            const rawCarp = String(o.carpeta ?? o.Carpeta ?? o.CARPETA ?? '').trim();
            
            // Normalizar otros campos vitales que pudieron cambiar de nombre
            const distrito = String(o.distrito ?? o.Distrito ?? o.DISTRITO ?? '').trim();
            const provincia = String(o.provincia ?? o.Provincia ?? o.PROVINCIA ?? '').trim();
            const descripcion = String(o.descripcion ?? o.Descripcion ?? o.DESCRIPCION ?? '').trim();
            const monto = String(o.monto ?? o.Monto ?? o.MONTO ?? '').trim();

            validas.push({ 
                ...o, 
                nombre, 
                estado, 
                x, 
                y, 
                carpeta: (rawCarp && rawCarp.toLowerCase() !== 'null' && rawCarp !== '-') ? rawCarp : null,
                distrito,
                provincia,
                descripcion,
                monto
            });
        }
        console.log(`[Mapa] Pines listos para renderizar: ${validas.length}`);

        // FASE 2: Empaquetado puro de datos, sin distorsionar coordenadas (Modo Google Maps)
        let featureNumId = 1; // Generador de IDs puros para WebGL
        const geojsonFeatures = validas.map((o) => {
            const finalLng = o.x * mapLon;
            // FIX 1: Eje Y Cartesiano. Dejamos que MapLibre procese el eje Y tal como viene en el Excel.
            const finalLat = o.y * mapLat; 

            const color = (typeof window.colorPinPorEstado === 'function' ? window.colorPinPorEstado(o.estado) : null) || '#801039';
            const k = typeof window._obraKey === 'function' ? window._obraKey(o) : `${o.x}_${o.y}`;
            const numId = featureNumId++;
            
            window.__OBRA_DATA.set(k, { o, lat: finalLat, lng: finalLng });

            return {
                type: 'Feature',
                id: numId, // FIX: ID numérico obligatorio para Feature-State en MapLibre
                geometry: { type: 'Point', coordinates: [finalLng, finalLat] },
                properties: { id: k, nombre: o.nombre, estado: o.estado, color }
            };
        });

        const sourceId = 'obras-source';
        
        // Siempre removemos las capas visuales antes de actualizar,
        // garantizando que los pines siempre se dibujen ENCIMA del plano base.
        if (map.getLayer('clusters')) map.removeLayer('clusters');
        if (map.getLayer('cluster-count')) map.removeLayer('cluster-count');
        if (map.getLayer('obras-labels-layer')) map.removeLayer('obras-labels-layer');
        if (map.getLayer('obras-layer')) map.removeLayer('obras-layer');
        if (map.getLayer('obras-pulse-layer')) map.removeLayer('obras-pulse-layer');
        if (map.getLayer('obras-shadow-layer')) map.removeLayer('obras-shadow-layer');

        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setData({ type: 'FeatureCollection', features: geojsonFeatures });
        } else {
            map.addSource(sourceId, {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: geojsonFeatures }
            });
        }

        // DIBUJADO GPU 1: Sombras Gaussianas Nativas
        map.addLayer({
            id: 'obras-shadow-layer',
            type: 'circle',
            source: sourceId,
            paint: {
                'circle-radius': 8,
                'circle-color': '#000000',
                'circle-opacity': 0.4,
                'circle-translate': [0, 4],
                'circle-blur': 0.8
            }
        });

        // DIBUJADO GPU 1.5: Capa Fantasma del Latido (Radar)
        map.addLayer({
            id: 'obras-pulse-layer',
            type: 'circle',
            source: sourceId,
            paint: {
                'circle-radius': 0, // Inicia en 0, lo anima Javascript
                'circle-radius-transition': { duration: 0 }, // ¡CLAVE! Desactiva el lag del motor interno para permitir los 60fps
                'circle-color': ['get', 'color'], // Hereda dinámicamente el mismo color del pin
                'circle-opacity': 0,
                'circle-opacity-transition': { duration: 0 },
                'circle-stroke-width': 0
            }
        });

        // DIBUJADO GPU 2: Pines de Color Vectoriales
        map.addLayer({
            id: 'obras-layer',
            type: 'circle',
            source: sourceId,
            paint: {
                'circle-radius': [
                    'case',
                    ['==', ['feature-state', 'hover'], true], // FIX 2: Sintaxis WebGL 100% estricta
                    12, // Crece magnéticamente a 12px
                    8   // Tamaño normal de 8px
                ],
                'circle-radius-transition': { duration: 350 }, // Crecimiento magnético mucho más lento
                'circle-color': ['get', 'color'],
                'circle-stroke-width': [
                    'case',
                    ['==', ['feature-state', 'hover'], true],
                    6, // El borde se ensancha para crear el halo
                    2  // Borde blanco estándar
                ],
                'circle-stroke-width-transition': { duration: 350 },
                'circle-stroke-color': [
                    'case',
                    ['==', ['feature-state', 'hover'], true],
                    ['get', 'color'], // El halo adquiere dinámicamente el color del pin
                    '#ffffff'         // Color normal del borde
                ],
                'circle-stroke-opacity': [
                    'case',
                    ['==', ['feature-state', 'hover'], true],
                    0.4, // Halo translúcido e iluminado
                    1.0  // Borde blanco sólido 
                ],
                'circle-stroke-opacity-transition': { duration: 350 }
            }
        });

        // DIBUJADO GPU 3: Etiquetas Inteligentes Vectoriales (Anti-colisiones y Halo)
        map.addLayer({
            id: 'obras-labels-layer',
            type: 'symbol',
            source: sourceId,
            layout: {
                'text-field': ['get', 'nombre'],
                'text-size': [
                    'interpolate', ['linear'], ['zoom'],
                    4, 6.5,   // En zoom 4 (lejos), letra pequeñita (6.5px)
                    5, 8.5,   // En zoom 5 (distancia media), letra visible (8.5px)
                6, 8,     // En zoom 6 (acercamiento), tamaño base (8px)
                8, 11     // En zoom 8 (máximo acercamiento), letra grande (11px)
                ],
                'text-transform': 'uppercase',
                'text-letter-spacing': 0.05,
                'text-variable-anchor': ['bottom'],
                'text-radial-offset': -0.1,
                'text-justify': 'center',
                'text-max-width': 8,
                'text-padding': 2 
            },
            paint: {
                'text-color': '#000000',
                'text-halo-color': '#FFFFF0',
                'text-halo-width': 3.5,
                'text-halo-blur': 1
            }
        });

        PINS_LOADING.delete(segmento);
        console.timeEnd("⏱️ DIAGNOSTICO: Dibujo_Pines_" + segmento);
    }

    window.swapSegment = function(key, revealAfter = false){
        const videoIntro = document.getElementById('video-intro-container');
        const chips = target.querySelector('.chips');

        // Limpiar buscador al cambiar de segmento o volver a inicio
        const sInput = target.querySelector('#obraSearchInput');
        if (sInput) sInput.value = '';
        const sList = target.querySelector('#obraSearchList');
        if (sList) sList.hidden = true;

        // --- NUEVO: Actualizar la URL dinámicamente ---
        if (key !== 'base') {
            const newUrl = window.location.pathname.split('?')[0] + '?s=' + key;
            window.history.pushState({ segment: key }, '', newUrl);
        } else {
            window.history.pushState({ segment: 'base' }, '', window.location.pathname.split('?')[0]);
        }

        // Caso especial: Regresar al Inicio (Video ambiental como "Home")
        if (key === 'base') {
            currentKey = 'base';
            isSwapping = false; // Detenemos cualquier swap en curso
            pendingKey = null;
            document.body.className = document.body.className.replace(/\bsegmento-[^\s]+\b/g, '').trim();
            if (mapEl) {
                mapEl.setAttribute('data-segment', 'base');
            }
            if (typeof window.deactivateRedVial === 'function') {
                window.deactivateRedVial();
            }
            isAutoCenterBlocked = false; // Resetear bandera al volver a inicio

            // Cerrar ficha de obra si está abierta para limpiar la pantalla
            try {
                if (window.PanelObra && typeof window.PanelObra.close === 'function') {
                    window.PanelObra.close();
                }
            } catch(e) {}

            if (videoIntro) {
                videoIntro.style.visibility = 'visible';
                videoIntro.style.opacity = '1';
                const v = videoIntro.querySelector('video');
            if (v) { 
                v.muted = true; 
                v.loop = true; 
                v.setAttribute('playsinline', '');
                v.setAttribute('webkit-playsinline', '');
                v.play().catch(() => {}); 
            }
            }
            if (mapEl) {
                mapEl.style.opacity = '1';
                mapEl.style.visibility = 'visible';
                mapEl.style.pointerEvents = 'auto';
            }
            
            try {
                const svgContainer = document.getElementById('synced-svg-container');
                if (svgContainer) svgContainer.style.opacity = '0';

                if (map && map.style && map.getSource('plano-base')) {
                    if (map.getLayer('plano-layer')) map.removeLayer('plano-layer');
                    map.removeSource('plano-base');
                }
                updateHud('Inicio');
            } catch(e) {
                console.warn("Map cleanup deferred", e);
            }
            
            updateLegendVisibility('base');

            // Simular "recarga" ocultando y volviendo a mostrar la UI con su delay
            const dock = getEl('filtersDock');
            if (dock) { dock.style.opacity = '0'; dock.style.visibility = 'hidden'; }

            revealUI(); // SIN ESPERAS: Los chips y el footer aparecen instantáneamente
            return;
        }

        if (isSwapping){ pendingKey = key; return; }
        
        // BUGFIX: Capturamos el estado anterior antes de actualizar currentKey
        const prevKey = currentKey;
        isSwapping = true; 
        currentKey = key; // Definimos el intento de navegación inmediatamente
        
        // --- FIX: Clases dinámicas de temática especial (VHS / Alcalde) ---
        document.body.className = document.body.className.replace(/\bsegmento-[^\s]+\b/g, '').trim();
        if (key && key !== 'base') {
            document.body.classList.add('segmento-' + key);
        }
        if (mapEl) {
            mapEl.setAttribute('data-segment', key);
        }
        
        setBackgroundFor(key);

        // --- FASE RED VIAL: Activador bajo demanda (Sandbox) ---
        if (key === 'alcalde_provincial') {
            if (typeof window.activateRedVial === 'function') window.activateRedVial();
        } else {
            if (typeof window.deactivateRedVial === 'function') window.deactivateRedVial();
        }

        if (key === 'alcalde_provincial') {
            const svgContainer = document.getElementById('synced-svg-container');
            const dock = getEl('filtersDock');

            if (videoIntro) {
                videoIntro.style.opacity = '0';
                videoIntro.style.visibility = 'hidden';
            }

            if (svgContainer) {
                svgContainer.style.setProperty('opacity', '0', 'important');
                svgContainer.className = `active-segment-${key}`;
            }

            if (mapEl) {
                mapEl.style.opacity = '0';
                mapEl.style.visibility = 'hidden';
                mapEl.style.pointerEvents = 'none';
            }

            if (dock) {
                dock.style.opacity = '0';
                dock.style.visibility = 'hidden';
            }

            target.querySelectorAll('.chips, .fp').forEach(el => {
                el.style.visibility = 'visible';
                el.style.opacity = '1';
            });

            updateHud(key);
            updateLegendVisibility(key);

            isSwapping = false;
            if (pendingKey){ const k = pendingKey; pendingKey = null; swapSegment(k); }
            return;
        }


        // --- NUEVO: Si entramos a un segmento de mapa, ocultamos el video ---
        const isFromBase = (prevKey === 'base' || !prevKey);

        if (videoIntro) {
            videoIntro.style.opacity = '0';
            setTimeout(() => {
                // Solo lo ocultamos físicamente si no hemos regresado a 'base' durante la transición
                if (currentKey !== 'base') {
                    videoIntro.style.visibility = 'hidden';
                }
            }, 1500);
        }
        if (mapEl) {
            mapEl.style.opacity = '1';
            mapEl.style.visibility = 'visible';
            mapEl.style.pointerEvents = 'auto';

            // --- NUEVO: Revelar el dock de filtros solo cuando se entra a un segmento ---
            const dock = getEl('filtersDock');
            if (dock) { 
                dock.style.opacity = '1'; 
                dock.style.visibility = 'visible'; 
            }
        }
        
        // --- CIRUGÍA DE FASE 2: Eliminamos la dependencia de imágenes PNG ---
        if (currentKey !== key || currentKey === 'base') {
            isSwapping = false;
            return;
        }

        // Medidas exactas de tu archivo SVG original
        const lon = 1093.78 * 0.005; 
        const lat = 1035.32 * 0.005;
        const bounds = [ [0, 0], [lon, lat] ];
        
        map.resize();

        // Le avisamos al SVG qué botón se presionó y lo hacemos visible
        const svgContainer = document.getElementById('synced-svg-container');
        if (svgContainer) {
            if (key !== 'alcalde_provincial') {
                svgContainer.style.setProperty('opacity', '1', 'important');
            }
            svgContainer.className = `active-segment-${key}`;
        }

        // Limpieza de emergencia por si quedó alguna capa vieja de imagen
        if (map.getLayer('plano-layer')) map.removeLayer('plano-layer');
        if (map.getSource('plano-base')) map.removeSource('plano-base');

            let [gFx, gFy] = FOCUS[key] || [0.5, 0.5];
            
            
            const cx = lon * gFx;
            const cy = lat * gFy; 

            if (isFromBase || !isAutoCenterBlocked) {
                map.fitBounds(bounds, { padding: 50, duration: 0 });
                map.setMaxBounds([ [-lon*0.5, -lat*0.5], [lon*1.5, lat*1.5] ]);
                map.setCenter([cx, cy]);
                
            }

            target.querySelectorAll('.chips, .fp').forEach(el => {
                el.style.visibility = 'visible';
                el.style.opacity = '1';
            });
            
            updateHud(key); 
            updateLegendVisibility(key);
            
            // --- NUEVO: Mostrar hint de Onboarding con scroll (Solo una vez por sesión) ---
            if (key !== 'base' && key !== 'alcalde_provincial' && !window._mapHintShown) {
                window._mapHintShown = true;
                setTimeout(() => {
                    let hint = document.getElementById('map-onboarding-hint');
                    if (!hint) {
                        hint = document.createElement('div');
                        hint.id = 'map-onboarding-hint';
                        hint.className = 'map-onboarding-hint';
                        
                        // Textos adaptados para móvil o PC
                        const isMobileHint = window.innerWidth <= 768;
                        const hintText = isMobileHint ? '¡Desliza para<br>explorar el mapa!' : '¡Navega con el scroll<br>para explorar las obras!';
                        const hintIcon = isMobileHint ? '👆' : '🖱️';

                        hint.innerHTML = `<span class="icon-mouse">${hintIcon}</span> ${hintText}`;
                        document.body.appendChild(hint);
                    }
                    
                    requestAnimationFrame(() => {
                        hint.classList.add('is-visible');
                        setTimeout(() => {
                            hint.classList.remove('is-visible');
                            hint.classList.add('is-hiding');
                            setTimeout(() => { if(hint) hint.remove(); }, 1000);
                        }, 6000);
                    });
                }, 1800);
            }

            isSwapping = false; 
            if (pendingKey){ const k = pendingKey; pendingKey = null; swapSegment(k); }

            (async ()=>{
                try{ 
                    if (currentKey !== key) return; // Cancelar si el usuario navegó a otro lado
                    await cargarPinesDesdeSheet(key, lat, lon);
                if (window.SHEETS && window.SHEETS[key]) { 
                    // Llamamos a las funciones que ahora viven en mapa-filtros.js
                    if (typeof buildFilterOptions === 'function') buildFilterOptions();
                    if (typeof attachFilterEvents === 'function') attachFilterEvents();
                }
                }catch(err){ console.error(err); }
            })();
    };

    // BUSCADOR, DOCKS Y FILTROS
    // El buscador ahora vive en mapa-buscador.js
    window.gotoObra = async function(key, seg){
        // Traductor Inverso Inteligente (IA, URLs antiguas y temática VHS)
        if (seg) {
            const normSegReq = String(seg).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
            
            if (normSegReq.includes('alcalde') || normSegReq.includes('provincial')) {
                seg = 'alcalde_provincial';
            } else if (window.SHEETS_FALLBACK) {
                for (const [keyFallback, valFallback] of Object.entries(window.SHEETS_FALLBACK)) {
                    if (!valFallback) continue; // Si no hay ID, ignoramos
                    const normFallbackKey = String(keyFallback).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
                    if (normFallbackKey === normSegReq || String(valFallback).toLowerCase() === normSegReq) {
                        seg = valFallback;
                        break;
                    }
                }
            }
        }

        if (seg && currentKey !== seg){
            isAutoCenterBlocked = true; // Bloqueo preventivo
            const chip = target.querySelector(`.chip[data-map="${seg}"]`);
            chip ? chip.click() : swapSegment(seg);
            await new Promise(res=>{ const t = setInterval(()=>{ if (!isSwapping && !PINS_LOADING.has(seg) && currentKey === seg){ clearInterval(t); res(); } }, 50); });
        }
        let d = window.__OBRA_DATA.get(key);
        if (!d){
            for (const s of Object.keys(window.SHEETS)){
                if ((window.SHEET_CACHE[s] || []).some(o => (typeof _obraKey === 'function' ? _obraKey(o) : `${o.x}_${o.y}`) === key)){ seg = s; break; }
            }
            if (seg && currentKey !== seg){
                isAutoCenterBlocked = true;
                const chip = target.querySelector(`.chip[data-map="${seg}"]`);
                chip ? chip.click() : swapSegment(seg);
                await new Promise(res=>{ const t = setInterval(()=>{ if (!isSwapping && !PINS_LOADING.has(seg) && currentKey === seg){ clearInterval(t); res(); } }, 50); });
                d = window.__OBRA_DATA.get(key);
            }
        }
        
        // FIX PARA INTELIGENCIA ARTIFICIAL: Si no encuentra por llave estricta, busca por nombre aproximado
        if (!d && window.__OBRA_DATA.size > 0) {
            const normKeySearch = String(key).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
            for (const [k, v] of window.__OBRA_DATA.entries()) {
                const normNombre = String(v.o.nombre || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
                if (normNombre === normKeySearch || normNombre.includes(normKeySearch)) {
                    d = v;
                    key = k; // Sobrescribimos la llave con la real para que la cámara y el panel no fallen
                    break;
                }
            }
        }

        if (d) {
            // Transición de cámara acelerada por hardware (Vuelo 3D)
            map.flyTo({
                center: [d.lng, d.lat],
                zoom: Math.max(map.getZoom(), 8),
                speed: 1.2,
                curve: 1.42
            });
            map.once('moveend', () => { isAutoCenterBlocked = false; });
            setTimeout(() => { isAutoCenterBlocked = false; }, 2000);

            // Abrir el Panel de Obra
            const o = d.o;
            const realTab = window.SHEETS ? window.SHEETS[currentKey] : currentKey;
            const dirFotos = String(realTab || currentKey).toLowerCase();
            const base  = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}` : null;
            const dinBuster = "?v=" + new Date().getTime();
            const rawMonto = (o.monto || '').trim();
            const monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';

            if (window.PanelObra && typeof window.PanelObra.open === 'function') {
                window.PanelObra.open({ 
                    key: o.carpeta || `${o.nombre}|${o.x}|${o.y}`, nombre: o.nombre, estado: o.estado, monto: monto, 
                    distrito: o.distrito, provincia: o.provincia, descripcion: o.descripcion || '', 
                    portada: base ? `${base}/1.thumb.webp${dinBuster}` : null, fotos: base ? Array.from({length:6}, (_,i)=> `${base}/${i+1}.webp${dinBuster}`) : [], 
                    onCenter: () => { map.flyTo({ center: [d.lng, d.lat], zoom: Math.max(map.getZoom(), 8), speed: 1.2, curve: 1.42 }); } 
                });
                if (typeof window.recordVisit === 'function') window.recordVisit(key, currentKey);
            }
        } else {
            isAutoCenterBlocked = false;
        }
    };

    // El buscador, filtros y docks ahora se manejan en sus respectivos archivos .js

    // ==============================================================================
    // GENERACIÓN DINÁMICA DEL MENÚ DE SEGMENTOS
    // ==============================================================================
    function attachChipListeners() {
        target.querySelectorAll('.chip[data-map]').forEach(chip => {
            // Clonar para evitar listeners duplicados al reconstruir
            const newChip = chip.cloneNode(true);
            chip.parentNode.replaceChild(newChip, chip);
            
            newChip.addEventListener('click', () => {
                const key = newChip.getAttribute('data-map');
                
                // Comportamiento Inteligente del botón Inicio
                if (key === 'base' && (currentKey === 'base' || currentKey === null)) {
                    let rootUrl = window.location.href.split('?')[0].split('#')[0];
                    if (rootUrl.includes('/assets/')) rootUrl = rootUrl.substring(0, rootUrl.indexOf('/assets/'));
                    else if (rootUrl.match(/\/obras\/?$/)) rootUrl = rootUrl.replace(/\/obras\/?$/, '');
                    window.location.href = rootUrl + '/index.html';
                    return;
                }

                if (key === currentKey) return;
                target.querySelectorAll('.chip[data-map]').forEach(c => c.classList.remove('is-active'));
                newChip.classList.add('is-active');
                swapSegment(key);
            });
        });
    }
    attachChipListeners();

    window.loadDynamicMenu = async function() {
        if (!window.SHEET_ID) return;
        try {
            console.log("[Mapa] 🔍 Construyendo menú dinámico desde 'SEGMENTOS'...");
            const tqBuster = encodeURIComponent(`select * offset 0`);
            let mapUrl = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=SEGMENTOS&range=A:E`;
            let resp = await fetch(mapUrl);
            let txt = await resp.text();
            let match = txt.match(/setResponse\(([\s\S]+)\);?/);
            
            if (!match) {
                mapUrl = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=Segmentos&range=A:E`;
                resp = await fetch(mapUrl);
                txt = await resp.text();
                match = txt.match(/setResponse\(([\s\S]+)\);?/);
            }

            if (match) {
                const data = JSON.parse(match[1]);
                const rows = data.table.rows || [];
                let menuItems = [];
                
                window.SHEETS = { base: '' };
                window.SHEETS_FALLBACK = window.SHEETS_FALLBACK || {};

                rows.forEach((row, index) => {
                    if (!row || !row.c) return;
                    // Ignorar la cabecera
                    if (index === 0 && row.c[0] && String(row.c[0].v).toLowerCase().includes('id')) return;
                    
                    // Extracción robusta por columna (A=0, B=1, D=3, E=4)
                    const v0 = row.c[0] && row.c[0].v ? String(row.c[0].v).trim() : ''; // El ID exacto (Ej: alcalde_provincial)
                    const v1 = row.c[1] && row.c[1].v ? String(row.c[1].v).trim() : ''; 
                    const v2 = row.c[2] && row.c[2].v ? String(row.c[2].v).trim() : ''; // Nombre Pestaña en Excel
                    const activoRaw = row.c[3] && row.c[3].v ? String(row.c[3].v).toUpperCase().trim() : 'NO';
                    const ordenRaw = row.c[4] && row.c[4].v ? parseInt(row.c[4].v) || 0 : 0;
                    
                    let idHtml = v0 ? v0 : (v1 ? String(v1).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim().replace(/\s+/g, "_") : '');

                    // FIX ESTÉTICO (VHS): Restaurar ID legacy para que coincida con tu CSS antiguo
                    const nomLow = v1 ? String(v1).toLowerCase() : '';
                    if (nomLow.includes('alcalde') || nomLow.includes('provincial')) {
                        idHtml = 'alcalde_provincial';
                    }

                    // RECONSTRUIR EL DICCIONARIO TRADUCTOR SIMULTÁNEAMENTE
                    if (idHtml && v2) {
                        window.SHEETS[idHtml] = v2;
                    }
                    if (v1 && v2 && v1 !== idHtml) {
                        window.SHEETS[String(v1).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim()] = v2;
                    }
                    if (v1 && v0) window.SHEETS_FALLBACK[v1] = v0;
                    if (idHtml === 'alcalde_provincial' && v0) window.SHEETS_FALLBACK[idHtml] = v0;

                    if (activoRaw === 'SI' || activoRaw === '1' || activoRaw === 'TRUE' || activoRaw === 'SÍ') {
                        if (v1 && idHtml) {
                            menuItems.push({ idHtml, nombreVis: v1, orden: ordenRaw });
                        }
                    }
                });
                
                window.SHEETS_MAPPED = true;
                
                const chipsGroup = target.querySelector('.chips-group');
                const navChips = target.querySelector('.chips');
                
                if (chipsGroup) {
                    // Buscar Inicio y Buscador en toda la barra para no perderlos jamás
                    let inicioBtn = (navChips && navChips.querySelector('.chip[data-map="base"]')) || chipsGroup.querySelector('.chip[data-map="base"]');
                    const label = chipsGroup.querySelector('.chip--label');
                    const searchPill = (navChips && navChips.querySelector('.search-pill')) || chipsGroup.querySelector('.search-pill');
                    
                    // Failsafe por si el script anterior borró por completo el botón Inicio
                    if (!inicioBtn) {
                        inicioBtn = document.createElement('button');
                        inicioBtn.className = 'chip';
                        inicioBtn.setAttribute('data-map', 'base');
                        inicioBtn.innerHTML = `<span>Inicio</span>`;
                    }
                    
                    chipsGroup.innerHTML = '';
                    
                    // Orden Exacto: Inicio -> Divisor -> Buscador -> Divisor -> Label -> Segmentos
                    if (inicioBtn) {
                        chipsGroup.appendChild(inicioBtn);
                        let div1 = document.createElement('div'); div1.className = 'divider-v'; 
                        chipsGroup.appendChild(div1);
                    }
                    
                    if (searchPill) {
                        chipsGroup.appendChild(searchPill);
                        let div2 = document.createElement('div'); div2.className = 'divider-v'; 
                        chipsGroup.appendChild(div2);
                    }

                    if (label) chipsGroup.appendChild(label);
                    
                    menuItems.sort((a, b) => a.orden - b.orden);
                    
                    menuItems.forEach(item => {
                        const btn = document.createElement('button');
                        btn.className = 'chip';
                        
                        // Rescate de temática especial (Ej: Alcalde Provincial - Animación VHS)
                        if (item.idHtml.includes('alcalde') || item.idHtml.includes('provincial')) {
                            btn.classList.add('chip-alcalde');
                            btn.classList.add('vhs-theme');
                        }
                        
                        btn.setAttribute('data-map', item.idHtml);
                        btn.innerHTML = `<span>${item.nombreVis}</span>`; // FIX: El span hace que el CSS funcione
                        chipsGroup.appendChild(btn);
                    });

                    let mobileToggle = chipsGroup.querySelector('.chip-more');
                    if (!mobileToggle) {
                        mobileToggle = document.createElement('button');
                        mobileToggle.className = 'chip chip-more';
                        mobileToggle.type = 'button';
                        mobileToggle.setAttribute('aria-expanded', 'false');
                        mobileToggle.setAttribute('aria-controls', 'mobileCategoryPanel');
                        mobileToggle.innerHTML = '<span>Categorías</span>';
                        chipsGroup.appendChild(mobileToggle);
                    }

                    let mobilePanel = navChips ? navChips.querySelector('#mobileCategoryPanel') : null;
                    if (!mobilePanel && navChips) {
                        mobilePanel = document.createElement('div');
                        mobilePanel.id = 'mobileCategoryPanel';
                        mobilePanel.className = 'mobile-category-panel';
                        mobilePanel.hidden = true;
                        navChips.appendChild(mobilePanel);
                    }

                    if (mobilePanel) {
                        mobilePanel.innerHTML = '';
                        const mobileIcon = (id, name) => {
                            const label = `${id} ${name}`.toLowerCase();
                            if (label.includes('educ')) return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3 2 8l10 5 8-4v6h2V8L12 3Zm-6 8.18V15c0 1.76 3.58 4 6 4s6-2.24 6-4v-3.82l-6 3-6-3Z"/></svg>';
                            if (label.includes('agua')) return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.25S6 10.1 6 14.2A6 6 0 0 0 18 14.2c0-4.1-6-10.95-6-10.95Zm0 13.95a3 3 0 0 1-3-3h2a1 1 0 0 0 1 1v2Z"/></svg>';
                            if (label.includes('formal')) return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h7l4 4v14H7V3Zm6 1.5V8h3.5L13 4.5ZM9 11h6v2H9v-2Zm0 4h7v2H9v-2Z"/></svg>';
                            if (label.includes('alcalde') || label.includes('provincial')) return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm0 4.1 4 1.5v3.5c0 3.1-1.7 6-4 7.2-2.3-1.2-4-4.1-4-7.2V7.6l4-1.5Zm-1 9.4 5-5-1.4-1.4-3.6 3.6-1.6-1.6L8 12.5l3 3Z"/></svg>';
                            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 20 6.5v9L12 20 4 15.5v-9L12 2Zm0 2.3L6 7.7v6.6l6 3.4 6-3.4V7.7l-6-3.4Z"/></svg>';
                        };
                        menuItems.forEach(item => {
                            const displayName = String(item.nombreVis || '').replace(/^acalde\b/i, 'Alcalde');
                            const option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'mobile-category-option';
                            option.setAttribute('data-mobile-map', item.idHtml);
                            option.innerHTML = `
                                <span class="mobile-category-option__icon">${mobileIcon(item.idHtml, displayName)}</span>
                                <span class="mobile-category-option__label"></span>
                                <span class="mobile-category-option__chevron" aria-hidden="true"></span>
                            `;
                            option.querySelector('.mobile-category-option__label').textContent = displayName;
                            option.addEventListener('click', () => {
                                const sourceChip = chipsGroup.querySelector(`.chip[data-map="${item.idHtml}"]`);
                                if (sourceChip) sourceChip.click();
                                mobilePanel.querySelectorAll('.mobile-category-option').forEach(btn => btn.classList.remove('is-active'));
                                option.classList.add('is-active');
                                const toggleLabel = mobileToggle.querySelector('span');
                                if (toggleLabel) toggleLabel.textContent = 'Categorías';
                                mobilePanel.hidden = true;
                                mobilePanel.classList.remove('is-open');
                                mobileToggle.setAttribute('aria-expanded', 'false');
                            });
                            mobilePanel.appendChild(option);
                        });
                    }

                    mobileToggle.addEventListener('click', () => {
                        if (!mobilePanel) return;
                        const isOpen = mobilePanel.hidden;
                        mobilePanel.hidden = !isOpen;
                        mobilePanel.classList.toggle('is-open', isOpen);
                        mobileToggle.setAttribute('aria-expanded', String(isOpen));
                    });

                    attachChipListeners();
                    console.log("[Mapa] ✅ Menú visual inyectado exitosamente:", menuItems);
                }
            }
        } catch (e) {
            console.warn("[Mapa] ⚠️ Error al generar menú dinámico.", e);
        }
    };

    // Resize
    if (window._mapResizeHandler) {
        window.removeEventListener('resize', window._mapResizeHandler);
    }
    window._mapResizeHandler = () => { if (map) map.resize(); };
    window.addEventListener('resize', window._mapResizeHandler);
    
    // Auto-arranque de Obras
    setTimeout(() => {
        if (map) map.resize();
        swapSegment('base');
        
        // Construir menú dinámico desde la pestaña SEGMENTOS
        if (typeof window.loadDynamicMenu === 'function') {
            window.loadDynamicMenu().catch(error => {
                console.warn("[Mapa] Menu dinamico no disponible al iniciar.", error);
            });
        }
        
        // FORMA NATURAL RESTAURADA: Encadenamos todo desde la base de manera orgánica.
    }, 500);

    // Función de limpieza obligatoria para Barba.js
    window.obrasCleanup = () => {
        if (window.mapInstance) {
            window.mapInstance.remove();
            window.mapInstance = null;
        }
        // Destrucción total del entorno de Red Vial para evitar Memory Leaks en Barba.js
        if (typeof window.destroyRedVial === 'function') window.destroyRedVial();

        document.querySelector('.app-bg')?.classList.remove('show');
    };
}
