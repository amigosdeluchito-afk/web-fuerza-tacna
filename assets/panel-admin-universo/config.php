<?php
// config.php – Configuración común del panel de fotos + usuarios/login

// =======================
//  SESIÓN
// =======================
function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

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
$legacyConfig = [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'tacnwddf_adminfreddy',
    'DB_PASS' => 'adminfreddy14021993',
    'DB_NAME' => 'tacnwddf_fuerza',
    'IA_HASH_SALT' => 'FuerzaTacna_IA_SecretSalt_2024!@#',
    'OPENAI_API_KEY' => '',
    'OPENAI_KEY_ENCRYPTION_SECRET' => 'FuerzaTacna_AES_MasterKey_2024**!!',
    'CRON_SYNC_TOKEN' => '',
];

function load_external_config(string $path): ?array {
    if (!is_file($path)) {
        return null;
    }

    try {
        $config = require $path;
    } catch (Throwable $e) {
        error_log('External configuration load failed');
        return null;
    }

    if (!is_array($config)) {
        error_log('External configuration load failed');
        return null;
    }

    $requiredKeys = [
        'DB_HOST',
        'DB_USER',
        'DB_PASS',
        'DB_NAME',
        'IA_HASH_SALT',
        'OPENAI_API_KEY',
        'OPENAI_KEY_ENCRYPTION_SECRET',
    ];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            error_log('External configuration load failed');
            return null;
        }
    }

    return $config;
}

function create_external_config(string $path, array $config): bool {
    if (is_file($path)) {
        return false;
    }

    try {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        $contents = "<?php\n\nreturn " . var_export($config, true) . ";\n";

        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new RuntimeException('write failed');
        }

        @chmod($tmp, 0600);

        if (is_file($path) || !rename($tmp, $path)) {
            throw new RuntimeException('rename failed');
        }

        @chmod($path, 0600);
        return true;
    } catch (Throwable $e) {
        if (isset($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }

        error_log('External configuration creation failed');
        return false;
    }
}

$configLocalPath = __DIR__ . '/data/config.local.php';
$externalConfig = load_external_config($configLocalPath);

if ($externalConfig === null && !is_file($configLocalPath)) {
    create_external_config($configLocalPath, $legacyConfig);
    $externalConfig = load_external_config($configLocalPath);
}

$appConfig = $externalConfig ?? $legacyConfig;

define('PRIVATE_CONFIG_ACTIVE', $externalConfig !== null);
define('DB_HOST', $appConfig['DB_HOST'] ?? $legacyConfig['DB_HOST']);
define('DB_USER', $appConfig['DB_USER'] ?? $legacyConfig['DB_USER']);
define('DB_PASS', $appConfig['DB_PASS'] ?? $legacyConfig['DB_PASS']);
define('DB_NAME', $appConfig['DB_NAME'] ?? $legacyConfig['DB_NAME']);

// =======================
//  SEGURIDAD IA (SALT PARA IPs)
// =======================
define('IA_HASH_SALT', $appConfig['IA_HASH_SALT'] ?? $legacyConfig['IA_HASH_SALT']);
define('OPENAI_API_KEY', $appConfig['OPENAI_API_KEY'] ?? $legacyConfig['OPENAI_API_KEY']); // Dejar vacío en Git, poner la clave directa en cPanel
define('OPENAI_KEY_ENCRYPTION_SECRET', $appConfig['OPENAI_KEY_ENCRYPTION_SECRET'] ?? $legacyConfig['OPENAI_KEY_ENCRYPTION_SECRET']); // Llave inventada, GitHub no la bloquea
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



// =======================
//  CSRF
// =======================

function csrf_token(): string {
    if (isset($_SESSION['_csrf_token']) && is_string($_SESSION['_csrf_token']) && $_SESSION['_csrf_token'] !== '') {
        return $_SESSION['_csrf_token'];
    }

    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function csrf_request_token(): ?string {
    if (isset($_POST['_csrf'])) {
        return is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    }

    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return is_string($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null;
    }

    return null;
}

function csrf_validate(?string $token = null): bool {
    $sessionToken = $_SESSION['_csrf_token'] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    if ($token === null) {
        $token = csrf_request_token();
    }

    if (!is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function require_csrf(): void {
    if (csrf_validate()) {
        return;
    }

    http_response_code(403);
    exit('Solicitud no valida');
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
