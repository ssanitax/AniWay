<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/spain.php';

header('Content-Type: application/json; charset=utf-8');

function ruta_parecida(array $mejor, array $otra): bool
{
    if ($mejor['distance'] <= 0 || $mejor['duration'] <= 0) {
        return false;
    }
    $mismoRecorrido = abs($otra['distance'] - $mejor['distance']) / $mejor['distance'] < 0.02
        && abs($otra['duration'] - $mejor['duration']) / $mejor['duration'] < 0.02;
    if ($mismoRecorrido) {
        return false;
    }
    return $otra['duration'] <= $mejor['duration'] * 1.25
        || $otra['distance'] <= $mejor['distance'] * 1.25;
}

function paso_rapido(array $step): bool
{
    foreach ($step['intersections'] ?? [] as $inter) {
        if (!is_array($inter)) {
            continue;
        }
        foreach ($inter['classes'] ?? [] as $clase) {
            if ($clase === 'motorway' || $clase === 'trunk') {
                return true;
            }
        }
    }
    $ref = (string) ($step['ref'] ?? '');
    return (bool) preg_match('/\b(AP|A|R)-\d+/', $ref);
}

function es_giro_urbano(array $step): bool
{
    if (paso_rapido($step)) {
        return false;
    }
    $tipo = (string) ($step['maneuver']['type'] ?? '');
    $mod = (string) ($step['maneuver']['modifier'] ?? '');
    if (in_array($tipo, ['depart', 'arrive', 'continue', 'new name', 'notification', 'merge', 'on ramp', 'off ramp'], true)) {
        return false;
    }
    $suave = $mod === '' || $mod === 'straight' || str_starts_with($mod, 'slight');
    if ($suave) {
        return false;
    }
    return true;
}

function etiqueta_via(float $ratio): string
{
    if ($ratio >= 0.45) {
        return 'autovía';
    }
    if ($ratio >= 0.20) {
        return 'mixta';
    }
    return 'urbana';
}

function json_error(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
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
        . '&overview=full&geometries=geojson&steps=true&alternatives=true';

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
    $rutas = [];
    foreach (is_array($data['routes'] ?? null) ? $data['routes'] : [] as $route) {
        if (!is_array($route) || empty($route['distance']) || empty($route['duration'])) {
            continue;
        }
        $geometry = $route['geometry']['coordinates'] ?? [];
        $metros = (float) $route['distance'];
        $segundos = (float) $route['duration'];
        $autovia = 0.0;
        $giros = 0;
        foreach (is_array($route['legs'] ?? null) ? $route['legs'] : [] as $leg) {
            foreach (is_array($leg['steps'] ?? null) ? $leg['steps'] : [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                if (paso_rapido($step)) {
                    $autovia += (float) ($step['distance'] ?? 0);
                } elseif (es_giro_urbano($step)) {
                    $giros++;
                }
            }
        }
        $ratio = $metros > 0 ? min(1.0, $autovia / $metros) : 0.0;
        $rutas[] = [
            'km' => round($metros / 1000, 1),
            'min' => max(1, (int) round($segundos / 60)),
            'via' => etiqueta_via($ratio),
            'distance' => $metros,
            'duration' => $segundos,
            'score' => $segundos * (1 - 0.25 * $ratio) + $giros * 12,
            'geometry' => is_array($geometry) ? $geometry : [],
        ];
    }

    if ($rutas === []) {
        json_error(404, 'No se encontró ruta');
    }

    usort($rutas, static function (array $a, array $b): int {
        $porNota = $a['score'] <=> $b['score'];
        if ($porNota !== 0) {
            return $porNota;
        }
        $porTiempo = $a['duration'] <=> $b['duration'];
        return $porTiempo !== 0 ? $porTiempo : ($a['distance'] <=> $b['distance']);
    });

    $elegidas = [$rutas[0]];
    if (isset($rutas[1]) && ruta_parecida($rutas[0], $rutas[1])) {
        $elegidas[] = $rutas[1];
    }

    $salida = array_map(static function (array $ruta): array {
        return [
            'km' => $ruta['km'],
            'min' => $ruta['min'],
            'via' => $ruta['via'],
            'geometry' => $ruta['geometry'],
        ];
    }, $elegidas);

    echo json_encode(['routes' => $salida], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    json_error(500, 'Error interno: ' . $e->getMessage());
}
