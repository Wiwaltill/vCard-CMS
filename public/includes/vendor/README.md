# Bundled QR libraries

The original source files are included unchanged, so deployments need no Composer step.

- [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode/tree/5.0.5), version **5.0.5**. See `php-qrcode/LICENSE-MIT`, `php-qrcode/LICENSE-ASL-2.0` and `php-qrcode/NOTICE`.
- [chillerlan/php-settings-container](https://github.com/chillerlan/php-settings-container/tree/2.1.6), version **2.1.6**. See `php-settings-container/LICENSE`.

Only encoding is used by the application. The local wrapper in `../qr-code.php` renders the matrix to SVG or PNG with a four-module quiet zone. Updating these bundled sources requires running the QR roundtrip and application tests under both supported PHP versions.
