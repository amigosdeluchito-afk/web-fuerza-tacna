// Juego de Memoria - Registrado en luchito-games
window.LuchitoGames.registerGame('memory', {
    title: 'Memoria',
    render: function(container, level = 'medium') {
        // Emojis de Fuerza Tacna
        let baseEmojis = ['🐻', '🏗️', '🚜', '🌳', '🏫', '👷'];
        
        // Ajustar dificultad
        if (level === 'easy') baseEmojis = ['🐻', '🏗️', '🚜', '🌳'];
        else if (level === 'hard') baseEmojis = ['🐻', '🏗️', '🚜', '🌳', '🏫', '👷', '🌉', '🚌'];
        
        // Duplicamos y mezclamos (Fisher-Yates)
        let cardsArray = [...baseEmojis, ...baseEmojis];
        cardsArray.sort(() => Math.random() - 0.5);

        let html = `
            <div class="lg-game-wrapper">
                <div class="lg-memory-status" style="text-align: center; font-weight: bold; color: #801039; margin-bottom: 1rem;">Movimientos: <span id="lg-mem-moves">0</span></div>
                <div class="lg-memory-board">
                    ${cardsArray.map((e, index) => `
                        <div class="lg-memory-card" data-val="${e}" data-index="${index}">
                            <div class="lg-memory-card-inner">
                                <div class="lg-memory-card-front">❓</div>
                                <div class="lg-memory-card-back">${e}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div id="lg-mem-win" style="display:none; text-align:center; margin-top:20px; animation: lgFadeSlideIn 0.5s ease forwards;">
                    <h3 style="color:#801039; font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; text-transform: uppercase;">¡Memoria de Elefante! 🎉</h3>
                    <button class="lg-btn-primary" id="lg-mem-reset">Jugar otra vez</button>
                </div>
            </div>
        `;
        container.innerHTML = html;

        let hasFlippedCard = false;
        let lockBoard = false;
        let firstCard, secondCard;
        let moves = 0;
        let matchedPairs = 0;

        const cardsEl = container.querySelectorAll('.lg-memory-card');
        const movesEl = document.getElementById('lg-mem-moves');
        const winEl = document.getElementById('lg-mem-win');
        const resetBtn = document.getElementById('lg-mem-reset');

        function flipCard() {
            if (lockBoard) return;
            if (this === firstCard) return;
            
            this.classList.add('flipped');

            if (!hasFlippedCard) {
                hasFlippedCard = true;
                firstCard = this;
                return;
            }

            secondCard = this;
            moves++;
            movesEl.textContent = moves;
            checkForMatch();
        }

        function checkForMatch() {
            let isMatch = firstCard.dataset.val === secondCard.dataset.val;
            if (isMatch) {
                firstCard.removeEventListener('click', flipCard);
                secondCard.removeEventListener('click', flipCard);
                matchedPairs++;
                if (matchedPairs === baseEmojis.length) setTimeout(() => winEl.style.display = 'block', 400);
                resetBoard();
            } else {
                lockBoard = true;
                setTimeout(() => {
                    firstCard.classList.remove('flipped');
                    secondCard.classList.remove('flipped');
                    resetBoard();
                }, 1000);
            }
        }
        function resetBoard() { [hasFlippedCard, lockBoard] = [false, false]; [firstCard, secondCard] = [null, null]; }

        cardsEl.forEach(c => c.addEventListener('click', flipCard));
        resetBtn.addEventListener('click', () => this.render(container, level)); // Reiniciar juego
    }
});