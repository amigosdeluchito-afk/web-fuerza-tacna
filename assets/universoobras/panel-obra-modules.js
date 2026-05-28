/**
 * Módulos de apoyo para el Panel de Obra
 */
(function() {
    'use strict';

    // --- UTILIDADES ---
    window.PO_Utils = {
        safe: s => String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])),
        
        formatMoney: v => {
            if (v == null || v === '') return '—';
            const str = String(v).trim();
            // Si el texto ya tiene letras (como "MILLONES"), lo respetamos tal cual.
            // Quitamos el prefijo "S/" solo para la comprobación.
            const checkStr = str.replace(/^S\//i, '').trim();
            if (/[a-zA-Z]/.test(checkStr)) return str;

            const num = typeof v === 'number' ? v : Number(str.replace(/[^\d.-]/g, ''));
            if (!isFinite(num)) return str;
            if (Math.abs(num) >= 1_000_000) return `S/ ${parseFloat((num / 1_000_000).toFixed(2))} Millones`;
            if (Math.abs(num) >= 1_000) return `S/ ${parseFloat((num / 1_000).toFixed(2))} Mil`;
            return 'S/ ' + num.toLocaleString('es-PE');
        },

        estadoToPill: (estado = '') => {
            const e = String(estado).toLowerCase();
            if (e.includes('entregado')) return { cls: 'pill pill--entregado', txt: 'ENTREGADA' };
            if (e.includes('constru')) return { cls: 'pill pill--construccion', txt: 'EN CONSTRUCCIÓN' };
            if (e.includes('paraliz')) return { cls: 'pill pill--paralizado', txt: 'PARALIZADA' };
            return { cls: 'pill pill--estudios', txt: 'EN ESTUDIOS' };
        }
    };

    // --- VISOR DE FOTOS (LIGHTBOX) ---
    let _vList = [], _vIdx = 0;
    const el = (t, c) => { const n = document.createElement(t); if (c) n.className = c; return n; };

    window.PO_Viewer = {
        els: {},
        init: function() {
            if (document.getElementById('pv')) return;
            const pvBd = el('div'); pvBd.id = 'pvBackdrop';
            const pvEl = el('div'); pvEl.id = 'pv';
            const box = el('div', 'box');
            const img = el('img');
            const btnClose = el('button', 'pv-btn pv-close');
            btnClose.innerHTML = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M18.3 5.7 12 12l-6.3-6.3-1.4 1.4L10.6 13.4l-6.3 6.3 1.4 1.4L12 13.4l6.3 6.3 1.4-1.4-6.3-6.3 6.3-6.3z"/></svg>';
            const btnPrev = el('button', 'pv-btn pv-prev');
            btnPrev.innerHTML = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
            const btnNext = el('button', 'pv-btn pv-next');
            btnNext.innerHTML = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M8.59 16.59 10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg>';
            const counter = el('div', 'pv-counter');
            box.append(img, btnClose, btnPrev, btnNext, counter);
            pvEl.appendChild(box);
            document.body.append(pvBd, pvEl);
            this.els = { pvBd, pvEl, img, btnClose, btnPrev, btnNext, counter };
            btnClose.onclick = pvBd.onclick = () => this.close();
            btnPrev.onclick = () => { if (_vIdx > 0) { _vIdx--; this.update(); } };
            btnNext.onclick = () => { if (_vIdx < _vList.length - 1) { _vIdx++; this.update(); } };
        },
        open: function(list, startIdx = 0) {
            this.init();
            _vList = Array.isArray(list) ? list.slice() : [];
            _vIdx = Math.max(0, Math.min(startIdx, _vList.length - 1));
            if (!_vList.length) return;
            this.els.pvBd.classList.add('is-open');
            this.els.pvEl.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            this.update();
            window.addEventListener('keydown', this._keys);
        },
        update: function() {
            const { img, btnPrev, btnNext, counter } = this.els;
            img.src = _vList[_vIdx];
            btnPrev.disabled = (_vIdx <= 0);
            btnNext.disabled = (_vIdx >= _vList.length - 1);
            counter.textContent = `${_vIdx + 1} / ${_vList.length}`;
        },
        close: function() {
            this.els.pvBd.classList.remove('is-open');
            this.els.pvEl.classList.remove('is-open');
            document.body.style.overflow = '';
            window.removeEventListener('keydown', this._keys);
        },
        _keys: (e) => {
            if (e.key === 'Escape') window.PO_Viewer.close();
            if (e.key === 'ArrowLeft' && _vIdx > 0) { _vIdx--; window.PO_Viewer.update(); }
            if (e.key === 'ArrowRight' && _vIdx < _vList.length - 1) { _vIdx++; window.PO_Viewer.update(); }
        }
    };

    // --- NAVEGACIÓN DE CARRUSEL ---
    window.PO_Carousel = {
        setup: function(scroller, navRoot) {
            const prev = navRoot.querySelector('.prev');
            const next = navRoot.querySelector('.next');
            const update = () => {
                const max = scroller.scrollWidth - scroller.clientWidth - 1;
                prev.disabled = scroller.scrollLeft <= 2;
                next.disabled = scroller.scrollLeft >= max;
            };
            prev.onclick = () => scroller.scrollBy({ left: -220, behavior: 'smooth' });
            next.onclick = () => scroller.scrollBy({ left: 220, behavior: 'smooth' });
            scroller.onscroll = update;
            window.addEventListener('resize', update);
            update();
        }
    };

    // --- GESTOS PARA MÓVIL (BOTTOM SHEET) ---
    window.PO_Gestures = {
        enableBottomSheetDrag: function(sheetEl) {
            const hero = sheetEl.querySelector('.sheet-hero');
            const body = sheetEl.querySelector('.sheet-body');
            let startY = 0, startH = 0, curY = 0, dragging = false;

            const minH = Math.round(window.innerHeight * 0.40);
            const fullH = Math.round(window.innerHeight * 0.88);
            const peekH = Math.round(window.innerHeight * 0.55);

            const onStart = (e) => {
                if (window.innerWidth > 700) return;
                const t = e.touches ? e.touches[0] : e;
                if (body.scrollTop > 0 && t.clientY > hero.getBoundingClientRect().bottom) return;
                
                startY = curY = t.clientY;
                startH = sheetEl.classList.contains('is-full') ? fullH : sheetEl.getBoundingClientRect().height;
                dragging = true;
                sheetEl.style.transition = 'none';
            };

            const onMove = (e) => {
                if (!dragging) return;
                const t = e.touches ? e.touches[0] : e;
                curY = t.clientY;
                const dy = curY - startY;
                let newH = Math.max(minH, Math.min(fullH, startH - dy));
                sheetEl.style.height = newH + 'px';
                if (newH <= peekH * 0.9 && dy > 0) sheetEl.style.transform = `translateY(${Math.min(dy, 100)}px)`;
            };

            const onEnd = () => {
                if (!dragging) return;
                dragging = false;
                sheetEl.style.transition = '';
                const hNow = parseFloat(sheetEl.style.height) || peekH;
                sheetEl.style.transform = '';

                if (curY - startY > 100 && hNow < peekH) {
                    window.PanelObra.close();
                } else if (hNow > window.innerHeight * 0.7) {
                    sheetEl.classList.add('is-full');
                    sheetEl.style.height = fullH + 'px';
                } else {
                    sheetEl.classList.remove('is-full');
                    sheetEl.style.height = peekH + 'px';
                }
            };

            hero.addEventListener('touchstart', onStart, {passive:true});
            window.addEventListener('touchmove', onMove, {passive:false});
            window.addEventListener('touchend', onEnd);
        }
    };
})();