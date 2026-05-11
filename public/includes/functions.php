<?php

function data_path(string $file): string
{
    return __DIR__ . '/../../data/' . $file;
}

function load_json(string $file, array $fallback = []): array
{
    $path = data_path($file);

    if (!file_exists($path)) {
        file_put_contents($path, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);

    return is_array($data) ? $data : $fallback;
}

function save_json(string $file, array $data): void
{
    $path = data_path($file);

    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function get_config(): array
{
    $config = load_json('config.json', []);

    return array_merge([
        'company_name' => 'KB-Events',
        'company_color' => '#0d6efd',
        'company_logo' => '/uploads/logo.png',
        'github_url' => 'https://github.com/kb-events',
        'logo_link' => 'https://kb-events.eu',
        'admin_user' => 'admin',
        'admin_password_hash' => password_hash('admin123', PASSWORD_DEFAULT)
    ], $config);
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

function is_logged_in(): bool
{
    return isset($_COOKIE['kb_admin_login']) && $_COOKIE['kb_admin_login'] === hash('sha256', 'kb-events-admin');
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}
