<?php
require_once __DIR__ . '/config.php';
require_login();

$mensaje = '';
if (isset($_GET['success'])) {
    $mensaje = '<div class="msg-success">¡Obra agregada con éxito en Google Sheets! Ya puedes verla en tu Excel y en la pestaña de Fotos.</div>';
} elseif (isset($_GET['error'])) {
    $mensaje = '<div class="msg-error">Error: ' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nueva Obra - Panel Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #020617; color: #e5e7eb; min-height: 100vh; margin: 0; padding-bottom: 40px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 20; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .app-main { margin-top: 72px; display: flex; justify-content: center; padding: 20px; }
        .card { width: 100%; max-width: 700px; background: #020617; border-radius: 18px; padding: 24px 28px 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7), 0 0 0 1px rgba(148, 163, 184, 0.15); border: 1px solid rgba(148, 163, 184, 0.15); }
        h1 { margin-top: 0; font-size: 22px; color: #f9fafb; margin-bottom: 20px; }
        label { font-size: 13px; color: #e5e7eb; display: block; margin-top: 15px; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #020617; color: #e5e7eb; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus, select:focus { border-color: #2563eb; }
        .btn-submit { margin-top: 25px; width: 100%; padding: 12px; background: #2563eb; color: #f9fafb; border: none; font-weight: 600; font-size: 14px; border-radius: 999px; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #1d4ed8; }
        .msg-success { background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #059669; }
        .msg-error { background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #dc2626; }
        .row { display: flex; gap: 15px; }
        .row > div { flex: 1; }
    </style>
</head>
<body>
    <header class="app-header">
      <nav>
        <a href="index.php">📷 Fotos</a>
        <a href="agregar_obra.php" class="active">➕ Agregar Obra</a>
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
            <h1>Agregar Nueva Obra</h1>
            <?= $mensaje ?>
        
            <form action="guardar_obra.php" method="POST">
                <label>Segmento (Hoja de Excel destino):</label>
                <select name="segmento" required>
                    <option value="EDUCACION">Educación</option>
                    <option value="AGUA Y SANEAMIENTO">Agua y Saneamiento</option>
                    <option value="TRANSPORTE">Transporte</option>
                    <option value="AGRICULTURA">Agricultura</option>
                    <option value="SOCIAL">Social</option>
                    <option value="VIAS">Vías y Caminos</option>
                </select>

                <label>Nombre de la Obra:</label>
                <input type="text" name="nombre" required placeholder="Ej. Creación de colegio en Viñani...">

                <label>Estado:</label>
                <select name="estado" required>
                    <option value="Entregado">Entregado</option>
                    <option value="En construcción">En construcción</option>
                    <option value="Paralizado">Paralizado</option>
                    <option value="Buena Pro">Buena Pro</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="En estudios">En estudios</option>
                </select>

                <label>Monto Referencial (S/):</label>
                <input type="text" name="monto" placeholder="Ej. 1,500,000.00">

                <div class="row">
                    <div><label>Distrito:</label><input type="text" name="distrito" placeholder="Ej. Gregorio Albarracín"></div>
                    <div><label>Provincia:</label><input type="text" name="provincia" value="Tacna"></div>
                </div>

                <div class="row">
                    <div><label>Coordenada X (Longitud):</label><input type="text" name="x" placeholder="Ej. 0.345"></div>
                    <div><label>Coordenada Y (Latitud):</label><input type="text" name="y" placeholder="Ej. 0.678"></div>
                </div>

                <button type="submit" class="btn-submit">Guardar Obra en Excel</button>
            </form>
        </div>
    </main>
</body>
</html>