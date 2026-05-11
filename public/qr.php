<?php
require_once 'includes/functions.php';
require_installed();
$contacts = load_json('contacts.json', []);
$id = $_GET['id'] ?? '';
$format = ($_GET['format'] ?? 'png') === 'svg' ? 'svg' : 'png';
$contact = null;
foreach ($contacts as $c) if (($c['id'] ?? '') === $id) { $contact = $c; break; }
if (!$contact) { http_response_code(404); exit('not found'); }
$url = contact_url($contact);
$remote = 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&format=' . $format . '&data=' . urlencode($url);
$data = @file_get_contents($remote);
if ($data === false) { header('Location: '.$remote); exit; }
header('Content-Type: ' . ($format === 'svg' ? 'image/svg+xml' : 'image/png'));
header('Content-Disposition: attachment; filename="qr-' . preg_replace('/[^a-z0-9_-]/i','',$id) . '.' . $format . '"');
echo $data;
