

// --- ESCUDO TEMPORAL DE REVISIÓN ---
function checkTemporalAccess() {
    // Si ya ingresaron la contraseña en esta sesión, los dejamos pasar
    if (sessionStorage.getItem('temporal_access') === 'granted') return;

    // Primero preguntamos al servidor de forma invisible
    fetch('assets/panel-admin-universo/api_estado_sitio.php')
        .then(response => response.json())
        .then(config => {
            if (!config.privado_activo) {
                return; // Si es público, no hacemos nada. Adiós pestañeo negro.
            }
            
            // Crear pantalla de bloqueo SOLO si está privado
            const shield = document.createElement('div');
            shield.id = 'temporal-shield';
            shield.style.cssText = `
                position: fixed; inset: 0; width: 100vw; height: 100vh;
                background: rgba(20, 20, 20, 0.98); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
                z-index: 9999999999; display: flex; flex-direction: column; align-items: center; justify-content: center;
                font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif;
            `;
            shield.innerHTML = `
                <div id="shield-content" style="background: #801039; padding: 40px; border-radius: 20px; text-align: center; box-shadow: 0 15px 50px rgba(0,0,0,0.8); border: 2px solid #ffc300; max-width: 90%; width: 350px;">
                    <h2 style="color: #ffc300; margin: 0 0 10px 0; font-size: 1.5rem; text-transform: uppercase;">ACCESO PRIVADO</h2>
                    <p style="color: #fff; font-family: system-ui, sans-serif; font-size: 0.9rem; margin-bottom: 25px; font-weight: 300;">Sitio en fase de revisión. Ingrese la contraseña:</p>
                    <input type="password" id="shield-pass" style="padding: 12px; border: none; border-radius: 8px; margin-bottom: 15px; width: 100%; box-sizing: border-box; font-size: 16px; text-align: center; outline: 2px solid transparent; transition: outline 0.3s;" placeholder="Contraseña">
                    <button id="shield-btn" style="background: #ffc300; color: #801039; border: none; padding: 12px 20px; font-family: 'Arial Black Web', 'Arial Black', sans-serif; font-weight: 900; border-radius: 8px; cursor: pointer; width: 100%; font-size: 16px; transition: transform 0.2s;">ENTRAR</button>
                    <p id="shield-error" style="color: #ff6b6b; display: none; margin-top: 15px; font-family: system-ui, sans-serif; font-size: 13px; margin-bottom: 0;">Contraseña incorrecta</p>
                </div>
            `;
            
            document.documentElement.appendChild(shield);

            const verifyPass = () => {
                const pass = document.getElementById('shield-pass').value;
                const btn = document.getElementById('shield-btn');
                const originalText = btn.innerText;
                btn.innerText = 'VERIFICANDO...';
                btn.disabled = true;
                
                const fd = new URLSearchParams();
                fd.append('pass', pass);

                fetch('assets/panel-admin-universo/api_estado_sitio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        sessionStorage.setItem('temporal_access', 'granted');
                        shield.remove();
                        fetch('assets/panel-admin-universo/log_access.php', { method: 'POST' }).catch(() => {});
                    } else {
                        document.getElementById('shield-error').style.display = 'block';
                        document.getElementById('shield-pass').style.outline = '2px solid #ff6b6b';
                        btn.innerText = originalText;
                        btn.disabled = false;
                    }
                }).catch(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                });
            };
            
            setTimeout(() => {
                document.getElementById('shield-btn').addEventListener('click', verifyPass);
                document.getElementById('shield-pass').addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') verifyPass();
                });
                document.getElementById('shield-pass').focus();
            }, 50);
        })
        .catch(err => {
            console.error('Error cargando config del escudo:', err);
        });
}
checkTemporalAccess();

// Obligar al navegador a empezar desde arriba al recargar (F5)
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
window.scrollTo(0, 0);

