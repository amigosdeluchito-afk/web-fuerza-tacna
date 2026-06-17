window.LuchitoGames.registerGame('rock-paper-scissors', {
    title: 'Piedra, Papel o Tijera',
    render: function(container, level = 'medium') {
        // Estado del juego
        let playerScore = 0;
        let luchitoScore = 0;
        const choices = ['rock', 'paper', 'scissors'];
        const choiceEmojis = { rock: '✊', paper: '🤚', scissors: '✌️' };

        // Estructura HTML inicial del juego
        container.innerHTML = `
            <div class="lg-rps-scoreboard">
                <div class="lg-rps-score">Tú: <span id="lg-rps-player-score">0</span></div>
                <div class="lg-rps-score">Luchito: <span id="lg-rps-luchito-score">0</span></div>
            </div>
            <div class="lg-rps-choices">
                <button class="lg-rps-choice-btn" data-choice="rock">✊</button>
                <button class="lg-rps-choice-btn" data-choice="paper">🤚</button>
                <button class="lg-rps-choice-btn" data-choice="scissors">✌️</button>
            </div>
            <div class="lg-rps-result" id="lg-rps-result">
                <p>¡Elige tu jugada!</p>
            </div>
            <div class="lg-rps-actions">
                 <button class="lg-btn" id="lg-rps-reset" style="display:none;">Reiniciar Marcador</button>
            </div>
        `;

        // Referencias a elementos del DOM
        const playerChoiceBtns = container.querySelectorAll('.lg-rps-choice-btn');
        const resultDisplay = container.querySelector('#lg-rps-result');
        const playerScoreDisplay = container.querySelector('#lg-rps-player-score');
        const luchitoScoreDisplay = container.querySelector('#lg-rps-luchito-score');
        const resetBtn = container.querySelector('#lg-rps-reset');

        // Asignar eventos
        playerChoiceBtns.forEach(button => {
            button.addEventListener('click', () => playGame(button.dataset.choice));
        });

        resetBtn.addEventListener('click', () => {
            playerScore = 0;
            luchitoScore = 0;
            playerScoreDisplay.textContent = '0';
            luchitoScoreDisplay.textContent = '0';
            resultDisplay.innerHTML = '<p>¡Elige tu jugada!</p>';
            playerChoiceBtns.forEach(btn => btn.disabled = false);
            resetBtn.style.display = 'none';
        });

        function playGame(playerChoice) {
            playerChoiceBtns.forEach(btn => btn.disabled = true);
            const luchitoChoice = choices[Math.floor(Math.random() * choices.length)];

            const playerEmoji = choiceEmojis[playerChoice];
            const luchitoEmoji = choiceEmojis[luchitoChoice];

            resultDisplay.innerHTML = `
                <p>Tú elegiste: <span class="lg-rps-hand">${playerEmoji}</span></p>
                <p>Luchito está eligiendo...</p>
            `;

            // Simular que Luchito "piensa"
            setTimeout(() => {
                const winner = getWinner(playerChoice, luchitoChoice);
                let resultText = '';
                if (winner === 'player') {
                    playerScore++;
                    resultText = '¡Ganaste esta ronda! 🎉';
                } else if (winner === 'luchito') {
                    luchitoScore++;
                    resultText = '¡Luchito ganó! 🐻';
                } else {
                    resultText = '¡Empate! 🤝';
                }

                resultDisplay.innerHTML = `
                    <p>Tú elegiste: <span class="lg-rps-hand">${playerEmoji}</span></p>
                    <p>Luchito eligió: <span class="lg-rps-hand">${luchitoEmoji}</span></p>
                    <p class="lg-rps-round-result">${resultText}</p>
                `;

                playerScoreDisplay.textContent = playerScore;
                luchitoScoreDisplay.textContent = luchitoScore;
                
                playerChoiceBtns.forEach(btn => btn.disabled = false);
                resetBtn.style.display = 'inline-block';
            }, 1000);
        }

        function getWinner(player, luchito) {
            if (player === luchito) return 'tie';
            if ((player === 'rock' && luchito === 'scissors') || (player === 'scissors' && luchito === 'paper') || (player === 'paper' && luchito === 'rock')) return 'player';
            return 'luchito';
        }
    }
});