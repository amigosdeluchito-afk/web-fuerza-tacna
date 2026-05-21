<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nueva Obra - Panel Admin</title>
    <style>
        body { font-family: 'Arial', sans-serif; background: #fdf5f7; color: #333; padding: 30px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        h2 { color: #801039; text-transform: uppercase; font-weight: 900; margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;}
        label { display: block; margin-top: 15px; font-weight: bold; color: #555; font-size: 14px;}
        input, select, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: #801039; outline: none; }
        button { margin-top: 25px; width: 100%; padding: 15px; background: #801039; color: #ffc300; border: none; font-weight: 900; font-size: 16px; border-radius: 6px; cursor: pointer; text-transform: uppercase; transition: background 0.3s; }
        button:hover { background: #9a1546; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Agregar Nueva Obra</h2>
        
        <form action="guardar_obra.php" method="POST">
            <label>Segmento (Hoja de Excel destino):</label>
            <select name="segmento" required>
                <option value="educacion">Educación</option>
                <option value="agua">Agua y Saneamiento</option>
                <option value="transporte">Transporte</option>
                <option value="agricultura">Agricultura</option>
                <option value="social">Social</option>
                <option value="vias">Vías y Caminos</option>
            </select>

            <label>Nombre de la Obra:</label>
            <input type="text" name="nombre" required placeholder="Ej. Creación de colegio en Viñani...">

            <label>Estado:</label>
            <select name="estado" required>
                <option value="Entregado">Entregado</option>
                <option value="En construcción">En construcción</option>
                <option value="Paralizado">Paralizado</option>
                <option value="Buena Pro">Buena Pro</option>
                <option value="Transferencia">Transferencia</option>
                <option value="En estudios">En estudios</option>
            </select>

            <label>Monto Referencial (S/):</label>
            <input type="text" name="monto" placeholder="Ej. 1,500,000.00">

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;"><label>Distrito:</label><input type="text" name="distrito" placeholder="Ej. Gregorio Albarracín"></div>
                <div style="flex: 1;"><label>Provincia:</label><input type="text" name="provincia" value="Tacna"></div>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;"><label>Coordenada X (Longitud):</label><input type="text" name="x" placeholder="Ej. 0.345"></div>
                <div style="flex: 1;"><label>Coordenada Y (Latitud):</label><input type="text" name="y" placeholder="Ej. 0.678"></div>
            </div>

            <button type="submit">Guardar Obra</button>
        </form>
    </div>
</body>
</html>