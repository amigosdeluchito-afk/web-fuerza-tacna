document.addEventListener('DOMContentLoaded', () => {
    const gamesListContainer = document.getElementById('games-list');
    const saveBtn = document.getElementById('save-all-btn');
    const toastEl = document.getElementById('save-toast');
    const toastBody = toastEl.querySelector('.toast-body');

    const showToast = (message, isError = false) => {
        toastBody.textContent = message;
        toastEl.classList.toggle('bg-danger', isError);
        toastEl.classList.toggle('text-white', isError);
        $('.toast').toast('show');
    };

    const renderGames = (games) => {
        if (!games || games.length === 0) {
            gamesListContainer.innerHTML = '<p>No se encontraron juegos para configurar.</p>';
            return;
        }

        gamesListContainer.innerHTML = games.map(game => `
            <div class="col-md-6 col-lg-4">
                <div class="card game-card" data-game-id="${game.game_id}">
                    <div class="card-header">
                        ${game.title}
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">Título Visible</label>
                            <input type="text" class="form-control form-control-sm" data-field="title" value="${game.title}">
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Descripción</label>
                            <textarea class="form-control form-control-sm" data-field="description" rows="2">${game.description}</textarea>
                        </div>
                         <div class="form-group">
                            <label class="small font-weight-bold">Ícono (Emoji)</label>
                            <input type="text" class="form-control form-control-sm" data-field="icon" value="${game.icon}" maxlength="4">
                        </div>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label class="small font-weight-bold">Estado</label>
                                <select class="form-control form-control-sm" data-field="status">
                                    <option value="active" ${game.status === 'active' ? 'selected' : ''}>Activo</option>
                                    <option value="soon" ${game.status === 'soon' ? 'selected' : ''}>Próximamente</option>
                                    <option value="disabled" ${game.status === 'disabled' ? 'selected' : ''}>Deshabilitado</option>
                                </select>
                            </div>
                            <div class="col-6 form-group">
                                <label class="small font-weight-bold">Orden</label>
                                <input type="number" class="form-control form-control-sm" data-field="sort_order" value="${game.sort_order}">
                            </div>
                        </div>
                         <div class="form-group mb-0">
                            <label class="small font-weight-bold">Dificultad por Defecto</label>
                            <input type="text" class="form-control form-control-sm" data-field="default_difficulty" value="${game.default_difficulty}">
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        // Habilitar el botón de guardar cuando se detecte un cambio
        gamesListContainer.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', () => {
                saveBtn.disabled = false;
            });
        });
    };

    const loadGames = async () => {
        try {
            const response = await fetch('juegos_api.php');
            const data = await response.json();
            if (data.ok) {
                renderGames(data.games);
            } else {
                showToast(data.error || 'Error al cargar los juegos.', true);
            }
        } catch (error) {
            showToast('Error de conexión con la API.', true);
        }
    };

    const saveGames = async () => {
        const gameCards = gamesListContainer.querySelectorAll('.game-card');
        const gamesData = Array.from(gameCards).map(card => {
            const game = { game_id: card.dataset.gameId };
            card.querySelectorAll('[data-field]').forEach(field => {
                game[field.dataset.field] = field.value;
            });
            return game;
        });

        saveBtn.disabled = true;
        saveBtn.textContent = 'Guardando...';

        try {
            const response = await fetch('juegos_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ games: gamesData })
            });
            const result = await response.json();
            showToast(result.message || result.error, !result.ok);
        } catch (error) {
            showToast('Error de conexión al guardar.', true);
        } finally {
            saveBtn.textContent = 'Guardar Cambios';
        }
    };

    saveBtn.addEventListener('click', saveGames);
    loadGames();
});