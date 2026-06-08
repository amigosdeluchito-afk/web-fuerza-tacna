<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();

// ==========================================
// BLOQUE 1: LÓGICA BASE Y CONSULTAS (READ-ONLY)
// ==========================================

// 1. Filtro de Fechas Dinámico
$filtro = $_GET['filtro'] ?? 'hoy';
$where_auditoria = "DATE(fecha) = CURDATE()";
$where_huerfanas = "DATE(fecha) = CURDATE()";
$rango_label = "Hoy";

switch ($filtro) {
    case 'ayer':
        $where_auditoria = "DATE(fecha) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $where_huerfanas = "DATE(fecha) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $rango_label = "Ayer";
        break;
    case '7dias':
        $where_auditoria = "DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $where_huerfanas = "DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $rango_label = "Últimos 7 días";
        break;
    case 'mes':
        $where_auditoria = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        $where_huerfanas = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        $rango_label = "Este Mes";
        break;
}

// 2. Consultas a Auditoría (Tráfico, Tokens, Errores y Límites)
$stats = [
    'total' => 0, 'usuarios' => 0, 'openai' => 0, 'simulador' => 0, 'tokens_in' => 0, 'tokens_out' => 0,
    'errores' => 0, 'limite_ip' => 0, 'limite_global' => 0 
];

try {
    $sql_auditoria = "SELECT 
        COUNT(*) as total_consultas,
        COUNT(DISTINCT ip_hash) as total_usuarios,
        SUM(CASE WHEN estado = 'exito_openai' THEN 1 ELSE 0 END) as total_openai,
        SUM(CASE WHEN estado = 'exito_simulador' THEN 1 ELSE 0 END) as total_simulador,
        SUM(tokens_input) as tokens_input,
        SUM(tokens_output) as tokens_output,
        SUM(CASE WHEN estado = 'error_openai' THEN 1 ELSE 0 END) as errores,
        SUM(CASE WHEN estado = 'limite_ip' THEN 1 ELSE 0 END) as limite_ip,
        SUM(CASE WHEN estado = 'limite_global' THEN 1 ELSE 0 END) as limite_global
    FROM panel_ia_auditoria WHERE $where_auditoria";
    
    $stmt = $db->query($sql_auditoria);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $stats['total'] = (int)$row['total_consultas'];
        $stats['usuarios'] = (int)$row['total_usuarios'];
        $stats['openai'] = (int)$row['total_openai'];
        $stats['simulador'] = (int)$row['total_simulador'];
        $stats['tokens_in'] = (int)$row['tokens_input'];
        $stats['tokens_out'] = (int)$row['tokens_output'];
        $stats['errores'] = (int)$row['errores'];
        $stats['limite_ip'] = (int)$row['limite_ip'];
        $stats['limite_global'] = (int)$row['limite_global'];
    }
} catch (Exception $e) {}

// 3. Consulta a Huérfanas (Nuevas)
$huerfanas_nuevas = 0;
try {
    $sql_huerfanas = "SELECT COUNT(*) FROM panel_preguntas_huerfanas WHERE estado = 'pendiente' AND $where_huerfanas";
    $huerfanas_nuevas = (int)$db->query($sql_huerfanas)->fetchColumn();
} catch (Exception $e) {}

