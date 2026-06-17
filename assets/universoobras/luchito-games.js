window.LuchitoGames = {
    games: {}, // Almacén de juegos cargados

    registerGame: function(id, config) {
        this.games[id] = config;
    },

    init: function() {
        if (document.getElementById('luchito-games-overlay')) return;

        // Inyectar overlay global directamente en el body
        const overlayHTML = `
            <style id="lg-global-anti-ripple">
                /* --- FIX ANTIRRIPPLE GLOBAL PARA LA ZONA ARCADE --- */
                #luchito-games-overlay button, 
                #luchito-games-overlay .lg-game-card {
                    -webkit-tap-highlight-color: transparent !important;
                    outline: none !important;
                }
                #luchito-games-overlay button::before, 
                #luchito-games-overlay button::after,
                #luchito-games-overlay .lg-game-card::before,
                #luchito-games-overlay .lg-game-card::after {
                    display: none !important;
                    content: none !important;
                    animation: none !important;
                    background: transparent !important;
                }
                #luchito-games-overlay .lg-btn, #luchito-games-overlay .lg-back-btn, #luchito-games-overlay .lg-close-btn-game {
                    transition: transform 0.2s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.2s ease !important;
                }
                #luchito-games-overlay .lg-btn:hover, #luchito-games-overlay .lg-back-btn:hover, #luchito-games-overlay .lg-close-btn-game:hover { transform: translateY(-2px) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important; }
                #luchito-games-overlay .lg-btn:active, #luchito-games-overlay .lg-back-btn:active, #luchito-games-overlay .lg-close-btn-game:active { transform: scale(0.97) !important; }
                #luchito-games-overlay button:focus-visible { outline: 2px solid #ffc300 !important; outline-offset: 2px; }
            </style>
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
                    <div class="lg-game-card active" onclick="window.LuchitoGames.openGame('memory')">
                        <div class="lg-game-icon">🧠</div>
                        <div class="lg-game-name">Memoria</div>
                        <div class="lg-game-play">Jugar Ahora</div>
                    </div>
                    <div class="lg-game-card active" onclick="window.LuchitoGames.openGame('trivia')">
                        <div class="lg-game-icon">❓</div>
                        <div class="lg-game-name">Trivia</div>
                        <div class="lg-game-play">Jugar Ahora</div>
                    </div>
                    <div class="lg-game-card active" onclick="window.LuchitoGames.openGame('rock-paper-scissors')">
                        <div class="lg-game-icon">✌️🤚</div>
                        <div class="lg-game-name">Piedra, Papel o Tijera</div>
                        <div class="lg-game-play">Jugar Ahora</div>
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

    openGame: async function(gameId, level = 'medium') {
        const view = document.getElementById('luchito-games-view');
        
        view.innerHTML = `
            <div class="lg-game-screen">
                <div class="lg-game-nav">
                    <div class="lg-nav-left">
                        <button class="lg-back-btn" onclick="window.LuchitoGames.renderHome()">⬅ Volver</button>
                        <h3 class="lg-game-title" id="lg-dynamic-title">Cargando...</h3>
                    </div>
                    <button class="lg-close-btn-game" onclick="window.LuchitoGames.close()">✖</button>
                </div>
                <div id="lg-game-container" class="lg-game-container">
                    <div style="color: #801039; font-weight: bold; margin-top: 20px;">Cargando juego... 🐻</div>
                </div>
            </div>
        `;

        if (!this.games[gameId]) {
            const GAMES_BASE_PATH = '/assets/luchito-games/games/';
            const scriptUrl = `${GAMES_BASE_PATH}${gameId}.js?v=1`;
            
            try {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = scriptUrl;
                    script.onload = resolve;
                    script.onerror = () => reject(new Error(scriptUrl));
                    document.body.appendChild(script);
                });
            } catch (e) {
                document.getElementById('lg-game-container').innerHTML = `<p style="text-align: center; color: #801039; font-weight: bold; margin-top: 20px;">No se encontró el archivo del juego.<br><small style="color: #ff6b6b; display: block; margin-top: 10px;">Ruta intentada: ${scriptUrl}</small></p>`;
                document.getElementById('lg-dynamic-title').textContent = 'Error';
                return;
            }
        }

        const game = this.games[gameId];
        if (game) {
            document.getElementById('lg-dynamic-title').textContent = game.title || 'Minijuego';
            const container = document.getElementById('lg-game-container');
            container.innerHTML = '';
            if (typeof game.render === 'function') game.render(container, level);
        }
    }
};