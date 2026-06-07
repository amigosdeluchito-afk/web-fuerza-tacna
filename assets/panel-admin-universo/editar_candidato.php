<?php
require_once __DIR__ . '/config.php';
require_login();

$id_candidato = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editor Visual de Candidato - Fuerza Tacna</title>
    <style>
        body { margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #f8fafc; overflow: hidden; }
        
        /* Cabecera del Panel */
        .app-header { height: 56px; background: #020617; border-bottom: 1px solid #1e293b; display: flex; align-items: center; padding: 0 24px; justify-content: space-between; z-index: 100; position: relative; }
        .app-header nav a { color: #94a3b8; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #fff; font-weight: 600; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .app-header .user a { color: #9ca3af; text-decoration: none; }
        
        /* Layout de Pantalla Dividida */
        .split-layout { display: flex; height: calc(100vh - 56px); }
        
        /* MITAD IZQUIERDA: Formulario */
        .editor-left { width: 45%; border-right: 1px solid #1e293b; display: flex; flex-direction: column; background: #0f172a; }
        
        /* MITAD DERECHA: Live Preview (Fase 3) */
        .editor-right { width: 55%; background: #1e293b; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        
        /* --- VISTA PREVIA MÓVIL (DISEÑO FUERZA TACNA) --- */
        .preview-container { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #0f172a; padding: 20px; box-sizing: border-box; }
        .candidate-card { width: 375px; height: 100%; max-height: 800px; background: #1e293b; border-radius: 24px; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 1px solid #334155; position: relative; font-family: system-ui, sans-serif; }
        .candidate-card::-webkit-scrollbar { width: 6px; }
        .candidate-card::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .card-hero { text-align: center; padding: 30px 20px 10px; position: relative; border-bottom: 1px solid #334155; }
        .badge-flotante { display: inline-block; background: #801039; color: #ffc300; font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 999px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .hero-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ffc300; margin-bottom: 10px; background: #334155; }
        .card-hero h1 { font-size: 22px; margin: 0 0 10px; color: #f8fafc; font-weight: 900; }
        .tags-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
        .preview-tag { background: #334155; color: #cbd5e1; font-size: 10px; padding: 3px 8px; border-radius: 4px; font-weight: 600; }
        .card-body { padding: 20px; }
        .quote-box { background: linear-gradient(135deg, rgba(128,16,57,0.2), transparent); border-left: 3px solid #ffc300; padding: 12px 15px; font-style: italic; color: #f1f5f9; font-size: 14px; margin-bottom: 20px; border-radius: 0 8px 8px 0; }
        .bio-text { font-size: 13px; color: #94a3b8; line-height: 1.6; margin-bottom: 25px; }
        .section-title { font-size: 15px; color: #f8fafc; margin: 0 0 15px; border-bottom: 1px solid #334155; padding-bottom: 5px; text-transform: uppercase; font-weight: bold; }
        .timeline { border-left: 2px solid #334155; padding-left: 15px; margin-bottom: 25px; margin-left: 5px; }
        .timeline-item { position: relative; margin-bottom: 15px; }
        .timeline-item::before { content: ""; position: absolute; left: -21px; top: 2px; width: 10px; height: 10px; background: #ffc300; border-radius: 50%; box-shadow: 0 0 8px rgba(255,195,0,0.5); }
        .timeline-period { font-size: 11px; font-weight: bold; color: #3b82f6; text-transform: uppercase; }
        .timeline-desc { font-size: 12px; color: #cbd5e1; margin: 4px 0 0; line-height: 1.4; }
        .propuestas-grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 25px; }
        .prop-card { background: #0f172a; border: 1px solid #334155; padding: 12px; border-radius: 12px; display: flex; gap: 12px; align-items: flex-start; }
        .prop-icon { font-size: 22px; line-height: 1; }
        .prop-content h4 { margin: 0 0 4px; font-size: 13px; color: #f8fafc; font-weight: bold; }
        .prop-content p { margin: 0; font-size: 11.5px; color: #94a3b8; line-height: 1.4; }
        .fb-widget { background: #1877f2; border-radius: 12px; padding: 15px; text-align: center; color: white; display: flex; flex-direction: column; gap: 5px; }
        .fb-widget h4 { margin: 0; font-size: 14px; font-weight: bold; }
        .fb-widget p { margin: 0; font-size: 12px; opacity: 0.9; }
        
        /* Sistema de Pestañas (Tabs) */
        .tabs-header { display: flex; background: #020617; border-bottom: 1px solid #1e293b; flex-wrap: wrap; }
        .tab-btn { flex: 1; min-width: 80px; padding: 12px 10px; background: transparent; border: none; color: #94a3b8; font-weight: bold; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 13px; }
        .tab-btn:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; background: rgba(59,130,246,0.1); }
        
        .tab-content { display: none; padding: 20px; overflow-y: auto; flex: 1; }
        .tab-content.active { display: block; }
        
        /* Controles de Formulario */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 6px; color: #cbd5e1; font-weight: 600; text-transform: uppercase; }
        .form-control { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #334155; background: #020617; color: #fff; box-sizing: border-box; font-family: inherit; font-size: 13px; }
        .form-control:focus { border-color: #3b82f6; outline: none; }
        
        /* Listas Dinámicas (Tarjetas repetibles) */
        .dynamic-item { background: #1e293b; border: 1px solid #334155; padding: 15px; border-radius: 8px; margin-bottom: 15px; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-remove { position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; border-radius: 50%; cursor: pointer; width: 24px; height: 24px; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .btn-remove:hover { background: #dc2626; transform: scale(1.1); }
        
        .btn-add { background: transparent; border: 1px dashed #3b82f6; color: #3b82f6; padding: 12px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 5px; font-size: 13px; transition: 0.2s; }
        .btn-add:hover { background: rgba(59,130,246,0.1); }
        
        .btn-save { background: #10b981; color: white; border: none; padding: 15px 20px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; transition: 0.2s; }
        .btn-save:hover { background: #059669; }
    </style>
</head>
<body>

<header class="app-header">
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
    <a href="gestionar_visibilidad.php">👁️ Ocultar/Eliminar</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="editar_candidato.php" class="active">👥 Editar Candidatos</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
    <a href="ia_conocimiento.php">📚 Base Conocimiento</a>
    <a href="ia_fuentes.php">🔗 Fuentes Externas</a>
    <a href="ia_estadisticas.php">📊 Estadísticas IA</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php">👤 Usuarios</a>
    <a href="historial.php">🕒 Historial</a>
    <a href="ver_accesos.php">🕵️ Accesos IP</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php">Salir</a>
  </div>
</header>

<div class="split-layout">
    <!-- LADO IZQUIERDO: CONTROLES DEL EDITOR -->
    <div class="editor-left">
        <div class="tabs-header">
            <button type="button" class="tab-btn active" onclick="openTab('perfil', this)">👤 Perfil</button>
            <button type="button" class="tab-btn" onclick="openTab('etiquetas', this)">🏷️ Etiquetas</button>
            <button type="button" class="tab-btn" onclick="openTab('trayectoria', this)">⏳ Trayectoria</button>
            <button type="button" class="tab-btn" onclick="openTab('propuestas', this)">🚀 Propuestas</button>
        </div>
        
        <form id="candidato-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <!-- PESTAÑA 1: PERFIL PRINCIPAL -->
            <div id="tab-perfil" class="tab-content active">
                <div class="form-group">
                    <label>Nombres y Apellidos</label>
                    <input type="text" name="nombres" class="form-control" placeholder="Ej: Patrick Stewart">
                </div>
                <div class="form-group">
                    <label>Cargo Flotante (Badge Superior)</label>
                    <input type="text" name="cargo_flotante" class="form-control" placeholder="Ej: Candidato a Alcalde Provincial">
                </div>
                <div class="form-group">
                    <label>Frase de Campaña (Cita Destacada)</label>
                    <textarea name="frase_cita" class="form-control" rows="3" placeholder="Trabajaré incansablemente por una ciudad más segura..."></textarea>
                </div>
                <div class="form-group">
                    <label>Biografía Resumida</label>
                    <textarea name="biografia" class="form-control" rows="5" placeholder="Nació en Tacna, con más de 20 años de experiencia..."></textarea>
                </div>
                
                <h4 style="color:#94a3b8; font-size: 14px; border-bottom:1px solid #334155; padding-bottom:5px; margin-top:25px;">📘 Widget de Facebook</h4>
                <div class="form-group">
                    <label>Título del Widget</label>
                    <input type="text" name="fb_titulo" class="form-control" placeholder="Ej: ¡Sigue mi campaña!">
                </div>
                <div class="form-group">
                    <label>Descripción del Widget</label>
                    <input type="text" name="fb_descripcion" class="form-control" placeholder="Entérate de las últimas noticias...">
                </div>
                <div class="form-group">
                    <label>Enlace del Perfil de Facebook</label>
                    <input type="text" name="fb_url_perfil" class="form-control" placeholder="https://facebook.com/patrick...">
                </div>
            </div>
            
            <!-- PESTAÑA 2: ETIQUETAS (BADGES) -->
            <div id="tab-etiquetas" class="tab-content">
                <p style="color:#94a3b8; font-size:13px; margin-top:0;">Añade los pequeños botones grises que aparecen debajo del nombre.</p>
                <div id="etiquetas-list"></div>
                <button type="button" class="btn-add" onclick="addEtiqueta()">+ Añadir Etiqueta</button>
            </div>
            
            <!-- PESTAÑA 3: TRAYECTORIA (LÍNEA DE TIEMPO) -->
            <div id="tab-trayectoria" class="tab-content">
                <p style="color:#94a3b8; font-size:13px; margin-top:0;">Agrega los años de experiencia. Más adelante activaremos la subida de fotos por año.</p>
                <div id="trayectoria-list"></div>
                <button type="button" class="btn-add" onclick="addTrayectoria()">+ Añadir Periodo</button>
            </div>
            
            <!-- PESTAÑA 4: PROPUESTAS (PILARES) -->
            <div id="tab-propuestas" class="tab-content">
                <p style="color:#94a3b8; font-size:13px; margin-top:0;">Agrega las tarjetas de propuestas con sus iconos.</p>
                <div id="propuestas-list"></div>
                <button type="button" class="btn-add" onclick="addPropuesta()">+ Añadir Propuesta</button>
            </div>
            
            <button type="button" class="btn-save">💾 Guardar Cambios en la Base de Datos</button>
        </form>
    </div>

    <!-- LADO DERECHO: VISTA PREVIA (Fase 3) -->
    <div class="editor-right">
        <div class="preview-container">
            <div class="candidate-card">
                <div class="card-hero">
                    <div class="badge-flotante" id="preview-cargo">Candidato a Alcalde</div>
                    <img src="https://via.placeholder.com/100/334155/94a3b8?text=Foto" id="preview-foto" class="hero-avatar" alt="Foto">
                    <h1 id="preview-nombre">Nombre del Candidato</h1>
                    <div class="tags-container" id="preview-etiquetas">
                        <!-- Se inyectan etiquetas aquí -->
                    </div>
                </div>
                <div class="card-body">
                    <div class="quote-box">
                        <p id="preview-frase">"Escribe una frase destacada para verla aquí..."</p>
                    </div>
                    <p class="bio-text" id="preview-bio">Resumen biográfico aparecerá aquí...</p>
                    
                    <h3 class="section-title">Trayectoria</h3>
                    <div class="timeline" id="preview-trayectoria">
                        <!-- Se inyecta cronología aquí -->
                    </div>

                    <h3 class="section-title">Propuestas</h3>
                    <div class="propuestas-grid" id="preview-propuestas">
                        <!-- Se inyectan propuestas aquí -->
                    </div>
                    
                    <div class="fb-widget">
                        <h4 id="preview-fb-titulo">¡Sigue mi campaña!</h4>
                        <p id="preview-fb-desc">Entérate de las últimas noticias</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- 1. LÓGICA DE PESTAÑAS (TABS) ---
    function openTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById('tab-' + tabId).classList.add('active');
        btn.classList.add('active');
    }

    // --- 2. GENERADORES DE FORMULARIOS DINÁMICOS ---
    function addEtiqueta() {
        const container = document.getElementById('etiquetas-list');
        const index = Date.now();
        const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div style="display:flex; gap:10px;"><div style="flex:1;"><label>Icono</label><input type="text" name="etiquetas[${index}][icono]" class="form-control" placeholder="📍"></div><div style="flex:3;"><label>Texto</label><input type="text" name="etiquetas[${index}][texto]" class="form-control" placeholder="Tacna Centro"></div></div></div>`;
        container.insertAdjacentHTML('beforeend', html);
        setTimeout(updateLivePreview, 10);
    }

    function addTrayectoria() {
        const container = document.getElementById('trayectoria-list');
        const index = Date.now();
        const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div class="form-group"><label>Periodo o Año</label><input type="text" name="trayectoria[${index}][periodo]" class="form-control" placeholder="Ej: 2018 - 2022"></div><div class="form-group" style="margin-bottom:0;"><label>Descripción de Logros</label><textarea name="trayectoria[${index}][descripcion]" class="form-control" rows="2" placeholder="Fui responsable de..."></textarea></div><div style="background: rgba(0,0,0,0.3); border-radius:6px; padding: 10px; margin-top:10px; text-align:center;"><span style="font-size:11px; color:#64748b;">📷 Botón de subir galería de fotos (Se habilitará en la Fase 4)</span></div></div>`;
        container.insertAdjacentHTML('beforeend', html);
        setTimeout(updateLivePreview, 10);
    }

    function addPropuesta() {
        const container = document.getElementById('propuestas-list');
        const index = Date.now();
        const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div style="display:flex; gap:10px; margin-bottom:10px;"><div style="flex:1;"><label>Icono</label><input type="text" name="propuestas[${index}][icono]" class="form-control" placeholder="🛡️"></div><div style="flex:3;"><label>Título de la Propuesta</label><input type="text" name="propuestas[${index}][titulo]" class="form-control" placeholder="Ej: Seguridad Ciudadana"></div></div><div class="form-group" style="margin-bottom:0;"><label>Descripción o Desarrollo</label><textarea name="propuestas[${index}][descripcion]" class="form-control" rows="2" placeholder="Implementaremos un sistema de vigilancia..."></textarea></div></div>`;
        container.insertAdjacentHTML('beforeend', html);
        setTimeout(updateLivePreview, 10);
    }

    // --- 3. MAGIA DE LA VISTA PREVIA (LIVE PREVIEW) ---
    function updateLivePreview() {
        const form = document.getElementById('candidato-form');
        
        // Textos Fijos
        document.getElementById('preview-nombre').innerText = form.querySelector('input[name="nombres"]').value || 'Nombre del Candidato';
        document.getElementById('preview-cargo').innerText = form.querySelector('input[name="cargo_flotante"]').value || 'Cargo a postular';
        document.getElementById('preview-frase').innerText = '"' + (form.querySelector('textarea[name="frase_cita"]').value || 'Frase destacada...') + '"';
        document.getElementById('preview-bio').innerText = form.querySelector('textarea[name="biografia"]').value || 'Escribe el resumen biográfico y lo verás aquí...';
        document.getElementById('preview-fb-titulo').innerText = form.querySelector('input[name="fb_titulo"]').value || '¡Sigue mi campaña!';
        document.getElementById('preview-fb-desc').innerText = form.querySelector('input[name="fb_descripcion"]').value || 'Entérate de las últimas noticias.';
        
        // Etiquetas
        const tagsCont = document.getElementById('preview-etiquetas');
        tagsCont.innerHTML = '';
        const iIco = form.querySelectorAll('input[name^="etiquetas"][name$="[icono]"]');
        const iTex = form.querySelectorAll('input[name^="etiquetas"][name$="[texto]"]');
        iIco.forEach((input, i) => {
            if(input.value || iTex[i].value) tagsCont.innerHTML += `<div class="preview-tag">${input.value} ${iTex[i].value}</div>`;
        });

        // Trayectoria
        const trayCont = document.getElementById('preview-trayectoria');
        trayCont.innerHTML = '';
        const tPer = form.querySelectorAll('input[name^="trayectoria"][name$="[periodo]"]');
        const tDes = form.querySelectorAll('textarea[name^="trayectoria"][name$="[descripcion]"]');
        tPer.forEach((input, i) => {
            if(input.value || tDes[i].value) trayCont.innerHTML += `<div class="timeline-item"><div class="timeline-period">${input.value || 'Año'}</div><p class="timeline-desc">${tDes[i].value || '...'}</p></div>`;
        });
        if(trayCont.innerHTML === '') trayCont.innerHTML = '<div class="timeline-item"><div class="timeline-period">Año</div><p class="timeline-desc">Agrega trayectoria en la pestaña de la izquierda...</p></div>';

        // Propuestas
        const propCont = document.getElementById('preview-propuestas');
        propCont.innerHTML = '';
        const pIco = form.querySelectorAll('input[name^="propuestas"][name$="[icono]"]');
        const pTit = form.querySelectorAll('input[name^="propuestas"][name$="[titulo]"]');
        const pDes = form.querySelectorAll('textarea[name^="propuestas"][name$="[descripcion]"]');
        pIco.forEach((input, i) => {
            if(input.value || pTit[i].value || pDes[i].value) propCont.innerHTML += `<div class="prop-card"><div class="prop-icon">${input.value || '✨'}</div><div class="prop-content"><h4>${pTit[i].value || 'Propuesta'}</h4><p>${pDes[i].value || '...'}</p></div></div>`;
        });
        if(propCont.innerHTML === '') propCont.innerHTML = '<div class="prop-card"><div class="prop-icon">🛡️</div><div class="prop-content"><h4>Propuesta</h4><p>Agrega propuestas en la pestaña de la izquierda...</p></div></div>';
    }

    // Escuchar cada tecla pulsada para actualizar todo al instante
    document.getElementById('candidato-form').addEventListener('input', updateLivePreview);

    // Iniciar con campos vacíos de ejemplo si es nuevo
    window.onload = function() {
        if (!<?= $id_candidato ?>) {
            addEtiqueta(); addTrayectoria(); addPropuesta();
        }
        updateLivePreview(); // Primer render
    };
</script>
</body>
</html>