// 4. Consulta a Salud del RAG (Documentos por fuente)
$rag_docs = [];
try {
    $sql_rag = "SELECT fuente, COUNT(*) as cantidad FROM panel_ia_conocimiento WHERE estado = 1 GROUP BY fuente";
    $rag_docs = $db->query($sql_rag)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 5. Cálculos de Costos Aproximados (GPT-4o-mini)
// Tarifas oficiales OpenAI: $0.150 / 1M Input, $0.600 / 1M Output
$tarifa_in_1M = 0.150;
$tarifa_out_1M = 0.600;

$costo_usd = ($stats['tokens_in'] / 1000000 * $tarifa_in_1M) + ($stats['tokens_out'] / 1000000 * $tarifa_out_1M);

// Ahorro: Asumimos que una consulta típica de simulador ahorra un promedio de 250 tokens input + 100 output
$costo_promedio_consulta_ahorrada = ((250 / 1000000) * $tarifa_in_1M) + ((100 / 1000000) * $tarifa_out_1M);
$ahorro_usd = $stats['simulador'] * $costo_promedio_consulta_ahorrada;

// Métricas Avanzadas (Eficiencia)
$promedio_consultas = $stats['usuarios'] > 0 ? round($stats['total'] / $stats['usuarios'], 1) : 0;
$costo_por_usuario = $stats['usuarios'] > 0 ? ($costo_usd / $stats['usuarios']) : 0;
$ratio_tokens = $stats['tokens_out'] > 0 ? round($stats['tokens_in'] / $stats['tokens_out'], 1) : 0;

// Retención (Usuarios que visitan en días distintos)
$usuarios_recurrentes = 0;
try {
    $stmt_ret = $db->query("SELECT COUNT(*) FROM (SELECT ip_hash FROM panel_ia_auditoria WHERE $where_auditoria GROUP BY ip_hash HAVING COUNT(DISTINCT DATE(fecha)) > 1) as t");
    $usuarios_recurrentes = (int)$stmt_ret->fetchColumn();
} catch(Exception $e) {}
$tasa_retencion = $stats['usuarios'] > 0 ? round(($usuarios_recurrentes / $stats['usuarios']) * 100, 1) : 0;

// Consulta más cara del periodo
$consulta_cara = null;
try {
    $stmt_cara = $db->query("SELECT pregunta, (tokens_input + tokens_output) as total_t FROM panel_ia_auditoria WHERE $where_auditoria AND (tokens_input + tokens_output) > 0 ORDER BY total_t DESC LIMIT 1");
    $consulta_cara = $stmt_cara->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Gráfico de Horas Pico
$labels_horas = []; $data_horas = [];
try {
    $stmt_horas = $db->query("SELECT HOUR(fecha) as hora, COUNT(*) as cantidad FROM panel_ia_auditoria WHERE $where_auditoria GROUP BY HOUR(fecha) ORDER BY hora ASC");
    $horas_data = $stmt_horas->fetchAll(PDO::FETCH_ASSOC);
    foreach($horas_data as $row) { $labels_horas[] = str_pad($row['hora'], 2, '0', STR_PAD_LEFT) . ':00'; $data_horas[] = (int)$row['cantidad']; }
} catch(Exception $e) {}

// ==========================================
// BLOQUE 2: DATOS PARA GRÁFICO DE COSTOS Y ROI (30 DÍAS)
// ==========================================
$historial_consumo = [];
$labels_costos = [];
$data_costos_input = [];
$data_costos_output = [];
$data_costos_usd = [];

try {
    // Esta consulta es independiente del filtro de arriba, siempre muestra los últimos 30 días.
    $sql_costos_30d = "
        SELECT 
            DATE(fecha) as dia,
            SUM(tokens_input) as sum_input,
            SUM(tokens_output) as sum_output
        FROM panel_ia_auditoria
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND estado = 'exito_openai'
        GROUP BY DATE(fecha)
        ORDER BY dia ASC
    ";
    $historial_consumo = $db->query($sql_costos_30d)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historial_consumo as $row) {
        $in = (int)$row['sum_input'];
        $out = (int)$row['sum_output'];
        $costo_dia_usd = (($in / 1000000) * $tarifa_in_1M) + (($out / 1000000) * $tarifa_out_1M);
        $labels_costos[] = date('d/m', strtotime($row['dia']));
        $data_costos_input[] = $in;
        $data_costos_output[] = $out;
        $data_costos_usd[] = round($costo_dia_usd, 4);
    }
} catch (Exception $e) {}

// ==========================================
// BLOQUE 3 Y 4: DATOS PARA GRÁFICOS (AGRUPACIÓN SQL)
// ==========================================

// Datos Gráfico 1: Tráfico por Día
$labels_trafico = []; $data_total = []; $data_openai = [];
try {
    $sql_trafico = "SELECT DATE(fecha) as dia, COUNT(*) as total, SUM(CASE WHEN estado='exito_openai' THEN 1 ELSE 0 END) as openai FROM panel_ia_auditoria WHERE $where_auditoria GROUP BY DATE(fecha) ORDER BY DATE(fecha) ASC";
    $trafico_data = $db->query($sql_trafico)->fetchAll(PDO::FETCH_ASSOC);
    foreach($trafico_data as $row) {
        $labels_trafico[] = date('d/m', strtotime($row['dia']));
        $data_total[] = (int)$row['total'];
        $data_openai[] = (int)$row['openai'];
    }
} catch(Exception $e) {}

// Datos Gráfico 2: Distribución de Estados
$labels_estados = []; $data_estados = []; $colores_estados = [];
try {
    $sql_estados = "SELECT estado, COUNT(*) as cantidad FROM panel_ia_auditoria WHERE $where_auditoria GROUP BY estado ORDER BY cantidad DESC";
    $estados_data = $db->query($sql_estados)->fetchAll(PDO::FETCH_ASSOC);
    // Paleta de colores semánticos
    $bg_colors = ['exito_openai' => '#10b981', 'exito_simulador' => '#3b82f6', 'limite_ip' => '#f59e0b', 'limite_global' => '#f97316', 'error_openai' => '#ef4444', 'router_fuera_tema' => '#8b5cf6'];
    foreach($estados_data as $row) {
        $est = $row['estado'];
        $labels_estados[] = ucfirst(str_replace('_', ' ', $est));
        $data_estados[] = (int)$row['cantidad'];
        $colores_estados[] = $bg_colors[$est] ?? '#64748b'; // Gris si es un estado desconocido
    }
} catch(Exception $e) {}

// Datos Gráfico 3: Escudos de Seguridad
$data_escudos = [
    'Límite de IP' => 0,
    'Límite Global' => 0,
    'Inyección / Hackeo' => 0,
    'Política Nacional' => 0,
    'Spam / Ruido' => 0,
];
try {
    $sql_lim = "SELECT estado, COUNT(*) as cant FROM panel_ia_auditoria WHERE $where_auditoria AND estado IN ('limite_ip', 'limite_global') GROUP BY estado";
    $res_lim = $db->query($sql_lim)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res_lim as $r) {
        if ($r['estado'] === 'limite_ip') $data_escudos['Límite de IP'] += (int)$r['cant'];
        if ($r['estado'] === 'limite_global') $data_escudos['Límite Global'] += (int)$r['cant'];
    }

    $sql_huerf = "SELECT categoria_detectada, SUM(repeticiones) as cant FROM panel_preguntas_huerfanas WHERE $where_huerfanas AND categoria_detectada IN ('Auditoría - Inyección', 'Política Nacional', 'Ruido / Spam') GROUP BY categoria_detectada";
    $res_huerf = $db->query($sql_huerf)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res_huerf as $r) {
        if ($r['categoria_detectada'] === 'Auditoría - Inyección') $data_escudos['Inyección / Hackeo'] += (int)$r['cant'];
        if ($r['categoria_detectada'] === 'Política Nacional') $data_escudos['Política Nacional'] += (int)$r['cant'];
        if ($r['categoria_detectada'] === 'Ruido / Spam') $data_escudos['Spam / Ruido'] += (int)$r['cant'];
    }
} catch(Exception $e) {}