// --- AUTO INYECCIÓN DE ESTILOS GLOBALES Y LOADER ---
// Esto evita que tengas que copiar y pegar código en cada página HTML individualmente.
// El JS se encargará de poner la píldora, el WhatsApp y el loader automáticamente.
function injectGlobalAssets() {
    // Inyectar estilos globales si no existen en la página actual
    if (!document.getElementById('le-lab-dynamic-styles')) {
        const style = document.createElement('style');
        style.id = 'le-lab-dynamic-styles';

        // FIX: Se calcula la ruta al fondo 'pattern.svg' dinámicamente.
        // Esto evita errores 404 al navegar a páginas en subcarpetas (como el mapa de obras),
        // ya que la ruta relativa se ajusta automáticamente.
        const basePath = window.location.pathname.includes('/assets/') ? '../../' : '';
        const patternUrl = `${basePath}assets/img/pattern.svg`;

        style.innerHTML = `
            .menu-links a, #hero-header ul li a { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-weight: 900 !important; }
            .magnetic .wrap .span { background-color: #25D366 !important; }
            .mouse-in, #services h3::before, #services .title-client::before, #intro button:hover, #video-section button:hover, #drone-section button:hover, #design-section button:hover, .references-section button:hover, #button-center:hover { background-color: #ffc300 !important; }
            .scroll-indication, .small-title, #intro button, #video-section button, #drone-section button, #votar-section button, .references-section button, #button-center, .menu-links a:hover, .menu-links a.active, .hyperlinks { color: #ffc300 !important; }
            #intro button, #video-section button, #drone-section button, #votar-section button, .references-section button, #button-center { border-color: #ffc300 !important; }
            #hero-header { position: fixed; top: 15px; left: 2% !important; width: 96% !important; box-sizing: border-box !important; z-index: 1000; background: transparent; }
            @media (min-width: 992px) { #hero-header { display: flex !important; justify-content: space-between !important; align-items: center !important; } #hero-header .logo-container { flex: 1 !important; display: flex !important; justify-content: flex-start !important; } #hero-header .button-container { flex: 1 !important; display: flex !important; justify-content: flex-end !important; } #hero-header ul { flex: 0 1 auto !important; margin: 0 auto !important; } }
            #hero-header ul { background-color: rgba(255, 195, 0, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 50px; padding: 18px 24px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid rgba(255, 255, 255, 0.4); width: max-content !important; margin: 0 auto; will-change: transform, backdrop-filter; transform: translateZ(0); backface-visibility: hidden; }
            #hero-header ul li { margin: 0 15px !important; }
            #hero-section, #hero-drone-section, #hero-video-section, #hero-contact, #hero-design-section, #sumate-hyperspace-section, #contacto-escenario { min-height: 100vh !important; width: 100%; box-sizing: border-box; }
            body.hide-header #hero-header, body.hide-header .mobile-arrows {
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
                transition: opacity 0.3s ease, visibility 0.3s ease !important;
            }
            main { position: relative; z-index: 0; }
            main::before { content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-image: url('${patternUrl}'); background-repeat: no-repeat; background-size: cover; background-position: center; pointer-events: none; z-index: -1; transform: translateZ(0); will-change: transform; }
            
            /* --- Flechas Flotantes Globales --- */
            .mobile-arrows { display: flex !important; flex-direction: column; align-items: center; position: absolute; bottom: 10vh; z-index: 99999; text-decoration: none; margin: 0 !important; padding: 0 !important; }
            .mobile-arrows.arr-left { left: 20%; transform: translateX(-50%); }
            .mobile-arrows.arr-center { left: 50%; transform: translateX(-50%); }
            .mobile-arrows.arr-right { left: 80%; transform: translateX(-50%); }
            .mobile-arrows span { display: block; width: 16px; height: 16px; border-bottom: 4px solid #ffffff; border-right: 4px solid #ffffff; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); margin: -4px auto; animation: arrow-float 2s infinite; }
            .mobile-arrows span:nth-child(2) { animation-delay: -0.2s; }
            .mobile-arrows span:nth-child(3) { animation-delay: -0.4s; }
            .mobile-arrows.arr-dark span { border-bottom: 4px solid #801039 !important; border-right: 4px solid #801039 !important; filter: drop-shadow(0 2px 2px rgba(255,195,0,0.5)) !important; }
            @keyframes arrow-float { 0% { opacity: 0; transform: translateY(-10px) rotate(45deg); } 50% { opacity: 0.9; transform: translateY(0) rotate(45deg); } 100% { opacity: 0; transform: translateY(10px) rotate(45deg); } }
            #mouse-scroll { display: none !important; }

            /* --- Efecto Star Wars para "Súmate" (Globalizado para evitar bugs de ventana gigante) --- */
            #sumate-menu-item { position: relative; display: flex; align-items: center; justify-content: center; }
            #sumate-menu-item > a { line-height: 1.05; font-size: 13px; text-align: center; position: relative; z-index: 1; transition: color 0.3s ease, transform 0.3s ease; }
            #sumate-menu-item .lucho-fuerza-peek { position: absolute; bottom: -30px; left: 50%; transform: translateX(-50%) translateY(-20px); opacity: 0; transition: opacity 0.4s ease-out, transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); pointer-events: none; }
            #sumate-menu-item .lucho-fuerza-peek img { width: 100px !important; height: auto !important; display: block !important; filter: drop-shadow(0px 5px 15px rgba(0, 0, 0, 0.4)); mask-image: linear-gradient(to bottom, black 40%, transparent 70%); -webkit-mask-image: linear-gradient(to bottom, black 40%, transparent 70%); }
            #sumate-menu-item > a::before { content: 'SÚMATE\\A A LA FUERZA'; white-space: pre; color: #adfdff; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1; opacity: 0; filter: blur(1.5px); transition: opacity 0.4s ease; }
            @keyframes force-flicker { 0%, 100% { opacity: 1; } 50% { opacity: 0.95; } }
            @keyframes force-glow-pulse { 0% { filter: blur(1.5px); text-shadow: 0 0 10px #00d9ff, 0 0 20px #00d9ff, 0 0 35px #00d9ff; } 100% { filter: blur(2.5px); text-shadow: 0 0 15px #00d9ff, 0 0 30px #00d9ff, 0 0 50px #00d9ff; } }
            #sumate-menu-item:hover > a { color: #fff !important; transform: translateY(-2px); animation: force-flicker 0.15s infinite alternate; }
            #sumate-menu-item:hover > a::before { opacity: 1; animation: force-glow-pulse 1s infinite alternate ease-in-out; }
            #sumate-menu-item:hover .lucho-fuerza-peek { opacity: 1; transform: translateX(-50%) translateY(0); }

            /* --- Estilos Mapa Interactivo Tacna (Solución SPAs Barba.js) --- */
            #mapa-tacna-container svg { width: 100%; height: auto; overflow: visible; filter: drop-shadow(0 15px 25px rgba(0,0,0,0.3)); }
            #mapa-tacna-container svg path, #mapa-tacna-container svg polygon, #mapa-tacna-container svg g { fill: #801039; stroke: url(#map-shiny-border); stroke-width: 2.5px; stroke-linejoin: round; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.27); cursor: pointer; transform-origin: center; }
            #mapa-tacna-container svg path:hover, #mapa-tacna-container svg polygon:hover, #mapa-tacna-container svg g:hover { fill: #ffc300; stroke: #ffffff; stroke-width: 3.5px; transform: translateY(-12px) scale(1.03); filter: drop-shadow(0 20px 25px rgba(0,0,0,0.5)); }
            #mapa-tacna-container { animation: map-float 6s ease-in-out infinite; }
            @keyframes map-float { 0% { transform: translateY(0px); } 50% { transform: translateY(-12px); } 100% { transform: translateY(0px); } }
            #map-tooltip { position: fixed; background: rgba(20, 20, 20, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 195, 0, 0.4); color: #fff; padding: 0.8rem 1.4rem; border-radius: 8px; pointer-events: none; opacity: 0; visibility: hidden; z-index: 999999; transform: translate(-50%, -100%) scale(0.9); transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.27); box-shadow: 0 15px 35px rgba(0,0,0,0.4); text-align: center; }
            #map-tooltip.visible { opacity: 1; visibility: visible; transform: translate(-50%, -130%) scale(1); }
            #map-tooltip h4 { margin: 0 0 0.3rem 0; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; color: #ffc300; font-size: 1.05rem; text-transform: uppercase; letter-spacing: 1px; line-height: 1; }
            #map-tooltip p { margin: 0; font-size: 0.75rem; font-weight: 600; color: #e0e0e0; font-family: system-ui, -apple-system, sans-serif; text-transform: uppercase; letter-spacing: 1px; }

            /* --- Estilos Globales de Candidatos y Carrusel --- */
            #candidatos-section { background-color: transparent; position: relative !important; top: 0 !important; transform: none !important; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; width: 100%; overflow: hidden; z-index: 10; padding-top: 15vh; }
            .marquee-container { overflow: hidden; width: 100vw; left: 50%; transform: translateX(-50%); position: relative; display: flex; cursor: grab; }
            .marquee-container:active { cursor: grabbing; }
            .marquee-container * { user-select: none; -webkit-user-drag: none; }
            .marquee-content { display: flex; gap: 1.5rem; width: max-content; padding: 1rem 0; will-change: transform; }
            .candidate-card { width: 350px; height: 500px; position: relative; border-radius: 1.5rem; overflow: hidden; flex-shrink: 0; background: rgba(255, 195, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.1); transform-style: preserve-3d; }
            .candidate-card img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; transition: opacity 0.4s ease; transform: translateZ(-5px) scale(1.02); }
            .candidate-card .img-hover { opacity: 0; }
            .candidate-card:hover .img-hover { opacity: 1; }
            .candidate-card:hover .img-default { opacity: 0; }
            .candidate-info { position: absolute; bottom: 0; width: 100%; background: rgba(20, 20, 20, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 1.5rem 1rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); transform: translateZ(40px); }
            .candidate-info h3 { margin: 0 0 0.4rem 0; font-size: 1.3rem; font-weight: 900; color: #fff; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; text-transform: uppercase; }
            .candidate-info p { margin: 0; font-size: 0.95rem; color: #ffc300; font-weight: bold; text-transform: uppercase; }
            @media (max-width: 768px) { .candidate-card { width: 200px !important; height: 300px !important; border-radius: 1.2rem; } .candidate-info { padding: 0.6rem 0.5rem !important; } .candidate-info h3 { font-size: 1rem !important; margin-bottom: 0.2rem; } .candidate-info p { font-size: 0.7rem !important; } #candidatos-section { min-height: 100vh; padding-top: 15vh !important; } }

            /* --- Título de la Página de Candidatos --- */
            .candidatos-page-title {
                text-align: center;
                margin-bottom: 2rem;
                width: 100%;
                padding: 0 1rem;
            }
            .candidatos-page-title h2 {
                font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important;
                font-weight: 900 !important;
                font-size: clamp(2.5rem, 5vw, 4.5rem);
                color: #801039; /* Color granate para todo el título */
                text-transform: uppercase;
                line-height: 1.1;
                margin: 0;
                letter-spacing: -1px;
            }
            @media (max-width: 768px) {
                .candidatos-page-title { margin-bottom: 1.5rem; }
                .candidatos-page-title h2 { font-size: clamp(1.8rem, 8vw, 2.5rem); line-height: 1; }
            }
            @media (min-width: 769px) {
                .candidatos-page-title, .lucho-candidatos-container { display: none !important; }
            }

            /* --- Ajuste de Altura Sección Intro --- */
            #intro {
                min-height: 95vh;
                display: flex;
                align-items: center;
                padding-top: 6vh; /* SIN !important para no bloquear los estilos de la versión celular */
            }

            /* --- Split Section (Intro) --- */
            .candidatos-intro-split {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2rem;
                max-width: 90rem; /* Ampliado para dar más espacio a los lados */
                margin: 0 auto;
                padding: 0 3%; /* Reducido para que se acerque más al borde izquierdo */
            }
            .candidatos-intro-text {
                width: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                text-align: center;
            }
            .candidatos-intro-text h2 {
                font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important;
                font-size: clamp(4.5rem, 8vw, 9.5rem); /* Tamaño aumentado */
                color: #801039;
                line-height: 1.1;
                text-transform: uppercase;
                margin: 0; /* Eliminamos el margen inferior para que se centre mejor */
                text-shadow: none;
            }

            @media (min-width: 992px) {
                .candidatos-intro-split { flex-direction: row; justify-content: space-between; align-items: flex-start; gap: 4rem; } /* Alineado arriba */
                .candidatos-intro-text { flex: 1.2; text-align: left; padding-right: 1rem; margin-top: -5.5rem; margin-left: -2vw; } /* Subido notoriamente más arriba */
            }
            @media (max-width: 991px) { 
                .candidatos-intro-text { display: contents; }
                .candidatos-intro-text h2 { order: 1; margin-bottom: 1.5rem; }
            }
            
            /* --- Solución al Bug de Scroll en Videos (Escudo Transparente) --- */
            .iframe-container { position: relative !important; }
            .iframe-container::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; cursor: pointer; }
            .iframe-container.is-interactive::after { pointer-events: none; }

            /* Estilos de la Pantalla de Carga */
            .initial-loader { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999999 !important; display: flex; align-items: center; justify-content: center; background-color: #ffc300; }
            .initial-loader .loader-h1 { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-size: 5rem; line-height: 1.1; margin: 0; text-align: center; color: #801039; font-weight: 900; letter-spacing: 2px; }
            .initial-loader .water-layer { position: absolute; bottom: 0; left: 0; width: 100%; height: 0%; background-color: #801039; overflow: hidden; }
            .initial-loader .water-content { position: absolute; bottom: 0; left: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; }
            .initial-loader .water-content .loader-h1 { color: #ffc300; }
            .initial-loader .wave { position: absolute; bottom: 0%; margin-bottom: -2vw; left: -10vw; width: 120vw; height: clamp(60px, 10vw, 130px); z-index: 2; pointer-events: none; }
            .initial-loader .wave::before { content: ''; position: absolute; bottom: 0; left: 0; width: 240vw; height: 100%; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 150" preserveAspectRatio="none"><path d="M0,50 Q125,10 250,50 T500,50 T750,50 T1000,50 L1000,150 L0,150 Z" fill="%23801039"/></svg>'); background-size: 120vw 100%; background-repeat: repeat-x; transform-origin: bottom center; animation: waveAnimFluid 2.5s linear infinite; }
            @keyframes waveAnimFluid { 0% { transform: translateX(0) translateY(0); } 50% { transform: translateX(-60vw) translateY(8px); } 100% { transform: translateX(-120vw) translateY(0); } }
            @media (max-width: 768px) { .initial-loader .loader-h1 { font-size: 3.5rem; } }

            /* --- Glass Button v3 Global Styles (Anti-Barba.js Bug) --- */
            #hero-main-content {
                position: absolute !important;
                top: 82vh !important; /* Un poco más abajo, cerca del borde inferior */
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                text-align: center !important;
                z-index: 999999 !important;
                width: 100% !important;
                display: block !important;
                opacity: 0;
                visibility: visible !important;
                pointer-events: auto !important;
                animation: heroFadeUp 1.8s cubic-bezier(0.25, 1, 0.5, 1) 0.5s forwards;
            }
            @keyframes heroFadeUp {
                0% { opacity: 0; transform: translate(-50%, 0%); }
                100% { opacity: 1; transform: translate(-50%, -50%); }
            }
            .glass-button-wrap {
                position: relative !important;
                display: inline-block !important;
                cursor: pointer !important;
                border-radius: 9999px !important;
                transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
                text-decoration: none !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
            .glass-button {
                position: relative;
                display: block !important;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.02)) !important;
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                border-radius: 9999px !important;
                z-index: 2 !important;
                overflow: hidden !important;
                transition: all 0.4s ease; will-change: transform, backdrop-filter; transform: translateZ(0); backface-visibility: hidden;
                box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            }
            .glass-button::before {
                content: '';
                position: absolute;
                top: 0;
                left: -150%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
                transform: skewX(-30deg);
                transition: left 0.7s ease;
                z-index: 1;
            }
            .glass-button-text {
                position: relative;
                display: block !important;
                padding: 1.2rem 3.5rem !important;
                font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important;
                font-weight: 900 !important;
                font-size: 1.15rem !important;
                color: #ffffff !important;
                text-transform: uppercase !important;
                letter-spacing: 2px !important;
                text-decoration: none !important;
                z-index: 3 !important;
                text-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
            }
            .glass-button-shadow {
                position: absolute;
                inset: 0;
                border-radius: 9999px;
                box-shadow: 0 0 40px rgba(255, 195, 0, 0.8), 0 0 80px rgba(255, 195, 0, 0.4);
                opacity: 0;
                transition: opacity 0.4s ease, transform 0.4s ease;
                z-index: 1;
            }
            .glass-button-wrap:hover { transform: translateY(-4px); }
            .glass-button-wrap:hover .glass-button {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.08)) !important;
                border-color: rgba(255, 195, 0, 0.8) !important;
            }
            .glass-button-wrap:hover .glass-button::before { left: 150%; }
            .glass-button-wrap:hover .glass-button-shadow { opacity: 1; transform: scale(1.05); }
            .glass-button-wrap:active { transform: translateY(0) scale(0.98); }

            /* --- Estilos Dinámicos de la Sección de Detalle de Candidatos --- */
            #detalle-candidato-wrapper { display: none; width: 100%; flex-shrink: 0; clear: both; box-sizing: border-box; padding: 5rem 5%; min-height: 100vh; background: transparent; position: relative; z-index: 40; margin-top: -10vh; border-top: 1px solid rgba(255, 255, 255, 0.1); }
            .candidato-detalle-container { display: flex; gap: 2.5rem; max-width: 90rem; margin: 0 auto; align-items: flex-start; }
            .candidato-sidebar { display: flex; flex-direction: column; gap: 1rem; width: 95px; flex-shrink: 0; position: sticky; top: 120px; }
            .mini-card { width: 100%; height: 120px; border-radius: 1rem; cursor: pointer; overflow: hidden; opacity: 0.5; transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); border: 2px solid transparent; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
            .mini-card.active, .mini-card:hover { opacity: 1; border-color: #ffc300; transform: scale(1.05) translateX(10px); }
            .mini-card img { width: 100%; height: 100%; object-fit: cover; }
            .candidato-content { flex-grow: 1; display: flex; flex-direction: column; background: #801039; border-radius: 2rem; padding: 3.5rem; border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 20px 60px rgba(0,0,0,0.7); }
            .candidato-content p.stagger-el { margin-bottom: 2.5rem !important; font-size: 1.1rem !important; line-height: 1.8 !important; }
            
            /* --- Diseño de Ficha Profesional (Bloques Visuales) --- */
            .candidate-top-row { display: flex; gap: 3.5rem; width: 100%; align-items: flex-start; }
            .candidate-bottom-row { width: 100%; margin-top: 3.5rem; padding-top: 3.5rem; border-top: 1px solid rgba(255, 255, 255, 0.08); }

            /* --- Animación de Borde Giratorio (React Port) --- */
            @property --gradient-angle { syntax: "<angle>"; initial-value: 0deg; inherits: false; }
            @keyframes border-rotate { 0% { --gradient-angle: 0deg; } 100% { --gradient-angle: 360deg; } }

            .candidato-photo-wrapper { position: relative; width: 35%; flex-shrink: 0; perspective: 1000px; }
            .photo-glow { position: absolute; top: 10%; left: 10%; width: 80%; height: 80%; background: #ffc300; filter: blur(70px); opacity: 0.15; z-index: 0; border-radius: 50%; transition: opacity 0.5s; }
            .candidato-photo-wrapper:hover .photo-glow { opacity: 0.3; }
            
            .candidato-photo { 
                position: relative; z-index: 1; width: 100%; border-radius: 1.5rem; box-shadow: 0 15px 40px rgba(0,0,0,0.5); 
                border: 4px solid transparent; /* Grosor del borde animado */
                background-image: linear-gradient(#801039, #801039), conic-gradient(from var(--gradient-angle, 0deg), #801039 0%, #ffc300 20%, #fff 25%, #ffc300 30%, #801039 50%, #801039 50%, #ffc300 70%, #fff 75%, #ffc300 80%, #801039 100%);
                background-clip: padding-box, border-box;
                background-origin: padding-box, border-box;
                animation: border-rotate 4s linear infinite;
            }
            .candidato-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top center; display: block; border-radius: calc(1.5rem - 4px); }
            .photo-badge { position: absolute; top: 1.5rem; left: -1rem; background: #ffc300; color: #801039; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: 8px; z-index: 3; box-shadow: 0 6px 20px rgba(0,0,0,0.4); text-transform: uppercase; transform: rotate(-4deg); border: 2px solid #fff; }
            
            .candidate-top-info { flex: 1; color: #fff; display: flex; flex-direction: column; justify-content: flex-start; }
            .candidate-top-info h2 { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-size: clamp(2.5rem, 4vw, 4rem); color: #ffc300; text-transform: uppercase; margin: 0 0 1rem 0; line-height: 1.1; }
            
            .candidate-badges { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
            .badge { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,195,0,0.4); color: #ffc300; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; text-transform: uppercase; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; letter-spacing: 1px; backdrop-filter: blur(5px); }
            
            .candidate-quote { position: relative; border-left: 2px solid #ffc300; padding: 2rem 2.5rem 2rem 4rem; color: #fff; margin: 2.5rem 0 3.5rem 0; font-size: 1.4rem; line-height: 1.6; background: linear-gradient(90deg, rgba(255,195,0,0.15), transparent); border-radius: 0 1.5rem 1.5rem 0; }
            .candidate-quote::before { content: '"'; position: absolute; left: 1rem; top: -0.5rem; font-family: Georgia, serif; font-size: 6rem; color: rgba(255, 195, 0, 0.4); line-height: 1; }
            .candidate-quote p { font-style: italic; margin: 0 0 1rem 0; font-weight: 300; }
            .quote-author { display: block; font-size: 0.9rem; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; color: #ffc300; text-transform: uppercase; letter-spacing: 1px; opacity: 0.95; }
            
            .info-block { margin-bottom: 4rem; }
            .block-title { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-size: 1.2rem; color: #ffc300; margin-bottom: 1.8rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; }
            
            .proposals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
            .proposal-card { background: rgba(255,255,255,0.02); padding: 2rem; border-radius: 1.2rem; border: 1px solid rgba(255,255,255,0.05); transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); position: relative; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
            .proposal-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #ffc300; transform: scaleX(0); transform-origin: left; transition: transform 0.4s ease; }
            .proposal-card:hover { transform: translateY(-6px); border-color: rgba(255,195,0,0.5); background: rgba(255,195,0,0.06); box-shadow: 0 12px 30px rgba(0,0,0,0.35); }
            .proposal-card:hover::before { transform: scaleX(1); }
            .proposal-icon { font-size: 2.8rem; margin-bottom: 1.5rem; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); transform-origin: center bottom; }
            .proposal-card:hover .proposal-icon { transform: scale(1.1) translateY(-2px); }
            .proposal-card h6 { color: #ffc300; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 1.1rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px; }
            .proposal-card p { font-size: 0.95rem !important; margin: 0; line-height: 1.7 !important; color: #bbb !important; font-weight: 300; flex-grow: 1; }
            
            .timeline { border-left: 2px solid rgba(255,195,0,0.2); padding-left: 2.5rem; margin-left: 1rem; display: flex; flex-direction: column; }
            .timeline-item { position: relative; padding-bottom: 2.5rem; }
            .timeline-item:last-child { padding-bottom: 0; }
            .timeline-item::before { content: ''; position: absolute; left: -3.05rem; top: 0.2rem; width: 18px; height: 18px; background: #801039; border: 4px solid #ffc300; border-radius: 50%; box-shadow: 0 0 15px rgba(255,195,0,0.4); transition: all 0.3s; }
            .timeline-item:hover::before { transform: scale(1.3); background: #ffc300; box-shadow: 0 0 20px rgba(255,195,0,0.8); }
            .timeline-year { color: #ffc300; font-weight: 900; font-size: 1.25rem; margin-bottom: 0.6rem; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; letter-spacing: 1px; }
            
            /* --- Diseño Fijo de Galería (Lado Derecho) --- */
            .timeline-item { position: relative; padding-bottom: 2.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
            .timeline-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
            .timeline-body { position: relative; display: flex; flex-direction: row; justify-content: flex-start; align-items: center; gap: 2rem; width: 100%; }
            .timeline-content-left { display: flex; flex-direction: column; align-items: flex-start; gap: 0.8rem; }
            .timeline-text { color: #bbb; line-height: 1.8; font-size: 1.05rem; margin: 0; font-weight: 300; max-width: 50ch; }
            
            .mobile-gallery-btn { display: none; margin-top: 0.4rem; background: rgba(255,195,0,0.1); border: 1px solid rgba(255,195,0,0.4); color: #ffc300; padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.75rem; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; text-transform: uppercase; cursor: pointer; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
            .mobile-gallery-btn:hover { background: #ffc300; color: #801039; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(255,195,0,0.2); }
            .btn-ver-galeria { background: rgba(255,195,0,0.08); border: 1px solid rgba(255,195,0,0.3); color: #ffc300; padding: 0.6rem 1.4rem; border-radius: 50px; font-size: 0.85rem; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.6rem; z-index: 2; }
            .btn-ver-galeria:hover { background: #ffc300; color: #801039; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(255,195,0,0.3); }
            
            /* --- Estilos del Carrusel de la Línea de Tiempo --- */
            .timeline-carousel-wrapper { width: 340px; height: 200px; flex-shrink: 0; border-radius: 12px; overflow: hidden; position: relative; box-shadow: 0 15px 40px rgba(0,0,0,0.4); margin: 0 auto; }
            .timeline-carousel-content { display: flex; height: 100%; width: 100%; }
            .timeline-carousel-item { width: 100%; height: 100%; flex-shrink: 0; position: relative; cursor: zoom-in; }
            .timeline-carousel-item img { width: 100%; height: 100%; object-fit: cover; }
            .timeline-carousel-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 10; width: 44px; height: 44px; border-radius: 50%; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; backdrop-filter: blur(5px); }
            .timeline-carousel-nav:hover { background: #ffc300; transform: translateY(-50%) scale(1.1); }
            .timeline-carousel-nav svg { stroke: #fff; width: 24px; height: 24px; transition: stroke 0.3s; }
            .timeline-carousel-nav:hover svg { stroke: #801039; }
            .timeline-carousel-nav.prev { left: 12px; }
            .timeline-carousel-nav.next { right: 12px; }

            /* --- Contenedor de Facebook (Widget 2 Columnas) --- */
            .facebook-layout-grid { display: grid; grid-template-columns: 1fr auto; gap: 3rem; align-items: center; background: rgba(255,255,255,0.02); padding: 2.5rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.05); margin-top: 1rem; overflow: hidden; }
            .facebook-text { display: flex; flex-direction: column; gap: 1.2rem; align-items: flex-start; padding: 0; }
            .facebook-text h3 { color: #ffc300; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 1.8rem; margin: 0; text-transform: uppercase; line-height: 1.1; }
            .facebook-text p { color: #bbb; font-size: 1.05rem; line-height: 1.6; margin: 0 0 1rem 0; max-width: 40ch; font-weight: 300; }
            .fb-widget-container { display: flex; justify-content: center; background: transparent; padding: 0; border: none; overflow: hidden; width: 500px; max-width: 100%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); }
            .fb-widget-container iframe { width: 100% !important; max-width: 100%; background: #fff; border-radius: 8px; display: block; }

            /* --- Estilos del Popup / Lightbox --- */
            .fuerza-lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.4s ease; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); }
            .fuerza-lightbox.active { opacity: 1; pointer-events: auto; }
            .fuerza-lightbox-close { position: absolute; top: 30px; right: 40px; font-size: 2.5rem; color: #ffc300; cursor: pointer; background: none; border: none; padding: 0; line-height: 1; transition: transform 0.3s; outline: none; }
            .fuerza-lightbox-close:hover { transform: scale(1.2) rotate(90deg); }
            .fuerza-lightbox-img { max-width: 90vw; max-height: 65vh; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 60px rgba(0,0,0,0.8); object-fit: contain; animation: zoomIn 0.4s ease; }
            .fuerza-lightbox-thumbs { display: flex; gap: 15px; margin-top: 30px; max-width: 90vw; overflow-x: auto; padding: 10px 5px; }
            .fuerza-lightbox-thumbs img { width: 100px; height: 70px; object-fit: cover; border-radius: 8px; cursor: pointer; opacity: 0.4; transition: all 0.3s; border: 2px solid transparent; flex-shrink: 0; }
            .fuerza-lightbox-thumbs img:hover, .fuerza-lightbox-thumbs img.active { opacity: 1; border-color: #ffc300; transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.4); }
            @keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            
            .candidate-actions { display: flex; gap: 1rem; margin-top: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; width: 100%; justify-content: center; }
            .action-btn { padding: 0.8rem 1.8rem; border-radius: 50px; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 0.85rem; text-transform: uppercase; text-decoration: none !important; transition: all 0.3s ease; cursor: pointer; text-align: center; letter-spacing: 1px; }
            .action-btn.primary { background: #ffc300; color: #801039 !important; border: 2px solid #ffc300; box-shadow: 0 6px 20px rgba(255,195,0,0.4); }
            .action-btn.primary:hover { background: #fff; border-color: #fff; transform: translateY(-3px); box-shadow: 0 10px 25px rgba(255,255,255,0.5); }
            .action-btn.outline { background: rgba(255,255,255,0.02); color: #ffc300 !important; border: 2px solid #ffc300; }
            .action-btn.outline:hover { background: rgba(255,195,0,0.15); transform: translateY(-3px); }

            .next-candidate-module { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; padding: 1rem 1.5rem; cursor: pointer; transition: all 0.4s ease; margin-top: 1rem; text-decoration: none; }
            .next-candidate-module:hover { background: rgba(0,0,0,0.4); border-color: rgba(255,195,0,0.6); transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
            .next-candidate-info { display: flex; align-items: center; gap: 1.2rem; }
            .next-candidate-avatar { width: 50px; height: 50px; border-radius: 50%; border: 2px solid #ffc300; object-fit: cover; }
            .next-candidate-text h5 { color: rgba(255,255,255,0.6); font-size: 0.75rem; text-transform: uppercase; margin: 0 0 0.2rem 0; letter-spacing: 1px; }
            .next-candidate-text h3 { color: #ffc300; margin: 0; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 1.1rem; text-transform: uppercase; }
            .next-candidate-arrow { color: #ffc300; transition: transform 0.3s ease; display: flex; align-items: center; }
            .next-candidate-module:hover .next-candidate-arrow { transform: translateX(10px); }
            
            @media (max-width: 991px) {
                #detalle-candidato-wrapper { margin-top: -5vh !important; padding-top: 3.5rem !important; padding-bottom: 2rem !important; }
                .candidato-detalle-container { flex-direction: column; }
                .candidato-sidebar { flex-direction: row; width: 100%; overflow-x: auto; padding-bottom: 1rem; position: static; gap: 0.8rem; }
                .mini-card { width: 70px; height: 90px; flex-shrink: 0; }
                .mini-card.active, .mini-card:hover { transform: scale(1.05) translateY(-5px); }
                .candidato-content { padding: 1.2rem; border-radius: 1.5rem; }
                .candidato-content p.stagger-el { font-size: 0.85rem !important; line-height: 1.4 !important; margin-bottom: 1rem !important; }
                .candidate-top-row { flex-direction: column; gap: 1rem; margin-bottom: 1rem; }
                .candidato-photo-wrapper { width: 100%; max-width: 250px; margin: 0 auto; }
                .photo-badge { left: 0; top: 0.5rem; }
                .candidate-top-info h2 { font-size: 1.8rem; text-align: center; margin-bottom: 0.5rem; }
                .candidate-badges { justify-content: center; gap: 0.5rem; margin-bottom: 1rem; }
                .candidate-top-info { width: 100%; text-align: left; }
                .timeline { padding-left: 1.5rem; margin-left: 0.2rem; }
                .timeline-item { padding-bottom: 1.2rem; margin-bottom: 1rem; }
                .timeline-item::before { width: 14px; height: 14px; left: -1.95rem; top: 0.1rem; border-width: 2px; }
                .timeline-year { font-size: 1.1rem; margin-bottom: 0.3rem; }
                .timeline-text { font-size: 0.85rem; line-height: 1.4; max-width: 100%; }
                .timeline-body { flex-direction: column; align-items: flex-start; gap: 0.6rem; }
                .mobile-gallery-btn { margin-top: 0; padding: 0.4rem 1rem; font-size: 0.7rem; }
                .timeline-carousel-wrapper { display: none !important; /* Ocultamos los pesados carruseles en móvil */ }
                .mobile-gallery-btn { display: inline-flex !important; /* Activamos los botones rápidos en móvil */ }
                .next-candidate-module { flex-direction: column; text-align: center; gap: 0.8rem; padding: 0.8rem; margin-top: 1rem; }
                .next-candidate-arrow { transform: rotate(90deg); }
                .next-candidate-module:hover .next-candidate-arrow { transform: rotate(90deg) translateY(6px); }
                .facebook-layout-grid { grid-template-columns: 1fr; gap: 1rem; padding: 1.5rem 0 0 0; text-align: center; margin-top: 0.5rem; overflow: hidden; border-radius: 1rem; }
                .facebook-text { align-items: center; gap: 0.6rem; }
                .facebook-text p { max-width: 100%; font-size: 0.85rem; line-height: 1.3; }
                .fb-widget-container { width: 100%; max-width: 100%; margin: 0 auto; border-radius: 0; overflow: hidden; }
                .fb-widget-container iframe { width: 100% !important; max-width: 100%; border-radius: 0; }
                .proposals-grid { grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
                .proposal-card { padding: 0.8rem; border-radius: 0.6rem; }
                .proposal-icon { font-size: 1.5rem; margin-bottom: 0.4rem; }
                .proposal-card h6 { font-size: 0.75rem; margin-bottom: 0.2rem; }
                .proposal-card p { font-size: 0.65rem !important; line-height: 1.2 !important; }
                .candidate-quote { margin: 1rem 0; padding: 0.8rem 0.8rem 0.8rem 2rem; font-size: 0.95rem; }
                .candidate-quote::before { font-size: 3rem; left: 0.3rem; }
            }
            
            /* Contenedor invisible por defecto para animarlo al estilo "fade-right" */
            .candidato-detalle-container { opacity: 0; }
            
            /* Botón Cerrar Detalles - Relative to push content down and not overlap */
            .close-detail-btn { 
                position: relative; 
                display: block; 
                margin: 0 0 1rem auto; /* Aligned right, pushes content down */
                background: rgba(255, 195, 0, 0.15); 
                color: #ffc300; 
                padding: 0.6rem 1.2rem; 
                border-radius: 50px; 
                font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; 
                font-size: 0.85rem; 
                cursor: pointer; 
                border: 1px solid rgba(255, 195, 0, 0.4); 
                transition: all 0.3s ease; 
                z-index: 100; 
                backdrop-filter: blur(10px); 
            }
            .close-detail-btn:hover { background: #ffc300; color: #801039; transform: scale(1.05); box-shadow: 0 5px 15px rgba(255,195,0,0.3); }
            @media (max-width: 991px) {
                .close-detail-btn { margin-bottom: 0.8rem; padding: 0.5rem 1rem; font-size: 0.75rem; }
            }

            /* --- ESTILOS FORZADOS DEL POPUP (MODAL) TIMELINE --- */
            .timeline-modal-overlay {
                position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85) !important;
                backdrop-filter: blur(10px) !important; -webkit-backdrop-filter: blur(10px) !important;
                z-index: 999999 !important; display: flex; align-items: center; justify-content: center;
                opacity: 0; pointer-events: none; transition: opacity 0.4s ease;
            }
            .timeline-modal-overlay.active { opacity: 1; pointer-events: auto; }
            .timeline-modal-content {
                background: linear-gradient(135deg, #801039, #4a051f) !important;
                border: 2px solid rgba(255, 195, 0, 0.6) !important; border-radius: 20px !important;
                padding: 4rem 3rem !important; width: 95% !important; max-width: 1100px !important; min-height: 65vh !important; position: relative !important;
                transform: scale(0.9) translateY(20px); transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
                box-shadow: 0 25px 50px rgba(0,0,0,0.5), inset 0 0 40px rgba(255,195,0,0.1) !important;
                color: #fff !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
                display: flex !important; flex-direction: column !important; justify-content: center !important; align-items: center !important;
            }
            .timeline-modal-layout { display: flex !important; width: 100% !important; gap: 3rem !important; align-items: center !important; justify-content: center !important; text-align: left !important; }
            .timeline-modal-left { flex: 1 !important; display: flex !important; justify-content: center !important; align-items: center !important; }
            .timeline-modal-right { flex: 1 !important; display: flex !important; flex-direction: column !important; justify-content: center !important; }
            .timeline-modal-left img { width: 100% !important; max-height: 60vh !important; object-fit: cover !important; border-radius: 15px !important; box-shadow: 0 15px 35px rgba(0,0,0,0.4) !important; border: 2px solid rgba(255, 195, 0, 0.3) !important; margin: 0 !important; }
            .timeline-modal-overlay.active .timeline-modal-content { transform: scale(1) translateY(0); }
            .timeline-modal-close {
                position: absolute !important; top: 15px !important; right: 20px !important; background: transparent !important; border: none !important;
                color: #ffc300 !important; font-size: 1.8rem !important; cursor: pointer; transition: transform 0.3s ease; outline: none; z-index: 10 !important;
            }
            .timeline-modal-close:hover { transform: scale(1.2) rotate(90deg); color: #fff !important; }
            .timeline-modal-year { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-size: 4rem !important; color: #ffc300 !important; margin: 0 0 1.5rem 0 !important; text-transform: uppercase !important; letter-spacing: 2px !important; text-shadow: 0 4px 10px rgba(0,0,0,0.5) !important; line-height: 1 !important; }
            .timeline-modal-text { font-family: Arial, sans-serif !important; font-size: 1.3rem !important; line-height: 1.8 !important; color: rgba(255, 255, 255, 0.95) !important; max-width: 900px !important; }
            .timeline-modal-text p { margin-bottom: 1rem !important; }
            .timeline-modal-text strong { color: #ffc300 !important; }
            .timeline-modal-content::-webkit-scrollbar { width: 8px; }
            .timeline-modal-content::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; margin: 15px 0; }
            .timeline-modal-content::-webkit-scrollbar-thumb { background: rgba(255,195,0,0.5); border-radius: 10px; }
            .timeline-modal-content::-webkit-scrollbar-thumb:hover { background: rgba(255,195,0,0.8); }
            @media (max-width: 991px) {
                .timeline-modal-layout { flex-direction: column !important; gap: 1.5rem !important; text-align: center !important; }
                .timeline-modal-left img { max-height: 40vh !important; }
                .timeline-modal-right { align-items: center !important; }
            }
            
            /* --- ESTILOS SECCION SUMATE A LA FUERZA --- */
            html:has(main[data-barba-namespace="sumate-section"]), body:has(main[data-barba-namespace="sumate-section"]) { background-color: #000 !important; }
            main[data-barba-namespace="sumate-section"] { background-color: #000 !important; min-height: 100vh; }
            main[data-barba-namespace="sumate-section"]::before { display: none !important; }
            main[data-barba-namespace="sumate-section"] footer { background-color: #000 !important; margin-top: 0 !important; padding-top: 50px; padding-bottom: 4000px !important; margin-bottom: -3900px !important; }
            main[data-barba-namespace="sumate-section"] footer #legal-notice { color: #fff !important; opacity: 0.7; }
            #sumate-hyperspace-section { position: relative; width: 100%; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 150px 20px 50px; overflow: hidden; background: #000; background-image: radial-gradient(white, rgba(255,255,255,.2) 2px, transparent 4px), radial-gradient(white, rgba(255,255,255,.15) 1px, transparent 3px), radial-gradient(white, rgba(255,255,255,.1) 2px, transparent 4px), radial-gradient(rgba(255,255,255,.4), rgba(255,255,255,.1) 2px, transparent 3px); background-size: 550px 550px, 350px 350px, 250px 250px, 150px 150px; background-position: 0 0, 40px 60px, 130px 270px, 70px 100px; animation: starScroll 100s linear infinite; }
            @keyframes starScroll { from { background-position: 0 0, 40px 60px, 130px 270px, 70px 100px; } to { background-position: 0 10000px, 40px 10060px, 130px 10270px, 70px 10100px; } }
            .form-container { position: relative; z-index: 10; width: 100%; max-width: 800px; display: flex; flex-direction: column; align-items: center; gap: 2rem; animation: float-in 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.5s forwards; opacity: 0; margin-bottom: 2rem; }
            @keyframes float-in { from { opacity: 0; transform: translateY(50px); } to { opacity: 1; transform: translateY(0); } }
            .sumate-title { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-weight: 900; font-size: clamp(2rem, 5vw, 3.5rem); color: #fff; text-align: center; line-height: 1.2; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 0 15px rgba(255, 195, 0, 0.8), 0 0 30px rgba(255, 195, 0, 0.6), 0 0 45px rgba(255, 195, 0, 0.4); margin: 0; }
            .form-wrapper { width: 100%; height: 3400px; background: rgba(20, 20, 30, 0.8); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 195, 0, 0.4); border-radius: 15px; box-shadow: 0 0 25px rgba(255, 195, 0, 0.2), 0 10px 40px rgba(0,0,0,0.8); padding: 10px; animation: levitate 6s ease-in-out infinite; }
            @keyframes levitate { 0% { transform: translateY(0px); } 50% { transform: translateY(-15px); } 100% { transform: translateY(0px); } }
            .form-wrapper iframe { width: 100%; height: 100%; border-radius: 10px; background: transparent; }
            @media (max-width: 768px) { #sumate-hyperspace-section { padding: 120px 15px 40px; } .form-wrapper { height: 4000px; } }

            /* --- Ajustes Visuales Chat IA (Scroll y Orden Inferior) --- */
            #ft-chat-container.ft-chat-open { pointer-events: auto !important; }
            #ft-chat-messages { 
                overflow-y: auto !important; 
                display: flex !important; 
                flex-direction: column !important; 
                overscroll-behavior: contain; 
                scroll-behavior: smooth;
            }
            #ft-chat-messages > :first-child { margin-top: auto !important; }
            #ft-chat-messages::-webkit-scrollbar { width: 6px; }
            #ft-chat-messages::-webkit-scrollbar-track { background: transparent; }
            #ft-chat-messages::-webkit-scrollbar-thumb { background-color: rgba(128, 16, 57, 0.3); border-radius: 10px; }
            #ft-chat-messages::-webkit-scrollbar-thumb:hover { background-color: rgba(128, 16, 57, 0.6); }
        `;
        document.head.appendChild(style);
    }

    // Inyectar HTML del Loader si refrescas en una página que no lo tiene
    if (!document.querySelector('.initial-loader')) {
        const loaderDiv = document.createElement('div');
        loaderDiv.className = 'initial-loader';
        
        // Detectar si es una recarga manual (F5) para limpiar la memoria
        let isReload = false;
        if (window.performance) {
            if (performance.getEntriesByType && performance.getEntriesByType("navigation").length > 0) isReload = performance.getEntriesByType("navigation")[0].type === "reload";
            else if (performance.navigation && performance.navigation.type === 1) isReload = true;
        }
        // ELIMINADO: Ya no obligamos al usuario a comerse la intro de 5s en cada recarga
        // if (isReload) sessionStorage.removeItem('fuerzaTacnaLoaderPlayed');

        // Ocultar antes de inyectar si ya se reprodujo, evita el pestañeo
        if (sessionStorage.getItem('fuerzaTacnaLoaderPlayed')) {
            loaderDiv.style.display = 'none';
        }
        loaderDiv.innerHTML = `
            <div class="loader-h1">FUERZA<br>TACNA</div>
            <div class="water-layer"><div class="water-content"><div class="loader-h1">FUERZA<br>TACNA</div></div></div>
            <div class="wave"></div>
        `;
        document.body.insertBefore(loaderDiv, document.body.firstChild);
    }

    // --- AUTO INYECCIÓN DEL ASISTENTE IA ---
    if (!document.getElementById('ft-chat-container')) {
        // 1. Inyectar CSS
        const chatCSS = document.createElement('link');
        chatCSS.rel = 'stylesheet';
        chatCSS.href = 'assets/universoobras/chat-ia.css?v=4'; // Aesthetic UI update
        document.head.appendChild(chatCSS);

        // 2. Inyectar HTML
        const chatDiv = document.createElement('div');
        chatDiv.id = 'ft-chat-container';
        chatDiv.className = 'ft-chat-closed';
        chatDiv.style.cssText = 'opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.5s ease;'; // Previene FOUC
        chatDiv.innerHTML = `
            <button id="ft-chat-fab" aria-label="Abrir Asistente IA">
                <div class="ft-fab-avatar">🐻</div>
            </button>
            <div id="ft-chat-window">
                <div class="ft-chat-header">
                    <div class="ft-chat-title">
                        <div class="ft-header-avatar">🐻</div>
                        <div class="ft-header-info">
                            <span>Luchito IA</span>
                            <small><span class="ft-online-dot"></span> En línea</small>
                        </div>
                    </div>
                    <button id="ft-chat-close" aria-label="Cerrar chat"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                <div class="ft-chat-body" id="ft-chat-messages">
                    <div class="ft-message ai-message">
                        <div class="ft-avatar">🤖</div>
                        <div class="ft-bubble">¡Hola! Soy Luchito, el asistente inteligente de <strong>Fuerza Tacna</strong>. ¿En qué te puedo ayudar hoy?</div>
                    </div>
                </div>
                <div class="ft-chat-footer">
                    <div class="ft-input-wrapper">
                        <input type="text" id="ft-chat-input" placeholder="Pregúntale a Luchito..." autocomplete="off">
                        <button id="ft-chat-send" aria-label="Enviar mensaje"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg></button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(chatDiv);

        // 3. Inyectar JS
        const chatJS = document.createElement('script');
        chatJS.src = 'assets/universoobras/chat-ia.js?v=4'; // Aesthetic UI update
        document.body.appendChild(chatJS);
    }
}
injectGlobalAssets(); // Se ejecuta inmediatamente al cargar el JS

function delay(n) {
    n = n || 2000;
    return new Promise((done) => {
        setTimeout(() => {
            done();
        }, n);
    });
}

function pageTransition() {
    var tl = new TimelineMax(); 
    tl.set(".loading-screen", { yPercent: 100, top: 0, bottom: "auto", height: "100%" });
    tl.to(".loading-screen", 1.2, {
        width: "100%",
        yPercent: 0,
        ease: "Expo.easeInOut",
    });

    tl.to(".loading-screen", 1, {
        width: "100%",
        yPercent: -100,
        ease: "Expo.easeInOut",
        delay: 0.5,
    });
    tl.set(".loading-screen", { yPercent: 100 });
}

function contentAnimation() {
    var tl = new TimelineMax();
    tl.staggerFrom(".animate-this", 1, { opacity: 0, delay: 0.2 }, 0.4);
}

function initialLoadAnimation() {
    const loader = document.querySelector('.initial-loader');
    if (!loader) return;

    try {
        var tl = new TimelineMax();
        
        tl.to({}, 0.2, {});

        // VOLVEMOS a la sintaxis clásica porque la web sigue usando GSAP 2 en el fondo
        tl.to(".water-layer", 4.8, { height: "100%", ease: "Power1.easeInOut" }, "waveStart");
        tl.to(".wave", 4.8, { bottom: "100%", ease: "Power1.easeInOut" }, "waveStart");

        tl.to(".wave", 1.6, {
            rotation: 2,
            transformOrigin: "center bottom",
            ease: "Sine.easeInOut",
            yoyo: true,
            repeat: 2 
        }, "waveStart");

        tl.to({}, 0.5, {});

        tl.to(".initial-loader", 0.8, {
            opacity: 0,
            ease: "Power2.easeInOut",
            onComplete: () => {
                document.querySelector('.initial-loader').style.display = 'none';
            }
        });

        tl.staggerFrom(".animate-this", 1, { opacity: 0 }, 0.4, "-=0.4");
    } catch (e) {
        console.error("Error en animación GSAP, cerrando loader a la fuerza:", e);
        if (loader) loader.style.display = 'none';
    }
}

document.addEventListener("DOMContentLoaded", function () {
    AOS.init({ duration: 1000, easing: 'ease', once: true });
    
    barba.init({
        sync: false, // FIX: Espera que el telón cubra la pantalla antes de inyectar la nueva página

        transitions: [
            {
                async leave(data) {
                    const done = this.async();

                    if (typeof window.obrasCleanup === 'function') {
                        window.obrasCleanup();
                    }

                        // --- LIMPIEZA DE TARJETAS FANTASMAS (TOOLTIPS) ---
                        // Evita que el cuadrito negro del mapa SVG se quede pegado al cambiar de menú
                        const svgTooltip = document.getElementById('map-tooltip');
                        if (svgTooltip) svgTooltip.classList.remove('visible');
                        
                        // Limpieza de seguridad para los pines del mapa de Universo de Obras
                        document.querySelectorAll('.maplibregl-popup, .ghost-card-popup').forEach(el => el.remove());

                    pageTransition();
                await delay(1200);
                    done();
                },

                async enter(data) {
                    // Forzar que la pantalla vuelva arriba al entrar a la nueva vista
                    if (window.scroller && typeof window.scroller.setPostion === 'function') {
                        window.scroller.setPostion(0);
                    }
                    window.scrollTo(0, 0);
                    contentAnimation();
                },

                async once(data) {
                    // Si la capa inicial existe en el HTML, corre la animación épica. 
                    // Si no existe, se salta a la animación por defecto.
                    if (document.querySelector('.initial-loader') && !sessionStorage.getItem('fuerzaTacnaLoaderPlayed')) {
                        sessionStorage.setItem('fuerzaTacnaLoaderPlayed', 'true');
                        window.isFirstLoadAnimationRunning = true;
                        setTimeout(() => window.isFirstLoadAnimationRunning = false, 6500); // Semáforo de seguridad
                        initialLoadAnimation();
                    } else {
                        contentAnimation();
                    }
                },
            },
        ],
    });
});

async function initCandidatos(container) {
    // Apuntamos estrictamente al nuevo contenedor de Barba para evitar atrapar el DOM de la página vieja
    const target = container || document;

    // Limpiamos la animación previa en caso de que exista tras un cambio de página
    if (window.marqueeAnimFrame) {
        cancelAnimationFrame(window.marqueeAnimFrame);
    }

    // Limpiar eventos globales previos del ratón/táctil para evitar fugas de memoria
    if (window.marqueeCleanup) {
        window.marqueeCleanup();
    }

    // Buscamos los contenedores primero en el target, y si fallan, en todo el documento
    const marqueeContent = target.querySelector('.marquee-content') || document.querySelector('.marquee-content');
    const marqueeContainer = target.querySelector('.marquee-container') || document.querySelector('.marquee-container');
    
    if(!marqueeContent || !marqueeContainer) {
        // Retornamos silenciosamente si la página actual no tiene el carrusel de candidatos
        return;
    }

    // --- NUEVO: FASE 5 - OBTENER CANDIDATOS DESDE LA BASE DE DATOS ---
    if (marqueeContent.getAttribute('data-loaded') !== 'true') {
        // 1. Limpiamos INMEDIATAMENTE las tarjetas estáticas viejas antes de hacer el fetch.
        // Esto evita el "pestañeo" de los candidatos que ya habías borrado en el panel.
        marqueeContent.innerHTML = '';

        try {
            // Llamamos a la API que construimos en la Fase 1
            const resp = await fetch('assets/panel-admin-universo/api_candidatos.php?action=listar');
            const data = await resp.json();
            
            if (data.ok && data.candidatos && data.candidatos.length > 0) {
                // Filtramos solo los visibles y los guardamos en memoria global
                window.CANDIDATOS_LIST = data.candidatos.filter(c => c.estado == 1);
                
                window.CANDIDATOS_LIST.forEach(c => {
                    const fotoUrl = c.foto_perfil ? `assets/universoobras/IMG/candidatos/${c.foto_perfil}` : 'https://via.placeholder.com/400';
                    const fotoHover = c.foto_portada ? `assets/universoobras/IMG/candidatos/${c.foto_portada}` : fotoUrl;
                    marqueeContent.innerHTML += `
                        <div class="candidate-card" data-id="${c.id}">
                        <img src="${fotoUrl}" alt="${c.nombres}" class="img-default">
                        <img src="${fotoHover}" alt="${c.nombres}" class="img-hover">
                            <div class="candidate-info">
                                <h3>${c.nombres}</h3>
                                <p>${c.cargo_flotante}</p>
                            </div>
                        </div>
                    `;
                });
                marqueeContent.setAttribute('data-loaded', 'true');
            }
        } catch (err) {
            console.error("Error al cargar candidatos de la BD:", err);
        }
    }

    // Le damos un pequeño respiro al navegador de 150ms para que renderice los tamaños y CSS del nuevo HTML
    setTimeout(() => {
        // Forzamos visibilidad absoluta por si AOS u otra librería dejó el bloque oculto en opacity: 0
        marqueeContainer.style.opacity = '1';
        marqueeContainer.style.visibility = 'visible';
        marqueeContent.style.opacity = '1';
        marqueeContent.style.visibility = 'visible';

        // Solo seleccionamos las tarjetas
        let cards = marqueeContent.querySelectorAll('.candidate-card');
        if(cards.length === 0) return;

        // Auto-duplicar tarjetas por JS (Asegura que la matemática del loop infinito nunca falle)
        if (marqueeContent.getAttribute('data-cloned') !== 'true') {
            const originalCards = Array.from(cards);
            marqueeContent.setAttribute('data-original-count', originalCards.length);
            
            // Si hay muy pocos candidatos (ej. 1 o 2), clonamos varias veces para llenar la pantalla y que no se vea vacío
            const multiplier = Math.max(2, Math.ceil(8 / originalCards.length));
            for (let m = 1; m < multiplier; m++) {
                originalCards.forEach(card => {
                    const clone = card.cloneNode(true);
                    marqueeContent.appendChild(clone);
                });
            }
            marqueeContent.setAttribute('data-cloned', 'true');
            cards = marqueeContent.querySelectorAll('.candidate-card'); // Actualizamos la lista
        }

        let dragDistance = 0; // <-- Rastreará de forma global cuánto se movió el ratón/dedo

        // --- 3D Hover Effect ---
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const rotateX = ((y - rect.height / 2) / (rect.height / 2)) * -8;
                const rotateY = ((x - rect.width / 2) / (rect.width / 2)) * 8;

                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
                card.style.transition = "transform 0.1s ease-out";
                card.style.zIndex = "50"; 
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`;
                card.style.transition = "transform 0.4s ease-in-out";
                card.style.zIndex = "1";
            });

            // --- Lógica de Clic Nativo e Infalible ---
            card.addEventListener('click', (e) => {
                // Si se arrastró el carrusel más de 15px, anulamos el clic (es un arrastre)
                if (dragDistance > 15) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                
                // Ahora le pasamos el ID real de la base de datos a la función del detalle
                const cid = card.getAttribute('data-id');
                console.log("✅ Clic VÁLIDO en la tarjeta del candidato ID:", cid);
                if (cid) window.showCandidateDetail(cid);
            });
        });

        // --- Lógica de Arrastre y Auto-Scroll ---
        let isDragging = false;
        let startX;
        let currentX = 0;
        let isHovered = false;
        const speed = 1; // Velocidad del movimiento automático
        
        const getX = (e) => e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        
        function animateMarquee() {
            if (!isDragging && !isHovered) {
                currentX -= speed;
            }
            
            const cardWidth = cards[0].offsetWidth;
            
            // Blindaje contra "NaN" si la página inyectada aún no renderiza anchos
            if (cardWidth > 0) {
                const gap = parseFloat(window.getComputedStyle(marqueeContent).gap) || 24;
                const originalCount = parseInt(marqueeContent.getAttribute('data-original-count')) || 1;
                const loopWidth = (cardWidth + gap) * originalCount;
                
                // Usamos 'while' como blindaje matemático absoluto por si hay lags de rendimiento
                while (currentX <= -loopWidth) currentX += loopWidth;
                while (currentX > 0) currentX -= loopWidth;
                
                marqueeContent.style.transform = `translateX(${currentX}px)`;
            }
            
            window.marqueeAnimFrame = requestAnimationFrame(animateMarquee);
        }
        
        marqueeContainer.addEventListener('mouseenter', () => isHovered = true);
        marqueeContainer.addEventListener('mouseleave', () => isHovered = false); 
        
        const startDrag = (e) => { 
            isDragging = true; 
            dragDistance = 0; // Reiniciamos el contador de movimiento al tocar
            startX = getX(e); 
            marqueeContainer.style.cursor = 'grabbing'; 
        };
        const endDrag = () => { 
            isDragging = false; 
            marqueeContainer.style.cursor = 'grab'; 
            // Esperamos 100ms antes de resetear para que el clic lo pueda leer
            setTimeout(() => { dragDistance = 0; }, 100);
        };
        const moveDrag = (e) => { 
            if (!isDragging) return; 
            const currentPosition = getX(e); 
            dragDistance += Math.abs(currentPosition - startX); // Acumulamos distancia
            currentX += (currentPosition - startX) * 1.5; 
            startX = currentPosition; 
        };

        marqueeContainer.addEventListener('mousedown', (e) => { startDrag(e); }); // Eliminado el e.preventDefault() que bloqueaba clics
        marqueeContainer.addEventListener('touchstart', startDrag, { passive: true });
        window.addEventListener('mouseup', endDrag);
        window.addEventListener('touchend', endDrag);
        window.addEventListener('mousemove', moveDrag);
        window.addEventListener('touchmove', moveDrag, { passive: true });
        
        window.marqueeAnimFrame = requestAnimationFrame(animateMarquee);
        
        // Completar la función de limpieza prometida al inicio
        window.marqueeCleanup = () => {
            window.removeEventListener('mouseup', endDrag);
            window.removeEventListener('touchend', endDrag);
            window.removeEventListener('mousemove', moveDrag);
            window.removeEventListener('touchmove', moveDrag);
        };
    }, 150); // El retraso de 150ms garantiza que el CSS y anchos se apliquen primero
}

