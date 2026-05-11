<?php

require_once '../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $config['company_name'] = trim($_POST['company_name']);
    $config['company_color'] = trim($_POST['company_color']);
    $config['github_url'] = trim($_POST['github_url']);
    $config['logo_link'] = trim($_POST['logo_link']);
    $config['privacy_url'] = trim($_POST['privacy_url']);
    $config['imprint_url'] = trim($_POST['imprint_url']);
    $config['home_redirect_url'] = trim($_POST['home_redirect_url']);
    $config['contact_email'] = trim($_POST['contact_email']);
    $config['email_domain'] = strtolower(trim($_POST['email_domain']));
    $config['email_domain'] = preg_replace('/^@/', '', $config['email_domain']);
    $config['email_pattern'] = $_POST['email_pattern'];
    $config['admin_user'] = trim($_POST['admin_user']);
    $config['language'] = in_array(($_POST['language'] ?? 'auto'), ['auto','de','en'], true) ? $_POST['language'] : 'auto';
    $config['theme'] = in_array(($_POST['theme'] ?? 'classic'), ['classic','minimal','glass'], true) ? $_POST['theme'] : 'classic';
    $config['theme_mode'] = in_array(($_POST['theme_mode'] ?? 'auto'), ['auto','light','dark'], true) ? $_POST['theme_mode'] : 'auto';
    $config['darkmode_default'] = ($config['theme_mode'] === 'dark');
    $config['pwa_enabled'] = isset($_POST['pwa_enabled']);

    if (!empty($_POST['admin_password'])) {
        $config['admin_password_hash'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
    }

    if (isset($_POST['delete_company_logo']) && !empty($config['company_logo'])) {
        delete_public_file($config['company_logo']);
        $config['company_logo'] = '';
    }

    if (!empty($_FILES['company_logo']['name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
        $extension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

        if (in_array($extension, $allowed, true)) {
            if (!empty($config['company_logo'])) {
                delete_public_file($config['company_logo']);
            }

            $filename = 'logo-' . time() . '.' . $extension;
            $target = __DIR__ . '/../uploads/' . $filename;

            move_uploaded_file($_FILES['company_logo']['tmp_name'], $target);
            $config['company_logo'] = '/uploads/' . $filename;
        }
    }

    save_json('config.json', $config);
    $success = true;
}

include '../includes/header.php';

?>

<h1 class="mb-4"><?= h(admin_t('settings', $config)) ?></h1>

<?php if ($success): ?>
<div class="alert alert-success"><?= h(admin_t('saved', $config)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<div class="card bg-white shadow-sm mb-4">
<div class="card-header"><?= h(admin_t('company_data', $config)) ?></div>
<div class="card-body">

<div class="row">
<div class="col-md-8 mb-3">
<label class="form-label"><?= h(admin_t('company_name', $config)) ?></label>
<input type="text" name="company_name" value="<?= h($config['company_name']) ?>" class="form-control" required>
</div>

<div class="col-md-4 mb-3">
<label class="form-label"><?= h(admin_t('company_color', $config)) ?></label>
<input type="color" name="company_color" value="<?= h($config['company_color']) ?>" class="form-control form-control-color">
</div>
</div>

<div class="mb-3">
<label class="form-label"><?= h(admin_t('company_logo', $config)) ?></label>

<?php if (!empty($config['company_logo'])): ?>
<div class="mb-2 d-flex align-items-center gap-3">
<img src="<?= h($config['company_logo']) ?>" height="70" alt="Logo">
<button type="submit" name="delete_company_logo" value="1" class="btn btn-danger btn-sm" onclick="return confirm('<?= h(admin_t('company_logo', $config)) ?> <?= h(admin_t('delete_file_confirm', $config)) ?>');">
<i class="bi bi-trash"></i>
</button>
</div>
<?php endif; ?>

<input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp">
<div class="form-text"><?= h(admin_t('logo_replace', $config)) ?></div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('logo_link', $config)) ?></label>
<input type="url" name="logo_link" value="<?= h($config['logo_link']) ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('home_redirect', $config)) ?></label>
<input type="url" name="home_redirect_url" value="<?= h($config['home_redirect_url']) ?>" class="form-control">
<div class="form-text"><?= h(admin_t('home_redirect_help', $config)) ?></div>
</div>
</div>

</div>
</div>

<div class="card bg-white shadow-sm mb-4">
<div class="card-header"><?= h(admin_t('links', $config)) ?></div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('github_link', $config)) ?></label>
<input type="url" name="github_url" value="<?= h($config['github_url']) ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('contact_email_404', $config)) ?></label>
<input type="email" name="contact_email" value="<?= h($config['contact_email']) ?>" class="form-control">
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('imprint_link', $config)) ?></label>
<input type="url" name="imprint_url" value="<?= h($config['imprint_url']) ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('privacy_link', $config)) ?></label>
<input type="url" name="privacy_url" value="<?= h($config['privacy_url']) ?>" class="form-control">
</div>
</div>

