import { PMTiles } from '../vendor/pmtiles/pmtiles-3.0.6.js';

const PMTILES_URL = '../data/pmtiles_proxy_departamento.php';
const TRAMOS_URL = 'mapa_redvial_api.php?action=geojson';
const SVG_NS = 'http://www.w3.org/2000/svg';
const ROAD_KINDS = new Set(['highway', 'major_road', 'minor_road']);

const els = {
  west: document.getElementById('west'),
  south: document.getElementById('south'),
  east: document.getElementById('east'),
  north: document.getElementById('north'),
  zoom: document.getElementById('zoom'),
  svgWidth: document.getElementById('svgWidth'),
  generate: document.getElementById('btnGenerate'),
  download: document.getElementById('btnDownload'),
  status: document.getElementById('status'),
  preview: document.getElementById('svgPreview'),
  meta: document.getElementById('previewMeta')
};

let currentSvg = null;

class PbfReaderLite {
  constructor(buf) {
    this.buf = buf instanceof Uint8Array ? buf : new Uint8Array(buf);
    this.pos = 0;
    this.length = this.buf.length;
    this.view = new DataView(this.buf.buffer, this.buf.byteOffset, this.buf.byteLength);
  }

  readVarint() {
    let val = 0;
    let shift = 0;
    while (this.pos < this.length) {
      const b = this.buf[this.pos++];
      val += (b & 0x7f) * Math.pow(2, shift);
      if (b < 0x80) return val;
      shift += 7;
    }
    return val;
  }

  readSVarint() {
    const n = this.readVarint();
    return (n % 2 === 1) ? -(n + 1) / 2 : n / 2;
  }

  readFloat() {
    const value = this.view.getFloat32(this.pos, true);
    this.pos += 4;
    return value;
  }

  readDouble() {
    const value = this.view.getFloat64(this.pos, true);
    this.pos += 8;
    return value;
  }

  readString() {
    const len = this.readVarint();
    const start = this.pos;
    this.pos += len;
    return new TextDecoder('utf-8').decode(this.buf.subarray(start, start + len));
  }

  readBytes() {
    const len = this.readVarint();
    const start = this.pos;
    this.pos += len;
    return this.buf.subarray(start, start + len);
  }

  skip(wireType) {
    if (wireType === 0) this.readVarint();
    else if (wireType === 1) this.pos += 8;
    else if (wireType === 2) this.pos += this.readVarint();
    else if (wireType === 5) this.pos += 4;
    else throw new Error(`Wire type MVT no soportado: ${wireType}`);
  }
}

function parseMvt(buffer) {
  const pbf = new PbfReaderLite(buffer);
  const layers = {};
  while (pbf.pos < pbf.length) {
    const tag = pbf.readVarint();
    const field = tag >> 3;
    const wire = tag & 7;
    if (field === 3 && wire === 2) {
      const layer = parseLayer(pbf.readBytes());
      if (layer.name) layers[layer.name] = layer;
    } else {
      pbf.skip(wire);
    }
  }
  return layers;
}

function parseLayer(bytes) {
  const pbf = new PbfReaderLite(bytes);
  const layer = { name: '', features: [], keys: [], values: [], extent: 4096, version: 1 };
  while (pbf.pos < pbf.length) {
    const tag = pbf.readVarint();
    const field = tag >> 3;
    const wire = tag & 7;
    if (field === 1) layer.name = pbf.readString();
    else if (field === 2 && wire === 2) layer.features.push(parseFeature(pbf.readBytes()));
    else if (field === 3) layer.keys.push(pbf.readString());
    else if (field === 4 && wire === 2) layer.values.push(parseValue(pbf.readBytes()));
    else if (field === 5) layer.extent = pbf.readVarint();
    else if (field === 15) layer.version = pbf.readVarint();
    else pbf.skip(wire);
  }
  for (const feature of layer.features) {
    feature.properties = {};
    for (let i = 0; i < feature.tags.length; i += 2) {
      const key = layer.keys[feature.tags[i]];
      if (key !== undefined) feature.properties[key] = layer.values[feature.tags[i + 1]];
    }
    feature.lines = decodeLines(feature.geometry);
  }
  return layer;
}

function parseFeature(bytes) {
  const pbf = new PbfReaderLite(bytes);
  const feature = { id: null, tags: [], type: 0, geometry: [], properties: {}, lines: [] };
  while (pbf.pos < pbf.length) {
    const tag = pbf.readVarint();
    const field = tag >> 3;
    const wire = tag & 7;
    if (field === 1) feature.id = pbf.readVarint();
    else if (field === 2 && wire === 2) feature.tags = readPackedVarints(pbf.readBytes());
    else if (field === 3) feature.type = pbf.readVarint();
    else if (field === 4 && wire === 2) feature.geometry = readPackedVarints(pbf.readBytes());
    else pbf.skip(wire);
  }
  return feature;
}

