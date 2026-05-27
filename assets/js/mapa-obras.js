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

    // --- NUEVO: Lógica Gooey Text (Opción 1) ---
    const initGooeyText = () => {
        // FIX SUPREMO: Usamos querySelectorAll y tomamos siempre el último elemento.
        // Esto garantiza encontrar los textos aunque vivan fuera del <main>, 
        // y a la vez evita animar la página vieja de Barba.js.
        const cList = document.querySelectorAll('#gooey-text-container');
        const container = cList.length > 0 ? cList[cList.length - 1] : null;
        
        const e1List = document.querySelectorAll('#gooey-text-1');
        const el1 = e1List.length > 0 ? e1List[e1List.length - 1] : null;
        
        const e2List = document.querySelectorAll('#gooey-text-2');
        const el2 = e2List.length > 0 ? e2List[e2List.length - 1] : null;

        if (!el1 || !el2 || !container) return;
        
        if (container._gooeyActive) return; // Evitar que se duplique el bucle
        container._gooeyActive = true;

        // INYECCIÓN DE SEGURIDAD: Si no existe el filtro SVG en el HTML, el navegador 
        // oculta todo el texto al aplicar url(#threshold). Esto garantiza que siempre exista.
        if (!document.getElementById('threshold')) {
            document.body.insertAdjacentHTML('beforeend', '<svg style="display:none"><defs><filter id="threshold"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" /><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 255 -140" /></filter></defs></svg>');
        }

        const texts = [
            "BIENVENIDO A TACNA",
            "EL FUTURO SE CONSTRUYE HOY",
            "OBRAS FUERZA TACNA"
        ];

        container.style.setProperty('display', 'flex', 'important');
        container.style.setProperty('opacity', '1', 'important');
        container.style.setProperty('visibility', 'visible', 'important');
        container.style.filter = 'url(#threshold)';

        let textIndex = 0;
        let time = new Date();
        let morph = 0;
        const morphTime = 2.0; 
        const cooldownTime = 1.2; 
        let cooldown = cooldownTime;

        // Colocamos el primer texto de forma inicial
        el1.textContent = texts[0];
        el2.textContent = "";

        const setMorph = (fraction) => {
            const blurIn = Math.min(8 / fraction - 8, 100);
            el2.style.filter = `blur(${blurIn}px)`;
            el2.style.opacity = Math.pow(fraction, 0.4).toFixed(3);

            const f1 = 1 - fraction;
            const blurOut = Math.min(8 / f1 - 8, 100);
            el1.style.filter = `blur(${blurOut}px)`;
            el1.style.opacity = Math.pow(f1, 0.4).toFixed(3);
        };

        function animate() {
            if (!document.body.contains(container) || !container._gooeyActive) return;

            const newTime = new Date();
            const dt = (newTime.getTime() - time.getTime()) / 1000;
            time = newTime;

            const shouldIncrementIndex = cooldown > 0;
            cooldown -= dt;

            if (cooldown <= 0) {
                if (shouldIncrementIndex) {
                    // Si ya mostramos todos los textos, apagamos y revelamos los KPIs
                    if (textIndex >= texts.length - 1) {
                        container.style.opacity = '0';
                        container._gooeyActive = false;
                        setTimeout(() => { container.style.display = 'none'; }, 2000);
                        
                        console.log("Gooey: Secuencia completada. Iniciando KPIs...");
                        window.dispatchEvent(new CustomEvent('gooeyTextFinished'));
                        return;
                    }

                    textIndex++;
                    el1.textContent = texts[textIndex - 1];
                    el2.textContent = texts[textIndex];
                }

                morph -= cooldown;
                cooldown = 0;
                let fraction = morph / morphTime;
                if (fraction > 1) {
                    cooldown = cooldownTime;
                    fraction = 1;
                }
                setMorph(fraction);
            } else {
                morph = 0;
                if (textIndex === 0) setMorph(0);
                else setMorph(1);
            }
            requestAnimationFrame(animate);
        }
        animate();
    };

    const revealUI = () => {
        const chips = target.querySelector('.chips') || document.querySelector('.chips');
        const dock = getEl('filtersDock');
        const mapEl = getEl('map');
        const footer = getEl('home-footer');
        
        if (chips) { 
            chips.style.opacity = '1'; 
            chips.style.visibility = 'visible'; 
            chips.classList.add('is-glass');
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
        if (bg) bg.classList.add("show");
    }

    function updateHud(label){
        const hud = getEl('zoomHud');
        if (hud) hud.textContent = `${label} | zoom: ${map.getZoom().toFixed(2)}`;
    }

    await loadMapLibre();

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
    if (!IS_MOBILE) map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');

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
    let hoverIntentTimer = null; // NUEVO: Temporizador anti-colapso
    let hoverRAF = null;
    let lastMouseEvent = null;

    // ESCUDO DE RENDIMIENTO 1: Ocultar tarjetas de inmediato si el usuario hace zoom o arrastra
    map.on('zoomstart', () => { clearTimeout(hoverIntentTimer); ghostTooltip.remove(); currentHoverKey = null; });
    map.on('dragstart', () => { clearTimeout(hoverIntentTimer); ghostTooltip.remove(); currentHoverKey = null; });

    // SOLUCIÓN DEFINITIVA DE RENDIMIENTO (Reemplazo de mouseenter/mouseleave)
    // Evita que MapLibre ejecute queryRenderedFeatures miles de veces de forma interna
    map.on('mousemove', (e) => {
        if (map.isZooming() || map.isMoving() || map.isRotating()) return;
        if (window.matchMedia('(max-width: 600px)').matches) return; // En móvil no hay hover
        
        lastMouseEvent = e;
        if (hoverRAF) return;
        
        hoverRAF = requestAnimationFrame(() => {
            hoverRAF = null;
            
            // EVITA ERROR EN CONSOLA: Verificamos que la capa de obras exista antes de intentar leerla.
            // Esto es crucial porque en la vista "Inicio" (base) esta capa es eliminada del mapa.
            if (!map.getLayer('obras-layer')) {
                if (currentHoverKey !== null) {
                    clearTimeout(hoverIntentTimer);
                    currentHoverKey = null;
                    map.getCanvas().style.cursor = '';
                    ghostTooltip.remove();
                }
                return;
            }

            // Realizamos la consulta solo 1 vez por frame, y limitada solo a los pines
            const features = map.queryRenderedFeatures(lastMouseEvent.point, { layers: ['obras-layer'] });
            
            if (!features.length) {
                // Reemplazo del antiguo "mouseleave"
                if (currentHoverKey !== null) {
                    clearTimeout(hoverIntentTimer);
                    currentHoverKey = null;
                    map.getCanvas().style.cursor = '';
                    ghostTooltip.remove();
                }
                return;
            }
            
            map.getCanvas().style.cursor = 'pointer';
            
            const feature = features[0];
            const key = feature.properties.id;
            
            if (currentHoverKey === key) return;
            
            clearTimeout(hoverIntentTimer);
            
            hoverIntentTimer = setTimeout(() => {
                currentHoverKey = key;
                const data = window.__OBRA_DATA.get(key);
                
                if (data && data.o) {
                    const o = data.o;
                    const nombre = o.nombre || '';
                    const estado = o.estado || '';
                    const rawMonto = (o.monto || '').trim();
                    const monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';
                    
                    const pill = typeof estadoToPill === 'function' ? estadoToPill(estado) : {cls:'', txt:estado};
                    
                    let fragHTML = typeof imgFragmentFor === 'function' ? imgFragmentFor(currentKey, o.carpeta, nombre) : '';
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
            const dirFotos = window.FOTOS_DIR[currentKey] || currentKey;
            const base  = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}` : null;
            const dinBuster = "?v=" + new Date().getTime();
            const rawMonto = (o.monto || '').trim();
            const monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';

            if (window.PanelObra && typeof window.PanelObra.open === 'function') {
                window.PanelObra.open({ key: o.carpeta || `${o.nombre}|${o.x}|${o.y}`, nombre: o.nombre, estado: o.estado, monto: monto, distrito: o.distrito, provincia: o.provincia, descripcion: o.descripcion || '', portada: base ? `${base}/1.thumb.webp${dinBuster}` : null, fotos: base ? Array.from({length:6}, (_,i)=> `${base}/${i+1}.webp${dinBuster}`) : [], onCenter: () => { map.flyTo({ center: [data.lng, data.lat], zoom: Math.max(map.getZoom(), 2), speed: 1.2, curve: 1.42 }); } });
                if (typeof window.recordVisit === 'function') window.recordVisit(key, currentKey);
            }
        }
    });

    window.__OBRA_MARKERS = new Map();
    window.__OBRA_DATA    = new Map();
    window.SHEET_CACHE = window.SHEET_CACHE || Object.create(null);
    let PINS_LOADING = new Set();

    async function cargarPinesDesdeSheet(segmento, mapLat, mapLon){
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
                    window.SHEET_CACHE[segmento] = gvizToObjects(JSON.parse(match[1]));
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

        if (currentKey !== segmento) {
            PINS_LOADING.delete(segmento);
            return;
        }

        const obras = window.SHEET_CACHE[segmento] || [];
        const toNum = v => { if (v == null) return NaN; const n = parseFloat(String(v).trim().replace(',', '.').replace('%','')); return Number.isFinite(n) ? n : NaN; };
        
        window.__OBRA_DATA.clear();
        const validas = [];
        
        for (const o of obras){
            const nombre = (o.nombre || '').trim();
            const x = toNum(o.x), y = toNum(o.y);
            if (!nombre || isNaN(x) || isNaN(y) || x < 0 || x > 1 || y < 0 || y > 1) continue;
            const rawCarp = (o.carpeta ?? '').toString().trim();
            validas.push({ ...o, x, y, carpeta: (rawCarp && rawCarp.toLowerCase() !== 'null' && rawCarp !== '-') ? rawCarp : null });
        }

        // FASE 2: Empaquetado puro de datos, sin distorsionar coordenadas (Modo Google Maps)
        const geojsonFeatures = validas.map((o) => {
            const finalLng = o.x * mapLon;
            const finalLat = o.y * mapLat; 

            const nombre = (o.nombre || '').trim(), estado = (o.estado || '').trim();
            const color = typeof colorPinPorEstado === 'function' ? colorPinPorEstado(estado) : '#801039';
            const k = typeof _obraKey === 'function' ? _obraKey(o) : `${o.x}_${o.y}`;
            
            window.__OBRA_DATA.set(k, { o, lat: finalLat, lng: finalLng });

            return {
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [finalLng, finalLat] },
                properties: { id: k, nombre, estado, color }
            };
        });

        const sourceId = 'obras-source';
        
        // Siempre removemos las capas visuales antes de actualizar,
        // garantizando que los pines siempre se dibujen ENCIMA del plano base.
        if (map.getLayer('clusters')) map.removeLayer('clusters');
        if (map.getLayer('cluster-count')) map.removeLayer('cluster-count');
        if (map.getLayer('obras-labels-layer')) map.removeLayer('obras-labels-layer');
        if (map.getLayer('obras-layer')) map.removeLayer('obras-layer');
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

        // DIBUJADO GPU 2: Pines de Color Vectoriales
        map.addLayer({
            id: 'obras-layer',
            type: 'circle',
            source: sourceId,
            paint: {
                'circle-radius': 8,
                'circle-color': ['get', 'color'],
                'circle-stroke-width': 2,
                'circle-stroke-color': '#ffffff'
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
                    5, 10,    // En zoom 5 (distancia media), letra visible (10px)
                    6, 20     // En zoom 6 (acercamiento), alcanza el tamaño máximo (20px)
                ],
                'text-transform': 'uppercase',
                'text-letter-spacing': 0.05,
                'text-variable-anchor': ['bottom'],
                'text-radial-offset': -0.1,
                'text-justify': 'center',
                'text-max-width': 12,
                'text-padding': 15 
            },
            paint: {
                'text-color': '#111111',
                'text-halo-color': '#ffffff',
                'text-halo-width': 2,
                'text-halo-blur': 0.5
            }
        });

        PINS_LOADING.delete(segmento);
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

            const allGooey = document.querySelectorAll('#gooey-text-container');
            if (allGooey.length > 0) allGooey[allGooey.length - 1]._gooeyActive = false; // Reset real del texto

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

            const kpiGrid = target.querySelector('#intro-kpi-grid') || document.getElementById('intro-kpi-grid');
            if (kpiGrid) {
                kpiGrid.classList.remove('is-visible', 'is-centered');
            }

            setTimeout(revealUI, 2000);      // Los chips y el footer aparecen a los 2s
            setTimeout(initGooeyText, 3500); // El texto inicia DESPUÉS del menú a los 3.5s
            return;
        }

        const rawUrl = window.MAPS[key];
        if (!rawUrl || isSwapping){ pendingKey = key; return; }
        
        // BUGFIX: Capturamos el estado anterior antes de actualizar currentKey
        const prevKey = currentKey;
        isSwapping = true; 
        currentKey = key; // Definimos el intento de navegación inmediatamente
        setBackgroundFor(key);
        const url = encodeURI(rawUrl);

        // Quitar efecto glass al entrar a cualquier mapa
        if (chips) chips.classList.remove('is-glass');

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
        
        const probe = new Image();
        let probeTimer = setTimeout(() => { 
            isSwapping = false; 
            const k = pendingKey; pendingKey = null; if (k) swapSegment(k); 
        }, 8000);
        probe.onerror = () => { 
            clearTimeout(probeTimer); isSwapping = false; 
            const k = pendingKey; pendingKey = null; if (k) swapSegment(k); 
        };
        probe.onload = () => {
            clearTimeout(probeTimer);
            
            if (currentKey !== key || currentKey === 'base') {
                isSwapping = false;
                return;
            }

            const w = probe.naturalWidth, h = probe.naturalHeight;
            // Mapear el PNG a coordenadas geográficas en MapLibre (escala 0.005)
            const lon = w * 0.005; 
            const lat = h * 0.005;
            const bounds = [ [0, 0], [lon, lat] ];
            
            map.resize();

            if (map.getSource('plano-base')) {
                if (map.getLayer('plano-layer')) map.removeLayer('plano-layer');
                map.removeSource('plano-base');
            }

            map.addSource('plano-base', {
                type: 'image',
                url: url,
                coordinates: [ [0, lat], [lon, lat], [lon, 0], [0, 0] ]
            });

            map.addLayer({
                id: 'plano-layer',
                type: 'raster',
                source: 'plano-base',
                paint: { 'raster-fade-duration': 600 } 
            });

            const [gFx, gFy] = FOCUS[key] || [0.5, 0.5];
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
            isSwapping = false; 
            if (pendingKey){ const k = pendingKey; pendingKey = null; swapSegment(k); }

            (async ()=>{
                try{ 
                    if (currentKey !== key) return; // Cancelar si el usuario navegó a otro lado
                    await cargarPinesDesdeSheet(key, lat, lon);
                if (window.SHEETS[key]) { 
                    // Llamamos a las funciones que ahora viven en mapa-filtros.js
                    if (typeof buildFilterOptions === 'function') buildFilterOptions();
                    if (typeof attachFilterEvents === 'function') attachFilterEvents();
                }
                }catch(err){ console.error(err); }
            })();
        };
        probe.src = url;
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
                zoom: Math.max(map.getZoom(), 2),
                speed: 1.2,
                curve: 1.42
            });
            map.once('moveend', () => { isAutoCenterBlocked = false; });
            setTimeout(() => { isAutoCenterBlocked = false; }, 2000);
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
        // --- EFECTO WIGGLE / PARALLAX ---
        const vContainer = document.getElementById('video-intro-container');
        const vImg = document.getElementById('introVideo');
        
        if (vContainer && vImg && typeof gsap !== 'undefined') {
            if (window._introVideoWiggle) {
                window.removeEventListener('mousemove', window._introVideoWiggle);
            }
            window._introVideoWiggle = (e) => {
                // Solo aplicar si el video es visible
                if (vContainer.style.opacity === '0') return;

                const { clientX, clientY } = e;
                const xPos = (clientX / window.innerWidth) - 0.5;
                const yPos = (clientY / window.innerHeight) - 0.5;

                // Movemos el video sutilmente (30px de rango)
                gsap.to(vImg, {
                    duration: 1.2,
                    x: xPos * 40,
                    y: yPos * 40,
                    rotateX: yPos * -2, // Un toque de inclinación 3D
                    rotateY: xPos * 2,
                    ease: "power2.out"
                });
            };
            window.addEventListener('mousemove', window._introVideoWiggle);
        }

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