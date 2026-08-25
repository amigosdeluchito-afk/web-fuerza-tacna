<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Juegos – Universo de Obras</title>
  <style>
    body { background:#050816; color:#fff; font-family:system-ui,sans-serif; margin:0; padding-bottom: 80px; }
    header { padding:16px 24px; border-bottom:1px solid #111827; display:flex; justify-content:space-between; align-items:center; background:#020617; position:sticky; top:0; z-index:100; }
    nav a { color:#9ca3af; margin-right:16px; text-decoration:none; font-size:14px; }
    nav a.active { color:#ffffff; font-weight:600; }
    nav a:hover { color:#e5e7eb; }
    .user { font-size:13px; color:#9ca3af; }
    .user a { color:#9ca3af; text-decoration:none; }
    
    main { padding:20px; max-width:1840px; margin:0 auto; }
    h1 { margin-top:0; font-size: 22px; display: flex; justify-content: space-between; align-items: center; color: #f9fafb; }
    
    .games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
    .game-card { background: #0b1020; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: border-color 0.2s; }
    .game-card:focus-within { border-color: #3b82f6; }
    .game-card[data-game-id="find-luchito"] { grid-column: 1 / -1; }
    
    .game-card-header { background: #0f172a; padding: 12px 16px; font-weight: bold; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 8px; font-size: 15px; color: #e2e8f0; }
    .game-card-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
    .game-card[data-game-id="find-luchito"] .game-card-body { display:grid; grid-template-columns: minmax(260px, 1fr) 110px minmax(280px, 1.1fr) 110px minmax(360px, 1.35fr); gap:10px 12px; align-items:end; padding:12px; }
    .game-card[data-game-id="find-luchito"] .game-card-body > div { min-width:0; }
    .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(1) { grid-column:1; }
    .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(2) { grid-column:2 / span 2; }
    .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(3) { grid-column:4; }
    .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(4) { grid-column:5; }
    .game-card[data-game-id="find-luchito"] .game-card-body > .special-editor { grid-column:1 / -1; align-self:stretch; }
    .game-card[data-game-id="find-luchito"] textarea[data-field="description"] { min-height:46px; height:46px; resize:vertical; }
    .game-card[data-game-id="find-luchito"] .form-row { gap:10px; }
    
    label { font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-bottom: 4px; display: block; }
    input, select, textarea { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #334155; background: #020617; color: #fff; box-sizing: border-box; font-family: inherit; font-size: 13px; outline: none; transition: border-color 0.2s; }
    input:focus, select:focus, textarea:focus { border-color: #3b82f6; }
    
    .form-row { display: flex; gap: 12px; }
    .form-row > div { flex: 1; }
    
    .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; border: none; transition: background 0.2s; text-decoration: none; display: inline-block; }
    .btn-secondary { background: #334155; color: white; }
    .btn-secondary:hover { background: #475569; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-success:disabled { background: #064e3b; color: #94a3b8; cursor: not-allowed; }

    .floating-save { position: fixed; bottom: 30px; right: 30px; padding: 12px 24px; font-size: 15px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 100; transition: transform 0.2s; }
    .floating-save:not(:disabled):hover { transform: translateY(-2px); }
    
    .toast-msg { position: fixed; top: 70px; right: 20px; background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; font-weight: bold; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .toast-msg.show { transform: translateX(0); }
    .toast-msg.error { background: #ef4444; }
    .special-editor { margin-top: 6px; border-top: 1px solid #1e293b; padding-top: 14px; }
    .game-card[data-game-id="find-luchito"] .special-editor { margin-top:4px; padding-top:10px; }
    .special-editor h3 { margin: 0 0 6px; font-size: 14px; color: #f8fafc; }
    .editor-help { margin: 0 0 10px; color: #94a3b8; font-size: 12px; line-height: 1.35; }
    .level-tabs { display:flex; gap:8px; overflow-x:auto; padding:2px 0 8px; margin-bottom: 6px; }
    .level-tab { min-width:70px; padding:7px 9px; border-radius:8px; border:1px solid #334155; background:#020617; color:#cbd5e1; cursor:pointer; font-weight:700; font-size:12px; }
    .level-tab.active { background:#2563eb; border-color:#60a5fa; color:#fff; }
    .level-tab.empty { opacity:.72; }
    .level-list { display: flex; flex-direction: column; gap: 10px; }
    .level-card { border: 1px solid #334155; background: #020617; border-radius: 10px; padding: 10px; }
    .level-card:not(.active) { display:none; }
    .level-card-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; color: #e2e8f0; font-weight: 700; font-size: 13px; }
    .level-admin-grid { display:grid; grid-template-columns:300px 390px minmax(640px, 1fr); gap:10px; align-items:start; }
    .level-section { background:#0b1224; border:1px solid #1e293b; border-radius:10px; padding:10px; min-width:0; }
    .level-section-title { margin:0 0 8px; color:#e2e8f0; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .level-section .form-row { flex-wrap:wrap; }
    .level-section .form-row > div { min-width:120px; }
    .level-section textarea { min-height:66px; resize:vertical; }
    .btn-danger { background: #7f1d1d; color: #fecaca; }
    .btn-danger:hover { background: #991b1b; }
    .btn-small { padding: 6px 10px; font-size: 12px; }
    .level-preview { margin-top: 8px; border-radius: 8px; overflow: hidden; border: 0; outline: 1px solid #1e293b; background: #0f172a; position: relative; display: none; max-height:460px; }
    .level-preview img { display: block; width: 100%; height: auto; }
    .level-target-area { position: absolute; border: 3px solid #facc15; background: rgba(250,204,21,0.18); box-shadow: 0 0 14px rgba(250,204,21,0.75); pointer-events: none; }
    .level-target-area.circle { border-radius: 999px; transform: translate(-50%, -50%); }
    .level-target-area.rect { border-radius: 6px; }
    .level-draw-hint { color:#94a3b8; font-size:11px; margin-top:6px; }
    .upload-row { display:flex; gap:8px; align-items:center; }
    .upload-row input[type="file"] { padding: 7px; }
    .level-section .upload-row { gap:6px; }
    .level-section .upload-row input[type="file"] { min-width:0; font-size:12px; }
    .level-section .upload-row .btn { flex:0 0 auto; }
    .upload-status { color:#94a3b8; font-size:12px; min-height:18px; }
    .game-card[data-game-id="find-luchito"] [data-difficulty-row] { display:none; }
    .area-editor-modal { position:fixed; inset:0; background:rgba(2,6,23,.86); z-index:2000; display:none; align-items:center; justify-content:center; padding:22px; }
    .area-editor-modal.open { display:flex; }
    .area-editor-panel { width:min(1500px, 98vw); max-height:94vh; background:#0b1020; border:1px solid #334155; border-radius:14px; box-shadow:0 25px 70px rgba(0,0,0,.55); display:flex; flex-direction:column; overflow:hidden; }
    .area-editor-head { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:14px 16px; border-bottom:1px solid #1e293b; }
    .area-editor-title { margin:0; font-size:16px; color:#f8fafc; }
    .area-editor-body { padding:12px; overflow:auto; }
    .area-editor-canvas { position:relative; width:100%; max-width:none; margin:0 auto; background:#020617; border:0; outline:1px solid #334155; border-radius:10px; overflow:hidden; cursor:crosshair; touch-action:none; }
    .area-editor-canvas img { display:block; width:100%; height:auto; user-select:none; -webkit-user-drag:none; }
    .area-editor-foot { padding:12px 16px; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; display:flex; justify-content:space-between; gap:12px; align-items:center; }
    @media (max-width: 1180px) {
      .game-card[data-game-id="find-luchito"] .game-card-body { grid-template-columns:1fr 120px 1fr; }
      .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(1) { grid-column:1; }
      .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(2) { grid-column:2 / span 2; }
      .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(3) { grid-column:1; }
      .game-card[data-game-id="find-luchito"] .game-card-body > div:nth-child(4) { grid-column:2 / span 2; }
      .level-admin-grid { grid-template-columns:1fr 1fr; }
      .level-section-preview { grid-column:1 / -1; }
    }
    @media (max-width: 720px) {
      main { padding:16px; }
      .level-admin-grid { grid-template-columns:1fr; }
      .form-row { flex-direction:column; }
    }
  </style>
</head>
<body>

<header>
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
    <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">📍 Gestor Mapa</a>
    <a href="panel-juegos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'panel-juegos.php' ? 'active' : '' ?>">🎮 Panel de Juegos</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
    <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
    <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <form method="POST" action="logout.php" style="display:inline; margin:0;"><?= csrf_field() ?><button type="submit" style="background:none; border:0; padding:0; color:inherit; cursor:pointer; font:inherit;">Salir</button></form>
  </div>
</header>

<main>
  <h1>
    <span>🎮 Configuración de Juegos</span>
    <a href="index.php" class="btn btn-secondary">Volver al Panel</a>
  </h1>
  
  <p style="color: #94a3b8; font-size: 14px; margin-top: -10px;">
    Aquí puedes gestionar la Zona Arcade pública. Modifica el estado, orden y descripciones.
  </p>

  <div id="games-grid" class="games-grid">
    <p style="color: #9ca3af; padding: 20px;">Cargando juegos...</p>
  </div>
</main>

<button id="save-all-btn" class="btn btn-success floating-save" disabled>
  💾 Guardar Cambios
</button>

<div id="toast-msg" class="toast-msg">Mensaje</div>

<script>
  window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>
<script src="panel-juegos.js?v=9"></script>

<script>
    // Ajuste de scroll horizontal de nav activo
    document.addEventListener("DOMContentLoaded", function() {
        const activeItem = document.querySelector('.nav-scroll a.active');
        if(activeItem) activeItem.scrollIntoView({ behavior: 'auto', inline: 'center', block: 'nearest' });
    });
</script>
</body>
</html>
