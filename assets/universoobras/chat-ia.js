/* =========================================================
   ASISTENTE IA - LÓGICA DE INTERFAZ (FRONTEND)
   ========================================================= */

function initChatIA() {
    const chatContainer = document.getElementById('ft-chat-container');
    if (!chatContainer || chatContainer.dataset.initialized) return;
    chatContainer.dataset.initialized = 'true';

    const fabBtn = document.getElementById('ft-chat-fab');
    const closeBtn = document.getElementById('ft-chat-close');
    const sendBtn = document.getElementById('ft-chat-send');
    const inputField = document.getElementById('ft-chat-input');
    const messagesBody = document.getElementById('ft-chat-messages');

    // 1. Abrir y Cerrar Ventana
    fabBtn.addEventListener('click', () => {
        chatContainer.classList.remove('ft-chat-closed');
        chatContainer.classList.add('ft-chat-open');
        // Auto-enfocar el input después de la animación
        setTimeout(() => inputField.focus(), 400);
    });

    closeBtn.addEventListener('click', () => {
        chatContainer.classList.remove('ft-chat-open');
        chatContainer.classList.add('ft-chat-closed');
    });

    // 2. Lógica para enviar mensaje
    const sendMessage = () => {
        const text = inputField.value.trim();
        if (!text) return; // No enviar si está vacío

        // A. Imprimir mensaje del usuario
        addMessage(text, 'user-message');
        inputField.value = '';

        // B. Mostrar que la IA está escribiendo
        showTypingIndicator();

        // C. SIMULACIÓN: Aquí luego conectaremos el PHP (Backend)
        // Por ahora, responde automáticamente después de 1.5 segundos
        setTimeout(() => {
            removeTypingIndicator();
            addMessage("¡Qué gran pregunta! Por el momento esta es una respuesta de prueba visual. Pronto estaré conectado al cerebro de Fuerza Tacna para darte datos reales.", 'ai-message');
        }, 1500);
    };

    // Función para crear e insertar los mensajes en el DOM
    const addMessage = (text, type) => {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ft-message ${type}`;
        
        const avatarIcon = type === 'user-message' ? 'FT' : '🤖';
        
        msgDiv.innerHTML = `
            <div class="ft-avatar">${avatarIcon}</div>
            <div class="ft-bubble">${text}</div>
        `;
        
        messagesBody.appendChild(msgDiv);
        // Auto-scroll hacia abajo
        messagesBody.scrollTop = messagesBody.scrollHeight;
    };

    // Animación de "Escribiendo..."
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

    const removeTypingIndicator = () => { const typing = document.getElementById('ft-typing-indicator'); if (typing) typing.remove(); };

    // Escuchar Clic y Enter
    sendBtn.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
}

// Arrancar inmediatamente al inyectarse
initChatIA();