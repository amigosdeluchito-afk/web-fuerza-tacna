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
function fail_private_config(): void {
    error_log('Private application configuration unavailable');

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Error de configuración del servidor.';
    exit;
}

function load_external_config(string $path): array {
    if (!is_file($path)) {
        fail_private_config();
    }

    try {
        $config = require $path;
    } catch (Throwable $e) {
        fail_private_config();
    }

    if (!is_array($config)) {
        fail_private_config();
    }

    $requiredKeys = [
        'DB_HOST',
        'DB_USER',
        'DB_PASS',
        'DB_NAME',
        'IA_HASH_SALT',
        'OPENAI_KEY_ENCRYPTION_SECRET',
    ];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            fail_private_config();
        }
    }

    return $config;
}

const LEGACY_CRON_SYNC_TOKEN = 'FuerzaTacnaCron2024';

function private_config_has_cron_token(array $config): bool {
    return isset($config['CRON_SYNC_TOKEN'])
        && is_string($config['CRON_SYNC_TOKEN'])
        && trim($config['CRON_SYNC_TOKEN']) !== '';
}

function write_private_config_array(string $path, array $config): bool {
    $dir = dirname($path);
    $tmp = tempnam($dir, 'config.local.');
    if ($tmp === false) {
        return false;
    }

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    $written = file_put_contents($tmp, $content, LOCK_EX);
    if ($written === false) {
        @unlink($tmp);
        return false;
    }

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    @chmod($path, 0600);
    return true;
}

function migrate_cron_token_to_private_config(string $path, array &$config): bool {
    if (private_config_has_cron_token($config)) {
        return true;
    }

    $updatedConfig = $config;
    $updatedConfig['CRON_SYNC_TOKEN'] = LEGACY_CRON_SYNC_TOKEN;

    if (!write_private_config_array($path, $updatedConfig)) {
        error_log('Cron token private config migration failed');
        return false;
    }

    $config = $updatedConfig;
    return true;
}

$configLocalPath = __DIR__ . '/data/config.local.php';
$appConfig = load_external_config($configLocalPath);
$cronPrivateTokenActive = migrate_cron_token_to_private_config($configLocalPath, $appConfig);

define('PRIVATE_CONFIG_ACTIVE', true);
define('DB_HOST', $appConfig['DB_HOST']);
define('DB_USER', $appConfig['DB_USER']);
define('DB_PASS', $appConfig['DB_PASS']);
define('DB_NAME', $appConfig['DB_NAME']);

// =======================
//  SEGURIDAD IA (SALT PARA IPs)
// =======================
define('IA_HASH_SALT', $appConfig['IA_HASH_SALT']);
define('OPENAI_API_KEY', $appConfig['OPENAI_API_KEY'] ?? ''); // Dejar vacío en Git, poner la clave directa en cPanel
define('OPENAI_KEY_ENCRYPTION_SECRET', $appConfig['OPENAI_KEY_ENCRYPTION_SECRET']);
define('CRON_PRIVATE_TOKEN_ACTIVE', $cronPrivateTokenActive);
define('CRON_SYNC_TOKEN', $cronPrivateTokenActive ? $appConfig['CRON_SYNC_TOKEN'] : LEGACY_CRON_SYNC_TOKEN);
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
        error_log('Database connection unavailable: ' . get_class($e));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Error interno del servidor.';
        exit;
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

function external_http_normalize_host(string $host): string {
    $host = trim($host);

    if (strlen($host) >= 2 && $host[0] === '[' && substr($host, -1) === ']') {
        $host = substr($host, 1, -1);
    }

    return rtrim(strtolower($host), '.');
}

function external_http_is_public_ip(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }

    if ($ip === '0.0.0.0' || $ip === '::') {
        return false;
    }

    return true;
}

function validate_external_http_url(string $url): array {
    $url = trim($url);

    if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f]/', $url)) {
        throw new InvalidArgumentException('URL no valida');
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        throw new InvalidArgumentException('URL no valida');
    }

    $scheme = strtolower((string)$parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('URL no valida');
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('URL no valida');
    }

    $host = external_http_normalize_host((string)$parts['host']);
    if ($host === '' || preg_match('/[\x00-\x20\x7f]/', $host)) {
        throw new InvalidArgumentException('URL no valida');
    }

    if (filter_var($host, FILTER_VALIDATE_IP) === false && preg_match('/^(?:0x[0-9a-f]+|[0-9]+|[0-9.]+)$/i', $host)) {
        throw new InvalidArgumentException('URL no valida');
    }

    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
    $port = (int)$port;
    if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
        throw new InvalidArgumentException('URL no valida');
    }

    return [
        'url' => $url,
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
    ];
}

