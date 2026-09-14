<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/spain.php';

header('Content-Type: application/json; charset=utf-8');
require_login_api();

$lat1 = filter_input(INPUT_GET, 'lat1', FILTER_VALIDATE_FLOAT);
$lon1 = filter_input(INPUT_GET, 'lon1', FILTER_VALIDATE_FLOAT);
$lat2 = filter_input(INPUT_GET, 'lat2', FILTER_VALIDATE_FLOAT);
$lon2 = filter_input(INPUT_GET, 'lon2', FILTER_VALIDATE_FLOAT);

if ($lat1 === false || $lon1 === false || $lat2 === false || $lon2 === false
    || $lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan coordenadas']);
    exit;
}

$errorSpain = assert_route_in_spain((float) $lat1, (float) $lon1, (float) $lat2, (float) $lon2);
if ($errorSpain !== null) {
    http_response_code(403);
    echo json_encode(['error' => $errorSpain]);
    exit;
}

if (LOCATIONIQ_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'LOCATIONIQ_KEY no configurada']);
    exit;
}

$p1 = round((float) $lon1, 6) . ',' . round((float) $lat1, 6);
$p2 = round((float) $lon2, 6) . ',' . round((float) $lat2, 6);

$url = 'https://eu1.locationiq.com/v1/directions/driving/' . $p1 . ';' . $p2
    . '?key=' . urlencode(LOCATIONIQ_KEY)
    . '&format=json&overview=false';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión: ' . $curlError]);
    exit;
}

if ($status !== 200) {
    http_response_code($status);
    echo json_encode([
        'error' => 'API Error ' . $status,
        'server_msg' => $body,
        'nota' => 'Si sale InvalidQuery, la API no encuentra carretera entre esos puntos',
    ]);
    exit;
}

$data = json_decode($body, true);
if (!empty($data['routes'][0]['distance'])) {
    $km = round(((float) $data['routes'][0]['distance']) / 1000, 1);
    echo json_encode(['km' => $km]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'No se encontró ruta']);
