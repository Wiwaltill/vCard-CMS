<?php

require_once __DIR__ . '/includes/functions.php';

if (!is_installed()) {
    header('Location: /install', true, 302);
    exit;
}

$config = get_config();

$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if ($requestPath === '') {
    $target = trim($config['home_redirect_url'] ?? '');

    if ($target !== '') {
        header('Location: ' . $target, true, 302);
        exit;
    }
}

require __DIR__ . '/contact-not-found.php';
exit;
