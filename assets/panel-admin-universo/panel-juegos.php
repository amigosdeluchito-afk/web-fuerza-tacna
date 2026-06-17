<?php
require_once __DIR__ . '/config.php';
require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Juegos - Fuerza Tacna</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1200px; }
        .card { margin-bottom: 1.5rem; }
        .card-header { background-color: #801039; color: #ffc300; font-weight: bold; }
        .form-control-sm { height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem; }
        .btn-save-all { position: fixed; bottom: 20px; right: 20px; z-index: 1000; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1050; }
        .game-card { transition: box-shadow 0.2s ease; }
        .game-card:focus-within { box-shadow: 0 0 0 3px rgba(255, 195, 0, 0.5); }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">🎮 Panel de Configuración de Juegos</h1>
            <a href="index.php" class="btn btn-secondary">Volver al Panel Principal</a>
        </div>

        <div id="games-list" class="row">
            <p>Cargando juegos...</p>
        </div>
    </div>

    <button id="save-all-btn" class="btn btn-warning btn-lg btn-save-all" disabled>
        Guardar Cambios
    </button>

    <div class="toast-container">
        <div id="save-toast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
            <div class="toast-header">
                <strong class="mr-auto">Panel de Juegos</strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body">
                <!-- Mensaje dinámico -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="panel-juegos.js?v=1"></script>
</body>
</html>