$labels_escudos = array_keys($data_escudos);
$valores_escudos = array_values($data_escudos);
$total_escudos = array_sum($valores_escudos);

// Datos Gráfico 4: Top Categorías Más Consultadas
$labels_categorias = []; 
$data_categorias = [];
$colores_categorias = [];

try {
    // Excluimos las categorías de seguridad para centrarnos en intereses reales
    $sql_cat = "SELECT categoria_detectada, SUM(repeticiones) as cant 
                FROM panel_preguntas_huerfanas 
                WHERE $where_huerfanas 
                  AND categoria_detectada NOT IN ('Auditoría - Inyección', 'Spam / Ruido', 'Política Nacional', 'Seguridad', 'Bloqueo')
                  AND categoria_detectada IS NOT NULL
                  AND categoria_detectada != ''
                GROUP BY categoria_detectada 
                ORDER BY cant DESC LIMIT 10";
    $res_cat = $db->query($sql_cat)->fetchAll(PDO::FETCH_ASSOC);
    
    $cat_colors_palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#64748b', '#ef4444', '#14b8a6'];
    foreach ($res_cat as $i => $r) {
        $labels_categorias[] = $r['categoria_detectada'];
        $data_categorias[] = (int)$r['cant'];
        $colores_categorias[] = $cat_colors_palette[$i % count($cat_colors_palette)];
    }
} catch(Exception $e) {}

