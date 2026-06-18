# Memoria Técnica Reforzada — Proyecto Red Vial Fuerza Tacna

## 1. Estado general del módulo

El módulo **Red Vial** forma parte de la plataforma Fuerza Tacna / Universo Obras. Permite gestionar tramos viales desde el panel administrativo y mostrarlos al público en un mapa interactivo moderno con MapLibre + PMTiles.

Actualmente el sistema ya permite:

* Crear tramos viales.
* Editar tramos existentes.
* Activar/desactivar vías sin eliminarlas.
* Dibujar líneas rectas.
* Usar curvas Bézier manuales.
* Guardar datos técnicos y ciudadanos.
* Adjuntar fotos por tramo.
* Definir foto portada.
* Mostrar galería pública.
* Mostrar ficha pública compacta con datos estratégicos.
* Mostrar distrito, sector, longitud, inversión, beneficiarios, fechas, antes/ahora textual.
* Usar toasts en el admin.
* Usar confirmaciones elegantes en el admin.
* Mostrar dashboard/resumen rápido de vías guardadas.

---

## 2. Arquitectura actual

### Frontend público

* Vanilla JS.
* MapLibre GL JS.
* PMTiles locales.
* Panel público construido desde `red-vial-module.js`.
* Estilos principales en `mapa-sidebar.css` y `mapa-obras.css`.

### Frontend admin

* `gestor-cartografico.php`.
* Vanilla JS.
* Dibujo de tramos.
* Edición de nodos.
* Curvas Bézier.
* Gestión de fotos en vivo.
* Toasts personalizados.
* Modales de confirmación.
* Dashboard de vías guardadas.

### Backend

* PHP puro.
* APIs JSON.
* MySQL.
* Migraciones silenciosas con `try/catch`.

### Mapas

* PMTiles local.
* Proxy estable mediante:

`assets/data/pmtiles_proxy.php`

No tocar el proxy salvo que el problema sea específicamente de carga PMTiles.

---

## 3. Archivos principales

### APIs

* `assets/panel-admin-universo/mapa_redvial_api.php`
* `assets/panel-admin-universo/fotos_redvial_api.php`
* `assets/panel-admin-universo/mapa_referencias_api.php`

### Admin

* `assets/panel-admin-universo/gestor-cartografico.php`

### Público

* `assets/universoobras/red-vial-module.js`
* `assets/universoobras/mapa-sidebar.css`
* `assets/universoobras/mapa-obras.css`

### PMTiles

* `assets/data/tacna.pmtiles`
* `assets/data/pmtiles_proxy.php`

---

## 4. Base de datos relevante

Tablas principales:

* `panel_tramos_viales`
* `panel_tramos_viales_fotos`
* `panel_mapa_referencias`

Campos importantes de `panel_tramos_viales`:

* `id`
* `nombre`
* `tipo`
* `estado`
* `activo`
* `coordenadas`
* `datos_edicion`
* `descripcion`
* `mensaje_principal`
* `sector`
* `distrito`
* `tramo_desde`
* `tramo_hasta`
* `longitud`
* `longitud_valor`
* `longitud_unidad`
* `longitud_cuadras`
* `beneficiarios`
* `situacion_antes`
* `situacion_ahora`
* `avance_fisico`
* `monto_inversion`
* `fecha_inicio`
* `fecha_entrega`

Importante:

* No eliminar `longitud`, porque sirve como fallback.
* Usar `longitud_valor`, `longitud_unidad` y `longitud_cuadras` como sistema nuevo.
* `distrito` se maneja manualmente con select.
* `sector` puede sugerirse desde PMTiles, pero siempre debe ser editable.

---

## 5. Fases completadas

### RV1 — Core Cartográfico

Completado:

* MapLibre + PMTiles.
* Render público de Red Vial.
* Lectura dinámica desde MySQL.
* Filtros por tipo de vía.
* Fallback estable.

### RV2 — Editor admin

Completado:

* Dibujo de tramos.
* Edición de nodos.
* Activar/desactivar vías.
* Lista de vías guardadas.
* Curvas Bézier manuales.
* Guardado de `datos_edicion`.
* Línea cocida/interpolada en `coordenadas`.

### RV2.9 — Lista admin mejorada

Completado:

* Vías guardadas.
* Búsqueda.
* Filtros.
* Activar/desactivar.
* Centrar vía.
* Editar.
* Gestión visual compacta.

### RV3 — Ficha pública y datos ciudadanos

Completado:

* Foto portada.
* Mini galería.
* Mensaje principal.
* Inversión formateada.
* Longitud con unidad.
* Equivalencia en cuadras.
* Beneficiarios.
* Fechas.
* Distrito y sector.
* Tramo desde/hasta.
* Antes/Ahora textual.
* Compartir vía.
* Panel público compacto y editorial.

### RV3-C2.2 — Distrito y sector

Completado:

* Distrito con select manual.
* Sector/Zona editable.
* Sugerencia de sector mediante PMTiles cuando sea posible.
* No sobrescribir sector si el admin ya escribió manualmente.

### RV4-A1 — Toasts admin

Completado:

