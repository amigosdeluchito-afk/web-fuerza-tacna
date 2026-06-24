<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();
$id_candidato = (int)($_GET['id'] ?? 0);

// Obtener lista para el selector
$stmt = $db->query("SELECT id, nombres FROM panel_candidatos ORDER BY orden ASC, id DESC");
$candidatos_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        
        /* --- VISTA PREVIA 100% IDÉNTICA (Extraída de main.js) --- */
        .candidato-content { display: flex; flex-direction: column; background: #801039; border-radius: 2rem; padding: 3.5rem; border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 20px 60px rgba(0,0,0,0.7); font-family: system-ui, sans-serif; }
        .candidate-top-row { display: flex; gap: 3.5rem; width: 100%; align-items: flex-start; }
        .candidate-bottom-row { width: 100%; margin-top: 3.5rem; padding-top: 3.5rem; border-top: 1px solid rgba(255, 255, 255, 0.08); }
        
        @property --gradient-angle { syntax: "<angle>"; initial-value: 0deg; inherits: false; }
        @keyframes border-rotate { 0% { --gradient-angle: 0deg; } 100% { --gradient-angle: 360deg; } }
        
        .candidato-photo-wrapper { position: relative; width: 35%; flex-shrink: 0; perspective: 1000px; }
        .photo-glow { position: absolute; top: 10%; left: 10%; width: 80%; height: 80%; background: #ffc300; filter: blur(70px); opacity: 0.15; z-index: 0; border-radius: 50%; }
        .candidato-photo { position: relative; z-index: 1; width: 100%; border-radius: 1.5rem; box-shadow: 0 15px 40px rgba(0,0,0,0.5); border: 4px solid transparent; background-image: linear-gradient(#801039, #801039), conic-gradient(from var(--gradient-angle, 0deg), #801039 0%, #ffc300 20%, #fff 25%, #ffc300 30%, #801039 50%, #801039 50%, #ffc300 70%, #fff 75%, #ffc300 80%, #801039 100%); background-clip: padding-box, border-box; background-origin: padding-box, border-box; animation: border-rotate 4s linear infinite; aspect-ratio: 3/4; }
        .candidato-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top center; border-radius: calc(1.5rem - 4px); display: block; }
        .photo-badge { position: absolute; top: 1.5rem; left: -1rem; background: #ffc300; color: #801039; font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: 8px; z-index: 3; box-shadow: 0 6px 20px rgba(0,0,0,0.4); text-transform: uppercase; transform: rotate(-4deg); border: 2px solid #fff; }
        
        .candidate-top-info { flex: 1; color: #fff; display: flex; flex-direction: column; justify-content: flex-start; }
        .candidate-top-info h2 { font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; font-size: clamp(2.5rem, 4vw, 4rem); color: #ffc300; text-transform: uppercase; margin: 0 0 1rem 0; line-height: 1.1; }
        
        .candidate-badges { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .badge-tag { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,195,0,0.4); color: #ffc300; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; text-transform: uppercase; font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; letter-spacing: 1px; }
        
        .candidate-quote { position: relative; border-left: 2px solid #ffc300; padding: 2rem 2.5rem 2rem 4rem; color: #fff; margin: 2.5rem 0; font-size: 1.4rem; line-height: 1.6; background: linear-gradient(90deg, rgba(255,195,0,0.15), transparent); border-radius: 0 1.5rem 1.5rem 0; }
        .candidate-quote::before { content: '"'; position: absolute; left: 1rem; top: -0.5rem; font-family: Georgia, serif; font-size: 6rem; color: rgba(255, 195, 0, 0.4); line-height: 1; }
        .candidate-quote p { font-style: italic; margin: 0; font-weight: 300; }
        
        .info-block { margin-bottom: 4rem; }
        .block-title { font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; font-size: 1.2rem; color: #ffc300; margin-bottom: 1.8rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; }
        
        .proposals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .proposal-card { background: rgba(255,255,255,0.02); padding: 2rem; border-radius: 1.2rem; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; }
        .proposal-icon { font-size: 2.8rem; margin-bottom: 1.5rem; }
        .proposal-card h6 { color: #ffc300; font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; font-size: 1.1rem; margin: 0 0 1rem 0; text-transform: uppercase; }
        .proposal-card p { font-size: 0.95rem; margin: 0; line-height: 1.7; color: #bbb; }
        
        .timeline { border-left: 2px solid rgba(255,195,0,0.2); padding-left: 2.5rem; margin-left: 1rem; display: flex; flex-direction: column; }
        .timeline-item { position: relative; padding-bottom: 2.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .timeline-item::before { content: ''; position: absolute; left: -3.05rem; top: 0.2rem; width: 18px; height: 18px; background: #801039; border: 4px solid #ffc300; border-radius: 50%; box-shadow: 0 0 15px rgba(255,195,0,0.4); }
        .timeline-year { color: #ffc300; font-weight: 900; font-size: 1.25rem; margin-bottom: 0.6rem; font-family: 'Arial Black', Arial, sans-serif; letter-spacing: 1px; }
        .timeline-text { color: #bbb; line-height: 1.8; font-size: 1.05rem; margin: 0; }
        
        .facebook-layout-grid { display: flex; flex-direction: column; gap: 1rem; background: rgba(255,255,255,0.02); padding: 2.5rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.05); }
        .facebook-text h3 { color: #ffc300; font-family: 'Arial Black', Arial, sans-serif; font-weight: 900; font-size: 1.6rem; margin: 0; text-transform: uppercase; line-height: 1.1; }
        .facebook-text p { color: #bbb; font-size: 1.05rem; line-height: 1.6; margin: 0; }
        
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
        .proposal-top-row { display: grid; grid-template-columns: 150px 1fr; gap: 12px; margin-bottom: 12px; align-items: end; }
        .icon-field { display: flex; gap: 8px; align-items: stretch; }
        .icon-field .form-control { width: 72px; height: 78px; text-align: center; font-size: 28px; flex-shrink: 0; padding: 0; }
        .icon-picker-btn { width: 54px; border: 1px solid #3b82f6; background: rgba(59,130,246,0.12); color: #93c5fd; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 11px; line-height: 1.1; }
        .icon-picker-btn:hover { background: rgba(59,130,246,0.22); color: #dbeafe; }
        .icon-palette { display: none; grid-template-columns: repeat(7, 42px); gap: 8px; margin-top: 10px; padding: 10px; border: 1px solid #334155; border-radius: 8px; background: #020617; position: absolute; z-index: 20; box-shadow: 0 18px 45px rgba(0,0,0,0.45); }
        .icon-palette.open { display: grid; }
        .icon-option { height: 42px; border: 1px solid #1e293b; border-radius: 8px; background: #0f172a; cursor: pointer; font-size: 22px; transition: 0.15s; }
        .icon-option:hover { border-color: #ffc300; transform: translateY(-2px); background: rgba(255,195,0,0.12); }
        .proposal-description-input { min-height: 160px; line-height: 1.55; resize: vertical; }
        @media (max-width: 900px) { .proposal-top-row { grid-template-columns: 1fr; } .icon-palette { grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); position: static; } }
        
        .btn-add { background: transparent; border: 1px dashed #3b82f6; color: #3b82f6; padding: 12px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 5px; font-size: 13px; transition: 0.2s; }
        .btn-add:hover { background: rgba(59,130,246,0.1); }
        
        .btn-save { background: #10b981; color: white; border: none; padding: 15px 20px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; transition: 0.2s; }
        .btn-save:hover { background: #059669; }

        /* Estilos del Menú Desplegable */
        .dropdown { position: relative; display: inline-block; margin-right: 16px; }
        .dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 15px; }
        .dropdown .dropbtn { background: transparent; border: none; color: #9ca3af; font-size: 14px; cursor: pointer; font-family: inherit; padding: 0; display: flex; align-items: center; outline: none; }
        .dropdown .dropbtn.active { color: #ffffff; font-weight: 600; }
        .dropdown:hover .dropbtn { color: #e5e7eb; }
        .dropdown-content { display: none; position: absolute; background-color: #0f172a; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5); z-index: 1; border-radius: 8px; border: 1px solid #1e293b; top: 100%; left: 0; padding: 8px 0; margin-top: 10px; }
        .dropdown-content a { color: #9ca3af !important; padding: 8px 16px !important; text-decoration: none; display: block; margin: 0 !important; font-size: 13px !important; }
        .dropdown-content a:hover { background-color: #1e293b; color: #fff !important; }
        .dropdown-content a.active { color: #3b82f6 !important; background-color: rgba(59,130,246,0.1); font-weight: 600; }
        .dropdown:hover .dropdown-content { display: block; }
    </style>
</head>
<body>

<header class="app-header">
  <style>.nav-scroll::-webkit-scrollbar { height: 4px; } .nav-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }</style>
  <nav class="nav-scroll" style="display:flex; align-items:center; overflow-x:auto; white-space:nowrap; width:100%; margin-right:15px; scrollbar-width:thin; scrollbar-color:#334155 transparent; padding-bottom: 4px;">
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">📷 Fotos</a>
    <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">➕ Agregar Obra</a>
    <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">✏️ Editar Obra</a>
    <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">👁️ Visibilidad</a>
    <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">🗂️ Segmentos</a>
    <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">⏳ Cronología</a>
    <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">👥 Candidatos</a>
    <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">🧠 Cerebro IA</a>
    <a href="ia_cerebro_obras.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_cerebro_obras.php' ? 'active' : '' ?>">🏗️ Obras IA</a>
    <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">📚 Base IA</a>
    <a href="ia_fuentes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_fuentes.php' ? 'active' : '' ?>">🔗 Fuentes IA</a>
    <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">📊 Stats IA</a>
        <a href="panel-juegos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'panel-juegos.php' ? 'active' : '' ?>">🎮 Panel de Juegos</a>
        <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">📍 Gestor Mapa</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
    <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
    <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php">Salir</a>
  </div>
</header>

<div class="split-layout">
    <!-- LADO IZQUIERDO: CONTROLES DEL EDITOR -->
    <div class="editor-left">
        <!-- NUEVO: Selector de Candidatos -->
        <div style="padding: 15px; background: #0b1020; border-bottom: 1px solid #1e293b;">
            <label style="color: #94a3b8; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; display: block;">👥 Seleccionar Candidato:</label>
            <select class="form-control" style="border-color: #3b82f6; background: #020617; color: #60a5fa; font-weight: bold;" onchange="if(this.value !== '') window.location.href='editar_candidato.php?id='+this.value;">
                <option value="0">➕ CREAR NUEVO CANDIDATO...</option>
                <?php foreach($candidatos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $id_candidato == $c['id'] ? 'selected' : '' ?>>
                        ✏️ Editar: <?= htmlspecialchars($c['nombres']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tabs-header">
            <button type="button" class="tab-btn active" onclick="openTab('perfil', this)">👤 Perfil</button>
            <button type="button" class="tab-btn" onclick="openTab('etiquetas', this)">🏷️ Etiquetas</button>
            <button type="button" class="tab-btn" onclick="openTab('trayectoria', this)">⏳ Trayectoria</button>
            <button type="button" class="tab-btn" onclick="openTab('propuestas', this)">🚀 Propuestas</button>
        </div>
        
        <form id="candidato-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <input type="hidden" name="estado" value="1">
            <!-- PESTAÑA 1: PERFIL PRINCIPAL -->
            <div id="tab-perfil" class="tab-content active">
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1; background: rgba(59,130,246,0.1); padding: 15px; border-radius: 8px; border: 1px dashed #3b82f6; margin-bottom: 0;">
                        <label style="color:#93c5fd;">📸 Foto Normal</label>
                        <input type="file" name="foto_perfil" id="input_foto" class="form-control" accept="image/*" style="border: none; background: transparent; padding: 0;">
                    </div>
                    <div class="form-group" style="flex: 1; background: rgba(16,185,129,0.1); padding: 15px; border-radius: 8px; border: 1px dashed #10b981; margin-bottom: 0;">
                        <label style="color:#6ee7b7;">✨ Foto Hover</label>
                        <input type="file" name="foto_portada" id="input_foto_hover" class="form-control" accept="image/*" style="border: none; background: transparent; padding: 0;">
                    </div>
                </div>
                <small style="color:#64748b; display: block; margin-top: -10px; margin-bottom: 15px;">Sube las dos fotos del carrusel. La Hover aparece al pasar el ratón.</small>
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
    <div class="editor-right" style="overflow-y: auto; padding: 40px 20px; align-items: flex-start; background: #000;">
        <!-- Zoom al 75% para que encaje perfectamente como si fuera un monitor gigante -->
        <div style="width: 100%; max-width: 950px; transform-origin: top center; zoom: 0.75; margin: 0 auto;">
            
            <div class="candidato-content">
                <div class="candidate-top-row">
                    <div class="candidato-photo-wrapper">
                        <div class="photo-glow"></div>
                        <div class="photo-badge" id="preview-cargo">Candidato a Alcalde</div>
                        <div class="candidato-photo">
                            <img src="../img/logo.svg" id="preview-foto" alt="Foto">
                        </div>
                    </div>
                    <div class="candidate-top-info">
                        <h2 id="preview-nombre">Nombre del Candidato</h2>
                        <div class="candidate-badges" id="preview-etiquetas"></div>
                        <div class="candidate-quote">
                            <p id="preview-frase">"Escribe una frase destacada para verla aquí..."</p>
                        </div>
                        <p id="preview-bio" style="color: #ddd; max-width: 85ch; line-height: 1.5; font-size: 0.95rem;">Resumen biográfico aparecerá aquí...</p>
                    </div>
                </div>

                <div class="candidate-bottom-row">
                    <div class="info-block">
                        <div class="block-title">⏱️ Trayectoria Profesional</div>
                        <div class="timeline" id="preview-trayectoria"></div>
                    </div>
                    <div class="info-block">
                        <div class="block-title">🚀 Ejes de Propuesta</div>
                        <div class="proposals-grid" id="preview-propuestas"></div>
                    </div>
                    <div class="info-block facebook-layout-grid">
                        <div class="facebook-text">
                            <h3 id="preview-fb-titulo">¡Sigue mi campaña!</h3>
                            <p id="preview-fb-desc">Entérate de las últimas noticias</p>
                        </div>
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

    // --- 2.5 LÓGICA DE SUBIDA DE FOTO EN VIVO ---
    const propuestaIconos = ['🛡️','👮','📹','🏥','🩺','💊','🏫','🎓','📚','💡','💼','🏗️','🚧','🚰','🌱','🌳','⚽','🏠','🚍','🛣️','👵','👶','🤝','📈','🧹','♻️','🔥','⭐'];

    function iconPaletteHtml() {
        return `<div class="icon-palette">${propuestaIconos.map(icon => `<button type="button" class="icon-option" onclick="chooseProposalIcon(this, '${icon}')" title="${icon}">${icon}</button>`).join('')}</div>`;
    }

    function toggleProposalIconPicker(btn) {
        const palette = btn.closest('.form-group').querySelector('.icon-palette');
        if (palette) palette.classList.toggle('open');
    }

    function chooseProposalIcon(btn, icon) {
        const group = btn.closest('.form-group');
        const input = group.querySelector('input[type="text"]');
        input.value = icon;
        group.querySelector('.icon-palette').classList.remove('open');
        updateLivePreview();
    }

    function propuestaFormHtml(index, icono = '', titulo = '', descripcion = '') {
        return `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div class="proposal-top-row"><div class="form-group" style="margin-bottom:0;"><label>Icono</label><div class="icon-field"><input type="text" name="propuestas[${index}][icono]" class="form-control" value="${icono}" placeholder="🛡️"><button type="button" class="icon-picker-btn" onclick="toggleProposalIconPicker(this)">Cambiar</button></div>${iconPaletteHtml()}</div><div class="form-group" style="margin-bottom:0;"><label>Título de la Propuesta</label><input type="text" name="propuestas[${index}][titulo]" class="form-control" value="${titulo}" placeholder="Ej: Seguridad Ciudadana"></div></div><div class="form-group" style="margin-bottom:0;"><label>Descripción o Desarrollo</label><textarea name="propuestas[${index}][descripcion]" class="form-control proposal-description-input" rows="6" placeholder="Implementaremos un sistema de vigilancia...">${descripcion}</textarea></div></div>`;
    }

    function upgradeProposalEditors() {
        document.querySelectorAll('#propuestas-list .dynamic-item').forEach(item => {
            if (item.querySelector('.proposal-top-row')) return;
            const iconInput = item.querySelector('input[name$="[icono]"]');
            const titleInput = item.querySelector('input[name$="[titulo]"]');
            const descInput = item.querySelector('textarea[name$="[descripcion]"]');
            if (!iconInput || !titleInput || !descInput) return;
            const match = iconInput.name.match(/propuestas\[([^\]]+)\]/);
            const index = match ? match[1] : Date.now();
            item.outerHTML = propuestaFormHtml(index, iconInput.value, titleInput.value, descInput.value);
        });
    }

    function addPropuesta() {
        const container = document.getElementById('propuestas-list');
        const index = Date.now();
        container.insertAdjacentHTML('beforeend', propuestaFormHtml(index));
        setTimeout(updateLivePreview, 10);
    }

    document.getElementById('input_foto').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Reemplaza la foto al instante en la Vista Previa
                document.getElementById('preview-foto').src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

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
            if(input.value || iTex[i].value) tagsCont.innerHTML += `<span class="badge-tag">${input.value} ${iTex[i].value}</span>`;
        });

        // Trayectoria
        const trayCont = document.getElementById('preview-trayectoria');
        trayCont.innerHTML = '';
        const tPer = form.querySelectorAll('input[name^="trayectoria"][name$="[periodo]"]');
        const tDes = form.querySelectorAll('textarea[name^="trayectoria"][name$="[descripcion]"]');
        tPer.forEach((input, i) => {
            if(input.value || tDes[i].value) trayCont.innerHTML += `<div class="timeline-item"><div class="timeline-year">${input.value || 'Año'}</div><div class="timeline-body"><p class="timeline-text">${tDes[i].value || '...'}</p></div></div>`;
        });
        if(trayCont.innerHTML === '') trayCont.innerHTML = '<div class="timeline-item"><div class="timeline-year">Año</div><div class="timeline-body"><p class="timeline-text">Agrega trayectoria en la pestaña de la izquierda...</p></div></div>';

        // Propuestas
        const propCont = document.getElementById('preview-propuestas');
        propCont.innerHTML = '';
        const pIco = form.querySelectorAll('input[name^="propuestas"][name$="[icono]"]');
        const pTit = form.querySelectorAll('input[name^="propuestas"][name$="[titulo]"]');
        const pDes = form.querySelectorAll('textarea[name^="propuestas"][name$="[descripcion]"]');
        pIco.forEach((input, i) => {
            if(input.value || pTit[i].value || pDes[i].value) propCont.innerHTML += `<div class="proposal-card"><div class="proposal-icon">${input.value || '✨'}</div><h6>${pTit[i].value || 'Propuesta'}</h6><p>${pDes[i].value || '...'}</p></div>`;
        });
        if(propCont.innerHTML === '') propCont.innerHTML = '<div class="proposal-card"><div class="proposal-icon">🛡️</div><h6>Propuesta</h6><p>Agrega propuestas en la pestaña de la izquierda...</p></div>';
    }

    // Escuchar cada tecla pulsada para actualizar todo al instante
    document.getElementById('candidato-form').addEventListener('input', updateLivePreview);

    // --- 4. GUARDAR EN BASE DE DATOS (AJAX) ---
    document.querySelector('.btn-save').addEventListener('click', async function() {
        const form = document.getElementById('candidato-form');
        const btn = this;
        const originalText = btn.innerHTML;
        
        // Validación básica
        if (!form.querySelector('input[name="nombres"]').value.trim()) {
            alert('Por favor, ingresa el nombre del candidato.');
            openTab('perfil', document.querySelector('.tab-btn.active'));
            form.querySelector('input[name="nombres"]').focus();
            return;
        }
        
        btn.innerHTML = '⏳ Guardando en Base de Datos...';
        btn.disabled = true;

        const fd = new FormData(form);
        fd.append('action', 'guardar');
        fd.append('id', '<?= $id_candidato ?>'); 

        try {
            const resp = await fetch('api_candidatos.php', { method: 'POST', body: fd });
            const data = await resp.json();
            
            if (data.ok) {
                alert('✅ ¡Candidato guardado exitosamente!');
                if (data.id && !<?= $id_candidato ?>) {
                    // Si era nuevo, recargamos la página con su nuevo ID para seguir editando
                    window.location.href = 'editar_candidato.php?id=' + data.id;
                }
            } else {
                alert('❌ Error al guardar: ' + data.error);
            }
        } catch (err) {
            alert('❌ Error de conexión con el servidor al guardar.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // --- 5. CARGAR DATOS SI ESTAMOS EDITANDO ---
    window.onload = async function() {
        const id_candidato = <?= $id_candidato ?>;
        if (id_candidato) {
            try {
                const resp = await fetch('api_candidatos.php?action=obtener&id=' + id_candidato);
                const data = await resp.json();
                if (data.ok) {
                    const c = data.candidato;
                    const form = document.getElementById('candidato-form');
                    
                    form.querySelector('input[name="nombres"]').value = c.nombres || '';
                    form.querySelector('input[name="cargo_flotante"]').value = c.cargo_flotante || '';
                    form.querySelector('textarea[name="frase_cita"]').value = c.frase_cita || '';
                    form.querySelector('textarea[name="biografia"]').value = c.biografia || '';
                    form.querySelector('input[name="fb_titulo"]').value = c.fb_titulo || '';
                    form.querySelector('input[name="fb_descripcion"]').value = c.fb_descripcion || '';
                    form.querySelector('input[name="fb_url_perfil"]').value = c.fb_url_perfil || '';
                    
                    // Cargar la foto en la vista previa
                    if (c.foto_perfil) {
                        document.getElementById('preview-foto').src = '../universoobras/IMG/candidatos/' + c.foto_perfil;
                    }

                    // Limpiar y Llenar Listas Dinámicas (Evitando que las comillas rompan el HTML)
                    const esc = (str) => (str||'').replace(/"/g, '&quot;');
                    
                    const cEti = document.getElementById('etiquetas-list'); cEti.innerHTML = '';
                    (c.etiquetas || []).forEach((e, i) => { const idx = 'e'+i; cEti.insertAdjacentHTML('beforeend', `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div style="display:flex; gap:10px;"><div style="flex:1;"><label>Icono</label><input type="text" name="etiquetas[${idx}][icono]" class="form-control" value="${esc(e.icono)}"></div><div style="flex:3;"><label>Texto</label><input type="text" name="etiquetas[${idx}][texto]" class="form-control" value="${esc(e.texto)}"></div></div></div>`); });
                    
                    const cTra = document.getElementById('trayectoria-list'); cTra.innerHTML = '';
                    (c.trayectoria || []).forEach((t, i) => { const idx = 't'+i; cTra.insertAdjacentHTML('beforeend', `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div class="form-group"><label>Periodo o Año</label><input type="text" name="trayectoria[${idx}][periodo]" class="form-control" value="${esc(t.periodo)}"></div><div class="form-group" style="margin-bottom:0;"><label>Descripción de Logros</label><textarea name="trayectoria[${idx}][descripcion]" class="form-control" rows="2">${esc(t.descripcion)}</textarea></div></div>`); });

                    const cPro = document.getElementById('propuestas-list'); cPro.innerHTML = '';
                    (c.propuestas || []).forEach((p, i) => { const idx = 'p'+i; cPro.insertAdjacentHTML('beforeend', `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="this.parentElement.remove(); updateLivePreview();" title="Eliminar">X</button><div style="display:flex; gap:10px; margin-bottom:10px;"><div style="flex:1;"><label>Icono</label><input type="text" name="propuestas[${idx}][icono]" class="form-control" value="${esc(p.icono)}"></div><div style="flex:3;"><label>Título de la Propuesta</label><input type="text" name="propuestas[${idx}][titulo]" class="form-control" value="${esc(p.titulo)}"></div></div><div class="form-group" style="margin-bottom:0;"><label>Descripción o Desarrollo</label><textarea name="propuestas[${idx}][descripcion]" class="form-control" rows="2">${esc(p.descripcion)}</textarea></div></div>`); });
                }
            } catch (err) {
                console.error("Error cargando datos del candidato", err);
            }
        } else {
            // Si es nuevo, añadir campos vacíos por defecto
            addEtiqueta(); addTrayectoria(); addPropuesta();
        }
        upgradeProposalEditors();
        updateLivePreview(); // Primer render
    };
</script>
</body>
</html>
