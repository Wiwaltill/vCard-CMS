# Bundled QR libraries

The original source files are included unchanged, so deployments need no Composer step.

- [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode/tree/6.0.1), version **6.0.1**. See `php-qrcode/LICENSE-MIT`, `php-qrcode/LICENSE-ASL-2.0` and `php-qrcode/NOTICE`.
- [chillerlan/php-settings-container](https://github.com/chillerlan/php-settings-container/tree/3.2.1), version **3.2.1**. See `php-settings-container/LICENSE`.

Only encoding is used by the application. The local wrapper in `../qr-code.php` renders the matrix to SVG or PNG with a four-module quiet zone. Updating these bundled sources requires running the QR roundtrip and application tests under both supported PHP versions.

`packages.json` records the pinned versions used by the read-only GitHub workflow `Vendor update check`. When updating bundled sources, update this manifest and the versions above together, retaining upstream license files. The workflow compares these pins with the latest stable GitHub releases; it never replaces source files automatically.

QR Code 6.0.1 requires PHP 8.2+ and Settings Container ^3.2.1. Settings Container 4.x is outside that supported dependency range and is intentionally not bundled.
