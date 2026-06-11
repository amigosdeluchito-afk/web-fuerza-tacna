<?php
require_once __DIR__ . '/config.php';
require_login();

// --- MANEJO DE PETICIONES AJAX PARA CAMBIAR ESTADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_visibilidad') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $rutaCredenciales = __DIR__ . '/data/credenciales.json';
        $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig($rutaCredenciales);
        $service = new \Google_Service_Sheets($client);

        $segmento = $_POST['segmento'] ?? '';
        $fila = (int)($_POST['fila'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';

        if ($segmento !== '' && $fila >= 2 && $nuevo_estado !== '') {
            // Columna B es donde se guarda el estado en el Excel
            $rango = $segmento . '!B' . $fila; 
            
            $body = new \Google_Service_Sheets_ValueRange(['values' => [[$nuevo_estado]]]);
            $params = ['valueInputOption' => 'RAW'];
            
            $service->spreadsheets_values->update($spreadsheetId, $rango, $body, $params);
            
            log_action('obra_visibilidad', "Cambió visibilidad a '$nuevo_estado' en $segmento (Fila $fila)");
            
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos recibidos.']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- MANEJO DE PETICIONES AJAX PARA ELIMINAR DEFINITIVAMENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_definitivo') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $rutaCredenciales = __DIR__ . '/data/credenciales.json';
        $spreadsheetId = '1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI';

        $client = new \Google_Client();
        $client->setApplicationName('Panel de Obras Fuerza Tacna');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig($rutaCredenciales);
        $service = new \Google_Service_Sheets($client);

        $segmento = $_POST['segmento'] ?? '';
        $fila = (int)($_POST['fila'] ?? 0);
        $carpeta = $_POST['carpeta'] ?? '';

        if ($segmento !== '' && $fila >= 2) {
            // 1. Encontrar el ID numérico de la pestaña del segmento
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            $sheetId = null;
            foreach ($spreadsheet->getSheets() as $sheet) {
                if (strtoupper($sheet->getProperties()->getTitle()) === strtoupper($segmento)) {
                    $sheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($sheetId !== null) {
                // 2. Eliminar la fila en Google Sheets (subiendo las de abajo)
                $request = new \Google_Service_Sheets_Request([
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $fila - 1, // Es 0-indexado e inclusivo
                            'endIndex' => $fila      // Exclusivo
                        ]
                    ]
                ]);
                $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => [$request]]);
                $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
                
                // 3. Eliminar carpeta de fotos del servidor (si existe)
                if (!empty($carpeta) && $carpeta !== '-') {
                    $dirAEliminar = rtrim($GLOBALS['FOTOS_BASE'], '/\\') . '/' . strtolower($segmento) . '/' . $carpeta;
                    if (is_dir($dirAEliminar)) {
                        $files = array_diff(scandir($dirAEliminar), ['.', '..']);
                        foreach ($files as $file) {
                            @unlink("$dirAEliminar/$file");
                        }
                        @rmdir($dirAEliminar);
                    }
                }

                log_action('obra_eliminar', "Eliminó definitivamente la obra de $segmento (Fila $fila)");
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'No se encontró la pestaña en el Excel.']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos recibidos.']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ocultar / Eliminar Obras</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; padding-bottom: 40px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .app-main { margin-top: 72px; display: flex; justify-content: center; padding: 20px; }
        .card { width: 100%; max-width: 1050px; background: #0b1020; border-radius: 18px; padding: 24px 28px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7); border: 1px solid rgba(148, 163, 184, 0.15); }
        
        h1 { margin-top: 0; font-size: 22px; color: #f9fafb; margin-bottom: 20px; }
        label { font-size: 13px; color: #e5e7eb; display: block; margin-top: 15px; margin-bottom: 4px; }
        select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #020617; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        select:focus { border-color: #2563eb; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; background: #020617; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #1f2937; text-align: left; }
        th { background: #1e293b; color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        tr:hover { background: #0f172a; }
        
        .btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; border: none; transition: background 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-ocultar { background: #475569; color: #f8fafc; }
        .btn-ocultar:hover { background: #334155; }
        .btn-mostrar { background: #10b981; color: #f8fafc; }
        .btn-mostrar:hover { background: #059669; }
        .btn-eliminar { background: #ef4444; color: #ffffff; border: none; }
        .btn-eliminar:hover { background: #dc2626; }
        
        .status-badge { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .status-oculto { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }
        .status-normal { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; }
        
        .loader { text-align: center; padding: 20px; color: #94a3b8; font-style: italic; }

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
        <?php if (is_admin()): ?>
        <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
        <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
        <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos</a>
        <?php endif; ?>
      </nav>
      <div class="user">
        <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
      </div>
    </header>

    <main class="app-main">
        <div class="card">
            <h1>👁️ Gestor de Visibilidad</h1>
            <p style="color: #94a3b8; font-size: 13.5px; margin-top: -10px; margin-bottom: 20px;">
                Selecciona un segmento para ver todas sus obras. Puedes ocultarlas para que desaparezcan del mapa sin perder su información ni sus fotos.
            </p>
            
            <div>
                <label>Selecciona el Segmento:</label>
                <select id="selectSegmento">
                    <option value="">Cargando segmentos...</option>
                </select>
            </div>

            <div style="overflow-x: auto;">
                <table id="tablaObras">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Fila</th>
                            <th style="width: 30%;">Nombre de la Obra</th>
                            <th style="width: 15%;">Distrito</th>
                            <th style="width: 25%;">Estado Actual</th>
                            <th style="width: 25%;">Acciones Rápidas</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyObras">
                        <tr>
                            <td colspan="5" class="loader">👈 Primero selecciona un segmento para cargar las obras.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq`;
        let obrasActuales = [];
        const selectSegmento = document.getElementById('selectSegmento');
        const tbodyObras = document.getElementById('tbodyObras');

        function parseGviz(text) {
            const m = text.match(/setResponse\(([\s\S]+)\);?/);
            if (!m) throw new Error("Error parseo Gviz");
            return JSON.parse(m[1]);
        }

        // 1. Cargar segmentos al inicio
        async function cargarSegmentos() {
            try {
                const tqBuster = encodeURIComponent(`select * offset 0`);
                const resp = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&tq=${tqBuster}&sheet=SEGMENTOS&range=A:D&headers=1`);
                const json = parseGviz(await resp.text());
                
                selectSegmento.innerHTML = '<option value="">-- Elige un segmento --</option>';
                
                const rows = json.table.rows || [];
                rows.forEach(r => {
                    if (r.c && r.c[2]?.v && String(r.c[3]?.v || '').toUpperCase() === 'SI') {
                        const opt = document.createElement('option');
                        opt.value = r.c[2].v; // Key (Nombre de la pestaña)
                        opt.textContent = r.c[1]?.v || r.c[2].v; // Nombre visible
                        selectSegmento.appendChild(opt);
                    }
                });
            } catch (error) {
                selectSegmento.innerHTML = '<option value="">Error al cargar segmentos</option>';
            }
        }

        // 2. Cargar obras al seleccionar un segmento
        selectSegmento.addEventListener('change', async function() {
            const segmento = this.value;
            if (!segmento) {
                tbodyObras.innerHTML = '<tr><td colspan="5" class="loader">👈 Primero selecciona un segmento para cargar las obras.</td></tr>';
                return;
            }

            tbodyObras.innerHTML = '<tr><td colspan="5" class="loader">⏳ Descargando datos desde Excel...</td></tr>';

            try {
                const tqBuster = encodeURIComponent(`select * offset 0`);
                const url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&tq=${tqBuster}&sheet=${encodeURIComponent(segmento)}&range=A:H&headers=1`;
                const resp = await fetch(url);
                const json = parseGviz(await resp.text());
                
                obrasActuales = (json.table.rows || []).map((r, idx) => ({
                    filaExcel: idx + 2, // Fila 1 es cabecera, así que idx 0 es fila 2
                    nombre: r.c[0]?.v || '',
                    estado: r.c[1]?.v || '',
                    distrito: r.c[6]?.v || '-',
                    carpeta: r.c[7]?.v || ''
                })).filter(o => o.nombre !== ''); // Filtrar filas vacías

                renderTable();
            } catch (error) {
                tbodyObras.innerHTML = '<tr><td colspan="5" style="color:#ef4444; text-align:center; padding:20px;">❌ Error al leer el Excel.</td></tr>';
            }
        });

        // 3. Dibujar la tabla
        function renderTable() {
            if (obrasActuales.length === 0) {
                tbodyObras.innerHTML = '<tr><td colspan="5" class="loader">No hay obras en este segmento.</td></tr>';
                return;
            }

            tbodyObras.innerHTML = '';
            obrasActuales.forEach(obra => {
                const isOculto = obra.estado.startsWith('Oculto');
                const badgeClass = isOculto ? 'status-oculto' : 'status-normal';
                const badgeText = isOculto ? '🚫 ' + obra.estado : '✅ Visible';
                
                let actionButton = '';
                if (isOculto) {
                    actionButton = `<button class="btn btn-mostrar" onclick="toggleVisibilidad(${obra.filaExcel}, '${obra.estado}', 'mostrar', this)">👁️ Mostrar</button>`;
                } else {
                    actionButton = `<button class="btn btn-ocultar" onclick="toggleVisibilidad(${obra.filaExcel}, '${obra.estado}', 'ocultar', this)">🚫 Ocultar</button>`;
                }

                const tr = document.createElement('tr');
                if(isOculto) tr.style.opacity = '0.7';

                tr.innerHTML = `
                    <td style="color: #64748b; font-weight: bold;">${obra.filaExcel}</td>
                    <td style="font-weight: 500; color: #fff;">${obra.nombre}</td>
                    <td style="color: #cbd5e1;">${obra.distrito}</td>
                    <td><span class="status-badge ${badgeClass}">${badgeText}</span><br><small style="color:#64748b; font-size:10px;">En Excel: ${obra.estado}</small></td>
                    <td>
                        ${actionButton}
                        <button class="btn btn-eliminar" onclick="eliminarObra(${obra.filaExcel}, '${obra.carpeta}', this)">🗑️ Eliminar</button>
                    </td>
                `;
                tbodyObras.appendChild(tr);
            });
        }

        // 4. Función AJAX para cambiar el estado
        async function toggleVisibilidad(fila, estadoActual, accion, btnEl) {
            const segmento = selectSegmento.value;
            let nuevoEstado = '';

            if (accion === 'ocultar') {
                nuevoEstado = `Oculto (Era: ${estadoActual})`;
            } else {
                // Extraer el estado original: de "Oculto (Era: Entregado)" sacar "Entregado"
                const match = estadoActual.match(/Era: (.*)\)/);
                nuevoEstado = match ? match[1] : 'En construcción'; // Fallback por si acaso
            }

            // Cambiar UI a "Cargando"
            const originalHtml = btnEl.innerHTML;
            btnEl.innerHTML = '⏳ Guardando...';
            btnEl.disabled = true;

            const fd = new FormData();
            fd.append('action', 'toggle_visibilidad');
            fd.append('segmento', segmento);
            fd.append('fila', fila);
            fd.append('nuevo_estado', nuevoEstado);

            try {
                const resp = await fetch('gestionar_visibilidad.php', { method: 'POST', body: fd });
                const data = await resp.json();

                if (data.ok) {
                    // Actualizar estado local y re-renderizar
                    const obraObj = obrasActuales.find(o => o.filaExcel === fila);
                    if (obraObj) obraObj.estado = nuevoEstado;
                    renderTable();
                } else {
                    alert("Error: " + data.error);
                    btnEl.innerHTML = originalHtml;
                    btnEl.disabled = false;
                }
            } catch (err) {
                alert("Error de conexión al servidor.");
                btnEl.innerHTML = originalHtml;
                btnEl.disabled = false;
            }
        }

        // 5. Función AJAX para Eliminar Definitivamente
        async function eliminarObra(fila, carpeta, btnEl) {
            if (!confirm("⚠️ ¡ADVERTENCIA CRÍTICA!\n\n¿Estás seguro de que deseas ELIMINAR esta obra para siempre?\n\n- Se borrará la fila del Excel.\n- Se destruirán todas sus fotos del servidor.\n\nEsta acción NO se puede deshacer.")) {
                return;
            }

            const segmento = selectSegmento.value;
            const originalHtml = btnEl.innerHTML;
            btnEl.innerHTML = '⏳ Destruyendo...';
            btnEl.disabled = true;

            const fd = new FormData();
            fd.append('action', 'eliminar_definitivo');
            fd.append('segmento', segmento);
            fd.append('fila', fila);
            fd.append('carpeta', carpeta);

            try {
                const resp = await fetch('gestionar_visibilidad.php', { method: 'POST', body: fd });
                const data = await resp.json();

                if (data.ok) {
                    // Recargamos el segmento completo porque los números de fila cambiaron al borrar una de en medio
                    selectSegmento.dispatchEvent(new Event('change'));
                } else {
                    alert("Error: " + data.error);
                    btnEl.innerHTML = originalHtml;
                    btnEl.disabled = false;
                }
            } catch (err) {
                alert("Error de conexión al servidor.");
                btnEl.innerHTML = originalHtml;
                btnEl.disabled = false;
            }
        }

        // Inicializar
        cargarSegmentos();
    </script>
</body>
</html>