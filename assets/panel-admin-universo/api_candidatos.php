<?php
// api_candidatos.php – API para gestionar a los candidatos
require_once __DIR__ . '/config.php';

// Permite que la API devuelva JSON
header('Content-Type: application/json; charset=utf-8');

$db = get_db_connection();
$action = $_REQUEST['action'] ?? '';

try {
    // 1. Obtener lista rápida (Para el menú principal del panel admin)
    if ($action === 'listar') {
        $stmt = $db->query("SELECT id, nombres, cargo_flotante, foto_perfil, estado FROM panel_candidatos ORDER BY orden ASC, id DESC");
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'candidatos' => $candidatos]);
        exit;
    }
    
    // 2. Obtener TODA la información de un candidato específico (Para el editor visual)
    if ($action === 'obtener') {
        $id = (int)($_GET['id'] ?? 0);
        
        // Datos principales
        $stmt = $db->prepare("SELECT * FROM panel_candidatos WHERE id = ?");
        $stmt->execute([$id]);
        $candidato = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$candidato) {
            echo json_encode(['ok' => false, 'error' => 'Candidato no encontrado']);
            exit;
        }
        
        // Extraer Relaciones (Listas dinámicas)
        $stmt = $db->prepare("SELECT * FROM panel_candidato_etiquetas WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['etiquetas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM panel_candidato_trayectoria WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['trayectoria'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM panel_candidato_propuestas WHERE candidato_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $candidato['propuestas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['ok' => true, 'candidato' => $candidato]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}