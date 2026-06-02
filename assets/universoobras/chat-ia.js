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

    const escapeHTML = (str) => {
        return str.replace(/[&<>'"]/g, tag => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[tag]));
    };

    const normalizeText = (str) => {
        return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    };

    const quickResponses = [
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
            pattern: /^(adios|chau|chaufa|nos vemos|hasta luego|bye|me voy)/i,
            responses: [
                "👋 Nos vemos vecino. Aquí sigo haciendo guardia por si necesitas algo más.",
                "Un gusto ayudarte. Que tengas un excelente día.",
                "😄 Chaufa amigo. Vuelve cuando quieras.",
                "Hasta la próxima campeón. Las puertas siempre están abiertas.",
                "Que te vaya muy bien. Gracias por darte una vuelta por nuestra página."
            ]
        },
        {
            pattern: /^(gracias|muchas gracias|te lo agradezco|chevere|bacán|bacan|ok|listo|perfecto)/i,
            responses: [
                "¡De nada vecino! Para eso estamos. ¿Quieres seguir explorando?",
                "😄 ¡Con gusto! Yo aquí sigo con mis apuntes. ¿Le damos una mirada a algo más?",
                "Ya sabes que estoy para servirte. ¿Te animas a ver nuestras obras?"
            ]
        },
        {
            pattern: /^(quien eres|como te llamas|eres (lucho|luis)|eres (una )?(ia|bot|robot|chatbot|inteligencia artificial))/i,
            responses: [
                "Soy Luchito, la mascota oficial y tu anfitrión digital en esta página. ¡Un tío con mucho cariño por Tacna! ¿Te enseño dónde están nuestras propuestas?",
                "😄 Justo estaba revisando mis apuntes sobre eso. Soy Luchito, tu asistente virtual buena gente. ¿Quieres que te muestre al equipo de candidatos?"
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
        },
        {
            pattern: /\b(gordo|feo|tonto|zonzo|gil|sonso|mierda|concha|puta|cagada|imbecil|estupido|idiota|cabro|ctm|ptm|carajo|basura)\b/i,
            responses: [
                "😄 Puede ser vecino, pero al menos soy un gordito buena gente. Ahora dime, ¿qué andabas buscando? ¿Le damos una mirada a las obras?",
                "Con estos dedos gorditos a veces leo mal los mensajes, pero no me enojo. ¿Quieres que te muestre los proyectos que tenemos?"
            ]
        },
        {
            pattern: /(dina|boluarte|castillo|fujimori|congreso|presidente|lima|politica nacional)/i,
            responses: [
                "😅 Me agarraste fuera de juego, vecino. Yo ando más pendiente de las obras, propuestas y todo lo que pasa por aquí en Tacna. ¿Quieres que te lo muestre?",
                "A ver vecino, de eso no tengo apuntes. Pero de nuestra región sí tengo todo clarito. ¿Te acompaño a la sección de proyectos?"
            ]
        },
        {
            pattern: /(chiste|clima|futbol|alianza|universitario|cristal|messi|ronaldo)/i,
            responses: [
                "Jajaja, de eso sí que no tengo apuntes guardados. Pero si quieres conocer las obras o los proyectos, ahí sí te puedo ayudar. ¿Te llevo?",
                "Déjame ponerme los lentes... no, de ese tema no tengo papeles aquí. Pero te puedo enseñar cómo sumarte al equipo. ¿Quieres verlo?"
            ]
        },
        {
            pattern: /(obras|mapa)/i,
            responses: [
                "¡Al toque! Justo estaba dando una vuelta por el mapa de obras. ¿Te llevo?"
            ],
            actions: [{ label: '🗺️ Abrir Mapa de Obras', type: 'ir_a_obras' }]
        },
        {
            pattern: /(candidatos|equipo|regidores)/i,
            responses: [
                "Vamos viendo... ¡Aquí están en mis apuntes! Tenemos un equipazo. ¿Te acompaño a conocerlos?"
            ],
            actions: [{ label: '👥 Ver Candidatos', type: 'ir_a_candidatos' }]
        },
        {
            pattern: /(propuestas|plan|seguridad|educacion)/i,
            responses: [
                "Ese es mi tema favorito, vecino. Cero floro y puras propuestas reales. ¿Le damos una mirada?"
            ],
            actions: [{ label: '🚀 Ver Propuestas', type: 'ir_a_propuestas' }]
        },
        {
            pattern: /(sumate|unirme|apoyar|voluntario)/i,
            responses: [
                "¡Esa es la actitud, campeón! Siempre hay sitio para uno más en la familia. ¿Te enseño dónde inscribirte?"
            ],
            actions: [{ label: '💪 Súmate a la Fuerza', type: 'ir_a_sumate' }]
        },
        {
            pattern: /(edad|años|cumpleaños|donde vives|que comes|casado|hijos|familia|cuanto pesas|gordito)/i,
            responses: [
                "😄 Los kilitos son pura experiencia acumulada, vecino. Y de edad... digamos que tengo la suficiente para conocer esta página de memoria. ¿Te llevo a ver el mapa de obras?",
                "Vivo aquí mismito, entre los servidores y las secciones de nuestra web. Y mi comida favorita, uff, un buen picante a la tacneña. 🐻 ¿Pero qué te parece si mejor miramos a los candidatos?",
                "Jajaja, familia somos todos los que nos damos una vuelta por aquí. Yo soy tu tío digital y anfitrión. ¿Te enseño dónde sumarte al equipo?"
            ]
        },
        {
            pattern: /(cuentame un chiste|hazme reir|broma|aburrido|chistoso|divertido|cuenta algo)/i,
            responses: [
                "A ver vecino, déjame revisar mis apuntes... ¿Qué le dice un cable a otro? 'Sígueme la corriente'. 😅 Jajaja, con estos dedos gorditos no soy el mejor comediante. ¿Mejor le damos una mirada a las propuestas?",
                "Te la pongo fácil: yo haciendo dieta. Ese es mi mejor chiste, campeón. 😄 Pero ya hablando en serio, aquí en la web tenemos secciones bien interesantes. ¿Quieres que te las muestre?",
                "Déjame ponerme los lentes porque chistes no tengo guardados en mis papeles. Lo que sí tengo es información al toque. ¿Te acompaño a ver alguna obra?"
            ]
        },
        {
            pattern: /(por que eres un (oso|animal)|mascota|lentes|mate|apuntes|dedos gorditos|historia tienes)/i,
            responses: [
                "Soy un osito porque así gordito inspiro más confianza para guiarte por la página, ¿no crees? 😄 Al toque te muestro cómo funciona esto... ¿buscabas alguna obra en especial?",
                "Siempre ando con mis apuntes y mi matecito para no perderme de nada. Como anfitrión de la web, tengo que estar listo para darte una mano. ¿Qué te gustaría explorar hoy?",
                "Me diseñaron con estos dedos gorditos y lentes porque así veo mejor cada detalle de nuestras secciones. Al toque te lo demuestro... ¿Le damos una mirada al mapa?"
            ]
        }
    ];

    const navigateTo = (actionType) => {
        const routes = {
            'ir_a_obras': 'assets/universoobras/mapa-obras.html',
            'ir_a_candidatos': 'candidatos.html',
            'ir_a_propuestas': 'candidatos.html#sec-propuestas',
            'ir_a_sumate': 'sumate.html',
            'ir_a_contacto': 'contacto.html'
        };

        const dest = routes[actionType];
        if (!dest) return;

        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');

        const basePath = window.location.pathname.includes('/assets/') ? '../../' : '';

        if (typeof barba !== 'undefined' && barba.go) {
            barba.go(basePath + dest);
        } else {
            window.location.href = basePath + dest;
        }
    };

    const addMessage = (text, type, actions = []) => {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ft-message ${type}`;
        
        const avatarIcon = type === 'user-message' ? 'FT' : '🤖';
        
        let htmlContent = `<div class="ft-avatar">${avatarIcon}</div><div class="ft-bubble">${text}`;
        
        if (actions && actions.length > 0) {
            htmlContent += `<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">`;
            actions.forEach((act) => {
                htmlContent += `<button class="ft-action-btn" data-action="${act.type}" style="background: rgba(128, 16, 57, 0.1); border: 1px solid #801039; color: #801039; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; cursor: pointer; transition: all 0.2s;">${act.label}</button>`;
            });
            htmlContent += `</div>`;
        }
        htmlContent += `</div>`;
        
        msgDiv.innerHTML = htmlContent;
        
        if (actions && actions.length > 0) {
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
        messagesBody.scrollTop = messagesBody.scrollHeight;
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
        messagesBody.scrollTop = messagesBody.scrollHeight;
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
            const basePath = window.location.pathname.includes('/assets/') ? '../../' : '';
            const routerUrl = basePath + 'assets/ia_luchito/router.php';

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
        setTimeout(() => inputField.focus(), 400);
    });

    closeBtn.addEventListener('click', () => {
        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');
    });

    sendBtn.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
}

initChatIA();