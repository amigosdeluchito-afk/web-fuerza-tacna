import { PMTiles } from '../vendor/pmtiles/pmtiles-3.0.6.js';

const PMTILES_URL = '../data/pmtiles_proxy_departamento.php';
const TRAMOS_URL = 'mapa_redvial_api.php?action=geojson';
const SVG_NS = 'http://www.w3.org/2000/svg';
const ROAD_KINDS = new Set(['highway', 'major_road', 'minor_road']);
const MAP_ASPECT_RATIO = 1.55;
const TRAMO_STROKE_WIDTH = 3;
const MARKER_RADIUS = 6;
const MARKER_FONT_SIZE = 7.5;
const MARKER_TEXT_DY = '0.34em';

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
    } else if (cmd !== 7) {
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
  const panelWidth = Math.min(460, Math.max(340, Math.round(bounds.width * 0.28)));
  const mapWidth = bounds.width - panelWidth;
  const mapHeight = Math.round(mapWidth / MAP_ASPECT_RATIO);
  const height = mapHeight + 80;
  const mapX = 30;
  const mapY = 30;
  return {
    width: bounds.width,
    height,
    mapX,
    mapY,
    mapWidth,
    mapHeight,
    panelX: mapWidth,
    panelWidth,
    pointFromMerc(globalX, globalY) {
      return [
        mapX + (globalX - minX) / (maxX - minX) * mapWidth,
        mapY + (globalY - minY) / (maxY - minY) * mapHeight
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

function midpointOfLines(lines) {
  const segments = [];
  let total = 0;
  for (const points of lines) {
    for (let i = 1; i < points.length; i++) {
      const start = points[i - 1];
      const end = points[i];
      const length = Math.hypot(end[0] - start[0], end[1] - start[1]);
      if (Number.isFinite(length) && length > 0) {
        segments.push({ start, end, length });
        total += length;
      }
    }
  }
  if (!total) return lines[0]?.[0] || [0, 0];
  let distance = total / 2;
  for (const segment of segments) {
    if (distance <= segment.length) {
      const ratio = distance / segment.length;
      return [
        segment.start[0] + (segment.end[0] - segment.start[0]) * ratio,
        segment.start[1] + (segment.end[1] - segment.start[1]) * ratio
      ];
    }
    distance -= segment.length;
  }
  return segments[segments.length - 1].end;
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
  const roadPaths = { secondary: [], major: [], highway: [] };
  let roadCount = 0;
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
        for (const line of feature.lines) {
          const points = line.map((p) => {
            const globalX = (x + p.x / roadsLayer.extent) / n;
            const globalY = (y + p.y / roadsLayer.extent) / n;
            return projector.pointFromMerc(globalX, globalY);
          }).filter(([px, py]) => Number.isFinite(px) && Number.isFinite(py));
          const d = lineToPath(points);
          let maxSegment = 0;
          for (let i = 1; i < points.length; i++) {
            maxSegment = Math.max(maxSegment, Math.hypot(
              points[i][0] - points[i - 1][0],
              points[i][1] - points[i - 1][1]
            ));
          }
          if (maxSegment > projector.mapWidth * 0.5 && kind !== 'highway') {
            console.warn('[mapa-vial] segmento sospechoso', {
              tile: { z: bounds.zoom, x, y },
              featureId: feature.id,
              kind,
              name: feature.properties.name || '',
              parts: feature.lines.length,
              points: points.length,
              maxSegment,
              d
            });
          }
          const key = `${kind}|${feature.properties.name || ''}|${d}`;
          if (d && !seen.has(key)) {
            seen.add(key);
            roadPaths[kind === 'highway' ? 'highway' : kind === 'major_road' ? 'major' : 'secondary'].push(d);
            roadCount++;
          }
        }
      }
    }
  }
  const roads = [];
  if (roadPaths.secondary.length) roads.push({ id: 'vias-secundarias', d: roadPaths.secondary.join(' '), style: roadStyle('minor_road') });
  if (roadPaths.major.length) roads.push({ id: 'vias-principales', d: roadPaths.major.join(' '), style: roadStyle('major_road') });
  if (roadPaths.highway.length) roads.push({ id: 'vias-highway', d: roadPaths.highway.join(' '), style: roadStyle('highway') });
  return { roads, roadCount, tileCount: range.count };
}

async function loadTramos(bounds) {
  const res = await fetch(TRAMOS_URL, { cache: 'no-store' });
  if (!res.ok) throw new Error(`No se pudo cargar tramos: HTTP ${res.status}`);
  const geojson = await res.json();
  const tramos = [];
  let minMercX = Infinity;
  let maxMercX = -Infinity;
  let minMercY = Infinity;
  let maxMercY = -Infinity;
  const features = Array.isArray(geojson.features) ? geojson.features : [];
  for (const feature of features) {
    const geom = feature.geometry || {};
    const props = feature.properties || {};
    const lines = geom.type === 'LineString'
      ? [geom.coordinates]
      : geom.type === 'MultiLineString'
        ? geom.coordinates
        : [];
    const validLines = lines.map((coords) => coords.filter((pair) => {
      const [lng, lat] = pair || [];
      return Number.isFinite(lng) && Number.isFinite(lat)
    })).filter((coords) => coords.length > 1);
    if (!validLines.length) continue;
    for (const coords of validLines) {
      for (const [lng, lat] of coords) {
        const mercX = lonToMercX(lng);
        const mercY = latToMercY(lat);
        minMercX = Math.min(minMercX, mercX);
        maxMercX = Math.max(maxMercX, mercX);
        minMercY = Math.min(minMercY, mercY);
        maxMercY = Math.max(maxMercY, mercY);
      }
    }
    const index = tramos.length + 1;
    tramos.push({
      id: `tramo-${index}-${slug(props.id || props.nombre || 'x')}`,
      color: safeColor(props.color, '#2f7d5b'),
      lines: validLines,
      nombre: String(props.nombre || 'Tramo vial')
    });
  }
  if (!tramos.length) return { tramos, frameBounds: bounds, warning: 'No hay tramos disponibles para calcular el encuadre automatico.' };

  let mercWidth = maxMercX - minMercX;
  let mercHeight = maxMercY - minMercY;
  const centerX = (minMercX + maxMercX) / 2;
  const centerY = (minMercY + maxMercY) / 2;
  const minWidth = lonToMercX(0.01) - lonToMercX(0);
  const minHeight = Math.abs(latToMercY(-0.01) - latToMercY(0));
  mercWidth = Math.max(mercWidth, minWidth);
  mercHeight = Math.max(mercHeight, minHeight);
  mercWidth *= 1.2;
  mercHeight *= 1.2;
  const targetAspect = MAP_ASPECT_RATIO;
  if (mercWidth / mercHeight < targetAspect) mercWidth = mercHeight * targetAspect;
  else mercHeight = mercWidth / targetAspect;
  minMercX = centerX - mercWidth / 2;
  maxMercX = centerX + mercWidth / 2;
  minMercY = centerY - mercHeight / 2;
  maxMercY = centerY + mercHeight / 2;
  return {
    tramos,
    frameBounds: {
      west: minMercX * 360 - 180,
      east: maxMercX * 360 - 180,
      north: Math.atan(Math.sinh(Math.PI * (1 - 2 * minMercY))) * 180 / Math.PI,
      south: Math.atan(Math.sinh(Math.PI * (1 - 2 * maxMercY))) * 180 / Math.PI
    }
  };
}

function safeColor(value, fallback) {
  const color = String(value || '').trim();
  return /^#[0-9a-fA-F]{6}$/.test(color) ? color : fallback;
}

function slug(value) {
  return String(value).toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'x';
}

function createSvg(projector, roads, tramos) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('xmlns', SVG_NS);
  svg.setAttribute('version', '1.1');
  svg.setAttribute('viewBox', `0 0 ${projector.width} ${projector.height}`);
  svg.setAttribute('width', String(projector.width));
  svg.setAttribute('height', String(projector.height));
  svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', 'Mapa vial y listado de vias mejoradas');

  const style = document.createElementNS(SVG_NS, 'style');
  style.textContent = 'path{fill:none}.road,.tramo{stroke-linecap:round;stroke-linejoin:round}.panel-title,.panel-row,.legend-text,.north-text{font-family:Arial,sans-serif;fill:#263238}.panel-title{font-size:22px;font-weight:700}.panel-row{font-size:14px}.legend-text,.north-text{font-size:12px}.marker-number{font-family:Arial,sans-serif;font-size:7.5px;font-weight:700;fill:#fff;text-anchor:middle;dominant-baseline:middle}';
  svg.appendChild(style);

  const defs = document.createElementNS(SVG_NS, 'defs');
  const clip = document.createElementNS(SVG_NS, 'clipPath');
  clip.setAttribute('id', 'mapClip');
  const clipRect = document.createElementNS(SVG_NS, 'rect');
  clipRect.setAttribute('x', projector.mapX);
  clipRect.setAttribute('y', projector.mapY);
  clipRect.setAttribute('width', projector.mapWidth - 30);
  clipRect.setAttribute('height', projector.mapHeight);
  clip.appendChild(clipRect);
  defs.appendChild(clip);
  svg.appendChild(defs);

  const bg = document.createElementNS(SVG_NS, 'rect');
  bg.setAttribute('width', '100%');
  bg.setAttribute('height', '100%');
  bg.setAttribute('fill', '#f8fafc');
  svg.appendChild(bg);

  const mapArea = document.createElementNS(SVG_NS, 'g');
  mapArea.setAttribute('id', 'map-area');
  mapArea.setAttribute('clip-path', 'url(#mapClip)');
  svg.appendChild(mapArea);

  const baseGroup = document.createElementNS(SVG_NS, 'g');
  baseGroup.setAttribute('id', 'mapa-base');
  mapArea.appendChild(baseGroup);
  for (const road of roads) {
    appendPath(baseGroup, road.d, {
      id: road.id,
      class: 'road',
      fill: 'none',
      stroke: road.style.stroke,
      'stroke-width': road.style.width,
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round'
    });
  }

  const tramosGroup = document.createElementNS(SVG_NS, 'g');
  tramosGroup.setAttribute('id', 'tramos');
  mapArea.appendChild(tramosGroup);
  for (const tramo of tramos) {
    appendPath(tramosGroup, tramo.d, {
      id: tramo.id,
      class: 'tramo',
      fill: 'none',
      stroke: tramo.color,
      'stroke-width': TRAMO_STROKE_WIDTH,
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round'
    });
  }

  const markersGroup = document.createElementNS(SVG_NS, 'g');
  markersGroup.setAttribute('id', 'marcadores-tramos');
  mapArea.appendChild(markersGroup);
  const offsets = [[0, 0], [8, -8], [-8, -8], [8, 8], [-8, 8], [0, -13], [13, 0]];
  const placed = [];
  for (const tramo of tramos) {
    const [x, y] = tramo.marker;
    const offset = offsets.find(([dx, dy]) => placed.every(([px, py]) => Math.hypot(x + dx - px, y + dy - py) >= 18)) || [0, 0];
    const markerX = x + offset[0];
    const markerY = y + offset[1];
    placed.push([markerX, markerY]);
    const marker = document.createElementNS(SVG_NS, 'g');
    marker.setAttribute('class', 'tramo-marker');
    marker.setAttribute('transform', `translate(${fmt(markerX)} ${fmt(markerY)})`);
    const circle = document.createElementNS(SVG_NS, 'circle');
    circle.setAttribute('r', String(MARKER_RADIUS));
    circle.setAttribute('fill', '#c62828');
    const number = document.createElementNS(SVG_NS, 'text');
    number.setAttribute('class', 'marker-number');
    number.setAttribute('font-family', 'Arial, sans-serif');
    number.setAttribute('font-size', String(MARKER_FONT_SIZE));
    number.setAttribute('font-weight', '700');
    number.setAttribute('fill', '#fff');
    number.setAttribute('x', '0');
    number.setAttribute('y', '0');
    number.setAttribute('dy', MARKER_TEXT_DY);
    number.setAttribute('text-anchor', 'middle');
    number.setAttribute('dominant-baseline', 'middle');
    number.setAttribute('alignment-baseline', 'middle');
    number.textContent = String(tramo.number);
    marker.append(circle, number);
    markersGroup.appendChild(marker);
  }

  const panel = document.createElementNS(SVG_NS, 'g');
  panel.setAttribute('id', 'panel-listado');
  const panelRect = document.createElementNS(SVG_NS, 'rect');
  panelRect.setAttribute('x', projector.panelX);
  panelRect.setAttribute('y', '0');
  panelRect.setAttribute('width', projector.panelWidth);
  panelRect.setAttribute('height', projector.height);
  panelRect.setAttribute('fill', '#fff');
  panelRect.setAttribute('stroke', '#d7dee3');
  panel.appendChild(panelRect);
  const title = document.createElementNS(SVG_NS, 'text');
  title.setAttribute('class', 'panel-title');
  title.setAttribute('font-family', 'Arial, sans-serif');
  title.setAttribute('font-size', '22');
  title.setAttribute('font-weight', '700');
  title.setAttribute('fill', '#263238');
  title.setAttribute('x', projector.panelX + 28);
  title.setAttribute('y', '48');
  title.textContent = 'RED VIAL - VIAS MEJORADAS';
  panel.appendChild(title);
  const columns = tramos.length > 25 ? 2 : 1;
  const rows = Math.ceil(tramos.length / columns);
  const rowHeight = Math.max(30, Math.min(52, Math.floor((projector.height - 105) / Math.max(rows, 1))));
  const fontSize = tramos.length > 40 ? 11 : tramos.length > 25 ? 12 : 14;
  for (let i = 0; i < tramos.length; i++) {
    const column = Math.floor(i / rows);
    const row = i % rows;
    const columnWidth = projector.panelWidth / columns;
    const x = projector.panelX + column * columnWidth + 26;
    const y = 88 + row * rowHeight;
    const circle = document.createElementNS(SVG_NS, 'circle');
    circle.setAttribute('cx', x);
    circle.setAttribute('cy', y - 5);
    circle.setAttribute('r', String(MARKER_RADIUS));
    circle.setAttribute('fill', '#c62828');
    panel.appendChild(circle);
    const number = document.createElementNS(SVG_NS, 'text');
    number.setAttribute('class', 'marker-number');
    number.setAttribute('font-family', 'Arial, sans-serif');
    number.setAttribute('font-size', String(MARKER_FONT_SIZE));
    number.setAttribute('font-weight', '700');
    number.setAttribute('fill', '#fff');
    number.setAttribute('x', x);
    number.setAttribute('y', y - 5);
    number.setAttribute('dy', MARKER_TEXT_DY);
    number.setAttribute('text-anchor', 'middle');
    number.setAttribute('dominant-baseline', 'middle');
    number.setAttribute('alignment-baseline', 'middle');
    number.textContent = String(tramos[i].number);
    panel.appendChild(number);
    const text = document.createElementNS(SVG_NS, 'text');
    text.setAttribute('class', 'panel-row');
    text.setAttribute('font-family', 'Arial, sans-serif');
    text.setAttribute('font-size', String(fontSize));
    text.setAttribute('fill', '#263238');
    text.setAttribute('x', x + 18);
    text.setAttribute('y', y - 9);
    for (const [lineIndex, line] of wrapText(tramos[i].nombre, Math.max(18, Math.floor((columnWidth - 62) / (fontSize * 0.56)))).entries()) {
      const tspan = document.createElementNS(SVG_NS, 'tspan');
      tspan.setAttribute('x', x + 18);
      tspan.setAttribute('dy', lineIndex === 0 ? '0' : String(fontSize + 3));
      tspan.textContent = line;
      text.appendChild(tspan);
    }
    panel.appendChild(text);
  }
  svg.appendChild(panel);
  appendMapDecorations(mapArea, projector);
  return svg;
}

