<?php

require_once __DIR__ . '/../includes/functions.php';
start_admin_session();
require_installed();

$config = get_config();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    if (!is_string($user) || !is_string($password)) {
        http_response_code(422);
        exit('Invalid credentials.');
    }

    $validUser = hash_equals($config['admin_user'] ?? 'admin', $user);
    $validPassword = false;

    if (!empty($config['admin_password_hash'])) {
        $validPassword = password_verify($password, $config['admin_password_hash']);
    }

    if ($validUser && $validPassword) {
        start_admin_session();
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['admin_fingerprint'] = auth_fingerprint($config);
        $_SESSION['admin_expires'] = time() + 8 * 3600;
        $tokens = load_json('remember.json', []);
        $old = $_COOKIE['vcard_remember'] ?? '';
        if (is_string($old)) unset($tokens[hash('sha256', $old)]);
        $tokens = array_filter($tokens, static fn($entry) => ($entry['expires'] ?? 0) > time());
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + 30 * 86400;
            $tokens[hash('sha256', $token)] = ['expires' => $expires, 'fingerprint' => auth_fingerprint($config)];
            remember_cookie($token, $expires);
        } else remember_cookie('', time() - 3600);
        save_json('remember.json', $tokens);

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

                <form method="post"><?= csrf_field() ?>

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