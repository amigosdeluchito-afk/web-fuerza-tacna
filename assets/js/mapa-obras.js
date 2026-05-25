/* =========================================================
   MAPA-OBRAS.JS - CONFIGURACIÓN GLOBAL Y NÚCLEO DEL MAPA
   ========================================================= */

window.initLeafletMap = function(container) {
    const target = container || document;
    const mapEl = target.querySelector('#map');
    if (!mapEl) return;

    // Limpieza de mapa previo para que no choque con Barba.js
    if (window.leafletMapInstance) {
        window.leafletMapInstance.remove();
        window.leafletMapInstance = null;
    }

    // Función auxiliar para buscar elementos solo dentro del nuevo contenedor
    const getEl = (id) => target.querySelector('#' + id);

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
        const chips = target.querySelector('.chips');
        
        if (chips) { 
            chips.style.opacity = '1'; 
            chips.style.visibility = 'visible'; 
            if (currentKey === 'base' || !currentKey) {
                chips.classList.add('is-glass');
            }
        }
        
        console.log("UI Revelada sobre el video");
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

    // SEGURIDAD: Si el div #map ya tiene un mapa de Leaflet, abortamos para evitar el crash.
    if (mapEl._leaflet_id) {
        console.warn("initLeafletMap: El mapa ya estaba inicializado. Abortando para evitar colisión.");
        return;
    }

    // Inicializar Mapa
    const map = L.map(mapEl, {
        crs: L.CRS.Simple,
        zoomControl: false,
        zoomSnap: 0.1,
        zoomDelta: 0.5,
        inertia: true,
        inertiaDeceleration: 3000,
        maxBoundsViscosity: 1.0,

        // FIX SUPREMO 4.0: Apagamos los motores nativos frágiles
        scrollWheelZoom: false, // Apagamos el lector de rueda nativo de Leaflet
        zoomAnimation: false,
        markerZoomAnimation: false
    });
    mapEl.style.background = 'transparent';
    window.leafletMapInstance = map;

    // =========================================================================
    // FIX SUPREMO 4.0: MOTOR DE ZOOM CUSTOM (BYPASS ABSOLUTO)
    // Reemplaza por completo el motor de rueda de Leaflet. 
    // Elimina el congelamiento en zonas densas garantizando un 100% de respuesta.
    // =========================================================================
    let targetZoom = null;
    let zoomFrame = null;

    mapEl.addEventListener('wheel', (e) => {
        e.preventDefault();
        e.stopPropagation(); // Destruye conflictos con SmoothScroll de raíz

        const rawDelta = e.deltaY * -1;
        if (rawDelta === 0) return;

        // Sensibilidad: Trackpad (fluido) vs Ratón Clásico (pasos fijos)
        const zDelta = Math.abs(rawDelta) < 50 ? (rawDelta * 0.015) : (Math.sign(rawDelta) * 0.4);

        if (targetZoom === null) targetZoom = map.getZoom();
        targetZoom += zDelta;

        // Respetar límites
        if (targetZoom < map.getMinZoom()) targetZoom = map.getMinZoom();
        if (targetZoom > map.getMaxZoom()) targetZoom = map.getMaxZoom();

        if (!zoomFrame) {
            zoomFrame = requestAnimationFrame(() => {
                try {
                    const mousePos = map.mouseEventToContainerPoint(e);
                    map.setZoomAround(mousePos, targetZoom, { animate: false });
                } catch(err) {}
                targetZoom = null;
                zoomFrame = null;
            });
        }
    }, { passive: false, capture: true });
    
    const IS_MOBILE = window.matchMedia('(max-width: 600px)').matches;
    if (!IS_MOBILE) L.control.zoom({ position: 'bottomright' }).addTo(map);
    const RESULT_ZOOM = IS_MOBILE ? 0 : 0.1;

    let currentOverlay = null;
    let currentBounds  = null;
    let currentKey     = null;
    let isSwapping     = false;
    let pendingKey     = null;
    let isAutoCenterBlocked = false; // Bandera para evitar que el mapa robe el foco durante una búsqueda
    let GLOBAL_ZOOM    = null;
    let __LAST_ZOOM = null;
    let __LABELS_UNLOCKED = false;
    let LABEL_MIN_ZOOM = null;

    let updateLabelsTimer;
    const requestLabelsUpdate = () => {
        clearTimeout(updateLabelsTimer);
        updateLabelsTimer = setTimeout(updateLabelsVisibility, 100);
    };

    map.on('zoomstart', ()=>{ 
        try {
            __LAST_ZOOM = map.getZoom(); 
            clearTimeout(updateLabelsTimer); // PREVIENE LAG: Cancela el dibujado si el usuario sigue haciendo zoom
            hideAllLabels();
        } catch(e){}
    });
    map.on('zoomend', () => {
        try {
            const z = map.getZoom();
            if (!__LABELS_UNLOCKED && typeof LABEL_MIN_ZOOM === 'number' && z + 1e-6 >= LABEL_MIN_ZOOM) { __LABELS_UNLOCKED = true; }
            updateHud(currentKey ?? '—');
            requestLabelsUpdate();
        } catch(e){}
    });
    map.on('moveend', requestLabelsUpdate);

    const pins = L.layerGroup().addTo(map);
    const relayoutDebounced = (() => { let t; return () => { clearTimeout(t); t = setTimeout(relayoutSoon, 30); }; })();
    let PINS_LOADING = new Set();

    function hideAllLabels(){
        // Ocultamiento en lote instantáneo usando la GPU (O(1) DOM operation)
        if (mapEl) mapEl.classList.add('labels-suspended');
    }

    window.__OBRA_MARKERS = new Map();
    window.__OBRA_DATA    = new Map();
    window.__OBRA_LABELS = new Map();
    window.__THUMB_STATUS = new Map();
    window.__MISSING_THUMBS = new Set();
    window.SHEET_CACHE = window.SHEET_CACHE || Object.create(null);
    
    // Nota: Las utilidades como safe(), twoLineName(), etc., se toman de mapa-utils.js

    const LAST_LABEL_POS = new Map();
    function cupoSegunZoom(z){
        const z0 = (typeof LABEL_MIN_ZOOM === 'number') ? LABEL_MIN_ZOOM : (window.BASE_ZOOM ?? z);
        const dz = z - z0;
        if (dz < 0.00) return 0;
        if (dz < 0.35) return 10;
        if (dz < 0.80) return 18;
        if (dz < 1.20) return 36;
        if (dz < 1.60) return 72;
        return 120;
    }

    function updateLabelsVisibility(){
        const z = map.getZoom();
        if (!__LABELS_UNLOCKED) {
            if (typeof LABEL_MIN_ZOOM === 'number' && z + 1e-6 < LABEL_MIN_ZOOM){ hideAllLabels(); return; }
            __LABELS_UNLOCKED = true;
        }
        layoutEtiquetas();
    }

    function relayoutSoon(){ requestAnimationFrame(() => requestAnimationFrame(() => { updateLabelsVisibility(); })); }

    function layoutEtiquetas(){
        // FIX VITAL: Quitar la suspensión de GPU ANTES de medir. 
        // Si medimos mientras está suspendido, el ancho dará 0 y forzará 120px de separación irreal.
        if (mapEl) mapEl.classList.remove('labels-suspended');
        
        try {
        const z  = map.getZoom();
        const z0 = (typeof LABEL_MIN_ZOOM === 'number') ? LABEL_MIN_ZOOM : (window.BASE_ZOOM ?? z);
        const N  = cupoSegunZoom(z);
        const k = Math.min(1, Math.max(0, (z - z0) / 1.2));
        const PIN_SIZE = 16, PIN_BORDER = 2, PIN_RADIUS = (PIN_SIZE/2) + PIN_BORDER, CLEAR = 1.5, BIAS = -4, BASE_Y = -3;
        const dyn = Math.max(2, Math.round(9 - 5*k) + BIAS);
        const offNear = Math.max(dyn, PIN_RADIUS + CLEAR);
        const offFarY = 10, longX = offNear + 8, longY = offFarY + 6;
        const yBias = (offNear <= PIN_RADIUS + CLEAR + 2) ? 2 : 0;
        const PAD_LABEL = 4, PAD_PIN = 6;
        const view = map.getBounds();
        
        const pinRects = [];
        const pinTotalRadius = Math.ceil(PIN_RADIUS + PAD_PIN);

        // OPTIMIZACIÓN 1: Extraer posiciones de los pines usando matemática pura (0 lecturas del DOM)
        pins.eachLayer(l=>{
            if (!(l instanceof L.Marker)) return;
            if (l.options.icon?.options.className === 'obra-label') return;
            const ll = l.getLatLng();  if (!view.contains(ll)) return;
            const p = map.latLngToContainerPoint(ll);
            pinRects.push({ left: p.x - pinTotalRadius, top: p.y - pinTotalRadius, right: p.x + pinTotalRadius, bottom: p.y + pinTotalRadius });
        });

        const cand = [];
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        const cx = winW / 2;
        const cy = winH / 2;

        // OPTIMIZACIÓN 2: Recolectar datos y medir etiquetas 1 sola vez en su vida
        pins.eachLayer(l=>{
            if (!(l instanceof L.Marker)) return;
            if (l.options.icon?.options.className !== 'obra-label') return;
            const ll = l.getLatLng(); if (!view.contains(ll)) return;
            const inner = l.getElement()?.querySelector('.obra-label__inner'); if (!inner) return;
            
            let w = inner.getAttribute('data-w');
            let h = inner.getAttribute('data-h');
            if (!w || !h) {
                const wasHidden = inner.classList.contains('is-hidden');
                if (wasHidden) inner.classList.remove('is-hidden');
                const rect = inner.getBoundingClientRect();
                w = rect.width || 120; // Fallback
                h = rect.height || 30; // Fallback
                inner.setAttribute('data-w', w);
                inner.setAttribute('data-h', h);
                if (wasHidden) inner.classList.add('is-hidden');
            } else {
                w = parseFloat(w); h = parseFloat(h);
            }

            inner.classList.add('is-hidden');
            const p  = map.latLngToContainerPoint(ll);
            const key = l._leaflet_id;
            cand.push({ key, inner, marker: l, p, w, h, score: l._obra ? obraScore(l._obra) : 0, distCentro: Math.hypot(p.x - cx, p.y - cy), wasPlaced: !!LAST_LABEL_POS.get(key) });
        });

        cand.sort((a,b)=> (b.wasPlaced - a.wasPlaced) || (b.score - a.score) || (a.distCentro - b.distCentro));
        const baseTry = [ {x:+offNear,y:BASE_Y-yBias,ax:'left'}, {x:+offNear,y:-offFarY-yBias,ax:'left'}, {x:+offNear,y:+offFarY-yBias,ax:'left'}, {x:-offNear-4,y:BASE_Y-yBias,ax:'right'}, {x:-offNear-4,y:-offFarY-yBias,ax:'right'}, {x:-offNear-4,y:+offFarY-yBias,ax:'right'}, {x:+longX,y:BASE_Y-yBias,ax:'left'}, {x:+longX,y:-longY-yBias,ax:'left'}, {x:+longX,y:+longY-yBias,ax:'left'}, {x:-longX-4,y:BASE_Y-yBias,ax:'right'}, {x:-longX-4,y:-longY-yBias,ax:'right'}, {x:-longX-4,y:+longY-yBias,ax:'right'} ];
        
        const rectsLabels = [];
        let placed = 0;

        // OPTIMIZACIÓN 3: Calcular colisiones en memoria virtual (0 llamadas al DOM interlazadas)
        for (const it of cand){
            if (placed >= N) break;
            const { inner, key, p, w, h } = it;
            const remembered = LAST_LABEL_POS.get(key);
            const tries = remembered ? [remembered, ...baseTry.filter(t => !(t.x===remembered.x && t.y===remembered.y && t.ax===remembered.ax))] : baseTry;
            
            let finalT = null;

            for (const t of tries){
                let l, r_edge, top, bot;
                if (t.ax === 'left') { l = p.x + t.x; r_edge = l + w; } 
                else if (t.ax === 'right') { r_edge = p.x + t.x; l = r_edge - w; } 
                else { l = p.x + t.x - w/2; r_edge = p.x + t.x + w/2; }
                
                top = p.y + t.y;
                bot = top + h;

                if (r_edge < 0 || l > winW || bot < 0 || top > winH) continue;

                let choca = false;
                for (const rr of rectsLabels){
                    if (!(r_edge < rr.left-PAD_LABEL || l > rr.right+PAD_LABEL || bot < rr.top-PAD_LABEL || top > rr.bottom+PAD_LABEL)){ choca = true; break; }
                }
                if (choca) continue;

                for (const rp of pinRects){
                    if (!(r_edge < rp.left || l > rp.right || bot < rp.top || top > rp.bottom)){ choca = true; break; }
                }
                if (choca) continue;

                rectsLabels.push({ left: l, right: r_edge, top: top, bottom: bot });
                LAST_LABEL_POS.set(key, { x:t.x, y:t.y, ax:t.ax }); 
                finalT = t;
                placed++; 
                break;
            }

            if (!finalT){
                const jx = (Math.random() < 0.5 ? -1 : 1) * 8, jy = (Math.random() < 0.5 ? -1 : 1) * 6;
                const tx = offNear + jx, ty = BASE_Y - yBias + jy, ax = 'left';
                let l = p.x + tx, r_edge = l + w, top = p.y + ty, bot = top + h;

                let chocaJ = false;
                for (const rr of rectsLabels){
                    if (!(r_edge < rr.left-PAD_LABEL || l > rr.right+PAD_LABEL || bot < rr.top-PAD_LABEL || top > rr.bottom+PAD_LABEL)){ chocaJ = true; break; }
                }
                if (!chocaJ){
                    for (const rp of pinRects){
                        if (!(r_edge < rp.left || l > rp.right || bot < rp.top || top > rp.bottom)){ chocaJ = true; break; }
                    }
                }

                if (!chocaJ){ 
                    rectsLabels.push({ left: l, right: r_edge, top: top, bottom: bot });
                    LAST_LABEL_POS.set(key, { x: tx, y: ty, ax: ax }); 
                    finalT = { x: tx, y: ty, ax: ax };
                    placed++; 
                } else {
                    LAST_LABEL_POS.delete(key);
                }
            }

            // Escritura Batch al final
            if (finalT) {
                // FIX VISUAL: Ajustar el anclaje real en pantalla para las etiquetas ubicadas a la izquierda
                let computedX = finalT.x;
                if (finalT.ax === 'right') computedX = finalT.x - w;
                else if (finalT.ax === 'center') computedX = finalT.x - w / 2;

                inner.style.setProperty('--lx', computedX+'px'); 
                inner.style.setProperty('--ly', finalT.y+'px'); 
                inner.style.setProperty('--ax', finalT.ax);
                inner.classList.remove('is-hidden');
            }
        }
        } catch (err) {
            console.error("Error en layoutEtiquetas:", err);
        }
    }

    async function cargarPinesDesdeSheet(segmento, h, w){
        if (PINS_LOADING.has(segmento)) return;
        PINS_LOADING.add(segmento);

        // Limpieza de estados de pines previos para este segmento
        const clearPinsInternal = () => {
            pins.clearLayers();
            window.__OBRA_MARKERS.clear();
            window.__OBRA_DATA.clear();
            window.__OBRA_LABELS.clear();
            LAST_LABEL_POS.clear(); // Limpiar memoria de etiquetas al cambiar de mapa
        };

        const TAB = window.SHEETS[segmento];
        if (!TAB){ PINS_LOADING.delete(segmento); return; }

        try{
            // Utilizamos una promesa centralizada para evitar race conditions con mapa-filtros.js
            window.SHEET_FETCH_PROMISES = window.SHEET_FETCH_PROMISES || {};
            if (!window.SHEET_CACHE[segmento] && !window.SHEET_FETCH_PROMISES[segmento]){
                // Usamos reqId y headers=1 para destruir la caché y forzar las columnas
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

        // ABORTAR si el usuario ya cambió a otro segmento mientras esperábamos la descarga
        if (currentKey !== segmento) {
            PINS_LOADING.delete(segmento);
            return;
        }

        const obras = window.SHEET_CACHE[segmento] || [];
        const toNum = v => { if (v == null) return NaN; const n = parseFloat(String(v).trim().replace(',', '.').replace('%','')); return Number.isFinite(n) ? n : NaN; };
        
        clearPinsInternal();
        const validas = [];
        for (const o of obras){
            const nombre = (o.nombre || '').trim();
            const x = toNum(o.x), y = toNum(o.y);
            if (!nombre || isNaN(x) || isNaN(y) || x < 0 || x > 1 || y < 0 || y > 1) continue;
            const rawCarp = (o.carpeta ?? '').toString().trim();
            validas.push({ ...o, x, y, carpeta: (rawCarp && rawCarp.toLowerCase() !== 'null' && rawCarp !== '-') ? rawCarp : null });
        }

        const IS_TOUCH = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const dirFotos = window.FOTOS_DIR[segmento] || segmento;

        // FIX INFALIBLE: Clusterización por radio (Distance-based clustering) O(N^2)
        // Elimina el "bug de la cuadrícula" donde 2 pines pegados caían en celdas distintas y no se separaban.
        const clusters = [];
        const CLUSTER_DIST = 85; // Distancia ampliada para capturar grupos dispersos

        validas.forEach((o) => {
            const lat = o.y * h;
            const lng = o.x * w;
            let found = false;

            for (const cluster of clusters) {
                const dist = Math.hypot(cluster.lat - lat, cluster.lng - lng);
                if (dist < CLUSTER_DIST) {
                    cluster.points.push({ o, lat, lng });
                    // Actualizar el Centro de Masa (Centroide) para agrupar orgánicamente
                    cluster.lat = ((cluster.lat * (cluster.points.length - 1)) + lat) / cluster.points.length;
                    cluster.lng = ((cluster.lng * (cluster.points.length - 1)) + lng) / cluster.points.length;
                    found = true;
                    break;
                }
            }

            if (!found) {
                clusters.push({ lat, lng, points: [{ o, lat, lng }] });
            }
        });

        clusters.forEach((cluster) => {
            const total = cluster.points.length;
            
            cluster.points.forEach((pt, index) => {
                let { o, lat, lng } = pt;
                
                if (total > 1) {
                    // Distribución circular perfecta de 360 grados
                    const angle = index * ((Math.PI * 2) / total); 
                    const radius = 35 + (total * 7); 
                    // FIX MATEMÁTICO: El círculo debe dibujarse alrededor del CENTRO del cluster.
                    // Sumarlo a las coordenadas individuales causaba que los pines se empujaran unos contra otros y se fusionaran.
                    lat = cluster.lat + Math.sin(angle) * radius;
                    lng = cluster.lng + Math.cos(angle) * radius;
                }

                const nombre = (o.nombre || '').trim(), estado = (o.estado || '').trim();
                const rawMonto = (o.monto || '').trim();
                const monto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : '';
                
                const color = colorPinPorEstado(estado);
                
                const icon = L.divIcon({ className: 'obra-pin', html: `<div style="width:16px;height:16px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.25)"></div>`, iconSize: [16,16], iconAnchor: [8,8] });
                const marker = L.marker([lat, lng], { icon, riseOnHover: true, riseOffset: 3000 }).addTo(pins);
                const k = _obraKey(o);
                window.__OBRA_MARKERS.set(k, marker);
                window.__OBRA_DATA.set(k, { o, lat, lng });

            marker.on('click', () => {
                // Cierra la tarjeta fantasma para que no se buguee al abrir el panel de detalle
                if (typeof marker.closeTooltip === 'function' && marker.isTooltipOpen && marker.isTooltipOpen()) {
                    marker.closeTooltip();
                }
                
                const base  = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}` : null;
                // Timestamp dinámico que se actualiza CADA VEZ que haces clic para evitar caché de imágenes borradas o nuevas
                const dinBuster = "?v=" + new Date().getTime();
                // Pasamos las rutas directamente; el PanelObra se encarga de limpiar las que no existan
                window.PanelObra.open({
                    key: o.carpeta || `${o.nombre}|${o.x}|${o.y}`,
                    nombre, estado, monto, distrito: o.distrito, provincia: o.provincia, descripcion: o.descripcion || '',
                    portada: base ? `${base}/1.thumb.webp${dinBuster}` : null,
                    fotos: base ? Array.from({length:6}, (_,i)=> `${base}/${i+1}.webp${dinBuster}`) : [],
                    onCenter: () => map.flyTo([lat, lng], Math.max(map.getZoom(), RESULT_ZOOM), { duration: 0.6, easeLinearity: 0.25 })
                });

                // REGISTRO DE HISTORIAL (Paso 1 de la limpieza)
                if (typeof window.recordVisit === 'function') window.recordVisit(k, segmento);
            });

            const labelIcon = L.divIcon({ className: 'obra-label', html: `<div class="obra-label__inner is-hidden" style="--pin:${color}">${twoLineName(nombre)}</div>`, iconSize: [1,1], iconAnchor: [0,0] });
            const labelMarker = L.marker([lat, lng], { icon: labelIcon, interactive:false, keyboard:false }).addTo(pins);
            window.__OBRA_LABELS.set(k, labelMarker);
            labelMarker._obra = o;
            marker.on('mouseover', () => labelMarker.getElement()?.querySelector('.obra-label__inner')?.classList.add('is-hover'));
            marker.on('mouseout', () => labelMarker.getElement()?.querySelector('.obra-label__inner')?.classList.remove('is-hover'));

            if (!IS_TOUCH){
                const initTs = new Date().getTime();
                marker._thumbSrc = o.carpeta ? `IMG/fotos-obras/${dirFotos}/${o.carpeta}/1.thumb.webp?v=${initTs}` : null;
                const pill = estadoToPill(estado);
                
                let fragHTML = imgFragmentFor(segmento, o.carpeta, nombre);
                if (fragHTML && fragHTML.includes('1.thumb.webp')) {
                    fragHTML = fragHTML.replace(/1\.thumb\.webp/g, `1.thumb.webp?v=${initTs}`);
                }
                const ghostHTML = `<div class="ghost-card">${fragHTML}<div class="ghost-card__body"><div class="ghost-card__kicker">Obra <span class="pill ${pill.cls}"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4Z"/></svg> ${pill.txt}</span></div><div class="ghost-card__title">${nombre}</div><div class="ghost-card__meta">${monto}</div><div class="ghost-card__divider"></div><div class="meta-row">${(o.distrito||'-')} · ${(o.provincia||'-')}</div></div></div>`;
                marker.bindTooltip(ghostHTML, { direction: 'top', sticky: false, className: 'ghost-tip ghost-card-tip', offset: L.point(0, -12), opacity: 1 });
                marker.on('tooltipopen', (e) => {
                    const tooltipEl = e.tooltip.getElement();
                    if (tooltipEl) {
                        const imgEl = tooltipEl.querySelector('.ghost-card__img img, img');
                        if (imgEl && imgEl.src && imgEl.src.includes('1.thumb.webp')) {
                            const freshUrl = imgEl.src.split('?')[0] + '?v=' + new Date().getTime();
                            if (imgEl.src !== freshUrl) imgEl.src = freshUrl;
                        }
                    }
                });
            }
        });
        });

        __LABELS_UNLOCKED = false;
        LABEL_MIN_ZOOM = (window.BASE_ZOOM ?? map.getZoom()) + 0.20;
        hideAllLabels(); updateLabelsVisibility();
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
            if (window.PanelObra && typeof window.PanelObra.close === 'function') {
                window.PanelObra.close();
            }

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
            pins.clearLayers();
            updateHud('Inicio');
            updateLegendVisibility('base');

            // Simular "recarga" ocultando y volviendo a mostrar la UI con su delay
            const dock = getEl('filtersDock');
            if (chips) { chips.style.opacity = '0'; chips.style.visibility = 'hidden'; }
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
        pins.clearLayers();
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
            const w = probe.naturalWidth, h = probe.naturalHeight;
            const bounds = [[0,0],[h,w]];
            map.invalidateSize();
            const fitZ = map.getBoundsZoom(bounds, true), startZ = fitZ - stepsBack();
            const [gFx, gFy] = FOCUS[key] || [0.5, 0.5];

            // Sincronizamos el zoom de todos los mapas basándonos en 'educacion'
            if (GLOBAL_ZOOM === null || key === 'educacion') {
                GLOBAL_ZOOM = startZ;
            }
            const z0 = GLOBAL_ZOOM;

            // INICIALIZACIÓN: Leaflet necesita un setView inicial para procesar capas.
            // 1. Si venimos de Inicio (isFromBase), lo hacemos siempre para 'despertar' el mapa.
            // 2. Si no es el inicio, solo centramos si no hay una búsqueda activa (!isAutoCenterBlocked).
            if (isFromBase || !isAutoCenterBlocked) {
                map.setView([h * gFy, w * gFx], z0, { animate:false });
            }
            window.BASE_ZOOM = z0; __LABELS_UNLOCKED = false; LABEL_MIN_ZOOM = z0 + 0.20;
            map.setMinZoom(z0 - 0.5); map.setMaxZoom(fitZ + 6); map.setMaxBounds(padBounds(bounds, 1.0));
            
            const next = L.imageOverlay(url, bounds, { opacity: 0, zIndex: 2 });
            next.addTo(map);
            
            const prev = currentOverlay;
            const onLoaded = () => {
                // Si el usuario ya cambió a otro segmento o volvió a Inicio mientras cargaba la imagen
                if (currentKey !== key || currentKey === 'base') {
                    if (next) map.removeLayer(next);
                    isSwapping = false;
                    return;
                }

                next.off('load', onLoaded); clearTimeout(overlayWatchdog);
                // EVITAR LAG: No re-centrar en el onLoaded si hay una búsqueda activa.
                // La cámara de búsqueda (flyTo) ya está o estará en movimiento.
                if (!isAutoCenterBlocked) {
                    map.setView([h * gFy, w * gFx], z0, { animate:false });
                }
                
                const elNext = next.getElement();
                target.querySelectorAll('.chips, .fp, #resultsDock').forEach(el => el.style.visibility = 'visible');
                if (elNext) void elNext.offsetWidth;
                next.setOpacity(1); if (prev) prev.setOpacity(0);
                if (elNext) {
                    let transitionDone = false;
                    const cleanupPrev = () => { if (!transitionDone) { transitionDone = true; if (prev) map.removeLayer(prev); } };
                    elNext.addEventListener('transitionend', cleanupPrev, { once:true });
                    setTimeout(cleanupPrev, 1200); // Failsafe por si el navegador omite el evento
                }
                else if (prev) map.removeLayer(prev);
                
                currentOverlay = next; currentBounds = bounds;
                
                updateHud(key); relayoutSoon(); updateLegendVisibility(key);
                isSwapping = false; if (pendingKey){ const k = pendingKey; pendingKey = null; swapSegment(k); }
            };
            next.on('error', () => { 
                clearTimeout(overlayWatchdog); isSwapping = false; 
                map.removeLayer(next); 
                if (pendingKey) swapSegment(pendingKey); 
            });
            next.once('load', onLoaded); next.addTo(map);
            const elMaybe = next.getElement();
            if (elMaybe && (elMaybe.complete || elMaybe.naturalWidth > 0)) setTimeout(onLoaded, 0);
            var overlayWatchdog = setTimeout(() => { onLoaded(); }, 400);

            (async ()=>{
                try{ 
                    if (currentKey === 'base') return;
                    pins.clearLayers(); 
                    if (currentKey !== key) return; // Cancelar si el usuario navegó a otro lado
                    await cargarPinesDesdeSheet(key, h, w);
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
            // Esperamos con un polling más rápido y chequeo de isSwapping
            await new Promise(res=>{ const t = setInterval(()=>{ if (!isSwapping && !PINS_LOADING.has(seg) && currentKey === seg){ clearInterval(t); res(); } }, 50); });
        }
        let m = window.__OBRA_MARKERS.get(key), d = window.__OBRA_DATA.get(key);
        if (!m && !d){
            for (const s of Object.keys(window.SHEETS)){
                if ((window.SHEET_CACHE[s] || []).some(o => _obraKey(o) === key)){ seg = s; break; }
            }
            if (seg && currentKey !== seg){
                isAutoCenterBlocked = true;
                const chip = target.querySelector(`.chip[data-map="${seg}"]`);
                chip ? chip.click() : swapSegment(seg);
                await new Promise(res=>{ const t = setInterval(()=>{ if (!isSwapping && !PINS_LOADING.has(seg) && currentKey === seg){ clearInterval(t); res(); } }, 50); });
                m = window.__OBRA_MARKERS.get(key); d = window.__OBRA_DATA.get(key);
            }
        }

        // Transición de cámara fluida
        const flyOptions = { duration: 1.2, easeLinearity: 0.25 };
        if (m || d) {
            const targetLoc = m ? m.getLatLng() : [d.lat, d.lng];
            // Iniciamos el vuelo de cámara
            map.flyTo(targetLoc, Math.max(map.getZoom(), RESULT_ZOOM), flyOptions);
            
            // Solo liberamos el centrado automático cuando el vuelo termina
            map.once('moveend', () => {
                if (m) m.fire('click');
                isAutoCenterBlocked = false;
            });
            // Seguridad: Si por algún motivo moveend no dispara, liberamos en 2s
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
    window._mapResizeHandler = () => { if (map) map.invalidateSize(); };
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

        // Forzar play del video
        const v = document.getElementById('introVideo');
        if (v) {
            v.muted = true; // El audio debe estar silenciado para autoplay
            v.loop = true;  // Aseguramos el bucle infinito por código
            v.play().catch(e => console.log("Esperando interacción..."));
        }

        map.invalidateSize();
        // Ya no arrancamos swapSegment('base') de inmediato

        // Inicializar menús de filtros inmediatamente aunque estemos en base
        buildFilterOptions();
        attachFilterEvents();

        // Sincronización de entrada: Menú -> Texto
        setTimeout(() => {
            revealUI(); // El menú aparece primero
            
            // El texto central inicia 1.2 segundos después del menú
            setTimeout(initGooeyText, 1200); 
        }, 2000); 
    }, 500);

    // Función de limpieza obligatoria para Barba.js
    window.obrasCleanup = () => {
        if (window.leafletMapInstance) {
            window.leafletMapInstance.remove();
            window.leafletMapInstance = null;
        }
        document.querySelector('.app-bg')?.classList.remove('show');
    };
}