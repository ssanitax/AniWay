<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/spain.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function http_get_json(string $url, int $timeout = 12): ?array
{
    $body = false;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AniWay/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "Accept: application/json\r\nUser-Agent: AniWay/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if ($body === false || $status !== 200) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function normalizar_texto(string $s): string
{
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ò' => 'o',
    ]);
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
    return trim($s);
}

function clave_via(string $s): string
{
    $s = normalizar_texto($s);
    $s = preg_replace('/^(calle|avenida|avda|av|plaza|paseo|camino|carretera|travesia|ronda|glorieta|via|urbanizacion|poligono)\s+(de\s+|del\s+|la\s+|las\s+|los\s+|el\s+)?/u', '', $s) ?? $s;
    return trim($s);
}

function tokens_via(string $s): array
{
    $partes = preg_split('/\s+/', clave_via($s)) ?: [];
    $out = [];
    foreach ($partes as $p) {
        if ($p === '' || strlen($p) <= 2 || in_array($p, ['de', 'del', 'las', 'los', 'el', 'la'], true)) {
            continue;
        }
        $out[] = $p;
    }
    return $out;
}

function via_encaja(string $pedida, string $real): bool
{
    $a = tokens_via($pedida);
    $b = tokens_via($real);
    if ($a === [] || $b === []) {
        return false;
    }
    foreach ($a as $t) {
        if (!in_array($t, $b, true)) {
            return false;
        }
    }
    return true;
}

function lugar_encaja(string $pedido, string $ciudad): bool
{
    $pedido = normalizar_texto($pedido);
    $ciudad = normalizar_texto($ciudad);
    if ($pedido === '') {
        return true;
    }
    if ($ciudad === '') {
        return false;
    }
    return $ciudad === $pedido
        || preg_match('/(?:^|\s)' . preg_quote($pedido, '/') . '(?:\s|$)/', $ciudad) === 1;
}

function partes_consulta(string $q): array
{
    $limpio = trim($q);
    $trozos = preg_split('/\s*,\s*/u', $limpio) ?: [];
    $numero = '';
    $via = $limpio;
    $lugar = '';

    if (count($trozos) >= 2) {
        $idxNum = null;
        foreach ($trozos as $i => $trozo) {
            if (preg_match('/^\d+\s*(?:bis|[a-zA-Z])?$/iu', trim($trozo)) === 1) {
                $idxNum = $i;
            }
        }
        if ($idxNum !== null) {
            $numero = strtolower(str_replace(' ', '', $trozos[$idxNum]));
            $via = trim(implode(' ', array_slice($trozos, 0, $idxNum)));
            $lugar = trim(implode(' ', array_slice($trozos, $idxNum + 1)));
        } else {
            $via = trim($trozos[0]);
            $lugar = trim(implode(' ', array_slice($trozos, 1)));
            if (preg_match('/(?:^|[\s,])(\d+)(?:\s*(bis)|([a-zA-Z]))?(?=[\s,]|$)/iu', $via, $m, PREG_OFFSET_CAPTURE) === 1) {
                $extra = strtolower(($m[2][0] ?? '') !== '' ? $m[2][0] : ($m[3][0] ?? ''));
                $numero = $m[1][0] . ($extra === 'bis' ? 'bis' : $extra);
                $via = trim(substr($via, 0, $m[0][1]), " ,");
            }
        }
    } elseif (preg_match_all('/(?:^|[\s,])(\d+)(?:\s*(bis)|([a-zA-Z]))?(?=[\s,]|$)/iu', $limpio, $m, PREG_OFFSET_CAPTURE) > 0) {
        $elegido = null;
        foreach ($m[0] as $k => $full) {
            $despues = substr($limpio, $full[1] + strlen($full[0]));
            if (preg_match('/^\s+(de|del|la|las|los|el)\b/iu', $despues) === 1) {
                continue;
            }
            $elegido = $k;
        }
        if ($elegido !== null) {
            $extra = strtolower(($m[2][$elegido][0] ?? '') !== '' ? $m[2][$elegido][0] : ($m[3][$elegido][0] ?? ''));
            $numero = $m[1][$elegido][0] . ($extra === 'bis' ? 'bis' : $extra);
            $via = trim(substr($limpio, 0, $m[0][$elegido][1]), " ,");
            $lugar = trim(substr($limpio, $m[0][$elegido][1] + strlen($m[0][$elegido][0])), " ,");
        }
    }

    return ['via' => $via, 'numero' => $numero, 'lugar' => $lugar];
}

