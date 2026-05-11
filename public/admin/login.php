<?php

require_once '../includes/functions.php';
require_installed();

$config = get_config();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $validUser = hash_equals($config['admin_user'] ?? 'admin', $user);
    $validPassword = false;

    if (!empty($config['admin_password_hash'])) {
        $validPassword = password_verify($password, $config['admin_password_hash']);
    }

    if ($validUser && $validPassword) {
        $duration = $remember ? time() + (60 * 60 * 24 * 30) : 0;

        setcookie(
            'kb_admin_login',
            hash('sha256', 'kb-events-admin'),
            [
                'expires' => $duration,
                'path' => '/admin',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        header('Location: /admin');
        exit;
    }

    $error = admin_t('login_failed', $config);
}

?>
<!DOCTYPE html>
<html lang="<?= h(admin_lang($config)) ?>">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<title><?= h(admin_t('login_title', $config)) ?></title>
</head>

<body class="bg-light">

<div class="container py-5">

<div class="card shadow mx-auto" style="max-width:420px;">
<div class="card-body">

<?php if (!empty($config['company_logo'])): ?>
<div class="text-center mb-4">
<a href="<?= h($config['logo_link']) ?>" target="_blank" rel="noopener">
<img src="<?= h($config['company_logo']) ?>" alt="Logo" style="max-height:90px; max-width:220px;">
</a>
</div>
<?php endif; ?>

<h1 class="h4 mb-4 text-center"><?= h(admin_t('login_title', $config)) ?></h1>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post">

<div class="mb-3">
<label class="form-label"><?= h(admin_t('username', $config)) ?></label>
<input type="text" name="username" class="form-control" required autocomplete="username">
</div>

<div class="mb-3">
<label class="form-label"><?= h(admin_t('password', $config)) ?></label>
<input type="password" name="password" class="form-control" required autocomplete="current-password">
</div>

<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" name="remember" id="remember">
<label class="form-check-label" for="remember">
<?= h(admin_t('remember_login', $config)) ?>
</label>
</div>

<button class="btn btn-primary w-100">
<?= h(admin_t('login', $config)) ?>
</button>

</form>

</div>
</div>

</div>

</body>
</html>
