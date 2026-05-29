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

    // =================================================================================
    // INICIALIZADOR DINÁMICO DE SEGMENTOS
    // =================================================================================
    if (typeof window.initGlobalConfig === 'function') {
        await window.initGlobalConfig();
    }

    // =================================================================================
    // AUTO-GENERACIÓN DE BOTONES (Dinámico desde Excel)
    // =================================================================================
    const chipsContainer = target.querySelector('.chips-group') || target.querySelector('.chips');
    if (chipsContainer) {
        // 1. Limpiamos SOLO los botones de segmentos viejos (respetando Inicio y el Buscador)
        chipsContainer.querySelectorAll('.chip[data-map]').forEach(chip => {
            if (chip.getAttribute('data-map') !== 'base') chip.remove();
        });
        
        // 2. Insertamos los botones dinámicos que vienen desde Excel
        (window.SEGMENTOS_DATA || []).forEach(seg => {
            const btn = document.createElement('button');
            btn.className = 'chip';
            btn.setAttribute('data-map', seg.id);
            btn.innerHTML = `<span>${seg.nombre}</span>`;
            chipsContainer.appendChild(btn);
        });
    }

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
        if (bg) {
            bg.classList.add("show");
            
            // INYECCIÓN FÍSICA DEL PATRÓN
            if (!bg.querySelector('.map-pattern-layer')) {
                const pattern = document.createElement('div');
                pattern.className = 'map-pattern-layer';
                
                // 3 rutas de respaldo por seguridad
                const paths = "url('assets/img/pattern.svg'), url('../img/pattern.svg'), url('/fuerza_tacna/assets/img/pattern.svg')";
                
                pattern.style.cssText = `
                    position: absolute;
                    top: 0; left: 0; width: 100%; height: 100%;
                    background-image: ${paths};
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    opacity: 0.35; /* <-- Bájalo a 0.15 si lo ves muy fuerte */
                    mix-blend-mode: multiply; /* <-- CRUCIAL: Fuerza a que el naranja contraste con el amarillo */
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
        pulsePhase = (pulsePhase + 0.02) % 1; // Velocidad del latido
        
        // Efecto "ease-out" para que la onda empiece fuerte y frene al desaparecer
        const easedPhase = 1 - Math.pow(1 - pulsePhase, 3);
        
        map.setPaintProperty('obras-pulse-layer', 'circle-radius', [
            'case', ['==', ['get', 'id'], currentHoverKey],
            12 + (easedPhase * 25), // El radio viaja hacia afuera exactamente desde el borde del pin grande
            0
        ]);
        map.setPaintProperty('obras-pulse-layer', 'circle-opacity', [
            'case', ['==', ['get', 'id'], currentHoverKey],
            (1 - pulsePhase) * 0.7, // Se difumina de 70% de opacidad a 0%
            0
        ]);
        
        map.triggerRepaint(); // Forzar el renderizado a 60 FPS

        if (isPulsing) requestAnimationFrame(renderPulse);
    }

    const clearHoverState = () => {
        if (currentHoverKey !== null) {
            currentHoverKey = null;
            if (map.getLayer('obras-layer')) {
                map.setPaintProperty('obras-layer', 'circle-radius', 8); // Restaura tamaño original
            }
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
            
            if (currentHoverKey === key) return;
            
            clearTimeout(hoverIntentTimer);
            
            if (currentHoverKey !== null) {
                map.setFeatureState({ source: 'obras-source', id: currentHoverKey }, { hover: false });
            }
            
            currentHoverKey = key;
            map.setFeatureState({ source: 'obras-source', id: key }, { hover: true });

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
                id: k, // FIX: Obligatorio para utilizar el Motor "Feature-State"
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
                'circle-radius': 8,
                'circle-radius-transition': { duration: 350 }, // Crecimiento magnético mucho más lento
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

            const kpiGrid = target.querySelector('#intro-kpi-grid') || document.getElementById('intro-kpi-grid');
            if (kpiGrid) {
                kpiGrid.classList.remove('is-visible', 'is-centered');
            }

            setTimeout(revealUI, 1500);      // Los chips y el footer aparecen a los 1.5s
            setTimeout(initGooeyText, 3500); // El texto inicia DESPUÉS del menú a los 3.5s
            return;
        }

        if (isSwapping){ pendingKey = key; return; }
        
        // BUGFIX: Capturamos el estado anterior antes de actualizar currentKey
        const prevKey = currentKey;
        isSwapping = true; 
        currentKey = key; // Definimos el intento de navegación inmediatamente
        setBackgroundFor(key);

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