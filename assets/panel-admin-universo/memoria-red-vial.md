# Memoria Técnica: Proyecto Red Vial (Fuerza Tacna)

## 1. Resumen del Proyecto
El módulo **Red Vial** es un sistema cartográfico interactivo para la plataforma web Fuerza Tacna. Permite al administrador trazar tramos viales (líneas, curvas Bézier), gestionar información técnica/ciudadana y adjuntar fotografías (Antes/Después, Galerías). Al público le presenta un mapa vectorial en 3D (Protomaps/PMTiles) rápido, moderno y altamente optimizado, enfocado en mostrar el impacto real de las obras en la ciudad con un lenguaje humano y accesible.

## 2. Arquitectura Actual
* **Frontend Público:** Vanilla JS, HTML5, CSS3, MapLibre GL JS v3.
* **Frontend Admin:** Vanilla JS interactivo, Drag & Drop (reordenamiento de fotos y capas), Toasts personalizados.
* **Backend:** PHP puro (sin frameworks). API RESTful (JSON).
* **Base de Datos:** MySQL (Tablas: `panel_tramos_viales`, `panel_tramos_viales_fotos`, `panel_mapa_referencias`).
* **Mapas (Tiles):** Archivos `.pmtiles` servidos de forma local (Offline) mediante un proxy en PHP (`pmtiles_proxy.php`). No hay dependencia económica de Google Maps ni Mapbox.
* **Procesamiento de Imágenes:** Librería GD de PHP. Conversión automática a formato `.webp` y generación de miniaturas (thumbnails).

## 3. Archivos Involucrados
### Backend / APIs
* `assets/panel-admin-universo/mapa_redvial_api.php`: CRUD de tramos viales, migraciones automáticas.
* `assets/panel-admin-universo/fotos_redvial_api.php`: Subida, conversión WebP, reordenamiento D&D, marcado de portada.
* `assets/panel-admin-universo/mapa_referencias_api.php`: CRUD para hitos urbanos (Puntos/Titanes).

### Frontend Administrador
* `assets/panel-admin-universo/gestor-cartografico.php`: Interfaz unificada para dibujar vías, colocar hitos, ver dashboard, gestionar fotos en vivo y editar atributos.

### Frontend Público
* `assets/universoobras/red-vial-module.js`: Sandbox principal. Inicializa el mapa, renderiza PMTiles, aplica perfiles visuales (Ciudadano, Técnico, Impacto) y construye el panel de la obra.
* `assets/universoobras/mapa-sidebar.css` y `mapa-obras.css`: Estilos visuales compartidos y animaciones.

## 4. Fases Ejecutadas
* **Fase RV1 (Core Cartográfico):** 
  * Implementación de MapLibre y PMTiles. 
  * Herramienta de dibujo de vías con nodos rectos y nodos de control para curvas cuadráticas (Bézier).
* **Fase RV2 (Multimedia):** 
  * Integración de BD para fotos de red vial. 
  * Conversor WebP con redimensionamiento dinámico (WEB_MAX, THUMB_MAX). 
  * Sistema Drag & Drop para reordenar fotos y selector de foto principal.
* **Fase RV3 (Expansión de Datos y Formato Ciudadano):** 
  * Inclusión de variables estratégicas (Mensaje principal, Avance físico, Inversión, Fechas, Beneficiarios, Antes/Ahora).
  * Compactación visual del panel (Ficha editorial en vez de listado pesado).
  * Autocompletado inteligente de "Sector" detectando los barrios de la capa PMTiles.
  * Conversión a "lenguaje ciudadano" (ej. 15600000 -> S/ 15.6 millones, auto-sufijo de "metros" o "km").
* **Fase RV4-A1 (UX Admin - Toasts):** 
  * Reemplazo de los molestos `alert()` nativos por un sistema de notificaciones en cascada elegante (`showToast`).
* **Fase RV4-B1 (Dashboard Admin):** 
  * Inyección de tarjetas de resumen rápido en la lista de vías (Total, Activas, Entregadas, En Ejecución) procesadas en crudo desde el GeoJSON en memoria JS.

## 5. Decisiones Técnicas Clave
1. **Migraciones Silenciosas (Auto-reparación):** Para evitar accesos manuales a phpMyAdmin, la API inyecta las columnas faltantes usando bloques `try/catch` individuales antes de cada INSERT o UPDATE.
2. **Capa Invisible (Hitbox) para PMTiles:** MapLibre ignora nombres de calles o barrios ocultos por colisión de textos. Se inyectó una capa circular de opacidad 0 (`places-hitbox`) para garantizar que la detección espacial de sectores funcione siempre.
3. **Vanilla JS sin Frameworks Reactivos:** Se mantiene para asegurar una integración limpia con la arquitectura existente de "Barba.js" (Transiciones de página fluidas) y evitar dependencias de compilación (npm/webpack) en el servidor de producción.
4. **Unificación de Galería en Vía (Inline):** En lugar de forzar al admin a ir a una página separada, el Gestor Cartográfico carga dinámicamente las herramientas de foto cuando se selecciona un tramo.

## 6. Bugs Críticos Resueltos
* `SQLSTATE Column not found (distrito)`: Solucionado separando la migración monolítica de `ALTER TABLE` en consultas individuales atrapadas por excepciones, garantizando robustez independientemente del estado previo de la BD.
* `htmlMetrics is not defined`: Ocurría por una variable desactualizada en el chequeo de panel sin datos; resuelto usando la nueva jerarquía de capas (`htmlNivel1`, `htmlNivel2`).
* **Filtros Recortados (Admin):** Selectores apretados se corrigieron aplicando `flex-wrap` y anchos flexibles base (`flex: 1 1 130px`) para saltar de línea limpiamente en pantallas estrechas.

## 7. Tareas Pendientes (Backlog Inmediato)
* **[RV4-A2]** Reemplazar los `confirm()` nativos de borrado de vías y fotos por Modales elegantes de HTML/CSS con backdrop oscuro.
* **[RV4-B2]** Enriquecer el Dashboard de vías inyectando una métrica de "Con foto / Sin foto". Requiere cruzar la información con un `LEFT JOIN` en el endpoint de listado en PHP.
* **[RV3-C4]** Implementar en el panel público de la obra un **Slider Interactivo "Antes / Después"** que permita deslizar una barra para revelar ambas fotos.
* **[RV3-C5]** Crear un sistema de **Lightbox (Pantalla Completa)** para las fotos públicas.

## 8. Próximos Pasos (Sugeridos)
Se sugiere iniciar la próxima sesión atacando **RV4-A2 (Modales Custom)** para cerrar la limpieza de UX de alertas del sistema operativo en el panel administrativo, o saltar directamente al impacto público con el **Slider de Antes/Después**.