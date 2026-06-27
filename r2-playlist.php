<?php
declare(strict_types=1);

$playlistUrl = 'https://pub-0c89cc9473aa46ba893c0661417f95e2.r2.dev/playlist.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "Accept: application/json\r\n",
    ],
]);

$json = @file_get_contents($playlistUrl, false, $context);

if ($json === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo leer playlist.json desde R2.']);
    exit;
}

$json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
$playlist = json_decode($json, true);

if (!is_array($playlist)) {
    http_response_code(502);
    echo json_encode(['error' => 'playlist.json de R2 no es un JSON valido.']);
    exit;
}

echo json_encode($playlist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
