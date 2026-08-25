<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mapa vial para impresion - Panel Admin</title>
    <style>
        body { margin:0; padding:0; font-family:system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .app-header { flex-shrink:0; height:56px; background:#020617; border-bottom:1px solid #111827; display:flex; align-items:center; justify-content:space-between; padding:0 24px; z-index:20; }
        .app-header nav a { color:#9ca3af; margin-right:16px; text-decoration:none; font-size:14px; }
        .app-header nav a.active { color:#ffffff; font-weight:600; }
        .app-header nav a:hover { color:#e5e7eb; }
        .app-header .user { font-size:13px; color:#9ca3af; white-space:nowrap; }
        .nav-scroll::-webkit-scrollbar { height:4px; }
        .nav-scroll::-webkit-scrollbar-thumb { background:#334155; border-radius:4px; }
        .main-container { flex:1; min-height:0; display:grid; grid-template-columns:360px minmax(0, 1fr); }
        .controls { background:#0f172a; border-right:1px solid #1e293b; padding:22px; overflow-y:auto; box-sizing:border-box; }
        .preview { min-width:0; min-height:0; background:#111827; display:flex; flex-direction:column; }
        .preview-head { flex-shrink:0; height:48px; border-bottom:1px solid #1f2937; display:flex; align-items:center; justify-content:space-between; padding:0 18px; color:#94a3b8; font-size:13px; box-sizing:border-box; }
        .svg-stage { flex:1; min-height:0; overflow:auto; padding:20px; box-sizing:border-box; }
        .svg-frame { min-height:100%; display:flex; align-items:center; justify-content:center; }
        h1 { margin:0 0 6px; color:#f8fafc; font-size:22px; line-height:1.15; }
        p { margin:0 0 16px; color:#94a3b8; font-size:13px; line-height:1.45; }
        .section-title { margin:18px 0 10px; color:#cbd5e1; text-transform:uppercase; font-size:11px; font-weight:800; letter-spacing:.06em; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .form-group { margin-bottom:12px; }
        .form-group label { display:block; color:#94a3b8; font-size:12px; font-weight:700; margin-bottom:5px; text-transform:uppercase; }
        .form-control { width:100%; box-sizing:border-box; padding:10px; border-radius:6px; border:1px solid #334155; background:#020617; color:#f8fafc; outline:none; font-size:14px; }
        .form-control:focus { border-color:#3b82f6; }
        .btn { width:100%; border:0; border-radius:6px; padding:12px; font-weight:800; cursor:pointer; font-size:14px; transition:.2s; text-decoration:none; text-align:center; box-sizing:border-box; display:inline-block; }
        .btn-primary { background:#10b981; color:#ffffff; }
        .btn-primary:hover { background:#059669; }
        .btn-secondary { background:#334155; color:#ffffff; margin-top:10px; }
        .btn-secondary:hover { background:#475569; }
        .btn:disabled { opacity:.55; cursor:not-allowed; }
        .status { margin-top:14px; padding:12px; border:1px solid #1e293b; background:#020617; border-radius:8px; color:#94a3b8; font-size:12px; line-height:1.5; white-space:pre-line; }
        .status.is-error { border-color:#7f1d1d; color:#fecaca; background:#190b10; }
        .status.is-ok { border-color:#14532d; color:#bbf7d0; background:#06140f; }
        #svgPreview svg { width:min(100%, 1180px); height:auto; background:#f8fafc; border:1px solid #334155; box-shadow:0 20px 50px rgba(0,0,0,.35); }
        .empty-preview { color:#64748b; text-align:center; border:1px dashed #334155; border-radius:10px; padding:40px; max-width:460px; }
    </style>
</head>
<body>
    <header class="app-header">
      <nav class="nav-scroll" style="display:flex; align-items:center; overflow-x:auto; white-space:nowrap; width:100%; margin-right:15px; scrollbar-width:thin; scrollbar-color:#334155 transparent; padding-bottom:4px;">
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Fotos</a>
        <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">Agregar Obra</a>
        <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">Editar Obra</a>
        <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">Visibilidad</a>
        <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">Segmentos</a>
        <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">Cronologia</a>
        <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">Candidatos</a>
        <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">Cerebro IA</a>
        <a href="ia_cerebro_obras.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_cerebro_obras.php' ? 'active' : '' ?>">Obras IA</a>
        <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">Base IA</a>
        <a href="ia_fuentes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_fuentes.php' ? 'active' : '' ?>">Fuentes IA</a>
        <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">Stats IA</a>
        <a href="panel-juegos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'panel-juegos.php' ? 'active' : '' ?>">Panel de Juegos</a>
        <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">Gestor Mapa</a>
        <a href="mapa_vial_impresion.php" class="<?= basename($_SERVER['PHP_SELF']) == 'mapa_vial_impresion.php' ? 'active' : '' ?>">Mapa para impresion</a>
        <?php if (is_admin()): ?>
        <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">Usuarios</a>
        <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">Historial</a>
        <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">Accesos</a>
        <?php endif; ?>
      </nav>
      <div class="user">
        <?= htmlspecialchars(current_user() ?? '') ?> &middot; <form method="POST" action="logout.php" style="display:inline; margin:0;"><?= csrf_field() ?><button type="submit" style="background:none; border:0; padding:0; color:#9ca3af; text-decoration:none; cursor:pointer; font:inherit;">Salir</button></form>
      </div>
    </header>

    <main class="main-container">
        <aside class="controls">
            <h1>Mapa vial para impresion</h1>
            <p>Genera un SVG editable con calles base, tramos destacados y nombres de tramos.</p>
            <a class="btn btn-secondary" href="gestor-cartografico.php" style="margin:0 0 14px;">Volver al Gestor de mapas</a>

            <div class="section-title">BBOX geografico</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="west">West</label>
                    <input id="west" class="form-control" type="number" step="0.000001" value="-70.315">
                </div>
                <div class="form-group">
                    <label for="south">South</label>
                    <input id="south" class="form-control" type="number" step="0.000001" value="-18.055">
                </div>
                <div class="form-group">
                    <label for="east">East</label>
                    <input id="east" class="form-control" type="number" step="0.000001" value="-70.185">
                </div>
                <div class="form-group">
                    <label for="north">North</label>
                    <input id="north" class="form-control" type="number" step="0.000001" value="-17.965">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="zoom">Zoom</label>
                    <input id="zoom" class="form-control" type="number" min="3" max="15" step="1" value="15">
                </div>
                <div class="form-group">
                    <label for="svgWidth">Ancho SVG</label>
                    <input id="svgWidth" class="form-control" type="number" min="600" max="5000" step="100" value="1600">
                </div>
            </div>

            <button id="btnGenerate" class="btn btn-primary" type="button">Generar / actualizar</button>
            <button id="btnDownload" class="btn btn-secondary" type="button" disabled>Descargar SVG</button>

            <div id="status" class="status">Listo para generar.</div>
        </aside>

        <section class="preview">
            <div class="preview-head">
                <strong>Previsualizacion SVG</strong>
                <span id="previewMeta">Sin generar</span>
            </div>
            <div class="svg-stage">
                <div id="svgPreview" class="svg-frame">
                    <div class="empty-preview">Define el BBOX y presiona Generar / actualizar.</div>
                </div>
            </div>
        </section>
    </main>

    <script type="module" src="mapa-vial-impresion.js?v=1"></script>
</body>
</html>
