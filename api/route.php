<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/spain.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_login_api();

    $lat1 = filter_input(INPUT_GET, 'lat1', FILTER_VALIDATE_FLOAT);
    $lon1 = filter_input(INPUT_GET, 'lon1', FILTER_VALIDATE_FLOAT);
    $lat2 = filter_input(INPUT_GET, 'lat2', FILTER_VALIDATE_FLOAT);
    $lon2 = filter_input(INPUT_GET, 'lon2', FILTER_VALIDATE_FLOAT);

    if ($lat1 === false || $lon1 === false || $lat2 === false || $lon2 === false
        || $lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        json_error(400, 'Faltan coordenadas');
    }

    $errorSpain = assert_route_in_spain((float) $lat1, (float) $lon1, (float) $lat2, (float) $lon2);
    if ($errorSpain !== null) {
        json_error(403, $errorSpain);
    }

    if (LOCATIONIQ_KEY === '') {
        json_error(500, 'LOCATIONIQ_KEY no configurada. Crea un archivo .env en la raíz con LOCATIONIQ_KEY=tu_clave');
    }

    $p1 = round((float) $lon1, 6) . ',' . round((float) $lat1, 6);
    $p2 = round((float) $lon2, 6) . ',' . round((float) $lat2, 6);

    $url = 'https://us1.locationiq.com/v1/directions/driving/' . $p1 . ';' . $p2
        . '?key=' . urlencode(LOCATIONIQ_KEY)
        . '&format=json&overview=false';

    $body = null;
    $status = 0;
    $transportError = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            json_error(500, 'No se pudo iniciar cURL');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transportError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            json_error(500, 'Error de conexión cURL: ' . $transportError);
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($body === false) {
            json_error(500, 'Error de conexión (allow_url_fopen/cURL no disponibles)');
        }
    }

    if ($status !== 200) {
        json_error($status >= 400 ? $status : 502, 'API Error ' . $status, [
            'server_msg' => $body,
            'nota' => 'Revisa la clave LocationIQ o si hay carretera entre esos puntos',
        ]);
    }

    $data = json_decode($body, true);
    if (!empty($data['routes'][0]['distance'])) {
        $km = round(((float) $data['routes'][0]['distance']) / 1000, 1);
        echo json_encode(['km' => $km]);
        exit;
    }

    json_error(404, 'No se encontró ruta');
} catch (Throwable $e) {
    json_error(500, 'Error interno: ' . $e->getMessage());
}
