<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();
$mensaje = '';

// ==========================================================
// LÓGICA DE GUARDADO (INDIVIDUAL Y EN LOTE)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    try {
        $stmtCheck = $db->prepare("SELECT id FROM panel_ia_conocimiento WHERE titulo = ?");
        $stmtInsert = $db->prepare("INSERT INTO panel_ia_conocimiento (categoria, titulo, contenido, palabras_clave, prioridad, estado, fuente, fecha_actualizacion) VALUES ('Obras', ?, ?, ?, 5, 1, 'Google Sheets - Obras', NOW())");
        $stmtUpdate = $db->prepare("UPDATE panel_ia_conocimiento SET contenido = ?, palabras_clave = ?, fecha_actualizacion = NOW() WHERE id = ?");

        if ($action === 'save_single') {
            $titulo = $_POST['titulo'];
            $contenido = $_POST['contenido'];
            $palabras = $_POST['palabras'];

            $stmtCheck->execute([$titulo]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($row) { $stmtUpdate->execute([$contenido, $palabras, $row['id']]); } 
            else { $stmtInsert->execute([$titulo, $contenido, $palabras]); }
            
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'save_batch') {
            $obras = json_decode($_POST['obras'], true);
            $db->beginTransaction();
            foreach ($obras as $o) {
                $stmtCheck->execute([$o['titulo']]);
                $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if ($row) { $stmtUpdate->execute([$o['contenido'], $o['palabras'], $row['id']]); } 
                else { $stmtInsert->execute([$o['titulo'], $o['contenido'], $o['palabras']]); }
            }
            $db->commit();
            echo json_encode(['ok' => true, 'mensaje' => "¡Éxito! Se sincronizaron " . count($obras) . " obras con el Cerebro de la IA."]);
            exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Obtenemos los textos que la IA ya conoce actualmente para cruzarlos con el Excel
$conocimiento_ia = [];
try {
    // Asumimos que los registros de obras tienen un identificador (en la parte 2 ajustaremos esto si es necesario)
    $stmt = $db->query("SELECT titulo, contenido FROM panel_ia_conocimiento");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $conocimiento_ia[trim($row['titulo'])] = $row['contenido'];
    }
} catch (Exception $e) {
    // Ignorar si la tabla aún no existe o hay error temporal
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cerebro de Obras - IA Panel</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        
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

        /* Layout Principal 2 Columnas */
        .app-main { margin-top: 56px; padding: 20px; display: flex; flex-direction: column; height: calc(100vh - 96px); }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 22px; color: #f9fafb; display: flex; align-items: center; gap: 10px; }
        
        .btn-sync-all { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-sync-all:hover { background: #2563eb; }

        .layout-2col { display: flex; gap: 20px; flex: 1; overflow: hidden; }
        
        /* Columna Izquierda: Lista de Obras */
        .col-lista { width: 350px; background: #0f172a; border-radius: 12px; border: 1px solid #1f2937; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
        .search-box { padding: 15px; border-bottom: 1px solid #1f2937; display: flex; flex-direction: column; gap: 10px; }
        .search-box select, .search-box input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #334155; background: #020617; color: #fff; box-sizing: border-box; outline: none; }
        .search-box select:focus, .search-box input:focus { border-color: #3b82f6; }
        .search-box select option { background: #0f172a; }
        
        .lista-obras { flex: 1; overflow-y: auto; padding: 10px; margin: 0; list-style: none; }
        .lista-obras::-webkit-scrollbar { width: 6px; }
        .lista-obras::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        
        .segment-header { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; padding: 10px 15px 5px; letter-spacing: 0.5px; background: #0f172a; position: sticky; top: 0; z-index: 10; margin-top: 5px; }
        .obra-item { padding: 12px 15px; border-radius: 8px; cursor: pointer; transition: background 0.2s; border: 1px solid transparent; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: flex-start; }
        .obra-item:hover { background: #1e293b; }
        .obra-item.active { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); }
        .obra-item-title { font-weight: 600; font-size: 13px; color: #f9fafb; margin-bottom: 4px; line-height: 1.3; }
        .obra-item-meta { font-size: 11px; color: #9ca3af; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; margin-top: 2px; }
        .dot-green { background: #10b981; box-shadow: 0 0 5px rgba(16, 185, 129, 0.5); }
        .dot-red { background: #ef4444; }

        /* Columna Derecha: Editor */
        .col-editor { flex: 1; background: #0f172a; border-radius: 12px; border: 1px solid #1f2937; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; }
        .editor-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #64748b; }
        .editor-placeholder svg { width: 64px; height: 64px; margin-bottom: 15px; opacity: 0.5; }
        
        .editor-content { display: none; flex-direction: column; min-height: 100%; }
        .excel-data-card { background: #020617; border: 1px solid #1e293b; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .excel-data-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
        .data-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .data-item label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .data-item div { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        
        .textarea-wrap { display: flex; flex-direction: column; flex: 1; min-height: 450px; margin-bottom: 20px; }
        .textarea-wrap label { font-size: 15px; color: #f9fafb; font-weight: 600; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .textarea-wrap label span { font-size: 12px; font-weight: normal; color: #9ca3af; background: #1e293b; padding: 2px 8px; border-radius: 4px; }
        
        /* Nuevos estilos para los múltiples contextos (Pestañas tipo Navegador) */
        .tabs-container { display: flex; flex-direction: column; flex: 1; background: #0f172a; border: 1px solid #1f2937; border-radius: 8px; }
        .tabs-header { display: flex; background: #020617; border-bottom: 1px solid #1f2937; overflow-x: auto; }
        .tabs-header::-webkit-scrollbar { height: 4px; }
        .tabs-header::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .tab-btn { flex: 0 0 auto; display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: transparent; border: none; border-right: 1px solid #1f2937; color: #94a3b8; font-size: 13px; font-weight: bold; cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; max-width: 180px; }
        .tab-btn span.ctx-titulo-display { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; pointer-events: none; }
        .tab-btn:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; background: rgba(59,130,246,0.1); }
        .tab-close { background: transparent; border: none; color: #ef4444; font-size: 16px; line-height: 1; cursor: pointer; padding: 0 4px; border-radius: 4px; opacity: 0.7; }
        .tab-close:hover { opacity: 1; background: rgba(239,68,68,0.2); }
        .btn-add-tab { flex: 0 0 auto; padding: 10px 15px; background: transparent; border: none; color: #10b981; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-add-tab:hover { background: rgba(16,185,129,0.1); }

        .tabs-content { display: flex; flex-direction: column; flex: 1; position: relative; }
        .tab-pane { display: none; flex-direction: column; flex: 1; padding: 0; box-sizing: border-box; }
        .tab-pane.active { display: flex; }
        .tab-pane textarea { flex: 1; width: 100%; min-height: 350px; background: transparent; border: none; color: #e2e8f0; font-size: 14px; resize: none; overflow-y: hidden; outline: none; line-height: 1.6; font-family: system-ui; padding: 15px; box-sizing: border-box; }

        .btn-save-obra { background: #10b981; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-save-obra:hover { background: #059669; }
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
    </header>

    <main class="app-main">
        <div class="header-actions">
            <h1>🏗️ Laboratorio: Cerebro de Obras</h1>
            <button class="btn-sync-all" id="btnSyncExcel">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Jalar Nuevas Obras del Excel
            </button>
        </div>

        <div class="layout-2col">
            <!-- Lista Izquierda -->
            <div class="col-lista">
                <div class="search-box">
                    <select id="selectSegmento">
                        <option value="">📁 Todos los segmentos</option>
                    </select>
                    <input type="text" id="searchInput" placeholder="Buscar obra...">
                </div>
                <ul class="lista-obras" id="obrasList">
                    <div style="padding: 20px; text-align: center; color: #64748b;">⏳ Cargando obras del Excel...</div>
                </ul>
            </div>

            <!-- Editor Derecha -->
            <div class="col-editor">
                <div class="editor-placeholder" id="editorPlaceholder">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <h2>Selecciona una obra de la lista</h2>
                    <p>Podrás visualizar sus datos oficiales e inyectarle contexto a la Inteligencia Artificial.</p>
                </div>

                <div class="editor-content" id="editorContent">
                    <div class="excel-data-card">
                        <h3>🔒 Datos Oficiales (Lectura del Excel)</h3>
                        <div class="data-grid">
                            <div class="data-item">
                                <label>Monto de Inversión</label>
                                <div id="lblMonto">S/ 0</div>
                            </div>
                            <div class="data-item">
                                <label>Ubicación</label>
                                <div id="lblUbicacion">Distrito, Provincia</div>
                            </div>
                            <div class="data-item">
                                <label>Estado Actual</label>
                                <div id="lblEstado">En Construcción</div>
                            </div>
                        </div>
                        <div class="data-item" style="margin-top: 15px;">
                            <label>Descripción Oficial (Resumen público)</label>
                            <div id="lblDescExcel" style="font-weight: normal; color: #cbd5e1; line-height: 1.5; font-size: 13px;"></div>
                        </div>
                    </div>

                    <div class="textarea-wrap">
                        <label>
                            Fuentes y Contexto Adicional
                            <span id="aiStatusBadge">🟢 Ya en Cerebro</span>
                        </label>
                        <div class="tabs-container">
                            <div class="tabs-header">
                                <div id="tabsHeaderList" style="display: flex;"></div>
                                <button type="button" class="btn-add-tab" onclick="agregarContexto(null, '', true)" title="Añadir nueva pestaña">➕ Nueva Pestaña</button>
                                <button type="button" class="btn-add-tab" onclick="abrirExtractorUrl()" title="Extraer texto de un enlace web" style="color: #3b82f6;">🌐 Extraer Link</button>
                            </div>
                            <div class="tabs-content" id="tabsContent">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: auto; padding-top: 15px;">
                        <button class="btn-save-obra" id="btnSaveObra" style="flex: 2;">🧠 Alimentar Cerebro con esta Obra</button>
                        <button class="btn-save-obra" id="btnExportWord" style="flex: 1; background: #4f46e5; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2);" onclick="exportarFichaWord()">⬇️ Exportar Ficha a Word</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Pasamos el conocimiento actual de la IA a Javascript
        const CEREBRO_IA = <?php echo json_encode($conocimiento_ia); ?>;
        
        const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";
        const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq`;
        
        let todasLasObras = [];
        let obraSeleccionada = null;
        let tabCounter = 0;

        // Formateador amigable de montos (Ej. 15000000 -> S/ 15 Millones)
        function formatMontoFriendly(val) {
            let str = String(val).trim();
            if (/[a-zA-Z]/.test(str) && !str.toLowerCase().startsWith('s/')) return str; 
            
            let num = parseFloat(str.replace(/[^\d.-]/g, '')) || 0;
            if (num === 0) return 'S/ 0';
            if (num >= 1000000) {
                let m = num / 1000000;
                return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Millones';
            } else if (num >= 1000) {
                let m = num / 1000;
                return 'S/ ' + (m % 1 === 0 ? m : m.toFixed(1)) + ' Mil';
            }
            return 'S/ ' + num.toLocaleString('en-US');
        }

        function parseGviz(text) {
            const m = text.match(/setResponse\(([\s\S]+)\);?/);
            if (!m) return {table:{rows:[]}};
            return JSON.parse(m[1]);
        }

        async function cargarObrasDesdeExcel() {
            try {
                // 1. Cargar segmentos
                const respSeg = await fetch(`${SHEET_BASE_URL}?tqx=out:json;reqId=${Date.now()}&sheet=SEGMENTOS&range=A:D&headers=1`);
                const jsonSeg = parseGviz(await respSeg.text());
                const segmentos = (jsonSeg.table.rows || [])
                    .map(r => ({ key: r.c[2]?.v, nombre: r.c[1]?.v, activo: String(r.c[3]?.v||'').toUpperCase() }))
                    .filter(s => s.key && (s.activo === 'SI' || s.activo === '1' || s.activo === 'TRUE'));
                    
                const selectSeg = document.getElementById('selectSegmento');
                segmentos.forEach(seg => {
                    const opt = document.createElement('option');
                    opt.value = seg.nombre || seg.key;
                    opt.textContent = `📁 ${seg.nombre || seg.key}`;
                    selectSeg.appendChild(opt);
                });

                // 2. Cargar obras de cada segmento
                for (const seg of segmentos) {
                    // FIX CACHÉ: Añadimos un rompe-caché a la petición (tq) para forzar a Google Sheets a darnos datos en vivo al instante.
                    const tqBuster = encodeURIComponent(`select * offset 0`);
                    let url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${new Date().getTime()}&tq=${tqBuster}&sheet=${encodeURIComponent(seg.key)}&range=A:J&headers=1`;
                    
                    let resp = await fetch(url);
                    let json = parseGviz(await resp.text());
                    
                    (json.table.rows || []).forEach(r => {
                        if(!r || !r.c) return;
                        const nombre = (r.c[0]?.v || "").trim();
                        if (nombre && !(r.c[1]?.v || "").includes('Oculto')) {
                            todasLasObras.push({
                                segmento: seg.nombre || seg.key,
                                tituloClave: "Obra: " + nombre, // Así lo guardaremos en la BD para evitar cruces
                                nombre: nombre,
                                estado: r.c[1]?.v || "Sin estado",
                                monto: r.c[2]?.v || "0",
                                provincia: r.c[5]?.v || "",
                                distrito: r.c[6]?.v || "",
                                descripcion_excel: r.c[8]?.v || r.c[8]?.f || ""
                            });
                        }
                    });
                }
                renderLista(todasLasObras);
            } catch (e) {
                document.getElementById('obrasList').innerHTML = `<div style="padding: 20px; color: #ef4444;">❌ Error cargando Excel. Verifica conexión.</div>`;
            }
        }

        function renderLista(lista) {
            const ul = document.getElementById('obrasList');
            ul.innerHTML = '';
            
            // Agrupar obras por su segmento
            const agrupado = {};
            lista.forEach(obra => {
                if (!agrupado[obra.segmento]) agrupado[obra.segmento] = [];
                agrupado[obra.segmento].push(obra);
            });
            
            Object.keys(agrupado).forEach(segNombre => {
                // Crear la etiqueta del segmento
                const header = document.createElement('div');
                header.className = 'segment-header';
                header.textContent = `📁 ${segNombre}`;
                ul.appendChild(header);
                
                // Pintar las obras debajo de su segmento
                agrupado[segNombre].forEach((obra) => {
                    const yaEnCerebro = CEREBRO_IA[obra.tituloClave] !== undefined;
                    
                    const li = document.createElement('li');
                    li.className = 'obra-item';
                    li.innerHTML = `
                        <div>
                            <div class="obra-item-title">${obra.nombre}</div>
                            <div class="obra-item-meta">${obra.distrito} - ${obra.monto}</div>
                        </div>
                        <div class="status-dot ${yaEnCerebro ? 'dot-green' : 'dot-red'}" title="${yaEnCerebro ? 'IA Conoce esta obra' : 'IA No conoce esta obra'}"></div>
                    `;
                    
                    li.addEventListener('click', () => {
                        document.querySelectorAll('.obra-item').forEach(el => el.classList.remove('active'));
                        li.classList.add('active');
                        abrirEditor(obra);
                    });
                    ul.appendChild(li);
                });
            });
        }

        function autoExpandTextarea(el) {
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight) + 'px';
        }

        function agregarContexto(titulo = "", texto = "", promptUser = false, url = "") {
            if (promptUser) {
                titulo = prompt("Ingresa un nombre para esta pestaña (Ej. 'Noticia', 'Expediente'):");
                if (titulo === null || titulo.trim() === "") return;
            }
            if (!titulo) titulo = "General";

            tabCounter++;
            const tabId = 'tab_' + tabCounter;

            const tabsHeaderList = document.getElementById('tabsHeaderList');
            const tabsContent = document.getElementById('tabsContent');

            // Desactivar actuales
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            // Crear botón de pestaña
            const tabBtn = document.createElement('button');
            tabBtn.type = 'button';
            tabBtn.className = 'tab-btn active';
            tabBtn.dataset.target = tabId;
            tabBtn.innerHTML = `
                <span class="ctx-titulo-display">${titulo}</span>
                <input type="hidden" class="ctx-titulo" value="${titulo}">
                <div class="tab-close" onclick="eliminarContexto(event, '${tabId}')" title="Cerrar pestaña">&times;</div>
            `;
            tabBtn.onclick = () => activarTab(tabId);

            tabsHeaderList.appendChild(tabBtn);

            // Crear contenido de pestaña
            const tabPane = document.createElement('div');
            tabPane.className = 'tab-pane active';
            tabPane.id = tabId;
            tabPane.innerHTML = `
                <input type="text" class="ctx-url" placeholder="Enlace web de referencia (Opcional)" value="${url}" style="width: 100%; margin-bottom: 10px; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid #1f2937; color: #93c5fd; border-radius: 6px; font-size: 12px; outline: none; box-sizing: border-box;">
                <textarea class="ctx-texto" placeholder="Pega aquí el contenido para '${titulo}'..." oninput="autoExpandTextarea(this)">${texto}</textarea>
            `;
            
            tabsContent.appendChild(tabPane);
            
            // Hacer scroll a la nueva pestaña en el header si hay muchas
            tabsHeaderList.parentElement.scrollLeft = tabsHeaderList.parentElement.scrollWidth;

            const textarea = tabPane.querySelector('.ctx-texto');
            if (textarea) {
                setTimeout(() => { autoExpandTextarea(textarea); }, 50);
            }
        }

        function activarTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.target === tabId);
            });
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.toggle('active', pane.id === tabId);
                if (isActive) {
                    const textarea = pane.querySelector('.ctx-texto');
                    if (textarea) { setTimeout(() => { autoExpandTextarea(textarea); }, 50); }
                }
            });
        }

        function eliminarContexto(event, tabId) {
            event.stopPropagation(); // Evitar que active el tab click
            if (!confirm("¿Seguro que deseas eliminar esta pestaña y todo su contenido?")) return;
            
            const btn = document.querySelector(`.tab-btn[data-target="${tabId}"]`);
            const pane = document.getElementById(tabId);
            
            const wasActive = btn.classList.contains('active');
            
            btn.remove();
            pane.remove();

            // Si cerramos la pestaña activa, activamos la última disponible
            if (wasActive) {
                const remainingTabs = document.querySelectorAll('.tab-btn');
                if (remainingTabs.length > 0) {
                    const lastTab = remainingTabs[remainingTabs.length - 1];
                    activarTab(lastTab.dataset.target);
                }
            }
        }

        async function abrirExtractorUrl() {
            const url = prompt("Pega aquí el enlace de la noticia o página web:");
            if (!url) return;
            
            const btn = document.querySelector('.btn-add-tab[onclick="abrirExtractorUrl()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = "⏳ Extrayendo...";
            btn.disabled = true;
            
            const fd = new FormData(); fd.append('action', 'extract_url_ajax'); fd.append('url', url);
            
            try {
                const resp = await fetch('ia_conocimiento.php', {method: 'POST', body: fd});
                const data = await resp.json();
                if (data.ok) {
                    agregarContexto(data.titulo, data.texto, false, url);
                } else { alert("Error: " + data.error); }
            } catch(e) {
                alert("Error de red al extraer el link."); 
            } finally { btn.innerHTML = originalText; btn.disabled = false; }
        }

        function abrirEditor(obra) {
            obraSeleccionada = obra;
            document.getElementById('editorPlaceholder').style.display = 'none';
            document.getElementById('editorContent').style.display = 'flex';
            
            // Llenar datos bloqueados
            let rawMonto = String(obra.monto).trim();
            let displayMonto = formatMontoFriendly(rawMonto);
            
            document.getElementById('lblMonto').textContent = displayMonto;
            document.getElementById('lblUbicacion').textContent = `${obra.distrito}, ${obra.provincia}`;
            document.getElementById('lblEstado').textContent = obra.estado;
            document.getElementById('lblDescExcel').textContent = obra.descripcion_excel || "Sin descripción oficial.";

            // Jalar texto de la IA si existe
            const tabsHeaderList = document.getElementById('tabsHeaderList');
            const tabsContent = document.getElementById('tabsContent');
            tabsHeaderList.innerHTML = '';
            tabsContent.innerHTML = '';
            const badge = document.getElementById('aiStatusBadge');
            let textoIA = CEREBRO_IA[obra.tituloClave];

            if (textoIA) {
                // Buscar si existe contexto adicional guardado
                const separador = "Contexto adicional:";
                if (textoIA.includes(separador)) {
                    textoIA = textoIA.substring(textoIA.indexOf(separador) + separador.length).trim();
                    let tabAdded = false;

                    if (textoIA !== "") {
                        if (textoIA.includes('--- ')) {
                            const bloques = textoIA.split(/--- (.*?) ---/g);
                            for (let i = 1; i < bloques.length; i += 2) {
                                const t = bloques[i].trim();
                                let c = (bloques[i+1] || "").trim();
                                
                                let foundUrl = '';
                                const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                                if (urlMatch) {
                                    foundUrl = urlMatch[1];
                                    c = c.replace(urlMatch[0], '').trim();
                                }
                                
                                let normC = c.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                let normExcel = (obra.descripcion_excel || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                if (normC !== normExcel && normC !== '') { agregarContexto(t, c, false, foundUrl); tabAdded = true; }
                            }
                        } else {
                            let c = textoIA;
                            let foundUrl = '';
                            const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                            if (urlMatch) {
                                foundUrl = urlMatch[1];
                                c = c.replace(urlMatch[0], '').trim();
                            }
                            let normC = c.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            let normExcel = (obra.descripcion_excel || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            if (normC !== normExcel && normC !== '') { agregarContexto("Contexto General", c, false, foundUrl); tabAdded = true; }
                        }
                    }
                    if (!tabAdded) agregarContexto("Principal");
                } else {
                    agregarContexto("Principal"); // Si no hay contexto extra, creamos caja vacía
                }

                badge.textContent = "🟢 Ya en Cerebro";
                badge.style.color = "#10b981";
            } else {
                agregarContexto("Principal"); // Crear primer bloque vacío
                badge.textContent = "🔴 Nuevo para IA";
                badge.style.color = "#ef4444";
            }
        }

        // Buscador y Filtro por Segmento
        function filtrarObras() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const seg = document.getElementById('selectSegmento').value;
            
            const filtradas = todasLasObras.filter(o => {
                // FIX BÚSQUEDA: Forzamos la conversión a String para evitar que el filtro colapse si una obra no tiene distrito
                const matchSearch = String(o.nombre || '').toLowerCase().includes(q) || String(o.distrito || '').toLowerCase().includes(q);
                const matchSegmento = seg === "" || o.segmento === seg;
                return matchSearch && matchSegmento;
            });
            renderLista(filtradas);
        }
        
        document.getElementById('searchInput').addEventListener('input', filtrarObras);
        document.getElementById('selectSegmento').addEventListener('change', filtrarObras);

        // ==========================================================
        // GENERADOR DEL SÚPER PÁRRAFO
        // ==========================================================
        function generarSuperParrafo(obra, contextoLibre) {
            let texto = `La obra '${obra.nombre}' pertenece al sector ${obra.segmento}. `;
            if (obra.distrito || obra.provincia) texto += `Ubicada en ${obra.distrito}, ${obra.provincia}. `;
            if (obra.estado) texto += `Estado actual: '${obra.estado}'. `;
            
            let rawMonto = String(obra.monto).trim();
            let displayMonto = formatMontoFriendly(rawMonto);
            texto += `Monto referencial: ${displayMonto}. `;
            
            if (obra.descripcion_excel) {
                texto += `Descripción oficial: ${obra.descripcion_excel}. `;
            }
            if (contextoLibre) {
                texto += `Contexto adicional: ${contextoLibre}`;
            }
            return texto.trim();
        }

        // ==========================================================
        // GUARDADO INDIVIDUAL (Botón Verde)
        // ==========================================================
        document.getElementById('btnSaveObra').addEventListener('click', async () => {
            if(!obraSeleccionada) return;
            const btn = document.getElementById('btnSaveObra');
            const txtOriginal = btn.innerHTML;
            btn.innerHTML = "⏳ Inyectando a la IA...";
            btn.disabled = true;

            // Recopilar todos los bloques creados por el usuario (Pestañas)
            const tabs = document.querySelectorAll('.tab-btn');
            let contextoManual = "";
            tabs.forEach(btn => {
                const tabId = btn.dataset.target;
                const tit = btn.querySelector('.ctx-titulo').value.trim();
                const pane = document.getElementById(tabId);
                if (pane) {
                    const txt = pane.querySelector('.ctx-texto').value.trim();
                    const urlVal = pane.querySelector('.ctx-url') ? pane.querySelector('.ctx-url').value.trim() : '';
                    if (txt) {
                        if (tit) contextoManual += `--- ${tit} ---\n${txt}\n`;
                        else contextoManual += `--- Fragmento ---\n${txt}\n`;
                        if (urlVal) contextoManual += `Enlace de referencia: ${urlVal}\n`;
                        contextoManual += `\n`;
                    }
                }
            });
            contextoManual = contextoManual.trim();
            
            const superContenido = generarSuperParrafo(obraSeleccionada, contextoManual);
            const palabrasClave = `${obraSeleccionada.nombre}, ${obraSeleccionada.distrito}, ${obraSeleccionada.segmento}`;

            const fd = new FormData();
            fd.append('action', 'save_single');
            fd.append('titulo', obraSeleccionada.tituloClave);
            fd.append('contenido', superContenido);
            fd.append('palabras', palabrasClave);

            try {
                const resp = await fetch('ia_cerebro_obras.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if(data.ok) {
                    CEREBRO_IA[obraSeleccionada.tituloClave] = superContenido; // Actualizar memoria local
                    document.getElementById('aiStatusBadge').textContent = "🟢 Ya en Cerebro";
                    document.getElementById('aiStatusBadge').style.color = "#10b981";
                    filtrarObras(); // Repintar lista para poner el punto verde
                    alert("🧠 ¡La IA acaba de aprenderse esta obra de memoria!");
                } else { alert("Error: " + data.error); }
            } catch(e) { alert("Error de conexión"); } 
            finally { btn.innerHTML = txtOriginal; btn.disabled = false; }
        });

        // ==========================================================
        // GUARDADO EN LOTE / BATCH (Botón Azul)
        // ==========================================================
        document.getElementById('btnSyncExcel').addEventListener('click', async () => {
            if(!confirm("¿Sincronizar TODAS las obras con la IA en este instante?\n\nEl sistema tomará los montos y estados frescos del Excel, y los fusionará cuidadosamente con las historias que ya hayas escrito a mano. Ninguna historia se perderá.")) return;
            
            const btn = document.getElementById('btnSyncExcel');
            const txtOriginal = btn.innerHTML;
            btn.innerHTML = "⏳ Sincronizando en lote...";
            btn.disabled = true;

            const payload = todasLasObras.map(obra => {
                let contextoIA = CEREBRO_IA[obra.tituloClave];
                let contextoManual = ""; 

                if (contextoIA) {
                    const sepModerno = "Contexto adicional:";
                    
                    if (contextoIA.includes(sepModerno)) {
                        contextoManual = contextoIA.substring(contextoIA.indexOf(sepModerno) + sepModerno.length).trim();
                        let cleanContexts = [];
                        if (contextoManual.includes('--- ')) {
                            const bloques = contextoManual.split(/--- (.*?) ---/g);
                            for (let i = 1; i < bloques.length; i += 2) {
                                const t = bloques[i].trim();
                                let c = (bloques[i+1] || "").trim();
                                
                                let foundUrl = '';
                                const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                                if (urlMatch) {
                                    foundUrl = urlMatch[1];
                                    c = c.replace(urlMatch[0], '').trim();
                                }
                                
                                let normC = c.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                let normExcel = (obra.descripcion_excel || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                                if (normC !== normExcel && normC !== '') {
                                    let blockStr = `--- ${t} ---\n${c}`;
                                    if (foundUrl) blockStr += `\nEnlace de referencia: ${foundUrl}`;
                                    cleanContexts.push(blockStr);
                                }
                            }
                            contextoManual = cleanContexts.join('\n\n');
                        } else {
                            let normC = contextoManual.replace(/Descripción oficial:/gi, '').replace(/Descripción:/gi, '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            let normExcel = (obra.descripcion_excel || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                            let c = contextoManual;
                            let foundUrl = '';
                            const urlMatch = c.match(/Enlace de referencia:\s*(https?:\/\/[^\s]+)/i);
                            if (urlMatch) {
                                foundUrl = urlMatch[1];
                                c = c.replace(urlMatch[0], '').trim();
                            }
                            if (normC === normExcel || normC === '') { contextoManual = ""; }
                            else { contextoManual = c; if (foundUrl) contextoManual += `\nEnlace de referencia: ${foundUrl}`; }
                        }
                    }
                }
                return { titulo: obra.tituloClave, palabras: `${obra.nombre}, ${obra.distrito}, ${obra.segmento}`, contenido: generarSuperParrafo(obra, contextoManual) };
            });

            const fd = new FormData(); fd.append('action', 'save_batch'); fd.append('obras', JSON.stringify(payload));
            try {
                const resp = await fetch('ia_cerebro_obras.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if(data.ok) { alert(data.mensaje); location.reload(); } 
                else { alert("Error: " + data.error); }
            } catch(e) { alert("Error de conexión"); } 
            finally { btn.innerHTML = txtOriginal; btn.disabled = false; }
        });

        // ==========================================================
        // EXPORTAR A WORD (DOC)
        // ==========================================================
        function exportarFichaWord() {
            if (!obraSeleccionada) return;

            const btn = document.getElementById('btnExportWord');
            const originalText = btn.innerHTML;
            btn.innerHTML = "⏳ Generando Word...";
            btn.disabled = true;

            let displayMonto = document.getElementById('lblMonto').textContent;
            let tabs = document.querySelectorAll('.tab-btn');
            let contextoHtml = "";

            tabs.forEach(tabBtn => {
                const tabId = tabBtn.dataset.target;
                const tit = tabBtn.querySelector('.ctx-titulo').value.trim();
                const pane = document.getElementById(tabId);
                if (pane) {
                    const txt = pane.querySelector('.ctx-texto').value.trim();
                    if (txt) {
                        contextoHtml += `<h3 style="color: #801039; font-family: Arial, sans-serif;">${tit || 'Contexto Adicional'}</h3>`;
                        contextoHtml += `<p style="font-family: Arial, sans-serif; line-height: 1.5;">${txt.replace(/\n/g, '<br>')}</p>`;
                    }
                }
            });

            let htmlContent = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8"><title>Ficha de Obra - ${obraSeleccionada.nombre}</title>
            <style>body{font-family:Arial,sans-serif;color:#333;}h1{color:#801039;border-bottom:2px solid #ffc300;padding-bottom:5px;}h3{color:#801039;margin-top:20px;}.info-table{width:100%;border-collapse:collapse;margin-bottom:20px;}.info-table th{text-align:left;padding:10px;background-color:#f4f6f9;border:1px solid #ddd;width:30%;color:#555;}.info-table td{padding:10px;border:1px solid #ddd;}</style>
            </head>
            <body>
                <h1>${obraSeleccionada.nombre}</h1>
                <table class="info-table">
                    <tr><th>Segmento</th><td>${obraSeleccionada.segmento}</td></tr>
                    <tr><th>Ubicación</th><td>${obraSeleccionada.distrito}, ${obraSeleccionada.provincia}</td></tr>
                    <tr><th>Estado</th><td>${obraSeleccionada.estado}</td></tr>
                    <tr><th>Monto de Inversión</th><td>${displayMonto}</td></tr>
                </table>
                <h3>Descripción Oficial (Excel)</h3>
                <p style="line-height: 1.5;">${obraSeleccionada.descripcion_excel ? obraSeleccionada.descripcion_excel.replace(/\n/g, '<br>') : 'Sin descripción oficial.'}</p>
                ${contextoHtml}
                <br><br><hr style="border: 0; border-top: 1px solid #ddd;">
                <p style="text-align: center; color: #888; font-size: 12px;">Generado desde el Cerebro de Obras - Fuerza Tacna</p>
            </body>
            </html>`;

            let blob = new Blob(['\ufeff', htmlContent], { type: 'application/msword' });
            let url = URL.createObjectURL(blob);
            let link = document.createElement('a');
            link.href = url; link.download = `Ficha_Obra_${obraSeleccionada.nombre.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.doc`;
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1000);
        }

        // Iniciar carga
        document.addEventListener("DOMContentLoaded", cargarObrasDesdeExcel);
    </script>
</body>
</html>