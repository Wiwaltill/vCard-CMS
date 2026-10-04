<?php

// Serialize complete read/modify/write requests, including API and config migration.
// Keep the lock file stable: JSON files themselves are replaced atomically.
if (PHP_SAPI !== 'cli') {
    $storageLock = fopen(__DIR__ . '/../../data/.storage.lock', 'c');
    if (!$storageLock || !flock($storageLock, LOCK_EX)) {
        http_response_code(503);
        exit('Storage temporarily unavailable.');
    }
    register_shutdown_function(static function () use ($storageLock) {
        flock($storageLock, LOCK_UN);
        fclose($storageLock);
    });
}

// A killed update must not serve mixed application files. The recovery page stays available.
if (PHP_SAPI !== 'cli' && is_file(__DIR__ . '/../../data/update-in-progress.json')
    && ($_SERVER['SCRIPT_NAME'] ?? '') !== '/admin/update.php') {
    http_response_code(503);
    header('Retry-After: 60');
    header('Cache-Control: no-store');
    exit('Update recovery required. Open /admin/update as administrator.');
}


function data_path(string $file): string
{
    return __DIR__ . '/../../data/' . $file;
}

function load_json_file_path(string $path, array $fallback = []): array
{
    if (!file_exists($path)) {
        return $fallback;
    }

    $json = file_get_contents($path);
    if ($json === false) throw new RuntimeException('Cannot read JSON storage.');
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON storage.');
    return $data;
}

function save_json_file_path(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $tmp = tempnam(dirname($path), '.json-');
    if ($tmp === false) throw new RuntimeException('Cannot create storage file.');
    try {
        if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !chmod($tmp, 0600) || !rename($tmp, $path)) {
            throw new RuntimeException('Cannot save JSON storage.');
        }
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
}

function sample_file_for(string $file): string
{
    if ($file === 'config.json') {
        return 'config.sample.json';
    }

    if ($file === 'contacts.json') {
        return 'contacts.sample.json';
    }

    return $file;
}

function load_json(string $file, array $fallback = []): array
{
    $path = data_path($file);

    if (!file_exists($path)) {
        $samplePath = data_path(sample_file_for($file));

        if (file_exists($samplePath)) {
            $sample = load_json_file_path($samplePath, $fallback);
            save_json_file_path($path, $sample);
            return $sample;
        }

        save_json_file_path($path, $fallback);
        return $fallback;
    }

    $data = load_json_file_path($path, $fallback);
    return $file === 'contacts.json'
        ? array_values(array_filter($data, static fn($contact) => empty($contact['_deleted_at'])))
        : $data;
}

function save_json(string $file, array $data): void
{
    save_json_file_path(data_path($file), $data);
}

function merge_missing_keys(array $current, array $sample): array
{
    foreach ($sample as $key => $value) {
        if (!array_key_exists($key, $current)) {
            $current[$key] = $value;
            continue;
        }

        // Datentypen sind Nutzer-Konfiguration.
        // Sie dürfen bei Updates nicht über numerische Array-Indizes erneut ergänzt werden,
        // sonst tauchen gelöschte Datentypen nach einem Update wieder auf.
        if ($key === 'data_types') {
            continue;
        }

        if (is_array($value) && is_array($current[$key])) {
            $current[$key] = merge_missing_keys($current[$key], $value);
        }
    }

    return $current;
}

function migrate_config_from_sample(): void
{
    $configPath = data_path('config.json');
    $samplePath = data_path('config.sample.json');

    if (!file_exists($configPath) || !file_exists($samplePath)) {
        return;
    }

    $current = load_json_file_path($configPath, []);
    $sample = load_json_file_path($samplePath, []);

    $merged = merge_missing_keys($current, $sample);
    unset($merged['github_url']);

    if ($merged !== $current) {
        save_json_file_path($configPath, $merged);
    }
}

function get_config(): array
{
    migrate_config_from_sample();

    $sample = load_json_file_path(data_path('config.sample.json'), []);

    $defaults = array_merge([
        'company_name' => 'Demo Company',
        'company_color' => '#0d6efd',
        'company_logo' => '',
        'logo_link' => 'https://example.com',
        'privacy_url' => 'https://example.com/privacy',
        'imprint_url' => 'https://example.com/imprint',
        'home_redirect_url' => 'https://example.com',
        'contact_email' => 'info@example.com',
        'email_domain' => 'example.com',
        'email_pattern' => 'vorname.nachname',
        'admin_user' => 'admin',
        'admin_password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'installed' => false,
        'language' => 'auto',
        'theme' => 'classic',
        'theme_mode' => 'auto',
        'darkmode_default' => false,
        'pwa_enabled' => true,
        'api_enabled' => true,
        'api_token' => '',
        'data_types' => [
            [
                'key' => 'phone',
                'label' => 'Telefon',
                'type' => 'tel',
                'enabled' => true,
                'builtin' => true,
                'sort' => 10,
                'vcard' => 'TEL;TYPE=CELL'
            ],
            [
                'key' => 'email',
                'label' => 'E-Mail',
                'type' => 'email',
                'enabled' => true,
                'builtin' => true,
                'sort' => 20,
                'vcard' => 'EMAIL'
            ]
        ]
    ], $sample);

    $config = array_merge($defaults, load_json('config.json', $defaults));
    if (empty($config['api_token']) || $config['api_token'] === 'change-me-after-install') {
        $config['api_token'] = bin2hex(random_bytes(24));
        save_json('config.json', $config);
    }
    return $config;
}

function load_contacts(): array
{
    $contacts = load_json('contacts.json', []);

    usort($contacts, function ($a, $b) {
        return strcasecmp(
            ($a['nachname'] ?? '') . ' ' . ($a['vorname'] ?? ''),
            ($b['nachname'] ?? '') . ' ' . ($b['vorname'] ?? '')
        );
    });

    return $contacts;
}

function save_contacts(array $contacts): void
{
    $deleted = reserved_contact_records();
    $reserved = array_column($deleted, 'id');
    foreach ($contacts as $contact) {
        if (in_array($contact['id'], $reserved, true)) throw new RuntimeException('Contact ID is reserved by a deleted contact.');
    }
    save_json('contacts.json', array_merge(array_values($contacts), $deleted));
}

function contact_form_values(array $input, array $config, array $existing = []): array
{
    $contact = array_replace(['vorname' => '', 'nachname' => '', 'position' => '', 'bild' => '', 'fields' => []], $existing);
    foreach (['vorname', 'nachname', 'position'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) throw new InvalidArgumentException('Invalid contact field.');
        $contact[$key] = trim($input[$key] ?? '');
    }
    $contact['email_override'] = isset($input['email_override']);
    if (isset($input['email']) && !is_string($input['email'])) throw new InvalidArgumentException('Invalid email.');
    $contact['email'] = $contact['email_override'] ? trim($input['email'] ?? '')
        : generate_email($contact['vorname'], $contact['nachname'], $config);
    foreach (data_types($config) as $type) {
        if ($type['key'] === 'email') continue;
        $value = $input[data_type_input_name($type['key'])] ?? '';
        if (!is_string($value)) throw new InvalidArgumentException('Invalid contact field.');
        set_data_type_value($contact, $type, trim($value));
    }
    return $contact;
}

