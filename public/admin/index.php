<?php

$contacts = json_decode(file_get_contents('../../data/contacts.json'), true);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <title>Adminbereich</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
    >
</head>
<body>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Kontakte</h1>

        <a href="new.php" class="btn btn-primary">
            Neuer Kontakt
        </a>
    </div>

    <table class="table table-bordered bg-white">

        <thead>
            <tr>
                <th>Name</th>
                <th>URL</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($contacts as $contact): ?>

            <tr>
                <td>
                    <?= $contact['vorname'] ?>
                    <?= $contact['nachname'] ?>
                </td>

                <td>
                    https://vc.kb-events.eu/<?= $contact['id'] ?>
                </td>
            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

</body>
</html>
