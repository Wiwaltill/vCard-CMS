<?php

require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$types = data_types($config);
$contacts = load_json('contacts.json', []);
$id = $_GET['id'] ?? '';
$current = null;
$currentKey = null;

foreach ($contacts as $key => $contact) {
    if (($contact['id'] ?? '') === $id) {
        $current = $contact;
        $currentKey = $key;
        break;
    }
}

if (!$current) {
    http_response_code(404);
    exit(admin_t('not_found', $config));
}

$current = array_replace(['vorname' => '', 'nachname' => '', 'position' => '', 'bild' => '', 'fields' => []], $current);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contact = contact_form_values($_POST, $config, $contacts[$currentKey]);

    if (isset($_POST['delete_bild']) && !empty($contacts[$currentKey]['bild'])) {
        delete_public_file($contacts[$currentKey]['bild']);
        $contact['bild'] = '';
    }

    $bild = isset($_POST['delete_bild']) ? '' : upload_image('bild', 'mitarbeiter-' . $id);

    if ($bild !== '') {
        if (!empty($contacts[$currentKey]['bild'])) {
            delete_public_file($contacts[$currentKey]['bild']);
        }
        $contact['bild'] = $bild;
    }

    $contact['id'] = $id;
    $contacts[$currentKey] = $contact;

    save_contacts($contacts);

    header('Location: ' . (isset($_POST['delete_bild']) ? '/admin/edit?id=' . rawurlencode($id) : '/admin'));
    exit;
}

$emailOverride = !empty($current['email_override']);
$autoEmail = generate_email($current['vorname'], $current['nachname'], $config);
$currentEmail = contact_email($current, $config);

include __DIR__ . '/../includes/header.php';

?>

<h1 class="mb-4"><?= h(admin_t('edit_contact', $config)) ?></h1>

<div class="row g-4">
<div class="col-lg-7">
<form method="post" enctype="multipart/form-data" data-contact-editor
    data-email-domain="<?= h($config['email_domain']) ?>" data-email-pattern="<?= h($config['email_pattern']) ?>"
    data-preview-loading="<?= h(admin_t('preview_loading', $config)) ?>" data-preview-error="<?= h(admin_t('preview_error', $config)) ?>"><?= csrf_field() ?>
    <input type="hidden" name="preview_id" value="<?= h($current['id'] ?? '') ?>">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('first_name', $config)) ?></label>
            <input type="text" name="vorname" value="<?= h($current['vorname']) ?>" class="form-control" required>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('last_name', $config)) ?></label>
            <input type="text" name="nachname" value="<?= h($current['nachname']) ?>" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(admin_t('position', $config)) ?></label>
            <input type="text" name="position" value="<?= h($current['position']) ?>" class="form-control">
        </div>

        <?php foreach ($types as $type): ?>
            <?php if ($type['key'] !== 'phone'): ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?= h($type['label']) ?></label>
                <input type="<?= h($type['type'] === 'social' ? 'text' : $type['type']) ?>" name="<?= h(data_type_input_name($type['key'])) ?>" value="<?= h(data_type_value($current, $type, $config)) ?>" class="form-control">
                <?php if (($type['type'] ?? '') === 'social'): ?>
                    <div class="form-text"><?= h(admin_t('username_or_url', $config)) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mb-3">
        <label class="form-label"><?= h(admin_t('email', $config)) ?></label>
        <input type="email" name="email" id="email" value="<?= h($currentEmail) ?>" class="form-control" <?= $emailOverride ? '' : 'disabled' ?>>
        <div class="form-text">
            <?= h(admin_t('automatic', $config)) ?>: <span id="automaticEmailHint"><?= h($autoEmail) ?></span>
        </div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="email_override" id="email_override" <?= $emailOverride ? 'checked' : '' ?>>
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
            <input type="<?= h($type['type'] === 'social' ? 'text' : $type['type']) ?>" name="<?= h(data_type_input_name($type['key'])) ?>" value="<?= h(data_type_value($current, $type, $config)) ?>" class="form-control">
            <?php if (($type['type'] ?? '') === 'social'): ?>
                <div class="form-text"><?= h(admin_t('username_or_url', $config)) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="mb-3">
        <label class="form-label"><?= h(admin_t('employee_photo', $config)) ?></label>

        <?php if (!empty($current['bild'])): ?>
            <div class="mb-2 d-flex align-items-center gap-3">
                <img src="<?= h($current['bild']) ?>" height="90" class="rounded" alt="<?= h(admin_t('employee_photo', $config)) ?>">
                <button type="submit" name="delete_bild" value="1" class="btn btn-danger btn-sm" onclick="return confirm('<?= h(admin_t('employee_photo', $config)) ?> <?= h(admin_t('delete_file_confirm', $config)) ?>');">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        <?php endif; ?>

        <input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
        <div class="form-text"><?= h(admin_t('image_replace', $config)) ?></div>
    </div>

    <button class="btn btn-success"><?= h(admin_t('save', $config)) ?></button>
    <a href="/admin" class="btn btn-secondary"><?= h(admin_t('cancel', $config)) ?></a>

</form>
</div>
<?php include __DIR__ . '/../includes/editor-preview.php'; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
