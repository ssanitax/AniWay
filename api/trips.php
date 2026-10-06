<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/trips.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login_api();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode(['trips' => get_user_trips($user['id'])]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '{}', true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }

    $resultados = calcular_reparto($payload);
    if ($resultados === []) {
        http_response_code(400);
        echo json_encode(['error' => 'No hay datos suficientes para calcular el reparto']);
        exit;
    }

    $trip = add_trip($user['id'], [
        'modo_coste' => $payload['modo_coste'] ?? 'total',
        'coste_total' => (float) ($payload['coste_total'] ?? 0),
        'coste_ida' => (float) ($payload['coste_ida'] ?? 0),
        'coste_vuelta' => (float) ($payload['coste_vuelta'] ?? 0),
        'grupos_ida' => $payload['grupos_ida'] ?? [],
        'grupos_vuelta' => $payload['grupos_vuelta'] ?? [],
        'resultados' => $resultados,
    ]);

    echo json_encode([
        'ok' => true,
        'resultados' => $resultados,
        'trip' => $trip,
        'trips' => get_user_trips($user['id']),
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
