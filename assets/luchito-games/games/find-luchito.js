window.LuchitoGames.registerGame('find-luchito', {
    title: 'Encuentra a Luchito',
    render: function(container, level = 'medium', gameMeta = {}) {
        const styleId = 'luchito-find-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-find-game { display: flex; flex-direction: column; font-family: system-ui, sans-serif; width: 100%; height: 100%; min-height: 0; position: relative; }
                .lg-game-wrapper.lg-find-game { max-width: none; padding: 0; }
                .lg-find-stage { position: relative; width: 100%; height: 100%; min-height: 0; }
                .lg-find-panel { position: absolute; top: 18px; left: 22px; z-index: 6; width: min(285px, calc(100vw - 44px)); display: flex; flex-direction: column; gap: 0.78rem; color: #801039; pointer-events: none; }
                .lg-find-panel-actions { display: flex; gap: 0.45rem; align-items: center; pointer-events: auto; }
                .lg-find-panel-actions .lg-back-btn { margin-right: 0; padding: 8px 14px; font-size: 11px; white-space: nowrap; box-sizing: border-box; background: rgba(253,245,247,0.86); backdrop-filter: blur(7px); }
                .lg-find-mini-close { background: rgba(253,245,247,0.82); backdrop-filter: blur(7px); border: 1px solid rgba(128,16,57,0.25); color: #801039; border-radius: 999px; width: 36px; height: 36px; cursor: pointer; font-weight: 900; font-size: 16px; line-height: 1; padding: 0; }
                .lg-find-timer-card { display: block; margin-top: 0.1rem; color: #801039; filter: drop-shadow(0 3px 0 rgba(255,255,255,0.9)) drop-shadow(0 10px 18px rgba(128,16,57,0.18)); }
                .lg-find-timer-label { display: block; font-size: clamp(1rem, 1.6vw, 1.28rem); font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; color: #801039; }
                .lg-find-timer-value { display: block; font-family: 'Arial Black Web', sans-serif; font-size: clamp(5.4rem, 9vw, 8.2rem); line-height: 0.78; color: #801039; text-shadow: 0 5px 0 #ffc300, 0 14px 24px rgba(0,0,0,0.16); }
                .lg-find-timer-card.urgent { animation: lgFindTimerPulse 0.72s ease-in-out infinite; }
                .lg-find-timer-card.urgent .lg-find-timer-value { color: #b91c1c; text-shadow: 0 4px 0 #ffc300, 0 0 18px rgba(239,68,68,0.45); }
                .lg-find-stats { display: flex; gap: 0.55rem; flex-wrap: wrap; }
                .lg-find-stat-card { background: rgba(253,245,247,0.86); backdrop-filter: blur(7px); border: 1px solid rgba(128,16,57,0.16); border-radius: 999px; padding: 0.58rem 0.82rem; box-shadow: 0 4px 10px rgba(0,0,0,0.04); min-width: 0; }
                .lg-find-stat-label { display: inline; font-size: clamp(0.78rem, 1.1vw, 0.92rem); text-transform: uppercase; letter-spacing: 0.03em; color: #8a6070; font-weight: 900; margin-right: 0.36rem; line-height: 1; }
                .lg-find-stat-value { display: inline; font-size: clamp(1.18rem, 1.7vw, 1.55rem); font-weight: 900; line-height: 1; }
                .lg-find-board { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; min-width: 0; min-height: 0; padding-left: min(300px, 22vw); box-sizing: border-box; transition: filter 0.25s ease, opacity 0.25s ease; }
                .lg-find-stage.intro-active .lg-find-board { filter: blur(7px); opacity: 0.72; pointer-events: none; }
                .lg-find-stage.wide-image .lg-find-panel { width: min(230px, calc(100vw - 44px)); gap: 0.58rem; }
                .lg-find-stage.wide-image .lg-find-timer-label { font-size: clamp(0.9rem, 1.2vw, 1.05rem); }
                .lg-find-stage.wide-image .lg-find-timer-value { font-size: clamp(4.2rem, 7vw, 6.3rem); }
                .lg-find-stage.wide-image .lg-find-board { padding-left: min(245px, 18vw); }
                .lg-find-scene { position: relative; width: fit-content; max-width: 100%; border: 0; outline: 3px solid #801039; outline-offset: 0; border-radius: 18px; overflow: hidden; background: #ffffff; box-shadow: 0 10px 24px rgba(0,0,0,0.12); cursor: crosshair; touch-action: none; flex: 0 1 auto; }
                .lg-find-scene.zoomed { cursor: grab; }
                .lg-find-scene.panning { cursor: grabbing; }
                .lg-find-scene.locked { cursor: default; }
                .lg-find-zoom-layer { position: relative; width: fit-content; max-width: 100%; transform-origin: 0 0; will-change: transform; }
                .lg-find-scene img { display: block; width: auto; height: auto; max-width: calc(100vw - 28px); max-height: calc(100vh - 36px); user-select: none; -webkit-user-drag: none; }
                .lg-find-zoom-controls { position: absolute; right: 14px; bottom: 14px; z-index: 9; display: flex; gap: 6px; align-items: center; padding: 6px; border-radius: 999px; background: rgba(253,245,247,0.86); border: 1px solid rgba(128,16,57,0.18); backdrop-filter: blur(7px); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
                .lg-find-zoom-btn { width: 34px; height: 34px; border: 0; border-radius: 999px; background: #801039; color: #ffc300; font-weight: 900; cursor: pointer; line-height: 1; font-size: 18px; }
                .lg-find-zoom-btn:hover { background: #5f0c2b; }
                .lg-find-zoom-level { min-width: 44px; text-align: center; color: #801039; font-weight: 900; font-size: 12px; }
                .lg-find-marker { position: absolute; width: 44px; height: 44px; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; }
                .lg-find-marker.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-marker.hit { border: 4px solid #16a34a; box-shadow: 0 0 0 999px rgba(22,163,74,0.08), 0 0 18px rgba(22,163,74,0.75); }
                .lg-find-marker.miss { border: 4px solid #ef4444; box-shadow: 0 0 0 999px rgba(239,68,68,0.06), 0 0 16px rgba(239,68,68,0.65); }
                .lg-find-miss-bubble { position: absolute; z-index: 8; left: var(--x); top: var(--y); transform: translate(-50%, calc(-100% - 14px)) scale(0.82); padding: 0.42rem 0.72rem; border-radius: 999px; background: rgba(128,16,57,0.92); color: #fff8d6; border: 2px solid #ffc300; font-family:'Arial Black Web', sans-serif; font-size: clamp(0.72rem, 1.3vw, 0.95rem); line-height: 1; white-space: nowrap; box-shadow: 0 10px 18px rgba(0,0,0,0.22); pointer-events: none; animation: lgFindMissBubble 1050ms cubic-bezier(0.18, 0.9, 0.24, 1.1) forwards; }
                .lg-find-miss-bubble::after { content: ""; position: absolute; left: 50%; bottom: -8px; width: 12px; height: 12px; transform: translateX(-50%) rotate(45deg); background: rgba(128,16,57,0.92); border-right: 2px solid #ffc300; border-bottom: 2px solid #ffc300; }
                .lg-find-target { position: absolute; border: 3px dashed #ffc300; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; background: rgba(255,195,0,0.1); box-shadow: 0 0 18px rgba(128,16,57,0.35); }
                .lg-find-target.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-hint { position: absolute; top: 16px; right: 16px; z-index: 4; max-width: min(360px, 48%); color: #801039; text-align: left; font-size: 0.95rem; font-weight: 800; line-height: 1.25; background: #ffffff; border: 3px solid #ffc300; border-radius: 28px; padding: 0.85rem 1.1rem; box-shadow: 0 12px 28px rgba(0,0,0,0.22); pointer-events: none; }
                .lg-find-hint::before { content: ""; position: absolute; right: 36px; bottom: -13px; width: 24px; height: 24px; background: #ffffff; border-right: 3px solid #ffc300; border-bottom: 3px solid #ffc300; border-bottom-right-radius: 20px; transform: rotate(45deg); }
                .lg-find-hint::after { content: ""; position: absolute; right: 18px; bottom: -28px; width: 13px; height: 13px; background: #ffffff; border: 3px solid #ffc300; border-radius: 50%; }
                .lg-find-hint.pending { opacity: 0; transform: translateY(-8px) scale(0.96); }
                .lg-find-hint.revealed { opacity: 1; transform: translateY(0) scale(1); transition: opacity 0.25s ease, transform 0.25s ease; }
                .lg-find-msg { min-height: 20px; font-weight: 800; color: #801039; font-size: 0.9rem; line-height: 1.25; }
                .lg-find-intro { position: absolute; inset: 0; z-index: 18; display: grid; place-items: center; padding: 1.5rem; background: rgba(253,245,247,0.28); pointer-events: auto; opacity: 1; transition: opacity 0.32s ease, visibility 0.32s ease; }
                .lg-find-intro.hide { opacity: 0; visibility: hidden; pointer-events: none; }
                .lg-find-intro-card { width: min(620px, 86vw); padding: clamp(1.2rem, 3vw, 2rem); border-radius: 24px; border: 2px solid rgba(128,16,57,0.14); background: rgba(255,250,252,0.9); backdrop-filter: blur(12px); box-shadow: 0 24px 60px rgba(47,20,33,0.2); color: #801039; animation: lgFindIntroPop 520ms cubic-bezier(0.18, 0.9, 0.24, 1.1) both; }
                .lg-find-intro-title { margin: 0; font-family:'Arial Black Web', sans-serif; text-transform: uppercase; font-size: clamp(2rem, 5vw, 4rem); line-height: 0.95; letter-spacing: 0; text-shadow: 0 2px 0 rgba(255,255,255,0.95); }
                .lg-find-intro-text { margin: 1rem 0 0; color: #5f3345; font-size: clamp(1.15rem, 2.5vw, 2rem); line-height: 1.28; border: 2px solid rgba(128,16,57,0.12); border-radius: 20px; padding: clamp(0.85rem, 2vw, 1.25rem); background: rgba(255,255,255,0.56); }
                /* Celebracion contenida: no cubre la imagen con filtros fuertes. */
                .luchito-found-overlay { --luchito-gold: #ffc300; --luchito-orange: #f59e0b; --luchito-glow: rgba(255,195,0,0.42); position: absolute; inset: 0; z-index: 30; display: flex; align-items: center; justify-content: center; padding: 18px; text-align: center; pointer-events: none; opacity: 0; transition: opacity 0.18s ease; overflow: hidden; }
                .luchito-found-overlay.show { opacity: 1; }
                .luchito-found-card { position: relative; display: grid; justify-items: center; gap: 0.55rem; max-width: min(520px, 78vw); }
                .luchito-pop { position: relative; display: grid; place-items: center; animation: luchitoFoundPop 620ms cubic-bezier(0.18, 0.9, 0.24, 1.15) both; }
                .luchito-found-glow { position: absolute; width: min(360px, 50vw); aspect-ratio: 1; border-radius: 999px; background: radial-gradient(circle, rgba(255,239,166,0.72) 0%, var(--luchito-glow) 34%, rgba(245,158,11,0.16) 52%, transparent 72%); filter: blur(10px); opacity: 0.78; animation: luchitoGlowPulse 1.4s ease-in-out infinite alternate; }
                .luchito-found-ripple { position: absolute; width: min(260px, 42vw); aspect-ratio: 1; border: 2px solid rgba(255,195,0,0.65); border-radius: 999px; opacity: 0; animation: luchitoRipple 760ms ease-out forwards; }
                .luchito-found-img { position: relative; z-index: 2; display: block; max-width: min(300px, 34vw); max-height: min(350px, 54vh); object-fit: contain; filter: drop-shadow(0 0 8px rgba(255,255,255,0.85)) drop-shadow(0 0 18px rgba(255,195,0,0.72)) drop-shadow(0 16px 20px rgba(47,24,7,0.35)); }
                .luchito-found-message { position: relative; z-index: 4; width: min(440px, 82vw); padding: 0.72rem 1rem; border: 1px solid rgba(255,195,0,0.32); border-radius: 16px; background: rgba(18, 12, 20, 0.72); backdrop-filter: blur(8px); box-shadow: 0 12px 28px rgba(0,0,0,0.2); }
                .luchito-found-title { margin: 0; color: #fff8d6; font-family: 'Arial Black Web', sans-serif; font-size: clamp(1.05rem, 2.2vw, 1.55rem); line-height: 1.05; text-transform: uppercase; text-shadow: 0 2px 10px rgba(0,0,0,0.36); }
                .luchito-found-subtitle { margin: 0.25rem 0 0; color: #f8fafc; font-weight: 800; font-size: clamp(0.78rem, 1.4vw, 0.98rem); line-height: 1.2; }
                .luchito-found-desc { margin: 0.3rem 0 0; color: #ffe7a0; font-weight: 800; font-size: clamp(0.82rem, 1.45vw, 1.02rem); line-height: 1.25; }
                /* Particulas temporales creadas por JS y removidas al terminar. */
                .luchito-effects { position: absolute; inset: 0; z-index: 3; pointer-events: none; }
                .luchito-particle { position: absolute; left: 50%; top: 45%; width: var(--size); height: var(--size); border-radius: 999px; background: var(--color); box-shadow: 0 0 10px var(--color); opacity: 0; transform: translate(-50%, -50%) scale(0.4); animation: luchitoParticleBurst var(--duration) ease-out var(--delay) forwards; }
                .luchito-particle.star { border-radius: 2px; transform: translate(-50%, -50%) scale(0.35) rotate(45deg); }
                .luchito-sparkle { position: absolute; left: 50%; top: 45%; width: var(--size); height: var(--size); opacity: 0; transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) scale(0.35) rotate(0deg); animation: luchitoSparklePop var(--duration) ease-out var(--delay) forwards; }
                .luchito-sparkle::before, .luchito-sparkle::after { content: ""; position: absolute; inset: 0; margin: auto; background: var(--color); border-radius: 999px; box-shadow: 0 0 10px var(--color); }
                .luchito-sparkle::before { width: 24%; height: 100%; }
                .luchito-sparkle::after { width: 100%; height: 24%; }
                .lg-find-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 0.4rem; width: 100%; margin-top: 0.5rem; padding: 1rem; background: #fff; border: 2px solid #801039; border-radius: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
                .lg-find-cell { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; height: 45px; background: transparent; cursor: pointer; user-select: none; transition: transform 0.1s ease; border-radius: 8px; }
                .lg-find-cell:hover:not(.locked) { transform: scale(1.15) translateY(-2px); background: #fdf5f7; }
                .lg-find-cell:active:not(.locked) { transform: scale(0.95); }
                .lg-find-cell.wrong { animation: lgShake 0.3s; opacity: 0.2; pointer-events: none; }
                .lg-find-cell.found { transform: scale(1.4); z-index: 2; position: relative; animation: lgBounceFound 0.5s ease; filter: drop-shadow(0 0 10px rgba(255,195,0,0.8)); pointer-events: none; }
                @keyframes lgShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
                @keyframes lgBounceFound { 0% { transform: scale(1); } 50% { transform: scale(1.6); } 100% { transform: scale(1.4); } }
                @keyframes lgFindPulse { 0% { transform: translate(-50%, -50%) scale(0.65); opacity: 0; } 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; } }
                @keyframes lgFindMissBubble { 0% { opacity: 0; transform: translate(-50%, calc(-100% - 4px)) scale(0.78); } 18% { opacity: 1; transform: translate(-50%, calc(-100% - 14px)) scale(1.04); } 70% { opacity: 1; transform: translate(-50%, calc(-100% - 20px)) scale(1); } 100% { opacity: 0; transform: translate(-50%, calc(-100% - 32px)) scale(0.94); } }
                @keyframes lgFindTimerPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.045); } }
                @keyframes lgFindIntroPop { 0% { opacity: 0; transform: translateY(16px) scale(0.94); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
                @keyframes luchitoFoundPop { 0% { opacity: 0; transform: translateY(18px) scale(0.75); } 62% { opacity: 1; transform: translateY(-5px) scale(1.12); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
                @keyframes luchitoGlowPulse { from { opacity: 0.52; transform: scale(0.95); } to { opacity: 0.82; transform: scale(1.06); } }
                @keyframes luchitoRipple { 0% { opacity: 0.55; transform: scale(0.55); } 100% { opacity: 0; transform: scale(1.45); } }
                @keyframes luchitoParticleBurst { 0% { opacity: 0; transform: translate(-50%, -50%) scale(0.35); } 16% { opacity: 1; } 100% { opacity: 0; transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) scale(0.9); } }
                @keyframes luchitoSparklePop { 0% { opacity: 0; transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) scale(0.25) rotate(0deg); } 35% { opacity: 1; transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) scale(1) rotate(45deg); } 100% { opacity: 0; transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) scale(0.25) rotate(90deg); } }
                @media (max-width: 640px) {
                    .lg-find-stage { display: block; }
                    .lg-find-panel { top: 8px; left: 8px; width: min(210px, 64vw); gap: 0.36rem; }
                    .lg-find-panel-actions { gap: 0.35rem; }
                    .lg-find-timer-value { font-size: clamp(2.45rem, 15vw, 4.2rem); }
                    .lg-find-stats { gap: 0.3rem; }
                    .lg-find-stat-card { padding: 0.42rem; }
                    .lg-find-stat-label { font-size: 0.58rem; }
                    .lg-find-stat-value { font-size: 0.78rem; }
                    .lg-find-msg { min-height: 0; font-size: 0.72rem; }
                    .lg-find-board { padding-left: 0; justify-content: center; }
                    .lg-find-scene img { max-width: 100%; max-height: calc(100vh - 112px); }
                    .lg-find-hint { top: 10px; right: 10px; max-width: 68%; font-size: 0.82rem; padding: 0.65rem 0.8rem; }
                    .luchito-found-card { max-width: 88vw; }
                    .luchito-found-img { max-width: 54vw; max-height: 42vh; }
                    .luchito-found-message { width: min(360px, 86vw); padding: 0.65rem 0.8rem; }
                }
            `;
            document.head.appendChild(style);
        }

        const config = gameMeta.config || {};
        const imageLevels = Array.isArray(config.levels)
            ? config.levels.filter(item => item && item.image && item.targetX !== '' && item.targetY !== '')
            : [];

        if (imageLevels.length > 0) {
            renderImageLevels(imageLevels);
            return;
        }

        renderEmojiFallback();

        function asNumber(value, fallback) {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        }

        function clamp(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function escapeHTML(value) {
            return window.LuchitoGames.escapeHTML(String(value || ''));
        }

        function renderImageLevels(levels) {
            let currentLevel = 1;
            let totalScore = 0;
            let time = 20;
            let attempts = 3;
            let timerId;
            let isGameOver = false;
            const NEXT_LEVEL_DELAY = 4200;
            const FINAL_SCORE_DELAY = 4200;
            const INTRO_DELAY = 6000;

            function startLevel(levelNumber) {
                const levelConfig = levels[levelNumber - 1];
                currentLevel = levelNumber;
                isGameOver = false;
                time = clamp(asNumber(levelConfig.time, 20), 5, 120);
                attempts = clamp(asNumber(levelConfig.attempts, 3), 1, 10);
                const shape = levelConfig.shape === 'rect' ? 'rect' : 'circle';
                const targetX = clamp(asNumber(levelConfig.targetX, 50), 0, 100);
                const targetY = clamp(asNumber(levelConfig.targetY, 50), 0, 100);
                const radius = clamp(asNumber(levelConfig.radius, 6), 2, 30);
                const targetW = clamp(asNumber(levelConfig.targetW, radius * 2), 2, 60);
                const targetH = clamp(asNumber(levelConfig.targetH, radius * 2), 2, 60);
                const hintDelay = clamp(asNumber(levelConfig.hintDelay, 5), 0, 120);
                clearInterval(timerId);

                container.innerHTML = `
                    <div class="lg-game-wrapper lg-find-game lg-animated-view">
                        <div class="lg-find-stage intro-active">
                            <aside class="lg-find-panel">
                                <div class="lg-find-panel-actions">
                                    <button class="lg-back-btn" onclick="window.LuchitoGames.renderHome()">← Volver</button>
                                    <button class="lg-find-mini-close" onclick="window.LuchitoGames.close()" aria-label="Cerrar">×</button>
                                </div>
                                <div class="lg-find-timer-card" id="lg-find-timer-card">
                                    <div>
                                        <span class="lg-find-timer-label">Tiempo</span>
                                        <span class="lg-find-timer-value" id="lg-find-time">${time}s</span>
                                    </div>
                                </div>
                                <div class="lg-find-stats">
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Nivel</span><span class="lg-find-stat-value">${currentLevel}/${levels.length}</span></div>
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Intentos</span><span class="lg-find-stat-value" id="lg-find-attempts">${attempts}</span></div>
                                </div>
                                <div id="lg-find-msg" class="lg-find-msg"></div>
                            </aside>
                            <div class="lg-find-board">
                                <div class="lg-find-scene" id="lg-find-scene" role="button" tabindex="0" aria-label="Imagen del nivel. Haz clic donde esta Luchito.">
                                    <div class="lg-find-zoom-layer" id="lg-find-zoom-layer">
                                        <img src="${escapeHTML(levelConfig.image)}" alt="Nivel ${currentLevel} de Encuentra a Luchito">
                                        <span class="lg-find-marker" id="lg-find-marker"></span>
                                        <span class="lg-find-target" id="lg-find-target"></span>
                                    </div>
                                    <div class="lg-find-hint ${hintDelay > 0 ? 'pending' : 'revealed'}" id="lg-find-hint">${escapeHTML(levelConfig.hint || 'Mira con calma y toca donde creas que esta escondido.')}</div>
                                    <div class="lg-find-zoom-controls" aria-label="Controles de zoom">
                                        <button type="button" class="lg-find-zoom-btn" id="lg-find-zoom-out" aria-label="Alejar">-</button>
                                        <span class="lg-find-zoom-level" id="lg-find-zoom-level">100%</span>
                                        <button type="button" class="lg-find-zoom-btn" id="lg-find-zoom-in" aria-label="Acercar">+</button>
                                        <button type="button" class="lg-find-zoom-btn" id="lg-find-zoom-reset" aria-label="Restablecer">↺</button>
                                    </div>
                                </div>
                            </div>
                            <div class="lg-find-intro" id="lg-find-intro">
                                <div class="lg-find-intro-card">
                                    <h3 class="lg-find-intro-title">Encuentra a Luchito</h3>
                                    <p class="lg-find-intro-text">${escapeHTML(levelConfig.description || 'Mira bien la imagen y encuentra a Luchito antes de que se acabe el tiempo.')}</p>
                                </div>
                            </div>
                        </div>
                        <div class="luchito-found-overlay" id="lg-find-found-pop" aria-hidden="true"></div>
                    </div>
                `;

                const scene = container.querySelector('#lg-find-scene');
                const stage = container.querySelector('.lg-find-stage');
                const zoomLayer = container.querySelector('#lg-find-zoom-layer');
                const sceneImg = scene.querySelector('img');
                const timeEl = container.querySelector('#lg-find-time');
                const timerCardEl = container.querySelector('#lg-find-timer-card');
                const attemptsEl = container.querySelector('#lg-find-attempts');
                const markerEl = container.querySelector('#lg-find-marker');
                const targetEl = container.querySelector('#lg-find-target');
                const msgEl = container.querySelector('#lg-find-msg');
                const hintEl = container.querySelector('#lg-find-hint');
                const foundPopEl = container.querySelector('#lg-find-found-pop');
                const introEl = container.querySelector('#lg-find-intro');
                const zoomLevelEl = container.querySelector('#lg-find-zoom-level');
                const zoomInBtn = container.querySelector('#lg-find-zoom-in');
                const zoomOutBtn = container.querySelector('#lg-find-zoom-out');
                const zoomResetBtn = container.querySelector('#lg-find-zoom-reset');
                const zoomState = { scale: 1, x: 0, y: 0, dragging: false, moved: false, startX: 0, startY: 0, originX: 0, originY: 0 };
                const MIN_ZOOM = 1;
                const MAX_ZOOM = 4;

                function clampPan() {
                    const viewportW = scene.clientWidth;
                    const viewportH = scene.clientHeight;
                    const contentW = zoomLayer.offsetWidth * zoomState.scale;
                    const contentH = zoomLayer.offsetHeight * zoomState.scale;
                    const minX = Math.min(0, viewportW - contentW);
                    const minY = Math.min(0, viewportH - contentH);
                    zoomState.x = clamp(zoomState.x, minX, 0);
                    zoomState.y = clamp(zoomState.y, minY, 0);
                    if (contentW <= viewportW) zoomState.x = (viewportW - contentW) / 2;
                    if (contentH <= viewportH) zoomState.y = (viewportH - contentH) / 2;
                }

                function applyZoom() {
                    clampPan();
                    zoomLayer.style.transform = `translate(${zoomState.x}px, ${zoomState.y}px) scale(${zoomState.scale})`;
                    scene.classList.toggle('zoomed', zoomState.scale > 1.01);
                    if (zoomLevelEl) zoomLevelEl.textContent = `${Math.round(zoomState.scale * 100)}%`;
                }

                function setZoom(nextScale, clientX, clientY) {
                    const oldScale = zoomState.scale;
                    const newScale = clamp(nextScale, MIN_ZOOM, MAX_ZOOM);
                    if (Math.abs(newScale - oldScale) < 0.001) return;

                    const rect = scene.getBoundingClientRect();
                    const focusX = Number.isFinite(clientX) ? clientX - rect.left : rect.width / 2;
                    const focusY = Number.isFinite(clientY) ? clientY - rect.top : rect.height / 2;
                    const imageX = (focusX - zoomState.x) / oldScale;
                    const imageY = (focusY - zoomState.y) / oldScale;

                    zoomState.scale = newScale;
                    zoomState.x = focusX - imageX * newScale;
                    zoomState.y = focusY - imageY * newScale;
                    applyZoom();
                }

                function resetZoom() {
                    zoomState.scale = 1;
                    zoomState.x = 0;
                    zoomState.y = 0;
                    applyZoom();
                }

                function updateImageLayout() {
                    const isWide = sceneImg.naturalWidth > sceneImg.naturalHeight;
                    stage?.classList.toggle('wide-image', isWide);
                    stage?.classList.toggle('tall-image', !isWide);
                }

                if (sceneImg.complete) {
                    updateImageLayout();
                    resetZoom();
                }
                sceneImg.addEventListener('load', updateImageLayout, { once: true });
                sceneImg.addEventListener('load', resetZoom, { once: true });

                scene.addEventListener('wheel', event => {
                    if (isGameOver) return;
                    event.preventDefault();
                    const factor = event.deltaY < 0 ? 1.14 : 0.88;
                    setZoom(zoomState.scale * factor, event.clientX, event.clientY);
                }, { passive: false });

                zoomInBtn?.addEventListener('click', event => {
                    event.stopPropagation();
                    setZoom(zoomState.scale * 1.2);
                });
                zoomOutBtn?.addEventListener('click', event => {
                    event.stopPropagation();
                    setZoom(zoomState.scale / 1.2);
                });
                zoomResetBtn?.addEventListener('click', event => {
                    event.stopPropagation();
                    resetZoom();
                });

                scene.addEventListener('pointerdown', event => {
                    if (isGameOver || event.button !== 0 || event.target.closest('.lg-find-zoom-controls')) return;
                    zoomState.dragging = true;
                    zoomState.moved = false;
                    zoomState.startX = event.clientX;
                    zoomState.startY = event.clientY;
                    zoomState.originX = zoomState.x;
                    zoomState.originY = zoomState.y;
                    scene.classList.add('panning');
                    scene.setPointerCapture?.(event.pointerId);
                });

                scene.addEventListener('pointermove', event => {
                    if (!zoomState.dragging) return;
                    const dx = event.clientX - zoomState.startX;
                    const dy = event.clientY - zoomState.startY;
                    if (Math.abs(dx) + Math.abs(dy) > 4) zoomState.moved = true;
                    if (!zoomState.moved) return;
                    event.preventDefault();
                    zoomState.x = zoomState.originX + dx;
                    zoomState.y = zoomState.originY + dy;
                    applyZoom();
                });

                const endPan = event => {
                    if (!zoomState.dragging) return;
                    zoomState.dragging = false;
                    scene.classList.remove('panning');
                    scene.releasePointerCapture?.(event.pointerId);
                    applyZoom();
                };
                scene.addEventListener('pointerup', endPan);
                scene.addEventListener('pointercancel', endPan);

                function revealHint() {
                    if (!hintEl || hintEl.classList.contains('revealed')) return;
                    hintEl.classList.remove('pending');
                    hintEl.classList.add('revealed');
                }

                timerCardEl?.classList.toggle('urgent', time <= 10);

                if (shape === 'rect') {
                    targetEl.style.left = `${targetX}%`;
                    targetEl.style.top = `${targetY}%`;
                    targetEl.style.width = `${targetW}%`;
                    targetEl.style.height = `${targetH}%`;
                    targetEl.style.transform = 'none';
                    targetEl.style.borderRadius = '8px';
                } else {
                    const targetSize = radius * 2;
                    targetEl.style.left = `${targetX}%`;
                    targetEl.style.top = `${targetY}%`;
                    targetEl.style.width = `${targetSize}%`;
                    targetEl.style.height = `${targetSize}%`;
                    targetEl.style.transform = 'translate(-50%, -50%)';
                    targetEl.style.borderRadius = '50%';
                }

                function isHit(x, y) {
                    if (shape === 'rect') {
                        return x >= targetX && x <= targetX + targetW && y >= targetY && y <= targetY + targetH;
                    }
                    return Math.hypot(x - targetX, y - targetY) <= radius;
                }

                function stopGame() {
                    isGameOver = true;
                    clearInterval(timerId);
                    scene.classList.add('locked');
                }

                function revealTarget() {
                    targetEl.classList.add('show');
                }

                function showMissBubble(x, y) {
                    zoomLayer.querySelectorAll('.lg-find-miss-bubble').forEach(item => item.remove());
                    const bubble = document.createElement('span');
                    bubble.className = 'lg-find-miss-bubble';
                    bubble.textContent = 'CASI PERO NO';
                    bubble.style.setProperty('--x', `${x}%`);
                    bubble.style.setProperty('--y', `${y}%`);
                    zoomLayer.appendChild(bubble);
                    bubble.addEventListener('animationend', () => bubble.remove(), { once: true });
                }

                function playLuchitoFoundAnimation(overlay, foundImage, foundDescription) {
                    const particleColors = ['#ffc300', '#ffe08a', '#fff7d6', '#f59e0b'];
                    const particleCount = 30;
                    const sparkleCount = 8;

                    const particles = Array.from({ length: particleCount }, (_, index) => {
                        const angle = (Math.PI * 2 * index) / particleCount + (Math.random() * 0.35);
                        const distance = 92 + Math.random() * 132;
                        const size = 4 + Math.random() * 7;
                        const color = particleColors[index % particleColors.length];
                        return `<span class="luchito-particle ${index % 5 === 0 ? 'star' : ''}" style="--x:${Math.cos(angle) * distance}px; --y:${Math.sin(angle) * distance}px; --size:${size}px; --color:${color}; --duration:${820 + Math.random() * 360}ms; --delay:${Math.random() * 110}ms;"></span>`;
                    }).join('');

                    const sparkles = Array.from({ length: sparkleCount }, (_, index) => {
                        const angle = (Math.PI * 2 * index) / sparkleCount + 0.2;
                        const distance = 118 + Math.random() * 82;
                        const size = 14 + Math.random() * 10;
                        const color = particleColors[(index + 2) % particleColors.length];
                        return `<span class="luchito-sparkle" style="--x:${Math.cos(angle) * distance}px; --y:${Math.sin(angle) * distance}px; --size:${size}px; --color:${color}; --duration:${680 + Math.random() * 260}ms; --delay:${index * 45}ms;"></span>`;
                    }).join('');

                    overlay.innerHTML = `
                        <div class="luchito-found-card">
                            <div class="luchito-effects" aria-hidden="true">${particles}${sparkles}</div>
                            <div class="luchito-pop">
                                <span class="luchito-found-glow" aria-hidden="true"></span>
                                <span class="luchito-found-ripple" aria-hidden="true"></span>
                                ${foundImage ? `<img class="luchito-found-img" src="${escapeHTML(foundImage)}" alt="Luchito encontrado">` : ''}
                            </div>
                            <div class="luchito-found-message">
                                <h3 class="luchito-found-title">&iexcl;Encontraste a Luchito!</h3>
                                <p class="luchito-found-subtitle">Buen ojo, vecino &#128064;</p>
                                ${foundDescription ? `<p class="luchito-found-desc">${escapeHTML(foundDescription)}</p>` : ''}
                            </div>
                        </div>
                    `;

                    overlay.setAttribute('aria-hidden', 'false');
                    overlay.classList.add('show');

                    // Limpia efectos temporales para no acumular particulas entre niveles.
                    setTimeout(() => overlay.querySelector('.luchito-effects')?.remove(), 1400);
                }

                function showFoundCelebration(callback, delay = 1800) {
                    const foundImage = String(levelConfig.foundImage || '').trim();
                    const foundDescription = String(levelConfig.foundDescription || '').trim();

                    playLuchitoFoundAnimation(foundPopEl, foundImage, foundDescription);
                    setTimeout(callback, delay);
                }

                function endGame(win) {
                    stopGame();
                    revealTarget();

                    if (win) {
                        msgEl.textContent = 'Muy bien, encontraste a Luchito.';
                        msgEl.style.color = '#155724';
                        totalScore += (time * 10) + (attempts * 60) + (currentLevel * 100);

                        showFoundCelebration(() => {
                            if (currentLevel < levels.length) {
                                msgEl.textContent = `Nivel ${currentLevel} superado. Preparando el siguiente...`;
                                startLevel(currentLevel + 1);
                            } else {
                                window.LuchitoGames.showRankingPrompt(totalScore, 'find-luchito', container);
                            }
                        }, currentLevel < levels.length ? NEXT_LEVEL_DELAY : FINAL_SCORE_DELAY);
                    } else {
                        msgEl.textContent = 'Se acabaron los intentos o el tiempo. Mira donde estaba.';
                        msgEl.style.color = '#721c24';

                        setTimeout(() => {
                            totalScore = 0;
                            startLevel(1);
                        }, 2600);
                    }
                }

                function handleGuess(clientX, clientY) {
                    if (isGameOver) return;
                    const rect = sceneImg.getBoundingClientRect();
                    if (!rect.width || !rect.height) return;
                    if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom) return;
                    const x = ((clientX - rect.left) / rect.width) * 100;
                    const y = ((clientY - rect.top) / rect.height) * 100;
                    const hit = isHit(x, y);

                    markerEl.style.left = `${x}%`;
                    markerEl.style.top = `${y}%`;
                    markerEl.className = `lg-find-marker show ${hit ? 'hit' : 'miss'}`;

                    if (hit) {
                        endGame(true);
                        return;
                    }

                    attempts--;
                    attemptsEl.textContent = attempts;
                    showMissBubble(x, y);
                    msgEl.textContent = attempts > 0 ? 'Cerca, pero no era ahi. Intenta otra vez.' : '';
                    msgEl.style.color = '#801039';
                    if (attempts <= 0) endGame(false);
                }

                scene.addEventListener('click', event => {
                    if (event.target.closest('.lg-find-zoom-controls')) return;
                    if (zoomState.moved) {
                        zoomState.moved = false;
                        return;
                    }
                    handleGuess(event.clientX, event.clientY);
                });
                scene.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        const rect = sceneImg.getBoundingClientRect();
                        handleGuess(rect.left + rect.width / 2, rect.top + rect.height / 2);
                    }
                });

                function startTimer() {
                    if (hintDelay === 0) revealHint();
                    timerId = setInterval(() => {
                        if (!document.body.contains(timeEl)) {
                            clearInterval(timerId);
                            return;
                        }
                        if (isGameOver) return;
                        time--;
                        timeEl.textContent = time + 's';
                        timerCardEl?.classList.toggle('urgent', time <= 10);
                        if ((asNumber(levelConfig.time, 20) - time) >= hintDelay) revealHint();
                        if (time <= 0) endGame(false);
                    }, 1000);
                }

                setTimeout(() => {
                    introEl?.classList.add('hide');
                    stage?.classList.remove('intro-active');
                    startTimer();
                }, INTRO_DELAY);
            }

            startLevel(1);
        }

        function renderEmojiFallback() {
            const emojis = ['👨', '👩', '👴', '👵', '👮', '🕵️', '👨‍🌾', '👩‍🍳', '👨‍🔧', '👷', '👨‍🏫', '👩‍🎤', '👲', '💂', '👨‍🚒', '👩‍⚕️', '👨‍💻', '👩‍🚀'];
            const levelConfigs = [
                { grid: 30, time: 30 },
                { grid: 45, time: 25 },
                { grid: 60, time: 20 },
                { grid: 80, time: 18 },
                { grid: 100, time: 15 }
            ];

            let currentLevel = 1;
            let totalScore = 0;
            let time = 15;
            let attempts = 3;
            let gridSize = 40;
            let timerId;
            let isGameOver = false;
            let luchitoIndex = -1;

            function startLevel(lvl) {
                currentLevel = lvl;
                isGameOver = false;
                time = levelConfigs[lvl - 1].time;
                gridSize = levelConfigs[lvl - 1].grid;
                attempts = 3;
                clearInterval(timerId);

                let board = Array(gridSize).fill('').map(() => emojis[Math.floor(Math.random() * emojis.length)]);
                luchitoIndex = Math.floor(Math.random() * gridSize);
                board[luchitoIndex] = '🐻';

                let cellSize = '45px', fontSize = '1.8rem', minmax = '45px';
                if (gridSize > 70) {
                    cellSize = '30px'; fontSize = '1.1rem'; minmax = '30px';
                } else if (gridSize > 45) {
                    cellSize = '35px'; fontSize = '1.4rem'; minmax = '35px';
                }

                container.innerHTML = `
                    <div class="lg-game-wrapper lg-find-game lg-animated-view">
                        <div style="width: 100%; text-align: center; color: #801039; font-weight: bold;">Nivel ${currentLevel}/5 | Puntos: ${totalScore}</div>
                        <div class="lg-find-header">
                            <div class="lg-find-stat">Tiempo <span id="lg-find-time">${time}s</span></div>
                            <div class="lg-find-title">Donde esta Luchito?</div>
                            <div class="lg-find-stat">Intentos <span id="lg-find-attempts">${attempts}</span></div>
                        </div>
                        <div class="lg-find-grid" id="lg-find-grid" style="grid-template-columns: repeat(auto-fill, minmax(${minmax}, 1fr));">
                            ${board.map((emoji, i) => `<div class="lg-find-cell" data-index="${i}" style="height: ${cellSize}; font-size: ${fontSize};">${emoji}</div>`).join('')}
                        </div>
                        <div id="lg-find-msg" class="lg-find-msg"></div>
                    </div>
                `;

                const timeEl = container.querySelector('#lg-find-time');
                const attemptsEl = container.querySelector('#lg-find-attempts');
                const msgEl = container.querySelector('#lg-find-msg');
                const cells = container.querySelectorAll('.lg-find-cell');

                function stopGame() {
                    isGameOver = true;
                    clearInterval(timerId);
                    cells.forEach(c => c.classList.add('locked'));
                }

                function endGame(win) {
                    stopGame();

                    if (win) {
                        msgEl.textContent = 'Encontraste a Luchito.';
                        msgEl.style.color = '#155724';
                        totalScore += (time * 10) + (attempts * 50);

                        setTimeout(() => {
                            if (currentLevel < 5) {
                                msgEl.textContent = `Nivel ${currentLevel} superado. Preparando siguiente...`;
                                setTimeout(() => startLevel(currentLevel + 1), 1200);
                            } else {
                                window.LuchitoGames.showRankingPrompt(totalScore, 'find-luchito', container);
                            }
                        }, 800);
                    } else {
                        msgEl.textContent = 'Se acabo el tiempo o los intentos.';
                        msgEl.style.color = '#721c24';
                        const luchitoCell = container.querySelector(`.lg-find-cell[data-index="${luchitoIndex}"]`);
                        if (luchitoCell) luchitoCell.classList.add('found');

                        setTimeout(() => {
                            totalScore = 0;
                            startLevel(1);
                        }, 2500);
                    }
                }

                cells.forEach(cell => {
                    cell.addEventListener('click', () => {
                        if (isGameOver) return;
                        const idx = parseInt(cell.dataset.index, 10);

                        if (idx === luchitoIndex) {
                            cell.classList.add('found');
                            endGame(true);
                        } else {
                            cell.classList.add('wrong');
                            attempts--;
                            attemptsEl.textContent = attempts;
                            if (attempts <= 0) endGame(false);
                        }
                    });
                });

                timerId = setInterval(() => {
                    if (!document.body.contains(timeEl)) {
                        clearInterval(timerId);
                        return;
                    }
                    if (isGameOver) return;
                    time--;
                    timeEl.textContent = time + 's';
                    if (time <= 0) endGame(false);
                }, 1000);
            }

            startLevel(currentLevel);
        }
    }
});