function wrapText(value, maxChars) {
  const words = String(value).split(/\s+/).filter(Boolean);
  const lines = [];
  let line = '';
  for (const word of words) {
    const next = line ? `${line} ${word}` : word;
    if (line && next.length > maxChars) {
      lines.push(line);
      line = word;
    } else line = next;
  }
  if (line) lines.push(line);
  return lines.length ? lines : ['Tramo vial'];
}

function appendMapDecorations(mapArea, projector) {
  const legendY = projector.mapY + projector.mapHeight - 28;
  const legend = document.createElementNS(SVG_NS, 'g');
  legend.setAttribute('id', 'leyenda');
  const legendTitle = document.createElementNS(SVG_NS, 'text');
  legendTitle.setAttribute('class', 'legend-text');
  legendTitle.setAttribute('font-family', 'Arial, sans-serif');
  legendTitle.setAttribute('font-size', '12');
  legendTitle.setAttribute('fill', '#263238');
  legendTitle.setAttribute('x', projector.mapX + 20);
  legendTitle.setAttribute('y', legendY - 20);
  legendTitle.textContent = 'LEYENDA';
  legend.appendChild(legendTitle);
  for (const [index, item] of ['Vias mejoradas', 'Vias existentes'].entries()) {
    const line = document.createElementNS(SVG_NS, 'line');
    line.setAttribute('x1', projector.mapX + 20 + index * 150);
    line.setAttribute('x2', projector.mapX + 48 + index * 150);
    line.setAttribute('y1', legendY);
    line.setAttribute('y2', legendY);
    line.setAttribute('stroke', index ? '#b7c0c8' : '#2f7d5b');
    line.setAttribute('stroke-width', '4');
    line.setAttribute('stroke-linecap', 'round');
    legend.appendChild(line);
    const text = document.createElementNS(SVG_NS, 'text');
    text.setAttribute('class', 'legend-text');
    text.setAttribute('font-family', 'Arial, sans-serif');
    text.setAttribute('font-size', '12');
    text.setAttribute('fill', '#263238');
    text.setAttribute('x', projector.mapX + 54 + index * 150);
    text.setAttribute('y', legendY + 4);
    text.textContent = item;
    legend.appendChild(text);
  }
  mapArea.appendChild(legend);
  const north = document.createElementNS(SVG_NS, 'g');
  north.setAttribute('id', 'norte');
  const northX = projector.mapX + projector.mapWidth - 65;
  const northY = projector.mapY + 50;
  const arrow = document.createElementNS(SVG_NS, 'polygon');
  arrow.setAttribute('points', `${northX},${northY - 24} ${northX - 10},${northY + 4} ${northX},${northY - 1} ${northX + 10},${northY + 4}`);
  arrow.setAttribute('fill', '#263238');
  north.appendChild(arrow);
  const northText = document.createElementNS(SVG_NS, 'text');
  northText.setAttribute('class', 'north-text');
  northText.setAttribute('font-family', 'Arial, sans-serif');
  northText.setAttribute('font-size', '12');
  northText.setAttribute('fill', '#263238');
  northText.setAttribute('x', northX);
  northText.setAttribute('y', northY - 30);
  northText.setAttribute('text-anchor', 'middle');
  northText.textContent = 'N';
  north.appendChild(northText);
  mapArea.appendChild(north);
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
    const requestedBounds = readBounds();
    els.zoom.value = String(requestedBounds.zoom);
    setStatus('Calculando encuadre de tramos...');
    const tramoData = await loadTramos(requestedBounds);
    const bounds = { ...requestedBounds, ...tramoData.frameBounds };
    const projector = buildProjector(bounds);
    const tramos = tramoData.tramos;
    for (const tramo of tramos) {
      const projectedLines = tramo.lines.map((coords) => coords
        .map(([lng, lat]) => projector.pointFromLngLat(lng, lat))
        .filter(([px, py]) => Number.isFinite(px) && Number.isFinite(py)));
      tramo.d = projectedLines.map(lineToPath).filter(Boolean).join(' ');
      tramo.marker = midpointOfLines(projectedLines);
    }
    setStatus('Leyendo PMTiles y preparando SVG...');
    const { roads, roadCount, tileCount } = await loadRoads(bounds, projector);
    tramos.forEach((tramo, index) => { tramo.number = index + 1; });
    const svg = createSvg(projector, roads, tramos);
    els.preview.innerHTML = '';
    els.preview.appendChild(svg);
    currentSvg = svg;
    els.download.disabled = false;
    els.meta.textContent = `${roadCount} calles en ${roads.length} objetos | ${tramos.length} tramos | panel vectorial`;
    setStatus(`SVG generado correctamente.${tramoData.warning ? `\nAdvertencia: ${tramoData.warning}` : ''}\nTiles leidos: ${tileCount}\nCalles: ${roadCount} en ${roads.length} objetos\nTramos numerados: ${tramos.length}`, tramoData.warning ? '' : 'ok');
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
