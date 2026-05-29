const elInput = document.getElementById('obraSearchInput');
const elList  = document.getElementById('obraSearchList');

function renderList(items) {
    if (!items.length) { elList.hidden = true; elList.innerHTML = ''; return; }
    elList.hidden = false;
    elList.innerHTML = items.map(it => {
        const segName = typeof window.getSegmentName === 'function' ? window.getSegmentName(it.seg) : it.seg;
        return `
        <li data-key="${it._k}" data-seg="${it.seg}" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div>${it.nombre}</div>
                <small>${[it.distrito, it.provincia].filter(Boolean).join(' · ') || '&nbsp;'}</small>
            </div>
            <span class="seg-tag">${segName.toUpperCase()}</span>
        </li>
    `}).join('');

    elList.querySelectorAll('li').forEach(li => {
        li.onclick = async () => {
            elList.hidden = true; elInput.value = ''; elInput.blur();
            if (typeof window.gotoObra === 'function') {
                await window.gotoObra(li.dataset.key, li.dataset.seg);
            }
        };
    });
}

const onType = debounce(() => {
    const q = norm(elInput.value);
    if (q.length < 2) { renderList([]); return; }
    const res = [];
    // Busca en TODAS las hojas cargadas en memoria
    for (const segKey in window.SHEET_CACHE) {
        const base = window.SHEET_CACHE[segKey] || [];
        for (const o of base) {
            const blob = norm(`${o.nombre} ${o.distrito} ${o.provincia}`);
            if (blob.includes(q)) {
                const k = _obraKey(o);
                if (!res.find(r => r._k === k)) {
                    res.push({ _k: k, nombre: o.nombre, distrito: o.distrito, provincia: o.provincia, seg: segKey });
                }
            }
            if (res.length >= 12) break;
        }
        if (res.length >= 12) break;
    }
    renderList(res);
});

elInput.oninput = onType;
elInput.onfocus = () => { if (elList.innerHTML) elList.hidden = false; };
document.addEventListener('click', e => { if (!e.target.closest('#obraSearch')) elList.hidden = true; });