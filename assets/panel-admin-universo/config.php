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
    die("No se pudo resolver PROJECT_ROOT. Revisa la ruta en config.php");
}

// Carpeta donde deben guardarse las fotos de las obras
$FOTOS_BASE = realpath(__DIR__ . '/../universoobras/IMG/fotos-obras');


// Tamaños máximos (en píxeles) para redimensionar (si los usas)
define('WEB_MAX',   1600); // lado más largo versión web
define('THUMB_MAX',  400); // lado más largo thumbnail

// =======================
//  USUARIOS / LOGIN
// =======================

// Archivos donde se guardan usuarios y log
const USERS_FILE = __DIR__ . '/data/usuarios.json';
const LOG_FILE   = __DIR__ . '/data/historial.log';

/**
 * Cargar usuarios desde JSON.
 * Estructura:
 * {
 *   "admin": { "password": "hash_bcrypt", "role": "admin" },
 *   "juan" : { "password": "hash_bcrypt", "role": "editor" }
 * }
 */
function load_users() {
    if (!file_exists(USERS_FILE)) {
        // Crear usuario admin por defecto si no existe el archivo
        if (!is_dir(dirname(USERS_FILE))) {
            mkdir(dirname(USERS_FILE), 0777, true);
        }
        $default = [
            'admin' => [
                // contraseña por defecto: admin123  (cámbiala luego desde el módulo de usuarios)
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role'     => 'admin',
            ],
        ];
        file_put_contents(USERS_FILE, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }

    $json = file_get_contents(USERS_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = [];
    }
    return $data;
}

/**
 * Guardar usuarios en el archivo JSON
 */
function save_users(array $users) {
    if (!is_dir(dirname(USERS_FILE))) {
        mkdir(dirname(USERS_FILE), 0777, true);
    }
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
