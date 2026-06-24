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

    const markDirty = () => {
        saveBtn.disabled = false;
    };

    const escapeHTML = (value) => String(value ?? '').replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));

    const parseConfig = (game) => {
        if (game.config && typeof game.config === 'object') return game.config;
        if (typeof game.config_json !== 'string' || !game.config_json.trim()) return {};
        try {
            return JSON.parse(game.config_json);
        } catch (error) {
            return {};
        }
    };

    const emptyFindLevel = (number) => ({
        title: `Nivel ${number}`,
        image: '',
        alt: '',
        hint: '',
        targetX: 50,
        targetY: 50,
        radius: 6,
        time: 25,
        attempts: 3
    });

    const renderFindLevelEditor = (game) => {
        const config = parseConfig(game);
        const levels = Array.isArray(config.levels) && config.levels.length
            ? config.levels
            : [emptyFindLevel(1)];

        const levelsHTML = levels.map((level, index) => `
            <div class="level-card" data-level-index="${index}">
                <div class="level-card-header">
                    <span>Nivel ${index + 1}</span>
                    <button type="button" class="btn btn-danger btn-small" data-action="remove-find-level">Eliminar</button>
                </div>
                <div class="form-row">
                    <div>
                        <label>Titulo del nivel</label>
                        <input type="text" data-level-field="title" value="${escapeHTML(level.title || `Nivel ${index + 1}`)}">
                    </div>
                    <div>
                        <label>Tiempo (seg.)</label>
                        <input type="number" data-level-field="time" min="5" max="120" value="${escapeHTML(level.time || 25)}">
                    </div>
                    <div>
                        <label>Intentos</label>
                        <input type="number" data-level-field="attempts" min="1" max="10" value="${escapeHTML(level.attempts || 3)}">
                    </div>
                </div>
                <div>
                    <label>Imagen del nivel (URL o ruta)</label>
                    <input type="text" data-level-field="image" placeholder="/assets/universoobras/IMG/mi-imagen.webp" value="${escapeHTML(level.image || '')}">
                </div>
                <div class="form-row">
                    <div>
                        <label>Pista corta</label>
                        <input type="text" data-level-field="hint" placeholder="Ej: Mira cerca del mural..." value="${escapeHTML(level.hint || '')}">
                    </div>
                    <div>
                        <label>Texto alternativo</label>
                        <input type="text" data-level-field="alt" placeholder="Descripcion de la imagen" value="${escapeHTML(level.alt || '')}">
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>X objetivo (%)</label>
                        <input type="number" data-level-field="targetX" min="0" max="100" step="0.1" value="${escapeHTML(level.targetX ?? 50)}">
                    </div>
                    <div>
                        <label>Y objetivo (%)</label>
                        <input type="number" data-level-field="targetY" min="0" max="100" step="0.1" value="${escapeHTML(level.targetY ?? 50)}">
                    </div>
                    <div>
                        <label>Radio (%)</label>
                        <input type="number" data-level-field="radius" min="2" max="30" step="0.1" value="${escapeHTML(level.radius || 6)}">
                    </div>
                </div>
                <div class="level-preview" data-preview>
                    <img alt="Preview nivel ${index + 1}">
                    <span class="level-preview-dot"></span>
                </div>
            </div>
        `).join('');

        return `
            <div class="special-editor" data-find-editor>
                <h3>Encuentra a Luchito por imagenes</h3>
                <p class="editor-help">
                    Cada nivel usa una imagen y un punto correcto. Puedes pegar la ruta de la imagen y luego hacer clic en la previsualizacion para calcular X/Y automaticamente.
                </p>
                <div class="level-list" data-find-levels>
                    ${levelsHTML}
                </div>
                <button type="button" class="btn btn-secondary" data-action="add-find-level" style="margin-top:12px;">Agregar nivel</button>
            </div>
        `;
    };

    const renderGames = (games) => {
        if (!games || games.length === 0) {
            gamesGrid.innerHTML = '<p style="color:#ef4444;">No se encontraron juegos en la base de datos.</p>';
            return;
        }

        gamesGrid.innerHTML = games.map(game => `
            <div class="game-card" data-game-id="${escapeHTML(game.game_id)}">
                <div class="game-card-header">
                    <span style="font-size: 18px;">${escapeHTML(game.icon)}</span> ${escapeHTML(game.title)}
                </div>
                <div class="game-card-body">
                    <div>
                        <label>Titulo visible</label>
                        <input type="text" data-field="title" value="${escapeHTML(game.title)}" required>
                    </div>
                    <div class="form-row">
                        <div style="flex: 1; max-width: 80px;">
                            <label>Icono</label>
                            <input type="text" data-field="icon" value="${escapeHTML(game.icon)}" maxlength="10">
                        </div>
                        <div style="flex: 3;">
                            <label>Estado publico</label>
                            <select data-field="status">
                                <option value="active" ${game.status === 'active' ? 'selected' : ''}>Activo (Jugar ahora)</option>
                                <option value="soon" ${game.status === 'soon' ? 'selected' : ''}>Proximamente</option>
                                <option value="disabled" ${game.status === 'disabled' ? 'selected' : ''}>Oculto (deshabilitado)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Orden</label>
                            <input type="number" data-field="sort_order" value="${escapeHTML(game.sort_order)}">
                        </div>
                        <div>
                            <label>Dificultad base</label>
                            <select data-field="default_difficulty">
                                <option value="easy" ${game.default_difficulty === 'easy' ? 'selected' : ''}>Facil</option>
                                <option value="medium" ${game.default_difficulty === 'medium' || !game.default_difficulty ? 'selected' : ''}>Medio</option>
                                <option value="hard" ${game.default_difficulty === 'hard' ? 'selected' : ''}>Dificil</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label>Descripcion / mensaje corto</label>
                        <textarea data-field="description" rows="2">${escapeHTML(game.description || '')}</textarea>
                    </div>
                    ${game.game_id === 'find-luchito' ? renderFindLevelEditor(game) : ''}
                </div>
            </div>
        `).join('');

        gamesGrid.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', () => {
                updatePreviewForElement(el);
                markDirty();
            });
        });

        gamesGrid.querySelectorAll('[data-find-editor]').forEach(editor => {
            refreshFindPreviews(editor);
        });
    };

    const getLevelValue = (card, field, fallback = '') => {
        const input = card.querySelector(`[data-level-field="${field}"]`);
        return input ? input.value : fallback;
    };

    const serializeFindConfig = (card) => {
        const levels = Array.from(card.querySelectorAll('[data-level-index]')).map((levelCard, index) => ({
            title: getLevelValue(levelCard, 'title', `Nivel ${index + 1}`).trim() || `Nivel ${index + 1}`,
            image: getLevelValue(levelCard, 'image').trim(),
            alt: getLevelValue(levelCard, 'alt').trim(),
            hint: getLevelValue(levelCard, 'hint').trim(),
            targetX: Number(getLevelValue(levelCard, 'targetX', 50)),
            targetY: Number(getLevelValue(levelCard, 'targetY', 50)),
            radius: Number(getLevelValue(levelCard, 'radius', 6)),
            time: Number(getLevelValue(levelCard, 'time', 25)),
            attempts: Number(getLevelValue(levelCard, 'attempts', 3))
        })).filter(level => level.image);

        return JSON.stringify({
            mode: 'image-hotspot',
            levels
        });
    };

    const updatePreviewForElement = (el) => {
        const levelCard = el.closest('[data-level-index]');
        if (!levelCard) return;
        refreshPreview(levelCard);
    };

    const refreshPreview = (levelCard) => {
        const preview = levelCard.querySelector('[data-preview]');
        if (!preview) return;

        const img = preview.querySelector('img');
        const dot = preview.querySelector('.level-preview-dot');
        const image = getLevelValue(levelCard, 'image').trim();
        const x = Number(getLevelValue(levelCard, 'targetX', 50));
        const y = Number(getLevelValue(levelCard, 'targetY', 50));

        if (!image) {
            preview.style.display = 'none';
            img.removeAttribute('src');
            return;
        }

        preview.style.display = 'block';
        if (img.getAttribute('src') !== image) img.src = image;
        dot.style.left = `${Number.isFinite(x) ? x : 50}%`;
        dot.style.top = `${Number.isFinite(y) ? y : 50}%`;
    };

    const refreshFindPreviews = (editor) => {
        editor.querySelectorAll('[data-level-index]').forEach(refreshPreview);
    };

    const renumberLevels = (editor) => {
        editor.querySelectorAll('[data-level-index]').forEach((card, index) => {
            card.dataset.levelIndex = index;
            const title = card.querySelector('.level-card-header span');
            if (title) title.textContent = `Nivel ${index + 1}`;
            const img = card.querySelector('[data-preview] img');
            if (img) img.alt = `Preview nivel ${index + 1}`;
        });
    };

    const addFindLevel = (editor) => {
        const list = editor.querySelector('[data-find-levels]');
        const existingCount = list.querySelectorAll('[data-level-index]').length;
        const html = renderFindLevelEditor({
            game_id: 'find-luchito',
            config: { levels: [emptyFindLevel(existingCount + 1)] }
        });
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newLevel = temp.querySelector('[data-level-index]');
        newLevel.dataset.levelIndex = existingCount;
        list.appendChild(newLevel);

        newLevel.querySelectorAll('input, textarea').forEach(el => {
            el.addEventListener('input', () => {
                updatePreviewForElement(el);
                markDirty();
            });
        });

        refreshPreview(newLevel);
        markDirty();
    };

    gamesGrid.addEventListener('click', event => {
        const actionBtn = event.target.closest('[data-action]');
        if (actionBtn) {
            const action = actionBtn.dataset.action;
            const editor = actionBtn.closest('[data-find-editor]');

            if (action === 'add-find-level' && editor) {
                addFindLevel(editor);
            }

            if (action === 'remove-find-level') {
                const levelCard = actionBtn.closest('[data-level-index]');
                const parentEditor = actionBtn.closest('[data-find-editor]');
                if (levelCard && parentEditor) {
                    levelCard.remove();
                    if (!parentEditor.querySelector('[data-level-index]')) addFindLevel(parentEditor);
                    renumberLevels(parentEditor);
                    markDirty();
                }
            }

            return;
        }

        const preview = event.target.closest('[data-preview]');
        if (!preview) return;

        const levelCard = preview.closest('[data-level-index]');
        const rect = preview.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        const xInput = levelCard.querySelector('[data-level-field="targetX"]');
        const yInput = levelCard.querySelector('[data-level-field="targetY"]');

        xInput.value = Math.max(0, Math.min(100, x)).toFixed(1);
        yInput.value = Math.max(0, Math.min(100, y)).toFixed(1);
        refreshPreview(levelCard);
        markDirty();
    });

    const loadGames = async () => {
        try {
            const response = await fetch('juegos_api.php');
            const data = await response.json();
            if (data.ok) renderGames(data.games);
            else showToast(data.error || 'Error al cargar los juegos.', true);
        } catch (error) {
            showToast('Error de conexion con la API.', true);
        }
    };

    const saveGames = async () => {
        const gameCards = gamesGrid.querySelectorAll('.game-card');
        const gamesData = Array.from(gameCards).map(card => {
            const game = { game_id: card.dataset.gameId };
            card.querySelectorAll('[data-field]').forEach(field => {
                game[field.dataset.field] = field.value;
            });

            if (game.game_id === 'find-luchito') {
                game.config_json = serializeFindConfig(card);
            }

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
            if (result.ok) showToast('Cambios guardados con exito.');
            else showToast(result.error || 'No se pudo guardar.', true);
        } catch (error) {
            showToast('Error de conexion al guardar.', true);
        } finally {
            saveBtn.textContent = 'Guardar Cambios';
        }
    };

    saveBtn.addEventListener('click', saveGames);
    loadGames();
});
