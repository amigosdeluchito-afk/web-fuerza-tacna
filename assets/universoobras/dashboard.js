(function() {
    // Arranque INMEDIATO estático (Ya no espera a la base de datos)
    const initStatic = setInterval(async () => {
        if (typeof gsap !== 'undefined') {
            clearInterval(initStatic);
            if (window._dashInitDone) return;
            window._dashInitDone = true;
            await iniciarDashboard();
        }
    }, 100);
})();

async function iniciarDashboard() {
    // 1. Secuencia Cinematográfica de Textos
    const tl = gsap.timeline();
    tl.to(".intro-text.it-1", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-1", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 })
      .to(".intro-text.it-2", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-2", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 })
      .to(".intro-text.it-3", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-3", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 });

    // 2. Mostrar las tarjetas estáticas al instante (SIN descargar nada de internet)
        tl.to(".dashboard-cards", { opacity: 1, visibility: "visible", duration: 1.5, y: 0, ease: "power3.out" }, "-=0.5");

    // 3. Animar números estáticos predefinidos
        setTimeout(() => {
            if(typeof animateValue === 'function') {
            // EDITAR AQUÍ TUS DATOS ESTÁTICOS REALES:
            animateValue('val-total-obras', 0, 480, 1500, false, '+');
            animateValue('val-total-inversion', 0, 200, 2000, true, ' MILLONES');
            animateValue('val-total-familias', 0, 120000, 2000, false, '');
            animateValue('val-total-empleos', 0, 8500, 1500, false, '+');
            }
        }, tl.duration() * 1000 - 500);
}

function animateValue(id, start, end, duration, isCurrency, suffix = '') {
    const obj = document.getElementById(id);
    if (!obj) return;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = progress * (end - start) + start;
        
        if (isCurrency) {
            obj.innerHTML = 'S/ ' + current.toFixed(0) + ' <span style="font-size: 0.5em;">' + suffix + '</span>';
        } else {
            obj.innerHTML = Math.floor(current).toLocaleString('es-PE') + (suffix ? ' <span style="font-size: 0.5em;">' + suffix + '</span>' : '');
        }
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}