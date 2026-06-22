<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Cartográfico – Panel Admin</title>
    <link href="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css" rel="stylesheet" />
    <style>
        body { margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #fff; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .app-header { flex-shrink: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        
        .main-container { flex: 1; display: flex; position: relative; height: calc(100vh - 56px); min-height: 0; box-sizing: border-box; }
        #map { flex: 1; background: #0f172a; }
        
        .instrucciones { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(15, 23, 42, 0.9); padding: 10px 20px; border-radius: 8px; z-index: 10; font-size: 14px; border: 1px solid #3b82f6; color: #93c5fd; pointer-events: none; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        
        .panel-formulario { width: 350px; background: #0f172a; border-left: 1px solid #1e293b; padding: 25px; display: none; flex-direction: column; overflow-y: auto; box-shadow: -5px 0 25px rgba(0,0,0,0.5); z-index: 20; }
        .panel-formulario.active { display: flex; }
        
        .panel-lista { width: 350px; background: #0f172a; border-left: 1px solid #1e293b; display: none; box-shadow: -5px 0 25px rgba(0,0,0,0.5); z-index: 20; height: 100%; max-height: 100%; box-sizing: border-box; overflow: hidden; }
        .panel-lista.active { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; }
        
        .rv-list-header { box-sizing: border-box; padding: 25px 25px 0 25px; }
        .rv-list-scroll { min-height: 0; overflow-y: auto; overflow-x: hidden; box-sizing: border-box; padding: 20px 25px; }
        .rv-list-footer { box-sizing: border-box; padding: 15px 25px 25px 25px; background: #0f172a; border-top: 1px solid #1e293b; z-index: 5; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .form-control { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #334155; background: #020617; color: #fff; box-sizing: border-box; font-size: 14px; outline: none; }
        .form-control:focus { border-color: #3b82f6; }
        .form-control[readonly] { color: #3b82f6; font-family: monospace; font-weight: bold; }
        
        .btn { padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 14px; transition: 0.2s; }
        .btn-primary { background: #10b981; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-secondary { background: #334155; color: white; margin-top: 10px; }
        .btn-secondary:hover { background: #475569; }
        
        /* DASHBOARD RESUMEN VIAS (RV4-B) */
        .rv-summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 12px; }
        .rv-sum-card { background: rgba(15, 23, 42, 0.6); border: 1px solid #1e293b; border-radius: 6px; padding: 8px 4px; text-align: center; display: flex; flex-direction: column; justify-content: center; }
        .rv-sum-val { font-size: 16px; font-weight: 900; color: #f8fafc; line-height: 1; margin-bottom: 2px; }
        .rv-sum-lbl { font-size: 8.5px; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
        .rv-sum-card.c-blue { border-bottom-color: rgba(59, 130, 246, 0.5); }
        .rv-sum-card.c-blue .rv-sum-val { color: #60a5fa; }
        .rv-sum-card.c-green { border-bottom-color: rgba(16, 185, 129, 0.5); }
        .rv-sum-card.c-green .rv-sum-val { color: #10b981; }
        .rv-sum-card.c-red { border-bottom-color: rgba(239, 68, 68, 0.5); }
        .rv-sum-card.c-red .rv-sum-val { color: #ef4444; }
        .rv-sum-card.c-orange { border-bottom-color: rgba(245, 158, 11, 0.5); }
        .rv-sum-card.c-orange .rv-sum-val { color: #f59e0b; }
        .rv-sum-card.c-purple { border-bottom-color: rgba(168, 85, 247, 0.5); }
        .rv-sum-card.c-purple .rv-sum-val { color: #a855f7; }
        
        /* TOAST NOTIFICATIONS (RV4-A) */
        .toast-container { position: fixed; top: 70px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast { min-width: 250px; max-width: 350px; background: #1e293b; color: #fff; padding: 12px 16px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; align-items: flex-start; gap: 10px; pointer-events: auto; animation: toast-in 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); border-left: 4px solid #3b82f6; }
        .toast.success { border-left-color: #10b981; }
        .toast.error { border-left-color: #ef4444; }
        .toast.warning { border-left-color: #f59e0b; }
        .toast.info { border-left-color: #3b82f6; }
        .toast-icon { font-size: 18px; line-height: 1; }
        .toast-content { flex: 1; font-size: 13px; line-height: 1.4; }
        .toast-close { background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 16px; padding: 0; line-height: 1; }
        .toast-close:hover { color: #fff; }
        .toast.hiding { animation: toast-out 0.3s forwards; }
        @keyframes toast-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toast-out { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
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
        <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af; text-decoration:none;">Salir</a>
      </div>
    </header>
    
    <div class="main-container">
        <!-- Control de Modos (Hitos vs Vías) -->
        <div style="position:absolute; top:20px; left:20px; z-index:10; display:flex; gap:5px; background:#0f172a; padding:5px; border-radius:8px; border:1px solid #334155; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
            <button id="btnModeHitos" class="btn btn-primary" style="padding:6px 12px; margin:0;" onclick="setMode('hitos')">📍 Hitos</button>
            <button id="btnModeVias" class="btn" style="padding:6px 12px; margin:0; background:transparent; color:#94a3b8;" onclick="setMode('vias')">🛣️ Vías</button>
        </div>

        <div class="instrucciones">📍 Haz clic en cualquier lugar de Tacna para anclar un nuevo Titán</div>
        <button id="btnVerLista" onclick="abrirListaActual()" style="position:absolute; top: 20px; right: 20px; z-index: 10; background: #0f172a; border: 1px solid #3b82f6; color: #93c5fd; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: 0.2s;">📋 Ver Lista de Puntos</button>
        <button id="btnVistaPublica" onclick="abrirConfigPublica()" style="position:absolute; top: 68px; right: 20px; z-index: 10; background: #0f172a; border: 1px solid #a855f7; color: #d8b4fe; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: 0.2s;">Vista Publica</button>
        <div id="map"></div>

        <div class="panel-lista" id="panelConfigPublica">
            <div class="rv-list-header">
                <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">Vista publica Red Vial</h3>
                <p style="color:#94a3b8; font-size:12px; line-height:1.45; margin-top:0;">Define lo que vera el ciudadano cuando recargue el mapa publico.</p>
            </div>
            <div class="rv-list-scroll">
                <div class="form-group">
                    <label>Perfil inicial</label>
                    <select id="rvPublicProfile" class="form-control">
                        <option value="ciudadano">Ciudadano</option>
                        <option value="tecnico">Tecnico</option>
                        <option value="impacto">Impacto</option>
                    </select>
                </div>

                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin: 16px 0 8px;">Vista inicial</div>
                <div id="rvPublicInitialView" style="display:grid; gap:8px;"></div>
                <button type="button" class="btn btn-secondary" style="margin-top:8px;" onclick="capturarVistaPublicaActual()">Usar vista actual del mapa</button>

                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin: 16px 0 8px;">Capas visibles al cargar</div>
                <div id="rvPublicLayers" style="display:grid; gap:8px;"></div>

                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin: 18px 0 8px;">Estilo de vias</div>
                <div id="rvPublicStyle" style="display:grid; gap:10px;"></div>
            </div>
            <div class="rv-list-footer">
                <button type="button" class="btn btn-primary" onclick="guardarConfigPublica()">Guardar vista publica</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarConfigPublica()">Volver al Mapa</button>
            </div>
        </div>
        
        <!-- Panel Flotante de Dibujo RV -->
        <div id="rvDrawPanel" style="display:none; position:absolute; bottom:30px; left:50%; transform:translateX(-50%); z-index:10; background:rgba(15,23,42,0.9); padding:10px 15px; border-radius:8px; border:1px solid #3b82f6; box-shadow:0 4px 15px rgba(0,0,0,0.5); gap:10px; align-items:center;">
            <span style="color:#93c5fd; font-size:13px; font-weight:bold; margin-right:10px; min-width:60px;" id="rvDrawCount">0 puntos</span>
            <button class="btn btn-secondary" style="margin:0; padding:6px 12px; background:#475569;" onclick="rvUndo()">↩️ Deshacer</button>
            <button class="btn btn-secondary" style="margin:0; padding:6px 12px; background:#ef4444;" onclick="rvCancel()">❌ Cancelar</button>
            <button class="btn btn-primary" id="btnRvFinish" style="margin:0; padding:6px 12px; background:#10b981;" onclick="rvFinish()" disabled>✅ Finalizar Tramo</button>
        </div>

        <div class="panel-formulario" id="panelFormulario">
            <h3 id="formTitle" style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">➕ Agregar Referencia</h3>
            <p id="formSub" style="font-size: 12px; color: #38bdf8;">Las coordenadas han sido capturadas automáticamente desde el mapa.</p>
            
            <form id="refForm">
                <input type="hidden" id="refId" value="">
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Latitud</label>
                        <input type="text" id="inpLat" class="form-control" readonly>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Longitud</label>
                        <input type="text" id="inpLng" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nombre Oficial (Completo)</label>
                    <input type="text" id="inpNombre" class="form-control" required placeholder="Ej: Hospital Regional Hipólito Unanue">
                </div>
                <div class="form-group">
                    <label>Nombre Corto (Visual)</label>
                    <input type="text" id="inpCorto" class="form-control" required placeholder="Ej: Hosp. Unanue">
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select id="inpCat" class="form-control" required>
                        <option value="General">📍 General</option>
                        <option value="Salud">🏥 Salud</option>
                        <option value="Educación">🎓 Educación</option>
                        <option value="Gobierno">🏛️ Gobierno</option>
                        <option value="Deporte">⚽ Deporte</option>
                        <option value="Transporte">🚌 Transporte</option>
                        <option value="Mercado">🛒 Mercado</option>
                        <option value="Parque">🌳 Parque / Plaza</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Icono</label>
                    <select id="inpIcon" class="form-control">
                        <option value="hito">📍 Hito General</option>
                        <option value="salud">🏥 Salud</option>
                        <option value="educacion">🎓 Educación Superior</option>
                        <option value="gobierno">🏛️ Gobierno / Cívico</option>
                        <option value="deporte">⚽ Deporte</option>
                        <option value="transporte">🚌 Transporte</option>
                        <option value="comercio">🛒 Comercio / Mercado</option>
                        <option value="parque">🌳 Parque / Plaza</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Zoom Mínimo de Aparición</label>
                    <input type="number" id="inpZoom" class="form-control" value="11" min="8" max="18">
                    <small style="color: #64748b; font-size: 11px;">10 = Muy Lejos, 13 = Cerca, 15 = Barrio</small>
                </div>
                
                <button type="submit" class="btn btn-primary" id="btnGuardar" style="margin-top: 10px;">💾 Guardar Referencia</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarPanel()">❌ Cancelar</button>
            </form>
        </div>
        
        <div class="panel-lista" id="panelLista">
            <div class="rv-list-header">
                <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">📋 Referencias Guardadas</h3>
            </div>
            <div id="listaReferenciasContainer" class="rv-list-scroll"></div>
            <div class="rv-list-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarLista()">❌ Volver al Mapa</button>
            </div>
        </div>
        
        <div class="panel-lista" id="panelListaRV">
            <div class="rv-list-header">
                <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">📋 Vías Guardadas</h3>
                
                <!-- Resumen Dashboard (RV4-B) -->
                <div class="rv-summary-grid">
                    <div class="rv-sum-card c-blue"><span class="rv-sum-val" id="sumRvTotal">0</span><span class="rv-sum-lbl">Total</span></div>
                    <div class="rv-sum-card c-green"><span class="rv-sum-val" id="sumRvActivas">0</span><span class="rv-sum-lbl">Activas</span></div>
                    <div class="rv-sum-card c-red"><span class="rv-sum-val" id="sumRvInactivas">0</span><span class="rv-sum-lbl">Inactivas</span></div>
                    <div class="rv-sum-card c-purple"><span class="rv-sum-val" id="sumRvEntregadas">0</span><span class="rv-sum-lbl">Entregadas</span></div>
                    <div class="rv-sum-card c-green"><span class="rv-sum-val" id="sumRvEjecucion">0</span><span class="rv-sum-lbl">Ejecución</span></div>
                    <div class="rv-sum-card c-orange"><span class="rv-sum-val" id="sumRvOtros">0</span><span class="rv-sum-lbl">Proyectadas</span></div>
                </div>

                <!-- Controles de Búsqueda y Filtros -->
                <div style="margin-bottom: 15px; display: flex; flex-direction: column; gap: 8px;">
                    <input type="text" id="filtroNombreRV" class="form-control" placeholder="🔍 Buscar por nombre..." oninput="renderListaRV()">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <select id="filtroTipoRV" class="form-control" style="padding: 6px; font-size: 12px; flex: 1 1 130px;" onchange="renderListaRV()">
                            <option value="">Todos los tipos</option><option value="Local">Local</option><option value="Provincial">Provincial</option><option value="Regional">Regional</option>
                        </select>
                        <select id="filtroEstadoRV" class="form-control" style="padding: 6px; font-size: 12px; flex: 1 1 130px;" onchange="renderListaRV()">
                            <option value="">Todos los estados</option><option value="En estudios">En estudios</option><option value="Buena Pro">Buena Pro</option><option value="En ejecución">En ejecución</option><option value="Paralizado">Paralizado</option><option value="Transferencia">Transferencia</option><option value="Entregado">Entregado</option>
                        </select>
                        <select id="filtroVisibilidadRV" class="form-control" style="padding: 6px; font-size: 12px; flex: 1 1 130px;" onchange="renderListaRV()">
                            <option value="">Visibilidad (Todas)</option><option value="1">Solo Activas</option><option value="0">Solo Inactivas</option>
                        </select>
                    </div>
                    <div id="contadorRV" style="font-size: 11px; color: #94a3b8; text-align: right;"></div>
                </div>
            </div>

            <div id="listaRvContainer" class="rv-list-scroll"></div>
            
            <div class="rv-list-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarListaRV()">❌ Volver al Mapa</button>
            </div>
        </div>
        
        <!-- NUEVO PANEL: GALERÍA DE FOTOS (RV3-B2-A) -->
        <div class="panel-lista" id="panelGaleriaRV">
            <div class="rv-list-header">
                <h3 style="margin-top: 0; color: #f8fafc; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">📸 Galería de Vía</h3>
                <p id="galeriaTramoNombre" style="color: #93c5fd; font-size: 13px; margin-top: -5px; margin-bottom: 15px; font-weight: bold;"></p>
                
                <form id="formSubirFotoRV" style="background: rgba(59, 130, 246, 0.1); padding: 12px; border-radius: 8px; border: 1px dashed #3b82f6; margin-bottom: 15px;">
                    <label style="font-size: 11px; color: #94a3b8; display: block; margin-bottom: 5px;">Subir Nueva Foto</label>
                    <input type="file" id="rvFotoInput" accept="image/jpeg, image/png, image/webp" class="form-control" style="font-size: 12px; padding: 5px;" required>
                    <select id="rvFotoTipo" class="form-control" style="font-size: 12px; padding: 5px; margin-top: 8px;" required>
                        <option value="galeria">Galería Estándar</option>
                        <option value="portada">Portada (Principal)</option>
                        <option value="antes">Antes</option>
                        <option value="despues">Después</option>
                    </select>
                    <button type="submit" id="btnSubirFotoRV" class="btn btn-primary" style="padding: 6px; font-size: 12px; margin-top: 8px;">Subir Foto</button>
                    <div id="uploadFotoMsg" style="font-size: 11px; margin-top: 5px; font-weight: bold;"></div>
                </form>
            </div>
            
            <div id="listaFotosRVContainer" class="rv-list-scroll"></div>
            
            <div class="rv-list-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarGaleriaRV()">⬅️ Volver a Lista de Vías</button>
            </div>
        </div>
        
        <!-- Panel de Formulario Red Vial -->
        <div class="panel-formulario" id="panelFormularioRV">
            <h3 style="margin-top:0; color:#f8fafc; font-size:18px; border-bottom:1px solid #1e293b; padding-bottom:10px;">🛣️ Guardar Tramo Vial</h3>
            <form id="rvForm">
                <input type="hidden" id="rvId" value="">
                
                <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                    <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">📍 1. Datos Básicos</div>
                    <div class="form-group"><label>Nombre de la Vía</label><input type="text" id="rvNombre" class="form-control" required></div>
                    <div class="form-group"><label>Tipo de Vía</label><select id="rvTipo" class="form-control"><option value="Local">Local</option><option value="Provincial">Provincial</option><option value="Regional">Regional</option></select></div>
                    <div class="form-group"><label>Estado Actual</label><select id="rvEstado" class="form-control"><option value="En estudios">En estudios</option><option value="Buena Pro">Buena Pro</option><option value="En ejecución">En ejecución</option><option value="Paralizado">Paralizado</option><option value="Transferencia">Transferencia</option><option value="Entregado">Entregado</option></select></div>
                    <div class="form-group"><label>Color en Mapa</label><input type="color" id="rvColor" class="form-control" value="#616161" style="padding:0; height:40px;"></div>
                </div>

                <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                    <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">🎯 2. Impacto y Mensaje</div>
                    <div class="form-group"><label>Mensaje Principal (Opcional)</label><input type="text" id="rvMensaje" class="form-control" placeholder="Ej: Más conectados, menos tráfico..."></div>
                    <div class="form-group"><label>Descripción Detallada (Opcional)</label><textarea id="rvDesc" class="form-control" rows="3" placeholder="Contexto de la obra..."></textarea></div>
                    <div class="form-group"><label>Beneficiarios (Opcional)</label><input type="text" id="rvBeneficiarios" class="form-control" placeholder="Ej: Más de 5,000 vecinos"></div>
                </div>

                <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                    <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">🛣️ 3. Alcance del Tramo</div>
                    <div style="display: flex; gap: 8px;">
                        <div class="form-group" style="flex:1;">
                            <label>Distrito</label>
                            <select id="rvDistrito" class="form-control" style="padding: 10px 5px;">
                                <option value="">Otro / Sin especificar</option>
                                <option value="Tacna">Tacna</option>
                                <option value="Alto de la Alianza">Alto de la Alianza</option>
                                <option value="Ciudad Nueva">Ciudad Nueva</option>
                                <option value="Gregorio Albarracín">Gregorio Albarracín</option>
                                <option value="Pocollay">Pocollay</option>
                                <option value="Calana">Calana</option>
                                <option value="Inclán">Inclán</option>
                                <option value="Pachía">Pachía</option>
                                <option value="Palca">Palca</option>
                                <option value="Sama">Sama</option>
                                <option value="La Yarada Los Palos">La Yarada Los Palos</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;"><label>Sector / Zona</label><input type="text" id="rvSector" class="form-control" placeholder="Ej: Viñani"></div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div class="form-group" style="flex:1;"><label>Desde</label><input type="text" id="rvDesde" class="form-control" placeholder="Referencia inicio"></div>
                        <div class="form-group" style="flex:1;"><label>Hasta</label><input type="text" id="rvHasta" class="form-control" placeholder="Referencia fin"></div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div class="form-group" style="flex:2;"><label>Longitud (Num.)</label><input type="number" step="0.01" min="0" id="rvLongitudValor" class="form-control" placeholder="Ej: 850"></div>
                        <div class="form-group" style="flex:1;"><label>Unidad</label><select id="rvLongitudUnidad" class="form-control" style="padding: 10px 5px;"><option value="metros">Metros</option><option value="km">Kilómetros</option><option value="cuadras">Cuadras</option></select></div>
                    </div>
                    <div class="form-group"><label>Equivalencia (Cuadras)</label><input type="number" step="0.1" min="0" id="rvLongitudCuadras" class="form-control" placeholder="Ej: 5"><small style="color:#64748b; font-size:10px;">Opcional: Aprox. cuántas cuadras representa.</small></div>
                    <!-- Campo legacy oculto -->
                    <input type="hidden" id="rvLongitud">
                </div>

                <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                    <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">💰 4. Inversión y Ejecución</div>
                    <div style="display: flex; gap: 8px;">
                        <div class="form-group" style="flex:1;"><label>Avance %</label><input type="number" id="rvAvance" class="form-control" min="0" max="100" placeholder="0-100"></div>
                        <div class="form-group" style="flex:1;"><label>Monto (S/)</label><input type="number" id="rvMonto" class="form-control" step="0.01" placeholder="Ej: 1500000.50"></div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div class="form-group" style="flex:1;"><label>F. Inicio</label><input type="date" id="rvInicio" class="form-control"></div>
                        <div class="form-group" style="flex:1;"><label>F. Entrega</label><input type="date" id="rvEntrega" class="form-control"></div>
                    </div>
                </div>

                <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                    <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">🔄 5. Contexto</div>
                    <div class="form-group"><label>Situación Antes (Opcional)</label><textarea id="rvAntes" class="form-control" rows="2" placeholder="Ej: Pista con baches y sin veredas..."></textarea></div>
                    <div class="form-group"><label>Situación Ahora (Opcional)</label><textarea id="rvAhora" class="form-control" rows="2" placeholder="Ej: Asfalto en caliente e iluminación..."></textarea></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="btnGuardarRv" style="margin-top:10px;">💾 Guardar Tramo</button>
                <button type="button" class="btn btn-secondary" onclick="rvCancel()">❌ Cancelar y Descartar</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
    <script>
        let map;
        let activeMarker = null;
        let refsGeoJSON = null;
        let currentMode = 'hitos';
        let rvNodes = [];
        let rvGeoJSON = null;
        
        let isDraggingRVNode = false;
        let draggedRVNodeIndex = -1;
        let justDragged = false;
        let isDraggingControlNode = false;
        let draggedControlIndex = -1;
        let hoveredSegmentIndex = -1;
        let highlightTimeout;
        
        // ==========================================
        // SISTEMA DE NOTIFICACIONES TOAST (RV4-A)
        // ==========================================
        window.showToast = function(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            
            const icons = { 'success': '✅', 'error': '❌', 'warning': '⚠️', 'info': 'ℹ️' };
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons['info']}</div><div class="toast-content">${message}</div><button class="toast-close" onclick="this.parentElement.classList.add('hiding'); setTimeout(() => this.parentElement.remove(), 300);">&times;</button>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                if(toast.parentElement) {
                    toast.classList.add('hiding');
                    setTimeout(() => { if(toast.parentElement) toast.remove(); }, 300);
                }
            }, 3500);
        };

        const RV_PUBLIC_LAYER_LABELS = {
            'water': 'Agua',
            'parks': 'Parques',
            'buildings': 'Edificios 2D',
            'buildings3d': 'Edificios 3D',
            'roads': 'Calles',
            'transit': 'Transporte ferreo',
            'boundaries': 'Limites distritales',
            'places-text': 'Nombres',
            'ref-urbanas': 'Referencias clave',
            'srv-edu': 'Servicios educativos',
            'srv-salud': 'Servicios de salud',
            'srv-seguridad': 'Seguridad',
            'srv-gobierno': 'Gobierno',
            'srv-mercados': 'Mercados',
            'srv-deporte': 'Deporte',
            'srv-transporte': 'Transporte',
            'srv-negocios': 'Negocios'
        };

        const RV_PUBLIC_STYLE_FIELDS = {
            roadHighway: 'Via regional / carretera',
            roadMain: 'Via principal',
            roadSecondary: 'Via secundaria',
            roadMinor: 'Via pequena',
            roadMinorCase: 'Borde via pequena'
        };

        const RV_PUBLIC_DEFAULT_STYLE = {
            roadHighway: '#89A5BE',
            roadHighwayCase: '#7893AA',
            roadMain: '#94AEC4',
            roadMainCase: '#819BB1',
            roadSecondary: '#C7D6E1',
            roadSecondaryCase: '#B5C7D5',
            roadMinor: '#C6CED3',
            roadMinorCase: '#D8E0E5',
            roadMinorWidthBoost: 1
        };

        const RV_PUBLIC_DEFAULT_VIEW = {
            center: [-70.30, -17.65],
            zoom: 8.5,
            pitch: 0,
            bearing: 0
        };

        let rvPublicConfig = null;

        function normalizarVistaPublica(view = {}) {
            const center = Array.isArray(view.center) ? view.center : RV_PUBLIC_DEFAULT_VIEW.center;
            const lng = Number(center[0]);
            const lat = Number(center[1]);
            const zoom = Number(view.zoom);
            const pitch = Number(view.pitch);
            const bearing = Number(view.bearing);

            return {
                center: [
                    Number.isFinite(lng) ? lng : RV_PUBLIC_DEFAULT_VIEW.center[0],
                    Number.isFinite(lat) ? lat : RV_PUBLIC_DEFAULT_VIEW.center[1]
                ],
                zoom: Number.isFinite(zoom) ? Math.max(5, Math.min(18, zoom)) : RV_PUBLIC_DEFAULT_VIEW.zoom,
                pitch: Number.isFinite(pitch) ? Math.max(0, Math.min(75, pitch)) : RV_PUBLIC_DEFAULT_VIEW.pitch,
                bearing: Number.isFinite(bearing) ? bearing : RV_PUBLIC_DEFAULT_VIEW.bearing
            };
        }

        function escribirVistaPublicaEnFormulario(view) {
            const viewBox = document.getElementById('rvPublicInitialView');
            if (!viewBox) return;
            const initialView = normalizarVistaPublica(view);
            const fields = {
                lng: initialView.center[0].toFixed(6),
                lat: initialView.center[1].toFixed(6),
                zoom: initialView.zoom.toFixed(2),
                pitch: Math.round(initialView.pitch),
                bearing: Math.round(initialView.bearing)
            };

            Object.entries(fields).forEach(([key, value]) => {
                const input = viewBox.querySelector(`[data-view-key="${key}"]`);
                if (input) input.value = value;
            });
        }

        const RV_PUBLIC_ADMIN_PREVIEW_LAYERS = {
            'water': ['water'],
            'parks': ['parks'],
            'roads': ['roads-minor', 'roads-major'],
            'transit': ['transit'],
            'boundaries': ['tacna-region-fill', 'tacna-provincias-outline', 'tacna-region-outline'],
            'places-text': ['places-text'],
            'ref-urbanas': ['ref-circles', 'ref-labels'],
            'srv-edu': ['landuse-edu'],
            'srv-salud': ['landuse-salud'],
            'srv-deporte': ['landuse-deporte']
        };

        const RV_PUBLIC_SERVICE_KINDS = {
            'srv-edu': ['school', 'university', 'college', 'kindergarten'],
            'srv-salud': ['hospital', 'clinic'],
            'srv-seguridad': ['police', 'fire_station'],
            'srv-gobierno': ['townhall', 'town_hall'],
            'srv-mercados': ['marketplace', 'market'],
            'srv-deporte': ['stadium', 'pitch'],
            'srv-transporte': ['bus_station']
        };

        function setAdminLayerVisibility(layerId, visible) {
            if (!map || !map.getLayer(layerId)) return;
            map.setLayoutProperty(layerId, 'visibility', visible ? 'visible' : 'none');
        }

        function getConfigPublicaFromControls() {
            const layers = {};
            document.querySelectorAll('#rvPublicLayers input[data-public-layer]').forEach(input => {
                layers[input.getAttribute('data-public-layer')] = input.checked;
            });

            const style = { ...RV_PUBLIC_DEFAULT_STYLE };
            document.querySelectorAll('#rvPublicStyle input[data-style-key]').forEach(input => {
                const key = input.getAttribute('data-style-key');
                style[key] = input.type === 'range' ? Number(input.value) : input.value;
            });

            const initialView = { ...RV_PUBLIC_DEFAULT_VIEW };
            const viewBox = document.getElementById('rvPublicInitialView');
            if (viewBox) {
                initialView.center = [
                    Number(viewBox.querySelector('[data-view-key="lng"]')?.value || RV_PUBLIC_DEFAULT_VIEW.center[0]),
                    Number(viewBox.querySelector('[data-view-key="lat"]')?.value || RV_PUBLIC_DEFAULT_VIEW.center[1])
                ];
                initialView.zoom = Number(viewBox.querySelector('[data-view-key="zoom"]')?.value || RV_PUBLIC_DEFAULT_VIEW.zoom);
                initialView.pitch = Number(viewBox.querySelector('[data-view-key="pitch"]')?.value || RV_PUBLIC_DEFAULT_VIEW.pitch);
                initialView.bearing = Number(viewBox.querySelector('[data-view-key="bearing"]')?.value || RV_PUBLIC_DEFAULT_VIEW.bearing);
            }

            return {
                defaultProfile: document.getElementById('rvPublicProfile')?.value || 'ciudadano',
                initialView: normalizarVistaPublica(initialView),
                layers,
                style
            };
        }

        function aplicarPreviewConfigPublica(config, applyView = false) {
            if (!map || !config || !config.layers) return;
            const layers = config.layers;
            const style = { ...RV_PUBLIC_DEFAULT_STYLE, ...(config.style || {}) };
            const initialView = normalizarVistaPublica({ ...RV_PUBLIC_DEFAULT_VIEW, ...(config.initialView || {}) });

            Object.entries(RV_PUBLIC_ADMIN_PREVIEW_LAYERS).forEach(([key, layerIds]) => {
                layerIds.forEach(layerId => setAdminLayerVisibility(layerId, !!layers[key]));
            });

            setAdminLayerVisibility('buildings', !!(layers.buildings || layers.buildings3d));
            setAdminLayerVisibility('roads-text', !!(layers.roads && layers['places-text']));

            const enabledPoiKinds = [];
            Object.entries(RV_PUBLIC_SERVICE_KINDS).forEach(([key, kinds]) => {
                if (layers[key]) enabledPoiKinds.push(...kinds);
            });

            if (layers['srv-negocios']) {
                enabledPoiKinds.push(
                    'restaurant', 'cafe', 'fast_food', 'bar', 'pub', 'pharmacy', 'dentist', 'doctors',
                    'veterinary', 'bakery', 'supermarket', 'convenience', 'butcher', 'bank', 'atm',
                    'hotel', 'motel', 'gas_station', 'car_wash', 'parking', 'hairdresser', 'clothes',
                    'shoes', 'cinema', 'theatre', 'gym', 'sports_centre'
                );
            }

            if (layers.parks) {
                enabledPoiKinds.push('park', 'recreation_ground');
            }

            const showPois = !!(layers['places-text'] && enabledPoiKinds.length);
            setAdminLayerVisibility('pois-text', showPois);
            if (showPois && map.getLayer('pois-text')) {
                map.setFilter('pois-text', ['all', ['has', 'name'], ['match', ['get', 'kind'], enabledPoiKinds, true, false]]);
            }

            if (map.getLayer('roads-major')) {
                map.setPaintProperty('roads-major', 'line-color', style.roadMain);
            }
            if (map.getLayer('roads-minor')) {
                map.setPaintProperty('roads-minor', 'line-color', style.roadMinor);
                map.setPaintProperty('roads-minor', 'line-width', 2 + Number(style.roadMinorWidthBoost || 0));
            }

            if (applyView && Array.isArray(initialView.center)) {
                map.jumpTo({
                    center: initialView.center,
                    zoom: Number(initialView.zoom),
                    pitch: Number(initialView.pitch),
                    bearing: Number(initialView.bearing)
                });
            }
        }

        function renderConfigPublica(config) {
            rvPublicConfig = config || {};
            const profile = document.getElementById('rvPublicProfile');
            const layersBox = document.getElementById('rvPublicLayers');
            if (!profile || !layersBox) return;

            profile.value = rvPublicConfig.defaultProfile || 'ciudadano';
            const layers = rvPublicConfig.layers || {};
            const initialView = normalizarVistaPublica({ ...RV_PUBLIC_DEFAULT_VIEW, ...(rvPublicConfig.initialView || {}) });
            layersBox.innerHTML = Object.keys(RV_PUBLIC_LAYER_LABELS).map(key => `
                <label style="display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #1e293b; border-radius:6px; background:#020617; color:#cbd5e1; font-size:13px;">
                    <input type="checkbox" data-public-layer="${key}" ${layers[key] ? 'checked' : ''} style="width:16px; height:16px;">
                    <span>${RV_PUBLIC_LAYER_LABELS[key]}</span>
                </label>
            `).join('');

            const viewBox = document.getElementById('rvPublicInitialView');
            if (viewBox) {
                viewBox.innerHTML = `
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <label style="font-size:12px; color:#cbd5e1;">Lng<input class="form-control" data-view-key="lng" type="number" step="0.000001" value="${initialView.center[0]}"></label>
                        <label style="font-size:12px; color:#cbd5e1;">Lat<input class="form-control" data-view-key="lat" type="number" step="0.000001" value="${initialView.center[1]}"></label>
                        <label style="font-size:12px; color:#cbd5e1;">Zoom<input class="form-control" data-view-key="zoom" type="number" min="5" max="18" step="0.1" value="${initialView.zoom}"></label>
                        <label style="font-size:12px; color:#cbd5e1;">Rotacion<input class="form-control" data-view-key="bearing" type="number" min="-360" max="360" step="1" value="${initialView.bearing}"></label>
                    </div>
                    <label style="font-size:12px; color:#cbd5e1;">Inclinacion<input class="form-control" data-view-key="pitch" type="number" min="0" max="75" step="1" value="${initialView.pitch}"></label>
                `;

                viewBox.querySelectorAll('input[data-view-key]').forEach(input => {
                    input.addEventListener('change', () => aplicarPreviewConfigPublica(getConfigPublicaFromControls(), true));
                });
            }

            const styleBox = document.getElementById('rvPublicStyle');
            const style = { ...RV_PUBLIC_DEFAULT_STYLE, ...(rvPublicConfig.style || {}) };
            if (styleBox) {
                styleBox.innerHTML = `
                    ${Object.entries(RV_PUBLIC_STYLE_FIELDS).map(([key, label]) => `
                        <label style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 10px; border:1px solid #1e293b; border-radius:6px; background:#020617; color:#cbd5e1; font-size:13px;">
                            <span>${label}</span>
                            <input type="color" data-style-key="${key}" value="${style[key]}" style="width:42px; height:28px; padding:0; border:0; background:transparent;">
                        </label>
                    `).join('')}
                    <label style="display:grid; gap:6px; padding:8px 10px; border:1px solid #1e293b; border-radius:6px; background:#020617; color:#cbd5e1; font-size:13px;">
                        <span>Grosor extra de vias pequenas: <strong id="rvMinorWidthValue">${style.roadMinorWidthBoost}</strong></span>
                        <input type="range" data-style-key="roadMinorWidthBoost" min="0" max="2" step="0.25" value="${style.roadMinorWidthBoost}">
                    </label>
                `;

                styleBox.querySelectorAll('input[data-style-key]').forEach(input => {
                    input.addEventListener('input', () => {
                        const value = document.getElementById('rvMinorWidthValue');
                        if (value && input.getAttribute('data-style-key') === 'roadMinorWidthBoost') value.textContent = input.value;
                        aplicarPreviewConfigPublica(getConfigPublicaFromControls());
                    });
                });
            }

            layersBox.querySelectorAll('input[data-public-layer]').forEach(input => {
                input.addEventListener('change', () => {
                    if (input.getAttribute('data-public-layer') === 'buildings3d' && input.checked) {
                        const buildings2d = layersBox.querySelector('input[data-public-layer="buildings"]');
                        if (buildings2d) buildings2d.checked = false;
                    }
                    if (input.getAttribute('data-public-layer') === 'buildings' && input.checked) {
                        const buildings3d = layersBox.querySelector('input[data-public-layer="buildings3d"]');
                        if (buildings3d) buildings3d.checked = false;
                    }
                    aplicarPreviewConfigPublica(getConfigPublicaFromControls());
                });
            });

            profile.onchange = () => aplicarPreviewConfigPublica(getConfigPublicaFromControls());
            aplicarPreviewConfigPublica(rvPublicConfig, true);
        }

        async function cargarConfigPublica() {
            const res = await fetch('red_vial_public_config_api.php?action=get', { cache: 'no-store' });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la configuracion');
            renderConfigPublica(data.config);
        }

        window.abrirConfigPublica = async function() {
            document.querySelectorAll('.panel-formulario, .panel-lista').forEach(panel => panel.classList.remove('active'));
            document.getElementById('panelConfigPublica').classList.add('active');
            try {
                await cargarConfigPublica();
            } catch (error) {
                showToast('No se pudo cargar la configuracion publica.', 'error');
            }
        };

        window.cerrarConfigPublica = function() {
            document.getElementById('panelConfigPublica').classList.remove('active');
        };

        window.capturarVistaPublicaActual = function() {
            if (!map) return;
            const center = map.getCenter();
            const viewBox = document.getElementById('rvPublicInitialView');
            if (!viewBox) return;

            escribirVistaPublicaEnFormulario({
                center: [center.lng, center.lat],
                zoom: map.getZoom(),
                pitch: map.getPitch(),
                bearing: map.getBearing()
            });

            showToast('Vista actual capturada. Guarda para aplicarla al mapa publico.', 'info');
            aplicarPreviewConfigPublica(getConfigPublicaFromControls(), true);
        };

        window.guardarConfigPublica = async function() {
            const draftConfig = getConfigPublicaFromControls();
            const profile = draftConfig.defaultProfile;
            const initialView = normalizarVistaPublica(draftConfig.initialView);
            escribirVistaPublicaEnFormulario(initialView);
            const layers = draftConfig.layers;
            const style = draftConfig.style;

            if (layers.buildings && layers.buildings3d) {
                layers.buildings3d = false;
            }

            try {
                const res = await fetch('red_vial_public_config_api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ defaultProfile: profile, initialView, layers, style })
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'No se pudo guardar');
                renderConfigPublica(data.config);
                aplicarPreviewConfigPublica(data.config, true);
                showToast('Vista publica guardada. El mapa publico usara esta configuracion al recargar.', 'success');
            } catch (error) {
                showToast('Error al guardar la vista publica.', 'error');
            }
        };

        async function initGestorCartografico() {
            // Importar PMTiles dinámicamente (compatible con ES Modules)
            const pmtiles = await import('https://unpkg.com/pmtiles@3.0.6/dist/index.js');
            window.pmtiles = pmtiles;

            // Configuración PMTiles y MapLibre
            const protocol = new pmtiles.Protocol();
            maplibregl.addProtocol('pmtiles', protocol.tile);
            
            const mapStyle = {
                version: 8,
                glyphs: "https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf",
                sources: {
                    "protomaps": { type: "vector", url: "pmtiles://../data/pmtiles_proxy_departamento.php" },
                    "referencias": { type: "geojson", data: "mapa_referencias_api.php?action=geojson" },
                    "tacna-region": { type: "geojson", data: "../data/tacna_region.geojson" },
                    "tacna-provincias": { type: "geojson", data: "../data/tacna_provincias.geojson" },
                    "highlight-source": { type: "geojson", data: { type: "Feature", geometry: { type: "LineString", coordinates: [] } } },
                    "tramos-viales": { type: "geojson", data: "mapa_redvial_api.php?action=geojson" },
                    "draw-source": { type: "geojson", data: { type: "Feature", geometry: { type: "LineString", coordinates: [] } } },
                    "draw-line-hit-source": { type: "geojson", data: { type: "FeatureCollection", features: [] } },
                    "draw-points": { type: "geojson", data: { type: "FeatureCollection", features: [] } },
                    "draw-control-source": { type: "geojson", data: { type: "FeatureCollection", features: [] } }
                },
                layers: [
                    { id: "bg", type: "background", paint: { "background-color": "#F2EFE9" } },
                    { id: "water", type: "fill", source: "protomaps", "source-layer": "water", paint: { "fill-color": "#B9D9F7" } },
                    { id: "parks", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["match", ["get", "kind"], ["park", "grass", "recreation_ground", "cemetery", "forest", "wood"], true, false], "paint": { "fill-color": "#C2E2BA" } },
                    { id: "landuse-salud", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["match", ["get", "kind"], ["hospital", "clinic"], true, false], "paint": { "fill-color": "#F4C7C3" } },
                    { id: "landuse-edu", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["match", ["get", "kind"], ["school", "university", "college", "kindergarten"], true, false], "paint": { "fill-color": "#F6E6A8" } },
                    { id: "landuse-deporte", type: "fill", "source": "protomaps", "source-layer": "landuse", "filter": ["match", ["get", "kind"], ["stadium", "pitch"], true, false], "paint": { "fill-color": "#C2E2BA" } },
                    { id: "tacna-region-fill", type: "fill", source: "tacna-region", paint: { "fill-color": "#8A1538", "fill-opacity": 0.01 } },
                    { id: "tacna-provincias-outline", type: "line", source: "tacna-provincias", layout: { "line-join": "miter", "line-cap": "butt" }, paint: { "line-color": "#8A1538", "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.45, 9, 0.7, 11, 1], "line-opacity": 0.35, "line-dasharray": [2, 3] } },
                    { id: "tacna-region-outline", type: "line", source: "tacna-region", layout: { "line-join": "miter", "line-cap": "butt" }, paint: { "line-color": "#8A1538", "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.8, 9, 1.1, 11, 1.4], "line-opacity": 0.55, "line-dasharray": [3, 2] } },
                    { id: "buildings", type: "fill", source: "protomaps", "source-layer": "buildings", paint: { "fill-color": "#e6e4df", "fill-opacity": 0.6 } },
                    { id: "transit", type: "line", source: "protomaps", "source-layer": "transit", paint: { "line-color": "#f87171", "line-dasharray": [2, 2] } },
                    { id: "roads-minor", type: "line", source: "protomaps", "source-layer": "roads", paint: { "line-color": "#FFFFFF", "line-width": 2 } },
                    { id: "roads-major", type: "line", source: "protomaps", "source-layer": "roads", filter: ["any", ["==", ["get", "kind"], "highway"], ["==", ["get", "kind"], "major_road"]], paint: { "line-color": "#CDD7E3", "line-width": 4 } },
                    { id: "places-text", type: "symbol", source: "protomaps", "source-layer": "places", layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 14}, paint: {"text-color": "#1e293b", "text-halo-color": "#F2EFE9", "text-halo-width": 2} },
                    { id: "places-hitbox", type: "circle", source: "protomaps", "source-layer": "places", paint: { "circle-radius": 20, "circle-opacity": 0 } },
                    { id: "roads-text", type: "symbol", source: "protomaps", "source-layer": "roads", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 11, "symbol-placement": "line"}, paint: {"text-color": "#3f3f46", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "pois-text", type: "symbol", source: "protomaps", "source-layer": "pois", filter: ["has", "name"], layout: {"text-field": ["get","name"], "text-font": ["Noto Sans Regular"], "text-size": 12}, paint: {"text-color": "#666666", "text-halo-color": "#FFFFFF", "text-halo-width": 2} },
                    { id: "tramos-viales-layer", type: "line", source: "tramos-viales", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": ["get", "color"], "line-width": 4 } },
                    { id: "highlight-layer", type: "line", source: "highlight-source", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": "#ffc300", "line-width": 8, "line-opacity": 0.9 } },
                    { id: "ref-circles", type: "circle", source: "referencias", paint: { "circle-color": "#10b981", "circle-radius": 6, "circle-stroke-width": 2, "circle-stroke-color": "#020617" } },
                    { id: "ref-labels", type: "symbol", source: "referencias", layout: { "text-field": ["get", "short_name"], "text-font": ["Noto Sans Regular"], "text-size": 13, "text-offset": [0, 1], "text-anchor": "top" }, paint: { "text-color": "#1e293b", "text-halo-color": "#ffffff", "text-halo-width": 3 } },
                    { id: "draw-line-layer", type: "line", source: "draw-source", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": "#3b82f6", "line-width": 4, "line-dasharray": [2, 2] } },
                    { id: "draw-line-hit", type: "line", source: "draw-line-hit-source", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-width": 20, "line-color": "rgba(0,0,0,0)" } },
                    { id: "draw-points-layer", type: "circle", source: "draw-points", paint: { "circle-radius": 5, "circle-color": "#ffffff", "circle-stroke-width": 2, "circle-stroke-color": "#3b82f6" } },
                    { id: "draw-points-hit", type: "circle", source: "draw-points", paint: { "circle-radius": 15, "circle-color": "rgba(0,0,0,0)" } },
                    { id: "draw-control-layer", type: "circle", source: "draw-control-source", paint: { "circle-radius": 6, "circle-color": "#ffc300", "circle-stroke-width": 2, "circle-stroke-color": "#801039" } },
                    { id: "draw-control-hit", type: "circle", source: "draw-control-source", paint: { "circle-radius": 16, "circle-color": "rgba(0,0,0,0)" } }
                ]
            };

            map = new maplibregl.Map({
                container: 'map',
                style: mapStyle,
                center: [-70.30, -17.65],
                zoom: 8.5,
                dragRotate: false // Desactiva la manito/rotación del clic derecho
            });
            
            // Bloquear el menú nativo del navegador en el canvas para asegurar el clic derecho
            map.on('load', () => {
                cargarConfigPublica().catch(() => {
                    showToast('No se pudo aplicar la vista publica en el mapa admin.', 'warning');
                });
            });

            map.getCanvas().addEventListener('contextmenu', e => e.preventDefault());
            
            // Evento Click en el Mapa
            map.on('click', (e) => {
                if (justDragged) return; // Evita crear un punto nuevo justo después de soltar un arrastre
                
                document.getElementById('panelLista').classList.remove('active');
                document.getElementById('panelListaRV').classList.remove('active');
                
                if (currentMode === 'hitos') {
                const lat = e.lngLat.lat.toFixed(6);
                const lng = e.lngLat.lng.toFixed(6);
                
                if (activeMarker) activeMarker.remove();
                activeMarker = new maplibregl.Marker({ color: '#ef4444' }).setLngLat([lng, lat]).addTo(map);
                
                document.getElementById('refId').value = '';
                document.getElementById('formTitle').textContent = '➕ Agregar Referencia';
                document.getElementById('btnGuardar').textContent = '💾 Guardar Referencia';
                document.getElementById('inpLat').value = lat;
                document.getElementById('inpLng').value = lng;
                document.getElementById('panelFormulario').classList.add('active');
                
                // =========================================================
                // 🔍 AUTOCOMPLETADO INTELIGENTE (Priorización Vectorial)
                // =========================================================
                const features = map.queryRenderedFeatures(e.point);
                let bestMatch = null;
                let priority = 99;

                for (let f of features) {
                    const layer = f.sourceLayer;
                    const props = f.properties || {};
                    const name = props.name || '';
                    const kind = props.kind || '';

                    if (layer === 'pois' && name && priority > 1) { bestMatch = { name, kind, layer }; priority = 1; } 
                    else if (layer === 'places' && name && priority > 2) { bestMatch = { name, kind, layer }; priority = 2; } 
                    else if (layer === 'landuse' && kind && priority > 3) { bestMatch = { name: name || kind.toUpperCase(), kind, layer }; priority = 3; } 
                    else if (layer === 'roads' && name && priority > 4) { bestMatch = { name, kind, layer }; priority = 4; }
                }

                let sugName = '', sugCorto = '', sugCat = 'General', sugIcon = 'hito';

                if (bestMatch) {
                    sugName = bestMatch.name;
                    sugCorto = bestMatch.name;
                    const k = bestMatch.kind;

                    if (['hospital', 'clinic'].includes(k)) {
                        sugCat = 'Salud'; sugIcon = 'salud';
                        sugCorto = 'Hosp. ' + (sugName.replace(/(hospital|clinica|centro de salud|puesto de salud)\s+/i, '').split(' ')[0] || 'Salud');
                    } else if (['school', 'university', 'college', 'kindergarten'].includes(k)) {
                        sugCat = 'Educación'; sugIcon = 'educacion';
                    } else if (['townhall', 'town_hall', 'police', 'fire_station'].includes(k)) {
                        sugCat = 'Gobierno'; sugIcon = 'gobierno';
                        if (k === 'townhall' || k === 'town_hall') sugCorto = 'Muni. ' + (sugName.split(' ')[0] || '');
                    } else if (['stadium', 'pitch', 'park', 'sports_centre'].includes(k)) {
                        sugCat = 'Deporte'; sugIcon = 'deporte';
                    } else if (['park', 'recreation_ground'].includes(k)) {
                        sugCat = 'Parque'; sugIcon = 'parque';
                    } else if (['marketplace', 'market'].includes(k)) {
                        sugCat = 'Mercado'; sugIcon = 'comercio';
                    } else if (['bus_station', 'aerodrome'].includes(k)) {
                        sugCat = 'Transporte'; sugIcon = 'transporte';
                    } else if (bestMatch.layer === 'roads') {
                        sugCat = 'General'; sugIcon = 'hito';
                    }
                }

                document.getElementById('inpNombre').value = sugName;
                document.getElementById('inpCorto').value = sugCorto;
                document.getElementById('inpCat').value = sugCat;
                document.getElementById('inpIcon').value = sugIcon;

                document.getElementById('inpNombre').focus();
                } 
                else if (currentMode === 'vias') {
                    document.getElementById('panelFormularioRV').classList.remove('active');
                    rvNodes.push({ nodo: [e.lngLat.lng, e.lngLat.lat], control: null });
                    
                    if (rvNodes.length === 1) {
                        document.getElementById('rvDrawPanel').style.display = 'flex';
                        // Captura inteligente de nombre con buffer de 8px
                        const bbox = [ [e.point.x - 8, e.point.y - 8], [e.point.x + 8, e.point.y + 8] ];
                        const features = map.queryRenderedFeatures(bbox, { layers: ['roads-major', 'roads-minor'] });
                        let detName = features.find(f => f.properties && f.properties.name)?.properties.name;
                        document.getElementById('rvNombre').value = detName || 'Tramo vial sin nombre';
                    }
                    
                    updateDrawLayer();
                }
            });
            
            // ==========================================
            // EVENTOS DE EDICIÓN AVANZADA (MOVER / ELIMINAR)
            // ==========================================
            

            map.on('mousedown', 'draw-points-hit', (e) => {
                const button = e.originalEvent ? e.originalEvent.button : 0;
                
                if (currentMode !== 'vias' || button !== 0) return;
                e.preventDefault();
                if (e.originalEvent) {
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();
                }
                map.dragPan.disable();
                
                isDraggingRVNode = true;
                draggedRVNodeIndex = e.features[0].properties.index;
                map.getCanvas().style.cursor = 'grabbing';
            });
            
            map.on('mousedown', 'draw-control-hit', (e) => {
                const button = e.originalEvent ? e.originalEvent.button : 0;
                if (currentMode !== 'vias' || button !== 0) return;
                
                e.preventDefault();
                if (e.originalEvent) {
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();
                }
                map.dragPan.disable();
                
                isDraggingControlNode = true;
                draggedControlIndex = e.features[0].properties.segmentIndex;
                map.getCanvas().style.cursor = 'grabbing';
            });
            
            map.on('mousemove', (e) => {
                if (currentMode !== 'vias') return;
                if (isDraggingRVNode && draggedRVNodeIndex !== -1) {
                    rvNodes[draggedRVNodeIndex].nodo = [e.lngLat.lng, e.lngLat.lat];
                    updateDrawLayer();
                    if (hoveredSegmentIndex !== -1) updateControlPointLayer(hoveredSegmentIndex);
                } else if (isDraggingControlNode && draggedControlIndex !== -1) {
                    rvNodes[draggedControlIndex].control = [e.lngLat.lng, e.lngLat.lat];
                    updateControlPointLayer(draggedControlIndex);
                    updateDrawLayer();
                } else {
                    const bbox = [[e.point.x - 8, e.point.y - 8], [e.point.x + 8, e.point.y + 8]];
                    const hitNodes = map.queryRenderedFeatures(bbox, { layers: ['draw-points-hit'] });
                    const hitControls = map.queryRenderedFeatures(bbox, { layers: ['draw-control-hit'] });
                    
                    if (hitNodes.length > 0 || hitControls.length > 0) {
                        map.getCanvas().style.cursor = 'move';
                    } else {
                        map.getCanvas().style.cursor = 'crosshair';
                        const hitSegments = map.queryRenderedFeatures(bbox, { layers: ['draw-line-hit'] });
                        if (hitSegments.length > 0) {
                            const idx = hitSegments[0].properties.segmentIndex;
                            if (hoveredSegmentIndex !== idx) {
                                hoveredSegmentIndex = idx;
                                updateControlPointLayer(idx);
                            }
                        } else {
                            if (hoveredSegmentIndex !== -1) {
                                hoveredSegmentIndex = -1;
                                hideControlPointLayer();
                            }
                        }
                    }
                }
            });
            
            window.addEventListener('mouseup', () => {
                if (isDraggingRVNode) {
                    isDraggingRVNode = false;
                    draggedRVNodeIndex = -1;
                    if (map) { map.getCanvas().style.cursor = 'crosshair'; map.dragPan.enable(); }
                    justDragged = true;
                    setTimeout(() => justDragged = false, 100);
                }
                if (isDraggingControlNode) {
                    isDraggingControlNode = false;
                    draggedControlIndex = -1;
                    if (map) { map.getCanvas().style.cursor = 'crosshair'; map.dragPan.enable(); }
                    justDragged = true;
                    setTimeout(() => justDragged = false, 100);
                }
            });
            
            map.on('contextmenu', 'draw-points-hit', (e) => {
                if (currentMode !== 'vias') return;
                if (confirm("🗑️ ¿Deseas eliminar este vértice del tramo?")) {
                    const idx = e.features[0].properties.index;
                    rvNodes.splice(idx, 1);
                    hoveredSegmentIndex = -1;
                    hideControlPointLayer();
                    updateDrawLayer();
                }
            });
            
            map.on('contextmenu', 'draw-control-hit', (e) => {
                if (currentMode !== 'vias') return;
                
                const idx = e.features[0].properties.segmentIndex;
                
                rvNodes[idx].control = null;
                
                updateControlPointLayer(idx);
                updateDrawLayer();
            });
        }
        
        initGestorCartografico();
        
        // ==========================================
        // LÓGICA DE DIBUJO Y MODO RED VIAL (RV2)
        // ==========================================
        function setMode(mode) {
            if (currentMode === 'vias' && rvNodes.length > 0 && mode === 'hitos') {
                if (!confirm("Tienes un trazo en progreso. ¿Descartarlo?")) return;
                rvCancel();
            }
            currentMode = mode;
            if (map) map.dragPan.enable(); // Seguro anti-bloqueo al cambiar de modo
            document.getElementById('btnModeHitos').className = mode === 'hitos' ? 'btn btn-primary' : 'btn';
            document.getElementById('btnModeHitos').style.background = mode === 'hitos' ? '' : 'transparent';
            document.getElementById('btnModeHitos').style.color = mode === 'hitos' ? '' : '#94a3b8';
            document.getElementById('btnModeVias').className = mode === 'vias' ? 'btn btn-primary' : 'btn';
            document.getElementById('btnModeVias').style.background = mode === 'vias' ? '' : 'transparent';
            document.getElementById('btnModeVias').style.color = mode === 'vias' ? '' : '#94a3b8';
            document.getElementById('btnVerLista').textContent = mode === 'hitos' ? '📋 Ver Lista de Puntos' : '📋 Ver Lista de Vías';
            
            document.getElementById('panelLista').classList.remove('active');
            document.getElementById('panelListaRV').classList.remove('active');
            rvCancel();
            
            document.querySelector('.instrucciones').textContent = mode === 'hitos' 
                ? "📍 Haz clic en el mapa para anclar un nuevo Titán" 
                : "🛣️ Clic para trazar • Arrastra nodos para mover • Clic derecho para borrar";
                
            map.getCanvas().style.cursor = mode === 'vias' ? 'crosshair' : '';
        }

        function getBakedSegment(p0, p1, p2) {
            if (!p1) return [p0, p2];
            let baked = [];
            const pasos = 20;
            for (let j = 0; j <= pasos; j++) {
                let t = j / pasos;
                let lng = Math.pow(1 - t, 2) * p0[0] + 2 * (1 - t) * t * p1[0] + Math.pow(t, 2) * p2[0];
                let lat = Math.pow(1 - t, 2) * p0[1] + 2 * (1 - t) * t * p1[1] + Math.pow(t, 2) * p2[1];
                baked.push([lng, lat]);
            }
            return baked;
        }

        function getBakedCoords(nodes) {
            if (nodes.length === 0) return [];
            if (nodes.length === 1) return [nodes[0].nodo];
            let baked = [];
            for (let i = 0; i < nodes.length - 1; i++) {
                let segment = getBakedSegment(nodes[i].nodo, nodes[i].control, nodes[i + 1].nodo);
                if (i > 0) segment.shift(); // Evitar duplicar el punto de unión
                baked.push(...segment);
            }
            return baked;
        }

        function updateControlPointLayer(idx) {
            if (idx < 0 || idx >= rvNodes.length - 1) return hideControlPointLayer();
            let p0 = rvNodes[idx].nodo;
            let p1 = rvNodes[idx].control;
            let p2 = rvNodes[idx+1].nodo;
            
            let controlPoint = p1 ? p1 : [(p0[0] + p2[0]) / 2, (p0[1] + p2[1]) / 2];
            
            
            if (map && map.getSource('draw-control-source')) {
                map.getSource('draw-control-source').setData({ type: "Feature", properties: { segmentIndex: idx }, geometry: { type: "Point", coordinates: controlPoint } });
            }
        }
        
        function hideControlPointLayer() {
            if (map && map.getSource('draw-control-source')) {
                map.getSource('draw-control-source').setData({ type: "FeatureCollection", features: [] });
            }
        }

        function updateDrawLayer() {
            if (map.getSource('draw-source')) {
                const bakedCoords = getBakedCoords(rvNodes);
                map.getSource('draw-source').setData({ type: "Feature", geometry: { type: "LineString", coordinates: bakedCoords } });
                
                const segmentFeatures = [];
                for (let i = 0; i < rvNodes.length - 1; i++) {
                    segmentFeatures.push({ type: "Feature", properties: { segmentIndex: i }, geometry: { type: "LineString", coordinates: getBakedSegment(rvNodes[i].nodo, rvNodes[i].control, rvNodes[i+1].nodo) } });
                }
                if (map.getSource('draw-line-hit-source')) {
                    map.getSource('draw-line-hit-source').setData({ type: "FeatureCollection", features: segmentFeatures });
                }
                
                map.getSource('draw-points').setData({ type: "FeatureCollection", features: rvNodes.map((n, i) => ({ type: "Feature", properties: { index: i }, geometry: { type: "Point", coordinates: n.nodo } })) });
            }
            document.getElementById('rvDrawCount').textContent = rvNodes.length + (rvNodes.length === 1 ? " punto" : " puntos");
            document.getElementById('btnRvFinish').disabled = rvNodes.length < 2;
        }

        function rvUndo() { 
            rvNodes.pop(); 
            hoveredSegmentIndex = -1;
            hideControlPointLayer();
            updateDrawLayer(); 
            if (rvNodes.length === 0) document.getElementById('rvDrawPanel').style.display = 'none'; 
        }
        
        function rvCancel() { 
            rvNodes = []; 
            hoveredSegmentIndex = -1;
            hideControlPointLayer();
            updateDrawLayer(); 
            document.getElementById('rvDrawPanel').style.display = 'none'; 
            document.getElementById('panelFormularioRV').classList.remove('active'); 
            document.getElementById('rvForm').reset(); 
            document.getElementById('rvId').value = '';
            document.getElementById('btnGuardarRv').textContent = '💾 Guardar Tramo';
        }
        
        function rvFinish() { 
            document.getElementById('rvDrawPanel').style.display = 'none'; 
            document.getElementById('panelFormularioRV').classList.add('active'); 
            document.getElementById('rvNombre').focus(); 
            
            // ==========================================
            // AUDITORÍA Y SUGERENCIA AUTOMÁTICA DE SECTOR
            // ==========================================
            const inputSector = document.getElementById('rvSector');
            if (inputSector && inputSector.value.trim() === '' && rvNodes.length > 0) {
                
                // 2. Usar el nodo central del tramo
                const midIndex = Math.floor(rvNodes.length / 2);
                const midNode = rvNodes[midIndex].nodo;
                const screenPoint = map.project(midNode);
                const bbox = [ [screenPoint.x - 80, screenPoint.y - 80], [screenPoint.x + 80, screenPoint.y + 80] ];
                
                // 3. Auditoría profunda en consola
                const allPlaces = map.queryRenderedFeatures(bbox, { layers: ['places-text', 'places-hitbox'] });
                
                // 4. Asignación si existe match válido
                let suggestedSector = null;
                for (let p of allPlaces) {
                    if (p.properties && ['neighbourhood', 'suburb', 'locality', 'village'].includes(p.properties.kind)) {
                        suggestedSector = p.properties.name; break;
                    }
                }
                
                if (suggestedSector) {
                    inputSector.value = suggestedSector;
                    let msg = document.getElementById('rvSectorMsg');
                    if (!msg) {
                        msg = document.createElement('small'); msg.id = 'rvSectorMsg';
                        msg.style.cssText = 'color:#3b82f6; font-size:10px; display:block; margin-top:4px; font-weight:bold;';
                        inputSector.parentNode.appendChild(msg);
                    }
                    msg.textContent = '✨ Sugerido según centro del tramo';
                    setTimeout(() => { if(msg) msg.textContent = ''; }, 6000);
                }
            }
        }

        // Auto-asignación de color por estado idéntico al CSS público
        document.getElementById('rvEstado').addEventListener('change', (e) => {
            const coloresBD = { 'Entregado': '#1b5e20', 'En ejecución': '#1a73e8', 'Paralizado': '#c62828', 'Buena Pro': '#ef6c00', 'Transferencia': '#6d28d9', 'En estudios': '#616161' };
            document.getElementById('rvColor').value = coloresBD[e.target.value] || '#3b82f6';
        });

        function cerrarPanel() {
            document.getElementById('panelFormulario').classList.remove('active');
            if (activeMarker) activeMarker.remove();
            document.getElementById('refForm').reset();
            document.getElementById('refId').value = '';
            document.getElementById('inpZoom').value = 11;
            document.getElementById('inpCat').value = 'General';
            document.getElementById('inpIcon').value = 'hito';
        }

        async function fetchLista() {
            try {
                const res = await fetch('mapa_referencias_api.php?action=geojson');
                refsGeoJSON = await res.json();
                renderLista();
            } catch (e) { console.error('Error fetching list', e); }
        }

        function abrirListaActual() {
            if (currentMode === 'hitos') abrirLista();
            else abrirListaRV();
        }

        function abrirLista() {
            document.getElementById('panelFormulario').classList.remove('active');
            document.getElementById('panelLista').classList.add('active');
            fetchLista();
        }

        function cerrarLista() {
            document.getElementById('panelLista').classList.remove('active');
        }

        function renderLista() {
            const cont = document.getElementById('listaReferenciasContainer');
            if (!refsGeoJSON || !refsGeoJSON.features || refsGeoJSON.features.length === 0) {
                cont.innerHTML = '<p style="color:#64748b; font-size:12px;">No hay referencias guardadas.</p>';
                return;
            }
            let html = '';
            const iconMap = { 'salud':'🏥', 'educacion':'🎓', 'gobierno':'🏛️', 'deporte':'⚽', 'transporte':'🚌', 'comercio':'🛒', 'parque':'🌳', 'hito':'📍' };
            refsGeoJSON.features.forEach(f => {
                const p = f.properties;
                const emoji = iconMap[p.icon_type] || '📍';
                html += `<div style="background:#1e293b; margin-bottom:10px; padding:12px; border-radius:8px; border:1px solid #334155; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
                    <div><strong style="color:#f8fafc;">${emoji} ${p.name}</strong><br><span style="color:#94a3b8; font-size:11px;">Cat: ${p.categoria} | Zoom: ${p.min_zoom}</span></div>
                    <div style="display:flex; gap:6px;"><button type="button" class="btn" style="padding:6px; background:#3b82f6; color:white; min-width:30px;" onclick="editarRef(${p.id})" title="Editar">✏️</button><button type="button" class="btn" style="padding:6px; background:#ef4444; color:white; min-width:30px;" onclick="eliminarRef(${p.id})" title="Eliminar">🗑️</button></div>
                </div>`;
            });
            cont.innerHTML = html;
        }

        function editarRef(id) {
            const feature = refsGeoJSON.features.find(f => f.properties.id === id);
            if (!feature) return;
            
            const lng = feature.geometry.coordinates[0], lat = feature.geometry.coordinates[1];
            if (activeMarker) activeMarker.remove();
            activeMarker = new maplibregl.Marker({ color: '#3b82f6' }).setLngLat([lng, lat]).addTo(map);
            map.flyTo({ center: [lng, lat], zoom: 15 });

            document.getElementById('refId').value = id;
            document.getElementById('inpLat').value = lat; document.getElementById('inpLng').value = lng;
            document.getElementById('inpNombre').value = feature.properties.name; document.getElementById('inpCorto').value = feature.properties.short_name;
            document.getElementById('inpCat').value = feature.properties.categoria; document.getElementById('inpIcon').value = feature.properties.icon_type;
            document.getElementById('inpZoom').value = feature.properties.min_zoom;
            
            document.getElementById('panelLista').classList.remove('active');
            document.getElementById('panelFormulario').classList.add('active');
            document.getElementById('formTitle').textContent = '✏️ Editar Referencia';
            document.getElementById('btnGuardar').textContent = '💾 Actualizar Referencia';
        }

        async function eliminarRef(id) {
            if (!confirm('⚠️ ¿Estás seguro de eliminar este punto? Desaparecerá de todos los mapas públicos de inmediato.')) return;
            try {
                const res = await fetch('mapa_referencias_api.php?action=delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id}) });
                const data = await res.json();
                if (data.ok) { if (map && map.getSource('referencias')) map.getSource('referencias').setData('mapa_referencias_api.php?action=geojson'); fetchLista(); } 
                else { alert('Error: ' + data.error); }
            } catch(e) { alert('Error de conexión'); }
        }

        // ==========================================
        // DASHBOARD RESUMEN DE VÍAS (RV4-B)
        // ==========================================
        function actualizarResumenVias() {
            if (!rvGeoJSON || !rvGeoJSON.features) {
                ['Total', 'Activas', 'Inactivas', 'Entregadas', 'Ejecucion', 'Otros'].forEach(id => {
                    const el = document.getElementById('sumRv' + id);
                    if(el) el.textContent = '0';
                });
                return;
            }
            
            let c = { total: 0, activas: 0, inactivas: 0, entregadas: 0, ejecucion: 0, otros: 0 };
            rvGeoJSON.features.forEach(f => {
                c.total++;
                const p = f.properties || {};
                if (p.activo === 1) c.activas++; else c.inactivas++;
                
                const est = (p.estado || '').toLowerCase();
                if (est.includes('entregado')) c.entregadas++;
                else if (est.includes('ejecución') || est.includes('construccion')) c.ejecucion++;
                else c.otros++;
            });
            
            document.getElementById('sumRvTotal').textContent = c.total; document.getElementById('sumRvActivas').textContent = c.activas; document.getElementById('sumRvInactivas').textContent = c.inactivas; document.getElementById('sumRvEntregadas').textContent = c.entregadas; document.getElementById('sumRvEjecucion').textContent = c.ejecucion; document.getElementById('sumRvOtros').textContent = c.otros;
        }

        // ==========================================
        // LÓGICA DE LISTADO Y EDICIÓN RED VIAL
        // ==========================================
        async function fetchListaRV() {
            try {
                const res = await fetch('mapa_redvial_api.php?action=listar_admin');
                rvGeoJSON = await res.json();
                renderListaRV();
            } catch (e) { console.error('Error fetching list RV', e); }
        }

        function abrirListaRV() {
            document.getElementById('panelFormularioRV').classList.remove('active');
            document.getElementById('rvDrawPanel').style.display = 'none';
            document.getElementById('panelGaleriaRV').classList.remove('active');
            document.getElementById('panelListaRV').classList.add('active');
            fetchListaRV();
        }

        function cerrarListaRV() {
            document.getElementById('panelListaRV').classList.remove('active');
        }

        function renderListaRV() {
            const cont = document.getElementById('listaRvContainer');
            const contadorEl = document.getElementById('contadorRV');
            
            // Actualizar dashboard con datos en crudo (sin importar los filtros actuales)
            actualizarResumenVias();
            
            if (!rvGeoJSON || !rvGeoJSON.features || rvGeoJSON.features.length === 0) {
                cont.innerHTML = '<p style="color:#64748b; font-size:12px;">No hay vías guardadas.</p>';
                if (contadorEl) contadorEl.textContent = '';
                return;
            }
            
            const qNombre = (document.getElementById('filtroNombreRV')?.value || '').toLowerCase();
            const qTipo = document.getElementById('filtroTipoRV')?.value || '';
            const qEstado = document.getElementById('filtroEstadoRV')?.value || '';
            const qVisib = document.getElementById('filtroVisibilidadRV')?.value || '';
            
            let html = '';
            let count = 0;
            const total = rvGeoJSON.features.length;
            
            rvGeoJSON.features.forEach(f => {
                const p = f.properties;
                
                if (qNombre && !p.nombre.toLowerCase().includes(qNombre)) return;
                if (qTipo && p.tipo !== qTipo) return;
                if (qEstado && p.estado !== qEstado) return;
                if (qVisib !== '' && p.activo.toString() !== qVisib) return;
                
                count++;
                
                const isActivo = p.activo === 1;
                const opacity = isActivo ? '1' : '0.6';
                const badgeActivo = isActivo ? '' : '<span style="background:#ef4444; color:white; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold; margin-left:8px;">INACTIVA</span>';
                const btnToggle = isActivo 
                    ? `<button type="button" class="btn" style="padding:6px; background:#ef4444; color:white; min-width:30px;" onclick="toggleActivoRV('${p.id}', 0)" title="Desactivar y ocultar">🚫</button>`
                    : `<button type="button" class="btn" style="padding:6px; background:#10b981; color:white; min-width:30px;" onclick="toggleActivoRV('${p.id}', 1)" title="Reactivar">✅</button>`;
                
                html += `<div style="background:#1e293b; margin-bottom:10px; padding:12px; border-radius:8px; border:1px solid #334155; font-size:13px; display:flex; justify-content:space-between; align-items:center; opacity:${opacity};">
                    <div><strong style="color:#f8fafc;">🛣️ ${p.nombre}</strong>${badgeActivo}<br><span style="color:#94a3b8; font-size:11px;">Tipo: ${p.tipo} | Estado: ${p.estado}</span></div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn" style="padding:6px; background:#4f46e5; color:white; min-width:30px;" onclick="centrarEnRV('${p.id}')" title="Ver en mapa">👁️</button>
                        <button type="button" class="btn" style="padding:6px; background:#8b5cf6; color:white; min-width:30px;" onclick="abrirGaleriaRV('${p.id}', '${p.nombre.replace(/'/g, "\\'")}')" title="Galería de Fotos">📸</button>
                        <button type="button" class="btn" style="padding:6px; background:#3b82f6; color:white; min-width:30px;" onclick="editarRV('${p.id}')" title="Editar">✏️</button>
                        ${btnToggle}
                    </div>
                </div>`;
            });
            
            if (count === 0) html = '<p style="color:#64748b; font-size:12px;">No se encontraron vías con esos filtros.</p>';
            
            cont.innerHTML = html;
            if (contadorEl) contadorEl.textContent = `Mostrando ${count} de ${total} vías`;
        }

        function centrarEnRV(id) {
            const feature = rvGeoJSON.features.find(f => f.properties.id === id);
            if (!feature) return;

            const coords = feature.geometry.coordinates;
            if (coords.length < 2) return;

            const bounds = coords.reduce((bounds, coord) => {
                return bounds.extend(coord);
            }, new maplibregl.LngLatBounds(coords[0], coords[0]));

            map.fitBounds(bounds, { padding: 100, maxZoom: 16 });

            const highlightSource = map.getSource('highlight-source');
            if (highlightSource) {
                highlightSource.setData(feature.geometry);

                if (highlightTimeout) clearTimeout(highlightTimeout);
                highlightTimeout = setTimeout(() => {
                    highlightSource.setData({ type: 'Feature', geometry: { type: 'LineString', coordinates: [] } });
                }, 3000);
            }
        }

        function editarRV(id) {
            const feature = rvGeoJSON.features.find(f => f.properties.id === id);
            if (!feature) return;
            
            if (feature.properties.datos_edicion && Array.isArray(feature.properties.datos_edicion)) {
                rvNodes = feature.properties.datos_edicion;
            } else {
                rvNodes = feature.geometry.coordinates.map(c => ({ nodo: c, control: null }));
            }
            updateDrawLayer();
            if (rvNodes.length > 0) map.flyTo({ center: rvNodes[0].nodo, zoom: 15 });

            document.getElementById('rvId').value = id;
            document.getElementById('rvNombre').value = feature.properties.nombre || '';
            document.getElementById('rvTipo').value = feature.properties.tipo || 'Local';
            document.getElementById('rvEstado').value = feature.properties.estado || 'En estudios';
            document.getElementById('rvColor').value = feature.properties.color || '#3b82f6';
            document.getElementById('rvDesc').value = feature.properties.descripcion || '';
            
            // RV3-C2: Nuevos campos estratégicos seguros
            document.getElementById('rvMensaje').value = feature.properties.mensaje_principal || '';
            document.getElementById('rvDistrito').value = feature.properties.distrito || '';
            document.getElementById('rvSector').value = feature.properties.sector || '';
            document.getElementById('rvDesde').value = feature.properties.tramo_desde || '';
            document.getElementById('rvHasta').value = feature.properties.tramo_hasta || '';
            document.getElementById('rvLongitud').value = feature.properties.longitud || '';
            document.getElementById('rvLongitudValor').value = feature.properties.longitud_valor || '';
            document.getElementById('rvLongitudUnidad').value = feature.properties.longitud_unidad || 'metros';
            document.getElementById('rvLongitudCuadras').value = feature.properties.longitud_cuadras || '';
            document.getElementById('rvBeneficiarios').value = feature.properties.beneficiarios || '';
            document.getElementById('rvAntes').value = feature.properties.situacion_antes || '';
            document.getElementById('rvAhora').value = feature.properties.situacion_ahora || '';
            document.getElementById('rvAvance').value = feature.properties.avance_fisico || '';
            document.getElementById('rvMonto').value = feature.properties.monto_inversion || '';
            document.getElementById('rvInicio').value = feature.properties.fecha_inicio || '';
            document.getElementById('rvEntrega').value = feature.properties.fecha_entrega || '';
            
            document.getElementById('panelListaRV').classList.remove('active');
            document.getElementById('rvDrawPanel').style.display = 'flex';
            document.getElementById('panelFormularioRV').classList.add('active');
            document.getElementById('btnGuardarRv').textContent = '💾 Actualizar Tramo';
        }

        async function toggleActivoRV(id, activo) {
            const accionTexto = activo === 1 ? 'reactivar' : 'desactivar (ocultar del mapa público)';
            if (!confirm(`⚠️ ¿Estás seguro de ${accionTexto} este tramo vial?`)) return;
            try {
                const res = await fetch('mapa_redvial_api.php?action=toggle_activo', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: id, activo: activo}) });
                const data = await res.json();
                if (data.ok) { 
                    if (map && map.getSource('tramos-viales')) map.getSource('tramos-viales').setData('mapa_redvial_api.php?action=geojson'); 
                    fetchListaRV(); 
                    rvCancel();
                } else { showToast('Error: ' + data.error, 'error'); }
            } catch(e) { showToast('Error de conexión al servidor.', 'error'); }
        }
        
        // ==========================================
        // LÓGICA DE FOTOS RED VIAL (RV3-B2)
        // ==========================================
        let currentGalleryTramoId = null;

        function abrirGaleriaRV(id, nombre) {
            currentGalleryTramoId = id;
            document.getElementById('galeriaTramoNombre').textContent = 'Vía: ' + nombre;
            document.getElementById('panelListaRV').classList.remove('active');
            document.getElementById('panelGaleriaRV').classList.add('active');
            document.getElementById('uploadFotoMsg').innerHTML = '';
            document.getElementById('rvFotoInput').value = '';
            fetchFotosRV();
        }

        function cerrarGaleriaRV() {
            document.getElementById('panelGaleriaRV').classList.remove('active');
            document.getElementById('panelListaRV').classList.add('active');
            currentGalleryTramoId = null;
        }

        async function fetchFotosRV() {
            if (!currentGalleryTramoId) return;
            const container = document.getElementById('listaFotosRVContainer');
            container.innerHTML = '<div style="text-align:center; color:#94a3b8; font-size: 12px;">⏳ Cargando fotos...</div>';
            try {
                const res = await fetch(`fotos_redvial_api.php?action=listar_admin&tramo_id=${currentGalleryTramoId}`);
                const data = await res.json();
                if (data.ok) renderFotosRV(data.fotos);
                else container.innerHTML = `<div style="color:#ef4444; font-size: 12px; text-align:center;">❌ Error: ${data.error}</div>`;
            } catch (e) { container.innerHTML = `<div style="color:#ef4444; font-size: 12px; text-align:center;">❌ Error de conexión al cargar fotos.</div>`; }
        }

        function renderFotosRV(fotos) {
            const container = document.getElementById('listaFotosRVContainer');
            if (!fotos || fotos.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:#64748b; font-size: 12px;">No hay fotos para esta vía.</div>';
                return;
            }
            let html = '';
            fotos.forEach(f => {
                const isPortada = f.tipo === 'portada';
                const isActivo = String(f.activo) === '1';
                const badgeTipo = isPortada ? '<span style="background:#10b981; color:white; padding:2px 6px; border-radius:4px; font-size:10px;">PORTADA</span>' : `<span style="background:#3b82f6; color:white; padding:2px 6px; border-radius:4px; font-size:10px; text-transform:uppercase;">${f.tipo}</span>`;
                const badgeEstado = isActivo ? '' : '<span style="background:#ef4444; color:white; padding:2px 6px; border-radius:4px; font-size:10px;">INACTIVA</span>';
                
                html += `
                <div style="background:#1e293b; margin-bottom:10px; padding:10px; border-radius:8px; border:1px solid #334155; opacity: ${isActivo ? '1' : '0.5'}; display: flex; gap: 10px; align-items: center; transition: 0.2s;">
                    <div style="width: 70px; height: 70px; flex-shrink: 0; background: #020617; border-radius: 6px; overflow: hidden; border: 1px solid #475569;">
                        <img src="../universoobras/IMG/red-vial/${currentGalleryTramoId}/${f.archivo_thumb}?v=${Date.now()}" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='https://via.placeholder.com/70x70/1e293b/94a3b8?text=Error'">
                    </div>
                    <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 6px;">
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">${badgeTipo} ${badgeEstado}</div>
                        <div style="display:flex; gap: 4px;">
                            ${!isPortada ? `<button class="btn" style="padding:6px; font-size:10px; background:#10b981; color:white;" onclick="marcarPortadaRV(${f.id})">⭐ Portada</button>` : ''}
                            <button class="btn" style="padding:6px; font-size:10px; background:${isActivo ? '#ef4444' : '#3b82f6'}; color:white;" onclick="toggleActivoFotoRV(${f.id}, ${isActivo ? 0 : 1})">${isActivo ? '🚫 Ocultar' : '✅ Activar'}</button>
                        </div>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        }

        document.getElementById('formSubirFotoRV')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!currentGalleryTramoId) return;
            const btn = document.getElementById('btnSubirFotoRV'); const msg = document.getElementById('uploadFotoMsg');
            const fileInput = document.getElementById('rvFotoInput'); const tipoInput = document.getElementById('rvFotoTipo');
            if (fileInput.files.length === 0) return;
            
            btn.disabled = true; btn.textContent = '⏳ Subiendo...'; msg.innerHTML = '';
            const fd = new FormData(); fd.append('action', 'upload'); fd.append('tramo_string_id', currentGalleryTramoId); fd.append('tipo', tipoInput.value); fd.append('foto', fileInput.files[0]);
            
            try {
                const res = await fetch('fotos_redvial_api.php?action=upload', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) { msg.innerHTML = '<span style="color:#10b981;">✅ Foto subida exitosamente</span>'; fileInput.value = ''; fetchFotosRV(); } 
                else { msg.innerHTML = `<span style="color:#ef4444;">❌ ${data.error}</span>`; }
            } catch (err) { msg.innerHTML = '<span style="color:#ef4444;">❌ Error de conexión al servidor.</span>'; } 
            finally { btn.disabled = false; btn.textContent = 'Subir Foto'; setTimeout(() => { if(msg.textContent.includes('✅')) msg.innerHTML = ''; }, 3000); }
        });

        async function marcarPortadaRV(fotoId) {
            if (!confirm('¿Marcar esta foto como la portada principal? La anterior pasará a ser galería normal.')) return;
            try { const res = await fetch('fotos_redvial_api.php?action=update_meta', { method: 'POST', body: JSON.stringify({id: fotoId, tipo: 'portada'}) }); const data = await res.json(); if (data.ok) fetchFotosRV(); else alert('Error: ' + data.error); } catch (e) { alert('Error de conexión'); }
        }
        async function toggleActivoFotoRV(fotoId, activo) {
            if (!confirm(`¿Estás seguro de ${activo ? 'mostrar' : 'ocultar'} esta foto?`)) return;
            try { const res = await fetch('fotos_redvial_api.php?action=toggle_activo', { method: 'POST', body: JSON.stringify({id: fotoId, activo: activo}) }); const data = await res.json(); if (data.ok) fetchFotosRV(); else alert('Error: ' + data.error); } catch (e) { alert('Error de conexión'); }
        }

        // Enviar Formulario
        document.getElementById('refForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            const isEdit = document.getElementById('refId').value !== '';
            btn.disabled = true; btn.textContent = isEdit ? '⏳ Actualizando...' : '⏳ Guardando...';
            
            const payload = { lat: parseFloat(document.getElementById('inpLat').value), lng: parseFloat(document.getElementById('inpLng').value), nombre: document.getElementById('inpNombre').value, nombre_corto: document.getElementById('inpCorto').value, categoria: document.getElementById('inpCat').value, icon_type: document.getElementById('inpIcon').value, min_zoom: parseInt(document.getElementById('inpZoom').value) };
            if (isEdit) payload.id = parseInt(document.getElementById('refId').value);
            
            try {
                const res = await fetch(`mapa_referencias_api.php?action=${isEdit ? 'update' : 'create'}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.ok) {
                    if (map && map.getSource('referencias')) map.getSource('referencias').setData('mapa_referencias_api.php?action=geojson');
                    cerrarPanel();
                } else { showToast('Error: ' + data.error, 'error'); }
            } catch (err) { showToast('Error de conexión al servidor.', 'error'); } 
            finally { btn.disabled = false; btn.textContent = isEdit ? '💾 Actualizar Referencia' : '💾 Guardar Referencia'; }
        });

        // Enviar Formulario de Red Vial
        document.getElementById('rvForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (rvNodes.length < 2) return showToast("Necesitas al menos 2 puntos para guardar una vía.", "warning");
            
            const btn = document.getElementById('btnGuardarRv');
            const isEdit = document.getElementById('rvId').value !== '';
            btn.disabled = true; btn.textContent = isEdit ? '⏳ Actualizando Tramo...' : '⏳ Guardando Tramo...';
            
            const bakedCoords = getBakedCoords(rvNodes);
            const payload = { 
                nombre: document.getElementById('rvNombre').value, 
                tipo: document.getElementById('rvTipo').value, 
                estado: document.getElementById('rvEstado').value, 
                color: document.getElementById('rvColor').value, 
                descripcion: document.getElementById('rvDesc').value, 
                coordenadas: bakedCoords, 
                datos_edicion: rvNodes,
                mensaje_principal: document.getElementById('rvMensaje').value,
                distrito: document.getElementById('rvDistrito').value,
                sector: document.getElementById('rvSector').value,
                tramo_desde: document.getElementById('rvDesde').value,
                tramo_hasta: document.getElementById('rvHasta').value,
                longitud: document.getElementById('rvLongitud').value,
                longitud_valor: document.getElementById('rvLongitudValor').value,
                longitud_unidad: document.getElementById('rvLongitudUnidad').value,
                longitud_cuadras: document.getElementById('rvLongitudCuadras').value,
                beneficiarios: document.getElementById('rvBeneficiarios').value,
                situacion_antes: document.getElementById('rvAntes').value,
                situacion_ahora: document.getElementById('rvAhora').value,
                avance_fisico: document.getElementById('rvAvance').value,
                monto_inversion: document.getElementById('rvMonto').value,
                fecha_inicio: document.getElementById('rvInicio').value,
                fecha_entrega: document.getElementById('rvEntrega').value
            };
            if (isEdit) payload.id = document.getElementById('rvId').value;
            
            try {
                const res = await fetch(`mapa_redvial_api.php?action=${isEdit ? 'update' : 'create'}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.ok) {
                    if (map && map.getSource('tramos-viales')) map.getSource('tramos-viales').setData('mapa_redvial_api.php?action=geojson');
                    rvCancel();
                    showToast(isEdit ? "Tramo actualizado con éxito." : "Tramo guardado con éxito.", "success");
                } else { showToast('Error: ' + data.error, 'error'); }
            } catch (err) { showToast('Error de conexión al guardar.', 'error'); } 
            finally { btn.disabled = false; btn.textContent = isEdit ? '💾 Actualizar Tramo' : '💾 Guardar Tramo'; }
        });
    </script>
</body>
</html>
