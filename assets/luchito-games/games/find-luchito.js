window.LuchitoGames.registerGame('find-luchito', {
    title: 'Encuentra a Luchito',
    render: function(container, level = 'medium', gameMeta = {}) {
        const styleId = 'luchito-find-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-find-game { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; font-family: system-ui, sans-serif; width: 100%; }
                .lg-find-header { display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 1rem; font-weight: bold; color: #801039; background: #fdf5f7; padding: 0.6rem 1.2rem; border-radius: 50px; border: 2px solid #ffc300; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box; gap: 0.8rem; }
                .lg-find-stat { display: flex; align-items: center; gap: 0.4rem; font-size: 1.05rem; white-space: nowrap; }
                .lg-find-title { font-family:'Arial Black Web', sans-serif; text-transform:uppercase; text-align: center; line-height: 1.15; }
                .lg-find-scene { position: relative; width: 100%; max-width: 680px; border: 3px solid #801039; border-radius: 18px; overflow: hidden; background: #ffffff; box-shadow: 0 10px 24px rgba(0,0,0,0.12); cursor: crosshair; touch-action: manipulation; }
                .lg-find-scene.locked { cursor: default; }
                .lg-find-scene img { display: block; width: 100%; height: auto; user-select: none; -webkit-user-drag: none; }
                .lg-find-marker { position: absolute; width: 44px; height: 44px; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; }
                .lg-find-marker.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-marker.hit { border: 4px solid #16a34a; box-shadow: 0 0 0 999px rgba(22,163,74,0.08), 0 0 18px rgba(22,163,74,0.75); }
                .lg-find-marker.miss { border: 4px solid #ef4444; box-shadow: 0 0 0 999px rgba(239,68,68,0.06), 0 0 16px rgba(239,68,68,0.65); }
                .lg-find-target { position: absolute; border: 3px dashed #ffc300; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; background: rgba(255,195,0,0.1); box-shadow: 0 0 18px rgba(128,16,57,0.35); }
                .lg-find-target.show { opacity: 1; animation: lgFindPulse 0.7s ease; }
                .lg-find-hint { width: 100%; max-width: 680px; color: #444; text-align: center; font-size: 0.95rem; line-height: 1.35; min-height: 22px; }
                .lg-find-msg { min-height: 24px; font-weight: 800; color: #801039; text-align: center; }
                .lg-find-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 0.4rem; width: 100%; margin-top: 0.5rem; padding: 1rem; background: #fff; border: 2px solid #801039; border-radius: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
                .lg-find-cell { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; height: 45px; background: transparent; cursor: pointer; user-select: none; transition: transform 0.1s ease; border-radius: 8px; }
                .lg-find-cell:hover:not(.locked) { transform: scale(1.15) translateY(-2px); background: #fdf5f7; }
                .lg-find-cell:active:not(.locked) { transform: scale(0.95); }
                .lg-find-cell.wrong { animation: lgShake 0.3s; opacity: 0.2; pointer-events: none; }
                .lg-find-cell.found { transform: scale(1.4); z-index: 2; position: relative; animation: lgBounceFound 0.5s ease; filter: drop-shadow(0 0 10px rgba(255,195,0,0.8)); pointer-events: none; }
                @keyframes lgShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
                @keyframes lgBounceFound { 0% { transform: scale(1); } 50% { transform: scale(1.6); } 100% { transform: scale(1.4); } }
                @keyframes lgFindPulse { 0% { transform: translate(-50%, -50%) scale(0.65); opacity: 0; } 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; } }
                @media (max-width: 640px) {
                    .lg-find-header { border-radius: 16px; padding: 0.65rem 0.8rem; }
                    .lg-find-title { font-size: 0.78rem; }
                    .lg-find-stat { font-size: 0.9rem; }
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
                const targetX = clamp(asNumber(levelConfig.targetX, 50), 0, 100);
                const targetY = clamp(asNumber(levelConfig.targetY, 50), 0, 100);
                const radius = clamp(asNumber(levelConfig.radius, 6), 2, 30);
                clearInterval(timerId);

                container.innerHTML = `
                    <div class="lg-game-wrapper lg-find-game lg-animated-view">
                        <div style="width: 100%; text-align: center; color: #801039; font-weight: bold;">Nivel ${currentLevel}/${levels.length} | Puntos: ${totalScore}</div>
                        <div class="lg-find-header">
                            <div class="lg-find-stat">Tiempo <span id="lg-find-time">${time}s</span></div>
                            <div class="lg-find-title">${escapeHTML(levelConfig.title || 'Donde esta Luchito?')}</div>
                            <div class="lg-find-stat">Intentos <span id="lg-find-attempts">${attempts}</span></div>
                        </div>
                        <div class="lg-find-hint">${escapeHTML(levelConfig.hint || 'Mira con calma y toca donde creas que esta escondido.')}</div>
                        <div class="lg-find-scene" id="lg-find-scene" role="button" tabindex="0" aria-label="Imagen del nivel. Haz clic donde esta Luchito.">
                            <img src="${escapeHTML(levelConfig.image)}" alt="${escapeHTML(levelConfig.alt || levelConfig.title || 'Nivel de Encuentra a Luchito')}">
                            <span class="lg-find-marker" id="lg-find-marker"></span>
                            <span class="lg-find-target" id="lg-find-target"></span>
                        </div>
                        <div id="lg-find-msg" class="lg-find-msg"></div>
                    </div>
                `;

                const scene = container.querySelector('#lg-find-scene');
                const timeEl = container.querySelector('#lg-find-time');
                const attemptsEl = container.querySelector('#lg-find-attempts');
                const markerEl = container.querySelector('#lg-find-marker');
                const targetEl = container.querySelector('#lg-find-target');
                const msgEl = container.querySelector('#lg-find-msg');

                const targetSize = radius * 2;
                targetEl.style.left = `${targetX}%`;
                targetEl.style.top = `${targetY}%`;
                targetEl.style.width = `${targetSize}%`;
                targetEl.style.height = `${targetSize}%`;

                function stopGame() {
                    isGameOver = true;
                    clearInterval(timerId);
                    scene.classList.add('locked');
                }

                function revealTarget() {
                    targetEl.classList.add('show');
                }

                function endGame(win) {
                    stopGame();
                    revealTarget();

                    if (win) {
                        msgEl.textContent = 'Muy bien, encontraste a Luchito.';
                        msgEl.style.color = '#155724';
                        totalScore += (time * 10) + (attempts * 60) + (currentLevel * 100);

                        setTimeout(() => {
                            if (currentLevel < levels.length) {
                                msgEl.textContent = `Nivel ${currentLevel} superado. Preparando el siguiente...`;
                                setTimeout(() => startLevel(currentLevel + 1), 1200);
                            } else {
                                window.LuchitoGames.showRankingPrompt(totalScore, 'find-luchito', container);
                            }
                        }, 900);
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
                    const rect = scene.getBoundingClientRect();
                    const x = ((clientX - rect.left) / rect.width) * 100;
                    const y = ((clientY - rect.top) / rect.height) * 100;
                    const distance = Math.hypot(x - targetX, y - targetY);
                    const hit = distance <= radius;

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
                        const rect = scene.getBoundingClientRect();
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
