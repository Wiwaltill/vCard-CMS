<?php

require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$contacts = load_json('contacts.json', []);
$types = data_types($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contact = contact_form_values($_POST, $config);
    $contact['id'] = make_contact_id($contact['vorname'], $contact['nachname'], $contacts);
    $contact['bild'] = upload_image('bild', 'mitarbeiter-' . $contact['id']);

    $contacts[] = $contact;
    save_contacts($contacts);

    header('Location:/admin');
    exit;
}

include __DIR__ . '/../includes/header.php';

?>

<h1 class="mb-4"><?= h(admin_t('new_contact', $config)) ?></h1>

<div class="row g-4">
<div class="col-lg-7">
<form method="post" enctype="multipart/form-data" data-contact-editor
    data-email-domain="<?= h($config['email_domain']) ?>" data-email-pattern="<?= h($config['email_pattern']) ?>"
    data-preview-loading="<?= h(admin_t('preview_loading', $config)) ?>" data-preview-error="<?= h(admin_t('preview_error', $config)) ?>"><?= csrf_field() ?>
    <input type="hidden" name="preview_id" value="<?= h($current['id'] ?? '') ?>">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('first_name', $config)) ?></label>
            <input type="text" name="vorname" id="vorname" class="form-control" required>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('last_name', $config)) ?></label>
            <input type="text" name="nachname" id="nachname" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('position', $config)) ?></label>
            <input type="text" name="position" class="form-control">
        </div>

        <?php foreach ($types as $type): ?>
            <?php if ($type['key'] !== 'email' && $type['key'] !== 'phone'): ?>
                <?php continue; ?>
            <?php endif; ?>
            <?php if ($type['key'] === 'phone'): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= h($type['label']) ?></label>
                    <input type="<?= h($type['type'] === 'social' ? 'text' : $type['type']) ?>" name="<?= h(data_type_input_name($type['key'])) ?>" class="form-control">
                    <?php if (($type['type'] ?? '') === 'social'): ?>
                        <div class="form-text"><?= h(admin_t('username_or_url', $config)) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="mb-3">
        <label class="form-label"><?= h(admin_t('email', $config)) ?></label>
        <input type="email" name="email" id="email" class="form-control" disabled>
        <div class="form-text"><?= h(admin_t('email_preview', $config)) ?></div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="email_override" id="email_override">
        <label class="form-check-label" for="email_override">
            <?= h(admin_t('override_email', $config)) ?>
        </label>
    </div>

    <?php foreach ($types as $type): ?>
        <?php if (in_array($type['key'], ['email', 'phone'], true)): ?>
            <?php continue; ?>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label"><?= h($type['label']) ?></label>
            <input type="<?= h($type['type'] === 'social' ? 'text' : $type['type']) ?>" name="<?= h(data_type_input_name($type['key'])) ?>" class="form-control">
            <?php if (($type['type'] ?? '') === 'social'): ?>
                <div class="form-text"><?= h(admin_t('username_or_url', $config)) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="mb-3">
        <label class="form-label"><?= h(admin_t('employee_photo', $config)) ?></label>
        <input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
    </div>

    <button class="btn btn-success"><?= h(admin_t('save', $config)) ?></button>
    <a href="/admin" class="btn btn-secondary"><?= h(admin_t('cancel', $config)) ?></a>

</form>
</div>
<?php include __DIR__ . '/../includes/editor-preview.php'; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>