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
        'installed' => false
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
