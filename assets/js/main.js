
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
        style.innerHTML = `
            .menu-links a, #hero-header ul li a { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-weight: 900 !important; }
            .magnetic .wrap .span { background-color: #25D366 !important; }
            .mouse-in, #services h3::before, #services .title-client::before, #intro button:hover, #video-section button:hover, #drone-section button:hover, #design-section button:hover, .references-section button:hover, #button-center:hover { background-color: #ffc300 !important; }
            .scroll-indication, .small-title, #intro button, #video-section button, #drone-section button, #votar-section button, .references-section button, #button-center, .menu-links a:hover, .menu-links a.active, .hyperlinks { color: #ffc300 !important; }
            #intro button, #video-section button, #drone-section button, #votar-section button, .references-section button, #button-center { border-color: #ffc300 !important; }
            #hero-header { position: fixed; top: 15px; left: 2% !important; width: 96% !important; box-sizing: border-box !important; z-index: 1000; background: transparent; }
            @media (min-width: 992px) { #hero-header { display: flex !important; justify-content: space-between !important; align-items: center !important; } #hero-header .logo-container { flex: 1 !important; display: flex !important; justify-content: flex-start !important; } #hero-header .button-container { flex: 1 !important; display: flex !important; justify-content: flex-end !important; } #hero-header ul { flex: 0 1 auto !important; margin: 0 auto !important; } }
            #hero-header ul { background-color: rgba(255, 195, 0, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 50px; padding: 18px 24px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid rgba(255, 255, 255, 0.4); width: max-content !important; margin: 0 auto; }
            #hero-header ul li { margin: 0 15px !important; }
            body.hide-header #hero-header, body.hide-header .mobile-arrows {
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
                transition: opacity 0.3s ease, visibility 0.3s ease !important;
            }
            main { position: relative; z-index: 0; }
            main::before { content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-image: url('assets/img/pattern.svg'); background-repeat: no-repeat; background-size: cover; background-position: center; pointer-events: none; z-index: -1; }
            
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

            /* --- Estilos Globales de Candidatos y Carrusel --- */
            #candidatos-section { background-color: transparent; position: relative !important; top: 0 !important; transform: none !important; min-height: 100vh; display: flex; align-items: center; width: 100%; overflow: hidden; z-index: 10; }
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
            @media (max-width: 768px) { .candidate-card { width: 280px; height: 400px; } #candidatos-section { min-height: 85vh; padding-top: 10vh; } }

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

            /* --- Circular 3D Carousel (Index) --- */
            .circular-testimonial-container {
                width: 100%;
                max-width: 40rem;
                color: #fff;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            .circular-image-container {
                position: relative;
                width: 100%;
                height: 26rem;
                perspective: 1000px;
            }
            .testimonial-image {
                position: absolute;
                width: 320px;
                left: 50%;
                margin-left: -160px;
                height: 100%;
                object-fit: contain;
                object-position: bottom;
                background-color: #ffffff;
                padding-top: 0.5rem; /* Reducimos el espacio superior dentro de la tarjeta */
                border-radius: 1.5rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
                transition: all 0.8s cubic-bezier(.4,2,.3,1);
                cursor: pointer;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .circular-testimonial-content { 
                position: absolute; bottom: 1rem; left: 50%; margin-left: -120px; width: 240px; /* Más pequeño */
                background: rgba(128, 16, 57, 0.55); backdrop-filter: blur(6px); /* Más transparente */
                border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 1rem;
                padding: 0.8rem; z-index: 10; pointer-events: none; /* Padding reducido */
                display: flex; flex-direction: column; justify-content: center; text-align: center;
                box-shadow: 0 8px 20px rgba(0,0,0,0.25);
            }
            .circular-name { font-size: 1.05rem; margin-bottom: 0.2rem; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; color: #fff; font-weight: 900; text-transform: uppercase; }
            .circular-designation { font-size: 0.75rem; color: #ffc300; font-weight: bold; text-transform: uppercase; margin-bottom: 0.4rem; }
            .circular-quote { font-size: 0.75rem; line-height: 1.35; color: #e5e7eb; margin: 0; min-height: 2.2rem; }
            .circular-arrow-buttons { display: flex; gap: 1.5rem; padding-top: 1.5rem; justify-content: center; }
            .circular-arrow-button {
                width: 3.5rem; height: 3.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                cursor: pointer; transition: all 0.3s; border: 2px solid #ffc300; background-color: #ffc300; z-index: 20;
                box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            }
            .circular-arrow-button svg { fill: #801039; width: 1.8rem; height: 1.8rem; transition: transform 0.2s; }
            .circular-arrow-button:hover { background-color: #fff; border-color: #fff; }
            .circular-arrow-button:hover svg { transform: scale(1.15); }
            @media (min-width: 992px) {
                .candidatos-intro-split { flex-direction: row; justify-content: space-between; align-items: flex-start; gap: 4rem; } /* Alineado arriba */
                .candidatos-intro-text { flex: 1.2; text-align: left; padding-right: 1rem; margin-top: -5.5rem; margin-left: -2vw; } /* Subido notoriamente más arriba */
                .circular-testimonial-container { flex: 1; }
            }
            @media (max-width: 991px) { 
                .candidatos-intro-text { display: contents; }
                .candidatos-intro-text h2 { order: 1; margin-bottom: 1.5rem; }
                .circular-testimonial-container { order: 2; margin-top: 1rem; }
                .circular-image-container { height: 22rem; }
                .circular-testimonial-content { width: 220px; margin-left: -110px; bottom: 0.8rem; padding: 0.6rem; } /* Ajustado para móvil */
                .testimonial-image { width: 280px; margin-left: -140px; }
            }
            
            /* --- Solución al Bug de Scroll en Videos (Escudo Transparente) --- */
            .iframe-container { position: relative !important; }
            .iframe-container::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; cursor: pointer; }
            .iframe-container.is-interactive::after { pointer-events: none; }

            /* Estilos de la Pantalla de Carga */
            .initial-loader { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999999 !important; display: flex; align-items: center; justify-content: center; background-color: #ffc300; }
            .initial-loader h1 { font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif !important; font-size: 5rem; line-height: 1.1; margin: 0; text-align: center; color: #801039; font-weight: 900; letter-spacing: 2px; }
            .initial-loader .water-layer { position: absolute; bottom: 0; left: 0; width: 100%; height: 0%; background-color: #801039; overflow: hidden; }
            .initial-loader .water-content { position: absolute; bottom: 0; left: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; }
            .initial-loader .water-content h1 { color: #ffc300; }
            .initial-loader .wave { position: absolute; bottom: 0%; margin-bottom: -2vw; left: -10vw; width: 120vw; height: clamp(60px, 10vw, 130px); z-index: 2; pointer-events: none; }
            .initial-loader .wave::before { content: ''; position: absolute; bottom: 0; left: 0; width: 240vw; height: 100%; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 150" preserveAspectRatio="none"><path d="M0,50 Q125,10 250,50 T500,50 T750,50 T1000,50 L1000,150 L0,150 Z" fill="%23801039"/></svg>'); background-size: 120vw 100%; background-repeat: repeat-x; transform-origin: bottom center; animation: waveAnimFluid 2.5s linear infinite; }
            @keyframes waveAnimFluid { 0% { transform: translateX(0) translateY(0); } 50% { transform: translateX(-60vw) translateY(8px); } 100% { transform: translateX(-120vw) translateY(0); } }
            @media (max-width: 768px) { .initial-loader h1 { font-size: 3.5rem; } }

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
                transition: all 0.4s ease;
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
            #detalle-candidato-wrapper { display: none; width: 100%; flex-shrink: 0; clear: both; box-sizing: border-box; padding: 5rem 5%; min-height: 100vh; background: transparent; position: relative; z-index: 20; margin-top: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); }
            .candidato-detalle-container { display: flex; gap: 2.5rem; max-width: 90rem; margin: 0 auto; align-items: flex-start; }
            .candidato-sidebar { display: flex; flex-direction: column; gap: 1rem; width: 95px; flex-shrink: 0; position: sticky; top: 120px; }
            .mini-card { width: 100%; height: 120px; border-radius: 1rem; cursor: pointer; overflow: hidden; opacity: 0.5; transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); border: 2px solid transparent; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
            .mini-card.active, .mini-card:hover { opacity: 1; border-color: #ffc300; transform: scale(1.05) translateX(10px); }
            .mini-card img { width: 100%; height: 100%; object-fit: cover; }
            .candidato-content { flex-grow: 1; display: flex; flex-direction: column; background: #801039; border-radius: 2rem; padding: 3.5rem; border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 20px 60px rgba(0,0,0,0.7); }
            
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
            .timeline-item { position: relative; padding-bottom: 2rem; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
            .timeline-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
            .timeline-body { position: relative; display: flex; flex-direction: row; justify-content: flex-start; align-items: center; gap: 2rem; width: 100%; }
            .timeline-content-left { display: flex; flex-direction: column; align-items: flex-start; gap: 0.8rem; }
            .timeline-text { color: #bbb; line-height: 1.8; font-size: 1.05rem; margin: 0; font-weight: 300; max-width: 50ch; }
            
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
            .facebook-layout-grid { display: grid; grid-template-columns: 1fr auto; gap: 3rem; align-items: center; background: rgba(255,255,255,0.02); padding: 2.5rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.05); margin-top: 1rem; }
            .facebook-text { display: flex; flex-direction: column; gap: 1.2rem; align-items: flex-start; }
            .facebook-text h3 { color: #ffc300; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 1.8rem; margin: 0; text-transform: uppercase; line-height: 1.1; }
            .facebook-text p { color: #bbb; font-size: 1.05rem; line-height: 1.6; margin: 0 0 1rem 0; max-width: 40ch; font-weight: 300; }
            .fb-widget-container { display: flex; justify-content: center; background: transparent; padding: 0; border: none; overflow: hidden; width: 500px; max-width: 100%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); }
            .fb-widget-container iframe { width: 100%; background: #fff; border-radius: 8px; display: block; }

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
            
            .candidate-actions { display: flex; gap: 1.2rem; margin-top: 2.5rem; flex-wrap: wrap; align-items: center; width: 100%; }
            .action-btn { padding: 1.1rem 2.2rem; border-radius: 50px; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 0.95rem; text-transform: uppercase; text-decoration: none !important; transition: all 0.3s ease; cursor: pointer; text-align: center; letter-spacing: 1px; }
            .action-btn.primary { background: #ffc300; color: #801039 !important; border: 2px solid #ffc300; box-shadow: 0 6px 20px rgba(255,195,0,0.4); }
            .action-btn.primary:hover { background: #fff; border-color: #fff; transform: translateY(-3px); box-shadow: 0 10px 25px rgba(255,255,255,0.5); }
            .action-btn.outline { background: rgba(255,255,255,0.02); color: #ffc300 !important; border: 2px solid #ffc300; }
            .action-btn.outline:hover { background: rgba(255,195,0,0.15); transform: translateY(-3px); }

            .next-candidate-module { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 1.5rem; padding: 1.5rem 2.5rem; cursor: pointer; transition: all 0.4s ease; margin-top: 3rem; text-decoration: none; }
            .next-candidate-module:hover { background: rgba(0,0,0,0.4); border-color: rgba(255,195,0,0.6); transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
            .next-candidate-info { display: flex; align-items: center; gap: 1.5rem; }
            .next-candidate-avatar { width: 65px; height: 65px; border-radius: 50%; border: 2px solid #ffc300; object-fit: cover; }
            .next-candidate-text h5 { color: rgba(255,255,255,0.6); font-size: 0.85rem; text-transform: uppercase; margin: 0 0 0.3rem 0; letter-spacing: 1px; }
            .next-candidate-text h3 { color: #ffc300; margin: 0; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 1.3rem; text-transform: uppercase; }
            .next-candidate-arrow { color: #ffc300; transition: transform 0.3s ease; display: flex; align-items: center; }
            .next-candidate-module:hover .next-candidate-arrow { transform: translateX(10px); }
            
            @media (max-width: 991px) {
                .candidato-detalle-container { flex-direction: column; }
                .candidato-sidebar { flex-direction: row; width: 100%; overflow-x: auto; padding-bottom: 1.5rem; position: static; }
                .mini-card { width: 85px; height: 110px; flex-shrink: 0; }
                .mini-card.active, .mini-card:hover { transform: scale(1.05) translateY(-5px); }
                .candidato-content { padding: 1.5rem; }
                .candidate-top-row { flex-direction: column; gap: 2rem; margin-bottom: 1rem; }
                .candidato-photo-wrapper { width: 100%; max-width: 380px; margin: 0 auto; }
                .photo-badge { left: 0; top: 1rem; }
                .candidate-top-info h2 { font-size: 2.5rem; text-align: center; }
                .candidate-badges { justify-content: center; }
                .candidate-top-info { width: 100%; text-align: left; }
                .timeline-body { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
                .timeline-carousel-wrapper { width: 100%; height: 200px; }
                .next-candidate-module { flex-direction: column; text-align: center; gap: 1.5rem; }
                .next-candidate-arrow { transform: rotate(90deg); }
                .next-candidate-module:hover .next-candidate-arrow { transform: rotate(90deg) translateY(6px); }
                .facebook-layout-grid { grid-template-columns: 1fr; gap: 2rem; padding: 1.5rem; text-align: center; }
                .facebook-text { align-items: center; }
                .facebook-text p { max-width: 100%; }
                .fb-widget-container { width: 100%; margin: 0 auto; }
            }
            
            /* Contenedor invisible por defecto para animarlo al estilo "fade-right" */
            .candidato-detalle-container { opacity: 0; }
            
            /* Botón Cerrar Detalles */
            .close-detail-btn { position: absolute; top: 1.5rem; right: 5%; background: rgba(255, 195, 0, 0.15); color: #ffc300; padding: 0.6rem 1.2rem; border-radius: 50px; font-family: 'Arial Black Web', "Arial Black", Arial, sans-serif; font-size: 0.85rem; cursor: pointer; border: 1px solid rgba(255, 195, 0, 0.4); transition: all 0.3s ease; z-index: 100; backdrop-filter: blur(10px); }
            .close-detail-btn:hover { background: #ffc300; color: #801039; transform: scale(1.05); box-shadow: 0 5px 15px rgba(255,195,0,0.3); }

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
        if (isReload) sessionStorage.removeItem('fuerzaTacnaLoaderPlayed');

        // Ocultar antes de inyectar si ya se reprodujo, evita el pestañeo
        if (sessionStorage.getItem('fuerzaTacnaLoaderPlayed')) {
            loaderDiv.style.display = 'none';
        }
        loaderDiv.innerHTML = `
            <h1>FUERZA<br>TACNA</h1>
            <div class="water-layer"><div class="water-content"><h1>FUERZA<br>TACNA</h1></div></div>
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
        chatDiv.innerHTML = `
            <button id="ft-chat-fab" aria-label="Abrir Asistente IA">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </button>
            <div id="ft-chat-window">
                <div class="ft-chat-header">
                    <div class="ft-chat-title"><svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg><span>Asistente IA</span></div>
                    <button id="ft-chat-close" aria-label="Cerrar chat"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                <div class="ft-chat-body" id="ft-chat-messages">
                    <div class="ft-message ai-message">
                        <div class="ft-avatar">🤖</div>
                        <div class="ft-bubble">¡Hola! Soy el asistente inteligente de <strong>Fuerza Tacna</strong>. ¿En qué te puedo ayudar hoy?</div>
                    </div>
                </div>
                <div class="ft-chat-footer">
                    <input type="text" id="ft-chat-input" placeholder="Escribe tu consulta aquí..." autocomplete="off">
                    <button id="ft-chat-send" aria-label="Enviar mensaje"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg></button>
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
    tl.set(".loading-screen", { bottom: "-100%", height: "100%" });
    tl.to(".loading-screen", 1.2, {
        width: "100%",
        bottom: "0%",
        ease: "Expo.easeInOut",
    });

    tl.to(".loading-screen", 1, {
        width: "100%",
        bottom: "100%",
        ease: "Expo.easeInOut",
        delay: 0.3,
    });
    tl.set(".loading-screen", { bottom: "-100%" });

}

function contentAnimation() {
    var tl = new TimelineMax();
    tl.staggerFrom(".animate-this", 1, { opacity: 0, delay: 0.2 }, 0.4);
}

function initialLoadAnimation() {
    // Failsafe de seguridad extrema: si por algún motivo la animación falla, forzamos ocultarla tras 6.5s
    setTimeout(() => {
        const loader = document.querySelector('.initial-loader');
        if (loader) loader.style.display = 'none';
    }, 6500);

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
        document.querySelector('.initial-loader').style.display = 'none';
    }
}

$(function () {
    barba.init({
        sync: true,

        transitions: [
            {
                async leave(data) {
                    const done = this.async();

                    pageTransition();
                    await delay(1000);
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
barba.hooks.enter(() => {

  function load_js()
           {
              var head= document.getElementsByTagName('head')[0];
              var script= document.createElement('script');
              script.src= 'assets/js/button.js';
              head.appendChild(script);
           }


});

function initCandidatos(container) {
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
            originalCards.forEach(card => {
                const clone = card.cloneNode(true);
                marqueeContent.appendChild(clone);
            });
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
                
                const nameTag = card.querySelector('h3');
                const name = nameTag ? nameTag.textContent.trim() : '';
                console.log("✅ Clic VÁLIDO en la tarjeta de:", name);
                if (name) window.showCandidateDetail(name);
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
                const loopWidth = (cardWidth + gap) * (cards.length / 2);
                
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
    }, 150); // El retraso de 150ms garantiza que el CSS y anchos se apliquen primero
}

// --- LÓGICA DE DETALLE Y MINIATURAS ---
function extractUniqueCandidates() {
    const allCards = document.querySelectorAll('.marquee-content .candidate-card');
    const unique = [];
    const seen = new Set();
    allCards.forEach(card => {
            const name = card.querySelector('h3')?.textContent.trim() || '';
        if(!seen.has(name) && name !== '') {
            seen.add(name);
            unique.push({
                name: name,
                role: card.querySelector('p')?.innerText || 'Candidato',
                imgSrc: card.querySelector('.img-default')?.src || card.querySelector('img')?.src || '',
                // Si quieres textos específicos por candidato, ponlos en tu HTML en un atributo 'data-bio="Tu texto..."' en la .candidate-card
                bio: card.getAttribute('data-bio') || 'Nuestra prioridad es devolver la confianza al pueblo con obras reales, transparencia y una gestión eficiente. Tenemos la experiencia y la energía para construir una ciudad segura, moderna y con igualdad de oportunidades para todos. ¡Somos la Fuerza Ganadora!'
            });
        }
    });
    return unique;
}

// Esta función es global para que pueda ser llamada desde los eventos onclick de las miniaturas
window.showCandidateDetail = function(selectedName) {
    const candidates = extractUniqueCandidates();
    const selectedCandidate = candidates.find(c => c.name === selectedName) || candidates[0];
    
    const currentIndex = candidates.findIndex(c => c.name === selectedName);
    const nextCandidate = candidates[(currentIndex + 1) % candidates.length];
    
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
        const isActive = c.name === selectedName ? 'active' : '';
        // Usamos onclick inline para agilizar la interacción
        sidebarHTML += `
            <div class="mini-card ${isActive}" onclick="window.showCandidateDetail('${c.name}')">
                <img src="${c.imgSrc}" alt="${c.name}" loading="lazy" decoding="async">
            </div>`;
    });
    sidebarHTML += `</div>`;
    
    // 2. Construir Contenido Principal
    let contentHTML = `
        <div class="candidato-content animate-detail-element">
            <div id="sec-perfil" class="candidate-top-row">
                <div class="candidato-photo-wrapper stagger-el">
                    <div class="photo-glow"></div>
                    <div class="photo-badge">${selectedCandidate.role}</div>
                    <div class="candidato-photo">
                        <img src="${selectedCandidate.imgSrc}" alt="${selectedCandidate.name}" loading="lazy" decoding="async">
                    </div>
                </div>
                <div class="candidate-top-info">
                    <h2 class="stagger-el">${selectedCandidate.name}</h2>
                    
                    <div class="candidate-badges stagger-el">
                        <span class="badge">📍 Tacna Centro</span>
                        <span class="badge">💼 Gestión Pública</span>
                        <span class="badge">🎯 Desarrollo Social</span>
                    </div>

                    <div class="candidate-quote stagger-el">
                        <p>"Trabajaré incansablemente por una ciudad más segura, ordenada y con verdaderas oportunidades de crecimiento para todos los tacneños."</p>
                        <span class="quote-author">— ${selectedCandidate.name} | ${selectedCandidate.role}</span>
                    </div>
                    
                    <p class="stagger-el" style="margin-bottom: 2.5rem; font-size: 1.1rem; line-height: 1.8; color: #ddd; max-width: 85ch;">
                        ${selectedCandidate.bio}
                    </p>
                </div>
            </div>

            <div class="candidate-bottom-row">
                <div id="sec-trayectoria" class="info-block">
                    <div class="block-title stagger-el">⏱️ Trayectoria Profesional</div>
                    <div class="timeline">
                        <div class="timeline-item stagger-el">
                            <div class="timeline-year">2018 – 2020</div>
                            <div class="timeline-body">
                                <div class="timeline-content-left">
                                    <p class="timeline-text">Líder en proyectos de desarrollo social comunitario y apoyo directo a familias vulnerables en distintos sectores. Implementación de ollas comunes y asistencia básica.</p>
                                </div>
                                <div class="timeline-carousel-wrapper">
                                    <div class="timeline-carousel-content">
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/photo-service-1.jpg" alt="Actividad 2018-1" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-1.jpg', 'assets/img/design-service-1.jpg', 'assets/img/photo-service-2.jpg'])">
                                        </div>
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/design-service-1.jpg" alt="Actividad 2018-2" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-1.jpg', 'assets/img/design-service-1.jpg', 'assets/img/photo-service-2.jpg'])">
                                        </div>
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/photo-service-2.jpg" alt="Actividad 2018-3" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-1.jpg', 'assets/img/design-service-1.jpg', 'assets/img/photo-service-2.jpg'])">
                                        </div>
                                    </div>
                                    <div class="timeline-carousel-nav prev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg></div>
                                    <div class="timeline-carousel-nav next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg></div>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-item stagger-el">
                            <div class="timeline-year">2021 – 2023</div>
                            <div class="timeline-body">
                                <div class="timeline-content-left">
                                    <p class="timeline-text">Gestión estratégica en iniciativas vecinales orientadas a la recuperación de espacios urbanos abandonados, creando nuevas áreas de recreación seguras para la juventud.</p>
                                </div>
                                <div class="timeline-carousel-wrapper">
                                    <div class="timeline-carousel-content">
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/photo-service-2.jpg" alt="Actividad 2021-1" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-2.jpg', 'assets/img/design-service-3.jpg'])">
                                        </div>
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/design-service-3.jpg" alt="Actividad 2021-2" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-2.jpg', 'assets/img/design-service-3.jpg'])">
                                        </div>
                                    </div>
                                    <div class="timeline-carousel-nav prev"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg></div>
                                    <div class="timeline-carousel-nav next"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg></div>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-item stagger-el">
                            <div class="timeline-year">2024 – Presente</div>
                            <div class="timeline-body">
                                <div class="timeline-content-left">
                                    <p class="timeline-text">Candidatura oficial impulsando planes de modernización tecnológica e infraestructura pública regional, promoviendo el emprendimiento formal.</p>
                                </div>
                                <div class="timeline-carousel-wrapper">
                                    <div class="timeline-carousel-content">
                                        <div class="timeline-carousel-item">
                                            <img src="assets/img/photo-service-3.jpg" alt="Actividad 2024-1" loading="lazy" onclick="window.openCandidateGallery(['assets/img/photo-service-3.jpg'])">
                                        </div>
                                    </div>
                                    <div class="timeline-carousel-nav prev"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg></div>
                                    <div class="timeline-carousel-nav next"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sec-propuestas" class="info-block">
                    <div class="block-title stagger-el">🚀 Ejes de Propuesta</div>
                    <div class="proposals-grid">
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">🛡️</div>
                            <h6>Seguridad Total</h6>
                            <p>Cámaras 4K, drones de vigilancia táctica y un moderno sistema de patrullaje integrado 24/7.</p>
                        </div>
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">🌳</div>
                            <h6>Espacios Vivos</h6>
                            <p>Creación de 5 mega-parques ecológicos y la recuperación total de áreas recreativas familiares.</p>
                        </div>
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">📈</div>
                            <h6>Emprendimiento</h6>
                            <p>Fondo de apoyo directo a emprendedores locales con digitalización y cero papeleo.</p>
                        </div>
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">🛣️</div>
                            <h6>Vías Modernas</h6>
                            <p>Plan agresivo de pavimentación de calles, ordenamiento vial y mejora del transporte público.</p>
                        </div>
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">🏥</div>
                            <h6>Salud Integral</h6>
                            <p>Mejoramiento de las postas médicas y campañas de salud preventiva gratuitas para los vecinos.</p>
                        </div>
                        <div class="proposal-card stagger-el">
                            <div class="proposal-icon">📚</div>
                            <h6>Educación 3.0</h6>
                            <p>Internet gratuito en plazas públicas y apoyo con herramientas tecnológicas para estudiantes.</p>
                        </div>
                    </div>
                </div>
                
                <div id="sec-facebook" class="info-block">
                    <div class="block-title stagger-el">📱 Actividad Reciente</div>
                    <div class="facebook-layout-grid stagger-el">
                        <div class="facebook-text">
                            <h3>¡Sigue mi campaña!</h3>
                            <p>Conéctate a mis redes sociales para enterarte de los últimos recorridos, propuestas en vivo y conversar directamente conmigo. ¡Tu voz importa para el futuro de Tacna!</p>
                            <a href="https://www.facebook.com/lilianabustinza.sa" target="_blank" class="action-btn primary" style="padding: 0.8rem 2rem; font-size: 0.85rem;">Ver Perfil Completo</a>
                        </div>
                        <div class="fb-widget-container">
                            <iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Flilianabustinza.sa&tabs=timeline&width=500&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId" width="500" height="500" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                        </div>
                    </div>
                </div>

                <div class="candidate-actions stagger-el" style="justify-content: center; margin-bottom: 3.5rem;">
                    <a href="contacto.html" class="action-btn outline" style="padding: 1.2rem 3rem;">Contactar Candidato</a>
                </div>

                <div class="stagger-el">
                    <div class="next-candidate-module" onclick="window.showCandidateDetail('${nextCandidate.name}')">
                        <div class="next-candidate-info">
                            <img src="${nextCandidate.imgSrc}" alt="${nextCandidate.name}" class="next-candidate-avatar" loading="lazy" decoding="async">
                            <div class="next-candidate-text">
                                <h5>Siguiente Perfil</h5>
                                <h3>${nextCandidate.name}</h3>
                            </div>
                        </div>
                        <div class="next-candidate-arrow">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </div>
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
                child.setAttribute('data-original-display', child.style.display || '');
                child.style.display = 'none';
                child.classList.add('hidden-by-detail');
            }
        }
    });

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
            const targetPos = wrapper.offsetTop - (window.innerHeight * 0.1); // Centrar bonito
            
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
                    child.style.display = child.getAttribute('data-original-display') || '';
                    child.classList.remove('hidden-by-detail');
                });

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

