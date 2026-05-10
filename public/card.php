<?php

$contacts = json_decode(file_get_contents('../data/contacts.json'), true);

$id = $_GET['id'] ?? '';

$card = null;

foreach ($contacts as $contact) {
    if ($contact['id'] === $id) {
        $card = $contact;
        break;
    }
}

if (!$card) {
    http_response_code(404);
    exit('Nicht gefunden');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $card['vorname'] ?> <?= $card['nachname'] ?></title>
</head>
<body>

<h1>
    <?= $card['vorname'] ?>
    <?= $card['nachname'] ?>
</h1>

<p><?= $card['firma'] ?></p>

<a href="tel:<?= $card['telefon'] ?>">
    <?= $card['telefon'] ?>
</a>

<br>

<a href="mailto:<?= $card['email'] ?>">
    <?= $card['email'] ?>
</a>

<br><br>

<a href="/<?= $card['id'] ?>/vcard">
    Kontakt speichern
</a>

</body>
</html>