/* =========================================================
   ASISTENTE IA - LÓGICA DE INTERFAZ (FRONTEND)
   ========================================================= */

function initChatIA() {
    const chatContainer = document.getElementById('ft-chat-container');
    if (!chatContainer || chatContainer.dataset.initialized) return;

    const fabBtn = document.getElementById('ft-chat-fab');
    const closeBtn = document.getElementById('ft-chat-close');
    const sendBtn = document.getElementById('ft-chat-send');
    const inputField = document.getElementById('ft-chat-input');
    const messagesBody = document.getElementById('ft-chat-messages');

    if (!fabBtn || !closeBtn || !sendBtn || !inputField || !messagesBody) return;

    chatContainer.dataset.initialized = 'true';

    const setupFabAvatarLoop = () => {
        const avatar = fabBtn.querySelector('.ft-fab-avatar');
        const video = avatar?.querySelector('video');
        if (!avatar || !video || video.dataset.loopReady) return;
        video.dataset.loopReady = 'true';

        const markFallbackFormat = () => {
            avatar.classList.toggle('is-mp4-fallback', /\.mp4(?:$|\?)/i.test(video.currentSrc || ''));
        };
        const showVideo = () => {
            markFallbackFormat();
            avatar.classList.add('is-video-ready');
        };
        let cycleTimer = null;
        const startCycle = () => {
            window.clearTimeout(cycleTimer);
            avatar.classList.remove('is-video-playing');
            video.loop = false;
            video.pause();
            video.currentTime = 0;
            cycleTimer = window.setTimeout(() => {
                markFallbackFormat();
                avatar.classList.add('is-video-playing');
                video.currentTime = 0;
                video.play().catch(() => {
                    avatar.classList.remove('is-video-playing');
                });
            }, 6000);
        };
        video.addEventListener('loadedmetadata', markFallbackFormat, { once: true });
        video.addEventListener('canplay', showVideo, { once: true });
        video.addEventListener('playing', showVideo, { once: true });
        video.addEventListener('ended', startCycle);
        startCycle();
    };
    setupFabAvatarLoop();

    // Detectar si el usuario presionó F5 (Recargar) vs Navegación normal (Click)
    let isReload = false;
    if (window.performance) {
        const navEntries = performance.getEntriesByType("navigation");
        if (navEntries.length > 0 && navEntries[0].type === "reload") isReload = true;
        else if (performance.navigation && performance.navigation.type === 1) isReload = true;
    }
    if (isReload) {
        sessionStorage.removeItem('ft_chat_history');
        sessionStorage.removeItem('ft_chat_state');
    }

    // Quitamos el escudo de invisibilidad una vez que el CSS ya está aplicado
    setTimeout(() => {
        chatContainer.style.opacity = '';
        chatContainer.style.visibility = '';
        chatContainer.style.pointerEvents = '';
    }, 150);

    // Vincular eventos a botones iniciales si existen
    const existingBtns = messagesBody.querySelectorAll('.ft-action-btn');
    existingBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const actionType = e.target.getAttribute('data-action');
            if (typeof navigateTo === 'function') navigateTo(actionType);
        });
    });

    // --- CÁLCULO DE LA RAÍZ DEL PROYECTO (Anti-Bug de Rutas y Etiquetas Base) ---
    let projectRoot = window.location.href.split('?')[0].split('#')[0];
    if (projectRoot.includes('/assets/')) {
        projectRoot = projectRoot.substring(0, projectRoot.indexOf('/assets/'));
    } else if (projectRoot.endsWith('/obras') || projectRoot.endsWith('/obras/')) {
        projectRoot = projectRoot.replace(/\/obras\/?$/, '');
    } else {
        projectRoot = projectRoot.substring(0, projectRoot.lastIndexOf('/'));
    }
    if (!projectRoot.endsWith('/')) projectRoot += '/';

    // --- AUTO INYECCIÓN DE ESTILOS Y DEPENDENCIAS DEL CHAT ---
    if (!document.getElementById('ft-chat-dynamic-styles')) {
        const style = document.createElement('style');
        style.id = 'ft-chat-dynamic-styles';
        style.innerHTML = `
            /* --- ESTÉTICA ORIGINAL INSTITUCIONAL (SIN MANCHA OSCURA) --- */
            .ft-action-btn { background: rgba(128, 16, 57, 0.1) !important; border: 1px solid #801039 !important; color: #801039 !important; padding: 8px 14px !important; border-radius: 20px !important; font-size: 12.5px !important; font-weight: bold !important; cursor: pointer !important; transition: all 0.25s cubic-bezier(0.25, 1, 0.5, 1) !important; outline: none !important; -webkit-tap-highlight-color: transparent !important; position: relative; overflow: hidden; }
            .ft-action-btn::before, .ft-action-btn::after { display: none !important; content: none !important; }
            .ft-action-btn:hover { transform: translateY(-2px) !important; box-shadow: 0 4px 10px rgba(128, 16, 57, 0.15) !important; background: #801039 !important; color: #ffc300 !important; border-color: #801039 !important; }
            .ft-action-btn:active { transform: scale(0.96) !important; box-shadow: none !important; transition-duration: 0.1s !important; }
        `;
        document.head.appendChild(style);
    }
    if (!document.getElementById('luchito-games-css')) {
        const gamesCSS = document.createElement('link');
        gamesCSS.id = 'luchito-games-css';
        gamesCSS.rel = 'stylesheet';
        gamesCSS.href = projectRoot + 'assets/universoobras/luchito-games.css?v=6';
        document.head.appendChild(gamesCSS);
    }
    if (!document.getElementById('luchito-games-script') && !document.getElementById('luchito-games-js')) {
        const gamesJS = document.createElement('script');
        gamesJS.id = 'luchito-games-script';
        gamesJS.src = projectRoot + 'assets/universoobras/luchito-games.js?v=20';
        document.body.appendChild(gamesJS);
    }

    const escapeHTML = (str) => {
        return str.replace(/[&<>'"]/g, tag => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[tag]));
    };

    const normalizeText = (str) => {
        return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    };

    let quickResponses = [
        {
            pattern: /^(hola|holas|buenos dias|buenas tardes|buenas noches|que tal|q tal|saludos|habla)/i,
            responses: [
                "😄 ¡Hola vecino! Qué bueno verte por aquí. ¿Qué te gustaría conocer hoy?",
                "👋 Pasa nomás. Yo me encargo de que no te pierdas en la página. ¿Por dónde empezamos?",
                "😄 ¡Habla amigo! Si buscas obras, candidatos o propuestas, estás en buenas manos. ¿Qué te gustaría ver primero?",
                "¡Qué tal vecina! Justo estaba dando una vuelta por el mapa cuando llegaste. ¿En qué te ayudo?",
                "Bienvenido. Ponte cómodo y dime qué andabas buscando. ¿Te llevo a alguna sección?"
            ]
        },
        {
            pattern: /^(que (puedes|sabes) hacer|ayuda|help|opciones|menu|que hago)/i,
            responses: [
                "Te la pongo fácil, vecino. Te puedo mostrar nuestras obras, presentarte a los candidatos o enseñarte el plan de trabajo. ¿Lo vemos?"
            ],
            actions: [
                { label: '🏗️ Ver Obras', type: 'ir_a_obras' },
                { label: '👥 Candidatos', type: 'ir_a_candidatos' },
                { label: '🚀 Propuestas', type: 'ir_a_propuestas' },
                { label: '📞 Contacto', type: 'ir_a_contacto' },
                { label: '🎮 Juegos', type: 'jugar_luchito' }
            ]
        }
    ];

    // CARGA DINÁMICA: Intentar traer las respuestas desde el JSON
    const jsonUrl = projectRoot + 'assets/ia_luchito/cache/quick_responses.json';
    const fetchUrl = jsonUrl + '?v=4'; // Evitar caché antigua

    fetch(fetchUrl)
        .then(response => {
            if (!response.ok) throw new Error('El archivo JSON no se pudo cargar.');
            return response.json();
        })
        .then(data => {
            // Si el JSON trae datos válidos, sobrescribimos quickResponses
            if (Array.isArray(data) && data.length > 0) {
                quickResponses = data.map(item => ({
                    categoria: item.categoria,
                    pattern: new RegExp(item.pattern || item.pattern_str, 'i'),
                    responses: item.responses || [],
                    actions: item.actions || []
                }));
                console.log("Cerebro JSON cargado exitosamente.");
            }
        })
        .catch(error => {
            // Si falla, no hacemos nada y Luchito seguirá usando las respuestas originales del código
            console.warn('Cerebro Local (JSON) inactivo. Usando fallback del código:', error);
        });

    const navigateTo = (actionType) => {
        if (actionType === 'jugar_luchito') {
            chatContainer.classList.remove('ft-chat-open');
            chatContainer.classList.add('ft-chat-closed');

            if (window.location.hash !== '#juega-luchito') {
                window.location.hash = 'juega-luchito';
            }

            let attempts = 0;
            const openGames = () => {
                if (window.LuchitoGames && typeof window.LuchitoGames.open === 'function') {
                    window.LuchitoGames.open();
                    return;
                }
                attempts++;
                if (attempts < 25) setTimeout(openGames, 120);
            };
            openGames();
            return;
        }

        const routes = {
            'ir_a_obras': projectRoot + 'assets/universoobras/mapa-obras.html',
            'ir_a_candidatos': projectRoot + 'candidatos.html',
            'ir_a_propuestas': projectRoot + 'candidatos.html#sec-propuestas',
            'ir_a_sumate': projectRoot + 'sumate.html',
            'ir_a_contacto': projectRoot + 'contacto.html'
        };

        const dest = routes[actionType];
        let finalDest = dest;

        // NUEVO: Permite que la IA derive a una obra específica en el mapa
        if (actionType.startsWith('ir_a_obra:')) {
            const parts = actionType.split(':');
            if (parts.length >= 3) {
                const obraKey = parts[1].trim();
                const segmentKey = parts[2].trim();
                
                // Si ya estamos en el mapa, volamos directo sin recargar la página (¡Magia pura!)
                if (typeof window.gotoObra === 'function') {
                    chatContainer.classList.remove('ft-chat-open');
                    chatContainer.classList.add('ft-chat-closed');
                    window.gotoObra(obraKey, segmentKey);
                    return;
                }
                
                finalDest = projectRoot + `assets/universoobras/mapa-obras.html?s=${encodeURIComponent(segmentKey)}&obra=${encodeURIComponent(obraKey)}`;
            }
        }

        if (!finalDest) return;

        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');

        if (typeof barba !== 'undefined' && barba.go && !finalDest.includes('mapa-obras.html')) {
            barba.go(finalDest);
        } else {
            window.location.href = finalDest;
        }
    };

    const renderMessage = (text, type, actions = []) => {
        let finalActions = actions ? [...actions] : [];
        let finalText = text;

        // Parseo de botones inline (Shortcodes) solo para mensajes de la IA
        if (type === 'ai-message') {
            const btnRegex = /\[btn:([^|\]]+)\|([^\]]+)\]/gi;
            finalText = finalText.replace(btnRegex, (match, actionType, actionLabel) => {
                finalActions.push({ type: actionType.trim(), label: actionLabel.trim() });
                return ""; // Elimina el shortcode del texto visible
            }).trim();
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = `ft-message ${type}`;
        
        const avatarIcon = type === 'user-message' ? 'FT' : '🤖';
        
        let htmlContent = `<div class="ft-avatar">${avatarIcon}</div><div class="ft-bubble">${finalText}`;
        
        if (finalActions.length > 0) {
            htmlContent += `<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">`;
            finalActions.forEach((act) => {
                htmlContent += `<button class="ft-action-btn" data-action="${act.type}">${act.label}</button>`;
            });
            htmlContent += `</div>`;
        }
        htmlContent += `</div>`;
        
        msgDiv.innerHTML = htmlContent;
        
        if (finalActions.length > 0) {
            const btns = msgDiv.querySelectorAll('.ft-action-btn');
            btns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const actionType = e.target.getAttribute('data-action');
                    if (typeof navigateTo === 'function') navigateTo(actionType);
                });
            });
        }
        
        messagesBody.appendChild(msgDiv);
        setTimeout(() => {
            messagesBody.scrollTop = messagesBody.scrollHeight;
        }, 10);
    };

    const addMessage = (text, type, actions = []) => {
        renderMessage(text, type, actions);
        let history = JSON.parse(sessionStorage.getItem('ft_chat_history') || '[]');
        history.push({ text, type, actions });
        sessionStorage.setItem('ft_chat_history', JSON.stringify(history));
    };

    const showTypingIndicator = () => {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ft-message ai-message`;
        msgDiv.id = 'ft-typing-indicator';
        msgDiv.innerHTML = `
            <div class="ft-avatar">🤖</div>
            <div class="ft-bubble" style="display: flex; gap: 5px; align-items: center; padding: 16px;">
                <span style="width: 6px; height: 6px; background:#801039; border-radius:50%; animation: typingBounce 1s infinite ease-in-out both;"></span>
                <span style="width: 6px; height: 6px; background:#801039; border-radius:50%; animation: typingBounce 1s infinite ease-in-out both; animation-delay: 0.15s;"></span>
                <span style="width: 6px; height: 6px; background:#801039; border-radius:50%; animation: typingBounce 1s infinite ease-in-out both; animation-delay: 0.3s;"></span>
            </div>
        `;
        messagesBody.appendChild(msgDiv);
        setTimeout(() => {
            messagesBody.scrollTop = messagesBody.scrollHeight;
        }, 10);
    };

    const removeTypingIndicator = () => {
        const typing = document.getElementById('ft-typing-indicator');
        if (typing) typing.remove();
    };

    const sendMessage = () => {
        const text = inputField.value.trim();
        if (!text) return;
        
        // 1. Mostrar mensaje del usuario limpio
        const safeText = escapeHTML(text);
        addMessage(safeText, 'user-message');
        inputField.value = '';

        // 2. Mostrar animación de escritura
        showTypingIndicator();

        // 3. Buscar coincidencias en la Capa 1 local
        const normalizedText = normalizeText(text);
        let matchFound = false;
        let responseText = "";
        let responseActions = [];

        for (const intent of quickResponses) {
            if (intent.pattern.test(normalizedText)) {
                matchFound = true;
                const randIndex = Math.floor(Math.random() * intent.responses.length);
                responseText = intent.responses[randIndex];
                if (intent.actions) responseActions = intent.actions;
                break;
            }
        }

        // 4. Lógica de derivación: Local vs Servidor
        if (matchFound) {
            // Respuesta Local (Capa 1)
            const typingDelay = 600 + Math.random() * 600;
            setTimeout(() => {
                removeTypingIndicator();
                addMessage(responseText, 'ai-message', responseActions);
            }, typingDelay);
        } else {
            // No hay respuesta local -> Derivar al Router (Capa 2)
            const routerUrl = projectRoot + 'assets/ia_luchito/router.php';
            
            fetch(routerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensaje: text }) // Enviamos el texto original para el contexto de IA
            })
            .then(response => response.json())
            .then(data => {
                removeTypingIndicator();
                if (data.ok) {
                    addMessage(data.texto, 'ai-message', data.acciones || []);
                } else {
                    addMessage("😅 " + (data.error || "Se me cruzaron los cables un ratito."), 'ai-message');
                }
            })
            .catch(error => {
                removeTypingIndicator();
                addMessage("😅 Uf, tuve un bajón de internet, vecino. ¿Puedes intentarlo de nuevo?", 'ai-message');
            });
        }
    };

    fabBtn.addEventListener('click', () => {
        chatContainer.classList.remove('ft-chat-closed');
        chatContainer.classList.add('ft-chat-open');
        sessionStorage.setItem('ft_chat_state', 'open');
        setTimeout(() => inputField.focus(), 400);
    });

    closeBtn.addEventListener('click', () => {
        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');
        sessionStorage.setItem('ft_chat_state', 'closed');
    });

    sendBtn.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // --- RECUPERAR HISTORIAL Y ESTADO DEL CHAT ---
    const history = JSON.parse(sessionStorage.getItem('ft_chat_history') || 'null');
    if (history && history.length > 0) {
        history.forEach(msg => renderMessage(msg.text, msg.type, msg.actions));
    }
    if (sessionStorage.getItem('ft_chat_state') === 'open') {
        // Si detectamos que entramos a la sección de Obras, forzamos al chat a minimizarse
        const isMapaObras = window.location.pathname.includes('mapa-obras.html') || window.location.pathname.endsWith('/obras') || window.location.pathname.endsWith('/obras/');
        
        if (isMapaObras) {
            sessionStorage.setItem('ft_chat_state', 'closed');
        } else {
            chatContainer.classList.remove('ft-chat-closed');
            chatContainer.classList.add('ft-chat-open');
        }
    }
}

initChatIA();
