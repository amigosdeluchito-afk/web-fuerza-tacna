// Helpers de seguridad y formato

// --- CONSTANTES GLOBALES DEL MAPA (Centralizadas aquí para asegurar disponibilidad temprana) ---
window.SHEET_ID = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';
window.SHEETS = { 
    base: null,
    educacion: 'EDUCACIÓN',
    vias: 'VÍAS',
    agua: 'AGUA',
    transporte: 'TRANSPORTE',
    agricultura: 'AGRICULTURA',
    social: 'SOCIAL'
};
window.FOTOS_DIR = {
    educacion: 'EDUCACIÓN',
    vias: 'VÍAS',
    agua: 'AGUA',
    transporte: 'TRANSPORTE',
    agricultura: 'AGRICULTURA',
    social: 'SOCIAL'
};

window.getSegmentName = function(segId) {
    return segId;
};

// --- FIN CONSTANTES GLOBALES ---

window.safe = s => String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[m]));

window.norm = s => String(s || '')
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .trim();

window.debounce = (fn, ms = 180) => {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
};

window.twoLineName = raw => {
    const txt = String(raw || '').trim();
    const words = txt.split(/\s+/);
    if (words.length <= 2) return window.safe(txt);
    const mid = Math.ceil(words.length / 2);
    return `${window.safe(words.slice(0, mid).join(' '))}<br>${window.safe(words.slice(mid).join(' '))}`;
};

window.parseMoney = s => Number(String(s || '').replace(/[^\d.-]/g, '')) || 0;

window.obraScore = o => {
    const base = window.parseMoney(o.monto);
    const e = String(o.estado || '').toLowerCase();
    const bonus = (e.includes('constru')) ? 50000 : (e.includes('entregado')) ? 20000 : 0;
    return base + bonus + Number(o.peso || 0) * 10000;
};

window.rectsChocan = (a, b, pad = 0) => {
    return !(a.right + pad < b.left - pad || a.left - pad > b.right + pad || a.bottom + pad < b.top - pad || a.top - pad > b.bottom + pad);
};

window.slugify = s => String(s || '')
    .toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

// Conexión con Google Sheets (GViz)
window.fetchSheetGVizJSON = async function(sheetId, opts) {
    const qs = opts?.gid ? `gid=${encodeURIComponent(opts.gid)}` : `sheet=${encodeURIComponent(opts.sheetName)}`;
    const url = `https://docs.google.com/spreadsheets/d/${sheetId}/gviz/tq?tqx=out:json&${qs}`;
    const res = await fetch(url, { cache: 'no-store' });
    const raw = await res.text();
    const marker = 'setResponse(';
    const i = raw.indexOf(marker);
    if (i === -1) throw new Error('GViz wrapper not found');
    const j = raw.lastIndexOf(');');
    const jsonText = raw.slice(i + marker.length, j);
    return JSON.parse(jsonText);
};

window.gvizToObjects = function(json) {
    const n = s => norm(s).replace(/\s+/g, '').replace(/[._-]+/g, '');
    const ALIAS = {
        nombre: ['nombre', 'obra', 'proyecto', 'denominacion', 'titulo'],
        estado: ['estado', 'situacion', 'estatus'],
        monto: ['monto', 'costo', 'presupuesto', 'inversion', 'importe'],
        provincia: ['provincia'],
        distrito: ['distrito', 'municipio', 'localidad'],
        carpeta: ['carpeta', 'folder', 'slug'],
        descripcion: ['descripcion', 'detalle', 'resumen', 'observacion', 'descripcionresumida'],
        antes: ['antes', 'foto_antes', 'previa'],
        despues: ['despues', 'foto_despues', 'actual'],
        beneficiarios: ['beneficiarios', 'impacto', 'poblacion', 'familias', 'personas'],
        avance: ['avance', 'fisico', 'progreso', 'porcentaje', 'completado'],
        inicio: ['inicio', 'fechainicio', 'fecha_inicio'],
        fin: ['fin', 'fechafin', 'fecha_fin', 'termino'],
        empleos: ['empleos', 'trabajo', 'puestos', 'mano_de_obra'],
        video: ['video', 'url_video', 'link_video'],
        x: ['x', 'coordx', 'coordenadax', 'coordenadaenx', 'lon', 'longitud', 'lng', 'ximg', 'imagenx'],
        y: ['y', 'coordy', 'coordenaday', 'coordenadaeny', 'lat', 'latitude', 'yimg', 'imageny']
    };
    let cols = (json.table.cols || []).map(c => String(c.label || ''));
    let rows = json.table.rows || [];
    if (cols.every(h => h.trim() === '') && rows.length) {
        const headerRow = rows[0].c || [];
        cols = headerRow.map(cell => (cell && cell.v != null) ? String(cell.v) : '');
        rows = rows.slice(1);
    }
    const colMap = {};
    cols.map(n).forEach((h, i) => {
        for (const [campo, aliases] of Object.entries(ALIAS)) {
            if (aliases.includes(h)) { colMap[i] = campo; break; }
        }
    });
    return rows.map(r => {
        const c = r.c || [];
        const o = {};
        for (const [idx, campo] of Object.entries(colMap)) {
            const cell = c[idx];
            if (!cell) { o[campo] = ''; continue; }
            // Preferimos el valor formateado (.f) que incluye texto como "MILLONES" o símbolos de moneda.
            // Si no existe, usamos el valor crudo (.v).
            o[campo] = (cell.f != null) ? String(cell.f) : (cell.v != null ? String(cell.v) : '');
        }
        return o;
    }).filter(o => o.nombre);
};

