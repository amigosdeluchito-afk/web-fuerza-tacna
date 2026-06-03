<?php
// assets/ia_luchito/router.php
header('Content-Type: application/json; charset=utf-8');

// Cargar conexión a la base de datos para registrar preguntas huérfanas
require_once __DIR__ . '/../panel-admin-universo/config.php';
$db = get_db_connection();

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
        'origen' => 'escudo_longitud',
        'categoria_detectada' => 'Bloqueo',
        'permitir_ia' => false,
        'motivo_bloqueo' => 'MENSAJE_MUY_LARGO'
    ]);
    exit;
}

// --- ESCUDOS CRÍTICOS DE PRIVACIDAD Y SEGURIDAD ---

// DNI o Teléfonos
if (preg_match('/[0-9]{8,}/', $mensaje)) {
    echo json_encode([
        'ok' => true,
        'texto' => 'Por tu privacidad vecino, prefiero no recibir números de teléfono ni documentos personales. ¿Te ayudo con alguna obra?',
        'acciones' => [],
        'origen' => 'escudo_seguridad',
        'categoria_detectada' => 'Seguridad',
        'permitir_ia' => false,
        'motivo_bloqueo' => 'DATOS_SENSIBLES'
    ]);
    exit;
}

// Correos Electrónicos
if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $mensaje)) {
    echo json_encode([
        'ok' => true,
        'texto' => 'Vecino, no es necesario que me dejes tu correo por aquí. Si quieres unirte al equipo, ve a la sección de Contacto.',
        'acciones' => [],
        'origen' => 'escudo_seguridad',
        'categoria_detectada' => 'Seguridad',
        'permitir_ia' => false,
        'motivo_bloqueo' => 'CORREO_DETECTADO'
    ]);
    exit;
}

// URLs o Enlaces
if (preg_match('/(https?:\/\/|www\.|\.(com|pe|net|org|info|gob)\b)/i', $mensaje)) {
    echo json_encode([
        'ok' => true,
        'texto' => 'Con estos lentes no puedo abrir enlaces de internet, vecino. Mejor cuéntame qué buscabas.',
        'acciones' => [],
        'origen' => 'escudo_seguridad',
        'categoria_detectada' => 'Seguridad',
        'permitir_ia' => false,
        'motivo_bloqueo' => 'URL_DETECTADA'
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
$categoria_detectada = 'Fuera de Tema';
$texto_final = '😄 Me agarraste fuera de juego, vecino. Yo ando más pendiente de esta página. ¿Te muestro obras o candidatos?';
$acciones_final = [
    ['label' => '🗺️ Ver Obras', 'type' => 'ir_a_obras'],
    ['label' => '👥 Candidatos', 'type' => 'ir_a_candidatos']
];
$origen_final = 'router_fuera_tema';

// 7. Motor de Clasificación
foreach ($temas_validos as $categoria => $datos) {
    if (preg_match($datos['regex'], $normalizada)) {
        $categoria_detectada = $categoria;
        $texto_final = $datos['simulado'];
        $acciones_final = $datos['acciones'];
        $origen_final = 'router_simulado_' . strtolower(str_replace(' ', '_', $categoria));
        break; // Detener búsqueda tras encontrar el primer tema
    }
}

// --- ESCUDOS DE CONTEXTO Y ABUSO (ETAPA 5A - PASO 2) ---
$permitir_ia = false;
$motivo_bloqueo = '';

if (preg_match('/(ignora|olvida|actua como|comportate como|eres un prompt|instrucciones|olvida todo|muestrame tu prompt)/i', $normalizada)) {
    $categoria_detectada = 'Auditoría - Inyección';
    $texto_final = 'Soy Luchito, tu tío digital, y mi única chamba es hablar de Tacna, sus obras y su gente.';
    $acciones_final = [];
    $origen_final = 'escudo_contexto';
    $motivo_bloqueo = 'PROMPT_INJECTION';
} elseif (preg_match('/(dina|boluarte|castillo|fujimori|congreso|presidente|lima|politica nacional)/i', $normalizada)) {
    $categoria_detectada = 'Política Nacional';
    $texto_final = 'De política nacional no tengo apuntes, vecino. Solo ando pendiente de lo que pasa aquí en Tacna.';
    $acciones_final = [];
    $origen_final = 'escudo_contexto';
    $motivo_bloqueo = 'TEMA_PROHIBIDO';
} elseif (preg_match('/\b(asdf|qwer|zxcv|jajaja)\b/i', $normalizada) || preg_match('/(.)\1{4,}/', $normalizada)) {
    $categoria_detectada = 'Ruido / Spam';
    $texto_final = 'Me agarraste fuera de juego. ¿Te muestro las obras o a los candidatos?';
    $acciones_final = [
        ['label' => '🗺️ Ver Obras', 'type' => 'ir_a_obras'],
        ['label' => '👥 Candidatos', 'type' => 'ir_a_candidatos']
    ];
    $origen_final = 'escudo_contexto';
    $motivo_bloqueo = 'TEXTO_ALEATORIO';
    if (preg_match('/(.)\1{7,}/', $normalizada)) $spam_extremo = true;
}

// --- REGISTRO DE PREGUNTAS HUÉRFANAS ---
// Crear tabla si no existe (Paso 1)
$db->exec("CREATE TABLE IF NOT EXISTS panel_preguntas_huerfanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta TEXT NOT NULL,
    normalizada VARCHAR(255) NOT NULL,
    categoria_detectada VARCHAR(100) NULL,
    fecha DATETIME NOT NULL,
    repeticiones INT DEFAULT 1,
    origen VARCHAR(50) DEFAULT 'router',
    estado VARCHAR(50) DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Validaciones Anti-Spam / Datos Sensibles
$guardar_bd = true;
if (isset($spam_extremo) && $spam_extremo) $guardar_bd = false; // Spam extremo no se guarda
if (preg_match('/\b(gordo|feo|tonto|zonzo|gil|sonso|mierda|concha|puta|cagada|imbecil|estupido|idiota|cabro|ctm|ptm|carajo|basura)\b/i', $normalizada)) $guardar_bd = false; // Insultos

// Guardar o actualizar en BD (Paso 2)
if ($guardar_bd) {
    $stmt = $db->prepare("SELECT id FROM panel_preguntas_huerfanas WHERE normalizada = ?");
    $stmt->execute([$normalizada]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Ya existe: aumentar contador y actualizar fecha
        $stmtUpd = $db->prepare("UPDATE panel_preguntas_huerfanas SET repeticiones = repeticiones + 1, fecha = NOW() WHERE id = ?");
        $stmtUpd->execute([$row['id']]);
    } else {
        // No existe: registrar como nueva
        $stmtIns = $db->prepare("INSERT INTO panel_preguntas_huerfanas (pregunta, normalizada, categoria_detectada, fecha, origen, estado) VALUES (?, ?, ?, NOW(), ?, 'pendiente')");
        $stmtIns->execute([$mensaje, $normalizada, $categoria_detectada, $origen_final]);
    }
}

// 8. Devolver el JSON final
echo json_encode([
    'ok' => true,
    'texto' => $texto_final,
    'acciones' => $acciones_final,
    'origen' => $origen_final,
    'categoria_detectada' => $categoria_detectada,
    'permitir_ia' => $permitir_ia,
    'motivo_bloqueo' => $motivo_bloqueo,
    'debug_ia_activa' => $ia_activa,
    'debug_prompt_cargado' => $debug_prompt_cargado
]);