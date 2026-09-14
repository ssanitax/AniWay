<?php
declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('USERS_FILE', DATA_PATH . '/users.json');
define('TRIPS_DIR', DATA_PATH . '/trips');
define('MAX_TRIPS_PER_USER', 10);

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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Crea carpetas/archivos de datos si es posible.
 * No lanza Warning si el servidor web no tiene permisos de escritura.
 */
function ensure_data_storage(): bool
{
    if (!is_dir(DATA_PATH) && !@mkdir(DATA_PATH, 0775, true) && !is_dir(DATA_PATH)) {
        return false;
    }
    if (!is_dir(TRIPS_DIR) && !@mkdir(TRIPS_DIR, 0775, true) && !is_dir(TRIPS_DIR)) {
        return false;
    }

    if (!file_exists(USERS_FILE)) {
        if (!is_writable(DATA_PATH)) {
            return false;
        }
        $ok = @file_put_contents(
            USERS_FILE,
            json_encode(['users' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        if ($ok === false) {
            return false;
        }
    }

    return is_writable(DATA_PATH) && is_writable(TRIPS_DIR) && is_writable(USERS_FILE);
}

if (!ensure_data_storage()) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>AniWay — Permisos</title></head><body style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5">';
    echo '<h1>Faltan permisos de escritura</h1>';
    echo '<p>PHP no puede escribir en <code>data/</code>. En el servidor ejecuta:</p>';
    echo '<pre style="background:#111;color:#eee;padding:14px;border-radius:8px;overflow:auto">';
    echo "sudo chown -R www-data:www-data /var/www/html/AniWay/data\n";
    echo "sudo chmod -R 775 /var/www/html/AniWay/data\n";
    echo '</pre>';
    echo '<p>Si tu usuario de Apache/Nginx no es <code>www-data</code>, sustituye por el correcto (<code>apache</code>, <code>nginx</code>, etc.).</p>';
    echo '</body></html>';
    exit;
}
