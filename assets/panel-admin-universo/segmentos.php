<?php
require_once __DIR__ . '/config.php';
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestor de Segmentos – Panel</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; padding-bottom: 40px; }
    .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
    .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
    .app-header nav a.active { color: #ffffff; font-weight: 600; }
    .app-header nav a:hover { color: #e5e7eb; }
    .app-header .user { font-size: 13px; color: #9ca3af; }
    .app-main { margin-top: 72px; display: flex; justify-content: center; padding: 20px; }
    .card { width: 100%; max-width: 900px; background: #0b1020; border-radius: 18px; padding: 24px 28px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7); border: 1px solid rgba(148, 163, 184, 0.15); }
    h1 { margin-top: 0; font-size: 22px; color: #f9fafb; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; background: #020617; border-radius: 8px; overflow: hidden; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #1f2937; text-align: left; }
    th { background: #1e293b; color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 12px; }
    tr:hover { background: #0f172a; }
    tr[draggable="true"] { cursor: grab; }
    tr.dragging { opacity: 0.5; background: #1e293b; }
    tr.drag-over-target { box-shadow: inset 0 2px 0 #2563eb; }
    .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; border: none; transition: background 0.2s; }
    .btn-primary { background: #2563eb; color: white; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-warning { background: #d97706; color: white; }
    .btn-warning:hover { background: #b45309; }
    
    /* Modal Estilos */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-content { background: #0f172a; width: 400px; border-radius: 12px; padding: 24px; border: 1px solid #1f2937; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
    .modal-content h3 { margin-top: 0; color: #f8fafc; }
    .modal-content label { display: block; margin-top: 15px; margin-bottom: 5px; color: #94a3b8; font-size: 13px; }
    .modal-content input { width: 100%; padding: 10px; background: #020617; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
    .tag { background: #1e293b; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #93c5fd; font-size: 12px; }

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
      <nav style="display:flex; align-items:center;">
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">📷 Fotos</a>
        <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">➕ Agregar Obra</a>
        <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">✏️ Editar Obra</a>
        <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">👁️ Visibilidad</a>
        <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">🗂️ Segmentos</a>
        <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">⏳ Cronología</a>
        <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">👥 Candidatos</a>
        
        <div class="dropdown">
          <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['ia_respuestas.php', 'ia_cerebro_obras.php', 'ia_conocimiento.php', 'ia_estadisticas.php']) ? 'active' : '' ?>">🧠 IA y Conocimiento ▾</button>
          <div class="dropdown-content">
            <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">🧠 Cerebro IA</a>
            <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">📚 Base Conocimiento</a>
            <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">📊 Estadísticas IA</a>
          </div>
        </div>

        <?php if (is_admin()): ?>
        <div class="dropdown">
          <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['usuarios.php', 'historial.php', 'ver_accesos.php']) ? 'active' : '' ?>">⚙️ Admin ▾</button>
          <div class="dropdown-content">
            <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
            <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
            <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos IP</a>
          </div>
        </div>
        <?php endif; ?>
      </nav>
      <div class="user">
        <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
      </div>
    </header>

    <main class="app-main">
        <div class="card">
            <h1>
                Gestión de Segmentos
                <div style="display: flex; gap: 10px;">
                    <button class="btn" id="btnGuardarOrden" style="display:none; background: #10b981; color: white;">💾 Guardar Orden</button>
                    <button class="btn btn-primary" onclick="abrirModalCrear()">+ Nuevo Segmento</button>
                </div>
            </h1>
            <p style="color: #94a3b8; font-size: 13.5px;">Aquí administras las categorías del mapa. Al crear o editar, el sistema se encargará automáticamente de duplicar y renombrar las pestañas en tu archivo de Excel base. <br>
            <span style="color: #60a5fa; display: inline-block; margin-top: 5px;">💡 <strong>Tip:</strong> Puedes reordenar los segmentos haciendo clic y arrastrando el icono <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><line x1="4" y1="8" x2="20" y2="8"></line><line x1="4" y1="16" x2="20" y2="16"></line></svg> de cualquier fila.</span></p>
            
            <table id="tablaSegmentos">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">Orden</th>
                        <th>ID Interno</th>
                        <th>Nombre Visible (Público)</th>
                        <th>Pestaña Excel Real</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6" style="text-align: center;">Cargando segmentos...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal-overlay" id="modalSegmento">
        <div class="modal-content">
            <h3 id="modalTitle">Nuevo Segmento</h3>
            <form id="formSegmento">
                <input type="hidden" id="inputIdSegmento">
                
                <label>Nombre Visible (El que ve el público en los botones):</label>
                <input type="text" id="inputNombreVisible" required placeholder="Ej. Seguridad Ciudadana">
                
                <p style="font-size: 11.5px; color: #64748b; margin-top: 10px; background: rgba(59, 130, 246, 0.1); padding: 8px; border-radius: 6px;">
                    💡 <strong>Nota Técnica:</strong> El nombre de la pestaña de Excel se generará automáticamente (Ej. SEGURIDAD_CIUDADANA).
                </p>

                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #334155; color: white;" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Segmento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let isEditMode = false;

        async function cargarTabla() {
            const tbody = document.querySelector('#tablaSegmentos tbody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>';
            
            try {
                const resp = await fetch('api_segmentos.php?action=listar');
                const data = await resp.json();
                
                if (data.ok && data.segmentos) {
                    // Ordenar por la columna 'orden' antes de renderizar
                    data.segmentos.sort((a, b) => (a.orden || 999) - (b.orden || 999));
                    tbody.innerHTML = '';
                    data.segmentos.forEach(seg => {
                        tbody.innerHTML += `
                            <tr draggable="true" data-id="${seg.id_segmento}">
                                <td style="cursor: grab; text-align: center; color: #64748b;" title="Arrastrar para reordenar">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="8" x2="20" y2="8"></line><line x1="4" y1="16" x2="20" y2="16"></line></svg>
                                </td>
                                <td><span class="tag">${seg.id_segmento}</span></td>
                                <td style="font-weight: bold; color: #fff;">${seg.nombre_visible}</td>
                                <td><span class="tag" style="color:#10b981;">${seg.nombre_pestana}</span></td>
                                <td>${seg.activo === 'SI' ? '✅ Sí' : '❌ No'}</td>
                                <td>
                                    <button class="btn btn-warning" onclick="abrirModalEditar('${seg.id_segmento}', '${seg.nombre_visible}')">✏️ Editar Nombre</button>
                                </td>
                            </tr>
                        `;
                    });
                    initDragAndDrop(); // Activar drag & drop después de renderizar
                } else {
                    tbody.innerHTML = '<tr><td colspan="6">No se encontraron segmentos.</td></tr>';
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" style="color:#ef4444;">Error de conexión.</td></tr>`;
            }
        }

        function initDragAndDrop() {
            const tbody = document.querySelector('#tablaSegmentos tbody');
            const rows = tbody.querySelectorAll('tr[draggable="true"]');
            const btnGuardarOrden = document.getElementById('btnGuardarOrden');
            let draggingElement = null;

            rows.forEach(row => {
                row.addEventListener('dragstart', () => {
                    draggingElement = row;
                    setTimeout(() => row.classList.add('dragging'), 0);
                });

                row.addEventListener('dragend', () => {
                    if (draggingElement) draggingElement.classList.remove('dragging');
                    draggingElement = null;
                    document.querySelectorAll('.drag-over-target').forEach(el => el.classList.remove('drag-over-target'));
                });

                row.addEventListener('dragover', e => {
                    e.preventDefault();
                    const target = e.target.closest('tr');
                    if (target && target !== draggingElement) {
                        document.querySelectorAll('.drag-over-target').forEach(el => el.classList.remove('drag-over-target'));
                        target.classList.add('drag-over-target');
                    }
                });

                row.addEventListener('dragleave', e => {
                    e.target.closest('tr')?.classList.remove('drag-over-target');
                });

                row.addEventListener('drop', e => {
                    e.preventDefault();
                    const target = e.target.closest('tr');
                    if (target && target !== draggingElement) {
                        const allRows = [...tbody.querySelectorAll('tr')];
                        const fromIndex = allRows.indexOf(draggingElement);
                        const toIndex = allRows.indexOf(target);

                        if (fromIndex < toIndex) target.parentNode.insertBefore(draggingElement, target.nextSibling);
                        else target.parentNode.insertBefore(draggingElement, target);
                        
                        btnGuardarOrden.style.display = 'inline-block';
                    }
                    document.querySelectorAll('.drag-over-target').forEach(el => el.classList.remove('drag-over-target'));
                });
            });

            btnGuardarOrden.addEventListener('click', async () => {
                const orderedIds = [...tbody.querySelectorAll('tr')].map(row => row.dataset.id);
                const btn = btnGuardarOrden;
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⏳ Guardando...';

                const fd = new FormData();
                fd.append('action', 'reordenar');
                fd.append('orden_ids', JSON.stringify(orderedIds));

                try {
                    const resp = await fetch('api_segmentos.php', { method: 'POST', body: fd });
                    const data = await resp.json();
                    if (data.ok) {
                        btn.style.display = 'none';
                        await cargarTabla(); 
                    } else { alert('Error: ' + data.error); }
                } catch (err) {
                    alert('Error de conexión al guardar el orden.');
                } finally { btn.disabled = false; btn.textContent = originalText; }
            });
        }

        function abrirModalCrear() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'Crear Nuevo Segmento';
            document.getElementById('inputIdSegmento').value = '';
            document.getElementById('inputNombreVisible').value = '';
            document.getElementById('modalSegmento').classList.add('active');
        }

        function abrirModalEditar(id, nombre) {
            isEditMode = true;
            document.getElementById('modalTitle').textContent = 'Editar Segmento (' + id + ')';
            document.getElementById('inputIdSegmento').value = id;
            document.getElementById('inputNombreVisible').value = nombre;
            document.getElementById('modalSegmento').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalSegmento').classList.remove('active');
        }

        document.getElementById('formSegmento').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Procesando en Google Sheets...';

            const fd = new FormData();
            fd.append('action', isEditMode ? 'editar' : 'crear');
            fd.append('nombre_visible', document.getElementById('inputNombreVisible').value);
            
            if (isEditMode) {
                fd.append('id_segmento', document.getElementById('inputIdSegmento').value);
            }

            try {
                const resp = await fetch('api_segmentos.php', { method: 'POST', body: fd });
                const text = await resp.text(); // Capturamos texto por si Google tira un error HTML Fatal
                const data = JSON.parse(text);
                
                if (data.ok) {
                    cerrarModal();
                    cargarTabla();
                } else {
                    alert("Error: " + data.error);
                }
            } catch (err) {
                alert("Ocurrió un error de red o interno. Revisa la consola.");
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });

        // Iniciar
        cargarTabla();
    </script>
</body>
</html>