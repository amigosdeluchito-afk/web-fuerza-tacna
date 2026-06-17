window.LuchitoGames = {
    init: function() {
        if (document.getElementById('luchito-games-overlay')) return;

        // Inyectar overlay global directamente en el body
        const overlayHTML = `
            <div id="luchito-games-overlay" class="lg-overlay">
                <div id="luchito-games-modal" class="lg-global-modal">
                    <div class="lg-global-header">
                        <div class="lg-global-title">🎮 Juega con Luchito</div>
                        <button id="lg-global-close" class="lg-global-close" aria-label="Cerrar">✖</button>
                    </div>
                    <div id="luchito-games-view" class="lg-global-view">
                        <!-- El contenido (home o juego) se inyecta aquí -->
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', overlayHTML);

        // Evento cerrar
        document.getElementById('lg-global-close').addEventListener('click', () => this.close());
        document.getElementById('luchito-games-overlay').addEventListener('click', (e) => {
            if (e.target.id === 'luchito-games-overlay') this.close();
        });
    },

    open: function() {
        this.init();
        const overlay = document.getElementById('luchito-games-overlay');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden'; // Evitar scroll de la página de fondo
        this.renderHome();
    },

    close: function() {
        const overlay = document.getElementById('luchito-games-overlay');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = ''; 
    },

    renderHome: function() {
        const view = document.getElementById('luchito-games-view');
        view.innerHTML = `
            <div class="lg-home">
                <div class="lg-home-header">
                    <h2>¡Bienvenido a la Zona Arcade, vecino!</h2>
                    <p>Elige un minijuego para pasar el rato mientras seguimos trabajando por Tacna.</p>
                </div>
                <div class="lg-games-grid">
                    <div class="lg-game-card active" onclick="window.LuchitoGames.openGame('tictactoe')">
                        <div class="lg-game-icon">❌⭕</div>
                        <div class="lg-game-name">Tres en Raya</div>
                        <div class="lg-game-play">Jugar Ahora</div>
                    </div>
                    <div class="lg-game-card disabled">
                        <div class="lg-game-icon">🧠</div>
                        <div class="lg-game-name">Memoria</div>
                        <div class="lg-game-badge">Próximamente</div>
                    </div>
                    <div class="lg-game-card disabled">
                        <div class="lg-game-icon">❓</div>
                        <div class="lg-game-name">Trivia</div>
                        <div class="lg-game-badge">Próximamente</div>
                    </div>
                    <div class="lg-game-card disabled">
                        <div class="lg-game-icon">✌️🤚</div>
                        <div class="lg-game-name">Piedra, Papel o Tijera</div>
                        <div class="lg-game-badge">Próximamente</div>
                    </div>
                    <div class="lg-game-card disabled">
                        <div class="lg-game-icon">🐻</div>
                        <div class="lg-game-name">Encuentra a Luchito</div>
                        <div class="lg-game-badge">Próximamente</div>
                    </div>
                    <div class="lg-game-card disabled">
                        <div class="lg-game-icon">🧩</div>
                        <div class="lg-game-name">Rompecabezas</div>
                        <div class="lg-game-badge">Próximamente</div>
                    </div>
                </div>
            </div>
        `;
    },

    openGame: function(gameId) {
        const view = document.getElementById('luchito-games-view');
        
        if (gameId === 'tictactoe') {
            view.innerHTML = `
                <div class="lg-game-screen">
                    <div class="lg-game-nav">
                        <button class="lg-back-btn" onclick="window.LuchitoGames.renderHome()">⬅ Volver a juegos</button>
                        <h3 class="lg-game-title">Tres en Raya</h3>
                    </div>
                    <div id="lg-game-container" class="lg-game-container"></div>
                </div>
            `;
            this.initTicTacToe(document.getElementById('lg-game-container'));
        }
    },

    // --- LÓGICA DE JUEGOS ---

    initTicTacToe: function(container) {
        let board = ['', '', '', '', '', '', '', '', ''];
        let currentPlayer = 'X'; 
        let gameActive = true;

        container.innerHTML = `
            <div class="lg-ttt-status" id="lg-ttt-status">Tu turno (X)</div>
            <div class="lg-ttt-board" id="lg-ttt-board">
                ${board.map((_, i) => `<div class="lg-ttt-cell" data-index="${i}"></div>`).join('')}
            </div>
            <button class="lg-btn" id="lg-ttt-reset">Reiniciar Juego</button>
        `;

        const cells = container.querySelectorAll('.lg-ttt-cell');
        const statusDisplay = container.querySelector('#lg-ttt-status');
        const resetBtn = container.querySelector('#lg-ttt-reset');

        const winningConditions = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8],
            [0, 3, 6], [1, 4, 7], [2, 5, 8],
            [0, 4, 8], [2, 4, 6]
        ];

        cells.forEach(cell => cell.addEventListener('click', handleCellClick));
        resetBtn.addEventListener('click', resetGame);

        function handleCellClick(e) {
            const clickedCell = e.target;
            const clickedCellIndex = parseInt(clickedCell.getAttribute('data-index'));

            if (board[clickedCellIndex] !== '' || !gameActive || currentPlayer !== 'X') return;
            handleMove(clickedCell, clickedCellIndex, 'X');
            
            if (gameActive) {
                currentPlayer = 'O';
                statusDisplay.textContent = 'Luchito está pensando...';
                setTimeout(luchitoMove, 600); 
            }
        }

        function handleMove(cell, index, player) {
            board[index] = player;
            cell.textContent = player;
            cell.classList.add(player.toLowerCase());
            checkResult();
        }

        function luchitoMove() {
            if (!gameActive) return;
            let emptyCells = [];
            board.forEach((val, index) => { if (val === '') emptyCells.push(index); });

            if (emptyCells.length > 0) {
                const randomIndex = emptyCells[Math.floor(Math.random() * emptyCells.length)];
                const cell = container.querySelector(`.lg-ttt-cell[data-index="${randomIndex}"]`);
                handleMove(cell, randomIndex, 'O');
                if (gameActive) {
                    currentPlayer = 'X';
                    statusDisplay.textContent = 'Tu turno (X)';
                }
            }
        }

        function checkResult() {
            let roundWon = false;
            let winningLine = [];
            for (let i = 0; i < winningConditions.length; i++) {
                const winCondition = winningConditions[i];
                let a = board[winCondition[0]];
                let b = board[winCondition[1]];
                let c = board[winCondition[2]];
                if (a === '' || b === '' || c === '') continue;
                if (a === b && b === c) { roundWon = true; winningLine = winCondition; break; }
            }

            if (roundWon) {
                winningLine.forEach(idx => { container.querySelector(`.lg-ttt-cell[data-index="${idx}"]`).classList.add('win'); });
                statusDisplay.textContent = currentPlayer === 'X' ? '¡Ganaste, vecino! 🎉' : '¡Luchito te ganó! 🐻';
                gameActive = false;
                return;
            }

            if (!board.includes('')) {
                statusDisplay.textContent = '¡Empate!';
                gameActive = false;
                return;
            }
        }

        function resetGame() {
            board = ['', '', '', '', '', '', '', '', ''];
            currentPlayer = 'X';
            gameActive = true;
            statusDisplay.textContent = 'Tu turno (X)';
            cells.forEach(cell => { cell.textContent = ''; cell.classList.remove('x', 'o', 'win'); });
        }
    }
};