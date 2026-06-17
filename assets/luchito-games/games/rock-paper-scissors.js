window.LuchitoGames.registerGame('rock-paper-scissors', {
    title: 'Piedra, Papel o Tijera',
    render: function(container, level = 'medium') {
        const styleId = 'luchito-rps-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-rps-game { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; padding: 0.5rem; box-sizing: border-box; width: 100%; max-width: 550px; margin: 0 auto; }
                .lg-rps-header { text-align: center; color: #801039; margin-bottom: 0.5rem; }
                .lg-rps-header p { font-size: 0.95rem; margin: 0.1rem 0 0.2rem 0; opacity: 0.85; font-weight: bold; }
                
                .lg-rps-scoreboard-new { display: flex; align-items: center; justify-content: center; background: #801039; color: #fff; padding: 0.6rem 2rem; border-radius: 50px; font-family: 'Arial Black Web', sans-serif; font-size: 1.2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
                .lg-rps-score-new { display: flex; align-items: center; gap: 0.6rem; }
                .lg-rps-score-new .score-value { font-size: 1.8rem; color: #ffc300; min-width: 30px; text-align: center; }
                .score-divider { background: #ffc300; color: #801039; font-size: 1rem; padding: 0.2rem 0.6rem; border-radius: 8px; margin: 0 1.5rem; }
                
                .lg-rps-choices-new { display: flex; justify-content: center; gap: 1.2rem; width: 100%; margin: 0.8rem 0; }
                
                /* --- FIX ANTIRRIPPLE Y BUGS OSCUROS DE BOTONES --- */
                .lg-rps-card { position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; width: 110px; height: 110px; background: #fff; border: 3px solid #801039; border-radius: 1.2rem; font-family: 'Arial Black Web', sans-serif; font-size: 1rem; color: #801039; cursor: pointer; transition: all 0.2s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 4px 10px rgba(0,0,0,0.1); outline: none !important; -webkit-tap-highlight-color: transparent; }
                .lg-rps-card::before, .lg-rps-card::after { display: none !important; content: none !important; }
                .lg-rps-card * { position: relative; z-index: 2; pointer-events: none; }
                
                /* --- NUEVAS ANIMACIONES ELEGANTES --- */
                .lg-rps-card:hover:not(:disabled) { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(128, 16, 57, 0.2); border-color: #ffc300; }
                .lg-rps-card:active:not(:disabled) { transform: scale(0.96); transition-duration: 0.1s; }
                .lg-rps-card.clicked {
                    border-color: #801039; /* Borde granate */
                    box-shadow: 0 0 20px rgba(255, 195, 0, 0.6); /* Glow amarillo suave */
                    transform: none; /* Anula cualquier transform de hover/active */
                }

                .lg-rps-card .emoji { font-size: 3rem; filter: drop-shadow(0 3px 3px rgba(0,0,0,0.15)); margin-bottom: -5px; }
                
                .lg-rps-result-display { min-height: 110px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; width: 100%; }
                .lg-rps-result-display .result-text { font-size: 1.1rem; color: #801039; font-weight: bold; animation: pulseOpacity 1s infinite; }
                
                .vs-zone { display: flex; align-items: center; justify-content: center; gap: 1.5rem; background: #fdf5f7; padding: 0.8rem 2rem; border-radius: 50px; border: 2px solid #ffc300; box-shadow: 0 5px 15px rgba(0,0,0,0.05); animation: fadeInZoom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                .vs-side { display: flex; flex-direction: column; align-items: center; font-family: 'Arial Black Web', sans-serif; color: #333; font-size: 0.9rem; text-transform: uppercase; }
                .vs-side .emoji { font-size: 2.2rem; margin-top: 0.2rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
                .vs-center { font-size: 1.4rem; color: #801039; font-weight: 900; }
                
                .final-result { font-family: 'Arial Black Web', sans-serif; font-size: 1.5rem; color: #801039; margin-top: 0.5rem; animation: fadeInZoom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; text-transform: uppercase; letter-spacing: 1px; }
                
                .lg-rps-actions-new { min-height: 45px; margin-top: 0.2rem; }
                .lg-btn-primary { position: relative; background: #ffc300; color: #801039; border: 2px solid #ffc300; padding: 0.8rem 2.5rem; border-radius: 50px; font-family: 'Arial Black Web', sans-serif; font-weight: 900; font-size: 1rem; text-transform: uppercase; cursor: pointer; transition: all 0.2s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 4px 15px rgba(255, 195, 0, 0.3); outline: none !important; -webkit-tap-highlight-color: transparent; }
                .lg-btn-primary::before, .lg-btn-primary::after { display: none !important; content: none !important; }
                .lg-btn-primary * { position: relative; z-index: 2; pointer-events: none; }
                .lg-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 195, 0, 0.5); background: #fff; border-color: #ffc300; }
                .lg-btn-primary:active { transform: scale(0.97); transition-duration: 0.1s; }
                
                @keyframes fadeInZoom { from { opacity: 0; transform: scale(0.5); } to { opacity: 1; transform: scale(1); } }
                @keyframes pulseOpacity { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
                
                @media (max-width: 550px) {
                    .lg-rps-choices-new { flex-direction: column; align-items: center; gap: 0.8rem; }
                    .lg-rps-card { width: 100%; max-width: 260px; height: 70px; flex-direction: row; justify-content: center; gap: 1rem; border-radius: 1rem; }
                    .lg-rps-card .emoji { font-size: 2.2rem; margin-bottom: 0; }
                    .lg-rps-header h1 { font-size: 1.4rem; }
                    .lg-rps-scoreboard-new { font-size: 1rem; padding: 0.5rem 1.5rem; }
                    .lg-rps-score-new .score-value { font-size: 1.5rem; }
                    .vs-zone { padding: 0.5rem 1.2rem; gap: 1rem; }
                    .vs-side .emoji { font-size: 1.8rem; }
                    .final-result { font-size: 1.2rem; }
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
                    <p>Elige tu jugada y reta a Luchito</p>
                </div>
                <div class="lg-rps-scoreboard-new">
                    <div class="lg-rps-score-new">
                        <span>Tú</span>
                        <span id="lg-rps-player-score" class="score-value">0</span>
                    </div>
                    <div class="score-divider">VS</div>
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
                playGame(card.dataset.choice);
            });
        });

        resetBtn.addEventListener('click', () => {
            resultDisplay.innerHTML = '';
            choiceCards.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('clicked');
                btn.style.opacity = '1';
            });
            resetBtn.style.display = 'none';
        });

        function updateScores() {
            playerScoreDisplay.textContent = playerScore;
            luchitoScoreDisplay.textContent = luchitoScore;
        }

        function playGame(playerChoice) {
            choiceCards.forEach(btn => {
                btn.disabled = true;
                if(btn.dataset.choice === playerChoice) {
                    btn.classList.add('clicked');
                } else {
                    btn.style.opacity = '0.4';
                }
            });
            const luchitoChoice = choices[Math.floor(Math.random() * choices.length)];

            const playerEmoji = choiceEmojis[playerChoice];
            const luchitoEmoji = choiceEmojis[luchitoChoice];

            resultDisplay.innerHTML = `
                <p class="result-text">Luchito está pensando...</p>
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
                    <div class="vs-zone">
                        <div class="vs-side"><span>Tú</span><span class="emoji">${playerEmoji}</span></div>
                        <div class="vs-center">VS</div>
                        <div class="vs-side"><span class="emoji">${luchitoEmoji}</span><span>Luchito</span></div>
                    </div>
                    <div class="final-result">${resultText}</div>
                `;

                updateScores();
                
                resetBtn.style.display = 'inline-block';
            }, 800);
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