<?php
// This updater only installs published releases from this fixed public repository.
const UPDATE_REPOSITORY = 'Wiwaltill/vCard-CMS';

function update_message(string $de, string $en): string { return maintenance_t($de, $en); }
function update_root(): string { return dirname(__DIR__, 2); }
function update_version(string $file = ''): array {
    $value = json_decode((string)file_get_contents($file ?: __DIR__ . '/version.json'), true);
    if (!is_array($value) || !preg_match('/^\d+\.\d+\.\d+$/D', $value['version'] ?? '')
        || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $value['php_min'] ?? '')) {
        throw new RuntimeException(update_message('Ungültige Versionsdatei.', 'Invalid version manifest.'));
    }
    return $value;
}
function update_directory(string $name): string {
    $path = data_path($name);
    if (is_link($path)) throw new RuntimeException('Unsafe update directory.');
    if (!is_dir($path) && !mkdir($path, 0700, true)) throw new RuntimeException(update_message('Update-Verzeichnis nicht beschreibbar.', 'Update directory is not writable.'));
    return $path;
}
function update_remove_tree(string $path): void {
    if (is_link($path) || !is_dir($path)) { if (file_exists($path) || is_link($path)) unlink($path); return; }
    foreach (new FilesystemIterator($path) as $entry) update_remove_tree($entry->getPathname());
    rmdir($path);
}
function update_http(string $url, string $destination, int $limit): void {
    // No arbitrary URL or redirect from release metadata is ever fetched.
    if (!preg_match('~^https://(?:api\.github\.com/repos/Wiwaltill/vCard-CMS/(?:releases/latest|git/ref/tags/[vV]?\d+\.\d+\.\d+|git/tags/[a-f0-9]{40})|codeload\.github\.com/Wiwaltill/vCard-CMS/zip/[a-f0-9]{40})$~D', $url)) throw new RuntimeException('Invalid update URL.');
    $out = fopen($destination, 'wb');
    if (!$out) throw new RuntimeException('Cannot write download.');
    $size = 0;
    $headers = ['User-Agent: vCard-CMS-Updater', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    try {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>60,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use ($out, &$size, $limit): int {
                    $size += strlen($chunk);
                    return $size <= $limit ? (int)fwrite($out, $chunk) : 0;
                }]);
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
        } else {
            $context = stream_context_create(['http'=>['timeout'=>30, 'follow_location'=>0, 'ignore_errors'=>true, 'header'=>implode("\r\n", $headers)],
                'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
            $input = @fopen($url, 'rb', false, $context);
            $status = preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $match) ? (int)$match[1] : 0;
            $ok = $input !== false;
            if ($input) {
                try {
                    $deadline = microtime(true) + 60;
                    while (!feof($input)) {
                        $chunk = fread($input, 65536);
                        if ($chunk === false || ($chunk === '' && !feof($input)) || microtime(true) > $deadline) { $ok = false; break; }
                        $size += strlen($chunk);
                        if ($size > $limit || fwrite($out, $chunk) !== strlen($chunk)) { $ok = false; break; }
                    }
                } finally { fclose($input); }
            }
        }
        if ($status === 404) throw new RuntimeException(update_message('Noch kein passendes GitHub-Release veröffentlicht.', 'No matching GitHub release has been published yet.'));
        if (!$ok || $status !== 200) throw new RuntimeException(update_message('GitHub-Abruf fehlgeschlagen', 'GitHub request failed') . ' (HTTP ' . $status . ', ' . $size . ' bytes).');
    } finally { fclose($out); }
}
function update_api(string $path): array {
    $tmp = tempnam(sys_get_temp_dir(), 'vcard-update-api-');
    if (!$tmp) throw new RuntimeException('Cannot create temporary file.');
    try {
        update_http('https://api.github.com/repos/' . UPDATE_REPOSITORY . '/' . $path, $tmp, 1024 * 1024);
        $value = json_decode((string)file_get_contents($tmp), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('Invalid GitHub response.');
        return $value;
    } finally { unlink($tmp); }
}
function update_release(?callable $fetch = null): array {
    $fetch = $fetch ?? 'update_api';
    $release = $fetch('releases/latest');
    $tag = $release['tag_name'] ?? '';
    if (!is_string($tag) || !preg_match('/^[vV]?(\d+\.\d+\.\d+)$/D', $tag, $match) || !empty($release['draft']) || !empty($release['prerelease'])) {
        throw new RuntimeException(update_message('Kein gültiges stabiles Release.', 'No valid stable release.'));
    }
    $object = $fetch('git/ref/tags/' . rawurlencode($tag))['object'] ?? [];
    for ($i = 0; $i < 5 && ($object['type'] ?? '') === 'tag'; $i++) {
        if (!preg_match('/^[a-f0-9]{40}$/D', $object['sha'] ?? '')) throw new RuntimeException('Invalid release tag.');
        $object = $fetch('git/tags/' . $object['sha'])['object'] ?? [];
    }
    if (($object['type'] ?? '') !== 'commit' || !preg_match('/^[a-f0-9]{40}$/D', $object['sha'] ?? '')) throw new RuntimeException('Invalid release commit.');
    $result = ['version'=>$match[1], 'tag'=>$tag, 'sha'=>$object['sha'], 'checked_at'=>gmdate('c'),
        'notes'=>mb_substr((string)($release['body'] ?? ''), 0, 12000), 'url'=>'https://github.com/' . UPDATE_REPOSITORY . '/releases/tag/' . rawurlencode($tag)];
    save_json('update-release.json', $result);
    return $result;
}
function update_allowed_path(string $path): bool {
    if ($path === '' || str_contains($path, '\\') || str_contains($path, '//') || str_contains($path, ':') || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('~(?:^|/)\.\.?(/|$)~', $path)) return false;
    if (in_array($path, ['data/config.sample.json', 'data/contacts.sample.json'], true)) return true;
    return str_starts_with($path, 'public/') && !str_starts_with(strtolower($path), 'public/uploads/') && strtolower($path) !== 'public/uploads'
        && !preg_match('~(?:^|/)(?:\.git|\.env[^/]*)(?:/|$)~i', $path);
}
function update_target(string $root, string $path): string {
    if (!update_allowed_path($path)) throw new RuntimeException('Protected or invalid update path.');
    $target = $root;
    foreach (explode('/', $path) as $part) {
        $target .= '/' . $part;
        if (is_link($target)) throw new RuntimeException('Symbolic links cannot be updated.');
    }
    if (file_exists($target) && !is_file($target)) throw new RuntimeException('Update file conflicts with directory.');
    return $target;
}
function update_stage(string $archive, string $stage, string $expectedVersion): array {
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Invalid update ZIP.');
    $files = []; $seen = []; $root = null; $total = 0;
    try {
        if ($zip->numFiles > 6000) throw new RuntimeException('Too many archive entries.');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = $stat['name'];
            if (str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(?:^|/)\.\.?(/|$)~', $name) || isset($seen[$name])) throw new RuntimeException('Unsafe or duplicate ZIP entry.');
            $seen[$name] = true;
            $parts = explode('/', $name, 2);
            if ($root === null) $root = $parts[0];
            if ($root === '' || $parts[0] !== $root) throw new RuntimeException('ZIP must contain one source directory.');
            $zip->getExternalAttributesIndex($i, $opsys, $attributes);
            if (($attributes >> 16 & 0170000) === 0120000) throw new RuntimeException('ZIP symbolic links are forbidden.');
            $total += $stat['size'];
            if ($total > 100 * 1024 * 1024 || $stat['size'] > 8 * 1024 * 1024) throw new RuntimeException('Update archive is too large.');
            $path = $parts[1] ?? '';
            if ($path === '' || str_ends_with($path, '/') || !update_allowed_path($path)) continue;
            $target = update_target($stage, $path);
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('Cannot stage update.');
            $contents = $zip->getFromIndex($i);
            if ($contents === false || strlen($contents) !== $stat['size'] || file_put_contents($target, $contents) !== strlen($contents)) throw new RuntimeException('Cannot read update file.');
            if (str_ends_with($path, '.php')) token_get_all($contents, TOKEN_PARSE);
            $files[] = $path;
        }
        foreach (['public/includes/functions.php','public/includes/version.json','public/includes/updater.php','public/admin/update.php','public/.htaccess'] as $required) {
            if (!in_array($required, $files, true)) throw new RuntimeException('Incomplete vCard-CMS release: ' . $required);
        }
        $version = update_version($stage . '/public/includes/version.json');
        if ($version['version'] !== $expectedVersion) throw new RuntimeException(update_message('Release-Tag und Versionsdatei stimmen nicht überein.', 'Release tag and version manifest do not match.'));
        if (version_compare(PHP_VERSION, $version['php_min'], '<')) throw new RuntimeException('PHP ' . $version['php_min'] . '+ required.');
        return $files;
    } finally { $zip->close(); }
}
function update_write(string $source, string $target): void {
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) throw new RuntimeException('Cannot create update directory.');
    $tmp = tempnam(dirname($target), '.update-');
    if (!$tmp) throw new RuntimeException('Cannot create update file.');
    try {
        if (!copy($source, $tmp) || !chmod($tmp, 0644) || !rename($tmp, $target)) throw new RuntimeException('Cannot replace update file.');
        if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function update_backup_path(string $name): string {
    if (!preg_match('/^update-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.zip$/D', $name)) throw new RuntimeException('Invalid code backup.');
    $path = update_directory('update-backups') . '/' . $name;
    if (!is_file($path) || is_link($path)) throw new RuntimeException('Code backup not found.');
    return $path;
}
function update_code_backup(array $paths): string {
    $name = 'update-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
    $path = update_directory('update-backups') . '/' . $name;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create code backup.');
    $manifest = ['paths'=>[], 'managed'=>load_json_file_path(data_path('update-managed.json'), [])];
    try {
        foreach ($paths as $relative) {
            $target = update_target(update_root(), $relative);
            $manifest['paths'][$relative] = is_file($target);
            if (is_file($target) && !$zip->addFile($target, 'files/' . $relative)) throw new RuntimeException('Cannot back up code.');
        }
        if (!$zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR))) throw new RuntimeException('Cannot back up manifest.');
    } finally { $closed = $zip->close(); }
    if (!$closed || !chmod($path, 0600)) throw new RuntimeException('Cannot finish code backup.');
    return $name;
}
function update_restore_code(string $name): void {
    $path = update_backup_path($name); $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Invalid code backup.');
    $stage = update_directory('update-work') . '/restore-' . bin2hex(random_bytes(6));
    mkdir($stage, 0700);
    try {
        $raw = $zip->getFromName('manifest.json');
        if ($raw === false || strlen($raw) > 1024 * 1024) throw new RuntimeException('Invalid code backup manifest.');
        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest['paths'] ?? null) || count($manifest['paths']) > 6000 || !is_array($manifest['managed'] ?? null)) throw new RuntimeException('Invalid code backup manifest.');
        $total = 0;
        foreach ($manifest['paths'] as $relative => $existed) {
            update_target(update_root(), $relative);
            if (!is_bool($existed)) throw new RuntimeException('Invalid backup entry.');
            if ($existed) {
                $stat = $zip->statName('files/' . $relative);
                if (!$stat || $stat['size'] > 8 * 1024 * 1024 || ($total += $stat['size']) > 100 * 1024 * 1024) throw new RuntimeException('Invalid backup size.');
                $contents = $zip->getFromName('files/' . $relative);
                $target = update_target($stage, $relative);
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                if ($contents === false || file_put_contents($target, $contents) !== strlen($contents)) throw new RuntimeException('Cannot stage code recovery.');
            }
        }
        foreach ($manifest['paths'] as $relative => $existed) {
            $target = update_target(update_root(), $relative);
            if ($existed) update_write($stage . '/' . $relative, $target);
            elseif (is_file($target) && !unlink($target)) throw new RuntimeException('Cannot remove added update file.');
        }
        save_json('update-managed.json', $manifest['managed']);
        if (is_file(data_path('update-in-progress.json'))) unlink(data_path('update-in-progress.json'));
    } finally { $zip->close(); update_remove_tree($stage); }
}
function update_rollback(string $name): void {
    $zip = new ZipArchive();
    if ($zip->open(update_backup_path($name)) !== true) throw new RuntimeException('Invalid recovery archive.');
    try { $manifest = json_decode((string)$zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR); }
    finally { $zip->close(); }
    if (!is_array($manifest['paths'] ?? null)) throw new RuntimeException('Invalid recovery manifest.');
    $safety = update_code_backup(array_keys($manifest['paths']));
    save_json('update-in-progress.json', ['backup'=>$safety, 'version'=>'rollback']);
    try { update_restore_code($name); }
    catch (Throwable $error) {
        try { update_restore_code($safety); }
        catch (Throwable $recovery) { throw new RuntimeException('Rollback failed. Recovery backup: ' . $safety . '. ' . $recovery->getMessage(), 0, $error); }
        throw new RuntimeException(update_message('Rücksetzung fehlgeschlagen; aktueller Code wiederhergestellt. ', 'Rollback failed; current code restored. ') . $error->getMessage(), 0, $error);
    }
}

