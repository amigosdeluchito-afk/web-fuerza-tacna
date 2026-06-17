window.LuchitoGames.registerGame('rock-paper-scissors', {
    title: 'Piedra, Papel o Tijera',
    render: function(container, level = 'medium') {
        const styleId = 'luchito-rps-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-rps-game { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 1rem; box-sizing: border-box; width: 100%; }
                .lg-rps-header { text-align: center; color: #801039; }
                .lg-rps-header h1 { font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; margin: 0; }
                .lg-rps-header p { font-size: 1rem; margin: 0.2rem 0 1rem 0; opacity: 0.8; }
                .lg-rps-scoreboard-new { display: flex; align-items: center; justify-content: center; gap: 1.5rem; background: #801039; color: #ffc300; padding: 0.5rem 2rem; border-radius: 12px; font-family: 'Arial Black Web', sans-serif; font-size: 1.5rem; }
                .lg-rps-score-new { display: flex; align-items: center; gap: 0.8rem; }
                .lg-rps-score-new .score-value { font-size: 2.2rem; min-width: 40px; text-align: center; }
                .lg-rps-choices-new { display: flex; justify-content: center; gap: 1.5rem; width: 100%; margin: 1.5rem 0; }
                .lg-rps-card { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; width: 130px; height: 130px; background: #fff; border: 3px solid #801039; border-radius: 1rem; font-family: 'Arial Black Web', sans-serif; font-size: 1rem; color: #801039; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .lg-rps-card:hover { transform: translateY(-5px) scale(1.05); box-shadow: 0 8px 20px rgba(128, 16, 57, 0.2); border-color: #ffc300; }
                .lg-rps-card:active { transform: translateY(0) scale(1); }
                .lg-rps-card .emoji { font-size: 3rem; filter: drop-shadow(0 3px 3px rgba(0,0,0,0.2)); }
                .lg-rps-card.clicked { animation: rps-bounce 0.4s ease; }
                .lg-rps-result-display { min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; width: 100%; }
                .lg-rps-result-display .result-text { font-size: 1.1rem; color: #555; }
                .lg-rps-result-display .result-choices { display: flex; gap: 1rem; margin: 0.5rem 0; font-size: 1.2rem; color: #333; }
                .lg-rps-result-display .result-choices .emoji { font-size: 2rem; }
                .lg-rps-result-display .final-result { font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; color: #801039; margin-top: 0.5rem; animation: fadeInZoom 0.5s ease forwards; }
                .lg-rps-actions-new { margin-top: 1rem; }
                .lg-btn-primary { background: #ffc300; color: #801039; border: none; padding: 0.8rem 2rem; border-radius: 50px; font-family: 'Arial Black Web', sans-serif; font-weight: 900; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
                .lg-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(255,195,0,0.5); }
                @keyframes rps-bounce { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
                @keyframes fadeInZoom { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
                @media (max-width: 500px) {
                    .lg-rps-choices-new { flex-direction: column; align-items: center; gap: 1rem; }
                    .lg-rps-card { width: 80%; max-width: 200px; height: auto; padding: 1rem; flex-direction: row; justify-content: flex-start; }
                    .lg-rps-card .emoji { font-size: 2rem; }
                    .lg-rps-header h1 { font-size: 1.5rem; }
                    .lg-rps-scoreboard-new { font-size: 1.2rem; }
                    .lg-rps-score-new .score-value { font-size: 1.8rem; }
                }
            `;
            document.head.appendChild(style);
        }

        // Estado del juego
        let playerScore = 0;
        let luchitoScore = 0;
        const choices = ['rock', 'paper', 'scissors'];
        const choiceEmojis = { rock: '✊', paper: '🤚', scissors: '✌️' };

        container.innerHTML = `
            <div class="lg-rps-game">
                <div class="lg-rps-header">
                    <h1>Piedra, Papel o Tijera</h1>
                    <p>Elige tu jugada y reta a Luchito</p>
                </div>
                <div class="lg-rps-scoreboard-new">
                    <div class="lg-rps-score-new">
                        <span>Tú</span>
                        <span id="lg-rps-player-score" class="score-value">0</span>
                    </div>
                    <div class="score-divider">—</div>
                    <div class="lg-rps-score-new">
                        <span id="lg-rps-luchito-score" class="score-value">0</span>
                        <span>Luchito</span>
                    </div>
                </div>
                <div class="lg-rps-choices-new" id="lg-rps-choices-container">
                    <button class="lg-rps-card" data-choice="rock"><span class="emoji">✊</span><span>Piedra</span></button>
                    <button class="lg-rps-card" data-choice="paper"><span class="emoji">🤚</span><span>Papel</span></button>
                    <button class="lg-rps-card" data-choice="scissors"><span class="emoji">✌️</span><span>Tijera</span></button>
                </div>
                <div class="lg-rps-result-display" id="lg-rps-result-display">
                    <!-- El resultado se mostrará aquí -->
                </div>
                <div class="lg-rps-actions-new">
                    <button class="lg-btn-primary" id="lg-rps-reset" style="display:none;">Nueva Partida</button>
                </div>
            </div>
        `;

        // Referencias a elementos del DOM
        const choiceCards = container.querySelectorAll('.lg-rps-card');
        const resultDisplay = container.querySelector('#lg-rps-result-display');
        const playerScoreDisplay = container.querySelector('#lg-rps-player-score');
        const luchitoScoreDisplay = container.querySelector('#lg-rps-luchito-score');
        const resetBtn = container.querySelector('#lg-rps-reset');

        // Asignar eventos
        choiceCards.forEach(card => {
            card.addEventListener('click', (e) => {
                card.classList.add('clicked');
                playGame(card.dataset.choice);
                card.addEventListener('animationend', () => card.classList.remove('clicked'), { once: true });
            });
        });

        resetBtn.addEventListener('click', () => {
            playerScore = 0;
            luchitoScore = 0;
            updateScores();
            resultDisplay.innerHTML = '';
            choiceCards.forEach(btn => btn.disabled = false);
            resetBtn.style.display = 'none';
        });

        function updateScores() {
            playerScoreDisplay.textContent = playerScore;
            luchitoScoreDisplay.textContent = luchitoScore;
        }

        function playGame(playerChoice) {
            choiceCards.forEach(btn => btn.disabled = true);
            const luchitoChoice = choices[Math.floor(Math.random() * choices.length)];

            const playerEmoji = choiceEmojis[playerChoice];
            const luchitoEmoji = choiceEmojis[luchitoChoice];

            resultDisplay.innerHTML = `
                <p class="result-text">Luchito está eligiendo...</p>
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
                    resultText = 'Luchito ganó esta vez 🐻';
                } else {
                    resultText = 'Empate, vecino 😄';
                }

                resultDisplay.innerHTML = `
                    <div class="result-choices">
                        <span>Tú: <span class="emoji">${playerEmoji}</span></span>
                        <span>Luchito: <span class="emoji">${luchitoEmoji}</span></span>
                    </div>
                    <p class="final-result">${resultText}</p>
                `;

                updateScores();
                
                choiceCards.forEach(btn => btn.disabled = false);
                resetBtn.style.display = 'inline-block';
            }, 1000);
        }

        function getWinner(player, luchito) {
            if (player === luchito) return 'tie';
            if (
                (player === 'rock' && luchito === 'scissors') ||
                (player === 'scissors' && luchito === 'paper') ||
                (player === 'paper' && luchito === 'rock')
            ) {
                return 'player';
            }
            return 'luchito';
        }
    }
});