function initCircularCarousel(container) {
    const target = container || document;
    const carousel = target.querySelector('.circular-testimonial-container');
    if (!carousel) return;

    // Limpiar memoria previa si existía
    if (window.circularCarouselCleanup) window.circularCarouselCleanup();

    const images = carousel.querySelectorAll('.testimonial-image');
    const nameEl = target.querySelector('#carousel-name');
    const desigEl = target.querySelector('#carousel-designation');
    const quoteEl = target.querySelector('#carousel-quote');
    const prevBtn = target.querySelector('#carousel-prev');
    const nextBtn = target.querySelector('#carousel-next');

    if (!images.length) return;

    const candidatesData = [
        { name: "Patrick Stewart", designation: "Candidato a Alcalde", quote: "Comprometido con el desarrollo urbano y la seguridad de nuestra ciudad para un futuro próspero." },
        { name: "Alena Rosser", designation: "Regidora", quote: "Trabajando por una educación inclusiva, el deporte y oportunidades reales para todos los jóvenes." },
        { name: "Fletch Skinner", designation: "Regidor", quote: "Transparencia y honestidad para una gestión municipal eficiente que realmente escuche al pueblo." },
        { name: "Marc Spector", designation: "Regidor", quote: "Innovación y tecnología para modernizar nuestros servicios públicos y mejorar tu calidad de vida." },
        { name: "Natalia Skinner", designation: "Regidora", quote: "Defendiendo el medio ambiente y creando espacios verdes sostenibles para las familias." }
    ];

    let activeIndex = 0;
    const total = images.length;
    let autoplayInterval;

    function updateCarousel() {
        const width = carousel.offsetWidth || 500;
        const gap = Math.min(120, Math.max(60, width * 0.22));
        const maxStickUp = gap * 0.5;

        images.forEach((img, i) => {
            const isLeft = (activeIndex - 1 + total) % total === i;
            const isRight = (activeIndex + 1) % total === i;
            const isFarLeft = (activeIndex - 2 + total) % total === i;
            const isFarRight = (activeIndex + 2) % total === i;

            if (i === activeIndex) {
                img.style.transform = 'translateX(0px) translateY(0px) scale(1) rotateY(0deg)';
                img.style.zIndex = 5; img.style.opacity = 1; img.style.pointerEvents = "auto";
            } else if (isLeft) {
                img.style.transform = `translateX(-${gap}px) translateY(-${maxStickUp}px) scale(0.85) rotateY(15deg)`;
                img.style.zIndex = 4; img.style.opacity = 1; img.style.pointerEvents = "auto";
            } else if (isRight) {
                img.style.transform = `translateX(${gap}px) translateY(-${maxStickUp}px) scale(0.85) rotateY(-15deg)`;
                img.style.zIndex = 4; img.style.opacity = 1; img.style.pointerEvents = "auto";
            } else if (isFarLeft) {
                img.style.transform = `translateX(-${gap * 1.8}px) translateY(-${maxStickUp * 1.8}px) scale(0.7) rotateY(25deg)`;
                img.style.zIndex = 3; img.style.opacity = 0.5; img.style.pointerEvents = "auto";
            } else if (isFarRight) {
                img.style.transform = `translateX(${gap * 1.8}px) translateY(-${maxStickUp * 1.8}px) scale(0.7) rotateY(-25deg)`;
                img.style.zIndex = 3; img.style.opacity = 0.5; img.style.pointerEvents = "auto";
            } else {
                img.style.transform = 'translateX(0px) translateY(0px) scale(0.5) rotateY(0deg)';
                img.style.zIndex = 1; img.style.opacity = 0; img.style.pointerEvents = "none";
            }
        });

        // Animación de cambio de texto
        TweenMax.killTweensOf([nameEl, desigEl, quoteEl]);
        TweenMax.to([nameEl, desigEl, quoteEl], 0.2, { y: -10, opacity: 0, onComplete: () => {
            nameEl.textContent = candidatesData[activeIndex].name;
            desigEl.textContent = candidatesData[activeIndex].designation;
            quoteEl.textContent = candidatesData[activeIndex].quote;
            TweenMax.staggerFromTo([nameEl, desigEl, quoteEl], 0.3, { y: 10, opacity: 0 }, { y: 0, opacity: 1 }, 0.05);
        }});
    }

    function next() { activeIndex = (activeIndex + 1) % total; updateCarousel(); resetAutoplay(); }
    function prev() { activeIndex = (activeIndex - 1 + total) % total; updateCarousel(); resetAutoplay(); }
    function resetAutoplay() { clearInterval(autoplayInterval); autoplayInterval = setInterval(next, 5000); }

    nextBtn.addEventListener('click', next);
    prevBtn.addEventListener('click', prev);
    window.addEventListener('resize', updateCarousel);
    images.forEach((img, i) => { img.addEventListener('click', () => { if (i !== activeIndex) { activeIndex = i; updateCarousel(); resetAutoplay(); }}); });

    updateCarousel();
    resetAutoplay();

    window.circularCarouselCleanup = () => {
        clearInterval(autoplayInterval);
        window.removeEventListener('resize', updateCarousel);
    };
}

