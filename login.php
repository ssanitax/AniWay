<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login_user($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        header('Location: index.php');
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniWay — Entrar</title>
    <link rel="shortcut icon" href="static/aniway_favicon.ico">
    <link rel="stylesheet" href="static/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="header-container">
        <img src="static/logo.png" class="neon-logo" alt="AniWay">
        <h1>AniWay</h1>
    </div>
    <p class="auth-sub">Calculadora de gastos de viaje · España</p>

    <?php if ($error): ?>
        <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
        <label>USUARIO</label>
        <input type="text" name="username" required autofocus>

        <label>CONTRASEÑA</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn btn-submit">ENTRAR</button>
    </form>

    <p class="auth-switch">¿No tienes cuenta? <a href="register.php">Regístrate</a></p>
</div>
</body>
</html>
