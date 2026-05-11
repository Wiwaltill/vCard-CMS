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

$pageUrl = contact_url($card);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pageUrl);
$lang = app_lang($config);
$theme = theme_name($config);
$mode = theme_mode($config);

$name = trim(($card['vorname'] ?? '') . ' ' . ($card['nachname'] ?? ''));
$email = contact_email($card, $config);

?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-theme="<?= h($theme) ?>" data-bs-theme="<?= h(initial_bs_theme($config)) ?>" data-bs-theme-mode="<?= h($mode) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Kontaktdaten austauschen">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="<?= h($config['company_color']) ?>">
<?php if (!empty($config['pwa_enabled'])): ?><link rel="manifest" href="/manifest.webmanifest?id=<?= h($card['id'] ?? '') ?>"><link rel="apple-touch-icon" href="/assets/icons/icon.svg"><?php endif; ?>

<title>Kontaktinformationen: <?= h($name) ?> | <?= h($config['company_name']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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
    color: var(--bs-body-color);
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
    background: rgba(var(--bs-body-bg-rgb), .72);
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
    background: rgba(var(--bs-body-bg-rgb), .76);
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

[data-bs-theme="dark"] .contact-icon {
    color: #fff;
}

.actions {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}

.btn-company {
    --bs-btn-color: #fff;
    --bs-btn-bg: var(--company-color);
    --bs-btn-border-color: var(--company-color);
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: color-mix(in srgb, var(--company-color) 90%, #000);
    --bs-btn-hover-border-color: color-mix(in srgb, var(--company-color) 85%, #000);
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: color-mix(in srgb, var(--company-color) 82%, #000);
    --bs-btn-active-border-color: color-mix(in srgb, var(--company-color) 78%, #000);
    --bs-btn-disabled-color: #fff;
    --bs-btn-disabled-bg: var(--company-color);
    --bs-btn-disabled-border-color: var(--company-color);
}

[data-bs-theme="light"] .contact-card.cover .btn-company,
[data-bs-theme="light"] .cover .btn-company {
    --bs-btn-color: var(--company-color);
    --bs-btn-bg: #fff;
    --bs-btn-border-color: #fff;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: var(--company-color);
    --bs-btn-hover-border-color: #fff;
}

[data-bs-theme="dark"] .contact-card.cover .btn-company,
[data-bs-theme="dark"] .cover .btn-company {
    --bs-btn-color: #fff;
    --bs-btn-bg: transparent;
    --bs-btn-border-color: #fff;
    --bs-btn-hover-color: var(--bs-body-bg);
    --bs-btn-hover-bg: #fff;
    --bs-btn-hover-border-color: #fff;
}

.site-footer {
    padding: 18px 16px;
    text-align: center;
    font-size: .9rem;
    color: var(--bs-secondary-color);
}

.site-footer a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 3px;
}
[data-bs-theme="dark"] body {
    background: linear-gradient(135deg, var(--bs-body-bg), var(--bs-tertiary-bg));
}

[data-bs-theme="dark"] .site-header {
    background: rgba(var(--bs-body-bg-rgb), .82);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .contact-card {
    background: rgba(var(--bs-body-bg-rgb), .72);
    color: var(--bs-body-color);
}

html[data-theme="glass"] body {
    background:
        radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--company-color) 34%, transparent), transparent 32%),
        radial-gradient(circle at 80% 0%, rgba(255,255,255,.35), transparent 30%),
        linear-gradient(135deg, color-mix(in srgb, var(--company-color) 18%, var(--bs-body-bg)), var(--bs-tertiary-bg));
}
html[data-theme="glass"] .site-header {
    background: rgba(var(--bs-body-bg-rgb), .48);
    backdrop-filter: blur(18px) saturate(160%);
}
html[data-theme="glass"] .contact-card {
    background: rgba(var(--bs-body-bg-rgb), .46);
    color: var(--bs-body-color);
    border-color: rgba(var(--bs-body-color-rgb), .18);
    box-shadow: 0 24px 70px rgba(0,0,0,.20), inset 0 1px 0 rgba(255,255,255,.22);
    backdrop-filter: blur(24px) saturate(170%);
}
html[data-theme="glass"] .employee-photo,
html[data-theme="glass"] .employee-placeholder {
    box-shadow: 0 22px 48px rgba(0,0,0,.24);
}

html[data-theme="minimal"] body { background: var(--bs-body-bg); }
html[data-theme="minimal"] .contact-card { box-shadow:none; border-radius:0; }
.theme-switch { margin-left:auto; display:flex; gap:8px; align-items:center; }

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
<div class="theme-switch">
<a class="btn btn-sm btn-outline-secondary" href="?lang=<?= $lang === 'de' ? 'en' : 'de' ?>"><?= strtoupper($lang === 'de' ? 'en' : 'de') ?></a>
<div class="dropdown">
<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="themeModeToggle"><i class="bi bi-circle-half"></i> <span data-theme-mode-label>Auto</span></button>
<ul class="dropdown-menu dropdown-menu-end">
<li><button class="dropdown-item" type="button" data-theme-value="auto"><i class="bi bi-circle-half me-2"></i>Auto</button></li>
<li><button class="dropdown-item" type="button" data-theme-value="light"><i class="bi bi-sun me-2"></i>Light</button></li>
<li><button class="dropdown-item" type="button" data-theme-value="dark"><i class="bi bi-moon-stars me-2"></i>Dark</button></li>
</ul>
</div>
</div>
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

<?php foreach (data_types($config, true) as $type): ?>
<?php $value = data_type_value($card, $type, $config); ?>
<?php if ($value === ''): ?>
<?php continue; ?>
<?php endif; ?>
<?php $href = data_type_href($type, $value); ?>

<?php if ($href !== ''): ?>
<a class="contact-link" href="<?= h($href) ?>" <?= data_type_opens_new_tab($type) ? 'target="_blank" rel="noopener"' : '' ?>>
<?= data_type_svg_icon($type) ?><?= h($value) ?>
</a>
<?php else: ?>
<span class="contact-link">
<?= data_type_svg_icon($type) ?><?= h($value) ?>
</span>
<?php endif; ?>

<?php endforeach; ?>

</div>

<div class="actions">
<a href="/<?= h($card['id']) ?>/vcard" class="btn btn-company">
<?= h(t('save_contact', $config)) ?>
</a>

<button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#qrModal">
<?= h(t('show_qr', $config)) ?>
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
<a href="<?= h($config['imprint_url']) ?>" target="_blank" rel="noopener"><?= h(t('imprint', $config)) ?></a>
<?php endif; ?>

<?php if (!empty($config['imprint_url']) && !empty($config['privacy_url'])): ?>
<span> · </span>
<?php endif; ?>

<?php if (!empty($config['privacy_url'])): ?>
<a href="<?= h($config['privacy_url']) ?>" target="_blank" rel="noopener"><?= h(t('privacy', $config)) ?></a>
<?php endif; ?>
</footer>

</div>

<div class="modal fade" id="qrModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title"><?= h(t('qr_code', $config)) ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body text-center">
<img src="<?= h($qrUrl) ?>" alt="QR-Code" class="img-fluid mb-3">
<p class="small text-body-secondary"><?= h($pageUrl) ?></p>
<div class="d-flex justify-content-center gap-2"><a class="btn btn-sm btn-outline-primary" href="/qr/<?= h($card['id']) ?>/png" download="qr-<?= h($card['id']) ?>.png"><?= h(t('download_png', $config)) ?></a><a class="btn btn-sm btn-outline-primary" href="/qr/<?= h($card['id']) ?>/svg" download="qr-<?= h($card['id']) ?>.svg"><?= h(t('download_svg', $config)) ?></a></div>
</div>

</div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
 const html=document.documentElement, key='vcard-theme-mode';
 const system=window.matchMedia('(prefers-color-scheme: dark)');
 const defaultMode=html.getAttribute('data-bs-theme-mode') || 'auto';
 const label=document.querySelector('[data-theme-mode-label]');
 function resolved(mode){ return mode === 'auto' ? (system.matches ? 'dark' : 'light') : mode; }
 function apply(mode,persist){
   html.setAttribute('data-bs-theme-mode', mode);
   html.setAttribute('data-bs-theme', resolved(mode));
   if(label) label.textContent = mode.charAt(0).toUpperCase()+mode.slice(1);
   document.querySelectorAll('[data-theme-value]').forEach(el=>el.classList.toggle('active', el.dataset.themeValue===mode));
   if(persist) localStorage.setItem(key, mode);
 }
 apply(localStorage.getItem(key)||defaultMode,false);
 system.addEventListener('change',()=>{ if((localStorage.getItem(key)||defaultMode)==='auto') apply('auto',false); });
 document.querySelectorAll('[data-theme-value]').forEach(el=>el.addEventListener('click',()=>apply(el.dataset.themeValue,true)));

 if('serviceWorker' in navigator && <?= !empty($config['pwa_enabled']) ? 'true' : 'false' ?>) {
   window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js', {scope:'/'}).catch(console.error));
 }
})();
</script>
</body>
</html>
