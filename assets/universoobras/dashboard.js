document.addEventListener('DOMContentLoaded', () => {
    // Esperamos a que la web esté lista y el diccionario de pestañas se haya creado
    const checkInterval = setInterval(async () => {
        if (window.SHEETS_MAPPED && window.SHEETS) {
            clearInterval(checkInterval);
            await iniciarDashboard();
        }
    }, 200);
});

async function iniciarDashboard() {
    // 1. Secuencia Cinematográfica de Textos
    const tl = gsap.timeline();
    tl.to(".intro-text.it-1", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-1", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 })
      .to(".intro-text.it-2", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-2", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 })
      .to(".intro-text.it-3", { opacity: 1, y: 0, duration: 1.2, ease: "power2.out" })
      .to(".intro-text.it-3", { opacity: 0, y: -20, duration: 0.8, delay: 1.5 });

    // 2. Extraer datos en segundo plano sin asfixiar la UI
    let totalInversion = 0;
    let totalObras = 0;
    let culminadas = 0;
    let riesgo = 0;

    try {
        const promesas = [];
        const segs = Object.values(window.SHEETS).filter(s => s);
        
        // Evitamos duplicados
        const tabsUnicos = [...new Set(segs)];

        for (const tab of tabsUnicos) {
            if (tab === 'base' || tab === '') continue;
            const url = `https://docs.google.com/spreadsheets/d/${window.SHEET_ID}/gviz/tq?tqx=out:json;reqId=${new Date().getTime()}&sheet=${encodeURIComponent(tab)}&range=A:Z&headers=1`;
            promesas.push(fetch(url).then(r => r.text()).then(txt => {
                const match = txt.match(/setResponse\(([\s\S]+)\);?/);
                if (match) {
                    const data = JSON.parse(match[1]);
                    return window.gvizToObjects(data);
                }
                return [];
            }).catch(() => []));
        }

        const resultados = await Promise.all(promesas);
        resultados.forEach(obras => {
            obras.forEach(o => {
                if (o.nombre) {
                    totalObras++;
                    const montoStr = String(o.monto || '').replace(/[^0-9.]/g, '');
                    totalInversion += (parseFloat(montoStr) || 0);

                    const est = String(o.estado || '').toLowerCase();
                    if (est.includes('entregad') || est.includes('terminad') || est.includes('culminad')) {
                        culminadas++;
                    } else if (est.includes('paralizad') || est.includes('suspendid')) {
                        riesgo++;
                    }
                }
            });
        });

        // 3. Mostrar las tarjetas una vez que terminaron los textos
        tl.to(".dashboard-cards", { opacity: 1, visibility: "visible", duration: 1.5, y: 0, ease: "power3.out" }, "-=0.5");

        // Animar el contador de números
        setTimeout(() => {
            if(typeof animateValue === 'function') {
                animateValue('val-total-obras', 0, totalObras, 1500, false);
                animateValue('val-total-inversion', 0, totalInversion, 2000, true);
                animateValue('val-total-culminadas', 0, culminadas, 1500, false);
                animateValue('val-total-riesgo', 0, riesgo, 1500, false);
            }
        }, tl.duration() * 1000 - 500);

    } catch (e) {
        console.error("Error fatal en el dashboard:", e);
    }
}

function animateValue(id, start, end, duration, isCurrency) {
    const obj = document.getElementById(id);
    if (!obj) return;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = progress * (end - start) + start;
        
        if (isCurrency) {
            obj.innerHTML = 'S/ ' + (current / 1000000).toFixed(1) + ' <span style="font-size: 0.5em;">MILLONES</span>';
        } else {
            obj.innerHTML = Math.floor(current);
        }
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            if (isCurrency) {
                obj.innerHTML = 'S/ ' + (end / 1000000).toFixed(1) + ' <span style="font-size: 0.5em;">MILLONES</span>';
            } else {
                obj.innerHTML = end;
            }
        }
    };
    window.requestAnimationFrame(step);
}