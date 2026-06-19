<?php
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'get';
$dataDir = realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
$configFile = $dataDir . DIRECTORY_SEPARATOR . 'red_vial_public_config.json';

$defaultConfig = [
    'defaultProfile' => 'ciudadano',
    'layers' => [
        'roads' => true,
        'buildings' => true,
        'buildings3d' => false,
        'water' => true,
        'parks' => true,
        'boundaries' => false,
        'transit' => false,
        'places-text' => true,
        'ref-urbanas' => true,
        'srv-edu' => true,
        'srv-salud' => true,
        'srv-seguridad' => true,
        'srv-gobierno' => true,
        'srv-mercados' => true,
        'srv-deporte' => true,
        'srv-transporte' => true,
        'srv-negocios' => true
    ],
    'style' => [
        'roadHighway' => '#89A5BE',
        'roadHighwayCase' => '#7893AA',
        'roadMain' => '#94AEC4',
        'roadMainCase' => '#819BB1',
        'roadSecondary' => '#C7D6E1',
        'roadSecondaryCase' => '#B5C7D5',
        'roadMinor' => '#C6CED3',
        'roadMinorCase' => '#D8E0E5',
        'roadMinorWidthBoost' => 1
    ]
];

function rv_public_config_merge($base, $incoming) {
    $profiles = ['ciudadano', 'tecnico', 'impacto'];
    if (isset($incoming['defaultProfile']) && in_array($incoming['defaultProfile'], $profiles, true)) {
        $base['defaultProfile'] = $incoming['defaultProfile'];
    }

    if (isset($incoming['layers']) && is_array($incoming['layers'])) {
        foreach ($base['layers'] as $key => $value) {
            if (array_key_exists($key, $incoming['layers'])) {
                $base['layers'][$key] = filter_var($incoming['layers'][$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
    }

    if (($base['layers']['buildings'] ?? false) && ($base['layers']['buildings3d'] ?? false)) {
        $base['layers']['buildings3d'] = false;
    }

    if (isset($incoming['style']) && is_array($incoming['style'])) {
        foreach ($base['style'] as $key => $value) {
            if (!array_key_exists($key, $incoming['style'])) continue;

            if ($key === 'roadMinorWidthBoost') {
                $boost = (float)$incoming['style'][$key];
                $base['style'][$key] = max(0, min(2, $boost));
                continue;
            }

            $color = strtoupper(trim((string)$incoming['style'][$key]));
            if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
                $base['style'][$key] = $color;
            }
        }
    }

    return $base;
}

function rv_public_config_read($file, $defaultConfig) {
    if (!is_file($file)) return $defaultConfig;
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) return $defaultConfig;
    return rv_public_config_merge($defaultConfig, $data);
}

if ($action === 'get') {
    echo json_encode(['ok' => true, 'config' => rv_public_config_read($configFile, $defaultConfig)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'save') {
    require_once __DIR__ . '/config.php';
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payload invalido']);
        exit;
    }

    $config = rv_public_config_merge($defaultConfig, $input);
    $saved = file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL, LOCK_EX);
    if ($saved === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la configuracion']);
        exit;
    }

    log_action('rv_config_publica', 'Actualizo la vista publica del mapa Red Vial', $config);
    echo json_encode(['ok' => true, 'config' => $config], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Accion invalida']);
