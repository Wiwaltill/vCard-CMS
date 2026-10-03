<?php
// Pinned bundled libraries; no Composer installation needed on the server.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'chillerlan\\QRCode\\' => __DIR__ . '/php-qrcode/src/',
        'chillerlan\\Settings\\' => __DIR__ . '/php-settings-container/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $relative = substr($class, strlen($prefix));
        if (!preg_match('/^[a-zA-Z0-9_\\\\]+$/D', $relative)) return;
        $path = $directory . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) require_once $path;
        return;
    }
});
