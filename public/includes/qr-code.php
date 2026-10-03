<?php
require_once __DIR__ . '/vendor/autoload.php';

function qr_matrix(string $text): array
{
    if ($text === '' || strlen($text) > 1024) throw new InvalidArgumentException('Invalid QR content length.');
    $options = new \chillerlan\QRCode\QROptions([
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
        'addQuietzone' => true,
        'quietzoneSize' => 4,
    ]);
    return (new \chillerlan\QRCode\QRCode($options))->addByteSegment($text)->getQRMatrix()->getMatrix(true);
}

function qr_svg(array $matrix): string
{
    $size = count($matrix);
    $path = '';
    foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
        if ($dark) $path .= 'M' . $x . ',' . $y . 'h1v1h-1z';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size
        . '" width="800" height="800" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/>'
        . '<path d="' . $path . '" fill="#000"/></svg>';
}

function qr_png(array $matrix): string
{
    $size = count($matrix);
    $scale = max(1, intdiv(800, $size));
    $image = imagecreatetruecolor($size * $scale, $size * $scale);
    if (!$image) throw new RuntimeException('Cannot create QR image.');
    try {
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
            if ($dark) imagefilledrectangle($image, $x * $scale, $y * $scale, ($x + 1) * $scale - 1, ($y + 1) * $scale - 1, $black);
        }
        ob_start();
        try {
            if (!imagepng($image)) throw new RuntimeException('Cannot encode QR image.');
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    } finally {
        imagedestroy($image);
    }
}
