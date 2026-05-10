<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$file = '../../data/contacts.json';

if (!file_exists($file)) {
    file_put_contents($file, '[]');
}

$json = file_get_contents($file);

$contacts = json_decode($json, true);

if (!is_array($contacts)) {
    $contacts = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);

    $id =
        strtolower(substr($vorname, 0, 1)) .
        strtolower(substr($nachname, 0, 1));

    $contacts[] = [
        'id' => $id,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'firma' => $_POST['firma'],
        'telefon' => $_POST['telefon'],
        'email' => $_POST['email'],
        'bild' => ''
    ];

    file_put_contents(
        $file,
        json_encode(
            $contacts,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );

    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <title>Kontakt anlegen</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
    >
</head>
<body>

<div class="container py-5">

    <h1 class="mb-4">
        Neuer Kontakt
    </h1>

    <form method="post">

        <div class="mb-3">
            <label class="form-label">Vorname</label>
            <input type="text" name="vorname" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Nachname</label>
            <input type="text" name="nachname" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Firma</label>
            <input type="text" name="firma" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Telefon</label>
            <input type="text" name="telefon" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">E-Mail</label>
            <input type="email" name="email" class="form-control">
        </div>

        <button class="btn btn-success">
            Speichern
        </button>

    </form>

</div>

</body>
</html>