function contact_list_page(array $contacts, array $config, string $query = '', string $position = '', int $page = 1, int $perPage = 20): array
{
    $query = trim($query);
    $matches = array_values(array_filter($contacts, static function (array $contact) use ($config, $query, $position): bool {
        if ($position !== '' && ($contact['position'] ?? '') !== $position) return false;
        if ($query === '') return true;
        $values = [$contact['id'] ?? '', $contact['vorname'] ?? '', $contact['nachname'] ?? '',
            trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? '')), $contact['position'] ?? '',
            contact_email($contact, $config), $contact['telefon'] ?? ''];
        foreach (data_types($config) as $type) $values[] = data_type_value($contact, $type, $config);
        $haystack = implode(' ', $values);
        return mb_stripos($haystack, $query, 0, 'UTF-8') !== false;
    }));
    $perPage = max(1, min(100, $perPage));
    $total = count($matches);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    return ['contacts' => array_slice($matches, ($page - 1) * $perPage, $perPage), 'total' => $total,
        'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

function make_contact_id(string $vorname, string $nachname, array $contacts, ?string $currentId = null): string
{
    $base = strtolower(substr(trim($vorname), 0, 1) . substr(trim($nachname), 0, 1));
    $base = preg_replace('/[^a-z0-9]/', '', $base);

    if ($base === '') {
        $base = 'xx';
    }

    $base = str_pad($base, 2, 'x');
    $id = $base;
    $counter = 2;

    $existingIds = array_map(function ($contact) {
        return $contact['id'] ?? '';
    }, array_merge($contacts, reserved_contact_records()));

    while (in_array($id, $existingIds, true) && $id !== $currentId) {
        $id = $base . $counter;
        $counter++;
    }

    return $id;
}

function normalize_email_part(string $value): string
{
    $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'
    ];

    $value = strtr(trim($value), $map);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);

    return $value;
}

function generate_email(string $vorname, string $nachname, array $config): string
{
    $first = normalize_email_part($vorname);
    $last = normalize_email_part($nachname);
    $domain = strtolower(trim($config['email_domain'] ?? 'example.com'));
    $domain = preg_replace('/^@/', '', $domain);

    switch ($config['email_pattern'] ?? 'vorname.nachname') {
        case 'vorname':
            $local = $first;
            break;
        case 'nachname':
            $local = $last;
            break;
        case 'initialen':
            $local = substr($first, 0, 1) . substr($last, 0, 1);
            break;
        case 'v.nachname':
            $local = substr($first, 0, 1) . '.' . $last;
            break;
        case 'vorname_nachname':
            $local = $first . '_' . $last;
            break;
        case 'vornamenachname':
            $local = $first . $last;
            break;
        case 'vorname.nachname':
        default:
            $local = $first . '.' . $last;
            break;
    }

    return trim($local, '.') . '@' . $domain;
}

function email_pattern_label(string $pattern): string
{
    $labels = [
        'vorname' => 'vorname@domain.de',
        'nachname' => 'nachname@domain.de',
        'initialen' => 'am@domain.de',
        'vorname.nachname' => 'vorname.nachname@domain.de',
        'v.nachname' => 'v.nachname@domain.de',
        'vorname_nachname' => 'vorname_nachname@domain.de',
        'vornamenachname' => 'vornamenachname@domain.de'
    ];

    return $labels[$pattern] ?? $labels['vorname.nachname'];
}

function contact_email(array $contact, array $config): string
{
    if (!empty($contact['email_override']) && !empty($contact['email'])) {
        return $contact['email'];
    }

    return generate_email($contact['vorname'] ?? '', $contact['nachname'] ?? '', $config);
}


function normalize_data_types(array $dataTypes): array
{
    $normalized = [];

    foreach ($dataTypes as $index => $type) {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower($type['key'] ?? ''));

        if ($key === '') {
            continue;
        }

        $normalized[] = [
            'key' => $key,
            'label' => trim($type['label'] ?? $key),
            'type' => $type['type'] ?? 'text',
            'enabled' => !empty($type['enabled']),
            'builtin' => !empty($type['builtin']),
            'sort' => (int)($type['sort'] ?? (($index + 1) * 10)),
            'vcard' => $type['vcard'] ?? '',
            'platform' => $type['platform'] ?? ''
        ];
    }

    usort($normalized, function ($a, $b) {
        return ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']);
    });

    return array_values($normalized);
}

function data_types(array $config, bool $onlyEnabled = false): array
{
    $types = normalize_data_types($config['data_types'] ?? []);

    if ($onlyEnabled) {
        $types = array_filter($types, function ($type) {
            return !empty($type['enabled']);
        });
    }

    return array_values($types);
}

function data_type_by_key(array $config, string $key): ?array
{
    foreach (data_types($config) as $type) {
        if (($type['key'] ?? '') === $key) {
            return $type;
        }
    }

    return null;
}

function data_type_input_name(string $key): string
{
    return 'field_' . $key;
}

function data_type_value(array $contact, array $type, array $config): string
{
    $key = $type['key'] ?? '';

    if ($key === 'email') {
        return contact_email($contact, $config);
    }

    if ($key === 'phone') {
        return $contact['telefon'] ?? '';
    }

    return $contact['fields'][$key] ?? '';
}

function set_data_type_value(array &$contact, array $type, string $value): void
{
    $key = $type['key'] ?? '';

    if ($key === 'email') {
        $contact['email'] = $value;
        return;
    }

    if ($key === 'phone') {
        $contact['telefon'] = $value;
        return;
    }

    if (!isset($contact['fields']) || !is_array($contact['fields'])) {
        $contact['fields'] = [];
    }

    $contact['fields'][$key] = $value;
}

function data_type_href(array $type, string $value): string
{
    $kind = $type['type'] ?? 'text';

    if ($kind === 'email') {
        return 'mailto:' . $value;
    }

    if ($kind === 'tel') {
        return 'tel:' . $value;
    }

    if ($kind === 'url') {
        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        return 'https://' . $value;
    }

    if ($kind === 'social') {
        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        $username = ltrim(trim($value), '@');
        $platform = strtolower($type['platform'] ?? '');

        $bases = [
            'facebook' => 'https://www.facebook.com/',
            'instagram' => 'https://www.instagram.com/',
            'linkedin' => 'https://www.linkedin.com/in/',
            'tiktok' => 'https://www.tiktok.com/@',
            'x' => 'https://x.com/',
            'youtube' => 'https://www.youtube.com/@',
            'xing' => 'https://www.xing.com/profile/'
        ];

        if (isset($bases[$platform])) {
            return $bases[$platform] . $username;
        }

        return '';
    }

    return '';
}


function data_type_output_value(array $type, string $value): string
{
    $kind = $type['type'] ?? 'text';

    if ($kind === 'url' || $kind === 'social') {
        return data_type_href($type, $value);
    }

    return $value;
}

function data_type_opens_new_tab(array $type): bool
{
    return in_array(($type['type'] ?? ''), ['url', 'social'], true);
}


function data_type_svg_icon(array $type): string
{
    $kind = $type['type'] ?? 'text';

    if ($kind === 'social') {
        $platform = strtolower($type['platform'] ?? '');
        $icons = [
            'facebook' => 'bi-facebook',
            'instagram' => 'bi-instagram',
            'linkedin' => 'bi-linkedin',
            'tiktok' => 'bi-tiktok',
            'x' => 'bi-twitter-x',
            'youtube' => 'bi-youtube',
            'xing' => 'bi-building'
        ];

        $icon = $icons[$platform] ?? 'bi-share';

        return '<i class="bi ' . $icon . ' contact-icon"></i>';
    }

    if ($kind === 'email') {
        return '<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"></path><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"></path></svg>';
    }

    if ($kind === 'tel') {
        return '<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 0 1 2.43 8.326 13.019 13.019 0 0 1 2 5V3.5Z" clip-rule="evenodd"></path></svg>';
    }

    if ($kind === 'url') {
        return '<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a2.5 2.5 0 0 1 3.536 3.536l-1.225 1.224a.75.75 0 0 0 1.061 1.061l1.224-1.225a4 4 0 0 0-5.656-5.656l-3 3a4 4 0 0 0 .225 5.865.75.75 0 0 0 .977-1.138 2.5 2.5 0 0 1-.142-3.667l3-3Z"></path><path d="M11.603 7.963a.75.75 0 0 0-.977 1.138 2.5 2.5 0 0 1 .142 3.667l-3 3a2.5 2.5 0 0 1-3.536-3.536l1.225-1.224a.75.75 0 0 0-1.061-1.061l-1.224 1.225a4 4 0 1 0 5.656 5.656l3-3a4 4 0 0 0-.225-5.865Z"></path></svg>';
    }

    return '<svg class="contact-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0ZM9.25 6.75a.75.75 0 0 1 1.5 0v.5a.75.75 0 0 1-1.5 0v-.5ZM10 9a.75.75 0 0 0-.75.75v3.5a.75.75 0 0 0 1.5 0v-3.5A.75.75 0 0 0 10 9Z" clip-rule="evenodd"></path></svg>';
}

