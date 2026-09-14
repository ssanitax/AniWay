<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function point_in_spain(float $lat, float $lon): bool
{
    return $lat >= SPAIN_LAT_MIN
        && $lat <= SPAIN_LAT_MAX
        && $lon >= SPAIN_LON_MIN
        && $lon <= SPAIN_LON_MAX;
}

function assert_route_in_spain(float $lat1, float $lon1, float $lat2, float $lon2): ?string
{
    if (!point_in_spain($lat1, $lon1) || !point_in_spain($lat2, $lon2)) {
        return 'AniWay solo calcula rutas dentro de España.';
    }
    return null;
}
