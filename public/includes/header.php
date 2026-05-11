<?php
require_once __DIR__ . '/functions.php';
$config = get_config();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<title><?= h($config['company_name']) ?> Admin</title>

<style>
:root {
    --company-color: <?= h($config['company_color']) ?>;
}

html,
body {
    height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

main {
    flex: 1 0 auto;
}

footer {
    flex-shrink: 0;
}

.btn-primary,
.bg-primary {
    background-color: var(--company-color) !important;
    border-color: var(--company-color) !important;
}

.navbar {
    background-color: var(--company-color) !important;
}
</style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark">
<div class="container">

<a class="navbar-brand d-flex align-items-center gap-2" href="<?= h($config['logo_link']) ?>" target="_blank" rel="noopener">
<?php if (!empty($config['company_logo'])): ?>
<img src="<?= h($config['company_logo']) ?>" height="40" alt="Logo">
<?php endif; ?>
<span><?= h($config['company_name']) ?></span>
</a>

<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="adminNavbar">
<ul class="navbar-nav ms-auto">

<li class="nav-item">
<a class="nav-link" href="/admin">
<i class="bi bi-people"></i> Kontakte
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/settings">
<i class="bi bi-gear"></i> Einstellungen
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/logout">
<i class="bi bi-box-arrow-right"></i> Logout
</a>
</li>

</ul>
</div>

</div>
</nav>

<main>
<div class="container py-4">