// --- LÓGICA DE DETALLE Y MINIATURAS ---
// Esta función es global para que pueda ser llamada desde los eventos onclick de las miniaturas
window.showCandidateDetail = async function(candidatoId) {
    const candidates = window.CANDIDATOS_LIST || [];
    const currentIndex = candidates.findIndex(c => c.id == candidatoId);
    
    if (currentIndex === -1) return;
    
    const nextCandidate = candidates[(currentIndex + 1) % candidates.length];

    // Petición al servidor para traer TODA la información profunda (Propuestas, Cronología, etc)
    let fullCandidato = null;
    try {
        const resp = await fetch(`assets/panel-admin-universo/api_candidatos.php?action=obtener&id=${candidatoId}`);
        const data = await resp.json();
        if (data.ok) fullCandidato = data.candidato;
    } catch(e) { console.error(e); }
    
    if (!fullCandidato) {
        alert("Error al cargar la información del candidato.");
        return;
    }
    
    let wrapper = document.getElementById('detalle-candidato-wrapper');
    const isFirstTime = !wrapper;
    
    if (isFirstTime) {
        wrapper = document.createElement('section');
        wrapper.id = 'detalle-candidato-wrapper';
        
        // Búsqueda ABSOLUTA: Extraer la sección de cualquier flexbox, incluso si el ID fue encontrado
        let currentElement = document.getElementById('candidatos-section') || document.querySelector('.marquee-container');
        
        if (currentElement) {
            // Subimos en el árbol HTML hasta encontrar el contenedor padre directo de <main> o <body>
            while (currentElement.parentElement && 
                   currentElement.parentElement.tagName !== 'MAIN' && 
                   currentElement.parentElement.tagName !== 'BODY') {
                currentElement = currentElement.parentElement;
            }
            
            // Insertamos justo después del bloque principal que contiene al carrusel
            if (currentElement.nextSibling) {
                currentElement.parentElement.insertBefore(wrapper, currentElement.nextSibling);
            } else {
                currentElement.parentElement.appendChild(wrapper);
            }
        } else {
            const mainTarget = document.querySelector('main') || document.body;
            mainTarget.appendChild(wrapper); // Fallback absoluto: al final de la página
        }
    }
    
    // 1. Construir Sidebar de Miniaturas
    let sidebarHTML = `<div class="candidato-sidebar animate-detail-element">`;
    candidates.forEach(c => {
        const isActive = c.id == candidatoId ? 'active' : '';
        const thumbUrl = c.foto_perfil ? `assets/universoobras/IMG/candidatos/${c.foto_perfil}` : 'https://via.placeholder.com/400';
        sidebarHTML += `
            <div class="mini-card ${isActive}" onclick="window.showCandidateDetail('${c.id}')">
                <img src="${thumbUrl}" alt="${c.nombres}" loading="lazy" decoding="async">
            </div>`;
    });
    sidebarHTML += `</div>`;
    
    const fotoPrincipal = fullCandidato.foto_perfil ? `assets/universoobras/IMG/candidatos/${fullCandidato.foto_perfil}` : 'https://via.placeholder.com/400';

    let badgesHTML = '';
    (fullCandidato.etiquetas || []).forEach(e => {
        badgesHTML += `<span class="badge">${e.icono} ${e.texto}</span>`;
    });

    let trayectoriaHTML = '';
    (fullCandidato.trayectoria || []).forEach(t => {
        trayectoriaHTML += `
            <div class="timeline-item stagger-el">
                <div class="timeline-year">${t.periodo}</div>
                <div class="timeline-body">
                    <div class="timeline-content-left">
                        <p class="timeline-text">${t.descripcion}</p>
                    </div>
                </div>
            </div>`;
    });
    if (!trayectoriaHTML) trayectoriaHTML = '<p class="text-muted" style="color:#bbb;">Aún no se ha registrado trayectoria.</p>';

    let propuestasHTML = '';
    (fullCandidato.propuestas || []).forEach(p => {
        propuestasHTML += `
            <div class="proposal-card stagger-el">
                <div class="proposal-icon">${p.icono || '✨'}</div>
                <h6>${p.titulo}</h6>
                <p>${p.descripcion}</p>
            </div>`;
    });
    if (!propuestasHTML) propuestasHTML = '<p class="text-muted" style="color:#bbb;">Aún no se han registrado propuestas.</p>';

    let fbHTML = '';
    if (fullCandidato.fb_url_perfil && fullCandidato.fb_url_perfil.trim() !== '') {
        fbHTML = `
            <div id="sec-facebook" class="info-block">
                <div class="block-title stagger-el">📱 Actividad Reciente</div>
                <div class="facebook-layout-grid stagger-el">
                    <div class="facebook-text">
                        <h3>${fullCandidato.fb_titulo || '¡Sigue mi campaña!'}</h3>
                        <p>${fullCandidato.fb_descripcion || 'Entérate de mis últimos recorridos y propuestas.'}</p>
                        <a href="${fullCandidato.fb_url_perfil}" target="_blank" class="action-btn primary" style="padding: 0.8rem 2rem; font-size: 0.85rem;">Ver Perfil Completo</a>
                    </div>
                    <div class="fb-widget-container">
                        <iframe src="https://www.facebook.com/plugins/page.php?href=${encodeURIComponent(fullCandidato.fb_url_perfil)}&tabs=timeline&width=340&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId" width="100%" height="500" style="border:none;overflow:hidden; max-width: 100%;" scrolling="no" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                    </div>
                </div>
            </div>`;
    }

    // 2. Construir Contenido Principal
    let contentHTML = `
        <div class="candidato-content animate-detail-element">
            <div id="sec-perfil" class="candidate-top-row">
                <div class="candidato-photo-wrapper stagger-el">
                    <div class="photo-glow"></div>
                    <div class="photo-badge">${fullCandidato.cargo_flotante || 'Candidato'}</div>
                    <div class="candidato-photo">
                        <img src="${fotoPrincipal}" alt="${fullCandidato.nombres}" loading="lazy" decoding="async">
                    </div>
                </div>
                <div class="candidate-top-info">
                    <h2 class="stagger-el">${fullCandidato.nombres}</h2>
                    
                    <div class="candidate-badges stagger-el">
                        ${badgesHTML}
                    </div>

                    <div class="candidate-quote stagger-el">
                        <p>"${fullCandidato.frase_cita || 'Fuerza Tacna'}"</p>
                        <span class="quote-author">— ${fullCandidato.nombres} | ${fullCandidato.cargo_flotante}</span>
                    </div>
                    
                    <p class="stagger-el" style="margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5; color: #ddd; max-width: 85ch;">
                        ${fullCandidato.biografia || ''}
                    </p>
                </div>
            </div>

            <div class="candidate-bottom-row">
                <div id="sec-trayectoria" class="info-block">
                    <div class="block-title stagger-el">⏱️ Trayectoria Profesional</div>
                    <div class="timeline">
                        ${trayectoriaHTML}
                    </div>
                </div>

                <div id="sec-propuestas" class="info-block">
                    <div class="block-title stagger-el">🚀 Ejes de Propuesta</div>
                    <div class="proposals-grid">
                        ${propuestasHTML}
                    </div>
                </div>
                
                ${fbHTML}

                <div class="candidate-actions stagger-el" style="justify-content: center; margin-bottom: 3.5rem;">
                    <a href="contacto.html" class="action-btn outline" style="padding: 1.2rem 3rem;">Contactar Candidato</a>
                </div>

                ${nextCandidate ? `
                <div class="stagger-el">
                    <div class="next-candidate-module" onclick="window.showCandidateDetail('${nextCandidate.id}')">
                        <div class="next-candidate-info">
                            <img src="${nextCandidate.foto_perfil ? `assets/universoobras/IMG/candidatos/${nextCandidate.foto_perfil}` : 'https://via.placeholder.com/100'}" alt="${nextCandidate.nombres}" class="next-candidate-avatar" loading="lazy" decoding="async">
                            <div class="next-candidate-text">
                                <h5>Siguiente Perfil</h5>
                                <h3>${nextCandidate.nombres}</h3>
                            </div>
                        </div>
                        <div class="next-candidate-arrow">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    wrapper.innerHTML = `
        <button class="close-detail-btn" onclick="window.closeCandidateDetail()">✖ CERRAR</button>
        <div class="candidato-detalle-container">${sidebarHTML}${contentHTML}</div>
    `;
    wrapper.style.display = 'block';
    
    // ¡NUEVO! Inicializar carruseles de la trayectoria que acaban de inyectarse dinámicamente
    initTimelineCarousels(wrapper);

    // --- Magia UX: Ocultar todas las secciones que están DEBAJO para no distraer ---
    const mainChildren = Array.from(document.querySelector('main').children);
    let hideMode = false;
    mainChildren.forEach(child => {
        // Usamos .contains por si el wrapper se insertó dentro de otro div por accidente
        if (child.id === 'detalle-candidato-wrapper' || child.contains(document.getElementById('detalle-candidato-wrapper'))) {
            hideMode = true; // Todo lo que esté después de este wrapper se ocultará temporalmente
        } else if (hideMode && child.tagName !== 'SCRIPT' && child.tagName !== 'STYLE' && child.id !== 'hero-header') {
            if (!child.classList.contains('hidden-by-detail')) {
                child.setAttribute('data-original-visibility', child.style.visibility || '');
                child.style.visibility = 'hidden';
                child.classList.add('hidden-by-detail');
            }
        }
    });

    // Ocultar a Lucho en la versión celular al abrir el detalle
    const luchoContainer = document.querySelector('.lucho-candidatos-container');
    if (luchoContainer) {
        luchoContainer.style.display = 'none';
    }

    // 3. Animación de Entrada Fluida y en Cascada (Stagger)
    
    TweenMax.fromTo(wrapper.querySelector('.candidato-detalle-container'), 
        0.6,
        { opacity: 0, x: -50 }, 
        { opacity: 1, x: 0, ease: "Power2.easeOut" }
    );
    
    // Elementos internos entran uno por uno creando un efecto dinámico y premium
    TweenMax.staggerFromTo(wrapper.querySelectorAll('.stagger-el'),
        0.6,
        { opacity: 0, y: 30 },
        { opacity: 1, y: 0, ease: "Power2.easeOut", delay: 0.2 },
        0.08
    );

    // 4. Scroll Suave solo la primera vez que se hace clic desde el Carrusel principal
    if(isFirstTime || window.scrollY < wrapper.offsetTop - 300) {
        // CLAVE: Usar setTimeout permite que el navegador dibuje la sección antes de calcular hacia dónde bajar
        setTimeout(() => {
            const targetPos = wrapper.offsetTop - (window.innerHeight * 0.02); // Dejamos un respiro sutil arriba sin sobreponer
            
            // Obligamos físicamente al navegador a bajar la pantalla
            if (window.scroller && typeof window.scroller.setPostion === 'function') window.scroller.setPostion(targetPos);
            window.scrollTo({ top: targetPos, behavior: 'smooth' });
        }, 150); // 150 milisegundos son suficientes para que todo cargue perfecto
    }
}

