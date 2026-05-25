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
        const container = getEl('gooey-text-container');
        const el1 = getEl('gooey-text-1');
        const el2 = getEl('gooey-text-2');
        if (!el1 || !el2 || !container) return;
        
        if (container._gooeyActive) return; // Evitar múltiples bucles
        container._gooeyActive = true;

        const texts = [
            "BIENVENIDO A TACNA",
            "EL FUTURO SE CONSTRUYE HOY",
            "OBRAS FUERZA TACNA"
        ];

        // Aparecemos el contenedor suavemente al iniciar
        container.style.display = 'flex';
        container.style.opacity = '1';
        container.style.filter = 'url(#threshold)';

        let textIndex = 0; // Empezamos desde el primer texto
        let textsDone = 0; // Contador para finalizar la animación
        let time = new Date();
        let morph = 0;
        const morphTime = 2.5; 
        const cooldownTime = 2; 
        let cooldown = cooldownTime;


        const setMorph = (fraction) => {
            // Incoming text (el2)
            const blurIn = Math.min(8 / fraction - 8, 100);
            el2.style.filter = `blur(${blurIn}px)`;
            el2.style.opacity = `${Math.pow(fraction, 0.4) * 100}%`;

            // Outgoing text (el1)
            const f1 = 1 - fraction;
            const blurOut = Math.min(8 / f1 - 8, 100);
            el1.style.filter = `blur(${blurOut}px)`;
            el1.style.opacity = `${Math.pow(f1, 0.4) * 100}%`;
        };

        function animate() {
            // Si el contenedor ya no está en el DOM (navegación Barba), detenemos el bucle
            if (!document.body.contains(container)) return;

            const newTime = new Date();
            const dt = (newTime.getTime() - time.getTime()) / 1000;
            time = newTime;

            // Pausar lógica si el video está oculto, pero seguir actualizando el 'time' para evitar saltos
            if (container.style.opacity === '0') {
                requestAnimationFrame(animate);
                return;
            }

            const shouldIncrementIndex = cooldown > 0;

            cooldown -= dt;

            if (cooldown <= 0) {
                if (shouldIncrementIndex) {
                    // Si ya terminamos el cooldown del último texto, disparamos evento
                    if (textIndex >= texts.length - 1) {
                        container.style.opacity = '0';
                        container._gooeyActive = false;
                        setTimeout(() => { container.style.display = 'none'; }, 1000);
                        
                        console.log("Gooey: Enviando evento de finalización...");
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
                // Aseguramos que durante el cooldown el texto actual esté nítido y visible
                // Si es el primer texto, forzamos que se vea el1, si no, el2
                if (textIndex === 0 && textsDone === 0) {
                    el1.textContent = texts[0];
                    setMorph(0); 
                } else {
                    setMorph(1);
                }
            }

            requestAnimationFrame(animate);
        }
        animate();
    };

    // --- NUEVO: Función para revelar la interfaz (Chips y Sidebar) ---
    const revealUI = () => {
        const chips = target.querySelector('.chips') || document.querySelector('.chips');
        const kpiGrid = getEl('intro-kpi-grid');
        
        if (chips) { 
            chips.style.opacity = '1'; 
            chips.style.visibility = 'visible'; 
            if (currentKey === 'base' || !currentKey) {
                chips.classList.add('is-glass');
            }
        }

        if (kpiGrid) {
            kpiGrid.classList.add('is-visible');
        }
        
        console.log("UI Revelada de forma natural");
    };

    // =================================================================================
    // FIX SUPREMO: ARRANQUE INDESTRUCTIBLE DE INTERFAZ
    // Mostramos la interfaz de inmediato. Si MapLibre tarda en descargar o falla,
    // el usuario igual verá el video, los botones y el texto sin quedarse congelado.
    // =================================================================================
    setTimeout(() => {
        const videoIntro = document.getElementById('video-intro-container');
        if (videoIntro) {
            videoIntro.style.visibility = 'visible';
            videoIntro.style.opacity = '1';
            const v = videoIntro.querySelector('video');
            if (v) { 
                v.muted = true; 
                v.loop = true; 
                v.setAttribute('playsinline', '');
                v.setAttribute('webkit-playsinline', '');
            }
        }
        
        const chipsEl = target.querySelector('.chips') || document.querySelector('.chips');
        const dock = getEl('filtersDock');
        if (chipsEl) { chipsEl.style.opacity = '0'; chipsEl.style.visibility = 'hidden'; }
        if (dock) { dock.style.opacity = '0'; dock.style.visibility = 'hidden'; }
        
        setTimeout(initGooeyText, 1200); 
        setTimeout(() => {
            revealUI();
            // Dejamos que el navegador dibuje la interfaz primero, 
            // y luego le damos la orden pesada de reproducir el video.
            setTimeout(() => {
                const v = document.querySelector('#video-intro-container video');
                if (v) v.play().catch(() => {});
            }, 150);
        }, 2000);
    }, 100);

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
        style: { version: 8, sources: {}, layers: [] },
        center: [0, 0],
        zoom: 0,
        maxPitch: 0,
        dragRotate: false,
        attributionControl: false
    });
    
    mapEl.style.background = 'transparent';
    window.mapInstance = map;
    
    const IS_MOBILE = window.matchMedia('(max-width: 600px)').matches;
    if (!IS_MOBILE) map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');

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

        // FASE 2: Proyección Inversa para la clusterización
        // Usamos la resolución original para preservar el algoritmo matemático exacto de Leaflet
        const imgH = mapLat / 0.005;
        const imgW = mapLon / 0.005;
        const clusters = [];
        const CLUSTER_DIST = 85; 

        validas.forEach((o) => {
            const pxY = o.y * imgH;
            const pxX = o.x * imgW;
            let found = false;

            for (const cluster of clusters) {
                const dist = Math.hypot(cluster.pxY - pxY, cluster.pxX - pxX);
                if (dist < CLUSTER_DIST) {
                    cluster.points.push({ o, pxY, pxX });
                    cluster.pxY = ((cluster.pxY * (cluster.points.length - 1)) + pxY) / cluster.points.length;
                    cluster.pxX = ((cluster.pxX * (cluster.points.length - 1)) + pxX) / cluster.points.length;
                    found = true;
                    break;
                }
            }
            if (!found) clusters.push({ pxY, pxX, points: [{ o, pxY, pxX }] });
        });

        const geojsonFeatures = [];

        clusters.forEach((cluster) => {
            const total = cluster.points.length;
            
            cluster.points.forEach((pt, index) => {
                let { o, pxY, pxX } = pt;
                
                if (total > 1) {
                    const angle = index * ((Math.PI * 2) / total); 
                    const radius = 35 + (total * 7); 
                    pxY = cluster.pxY + Math.sin(angle) * radius;
                    pxX = cluster.pxX + Math.cos(angle) * radius;
                }

                // Convertir de vuelta a las coordenadas 3D de MapLibre
                const finalLng = (pxX / imgW) * mapLon;
                const finalLat = mapLat * (1 - (pxY / imgH)); 

                const nombre = (o.nombre || '').trim(), estado = (o.estado || '').trim();
                const color = typeof colorPinPorEstado === 'function' ? colorPinPorEstado(estado) : '#801039';
                const k = typeof _obraKey === 'function' ? _obraKey(o) : `${o.x}_${o.y}`;
                
                window.__OBRA_DATA.set(k, { o, lat: finalLat, lng: finalLng });

                // Empaquetar el punto para la Tarjeta de Video
                geojsonFeatures.push({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [finalLng, finalLat] },
                    properties: { id: k, nombre, estado, color }
                });
            });
        });

        const sourceId = 'obras-source';
        
        // Siempre removemos las capas visuales antes de actualizar,
        // garantizando que los pines siempre se dibujen ENCIMA del plano base.
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

            if (videoIntro) videoIntro._gooeyActive = false; // Reset para permitir reinicio de texto

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
                if (v) { v.loop = true; v.play().catch(() => {}); }
            }
            if (mapEl) {
                mapEl.style.opacity = '0';
                mapEl.style.visibility = 'hidden';
                mapEl.style.pointerEvents = 'none';
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
            const chipsEl = target.querySelector('.chips') || document.querySelector('.chips');
            if (chipsEl) { chipsEl.style.opacity = '0'; chipsEl.style.visibility = 'hidden'; }
            if (dock) { dock.style.opacity = '0'; dock.style.visibility = 'hidden'; }
            setTimeout(initGooeyText, 1200); 
            setTimeout(revealUI, 2000);
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
            const cy = lat * (1 - gFy); // Invertimos Y porque MapLibre sube al norte

            if (isFromBase || !isAutoCenterBlocked) {
                map.fitBounds(bounds, { padding: 50, duration: 0 });
                map.setMaxBounds([ [-lon*0.5, -lat*0.5], [lon*1.5, lat*1.5] ]);
                map.setCenter([cx, cy]);
            }

            target.querySelectorAll('.chips, .fp, #resultsDock').forEach(el => {
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
        
        // Ya no llamamos a swapSegment('base') aquí para evitar doble animación,
        // la interfaz ya fue arrancada en el bloque independiente al inicio.
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