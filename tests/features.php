<?php
// Run with php tests/features.php (requires gd/WebP, exif, mbstring, fileinfo).
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
$urls = ['https://example.test/em', 'https://cards.example.test:8443/abcdefghijklmnopqrst', 'https://' . str_repeat('long-subdomain.', 8) . 'example.test/em'];
$output = [];
foreach ($urls as $url) {
    $matrix = qr_matrix($url);
    feature_check(count($matrix) >= 29 && !in_array(true, $matrix[0], true), 'QR quiet zone missing');
    $svg = qr_svg($matrix);
    feature_check(strpos($svg, '<svg') === 0 && strpos($svg, '<script') === false, 'Invalid SVG output');
    $png = qr_png($matrix);
    feature_check(substr($png, 0, 8) === "\x89PNG\r\n\x1a\n", 'Invalid PNG output');
    feature_check((new \chillerlan\QRCode\QRCode())->readFromBlob($png)->data === $url, 'Generated QR could not be decoded.');
}
echo "OK: $checks feature checks passed.\n";
} finally {
    feature_remove_tree($fixture);
}