* Sistema `showToast`.
* Reemplazo progresivo de `alert()`.
* Mensajes success, warning, error, info.

### RV4-A2 — Confirmaciones elegantes

Completado:

* Reemplazo de `confirm()` nativo por modal propio.
* Confirmación para acciones delicadas.
* Diseño integrado al admin.
* No se tocó BD ni APIs.

### RV4-B1 — Dashboard rápido admin

Completado:

* Total de vías.
* Activas.
* Inactivas.
* Entregadas.
* En ejecución.
* Proyectadas.
* Cálculo desde `rvGeoJSON.features`.
* Dashboard compacto en “Vías Guardadas”.

### RV4-B1.1 — Ajuste visual dashboard/filtros

Completado:

* Filtros ya no aparecen cortados.
* Mejor responsive.
* No se rompió el scroll.
* No se tocó backend.

---

## 6. Bugs críticos ya resueltos

### Error SQL `Unknown column distrito`

Solución:

* Migraciones separadas.
* `ALTER TABLE` individuales.
* `try/catch`.
* Evitar migración monolítica.

### Error JS `htmlMetrics is not defined`

Solución:

* Unificación de variables del panel público.
* Uso correcto de bloques de jerarquía visual como `htmlNivel1`, `htmlNivel2` o nombres equivalentes existentes.
* No usar variables inexistentes dentro del template.

### Filtros recortados

Solución:

* `flex-wrap`.
* Anchos flexibles.
* Layout responsive.

### Sector no sugerido

Solución:

* Revisar layers reales.
* Usar capa auxiliar/hitbox si aplica.
* No prometer detección 100%.
* Si no encuentra sector, dejar manual.

---

## 7. Decisiones técnicas importantes

1. No usar React ni frameworks nuevos.
2. Mantener Vanilla JS.
3. No tocar PMTiles proxy salvo problema específico de PMTiles.
4. Mantener Barba.js compatible.
5. No duplicar listeners de clic sobre vías públicas.
6. No crear otra función paralela si ya existe `abrirPanelRedVial(props)`.
7. No romper `datos_edicion`, porque guarda la receta de curvas Bézier.
8. No cambiar `coordenadas` sin entender que contiene la línea cocida/interpolada.
9. No eliminar campos antiguos si sirven como fallback.
10. Todo cambio debe ser microfase, no refactor gigante.

---

## 8. Zonas delicadas que NO deben tocarse sin permiso

No tocar salvo que el problema sea específicamente ahí:

* `pmtiles_proxy.php`
* URL del PMTiles.
* `.htaccess`
* carga base de PMTiles.
* Curvas Bézier.
* `datos_edicion`.
* Gestión de nodos.
* API de fotos.
* Conversión WebP.
* Galería pública.
* Panel público si el cambio es solo admin.
* Admin si el cambio es solo ficha pública.
* Sistema de referencias urbanas si el cambio es solo Red Vial.
* Estilos globales de toda la página.

---

## 9. Backlog pausado

No implementar todavía salvo indicación directa:

* Fotos Antes/Después visuales.
* Slider interactivo Antes/Después.
* Lightbox público.
* Con foto / Sin foto en dashboard.
* Previsualizar ficha pública desde admin.
* Pulido final completo de ficha pública.
* Leyenda pública avanzada.
* Filtros ciudadanos públicos nuevos.

---

## 10. Protocolo obligatorio antes de modificar

Antes de tocar código, responder siempre:

1. Qué archivo se tocará.
2. Qué función o bloque exacto se tocará.
3. Qué NO se tocará.
4. Qué riesgo existe.
5. Cómo se probará.
6. Si requiere BD o no.
7. Si requiere API o no.
8. Si es admin o público.
9. Si afecta PMTiles o no.
10. Si afecta fotos o curvas o no.

No aplicar cambios masivos sin diagnóstico.

---

## 11. Pruebas mínimas después de cada cambio

Después de cualquier cambio, probar:

### Admin

* Cargar Gestor Cartográfico.
* Entrar a Vías.
* Crear tramo.
* Editar tramo.
* Guardar.
* Activar/desactivar.
* Ver toast.
* Ver modal si aplica.
* Confirmar que lista se actualiza.
* Confirmar que dashboard se actualiza.

### Curvas

* Crear curva.
* Mover nodo.
* Mover control Bézier.
* Guardar.
* Recargar.
* Ver que la curva se conserva.

### Fotos

* Subir foto.
* Ver miniatura.
* Marcar portada.
* Activar/desactivar.
* Confirmar que no se rompe la galería.

### Público

* Abrir mapa público.
* Clic en vía.
* Ver panel.
* Ver portada.
* Ver galería.
* Ver inversión.
* Ver longitud.
* Ver distrito/sector.
* Ver que no haya errores en consola.

---

## 12. Regla de oro

Cada mejora debe ser una microfase aislada.

Nunca mezclar en un mismo parche:

* BD + diseño público + fotos + curvas.
* Admin + público sin necesidad.
* PMTiles + UI.
* Refactor grande + feature nueva.

Primero diagnosticar, luego aplicar el cambio mínimo.