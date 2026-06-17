// Tres en Raya - Registrado en luchito-games
window.LuchitoGames.registerGame('tictactoe', {
    title: 'Tres en Raya',
    render: function(container, level = 'medium') {
        let board = ['', '', '', '', '', '', '', '', ''];
        let currentPlayer = 'X'; 
        let gameActive = true;

        container.innerHTML = `
            <div class="lg-game-wrapper" style="position: relative; text-align: center;">
                <div class="lg-ttt-status" id="lg-ttt-status">Tu turno (X)</div>
                <div style="max-width: 320px; margin: 1rem auto; width: 100%;">
                    <div class="lg-ttt-board" id="lg-ttt-board">
                        ${board.map((_, i) => `<div class="lg-ttt-cell" data-index="${i}"></div>`).join('')}
                    </div>
                </div>
                <button class="lg-btn-primary" id="lg-ttt-reset" style="margin-top: 1.5rem; position: relative; z-index: 20;">Reiniciar Juego</button>
            </div>
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
                if (gameActive) { currentPlayer = 'X'; statusDisplay.textContent = 'Tu turno (X)'; }
            }
        }

        function checkResult() {
            let roundWon = false;
            let winningLine = [];
            for (let i = 0; i < winningConditions.length; i++) {
                const winCondition = winningConditions[i];
                if (board[winCondition[0]] && board[winCondition[0]] === board[winCondition[1]] && board[winCondition[1]] === board[winCondition[2]]) {
                    roundWon = true; winningLine = winCondition; break;
                }
            }

            const showFinalMessage = (message) => {
                gameActive = false;
                const wrapper = container.querySelector('.lg-game-wrapper');
                const messageDiv = document.createElement('div');
                messageDiv.className = 'lg-final-message';
                messageDiv.innerHTML = `<h3>${message}</h3>`;
                wrapper.appendChild(messageDiv);
            };

            if (roundWon) { winningLine.forEach(idx => { container.querySelector(`.lg-ttt-cell[data-index="${idx}"]`).classList.add('win'); }); showFinalMessage(currentPlayer === 'X' ? '¡Ganaste, vecino! 🎉' : '¡Luchito te ganó! 🐻'); return; }
            if (!board.includes('')) { showFinalMessage('¡Empate!'); return; }
        }

        function resetGame() {
            // Re-render the game to reset everything including the final message overlay
            window.LuchitoGames.games['tictactoe'].render(container, level);
        }
    }
});