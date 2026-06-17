function initLuchitoGames() {
    if (document.getElementById('luchito-games-modal')) return;

    // Se asume que #ft-chat-window es el contenedor del chat
    const chatWindow = document.getElementById('ft-chat-window');
    if (!chatWindow) return;

    const modalHTML = `
        <div id="luchito-games-modal" class="lg-modal">
            <div class="lg-header">
                <div class="lg-title">🎮 Zona Arcade</div>
                <button id="lg-close-btn" class="lg-close" aria-label="Cerrar juegos">✖</button>
            </div>
            <div id="lg-content" class="lg-content">
                <!-- Menu -->
                <div id="lg-menu" class="lg-view active">
                    <p class="lg-subtitle">¡Elige un juego, vecino!</p>
                    <div class="lg-game-grid">
                        <div class="lg-game-card" data-game="tresenraya">
                            <div class="lg-game-icon">❌⭕</div>
                            <div class="lg-game-name">Tres en Raya</div>
                        </div>
                    </div>
                </div>
                
                <!-- Game Container -->
                <div id="lg-game-container" class="lg-view">
                    <div class="lg-game-header">
                        <button id="lg-back-btn" class="lg-back">⬅</button>
                        <span id="lg-game-title" class="lg-game-title"></span>
                    </div>
                    <div id="lg-game-area" class="lg-game-area"></div>
                </div>
            </div>
        </div>
    `;

    chatWindow.insertAdjacentHTML('beforeend', modalHTML);

    const closeBtn = document.getElementById('lg-close-btn');
    const backBtn = document.getElementById('lg-back-btn');
    const menuView = document.getElementById('lg-menu');
    const gameView = document.getElementById('lg-game-container');
    const gameArea = document.getElementById('lg-game-area');
    const gameTitle = document.getElementById('lg-game-title');
    const gameCards = document.querySelectorAll('.lg-game-card');

    closeBtn.addEventListener('click', closeLuchitoGames);
    backBtn.addEventListener('click', () => {
        showMenu();
        gameArea.innerHTML = ''; 
    });

    gameCards.forEach(card => {
        card.addEventListener('click', () => {
            const game = card.getAttribute('data-game');
            loadGame(game);
        });
    });

    function showMenu() {
        menuView.classList.add('active');
        gameView.classList.remove('active');
    }

    function showGame(title) {
        gameTitle.textContent = title;
        menuView.classList.remove('active');
        gameView.classList.add('active');
    }

    function loadGame(game) {
        if (game === 'tresenraya') {
            showGame('Tres en Raya');
            initTresEnRaya(gameArea);
        }
    }
}

function openLuchitoGames() {
    let modal = document.getElementById('luchito-games-modal');
    if (!modal) {
        initLuchitoGames();
        modal = document.getElementById('luchito-games-modal');
    }
    if (modal) {
        modal.classList.add('open');
    }
}

function closeLuchitoGames() {
    const modal = document.getElementById('luchito-games-modal');
    if (modal) {
        modal.classList.remove('open');
        setTimeout(() => {
            const menuView = document.getElementById('lg-menu');
            const gameView = document.getElementById('lg-game-container');
            const gameArea = document.getElementById('lg-game-area');
            if (menuView && gameView) {
                menuView.classList.add('active');
                gameView.classList.remove('active');
                gameArea.innerHTML = '';
            }
        }, 300); // limpiar después de la transición
    }
}

window.openLuchitoGames = openLuchitoGames;

// --- TRES EN RAYA ---
function initTresEnRaya(container) {
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
        board.forEach((val, index) => {
            if (val === '') emptyCells.push(index);
        });

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
            if (a === b && b === c) {
                roundWon = true;
                winningLine = winCondition;
                break;
            }
        }

        if (roundWon) {
            winningLine.forEach(idx => {
                container.querySelector(`.lg-ttt-cell[data-index="${idx}"]`).classList.add('win');
            });
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
        cells.forEach(cell => {
            cell.textContent = '';
            cell.classList.remove('x', 'o', 'win');
        });
    }
}