// Datos Tabla 1: Top Usuarios (IP Hash)
$top_usuarios = [];
try {
    $sql_top_usuarios = "SELECT ip_hash, COUNT(*) as total, SUM(CASE WHEN estado='limite_ip' THEN 1 ELSE 0 END) as bloqueos FROM panel_ia_auditoria WHERE $where_auditoria GROUP BY ip_hash ORDER BY total DESC LIMIT 5";
    $top_usuarios = $db->query($sql_top_usuarios)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Datos Tabla 2: Top Preguntas Huérfanas
$top_huerfanas = [];
try {
    $sql_top_huerfanas = "SELECT pregunta, repeticiones, categoria_detectada FROM panel_preguntas_huerfanas WHERE $where_huerfanas ORDER BY repeticiones DESC LIMIT 5";
    $top_huerfanas = $db->query($sql_top_huerfanas)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Datos Tabla 3: Últimos Errores
$ultimos_errores = [];
try {
    $sql_errores = "SELECT fecha, motivo_error FROM panel_ia_auditoria WHERE estado='error_openai' AND $where_auditoria ORDER BY fecha DESC LIMIT 5";
    $ultimos_errores = $db->query($sql_errores)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas IA - Panel Universo</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #020617; color: #e5e7eb; font-family: system-ui, sans-serif; padding-bottom: 40px; }
        .app-header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #020617; border-bottom: 1px solid #111827; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 2000; }
        .app-header nav a { color: #9ca3af; margin-right: 16px; text-decoration: none; font-size: 14px; }
        .app-header nav a.active { color: #ffffff; font-weight: 600; }
        .app-header nav a:hover { color: #e5e7eb; }
        .app-header .user { font-size: 13px; color: #9ca3af; }
        
        /* BLOQUE 2: DISEÑO DE TARJETAS KPI */
        .kpi-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; height: 100%; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .kpi-title { color: #94a3b8; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .kpi-value { color: #f8fafc; font-size: 28px; font-weight: 800; line-height: 1.2; margin: 0; }
        .kpi-sub { color: #64748b; font-size: 12px; margin-top: 8px; display: block; }
        
        .text-openai { color: #10b981; }
        .text-costo { color: #fbbf24; }
        .text-alerta { color: #ef4444; }
        
        /* Filtro de UI */
        .filter-select { background: #1e293b; color: #fff; border: 1px solid #334155; border-radius: 6px; padding: 6px 12px; font-size: 14px; outline: none; }
        .filter-select:focus { border-color: #3b82f6; }
        
        /* Contenedores de Gráficos (Para Fase Futura, no se rompe si Chart.js falla) */
        .chart-box { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; margin-top: 20px; min-height: 300px; display: flex; align-items: center; justify-content: center; }
        .chart-placeholder { color: #475569; font-size: 13px; text-align: center; }

        /* Estilos del Menú Desplegable */
        .dropdown { position: relative; display: inline-block; margin-right: 16px; }
        .dropdown::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 15px; }
        .dropdown .dropbtn { background: transparent; border: none; color: #9ca3af; font-size: 14px; cursor: pointer; font-family: inherit; padding: 0; display: flex; align-items: center; outline: none; }
        .dropdown .dropbtn.active { color: #ffffff; font-weight: 600; }
        .dropdown:hover .dropbtn { color: #e5e7eb; }
        .dropdown-content { display: none; position: absolute; background-color: #0f172a; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5); z-index: 1; border-radius: 8px; border: 1px solid #1e293b; top: 100%; left: 0; padding: 8px 0; margin-top: 10px; }
        .dropdown-content a { color: #9ca3af !important; padding: 8px 16px !important; text-decoration: none; display: block; margin: 0 !important; font-size: 13px !important; }
        .dropdown-content a:hover { background-color: #1e293b; color: #fff !important; }
        .dropdown-content a.active { color: #3b82f6 !important; background-color: rgba(59,130,246,0.1); font-weight: 600; }
        .dropdown:hover .dropdown-content { display: block; }
    </style>
</head>
<body>

<header class="app-header">
  <nav style="display:flex; align-items:center;">
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">📷 Fotos</a>
    <a href="agregar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'agregar_obra.php' ? 'active' : '' ?>">➕ Agregar Obra</a>
    <a href="editar_obra.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_obra.php' ? 'active' : '' ?>">✏️ Editar Obra</a>
    <a href="gestionar_visibilidad.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gestionar_visibilidad.php' ? 'active' : '' ?>">👁️ Visibilidad</a>
    <a href="segmentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'segmentos.php' ? 'active' : '' ?>">🗂️ Segmentos</a>
    <a href="cronologia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cronologia.php' ? 'active' : '' ?>">⏳ Cronología</a>
    <a href="editar_candidato.php" class="<?= basename($_SERVER['PHP_SELF']) == 'editar_candidato.php' ? 'active' : '' ?>">👥 Candidatos</a>
    
    <div class="dropdown">
      <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['ia_respuestas.php', 'ia_conocimiento.php', 'ia_fuentes.php', 'ia_estadisticas.php']) ? 'active' : '' ?>">🧠 IA y Conocimiento ▾</button>
      <div class="dropdown-content">
        <a href="ia_respuestas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_respuestas.php' ? 'active' : '' ?>">🧠 Cerebro IA</a>
        <a href="ia_conocimiento.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_conocimiento.php' ? 'active' : '' ?>">📚 Base Conocimiento</a>
        <a href="ia_fuentes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_fuentes.php' ? 'active' : '' ?>">🔗 Fuentes Externas</a>
        <a href="ia_estadisticas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ia_estadisticas.php' ? 'active' : '' ?>">📊 Estadísticas IA</a>
      </div>
    </div>

    <?php if (is_admin()): ?>
    <div class="dropdown">
      <button class="dropbtn <?= in_array(basename($_SERVER['PHP_SELF']), ['usuarios.php', 'historial.php', 'ver_accesos.php']) ? 'active' : '' ?>">⚙️ Admin ▾</button>
      <div class="dropdown-content">
        <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : '' ?>">👤 Usuarios</a>
        <a href="historial.php" class="<?= basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : '' ?>">🕒 Historial</a>
        <a href="ver_accesos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ver_accesos.php' ? 'active' : '' ?>">🕵️ Accesos IP</a>
      </div>
    </div>
    <?php endif; ?>
  </nav>
  <div class="user">
    <?= htmlspecialchars(current_user() ?? '') ?> · <a href="logout.php" style="color:#9ca3af;">Salir</a>
  </div>
</header>

<div class="container-fluid px-4" style="margin-top: 80px; max-width: 1400px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 style="color:#f8fafc; font-weight:bold; margin-bottom: 0;">📊 Panel de Monitoreo de IA</h4>
            <p style="color:#94a3b8; font-size: 13px; margin: 0;">Métricas de consumo, salud y rendimiento de Luchito.</p>
        </div>
        
        <div>
            <form method="GET" id="filtroForm">
                <select name="filtro" class="filter-select" onchange="document.getElementById('filtroForm').submit();">
                    <option value="hoy" <?= $filtro === 'hoy' ? 'selected' : '' ?>>📅 Hoy</option>
                    <option value="ayer" <?= $filtro === 'ayer' ? 'selected' : '' ?>>📅 Ayer</option>
                    <option value="7dias" <?= $filtro === '7dias' ? 'selected' : '' ?>>📅 Últimos 7 días</option>
                    <option value="mes" <?= $filtro === 'mes' ? 'selected' : '' ?>>📅 Este mes</option>
                </select>
            </form>
        </div>
    </div>

    <!-- FILA 1: KPIs DE COSTOS Y ROI -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="kpi-card" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">
                <div class="kpi-title">Costo Real API (<?= $rango_label ?>) <span>💰</span></div>
                <h3 class="kpi-value" style="color: #ef4444;">$<?= number_format($costo_usd, 4) ?></h3>
                <span class="kpi-sub">Costo exacto de peticiones a OpenAI.</span>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="kpi-card" style="border-color: rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.05);">
                <div class="kpi-title">Tokens Consumidos <span>🪙</span></div>
                <h3 class="kpi-value" style="color: #60a5fa;"><?= number_format($stats['tokens_in'] + $stats['tokens_out']) ?></h3>
                <span class="kpi-sub">
                    <span style="color: #60a5fa;">In: <?= number_format($stats['tokens_in']) ?></span> | 
                    <span style="color: #a78bfa;">Out: <?= number_format($stats['tokens_out']) ?></span>
                </span>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="kpi-card" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05);">
                <div class="kpi-title">Ahorro por Simulador <span>🛡️</span></div>
                <h3 class="kpi-value" style="color: #10b981;">$<?= number_format($ahorro_usd, 4) ?></h3>
                <span class="kpi-sub">Dinero ahorrado gracias a los escudos RAG locales.</span>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE EVOLUCIÓN DE COSTOS -->
    <div class="row mb-4">
        <div class="col-12"><div class="chart-box">
            <?php if(empty($data_costos_usd)): ?><div class="chart-placeholder">No hay datos de consumo de OpenAI en los últimos 30 días.</div><?php else: ?><canvas id="chartTokensCostos"></canvas><?php endif; ?>
        </div></div>
    </div>

    <!-- FILA 3: Alertas y Salud -->
    <div class="row">
        <div class="col-md-8">
            <h6 style="color:#f8fafc; font-weight:bold; margin-bottom: 15px;">🛡️ Monitoreo de Alertas y Escudos</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="kpi-card">
                        <div class="kpi-title text-alerta">Límites por IP <span>🛑</span></div>
                        <h3 class="kpi-value"><?= number_format($stats['limite_ip']) ?></h3>
                        <span class="kpi-sub">Usuarios bloqueados por abuso.</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="kpi-card">
                        <div class="kpi-title text-alerta">Errores OpenAI <span>🔌</span></div>
                        <h3 class="kpi-value"><?= number_format($stats['errores']) ?></h3>
                        <span class="kpi-sub">Fallos de API Key, Saldo o Red.</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="kpi-card">
                        <div class="kpi-title" style="color:#38bdf8;">Huérfanas Nuevas <span>❓</span></div>
                        <h3 class="kpi-value"><?= number_format($huerfanas_nuevas) ?></h3>
                        <span class="kpi-sub">Preguntas sin responder pendientes.</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <h6 style="color:#f8fafc; font-weight:bold; margin-bottom: 15px;">📚 Estado del Conocimiento (RAG)</h6>
            <div class="kpi-card">
                <?php if(empty($rag_docs)): ?>
                    <div class="text-muted" style="font-size: 13px; text-align:center; padding-top:20px;">No hay conocimiento cargado o activo.</div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size: 13.5px; color: #cbd5e1;">
                        <?php foreach($rag_docs as $doc): ?>
                            <li class="d-flex justify-content-between mb-2 pb-2" style="border-bottom: 1px solid #1e293b;">
                                <span><?= htmlspecialchars($doc['fuente']) ?></span>
                                <span class="badge badge-primary" style="background:#3b82f6;"><?= $doc['cantidad'] ?> Docs</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if($consulta_cara): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 10px; margin-top: 15px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #fca5a5; font-weight: bold; display: block; margin-bottom: 5px;">🔥 La consulta más cara</span>
                        <span style="font-size: 12px; color: #f8fafc; font-style: italic;">"<?= htmlspecialchars(mb_strimwidth($consulta_cara['pregunta'], 0, 50, '...')) ?>"</span>
                        <span class="badge badge-danger float-right"><?= $consulta_cara['total_t'] ?> tokens</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FILA 4: Métricas de Eficiencia -->
    <h6 style="color:#f8fafc; font-weight:bold; margin-top: 20px; margin-bottom: 15px;">⚡ Eficiencia y Comportamiento</h6>
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="kpi-card" style="padding: 15px; height: auto;">
            <div class="kpi-title" style="color:#a78bfa;">Consultas / Usuario</div>
            <h3 class="kpi-value" style="font-size:22px;"><?= $promedio_consultas ?></h3>
            <span class="kpi-sub">De <?= number_format($stats['usuarios']) ?> usuarios únicos.</span>
        </div></div>
        <div class="col-md-3 mb-3"><div class="kpi-card" style="padding: 15px; height: auto;">
            <div class="kpi-title" style="color:#a78bfa;">Retención (Vuelven)</div>
            <h3 class="kpi-value" style="font-size:22px;"><?= $tasa_retencion ?>%</h3>
            <span class="kpi-sub"><?= $usuarios_recurrentes ?> volvieron otros días.</span>
        </div></div>
        <div class="col-md-3 mb-3"><div class="kpi-card" style="padding: 15px; height: auto;">
            <div class="kpi-title" style="color:#34d399;">Ratio In/Out (Tokens)</div>
            <h3 class="kpi-value" style="font-size:22px;"><?= $ratio_tokens ?></h3>
            <span class="kpi-sub">Lee <?= $ratio_tokens ?> palabras por 1 que escribe.</span>
        </div></div>
        <div class="col-md-3 mb-3"><div class="kpi-card" style="padding: 15px; height: auto;">
            <div class="kpi-title" style="color:#fbbf24;">Costo Prom. / Usuario</div>
            <h3 class="kpi-value" style="font-size:22px;">$<?= number_format($costo_por_usuario, 4) ?></h3>
            <span class="kpi-sub">Basado en el costo general.</span>
        </div></div>
    </div>

    <!-- FILA 5: Contenedores para Gráficos de Tráfico -->
    <h6 style="color:#f8fafc; font-weight:bold; margin-top: 30px; margin-bottom: 0;">📉 Tendencias Gráficas</h6>
    <div class="row">
        <div class="col-md-8">
            <div class="chart-box">
                <?php if(empty($data_total)): ?>
                    <div class="chart-placeholder">No hay datos de consultas para el rango de fechas seleccionado.</div>
                <?php else: ?>
                    <canvas id="chartTrafico"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-box">
                <?php if(empty($data_estados)): ?>
                    <div class="chart-placeholder">Sin interacciones registradas.</div>
                <?php else: ?>
                    <canvas id="chartEstados"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE HORAS PICO Y ESCUDOS -->
    <div class="row mt-4">
        <div class="col-md-7">
            <h6 style="color:#f8fafc; font-weight:bold; margin-bottom: 15px;">🕒 Interacciones por Hora</h6>
            <div class="chart-box" style="min-height: 250px; padding: 15px; margin-top: 0;">
                <?php if(empty($data_horas)): ?>
                    <div class="chart-placeholder">No hay datos de tráfico por horas registrados.</div>
                <?php else: ?>
                    <canvas id="chartHoras"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-5">
            <h6 style="color:#f8fafc; font-weight:bold; margin-bottom: 15px;">🛡️ Activación de Escudos</h6>
            <div class="chart-box" style="min-height: 250px; padding: 15px; margin-top: 0;">
                <?php if($total_escudos == 0): ?>
                    <div class="chart-placeholder">No se han registrado intentos de abuso. ¡Todo en orden!</div>
                <?php else: ?>
                    <canvas id="chartEscudos"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE TOP CATEGORÍAS -->
    <div class="row mt-4">
        <div class="col-12">
            <h6 style="color:#f8fafc; font-weight:bold; margin-bottom: 15px;">🎯 Top Temas de Interés (Categorías)</h6>
            <div class="chart-box" style="min-height: 280px; padding: 15px; margin-top: 0;">
                <?php if(empty($data_categorias)): ?>
                    <div class="chart-placeholder">No hay suficientes datos de categorías en este periodo.</div>
                <?php else: ?>
                    <canvas id="chartCategorias"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FILA 6: Tablas de Ranking y Monitoreo -->
    <h6 style="color:#f8fafc; font-weight:bold; margin-top: 30px; margin-bottom: 15px;">📋 Tablas de Monitoreo Clave</h6>
    <div class="row">
        <!-- Top Usuarios -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm" style="background: #0f172a; border: 1px solid #1e293b; height: 100%;">
                <div class="card-header bg-dark text-white border-bottom-0" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-size: 13px;">🕵️ Top 5 Usuarios Activos</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-dark table-striped mb-0" style="background: transparent; font-size: 12px;">
                        <thead><tr><th>Hash (IP)</th><th class="text-center">Consultas</th><th class="text-center">Límites</th></tr></thead>
                        <tbody>
                            <?php if(empty($top_usuarios)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Sin datos registrados</td></tr>
                            <?php else: ?>
                                <?php foreach($top_usuarios as $tu): ?>
                                    <tr>
                                        <td><span title="<?= htmlspecialchars($tu['ip_hash']) ?>" style="font-family:monospace;"><?= substr($tu['ip_hash'], 0, 8) ?>...</span></td>
                                        <td class="text-center text-info font-weight-bold"><?= $tu['total'] ?></td>
                                        <td class="text-center <?= $tu['bloqueos'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= $tu['bloqueos'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Huérfanas -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm" style="background: #0f172a; border: 1px solid #1e293b; height: 100%;">
                <div class="card-header bg-dark text-white border-bottom-0" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-size: 13px;">❓ Top 5 Huérfanas Más Repetidas</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-dark table-striped mb-0" style="background: transparent; font-size: 12px;">
                        <thead><tr><th>Pregunta</th><th class="text-center">Repetidas</th></tr></thead>
                        <tbody>
                            <?php if(empty($top_huerfanas)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-3">No hay huérfanas pendientes</td></tr>
                            <?php else: ?>
                                <?php foreach($top_huerfanas as $th): ?>
                                    <tr>
                                        <td title="<?= htmlspecialchars($th['pregunta']) ?>"><?= htmlspecialchars(mb_strimwidth($th['pregunta'], 0, 30, '...')) ?> <br><small class="text-muted"><?= htmlspecialchars($th['categoria_detectada'] ?: 'Sin tema') ?></small></td>
                                        <td class="text-center text-warning font-weight-bold align-middle"><?= $th['repeticiones'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Últimos Errores -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm" style="background: #0f172a; border: 1px solid #1e293b; height: 100%;">
                <div class="card-header bg-dark text-white border-bottom-0" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-size: 13px;">🚨 Últimos 5 Errores IA</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-dark table-striped mb-0" style="background: transparent; font-size: 12px;">
                        <thead><tr><th>Hora</th><th>Motivo / Diagnóstico</th></tr></thead>
                        <tbody>
                            <?php if(empty($ultimos_errores)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-3">Todo funciona perfecto ✅</td></tr>
                            <?php else: ?>
                                <?php foreach($ultimos_errores as $te): ?>
                                    <tr>
                                        <td style="white-space:nowrap;"><?= date('H:i', strtotime($te['fecha'])) ?></td>
                                        <td class="text-danger" title="<?= htmlspecialchars($te['motivo_error']) ?>"><?= htmlspecialchars(mb_strimwidth($te['motivo_error'], 0, 35, '...')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Cargar librería Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Script Wrapper seguro (No rompe UI si falla) -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        try {
            if (typeof Chart === 'undefined') {
                console.warn("Chart.js no cargó desde el CDN. Los gráficos no se mostrarán, pero el panel sigue vivo.");
                return;
            }

            // Configuración global de Chart.js para Modo Oscuro
            Chart.defaults.color = '#94a3b8';
            Chart.defaults.borderColor = '#1e293b';
            Chart.defaults.font.family = 'system-ui, sans-serif';

            // Renderizado: Tráfico vs OpenAI
            const cvTrafico = document.getElementById('chartTrafico');
            if (cvTrafico) {
                new Chart(cvTrafico.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($labels_trafico) ?>,
                        datasets: [
                            { label: 'Consultas Totales', data: <?= json_encode($data_total) ?>, borderColor: '#38bdf8', backgroundColor: 'rgba(56, 189, 248, 0.15)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 4 },
                            { label: 'Llegaron a OpenAI', data: <?= json_encode($data_openai) ?>, borderColor: '#10b981', backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 5], tension: 0.3, pointRadius: 4 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }

            // Renderizado: Dona de Distribución de Estados
            const cvEstados = document.getElementById('chartEstados');
            if (cvEstados) {
                new Chart(cvEstados.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($labels_estados) ?>,
                        datasets: [{
                            data: <?= json_encode($data_estados) ?>,
                            backgroundColor: <?= json_encode($colores_estados) ?>,
                            borderWidth: 1,
                            borderColor: '#0f172a'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
                });
            }
            
            // Renderizado: Horas Pico
            const cvHoras = document.getElementById('chartHoras');
            if (cvHoras) {
                new Chart(cvHoras.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels_horas) ?>,
                        datasets: [{
                            label: 'Interacciones por Hora',
                            data: <?= json_encode($data_horas) ?>,
                            backgroundColor: '#8b5cf6',
                            borderRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }

            // Renderizado: Escudos de Seguridad
            const cvEscudos = document.getElementById('chartEscudos');
            if (cvEscudos) {
                new Chart(cvEscudos.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($labels_escudos) ?>,
                        datasets: [{
                            data: <?= json_encode($valores_escudos) ?>,
                            backgroundColor: ['#f59e0b', '#f97316', '#ef4444', '#ec4899', '#8b5cf6'],
                            borderWidth: 2,
                            borderColor: '#0f172a'
                        }]
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false, cutout: '65%', 
                        plugins: { legend: { position: 'right', labels: { boxWidth: 12, color: '#cbd5e1' } } } 
                    }
                });
            }

            // Renderizado: Top Categorías (Barras Horizontales)
            const cvCategorias = document.getElementById('chartCategorias');
            if (cvCategorias) {
                new Chart(cvCategorias.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels_categorias) ?>,
                        datasets: [{
                            label: 'Veces Consultada',
                            data: <?= json_encode($data_categorias) ?>,
                            backgroundColor: <?= json_encode($colores_categorias) ?>,
                            borderRadius: 4
                        }]
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false, 
                        indexAxis: 'y', // Convertir a barras horizontales
                        plugins: { legend: { display: false } }, 
                        scales: { 
                            x: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#cbd5e1', font: { weight: 'bold' } }, grid: { display: false } }
                        } 
                    }
                });
            }

            // Renderizado: Evolución de Consumo y Costos (30 días)
            const cvCostos = document.getElementById('chartTokensCostos');
            if (cvCostos) {
                new Chart(cvCostos.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels_costos) ?>,
                        datasets: [
                            {
                                label: 'Costo (USD)',
                                data: <?= json_encode($data_costos_usd) ?>,
                                type: 'line',
                                borderColor: '#ef4444',
                                backgroundColor: '#ef4444',
                                borderWidth: 2,
                                tension: 0.3,
                                yAxisID: 'yCosto',
                                order: 0
                            },
                            {
                                label: 'Tokens de Entrada (Prompt)',
                                data: <?= json_encode($data_costos_input) ?>,
                                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                borderColor: '#3b82f6',
                                borderWidth: 1,
                                stack: 'Stack 0',
                                yAxisID: 'yTokens',
                                order: 1
                            },
                            {
                                label: 'Tokens de Salida (Respuesta)',
                                data: <?= json_encode($data_costos_output) ?>,
                                backgroundColor: 'rgba(167, 139, 250, 0.7)',
                                borderColor: '#a78bfa',
                                borderWidth: 1,
                                stack: 'Stack 0',
                                yAxisID: 'yTokens',
                                order: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { 
                            title: { display: true, text: 'Evolución de Consumo y Costos (Últimos 30 días)', color: '#f8fafc', font: { size: 16 } },
                            legend: { position: 'top' } 
                        },
                        scales: {
                            x: { stacked: true },
                            yTokens: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Tokens', color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            yCosto: {
                                type: 'linear', display: true, position: 'right', title: { display: true, text: 'Costo (USD)', color: '#ef4444' },
                                ticks: { color: '#ef4444', callback: function(value) { return '$' + value.toFixed(4); } },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            }
        } catch(e) {
            console.error("Error al cargar gráficos: ", e);
        }
    });
</script>
</body>
</html>