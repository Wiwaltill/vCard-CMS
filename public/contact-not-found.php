<?php

require_once 'includes/functions.php';

$config = get_config();

http_response_code(404);

$contactEmail = trim($config['contact_email'] ?? '');
$mailto = $contactEmail !== '' ? 'mailto:' . $contactEmail : '';

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kontakt nicht gefunden | <?= h($config['company_name']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --company-color: <?= h($config['company_color']) ?>;
}

html,
body {
    min-height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: #f8f9fa;
}

.btn-company {
    background: var(--company-color);
    border-color: var(--company-color);
    color: #fff;
}

.btn-company:hover {
    filter: brightness(.94);
    color: #fff;
}
</style>
</head>

<body>

<main class="container py-5">
<div class="card shadow-sm mx-auto" style="max-width: 560px;">
<div class="card-body text-center p-5">

<?php if (!empty($config['company_logo'])): ?>
<img src="<?= h($config['company_logo']) ?>" alt="<?= h($config['company_name']) ?>" style="max-height: 70px; max-width: 220px;" class="mb-4">
<?php endif; ?>

<h1 class="h3 mb-3">Kontakt nicht gefunden</h1>

<p class="text-muted mb-4">
Der gesuchte Kontakt konnte nicht gefunden werden.
</p>

<?php if ($mailto): ?>
<a href="<?= h($mailto) ?>" class="btn btn-company">
Schreiben Sie uns
</a>
<?php endif; ?>

</div>
</div>
</main>

</body>
</html>
