<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/qr-code.php';
require_installed();
$id = $_GET['id'] ?? '';
$format = $_GET['format'] ?? 'png';
if (!is_string($id) || !preg_match('/^[a-z0-9]{2,20}$/D', $id) || !in_array($format, ['png', 'svg'], true)) {
    http_response_code(400);
    exit('Invalid QR request.');
}
$contact = null;
foreach (load_json('contacts.json', []) as $candidate) if (($candidate['id'] ?? '') === $id) {
    $contact = $candidate;
    break;
}
if (!$contact) {
    http_response_code(404);
    exit('Not found.');
}
$matrix = qr_matrix(contact_url($contact));
$data = $format === 'svg' ? qr_svg($matrix) : qr_png($matrix);
header('Content-Type: ' . ($format === 'svg' ? 'image/svg+xml' : 'image/png'));
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="qr-' . $id . '.' . $format . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-cache');
header('Content-Length: ' . strlen($data));
echo $data;
