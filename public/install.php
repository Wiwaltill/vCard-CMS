<?php

require_once 'includes/functions.php';

$config = get_config();
$contacts = load_json('contacts.json', []);

if (!empty($config['installed'])) {
    header('Location: /admin/login');
    exit;
}

$errors = [];
$success = false;

$dataDirWritable = is_writable(__DIR__ . '/../data');
$uploadsWritable = is_writable(__DIR__ . '/uploads');
$baseDomain = base_domain_from_host();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $companyColor = trim($_POST['company_color'] ?? '#0d6efd');
    $logoLink = trim($_POST['logo_link'] ?? '');
    $githubUrl = trim($_POST['github_url'] ?? '');
    $homeRedirectUrl = trim($_POST['home_redirect_url'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $imprintUrl = trim($_POST['imprint_url'] ?? '');
    $privacyUrl = trim($_POST['privacy_url'] ?? '');
    $emailDomain = strtolower(trim($_POST['email_domain'] ?? ''));
    $emailDomain = preg_replace('/^@/', '', $emailDomain);
    $emailPattern = $_POST['email_pattern'] ?? 'vorname.nachname';
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminPasswordRepeat = $_POST['admin_password_repeat'] ?? '';

    if ($companyName === '') {
        $errors[] = 'Bitte einen Firmennamen angeben.';
    }

    if ($emailDomain === '') {
        $errors[] = 'Bitte eine E-Mail-Domain angeben.';
    }

    if ($adminUser === '') {
        $errors[] = 'Bitte einen Admin-Benutzernamen angeben.';
    }

    if (strlen($adminPassword) < 8) {
        $errors[] = 'Das Admin-Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if ($adminPassword !== $adminPasswordRepeat) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if (!$dataDirWritable) {
        $errors[] = 'Der Ordner /data ist nicht beschreibbar.';
    }

    if (!$uploadsWritable) {
        $errors[] = 'Der Ordner /public/uploads ist nicht beschreibbar.';
    }

    if (!$errors) {
        $config['company_name'] = $companyName;
        $config['company_color'] = $companyColor;
        $config['logo_link'] = $logoLink;
        $config['github_url'] = $githubUrl;
        $config['home_redirect_url'] = $homeRedirectUrl;
        $config['contact_email'] = $contactEmail;
        $config['imprint_url'] = $imprintUrl;
        $config['privacy_url'] = $privacyUrl;
        $config['email_domain'] = $emailDomain;
        $config['email_pattern'] = $emailPattern;
        $config['admin_user'] = $adminUser;
        $config['admin_password_hash'] = password_hash($adminPassword, PASSWORD_DEFAULT);
        $config['installed'] = true;

        if (!empty($_FILES['company_logo']['name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
            $logo = upload_image('company_logo', 'logo');
            if ($logo !== '') {
                $config['company_logo'] = $logo;
            }
        }

        save_json('config.json', $config);

        // Start with an empty contact list after installation.
        save_json('contacts.json', []);

        header('Location: /admin/login');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation | Digital vCard CMS</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5" style="max-width: 900px;">

<div class="mb-4">
<h1>Digital vCard CMS installieren</h1>
<p class="text-body-secondary mb-0">Dieser Assistent richtet die wichtigsten Einstellungen für die erste Nutzung ein.</p>
<p class="text-body-secondary small mb-0">Erkannte Basis-Domain: <strong><?= h($baseDomain) ?></strong></p>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
<ul class="mb-0">
<?php foreach ($errors as $error): ?>
<li><?= h($error) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div class="card mb-4">
<div class="card-header">Systemcheck</div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-2">
/data beschreibbar:
<?= $dataDirWritable ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Fehlt</span>' ?>
</div>

<div class="col-md-6 mb-2">
/public/uploads beschreibbar:
<?= $uploadsWritable ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Fehlt</span>' ?>
</div>
</div>

</div>
</div>

<form method="post" enctype="multipart/form-data">

<div class="card mb-4">
<div class="card-header">Firmendaten</div>
<div class="card-body">

<div class="row">
<div class="col-md-8 mb-3">
<label class="form-label">Firmenname</label>
<input type="text" name="company_name" class="form-control" value="<?= h($_POST['company_name'] ?? 'Demo Company') ?>" required>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Firmenfarbe</label>
<input type="color" name="company_color" class="form-control form-control-color" value="<?= h($_POST['company_color'] ?? '#0d6efd') ?>">
</div>
</div>

<div class="mb-3">
<label class="form-label">Firmenlogo</label>
<input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp">
</div>

<div class="mb-3">
<label class="form-label">Logo-Link</label>
<input type="url" name="logo_link" class="form-control" value="<?= h($_POST['logo_link'] ?? default_url_for_base_domain($baseDomain)) ?>">
</div>

</div>
</div>

<div class="card mb-4">
<div class="card-header">Links</div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">GitHub Link optional</label>
<input type="url" name="github_url" class="form-control" value="<?= h($_POST['github_url'] ?? '') ?>">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Startseiten-Weiterleitung</label>
<input type="url" name="home_redirect_url" class="form-control" value="<?= h($_POST['home_redirect_url'] ?? default_url_for_base_domain($baseDomain)) ?>">
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Sammelmail für nicht gefundene Kontakte</label>
<input type="email" name="contact_email" class="form-control" value="<?= h($_POST['contact_email'] ?? 'info@' . $baseDomain) ?>">
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Impressum Link</label>
<input type="url" name="imprint_url" class="form-control" value="<?= h($_POST['imprint_url'] ?? default_url_for_base_domain($baseDomain, 'impressum')) ?>">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Datenschutz Link</label>
<input type="url" name="privacy_url" class="form-control" value="<?= h($_POST['privacy_url'] ?? default_url_for_base_domain($baseDomain, 'datenschutz')) ?>">
</div>
</div>

</div>
</div>

<div class="card mb-4">
<div class="card-header">E-Mail Automatik</div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Mail-Domain hinter dem @</label>
<div class="input-group">
<span class="input-group-text">@</span>
<input type="text" name="email_domain" class="form-control" value="<?= h($_POST['email_domain'] ?? $baseDomain) ?>" required>
</div>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Schema vor dem @</label>
<select name="email_pattern" class="form-select">
<?php
$selectedPattern = $_POST['email_pattern'] ?? 'vorname.nachname';
$patterns = [
    'vorname' => 'Vorname — jane@example.com',
    'nachname' => 'Nachname — doe@example.com',
    'initialen' => 'Initialen — jd@example.com',
    'vorname.nachname' => 'Vorname.Nachname — jane.doe@example.com',
    'v.nachname' => 'Initial.Nachname — j.doe@example.com',
    'vorname_nachname' => 'Vorname_Nachname — jane_doe@example.com',
    'vornamenachname' => 'VornameNachname — janedoe@example.com'
];
foreach ($patterns as $value => $label):
?>
<option value="<?= h($value) ?>" <?= $selectedPattern === $value ? 'selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

</div>
</div>

<div class="card mb-4">
<div class="card-header">Admin-Benutzer</div>
<div class="card-body">

<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">Benutzername</label>
<input type="text" name="admin_user" class="form-control" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Passwort</label>
<input type="password" name="admin_password" class="form-control" required>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Passwort wiederholen</label>
<input type="password" name="admin_password_repeat" class="form-control" required>
</div>
</div>

</div>
</div>

<button class="btn btn-primary btn-lg">Installation abschließen</button>

</form>

</div>

</body>
</html>