function unique_data_type_key(string $label, array $existingTypes): string
{
    $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'
    ];

    $base = strtr($label, $map);
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9]+/', '_', $base);
    $base = trim($base, '_');

    if ($base === '') {
        $base = 'feld';
    }

    $existing = array_map(function ($type) {
        return $type['key'] ?? '';
    }, $existingTypes);

    $key = $base;
    $counter = 2;

    while (in_array($key, $existing, true)) {
        $key = $base . '_' . $counter;
        $counter++;
    }

    return $key;
}


function valid_image_file(string $path, string $extension): bool
{
    $mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    if (!isset($mimes[$extension]) || !is_file($path) || filesize($path) > 5 * 1024 * 1024) return false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $size = @getimagesize($path);
    return $mime === $mimes[$extension] && $size !== false && $size[0] > 0 && $size[1] > 0
        && $size[0] <= 8192 && $size[1] <= 8192 && $size[0] * $size[1] <= 25000000;
}

function image_memory_available(int $bytes): bool
{
    $limit = trim(ini_get('memory_limit'));
    if ($limit === '-1') return true;
    $value = (int)$limit;
    $unit = strtolower(substr($limit, -1));
    if ($unit === 'g') $value *= 1024 * 1024 * 1024;
    elseif ($unit === 'm') $value *= 1024 * 1024;
    elseif ($unit === 'k') $value *= 1024;
    return memory_get_usage(true) + $bytes + 8 * 1024 * 1024 < $value;
}

function optimize_image_file(string $source, string $target, int $maxDimension = 768): void
{
    $dimensions = getimagesize($source);
    if (!$dimensions) throw new RuntimeException(maintenance_t('Bildmaße konnten nicht gelesen werden.', 'Could not read image dimensions.'));
    $estimatedBytes = $dimensions[0] * $dimensions[1] * 12 + $maxDimension * $maxDimension * 12 + filesize($source);
    if (!image_memory_available($estimatedBytes)) {
        $totalMiB = (int)ceil(($estimatedBytes + memory_get_usage(true) + 8 * 1024 * 1024) / (1024 * 1024));
        $recommendedMiB = max(256, (int)ceil(($totalMiB + 1) / 64) * 64);
        $details = $dimensions[0] . ' × ' . $dimensions[1] . ' px; ' . ini_get('memory_limit');
        throw new RuntimeException(maintenance_t(
            'Zu wenig PHP-Arbeitsspeicher (' . $details . '). Geschätzter Gesamtbedarf: ' . $totalMiB . ' MiB. memory_limit auf mindestens ' . $recommendedMiB . 'M erhöhen oder das Bild verkleinern. Das Original bleibt erhalten.',
            'Insufficient PHP memory (' . $details . '). Estimated total requirement: ' . $totalMiB . ' MiB. Increase memory_limit to at least ' . $recommendedMiB . 'M or resize the image. The original is preserved.'
        ));
    }
    $orientation = 1;
    if ($dimensions[2] === IMAGETYPE_JPEG) {
        $exif = @exif_read_data($source);
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;
    }
    $image = @imagecreatefromstring(file_get_contents($source));
    if (!$image) throw new RuntimeException('Cannot decode image.');
    try {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, $maxDimension / max($width, $height));
        $outWidth = max(1, (int)round($width * $ratio));
        $outHeight = max(1, (int)round($height * $ratio));
        $output = imagecreatetruecolor($outWidth, $outHeight);
        if (!$output) throw new RuntimeException('Cannot resize image.');
        try {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
            if (!imagecopyresampled($output, $image, 0, 0, 0, 0, $outWidth, $outHeight, $width, $height)) throw new RuntimeException('Cannot resize image.');
            imagedestroy($image);
            $image = null;
            if (in_array($orientation, [2, 5, 7], true)) imageflip($output, IMG_FLIP_HORIZONTAL);
            elseif ($orientation === 4) imageflip($output, IMG_FLIP_VERTICAL);
            $angles = [3 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90];
            if (isset($angles[$orientation])) {
                $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
                $rotated = imagerotate($output, $angles[$orientation], $transparent);
                if (!$rotated) throw new RuntimeException('Cannot orient image.');
                imagedestroy($output);
                $output = $rotated;
                imagesavealpha($output, true);
            }
            if (!imagewebp($output, $target, 82) || !is_file($target) || filesize($target) === 0) {
                throw new RuntimeException('Cannot save optimized image.');
            }
        } finally {
            imagedestroy($output);
        }
    } finally {
        if ($image) imagedestroy($image);
    }
}

function upload_image(string $field, string $prefix): string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    $file = $_FILES[$field];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || !valid_image_file($file['tmp_name'], $extension)) {
        http_response_code(422);
        exit('Invalid image. Use PNG, JPEG or WebP, at most 5 MB and 8192 pixels per side.');
    }
    $basename = preg_replace('/[^a-z0-9_-]/i', '', $prefix) . '-' . bin2hex(random_bytes(16));
    $filename = $basename . '.webp';
    $target = __DIR__ . '/../uploads/' . $filename;
    try {
        optimize_image_file($file['tmp_name'], $target, $field === 'company_logo' ? 1200 : 768);
    } catch (Throwable $error) {
        if (is_file($target)) unlink($target);
        error_log('vCard image optimization failed: ' . get_class($error) . ': ' . $error->getMessage());
        $_SESSION['image_upload_warning'] = $error->getMessage();
        // Optimization is optional: a validated upload must remain usable even
        // when GD/WebP/EXIF support or enough decoding memory is unavailable.
        $filename = $basename . '.' . $extension;
        $target = __DIR__ . '/../uploads/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            http_response_code(500);
            exit('Cannot save uploaded image.');
        }
    }
    return '/uploads/' . $filename;
}

