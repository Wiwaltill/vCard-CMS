<?php

require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$contacts = load_json('contacts.json', []);
$id = $_POST['id'] ?? '';

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
