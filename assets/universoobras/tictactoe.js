window.LuchitoGames.games['tictactoe'] = {
    init: function(container, level = 'medium') {
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
                // En el futuro: Implementar Lógica según "level" (easy, medium, hard)
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
            if (roundWon) { winningLine.forEach(idx => { container.querySelector(`.lg-ttt-cell[data-index="${idx}"]`).classList.add('win'); }); statusDisplay.textContent = currentPlayer === 'X' ? '¡Ganaste, vecino! 🎉' : '¡Luchito te ganó! 🐻'; gameActive = false; return; }
            if (!board.includes('')) { statusDisplay.textContent = '¡Empate!'; gameActive = false; return; }
        }

        function resetGame() {
            board = ['', '', '', '', '', '', '', '', '']; currentPlayer = 'X'; gameActive = true; statusDisplay.textContent = 'Tu turno (X)';
            cells.forEach(cell => { cell.textContent = ''; cell.classList.remove('x', 'o', 'win'); });
        }
    }
};