function delete_public_file(?string $publicPath): bool
{
    if (empty($publicPath)) {
        return false;
    }

    if (strpos($publicPath, '/uploads/') !== 0) {
        return false;
    }

    $file = realpath(__DIR__ . '/..' . $publicPath);
    $uploadsDir = realpath(__DIR__ . '/../uploads');

    if (!$file || !$uploadsDir) {
        return false;
    }

    if (strpos($file, $uploadsDir . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    if (is_file($file)) {
        return unlink($file);
    }

    return false;
}

function base_domain_from_host(?string $host = null): string
{
    $host = $host ?: ($_SERVER['HTTP_HOST'] ?? 'example.com');
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    $parts = explode('.', $host);

    if (count($parts) >= 2) {
        return implode('.', array_slice($parts, -2));
    }

    return $host ?: 'example.com';
}

function default_url_for_base_domain(string $baseDomain, string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return 'https://' . $baseDomain . ($path === '/' ? '' : $path);
}

function is_installed(): bool
{
    $config = get_config();
    return !empty($config['installed']);
}

function require_installed(): void
{
    if (!is_installed() && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: /install');
        exit;
    }
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('vcard_admin_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('Cache-Control: no-store');
}

function csrf_field(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf']) . '">';
}

function require_csrf(): void
{
    start_admin_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Invalid form token. Reload the page and try again.');
    }
}

function auth_fingerprint(array $config): string
{
    return hash('sha256', ($config['admin_user'] ?? '') . ':' . ($config['admin_password_hash'] ?? ''));
}

function remember_cookie(string $token, int $expires): void
{
    setcookie('vcard_remember', $token, ['expires' => $expires, 'path' => '/admin',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax']);
}

function is_logged_in(): bool
{
    start_admin_session();
    $config = get_config();
    $fingerprint = auth_fingerprint($config);
    if (isset($_SESSION['admin_fingerprint']) && hash_equals($fingerprint, $_SESSION['admin_fingerprint'])
        && ($_SESSION['admin_expires'] ?? 0) > time()) return true;
    unset($_SESSION['admin_fingerprint']);
    $token = $_COOKIE['vcard_remember'] ?? '';
    $remember = load_json('remember.json', []);
    $key = is_string($token) ? hash('sha256', $token) : '';
    if (isset($remember[$key]) && ($remember[$key]['expires'] ?? 0) > time()
        && hash_equals($fingerprint, $remember[$key]['fingerprint'] ?? '')) {
        session_regenerate_id(true);
        $_SESSION['admin_fingerprint'] = $fingerprint;
        $_SESSION['admin_expires'] = time() + 8 * 3600;
        return true;
    }
    return false;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/login');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf();
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function vcard_escape(?string $value): string
{
    $value = $value ?? '';
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace([";", ",", "\r", "\n"], ["\;", "\,", "", "\\n"], $value);

    return $value;
}

function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function current_url_without_query(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
}

// ---- v8.6 Erweiterungen: i18n, Themes, API, CSV, Backup, QR/PWA ----
function browser_lang(): string
{
    $header = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if (preg_match('/\bde\b|\bde[-_]/', $header)) {
        return 'de';
    }
    if (preg_match('/\ben\b|\ben[-_]/', $header)) {
        return 'en';
    }
    return 'de';
}

function configured_lang(?array $config = null): string
{
    $config = $config ?: get_config();
    $allowed = ['de', 'en'];
    $lang = $config['language'] ?? 'auto';

    if ($lang === 'auto') {
        return browser_lang();
    }

    return in_array($lang, $allowed, true) ? $lang : 'de';
}

function admin_lang(?array $config = null): string
{
    // Admin language must follow the saved setting. The public contact-card
    // language cookie is intentionally ignored here, otherwise a public
    // ?lang=en click can force the whole admin UI to English.
    return configured_lang($config);
}

function app_lang(?array $config = null): string
{
    $config = $config ?: get_config();
    $allowed = ['de', 'en'];

    if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
        if (!headers_sent()) {
            setcookie('vcard_lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/', '', !empty($_SERVER['HTTPS']), true);
        }
        return $_GET['lang'];
    }

    if (isset($_COOKIE['vcard_lang']) && in_array($_COOKIE['vcard_lang'], $allowed, true)) {
        return $_COOKIE['vcard_lang'];
    }

    return configured_lang($config);
}

function t(string $key, ?array $config = null): string
{
    static $dict = [
        'de' => [
            'save_contact' => 'Kontakt speichern', 'show_qr' => 'QR-Code anzeigen', 'qr_code' => 'QR-Code',
            'download_png' => 'PNG herunterladen', 'download_svg' => 'SVG herunterladen',
            'imprint' => 'Impressum', 'privacy' => 'Datenschutz', 'contact_not_found' => 'Kontakt nicht gefunden',
            'contact_not_found_text' => 'Der gesuchte Kontakt konnte nicht gefunden werden.', 'write_us' => 'Schreiben Sie uns'
        ],
        'en' => [
            'save_contact' => 'Save contact', 'show_qr' => 'Show QR code', 'qr_code' => 'QR code',
            'download_png' => 'Download PNG', 'download_svg' => 'Download SVG',
            'imprint' => 'Legal notice', 'privacy' => 'Privacy', 'contact_not_found' => 'Contact not found',
            'contact_not_found_text' => 'The requested contact could not be found.', 'write_us' => 'Contact us'
        ]
    ];
    $lang = app_lang($config);
    return $dict[$lang][$key] ?? $dict['de'][$key] ?? $key;
}

function theme_name(?array $config = null): string
{
    $config = $config ?: get_config();
    $allowed = ['classic', 'minimal', 'glass'];
    $theme = $config['theme'] ?? 'classic';
    return in_array($theme, $allowed, true) ? $theme : 'classic';
}

function theme_mode(?array $config = null): string
{
    $config = $config ?: get_config();
    $mode = $config['theme_mode'] ?? (!empty($config['darkmode_default']) ? 'dark' : 'auto');
    return in_array($mode, ['auto', 'light', 'dark'], true) ? $mode : 'auto';
}

function initial_bs_theme(?array $config = null): string
{
    $mode = theme_mode($config);
    return $mode === 'dark' ? 'dark' : 'light';
}

function darkmode_default(?array $config = null): bool
{
    return theme_mode($config) === 'dark';
}

function admin_t(string $key, ?array $config = null): string
{
    static $dict = [
        'de' => [
            'search'=>'Suchen','search_placeholder'=>'Name, Position, E-Mail oder Kontaktfeld','all_positions'=>'Alle Positionen','per_page'=>'Pro Seite','reset_filters'=>'Filter zurücksetzen','no_contacts_found'=>'Keine passenden Kontakte gefunden.','pagination'=>'Kontaktseiten','page'=>'Seite','first_page'=>'Erste Seite','previous_page'=>'Vorherige Seite','next_page'=>'Nächste Seite','last_page'=>'Letzte Seite','live_preview'=>'Live-Vorschau','draft'=>'Entwurf','preview_help'=>'Änderungen erscheinen hier vor dem Speichern.','preview_loading'=>'Vorschau wird aktualisiert …','preview_error'=>'Vorschau nicht verfügbar. Bitte neu laden und erneut anmelden.','stable_link'=>'Dieser Kontaktlink bleibt auch bei Namensänderungen erhalten:','preview_requires_js'=>'Für die Live-Vorschau bitte JavaScript aktivieren.',
            'contacts'=>'Kontakte','data_types'=>'Datentypen','settings'=>'Einstellungen','logout'=>'Logout','backup'=>'Backup','csv'=>'CSV','api'=>'API',
            'appearance_language'=>'Darstellung & Sprache','language'=>'Sprache','language_auto'=>'Automatisch nach Browser','german'=>'Deutsch','english'=>'English','theme'=>'Theme','color_mode'=>'Farbmodus','auto'=>'Auto','light'=>'Hell','dark'=>'Dunkel','pwa_enable'=>'PWA aktivieren','save'=>'Speichern',
            'company_data'=>'Firmendaten','links'=>'Links','email_auto'=>'E-Mail Automatik','user_admin'=>'Nutzerverwaltung','saved'=>'Einstellungen gespeichert.',
            'new_contact'=>'Neuer Kontakt','edit_contact'=>'Kontakt bearbeiten','delete_contact'=>'Kontakt löschen','delete_contact_confirm'=>'Soll der Kontakt in den Papierkorb verschoben werden?','cancel'=>'Abbrechen','delete'=>'Löschen','actions'=>'Aktionen','last_name'=>'Nachname','first_name'=>'Vorname','email'=>'E-Mail','url'=>'URL','manual'=>'Manuell','position'=>'Position','phone'=>'Telefon','employee_photo'=>'Mitarbeiterfoto','username_or_url'=>'Username oder vollständige Profil-URL eintragen.','email_preview'=>'Live-Vorschau der automatisch generierten Adresse','override_email'=>'Automatische E-Mail überschreiben','image_replace'=>'Neues Bild ersetzt die alte Datei automatisch.','automatic'=>'Automatisch','delete_file_confirm'=>'wirklich löschen?','not_found'=>'Nicht gefunden','imported_contacts'=>'Kontakte importiert/aktualisiert.',
            'username'=>'Benutzername','password'=>'Passwort','remember_login'=>'Eingeloggt bleiben','login'=>'Einloggen','login_failed'=>'Login fehlgeschlagen.',
            'company_name'=>'Firmenname','company_color'=>'Firmenfarbe','company_logo'=>'Firmenlogo','logo_delete_confirm'=>'Firmenlogo wirklich löschen?','logo_replace'=>'Ein neues Logo ersetzt die alte Datei automatisch.','logo_link'=>'Logo-Link','home_redirect'=>'Startseiten-Weiterleitung','home_redirect_help'=>'Diese URL wird geöffnet, wenn die Root-Domain aufgerufen wird.','contact_email_404'=>'Sammelmail für 404-Seite','imprint_link'=>'Impressum Link','privacy_link'=>'Datenschutz Link','mail_domain'=>'Mail-Domain hinter dem @','mail_pattern'=>'Schema vor dem @','admin_username'=>'Admin Benutzername','new_password'=>'Neues Passwort','password_empty_help'=>'Leer lassen, wenn das Passwort nicht geändert werden soll.',
            'api_saved'=>'API-Einstellungen gespeichert.','api_enable'=>'REST API aktivieren','api_token'=>'API Token','regen_token'=>'Token neu erzeugen','endpoints_with_header'=>'Endpoints mit Header',
            'backup_restore'=>'Backup / Restore','create_backup'=>'Backup erstellen','backup_export_help'=>'Exportiert Konfiguration, Kontakte und Uploads als ZIP.','download_backup'=>'Backup herunterladen','restore'=>'Restore','restore_confirm'=>'Backup wirklich einspielen? Bestehende Daten werden überschrieben.','restore_backup'=>'Backup wiederherstellen','stored_backups'=>'Gespeicherte Backups','backup_file'=>'Datei','created_at'=>'Erstellt am','file_size'=>'Größe','no_backups'=>'Noch keine gespeicherten Backups vorhanden.','delete_backup_confirm'=>'Backup wirklich vom Webserver löschen?','backup_deleted'=>'Backup wurde gelöscht.','backup_delete_failed'=>'Backup konnte nicht gelöscht werden.','backup_restored'=>'Backup wurde wiederhergestellt.','backup_restore_failed'=>'Backup konnte nicht wiederhergestellt werden.','backup_created'=>'Backup wurde erstellt.',
            'datatypes_saved'=>'Datentypen gespeichert.','vcard_fields_note'=>'Hinweis zu vCard-Feldern:','vcard_fields_help'=>'Mehr Informationen zu möglichen vCard-Feldern und deren Bedeutung findest du auf ','sort_and_show_datatypes'=>'Datentypen sortieren und anzeigen','order'=>'Reihenfolge','label'=>'Bezeichnung','type'=>'Typ','show'=>'Anzeigen','vcard_field_optional'=>'vCard-Feld optional','system_field'=>'Systemfeld','drag_help'=>'Die Reihenfolge kann per Drag & Drop geändert werden. Systemfelder können nicht gelöscht, aber ausgeblendet werden.','add_datatype'=>'Neuen Datentyp hinzufügen','add_datatype_button'=>'Datentyp hinzufügen','social_platform'=>'Social-Media-Plattform','please_choose'=>'Bitte wählen','social_auto_help'=>'Bei Social Media werden Bezeichnung, Key und vCard-Feld automatisch gesetzt, z.B.','social_username_help'=>'Bei Social Media reicht im Kontaktformular später der Username. Beispiel:',
            'text'=>'Text','website'=>'Website','social_media'=>'Social Media','regen_token'=>'Token neu erzeugen','login_title'=>'Admin Login','first_name_pattern'=>'Vorname','last_name_pattern'=>'Nachname','initials_pattern'=>'Initialen','first_last_pattern'=>'Vorname.Nachname','initial_last_pattern'=>'Initial.Nachname','first_last_underscore_pattern'=>'Vorname_Nachname','firstlast_pattern'=>'VornameNachname','key'=>'Key','vcard_field'=>'vCard-Feld','csv_import_export'=>'CSV Import/Export','export_contacts_csv'=>'Kontakte als CSV exportieren','import_csv'=>'CSV importieren','csv_import_help'=>'Trennzeichen: Semikolon. Vorhandene Kontakte werden über die Spalte id aktualisiert.','start_import'=>'Import starten'
        ],
        'en' => [
            'search'=>'Search','search_placeholder'=>'Name, position, email or contact field','all_positions'=>'All positions','per_page'=>'Per page','reset_filters'=>'Reset filters','no_contacts_found'=>'No matching contacts found.','pagination'=>'Contact pages','page'=>'Page','first_page'=>'First page','previous_page'=>'Previous page','next_page'=>'Next page','last_page'=>'Last page','live_preview'=>'Live preview','draft'=>'Draft','preview_help'=>'See your changes here before saving.','preview_loading'=>'Updating preview …','preview_error'=>'Preview unavailable. Please reload and sign in again.','stable_link'=>'This contact link stays the same when the name changes:','preview_requires_js'=>'Enable JavaScript to see the live preview.',
            'contacts'=>'Contacts','data_types'=>'Data types','settings'=>'Settings','logout'=>'Logout','backup'=>'Backup','csv'=>'CSV','api'=>'API',
            'appearance_language'=>'Appearance & language','language'=>'Language','language_auto'=>'Automatic based on browser','german'=>'German','english'=>'English','theme'=>'Theme','color_mode'=>'Color mode','auto'=>'Auto','light'=>'Light','dark'=>'Dark','pwa_enable'=>'Enable PWA','save'=>'Save',
            'company_data'=>'Company data','links'=>'Links','email_auto'=>'Email automation','user_admin'=>'User administration','saved'=>'Settings saved.',
            'new_contact'=>'New contact','edit_contact'=>'Edit contact','delete_contact'=>'Delete contact','delete_contact_confirm'=>'Do you really want to delete this contact?','cancel'=>'Cancel','delete'=>'Delete','actions'=>'Actions','last_name'=>'Last name','first_name'=>'First name','email'=>'Email','url'=>'URL','manual'=>'Manual','position'=>'Position','phone'=>'Phone','employee_photo'=>'Employee photo','username_or_url'=>'Enter a username or full profile URL.','email_preview'=>'Live preview of the automatically generated address','override_email'=>'Override automatic email','image_replace'=>'A new image automatically replaces the old file.','automatic'=>'Automatic','delete_file_confirm'=>'really delete?','not_found'=>'Not found','imported_contacts'=>'contacts imported/updated.',
            'username'=>'Username','password'=>'Password','remember_login'=>'Stay signed in','login'=>'Log in','login_failed'=>'Login failed.',
            'company_name'=>'Company name','company_color'=>'Company color','company_logo'=>'Company logo','logo_delete_confirm'=>'Really delete company logo?','logo_replace'=>'A new logo automatically replaces the old file.','logo_link'=>'Logo link','home_redirect'=>'Homepage redirect','home_redirect_help'=>'This URL opens when the root domain is requested.','contact_email_404'=>'Contact email for 404 page','imprint_link'=>'Legal notice link','privacy_link'=>'Privacy link','mail_domain'=>'Mail domain after @','mail_pattern'=>'Pattern before @','admin_username'=>'Admin username','new_password'=>'New password','password_empty_help'=>'Leave empty if the password should not be changed.',
            'api_saved'=>'API settings saved.','api_enable'=>'Enable REST API','api_token'=>'API token','regen_token'=>'Regenerate token','endpoints_with_header'=>'Endpoints with header',
            'backup_restore'=>'Backup / Restore','create_backup'=>'Create backup','backup_export_help'=>'Exports configuration, contacts and uploads as ZIP.','download_backup'=>'Download backup','restore'=>'Restore','restore_confirm'=>'Really restore backup? Existing data will be overwritten.','restore_backup'=>'Restore backup','stored_backups'=>'Stored backups','backup_file'=>'File','created_at'=>'Created at','file_size'=>'Size','no_backups'=>'No stored backups yet.','delete_backup_confirm'=>'Really delete backup from the webserver?','backup_deleted'=>'Backup deleted.','backup_delete_failed'=>'Backup could not be deleted.','backup_restored'=>'Backup restored.','backup_restore_failed'=>'Backup could not be restored.','backup_created'=>'Backup created.',
            'datatypes_saved'=>'Data types saved.','vcard_fields_note'=>'Note about vCard fields:','vcard_fields_help'=>'More information about possible vCard fields and their meaning is available on ','sort_and_show_datatypes'=>'Sort and display data types','order'=>'Order','label'=>'Label','type'=>'Type','show'=>'Show','vcard_field_optional'=>'vCard field','system_field'=>'System field','drag_help'=>'The order can be changed by drag & drop. System fields cannot be deleted, but can be hidden.','add_datatype'=>'Add new data type','add_datatype_button'=>'Add data type','social_platform'=>'Social media platform','please_choose'=>'Please choose','social_auto_help'=>'','social_username_help'=>'For social media, the username is enough in the contact form later. Example:',
            'text'=>'Text','website'=>'Website','social_media'=>'Social Media','regen_token'=>'Regenerate token','login_title'=>'Admin login','first_name_pattern'=>'First name','last_name_pattern'=>'Last name','initials_pattern'=>'Initials','first_last_pattern'=>'First.Last','initial_last_pattern'=>'Initial.Last','first_last_underscore_pattern'=>'First_Last','firstlast_pattern'=>'FirstLast','key'=>'Key','vcard_field'=>'vCard field','csv_import_export'=>'CSV Import/Export','export_contacts_csv'=>'Export contacts as CSV','import_csv'=>'Import CSV','csv_import_help'=>'Delimiter: semicolon. Existing contacts are updated using the id column.','start_import'=>'Start import'
        ],
    ];
    $lang = admin_lang($config);
    return $dict[$lang][$key] ?? $dict['de'][$key] ?? $key;
}

function public_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function contact_url(array $contact): string
{
    return public_base_url() . '/' . rawurlencode($contact['id'] ?? '');
}

function api_token(array $config): string
{
    if (empty($config['api_token']) || $config['api_token'] === 'change-me-after-install') {
        $config['api_token'] = bin2hex(random_bytes(24));
        save_json('config.json', $config);
    }
    return $config['api_token'];
}

function require_api_auth(array $config): void
{
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if (!hash_equals(api_token($config), (string)$token)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function csv_columns(array $config): array
{
    $cols = ['id','vorname','nachname','position','bild','email','email_override','telefon'];
    foreach (data_types($config) as $type) {
        $key = $type['key'] ?? '';
        if ($key && !in_array($key, ['email','phone'], true) && !in_array($key, $cols, true)) $cols[] = $key;
    }
    return $cols;
}

function contact_to_csv_row(array $contact, array $config): array
{
    $row = [];
    foreach (csv_columns($config) as $col) {
        if ($col === 'telefon') $row[$col] = $contact['telefon'] ?? '';
        elseif ($col === 'email') $row[$col] = $contact['email'] ?? contact_email($contact, $config);
        elseif ($col === 'email_override') $row[$col] = !empty($contact['email_override']) ? '1' : '0';
        elseif (array_key_exists($col, $contact)) $row[$col] = is_scalar($contact[$col]) ? (string)$contact[$col] : '';
        else $row[$col] = $contact['fields'][$col] ?? '';
    }
    return $row;
}

function csv_row_to_contact(array $row, array $config, array $existing = []): array
{
    $contact = $existing;
    foreach (['id','vorname','nachname','position','bild','email','telefon'] as $col) {
        if (isset($row[$col])) $contact[$col] = trim((string)$row[$col]);
    }
    $contact['email_override'] = !empty($row['email_override']) && !in_array(strtolower((string)$row['email_override']), ['0','false','nein','no'], true);
    if (empty($contact['id'])) $contact['id'] = make_contact_id($contact['vorname'] ?? '', $contact['nachname'] ?? '', load_json('contacts.json', []));
    foreach (data_types($config) as $type) {
        $key = $type['key'] ?? '';
        if ($key && !in_array($key, ['email','phone'], true) && array_key_exists($key, $row)) {
            if (!isset($contact['fields']) || !is_array($contact['fields'])) $contact['fields'] = [];
            $contact['fields'][$key] = trim((string)$row[$key]);
        }
    }
    return $contact;
}

function make_backup_zip(): string
{
    $dir = data_path('backups');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $zipPath = $dir . '/backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return '';
    foreach (['config.json','contacts.json'] as $file) {
        if (file_exists(data_path($file)) && !$zip->addFile(data_path($file), 'data/'.$file)) {
            $zip->close();
            return '';
        }
    }
    $uploads = realpath(__DIR__ . '/../uploads');
    if ($uploads) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isLink() || !$file->isFile() || in_array($file->getFilename(), ['.htaccess', '.gitkeep'], true)) continue;
            if (!$zip->addFile($file->getPathname(), 'public/uploads/'.$file->getFilename())) {
                $zip->close();
                return '';
            }
        }
    }
    if (!$zip->close()) return '';
    chmod($zipPath, 0600);
    return $zipPath;
}

function backup_storage_dir(): string
{
    $dir = data_path('backups');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir;
}

function backup_file_path(string $name): string
{
    $name = basename($name);
    if (!preg_match('/^backup-[0-9]{8}-[0-9]{6}(?:-[a-f0-9]{8})?\.zip$/', $name)) return '';
    $path = backup_storage_dir() . '/' . $name;
    return is_file($path) ? $path : '';
}

function list_backup_zips(): array
{
    $files = [];
    foreach (glob(backup_storage_dir() . '/backup-*.zip') ?: [] as $path) {
        if (!is_file($path)) continue;
        $files[] = [
            'name' => basename($path),
            'path' => $path,
            'created' => filemtime($path) ?: 0,
            'size' => filesize($path) ?: 0,
        ];
    }
    usort($files, fn($a, $b) => ($b['created'] <=> $a['created']));
    return $files;
}

function delete_backup_zip(string $name): bool
{
    $path = backup_file_path($name);
    return $path !== '' && @unlink($path);
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return $bytes . ' B';
}

function valid_backup_config(array $config): bool
{
    if (!is_string($config['admin_user'] ?? null) || !is_string($config['admin_password_hash'] ?? null)
        || empty(password_get_info($config['admin_password_hash'])['algo'])
        || !is_array($config['data_types'] ?? null)) return false;
    foreach ($config as $key => $value) {
        if ($key !== 'data_types' && !is_scalar($value) && $value !== null) return false;
    }
    foreach ($config['data_types'] as $type) {
        if (!is_array($type)) return false;
        foreach ($type as $value) if (!is_scalar($value) && $value !== null) return false;
    }
    return true;
}

function valid_backup_contacts(array $contacts): bool
{
    if ($contacts !== [] && array_keys($contacts) !== range(0, count($contacts) - 1)) return false;
    $seen = [];
    foreach ($contacts as $contact) {
        if (!is_array($contact) || !is_string($contact['id'] ?? null)
            || !preg_match('/^[a-z0-9]{2,20}$/D', $contact['id']) || isset($seen[$contact['id']])) return false;
        $seen[$contact['id']] = true;
        foreach ($contact as $key => $value) {
            if ($key === 'fields') {
                if (!is_array($value)) return false;
                foreach ($value as $field) if (!is_string($field)) return false;
            } elseif (!is_scalar($value) && $value !== null) return false;
        }
    }
    return true;
}

function snapshot_restore_target(string $target): ?string
{
    if (!is_file($target)) return null;
    $snapshot = tempnam(sys_get_temp_dir(), 'vcard-rollback-');
    if (!$snapshot || !copy($target, $snapshot)) {
        if ($snapshot && is_file($snapshot)) unlink($snapshot);
        throw new RuntimeException('Cannot prepare restore rollback.');
    }
    return $snapshot;
}

function restore_backup_zip(string $tmp): bool
{
    if (!is_file($tmp) || filesize($tmp) > 50 * 1024 * 1024) return false;
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) return false;
    $staged = [];
    $json = [];
    $names = [];
    $rollback = [];
    $committed = false;
    $total = 0;
    try {
        if ($zip->numFiles > 1000) return false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) return false;
            $name = $stat['name'];
            $total += $stat['size'];
            if ($total > 100 * 1024 * 1024 || $stat['size'] > 10 * 1024 * 1024 || isset($names[$name])) return false;
            $names[$name] = true;
            if (in_array($name, ['public/uploads/.gitkeep', 'public/uploads/.htaccess'], true)) continue;
            $isJson = in_array($name, ['data/config.json', 'data/contacts.json'], true);
            // Never extract arbitrary archive paths or executable/SVG files.
            if (!$isJson && !preg_match('~^public/uploads/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)$~D', $name)) return false;
            $contents = $zip->getFromIndex($i, $stat['size'] + 1);
            if ($contents === false || strlen($contents) !== $stat['size']) return false;
            if ($isJson) {
                $value = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($value)) return false;
                $json[basename($name)] = $value;
            } else {
                $path = tempnam(sys_get_temp_dir(), 'vcard-image-');
                if (!$path) return false;
                $staged[$name] = $path;
                if (file_put_contents($path, $contents) !== strlen($contents)
                    || !valid_image_file($path, strtolower(pathinfo($name, PATHINFO_EXTENSION)))) return false;
            }
        }
        if (!isset($json['config.json'], $json['contacts.json'])
            || !valid_backup_config($json['config.json']) || !valid_backup_contacts($json['contacts.json'])) return false;
        // Keep previously retired QR targets absent from an older backup reserved.
        $restoredIds = array_column($json['contacts.json'], 'id');
        foreach (reserved_contact_records() as $record) {
            if (!empty($record['_purged_at']) && !in_array($record['id'], $restoredIds, true)) $json['contacts.json'][] = $record;
        }
        // Validate everything before modifying live data; preserve a rollback snapshot.
        if (make_backup_zip() === '') return false;
        foreach (['config.json', 'contacts.json', 'remember.json'] as $file) {
            $target = data_path($file);
            $rollback[$target] = snapshot_restore_target($target);
        }
        foreach ($staged as $name => $path) {
            $target = __DIR__ . '/../uploads/' . basename($name);
            if (is_link($target)) return false;
            $rollback[$target] = snapshot_restore_target($target);
            $new = tempnam(dirname($target), '.restore-');
            if (!$new) return false;
            try {
                if (!copy($path, $new) || !chmod($new, 0644) || !rename($new, $target)) return false;
            } finally {
                if (is_file($new)) unlink($new);
            }
        }
        save_json('config.json', $json['config.json']);
        save_json('contacts.json', $json['contacts.json']);
        save_json('remember.json', []);
        $committed = true;
        return true;
    } catch (Throwable $error) {
        return false;
    } finally {
        try {
            if (!$committed) {
                foreach ($rollback as $target => $snapshot) {
                    if ($snapshot === null) {
                        if (is_file($target)) unlink($target);
                    } else {
                        $recovery = tempnam(dirname($target), '.restore-');
                        try {
                            if (!$recovery || !copy($snapshot, $recovery) || !rename($recovery, $target)) {
                                throw new RuntimeException('Restore rollback failed. Recover the pre-restore backup.');
                            }
                        } finally {
                            if ($recovery && is_file($recovery)) unlink($recovery);
                        }
                    }
                }
            }
        } finally {
            $zip->close();
            foreach ($staged as $path) if (is_file($path)) unlink($path);
            foreach ($rollback as $snapshot) if ($snapshot !== null && is_file($snapshot)) unlink($snapshot);
        }
    }
}