function parseValue(bytes) {
  const pbf = new PbfReaderLite(bytes);
  let value = null;
  while (pbf.pos < pbf.length) {
    const tag = pbf.readVarint();
    const field = tag >> 3;
    const wire = tag & 7;
    if (field === 1) value = pbf.readString();
    else if (field === 2) value = pbf.readFloat();
    else if (field === 3) value = pbf.readDouble();
    else if (field === 4) value = pbf.readVarint();
    else if (field === 5) value = pbf.readVarint();
    else if (field === 6) value = pbf.readSVarint();
    else if (field === 7) value = Boolean(pbf.readVarint());
    else pbf.skip(wire);
  }
  return value;
}

function readPackedVarints(bytes) {
  const pbf = new PbfReaderLite(bytes);
  const out = [];
  while (pbf.pos < pbf.length) out.push(pbf.readVarint());
  return out;
}

function decodeLines(geometry) {
  const lines = [];
  let line = [];
  let x = 0;
  let y = 0;
  let i = 0;
  while (i < geometry.length) {
    const cmdLen = geometry[i++];
    const cmd = cmdLen & 7;
    const count = cmdLen >> 3;
    if (cmd === 1) {
      if (line.length > 1) lines.push(line);
      line = [];
      for (let c = 0; c < count; c++) {
        x += zigZagDecode(geometry[i++]);
        y += zigZagDecode(geometry[i++]);
        line.push({ x, y });
      }
    } else if (cmd === 2) {
      for (let c = 0; c < count; c++) {
        x += zigZagDecode(geometry[i++]);
        y += zigZagDecode(geometry[i++]);
        line.push({ x, y });
      }
    } else if (cmd === 7) {
      if (line.length) line.push(line[0]);
    } else {
      break;
    }
  }
  if (line.length > 1) lines.push(line);
  return lines;
}

function zigZagDecode(n) {
  return (n >> 1) ^ (-(n & 1));
}

function lonToMercX(lon) {
  return (lon + 180) / 360;
}

