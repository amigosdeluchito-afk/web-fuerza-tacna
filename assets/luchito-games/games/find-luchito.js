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
        let time = 15;
        let attempts = 3;
        let gridSize = 40; 
        
        // Ajustar dificultad
        if (level === 'easy') {
            time = 20;
            gridSize = 25;
        } else if (level === 'hard') {
            time = 10;
            gridSize = 60;
        }
        
        let timerId;
        let isGameOver = false;

        let board = Array(gridSize).fill('').map(() => emojis[Math.floor(Math.random() * emojis.length)]);
        
        // Posicionar a Luchito en un lugar aleatorio
        const luchitoIndex = Math.floor(Math.random() * gridSize);
        board[luchitoIndex] = '🐻'; 

        container.innerHTML = `
            <div class="lg-game-wrapper lg-find-game lg-animated-view">
                <div class="lg-find-header">
                    <div class="lg-find-stat">⏱️ <span id="lg-find-time">${time}s</span></div>
                    <div style="font-family:'Arial Black Web', sans-serif; text-transform:uppercase;">¿Dónde está Luchito?</div>
                    <div class="lg-find-stat">❤️ <span id="lg-find-attempts">${attempts}</span></div>
                </div>
                <div class="lg-find-grid" id="lg-find-grid">
                    ${board.map((emoji, i) => `<div class="lg-find-cell" data-index="${i}">${emoji}</div>`).join('')}
                </div>
                <div id="lg-find-msg" style="height: 20px; font-weight: bold; color: #801039; margin-top: 0.5rem;"></div>
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
                msgEl.textContent = '¡Encontraste a Luchito! 🎉';
                msgEl.style.color = '#155724';
                const score = (time * 10) + (attempts * 50); // Fórmula de puntaje
                setTimeout(() => window.LuchitoGames.showRankingPrompt(score, 'find-luchito', container), 1500);
            } else {
                msgEl.textContent = '¡Oh no! Luchito se escondió muy bien. 🐻';
                msgEl.style.color = '#721c24';
                // Revelar dónde estaba
                const luchitoCell = container.querySelector(`.lg-find-cell[data-index="${luchitoIndex}"]`);
                if (luchitoCell) luchitoCell.classList.add('found');
                
                setTimeout(() => window.LuchitoGames.openGame('find-luchito'), 2500); // Reinicio automático
            }
        }

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
            // FIX: Destruir el cronómetro fantasma si el usuario sale del juego
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
});