// Minimal purged records reserve printed QR targets without retaining contact data.
function reserved_contact_records(): array
{
    return array_values(array_filter(load_json_file_path(data_path('contacts.json'), []), static fn($contact) => !empty($contact['_deleted_at'])));
}

function trashed_contacts(): array
{
    return array_values(array_filter(reserved_contact_records(), static fn($contact) => empty($contact['_purged_at'])));
}

function trash_contact(string $id): bool
{
    $contacts = load_json_file_path(data_path('contacts.json'), []);
    foreach ($contacts as &$contact) {
        if (($contact['id'] ?? '') === $id && empty($contact['_deleted_at'])) {
            $contact['_deleted_at'] = gmdate('c');
            save_json('contacts.json', $contacts);
            return true;
        }
    }
    return false;
}

function restore_trashed_contact(string $id): bool
{
    $contacts = load_json_file_path(data_path('contacts.json'), []);
    foreach ($contacts as &$contact) {
        if (($contact['id'] ?? '') === $id && !empty($contact['_deleted_at']) && empty($contact['_purged_at'])) {
            unset($contact['_deleted_at']);
            save_json('contacts.json', $contacts);
            return true;
        }
    }
    return false;
}

function upload_path(string $path): string
{
    if (!preg_match('~^/uploads/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)$~D', $path)) return '';
    $candidate = __DIR__ . '/..' . $path;
    if (is_link($candidate) || !is_file($candidate)) return '';
    return $candidate;
}

