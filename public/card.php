<?php

require_once __DIR__ . '/includes/functions.php';
require_installed();

header('X-Robots-Tag: noindex, nofollow', true);

$config = get_config();
$contacts = load_json('contacts.json', []);

$id = $_GET['id'] ?? '';

$card = null;

foreach ($contacts as $contact) {
    if (($contact['id'] ?? '') === $id) {
        $card = $contact;
        break;
    }
}

if (!$card) {
    require __DIR__ . '/contact-not-found.php';
    exit;
}

require __DIR__ . '/includes/card-view.php';
