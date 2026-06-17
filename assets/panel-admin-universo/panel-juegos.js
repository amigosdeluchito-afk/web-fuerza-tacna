document.addEventListener('DOMContentLoaded', () => {
    const gamesGrid = document.getElementById('games-grid');
    const saveBtn = document.getElementById('save-all-btn');
    const toastMsg = document.getElementById('toast-msg');

    const showToast = (message, isError = false) => {
        toastMsg.textContent = message;
        if (isError) toastMsg.classList.add('error');
        else toastMsg.classList.remove('error');
        
        toastMsg.classList.add('show');
        setTimeout(() => toastMsg.classList.remove('show'), 3000);
    };

    const renderGames = (games) => {
        if (!games || games.length === 0) {
            gamesGrid.innerHTML = '<p style="color:#ef4444;">No se encontraron juegos en la base de datos.</p>';
            return;
        }

        gamesGrid.innerHTML = games.map(game => `
            <div class="game-card" data-game-id="${game.game_id}">
                <div class="game-card-header">
                    <span style="font-size: 18px;">${game.icon}</span> ${game.title}
                </div>
                <div class="game-card-body">
                    <div>
                        <label>Título Visible</label>
                        <input type="text" data-field="title" value="${game.title}" required>
                    </div>
                    <div class="form-row">
                        <div style="flex: 1; max-width: 80px;">
                            <label>Ícono</label>
                            <input type="text" data-field="icon" value="${game.icon}" maxlength="10">
                        </div>
                        <div style="flex: 3;">
                            <label>Estado Público</label>
                            <select data-field="status">
                                <option value="active" ${game.status === 'active' ? 'selected' : ''}>🟢 Activo (Jugar Ahora)</option>
                                <option value="soon" ${game.status === 'soon' ? 'selected' : ''}>🟡 Próximamente</option>
                                <option value="disabled" ${game.status === 'disabled' ? 'selected' : ''}>🔴 Oculto (Deshabilitado)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Orden</label>
                            <input type="number" data-field="sort_order" value="${game.sort_order}">
                        </div>
                        <div>
                            <label>Dificultad Base</label>
                            <select class="form-control form-control-sm" data-field="default_difficulty">
                                <option value="easy" ${game.default_difficulty === 'easy' ? 'selected' : ''}>🟢 Fácil</option>
                                <option value="medium" ${game.default_difficulty === 'medium' || !game.default_difficulty ? 'selected' : ''}>🟡 Medio</option>
                                <option value="hard" ${game.default_difficulty === 'hard' ? 'selected' : ''}>🔴 Difícil</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label>Descripción / Mensaje corto</label>
                        <textarea data-field="description" rows="2">${game.description || ''}</textarea>
                    </div>
                </div>
            </div>
        `).join('');

        // Detectar cambios para habilitar botón
        gamesGrid.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', () => { saveBtn.disabled = false; });
        });
    };

    const loadGames = async () => {
        try {
            const response = await fetch('juegos_api.php');
            const data = await response.json();
            if (data.ok) renderGames(data.games);
            else showToast(data.error || 'Error al cargar los juegos.', true);
        } catch (error) { showToast('Error de conexión con la API.', true); }
    };

    const saveGames = async () => {
        const gameCards = gamesGrid.querySelectorAll('.game-card');
        const gamesData = Array.from(gameCards).map(card => {
            const game = { game_id: card.dataset.gameId };
            card.querySelectorAll('[data-field]').forEach(f => game[f.dataset.field] = f.value);
            return game;
        });

        saveBtn.disabled = true; saveBtn.textContent = '⏳ Guardando...';
        try {
            const response = await fetch('juegos_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ games: gamesData }) });
            const result = await response.json();
            if (result.ok) showToast('¡Cambios guardados con éxito!');
            else showToast(result.error, true);
        } catch (error) { showToast('Error de conexión al guardar.', true); } 
        finally { saveBtn.textContent = '💾 Guardar Cambios'; }
    };

    saveBtn.addEventListener('click', saveGames);
    loadGames();
});