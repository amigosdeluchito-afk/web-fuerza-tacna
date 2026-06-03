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

// 5. Normalizar texto (Quitar tildes y mayúsculas para buscar coincidencias)
$normalizada = mb_strtolower($mensaje, 'UTF-8');
$unwanted_array = array('á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n');
$normalizada = strtr($normalizada, $unwanted_array);

// 6. Diccionario Temático de Clasificación
$temas_validos = [
    'Obras' => [
        'regex' => '/(obra|proyecto|mapa|construccion|colegio|posta|infraestructura)/i',
        'simulado' => '😄 A ver vecino, esa consulta va por el lado de obras. Pronto podré buscar ese dato con más detalle. ¿Te llevo al mapa?',
        'acciones' => [['label' => '🗺️ Ver Obras', 'type' => 'ir_a_obras']]
    ],
    'Candidatos' => [
        'regex' => '/(candidato|equipo|regidor|alcalde|patrick|alena|fletch|marc|natalia)/i',
        'simulado' => '😄 Esa pregunta es sobre nuestro equipazo. Pronto tendré los apuntes completos de todos. ¿Te muestro a los candidatos por ahora?',
        'acciones' => [['label' => '👥 Ver Candidatos', 'type' => 'ir_a_candidatos']]
    ],
    'Propuestas' => [
        'regex' => '/(propuesta|plan|seguridad|educacion|salud|emprendimiento|promesa)/i',
        'simulado' => '😄 Me hablas de nuestras propuestas, vecino. Estoy organizando esos documentos. ¿Le damos una mirada a la sección mientras tanto?',
        'acciones' => [['label' => '🚀 Ver Propuestas', 'type' => 'ir_a_propuestas']]
    ],
    'Súmate' => [
        'regex' => '/(sumate|unirme|apoyar|voluntario|afiliarse)/i',
        'simulado' => '😄 ¡Qué bacán que quieras sumarte! Te dejo el acceso directo para que te inscribas.',
        'acciones' => [['label' => '💪 Súmate a la Fuerza', 'type' => 'ir_a_sumate']]
    ],
    'Contacto' => [
        'regex' => '/(contacto|llamar|telefono|ubicacion|redes|facebook|whatsapp|local)/i',
        'simulado' => '😄 ¿Buscas cómo contactarnos? Te paso el acceso para que hablemos directo.',
        'acciones' => [['label' => '📞 Contacto', 'type' => 'ir_a_contacto']]
    ],
    'Cronología' => [
        'regex' => '/(cronologia|historia|pasado|años)/i',
        'simulado' => '😄 Quieres saber de nuestra historia. Acompáñame a ver cómo empezamos.',
        'acciones' => [['label' => '⏳ Conocer Historia', 'type' => 'ir_a_candidatos']]
    ],
    'Actividades' => [
        'regex' => '/(actividad|mitin|evento|campaña|recorrido)/i',
        'simulado' => '😄 Sobre nuestros eventos, siempre publicamos todo en redes. ¿Te llevo a la sección de contacto?',
        'acciones' => [['label' => '📞 Contacto', 'type' => 'ir_a_contacto']]
    ],
    'Navegación' => [
        'regex' => '/(pagina|web|sitio|explorar|menu)/i',
        'simulado' => '😄 ¡Esta página tiene de todo! ¿Quieres que te muestre los proyectos o prefieres conocer al equipo?',
        'acciones' => [
            ['label' => '🗺️ Ver Obras', 'type' => 'ir_a_obras'],
            ['label' => '👥 Candidatos', 'type' => 'ir_a_candidatos']
        ]
    ]
];

// Valores por defecto (Fuera de Tema)
$texto_final = '😄 Me agarraste fuera de juego, vecino. Yo ando más pendiente de esta página. ¿Te muestro obras o candidatos?';
$acciones_final = [
    ['label' => '🗺️ Ver Obras', 'type' => 'ir_a_obras'],
    ['label' => '👥 Candidatos', 'type' => 'ir_a_candidatos']
];
$origen_final = 'router_fuera_tema';

// 7. Motor de Clasificación
foreach ($temas_validos as $categoria => $datos) {
    if (preg_match($datos['regex'], $normalizada)) {
        $texto_final = $datos['simulado'];
        $acciones_final = $datos['acciones'];
        $origen_final = 'router_simulado_' . strtolower(str_replace(' ', '_', $categoria));
        break; // Detener búsqueda tras encontrar el primer tema
    }
}

// 8. Devolver el JSON final
echo json_encode([
    'ok' => true,
    'texto' => $texto_final,
    'acciones' => $acciones_final,
    'origen' => $origen_final
]);