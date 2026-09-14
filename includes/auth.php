<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function load_users(): array
{
    $raw = file_get_contents(USERS_FILE);
    $data = json_decode($raw ?: '{"users":[]}', true);
    return is_array($data) ? $data : ['users' => []];
}

function save_users(array $data): bool
{
    return file_put_contents(
        USERS_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function find_user(string $username): ?array
{
    $data = load_users();
    foreach ($data['users'] as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            return $user;
        }
    }
    return null;
}

function register_user(string $username, string $password): array
{
    $username = trim($username);
    if ($username === '' || strlen($username) < 3) {
        return ['ok' => false, 'error' => 'El usuario debe tener al menos 3 caracteres.'];
    }
    if (strlen($password) < 4) {
        return ['ok' => false, 'error' => 'La contraseña debe tener al menos 4 caracteres.'];
    }
    if (find_user($username) !== null) {
        return ['ok' => false, 'error' => 'Ese usuario ya existe.'];
    }

    $data = load_users();
    $user = [
        'id' => bin2hex(random_bytes(8)),
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
    ];
    $data['users'][] = $user;
    save_users($data);

    $tripsFile = TRIPS_DIR . '/' . $user['id'] . '.json';
    file_put_contents($tripsFile, json_encode(['trips' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return ['ok' => true, 'user' => $user];
}

function login_user(string $username, string $password): array
{
    $user = find_user(trim($username));
    if ($user === null || !password_verify($password, $user['password'])) {
        return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    return ['ok' => true, 'user' => $user];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
    ];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function require_login_api(): array
{
    $user = current_user();
    if ($user === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    return $user;
}
