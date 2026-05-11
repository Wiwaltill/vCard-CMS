<?php
require_once __DIR__ . '/functions.php';
$config = get_config();
?>
<!DOCTYPE html>
<html lang="<?= h(app_lang($config)) ?>" data-bs-theme="<?= h(initial_bs_theme($config)) ?>" data-bs-theme-mode="<?= h(theme_mode($config)) ?>">
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
.card.bg-white {
    background-color: var(--bs-body-bg) !important;
}

[data-bs-theme="dark"] .navbar {
    background-color: var(--company-color) !important;
}

[data-bs-theme="dark"] .btn-light {
    --bs-btn-color: var(--bs-body-color);
    --bs-btn-bg: var(--bs-tertiary-bg);
    --bs-btn-border-color: var(--bs-border-color);
}

pre, code {
    color: var(--bs-body-color);
}

pre {
    background-color: var(--bs-tertiary-bg);
    border-color: var(--bs-border-color);
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
<i class="bi bi-people"></i> <?= h(admin_t('contacts', $config)) ?>
</a>
</li>


<li class="nav-item">
<a class="nav-link" href="/admin/datatypes">
<i class="bi bi-list-check"></i> <?= h(admin_t('data_types', $config)) ?>
</a>
</li>


<li class="nav-item">
<a class="nav-link" href="/admin/import_export">
<i class="bi bi-filetype-csv"></i> CSV
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/api">
<i class="bi bi-braces"></i> API
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/backup">
<i class="bi bi-archive"></i> Backup
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/settings">
<i class="bi bi-gear"></i> <?= h(admin_t('settings', $config)) ?>
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/admin/logout">
<i class="bi bi-box-arrow-right"></i> <?= h(admin_t('logout', $config)) ?>
</a>
</li>

<li class="nav-item dropdown ms-lg-2">
<button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="themeModeToggle">
<i class="bi bi-circle-half me-1"></i><span data-theme-mode-label>Auto</span>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><button class="dropdown-item" type="button" data-theme-value="auto"><i class="bi bi-circle-half me-2"></i>Auto</button></li>
<li><button class="dropdown-item" type="button" data-theme-value="light"><i class="bi bi-sun me-2"></i>Light</button></li>
<li><button class="dropdown-item" type="button" data-theme-value="dark"><i class="bi bi-moon-stars me-2"></i>Dark</button></li>
</ul>
</li>

</ul>
</div>

</div>
</nav>

<main>
<div class="container py-4">