// --- FUNCIÓN PARA CERRAR EL DETALLE Y RESTAURAR LA PÁGINA ---
window.closeCandidateDetail = function() {
    const wrapper = document.getElementById('detalle-candidato-wrapper');
    if(wrapper) {
        // 1. Animamos la salida hacia la izquierda (fade-left inverso)
        TweenMax.to(wrapper.querySelector('.candidato-detalle-container'), 0.6, {
            opacity: 0, x: -100, ease: "Power2.easeIn",
            onComplete: () => {
                wrapper.style.display = 'none';
                
                // 2. Restauramos todas las secciones ocultas
                document.querySelectorAll('.hidden-by-detail').forEach(child => {
                    child.style.visibility = child.getAttribute('data-original-visibility') || '';
                    child.classList.remove('hidden-by-detail');
                });

                // Reaparecer a Lucho al cerrar el detalle
                const luchoContainer = document.querySelector('.lucho-candidatos-container');
                if (luchoContainer) {
                    luchoContainer.style.display = '';
                }

                // 3. Hacemos scroll de regreso al carrusel principal
                const carruselSection = document.getElementById('candidatos-section') || document.querySelector('.marquee-container').closest('section');
                if (carruselSection) {
                    const targetPos = carruselSection.offsetTop - (window.innerHeight * 0.1);
                    if (window.scroller && typeof window.scroller.setPostion === 'function') window.scroller.setPostion(targetPos);
                    window.scrollTo({ top: targetPos, behavior: 'smooth' });
                }
            }
        });
    }
};

