/* =========================================================
   MAPA-FILTROS.JS - Lógica de filtrado y Dock
   ========================================================= */

// --- 1. ESTADO GLOBAL ---
const FILTERS = {
    provincia: '',
    distrito: '',
    estados:  new Set(),
    segments: new Set(),
    distritos: new Set(),
    provincias: new Set(),
};
const SHOW_COUNTERS = true; 
let filtersBuilt = false; // Flag para asegurar que los filtros se construyan solo una vez

// --- 2. HELPERS DE UI ---
function _btnLabelFromSet(set, singular = 'elem.', plural = 'elem.') {
    const n = set?.size ?? 0;
    return n === 1 ? `1 ${singular}` : `${n} ${plural}`;
}

function toggleDisclosure($menu, $icon) {
    if (!$menu) return;
    const isHidden = $menu.hidden;
    $menu.hidden = !isHidden;
    if ($icon) $icon.classList.toggle('is-rotated', isHidden);
    $menu.classList.toggle('is-open', isHidden);
}

function markDirty() {
    const b = document.getElementById('fApply');
    if (b) b.disabled = false;
}

function resetDirty() {
    const b = document.getElementById('fApply');
    if (b) b.disabled = true;
}

// --- 3. LÓGICA DE DOCKS (POSICIÓN Y VISIBILIDAD) ---
function placeResultsDock() {
    const dock = document.getElementById('filtersDock');
    const res = document.getElementById('resultsDock');
    if (!dock || !res) return;
}

function syncResultsWithFilters() {
    const dock = document.getElementById('filtersDock');
    const results = document.getElementById('resultsDock');
    if (!dock || !results) return;
    const apply = () => {
        const isCollapsed = dock.classList.contains('is-collapsed');
        if (isCollapsed) {
            results.classList.add('is-hidden');
        } else {
            const count = document.getElementById('rdCount');
            const hasItems = count && count.textContent && parseInt(count.textContent) > 0;
            if (hasItems) results.classList.remove('is-hidden');
        }
    };
    const observer = new MutationObserver(apply);
    observer.observe(dock, { attributes: true, attributeFilter: ['class'] });
    apply();
}

let currentDrawerContentId = 'fpPanel'; // Por defecto, el panel de filtros
let closeTimeout = null;

window.openDock = async function(contentId = 'fpPanel', headerText = 'Filtrar Obras') {
    const dock = document.getElementById('filtersDock');
    const drawerHeader = document.getElementById('fpDrawerHeader');
    if (!dock || !drawerHeader) return;

    if (closeTimeout) { clearTimeout(closeTimeout); closeTimeout = null; }

    const isOpening = dock.classList.contains('is-collapsed');

    dock.classList.remove('is-collapsed');
    dock.setAttribute('aria-expanded', 'true');
    
    // Si es la primera vez que abrimos filtros, empezamos la carga YA
    if (contentId === 'fpPanel' && !filtersBuilt) {
        buildFilterOptions().then(() => { filtersBuilt = true; });
    }

    showDrawerContent(contentId, headerText, isOpening);
};

window.closeDock = function() {
    const dock = document.getElementById('filtersDock');
    const drawerContentPanels = document.querySelectorAll('.fp-drawer-content-wrapper > div');
    if (!dock || !drawerContentPanels) return;

        dock.classList.add('is-collapsed');
        dock.setAttribute('aria-expanded', 'false');
        placeResultsDock();
        drawerContentPanels.forEach(p => p.classList.remove('is-active'));
    
    if (closeTimeout) clearTimeout(closeTimeout);
    closeTimeout = setTimeout(() => {
        if(dock.classList.contains('is-collapsed')) {
            drawerContentPanels.forEach(panel => panel.hidden = true);
            // Limpia todos los filtros y resultados silenciosamente después de cerrar
            clearFilters();
        }
        closeTimeout = null;
    }, 300);
};

// --- 4. LÓGICA DE FILTRADO Y DATOS ---
async function _getUniverseData() {
    const segs = FILTERS.segments.size ? [...FILTERS.segments] : Object.keys(SHEETS).filter(s => s !== 'base' && SHEETS[s]);
    const allData = [];
    for (const seg of segs) {
        if (SHEETS[seg]) {
            if (!window.SHEET_CACHE[seg]) {
                const json = await fetchSheetGVizJSON(SHEET_ID, { sheetName: SHEETS[seg] });
                window.SHEET_CACHE[seg] = gvizToObjects(json);
            }
            allData.push(...(window.SHEET_CACHE[seg] || []).map(o => ({ ...o, seg })));
        }
    }
    return allData;
}