function remove_unreferenced_image(string $path): void
{
    if (($path === '') || (get_config()['company_logo'] ?? '') === $path) return;
    foreach (load_json_file_path(data_path('contacts.json'), []) as $contact) {
        if (($contact['bild'] ?? '') === $path) return;
    }
    delete_public_file($path);
}

function purge_trashed_contact(string $id): bool
{
    $contacts = load_json_file_path(data_path('contacts.json'), []);
    foreach ($contacts as $key => $contact) {
        if (($contact['id'] ?? '') === $id && !empty($contact['_deleted_at']) && empty($contact['_purged_at'])) {
            $contacts[$key] = ['id' => $id, '_deleted_at' => $contact['_deleted_at'], '_purged_at' => gmdate('c')];
            save_json('contacts.json', array_values($contacts));
            remove_unreferenced_image($contact['bild'] ?? '');
            return true;
        }
    }
    return false;
}

function maintenance_t(string $de, string $en): string
{
    return admin_lang(get_config()) === 'en' ? $en : $de;
}

function server_checks(): array
{
    $checks = [
        ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>=')],
        ['memory_limit', ini_get('memory_limit'), ini_get('memory_limit') === '-1' || ini_bytes(ini_get('memory_limit')) >= 128 * 1024 * 1024,
            maintenance_t('Empfohlen: mindestens 128M mit der Verkleinerung im Browser. 192M reichen für die vorbereiteten Profilbilder und Logos. Große Originale werden vor dem Upload oder während der Migration im Browser verkleinert. Ohne Browser-Verkleinerung kann die serverseitige Verarbeitung eines Originals mehr Speicher benötigen.', 'Recommended: at least 128M with browser resizing. 192M is sufficient for prepared profile photos and logos. Large originals are resized in the browser before upload or during migration. Without browser resizing, server processing of an original may need more memory.')],
        ['upload_max_filesize', ini_get('upload_max_filesize'), ini_bytes(ini_get('upload_max_filesize')) >= 5 * 1024 * 1024],
        ['post_max_size', ini_get('post_max_size'), ini_bytes(ini_get('post_max_size')) === 0 || ini_bytes(ini_get('post_max_size')) >= 6 * 1024 * 1024],
    ];
    foreach (['fileinfo', 'gd', 'exif', 'mbstring', 'zip'] as $extension) {
        $checks[] = [$extension, extension_loaded($extension) ? 'OK' : maintenance_t('Fehlt', 'Missing'), extension_loaded($extension)];
    }
    $gd = function_exists('gd_info') ? gd_info() : [];
    foreach (['JPEG' => 'imagecreatefromjpeg', 'PNG' => 'imagecreatefrompng', 'WebP' => 'imagewebp'] as $label => $function) {
        $supported = function_exists($function) && !empty($gd[$label . ' Support']);
        $checks[] = [$label, $supported ? 'OK' : maintenance_t('Fehlt', 'Missing'), $supported];
    }
    foreach (['data/' => data_path(''), 'uploads/' => __DIR__ . '/../uploads', 'backups/' => data_path('backups')] as $label => $path) {
        $writable = is_dir($path) ? is_writable($path) : is_writable(dirname(rtrim($path, '/')));
        $checks[] = [$label, $writable ? maintenance_t('Beschreibbar', 'Writable') : maintenance_t('Nicht beschreibbar', 'Not writable'), $writable];
    }
    return $checks;
}

