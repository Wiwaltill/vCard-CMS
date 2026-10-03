<?php
// Run with: php tests/security.php (requires fileinfo and ZipArchive).
// All runtime data and uploads are created in a disposable directory.
$fixture = sys_get_temp_dir() . '/vcard-test-' . bin2hex(random_bytes(8));
mkdir($fixture . '/public/includes', 0700, true);
mkdir($fixture . '/public/uploads', 0700, true);
mkdir($fixture . '/data', 0700, true);
copy(__DIR__ . '/../public/includes/functions.php', $fixture . '/public/includes/functions.php');
copy(__DIR__ . '/../data/config.sample.json', $fixture . '/data/config.sample.json');
copy(__DIR__ . '/../data/contacts.sample.json', $fixture . '/data/contacts.sample.json');
require $fixture . '/public/includes/functions.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function test_zip(array $entries): string
{
    global $fixture;
    $path = $fixture . '/test-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) throw new RuntimeException('Cannot create test ZIP.');
    foreach ($entries as $name => $contents) $zip->addFromString($name, $contents);
    $zip->close();
    return $path;
}
function remove_fixture(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $entry) if ($entry !== '.' && $entry !== '..') remove_fixture($path . '/' . $entry);
        rmdir($path);
    } else unlink($path);
}
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $config = get_config();
    check(strlen($config['api_token']) === 48 && $config['api_token'] !== 'change-me-after-install', 'Placeholder token not migrated.');
    check(get_config()['api_token'] === $config['api_token'], 'Token must remain stable.');
    save_json('contacts.json', [['id' => 'ab', 'vorname' => 'Erika', 'nachname' => 'Mustermann']]);
    check(strlen(make_contact_id('A', '', [])) >= 2, 'Generated ID cannot be routed.');
    $original = file_get_contents(data_path('contacts.json'));
    $recursive = [];
    $recursive['loop'] = &$recursive;
    try { save_json('contacts.json', $recursive); check(false, 'Invalid JSON accepted.'); }
    catch (JsonException $expected) {}
    check(file_get_contents(data_path('contacts.json')) === $original, 'Failed write destroyed existing data.');
    check(glob($fixture . '/data/.json-*') === [], 'Temporary files leaked.');
    $_COOKIE['kb_admin_login'] = hash('sha256', 'kb-events-admin');
    check(!is_logged_in(), 'Legacy forged cookie accepted.');
    csrf_field();
    $_POST['csrf_token'] = $_SESSION['csrf'];
    require_csrf();
    check(strlen($_SESSION['csrf']) === 64, 'CSRF token is not random.');
    $_SESSION['admin_fingerprint'] = auth_fingerprint($config);
    $_SESSION['admin_expires'] = time() + 3600;
    check(is_logged_in(), 'Authenticated session rejected.');
    $config['admin_password_hash'] = password_hash('changed-password', PASSWORD_DEFAULT);
    save_json('config.json', $config);
    check(!is_logged_in(), 'Password change did not invalidate session.');
    $token = bin2hex(random_bytes(32));
    save_json('remember.json', [hash('sha256', $token) => ['fingerprint' => auth_fingerprint($config), 'expires' => time() + 3600]]);
    $_COOKIE['vcard_remember'] = $token;
    check(is_logged_in(), 'Valid remember token rejected.');
    unset($_SESSION['admin_fingerprint']);
    $_COOKIE['vcard_remember'] = bin2hex(random_bytes(32));
    check(!is_logged_in(), 'Forged remember token accepted.');
    unset($_SESSION['admin_fingerprint']);
    save_json('remember.json', [hash('sha256', $token) => ['fingerprint' => auth_fingerprint($config), 'expires' => time() - 1]]);
    $_COOKIE['vcard_remember'] = $token;
    check(!is_logged_in(), 'Expired remember token accepted.');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aE1sAAAAASUVORK5CYII=');
    file_put_contents($fixture . '/public/uploads/test.png', $png);
    file_put_contents($fixture . '/public/uploads/.htaccess', 'Require all denied');
    check(valid_image_file($fixture . '/public/uploads/test.png', 'png'), 'Valid PNG rejected.');
    check(!valid_image_file($fixture . '/public/uploads/test.png', 'jpg'), 'Wrong image extension accepted.');
    file_put_contents($fixture . '/fake.png', '<?php echo "bad";');
    check(!valid_image_file($fixture . '/fake.png', 'png'), 'Executable disguised as image accepted.');
    file_put_contents($fixture . '/fake.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    check(!valid_image_file($fixture . '/fake.svg', 'svg'), 'SVG accepted.');
    file_put_contents($fixture . '/large.png', str_repeat('x', 5 * 1024 * 1024 + 1));
    check(!valid_image_file($fixture . '/large.png', 'png'), 'Oversized image accepted.');
    $entries = ['data/config.json' => json_encode($config), 'data/contacts.json' => $original, 'public/uploads/test.png' => $png];
    foreach ([
        '../escape.php' => '<?php',
        'public/uploads/bad.php' => '<?php',
        'public/uploads/bad.svg' => '<svg/>',
        'public/uploads/fake.png' => '<?php',
        'public/uploads/../../escape.png' => $png,
        '/absolute.png' => $png,
    ] as $name => $contents) {
        check(!restore_backup_zip(test_zip($entries + [$name => $contents])), 'Unsafe archive accepted: ' . $name);
        check(file_get_contents(data_path('contacts.json')) === $original, 'Rejected restore modified live data.');
    }
    check(!restore_backup_zip(test_zip(['data/config.json' => '{}', 'data/contacts.json' => '[]'])), 'Invalid config accepted.');
    check(!restore_backup_zip(test_zip(array_replace($entries, ['data/contacts.json' => '{invalid']))), 'Malformed JSON accepted.');
    check(!restore_backup_zip(test_zip(array_replace($entries, ['data/contacts.json' => '[{"id":"ab"},{"id":"ab"}]']))), 'Duplicate IDs accepted.');
    check(!restore_backup_zip(test_zip(['data/contacts.json' => '[]'])), 'Incomplete backup accepted.');
    check(!valid_backup_contacts(['named' => ['id' => 'ab']]), 'Object-shaped contacts accepted.');
    check(!valid_backup_contacts([['id' => 'ab', 'fields' => ['phone' => []]]]), 'Nested invalid field accepted.');
    check(!restore_backup_zip(test_zip($entries + ['public/uploads/large.png' => str_repeat('x', 10 * 1024 * 1024 + 1)])), 'Expanded archive entry limit ignored.');
    $outside = $fixture . '/outside.png';
    file_put_contents($outside, $png);
    symlink($outside, $fixture . '/public/uploads/blocked.png');
    $rollbackTest = array_replace($entries, ['public/uploads/test.png' => $png . 'changed']);
    $rollbackTest['public/uploads/blocked.png'] = $png;
    check(!restore_backup_zip(test_zip($rollbackTest)), 'Restore overwrote a symlink.');
    check(file_get_contents($fixture . '/public/uploads/test.png') === $png, 'Failed restore did not roll back earlier image changes.');
    check(file_get_contents($outside) === $png, 'Restore escaped upload directory.');
    unlink($fixture . '/public/uploads/blocked.png');
    $backup = make_backup_zip();
    check($backup !== '' && is_file($backup), 'Backup creation failed.');
    check(backup_file_path(basename($backup)) === $backup, 'New backup filename cannot be downloaded.');
    save_json('contacts.json', []);
    check(restore_backup_zip($backup), 'Own backup could not be restored.');
    check(file_get_contents(data_path('contacts.json')) === $original, 'Roundtrip lost contacts.');
    check(file_get_contents($fixture . '/public/uploads/test.png') === $png, 'Roundtrip lost image.');
    check(load_json('remember.json') === [], 'Restore did not revoke remembered logins.');
    check(count(glob($fixture . '/data/backups/*.zip')) >= 1, 'Rollback backup missing.');
    echo "OK: {$checks} security checks passed.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    remove_fixture($fixture);
}
