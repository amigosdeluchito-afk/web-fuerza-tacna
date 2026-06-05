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
        .card { width: 100%; max-width: 900px; background: #0b1020; border-radius: 18px; padding: 24px 28px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7); border: 1px solid rgba(148, 163, 184, 0.15); }
        
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
        .btn-eliminar { background: transparent; color: #ef4444; border: 1px solid #ef4444; opacity: 0.5; cursor: not-allowed; }
        
        .status-badge { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .status-oculto { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }
        .status-normal { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; }
        
        .loader { text-align: center; padding: 20px; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <header class="app-header">
      <nav>
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php">➕ Agregar Obra</a>
        <a href="editar_obra.php">✏️ Editar Obra y Fotos</a>
        <a href="gestionar_visibilidad.php" class="active">👁️ Ocultar/Eliminar</a>
        <a href="segmentos.php">🗂️ Segmentos</a>
        <a href="cronologia.php">⏳ Cronología</a>
        <a href="ia_respuestas.php">🧠 Cerebro IA</a>
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
                            <th style="width: 35%;">Nombre de la Obra</th>
                            <th style="width: 15%;">Distrito</th>
                            <th style="width: 20%;">Estado Actual</th>
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
                const resp = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&sheet=SEGMENTOS&range=A:D&headers=1`);
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
                const url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&sheet=${encodeURIComponent(segmento)}&range=A:G&headers=1`;
                const resp = await fetch(url);
                const json = parseGviz(await resp.text());
                
                obrasActuales = (json.table.rows || []).map((r, idx) => ({
                    filaExcel: idx + 2, // Fila 1 es cabecera, así que idx 0 es fila 2
                    nombre: r.c[0]?.v || '',
                    estado: r.c[1]?.v || '',
                    distrito: r.c[6]?.v || '-'
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
                        <button class="btn btn-eliminar" title="El borrado definitivo estará disponible en la Fase 2">🗑️ Eliminar</button>
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

        // Inicializar
        cargarSegmentos();
    </script>
</body>
</html>