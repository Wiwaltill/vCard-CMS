<?php

require_once 'includes/functions.php';

header('X-Robots-Tag: noindex, nofollow', true);

$config = get_config();
$contacts = load_json('contacts.json', []);

$id = $_GET['id'] ?? '';

$card = null;

foreach ($contacts as $contact) {
    if (($contact['id'] ?? '') === $id) {
        $card = $contact;
        break;
    }
}

if (!$card) {
    http_response_code(404);
    exit('Nicht gefunden');
}

$pageUrl = current_url();
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pageUrl);

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<title><?= h($card['vorname']) ?> <?= h($card['nachname']) ?></title>

<style>
html,
body {
    min-height: 100%;
}

body {
    background: #f8f9fa;
}

.btn-primary {
    background-color: <?= h($config['company_color']) ?>;
    border-color: <?= h($config['company_color']) ?>;
}

.employee-photo {
    width: 140px;
    height: 140px;
    object-fit: cover;
}
</style>
</head>

<body>

<div class="container py-5">

<div class="card shadow mx-auto" style="max-width:500px;">

<div class="card-body text-center">

<?php if (!empty($config['company_logo'])): ?>
<a href="<?= h($config['logo_link']) ?>" target="_blank" rel="noopener">
<img src="<?= h($config['company_logo']) ?>" height="70" class="mb-3" alt="Logo">
</a>
<?php endif; ?>

<?php if (!empty($card['bild'])): ?>
<div>
<img src="<?= h($card['bild']) ?>" class="rounded-circle employee-photo mb-3" alt="<?= h($card['vorname']) ?> <?= h($card['nachname']) ?>">
</div>
<?php endif; ?>

<h1 class="h3">
<?= h($card['vorname']) ?> <?= h($card['nachname']) ?>
</h1>

<p class="text-muted">
<?= h($config['company_name']) ?>
</p>

<?php if (!empty($card['position'])): ?>
<p><?= h($card['position']) ?></p>
<?php endif; ?>

<div class="d-grid gap-2">

<a href="tel:<?= h($card['telefon']) ?>" class="btn btn-primary">
Anrufen
</a>

<a href="mailto:<?= h($card['email']) ?>" class="btn btn-outline-primary">
E-Mail
</a>

<a href="/<?= h($card['id']) ?>/vcard" class="btn btn-success">
Kontakt speichern
</a>

<button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#qrModal">
QR-Code anzeigen
</button>

</div>

</div>

</div>

</div>

<div class="modal fade" id="qrModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">QR-Code</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body text-center">
<img src="<?= h($qrUrl) ?>" alt="QR-Code" class="img-fluid mb-3">
<p class="small text-muted mb-0"><?= h($pageUrl) ?></p>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