function titulo_frase(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    if ($s === '') {
        return '';
    }
    $parts = preg_split('/\s+/u', function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s)) ?: [];
    $out = [];
    foreach ($parts as $i => $p) {
        if ($i > 0 && in_array($p, ['de', 'del', 'la', 'las', 'los', 'el', 'y'], true)) {
            $out[] = $p;
            continue;
        }
        $out[] = function_exists('mb_strtoupper')
            ? mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8')
            : ucfirst($p);
    }
    return implode(' ', $out);
}

function etiqueta_cartociudad(array $row): string
{
    $tip = trim((string) ($row['tip_via'] ?? ''));
    $addr = trim((string) ($row['address'] ?? ''));
    $muni = trim((string) ($row['muni'] ?? ''));
    $poblacion = trim((string) ($row['poblacion'] ?? ''));
    $ciudad = $muni !== '' ? $muni : $poblacion;
    $portal = $row['portalNumber'] ?? null;
    $type = (string) ($row['type'] ?? '');

    // Quitar ciudad al final si viene en address: "CALLE X 5, Linares"
    $viaBruta = $addr;
    if ($ciudad !== '' && preg_match('/,\s*' . preg_quote($ciudad, '/') . '\s*$/iu', $viaBruta) === 1) {
        $viaBruta = trim(preg_replace('/,\s*' . preg_quote($ciudad, '/') . '\s*$/iu', '', $viaBruta) ?? $viaBruta);
    }

    $num = '';
    if ($portal !== null && $portal !== '') {
        $num = is_numeric($portal) ? (string) (int) $portal : (string) $portal;
        $viaBruta = trim(preg_replace('/\s+\d+\s*$/u', '', $viaBruta) ?? $viaBruta);
    } elseif (preg_match('/\s+(\d+)\s*$/u', $viaBruta, $m) === 1) {
        $num = $m[1];
        $viaBruta = trim(substr($viaBruta, 0, -strlen($m[0])));
    }

    // Si address ya incluye el tipo de vía, no anteponer tip_via.
    $normVia = normalizar_texto($viaBruta);
    $normTip = normalizar_texto($tip);
    if ($normTip !== '' && !preg_match('/^' . preg_quote($normTip, '/') . '\b/u', $normVia)) {
        $viaBruta = trim($tip . ' ' . $viaBruta);
    }

    $via = titulo_frase($viaBruta);
    if ($via === '') {
        return $ciudad !== '' ? $ciudad : $addr;
    }
    if ($num !== '' && ($type === 'portal' || $portal !== null)) {
        return $ciudad !== '' ? $via . ' ' . $num . ', ' . $ciudad : $via . ' ' . $num;
    }
    return $ciudad !== '' ? $via . ', ' . $ciudad : $via;
}

function cartociudad_find(string $id, string $type, ?string $portal = null): ?array
{
    $url = 'https://www.cartociudad.es/geocoder/api/geocoder/find?id=' . rawurlencode($id)
        . '&type=' . rawurlencode($type);
    if ($portal !== null && $portal !== '') {
        $url .= '&portal=' . rawurlencode($portal);
    }
    $data = http_get_json($url, 15);
    return is_array($data) && isset($data['lat'], $data['lng']) ? $data : null;
}