</div>
</div>

<div class="card bg-white shadow-sm mb-4">
<div class="card-header"><?= h(admin_t('email_auto', $config)) ?></div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('mail_domain', $config)) ?></label>
<div class="input-group">
<span class="input-group-text">@</span>
<input type="text" name="email_domain" value="<?= h($config['email_domain']) ?>" class="form-control" required>
</div>
</div>

<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('mail_pattern', $config)) ?></label>
<select name="email_pattern" class="form-select">
<option value="vorname" <?= ($config['email_pattern'] === 'vorname') ? 'selected' : '' ?>><?= h(admin_t('first_name_pattern', $config)) ?> — alex@domain.de</option>
<option value="nachname" <?= ($config['email_pattern'] === 'nachname') ? 'selected' : '' ?>><?= h(admin_t('last_name_pattern', $config)) ?> — mustermann@domain.de</option>
<option value="initialen" <?= ($config['email_pattern'] === 'initialen') ? 'selected' : '' ?>><?= h(admin_t('initials_pattern', $config)) ?> — am@domain.de</option>
<option value="vorname.nachname" <?= ($config['email_pattern'] === 'vorname.nachname') ? 'selected' : '' ?>><?= h(admin_t('first_last_pattern', $config)) ?> — alex.mustermann@domain.de</option>
<option value="v.nachname" <?= ($config['email_pattern'] === 'v.nachname') ? 'selected' : '' ?>><?= h(admin_t('initial_last_pattern', $config)) ?> — a.mustermann@domain.de</option>
<option value="vorname_nachname" <?= ($config['email_pattern'] === 'vorname_nachname') ? 'selected' : '' ?>><?= h(admin_t('first_last_underscore_pattern', $config)) ?> — alex_mustermann@domain.de</option>
<option value="vornamenachname" <?= ($config['email_pattern'] === 'vornamenachname') ? 'selected' : '' ?>><?= h(admin_t('firstlast_pattern', $config)) ?> — alexmustermann@domain.de</option>
</select>
</div>
</div>

</div>
</div>

<div class="card bg-white shadow-sm mb-4">
<div class="card-header"><?= h(admin_t('user_admin', $config)) ?></div>
<div class="card-body">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('admin_username', $config)) ?></label>
<input type="text" name="admin_user" value="<?= h($config['admin_user']) ?>" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label"><?= h(admin_t('new_password', $config)) ?></label>
<input type="password" name="admin_password" class="form-control" autocomplete="new-password">
<div class="form-text"><?= h(admin_t('password_empty_help', $config)) ?></div>
</div>
</div>

</div>
</div>


<div class="card bg-white shadow-sm mb-4">
<div class="card-header"><?= h(admin_t('appearance_language', $config)) ?></div>
<div class="card-body">
<div class="row">
<div class="col-md-3 mb-3"><label class="form-label"><?= h(admin_t('language', $config)) ?></label><select name="language" class="form-select"><option value="auto" <?= ($config['language'] ?? 'auto') === 'auto' ? 'selected' : '' ?>><?= h(admin_t('language_auto', $config)) ?></option><option value="de" <?= ($config['language'] ?? 'auto') === 'de' ? 'selected' : '' ?>><?= h(admin_t('german', $config)) ?></option><option value="en" <?= ($config['language'] ?? 'auto') === 'en' ? 'selected' : '' ?>><?= h(admin_t('english', $config)) ?></option></select></div>
<div class="col-md-3 mb-3"><label class="form-label"><?= h(admin_t('theme', $config)) ?></label><select name="theme" class="form-select"><option value="classic" <?= ($config['theme'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classic</option><option value="minimal" <?= ($config['theme'] ?? 'classic') === 'minimal' ? 'selected' : '' ?>>Minimal</option><option value="glass" <?= ($config['theme'] ?? 'classic') === 'glass' ? 'selected' : '' ?>>Glass</option></select></div>
<div class="col-md-3 mb-3"><label class="form-label"><?= h(admin_t('color_mode', $config)) ?></label><select name="theme_mode" class="form-select"><option value="auto" <?= theme_mode($config) === 'auto' ? 'selected' : '' ?>><?= h(admin_t('auto', $config)) ?></option><option value="light" <?= theme_mode($config) === 'light' ? 'selected' : '' ?>><?= h(admin_t('light', $config)) ?></option><option value="dark" <?= theme_mode($config) === 'dark' ? 'selected' : '' ?>><?= h(admin_t('dark', $config)) ?></option></select></div>
<div class="col-md-3 mb-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="pwa_enabled" id="pwa_enabled" <?= !empty($config['pwa_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="pwa_enabled"><?= h(admin_t('pwa_enable', $config)) ?></label></div></div>
</div>
</div>
</div>

<button class="btn btn-success"><?= h(admin_t('save', $config)) ?></button>

</form>

<?php include '../includes/footer.php'; ?>
