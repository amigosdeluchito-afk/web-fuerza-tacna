<?php
require_once 'config.php';

// Requerir que esté logueado y que sea admin
require_login();
require_admin();

// Leer el archivo de registros
$logFile = __DIR__ . '/data/accesos_escudo.log';
$logs = file_exists($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$logs = array_reverse($logs); // Mostrar los más recientes primero
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Accesos – Panel</title>
  <style>
    body {
      background:#050816; color:#fff;
      font-family: system-ui, -apple-system, sans-serif;
      padding: 30px; margin: 0;
    }
    a.btn-back { color: #60a5fa; text-decoration: none; margin-bottom: 20px; display: inline-block; }
    a.btn-back:hover { text-decoration: underline; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #0b1020; border-radius: 8px; overflow: hidden; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #1e293b; text-align: left; }
    th { background: #1e3a8a; color: #93c5fd; font-weight: 600; }
    tr:hover { background: #172554; }
    .empty { padding: 20px; text-align: center; color: #94a3b8; }
  </style>
</head>
<body>

  <a href="index.php" class="btn-back">← Volver al Panel Principal</a>
  
  <h1>Registro de Accesos (Escudo Temporal)</h1>
  <p>Esta lista muestra las direcciones IP y dispositivos de las personas que han colocado la contraseña correctamente en la web.</p>

  <table>
    <thead>
      <tr>
        <th>Fecha y Hora</th>
        <th>Dirección IP</th>
        <th>Navegador / Dispositivo</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="3" class="empty">No hay accesos registrados todavía.</td></tr>
      <?php else: ?>
        <?php foreach ($logs as $line): 
          // Extraemos los datos con un patrón regex básico
          if (preg_match('/^\[(.*?)\] IP: (.*?) - Navegador: (.*)$/', $line, $matches)):
        ?>
          <tr>
            <td><?= htmlspecialchars($matches[1]) ?></td>
            <td><?= htmlspecialchars($matches[2]) ?></td>
            <td style="font-size: 0.85em; color: #cbd5e1;"><?= htmlspecialchars($matches[3]) ?></td>
          </tr>
        <?php endif; endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

</body>
</html>