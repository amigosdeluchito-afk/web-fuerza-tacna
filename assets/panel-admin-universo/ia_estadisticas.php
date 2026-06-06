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
    'total' => 0, 'openai' => 0, 'simulador' => 0, 'tokens_in' => 0, 'tokens_out' => 0,
    'errores' => 0, 'limite_ip' => 0, 'limite_global' => 0
];

try {
    $sql_auditoria = "SELECT 
        COUNT(*) as total_consultas,
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
$tipo_cambio = 3.75; // <-- TIPO DE CAMBIO CONFIGURABLE MANUALMENTE

$costo_usd = ($stats['tokens_in'] / 1000000 * $tarifa_in_1M) + ($stats['tokens_out'] / 1000000 * $tarifa_out_1M);
$costo_pen = $costo_usd * $tipo_cambio;
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
    </style>
</head>
<body>

<header class="app-header">
  <nav>
    <a href="index.php">📷 Fotos</a>
    <a href="agregar_obra.php">➕ Agregar Obra</a>
    <a href="editar_obra.php">✏️ Editar Obra</a>
    <a href="segmentos.php">🗂️ Segmentos</a>
    <a href="cronologia.php">⏳ Cronología</a>
    <a href="ia_respuestas.php">🧠 Cerebro IA</a>
    <a href="ia_conocimiento.php">📚 Base Conocimiento</a>
    <a href="ia_estadisticas.php" class="active">📊 Estadísticas IA</a>
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

    <!-- FILA 1: KPIs Principales -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="kpi-title">Consultas Registradas <span>💬</span></div>
                <h3 class="kpi-value"><?= number_format($stats['total']) ?></h3>
                <span class="kpi-sub">Total de interacciones recibidas (<?= $rango_label ?>)</span>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="kpi-title">Llegaron a OpenAI <span class="text-openai">🤖</span></div>
                <h3 class="kpi-value text-openai"><?= number_format($stats['openai']) ?></h3>
                <span class="kpi-sub">Frente a <?= number_format($stats['simulador']) ?> atajadas en BD local.</span>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="kpi-title">Tokens Consumidos <span>🪙</span></div>
                <h3 class="kpi-value"><?= number_format($stats['tokens_in'] + $stats['tokens_out']) ?></h3>
                <span class="kpi-sub">In: <?= number_format($stats['tokens_in']) ?> | Out: <?= number_format($stats['tokens_out']) ?></span>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card" style="border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.05);">
                <div class="kpi-title">Costo Estimado (Aprox) <span>💰</span></div>
                <h3 class="kpi-value text-costo">S/ <?= number_format($costo_pen, 4) ?></h3>
                <span class="kpi-sub">≈ USD $<?= number_format($costo_usd, 4) ?> (T.C. <?= $tipo_cambio ?>)</span>
            </div>
        </div>
    </div>

    <!-- FILA 2: Alertas y Salud -->
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
            </div>
        </div>
    </div>

    <!-- FILA 3: Contenedores para Bloque 3 (Gráficos) -->
    <h6 style="color:#f8fafc; font-weight:bold; margin-top: 30px; margin-bottom: 0;">📉 Tendencias Gráficas (Bloque 3 - Próximamente)</h6>
    <div class="row">
        <div class="col-md-8">
            <div class="chart-box">
                <div class="chart-placeholder">
                    Aquí se renderizará el gráfico de <b>Tráfico de Consultas vs OpenAI</b> usando Chart.js.<br>
                    <small class="text-muted">(Añadido en el siguiente bloque para no mezclar lógicas)</small>
                </div>
                <!-- <canvas id="chartTrafico"></canvas> -->
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-placeholder">
                    Aquí se renderizará la Dona de <b>Distribución de Estados</b>.<br>
                    <small class="text-muted">(Añadido en el siguiente bloque)</small>
                </div>
                <!-- <canvas id="chartEstados"></canvas> -->
            </div>
        </div>
    </div>

</div>

<!-- Script Wrapper seguro (No rompe UI si falla) -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        try {
            // Aquí insertaremos la lógica de Chart.js en el Bloque 3.
            // Al usar Try-Catch nos aseguramos de que el dashboard nunca se rompa.
        } catch(e) {
            console.error("Error al cargar gráficos: ", e);
        }
    });
</script>
</body>
</html>