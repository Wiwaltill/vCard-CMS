<?php
// Offline, isolated updater tests. Never changes live application data or calls GitHub.
$fixture = sys_get_temp_dir() . '/vcard-updater-' . bin2hex(random_bytes(8));
mkdir($fixture . '/public/includes', 0700, true);
mkdir($fixture . '/public/admin', 0700, true);
mkdir($fixture . '/public/uploads', 0700, true);
mkdir($fixture . '/data', 0700, true);
foreach (['functions.php','updater.php','version.json'] as $name) copy(__DIR__ . '/../public/includes/' . $name, $fixture . '/public/includes/' . $name);
foreach (['config.sample.json','contacts.sample.json'] as $name) copy(__DIR__ . '/../data/' . $name, $fixture . '/data/' . $name);
require $fixture . '/public/includes/functions.php';
require $fixture . '/public/includes/updater.php';
$checks = 0;
function updater_check(bool $value, string $message): void {
    global $checks; $checks++;
    if (!$value) throw new RuntimeException($message);
}
function updater_reject(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable $error) { updater_check(true, $message); return; }
    updater_check(false, $message);
}
function updater_zip(string $fixture, string $version, array $extra = []): string {
    $path = $fixture . '/release-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE);
    $files = [
        'public/includes/version.json'=>json_encode(['version'=>$version, 'php_min'=>'8.2']),
        'public/includes/functions.php'=>'<?php // new release functions',
        'public/includes/updater.php'=>'<?php // new release updater',
        'public/admin/update.php'=>'<?php // new release page',
        'public/.htaccess'=>'# new rewrite rules',
        'public/new-code.php'=>'<?php echo "new";',
        'public/uploads/photo.png'=>'must never overwrite photo',
        'data/config.json'=>'must never overwrite config',
        'data/contacts.json'=>'must never overwrite contacts',
        '.git/config'=>'must never touch git',
        'data/update-backups/erase.zip'=>'must never overwrite backups',
    ];
    foreach (array_merge($files, $extra) as $name => $contents) if ($contents !== null) $zip->addFromString('repo-sha/' . $name, $contents);
    $zip->close(); return $path;
}
try {
    updater_check(update_normalized_version('2.0') === '2.0.0' && update_normalized_version('v2.0') === '2.0.0' && update_normalized_version('v2.0.1') === '2.0.1', 'Short release versions not normalized');
    updater_reject(fn()=>update_normalized_version('2.0-rc1'), 'Prerelease version normalized as stable');
    $short = update_release(static fn($path)=>$path === 'releases/latest' ? ['tag_name'=>'2.0'] : ['object'=>['type'=>'commit','sha'=>str_repeat('a',40)]]);
    updater_check($short['version'] === '2.0.0' && $short['tag'] === '2.0', 'Short release tag was changed or rejected');
    $config = get_config(); $config['installed'] = true; save_json('config.json', $config);
    save_json('contacts.json', [['id'=>'ab','vorname'=>'Erika','_deleted_at'=>'2026-10-04','_purged_at'=>'2026-10-04']]);
    file_put_contents($fixture . '/public/uploads/photo.png', 'original user photo');
    $originalConfig = file_get_contents(data_path('config.json'));
    $originalContacts = file_get_contents(data_path('contacts.json'));
    $originalFunctions = file_get_contents($fixture . '/public/includes/functions.php');
    $baseline = update_version()['version'];
    $next = (explode('.', $baseline)[0] + 1) . '.0.0';
    updater_check(!update_allowed_path('data/contacts.json') && !update_allowed_path('public/uploads/photo.png'), 'Runtime data writable by updater');
    updater_check(!update_allowed_path('public//uploads/photo.png') && !update_allowed_path('public/UPLOADS/photo.png') && !update_allowed_path('public/.env.local'), 'Protected path aliases accepted');
    updater_reject(fn()=>update_target($fixture, 'public/../data/config.json'), 'Traversal accepted');
    updater_reject(fn()=>update_http('https://evil.example/payload', $fixture . '/download', 100), 'Arbitrary host accepted');
    $sha = str_repeat('a', 40);
    $release = update_release(static function ($path) use ($next, $sha) {
        if ($path === 'releases/latest') return ['tag_name'=>'v'.$next, 'body'=>'<script>unsafe</script>', 'zipball_url'=>'https://evil.example/payload'];
        if (str_starts_with($path, 'git/ref/')) return ['object'=>['type'=>'tag','sha'=>str_repeat('b',40)]];
        return ['object'=>['type'=>'commit','sha'=>$sha]];
    });
    updater_check($release['sha'] === $sha && $release['version'] === $next, 'Annotated release tag not resolved');
    updater_check(!isset($release['zipball_url']), 'Untrusted download URL retained');
    updater_reject(fn()=>update_release(static fn($path)=>['tag_name'=>'v'.$next,'prerelease'=>true]), 'Prerelease accepted');
    updater_reject(fn()=>update_release(static fn($path)=>['tag_name'=>'../../payload']), 'Unsafe release tag accepted');
    $archive = updater_zip($fixture, $next);
    $bad = updater_zip($fixture, $next, ['../escape.php'=>'<?php echo "escape";']);
    updater_reject(fn()=>update_install_archive($bad, $next), 'ZIP traversal accepted');
    updater_check(update_version()['version'] === $baseline && !file_exists(data_path('update-in-progress.json')), 'Invalid ZIP changed installation');
    updater_reject(fn()=>update_install_archive($archive, '99.99.99'), 'Version/tag mismatch accepted');
    $broken = updater_zip($fixture, $next, ['public/broken.php'=>'<?php function {']);
    updater_reject(fn()=>update_install_archive($broken, $next), 'Invalid PHP syntax accepted');
    $highPhp = updater_zip($fixture, $next, ['public/includes/version.json'=>json_encode(['version'=>$next,'php_min'=>'99.0'])]);
    updater_reject(fn()=>update_install_archive($highPhp, $next), 'Unsupported PHP requirement accepted');
    $linkArchive = updater_zip($fixture, $next);
    $linkZip = new ZipArchive(); $linkZip->open($linkArchive);
    $linkZip->addFromString('repo-sha/public/link.php', '../../data/config.json');
    $linkZip->setExternalAttributesName('repo-sha/public/link.php', 3, 0120777 << 16);
    $linkZip->close();
    updater_reject(fn()=>update_install_archive($linkArchive, $next), 'ZIP symlink accepted');
    $result = update_install_archive($archive, $next);
    updater_check(update_version()['version'] === $next, 'Release not installed');
    updater_check(file_get_contents(data_path('config.json')) === $originalConfig && file_get_contents(data_path('contacts.json')) === $originalContacts, 'Update altered user JSON');
    updater_check(file_get_contents($fixture . '/public/uploads/photo.png') === 'original user photo', 'Update altered upload');
    updater_check(is_file(update_backup_path($result['code_backup'])) && is_file(backup_file_path($result['data_backup'])), 'Missing code or data backup');
    updater_check(!file_exists(data_path('update-in-progress.json')), 'Successful update left maintenance lock');
    updater_reject(fn()=>update_install_archive($archive, $next), 'Same version reinstall accepted');
    // New contact data created after the update must survive a code rollback.
    save_json('contacts.json', [['id'=>'newperson','vorname'=>'New person']]);
    file_put_contents($fixture . '/public/custom.txt', 'custom user file');
    $later = (explode('.', $next)[0] + 1) . '.0.0';
    $laterArchive = updater_zip($fixture, $later, ['public/new-code.php'=>null]);
    $laterResult = update_install_archive($laterArchive, $later);
    updater_check(!file_exists($fixture . '/public/new-code.php'), 'Obsolete managed code was retained');
    updater_check(file_get_contents($fixture . '/public/custom.txt') === 'custom user file', 'Unmanaged custom file deleted');
    update_rollback($laterResult['code_backup']);
    updater_check(update_version()['version'] === $next && is_file($fixture . '/public/new-code.php'), 'Rollback lost obsolete code');
    update_rollback($result['code_backup']);
    updater_check(update_version()['version'] === $baseline && file_get_contents($fixture . '/public/includes/functions.php') === $originalFunctions, 'Rollback failed to restore code');
    updater_check(!is_file($fixture . '/public/new-code.php'), 'Rollback retained newly added code');
    updater_check(load_contacts()[0]['id'] === 'newperson', 'Code rollback lost newly added contact');
    updater_check(file_get_contents($fixture . '/public/uploads/photo.png') === 'original user photo', 'Rollback changed upload');
    $writes = 0;
    updater_reject(function () use ($archive, $next, &$writes) {
        update_install_archive($archive, $next, static function ($source, $target) use (&$writes) {
            if (++$writes === 2) throw new RuntimeException('Simulated write failure');
            update_write($source, $target);
        });
    }, 'Write failure not reported');
    updater_check($writes === 2 && update_version()['version'] === $baseline && file_get_contents($fixture . '/public/includes/functions.php') === $originalFunctions, 'Partial update not rolled back');
    updater_check(!file_exists(data_path('update-in-progress.json')), 'Recovered update left maintenance marker');
    updater_reject(fn()=>update_backup_path('../config.json'), 'Backup traversal accepted');
    updater_check(!glob(data_path('update-work/stage-*')), 'Staging directories retained');
    echo "OK: $checks updater checks passed.\n";
} finally { update_remove_tree($fixture); }
