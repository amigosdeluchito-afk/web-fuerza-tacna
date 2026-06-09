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
        
        .editor-content { display: none; flex-direction: column; height: 100%; }
        .excel-data-card { background: #020617; border: 1px solid #1e293b; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .excel-data-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
        .data-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .data-item label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .data-item div { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        
        .textarea-wrap { flex: 1; display: flex; flex-direction: column; min-height: 250px; margin-bottom: 20px; }
        .textarea-wrap label { font-size: 15px; color: #f9fafb; font-weight: 600; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .textarea-wrap label span { font-size: 12px; font-weight: normal; color: #9ca3af; background: #1e293b; padding: 2px 8px; border-radius: 4px; }
        textarea { flex: 1; width: 100%; padding: 15px; border-radius: 8px; border: 1px solid #334155; background: #020617; color: #e2e8f0; font-size: 14px; resize: none; outline: none; line-height: 1.6; font-family: system-ui; }
        textarea:focus { border-color: #3b82f6; }

        .btn-save-obra { background: #10b981; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-save-obra:hover { background: #059669; }
    </style>
</head>
<body>
    <header class="app-header">
      <nav style="display:flex; align-items:center;">
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php">➕ Agregar Obra</a>
        <a href="editar_obra.php">✏️ Editar Obra</a>
        <a href="gestionar_visibilidad.php">👁️ Visibilidad</a>
        <a href="segmentos.php">🗂️ Segmentos</a>
        <a href="cronologia.php">⏳ Cronología</a>
        <a href="editar_candidato.php">👥 Candidatos</a>
        
        <div class="dropdown">
          <button class="dropbtn active">🧠 IA y Conocimiento ▾</button>
          <div class="dropdown-content">
            <a href="ia_respuestas.php">🧠 Cerebro IA</a>
            <a href="ia_cerebro_obras.php" class="active">🏗️ Cerebro Obras</a>
            <a href="ia_conocimiento.php">📚 Base Conocimiento</a>
            <a href="ia_fuentes.php">🔗 Fuentes Externas</a>
            <a href="ia_estadisticas.php">📊 Estadísticas IA</a>
          </div>
        </div>
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
                            Contexto Adicional Confidencial (Opcional)
                            <span id="aiStatusBadge">🟢 Ya en Cerebro</span>
                        </label>
                        <textarea id="aiContextText" placeholder="Pega aquí discursos, anécdotas, peticiones de vecinos o información extraída de documentos. Luchito usará esto para dar respuestas más completas e inteligentes."></textarea>
                    </div>

                    <button class="btn-save-obra" id="btnSaveObra">🧠 Alimentar Cerebro con esta Obra</button>
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
                    const url = `${SHEET_BASE_URL}?tqx=out:json;reqId=${new Date().getTime()}&sheet=${encodeURIComponent(seg.key)}&range=A:J&headers=1`;
                    const resp = await fetch(url);
                    const json = parseGviz(await resp.text());
                    
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

        function abrirEditor(obra) {
            obraSeleccionada = obra;
            document.getElementById('editorPlaceholder').style.display = 'none';
            document.getElementById('editorContent').style.display = 'flex';
            
            // Llenar datos bloqueados
            let rawMonto = String(obra.monto).trim();
            let displayMonto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : 'S/ 0';
            
            document.getElementById('lblMonto').textContent = displayMonto;
            document.getElementById('lblUbicacion').textContent = `${obra.distrito}, ${obra.provincia}`;
            document.getElementById('lblEstado').textContent = obra.estado;
            document.getElementById('lblDescExcel').textContent = obra.descripcion_excel || "Sin descripción oficial.";

            // Jalar texto de la IA si existe
            const textarea = document.getElementById('aiContextText');
            const badge = document.getElementById('aiStatusBadge');
            let textoIA = CEREBRO_IA[obra.tituloClave];

            if (textoIA) {
                // Buscar si existe contexto adicional guardado
                const separador = "Contexto adicional: ";
                if (textoIA.includes(separador)) {
                    textoIA = textoIA.substring(textoIA.indexOf(separador) + separador.length).trim();
                } else {
                    textoIA = ""; // Si no hay contexto extra, la caja se muestra limpia
                }

                textarea.value = textoIA;
                badge.textContent = "🟢 Ya en Cerebro";
                badge.style.color = "#10b981";
            } else {
                textarea.value = ""; // Totalmente limpio para empezar a escribir
                badge.textContent = "🔴 Nuevo para IA";
                badge.style.color = "#ef4444";
            }
        }

        // Buscador y Filtro por Segmento
        function filtrarObras() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const seg = document.getElementById('selectSegmento').value;
            
            const filtradas = todasLasObras.filter(o => {
                const matchSearch = o.nombre.toLowerCase().includes(q) || o.distrito.toLowerCase().includes(q);
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
            let displayMonto = rawMonto ? (/^\s*S\//i.test(rawMonto) ? rawMonto : 'S/ ' + rawMonto) : 'S/ 0';
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

            const contextoManual = document.getElementById('aiContextText').value.trim();
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
                    const sepModerno = "Contexto adicional: ";
                    const sepAntiguo = "Descripción: ";
                    
                    if (contextoIA.includes(sepModerno)) {
                        contextoManual = contextoIA.substring(contextoIA.indexOf(sepModerno) + sepModerno.length).trim();
                    } else if (contextoIA.includes(sepAntiguo)) {
                        // Rescatar textos antiguos por si acaso
                        let viejo = contextoIA.substring(contextoIA.indexOf(sepAntiguo) + sepAntiguo.length).trim();
                        viejo = viejo.replace(" (Tiene galería de fotos).", "").trim();
                        if (viejo && viejo !== obra.descripcion_excel) {
                            contextoManual = viejo;
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

        // Iniciar carga
        document.addEventListener("DOMContentLoaded", cargarObrasDesdeExcel);
    </script>
</body>
</html>