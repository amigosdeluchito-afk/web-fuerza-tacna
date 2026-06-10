document.addEventListener('DOMContentLoaded', async () => {
    Chart.defaults.color = '#8b9bb4';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.scale.grid.color = 'rgba(255, 255, 255, 0.05)';

    const loadingIndicator = document.querySelector('.feed-loading');
    
    try {
        await window.initGlobalConfig();
        
        const obrasPromises = [];
        const todasLasObras = [];

        window.SEGMENTOS_DATA.forEach(seg => {
            const promesa = window.fetchSheetGVizJSON(window.SHEET_ID, { sheetName: seg.tab })
                .then(json => {
                    const obras = window.gvizToObjects(json);
                    obras.forEach(o => {
                        if (o.nombre) {
                            o.segmento = seg.nombre;
                            todasLasObras.push(o);
                        }
                    });
                })
                .catch(err => console.error("Error al cargar segmento " + seg.nombre, err));
            obrasPromises.push(promesa);
        });

        await Promise.all(obrasPromises);

        if (todasLasObras.length === 0) {
            loadingIndicator.textContent = "No se encontraron obras.";
            return;
        }

        let totalInversion = 0;
        let estadoCount = { verde: 0, amarillo: 0, rojo: 0 };
        const distritoData = {};

        todasLasObras.forEach(o => {
            const montoStr = (o.monto || '').replace(/[^0-9.]/g, '');
            const montoVal = parseFloat(montoStr) || 0;
            totalInversion += montoVal;
            o.montoNumerico = montoVal;

            const est = (o.estado || '').toLowerCase();
            let colorStatus = 'amarillo';
            if (est.includes('terminad') || est.includes('culminad') || est.includes('recepcionad') || est.includes('liquid')) {
                colorStatus = 'verde';
                estadoCount.verde++;
            } else if (est.includes('paralizad') || est.includes('suspendid') || est.includes('arbitraje') || est.includes('resuelt')) {
                colorStatus = 'rojo';
                estadoCount.rojo++;
            } else {
                estadoCount.amarillo++;
            }
            o.colorStatus = colorStatus;

            const dist = (o.distrito || 'No Especificado').toUpperCase().trim();
            if (!distritoData[dist]) distritoData[dist] = 0;
            distritoData[dist] += montoVal;
        });

        animateValue('val-total-obras', 0, todasLasObras.length, 1500, false);
        animateValue('val-total-inversion', 0, totalInversion, 2000, true);
        animateValue('val-total-riesgo', 0, estadoCount.rojo, 1500, false);
        animateValue('val-total-culminadas', 0, estadoCount.verde, 1500, false);

        renderChartTop5(todasLasObras);
        renderChartSemaforo(estadoCount);
        renderChartDistritos(distritoData);
        renderFeed(todasLasObras);

        if (loadingIndicator) loadingIndicator.style.display = 'none';

        const searchInput = document.getElementById('feed-search');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                const filtradas = todasLasObras.filter(o => 
                    (o.nombre || '').toLowerCase().includes(term) || 
                    (o.distrito || '').toLowerCase().includes(term)
                );
                renderFeed(filtradas);
            });
        }

    } catch (e) {
        console.error("Error fatal en el dashboard:", e);
        if(loadingIndicator) loadingIndicator.textContent = "Error al cargar los datos.";
    }
});

function animateValue(id, start, end, duration, isCurrency) {
    const obj = document.getElementById(id);
    if (!obj) return;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = progress * (end - start) + start;
        
        if (isCurrency) {
            obj.innerHTML = (current / 1000000).toFixed(1) + ' <span style="font-size: 1rem;">M</span>';
        } else {
            obj.innerHTML = Math.floor(current);
        }
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            if (isCurrency) {
                obj.innerHTML = (end / 1000000).toFixed(1) + ' <span style="font-size: 1rem;">M</span>';
            } else {
                obj.innerHTML = end;
            }
        }
    };
    window.requestAnimationFrame(step);
}

function renderChartTop5(obras) {
    const ctx = document.getElementById('chart-top5');
    if (!ctx) return;
    const top5 = [...obras].sort((a, b) => b.montoNumerico - a.montoNumerico).slice(0, 5);
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: top5.map(o => o.nombre.length > 25 ? o.nombre.substring(0, 25) + '...' : o.nombre),
            datasets: [{
                label: 'Inversión (Millones S/)',
                data: top5.map(o => o.montoNumerico / 1000000),
                backgroundColor: 'rgba(0, 176, 255, 0.7)',
                borderColor: '#00b0ff',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => 'S/ ' + ctx.raw.toFixed(2) + ' Millones' } } },
            scales: { x: { beginAtZero: true, grid: { display: false } }, y: { grid: { display: false } } }
        }
    });
}

function renderChartSemaforo(estadoCount) {
    const ctx = document.getElementById('chart-semaforo');
    if (!ctx) return;
    const dataValues = [estadoCount.verde, estadoCount.amarillo, estadoCount.rojo];
    const bgColors = ['#00e676', '#ffea00', '#ff3d00'];
    const labels = ['Culminadas', 'En Ejecución', 'Riesgo / Paralizadas'];
    new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: dataValues, backgroundColor: bgColors, borderWidth: 0, hoverOffset: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
    });
    const legendDiv = document.getElementById('semaforo-legend');
    if(legendDiv) {
        legendDiv.innerHTML = labels.map((l, i) => 
            '<div class="legend-item">' +
            '<div class="legend-label"><div class="legend-color" style="background:' + bgColors[i] + '"></div>' + l + '</div>' +
            '<strong>' + dataValues[i] + '</strong>' +
            '</div>'
        ).join('');
    }
}

function renderChartDistritos(distritoData) {
    const ctx = document.getElementById('chart-distritos');
    if (!ctx) return;
    const entries = Object.entries(distritoData).filter(e => e[1] > 0).sort((a, b) => b[1] - a[1]);
    const labels = entries.map(e => e[0].length > 15 ? e[0].substring(0, 15) + '...' : e[0]);
    const data = entries.map(e => e[1] / 1000000);
    new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Inversión (Millones S/)', data: data, backgroundColor: 'rgba(0, 230, 118, 0.4)', borderColor: '#00e676', borderWidth: 1, borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45 } }, y: { grid: { color: 'rgba(255,255,255,0.05)' } } } }
    });
}

function renderFeed(obras) {
    const feed = document.getElementById('feed-list');
    if (!feed) return;
    const loading = feed.querySelector('.feed-loading');
    if(loading) loading.style.display = 'none';
    Array.from(feed.children).forAXch(c => { if(!c.classList.contains('feed-loading')) c.remove(); });
    if (obras.length === 0) {
        const p = document.createElement('p'); p.textContent = "No se encontraron resultados."; p.style.color = "var(--text-muted)"; p.style.textAlign = "center"; p.style.marginTop = "1rem";
        feed.appendChild(p);
        return;
    }
    obras.forEach(o => {
        const div = document.createElement('div');
        div.className = 'feed-item';
        div.innerHTML = '<div class="feed-status status-' + o.colorStatus + '"></div><div class="feed-info"><h4>' + o.nombre + '</h4><p>' + (o.distrito || 'Tacna') + ' • ' + o.segmento + '</p></div>';
        div.addEventListener('click', () => {
            div.style.transform = "scale(0.98)";
            setTimeout(() => div.style.transform = "scale(1)", 150);
        });
        feed.appendChild(div);
    });
}