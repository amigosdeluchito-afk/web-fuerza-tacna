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

// 5. Enrutador básico por palabras clave (Simulador Fase 2)
$mensaje_lower = mb_strtolower($mensaje, 'UTF-8');

$texto = '¡Esa es una gran pregunta, vecino! Pero mi cerebro (Servidor PHP) todavía está en entrenamiento antes de conectarse a la IA. 🧠';
$acciones = [];

if (preg_match('/(obra|obras|proyecto|mapa)/', $mensaje_lower)) {
    $texto = '¡Claro que sí! Tenemos obras por toda Tacna. Te llevo directito al mapa para que las cheques tú mismo. 🗺️';
    $acciones = [['label' => '🗺️ Abrir Mapa', 'type' => 'ir_a_obras']];
} elseif (preg_match('/(candidato|candidatos|equipo|regidor|alcalde)/', $mensaje_lower)) {
    $texto = '¡Tenemos un equipazo, campeón! Pura gente chamba. Te acompaño a la sección para que los conozcas a todos. 👥';
    $acciones = [['label' => '👥 Ver Equipo', 'type' => 'ir_a_candidatos']];
} elseif (preg_match('/(sumate|unirme|apoyar|voluntario)/', $mensaje_lower)) {
    $texto = '¡Esa es la actitud! Siempre hay sitio en la familia para los que quieren ver crecer a Tacna. ¿Te apuntas? 💪';
    $acciones = [['label' => '💪 Súmate a la Fuerza', 'type' => 'ir_a_sumate']];
} elseif (preg_match('/(contacto|llamar|telefono|ubicacion)/', $mensaje_lower)) {
    $texto = '¡Al toque! Si necesitas escribirnos o visitarnos, te paso toda la info para que estemos en contacto. 📞';
    $acciones = [['label' => '📞 Contacto', 'type' => 'ir_a_contacto']];
}

// 6. Devolver el JSON final
echo json_encode([
    'ok' => true,
    'texto' => $texto,
    'acciones' => $acciones,
    'origen' => 'simulador_fase2'
]);