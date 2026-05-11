<?php

require_once '../includes/functions.php';
require_login();

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
    $contacts[$currentKey]['telefon'] = $_POST['telefon'];
    $contacts[$currentKey]['email'] = $_POST['email'];
    $contacts[$currentKey]['position'] = $_POST['position'];

    save_contacts($contacts);

    header('Location:index.php');
    exit;
}

include '../includes/header.php';

?>

<h1 class="mb-4">Kontakt bearbeiten</h1>

<form method="post" enctype="multipart/form-data">

<div class="mb-3">
<label class="form-label">Vorname</label>
<input type="text" name="vorname" value="<?= h($current['vorname']) ?>" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Nachname</label>
<input type="text" name="nachname" value="<?= h($current['nachname']) ?>" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Position</label>
<input type="text" name="position" value="<?= h($current['position']) ?>" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Telefon</label>
<input type="text" name="telefon" value="<?= h($current['telefon']) ?>" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">E-Mail</label>
<input type="email" name="email" value="<?= h($current['email']) ?>" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Mitarbeiterfoto</label>

<?php if (!empty($current['bild'])): ?>
<div class="mb-2 d-flex align-items-center gap-3">
<img src="<?= h($current['bild']) ?>" height="90" class="rounded" alt="Mitarbeiterfoto">

<button
type="submit"
name="delete_bild"
value="1"
class="btn btn-danger btn-sm"
onclick="return confirm('Mitarbeiterfoto wirklich löschen?');"
>
<i class="bi bi-trash"></i>
</button>
</div>
<?php endif; ?>

<input type="file" name="bild" class="form-control" accept=".png,.jpg,.jpeg,.webp">
<div class="form-text">
Wenn ein neues Bild hochgeladen wird, wird die alte Datei automatisch vom Server gelöscht.
</div>
</div>

<button class="btn btn-success">
Speichern
</button>

<a href="index.php" class="btn btn-secondary">
Abbrechen
</a>

</form>

<?php include '../includes/footer.php'; ?>
