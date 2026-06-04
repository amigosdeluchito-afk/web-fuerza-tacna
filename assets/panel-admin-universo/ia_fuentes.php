<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// 1. Crear tabla de fuentes aprobadas si no existe
$db->exec("CREATE TABLE IF NOT EXISTS panel_fuentes_aprobadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('link', 'texto_manual', 'pdf', 'word') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    url_original VARCHAR(500) DEFAULT NULL,
    contenido_extraido MEDIUMTEXT,
    contenido_aprobado MEDIUMTEXT,
    palabras_clave TEXT,
    fuente VARCHAR(100) NOT NULL,
    prioridad INT DEFAULT 5,
    estado ENUM('borrador', 'pendiente_revision', 'aprobado', 'inactivo') DEFAULT 'borrador',
    fecha_creacion DATETIME NOT NULL,
    fecha_lectura DATETIME DEFAULT NULL,
    fecha_aprobacion DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mensaje = '';
if (isset($_SESSION['ia_msg'])) {
    $mensaje = $_SESSION['ia_msg'];
    unset($_SESSION['ia_msg']);
}

// Por ahora solo leemos la tabla, las acciones de INSERT vendrán en los Bloques 2 y 3.
$stmt = $db->query("SELECT * FROM panel_fuentes_aprobadas ORDER BY id DESC");
$fuentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fuentes Externas - Fuerza Tacna</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 2000; font-family: system-ui, sans-serif; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        .btn-ft { background-color: #801039; color: #ffc300; font-weight: bold; border: none; }
        .btn-ft:hover { background-color: #630c2c; color: white; }
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-aprobado { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .st-borrador { background: #f3f4f6; color: #4b5563; border: 1px solid #9ca3af; }
        .st-revision { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
        .st-inactivo { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .tipo-badge { font-family: monospace; font-size: 11px; padding: 2px 6px; background: #1e293b; color: #93c5fd; border-radius: 4px; }
    </style>
</head>
<body>

<header class="app-header">
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
    <a href="ia_conocimiento.php">📚 Base Conocimiento</a>
    <a href="ia_fuentes.php" class="active">🔗 Fuentes Externas</a>
    <?php if (is_admin()): ?>
    <a href="usuarios.php">👤 Usuarios</a>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>

<div class="container-fluid px-4" style="margin-top: 80px;">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-8">
            <h4 style="color:#801039; font-weight:bold;">🔗 Fuentes Externas Aprobadas</h4>
            <p class="text-muted">Espacio seguro de revisión (Human-in-the-Loop). Inyecta textos, noticias o links aprobados hacia la memoria de la IA.</p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-outline-success font-weight-bold mb-2">🔄 Sincronizar Hacia Conocimiento</button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid #e2e8f0;">
                <div class="card-body bg-light rounded d-flex gap-3">
                    <button class="btn btn-ft mr-2" onclick="abrirModalTexto()">➕ Nuevo Texto Manual</button>
                    <button class="btn btn-primary" onclick="abrirModalLink()">🌐 Extraer desde Link</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Fuentes -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>Listado de Fuentes (<?= count($fuentes) ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 13px;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 8%;">Tipo</th>
                            <th style="width: 25%;">Título / Categoría</th>
                            <th style="width: 32%;">URL Original / Fuente</th>
                            <th style="width: 10%;">Estado</th>
                            <th style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($fuentes)): ?>
                            <tr><td colspan="6" class="text-center py-4">No hay fuentes registradas. Inicia agregando un texto o extrayendo un link.</td></tr>
                        <?php else: ?>
                            <?php foreach($fuentes as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><span class="tipo-badge"><?= strtoupper($row['tipo']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['titulo']) ?></strong><br>
                                    <span class="text-muted">Cat: <?= htmlspecialchars($row['categoria']) ?></span>
                                </td>
                                <td>
                                    <?php if($row['url_original']): ?>
                                        <a href="<?= htmlspecialchars($row['url_original']) ?>" target="_blank" style="font-size:11px;"><?= htmlspecialchars(mb_strimwidth($row['url_original'], 0, 40, '...')) ?></a><br>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">(Sin URL externa)</span><br>
                                    <?php endif; ?>
                                    <small class="text-muted">Fte: <?= htmlspecialchars($row['fuente']) ?></small>
                                </td>
                                <td>
                                    <?php
                                        $clase = 'st-inactivo';
                                        if($row['estado'] == 'aprobado') $clase = 'st-aprobado';
                                        elseif($row['estado'] == 'borrador') $clase = 'st-borrador';
                                        elseif($row['estado'] == 'pendiente_revision') $clase = 'st-revision';
                                    ?>
                                    <span class="status-badge <?= $clase ?>"><?= str_replace('_', ' ', $row['estado']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2">Revisar/Editar</button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2">X</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Funciones placeholders para los modales que se implementarán en Bloques 2 y 3
    function abrirModalTexto() {
        alert("Próximamente: Abrirá el formulario para redactar un texto libre y guardarlo como Borrador o Aprobado.");
    }
    
    function abrirModalLink() {
        alert("Próximamente: Abrirá la herramienta para pegar una URL, descargar su texto de manera segura y limpiarlo.");
    }
</script>
</body>
</html>