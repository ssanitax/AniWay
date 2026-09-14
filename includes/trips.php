<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function trips_file_for(string $userId): string
{
    return TRIPS_DIR . '/' . preg_replace('/[^a-zA-Z0-9]/', '', $userId) . '.json';
}

function load_trips(string $userId): array
{
    $file = trips_file_for($userId);
    if (!file_exists($file)) {
        return ['trips' => []];
    }
    $data = json_decode(file_get_contents($file) ?: '{"trips":[]}', true);
    return is_array($data) ? $data : ['trips' => []];
}

function save_trips(string $userId, array $data): bool
{
    return file_put_contents(
        trips_file_for($userId),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function get_user_trips(string $userId): array
{
    $data = load_trips($userId);
    return $data['trips'] ?? [];
}

function add_trip(string $userId, array $trip): array
{
    $data = load_trips($userId);
    $trip['id'] = bin2hex(random_bytes(6));
    $trip['saved_at'] = date('c');

    array_unshift($data['trips'], $trip);
    $data['trips'] = array_slice($data['trips'], 0, MAX_TRIPS_PER_USER);
    save_trips($userId, $data);

    return $trip;
}

function calcular_trayecto(array $grupos, float $costeTotal): array
{
    $gruposValidos = array_values(array_filter(
        $grupos,
        static fn(array $g): bool => !empty($g['amigos']) && ($g['dist'] ?? 0) > 0
    ));

    if ($gruposValidos === [] || $costeTotal <= 0) {
        return [];
    }

    $totalDist = array_sum(array_column($gruposValidos, 'dist'));
    $resultados = [];

    foreach ($gruposValidos as $g) {
        $costeGrupo = ($g['dist'] / $totalDist) * $costeTotal;
        $costeIndividual = $costeGrupo / count($g['amigos']);

        foreach ($g['amigos'] as $amigo) {
            $nombre = trim((string) $amigo);
            if (function_exists('mb_convert_case')) {
                $nombre = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
            } else {
                $nombre = ucwords(strtolower($nombre));
            }
            $resultados[] = [
                'nombre' => $nombre,
                'coste' => round($costeIndividual, 2),
                'km' => $g['dist'],
            ];
        }
    }

    return $resultados;
}

function calcular_reparto(array $payload): array
{
    $modo = $payload['modo_coste'] ?? 'total';
    $gruposIda = $payload['grupos_ida'] ?? [];
    $gruposVuelta = $payload['grupos_vuelta'] ?? [];

    if ($modo === 'total') {
        $costeTotal = (float) ($payload['coste_total'] ?? 0);
        $brutos = calcular_trayecto(array_merge($gruposIda, $gruposVuelta), $costeTotal);
    } else {
        $brutos = array_merge(
            calcular_trayecto($gruposIda, (float) ($payload['coste_ida'] ?? 0)),
            calcular_trayecto($gruposVuelta, (float) ($payload['coste_vuelta'] ?? 0))
        );
    }

    $totales = [];
    foreach ($brutos as $r) {
        $n = $r['nombre'];
        if (!isset($totales[$n])) {
            $totales[$n] = ['coste' => 0.0, 'km' => 0.0];
        }
        $totales[$n]['coste'] += $r['coste'];
        $totales[$n]['km'] += $r['km'];
    }

    $resultados = [];
    foreach ($totales as $nombre => $d) {
        $resultados[] = [
            'nombre' => $nombre,
            'coste' => round($d['coste'], 2),
            'km' => round($d['km'], 1),
        ];
    }

    usort($resultados, static fn($a, $b) => $b['coste'] <=> $a['coste']);
    return $resultados;
}