window._obraKey = function(o) {
    return (o.carpeta && o.carpeta !== '-' ? o.carpeta : (o.nombre || '').trim());
};

window.colorPinPorEstado = function(estado) {
    const e = norm(estado);
    if (e.includes('entregado')) return '#2e7d32';
    if (e.includes('constru')) return '#1a73e8';
    if (e.includes('paraliz')) return '#c62828';
    if (e.includes('buena pro')) return '#ef6c00';
    if (e.includes('transfer')) return '#6d28d9';
    return '#616161';
};

window.thumbPath = function(segmento, carpetaObra) {
    const dirFotos = String(window.FOTOS_DIR[segmento] || segmento).toLowerCase();
    return `IMG/fotos-obras/${dirFotos}/${carpetaObra}/1.thumb.webp`;
};

window.estadoToPill = function(estado) {
    const e = String(estado || '').toLowerCase();
    if (e.includes('entregado')) return { cls: 'pill--entregado', txt: 'ENTREGADA' };
    if (e.includes('constru')) return { cls: 'pill--construccion', txt: 'EN CONSTRUCCIÓN' };
    if (e.includes('paraliz')) return { cls: 'pill--paralizado',   txt: 'PARALIZADA' };
    if (e.includes('buena pro')) return { cls: 'pill--buenapro',   txt: 'BUENA PRO' };
    if (e.includes('transfer')) return { cls: 'pill--transferencia', txt: 'TRANSFERENCIA' };
    return { cls: 'pill--estudios', txt: 'EN ESTUDIOS' };
};

window.imgFragmentFor = function(segmento, carpeta, alt) {
    if (!carpeta) return `<div class="ghost-card__img is-blank"></div>`;
    const src = thumbPath(segmento, carpeta);
    if (!window.__THUMB_STATUS) window.__THUMB_STATUS = new Map();
    if (!window.__MISSING_THUMBS) window.__MISSING_THUMBS = new Set();
    const known = window.__THUMB_STATUS.get(src);
    if (known === 'missing' || window.__MISSING_THUMBS.has(src)) return `<div class="ghost-card__img is-blank"></div>`;
    return `<img class="ghost-card__img" src="${src}" alt="${alt}" width="1280" height="720" decoding="async" loading="lazy" fetchpriority="low" onload="window.__THUMB_STATUS.set('${src}','ok')" onerror="this.onerror=null; window.__MISSING_THUMBS.add('${src}'); window.__THUMB_STATUS.set('${src}','missing'); this.outerHTML='<div class=\\'ghost-card__img is-blank\\'></div>';">`;
};

// --- SISTEMA DE HISTORIAL (Rescatado de mapa-core.js) ---
const HISTORY_KEY = 'obraHistory';
const MAX_HISTORY_ITEMS = 10;

window.getHistory = function() {
    try { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); }
    catch(e) { return []; }
};

window.saveHistory = function(history) {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
};

window.recordVisit = function(key, seg) {
    const data = window.__OBRA_DATA.get(key);
    if (!data || !data.o) return;
    const history = window.getHistory();
    const newItem = {
        key: key,
        seg: seg,
        nombre: data.o.nombre || '',
        distrito: data.o.distrito || '',
        provincia: data.o.provincia || '',
        estadoRaw: data.o.estado || ''
    };
    const updated = [newItem, ...history.filter(it => it.key !== key)].slice(0, MAX_HISTORY_ITEMS);
    window.saveHistory(updated);
};