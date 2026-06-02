<?php
// assets/ia_luchito/router.php
header('Content-Type: application/json; charset=utf-8');

// 1. Capturar el paquete JSON enviado desde Javascript
$input = json_decode(file_get_contents('php://input'), true);
$mensaje = trim($input['mensaje'] ?? '');

// 2. Validar que no esté vacío
if (empty($mensaje)) {
    echo json_encode(['ok' => false, 'error' => 'El mensaje está vacío.']);
    exit;
}

// 3. Escudo de Longitud
if (mb_strlen($mensaje) > 150) {
    echo json_encode([
        'ok' => true,
        'texto' => '¡Epa vecino! Me escribiste un testamento. Mándame mensajes un poquito más cortos para leerte más rápido, que con mis lentes me demoro. 👓',
        'acciones' => [],
        'origen' => 'simulador_limite'
    ]);
    exit;
}

// 4. Simular tiempo de "razonamiento" (1.5 segundos)
usleep(1500000);

// 5. Respuesta Fija (Modo Simulador)
echo json_encode([
    'ok' => true,
    'texto' => '¡Esta es una respuesta desde el cerebro (Servidor PHP)! El puente de comunicación funciona a la perfección, vecino. 🚀',
    'acciones' => [['label' => '🗺️ Ver Mapa', 'type' => 'ir_a_obras']],
    'origen' => 'simulador'
]);