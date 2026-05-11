<?php
require_once 'includes/functions.php';
$config = get_config();
header('Content-Type: application/manifest+json; charset=utf-8');
$theme = $config['company_color'] ?? '#0d6efd';
$id = preg_replace('/[^a-z0-9]/i', '', $_GET['id'] ?? '');
$startUrl = $id !== '' ? '/' . rawurlencode($id) : '/';
$name = ($config['company_name'] ?? 'vCards') . ' vCards';
if ($id !== '') {
    foreach (load_json('contacts.json', []) as $contact) {
        if (($contact['id'] ?? '') === $id) {
            $contactName = trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''));
            if ($contactName !== '') {
                $name = $contactName . ' | ' . ($config['company_name'] ?? 'vCards');
            }
            break;
        }
    }
}
echo json_encode([
    'id' => $startUrl,
    'name' => $name,
    'short_name' => $id !== '' ? 'vCard' : 'vCards',
    'description' => 'Digitale Visitenkarten',
    'start_url' => $startUrl,
    'scope' => '/',
    'display' => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
    'background_color' => '#ffffff',
    'theme_color' => $theme,
    'icons' => [
        ['src' => '/assets/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']
    ]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
