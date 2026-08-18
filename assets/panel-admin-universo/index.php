<?php
require_once __DIR__ . '/config.php';
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de fotos – Universo de Obras</title>
  <style>
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #020617;
      color: #e5e7eb;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      margin: 0;
    }

    .app-shell {
      width: 100%;
    }

    .app-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 56px;
      background: #020617;
      border-bottom: 1px solid #111827;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      z-index: 20;
    }

    .app-header nav a {
      color: #9ca3af;
      margin-right: 16px;
      text-decoration: none;
      font-size: 14px;
    }

    .app-header nav a.active {
      color: #ffffff;
      font-weight: 600;
    }

    .app-header nav a:hover {
      color: #e5e7eb;
    }

    .app-header .user {
      font-size: 13px;
      color: #9ca3af;
    }

    .app-main {
      margin-top: 72px; /* deja espacio para el header fijo */
      display: flex;
      justify-content: center;
      padding: 20px;
    }

    .card {
      width: 100%;
      max-width: 960px;
      background: #020617;
      border-radius: 18px;
      padding: 24px 28px 28px;
      box-shadow:
        0 20px 40px rgba(15, 23, 42, 0.7),
        0 0 0 1px rgba(148, 163, 184, 0.15);
      border: 1px solid rgba(148, 163, 184, 0.15);
    }

    .card h1 {
      margin-top: 0;
      margin-bottom: 4px;
      font-size: 22px;
      color: #f9fafb;
    }

    .card p {
      margin-top: 0;
      margin-bottom: 16px;
      font-size: 13px;
      color: #9ca3af;
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 10px;
    }

    label {
      font-size: 13px;
      color: #e5e7eb;
      display: block;
      margin-bottom: 4px;
    }

    select, input[type="file"] {
      width: 100%;
      padding: 10px 12px;
      border-radius: 999px;
      border: 1px solid #1f2937;
      background: #020617;
      color: #e5e7eb;
      font-size: 14px;
      outline: none;
    }

    select:focus, input[type="file"]:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.7);
    }

    .helper {
      font-size: 11px;
      color: #6b7280;
      margin-top: 4px;
    }

    .segmento-info {
      font-size: 13px;
      color: #9ca3af;
      margin-top: 6px;
      padding: 8px 10px;
      border-radius: 10px;
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(31, 41, 55, 0.8);
      white-space: pre-line;
    }

    #infoObra {
      margin-top: 10px;
      font-size: 13px;
      color: #e5e7eb;
      padding: 6px 10px;
      border-radius: 10px;
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(31, 41, 55, 0.8);
      white-space: pre-line;
      min-height: 40px;
    }

    .zona-obra {
      margin-top: 16px;
      margin-bottom: 10px;
      font-size: 13px;
      color: #9ca3af;
    }

    .galeria {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 12px;
      margin-top: 10px;
      margin-bottom: 10px;
    }

    .foto-card {
      border-radius: 12px;
      border: 1px solid #1f2937;
      padding: 6px;
      background: #020617;
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 4px;
      cursor: grab;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .foto-card:active {
      cursor: grabbing;
    }
    .foto-card.drag-over {
      transform: scale(1.05);
      box-shadow: 0 0 0 2px #2563eb;
      z-index: 10;
    }

    .foto-card img {
      width: 100%;
      border-radius: 8px;
      display: block;
    }

    .foto-meta {
      font-size: 11px;
      color: #9ca3af;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .foto-actions {
      display: flex;
      gap: 4px;
      margin-top: 4px;
    }

    .btn {
      flex: 1;
      padding: 6px 8px;
      border-radius: 999px;
      border: none;
      font-size: 11px;
      cursor: pointer;
      font-weight: 500;
    }

    .btn-primary {
      background: #2563eb;
      color: #f9fafb;
    }

    .btn-primary.outline {
      background: transparent;
      color: #bfdbfe;
      border: 1px solid #2563eb;
    }

    .btn-danger {
      background: #b91c1c;
      color: #fee2e2;
    }

    .badge-principal {
      position: absolute;
      top: 6px;
      left: 6px;
      background: rgba(22, 163, 74, 0.9);
      color: #ecfdf5;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 999px;
    }

    .badge-empty {
      font-size: 12px;
      color: #9ca3af;
      margin-top: 6px;
    }

    .status {
      font-size: 12px;
      margin-top: 8px;
      color: #93c5fd;
    }

    .progress-container {
      width: 100%;
      background-color: #1f2937;
      border-radius: 999px;
      margin-top: 10px;
      overflow: hidden;
      height: 8px;
      display: none;
    }

    .progress-bar {
      height: 100%;
      background-color: #10b981; /* Verde esmeralda */
      width: 0%;
      transition: width 0.2s ease;
    }

    .download-zip {
      margin-top: 10px;
      display: flex;
      justify-content: flex-end;
    }

    .download-zip button {
      padding: 8px 12px;
      border-radius: 999px;
      border: none;
      background: #2563eb;
      color: white;
      font-size: 13px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .download-zip button:disabled {
      background: #1f2937;
      color: #6b7280;
      cursor: default;
    }

    .download-zip button span {
      font-size: 15px;
    }

    #btnSubir {
      width: 100%;
      margin-top: 10px;
      border-radius: 999px;
      background: #2563eb;
      color: #f9fafb;
      border: none;
      padding: 10px 14px;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
    }

    #btnSubir:disabled {
      background: #1f2937;
      color: #6b7280;
      cursor: default;
    }

    @media (max-width: 768px) {
      .row {
        grid-template-columns: 1fr;
      }
    }

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
  <div class="app-shell">
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
        <a href="gestor-cartografico.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestor-cartografico.php' ? 'active' : '' ?>">📍 Gestor Mapa</a>
        <a href="panel-juegos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'panel-juegos.php' ? 'active' : '' ?>">🎮 Panel de Juegos</a>
        <?php if (is_admin()): ?>
        <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
        <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
        <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos</a>
        <?php endif; ?>
      </nav>
      <div class="user">
        <?= htmlspecialchars(current_user() ?? '') ?> ·
        <a href="logout.php" style="color:#9ca3af;">Salir</a>
      </div>
    </header>

    <main class="app-main">
      <div class="card">
        <h1>Panel de fotos – Universo de Obras</h1>
        <p>Selecciona un segmento y una obra para gestionar hasta 6 fotos. La foto principal es la <code>1.webp</code>.</p>

        <div class="row">
          <div>
            <label for="segmento">Segmento</label>
            <select id="segmento">
              <option value="">Cargando segmentos...</option>
            </select>
            <div class="helper">Segmentos leídos desde tu Google Sheet (EDUCACION).</div>
          </div>
          <div>
            <label for="obra">Obra</label>
            <select id="obra">
              <option value="">Primero elige segmento...</option>
            </select>
            <div class="helper">Lista de obras filtrada por segmento.</div>
          </div>
        </div>

        <!-- datos internos -->
        <input type="hidden" id="carpeta">
        <input type="hidden" id="segmentoSlug">

  

        <div id="infoObra"></div>

        <div class="zona-obra" id="zonaObra">
          Ninguna obra seleccionada.
        </div>

       <div class="download-zip" style="gap:10px;">
  <button id="btnEliminarTodo" disabled style="background:#b91c1c;">
    🗑 Eliminar todas
  </button>

  <button id="btnDescargarZip" disabled>
    <span>⬇</span> Descargar fotos (ZIP)
  </button>