function latToMercY(lat) {
  const rad = lat * Math.PI / 180;
  return (1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2;
}

function readBounds() {
  const bounds = {
    west: Number(els.west.value),
    south: Number(els.south.value),
    east: Number(els.east.value),
    north: Number(els.north.value),
    zoom: Math.max(3, Math.min(15, Math.round(Number(els.zoom.value) || 15))),
    width: Math.max(600, Math.min(5000, Math.round(Number(els.svgWidth.value) || 1600)))
  };
  if (![bounds.west, bounds.south, bounds.east, bounds.north].every(Number.isFinite)) {
    throw new Error('El BBOX contiene valores no numericos.');
  }
  if (bounds.west >= bounds.east || bounds.south >= bounds.north) {
    throw new Error('El BBOX no es valido: west/east o south/north estan invertidos.');
  }
  return bounds;
}

function getTileRange(bounds) {
  const z = bounds.zoom;
  const n = Math.pow(2, z);
  const xMin = clamp(Math.floor(lonToMercX(bounds.west) * n), 0, n - 1);
  const xMax = clamp(Math.floor(lonToMercX(bounds.east) * n), 0, n - 1);
  const yMin = clamp(Math.floor(latToMercY(bounds.north) * n), 0, n - 1);
  const yMax = clamp(Math.floor(latToMercY(bounds.south) * n), 0, n - 1);
  return { xMin, xMax, yMin, yMax, count: (xMax - xMin + 1) * (yMax - yMin + 1) };
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function buildProjector(bounds) {
  const minX = lonToMercX(bounds.west);
  const maxX = lonToMercX(bounds.east);
  const minY = latToMercY(bounds.north);
  const maxY = latToMercY(bounds.south);
  const height = Math.round(bounds.width * ((maxY - minY) / (maxX - minX)));
  return {
    width: bounds.width,
    height,
    pointFromMerc(globalX, globalY) {
      return [
        (globalX - minX) / (maxX - minX) * bounds.width,
        (globalY - minY) / (maxY - minY) * height
      ];
    },
    pointFromLngLat(lng, lat) {
      return this.pointFromMerc(lonToMercX(lng), latToMercY(lat));
    }
  };
}

function lineToPath(points) {
  if (!points || points.length < 2) return '';
  return points.map((p, i) => `${i === 0 ? 'M' : 'L'}${fmt(p[0])} ${fmt(p[1])}`).join(' ');
}

function fmt(n) {
  return Number(n).toFixed(2).replace(/\.?0+$/, '');
}

function appendPath(group, d, attrs = {}) {
  if (!d) return null;
  const path = document.createElementNS(SVG_NS, 'path');
  path.setAttribute('d', d);
  for (const [key, value] of Object.entries(attrs)) path.setAttribute(key, value);
  group.appendChild(path);
  return path;
}

function roadStyle(kind) {
  if (kind === 'highway') return { stroke: '#9aa7b2', width: 3.2 };
  if (kind === 'major_road') return { stroke: '#b7c0c8', width: 2.2 };
  return { stroke: '#d4d8dd', width: 1.15 };
}

async function loadRoads(bounds, projector) {
  const archive = new PMTiles(PMTILES_URL);
  const range = getTileRange(bounds);
  const tileLimit = 260;
  if (range.count > tileLimit) {
    throw new Error(`El BBOX requiere ${range.count} tiles en z${bounds.zoom}. Reduce el area o baja el zoom para esta primera version.`);
  }

  const n = Math.pow(2, bounds.zoom);
  const roads = [];
  const seen = new Set();
  for (let x = range.xMin; x <= range.xMax; x++) {
    for (let y = range.yMin; y <= range.yMax; y++) {
      const tile = await archive.getZxy(bounds.zoom, x, y);
      if (!tile || !tile.data) continue;
      const layers = parseMvt(tile.data);
      const roadsLayer = layers.roads;
      if (!roadsLayer) continue;
      for (const feature of roadsLayer.features) {
        const kind = feature.properties.kind || '';
        if (!ROAD_KINDS.has(kind)) continue;
        const style = roadStyle(kind);
        for (const line of feature.lines) {
          const points = line.map((p) => {
            const globalX = (x + p.x / roadsLayer.extent) / n;
            const globalY = (y + p.y / roadsLayer.extent) / n;
            return projector.pointFromMerc(globalX, globalY);
          });
          const d = lineToPath(points);
          const key = `${kind}|${feature.properties.name || ''}|${d}`;
          if (d && !seen.has(key)) {
            seen.add(key);
            roads.push({ d, kind, style });
          }
        }
      }
    }
  }
  return { roads, tileCount: range.count };
}

async function loadTramos(bounds, projector) {
  const res = await fetch(TRAMOS_URL, { cache: 'no-store' });
  if (!res.ok) throw new Error(`No se pudo cargar tramos: HTTP ${res.status}`);
  const geojson = await res.json();
  const tramos = [];
  const labels = [];
  const features = Array.isArray(geojson.features) ? geojson.features : [];
  const seenLabels = new Set();
  for (const feature of features) {
    const geom = feature.geometry || {};
    const props = feature.properties || {};
    const lines = geom.type === 'LineString'
      ? [geom.coordinates]
      : geom.type === 'MultiLineString'
        ? geom.coordinates
        : [];
    for (const coords of lines) {
      const inside = coords.some(([lng, lat]) => lng >= bounds.west && lng <= bounds.east && lat >= bounds.south && lat <= bounds.north);
      if (!inside) continue;
      const points = coords.map(([lng, lat]) => projector.pointFromLngLat(lng, lat));
      const d = lineToPath(points);
      if (!d) continue;
      const color = safeColor(props.color, '#8A1538');
      const id = `tramo-${slug(props.id || props.nombre || tramos.length)}`;
      tramos.push({ id, d, color, points, nombre: props.nombre || 'Tramo vial' });
      const labelKey = String(props.id || props.nombre || d);
      if (!seenLabels.has(labelKey)) {
        seenLabels.add(labelKey);
        labels.push({ id, nombre: props.nombre || 'Tramo vial', points });
      }
    }
  }
  return { tramos, labels };
}

function safeColor(value, fallback) {
  const color = String(value || '').trim();
  return /^#[0-9a-fA-F]{6}$/.test(color) ? color : fallback;
}

function slug(value) {
  return String(value).toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'x';
}

function labelTransform(points) {
  const midIndex = Math.max(0, Math.floor((points.length - 1) / 2));
  const p1 = points[midIndex];
  const p2 = points[Math.min(points.length - 1, midIndex + 1)] || p1;
  let angle = Math.atan2(p2[1] - p1[1], p2[0] - p1[0]) * 180 / Math.PI;
  if (angle > 90 || angle < -90) angle += 180;
  return { x: p1[0], y: p1[1], angle };
}

function createSvg(projector, roads, tramos, labels) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('xmlns', SVG_NS);
  svg.setAttribute('version', '1.1');
  svg.setAttribute('viewBox', `0 0 ${projector.width} ${projector.height}`);
  svg.setAttribute('width', String(projector.width));
  svg.setAttribute('height', String(projector.height));
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', 'Mapa vial para impresion');

  const style = document.createElementNS(SVG_NS, 'style');
  style.textContent = 'path{fill:none;vector-effect:non-scaling-stroke}.road{stroke-linecap:round;stroke-linejoin:round}.tramo{stroke-linecap:round;stroke-linejoin:round}.tramo-label{font-family:Arial,sans-serif;font-size:14px;font-weight:700;paint-order:stroke;stroke:#fff;stroke-width:4px;stroke-linejoin:round;fill:#111827}';
  svg.appendChild(style);

  const bg = document.createElementNS(SVG_NS, 'rect');
  bg.setAttribute('width', '100%');
  bg.setAttribute('height', '100%');
  bg.setAttribute('fill', '#f8fafc');
  svg.appendChild(bg);

  const baseGroup = document.createElementNS(SVG_NS, 'g');
  baseGroup.setAttribute('id', 'mapa-base');
  svg.appendChild(baseGroup);
  for (const road of roads) {
    appendPath(baseGroup, road.d, {
      class: `road road-${road.kind}`,
      stroke: road.style.stroke,
      'stroke-width': road.style.width
    });
  }

  const tramosGroup = document.createElementNS(SVG_NS, 'g');
  tramosGroup.setAttribute('id', 'tramos');
  svg.appendChild(tramosGroup);
  for (const tramo of tramos) {
    appendPath(tramosGroup, tramo.d, {
      id: tramo.id,
      class: 'tramo',
      stroke: tramo.color,
      'stroke-width': 6
    });
  }

  const labelsGroup = document.createElementNS(SVG_NS, 'g');
  labelsGroup.setAttribute('id', 'nombres-tramos');
  svg.appendChild(labelsGroup);
  for (const label of labels) {
    if (!label.nombre || !label.points || label.points.length < 2) continue;
    const t = labelTransform(label.points);
    const text = document.createElementNS(SVG_NS, 'text');
    text.setAttribute('class', 'tramo-label');
    text.setAttribute('text-anchor', 'middle');
    text.setAttribute('dominant-baseline', 'central');
    text.setAttribute('transform', `translate(${fmt(t.x)} ${fmt(t.y - 11)}) rotate(${fmt(t.angle)})`);
    text.textContent = label.nombre;
    labelsGroup.appendChild(text);
  }
  return svg;
}

function setStatus(message, type = '') {
  els.status.className = `status${type ? ` is-${type}` : ''}`;
  els.status.textContent = message;
}

async function generate() {
  els.generate.disabled = true;
  els.download.disabled = true;
  currentSvg = null;
  try {
    const bounds = readBounds();
    els.zoom.value = String(bounds.zoom);
    const projector = buildProjector(bounds);
    setStatus('Leyendo PMTiles y tramos...');
    const [{ roads, tileCount }, { tramos, labels }] = await Promise.all([
      loadRoads(bounds, projector),
      loadTramos(bounds, projector)
    ]);
    const svg = createSvg(projector, roads, tramos, labels);
    els.preview.innerHTML = '';
    els.preview.appendChild(svg);
    currentSvg = svg;
    els.download.disabled = false;
    els.meta.textContent = `${roads.length} calles | ${tramos.length} tramos | ${labels.length} nombres`;
    setStatus(`SVG generado correctamente.\nTiles leidos: ${tileCount}\nCalles: ${roads.length}\nTramos: ${tramos.length}\nNombres: ${labels.length}`, 'ok');
  } catch (error) {
    els.preview.innerHTML = '<div class="empty-preview">No se pudo generar el SVG. Revisa el mensaje del panel.</div>';
    els.meta.textContent = 'Error';
    setStatus(error.message || String(error), 'error');
  } finally {
    els.generate.disabled = false;
  }
}

function downloadSvg() {
  if (!currentSvg) return;
  const clone = currentSvg.cloneNode(true);
  clone.setAttribute('xmlns', SVG_NS);
  const source = `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(clone)}`;
  const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'mapa-vial-tacna.svg';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

els.generate.addEventListener('click', generate);
els.download.addEventListener('click', downloadSvg);
