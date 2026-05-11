<?php

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
    $data = json_decode($json, true);

    return is_array($data) ? $data : $fallback;
}

function save_json_file_path(string $path, array $data): void
{
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
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

    return load_json_file_path($path, $fallback);
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
        'github_url' => '',
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
        'language' => 'de',
        'theme' => 'classic',
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

    $config = load_json('config.json', $defaults);

    return array_merge($defaults, $config);
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
    save_json('contacts.json', array_values($contacts));
}

function make_contact_id(string $vorname, string $nachname, array $contacts, ?string $currentId = null): string
{
    $base = strtolower(substr(trim($vorname), 0, 1) . substr(trim($nachname), 0, 1));
    $base = preg_replace('/[^a-z0-9]/', '', $base);

    if ($base === '') {
        $base = 'xx';
    }

    $id = $base;
    $counter = 2;

    $existingIds = array_map(function ($contact) {
        return $contact['id'] ?? '';
    }, $contacts);

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


function upload_image(string $field, string $prefix): string
{
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }

    $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    if (!in_array($extension, $allowed, true)) {
        return '';
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix);
    $filename = $safePrefix . '-' . time() . '.' . $extension;
    $target = __DIR__ . '/../uploads/' . $filename;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return '/uploads/' . $filename;
    }

    return '';
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

    if (strpos($file, $uploadsDir) !== 0) {
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

function is_logged_in(): bool
{
    return isset($_COOKIE['kb_admin_login']) && $_COOKIE['kb_admin_login'] === hash('sha256', 'kb-events-admin');
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/login');
        exit;
    }
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

// ---- v8.6 Erweiterungen: i18n, Themes, API, CSV, Backup, QR/PWA ----
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

    $lang = $config['language'] ?? ($_COOKIE['vcard_lang'] ?? 'de');
    return in_array($lang, $allowed, true) ? $lang : 'de';
}

function t(string $key, ?array $config = null): string
{
    static $dict = [
        'de' => [
            'save_contact' => 'Kontakt speichern', 'show_qr' => 'QR-Code anzeigen', 'qr_code' => 'QR-Code',
            'download_png' => 'PNG herunterladen', 'download_svg' => 'SVG herunterladen', 'install_app' => 'Zum Homescreen hinzufügen',
            'imprint' => 'Impressum', 'privacy' => 'Datenschutz', 'contact_not_found' => 'Kontakt nicht gefunden',
            'contact_not_found_text' => 'Der gesuchte Kontakt konnte nicht gefunden werden.', 'write_us' => 'Schreiben Sie uns'
        ],
        'en' => [
            'save_contact' => 'Save contact', 'show_qr' => 'Show QR code', 'qr_code' => 'QR code',
            'download_png' => 'Download PNG', 'download_svg' => 'Download SVG', 'install_app' => 'Add to home screen',
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

function darkmode_default(?array $config = null): bool
{
    $config = $config ?: get_config();
    return !empty($config['darkmode_default']);
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
    if (empty($config['api_token'])) {
        $config['api_token'] = bin2hex(random_bytes(24));
        save_json('config.json', $config);
    }
    return $config['api_token'];
}

function require_api_auth(array $config): void
{
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? ($_GET['token'] ?? '');
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
    $zipPath = $dir . '/backup-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) return '';
    foreach (['config.json','contacts.json'] as $file) if (file_exists(data_path($file))) $zip->addFile(data_path($file), 'data/'.$file);
    $uploads = realpath(__DIR__ . '/../uploads');
    if ($uploads) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile()) $zip->addFile($file->getPathname(), 'public/uploads/'.$file->getFilename());
    }
    $zip->close();
    return $zipPath;
}

function restore_backup_zip(string $tmp): bool
{
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) return false;
    $extractBase = sys_get_temp_dir() . '/vcard-restore-' . bin2hex(random_bytes(4));
    mkdir($extractBase, 0775, true);
    $zip->extractTo($extractBase);
    $zip->close();
    foreach (['config.json','contacts.json'] as $file) {
        $src = $extractBase . '/data/' . $file;
        if (is_file($src)) copy($src, data_path($file));
    }
    $uploadsSrc = $extractBase . '/public/uploads';
    if (is_dir($uploadsSrc)) {
        foreach (glob($uploadsSrc . '/*') ?: [] as $src) if (is_file($src)) copy($src, __DIR__ . '/../uploads/' . basename($src));
    }
    return true;
}
