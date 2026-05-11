<?php

require_once '../includes/functions.php';
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
    exit('Kontakt nicht gefunden');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);

    $newId = make_contact_id($vorname, $nachname, $contacts, $id);

    $emailOverride = isset($_POST['email_override']);
    $autoEmail = generate_email($vorname, $nachname, $config);
    $email = $emailOverride ? trim($_POST['email']) : $autoEmail;

    if (isset($_POST['delete_bild']) && !empty($contacts[$currentKey]['bild'])) {
        delete_public_file($contacts[$currentKey]['bild']);
        $contacts[$currentKey]['bild'] = '';
    }

    $bild = upload_image('bild', 'mitarbeiter-' . $newId);

    if ($bild !== '') {
        if (!empty($contacts[$currentKey]['bild'])) {
            delete_public_file($contacts[$currentKey]['bild']);
        }
        $contacts[$currentKey]['bild'] = $bild;
    }

    $contacts[$currentKey]['id'] = $newId;
    $contacts[$currentKey]['vorname'] = $vorname;
    $contacts[$currentKey]['nachname'] = $nachname;
    $contacts[$currentKey]['email'] = $email;
    $contacts[$currentKey]['email_override'] = $emailOverride;
    $contacts[$currentKey]['position'] = $_POST['position'];

    if (!isset($contacts[$currentKey]['fields']) || !is_array($contacts[$currentKey]['fields'])) {
        $contacts[$currentKey]['fields'] = [];
    }

    foreach ($types as $type) {
        $key = $type['key'];

        if ($key === 'email') {
            continue;
        }

        $value = trim($_POST[data_type_input_name($key)] ?? '');
        set_data_type_value($contacts[$currentKey], $type, $value);
    }

    save_contacts($contacts);

    header('Location:/admin');
    exit;
}

$emailOverride = !empty($current['email_override']);
$autoEmail = generate_email($current['vorname'], $current['nachname'], $config);
$currentEmail = contact_email($current, $config);

include '../includes/header.php';

?>

<h1 class="mb-4">Kontakt bearbeiten</h1>

<form method="post" enctype="multipart/form-data">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Vorname</label>
<input type="text" name="vorname" value="<?= h($current['vorname']) ?>" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Nachname</label>
<input type="text" name="nachname" value="<?= h($current['nachname']) ?>" class="form-control" required>
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Position</label>
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
<div class="form-text">Username oder vollständige Profil-URL eintragen.</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<div class="mb-3">
<label class="form-label">E-Mail</label>
<input type="email" name="email" id="email" value="<?= h($currentEmail) ?>" class="form-control" <?= $emailOverride ? '' : 'disabled' ?>>
<div class="form-text">
Automatisch: <?= h($autoEmail) ?>
</div>
</div>

<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" name="email_override" id="email_override" <?= $emailOverride ? 'checked' : '' ?>>
<label class="form-check-label" for="email_override">
Automatische E-Mail überschreiben
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
<div class="form-text">Username oder vollständige Profil-URL eintragen.</div>
<?php endif; ?>
</div>
<?php endforeach; ?>

<div class="mb-3">
<label class="form-label">Mitarbeiterfoto</label>

<?php if (!empty($current['bild'])): ?>
<div class="mb-2 d-flex align-items-center gap-3">
<img src="<?= h($current['bild']) ?>" height="90" class="rounded" alt="Mitarbeiterfoto">
<button type="submit" name="delete_bild" value="1" class="btn btn-danger btn-sm" onclick="return confirm('Mitarbeiterfoto wirklich löschen?');">
<i class="bi bi-trash"></i>
</button>
</div>
<?php endif; ?>

<input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
<div class="form-text">Neues Bild ersetzt die alte Datei automatisch.</div>
</div>

<button class="btn btn-success">Speichern</button>
<a href="/admin" class="btn btn-secondary">Abbrechen</a>

</form>

<script>
const overrideCheckbox = document.getElementById('email_override');
const emailInput = document.getElementById('email');
const autoEmail = <?= json_encode($autoEmail) ?>;
const savedEmail = <?= json_encode($currentEmail) ?>;

function toggleEmailField() {
    emailInput.disabled = !overrideCheckbox.checked;

    if (overrideCheckbox.checked) {
        if (!emailInput.value) {
            emailInput.value = savedEmail || autoEmail;
        }
    } else {
        emailInput.value = autoEmail;
    }
}

overrideCheckbox.addEventListener('change', toggleEmailField);
toggleEmailField();
</script>

<?php include '../includes/footer.php'; ?>
