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

// =======================
//  SEGURIDAD IA (SALT PARA IPs)
// =======================
define('IA_HASH_SALT', 'FuerzaTacna_IA_SecretSalt_2024!@#');
define('OPENAI_API_KEY', ''); // Dejar vacío en Git, poner la clave directa en cPanel
define('OPENAI_KEY_ENCRYPTION_SECRET', 'FuerzaTacna_AES_MasterKey_2024**!!'); // Llave inventada, GitHub no la bloquea
define('IA_DEBUG_MODE', false); // Poner en true solo para diagnosticar problemas de enrutamiento

function encrypt_api_key($plain_text) {
    if (!defined('OPENAI_KEY_ENCRYPTION_SECRET') || trim(OPENAI_KEY_ENCRYPTION_SECRET) === '') return '';
    $method = 'aes-256-cbc';
    $key = hash('sha256', OPENAI_KEY_ENCRYPTION_SECRET, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($plain_text, $method, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_api_key($encoded_payload) {
    if (!defined('OPENAI_KEY_ENCRYPTION_SECRET') || trim(OPENAI_KEY_ENCRYPTION_SECRET) === '') return '';
    $method = 'aes-256-cbc';
    $key = hash('sha256', OPENAI_KEY_ENCRYPTION_SECRET, true);
    $decoded = base64_decode($encoded_payload);
    if ($decoded === false) return '';
    $iv_len = openssl_cipher_iv_length($method);
    if (strlen($decoded) < $iv_len) return '';
    $iv = substr($decoded, 0, $iv_len);
    $encrypted = substr($decoded, $iv_len);
    $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
}

function get_db_connection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
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
    $db = get_db_connection();
    
    $db->exec("CREATE TABLE IF NOT EXISTS panel_historial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time DATETIME NOT NULL,
        user VARCHAR(50) NULL,
        tipo VARCHAR(50) NULL,
        detalle TEXT NULL,
        extra TEXT NULL
    )");

    $stmt = $db->prepare("INSERT INTO panel_historial (time, user, tipo, detalle, extra) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        date('Y-m-d H:i:s'),
        current_user(),
        $tipo,
        $detalle,
        json_encode($extra, JSON_UNESCAPED_UNICODE)
    ]);
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
