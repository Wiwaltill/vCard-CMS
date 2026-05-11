<?php

require_once '../includes/functions.php';
require_login();

$config = get_config();
$contacts = load_json('contacts.json', []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $id = make_contact_id($vorname, $nachname, $contacts);

    $emailOverride = isset($_POST['email_override']);
    $autoEmail = generate_email($vorname, $nachname, $config);
    $email = $emailOverride ? trim($_POST['email']) : $autoEmail;

    $bild = upload_image('bild', 'mitarbeiter-' . $id);

    $contacts[] = [
        'id' => $id,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'telefon' => $_POST['telefon'],
        'email' => $email,
        'email_override' => $emailOverride,
        'position' => $_POST['position'],
        'bild' => $bild
    ];

    save_contacts($contacts);

    header('Location:/admin');
    exit;
}

include '../includes/header.php';

?>

<h1 class="mb-4">Neuer Kontakt</h1>

<form method="post" enctype="multipart/form-data">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Vorname</label>
<input type="text" name="vorname" id="vorname" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Nachname</label>
<input type="text" name="nachname" id="nachname" class="form-control" required>
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Position</label>
<input type="text" name="position" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Telefon</label>
<input type="text" name="telefon" class="form-control">
</div>
</div>

<div class="mb-3">
<label class="form-label">E-Mail</label>
<input type="email" name="email" id="email" class="form-control" disabled>
<div class="form-text">
Live-Vorschau der automatisch generierten Adresse
</div>
</div>

<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" name="email_override" id="email_override">
<label class="form-check-label" for="email_override">
Automatische E-Mail überschreiben
</label>
</div>

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Mitarbeiterfoto</label>
<input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
</div>
</div>

<button class="btn btn-success">Speichern</button>
<a href="/admin" class="btn btn-secondary">Abbrechen</a>

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
        case 'vorname':
            local = first;
            break;

        case 'nachname':
            local = last;
            break;

        case 'initialen':
            local = first.substring(0, 1) + last.substring(0, 1);
            break;

        case 'v.nachname':
            local = first.substring(0, 1) + '.' + last;
            break;

        case 'vorname_nachname':
            local = first + '_' + last;
            break;

        case 'vornamenachname':
            local = first + last;
            break;

        case 'vorname.nachname':
        default:
            local = first + '.' + last;
            break;
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
