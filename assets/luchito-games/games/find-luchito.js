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
                .lg-find-stage { display: grid; grid-template-columns: 176px minmax(0, 1fr); gap: 12px; width: 100%; height: 100%; min-height: 0; align-items: stretch; }
                .lg-find-panel { display: flex; flex-direction: column; gap: 0.5rem; min-height: 0; color: #801039; }
                .lg-find-panel-title { font-family:'Arial Black Web', sans-serif; text-transform: uppercase; font-size: 0.9rem; line-height: 1.05; margin: 0; }
                .lg-find-panel-actions { display: flex; gap: 0.5rem; align-items: center; }
                .lg-find-panel-actions .lg-back-btn { margin-right: 0; padding: 6px 10px; font-size: 11px; white-space: nowrap; }
                .lg-find-mini-close { background: transparent; border: 1px solid rgba(128,16,57,0.25); color: #801039; border-radius: 999px; width: 30px; height: 30px; cursor: pointer; font-weight: 900; font-size: 16px; line-height: 1; }
                .lg-find-stats { display: grid; gap: 0.42rem; }
                .lg-find-stat-card { background: #fff; border: 2px solid #ffc300; border-radius: 10px; padding: 0.45rem 0.55rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                .lg-find-stat-label { display: block; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: #8a6070; font-weight: 800; margin-bottom: 0.1rem; }
                .lg-find-stat-value { font-size: 0.92rem; font-weight: 900; }
                .lg-find-board { display: flex; align-items: center; justify-content: center; min-width: 0; min-height: 0; }
                .lg-find-scene { position: relative; width: fit-content; max-width: 100%; border: 0; outline: 3px solid #801039; outline-offset: 0; border-radius: 18px; overflow: hidden; background: #ffffff; box-shadow: 0 10px 24px rgba(0,0,0,0.12); cursor: crosshair; touch-action: manipulation; flex: 0 1 auto; }
                .lg-find-scene.locked { cursor: default; }
                .lg-find-scene img { display: block; width: auto; height: auto; max-width: calc(100vw - 230px); max-height: calc(100vh - 36px); user-select: none; -webkit-user-drag: none; }
                .lg-find-marker { position: absolute; width: 44px; height: 44px; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; }
                .lg-find-marker.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-marker.hit { border: 4px solid #16a34a; box-shadow: 0 0 0 999px rgba(22,163,74,0.08), 0 0 18px rgba(22,163,74,0.75); }
                .lg-find-marker.miss { border: 4px solid #ef4444; box-shadow: 0 0 0 999px rgba(239,68,68,0.06), 0 0 16px rgba(239,68,68,0.65); }
                .lg-find-target { position: absolute; border: 3px dashed #ffc300; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; background: rgba(255,195,0,0.1); box-shadow: 0 0 18px rgba(128,16,57,0.35); }
                .lg-find-target.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-hint { position: absolute; top: 16px; right: 16px; z-index: 4; max-width: min(360px, 48%); color: #801039; text-align: left; font-size: 0.95rem; font-weight: 800; line-height: 1.25; background: #ffffff; border: 3px solid #ffc300; border-radius: 28px; padding: 0.85rem 1.1rem; box-shadow: 0 12px 28px rgba(0,0,0,0.22); pointer-events: none; }
                .lg-find-hint::before { content: ""; position: absolute; right: 36px; bottom: -13px; width: 24px; height: 24px; background: #ffffff; border-right: 3px solid #ffc300; border-bottom: 3px solid #ffc300; border-bottom-right-radius: 20px; transform: rotate(45deg); }
                .lg-find-hint::after { content: ""; position: absolute; right: 18px; bottom: -28px; width: 13px; height: 13px; background: #ffffff; border: 3px solid #ffc300; border-radius: 50%; }
                .lg-find-hint.pending { opacity: 0; transform: translateY(-8px) scale(0.96); }
                .lg-find-hint.revealed { opacity: 1; transform: translateY(0) scale(1); transition: opacity 0.25s ease, transform 0.25s ease; }
                .lg-find-description { color: #5f3345; font-size: 0.74rem; line-height: 1.22; background: rgba(128,16,57,0.06); border: 1px solid rgba(128,16,57,0.12); border-radius: 10px; padding: 0.5rem 0.55rem; box-sizing: border-box; max-height: 92px; overflow: auto; }
                .lg-find-msg { min-height: 18px; font-weight: 800; color: #801039; font-size: 0.78rem; line-height: 1.22; }
                .lg-find-found-pop { position: absolute; inset: 0; z-index: 30; display: flex; align-items: center; justify-content: center; text-align: center; pointer-events: none; opacity: 0; transform: scale(0.88); transition: opacity 0.25s ease, transform 0.25s ease; }
                .lg-find-found-pop.show { opacity: 1; transform: scale(1); }
                .lg-find-found-content { position: relative; display: flex; flex-direction: column; align-items: center; gap: 0.6rem; max-width: min(560px, 72vw); animation: lgTreasureRise 0.75s cubic-bezier(0.18, 0.9, 0.28, 1.25) both; }
                .lg-find-found-content::before { content: ""; position: absolute; inset: 18% 8% 22%; z-index: -1; background: radial-gradient(circle, rgba(255,195,0,0.75), rgba(255,195,0,0.18) 38%, transparent 68%); filter: blur(10px); animation: lgTreasureGlow 1.1s ease-in-out infinite alternate; }
                .lg-find-found-title { margin: 0; color: #801039; font-family: 'Arial Black Web', sans-serif; font-size: clamp(1.35rem, 3.2vw, 2.35rem); text-transform: uppercase; text-shadow: 0 3px 0 #ffc300, 0 10px 28px rgba(0,0,0,0.2); }
                .lg-find-found-img-wrap { position: relative; display: grid; place-items: center; }
                .lg-find-found-img-wrap::before, .lg-find-found-img-wrap::after { content: ""; position: absolute; inset: -18%; border-radius: 50%; background: conic-gradient(from 0deg, transparent, rgba(255,195,0,0.95), transparent, rgba(255,255,255,0.95), transparent); opacity: 0.8; animation: lgTreasureSpin 1.7s linear infinite; }
                .lg-find-found-img-wrap::after { inset: -28%; animation-duration: 2.4s; animation-direction: reverse; opacity: 0.45; }
                .lg-find-found-img { position: relative; z-index: 1; display: block; max-width: min(300px, 34vw); max-height: min(360px, 58vh); object-fit: contain; filter: drop-shadow(0 0 10px rgba(255,195,0,0.95)) drop-shadow(0 14px 18px rgba(128,16,57,0.35)); animation: lgTreasureFloat 1.15s ease-in-out infinite alternate; }
                .lg-find-found-desc { margin: 0; color: #801039; font-weight: 900; font-size: clamp(0.95rem, 1.6vw, 1.25rem); line-height: 1.25; max-width: 520px; text-shadow: 0 2px 0 #ffffff, 0 8px 18px rgba(255,195,0,0.35); }
                .lg-find-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 0.4rem; width: 100%; margin-top: 0.5rem; padding: 1rem; background: #fff; border: 2px solid #801039; border-radius: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
                .lg-find-cell { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; height: 45px; background: transparent; cursor: pointer; user-select: none; transition: transform 0.1s ease; border-radius: 8px; }
                .lg-find-cell:hover:not(.locked) { transform: scale(1.15) translateY(-2px); background: #fdf5f7; }
                .lg-find-cell:active:not(.locked) { transform: scale(0.95); }
                .lg-find-cell.wrong { animation: lgShake 0.3s; opacity: 0.2; pointer-events: none; }
                .lg-find-cell.found { transform: scale(1.4); z-index: 2; position: relative; animation: lgBounceFound 0.5s ease; filter: drop-shadow(0 0 10px rgba(255,195,0,0.8)); pointer-events: none; }
                @keyframes lgShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
                @keyframes lgBounceFound { 0% { transform: scale(1); } 50% { transform: scale(1.6); } 100% { transform: scale(1.4); } }
                @keyframes lgFindPulse { 0% { transform: translate(-50%, -50%) scale(0.65); opacity: 0; } 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; } }
                @keyframes lgTreasureRise { 0% { transform: translateY(28px) scale(0.72); opacity: 0; } 65% { transform: translateY(-8px) scale(1.06); opacity: 1; } 100% { transform: translateY(0) scale(1); opacity: 1; } }
                @keyframes lgTreasureGlow { from { opacity: 0.6; transform: scale(0.92); } to { opacity: 1; transform: scale(1.12); } }
                @keyframes lgTreasureSpin { to { transform: rotate(360deg); } }
                @keyframes lgTreasureFloat { from { transform: translateY(0) scale(1); } to { transform: translateY(-8px) scale(1.04); } }
                @media (max-width: 640px) {
                    .lg-find-stage { grid-template-columns: 1fr; grid-template-rows: auto minmax(0, 1fr); gap: 8px; }
                    .lg-find-panel { gap: 0.45rem; }
                    .lg-find-panel-title { display: none; }
                    .lg-find-stats { grid-template-columns: repeat(4, 1fr); gap: 0.35rem; }
                    .lg-find-stat-card { padding: 0.45rem; }
                    .lg-find-stat-label { font-size: 0.58rem; }
                    .lg-find-stat-value { font-size: 0.78rem; }
                    .lg-find-description { display: none; }
                    .lg-find-msg { min-height: 0; font-size: 0.72rem; }
                    .lg-find-scene img { max-width: 100%; max-height: calc(100vh - 112px); }
                    .lg-find-hint { top: 10px; right: 10px; max-width: 68%; font-size: 0.82rem; padding: 0.65rem 0.8rem; }
                    .lg-find-found-content { max-width: 86vw; }
                    .lg-find-found-img { max-width: 52vw; max-height: 42vh; }
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
                        <div class="lg-find-stage">
                            <aside class="lg-find-panel">
                                <div class="lg-find-panel-actions">
                                    <button class="lg-back-btn" onclick="window.LuchitoGames.renderHome()">← Volver</button>
                                    <button class="lg-find-mini-close" onclick="window.LuchitoGames.close()" aria-label="Cerrar">×</button>
                                </div>
                                <h3 class="lg-find-panel-title">Encuentra a Luchito</h3>
                                <div class="lg-find-stats">
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Nivel</span><span class="lg-find-stat-value">${currentLevel}/${levels.length}</span></div>
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Tiempo</span><span class="lg-find-stat-value"><span id="lg-find-time">${time}s</span></span></div>
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Intentos</span><span class="lg-find-stat-value" id="lg-find-attempts">${attempts}</span></div>
                                    <div class="lg-find-stat-card"><span class="lg-find-stat-label">Puntos</span><span class="lg-find-stat-value">${totalScore}</span></div>
                                </div>
                                ${levelConfig.description ? `<div class="lg-find-description">${escapeHTML(levelConfig.description)}</div>` : ''}
                                <div id="lg-find-msg" class="lg-find-msg"></div>
                            </aside>
                            <div class="lg-find-board">
                                <div class="lg-find-scene" id="lg-find-scene" role="button" tabindex="0" aria-label="Imagen del nivel. Haz clic donde esta Luchito.">
                                    <img src="${escapeHTML(levelConfig.image)}" alt="Nivel ${currentLevel} de Encuentra a Luchito">
                                    <div class="lg-find-hint ${hintDelay > 0 ? 'pending' : 'revealed'}" id="lg-find-hint">${escapeHTML(levelConfig.hint || 'Mira con calma y toca donde creas que esta escondido.')}</div>
                                    <span class="lg-find-marker" id="lg-find-marker"></span>
                                    <span class="lg-find-target" id="lg-find-target"></span>
                                </div>
                            </div>
                        </div>
                        <div class="lg-find-found-pop" id="lg-find-found-pop" aria-hidden="true"></div>
                    </div>
                `;

                const scene = container.querySelector('#lg-find-scene');
                const sceneImg = scene.querySelector('img');
                const timeEl = container.querySelector('#lg-find-time');
                const attemptsEl = container.querySelector('#lg-find-attempts');
                const markerEl = container.querySelector('#lg-find-marker');
                const targetEl = container.querySelector('#lg-find-target');
                const msgEl = container.querySelector('#lg-find-msg');
                const hintEl = container.querySelector('#lg-find-hint');
                const foundPopEl = container.querySelector('#lg-find-found-pop');

                function revealHint() {
                    if (!hintEl || hintEl.classList.contains('revealed')) return;
                    hintEl.classList.remove('pending');
                    hintEl.classList.add('revealed');
                }

                if (hintDelay === 0) revealHint();

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

                function showFoundCelebration(callback) {
                    const foundImage = String(levelConfig.foundImage || '').trim();
                    const foundDescription = String(levelConfig.foundDescription || '').trim();
                    if (!foundImage && !foundDescription) {
                        setTimeout(callback, 900);
                        return;
                    }

                    foundPopEl.innerHTML = `
                        <div class="lg-find-found-content">
                            <h3 class="lg-find-found-title">Encontraste a Luchito</h3>
                            ${foundImage ? `<div class="lg-find-found-img-wrap"><img class="lg-find-found-img" src="${escapeHTML(foundImage)}" alt="Luchito encontrado"></div>` : ''}
                            ${foundDescription ? `<p class="lg-find-found-desc">${escapeHTML(foundDescription)}</p>` : ''}
                        </div>
                    `;
                    foundPopEl.setAttribute('aria-hidden', 'false');
                    foundPopEl.classList.add('show');
                    setTimeout(callback, 2600);
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
                                setTimeout(() => startLevel(currentLevel + 1), 1200);
                            } else {
                                window.LuchitoGames.showRankingPrompt(totalScore, 'find-luchito', container);
                            }
                        });
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
                    msgEl.textContent = attempts > 0 ? 'Cerca, pero no era ahi. Intenta otra vez.' : '';
                    msgEl.style.color = '#801039';
                    if (attempts <= 0) endGame(false);
                }

                scene.addEventListener('click', event => handleGuess(event.clientX, event.clientY));
                scene.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        const rect = sceneImg.getBoundingClientRect();
                        handleGuess(rect.left + rect.width / 2, rect.top + rect.height / 2);
                    }
                });

                timerId = setInterval(() => {
                    if (!document.body.contains(timeEl)) {
                        clearInterval(timerId);
                        return;
                    }
                    if (isGameOver) return;
                    time--;
                    timeEl.textContent = time + 's';
                    if ((asNumber(levelConfig.time, 20) - time) >= hintDelay) revealHint();
                    if (time <= 0) endGame(false);
                }, 1000);
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