// --- LÓGICA DEL LIGHTBOX (POPUP DE FOTOS) ---
window.openCandidateGallery = function(imagesArray) {
    let lightbox = document.getElementById('fuerza-lightbox');
    
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'fuerza-lightbox';
        lightbox.className = 'fuerza-lightbox';
        
        lightbox.onclick = function(e) {
            if (e.target === lightbox) window.closeCandidateGallery();
        };
        lightbox.innerHTML = `
            <button class="fuerza-lightbox-close" onclick="window.closeCandidateGallery()">✖</button>
            <img id="fuerza-lightbox-main" class="fuerza-lightbox-img" src="" alt="Imagen Ampliada">
            <div id="fuerza-lightbox-thumbs" class="fuerza-lightbox-thumbs"></div>
        `;
        document.body.appendChild(lightbox);
    }
    
    const mainImg = document.getElementById('fuerza-lightbox-main');
    const thumbsContainer = document.getElementById('fuerza-lightbox-thumbs');
    
    mainImg.src = imagesArray[0];
    thumbsContainer.innerHTML = '';
    
    imagesArray.forEach((src, index) => {
        const thumb = document.createElement('img');
        thumb.src = src;
        if (index === 0) thumb.className = 'active';
        thumb.onclick = (e) => {
            e.stopPropagation();
            mainImg.src = src;
            Array.from(thumbsContainer.children).forEach(img => img.classList.remove('active'));
            thumb.classList.add('active');
        };
        thumbsContainer.appendChild(thumb);
    });
    
    void lightbox.offsetWidth; // Forzar Reflow
    lightbox.classList.add('active');
    document.body.classList.add('hide-header');
};