function _estadoKey(estado = '') {
    const e = norm(estado);
    const mapping = {
        'entreg': 'entregado',
        'constru': 'constru',
        'paraliz': 'paraliz',
        'buena pro': 'buena pro',
        'transfer': 'transfer'
    };

    const found = Object.keys(mapping).find(key => e.includes(key));
    return found ? mapping[found] : 'estudio';
}

function pasaFiltro(o) {
    if (FILTERS.provincias.size > 0 && !FILTERS.provincias.has((o.provincia || '').trim())) return false;
    if (FILTERS.distritos.size > 0 && !FILTERS.distritos.has((o.distrito || '').trim())) return false;
    if (FILTERS.estados.size > 0 && !FILTERS.estados.has(_estadoKey(o.estado))) return false;
    return true;
}

async function applyFilters() {
    const data = await _getUniverseData();
    const items = data.filter(pasaFiltro).map(o => ({
        seg: o.seg || currentKey,
        key: _obraKey(o),
        nombre: o.nombre,
        meta: [o.distrito, o.provincia].filter(Boolean).join(' · '),
        estadoRaw: o.estado
    }));
    renderResults(items);
}

// --- 5. RENDERIZADO DE RESULTADOS ---
function renderResults(items) {
    const ul = document.getElementById('resultsList');
    const rdlist = document.getElementById('resultsDockList');
    const count = document.getElementById('rdCount');
    const dock = document.getElementById('resultsDock');

    const itemHTML = items.map(it => `
        <li onclick="gotoObra('${it.key}', '${it.seg}')">
            <div class="title">${safe(it.nombre)}</div>
            <div class="meta">${safe(it.meta)}</div>
            <div class="footer">
                ${estadoPillHTML(it)}
                <span class="pill pill--tag">${it.seg.toUpperCase()}</span>
            </div>
        </li>
    `).join('');

    if (ul) {
        ul.innerHTML = itemHTML;
        ul.hidden = !items.length;
    }

    if (rdlist) {
        rdlist.innerHTML = itemHTML;
    }

    if (count) {
        count.textContent = items.length ? `${items.length}` : '';
    }

    if (dock) {
        if (items.length) {
            dock.hidden = false;
            requestAnimationFrame(() => dock.classList.remove('is-hidden'));
        } else {
            dock.classList.add('is-hidden');
        }
    }
}

function estadoPillHTML(it) {
    const info = estadoToPill(it.estadoRaw || '');
    return `<span class="pill ${info.cls}">${info.txt}</span>`;
}

/**
 * Parte 1: Renderizado del Historial
 * Obtiene los datos de localStorage y los dibuja en el panel lateral.
 */
function renderHistoryList() {
    const list = document.getElementById('fpHistoryList');
    if (!list) return;

    const history = getHistory(); // Función definida en mapa-core.js
    if (!history.length) {
        list.innerHTML = '<li style="padding:40px 20px; color:#999; text-align:center; font-size:13px; font-weight:700">No has visitado ninguna obra todavía.</li>';
        return;
    }

    list.innerHTML = history.map(it => `
        <li onclick="gotoObra('${it.key}', '${it.seg}')">
            <div class="title">${safe(it.nombre)}</div>
            <div class="meta">${safe([it.distrito, it.provincia].filter(Boolean).join(' · '))}</div>
            <div class="footer">
                ${estadoPillHTML(it)}
                <span class="pill pill--tag">${it.seg.toUpperCase()}</span>
            </div>
        </li>
    `).join('');
}

function clearHistory() {
    if (confirm('¿Deseas limpiar tu historial de obras visitadas?')) {
        localStorage.removeItem('obraHistory');
        renderHistoryList();
    }
}