function resultados_cartociudad(string $q): array
{
    $partes = partes_consulta($q);
    $pedido = $partes['numero'];
    $viaPedida = $partes['via'];
    $lugarPedido = $partes['lugar'];

    $url = 'https://www.cartociudad.es/geocoder/api/geocoder/candidates?q=' . rawurlencode($q) . '&limit=12';
    $data = http_get_json($url, 15);
    if (!is_array($data)) {
        return [];
    }

    $mejores = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = (string) ($row['type'] ?? '');
        if (!in_array($type, ['portal', 'callejero'], true)) {
            continue;
        }

        $addr = (string) ($row['address'] ?? '');
        $muni = (string) ($row['muni'] ?? $row['poblacion'] ?? '');
        $portal = $row['portalNumber'] ?? null;
        $portalStr = $portal === null || $portal === '' ? '' : strtolower((string) (is_numeric($portal) ? (int) $portal : $portal));

        if ($pedido !== '' && $type === 'portal' && $portalStr !== '' && $portalStr !== $pedido) {
            continue;
        }
        if ($pedido !== '' && $type === 'callejero') {
            // Preferir portales exactos; la calle sola no sirve si pedimos número.
            continue;
        }
        if ($viaPedida !== '' && !via_encaja($viaPedida, $addr)) {
            continue;
        }
        if ($lugarPedido !== '' && !lugar_encaja($lugarPedido, $muni)) {
            continue;
        }

        $lat = isset($row['lat']) ? (float) $row['lat'] : 0.0;
        $lng = isset($row['lng']) ? (float) $row['lng'] : 0.0;
        if (($lat == 0.0 && $lng == 0.0) || !point_in_spain($lat, $lng)) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $encontrado = cartociudad_find($id, $type, $pedido !== '' ? $pedido : null);
            if ($encontrado === null) {
                continue;
            }
            $lat = (float) $encontrado['lat'];
            $lng = (float) $encontrado['lng'];
            if (!point_in_spain($lat, $lng)) {
                continue;
            }
            if (($encontrado['type'] ?? '') === 'portal' || isset($encontrado['portalNumber'])) {
                $row = array_merge($row, $encontrado);
            } else {
                $row['lat'] = $lat;
                $row['lng'] = $lng;
            }
        }

        $label = etiqueta_cartociudad($row);
        if ($pedido !== '' && $type === 'portal') {
            // Asegurar que la etiqueta conserve el número pedido.
            if (!preg_match('/\b' . preg_quote($pedido, '/') . '\b/i', $label)) {
                $base = preg_replace('/\s+\d+\s*(?:,|$)/u', '', $label) ?? $label;
                $label = rtrim($base, ' ,') . ' ' . $pedido . ($muni !== '' ? ', ' . $muni : '');
            }
        }

        $score = 0;
        if ($type === 'portal') {
            $score += 200;
        }
        if ($pedido !== '' && $portalStr === $pedido) {
            $score += 150;
        }
        if ($viaPedida !== '' && clave_via($viaPedida) === clave_via($addr)) {
            $score += 80;
        }
        if ($lugarPedido !== '' && normalizar_texto($muni) === normalizar_texto($lugarPedido)) {
            $score += 100;
        }

        $clave = $label !== '' ? normalizar_texto($label) : (round($lat, 5) . ',' . round($lng, 5));
        if (isset($mejores[$clave]) && $mejores[$clave]['score'] >= $score) {
            continue;
        }
        $mejores[$clave] = [
            'label' => $label,
            'lat' => $lat,
            'lng' => $lng,
            'score' => $score,
        ];
    }

    $results = array_values($mejores);
    usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $results = array_slice($results, 0, 6);

    return array_map(static function (array $item): array {
        unset($item['score']);
        return $item;
    }, $results);
}

function etiqueta_locationiq(array $item): string
{
    $addr = is_array($item['address'] ?? null) ? $item['address'] : [];
    $via = trim((string) (
        $addr['road']
        ?? $addr['pedestrian']
        ?? $addr['footway']
        ?? $addr['residential']
        ?? $addr['path']
        ?? ''
    ));
    $num = trim((string) ($addr['house_number'] ?? ''));
    $ciudad = trim((string) (
        $addr['city']
        ?? $addr['town']
        ?? $addr['village']
        ?? $addr['municipality']
        ?? ''
    ));

    if ($via !== '') {
        $linea = $num !== '' ? $via . ' ' . $num : $via;
        return $ciudad !== '' ? $linea . ', ' . $ciudad : $linea;
    }

    $name = trim((string) ($item['display_name'] ?? $item['display_place'] ?? ''));
    $parts = array_values(array_filter(
        array_map('trim', explode(',', $name)),
        static fn(string $part): bool => $part !== ''
    ));
    if (count($parts) > 3) {
        $parts = array_slice($parts, 0, 3);
    }
    return implode(', ', $parts);
}