function update_install_archive(string $archive, string $version, ?callable $write = null): array {
    if (version_compare($version, update_version()['version'], '<=')) throw new RuntimeException(update_message('Das Paket enthält keine neuere Version.', 'The package does not contain a newer version.'));
    $stage = update_directory('update-work') . '/stage-' . bin2hex(random_bytes(6));
    mkdir($stage, 0700);
    $backup = '';
    try {
        $files = update_stage($archive, $stage, $version);
        $managed = load_json_file_path(data_path('update-managed.json'), []);
        if (!is_array($managed['files'] ?? [])) throw new RuntimeException('Invalid managed files.');
        $obsolete = array_diff($managed['files'] ?? [], $files);
        $paths = array_unique(array_merge($files, $obsolete));
        foreach ($paths as $relative) {
            $target = update_target(update_root(), $relative);
            $parent = dirname($target);
            while (!is_dir($parent)) $parent = dirname($parent);
            if (!is_writable($parent) || (is_file($target) && !is_readable($target))) throw new RuntimeException(update_message('Keine Schreibrechte für: ', 'No write permission for: ') . $relative);
        }
        $dataBackup = make_backup_zip();
        if ($dataBackup === '') throw new RuntimeException('Cannot back up contact data.');
        $backup = update_code_backup($paths);
        save_json('update-in-progress.json', ['backup'=>$backup, 'version'=>$version]);
        try {
            $writer = $write ?? 'update_write';
            foreach ($files as $relative) $writer($stage . '/' . $relative, update_target(update_root(), $relative));
            foreach ($obsolete as $relative) {
                $target = update_target(update_root(), $relative);
                if (is_file($target) && !unlink($target)) throw new RuntimeException('Cannot delete obsolete code.');
                if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
            }
            save_json('update-managed.json', ['version'=>$version, 'files'=>$files, 'last_backup'=>$backup]);
            unlink(data_path('update-in-progress.json'));
        } catch (Throwable $error) {
            try { update_restore_code($backup); }
            catch (Throwable $recovery) { throw new RuntimeException(update_message('Update und Rücksetzung fehlgeschlagen. Code-Backup: ', 'Update and rollback failed. Code backup: ') . $backup . '. ' . $recovery->getMessage(), 0, $error); }
            throw new RuntimeException(update_message('Update fehlgeschlagen; vorheriger Code wiederhergestellt. ', 'Update failed; previous code restored. ') . $error->getMessage(), 0, $error);
        }
        return ['code_backup'=>$backup, 'data_backup'=>basename($dataBackup), 'version'=>$version];
    } finally { update_remove_tree($stage); }
}
