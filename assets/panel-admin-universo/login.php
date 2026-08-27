<?php
// Registrar todos los errores sin mostrarlos publicamente.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

require_once 'config.php';

const LOGIN_RATE_LIMIT_WINDOW_SECONDS = 600;
const LOGIN_RATE_LIMIT_BLOCK_SECONDS = 600;
const LOGIN_RATE_LIMIT_COMBO_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_IP_ATTEMPTS = 20;
const LOGIN_RATE_LIMIT_MESSAGE = 'Demasiados intentos. Intenta nuevamente en unos minutos.';

function login_rate_limit_key(array $parts): string {
    $secret = defined('IA_HASH_SALT') ? IA_HASH_SALT : '';
    return hash_hmac('sha256', implode('|', $parts), $secret);
}

function login_rate_limit_prepare(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS panel_login_rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(50) NOT NULL,
        key_hash CHAR(64) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        window_start DATETIME NOT NULL,
        blocked_until DATETIME NULL,
        last_attempt DATETIME NOT NULL,
        UNIQUE KEY uniq_scope_key (scope, key_hash),
        KEY idx_last_attempt (last_attempt),
        KEY idx_blocked_until (blocked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (random_int(1, 100) === 1) {
        $db->exec("DELETE FROM panel_login_rate_limits
            WHERE last_attempt < (NOW() - INTERVAL 7 DAY)
            AND (blocked_until IS NULL OR blocked_until < NOW())");
    }
}

function login_rate_limit_is_blocked(PDO $db, string $scope, string $keyHash): array {
    $stmt = $db->prepare("SELECT blocked_until FROM panel_login_rate_limits WHERE scope = ? AND key_hash = ? LIMIT 1");
    $stmt->execute([$scope, $keyHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['blocked_until'])) {
        return ['blocked' => false, 'retry_after' => 0];
    }

    $blockedUntil = strtotime($row['blocked_until']);
    if ($blockedUntil === false || $blockedUntil <= time()) {
        $stmt = $db->prepare("UPDATE panel_login_rate_limits
            SET attempts = 0, window_start = NOW(), blocked_until = NULL, last_attempt = NOW()
            WHERE scope = ? AND key_hash = ?");
        $stmt->execute([$scope, $keyHash]);
        return ['blocked' => false, 'retry_after' => 0];
    }

    return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - time())];
}

function login_rate_limit_register_failure(PDO $db, string $scope, string $keyHash, int $limit): void {
    $db->beginTransaction();

    try {
        $stmt = $db->prepare("SELECT attempts, window_start, blocked_until
            FROM panel_login_rate_limits
            WHERE scope = ? AND key_hash = ?
            LIMIT 1 FOR UPDATE");
        $stmt->execute([$scope, $keyHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $stmt = $db->prepare("INSERT INTO panel_login_rate_limits
                (scope, key_hash, attempts, window_start, blocked_until, last_attempt)
                VALUES (?, ?, 1, NOW(), NULL, NOW())");
            $stmt->execute([$scope, $keyHash]);
            $db->commit();
            return;
        }

        $windowStart = strtotime($row['window_start']);
        $blockedUntil = !empty($row['blocked_until']) ? strtotime($row['blocked_until']) : false;
        $windowExpired = ($windowStart === false || $windowStart <= (time() - LOGIN_RATE_LIMIT_WINDOW_SECONDS));
        $blockExpired = ($blockedUntil !== false && $blockedUntil <= time());
        $attempts = ($windowExpired || $blockExpired) ? 1 : ((int)$row['attempts'] + 1);
        $blockedUntilSql = ($attempts >= $limit) ? "DATE_ADD(NOW(), INTERVAL " . LOGIN_RATE_LIMIT_BLOCK_SECONDS . " SECOND)" : "NULL";
        $windowStartSql = ($windowExpired || $blockExpired) ? "NOW()" : "window_start";

        $stmt = $db->prepare("UPDATE panel_login_rate_limits
            SET attempts = ?,
                window_start = $windowStartSql,
                blocked_until = $blockedUntilSql,
                last_attempt = NOW()
            WHERE scope = ? AND key_hash = ?");
        $stmt->execute([$attempts, $scope, $keyHash]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function login_rate_limit_reset(PDO $db, string $scope, string $keyHash): void {
    $stmt = $db->prepare("DELETE FROM panel_login_rate_limits WHERE scope = ? AND key_hash = ?");
    $stmt->execute([$scope, $keyHash]);
}

// Si ya está logueado, lo mandamos al panel
if (!empty($_SESSION['user'])) {
    header('Location: editar_obra.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $normalizedUser = strtolower($user);
    $ipKey = login_rate_limit_key(['login_ip', $clientIp]);
    $comboKey = login_rate_limit_key(['login_combo', $normalizedUser, $clientIp]);
    $rateLimitDb = null;
    $rateLimitReady = false;
    $rateLimited = false;
    $retryAfter = 0;

    try {
        $rateLimitDb = get_db_connection();
        login_rate_limit_prepare($rateLimitDb);
        $rateLimitReady = true;

        foreach ([['login_combo', $comboKey], ['login_ip', $ipKey]] as $limitKey) {
            $blocked = login_rate_limit_is_blocked($rateLimitDb, $limitKey[0], $limitKey[1]);
            if ($blocked['blocked']) {
                $rateLimited = true;
                $retryAfter = max($retryAfter, (int)$blocked['retry_after']);
            }
        }
    } catch (Throwable $e) {
        error_log('Login rate limit unavailable: ' . get_class($e));
        $rateLimitReady = false;
        $rateLimited = false;
    }

    if ($rateLimited) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $retryAfter));
        $error = LOGIN_RATE_LIMIT_MESSAGE;
    } elseif (check_login($user, $pass)) {
        if ($rateLimitReady && $rateLimitDb instanceof PDO) {
            try {
                login_rate_limit_reset($rateLimitDb, 'login_combo', $comboKey);
            } catch (Throwable $e) {
                error_log('Login rate limit unavailable: ' . get_class($e));
            }
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        header('Location: editar_obra.php');
        exit;
    } else {
        if ($rateLimitReady && $rateLimitDb instanceof PDO) {
            try {
                login_rate_limit_register_failure($rateLimitDb, 'login_combo', $comboKey, LOGIN_RATE_LIMIT_COMBO_ATTEMPTS);
                login_rate_limit_register_failure($rateLimitDb, 'login_ip', $ipKey, LOGIN_RATE_LIMIT_IP_ATTEMPTS);
            } catch (Throwable $e) {
                error_log('Login rate limit unavailable: ' . get_class($e));
            }
        }

        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login – Panel de fotos</title>
  <style>
    body {
      background:#050816;
      color:#fff;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display:flex;
      justify-content:center;
      align-items:center;
      height:100vh;
      margin:0;
    }
    .card {
      background:#0b1020;
      padding:24px 28px;
      border-radius:16px;
      width:320px;
      box-shadow:0 0 25px rgba(0,0,0,0.6);
      border:1px solid rgba(148,163,184,0.25);
    }
    h1 {
      font-size:20px;
      margin:0 0 16px;
    }
    label {
      display:block;
      font-size:14px;
      margin-top:10px;
    }
    input {
      width:100%;
      padding:8px 10px;
      border-radius:8px;
      border:1px solid #2b3960;
      background:#050816;
      color:#fff;
      margin-top:4px;
      box-sizing:border-box;
    }
    button {
      margin-top:16px;
      width:100%;
      padding:10px;
      border-radius:999px;
      border:none;
      background:#2563eb;
      color:#fff;
      font-weight:600;
      cursor:pointer;
    }
    button:hover { background:#1d4ed8; }
    .error {
      margin-top:10px;
      color:#f87171;
      font-size:13px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Iniciar sesión</h1>
    <form method="post">
      <?= csrf_field() ?>
      <label>Usuario
        <input type="text" name="username" autocomplete="username">
      </label>
      <label>Contraseña
        <input type="password" name="password" autocomplete="current-password">
      </label>
      <button type="submit">Entrar al panel</button>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
