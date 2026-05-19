(function(){
  'use strict';

  // API pública
  window.PanelObra = { open, close };

  // Estado interno
  let els = { backdrop:null, sheet:null, body:null, cover:null, closeBtn:null, heroContent:null, videoBtn:null, topBar:null };
  let lastFocus = null;
  let isOpen = false;
  let currentDataKey = null;

  // Utils
  const $  = (s,r=document)=> r.querySelector(s);
  const el = (t,c)=>{ const n=document.createElement(t); if(c) n.className=c; return n; };

  // Acceso seguro a utilidades
  const getUtils = () => window.PO_Utils || { safe: s => s, formatMoney: s => s, estadoToPill: () => ({cls:'', txt:''}) };

  function ensureMounted(){
    console.log("PanelObra: Verificando montaje...");
    const { safe } = getUtils();
    // 0) Anti-duplicados
    document.querySelectorAll('#pv,#pvBackdrop').forEach((n, i) => i > 1 && n.remove());

    // Barba.js Fix: Si la referencia existe pero el elemento ya no está en el DOM, recrear.
    const sheetInDom = document.getElementById('sheet');
    if (els.sheet && (!document.body.contains(els.sheet) || !sheetInDom)) {
      console.log("PanelObra: Elemento perdido en el DOM, reseteando...");
      els.sheet = null;
      els.backdrop = null;
    }

    if (!els.sheet && !sheetInDom){
      console.log("PanelObra: Creando nuevos elementos del panel...");
      const backdrop = el('div'); backdrop.id='sheetBackdrop';
      const sheet = el('aside'); sheet.id='sheet';
      sheet.setAttribute('role','dialog'); sheet.ariaModal='true';

      const hero  = el('header','sheet-hero');
      const cover = el('img','sheet-cover');
      const heroContent = el('div', 'sheet-hero-content');
      const closeBtn = el('button','sheet-close');
      closeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
      
      hero.append(cover, heroContent, el('div','sheet-grabber'), closeBtn);

      const topBar = el('div'); topBar.id = 'sheetTopBar';
      const body  = el('div','sheet-body');
      const handle = el('button','sheet-handle');
      handle.innerHTML = '<span class="chev"></span>';

      sheet.append(topBar, hero, body, handle);
      document.body.append(backdrop, sheet);

      closeBtn.onclick = handle.onclick = backdrop.onclick = close;
      els = { backdrop, sheet, body, cover, closeBtn, handle, topBar, heroContent };
    } else if (!els.sheet && sheetInDom) {
      els = { backdrop: $('#sheetBackdrop'), sheet: sheetInDom, body: $('.sheet-body', sheetInDom), cover: $('.sheet-cover', sheetInDom), closeBtn: $('.sheet-close', sheetInDom), handle: $('.sheet-handle', sheetInDom), topBar: $('#sheetTopBar'), heroContent: $('.sheet-hero-content', sheetInDom) };
    }
  }

  function loadCover(src){
    if (!els.cover) return;
    if (!src){ els.cover.classList.add('is-loaded'); els.cover.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'; return; }
    
    els.cover.classList.remove('is-loaded');
    const handler = () => { els.cover.classList.add('is-loaded'); els.cover.removeEventListener('load', handler); els.cover.removeEventListener('error', handler); };
    els.cover.addEventListener('load', handler);
    els.cover.addEventListener('error', handler);
    els.cover.src = src;
  }

  function buildKPIs(d) {
    const { safe } = getUtils();
    const items = [];
    if (d.beneficiarios) items.push(`<div class="kpi-card"><span class="kpi-label">👨‍👩‍👧‍👦 Beneficiarios</span><span class="kpi-value">${safe(d.beneficiarios)}</span></div>`);
    if (d.empleos) items.push(`<div class="kpi-card"><span class="kpi-label">👷 Empleos</span><span class="kpi-value">${safe(d.empleos)}</span></div>`);
    if (d.inicio || d.fin) items.push(`<div class="kpi-card full"><span class="kpi-label">📅 Cronograma</span><span class="kpi-value">Del ${safe(d.inicio || '—')} al ${safe(d.fin || '—')}</span></div>`);
    
    if (d.avance) {
      const val = parseInt(d.avance) || 0;
      const colorClass = val >= 100 ? 'is-complete' : (val >= 50 ? 'is-half' : '');
      items.push(`
        <div class="kpi-card full">
          <span class="kpi-label">📊 Avance Físico: ${val}%</span>
          <div class="progress-track"><div class="progress-fill ${colorClass}" style="width:${val}%"></div></div>
        </div>`);
    }

    return items.length ? `<div class="sheet-kpis stagger-el">${items.join('')}</div>` : '';
  }

  function renderSkeleton() {
    if (!els.body) return;
    els.body.innerHTML = `<div class="sheet-metric"><div class="skel" style="height:60px;width:100%"></div></div>`;
  }

  function renderContent(d){
    if (!els.body) return;

    const { safe, formatMoney, estadoToPill } = getUtils();
    const pill = estadoToPill(d.estado);
    const lugar = d.lugar || [d.distrito, d.provincia].filter(Boolean).join(' · ');
    const montoFmt = formatMoney(d.monto);

    // Resetear barra superior y scroll al inyectar contenido nuevo
    if (els.topBar) {
      els.topBar.innerHTML = '';
      els.topBar.classList.remove('is-visible');
    }
    if (els.body) els.body.scrollTop = 0;

    // 1. Actualizar Hero (Evita duplicados usando refs estables)
    els.heroContent.innerHTML = `
      <div class="sheet-status-tag">
        <span class="${pill.cls}">${pill.txt}</span>
      </div>
      <h2 id="sheetTitle" class="sheet-title">${safe(d.nombre || 'Obra')}</h2>
      <div class="sheet-subtitle">
        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
        <span>${safe(lugar)}</span>
      </div>
    `;

    els.body.innerHTML = `
      <div class="sheet-metric stagger-el">
        <div class="metric-label">Monto</div>
        <div class="metric-value">${safe(montoFmt)}</div>
      </div>
      ${buildKPIs(d)}
      <div id="compPlaceholder"></div>

      <div class="sheet-section-title">Detalles de la Obra</div>
      <div class="sheet-desc is-clamped stagger-el" id="desc">${safe(d.descripcion || '—')}</div>
      <button class="sheet-desc-more stagger-el" id="descMore" ${d.descripcion && d.descripcion.trim().length>140 ? '' : 'hidden'}>Ver más</button>

      <div class="sheet-carousel stagger-el" id="carousel"></div>
      <div class="sheet-actions stagger-el">
        <button class="btn" id="actShare">Compartir</button>
        ${d.video ? `<button class="btn btn--video" id="actVideo">Ver video</button>` : ''}
        <button class="btn" id="actCenter">Centrar en el mapa</button>
      </div>
    `;

    // “Ver más”
    const more = $('#descMore', els.body);
    if (more){
      more.addEventListener('click', ()=>{
        const dEl = $('#desc', els.body);
        dEl.classList.toggle('is-clamped');
        more.textContent = dEl.classList.contains('is-clamped') ? 'Ver más' : 'Ver menos';
      });
    }

    // Comparador Antes/Después
    if (d.antes && d.despues) {
        const compWrap = el('div', 'comparison-slider stagger-el');
        compWrap.innerHTML = `
            <div class="comp-img comp-before">
                <img src="${d.antes}" alt="Antes">
                <span class="comp-label">Antes</span>
            </div>
            <div class="comp-img comp-after" style="width: 50%;">
                <img src="${d.despues}" alt="Después">
                <span class="comp-label">Después</span>
            </div>
            <input type="range" min="0" max="100" value="50" class="comp-range" aria-label="Deslizar para comparar">
        `;
        $('#compPlaceholder', els.body).replaceWith(compWrap);
        
        const range = compWrap.querySelector('.comp-range');
        const after = compWrap.querySelector('.comp-after');
        const afterImg = after.querySelector('img');
        
        const sync = () => {
            after.style.width = range.value + '%';
            afterImg.style.width = compWrap.offsetWidth + 'px';
        };
        range.addEventListener('input', sync);
        window.addEventListener('resize', sync);
        setTimeout(sync, 100);
    }

// Carrusel
const carrWrap = el('div','sheet-carousel-mask stagger-el');     // para el degradé
const carr     = el('div','sheet-carousel');          // el contenedor real
carrWrap.appendChild(carr);

// agrega el bloque al body donde estaba el #carousel
const placeholder = $('#carousel', els.body);
placeholder.replaceWith(carrWrap);

// miniaturas
// miniaturas
const fotos = Array.isArray(d.fotos) ? d.fotos : [];
const fotosValidas = []; // Guardaremos solo las que carguen bien para el Lightbox

fotos.forEach((src, i)=>{
  const card = el('div','media');
  const im = el('img');
  im.loading='lazy'; im.decoding='async'; im.src=src; im.alt=d.nombre||'';
  
  im.addEventListener('error', ()=> card.remove()); // Oculta la miniatura si da 404
  im.addEventListener('load', ()=> {
    fotosValidas.push(src); // Solo si existe la guardamos para el visor gigante
    im.addEventListener('click', ()=> {
      const currentIdx = fotosValidas.indexOf(src);
      window.PO_Viewer.open(fotosValidas, currentIdx);
    });
  });
  
  card.appendChild(im);
  carr.appendChild(card);
});


// flechas
const nav = el('div','sheet-carousel-nav');
nav.innerHTML = `
  <button class="prev" aria-label="Anterior">
    <svg viewBox="0 0 24 24"><path fill="currentColor" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
  </button>
  <button class="next" aria-label="Siguiente">
    <svg viewBox="0 0 24 24"><path fill="currentColor" d="M8.59 16.59 10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg>
  </button>`;
carrWrap.appendChild(nav);

// Usar el nuevo módulo de carrusel con guarda
if (window.PO_Carousel) window.PO_Carousel.setup(carr, nav);

    // Listener de scroll para la barra superior
    els.body.onscroll = () => {
      const st = Math.max(0, els.body.scrollTop);
      const isThreshold = st > 240; 
      
      if (isThreshold && !els.topBar.innerHTML) {
        els.topBar.innerHTML = `<h2 class="sheet-title">${safe(d.nombre || 'Obra')}</h2>`;
      } else if (!isThreshold) {
        els.topBar.innerHTML = ''; // Limpiar si subimos para evitar duplicados
      }
      els.topBar.classList.toggle('is-visible', isThreshold);
    };


    // Acciones
    $('#actShare', els.body)?.addEventListener('click', ()=>{
      const shareData = {
        title: d.nombre,
        text: `Mira los detalles de esta obra: ${d.nombre}`,
        url: window.location.href
      };

      if (navigator.share) {
        navigator.share(shareData).catch(console.error);
        return;
      }

      navigator.clipboard?.writeText?.(window.location.href);
      const btn = $('#actShare', els.body);
      const old = btn.textContent; btn.textContent='Copiado ✓';
      setTimeout(()=> btn.textContent=old, 1200);
    });

    $('#actVideo', els.body)?.addEventListener('click', ()=>{
      if (d.video) window.open(d.video, '_blank');
    });

    $('#actCenter', els.body)?.addEventListener('click', ()=>{
      if (typeof d.onCenter === 'function') d.onCenter();
    });
  }
function reEnterSheet() {
  // 1) sacar sin animar
  els.sheet.classList.add('no-trans');
  els.sheet.classList.remove('is-open');
  void els.sheet.getBoundingClientRect();     // forzar reflow

  // 2) reactivar transición y animar en dos frames
  requestAnimationFrame(() => {
    els.sheet.classList.remove('no-trans');
    requestAnimationFrame(() => {
      els.sheet.classList.add('is-open');
      if (window.gsap) {
        gsap.fromTo(els.sheet.querySelectorAll('.stagger-el'), 
          { opacity: 0, y: 25 }, 
          { opacity: 1, y: 0, duration: 0.6, stagger: 0.1, ease: "power2.out", delay: 0.2 }
        );
      }
    });
  });
}

function open(data){
  console.log("PanelObra: Intentando abrir obra:", data.nombre);
  ensureMounted();                     // asegura que existen backdrop, sheet, etc.
  lastFocus = document.activeElement;

  // Resetear scroll y ocultar barra superior
  if (els.body) els.body.scrollTop = 0;
  if (els.topBar) els.topBar.classList.remove('is-visible');

  // clave del contenido, para decidir re-animación si cambia de pin
  const dataKey = data.key || data.slug || (data.nombre ? data.nombre.trim() : '');

  // 1) pinta estado “skeleton” y portada, luego el contenido
  renderSkeleton();
  loadCover(data.portada);
  // Renderizado síncrono para asegurar que el DOM esté listo para la animación
  renderContent(data);

  // 2) mostrar backdrop
  els.backdrop.classList.add('is-open');

  // 3) activar el gesto de arrastre en móvil desde el módulo externo
  if (els.sheet && !els.sheet.dataset.dragSetup && window.PO_Gestures){
    window.PO_Gestures.enableBottomSheetDrag(els.sheet);
    els.sheet.dataset.dragSetup = '1';
  }

  // 4) animación de apertura:
  //    - en móvil: es un bottom sheet que sube desde abajo
  //    - en desktop: tu lateral derecho con re-entrada suave si ya estaba abierto
  const isMobile = window.matchMedia('(max-width:700px)').matches;
  console.log("PanelObra: Modo " + (isMobile ? "Móvil" : "Desktop"));

if (isMobile){
  els.sheet.style.transition = 'none';
  els.sheet.classList.remove('is-open');
  els.sheet.style.transform = 'translateX(0) translateY(100%)';
  void els.sheet.offsetHeight;
  els.sheet.style.transition = '';
  els.sheet.style.transform = '';              // ← quita el inline ANTES de .is-open
  requestAnimationFrame(()=> {
    els.sheet.classList.add('is-open');
    if (window.gsap) {
      gsap.fromTo(els.sheet.querySelectorAll('.stagger-el'), 
        { opacity: 0, y: 25 }, 
        { opacity: 1, y: 0, duration: 0.6, stagger: 0.1, ease: "power2.out", delay: 0.2 }
      );
    }
  });
  document.body.style.overflow = 'hidden';
  } else {
    // lateral derecho: si ya está abierto y cambió de pin, re-entra (tu helper)
    if (els.sheet.classList.contains('is-open')) {
      if (dataKey && dataKey !== currentDataKey) {
        reEnterSheet();  // saca y vuelve a entrar para que la animación se note
      }
    } else {
      // primera apertura en desktop
      els.sheet.classList.remove('is-open');
      void els.sheet.getBoundingClientRect(); // fuerza reflow
      requestAnimationFrame(()=> {
        els.sheet.classList.add('is-open');
        if (window.gsap) {
          gsap.fromTo(els.body.querySelectorAll('.stagger-el'), 
            { opacity: 0, y: 25 }, 
            { opacity: 1, y: 0, duration: 0.6, stagger: 0.1, ease: "power2.out", delay: 0.2 }
          );
        }
      });
    }
  }

  // 5) guarda estado
  currentDataKey = dataKey;
  isOpen = true;

  if (!isMobile && els.handle){
  els.handle.style.display = 'flex';
}

  // 7) accesibilidad: foco al título
  setTimeout(()=>{
    const t = document.querySelector('#sheetTitle');
    if (t) t.setAttribute('tabindex','-1'), t.focus({preventScroll:true});
  }, 50);

}

  function close(){
    if (!els.sheet) return;
    els.backdrop.classList.remove('is-open');
    els.sheet.classList.remove('is-open');
    els.sheet.style.transform = '';
    document.body.style.overflow = '';
    els.topBar.classList.remove('is-visible');
    isOpen = false;
    if (lastFocus && typeof lastFocus.focus === 'function'){
      setTimeout(()=> lastFocus.focus(), 10);
    }
  }

})();
