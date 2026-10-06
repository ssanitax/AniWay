<?php
declare(strict_types=1);

define('ROOT_PATH', __DIR__);

/**
 * Carga variables desde .env (KEY=VALUE) si existe.
 * Apache suele no exponer variables de entorno a PHP.
 */
function load_dotenv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name === '') {
            continue;
        }
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

load_dotenv(ROOT_PATH . '/.env');

// Prioridad: entorno → .env → valor vacío (rellena LOCATIONIQ_KEY en .env)
$locationIqKey = getenv('LOCATIONIQ_KEY') ?: ($_ENV['LOCATIONIQ_KEY'] ?? '');
define('LOCATIONIQ_KEY', is_string($locationIqKey) ? $locationIqKey : '');

// Bounding box España (península, Baleares y Canarias)
define('SPAIN_LAT_MIN', 27.6);
define('SPAIN_LAT_MAX', 43.85);
define('SPAIN_LON_MIN', -18.2);
define('SPAIN_LON_MAX', 4.4);
