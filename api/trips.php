<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/trips.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

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

echo json_encode([
    'ok' => true,
    'resultados' => $resultados,
], JSON_UNESCAPED_UNICODE);
