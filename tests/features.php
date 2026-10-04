<?php
// Run with php tests/features.php (requires gd/WebP, exif, mbstring, fileinfo, zip).
$fixture = sys_get_temp_dir() . '/vcard-features-' . bin2hex(random_bytes(8));
function feature_copy_tree(string $source, string $target): void {
    if (is_dir($source)) {
        mkdir($target, 0700, true);
        foreach (scandir($source) as $name) if ($name !== '.' && $name !== '..') feature_copy_tree($source . '/' . $name, $target . '/' . $name);
    } else copy($source, $target);
}
function feature_remove_tree(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') feature_remove_tree($path . '/' . $name);
        rmdir($path);
    } else unlink($path);
}
mkdir($fixture . '/public/includes', 0700, true);
mkdir($fixture . '/data', 0700, true);
foreach (['functions.php', 'qr-code.php'] as $name) copy(__DIR__ . '/../public/includes/' . $name, $fixture . '/public/includes/' . $name);
feature_copy_tree(__DIR__ . '/../public/includes/vendor', $fixture . '/public/includes/vendor');
foreach (['config.sample.json', 'contacts.sample.json'] as $name) copy(__DIR__ . '/../data/' . $name, $fixture . '/data/' . $name);
require $fixture . '/public/includes/functions.php';
require $fixture . '/public/includes/qr-code.php';
$checks = 0;
function feature_check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
try {
$config = get_config();
$contacts = [
    ['id' => 'em', 'vorname' => 'Érika', 'nachname' => 'Müller', 'position' => 'Design', 'fields' => ['city' => 'Köln']],
    ['id' => 'am', 'vorname' => 'Anna', 'nachname' => 'Meier', 'position' => 'Sales', 'telefon' => '+49123456'],
];
$config['data_types'][] = ['key'=>'city', 'label'=>'Ort', 'type'=>'text', 'enabled'=>true];
feature_check(contact_list_page($contacts, $config, 'mÜLLER')['total'] === 1, 'Unicode search failed');
feature_check(contact_list_page($contacts, $config, 'köln')['total'] === 1, 'Custom field search failed');
feature_check(contact_list_page($contacts, $config, '123456')['total'] === 1, 'Phone search failed');
feature_check(contact_list_page($contacts, $config, '', 'Design')['total'] === 1, 'Position filter failed');
feature_check(contact_list_page($contacts, $config, 'Anna', 'Design')['total'] === 0, 'Combined filters failed');
feature_check(contact_list_page($contacts, $config, '', '', 999, 1)['contacts'][0]['id'] === 'am', 'Page clamping failed');
feature_check(contact_list_page([], $config, '', '', -2)['page'] === 1, 'Empty page failed');
$_SERVER['HTTP_HOST'] = 'cards.example.test:8443'; $_SERVER['HTTPS'] = 'on';
feature_check(contact_url(['id'=>'em']) === 'https://cards.example.test:8443/em', 'Current host missing');
$draft = contact_form_values(['vorname'=>'Neue', 'nachname'=>'Name', 'position'=>'CEO','field_city'=>'Bonn'], $config, $contacts[0]);
feature_check($draft['id'] === 'em', 'Draft renamed ID');
feature_check($draft['email'] === 'neue.name@example.com', 'Draft auto email differs');
feature_check($draft['fields']['city'] === 'Bonn', 'Draft field missing');
$imageDir = $fixture . '/images'; mkdir($imageDir);
$image = imagecreatetruecolor(1600, 800);
imagefill($image, 0, 0, imagecolorallocate($image, 230, 20, 30));
imagepng($image, "$imageDir/source.png"); imagedestroy($image);
optimize_image_file("$imageDir/source.png", "$imageDir/out.webp");
$size = getimagesize("$imageDir/out.webp");
feature_check($size[0] === 768 && $size[1] === 384 && $size[2] === IMAGETYPE_WEBP, 'Resize failed');
feature_check(valid_image_file("$imageDir/out.webp", 'webp'), 'Optimized image invalid');
$image = imagecreatetruecolor(120, 80);
imagefill($image, 0, 0, imagecolorallocate($image, 240, 20, 30));
imagefilledrectangle($image, 60, 0, 119, 39, imagecolorallocate($image, 20, 20, 240));
imagefilledrectangle($image, 0, 40, 59, 79, imagecolorallocate($image, 20, 240, 20));
imagefilledrectangle($image, 60, 40, 119, 79, imagecolorallocate($image, 240, 240, 20));
ob_start(); imagejpeg($image, null, 95); $jpeg = ob_get_clean(); imagedestroy($image);
foreach (range(1, 8) as $orientation) {
    $tiff = 'II' . pack('vV', 42, 8) . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, $orientation) . "\0\0" . pack('V', 0);
    $exif = "Exif\0\0" . $tiff;
    $source = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
    file_put_contents("$imageDir/oriented.jpg", $source);
    optimize_image_file("$imageDir/oriented.jpg", "$imageDir/oriented.webp");
    $size = getimagesize("$imageDir/oriented.webp");
    feature_check($size[0] === ($orientation >= 5 ? 80 : 120) && $size[1] === ($orientation >= 5 ? 120 : 80), 'Orientation failed: ' . $orientation);
    $out = imagecreatefromwebp("$imageDir/oriented.webp");
    $corner = imagecolorsforindex($out, imagecolorat($out, 10, 10));
    $colors = [1=>[240,20,30],2=>[20,20,240],3=>[240,240,20],4=>[20,240,20],
        5=>[240,20,30],6=>[20,240,20],7=>[240,240,20],8=>[20,20,240]];
    $wanted=$colors[$orientation];
    feature_check(abs($corner['red']-$wanted[0]) < 40 && abs($corner['green']-$wanted[1]) < 40 && abs($corner['blue']-$wanted[2]) < 40, 'Orientation mirrored incorrectly: ' . $orientation);
    imagedestroy($out);
}
$image = imagecreatetruecolor(32, 24); imagealphablending($image, false); imagesavealpha($image, true);
imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
imagepng($image, "$imageDir/transparent.png"); imagedestroy($image);
optimize_image_file("$imageDir/transparent.png", "$imageDir/transparent.webp", 1200);
$image = imagecreatefromwebp("$imageDir/transparent.webp");
feature_check(imagesx($image) === 32 && imagesy($image) === 24, 'Small image enlarged');
feature_check(imagecolorsforindex($image, imagecolorat($image, 1, 1))['alpha'] >= 120, 'Transparency lost');
imagedestroy($image);
$urls = ['https://example.test/em', 'https://cards.example.test:8443/abcdefghijklmnopqrst', 'https://' . str_repeat('long-subdomain.', 8) . 'example.test/em', 'https://example.test/ä?name=Jörg&team=研发'];
$output = [];
foreach ($urls as $url) {
    $matrix = qr_matrix($url);
    $size = count($matrix);
    feature_check($size >= 29, 'QR matrix too small');
    foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
        feature_check(is_bool($dark), 'QR renderer needs boolean modules');
        if ($x < 4 || $y < 4 || $x >= $size - 4 || $y >= $size - 4) {
            feature_check(!$dark, 'QR quiet zone must remain four white modules on every side');
        }
    }
    $svg = qr_svg($matrix);
    feature_check(strpos($svg, '<svg') === 0 && strpos($svg, '<script') === false, 'Invalid SVG output');
    $png = qr_png($matrix);
    feature_check(substr($png, 0, 8) === "\x89PNG\r\n\x1a\n", 'Invalid PNG output');
    feature_check((new \chillerlan\QRCode\QRCode())->readFromBlob($png)->data === $url, 'Generated QR could not be decoded.');
}
mkdir($fixture . '/public/uploads');
copy("$imageDir/source.png", $fixture . '/public/uploads/old.png');
$contacts[0]['bild'] = '/uploads/old.png';
save_contacts($contacts);
feature_check(trash_contact('em'), 'Trash operation failed');
feature_check(count(load_json('contacts.json', [])) === 1 && count(trashed_contacts()) === 1, 'Trash visible in active contacts');
feature_check(is_file($fixture . '/public/uploads/old.png'), 'Trash deleted photo');
save_contacts(load_json('contacts.json', []));
feature_check(count(trashed_contacts()) === 1, 'Active save lost trash');
feature_check(make_contact_id('Erika', 'Muller', []) !== 'em', 'Trash ID reused');
feature_check(restore_trashed_contact('em'), 'Restore failed');
feature_check(count(load_json('contacts.json', [])) === 2 && !trashed_contacts(), 'Restored contact missing');
feature_check(!purge_trashed_contact('em'), 'Purge accepted active contact');
feature_check(trash_contact('em'), 'Second trash operation failed');
$job = start_image_optimization();
feature_check(is_file(data_path('backups/' . $job['backup'])), 'Migration started without backup');
feature_check(start_image_optimization()['id'] === $job['id'], 'Resume created a new job');
$job = step_image_optimization($job['id']);
feature_check($job['done'] === 1 && $job['report'][0]['status'] === 'optimized', 'Migration failed to optimize trashed photo');
$trashed = trashed_contacts()[0];
feature_check(substr($trashed['bild'], -5) === '.webp' && is_file($fixture . '/public' . $trashed['bild']), 'Migration lost photo reference');
feature_check(restore_trashed_contact('em'), 'Restore after migration failed');
feature_check(trash_contact('em') && purge_trashed_contact('em'), 'Permanent deletion failed');
feature_check(!is_file($fixture . '/public' . $trashed['bild']), 'Purge retained unused photo');
$oldMemoryLimit = ini_get('memory_limit');
$lowMemoryLimit = ((int)ceil(memory_get_usage(true) / (1024 * 1024)) + 2) . 'M';
ini_set('memory_limit', $lowMemoryLimit);
try {
    optimize_image_file("$imageDir/source.png", "$imageDir/blocked.webp", 1200);
    feature_check(false, 'Memory guard failed to stop an oversized decode');
} catch (RuntimeException $error) {
    feature_check(strpos($error->getMessage(), '1600 × 800') !== false && strpos($error->getMessage(), $lowMemoryLimit) !== false && strpos($error->getMessage(), '256M') !== false, 'Memory warning lacks dimensions, actual limit or recommendation');
    feature_check(is_file("$imageDir/source.png") && !is_file("$imageDir/blocked.webp"), 'Memory failure changed the original');
} finally {
    ini_set('memory_limit', $oldMemoryLimit);
}
$shared = '/uploads/shared.png';
copy("$imageDir/source.png", $fixture . '/public' . $shared);
save_contacts([['id'=>'aa', 'bild'=>$shared], ['id'=>'bb', 'bild'=>$shared]]);
feature_check(trash_contact('aa') && purge_trashed_contact('aa'), 'Shared-photo purge failed');
feature_check(is_file($fixture . '/public' . $shared), 'Purge deleted another contact photo');
feature_check(trash_contact('bb'), 'Backup trash setup failed');
$backup = make_backup_zip();
feature_check(restore_trashed_contact('bb') && restore_backup_zip($backup), 'Trash backup restore failed');
feature_check(count(trashed_contacts()) === 1 && !load_json('contacts.json', []), 'Backup lost trash status');
$job = start_image_optimization();
$changed = load_json_file_path(data_path('contacts.json'));
foreach ($changed as &$record) if ($record['id'] === 'bb') $record['bild'] = '/uploads/missing.png';
unset($record);
save_json('contacts.json', $changed);
$job = step_image_optimization($job['id']);
feature_check($job['report'][0]['status'] === 'skipped', 'Migration overwrote changed photo');
feature_check(step_image_optimization($job['id'])['done'] === $job['done'], 'Completed step repeated');
$skipJob = ['id'=>'skip-test', 'done'=>0, 'backup'=>basename($backup), 'report'=>[], 'items'=>[
    ['id'=>'bb', 'path'=>'/uploads/shared.png', 'label'=>'Photo'],
    ['id'=>null, 'path'=>'/uploads/shared.png', 'label'=>'Logo'],
]];
save_json('image-optimization.json', $skipJob);
feature_check(image_optimization_next($skipJob)['max_dimension'] === 768 && image_optimization_next($skipJob)['mime'] === 'image/png', 'Browser migration lacks image metadata');
try {
    step_image_optimization('skip-test', ['error'=>UPLOAD_ERR_OK]);
    feature_check(false, 'Prepared upload accepted without progress position');
} catch (RuntimeException $error) {feature_check(true, 'Prepared upload requires progress position');}
feature_check(step_image_optimization('skip-test', null, 1)['done'] === 0, 'Stale prepared step advanced the current image');
$beforeSkip = file_get_contents($fixture . '/public/uploads/shared.png');
$skipped = skip_image_optimization('skip-test', 0);
feature_check($skipped['done'] === 1 && $skipped['report'][0]['code'] === 'manual_skip', 'Manual skip failed');
feature_check(skip_image_optimization('skip-test', 0)['done'] === 1, 'Stale skip skipped the next image');
feature_check(file_get_contents($fixture . '/public/uploads/shared.png') === $beforeSkip, 'Manual skip changed original');
feature_check(skip_image_optimization('skip-test', 1)['done'] === 2, 'Skip did not finish blocked run');
feature_check(skip_image_optimization('skip-test', 2)['done'] === 2, 'Completed job advanced past end');
feature_check(ini_bytes('128M') === 134217728 && ini_bytes('2G') === 2147483648, 'Server limit parser failed');
feature_check(count(server_checks()) >= 15, 'Server check incomplete');
// Printed QR targets must survive contact edits and must never change owners after purge.
save_json('contacts.json', []);
$withoutTargetBackup = make_backup_zip();
$stable = ['id'=>'qr', 'vorname'=>'Quinn', 'nachname'=>'Roth'];
save_contacts([$stable]);
$printedUrl = contact_url($stable);
$printedPng = qr_png(qr_matrix($printedUrl));
$beforeDeletionBackup = make_backup_zip();
$stable = contact_form_values(['vorname'=>'Neue', 'nachname'=>'Person', 'field_phone'=>'+491234'], $config, $stable);
save_contacts([$stable]);
feature_check(contact_url(load_contacts()[0]) === $printedUrl, 'Rename changed printed QR target');
feature_check((new \chillerlan\QRCode\QRCode())->readFromBlob($printedPng)->data === $printedUrl, 'Previously printed QR no longer decodes');
feature_check(trash_contact('qr') && !load_contacts(), 'Trashed QR target remains active');
feature_check(make_contact_id('Quinn', 'Roth', []) !== 'qr', 'Trashed QR ID reused');
feature_check(restore_trashed_contact('qr'), 'QR contact cannot be restored');
feature_check(contact_url(load_contacts()[0]) === $printedUrl, 'Restored QR target changed');
feature_check(trash_contact('qr') && purge_trashed_contact('qr'), 'QR contact purge failed');
feature_check(!restore_trashed_contact('qr') && !trashed_contacts() && !load_contacts(), 'Purged QR contact remains visible or restorable');
$records = reserved_contact_records();
$retired = array_values(array_filter($records, static fn($record) => $record['id'] === 'qr'))[0];
feature_check(array_keys($retired) === ['id', '_deleted_at', '_purged_at'], 'Purged contact retained personal data');
feature_check(make_contact_id('Quinn', 'Roth', []) !== 'qr', 'Purged QR ID reused');
try {
    save_contacts([['id'=>'qr', 'vorname'=>'Different owner']]);
    feature_check(false, 'Explicitly retired QR ID accepted');
} catch (RuntimeException $error) {feature_check(true, 'Retired QR ID blocked');}
save_contacts([['id'=>'qr2', 'vorname'=>'Quinn', 'nachname'=>'Roth']]);
feature_check(in_array('qr', array_column(reserved_contact_records(), 'id'), true), 'Ordinary save lost retired QR ID');
$afterDeletionBackup = make_backup_zip();
feature_check(restore_backup_zip($afterDeletionBackup), 'Backup containing retired QR ID rejected');
feature_check(make_contact_id('Quinn', 'Roth', load_contacts()) !== 'qr', 'Backup restore lost retired QR ID');
// A backup without this target must not silently free the permanently deleted ID.
save_contacts([]);
feature_check(restore_backup_zip($withoutTargetBackup) && make_contact_id('Quinn', 'Roth', []) !== 'qr', 'Restore freed a retired QR target');
// Explicit recovery of the original contact from its backup keeps its printed target.
feature_check(restore_backup_zip($beforeDeletionBackup), 'Original QR contact backup restore failed');
feature_check(contact_url(load_contacts()[0]) === $printedUrl, 'Backup recovery changed printed QR target');
echo "OK: $checks feature checks passed.\n";
} finally {
    feature_remove_tree($fixture);
}
