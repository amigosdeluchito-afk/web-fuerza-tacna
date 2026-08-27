<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();

function diagnostico_ip_header_status(string $key): string {
    return isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '' ? 'PRESENTE' : 'AUSENTE';
}

function diagnostico_ip_mask_remote(string $ip): string {
    $ip = trim($ip);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $prefix = implode(':', array_slice($parts, 0, 2));
        return $prefix . ':xxxx:xxxx:xxxx';
    }

    return 'formato no reconocido';
}

function diagnostico_ip_compare_to_remote(string $key): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $header = trim((string)($_SERVER[$key] ?? ''));

    if ($remote === '' || $header === '') {
        return 'NO COMPARABLE';
    }

    if ($key === 'HTTP_X_FORWARDED_FOR') {
        $parts = array_map('trim', explode(',', $header));
        if (count($parts) !== 1) {
            return 'NO COMPARABLE';
        }
        $header = $parts[0] ?? '';
    }

    return hash_equals($remote, $header) ? 'IGUALES' : 'DIFERENTES';
}

function diagnostico_ip_https_status(): string {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return 'SI';
    }

    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return 'SI';
    }

    return 'NO';
}

function diagnostico_ip_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$remoteMasked = $remoteAddr !== '' ? diagnostico_ip_mask_remote($remoteAddr) : 'AUSENTE';
$host = (string)($_SERVER['HTTP_HOST'] ?? 'AUSENTE');
$file = basename(__FILE__);
$cfComparison = diagnostico_ip_compare_to_remote('HTTP_CF_CONNECTING_IP');
$xffComparison = diagnostico_ip_compare_to_remote('HTTP_X_FORWARDED_FOR');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnostico IP</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #020617;
            color: #e5e7eb;
            margin: 0;
            padding: 24px;
        }
        .box {
            max-width: 760px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
        }
        h1, h2 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        th, td {
            border-bottom: 1px solid #334155;
            padding: 10px;
            text-align: left;
        }
        th {
            color: #93c5fd;
            font-size: 13px;
            text-transform: uppercase;
        }
        .note {
            color: #cbd5e1;
            line-height: 1.55;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Diagnostico de IP</h1>

        <h2>Contexto</h2>
        <table>
            <tr>
                <th>Dato</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>Host actual</td>
                <td><?= diagnostico_ip_escape($host) ?></td>
            </tr>
            <tr>
                <td>Ruta ejecutada</td>
                <td><?= diagnostico_ip_escape($file) ?></td>
            </tr>
            <tr>
                <td>HTTPS</td>
                <td><?= diagnostico_ip_escape(diagnostico_ip_https_status()) ?></td>
            </tr>
        </table>

        <h2>Headers de IP</h2>
        <table>
            <tr>
                <th>Dato</th>
                <th>Estado</th>
            </tr>
            <tr>
                <td>REMOTE_ADDR</td>
                <td><?= diagnostico_ip_escape(diagnostico_ip_header_status('REMOTE_ADDR')) ?> (<?= diagnostico_ip_escape($remoteMasked) ?>)</td>
            </tr>
            <tr>
                <td>CF-Connecting-IP</td>
                <td><?= diagnostico_ip_escape(diagnostico_ip_header_status('HTTP_CF_CONNECTING_IP')) ?></td>
            </tr>
            <tr>
                <td>X-Forwarded-For</td>
                <td><?= diagnostico_ip_escape(diagnostico_ip_header_status('HTTP_X_FORWARDED_FOR')) ?></td>
            </tr>
            <tr>
                <td>X-Real-IP</td>
                <td><?= diagnostico_ip_escape(diagnostico_ip_header_status('HTTP_X_REAL_IP')) ?></td>
            </tr>
        </table>

        <h2>Comparaciones</h2>
        <table>
            <tr>
                <th>Comparacion</th>
                <th>Resultado</th>
            </tr>
            <tr>
                <td>REMOTE_ADDR vs CF-Connecting-IP</td>
                <td><?= diagnostico_ip_escape($cfComparison) ?></td>
            </tr>
            <tr>
                <td>REMOTE_ADDR vs X-Forwarded-For simple</td>
                <td><?= diagnostico_ip_escape($xffComparison) ?></td>
            </tr>
        </table>

        <h2>Interpretacion</h2>
        <p class="note">CASO A: REMOTE_ADDR representa al cliente y headers proxy estan ausentes: REMOTE_ADDR puede utilizarse directamente.</p>
        <p class="note">CASO B: REMOTE_ADDR y CF-Connecting-IP son diferentes: probablemente existe proxy/CDN y hace falta resolver proxies confiables antes de usar IP cliente.</p>
        <p class="note">CASO C: REMOTE_ADDR es una IP compartida/proxy: no debemos usarla directamente para login_ip.</p>
        <p class="note">No se afirma que una direccion pertenece a Cloudflare unicamente por verla.</p>
    </div>
</body>
</html>