function resolve_external_http_host(string $host): array {
    $host = external_http_normalize_host($host);

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!external_http_is_public_ip($host)) {
            throw new InvalidArgumentException('URL no valida');
        }

        return [$host];
    }

    $ips = [];
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (is_array($records)) {
        foreach ($records as $record) {
            if (($record['type'] ?? '') === 'A' && !empty($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (($record['type'] ?? '') === 'AAAA' && !empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
    }

    $ips = array_values(array_unique($ips));
    if (empty($ips)) {
        throw new InvalidArgumentException('URL no valida');
    }

    foreach ($ips as $ip) {
        if (!external_http_is_public_ip($ip)) {
            throw new InvalidArgumentException('URL no valida');
        }
    }

    return $ips;
}

function external_http_build_authority(array $parts): string {
    $host = $parts['host'] ?? '';
    if ($host === '') {
        throw new InvalidArgumentException('URL no valida');
    }

    $authority = $host;
    if (isset($parts['port'])) {
        $authority .= ':' . (int)$parts['port'];
    }

    return $authority;
}

function external_http_normalize_path(string $path): string {
    $segments = explode('/', $path);
    $safe = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($safe);
            continue;
        }

        $safe[] = $segment;
    }

    return '/' . implode('/', $safe);
}

function external_http_resolve_location(string $baseUrl, string $location): string {
    $location = trim($location);
    if ($location === '' || preg_match('/[\x00-\x1f\x7f]/', $location)) {
        throw new InvalidArgumentException('URL no valida');
    }

    $locationParts = parse_url($location);
    if ($locationParts !== false && isset($locationParts['scheme'])) {
        return $location;
    }

    $base = parse_url($baseUrl);
    if ($base === false || empty($base['scheme']) || empty($base['host'])) {
        throw new InvalidArgumentException('URL no valida');
    }

    if (substr($location, 0, 2) === '//') {
        return strtolower((string)$base['scheme']) . ':' . $location;
    }

    $authority = external_http_build_authority($base);
    $query = '';
    $fragment = '';

    if ($locationParts !== false) {
        if (isset($locationParts['query'])) {
            $query = '?' . $locationParts['query'];
        }

        if (isset($locationParts['fragment'])) {
            $fragment = '#' . $locationParts['fragment'];
        }
    }

    if (isset($location[0]) && $location[0] === '/') {
        $path = external_http_normalize_path($locationParts['path'] ?? '/');
    } else {
        $basePath = $base['path'] ?? '/';
        $baseDir = preg_replace('~/[^/]*$~', '/', $basePath);
        $path = external_http_normalize_path($baseDir . ($locationParts['path'] ?? $location));
    }

    return strtolower((string)$base['scheme']) . '://' . $authority . $path . $query . $fragment;
}

function external_http_is_text_content_type(?string $contentType): bool {
    if ($contentType === null || trim($contentType) === '') {
        return true;
    }

    $mime = strtolower(trim(explode(';', $contentType, 2)[0]));

    if (strpos($mime, 'text/') === 0) {
        return true;
    }

    return $mime === 'application/xhtml+xml';
}

function fetch_external_http_text(string $url, int $maxBytes = 1048576, int $maxRedirects = 3): array {
    $currentUrl = $url;

    for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
        $urlInfo = validate_external_http_url($currentUrl);
        $ips = resolve_external_http_host($urlInfo['host']);
        $selectedIp = $ips[0];
        $isLiteralIpHost = filter_var($urlInfo['host'], FILTER_VALIDATE_IP) !== false;
        $curlIp = strpos($selectedIp, ':') !== false ? '[' . $selectedIp . ']' : $selectedIp;

        $body = '';
        $location = null;
        $contentType = null;
        $tooLarge = false;
        $invalidMime = false;

        $ch = curl_init($urlInfo['url']);
        if ($ch === false) {
            throw new RuntimeException('No se pudo obtener el contenido');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (!$isLiteralIpHost) {
            curl_setopt($ch, CURLOPT_RESOLVE, [$urlInfo['host'] . ':' . $urlInfo['port'] . ':' . $curlIp]);
        }

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $header) use (&$location, &$contentType, &$tooLarge, &$invalidMime, $maxBytes): int {
            $line = trim($header);
            if ($line === '' || strpos($line, ':') === false) {
                return strlen($header);
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = strtolower($name);

            if ($name === 'location') {
                $location = $value;
            } elseif ($name === 'content-type') {
                $contentType = $value;
                if (!external_http_is_text_content_type($contentType)) {
                    $invalidMime = true;
                    return 0;
                }
            } elseif ($name === 'content-length' && ctype_digit($value) && (int)$value > $maxBytes) {
                $tooLarge = true;
                return 0;
            }

            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                $tooLarge = true;
                return 0;
            }

            $body .= $chunk;
            return strlen($chunk);
        });

        $ok = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tooLarge) {
            throw new LengthException('La pagina es demasiado grande');
        }

        if ($invalidMime) {
            throw new UnexpectedValueException('Contenido no compatible');
        }

        if ($ok === false) {
            throw new RuntimeException('No se pudo obtener el contenido');
        }

        if (in_array($httpCode, [301, 302, 303, 307, 308], true)) {
            if ($location === null || $redirects === $maxRedirects) {
                throw new RuntimeException('No se pudo obtener el contenido');
            }

            $currentUrl = external_http_resolve_location($urlInfo['url'], $location);
            continue;
        }

        if ($httpCode < 200 || $httpCode > 299) {
            throw new RuntimeException('No se pudo obtener el contenido');
        }

        if (!external_http_is_text_content_type($contentType)) {
            throw new UnexpectedValueException('Contenido no compatible');
        }

        return [
            'body' => $body,
            'final_url' => $urlInfo['url'],
            'content_type' => $contentType,
            'http_code' => $httpCode,
        ];
    }

    throw new RuntimeException('No se pudo obtener el contenido');
}

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