// --- Scroll Magnético Seguro (Efecto Imán Retardado) ---
let isMagneticScrollInitialized = false;
function initSafeMagneticScroll() {
    if (isMagneticScrollInitialized) return;
    isMagneticScrollInitialized = true;
    
    let snapTimeout;
    let isAutoScrolling = false;

    // Detectar interacciones reales del usuario para cancelar el imán si se arrepiente
    const handleUserInteraction = () => {
        if (window.currentScrollAnim) {
            cancelAnimationFrame(window.currentScrollAnim);
            window.currentScrollAnim = null;
            isAutoScrolling = false;
            if (window.scroller && typeof window.scroller.setPostion === 'function') {
                window.scroller.setPostion(window.scrollY);
            }
        }
    };

    const handleScroll = () => {
        // Si el scroll es provocado por nuestro propio imán, no hacemos nada
        if (isAutoScrolling) return;

        clearTimeout(snapTimeout);

        // Pausar el imán temporalmente si la vista de detalle de un candidato o algún modal están abiertos
        const detailWrapper = document.getElementById('detalle-candidato-wrapper');
        const isDetailOpen = detailWrapper && detailWrapper.style.display !== 'none';
        if (document.body.classList.contains('hide-header') || isDetailOpen) {
            return;
        }

        snapTimeout = setTimeout(() => {
            const sections = [
                { id: 'hero-section', offset: 0, threshold: 0.50 }, 
                { id: 'intro', align: 'center', threshold: 0.60 }, 
                { id: 'video-section', align: 'center', threshold: 0.60 },
                { id: 'drone-section', align: 'center', threshold: 0.60 },
                { id: 'votar-section', align: 'center', threshold: 0.60 },
                // Secciones globales (Quiénes Somos, Candidatos, Contacto)
                { id: 'quienes-somos-timeline', align: 'center', threshold: 0.50 },
                { id: 'candidatos-section', align: 'center', threshold: 0.50 },
                { id: 'contacto-escenario', align: 'center', threshold: 0.50 }
            ];

            const currentY = window.scrollY;
            const viewportHeight = window.innerHeight;
            
            let closestTarget = null;
            let closestId = null;
            let minDistance = Infinity;
            let activeThreshold = 0.60; 

            sections.forEach(secData => {
                const el = document.getElementById(secData.id);
                if (!el) return;
                
                // Calculamos la posición absoluta Y usando getBoundingClientRect para evitar fallos si el elemento está anidado
                const rect = el.getBoundingClientRect();
                
                let targetY;
                if (secData.align === 'center') {
                    // Calcula el centro exacto de la pantalla respecto al centro del elemento
                    targetY = (rect.top + currentY) - (viewportHeight / 2) + (rect.height / 2);
                } else {
                    targetY = (rect.top + currentY) - (secData.offset !== undefined ? secData.offset : 60);
                }
                
                if (targetY < 0) targetY = 0; // Evita valores negativos
                
                const distance = Math.abs(currentY - targetY);

                if (distance < minDistance) {
                    minDistance = distance;
                    closestTarget = targetY;
                    closestId = secData.id;
                    activeThreshold = secData.threshold !== undefined ? secData.threshold : 0.60;
                }
            });

            let shouldSnap = false;

            if (closestTarget !== null) {
                if (closestId === window.lastSnappedId) {
                    // Si seguimos en la misma sección, el rango para volver a centrarte es pequeño
                    if (minDistance > 5 && minDistance < 80) {
                        shouldSnap = true;
                    }
                } else {
                    if (minDistance < viewportHeight * activeThreshold) {
                        shouldSnap = true;
                        window.lastSnappedId = closestId; // Guardamos registro de quién te atrapó
                    }
                }
            }

            if (shouldSnap) {
                isAutoScrolling = true;
                const startY = window.scrollY;
                const distance = closestTarget - startY;
                let startTime = null;
                const duration = 900; // Fricción elegante
                
                function customAnim(currentTime) {
                    if (!startTime) startTime = currentTime;
                    const progress = Math.min((currentTime - startTime) / duration, 1);
                    const ease = 1 - Math.pow(1 - progress, 5); 
                    window.scrollTo(0, startY + distance * ease);
                    if (progress < 1) {
                        window.currentScrollAnim = requestAnimationFrame(customAnim);
                    } else {
                        window.currentScrollAnim = null;
                        if (window.scroller && typeof window.scroller.setPostion === 'function') window.scroller.setPostion(closestTarget);
                        // Liberar el auto-scroll despues de un margen de seguridad
                        setTimeout(() => { isAutoScrolling = false; }, 50);
                    }
                }
                window.currentScrollAnim = requestAnimationFrame(customAnim);
            }
        }, 150); // PACIENCIA DEL IMÁN: 150ms para que se active más rápido apenas termine la inercia
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('wheel', handleUserInteraction, { passive: true });
    window.addEventListener('touchstart', handleUserInteraction, { passive: true });
    window.addEventListener('touchmove', handleUserInteraction, { passive: true });
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
                window.scrollTo({ top: targetY, behavior: 'smooth' });
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
                        defs.innerHTML += `
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
                        `;
                    }
                    
                    // --- NUEVO: Generar Puntos de Pulsación Aleatorios ---
                    setTimeout(() => {
                        const pt = svgTag.createSVGPoint();
                        const elements = svgTag.querySelectorAll('path, polygon');
                        
                        elements.forEach(el => {
                            try {
                                const bbox = el.getBBox();
                                let dotsCreated = 0;
                                let attempts = 0;
                                
                                const gDots = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                                gDots.setAttribute('class', 'pulsing-dots');
                                gDots.style.pointerEvents = 'none'; // No bloquear tooltips ni hover
                                
                                while(dotsCreated < 6 && attempts < 1000) {
                                    attempts++;
                                    const rx = bbox.x + Math.random() * bbox.width;
                                    const ry = bbox.y + Math.random() * bbox.height;
                                    
                                    pt.x = rx; 
                                    pt.y = ry;
                                    
                                    let isInside = false;
                                    if (el.isPointInFill) {
                                        try {
                                            // Intento 1: Coordenadas locales (Navegadores Modernos)
                                            isInside = el.isPointInFill(pt);
                                        } catch(err) {}
                                        
                                        if (!isInside) {
                                            try {
                                                // Intento 2: Coordenadas de pantalla (Fallback clásico)
                                                const matrix = el.getScreenCTM();
                                                if (matrix) {
                                                    const clientPt = pt.matrixTransform(matrix);
                                                    isInside = el.isPointInFill(clientPt);
                                                }
                                            } catch(err) {}
                                        }
                                    }

                                    // SOLO dibujamos el radar si estamos 100% seguros de que cayó DENTRO del mapa
                                    if (isInside) {
                                        dotsCreated++;
                                        const baseR = 5 + Math.random() * 3; // Puntos centrales MUCHO más notorios (5 a 8px)
                                        const maxR = baseR + 30 + Math.random() * 30; // Ondas gigantes que viajan lejos
                                        const dur = 2 + Math.random() * 1.5;
                                        const delay = Math.random() * 2;
                                        
                                        // Mezclamos puntos Blancos y Dorados aleatoriamente para darle más vida
                                        const color = Math.random() > 0.4 ? '#ffffff' : '#ffc300';
                                        
                                        gDots.innerHTML += `
                                            <g transform="translate(${rx}, ${ry})">
                                                <!-- Punto central -->
                                                <circle r="${baseR}" fill="${color}" opacity="1" />
                                                
                                                <!-- Primera onda expansiva (Línea) -->
                                                <circle r="${baseR}" fill="none" stroke="${color}" stroke-width="2.5">
                                                    <animate attributeName="r" from="${baseR}" to="${maxR}" dur="${dur}s" begin="${delay}s" repeatCount="indefinite" />
                                                    <animate attributeName="opacity" from="0.7" to="0" dur="${dur}s" begin="${delay}s" repeatCount="indefinite" />
                                                </circle>
                                                
                                                <!-- Segunda onda expansiva intercalada (Línea) -->
                                                <circle r="${baseR}" fill="none" stroke="${color}" stroke-width="2.5">
                                                    <animate attributeName="r" from="${baseR}" to="${maxR}" dur="${dur}s" begin="${delay + (dur/2)}s" repeatCount="indefinite" />
                                                    <animate attributeName="opacity" from="0.7" to="0" dur="${dur}s" begin="${delay + (dur/2)}s" repeatCount="indefinite" />
                                                </circle>
                                            </g>
                                        `;
                                    }
                                }
                                
                                if (el.parentNode) {
                                    el.parentNode.appendChild(gDots);
                                }
                            } catch(e) {
                                console.warn("Puntos de pulsación omitidos:", e);
                            }
                        });
                    }, 600); // Darle tiempo a la web de calcular la matriz exacta del mapa
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
    AOS.init({
        duration: 1000,
        easing: 'ease',
        once: true
    });
    // Refresco forzado de AOS milisegundos después para detectar nuevo HTML de Barba
    setTimeout(() => AOS.refreshHard(), 100);
    setTimeout(() => AOS.refreshHard(), 500); // Segundo refresco de seguridad
    initCandidatos(container);
    initCircularCarousel(container);
    initTimelineCarousels(container);
    initMapaTacna(container);
    initSafeMagneticScroll();
    initVideoScrollFix(container);
    initSmoothArrows(container);
    initCircularTimeline(container);
    // Paso 2: Despertar el mapa de Leaflet cuando entramos a la página del mapa
    if (typeof initLeafletMap === 'function') initLeafletMap(container);
    // Paso 1: Despertar la lógica de los filtros que ahora vive en mapa-filtros.js
    if (typeof initFilters === 'function') initFilters();
    // Inicializador del cómic interactivo de la página de contacto
    if (typeof initContactoComic === 'function') initContactoComic(container);
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

function initCircularTimeline(container) {
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

    const baseEvents = [
        { year: "2018", description: "Iniciamos nuestro camino con la firme convicción de construir una Tacna más fuerte, uniendo a líderes vecinales.", details: "<p>En este año, comenzamos reuniéndonos con representantes de diferentes distritos para escuchar de primera mano las necesidades más urgentes. <strong>Fuerza Tacna</strong> nace como una respuesta directa a la falta de liderazgo y a la necesidad de construir un proyecto político con base ciudadana y visión a largo plazo.</p><img src='assets/img/historia-2018.jpg' loading='lazy'>" },
        { year: "2019", description: "Consolidamos nuestras bases vecinales en diferentes distritos de la región, escuchando al pueblo.", details: "<p>Se abrieron los primeros locales partidarios y se formaron comités en cada junta vecinal. Este año estuvo marcado por un fuerte trabajo de campo, recorriendo asentamientos humanos y asociaciones de vivienda para empadronar a nuevos militantes y recoger las verdaderas prioridades de la población.</p><img src='assets/img/historia-2019.jpg' loading='lazy'>" },
        { year: "2020", description: "Lanzamiento de nuestros programas de apoyo solidario durante tiempos de crisis, apoyando a las familias.", details: "<p>Frente a los desafíos de la pandemia, adaptamos nuestro enfoque hacia la asistencia social directa. Implementamos campañas de donación de alimentos, asesoría médica gratuita y creación de ollas comunes solidarias, demostrando que nuestra fuerza está en la solidaridad y el trabajo en equipo.</p><img src='assets/img/historia-2020.jpg' loading='lazy'>" },
        { year: "2021", description: "Expansión de nuestro equipo técnico para diseñar soluciones urbanas sostenibles y modernas.", details: "<p>Convocamos a profesionales tacneños de primer nivel (ingenieros, arquitectos, economistas) para conformar las mesas técnicas. Juntos comenzamos a estructurar proyectos viables en transporte, seguridad ciudadana y servicios básicos, asegurando que cada propuesta tenga un sustento técnico sólido.</p><img src='assets/img/historia-2021.jpg' loading='lazy'>" },
        { year: "2022", description: "Participación histórica en elecciones locales, ganando representación y consolidando la confianza ciudadana.", details: "<p>Marcamos un hito en nuestra historia política al lograr una importante votación que nos permitió obtener representación en diferentes niveles de gobierno local. Esto demostró el respaldo popular y nos impulsó a seguir trabajando con mayor compromiso y fiscalización.</p><img src='assets/img/historia-2022.jpg' loading='lazy'>" },
        { year: "2023", description: "Mesas de trabajo ciudadanas y foros para la creación integral de nuestro plan de gobierno.", details: "<p>Realizamos asambleas públicas participativas donde la ciudadanía tuvo voz y voto en la construcción de nuestro plan de gobierno. Integramos propuestas de jóvenes, emprendedores y asociaciones civiles para asegurar un plan de desarrollo verdaderamente inclusivo.</p>" },
        { year: "2024", description: "Presentación oficial de nuestra campaña por la Alcaldía, con propuestas concretas, transparentes y viables.", details: "<p>Iniciamos nuestra campaña central enfocada en la innovación y la transparencia. Recorrimos calles y plazas presentando nuestra visión de una Tacna ordenada, con megaproyectos de infraestructura, modernización del comercio y tolerancia cero a la corrupción.</p>" },
        { year: "2025", description: "Implementación de foros de inversión y alianzas estratégicas para el financiamiento de megaproyectos.", details: "<p>Nos proyectamos a gestionar asociaciones público-privadas para viabilizar obras de envergadura. Planeamos atraer inversión nacional e internacional para modernizar nuestro sistema de transporte y crear verdaderos parques ecológicos zonales.</p>" },
        { year: "2026", description: "Proyección hacia una ciudad inteligente y segura, garantizando obras reales y bienestar para todos.", details: "<p>Nuestro objetivo es implementar el primer centro de monitoreo integral (Smart City) en Tacna, con cámaras 4K, drones de vigilancia y botones de pánico conectados en tiempo real, garantizando la paz y tranquilidad de las familias.</p>" },
        { year: "2028", description: "Inauguración de la primera etapa del nuevo sistema de transporte integrado y parques ecológicos.", details: "<p>Vislumbramos la entrega de corredores viales modernos que reduzcan los tiempos de viaje y la inauguración de pulmones verdes en áreas antes abandonadas. Un modelo de ciudad que prioriza al peatón y al transporte limpio.</p>" },
        { year: "2030", description: "Tacna consolidada como la capital de la innovación, el turismo y el desarrollo sostenible en el sur.", details: "<p>Vemos a nuestra Heroica Ciudad como el principal polo de desarrollo del sur del Perú. Una metrópoli ordenada, segura, que atrae turismo todo el año, potencia a sus emprendedores y brinda calidad de vida a cada uno de sus habitantes.</p>" }
    ];

    // TRUCO MATEMÁTICO: Duplicamos la lista para rellenar el círculo gigante de 1600px 
    // y hacer que las fechas estén mucho más cerca unas de otras sin romper el circuito cerrado.
    const events = [...baseEvents, ...baseEvents];

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
            let delayTime = 1000; // 1 segundo para que termine de subir el telón negro
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
