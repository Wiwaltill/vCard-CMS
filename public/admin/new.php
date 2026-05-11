<?php

require_once '../includes/functions.php';
require_login();

$config = get_config();
$contacts = load_json('contacts.json', []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $id = make_contact_id($vorname, $nachname, $contacts);
    $email = generate_email($vorname, $nachname, $config);

    $bild = upload_image('bild', 'mitarbeiter-' . $id);

    $contacts[] = [
        'id' => $id,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'telefon' => $_POST['telefon'],
        'email' => $email,
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

<div class="mb-3">
<label class="form-label">Vorname</label>
<input type="text" name="vorname" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Nachname</label>
<input type="text" name="nachname" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">E-Mail Schema</label>
<input type="text" class="form-control" value="<?= h(email_pattern_label($config['email_pattern'])) ?> mit @<?= h($config['email_domain']) ?>" disabled>
<div class="form-text">Die E-Mail wird beim Speichern automatisch aus Vor- und Nachname erstellt.</div>
</div>

<div class="mb-3">
<label class="form-label">Position</label>
<input type="text" name="position" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Telefon</label>
<input type="text" name="telefon" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Mitarbeiterfoto</label>
<input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
</div>

<button class="btn btn-success">Speichern</button>
<a href="/admin" class="btn btn-secondary">Abbrechen</a>

</form>

<?php include '../includes/footer.php'; ?>