function resultados_locationiq(string $q): array
{
    if (LOCATIONIQ_KEY === '') {
        return [];
    }
    $partes = partes_consulta($q);
    $pedido = $partes['numero'];
    $viaPedida = $partes['via'];
    $lugarPedido = $partes['lugar'];
    $consulta = rawurlencode($q);
    $base = 'https://us1.locationiq.com/v1/search?key=' . urlencode(LOCATIONIQ_KEY)
        . '&q=' . $consulta
        . '&format=json&addressdetails=1&dedupe=0&limit=10&countrycodes=es&accept-language=es&normalizecity=1';
    $data = http_get_json($base, 12);
    if (!is_array($data)) {
        return [];
    }

    $mejores = [];
    foreach ($data as $item) {
        if (!is_array($item) || !isset($item['lat'], $item['lon'])) {
            continue;
        }
        $lat = (float) $item['lat'];
        $lng = (float) $item['lon'];
        if (!point_in_spain($lat, $lng)) {
            continue;
        }
        $addr = is_array($item['address'] ?? null) ? $item['address'] : [];
        $num = strtolower(str_replace(' ', '', (string) ($addr['house_number'] ?? '')));
        $via = (string) ($addr['road'] ?? $addr['pedestrian'] ?? '');
        $ciudad = (string) ($addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? '');
        if ($pedido !== '' && $num !== '' && $num !== $pedido) {
            continue;
        }
        if ($viaPedida !== '' && $via !== '' && !via_encaja($viaPedida, $via)) {
            continue;
        }
        if ($lugarPedido !== '' && !lugar_encaja($lugarPedido, $ciudad)) {
            continue;
        }
        $label = etiqueta_locationiq($item);
        if ($pedido !== '' && $num === '' && $via !== '') {
            // Sin portal real: no fingir el número.
            continue;
        }
        $clave = normalizar_texto($label);
        $score = ($num === $pedido && $pedido !== '' ? 100 : 0) + (($item['class'] ?? '') === 'building' ? 40 : 0);
        if (isset($mejores[$clave]) && $mejores[$clave]['score'] >= $score) {
            continue;
        }
        $mejores[$clave] = [
            'label' => $label,
            'lat' => $lat,
            'lng' => $lng,
            'score' => $score,
        ];
    }

    $results = array_values($mejores);
    usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    return array_map(static function (array $item): array {
        unset($item['score']);
        return $item;
    }, array_slice($results, 0, 6));
}

try {
    $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
    $lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
    if ($lat !== false && $lat !== null && $lon !== false && $lon !== null) {
        if (!point_in_spain((float) $lat, (float) $lon)) {
            json_error(403, 'AniWay solo funciona dentro de España.');
        }

        // Reverse: CartoCiudad primero, LocationIQ de respaldo.
        $rev = http_get_json(
            'https://www.cartociudad.es/geocoder/api/geocoder/reverseGeocode?lat='
            . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lon),
            10
        );
        if (is_array($rev) && !empty($rev['address'])) {
            $label = etiqueta_cartociudad($rev);
            if ($label !== '') {
                echo json_encode(['label' => $label], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if (LOCATIONIQ_KEY !== '') {
            $url = 'https://us1.locationiq.com/v1/reverse?key=' . urlencode(LOCATIONIQ_KEY)
                . '&lat=' . rawurlencode((string) $lat)
                . '&lon=' . rawurlencode((string) $lon)
                . '&format=json&accept-language=es&normalizeaddress=1';
            $data = http_get_json($url, 10);
            $label = is_array($data) ? etiqueta_locationiq($data) : '';
            echo json_encode(['label' => $label], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['label' => ''], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $largo = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
    if ($largo < 3) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $results = resultados_cartociudad($q);
    if ($results === []) {
        $results = resultados_locationiq($q);
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    json_error(500, 'Error interno');
}
