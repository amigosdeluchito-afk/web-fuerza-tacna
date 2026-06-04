<?php
// assets/ia_luchito/openai_client.php

function llamar_openai_responses($modelo, $instructions, $input, $temp, $max_tokens, $api_key_db = '') {
    // 1. Verificación de seguridad de la API Key
    $key = $api_key_db !== '' ? $api_key_db : (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
    
    if (trim($key) === '') {
        return [
            'ok' => false, 'texto' => '', 'tokens_input' => 0, 'tokens_output' => 0,
            'error' => 'API Key no configurada o vacía.'
        ];
    }

    // 2. Preparar el payload con la estructura de Chat Completions
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => $modelo,
        'messages' => [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input]
        ],
        'temperature' => (float)$temp,
        'max_tokens' => (int)$max_tokens
    ];

    // 3. Ejecutar cURL protegido
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($key)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Aumentado a 15s para dar tiempo al prompt maestro

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // 4. Manejo de Errores de Conexión
    if ($response === false) {
        return ['ok' => false, 'texto' => '', 'tokens_input' => 0, 'tokens_output' => 0, 'error' => 'Timeout o Error cURL: ' . $curl_error];
    }

    $decoded = json_decode($response, true);
    if (!$decoded) {
        return ['ok' => false, 'texto' => '', 'tokens_input' => 0, 'tokens_output' => 0, 'error' => "Respuesta JSON inválida. HTTP $http_code"];
    }

    if ($http_code !== 200) {
        $apiError = $decoded['error']['message'] ?? 'Error desconocido';
        return ['ok' => false, 'texto' => '', 'tokens_input' => 0, 'tokens_output' => 0, 'error' => "HTTP $http_code - $apiError"];
    }

    // 5. Extracción de la respuesta del modelo
    $texto_salida = $decoded['choices'][0]['message']['content'] ?? ''; 
    $t_input = $decoded['usage']['prompt_tokens'] ?? 0;
    $t_output = $decoded['usage']['completion_tokens'] ?? 0;

    return [
        'ok' => true,
        'texto' => trim($texto_salida),
        'tokens_input' => (int)$t_input,
        'tokens_output' => (int)$t_output,
        'error' => ''
    ];
}