window.closeCandidateGallery = function() {
    const lightbox = document.getElementById('fuerza-lightbox');
    if (lightbox) lightbox.classList.remove('active');
    document.body.classList.remove('hide-header');
};

function initTimelineCarousels(container) {
    const carousels = (container || document).querySelectorAll('.timeline-carousel-wrapper');
    carousels.forEach((carousel) => {
        const content = carousel.querySelector('.timeline-carousel-content');
        const items = carousel.querySelectorAll('.timeline-carousel-item');
        const prevBtn = carousel.querySelector('.timeline-carousel-nav.prev');
        const nextBtn = carousel.querySelector('.timeline-carousel-nav.next');
        
        if (!content || items.length <= 1 || !prevBtn || !nextBtn) {
            if(prevBtn) prevBtn.style.display = 'none';
            if(nextBtn) nextBtn.style.display = 'none';
            return;
        }

        let currentIndex = 0;
        const totalItems = items.length;

        function updateCarousel() {
            const offset = -currentIndex * 100;
            TweenMax.to(content, 0.6, {
                x: offset + '%',
                ease: "Power3.easeInOut"
            });
            
            prevBtn.style.opacity = currentIndex === 0 ? '0.3' : '1';
            prevBtn.style.pointerEvents = currentIndex === 0 ? 'none' : 'auto';
            nextBtn.style.opacity = currentIndex === totalItems - 1 ? '0.3' : '1';
            nextBtn.style.pointerEvents = currentIndex === totalItems - 1 ? 'none' : 'auto';
        }

        // Evitar duplicar eventos si la función vuelve a pasar por este elemento
        if (carousel.dataset.initialized) return;
        carousel.dataset.initialized = 'true';

        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (currentIndex > 0) { currentIndex--; updateCarousel(); }
        });

        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (currentIndex < totalItems - 1) { currentIndex++; updateCarousel(); }
        });

        updateCarousel(); // Estado inicial
    });
}

// --- Scroll Magnético Seguro (Efecto Imán Retardado) ---
let isMagneticScrollInitialized = false;
function initSafeMagneticScroll() {
    if (isMagneticScrollInitialized) return;
    isMagneticScrollInitialized = true;
    
    let inputTimeout;
    let scrollTimeout;
    let isAutoScrolling = false;

    const handleInputEnd = () => {
        if (isAutoScrolling) return;
        
        // --- APAGAR IMÁN EN CELULARES Y TABLETS (Navegación 100% libre y natural) ---
        if (window.innerWidth <= 1024) return;

        // ¿Hacia dónde se dirige la inercia del scroll suave? Si no hay smoothscroll, usamos la posición actual
        const projectedY = (window.scroller && typeof window.scroller.getDestination === 'function') 
            ? window.scroller.getDestination() 
            : window.scrollY;

        // Pausar el imán temporalmente si la vista de detalle de un candidato o algún modal están abiertos
        const detailWrapper = document.getElementById('detalle-candidato-wrapper');
        const isDetailOpen = detailWrapper && detailWrapper.style.display !== 'none';
        if (document.body.classList.contains('hide-header') || isDetailOpen) {
            return;
        }

            const sections = [
                // Top de las páginas (Snap a 0)
                { id: 'hero-section', offset: 0, threshold: 0.60 },
                { id: 'hero-drone-section', offset: 0, threshold: 0.60 },
                { id: 'hero-video-section', offset: 0, threshold: 0.60 },
                { id: 'hero-design-section', offset: 0, threshold: 0.60 },
                { id: 'sumate-hyperspace-section', offset: 0, threshold: 0.60 },
                { id: 'contacto-escenario', offset: 0, threshold: 0.60 }, 

                // Secciones de contenido (Centradas)
                { id: 'intro', align: 'center', threshold: 0.60 }, 
                { id: 'video-section', align: 'center', threshold: 0.60 },
                { id: 'drone-section', align: 'center', threshold: 0.60 },
                { id: 'votar-section', align: 'center', threshold: 0.60 },
                { id: 'motor-norte-section', align: 'center', threshold: 0.60 },
                { id: 'quienes-somos-timeline', align: 'center', threshold: 0.60 },
                { id: 'candidatos-section', align: 'center', threshold: 0.60 }
            ];

            const currentY = window.scrollY; // Solo para calcular la posición real de los elementos
            const viewportHeight = window.innerHeight;
            
            let closestTarget = null;
            let closestId = null;
            let minDistance = Infinity;
            let activeThreshold = 0.60; 

            sections.forEach(secData => {
                const el = document.getElementById(secData.id);
                if (!el) return;
                
                const rect = el.getBoundingClientRect();
                const absoluteElTop = rect.top + currentY;
                
                let targetY;
                const isMobileView = window.innerWidth <= 991;
                const fullScreenSections = ['intro', 'video-section', 'drone-section', 'votar-section'];

                if (secData.align === 'center') {
                    if (isMobileView && fullScreenSections.includes(secData.id)) {
                        // Soluciona el efecto de "doble imán" en móviles debido al conflicto matemático entre 100dvh y viewportHeight
                        targetY = absoluteElTop;
                    } else {
                        let centerShift = 0;
                        if (secData.id === 'video-section') {
                            centerShift = viewportHeight * 0.01; 
                        }
                        targetY = absoluteElTop - (viewportHeight / 2) + (rect.height / 2) + centerShift;
                    }
                } else {
                    targetY = absoluteElTop - (secData.offset !== undefined ? secData.offset : 60);
                }
                
                if (targetY < 0) targetY = 0; // Evita valores negativos
                
                // Comparamos contra la posición PROYECTADA de la inercia
                const distance = Math.abs(projectedY - targetY);

                if (distance < minDistance) {
                    minDistance = distance;
                    closestTarget = targetY;
                    closestId = secData.id;
                    activeThreshold = secData.threshold !== undefined ? secData.threshold : 0.60;
                }
            });

            let shouldSnap = false;

            if (closestTarget !== null) {
                if (minDistance < viewportHeight * activeThreshold) {
                    if (minDistance > 5) {
                        shouldSnap = true;
                    }
                }
            }

            if (shouldSnap) {
                // --- NUEVO: Chequeo anti-footer-trap ---
                const lastSections = ['votar-section', 'motor-norte-section', 'candidatos-section'];

                // Si el imán quiere atraparnos en la última sección, pero ya estamos MÁS ABAJO
                // de su punto de anclaje, significa que queremos ver el footer. ¡Lo dejamos pasar!
                if (lastSections.includes(closestId) && window.scrollY > closestTarget) {
                    // No hacer nada. El scroll suave continuará su inercia hasta el final.
                } else {
                    // Proceder con el snap normal
                    isAutoScrolling = true;
                    
                    // Detenemos la inercia nativa de smoothscroll para tomar el control instantáneamente
                    if (window.scroller && typeof window.scroller.stop === 'function') {
                        window.scroller.stop();
                    }

                    const startY = window.scrollY;
                    const distance = closestTarget - startY;
                    let startTime = null;
                    const duration = 650; // Animación ágil, ni muy lenta ni seca
                    
                    function customAnim(currentTime) {
                        if (!startTime) startTime = currentTime;
                        const progress = Math.min((currentTime - startTime) / duration, 1);
                        const ease = 1 - Math.pow(1 - progress, 4); // easeOutQuart para efecto magnético perfecto
                        
                        window.scrollTo(0, startY + distance * ease);
                        
                        if (progress < 1) {
                            window.currentScrollAnim = requestAnimationFrame(customAnim);
                        } else {
                            window.currentScrollAnim = null;
                            if (window.scroller && typeof window.scroller.setPostion === 'function') {
                                window.scroller.setPostion(closestTarget);
                            }
                            setTimeout(() => { isAutoScrolling = false; }, 50);
                        }
                    }
                    window.currentScrollAnim = requestAnimationFrame(customAnim);
                }
            }
    };

    const handleScrollFallback = () => {
        if (isAutoScrolling) return;
        
        if (window.innerWidth <= 1024) return;

        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // Si el scroller NO se está moviendo, significa que el scroll fue por barra de desplazamiento
            if (!window.scroller || !window.scroller.isMoving()) {
                handleInputEnd();
            }
        }, 150);
    };

    const markUserInput = () => {
        // Apagado total del magnetismo en móviles
        if (window.innerWidth <= 1024) return;

        if (window.currentScrollAnim) {
            cancelAnimationFrame(window.currentScrollAnim);
            window.currentScrollAnim = null;
            isAutoScrolling = false;
            if (window.scroller && typeof window.scroller.setPostion === 'function') {
                window.scroller.setPostion(window.scrollY);
            }
        }
        clearTimeout(inputTimeout);
        inputTimeout = setTimeout(handleInputEnd, 100); // Actúa rápido apenas dejas de mover la rueda/dedo
    };

    window.addEventListener('scroll', handleScrollFallback, { passive: true });
    window.addEventListener('wheel', markUserInput, { passive: true });
    window.addEventListener('touchstart', markUserInput, { passive: true });
    window.addEventListener('touchmove', markUserInput, { passive: true });
}

