<?php
require_once __DIR__ . '/../includes/functions.php';
start_admin_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
require_csrf();
$tokens = load_json('remember.json', []);
$token = $_COOKIE['vcard_remember'] ?? '';
if (is_string($token)) unset($tokens[hash('sha256', $token)]);
save_json('remember.json', $tokens);
remember_cookie('', time() - 3600);
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
setcookie('kb_admin_login', '', ['expires' => time() - 3600, 'path' => '/admin', 'httponly' => true, 'samesite' => 'Lax']);
header('Location: /admin/login');
exit;