</div>


        <div id="galeria" class="galeria"></div>
        <div id="galeriaEmpty" class="badge-empty" style="display:none;">
          Esta obra aún no tiene fotos.
        </div>

        <form id="uploadForm" enctype="multipart/form-data" style="margin-top:10px;">
          <label for="files">Agregar nuevas fotos (máx. 6 por obra)</label>
          <input type="file" id="files" name="fotos[]" accept="image/*" multiple>
          <div class="helper">
            Formatos soportados: JPG, PNG, WEBP, GIF. Tamaño recomendado &lt; 5MB.<br>
            Si ya hay 6 fotos, no se podrán subir más.
          </div>
          <div id="previewContainer" class="galeria"></div>
          <div id="progressContainer" class="progress-container">
            <div id="progressBar" class="progress-bar"></div>
          </div>
          <button type="submit" id="btnSubir" disabled>Subir imágenes</button>
          <div id="status" class="status"></div>
        </form>
      </div>
    </main>
  </div>

 <script>
// =============================
//  CONFIGURACIÓN GOOGLE SHEET
// =============================

// ID de tu Google Sheet
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";

// URL base del GViz
const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:json&sheet=`;

let SEGMENTOS = [];

// Aquí guardamos las obras leídas desde el Sheet
// ejemplo: obrasPorSegmento["EDUCACION"] = [ { nombre, estado, carpeta, ... }, ... ]
const obrasPorSegmento = {};

// =============================
//  HELPERS
// =============================

function parseGviz(text) {
  // Google devuelve: google.visualization.Query.setResponse({...});
  const m = text.match(/setResponse\(([\s\S]+)\);?/);
  if (!m) throw new Error("No se pudo parsear respuesta GViz");
  return JSON.parse(m[1]);
}

function capitalizar(t) {
  return t.charAt(0).toUpperCase() + t.slice(1);
}

// =============================
//  CARGA DE SEGMENTOS Y OBRAS
// =============================

async function cargarSegmentos() {
  const segmentoEl = document.getElementById("segmento");
  segmentoEl.innerHTML = `<option value="">Cargando segmentos...</option>`;

  // Limpiamos cache
  for (const k in obrasPorSegmento) delete obrasPorSegmento[k];

  try {
    const tqBuster = encodeURIComponent(`select * offset 0`);
    const respSeg = await fetch(`${SHEET_BASE_URL}SEGMENTOS&tq=${tqBuster}`);
    const jsonSeg = parseGviz(await respSeg.text());
    SEGMENTOS = (jsonSeg.table.rows || [])
        .map(r => ({ key: r.c[2]?.v, nombre: r.c[1]?.v, activo: String(r.c[3]?.v||'').toUpperCase() }))
        .filter(s => s.key && (s.activo === 'SI' || s.activo === '1' || s.activo === 'TRUE'));
  } catch(e) { console.error("Error cargando SEGMENTOS", e); }

  // Cargar cada hoja del Sheet
  for (const seg of SEGMENTOS) {
    const tqBuster = encodeURIComponent(`select * offset 0`);
    const url = SHEET_BASE_URL + encodeURIComponent(seg.key) + `&tq=${tqBuster}`;
    const resp = await fetch(url);
    const txt  = await resp.text();
    const json = parseGviz(txt);

    const rows = json.table.rows || [];

    const obras = rows.map(r => {
      const c = r.c;
      return {
        nombre:    c[0]?.v || "", // A: nombre
        estado:    c[1]?.v || "", // B: estado
        monto:     c[2]?.v || "", // C: monto
        x:         c[3]?.v || "", // D: x
        y:         c[4]?.v || "", // E: y
        provincia: c[5]?.v || "", // F
        distrito:  c[6]?.v || "", // G
        carpeta:   c[7]?.v || "", // H: *** carpeta real (pons-muzzo, esfap, etc.) ***
        desc:      c[8]?.v || ""  // I
      };
    });

    obrasPorSegmento[seg.key] = obras;
  }

  poblarSegmentos();
}

function poblarSegmentos() {
  const segmentoEl = document.getElementById("segmento");
  segmentoEl.innerHTML = `<option value="">Selecciona segmento...</option>`;

  SEGMENTOS.forEach(seg => {
    const lista = obrasPorSegmento[seg.key] || [];
    const opt = document.createElement("option");
    opt.value = seg.key; // "EDUCACION", "VIAS"
    opt.textContent = `${seg.nombre} · ${lista.length} obras`;
    segmentoEl.appendChild(opt);
  });

  const obraEl = document.getElementById("obra");
  obraEl.innerHTML = `<option value="">Primero elige segmento...</option>`;
}

// =============================
//  CAMBIAR SEGMENTO / OBRAS
// =============================

async function actualizarObras() {
  const segmentoEl = document.getElementById("segmento");
  const obraEl     = document.getElementById("obra");
  const segSlugEl  = document.getElementById("segmentoSlug");
  const infoObraEl = document.getElementById("infoObra");
  const zonaObra   = document.getElementById("zonaObra");
  const galeriaEl  = document.getElementById("galeria");
  const galeriaEmpty = document.getElementById("galeriaEmpty");
  const btnZip     = document.getElementById("btnDescargarZip");
  const btnSubir   = document.getElementById("btnSubir");

  const segmento = segmentoEl.value;

  segSlugEl.value = segmento || "";
  obraEl.innerHTML = "";
  infoObraEl.textContent = "";
  zonaObra.textContent = "Ninguna obra seleccionada.";
  galeriaEl.innerHTML = "";
  galeriaEmpty.style.display = "none";
  btnZip.disabled   = true;
  btnSubir.disabled = true;

  if (!segmento) {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "Primero elige segmento...";
    obraEl.appendChild(opt);
    return;
  }

  const lista = obrasPorSegmento[segmento] || [];
  if (!lista.length) {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "Este segmento no tiene obras configuradas.";
    obraEl.appendChild(opt);
    return;
  }

  // Mostrar estado de carga mientras cuenta las fotos
  obraEl.innerHTML = `<option value="">Cargando estado de fotos...</option>`;
  obraEl.disabled = true;

  let conteos = {};
  try {
    const fd = new FormData();
    fd.append("action", "contar_segmento");
    fd.append("segmento", segmento.toLowerCase());
    const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
    const data = await resp.json();
    if (data.ok) conteos = data.conteos || {};
  } catch (e) {
    console.error("Error obteniendo conteos:", e);
  }

  obraEl.innerHTML = "";
  obraEl.disabled = false;

  const opt0 = document.createElement("option");
  opt0.value = "";
  opt0.textContent = "Selecciona obra...";
  obraEl.appendChild(opt0);

  lista.forEach((obra, idx) => {
    const opt = document.createElement("option");
    opt.value = String(idx); // índice dentro del arreglo
    
    let txtExtra = "";
    if (obra.carpeta) {
      const numFotos = conteos[obra.carpeta] || 0;
      txtExtra = numFotos > 0 ? `   [ 📷 ${numFotos} fotos ]` : `   [ ⚪ sin fotos ]`;
    } else {
      txtExtra = `   [ ⚠ sin carpeta ]`;
    }
    
    opt.textContent = obra.nombre + txtExtra;
    obraEl.appendChild(opt);
  });
}

function actualizarInfoObra(data) {
  const segSlugEl  = document.getElementById("segmentoSlug");
  const obraEl     = document.getElementById("obra");
  const infoObraEl = document.getElementById("infoObra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item) {
    infoObraEl.textContent = "Ninguna obra seleccionada.";
    return;
  }

  let texto = `${segmento} → ${item.nombre}\nEstado: ${item.estado || "—"}\nCarpeta: ${item.carpeta || "—"}`;

  if (data && typeof data.total !== "undefined") {
    texto += `\nFotos: ${data.total}/6 · Peso total: ${data.totalSize.toFixed(1)} KB`;
  }

  infoObraEl.textContent = texto;
}

// =============================
//  CARGAR FOTOS DE UNA OBRA
// =============================

async function cargarFotosObra() {
  const segSlugEl    = document.getElementById("segmentoSlug");
  const obraEl       = document.getElementById("obra");
  const carpetaEl    = document.getElementById("carpeta");
  const zonaObra     = document.getElementById("zonaObra");
  const galeriaEl    = document.getElementById("galeria");
  const galeriaEmpty = document.getElementById("galeriaEmpty");
  const btnZip       = document.getElementById("btnDescargarZip");
  const btnSubir     = document.getElementById("btnSubir");
  const filesInput   = document.getElementById("files");
  const btnEliminarTodo = document.getElementById("btnEliminarTodo");


  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  galeriaEl.innerHTML = "";
  galeriaEmpty.style.display = "none";
  btnZip.disabled   = true;
  btnSubir.disabled = true;
  btnEliminarTodo.disabled = true;
  document.getElementById("previewContainer").innerHTML = "";
  document.getElementById("files").value = "";
  document.getElementById("progressContainer").style.display = "none";
  document.getElementById("progressBar").style.width = "0%";
  



  if (!item) {
    carpetaEl.value = "";
    zonaObra.textContent = "Ninguna obra seleccionada.";
    actualizarInfoObra(null);
    return;
  }

  if (!item.carpeta) {
    carpetaEl.value = "";
    zonaObra.textContent = `${segmento} → ${item.nombre}\nEsta obra aún no tiene carpeta configurada.`;
    galeriaEmpty.style.display = "block";
    galeriaEmpty.innerHTML = "Esta obra no tiene carpeta configurada. <br>Ve a la pestaña <b>✏️ Editar Obra</b> y dale a <b>Guardar Todos los Cambios</b> para generarla automáticamente.";
    document.getElementById('uploadForm').style.display = 'none';
    actualizarInfoObra(null);
    return;
  }
  document.getElementById('uploadForm').style.display = 'block';

  carpetaEl.value = item.carpeta;
  zonaObra.textContent = `${segmento} → ${item.nombre}\nCarpeta: ${item.carpeta}`;

  const fd = new FormData();
  fd.append("action", "listar");
  fd.append("segmento", segmento.toLowerCase());
 // "educacion", "vias"
  fd.append("carpeta", item.carpeta);

  const resp = await fetch("fotos_api.php?_t=" + Date.now(), { method: "POST", body: fd });
  const data = await resp.json();

  if (!data.ok) {
    galeriaEmpty.style.display = "block";
    galeriaEmpty.textContent = data.error || "No se pudo cargar la galería.";
    actualizarInfoObra(null);
    return;
  }

  btnEliminarTodo.disabled = !(data.fotos && data.fotos.length);

  btnZip.disabled = !(data.fotos && data.fotos.length);
  if (data.fotos && data.fotos.length < 6) {
    btnSubir.disabled = !filesInput.files.length;
  }

  renderGaleria(data);
  actualizarInfoObra(data);

  // Actualizar el texto del <option> en la lista de obras en tiempo real
  const selectedOption = obraEl.options[obraEl.selectedIndex];
  if (selectedOption && item) {
    const numFotos = data.total || 0;
    const txtExtra = numFotos > 0 ? `   [ 📷 ${numFotos} fotos ]` : `   [ ⚪ sin fotos ]`;
    selectedOption.textContent = item.nombre + txtExtra;
  }
}

function renderGaleria(data) {
  const galeriaEl    = document.getElementById("galeria");
  const galeriaEmpty = document.getElementById("galeriaEmpty");

  galeriaEl.innerHTML = "";
  galeriaEmpty.style.display = "none";

  if (!data.fotos || !data.fotos.length) {
    galeriaEmpty.style.display = "block";
    galeriaEmpty.textContent = "Esta obra aún no tiene fotos.";
    return;
  }

  data.fotos.forEach((foto, idx) => {
    const card = document.createElement("div");
    card.className = "foto-card";
    card.draggable = true;
    card.dataset.num = idx + 1;

    const img = document.createElement("img");
    img.src = foto.thumb_url || foto.url;
    img.alt = `Foto ${idx + 1}`;
    card.appendChild(img);

    if (foto.es_principal) {
      const badge = document.createElement("div");
      badge.className = "badge-principal";
      badge.textContent = "Foto principal";
      card.appendChild(badge);
    }

    const meta = document.createElement("div");
    meta.className = "foto-meta";
    meta.innerHTML = `<span>Foto ${idx + 1}</span><span>${foto.size_kb} KB</span>`;
    card.appendChild(meta);

    const actions = document.createElement("div");
    actions.className = "foto-actions";

    const btnPrincipal = document.createElement("button");
    btnPrincipal.className = "btn btn-primary outline";
    btnPrincipal.textContent = "Principal";
    btnPrincipal.onclick = () => marcarPrincipal(idx + 1);
    actions.appendChild(btnPrincipal);

    const btnEliminar = document.createElement("button");
    btnEliminar.className = "btn btn-danger";
    btnEliminar.textContent = "Eliminar";
    btnEliminar.onclick = () => eliminarFoto(idx + 1);
    actions.appendChild(btnEliminar);

    card.appendChild(actions);
    galeriaEl.appendChild(card);

    // Drag & Drop events
    card.addEventListener("dragstart", () => {
      card.style.opacity = "0.5";
      card.classList.add("dragging");
    });
    
    card.addEventListener("dragend", () => {
      card.style.opacity = "1";
      card.classList.remove("dragging");
      document.querySelectorAll(".foto-card").forEach(c => c.classList.remove("drag-over"));
    });
    
    card.addEventListener("dragover", (e) => {
      e.preventDefault(); // Permitir drop
    });
    
    card.addEventListener("dragenter", (e) => {
      e.preventDefault();
      if (!card.classList.contains("dragging")) {
        card.classList.add("drag-over");
      }
    });
    
    card.addEventListener("dragleave", () => {
      card.classList.remove("drag-over");
    });
    
    card.addEventListener("drop", (e) => {
      e.preventDefault();
      card.classList.remove("drag-over");
      
      const draggingCard = document.querySelector(".dragging");
      if (draggingCard && draggingCard !== card) {
        const allCards = [...galeriaEl.querySelectorAll(".foto-card")];
        const draggedIdx = allCards.indexOf(draggingCard);
        const droppedIdx = allCards.indexOf(card);
        
        if (draggedIdx < droppedIdx) {
          card.parentNode.insertBefore(draggingCard, card.nextSibling);
        } else {
          card.parentNode.insertBefore(draggingCard, card);
        }
        
        guardarNuevoOrden();
      }
    });
  });
}

// =============================
//  ACCIONES: PRINCIPAL / ELIMINAR
// =============================

async function guardarNuevoOrden() {
  const segSlugEl = document.getElementById("segmentoSlug");
  const obraEl    = document.getElementById("obra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item || !item.carpeta) return;

  const galeriaEl = document.getElementById("galeria");
  const allCards = [...galeriaEl.querySelectorAll(".foto-card")];
  
  // Extraemos el orden de los atributos dataset.num
  const nuevoOrden = allCards.map(c => parseInt(c.dataset.num, 10));

  const fd = new FormData();
  fd.append("action", "reordenar");
  fd.append("_csrf", CSRF_TOKEN);
  fd.append("segmento", segmento.toLowerCase());
  fd.append("carpeta", item.carpeta);
  fd.append("orden", JSON.stringify(nuevoOrden));

  try {
    const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
    const data = await resp.json();

    if (data.ok) {
      await cargarFotosObra(); // Recargar para reflejar el nuevo orden en URLs y metadata
    } else {
      alert(data.error || "Error al reordenar");
      await cargarFotosObra(); // Revertir visualmente si hay error
    }
  } catch (error) {
    alert("Error de conexión al reordenar");
    await cargarFotosObra();
  }
}

async function marcarPrincipal(numFoto) {
  const segSlugEl = document.getElementById("segmentoSlug");
  const obraEl    = document.getElementById("obra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item || !item.carpeta) return;

  const fd = new FormData();
  fd.append("action", "principal");
  fd.append("_csrf", CSRF_TOKEN);
  fd.append("segmento", segmento.toLowerCase());

  fd.append("carpeta", item.carpeta);
  fd.append("numero", numFoto);

  const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
  const data = await resp.json();

  if (data.ok) {
    await cargarFotosObra();
  } else {
    alert(data.error || "Error al marcar principal");
  }
}

async function eliminarFoto(numFoto) {
  if (!confirm("¿Seguro que deseas eliminar esta foto?")) return;

  const segSlugEl = document.getElementById("segmentoSlug");
  const obraEl    = document.getElementById("obra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item || !item.carpeta) return;

  const fd = new FormData();
  fd.append("action", "eliminar");
  fd.append("_csrf", CSRF_TOKEN);
  fd.append("segmento", segmento.toLowerCase());

  fd.append("carpeta", item.carpeta);
  fd.append("numero", numFoto);

  const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
  const data = await resp.json();

  if (data.ok) {
    await cargarFotosObra();
  } else {
    alert(data.error || "Error al eliminar foto");
  }
}

async function eliminarTodasFotos() {
  const segSlugEl = document.getElementById("segmentoSlug");
  const obraEl    = document.getElementById("obra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item || !item.carpeta) return;

  const msg = `Vas a eliminar TODAS las fotos de:\n\n${segmento} → ${item.nombre}\nCarpeta: ${item.carpeta}\n\n¿Confirmas?`;
  if (!confirm(msg)) return;

  const fd = new FormData();
  fd.append("action", "eliminar_todas");
  fd.append("_csrf", CSRF_TOKEN);
  fd.append("segmento", segmento.toLowerCase());
  fd.append("carpeta", item.carpeta);

  const resp = await fetch("fotos_api.php?_t=" + Date.now(), { method: "POST", body: fd });
  const data = await resp.json();

  if (data.ok) {
    await cargarFotosObra();
  } else {
    alert(data.error || "Error al eliminar todas las fotos");
  }
}



// =============================
//  SUBIR FOTOS / DESCARGAR ZIP
// =============================

async function subirFotos(e) {
  e.preventDefault();

  const segSlugEl  = document.getElementById("segmentoSlug");
  const obraEl     = document.getElementById("obra");
  const filesInput = document.getElementById("files");
  const statusEl   = document.getElementById("status");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item) {
    alert("Selecciona una obra.");
    return;
  }

  if (!item.carpeta) {
    alert("Esta obra no tiene carpeta configurada en el Sheet (columna H).");
    return;
  }

  if (!filesInput.files.length) {
    alert("Selecciona al menos una foto.");
    return;
  }

  const fd = new FormData();
  fd.append("action", "subir");
  fd.append("_csrf", CSRF_TOKEN);
  fd.append("segmento", segmento.toLowerCase());

 // ej. "educacion"
  fd.append("carpeta", item.carpeta);

  for (const file of filesInput.files) {
    fd.append("files[]", file);

  }

  statusEl.textContent = "Subiendo...";
  const progressContainer = document.getElementById("progressContainer");
  const progressBar = document.getElementById("progressBar");
  
  progressContainer.style.display = "block";
  progressBar.style.width = "0%";
  btnSubir.disabled = true;

  try {
    // Usamos XMLHttpRequest envuelto en Promesa para poder capturar el evento 'progress'
    const text = await new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open("POST", "upload.php", true);

      xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
          const percentComplete = (e.loaded / e.total) * 100;
          progressBar.style.width = percentComplete + "%";
          statusEl.textContent = `Subiendo (${Math.round(percentComplete)}%)...`;
        }
      });

      xhr.onload = () => resolve(xhr.responseText);
      xhr.onerror = () => reject(new Error("Error de red al subir archivos"));
      xhr.send(fd);
    });

    progressContainer.style.display = "none"; // Ocultamos la barra al terminar

    let data;
    try {
      data = JSON.parse(text);
    } catch (err) {
      // PHP devolvió HTML o algo raro, no JSON
      console.error("Respuesta upload.php:", text);
      statusEl.textContent = "Error: respuesta no válida de upload.php.\nRevisa consola del navegador.";
      btnSubir.disabled = false;
      return;
    }

    if (data.ok) {
      statusEl.textContent = "Fotos subidas correctamente.";
      filesInput.value = "";
      document.getElementById("previewContainer").innerHTML = "";
      await cargarFotosObra();
    } else {
      statusEl.innerHTML = `<span style="color:#ef4444">❌ Error: ${data.error || (data.errores ? data.errores.join('<br>') : 'Error desconocido')}</span>`;
      btnSubir.disabled = false;
    }
  } catch (err) {
    console.error(err);
    statusEl.innerHTML = `<span style="color:#ef4444">❌ Error al procesar respuesta del servidor. ¿Quizás la foto es demasiado pesada? (${err.message})</span>`;
    btnSubir.disabled = false;
  }
}


function descargarZip() {
  const segSlugEl = document.getElementById("segmentoSlug");
  const obraEl    = document.getElementById("obra");

  const segmento = segSlugEl.value;
  const idx = parseInt(obraEl.value, 10);
  const lista = obrasPorSegmento[segmento] || [];
  const item = lista[idx];

  if (!item || !item.carpeta) return;

 const params = new URLSearchParams({
  segmento: segmento.toLowerCase(),
  carpeta: item.carpeta
});


  window.location.href = "fotos_api.php?download_zip=1&_t=" + Date.now() + "&" + params.toString();
}

// =============================
//  INICIALIZACIÓN
// =============================

document.addEventListener("DOMContentLoaded", async () => {
  const segmentoEl = document.getElementById("segmento");
  const obraEl     = document.getElementById("obra");
  const uploadForm = document.getElementById("uploadForm");
  const filesInput = document.getElementById("files");
  const btnSubir   = document.getElementById("btnSubir");
  const btnZip     = document.getElementById("btnDescargarZip");
  const btnEliminarTodo = document.getElementById("btnEliminarTodo");


  try {
    await cargarSegmentos();
  } catch (e) {
    console.error(e);
    segmentoEl.innerHTML = `<option value="">Error cargando segmentos</option>`;
  }

  segmentoEl.addEventListener("change", actualizarObras);
  obraEl.addEventListener("change", cargarFotosObra);

  filesInput.addEventListener("change", () => {
    const segSlugEl = document.getElementById("segmentoSlug");
    const obraEl    = document.getElementById("obra");
    const previewEl = document.getElementById("previewContainer");
    const segmento  = segSlugEl.value;
    const idx       = parseInt(obraEl.value, 10);
    const lista     = obrasPorSegmento[segmento] || [];
    const item      = lista[idx];

    btnSubir.disabled = !(filesInput.files.length && item && item.carpeta);

    // Generar previsualización
    previewEl.innerHTML = "";
    if (filesInput.files.length > 0) {
      Array.from(filesInput.files).forEach((file, i) => {
        const sizeKB = (file.size / 1024).toFixed(1);
        const objUrl = URL.createObjectURL(file);
        
        const card = document.createElement("div");
        card.className = "foto-card";
        card.style.borderColor = "#4f46e5"; // Borde distinto para identificar que es una previsualización
        card.style.opacity = "0.8"; // Ligeramente transparente
        
        card.innerHTML = `
          <img src="${objUrl}" alt="Previsualización ${i + 1}">
          <div class="foto-meta">
            <span style="color:#a5b4fc">A subir...</span>
            <span>${sizeKB} KB</span>
          </div>
        `;
        previewEl.appendChild(card);
      });
    }
  });

  uploadForm.addEventListener("submit", subirFotos);
  btnZip.addEventListener("click", descargarZip);
  btnEliminarTodo.addEventListener("click", eliminarTodasFotos);

});
</script>


</body>
</html>
