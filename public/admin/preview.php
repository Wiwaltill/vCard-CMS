<?php
require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    http_response_code(413);
    exit('Preview is too large.');
}
$config = get_config();
$existing = [];
$id = $_POST['preview_id'] ?? '';
if (!is_string($id)) {
    http_response_code(422);
    exit('Invalid contact.');
}
if ($id !== '') {
    foreach (load_json('contacts.json', []) as $contact) if (($contact['id'] ?? '') === $id) {
        $existing = $contact;
        break;
    }
    if (!$existing) {
        http_response_code(404);
        exit('Not found.');
    }
}
try {
    $card = contact_form_values($_POST, $config, $existing);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit('Invalid contact fields.');
}
$card['id'] = $id !== '' ? $id : 'preview';
$isPreview = true;
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
require __DIR__ . '/../includes/card-view.php';
