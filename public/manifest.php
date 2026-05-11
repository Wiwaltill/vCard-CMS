<?php
require_once 'includes/functions.php';
$config = get_config();
header('Content-Type: application/manifest+json; charset=utf-8');
$theme = $config['company_color'] ?? '#0d6efd';
echo json_encode([
    'id' => '/',
    'name' => ($config['company_name'] ?? 'vCards') . ' vCards',
    'short_name' => 'vCards',
    'description' => 'Digitale Visitenkarten',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
    'background_color' => '#ffffff',
    'theme_color' => $theme,
    'icons' => [
        ['src' => '/assets/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']
    ]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
