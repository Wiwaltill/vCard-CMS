<?php

require_once 'includes/functions.php';
require_installed();

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
    require 'contact-not-found.php';
    exit;
}

$pageUrl = current_url();
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pageUrl);

$name = trim(($card['vorname'] ?? '') . ' ' . ($card['nachname'] ?? ''));
$email = contact_email($card, $config);

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Kontaktdaten austauschen">
<meta name="robots" content="noindex,nofollow,noarchive">

<title>Kontaktinformationen: <?= h($name) ?> | <?= h($config['company_name']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --company-color: <?= h($config['company_color']) ?>;
}

* {
    box-sizing: border-box;
}

html,
body {
    min-height: 100%;
}

body {
    margin: 0;
    min-height: 100vh;
    color: #111827;
    background:
        linear-gradient(rgba(255,255,255,.55), rgba(255,255,255,.55)),
        radial-gradient(circle at top left, rgba(0,0,0,.08), transparent 30%),
        linear-gradient(135deg, #f4f4f4, #ffffff);
    font-family: Arial, Helvetica, sans-serif;
}

.page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.site-header {
    position: sticky;
    top: 0;
    z-index: 50;
    height: 64px;
    border-bottom: 1px solid rgba(17,24,39,.1);
    background: rgba(255,255,255,.72);
    backdrop-filter: blur(6px);
}

.site-header-inner {
    max-width: 1120px;
    height: 64px;
    margin: 0 auto;
    padding: 0 16px;
    display: flex;
    align-items: center;
}

.logo {
    height: 40px;
    width: auto;
    max-width: 180px;
    object-fit: contain;
}

main {
    flex: 1;
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
    padding: 32px 16px;
    display: flex;
    align-items: center;
}

.contact-card {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 32px;
    padding: 32px;
    border: 1px solid rgba(17,24,39,.12);
    border-radius: 10px;
    background: rgba(255,255,255,.76);
    box-shadow: 0 12px 30px rgba(0,0,0,.12);
}

.contact-content {
    order: 2;
    text-align: center;
}

.photo-wrap {
    order: 1;
    display: flex;
    justify-content: center;
}

.employee-photo {
    width: 192px;
    height: 192px;
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,.16), 0 8px 10px -6px rgba(0,0,0,.14);
}

.employee-placeholder {
    width: 192px;
    height: 192px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    background: var(--company-color);
    font-size: 52px;
    font-weight: 700;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,.16), 0 8px 10px -6px rgba(0,0,0,.14);
}

h1 {
    margin: 0;
    font-size: 2.25rem;
    line-height: 1.15;
    font-weight: 700;
}

.position {
    margin-top: 6px;
    font-size: 1.25rem;
}

.contact-links {
    margin-top: 24px;
    display: grid;
    gap: 10px;
}

.contact-link {
    color: inherit;
    text-decoration: none;
}

.contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
}

.contact-icon {
    width: 18px;
    height: 18px;
    display: inline-block;
    margin-right: 12px;
    vertical-align: -3px;
    color: var(--company-color);
}

.actions {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
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

.site-footer {
    padding: 18px 16px;
    text-align: center;
    font-size: .9rem;
    color: #4b5563;
}

.site-footer a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 3px;
}

@media (min-width: 768px) {
    main {
        padding: 48px 24px;
    }

    .contact-card {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .contact-content {
        order: 1;
        text-align: left;
    }

    .photo-wrap {
        order: 2;
        flex: 0 0 auto;
    }

    .actions {
        justify-content: flex-start;
    }
}
</style>
</head>

<body>

<div class="page">

<header class="site-header">
<div class="site-header-inner">
<?php if (!empty($config['company_logo'])): ?>
<a href="<?= h($config['logo_link']) ?>" target="_blank" rel="noopener">
<img src="<?= h($config['company_logo']) ?>" alt="<?= h($config['company_name']) ?>" class="logo">
</a>
<?php else: ?>
<strong><?= h($config['company_name']) ?></strong>
<?php endif; ?>
</div>
</header>

<main>
<section class="contact-card">

<div class="contact-content">
<h1><?= h($name) ?></h1>

<?php if (!empty($card['position'])): ?>
<div class="position"><?= h($card['position']) ?></div>
<?php endif; ?>

<div class="contact-links">

<?php if (!empty($card['telefon'])): ?>
<a class="contact-link" href="tel:<?= h($card['telefon']) ?>">
<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
<path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 0 1 2.43 8.326 13.019 13.019 0 0 1 2 5V3.5Z" clip-rule="evenodd"></path>
</svg><?= h($card['telefon']) ?>
</a>
<?php endif; ?>

<?php if (!empty($email)): ?>
<a class="contact-link" href="mailto:<?= h($email) ?>">
<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
<path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"></path>
<path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"></path>
</svg><?= h($email) ?>
</a>
<?php endif; ?>

</div>

<div class="actions">
<a href="/<?= h($card['id']) ?>/vcard" class="btn btn-company">
Kontakt speichern
</a>

<button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#qrModal">
QR-Code anzeigen
</button>
</div>
</div>

<div class="photo-wrap">
<?php if (!empty($card['bild'])): ?>
<img src="<?= h($card['bild']) ?>" alt="<?= h($name) ?>" class="employee-photo">
<?php else: ?>
<div class="employee-placeholder">
<?= h(strtoupper(substr($card['vorname'] ?? '', 0, 1) . substr($card['nachname'] ?? '', 0, 1))) ?>
</div>
<?php endif; ?>
</div>

</section>
</main>

<footer class="site-footer">
<?php if (!empty($config['imprint_url'])): ?>
<a href="<?= h($config['imprint_url']) ?>" target="_blank" rel="noopener">Impressum</a>
<?php endif; ?>

<?php if (!empty($config['imprint_url']) && !empty($config['privacy_url'])): ?>
<span> · </span>
<?php endif; ?>

<?php if (!empty($config['privacy_url'])): ?>
<a href="<?= h($config['privacy_url']) ?>" target="_blank" rel="noopener">Datenschutz</a>
<?php endif; ?>
</footer>

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