function initVideoScrollFix(container) {
    const target = container || document;
    const iframeContainers = target.querySelectorAll('.iframe-container');
    
    iframeContainers.forEach(container => {
        // Al hacer clic, quitamos el escudo transparente para que el video sea interactivo
        container.addEventListener('click', () => container.classList.add('is-interactive'));
        // Al sacar el ratón, regresamos el escudo para que el scroll vuelva a funcionar
        container.addEventListener('mouseleave', () => container.classList.remove('is-interactive'));
    });
}

// --- Carga Diferida (Lazy Load) de Iframe de YouTube ---
function initYouTubePlaceholder(container) {
    const target = container || document;
    const placeholders = target.querySelectorAll('.yt-placeholder');
    
    placeholders.forEach(ph => {
        if (ph.dataset.youtubeReady === 'true') return;
        ph.dataset.youtubeReady = 'true';
        
        const videoId = ph.getAttribute('data-video-id');
        const playBtn = ph.querySelector('.yt-play-btn');
        if (!videoId) return;

        const loadVideo = () => {
            if (ph.querySelector('iframe')) return; // Evita inyectar múltiples iframes
            ph.innerHTML = `<iframe class="video-player" src="https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;" frameborder="0" title="Video de presentación de Fuerza Tacna" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
            ph.classList.add('is-interactive'); // Permite que funcione el scroll interno
        };

        ph.addEventListener('click', loadVideo);
        if (playBtn) {
            playBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                loadVideo();
            });
        }
    });
}

// --- Smooth Scroll para Flechas Móviles (Evita el bug del hash en celulares) ---
function initSmoothArrows(container) {
    const target = container || document;
    const arrows = target.querySelectorAll('.mobile-arrows');
    arrows.forEach(arrow => {
        const newArrow = arrow.cloneNode(true);
        arrow.parentNode.replaceChild(newArrow, arrow);
        
        newArrow.addEventListener('click', function(e) {
            e.preventDefault(); // Evita que cambie la URL y salte la barra de navegación del móvil
            const targetId = this.getAttribute('href');
            if (!targetId || !targetId.startsWith('#')) return;
            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                // Alineamos estrictamente al techo de la sección 
                const targetY = targetEl.offsetTop;
                if (window.scroller && typeof window.scroller.setPostion === 'function') {
                    window.scroller.setPostion(targetY);
                } else {
                    window.scrollTo({ top: targetY, behavior: 'smooth' });
                }
            }
        });
    });
}

// --- Auto-Cargador del Mapa Vectorial (SVG) ---
function initMapaTacna(container) {
    const target = container || document;
    const mapContainer = target.querySelector('#mapa-tacna-container');
    
    // Usamos fetch para cargar el archivo SVG sin tener que pegarlo a mano en el HTML
    if (mapContainer && !mapContainer.querySelector('svg')) {
        console.log("Intentando cargar el mapa SVG...");
        fetch('assets/img/MAPA TACNA.svg')
            .then(response => {
                if (!response.ok) throw new Error("No se pudo cargar el SVG. Código: " + response.status);
                return response.text();
            })
            .then(svgData => {
                mapContainer.innerHTML = svgData;
                
                // AUTO-REPARADOR: Soluciona problemas de exportación de Illustrator
                const svgTag = mapContainer.querySelector('svg');
                if(svgTag) {
                    if(!svgTag.getAttribute('viewBox') && svgTag.getAttribute('width') && svgTag.getAttribute('height')) {
                        svgTag.setAttribute('viewBox', `0 0 ${parseFloat(svgTag.getAttribute('width'))} ${parseFloat(svgTag.getAttribute('height'))}`);
                    }
                    svgTag.removeAttribute('width');
                    svgTag.removeAttribute('height');
                    
                    // --- NUEVO: Inyectar la luz animada directamente DENTRO del mapa ---
                    let defs = svgTag.querySelector('defs');
                    if (!defs) {
                        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                        svgTag.prepend(defs);
                    }
                    if (!defs.querySelector('#map-shiny-border')) {
                        defs.insertAdjacentHTML('beforeend', `
                            <linearGradient id="map-shiny-border" gradientUnits="objectBoundingBox" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="rgba(255, 195, 0, 0.2)" />
                                <stop offset="35%" stop-color="rgba(255, 195, 0, 0.2)" />
                                <stop offset="45%" stop-color="#ffc300" />
                                <stop offset="50%" stop-color="#ffffff" />
                                <stop offset="55%" stop-color="#ffc300" />
                                <stop offset="65%" stop-color="rgba(255, 195, 0, 0.2)" />
                                <stop offset="100%" stop-color="rgba(255, 195, 0, 0.2)" />
                                <animateTransform attributeName="gradientTransform" type="rotate" from="0 0.5 0.5" to="360 0.5 0.5" dur="3s" repeatCount="indefinite" />
                            </linearGradient>
                        `);
                    }
                    
                    /* BOMBA DE CPU ELIMINADA: La generación aleatoria de puntos con isPointInFill asfixiaba el navegador por 30s */
                }
                
                // --- NUEVO: Lógica del Tooltip Flotante ---
                let tooltip = document.getElementById('map-tooltip');
                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.id = 'map-tooltip';
                    document.body.appendChild(tooltip);
                }

                // Seleccionamos solo los trazados geométricos para evitar conflictos con grupos vacíos
                const mapElements = mapContainer.querySelectorAll('svg path, svg polygon');
                
                // Detector inteligente de provincias según ID o etiqueta Data-Name
                const getProvinciaName = (id) => {
                    if (!id) return null;
                    id = id.toLowerCase();
                    if (id.includes('tarata')) return 'Tarata';
                    if (id.includes('candarave')) return 'Candarave';
                    if (id.includes('basadre') || id.includes('jorge')) return 'Jorge Basadre';
                    if (id.includes('tacna')) return 'Tacna';
                    return null;
                };

                // Asignador automático de nombres para SVGs sin IDs (Fallback)
                const provinciasPorDefecto = ['Tacna', 'Tarata', 'Candarave', 'Jorge Basadre'];
                let indexFallback = 0;

                mapElements.forEach(el => {
                    // Pre-calcular el nombre de esta pieza una sola vez
                    let current = el;
                    let id = '';
                    while (current && current.tagName.toLowerCase() !== 'svg') {
                        id = current.id || current.getAttribute('data-name') || current.getAttribute('name') || '';
                        if (id) break;
                        current = current.parentElement;
                    }
                    
                    let provName = getProvinciaName(id);
                    if (!provName) {
                        provName = provinciasPorDefecto[indexFallback % provinciasPorDefecto.length];
                        indexFallback++;
                    }

                    // NUEVO: Agregamos una clase CSS basada en el nombre de la provincia
                    // Ejemplo: "Tacna" -> "provincia-tacna", "Jorge Basadre" -> "provincia-jorge-basadre"
                    if (provName) {
                        el.classList.add('provincia-' + provName.toLowerCase().replace(/\s+/g, '-'));
                    }

                    el.addEventListener('mouseenter', (e) => {

                        let obrasCount = 25; // Valor estático a 25 como lo pediste
                        
                        tooltip.innerHTML = `<h4>${provName}</h4><p>${obrasCount} OBRAS EJECUTADAS</p>`;
                        tooltip.classList.add('visible');
                    });

                    el.addEventListener('mousemove', (e) => {
                        // Un pequeño margen para que el cursor no tape el cuadrito
                        tooltip.style.left = (e.clientX) + 'px';
                        tooltip.style.top = (e.clientY - 15) + 'px';
                    });

                    el.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('visible');
                    });
                });

                console.log("Mapa SVG inyectado y reparado correctamente.");
                setTimeout(() => AOS.refresh(), 200); // Refresca las animaciones
            })
            .catch(err => console.error('Error cargando el archivo MAPA TACNA.svg:', err));
    }
}

function inits(container) {
    setTimeout(() => AOS.refreshHard(), 100);
    setTimeout(() => AOS.refreshHard(), 500);
        
        // Inicializaciones Ligeras
    initSafeMagneticScroll();
    initSmoothArrows(container);
        
        // TRUCO UX: Retrasamos 250ms las inicializaciones pesadas (Mapas, Leaflet, Carruseles, etc.)
        // Esto hace que el navegador use todo su procesador EXACTAMENTE cuando el telón de Barba.js
        // está cubriendo toda la pantalla de un color sólido, escondiendo cualquier caída de FPS.
    requestAnimationFrame(() => {
                console.log("=== 🔍 INICIANDO DETECTOR DE CUELLOS DE BOTELLA ===");
                console.time("⏱️ DIAGNOSTICO: initCandidatos");
                initCandidatos(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initCandidatos");
                console.time("⏱️ DIAGNOSTICO: initTimelineCarousels");
                initTimelineCarousels(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initTimelineCarousels");
                console.time("⏱️ DIAGNOSTICO: initMapaTacna");
                initMapaTacna(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initMapaTacna");
                console.time("⏱️ DIAGNOSTICO: initVideoScrollFix");
                initVideoScrollFix(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initVideoScrollFix");
                console.time("⏱️ DIAGNOSTICO: initYouTubePlaceholder");
                initYouTubePlaceholder(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initYouTubePlaceholder");
                console.time("⏱️ DIAGNOSTICO: initCircularTimeline");
                initCircularTimeline(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initCircularTimeline");
                console.time("⏱️ DIAGNOSTICO: initMapEngine");
                // Paso 2: Despertar el nuevo motor MapLibre cuando entramos a la página del mapa
                if (typeof initMapEngine === 'function') initMapEngine(container);
                console.timeEnd("⏱️ DIAGNOSTICO: initMapEngine");
                // Paso 1: Despertar la lógica de los filtros que ahora vive en mapa-filtros.js
                if (typeof initFilters === 'function') initFilters();
                // Inicializador del cómic interactivo de la página de contacto
                if (typeof initContactoComic === 'function') initContactoComic(container);
                console.log("=== 🔍 FIN DEL DETECTOR ===");
    });
}

barba.hooks.afterOnce((data) => {
    const targetContainer = (data && data.next && data.next.container) ? data.next.container : document;
    inits(targetContainer);
});

// REEMPLAZAMOS LOS HOOKS DUPLICADOS POR ESTA VERSIÓN BLINDADA
barba.hooks.after((data) => {
    console.log('ok');
    if (window.scroller && typeof window.scroller.setPostion === 'function') {
        window.scroller.setPostion(0);
    }
    window.scrollTo(0, 0);

    // 1. Destrucción absoluta de la página anterior para evitar solapamientos (Fantasmas)
    if (data && data.current && data.current.container) {
        data.current.container.remove();
    }

    // Failsafe: Eliminar contenedores fantasma si Barba.js falla al limpiar el DOM por navegación rápida
    const containers = document.querySelectorAll('main[data-barba="container"]');
    if (containers.length > 1) {
        for (let i = 0; i < containers.length - 1; i++) {
            containers[i].remove();
        }
    }

    // 2. Solo DESPUÉS de limpiar a fondo, inicializamos la página nueva
    const targetContainer = (data && data.next && data.next.container) ? data.next.container : document;
    try {
        inits(targetContainer);
    } catch (e) {
        console.error("Error al inicializar la nueva página:", e);
    }
});

async function initCircularTimeline(container) {
    const target = container || document;
    const wheel = target.querySelector('#timeline-wheel');
    const itemsContainer = target.querySelector('#timeline-items');
    const descElement = target.querySelector('#timeline-desc');
    const badgeElement = target.querySelector('#timeline-year-badge');
    const verMasElement = target.querySelector('#timeline-ver-mas');
    const modalOverlay = target.querySelector('#timeline-modal');
    const modalClose = target.querySelector('#timeline-modal-close');
    const modalYear = target.querySelector('#timeline-modal-year');
    const modalText = target.querySelector('#timeline-modal-text');
    
    if(!wheel || !itemsContainer || wheel.dataset.initialized) return;
    wheel.dataset.initialized = 'true';

    try {
        const response = await fetch('assets/panel-admin-universo/api_cronologia.php');
        const result = await response.json();
        
        if(!result.ok || !result.datos || result.datos.length === 0) {
            if(badgeElement) badgeElement.innerText = "";
            if(descElement) descElement.innerText = "Próximamente la historia de nuestra fuerza...";
            if(verMasElement) verMasElement.style.display = "none";
            return;
        }

        // Mapeamos los datos de tu BD al formato que entiende la web
        const baseEvents = result.datos.map(item => {
            let detailsHtml = `<h3 style="color:#ffc300; margin-top:0;">${item.titulo}</h3><p style="margin-top:1rem;">${item.descripcion}</p>`;
            if (item.imagen && item.imagen !== "") {
                detailsHtml = `<img src="assets/IMG/cronologia/${item.imagen}" alt="${item.titulo}" loading="lazy">` + detailsHtml;
            }
            return { year: item.fecha_texto, description: item.titulo, details: detailsHtml };
        });

        // TRUCO: Multiplicamos la lista para rellenar el círculo gigante, así si subes 3 fotos, el círculo no se ve vacío
        let events = [...baseEvents];
        while (events.length < 12) {
            events = [...events, ...baseEvents];
        }

    const STEP = 360 / events.length; // 360 grados repartidos entre todas las fechas = circuito cerrado
    let activeIndex = 0;
    let currentWheelRotation = 0; // Guardará la rotación absoluta para giros infinitos
    // Aseguramos el tamaño del radio incluso si la transición (Barba) lo oculta
    let radius = (wheel.offsetWidth || (window.innerWidth <= 768 ? 1000 : 1600)) / 2;

    function createItems() {
        itemsContainer.innerHTML = '';
        events.forEach((ev, i) => {
            const angle = i * STEP; // Empezamos en 0 grados exactos (centro superior)
            const btn = document.createElement('button');
            btn.className = 'timeline-btn';
            btn.style.transform = `translate(-50%, -50%) rotate(${angle}deg) translateY(-${radius}px)`;
            
            const wrapper = document.createElement('div');
            wrapper.className = 'timeline-wrapper';
            
            const yearSpan = document.createElement('span');
            yearSpan.className = 'timeline-year-text';
            yearSpan.textContent = ev.year;
            yearSpan.style.transform = `rotate(0deg)`; // Eliminamos la contra-rotación
            
            const dot = document.createElement('span');
            dot.className = 'timeline-dot';

            wrapper.appendChild(yearSpan);
            wrapper.appendChild(dot);
            btn.appendChild(wrapper);
            
            btn.addEventListener('click', () => {
                clearInterval(window.timelineAutoplay); // Pausa el auto-giro si el usuario interactúa
                updateTimeline(i);
            });
            itemsContainer.appendChild(btn);
        });
    }

    function updateTimeline(index) {
        activeIndex = index;
        
        // ROTACIÓN INFINITA Y RUTA MÁS CORTA (CIRCUITO CERRADO)
        let targetRotation = -(activeIndex * STEP);
        let diff = (targetRotation - currentWheelRotation) % 360;
        if (diff > 180) diff -= 360;
        if (diff < -180) diff += 360;
        
        currentWheelRotation += diff;
        
        wheel.style.transform = `translate(-50%, -50%) rotate(${currentWheelRotation}deg)`;
        
        descElement.style.opacity = '0';
        descElement.style.transform = 'translateY(15px)';
        if(badgeElement) {
            badgeElement.style.opacity = '0';
            badgeElement.style.transform = 'translateY(10px)';
        }
        if(verMasElement) {
            verMasElement.style.opacity = '0';
            verMasElement.style.transform = 'translateY(15px)';
        }
        
        setTimeout(() => {
            descElement.textContent = events[activeIndex].description;
            descElement.style.opacity = '1';
            descElement.style.transform = 'translateY(0)';
            if(badgeElement) {
                badgeElement.textContent = events[activeIndex].year;
                badgeElement.style.opacity = '1';
                badgeElement.style.transform = 'translateY(0)';
            }
            if(verMasElement) {
                verMasElement.style.opacity = '1';
                verMasElement.style.transform = 'translateY(0)';
            }
        }, 300); 
        
        const btns = itemsContainer.querySelectorAll('.timeline-btn');
        btns.forEach((btn, i) => {
            const wrapper = btn.querySelector('.timeline-wrapper');
            const yearSpan = btn.querySelector('.timeline-year-text');
            const dot = btn.querySelector('.timeline-dot');
            
            wrapper.style.transform = `rotate(0deg)`; 
            
            if (i === activeIndex) {
                yearSpan.style.opacity = '1';
                yearSpan.style.transform = `scale(1.15) translateY(-10px)`;
                yearSpan.style.color = '#801039';
                dot.style.transform = 'scale(1.3)';
                dot.classList.add('active-dot');
            } else {
                yearSpan.style.opacity = '0.55';
                yearSpan.style.transform = `scale(0.9) translateY(0)`;
                yearSpan.style.color = '#666666';
                dot.style.transform = 'scale(1)';
                dot.classList.remove('active-dot');
            }
        });
    }

    createItems();
    updateTimeline(activeIndex);
    
    // Activación del botón VER MÁS para abrir el popup
    if (verMasElement && modalOverlay && !verMasElement.dataset.modalListener) {
        verMasElement.dataset.modalListener = "true";
        verMasElement.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.timelineAutoplay) clearInterval(window.timelineAutoplay); // Detiene el giro para que puedas leer tranquilo
            document.body.classList.add('hide-header'); // Ocultar header
            
            modalYear.textContent = events[activeIndex].year;
            
            // --- LÓGICA DE DIVISIÓN DE PANTALLA ---
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = events[activeIndex].details;
            const img = tempDiv.querySelector('img');
            
            const leftContainer = document.getElementById('timeline-modal-left');
            const rightContainer = document.querySelector('.timeline-modal-right');
            const layoutContainer = document.querySelector('.timeline-modal-layout');
            
            if (img) {
                leftContainer.innerHTML = '';
                leftContainer.appendChild(img);
                leftContainer.style.display = 'flex';
                layoutContainer.style.textAlign = 'left';
                rightContainer.style.alignItems = 'flex-start';
            } else {
                leftContainer.style.display = 'none';
                layoutContainer.style.textAlign = 'center';
                rightContainer.style.alignItems = 'center';
            }
            
            modalText.innerHTML = tempDiv.innerHTML;
            modalOverlay.classList.add('active');
        });
        
        modalClose.addEventListener('click', () => {
            modalOverlay.classList.remove('active');
            document.body.classList.remove('hide-header');
        });
        modalOverlay.addEventListener('click', (e) => {
            // Cierra el modal si haces click en el fondo oscuro
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('active');
                document.body.classList.remove('hide-header');
            }
        });
    }

    // Efecto Autoplay Inteligente: Solo gira cuando el usuario está viendo la línea de tiempo
    if (window.timelineAutoplay) clearInterval(window.timelineAutoplay);
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            clearInterval(window.timelineAutoplay);
            if (entries[0].isIntersecting) {
                window.timelineAutoplay = setInterval(() => {
                    let nextIndex = (activeIndex + 1) % events.length;
                    updateTimeline(nextIndex);
                }, 5000); // Gira cada 5 segundos
            }
        }, { threshold: 0.2 }); // Se activa cuando el 20% de la sección es visible
        const section = target.querySelector('.timeline-section');
        if (section) observer.observe(section);
    }

    window.addEventListener('resize', () => {
        radius = (wheel.offsetWidth || (window.innerWidth <= 768 ? 1000 : 1600)) / 2;
        const btns = itemsContainer.querySelectorAll('.timeline-btn');
        btns.forEach((btn, i) => {
            const angle = i * STEP;
            btn.style.transform = `translate(-50%, -50%) rotate(${angle}deg) translateY(-${radius}px)`;
        });
    });
    } catch (error) {
        console.error("Error cargando la historia dinámica:", error);
    }
}

// --- Animación Épica WOW del Cómic de Contacto ---
function initContactoComic(container) {
    const target = container || document;
    const btn = target.querySelector('#comic-btn');
    const btnText = target.querySelector('#comic-btn-text');
    const lucho1 = target.querySelector('#lucho-pose-1');
    const lucho2 = target.querySelector('#lucho-pose-2');
    const dialogText = target.querySelector('#comic-text');
    const dialogStage = target.querySelector('#dialog-stage');
    const comicLayout = target.querySelector('#comic-layout');
    const cardsContainer = target.querySelector('#contact-cards-container');
    const cards = target.querySelectorAll('.glass-contact-card');
    const luchoStage = target.querySelector('.lucho-stage');
    
    if (!btn || !lucho1 || !lucho2) return;

    // Evitar inicialización duplicada tras navegar
    if (btn.dataset.wowInit === 'true') return;
    btn.dataset.wowInit = 'true';

    let comicStep = 1;

    if (typeof gsap !== 'undefined') {
        // 1. ESTADO INICIAL OCULTO POR JS (Para que la entrada sea sorpresa pero segura)
            gsap.set(comicLayout, { y: 50 }); // Preparamos la entrada desde abajo
        gsap.set(luchoStage, { opacity: 1 }); // Nos aseguramos de que el padre sea visible
        gsap.set(lucho1, { y: 250, opacity: 0, scale: 0.7 });
        gsap.set(dialogStage, { scale: 0, opacity: 0, transformOrigin: "bottom left" });
        gsap.set(lucho2, { opacity: 0, scale: 0.5, rotationY: -90, display: "none" });
        gsap.set(cards, { opacity: 0, y: 150, rotationX: 45, rotationY: 30, z: -300 });

        // 2. ENTRADA "WOW" CUANDO CARGA LA PÁGINA
        let delayTime = 1500; // 1.5s sincronizado para cuando el telón amarillo empieza a bajar
            // Semáforo inteligente: Solo si la ola amarilla está corriendo realmente esperamos 6s
            if (window.isFirstLoadAnimationRunning) {
                delayTime = 5800; // Si el agua amarilla está cargando, esperamos casi 6 segundos
            }

            setTimeout(() => {
                // Failsafe: Si el usuario ya cambió de página antes de los 5.8s, cancelamos la animación fantasma
                if (!document.contains(comicLayout)) return;

            const tlIntro = gsap.timeline();
                // Aparece todo el bloque del cómic suavemente
                tlIntro.to(comicLayout, { duration: 0.6, opacity: 1, y: 0, ease: "power2.out" })
            // Lucho salta como un resorte gigante
                .to(lucho1, { duration: 1.2, y: 0, opacity: 1, scale: 1, ease: "elastic.out(1, 0.5)" }, "-=0.3")
            // El globo de diálogo infla rápidamente
            .to(dialogStage, { duration: 0.8, scale: 1, opacity: 1, ease: "elastic.out(1.2, 0.6)" }, "-=0.9")
            // El botón hace un latido (pop-up)
            .fromTo(btn, { scale: 0.7 }, { duration: 0.5, scale: 1, ease: "back.out(2)" }, "-=0.6");
            }, delayTime);

        // 3. INTERACCIONES DEL USUARIO (WOW Effects)
        btn.addEventListener('click', () => {
            if (comicStep === 1) {
                const tlStep2 = gsap.timeline();
                tlStep2.to(btn, { duration: 0.15, scale: 0.8, ease: "power2.inOut", yoyo: true, repeat: 1 });
                tlStep2.to(lucho1, { duration: 0.4, rotationY: 90, opacity: 0, scale: 0.8, ease: "back.in(1.5)" }, "change");
                tlStep2.set(lucho1, { display: "none" });
                tlStep2.set(lucho2, { display: "block", visibility: "visible" });
                tlStep2.to(lucho2, { duration: 0.8, rotationY: 0, opacity: 1, scale: 1, ease: "elastic.out(1, 0.6)" }, "change+=0.2");
                tlStep2.to(dialogStage, { duration: 0.25, scaleX: 1.15, scaleY: 0.75, y: 30, ease: "power2.inOut" }, "change");
                tlStep2.call(() => {
                    dialogText.innerHTML = "¡La <span class='highlight'>Fuerza Ganadora</span> la hacemos todos! Elige por dónde quieres enterarte de nuestras propuestas.";
                    btnText.innerHTML = "VER REDES Y WHATSAPP";
                }, [], "change+=0.25");
                tlStep2.to(dialogStage, { duration: 0.9, scaleX: 1, scaleY: 1, y: 0, ease: "elastic.out(1, 0.3)" }, "change+=0.25");
                comicStep = 2;
            } else if (comicStep === 2) {
                const tlStep3 = gsap.timeline();
                tlStep3.to(dialogStage, { duration: 0.5, scale: 1.1, opacity: 0, filter: "blur(5px)", y: -50, ease: "power2.in" });
                tlStep3.set(dialogStage, { display: "none" });
                
                const isMobile = window.innerWidth <= 991;
                tlStep3.to(comicLayout, { duration: 1, x: isMobile ? 0 : -280, y: isMobile ? -120 : 0, ease: "power3.inOut" }, "-=0.3");
                
                cardsContainer.style.visibility = 'visible';
                cardsContainer.style.opacity = '1';
                cardsContainer.style.pointerEvents = 'auto';
                tlStep3.to(cards, { duration: 1.2, x: 0, y: 0, rotationY: 0, rotationX: 0, opacity: 1, scale: 1, z: 0, stagger: 0.15, ease: "elastic.out(1, 0.6)" }, "-=0.7");
                comicStep = 3;
            }
        });
    }
}
