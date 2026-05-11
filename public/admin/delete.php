<?php

require_once '../includes/functions.php';
require_installed();
require_login();

$contacts = load_json('contacts.json', []);
$id = $_GET['id'] ?? '';

foreach ($contacts as $contact) {
    if (($contact['id'] ?? '') === $id && !empty($contact['bild'])) {
        delete_public_file($contact['bild']);
        break;
    }
}

$contacts = array_filter($contacts, function ($contact) use ($id) {
    return ($contact['id'] ?? '') !== $id;
});

save_contacts($contacts);

header('Location: /admin');
exit;
