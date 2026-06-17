window.LuchitoGames.registerGame('find-luchito', {
    title: 'Encuentra a Luchito',
    render: function(container, level = 'medium') {
        const styleId = 'luchito-find-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-find-game { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; font-family: system-ui, sans-serif; }
                .lg-find-header { display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 1rem; font-weight: bold; color: #801039; background: #fdf5f7; padding: 0.6rem 1.2rem; border-radius: 50px; border: 2px solid #ffc300; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                .lg-find-stat { display: flex; align-items: center; gap: 0.4rem; font-size: 1.1rem; }
                .lg-find-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 0.4rem; width: 100%; margin-top: 0.5rem; padding: 1rem; background: #fff; border: 2px solid #801039; border-radius: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
                .lg-find-cell { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; height: 45px; background: transparent; cursor: pointer; user-select: none; transition: transform 0.1s ease; border-radius: 8px; }
                .lg-find-cell:hover:not(.locked) { transform: scale(1.15) translateY(-2px); background: #fdf5f7; }
                .lg-find-cell:active:not(.locked) { transform: scale(0.95); }
                .lg-find-cell.wrong { animation: lgShake 0.3s; opacity: 0.2; pointer-events: none; }
                .lg-find-cell.found { transform: scale(1.4); z-index: 2; position: relative; animation: lgBounceFound 0.5s ease; filter: drop-shadow(0 0 10px rgba(255,195,0,0.8)); pointer-events: none; }
                
                @keyframes lgShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
                @keyframes lgBounceFound { 0% { transform: scale(1); } 50% { transform: scale(1.6); } 100% { transform: scale(1.4); } }
            `;
            document.head.appendChild(style);
        }

        const emojis = ['👨', '👩', '👴', '👵', '👮', '🕵️', '👨‍🌾', '👩‍🍳', '👨‍🔧', '👷', '👨‍🏫', '👩‍🎤', '👲', '💂', '👨‍🚒', '👩‍⚕️', '👨‍💻', '👩‍🚀'];
        
        const levelConfigs = [
            { grid: 12, time: 30 },
            { grid: 20, time: 25 },
            { grid: 30, time: 20 },
            { grid: 42, time: 18 },
            { grid: 56, time: 15 }
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

            container.innerHTML = `
                <div class="lg-game-wrapper lg-find-game lg-animated-view">
                    <div style="width: 100%; text-align: center; color: #801039; font-weight: bold;">Nivel ${currentLevel}/5 | Puntos: ${totalScore}</div>
                    <div class="lg-find-header">
                        <div class="lg-find-stat">⏱️ <span id="lg-find-time">${time}s</span></div>
                        <div style="font-family:'Arial Black Web', sans-serif; text-transform:uppercase;">¿Dónde está Luchito?</div>
                        <div class="lg-find-stat">❤️ <span id="lg-find-attempts">${attempts}</span></div>
                    </div>
                    <div class="lg-find-grid" id="lg-find-grid" style="grid-template-columns: repeat(auto-fill, minmax(${gridSize > 40 ? '35px' : '45px'}, 1fr));">
                        ${board.map((emoji, i) => `<div class="lg-find-cell" data-index="${i}" style="height: ${gridSize > 40 ? '35px' : '45px'}; font-size: ${gridSize > 40 ? '1.4rem' : '1.8rem'};">${emoji}</div>`).join('')}
                    </div>
                    <div id="lg-find-msg" style="height: 20px; font-weight: bold; color: #801039; margin-top: 0.5rem;"></div>
                    <div id="lg-find-win" class="lg-final-message" style="display:none;">
                        <h3 id="lg-find-win-title" style="color:#801039; font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; text-transform: uppercase;"></h3>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="lg-btn" id="lg-find-menu">Volver a juegos</button>
                            <button class="lg-btn-primary" id="lg-find-next">Siguiente Nivel</button>
                        </div>
                    </div>
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
                const winEl = container.querySelector('#lg-find-win');
                const titleEl = container.querySelector('#lg-find-win-title');
                const nextBtn = container.querySelector('#lg-find-next');
                
                if (win) {
                    msgEl.textContent = '¡Encontraste a Luchito! 🎉';
                    msgEl.style.color = '#155724';
                    totalScore += (time * 10) + (attempts * 50); 
                    
                    setTimeout(() => {
                        if (currentLevel < 5) {
                            titleEl.textContent = `¡Nivel ${currentLevel} Superado! 🎉`;
                            nextBtn.textContent = "Siguiente Nivel";
                            nextBtn.onclick = () => startLevel(currentLevel + 1);
                            winEl.style.display = 'flex';
                        } else {
                            window.LuchitoGames.showRankingPrompt(totalScore, 'find-luchito', container);
                        }
                    }, 800);
                } else {
                    msgEl.textContent = '¡Oh no! Se acabó el tiempo o intentos. 🐻';
                    msgEl.style.color = '#721c24';
                    const luchitoCell = container.querySelector(`.lg-find-cell[data-index="${luchitoIndex}"]`);
                    if (luchitoCell) luchitoCell.classList.add('found');
                    
                    setTimeout(() => {
                        titleEl.textContent = "¡Juego Terminado!";
                        nextBtn.textContent = "Reintentar Nivel 1";
                        nextBtn.onclick = () => { totalScore = 0; startLevel(1); };
                        winEl.style.display = 'flex';
                    }, 1500);
                }
            }
            
            container.querySelector('#lg-find-menu').addEventListener('click', () => window.LuchitoGames.renderHome());

            cells.forEach(cell => {
                cell.addEventListener('click', () => {
                    if (isGameOver) return;
                    const idx = parseInt(cell.dataset.index);
                    
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
});