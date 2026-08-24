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
        image: '',
        hint: '',
        hintDelay: 5,
        description: '',
        foundImage: '',
        foundDescription: '',
        shape: 'circle',
        targetX: 50,
        targetY: 50,
        radius: 6,
        targetW: 12,
        targetH: 12,
        time: 25,
        attempts: 3
    });

    const renderFindLevelEditor = (game) => {
        const config = parseConfig(game);
        const levels = Array.isArray(config.levels) && config.levels.length
            ? config.levels.slice(0, 10)
            : [emptyFindLevel(1)];

        const tabsHTML = levels.map((level, index) => `
            <button type="button" class="level-tab ${index === 0 ? 'active' : ''} ${level.image ? '' : 'empty'}" data-action="select-find-level" data-tab-index="${index}">
                Nivel ${index + 1}
            </button>
        `).join('');

        const levelsHTML = levels.map((level, index) => `
            <div class="level-card ${index === 0 ? 'active' : ''}" data-level-index="${index}">
                <div class="level-card-header">
                    <span>Nivel ${index + 1}</span>
                    <button type="button" class="btn btn-danger btn-small" data-action="remove-find-level">Eliminar</button>
                </div>
                <div class="level-admin-grid">
                    <section class="level-section">
                        <p class="level-section-title">Juego</p>
                        <div class="form-row">
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
                            <label>Imagen del nivel</label>
                            <div class="upload-row">
                                <input type="file" data-level-file accept="image/*">
                                <button type="button" class="btn btn-secondary btn-small" data-action="upload-find-image">Subir</button>
                            </div>
                            <input type="hidden" data-level-field="image" value="${escapeHTML(level.image || '')}">
                            <div class="upload-status" data-upload-status>${level.image ? 'Imagen cargada' : 'Sin imagen cargada'}</div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Pista corta</label>
                                <input type="text" data-level-field="hint" placeholder="Ej: Mira cerca del mural..." value="${escapeHTML(level.hint || '')}">
                            </div>
                            <div>
                                <label>Pista despues de (seg.)</label>
                                <input type="number" data-level-field="hintDelay" min="0" max="120" value="${escapeHTML(level.hintDelay ?? 5)}">
                            </div>
                        </div>
                    </section>
                    <section class="level-section">
                        <p class="level-section-title">Textos y personaje</p>
                        <div>
                            <label>Descripcion de la imagen</label>
                            <textarea data-level-field="description" rows="3" placeholder="Ej: Imagen panoramica del mercado, con varias personas y puestos alrededor.">${escapeHTML(level.description || '')}</textarea>
                        </div>
                        <div>
                            <label>Imagen del personaje encontrado</label>
                            <div class="upload-row">
                                <input type="file" data-level-found-file accept="image/*">
                                <button type="button" class="btn btn-secondary btn-small" data-action="upload-find-found-image">Subir personaje</button>
                            </div>
                            <input type="hidden" data-level-field="foundImage" value="${escapeHTML(level.foundImage || '')}">
                            <div class="upload-status" data-found-upload-status>${level.foundImage ? 'Personaje cargado' : 'Sin personaje cargado'}</div>
                            <div class="level-draw-hint">Recomendado: PNG o WEBP sin fondo para que aparezca como pop up.</div>
                        </div>
                        <div>
                            <label>Texto del pop up al encontrarlo</label>
                            <textarea data-level-field="foundDescription" rows="2" placeholder="Ej: Luchito estaba supervisando esta importante obra para los vecinos.">${escapeHTML(level.foundDescription || '')}</textarea>
                        </div>
                    </section>
                    <section class="level-section level-section-preview">
                        <p class="level-section-title">Area correcta</p>
                        <div>
                            <label>Forma del area</label>
                            <select data-level-field="shape">
                                <option value="circle" ${(level.shape || 'circle') === 'circle' ? 'selected' : ''}>Circulo</option>
                                <option value="rect" ${level.shape === 'rect' ? 'selected' : ''}>Cuadrado</option>
                            </select>
                        </div>
                        <input type="hidden" data-level-field="targetX" value="${escapeHTML(level.targetX ?? 50)}">
                        <input type="hidden" data-level-field="targetY" value="${escapeHTML(level.targetY ?? 50)}">
                        <input type="hidden" data-level-field="radius" value="${escapeHTML(level.radius || 6)}">
                        <input type="hidden" data-level-field="targetW" value="${escapeHTML(level.targetW || (Number(level.radius || 6) * 2))}">
                        <input type="hidden" data-level-field="targetH" value="${escapeHTML(level.targetH || (Number(level.radius || 6) * 2))}">
                        <div class="level-preview" data-preview>
                            <img alt="Preview nivel ${index + 1}">
                            <span class="level-target-area"></span>
                        </div>
                        <div class="level-draw-hint">Abre la imagen en grande para dibujar el area exacta donde esta Luchito.</div>
                        <button type="button" class="btn btn-secondary btn-small" data-action="open-area-editor" style="margin-top:8px;">Editar area en grande</button>
                    </section>
                </div>
            </div>
        `).join('');

        return `
            <div class="special-editor" data-find-editor>
                <h3>Encuentra a Luchito por imagenes</h3>
                <p class="editor-help">
                    Hasta 10 imagenes. Dibuja el area correcta; al acertar pasa al siguiente nivel.
                </p>
                <div class="level-tabs" data-find-tabs>
                    ${tabsHTML}
                </div>
                <div class="level-list" data-find-levels>
                    ${levelsHTML}
                </div>
                <button type="button" class="btn btn-secondary btn-small" data-action="add-find-level" style="margin-top:10px;" ${levels.length >= 10 ? 'disabled' : ''}>Agregar nivel</button>
            </div>
        `;
    };

    const ensureAreaEditorModal = () => {
        let modal = document.getElementById('find-area-editor-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'find-area-editor-modal';
        modal.className = 'area-editor-modal';
        modal.innerHTML = `
            <div class="area-editor-panel">
                <div class="area-editor-head">
                    <h2 class="area-editor-title" id="area-editor-title">Editar area</h2>
                    <button type="button" class="btn btn-secondary btn-small" data-action="close-area-editor">Cerrar</button>
                </div>
                <div class="area-editor-body">
                    <div class="area-editor-canvas" data-area-editor-canvas>
                        <img alt="Imagen del nivel">
                        <span class="level-target-area"></span>
                    </div>
                </div>
                <div class="area-editor-foot">
                    <span>Arrastra sobre la imagen para marcar el area correcta. Puedes cambiar entre circulo y cuadrado en el nivel.</span>
                    <button type="button" class="btn btn-success btn-small" data-action="close-area-editor">Listo</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
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
                        ${game.game_id === 'find-luchito' ? `
                            <input type="hidden" data-field="default_difficulty" value="medium">
                        ` : `
                            <div>
                                <label>Dificultad base</label>
                                <select data-field="default_difficulty">
                                    <option value="easy" ${game.default_difficulty === 'easy' ? 'selected' : ''}>Facil</option>
                                    <option value="medium" ${game.default_difficulty === 'medium' || !game.default_difficulty ? 'selected' : ''}>Medio</option>
                                    <option value="hard" ${game.default_difficulty === 'hard' ? 'selected' : ''}>Dificil</option>
                                </select>
                            </div>
                        `}
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
            const onFieldChange = () => {
                updatePreviewForElement(el);
                markDirty();
            };
            el.addEventListener('input', onFieldChange);
            el.addEventListener('change', onFieldChange);
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
            title: `Nivel ${index + 1}`,
            image: getLevelValue(levelCard, 'image').trim(),
            hint: getLevelValue(levelCard, 'hint').trim(),
            hintDelay: Number(getLevelValue(levelCard, 'hintDelay', 5)),
            description: getLevelValue(levelCard, 'description').trim(),
            foundImage: getLevelValue(levelCard, 'foundImage').trim(),
            foundDescription: getLevelValue(levelCard, 'foundDescription').trim(),
            shape: getLevelValue(levelCard, 'shape', 'circle'),
            targetX: Number(getLevelValue(levelCard, 'targetX', 50)),
            targetY: Number(getLevelValue(levelCard, 'targetY', 50)),
            radius: Number(getLevelValue(levelCard, 'radius', 6)),
            targetW: Number(getLevelValue(levelCard, 'targetW', 12)),
            targetH: Number(getLevelValue(levelCard, 'targetH', 12)),
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
        if (activeAreaLevelCard === levelCard) refreshLargeEditor();
    };

    const paintTargetArea = (levelCard, area) => {
        const shape = getLevelValue(levelCard, 'shape', 'circle');
        const x = Number(getLevelValue(levelCard, 'targetX', 50));
        const y = Number(getLevelValue(levelCard, 'targetY', 50));
        const radius = Number(getLevelValue(levelCard, 'radius', 6));
        const targetW = Number(getLevelValue(levelCard, 'targetW', 12));
        const targetH = Number(getLevelValue(levelCard, 'targetH', 12));

        area.className = `level-target-area ${shape === 'rect' ? 'rect' : 'circle'}`;

        if (shape === 'rect') {
            area.style.left = `${Number.isFinite(x) ? x : 50}%`;
            area.style.top = `${Number.isFinite(y) ? y : 50}%`;
            area.style.width = `${Number.isFinite(targetW) ? targetW : 12}%`;
            area.style.height = `${Number.isFinite(targetH) ? targetH : 12}%`;
            area.style.transform = 'none';
            return;
        }

        const size = (Number.isFinite(radius) ? radius : 6) * 2;
        area.style.left = `${Number.isFinite(x) ? x : 50}%`;
        area.style.top = `${Number.isFinite(y) ? y : 50}%`;
        area.style.width = `${size}%`;
        area.style.height = `${size}%`;
        area.style.transform = 'translate(-50%, -50%)';
    };

    const refreshPreview = (levelCard) => {
        const preview = levelCard.querySelector('[data-preview]');
        if (!preview) return;

        const img = preview.querySelector('img');
        const area = preview.querySelector('.level-target-area');
        const image = getLevelValue(levelCard, 'image').trim();

        if (!image) {
            preview.style.display = 'none';
            img.removeAttribute('src');
            return;
        }

        preview.style.display = 'block';
        if (img.getAttribute('src') !== image) img.src = image;
        paintTargetArea(levelCard, area);
    };

    const refreshFindPreviews = (editor) => {
        editor.querySelectorAll('[data-level-index]').forEach(refreshPreview);
        updateTabs(editor);
    };

    const updateTabs = (editor) => {
        const tabs = editor.querySelector('[data-find-tabs]');
        if (!tabs) return;

        const activeIndex = Number(editor.dataset.activeLevel || 0);
        tabs.innerHTML = Array.from(editor.querySelectorAll('[data-level-index]')).map((card, index) => {
            const hasImage = Boolean(getLevelValue(card, 'image').trim());
            return `
                <button type="button" class="level-tab ${index === activeIndex ? 'active' : ''} ${hasImage ? '' : 'empty'}" data-action="select-find-level" data-tab-index="${index}">
                    Nivel ${index + 1}
                </button>
            `;
        }).join('');
    };

    const selectLevel = (editor, index) => {
        const cards = Array.from(editor.querySelectorAll('[data-level-index]'));
        const safeIndex = Math.max(0, Math.min(index, cards.length - 1));
        editor.dataset.activeLevel = String(safeIndex);
        cards.forEach((card, cardIndex) => {
            card.classList.toggle('active', cardIndex === safeIndex);
        });
        updateTabs(editor);
    };

    const renumberLevels = (editor) => {
        editor.querySelectorAll('[data-level-index]').forEach((card, index) => {
            card.dataset.levelIndex = index;
            const title = card.querySelector('.level-card-header span');
            if (title) title.textContent = `Nivel ${index + 1}`;
            const img = card.querySelector('[data-preview] img');
            if (img) img.alt = `Preview nivel ${index + 1}`;
        });
        selectLevel(editor, Number(editor.dataset.activeLevel || 0));
    };

    const addFindLevel = (editor) => {
        const list = editor.querySelector('[data-find-levels]');
        const existingCount = list.querySelectorAll('[data-level-index]').length;
        if (existingCount >= 10) {
            showToast('Este juego permite maximo 10 niveles.', true);
            return;
        }
        const html = renderFindLevelEditor({
            game_id: 'find-luchito',
            config: { levels: [emptyFindLevel(existingCount + 1)] }
        });
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newLevel = temp.querySelector('[data-level-index]');
        newLevel.dataset.levelIndex = existingCount;
        list.appendChild(newLevel);

        newLevel.querySelectorAll('input, select, textarea').forEach(el => {
            const onFieldChange = () => {
                updatePreviewForElement(el);
                markDirty();
            };
            el.addEventListener('input', onFieldChange);
            el.addEventListener('change', onFieldChange);
        });

        refreshPreview(newLevel);
        updateAddLevelButtons();
        selectLevel(editor, existingCount);
        markDirty();
    };

    const updateAddLevelButtons = () => {
        gamesGrid.querySelectorAll('[data-find-editor]').forEach(editor => {
            const count = editor.querySelectorAll('[data-level-index]').length;
            const addBtn = editor.querySelector('[data-action="add-find-level"]');
            if (addBtn) addBtn.disabled = count >= 10;
        });
    };

    const uploadFindImage = async (levelCard, kind = 'level') => {
        const isFoundImage = kind === 'found';
        const fileInput = levelCard.querySelector(isFoundImage ? '[data-level-found-file]' : '[data-level-file]');
        const status = levelCard.querySelector(isFoundImage ? '[data-found-upload-status]' : '[data-upload-status]');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            showToast('Selecciona una imagen para subir.', true);
            return;
        }

        const levelIndex = Number(levelCard.dataset.levelIndex || 0) + 1;
        const formData = new FormData();
        formData.append('action', 'upload_find_image');
        formData.append('_csrf', window.CSRF_TOKEN || '');
        formData.append('level', String(levelIndex));
        formData.append('asset', isFoundImage ? 'found' : 'level');
        formData.append('image', fileInput.files[0]);

        status.textContent = isFoundImage ? 'Subiendo personaje...' : 'Subiendo imagen...';

        try {
            const response = await fetch('juegos_api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.ok) throw new Error(result.error || 'No se pudo subir la imagen.');

            const imageInput = levelCard.querySelector(isFoundImage ? '[data-level-field="foundImage"]' : '[data-level-field="image"]');
            imageInput.value = result.url;
            status.textContent = isFoundImage ? 'Personaje cargado' : 'Imagen cargada';
            refreshPreview(levelCard);
            const editor = levelCard.closest('[data-find-editor]');
            if (editor) updateTabs(editor);
            markDirty();
            showToast(isFoundImage ? 'Personaje subido para el pop up.' : 'Imagen subida. Ahora dibuja el area correcta.');
        } catch (error) {
            status.textContent = 'Error al subir';
            showToast(error.message || 'Error al subir la imagen.', true);
        }
    };

    let drawState = null;
    let activeAreaLevelCard = null;

    const refreshLargeEditor = () => {
        if (!activeAreaLevelCard) return;
        const modal = ensureAreaEditorModal();
        const canvas = modal.querySelector('[data-area-editor-canvas]');
        const img = canvas.querySelector('img');
        const area = canvas.querySelector('.level-target-area');
        const image = getLevelValue(activeAreaLevelCard, 'image').trim();
        const levelNumber = Number(activeAreaLevelCard.dataset.levelIndex || 0) + 1;

        modal.querySelector('#area-editor-title').textContent = `Nivel ${levelNumber} - dibujar area`;
        img.src = image;
        paintTargetArea(activeAreaLevelCard, area);
    };

    const openLargeEditor = (levelCard) => {
        const image = getLevelValue(levelCard, 'image').trim();
        if (!image) {
            showToast('Primero sube una imagen para este nivel.', true);
            return;
        }

        activeAreaLevelCard = levelCard;
        const modal = ensureAreaEditorModal();
        refreshLargeEditor();
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const closeLargeEditor = () => {
        const modal = document.getElementById('find-area-editor-modal');
        if (modal) modal.classList.remove('open');
        activeAreaLevelCard = null;
        drawState = null;
        document.body.style.overflow = '';
    };

    const setLevelValue = (card, field, value) => {
        const input = card.querySelector(`[data-level-field="${field}"]`);
        if (input) input.value = value;
    };

    const updateAreaFromDrag = (levelCard, startX, startY, endX, endY) => {
        const shape = getLevelValue(levelCard, 'shape', 'circle');
        const left = Math.max(0, Math.min(startX, endX));
        const top = Math.max(0, Math.min(startY, endY));
        const right = Math.min(100, Math.max(startX, endX));
        const bottom = Math.min(100, Math.max(startY, endY));
        const width = Math.max(2, right - left);
        const height = Math.max(2, bottom - top);

        if (shape === 'rect') {
            setLevelValue(levelCard, 'targetX', left.toFixed(1));
            setLevelValue(levelCard, 'targetY', top.toFixed(1));
            setLevelValue(levelCard, 'targetW', width.toFixed(1));
            setLevelValue(levelCard, 'targetH', height.toFixed(1));
        } else {
            setLevelValue(levelCard, 'targetX', ((left + right) / 2).toFixed(1));
            setLevelValue(levelCard, 'targetY', ((top + bottom) / 2).toFixed(1));
            setLevelValue(levelCard, 'radius', (Math.max(width, height) / 2).toFixed(1));
        }

        refreshPreview(levelCard);
        if (activeAreaLevelCard === levelCard) refreshLargeEditor();
        markDirty();
    };

    gamesGrid.addEventListener('click', event => {
        const actionBtn = event.target.closest('[data-action]');
        if (actionBtn) {
            const action = actionBtn.dataset.action;
            const editor = actionBtn.closest('[data-find-editor]');

            if (action === 'select-find-level' && editor) {
                selectLevel(editor, Number(actionBtn.dataset.tabIndex || 0));
            }

            if (action === 'add-find-level' && editor) {
                addFindLevel(editor);
            }

            if (action === 'upload-find-image') {
                const levelCard = actionBtn.closest('[data-level-index]');
                if (levelCard) uploadFindImage(levelCard);
            }

            if (action === 'upload-find-found-image') {
                const levelCard = actionBtn.closest('[data-level-index]');
                if (levelCard) uploadFindImage(levelCard, 'found');
            }

            if (action === 'open-area-editor') {
                const levelCard = actionBtn.closest('[data-level-index]');
                if (levelCard) openLargeEditor(levelCard);
            }

            if (action === 'remove-find-level') {
                const levelCard = actionBtn.closest('[data-level-index]');
                const parentEditor = actionBtn.closest('[data-find-editor]');
                if (levelCard && parentEditor) {
                    levelCard.remove();
                    if (!parentEditor.querySelector('[data-level-index]')) addFindLevel(parentEditor);
                    renumberLevels(parentEditor);
                    updateAddLevelButtons();
                    markDirty();
                }
            }

            return;
        }
    });

    document.addEventListener('click', event => {
        const actionBtn = event.target.closest('[data-action="close-area-editor"]');
        if (actionBtn) closeLargeEditor();

        const modal = event.target.closest('#find-area-editor-modal');
        if (event.target.id === 'find-area-editor-modal' && modal) closeLargeEditor();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.getElementById('find-area-editor-modal')?.classList.contains('open')) {
            closeLargeEditor();
        }
    });

    document.addEventListener('pointerdown', event => {
        const canvas = event.target.closest('[data-area-editor-canvas]');
        const img = canvas?.querySelector('img');
        if (!canvas || !activeAreaLevelCard || !img?.getAttribute('src')) return;
        const rect = img.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;

        drawState = {
            canvas,
            img,
            levelCard: activeAreaLevelCard,
            startX: Math.max(0, Math.min(100, x)),
            startY: Math.max(0, Math.min(100, y))
        };
        canvas.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });

    document.addEventListener('pointermove', event => {
        if (!drawState) return;
        const rect = drawState.img.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        updateAreaFromDrag(
            drawState.levelCard,
            drawState.startX,
            drawState.startY,
            Math.max(0, Math.min(100, x)),
            Math.max(0, Math.min(100, y))
        );
    });

    window.addEventListener('pointerup', () => {
        drawState = null;
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
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
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