// --- Función para alternar entre paneles de contenido del cajón ---
function showDrawerContent(contentId, headerText, immediate = false) {
    const drawerHeader = document.getElementById('fpDrawerHeader');
    const contentPanels = document.querySelectorAll('.fp-drawer-content-wrapper > div');
    
    if (drawerHeader) drawerHeader.textContent = headerText;

    contentPanels.forEach(panel => {
        if (panel.id === contentId) {
            // PANEL OBJETIVO: Lo hacemos visible inmediatamente
            panel.hidden = false;
            if (immediate) {
                panel.classList.add('is-active');
            } else {
                // Si ya estaba abierto, aseguramos que empiece de 0 para la animación
                panel.classList.remove('is-active');
                void panel.offsetWidth; // Forzar reflujo del navegador
                requestAnimationFrame(() => {
                    panel.classList.add('is-active');
                });
            }
        } else {
            // PANELES QUE NO SON EL OBJETIVO: Ocultar al instante
            panel.classList.remove('is-active');
            panel.hidden = true;
        }
    });

    currentDrawerContentId = contentId;
    if (contentId === 'fpHistoryPanel') renderHistoryList(); // Renderiza el historial al mostrarlo
}

// --- 6. CONSTRUCTORES DE MENÚS ---
async function buildSegmentMenu() {
    const $menu = document.getElementById('fSegMenu');
    if (!$menu) return;

    const $btnLabel = document.getElementById('fSegBtnLabel');
    const setBtnText = () => {
        if ($btnLabel) $btnLabel.textContent = SHOW_COUNTERS ? _btnLabelFromSet(FILTERS.segments, 'categ.', 'categ.') : '—';
    };

    const segs = Object.keys(SHEETS).filter(s => s !== 'base' && SHEETS[s]);
    $menu.innerHTML = [
        `<label><input type="checkbox" data-role="all"> Seleccionar todo</label>`,
        ...segs.map(s => `<label><input type="checkbox" value="${s}"> ${s.charAt(0).toUpperCase() + s.slice(1)}</label>`)
    ].join('');

    const all = $menu.querySelector('input[data-role="all"]');
    const items = [...$menu.querySelectorAll('input[type="checkbox"]:not([data-role="all"])')];

    items.forEach(ch => ch.checked = FILTERS.segments.has(ch.value));
    all.checked = items.length > 0 && items.every(i => i.checked);
    setBtnText();

    all.addEventListener('change', () => {
        FILTERS.segments = all.checked ? new Set(items.map(i => i.value)) : new Set();
        items.forEach(ch => ch.checked = all.checked);
        setBtnText();
        markDirty();
        buildProvinciaMenu();
        buildDistritoMenu();
    });

    items.forEach(ch => {
        ch.addEventListener('change', () => {
            ch.checked ? FILTERS.segments.add(ch.value) : FILTERS.segments.delete(ch.value);
            all.checked = items.length > 0 && items.every(i => i.checked);
            setBtnText();
            markDirty();
            buildProvinciaMenu();
            buildDistritoMenu();
        });
    });
}

