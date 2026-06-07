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
        $stmt = $db->query("SELECT id, nombres, cargo_flotante, foto_perfil, foto_portada, estado FROM panel_candidatos ORDER BY orden ASC, id DESC");
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

    // 3. Guardar Candidato (Crear o Actualizar) y Procesar Imágenes
    if ($action === 'guardar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nombres = trim($_POST['nombres'] ?? '');
        $cargo_flotante = trim($_POST['cargo_flotante'] ?? '');
        $frase_cita = trim($_POST['frase_cita'] ?? '');
        $biografia = trim($_POST['biografia'] ?? '');
        $fb_titulo = trim($_POST['fb_titulo'] ?? '');
        $fb_descripcion = trim($_POST['fb_descripcion'] ?? '');
        $fb_url_perfil = trim($_POST['fb_url_perfil'] ?? '');

        if ($nombres === '') {
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
            exit;
        }

        $db->beginTransaction();

        try {
            $foto_perfil = null;
            
            // Procesar la foto de perfil si se subió una
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../universoobras/IMG/candidatos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
                // Creamos un nombre único aleatorio para no sobreescribir fotos (Ej. cand_17315512_8392.jpg)
                $filename = 'cand_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $uploadDir . $filename)) {
                    $foto_perfil = $filename;
                }
            }
            
            $foto_portada = null;
            if (isset($_FILES['foto_portada']) && $_FILES['foto_portada']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../universoobras/IMG/candidatos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = pathinfo($_FILES['foto_portada']['name'], PATHINFO_EXTENSION);
                $filename = 'cand_hover_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_portada']['tmp_name'], $uploadDir . $filename)) {
                    $foto_portada = $filename;
                }
            }

            if ($id > 0) {
                // Actualizar candidato existente
                $sql = "UPDATE panel_candidatos SET nombres=?, cargo_flotante=?, frase_cita=?, biografia=?, fb_titulo=?, fb_descripcion=?, fb_url_perfil=?";
                $params = [$nombres, $cargo_flotante, $frase_cita, $biografia, $fb_titulo, $fb_descripcion, $fb_url_perfil];
                
                if ($foto_perfil) {
                    $sql .= ", foto_perfil=?";
                    $params[] = $foto_perfil;
                }
                if ($foto_portada) {
                    $sql .= ", foto_portada=?";
                    $params[] = $foto_portada;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insertar nuevo candidato
                $sql = "INSERT INTO panel_candidatos (nombres, cargo_flotante, frase_cita, biografia, fb_titulo, fb_descripcion, fb_url_perfil, foto_perfil, foto_portada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nombres, $cargo_flotante, $frase_cita, $biografia, $fb_titulo, $fb_descripcion, $fb_url_perfil, $foto_perfil, $foto_portada]);
                $id = $db->lastInsertId();
            }

            // -- Procesar Relaciones Dinámicas (Eliminar viejas e insertar nuevas para mantener la sincronía) --
            
            // 1. Etiquetas (Badges)
            $db->prepare("DELETE FROM panel_candidato_etiquetas WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['etiquetas']) && is_array($_POST['etiquetas'])) {
                $stmtEt = $db->prepare("INSERT INTO panel_candidato_etiquetas (candidato_id, icono, texto, orden) VALUES (?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['etiquetas'] as $et) {
                    $icono = trim($et['icono'] ?? '');
                    $texto = trim($et['texto'] ?? '');
                    if ($texto !== '' || $icono !== '') {
                        $stmtEt->execute([$id, $icono, $texto, $ord++]);
                    }
                }
            }

            // 2. Trayectoria
            $db->prepare("DELETE FROM panel_candidato_trayectoria WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['trayectoria']) && is_array($_POST['trayectoria'])) {
                $stmtTr = $db->prepare("INSERT INTO panel_candidato_trayectoria (candidato_id, periodo, descripcion, orden) VALUES (?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['trayectoria'] as $tr) {
                    $periodo = trim($tr['periodo'] ?? '');
                    $descripcion = trim($tr['descripcion'] ?? '');
                    if ($periodo !== '' || $descripcion !== '') {
                        $stmtTr->execute([$id, $periodo, $descripcion, $ord++]);
                    }
                }
            }

            // 3. Propuestas
            $db->prepare("DELETE FROM panel_candidato_propuestas WHERE candidato_id = ?")->execute([$id]);
            if (!empty($_POST['propuestas']) && is_array($_POST['propuestas'])) {
                $stmtPr = $db->prepare("INSERT INTO panel_candidato_propuestas (candidato_id, icono, titulo, descripcion, orden) VALUES (?, ?, ?, ?, ?)");
                $ord = 0;
                foreach ($_POST['propuestas'] as $pr) {
                    $icono = trim($pr['icono'] ?? '');
                    $titulo = trim($pr['titulo'] ?? '');
                    $descripcion = trim($pr['descripcion'] ?? '');
                    if ($titulo !== '' || $descripcion !== '') {
                        $stmtPr->execute([$id, $icono, $titulo, $descripcion, $ord++]);
                    }
                }
            }

            $db->commit();
            echo json_encode(['ok' => true, 'id' => $id]);
            
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}