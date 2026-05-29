<?php
// config.php – Configuración común del panel de fotos + usuarios/login

// =======================
//  SESIÓN
// =======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================
//  RUTAS DEL PROYECTO
// =======================

// Ruta base del proyecto público (donde está tu mapa y las IMG)
$PROJECT_ROOT = realpath(__DIR__ . '/../universoobras');  // ajusta si tu carpeta pública se llama distinto

if ($PROJECT_ROOT === false) {
    // PLAN B: Si no está dentro de 'assets', buscar en la carpeta principal
    $PROJECT_ROOT = realpath(__DIR__ . '/../../universoobras');
    if ($PROJECT_ROOT === false) {
        die("<h1 style='color:red; font-family:sans-serif;'>Error de carpetas</h1><p style='font-family:sans-serif;'>El panel de administración no logra encontrar la carpeta <b>universoobras</b> en tu servidor. Revisa que sí la hayas subido a Internet.</p>");
    }
}

// Carpeta donde deben guardarse las fotos de las obras
$FOTOS_BASE = $PROJECT_ROOT . '/IMG/fotos-obras';


// Tamaños máximos (en píxeles) para redimensionar (si los usas)
define('WEB_MAX',   1600); // lado más largo versión web
define('THUMB_MAX',  400); // lado más largo thumbnail

// =======================
//  USUARIOS / LOGIN
// =======================

// Archivo donde se guardan logs de actividad
const LOG_FILE   = __DIR__ . '/data/historial.log';

// =======================
//  BASE DE DATOS (MYSQL)
// =======================
const DB_HOST = 'localhost';
const DB_USER = 'tacnwddf_adminfreddy';
const DB_PASS = 'adminfreddy14021993';
const DB_NAME = 'tacnwddf_fuerza'; 

function get_db_connection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Error BD: Crea la base de datos '" . DB_NAME . "' en phpMyAdmin. Detalle: " . $e->getMessage());
    }
}

/**
 * Cargar usuarios desde MySQL.
 */
function load_users() {
    $db = get_db_connection();
    
    // Crear la tabla automáticamente si no existe en la base de datos
    $db->exec("CREATE TABLE IF NOT EXISTS panel_usuarios (
        username VARCHAR(50) PRIMARY KEY,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin'
    )");

    $stmt = $db->query("SELECT * FROM panel_usuarios");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach ($rows as $row) {
        $data[$row['username']] = [
            'password' => $row['password'],
            'role'     => $row['role']
        ];
    }

    // Si la tabla está vacía, crear el usuario admin por defecto
    if (empty($data)) {
        $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO panel_usuarios (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $default_pass, 'admin']);
        
        $data['admin'] = [
            'password' => $default_pass,
            'role'     => 'admin',
        ];
    }
    return $data;
}

/**
 * Guardar usuarios en MySQL
 */
function save_users(array $users) {
    $db = get_db_connection();
    
    // Borramos los registros actuales y reinsertamos (emula el guardado idéntico al que tenías en JSON)
    $db->exec("DELETE FROM panel_usuarios");
    
    $stmt = $db->prepare("INSERT INTO panel_usuarios (username, password, role) VALUES (?, ?, ?)");
    foreach ($users as $username => $data) {
        $stmt->execute([
            $username, 
            $data['password'] ?? '', 
            $data['role'] ?? 'admin'
        ]);
    }
}

/**
 * Atajo para obtener usuarios siempre
 */
function get_users() {
    return load_users();
}

/**
 * Usuario actual (nombre) o null
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * ¿El usuario actual es admin?
 */
function is_admin() {
    $user = current_user();
    if (!$user) return false;
    $users = get_users();
    return isset($users[$user]) && (($users[$user]['role'] ?? '') === 'admin');
}

/**
 * Verificar login
 */
function check_login($username, $password) {
    $users = get_users();
    if (!isset($users[$username])) {
        return false;
    }
    return password_verify($password, $users[$username]['password']);
}



/**
 * Exigir que haya sesión iniciada
 */
function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exigir que el usuario actual sea admin
 */
function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        echo "Solo el administrador puede acceder a esta sección.";
        exit;
    }
}

// =======================
//  HISTORIAL / LOG
// =======================

/**
 * Registrar acción en el historial.
 *
 * $tipo    = código corto, ej: 'fotos_subir', 'fotos_eliminar', 'usuario_guardar'
 * $detalle = texto más humano, ej: 'Subió 3 fotos a IE Gustavo Pons'
 * $extra   = array opcional con datos adicionales (segmento, carpeta, etc.)
 */
function log_action($tipo, $detalle, $extra = []) {
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0777, true);
    }

    $entry = [
        'time'    => date('Y-m-d H:i:s'),
        'user'    => current_user(),
        'tipo'    => $tipo,
        'detalle' => $detalle,
        'extra'   => $extra,
    ];

    file_put_contents(
        LOG_FILE,
        json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

// =======================
//  HELPERS GENERALES
// =======================

/**
 * Asegurarse de que exista una carpeta.
 */
function ensure_dir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/**
 * Pequeño helper para slugs, por si lo necesitas.
 */
function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'item';
}
