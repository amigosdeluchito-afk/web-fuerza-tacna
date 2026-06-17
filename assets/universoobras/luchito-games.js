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
                /* --- ESTILOS DE ESTABILIZACIÓN (JUEGO 12) --- */
                .lg-game-wrapper { max-width: 600px; margin: 0 auto; padding: 0 1rem; box-sizing: border-box; width: 100%; }
                .lg-game-card.disabled { opacity: 0.6; pointer-events: none; filter: grayscale(80%); }
                .lg-game-nav .lg-game-title { font-family: 'Arial Black Web', sans-serif; font-size: 1.4rem; color: #801039; text-transform: uppercase; letter-spacing: 1px; }
                .lg-btn-primary {
                    position: relative; background: #ffc300; color: #801039; border: 2px solid #ffc300; padding: 0.8rem 2.5rem; border-radius: 50px; font-family: 'Arial Black Web', sans-serif; font-weight: 900; font-size: 1rem; text-transform: uppercase; cursor: pointer; transition: all 0.2s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 4px 15px rgba(255, 195, 0, 0.3); outline: none !important; -webkit-tap-highlight-color: transparent;
                }
                .lg-btn-primary::before, .lg-btn-primary::after { display: none !important; }
                .lg-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 195, 0, 0.5); background: #fff; border-color: #ffc300; }
                .lg-btn-primary:active { transform: scale(0.97); transition-duration: 0.1s; }
                .lg-final-message { position: absolute; inset: 0; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; z-index: 10; animation: lgFadeSlideIn 0.5s ease forwards; }
                .lg-final-message h3 { font-family: 'Arial Black Web', sans-serif; font-size: 2rem; color: #801039; margin: 0 0 1rem 0; }
                /* --- FIN ESTILOS DE ESTABILIZACIÓN --- */

                @keyframes lgFadeSlideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
                .lg-animated-view { animation: lgFadeSlideIn 0.35s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }

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
            <div class="lg-home lg-animated-view">
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
                    <div class="lg-game-card active" onclick="window.LuchitoGames.openGame('find-luchito')">
                        <div class="lg-game-icon">🐻</div>
                        <div class="lg-game-name">Encuentra a Luchito</div>
                        <div class="lg-game-play">Jugar Ahora</div>
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
            <div class="lg-game-screen lg-animated-view">
                <div class="lg-game-nav">
                    <div class="lg-nav-left">
                        <button class="lg-back-btn" onclick="window.LuchitoGames.renderHome()">⬅ Volver</button>
                        <div class="lg-game-title" id="lg-dynamic-title">Cargando...</div>
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
    },

    // Arquitectura base para el Sistema de Ranking (MVP UI)
    showRankingPrompt: function(score, gameId, container) {
        container.innerHTML = `
            <div class="lg-animated-view" style="text-align: center; padding: 2rem 1rem;">
                <h2 style="color: #801039; font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; margin-bottom: 0.5rem; text-transform: uppercase;">¡Juego Terminado!</h2>
                <p style="font-size: 1.2rem; font-weight: bold; color: #333;">Tu puntaje fue: <span style="color:#ffc300; font-size:1.5rem; background:#801039; padding:2px 10px; border-radius:8px;">${score}</span></p>
                
                <div style="margin: 2rem auto; background: #fdf5f7; padding: 1.5rem; border-radius: 12px; border: 1px solid #ffc300; max-width: 400px;">
                    <p style="margin-top: 0; font-weight: 600; color: #555;">Escribe tu nombre para aparecer en el ranking:</p>
                    <input type="text" id="lg-ranking-name" placeholder="Tu nombre o apodo" style="width: 100%; max-width: 300px; padding: 0.8rem; border: 2px solid #801039; border-radius: 50px; font-size: 1rem; text-align: center; outline: none; margin-bottom: 1rem;">
                    <button class="lg-btn-primary" style="width: 100%; max-width: 300px; display: block; margin: 0 auto;" onclick="alert('¡Excelente! (Tu puntaje se guardará cuando la BD esté activa)')">Guardar Puntaje</button>
                </div>

                <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 1rem;">
                    <button class="lg-btn" onclick="window.LuchitoGames.renderHome()">Volver al menú</button>
                    <button class="lg-btn-primary" onclick="window.LuchitoGames.openGame('${gameId}')">Jugar otra vez</button>
                </div>
            </div>
        `;
    }
};