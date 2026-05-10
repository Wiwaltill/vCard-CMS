<?php

header('X-Robots-Tag: noindex, nofollow', true);

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
    exit('Kontakt nicht gefunden');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= $card['vorname'] ?> <?= $card['nachname'] ?></title>

    <meta name="robots" content="noindex, nofollow, noarchive">

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
    >
</head>
<body class="bg-light">

<div class="container py-5">

    <div class="card shadow mx-auto" style="max-width: 500px;">
        <div class="card-body text-center">

            <?php if (!empty($card['bild'])): ?>
                <img
                    src="/uploads/<?= $card['bild'] ?>"
                    class="rounded-circle mb-3"
                    width="120"
                >
            <?php endif; ?>

            <h1 class="h3">
                <?= $card['vorname'] ?>
                <?= $card['nachname'] ?>
            </h1>

            <p class="text-muted">
                <?= $card['firma'] ?>
            </p>

            <div class="d-grid gap-2 mt-4">

                <a
                    href="tel:<?= $card['telefon'] ?>"
                    class="btn btn-primary"
                >
                    Anrufen
                </a>

                <a
                    href="mailto:<?= $card['email'] ?>"
                    class="btn btn-outline-primary"
                >
                    E-Mail
                </a>

                <a
                    href="/<?= $card['id'] ?>/vcard"
                    class="btn btn-success"
                >
                    Kontakt speichern
                </a>

            </div>

        </div>
    </div>

</div>

</body>
</html>
