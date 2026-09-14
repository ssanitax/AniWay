<?php
declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('USERS_FILE', DATA_PATH . '/users.json');
define('TRIPS_DIR', DATA_PATH . '/trips');
define('MAX_TRIPS_PER_USER', 10);

// LocationIQ (definir LOCATIONIQ_KEY en el entorno o aquí)
define('LOCATIONIQ_KEY', getenv('LOCATIONIQ_KEY') ?: '');

// Bounding box España (península, Baleares y Canarias)
define('SPAIN_LAT_MIN', 27.6);
define('SPAIN_LAT_MAX', 43.85);
define('SPAIN_LON_MIN', -18.2);
define('SPAIN_LON_MAX', 4.4);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}
if (!is_dir(TRIPS_DIR)) {
    mkdir(TRIPS_DIR, 0755, true);
}
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode(['users' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
