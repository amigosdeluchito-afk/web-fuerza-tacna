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
        if (el) el.style.display = (key === 'base') ? 'none' : 'block';
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
                    
                    let fragHTML = typeof window.imgFragmentFor === 'function' ? window.imgFragmentFor(currentKey, o.carpeta, nombre) : '';
                    if (fragHTML && fragHTML.includes('1.thumb.webp')) fragHTML = fragHTML.replace(/1\.thumb\.webp/g, `1.thumb.webp?v=${sessionTs}`);
                    
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
            const dirFotos = String(window.FOTOS_DIR[currentKey] || currentKey).toLowerCase();
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

        const TAB = window.SHEETS[segmento];
        if (!TAB){ PINS_LOADING.delete(segmento); return; }

        try{
            window.SHEET_FETCH_PROMISES = window.SHEET_FETCH_PROMISES || {};
            if (!window.SHEET_CACHE[segmento] && !window.SHEET_FETCH_PROMISES[segmento]){
                const url = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&sheet=${encodeURIComponent(TAB)}&range=A:J&headers=1`;
                window.SHEET_FETCH_PROMISES[segmento] = fetch(url).then(r => r.text()).then(txt => {
                    const match = txt.match(/setResponse\(([\s\S]+)\);?/);
                    if (!match) throw new Error("Error GViz");
                    window.SHEET_CACHE[segmento] = window.gvizToObjects(JSON.parse(match[1]));
                });
            }
            if (window.SHEET_FETCH_PROMISES[segmento]) {
                await window.SHEET_FETCH_PROMISES[segmento];
            }
        }catch(err){
            console.error("Error al cargar datos de la hoja:", err);
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
            // FIX: Soporte a prueba de fallos para nombres de columnas en mayúscula (Nombre, X, Y)
            const nombre = String(o.nombre ?? o.Nombre ?? o.NOMBRE ?? '').trim();
            const rawX = o.x ?? o.X ?? o.lng ?? o.Lng;
            const rawY = o.y ?? o.Y ?? o.lat ?? o.Lat;
            const x = toNum(rawX), y = toNum(rawY);
            
            if (!nombre) continue;
            if (isNaN(x) || isNaN(y)) {
                console.warn(`[Mapa] Fila omitida (Coordenadas inválidas): ${nombre} -> X: ${rawX}, Y: ${rawY}`);
                continue;
            }
            
            const estado = String(o.estado ?? o.Estado ?? o.ESTADO ?? '').trim();
            if (estado.toLowerCase().includes('oculto')) continue;

            const rawCarp = String(o.carpeta ?? o.Carpeta ?? o.CARPETA ?? '').trim();
            validas.push({ ...o, nombre, estado, x, y, carpeta: (rawCarp && rawCarp.toLowerCase() !== 'null' && rawCarp !== '-') ? rawCarp : null });
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

            setTimeout(revealUI, 1500);      // Los chips y el footer aparecen a los 1.5s
            return;
        }

        if (isSwapping){ pendingKey = key; return; }
        
        // BUGFIX: Capturamos el estado anterior antes de actualizar currentKey
        const prevKey = currentKey;
        isSwapping = true; 
        currentKey = key; // Definimos el intento de navegación inmediatamente
        
        
        setBackgroundFor(key);


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
            svgContainer.style.opacity = '1';
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
            if (key !== 'base' && !window._mapHintShown) {
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
            const dirFotos = String(window.FOTOS_DIR[currentKey] || currentKey).toLowerCase();
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

    // Clicks en los chips
    target.querySelectorAll('.chip[data-map]').forEach(chip => {
        chip.addEventListener('click', () => {
            const key = chip.getAttribute('data-map');
            
            // --- NUEVO: Comportamiento Inteligente del botón Inicio ---
            // Si se presiona "Inicio" (base) y ya estamos en esa pantalla, regresamos a la Matriz Principal.
            if (key === 'base' && (currentKey === 'base' || currentKey === null)) {
                // Método infalible para encontrar la raíz del proyecto y redirigir
                let rootUrl = window.location.href.split('?')[0].split('#')[0];
                if (rootUrl.includes('/assets/')) rootUrl = rootUrl.substring(0, rootUrl.indexOf('/assets/'));
                else if (rootUrl.match(/\/obras\/?$/)) rootUrl = rootUrl.replace(/\/obras\/?$/, '');
                
                window.location.href = rootUrl + '/index.html';
                return;
            }

            if (key === currentKey) return;
            target.querySelectorAll('.chip[data-map]').forEach(c => c.classList.remove('is-active'));
            chip.classList.add('is-active');
            swapSegment(key);
        });
    });

    // Resize
    if (window._mapResizeHandler) {
        window.removeEventListener('resize', window._mapResizeHandler);
    }
    window._mapResizeHandler = () => { if (map) map.resize(); };
    window.addEventListener('resize', window._mapResizeHandler);
    
    // Auto-arranque de Obras
    setTimeout(() => {

        if (map) map.resize();
        
        // FORMA NATURAL RESTAURADA: Encadenamos todo desde la base de manera orgánica.
        swapSegment('base');
    }, 500);

    // Función de limpieza obligatoria para Barba.js
    window.obrasCleanup = () => {
        if (window.mapInstance) {
            window.mapInstance.remove();
            window.mapInstance = null;
        }
        document.querySelector('.app-bg')?.classList.remove('show');
    };
}