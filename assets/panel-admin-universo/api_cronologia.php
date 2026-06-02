<?php
// api_cronologia.php - Envía los datos de la historia a la página web
require_once __DIR__ . '/config.php';

// Le decimos al navegador que lo que vamos a devolver es JSON (datos puros, sin diseño)
header('Content-Type: application/json; charset=utf-8');

try {
    // Conectamos a la base de datos usando tu función que ya existe en config.php
    $db = get_db_connection();
    
    // Traemos las fechas visibles (estado = 1) y las ordenamos por la columna 'orden'
    $stmt = $db->query("SELECT id, fecha_texto, titulo, descripcion, imagen, orden FROM cronologia_historia WHERE estado = 1 ORDER BY orden ASC");
    $cronologia = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Empaquetamos los datos y los enviamos
    echo json_encode(['ok' => true, 'datos' => $cronologia]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}