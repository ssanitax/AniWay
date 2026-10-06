<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniWay - Calculadora de Viaje</title>
    <link rel="shortcut icon" href="static/aniway_favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="static/style.css">
</head>
<body>
<div id="layout">

<div id="sidebar">
<div class="header-container">
<img src="static/logo.png" class="neon-logo" alt="AniWay">
<div class="header-text">
<h1>AniWay</h1>
</div>
</div>

<p class="spain-badge">Solo rutas en España</p>

<form id="trip-form">

<label>TIPO DE REPARTO</label>
<select id="modo_coste" name="modo_coste" onchange="cambiarModoCoste()">
<option value="total">Coste total conjunto</option>
<option value="separado">Coste separado ida / vuelta</option>
</select>

<div id="coste_total_container">
<label>DINERO TOTAL VIAJE (€)</label>
<input type="number" step="0.01" id="coste_total" name="coste_total" placeholder="0.00">
</div>

<div id="coste_separado_container" style="display:none;">
<label>COSTE IDA (€)</label>
<input type="number" step="0.01" id="coste_ida" name="coste_ida" placeholder="0.00">
<label>COSTE VUELTA (€)</label>
<input type="number" step="0.01" id="coste_vuelta" name="coste_vuelta" placeholder="0.00">
</div>

<h3>Ruta y Pasajeros</h3>
<div id="listaTrayectos"></div>

<button type="button" class="btn btn-add" onclick="crearTramo('ida')">+ AÑADIR TRAMO IDA</button>
<button type="button" class="btn btn-add" onclick="crearTramo('vuelta')">+ AÑADIR TRAMO VUELTA</button>
<button type="submit" class="btn btn-submit">CALCULAR REPARTO</button>

</form>

<div id="results" class="results-container" style="display:none;">
<h4>RESULTADOS</h4>
<div id="results-list"></div>
</div>

</div>

<div id="map-container"></div>
</div>

<script>
window.ANIWAY = {
    spainBounds: [
        [<?= SPAIN_LAT_MIN ?>, <?= SPAIN_LON_MIN ?>],
        [<?= SPAIN_LAT_MAX ?>, <?= SPAIN_LON_MAX ?>]
    ]
};
</script>
<script src="static/app.js"></script>
</body>
</html>
