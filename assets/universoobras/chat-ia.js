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
                { label: '📞 Contacto', type: 'ir_a_contacto' }
            ]
        }
    ];

    // CARGA DINÁMICA: Intentar traer las respuestas desde el JSON
    const jsonUrl = projectRoot + 'assets/ia_luchito/cache/quick_responses.json';
    const fetchUrl = jsonUrl + '?v=' + new Date().getTime(); // Evitar caché antigua

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
        const routes = {
            'ir_a_obras': projectRoot + 'assets/universoobras/mapa-obras.html',
            'ir_a_candidatos': projectRoot + 'candidatos.html',
            'ir_a_propuestas': projectRoot + 'candidatos.html#sec-propuestas',
            'ir_a_sumate': projectRoot + 'sumate.html',
            'ir_a_contacto': projectRoot + 'contacto.html'
        };

        const dest = routes[actionType];
        if (!dest) return;

        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');

        if (typeof barba !== 'undefined' && barba.go) {
            barba.go(dest);
        } else {
            window.location.href = dest;
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
                htmlContent += `<button class="ft-action-btn" data-action="${act.type}" style="background: rgba(128, 16, 57, 0.1); border: 1px solid #801039; color: #801039; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; cursor: pointer; transition: all 0.2s;">${act.label}</button>`;
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
                btn.addEventListener('mouseenter', () => { btn.style.background = '#801039'; btn.style.color = '#ffc300'; });
                btn.addEventListener('mouseleave', () => { btn.style.background = 'rgba(128, 16, 57, 0.1)'; btn.style.color = '#801039'; });
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
        chatContainer.classList.remove('ft-chat-closed');
        chatContainer.classList.add('ft-chat-open');
    }
}

initChatIA();