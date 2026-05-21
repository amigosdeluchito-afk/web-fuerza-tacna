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
  </style>
</head>
<body>
  <div class="app-shell">
    <header class="app-header">
      <nav>
        <a href="index.php" class="active">📷 Fotos</a>
        <a href="usuarios.php">👤 Usuarios</a>
        <a href="historial.php">🕒 Historial</a>
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
const SHEET_ID = "1ybyNINgEElYXGnsMQsoWSbwlr0kz67HZ1M1OJJmayHI";

// URL base del GViz
const SHEET_BASE_URL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:json&sheet=`;

// Segmentos (hojas) que vamos a usar
const SEGMENTOS = [
  { key: "EDUCACION", nombre: "Educacion" },
  { key: "VIAS",      nombre: "Vias" }
];

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

  // Cargar cada hoja del Sheet
  for (const seg of SEGMENTOS) {
    const url = SHEET_BASE_URL + encodeURIComponent(seg.key);
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

function actualizarObras() {
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

  const opt0 = document.createElement("option");
  opt0.value = "";
  opt0.textContent = "Selecciona obra...";
  obraEl.appendChild(opt0);

  lista.forEach((obra, idx) => {
    const opt = document.createElement("option");
    opt.value = String(idx); // índice dentro del arreglo
    opt.textContent = obra.nombre;
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
    galeriaEmpty.textContent = "Esta obra aún no tiene fotos.";
    actualizarInfoObra(null);
    return;
  }

  carpetaEl.value = item.carpeta;
  zonaObra.textContent = `${segmento} → ${item.nombre}\nCarpeta: ${item.carpeta}`;

  const fd = new FormData();
  fd.append("action", "listar");
  fd.append("segmento", segmento.toLowerCase());
 // "educacion", "vias"
  fd.append("carpeta", item.carpeta);

  const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
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

    const img = document.createElement("img");
    img.src = foto.url;
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
  });
}

// =============================
//  ACCIONES: PRINCIPAL / ELIMINAR
// =============================

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
  fd.append("segmento", segmento.toLowerCase());
  fd.append("carpeta", item.carpeta);

  const resp = await fetch("fotos_api.php", { method: "POST", body: fd });
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
  fd.append("segmento", segmento.toLowerCase());

 // ej. "educacion"
  fd.append("carpeta", item.carpeta);

  for (const file of filesInput.files) {
    fd.append("files[]", file);

  }

  statusEl.textContent = "Subiendo...";

  try {
    const resp = await fetch("upload.php", { method: "POST", body: fd });
    const text = await resp.text(); // leemos como texto primero

    let data;
    try {
      data = JSON.parse(text);
    } catch (err) {
      // PHP devolvió HTML o algo raro, no JSON
      console.error("Respuesta upload.php:", text);
      statusEl.textContent = "Error: respuesta no válida de upload.php.\nRevisa consola del navegador.";
      return;
    }

    if (data.ok) {
      statusEl.textContent = "Fotos subidas correctamente.";
      filesInput.value = "";
      document.getElementById("previewContainer").innerHTML = "";
      await cargarFotosObra();
    } else {
      statusEl.textContent = data.error || "Error al subir fotos.";
    }
  } catch (err) {
    console.error(err);
    statusEl.textContent = "Error de red / PHP: " + err.message;
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


  window.location.href = "fotos_api.php?download_zip=1&" + params.toString();
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
