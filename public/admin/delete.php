<?php

require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

trash_contact((string)($_POST['id'] ?? ''));

header('Location: /admin');
exit;
