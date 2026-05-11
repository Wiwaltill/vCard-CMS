<?php

require_once '../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$contacts = load_json('contacts.json', []);
$types = data_types($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $id = make_contact_id($vorname, $nachname, $contacts);

    $emailOverride = isset($_POST['email_override']);
    $autoEmail = generate_email($vorname, $nachname, $config);
    $email = $emailOverride ? trim($_POST['email']) : $autoEmail;

    $bild = upload_image('bild', 'mitarbeiter-' . $id);

    $contact = [
        'id' => $id,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'telefon' => '',
        'email' => $email,
        'email_override' => $emailOverride,
        'position' => $_POST['position'],
        'bild' => $bild,
        'fields' => []
    ];

    foreach ($types as $type) {
        $key = $type['key'];

        if ($key === 'email') {
            continue;
        }

        $value = trim($_POST[data_type_input_name($key)] ?? '');
        set_data_type_value($contact, $type, $value);
    }

    $contacts[] = $contact;
    save_contacts($contacts);

    header('Location:/admin');
    exit;
}

include '../includes/header.php';

?>

<h1 class="mb-4"><?= h(admin_t('new_contact', $config)) ?></h1>

<form method="post" enctype="multipart/form-data">

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

<script>
const overrideCheckbox = document.getElementById('email_override');
const emailInput = document.getElementById('email');
const firstNameInput = document.getElementById('vorname');
const lastNameInput = document.getElementById('nachname');

const emailDomain = <?= json_encode($config['email_domain']) ?>;
const emailPattern = <?= json_encode($config['email_pattern']) ?>;

function normalizeEmailPart(value) {
    return value
        .trim()
        .toLowerCase()
        .replaceAll('ä', 'ae')
        .replaceAll('ö', 'oe')
        .replaceAll('ü', 'ue')
        .replaceAll('ß', 'ss')
        .replace(/[^a-z0-9]+/g, '');
}

function generateEmailPreview() {
    const first = normalizeEmailPart(firstNameInput.value);
    const last = normalizeEmailPart(lastNameInput.value);

    if (!first && !last) {
        return '';
    }

    let local = '';

    switch (emailPattern) {
        case 'vorname': local = first; break;
        case 'nachname': local = last; break;
        case 'initialen': local = first.substring(0, 1) + last.substring(0, 1); break;
        case 'v.nachname': local = first.substring(0, 1) + '.' + last; break;
        case 'vorname_nachname': local = first + '_' + last; break;
        case 'vornamenachname': local = first + last; break;
        case 'vorname.nachname':
        default: local = first + '.' + last; break;
    }

    local = local.replace(/^\.+|\.+$/g, '');

    if (!local) {
        return '';
    }

    return local + '@' + emailDomain.replace(/^@/, '');
}

function updateEmailPreview() {
    if (!overrideCheckbox.checked) {
        emailInput.value = generateEmailPreview();
    }
}

function toggleEmailField() {
    emailInput.disabled = !overrideCheckbox.checked;

    if (!overrideCheckbox.checked) {
        updateEmailPreview();
    }
}

overrideCheckbox.addEventListener('change', toggleEmailField);
firstNameInput.addEventListener('input', updateEmailPreview);
lastNameInput.addEventListener('input', updateEmailPreview);

toggleEmailField();
updateEmailPreview();
</script>

<?php include '../includes/footer.php'; ?>