function ini_bytes(string $value): int
{
    $number = (int)$value;
    switch (strtolower(substr(trim($value), -1))) {
        case 'g': return $number * 1024 * 1024 * 1024;
        case 'm': return $number * 1024 * 1024;
        case 'k': return $number * 1024;
        default: return $number;
    }
}

function start_image_optimization(): array
{
    $previous = load_json_file_path(data_path('image-optimization.json'), []);
    if (!empty($previous) && $previous['done'] < count($previous['items'])) return $previous;
    if (!function_exists('imagewebp')) throw new RuntimeException(maintenance_t('GD mit WebP wird benötigt.', 'GD with WebP is required.'));
    $backup = make_backup_zip();
    if (!$backup) throw new RuntimeException(maintenance_t('Backup fehlgeschlagen. Es wurden keine Bilder verändert.', 'Backup failed. No images were changed.'));
    $items = [];
    foreach (load_json_file_path(data_path('contacts.json'), []) as $contact) {
        if (!empty($contact['bild'])) $items[] = ['id' => $contact['id'], 'path' => $contact['bild'], 'label' => trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''))];
    }
    $config = get_config();
    if (!empty($config['company_logo'])) $items[] = ['id' => null, 'path' => $config['company_logo'], 'label' => maintenance_t('Firmenlogo', 'Company logo')];
    $job = ['id' => bin2hex(random_bytes(12)), 'backup' => basename($backup), 'done' => 0, 'items' => $items, 'report' => []];
    save_json('image-optimization.json', $job);
    return $job;
}