async function buildProvinciaMenu() {
    const $menu = document.getElementById('fProvMenu');
    if (!$menu) return;

    const $btnLabel = document.getElementById('fProvBtnLabel');
    const setBtnText = () => {
        if ($btnLabel) $btnLabel.textContent = SHOW_COUNTERS ? _btnLabelFromSet(FILTERS.provincias, 'prov.', 'prov.') : '—';
    };

    const data = await _getUniverseData();
    const provs = [...new Set(data.map(o => (o.provincia || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));

    $menu.innerHTML = [
        `<label><input type="checkbox" data-role="all"> Seleccionar todo</label>`,
        ...provs.map(p => `<label><input type="checkbox" value="${p}"> ${p}</label>`)
    ].join('');

    const all = $menu.querySelector('input[data-role="all"]');
    const items = [...$menu.querySelectorAll('input[type="checkbox"]:not([data-role="all"])')];

    items.forEach(ch => ch.checked = FILTERS.provincias.has(ch.value));
    all.checked = items.length > 0 && items.every(i => i.checked);
    setBtnText();

    all.addEventListener('change', () => {
        FILTERS.provincias = all.checked ? new Set(items.map(i => i.value)) : new Set();
        items.forEach(ch => ch.checked = all.checked);
        setBtnText();
        buildDistritoMenu();
        markDirty();
    });

    items.forEach(ch => {
        ch.addEventListener('change', () => {
            ch.checked ? FILTERS.provincias.add(ch.value) : FILTERS.provincias.delete(ch.value);
            all.checked = items.length > 0 && items.every(i => i.checked);
            setBtnText();
            buildDistritoMenu();
            markDirty();
        });
    });
}

async function buildDistritoMenu() {
    const $menu = document.getElementById('fDistMenu');
    const $btn = document.getElementById('fDistBtn');
    if (!$menu || !$btn) return;

    const $lbl = document.getElementById('fDistBtnLabel');
    const $all = document.getElementById('fDistAll');
    const $listBox = document.getElementById('fDistList');
    if (!$all || !$listBox) return;

    const data = await _getUniverseData();
    const provOK = (p) => (FILTERS.provincias.size === 0) || FILTERS.provincias.has((p || '').trim());

    const dists = [...new Set(
        data.filter(o => provOK(o.provincia))
            .map(o => (o.distrito || '').trim())
            .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, 'es'));

    $btn.disabled = !dists.length;
    $listBox.innerHTML = dists.map(d => `<label><input type="checkbox" value="${d}"> ${d}</label>`).join('');
    const items = [...$listBox.querySelectorAll('input[type="checkbox"]')];

    items.forEach(ch => ch.checked = FILTERS.distritos.has(ch.value));
    $all.checked = items.length > 0 && items.every(ch => ch.checked);
    if ($lbl) $lbl.textContent = SHOW_COUNTERS ? _btnLabelFromSet(FILTERS.distritos, 'dist.', 'dist.') : '—';

    $all.onchange = () => {
        FILTERS.distritos = $all.checked ? new Set(items.map(i => i.value)) : new Set();
        items.forEach(ch => ch.checked = $all.checked);
        if ($lbl) $lbl.textContent = _btnLabelFromSet(FILTERS.distritos, 'dist.', 'dist.');
        markDirty();
    };

    items.forEach(ch => {
        ch.onchange = () => {
            ch.checked ? FILTERS.distritos.add(ch.value) : FILTERS.distritos.delete(ch.value);
            $all.checked = items.length > 0 && items.every(i => i.checked);
            if ($lbl) $lbl.textContent = _btnLabelFromSet(FILTERS.distritos, 'dist.', 'dist.');
            markDirty();
        };
    });
}

async function buildEstadoMenu() {
    const $menu = document.getElementById('fEstadoMenu');
    if (!$menu) return;

    const $lbl = document.getElementById('fEstadoBtnLabel');
    const estados = [
        { value: 'entregado', label: 'Entregada' },
        { value: 'constru', label: 'En construcción' },
        { value: 'paraliz', label: 'Paralizada' },
        { value: 'buena pro', label: 'Buena Pro' },
        { value: 'transfer', label: 'Transferencia' },
        { value: 'estudio', label: 'En estudios' }
    ];

    $menu.innerHTML = estados.map(e => `<label><input type="checkbox" value="${e.value}"> ${e.label}</label>`).join('');
    const items = [...$menu.querySelectorAll('input[type="checkbox"]')];

    items.forEach(ch => ch.checked = FILTERS.estados.has(ch.value));
    if ($lbl) $lbl.textContent = SHOW_COUNTERS ? _btnLabelFromSet(FILTERS.estados, 'sel.', 'sel.') : '—';

    items.forEach(ch => {
        ch.onchange = () => {
            ch.checked ? FILTERS.estados.add(ch.value) : FILTERS.estados.delete(ch.value);
            if ($lbl) $lbl.textContent = SHOW_COUNTERS ? _btnLabelFromSet(FILTERS.estados, 'seleccionado', 'seleccionados') : '—';
            markDirty();
        };
    });
}

window.buildFilterOptions = async function() {
    await buildSegmentMenu();
    await buildProvinciaMenu();
    await buildDistritoMenu();
    await buildEstadoMenu();
    resetDirty();
};

// --- 7. MANEJO DE EVENTOS ---
window.attachFilterEvents = function() {
    document.getElementById('fApply')?.addEventListener('click', () => {
        applyFilters();
        resetDirty();
    });

    document.getElementById('fClear')?.addEventListener('click', clearFilters);

    document.getElementById('fClearHistory')?.addEventListener('click', clearHistory);

    // Adjuntar escuchadores de clic a toda el área de cada `.f-field`
    const filterFields = document.querySelectorAll('#fpPanel .f-field'); // Seleccionamos todos los campos de filtro

    filterFields.forEach(field => {
        const button = field.querySelector('.f-chip'); // El botón original que contiene el icono
        
        // Derivamos el ID del menú a partir del ID del botón (ej: fSegBtn -> fSegMenu)
        const menuId = button ? button.id.replace('Btn', 'Menu') : null;
        const menu = menuId ? document.getElementById(menuId) : null;
        const icon = button ? button.querySelector('.f-chip__icon') : null;

        if (menu && icon) {
            // Hacemos que toda la caja cambie el cursor a la "manito"
            field.style.cursor = 'pointer';
            
            // Adjuntamos el evento de clic a todo el `div.f-field`
            field.addEventListener('click', (e) => {
                // IMPORTANTE: Evitar que el acordeón se cierre si el usuario hace clic en los checkboxes de adentro
                if (e.target.closest('.f-menu')) return;
                console.log('mapa-filtros.js: e.stopPropagation() llamado en un campo de filtro. Target:', e.target, 'Tipo de evento:', e.type);
                e.stopPropagation(); // Evita que el clic se propague al documento y cierre el dock
                // Nota: Este stopPropagation ocurre en la fase de burbujeo.
                // El listener del onboarding hint está en la fase de captura, por lo que debería ejecutarse ANTES.
                toggleDisclosure(menu, icon);
            });
        }
    });
};

function clearFilters() {
    FILTERS.provincias.clear();
    FILTERS.distritos.clear();
    FILTERS.estados.clear();
    FILTERS.segments.clear();

    document.querySelectorAll('#fpPanel input[type="checkbox"]').forEach(i => i.checked = false);
    
    // Cerrar todos los submenús (acordeones) abiertos
    document.querySelectorAll('#fpPanel .f-menu.is-open').forEach(menu => {
        menu.classList.remove('is-open');
        menu.hidden = true;
    });
    // Restaurar todos los iconos a su estado original (no rotados)
    document.querySelectorAll('#fpPanel .f-chip__icon').forEach(icon => {
        icon.classList.remove('is-rotated');
    });

    buildFilterOptions();
    renderResults([]);
    resetDirty();
}

// --- 8. INICIALIZACIÓN ---
function initFilters() {
    const pill = document.getElementById('fpPill');
    const dock = document.getElementById('filtersDock');

    // Paso clave: Asegurar que el panel nazca colapsado si el HTML no lo tiene puesto.
    // Esto evita que la franja blanca tape el mapa en móviles al cargar.
    if (dock && !dock.classList.contains('is-collapsed')) {
        dock.classList.add('is-collapsed');
    }

    const historyBtn = document.getElementById('fpHistoryBtn');

    if (dock) {
        // Evento para el botón "Filtrar"
        if (pill) pill.onclick = async (e) => {
            e.preventDefault();
            const expanded = dock.getAttribute('aria-expanded') === 'true';
            // Si está abierto y ya estamos en Filtros, lo cerramos. 
            // De lo contrario (cerrado o en otra sección), abrimos Filtros.
            if (expanded && currentDrawerContentId === 'fpPanel') {
                closeDock();
            } else {
                await openDock('fpPanel', 'Filtrar Obras');
            }
        };

        // Evento para el botón "Historial"
        if (historyBtn) historyBtn.onclick = async (e) => {
            e.preventDefault();
            const expanded = dock.getAttribute('aria-expanded') === 'true';
            // Si está abierto y ya estamos en Historial, lo cerramos.
            if (expanded && currentDrawerContentId === 'fpHistoryPanel') {
                closeDock();
            } else {
                await openDock('fpHistoryPanel', 'Visitados Recientemente');
            }
        };
    }

    // Cerrar automáticamente al hacer clic fuera del panel
    document.addEventListener('click', (e) => {
        const dockEl = document.getElementById('filtersDock');
        if (dockEl && dockEl.contains(e.target)) return;
        if (dockEl && dockEl.getAttribute('aria-expanded') === 'true') {
            closeDock();
        }
    });

    attachFilterEvents();
    syncResultsWithFilters();
    window.addEventListener('resize', placeResultsDock);
}