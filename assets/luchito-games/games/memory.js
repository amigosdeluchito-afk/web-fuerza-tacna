// Juego de Memoria - Registrado en luchito-games
window.LuchitoGames.registerGame('memory', {
    title: 'Memoria',
    render: function(container, default_difficulty = 'medium') {
        const fullEmojiPool = ['🐻', '🏗️', '🚜', '🌳', '🏫', '👷', '🌉', '🚌', '🏥', '⚽', '🚓', '🚒', '🛒', '🏛️'];
        const levelConfigs = [4, 6, 8, 10, 12]; // Pares a encontrar por nivel
        
        let currentLevel = 1;
        let totalScore = 0;
        let moves = 0;
        let matchedPairs = 0;
        let firstCard, secondCard;
        let hasFlippedCard = false;
        let lockBoard = false;

        function startLevel(lvl) {
            currentLevel = lvl;
            moves = 0;
            matchedPairs = 0;
            hasFlippedCard = false;
            lockBoard = false;
            firstCard = null;
            secondCard = null;

            const numPairs = levelConfigs[lvl - 1];
            let levelEmojis = fullEmojiPool.slice(0, numPairs);
            
            let cardsArray = [...levelEmojis, ...levelEmojis];
            cardsArray.sort(() => Math.random() - 0.5);

            let html = `
                <div class="lg-game-wrapper">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; color: #801039; margin-bottom: 1rem; font-size: 0.9rem;">
                        <span>Nivel ${currentLevel}/5</span>
                        <span>Movs: <span id="lg-mem-moves">0</span></span>
                        <span>Puntos: ${totalScore}</span>
                    </div>
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
                </div>
            `;
            container.innerHTML = html;

            const cardsEl = container.querySelectorAll('.lg-memory-card');
            cardsEl.forEach(c => c.addEventListener('click', flipCard));
        }

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
            container.querySelector('#lg-mem-moves').textContent = moves;
            checkForMatch();
        }

        function checkForMatch() {
            let isMatch = firstCard.dataset.val === secondCard.dataset.val;
            if (isMatch) {
                firstCard.removeEventListener('click', flipCard);
                secondCard.removeEventListener('click', flipCard);
                matchedPairs++;
                
                totalScore += 50; // Puntos fijos por par
                if (matchedPairs === levelConfigs[currentLevel - 1]) {
                    const parIdeal = levelConfigs[currentLevel - 1] + 2;
                    if (moves <= parIdeal) totalScore += 100; // Bono de desempeño
                    
                    setTimeout(() => {
                        if (currentLevel < 5) {
                            const wrapper = container.querySelector('.lg-game-wrapper');
                            const msgDiv = document.createElement('div');
                            msgDiv.className = 'lg-final-message';
                            msgDiv.style.display = 'flex';
                            msgDiv.innerHTML = `<h3>¡Nivel ${currentLevel} Superado!</h3>`;
                            wrapper.appendChild(msgDiv);
                            setTimeout(() => startLevel(currentLevel + 1), 1200);
                        } else {
                            window.LuchitoGames.showRankingPrompt(totalScore, 'memory', container);
                        }
                    }, 400);
                }
                resetBoard();
            } else {
                lockBoard = true;
                totalScore = Math.max(0, totalScore - 5); // Penalización por equivocarse
                setTimeout(() => {
                    firstCard.classList.remove('flipped');
                    secondCard.classList.remove('flipped');
                    resetBoard();
                }, 1000);
            }
        }
        function resetBoard() { [hasFlippedCard, lockBoard] = [false, false]; [firstCard, secondCard] = [null, null]; }

        startLevel(currentLevel);
    }
});