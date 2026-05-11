<?php

setcookie('kb_admin_login', '', [
    'expires' => time() - 3600,
    'path' => '/admin',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

header('Location: login.php');
exit;