function step_image_optimization(string $jobId, ?array $prepared = null, ?int $expectedDone = null): array
{
    if ($prepared !== null && $expectedDone === null) throw new RuntimeException(maintenance_t('Für vorbereitete Bilder fehlt der Fortschrittsstand.', 'Prepared image is missing the progress position.'));
    $job = load_json_file_path(data_path('image-optimization.json'), []);
    if (!$job || !hash_equals($job['id'], $jobId)) throw new RuntimeException(maintenance_t('Dieser Durchlauf ist nicht mehr aktuell.', 'This run is no longer current.'));
    if ($job['done'] >= count($job['items']) || ($expectedDone !== null && $job['done'] !== $expectedDone)) return $job;
    $item = $job['items'][$job['done']];
    $status = 'skipped';
    $code = '';
    $reason = '';
    $target = '';
    $processing = false;
    try {
        $contacts = load_json_file_path(data_path('contacts.json'), []);
        $config = get_config();
        $key = null;
        foreach ($contacts as $index => $contact) if ($contact['id'] === $item['id']) $key = $index;
        $currentPath = $item['id'] === null ? ($config['company_logo'] ?? '') : ($key !== null ? ($contacts[$key]['bild'] ?? '') : '');
        if ($currentPath !== $item['path']) throw new RuntimeException(maintenance_t('Bild inzwischen geändert oder Kontakt entfernt.', 'Image changed or contact removed meanwhile.'));
        $source = upload_path($item['path']);
        if (!$source || !valid_image_file($source, strtolower(pathinfo($source, PATHINFO_EXTENSION)))) throw new RuntimeException(maintenance_t('Datei fehlt oder Bild ist ungültig.', 'File missing or image invalid.'));
        $limit = $item['id'] === null ? 1200 : 768;
        $size = getimagesize($source);
        if ($size[2] === IMAGETYPE_WEBP && max($size[0], $size[1]) <= $limit) {
            $code = 'already_optimized';
            throw new RuntimeException(maintenance_t('Bereits im Zielformat und innerhalb der Zielgröße.', 'Already in target format and within target dimensions.'));
        }
        $path = '/uploads/optimized-' . bin2hex(random_bytes(16)) . '.webp';
        $target = __DIR__ . '/..' . $path;
        $processing = true;
        $input = $source;
        if ($prepared !== null) {
            $extension = strtolower(pathinfo($prepared['name'] ?? '', PATHINFO_EXTENSION));
            $tmp = $prepared['tmp_name'] ?? '';
            $dimensions = is_string($tmp) && is_file($tmp) ? @getimagesize($tmp) : false;
            if (($prepared['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)
                || !valid_image_file($tmp, $extension) || !$dimensions || max($dimensions[0], $dimensions[1]) > $limit) {
                throw new RuntimeException(maintenance_t('Das vorbereitete Bild ist ungültig oder zu groß. Das Original bleibt erhalten.', 'Prepared image is invalid or too large. The original is preserved.'));
            }
            $input = $tmp;
        }
        optimize_image_file($input, $target, $limit);
        if ($item['id'] === null) {
            $config['company_logo'] = $path;
            save_json('config.json', $config);
        } else {
            $contacts[$key]['bild'] = $path;
            save_json('contacts.json', $contacts);
        }
        $status = 'optimized';
        $reason = number_format(filesize($source) / 1024, 0) . ' KB → ' . number_format(filesize($target) / 1024, 0) . ' KB';
        $target = '';
        remove_unreferenced_image($item['path']);
    } catch (Throwable $error) {
        if ($target && is_file($target)) unlink($target);
        $reason = $error->getMessage();
        if ($processing) error_log('vCard batch image optimization: ' . $reason);
    }
    $job['report'][] = ['label' => $item['label'], 'status' => $status, 'code' => $code, 'reason' => $reason];
    $job['done']++;
    save_json('image-optimization.json', $job);
    return $job;
}


function skip_image_optimization(string $jobId, int $expectedDone): array
{
    $job = load_json_file_path(data_path('image-optimization.json'), []);
    if (!$job || !hash_equals($job['id'], $jobId)) throw new RuntimeException(maintenance_t('Dieser Durchlauf ist nicht mehr aktuell.', 'This run is no longer current.'));
    // Never skip the next image when a previous response was lost or another tab advanced.
    if ($job['done'] !== $expectedDone || $job['done'] >= count($job['items'])) return $job;
    $item = $job['items'][$job['done']];
    $job['report'][] = ['label' => $item['label'], 'status' => 'skipped', 'code' => 'manual_skip', 'reason' => maintenance_t('Manuell übersprungen. Das Original bleibt erhalten.', 'Skipped manually. The original is preserved.')];
    $job['done']++;
    save_json('image-optimization.json', $job);
    return $job;
}


function image_optimization_next(array $job): ?array
{
    if ($job['done'] >= count($job['items'])) return null;
    $item = $job['items'][$job['done']];
    $source = upload_path($item['path']);
    if (!$source) return null;
    $size = @getimagesize($source);
    $limit = $item['id'] === null ? 1200 : 768;
    if (!$size || ($size[2] === IMAGETYPE_WEBP && max($size[0], $size[1]) <= $limit)) return null;
    return ['path' => $item['path'], 'max_dimension' => $limit, 'mime' => $size['mime']];
}
