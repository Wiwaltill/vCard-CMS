<?php
require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();
require_once __DIR__ . '/../includes/updater.php';
$config = get_config();
$error = ''; $message = '';
$managed = load_json_file_path(data_path('update-managed.json'), []);
$pending = load_json_file_path(data_path('update-in-progress.json'), []);
$recoveryBackup = $pending['backup'] ?? $managed['last_backup'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'download') {
            $path = update_backup_path((string)($_POST['backup'] ?? ''));
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            readfile($path); exit;
        }
        if ($action === 'restore') {
            if ($recoveryBackup === '' || !hash_equals($recoveryBackup, (string)($_POST['backup'] ?? ''))) throw new RuntimeException('Invalid recovery backup.');
            update_rollback($recoveryBackup);
            header('Location: /admin/update?restored=1'); exit;
        }
        if ($action === 'check') {
            update_release();
            $message = update_message('GitHub-Releases wurden geprüft.', 'GitHub releases checked.');
        }
        if ($action === 'install') {
            if ($pending) throw new RuntimeException(update_message('Zuerst den unterbrochenen Durchlauf zurücksetzen.', 'Recover the interrupted update first.'));
            if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive required.');
            $release = update_release();
            if (!hash_equals($release['sha'], (string)($_POST['sha'] ?? '')) || $release['tag'] !== ($_POST['tag'] ?? '')) throw new RuntimeException(update_message('Release wurde geändert. Bitte prüfen und erneut installieren.', 'Release changed. Check and install again.'));
            if (version_compare($release['version'], update_version()['version'], '<=')) throw new RuntimeException(update_message('Kein neueres Release verfügbar.', 'No newer release available.'));
            $tmp = tempnam(update_directory('update-work'), 'download-');
            if (!$tmp) throw new RuntimeException('Cannot create download.');
            try {
                update_http('https://codeload.github.com/' . UPDATE_REPOSITORY . '/zip/' . $release['sha'], $tmp, 32 * 1024 * 1024);
                $result = update_install_archive($tmp, $release['version']);
                save_json('update-result.json', $result);
            } finally { unlink($tmp); }
            header('Location: /admin/update?installed=1'); exit;
        }
    } catch (Throwable $failure) { $error = $failure->getMessage(); }
}
try { $current = update_version()['version']; }
catch (Throwable $failure) { $current = '?'; $error = $error ?: $failure->getMessage(); }
$release = load_json_file_path(data_path('update-release.json'), []);
$pending = load_json_file_path(data_path('update-in-progress.json'), []);
$result = load_json_file_path(data_path('update-result.json'), []);
$available = isset($release['version'], $release['sha']) && $current !== '?' && version_compare($release['version'], $current, '>');
$backups = glob(data_path('update-backups/update-*.zip')) ?: [];
rsort($backups);
if (isset($_GET['installed'])) $message = update_message('Update installiert. Daten-Backup: ', 'Update installed. Data backup: ') . ($result['data_backup'] ?? '');
if (isset($_GET['restored'])) $message = update_message('Vorheriger Code wiederhergestellt. Kontakte, Einstellungen und Bilder wurden nicht zurückgesetzt.', 'Previous code restored. Contacts, settings and images were not rolled back.');
include __DIR__ . '/../includes/header.php';
?>
<h1 class="mb-3"><?= h(update_message('GitHub-Updater', 'GitHub updater')) ?></h1>
<p><?= h(update_message('Veröffentlichte stabile Releases direkt aus dem öffentlichen Repository installieren. Kontakte, Einstellungen, Bilder und bestehende Backups bleiben erhalten.', 'Install published stable releases from the public repository. Contacts, settings, images and existing backups are preserved.')) ?></p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-success" role="status"><?= h($message) ?></div><?php endif; ?>
<?php if ($pending): ?><div class="alert alert-warning" role="alert"><?= h(update_message('Ein Update wurde unterbrochen. Die öffentlichen Seiten sind bis zur Rücksetzung gesperrt. Nutze unten „Vorherigen Code wiederherstellen“.', 'An update was interrupted. Public pages are unavailable until recovery. Use “Restore previous code” below.')) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body">
<p><?= h(update_message('Installierte Version', 'Installed version')) ?>: <strong><?= h($current) ?></strong></p>
<?php if ($release): ?><p><?= h(update_message('Zuletzt geprüftes Release', 'Last checked release')) ?>: <strong><?= h($release['version'] ?? '') ?></strong> · <?= h($release['checked_at'] ?? '') ?></p><p><a href="<?= h('https://github.com/' . UPDATE_REPOSITORY . '/releases') ?>" target="_blank" rel="noopener noreferrer">GitHub Releases</a></p><?php endif; ?>
<form method="post" class="d-inline"><?= csrf_field() ?><button name="action" value="check" class="btn btn-outline-primary"><?= h(update_message('Auf Updates prüfen', 'Check for updates')) ?></button></form>
<?php if ($available && !$pending): ?>
<form method="post" class="d-inline" onsubmit="return confirm(<?= h(json_encode(update_message('Update installieren? Vorher werden Daten und der ersetzte Code gesichert. Eigene Änderungen an Programmdateien können überschrieben werden.', 'Install update? Data and replaced code will be backed up first. Custom application file changes may be overwritten.'))) ?>)"><?= csrf_field() ?><input type="hidden" name="tag" value="<?= h($release['tag']) ?>"><input type="hidden" name="sha" value="<?= h($release['sha']) ?>"><button name="action" value="install" class="btn btn-primary"><?= h(update_message('Release installieren', 'Install release')) ?> <?= h($release['version']) ?></button></form>
<?php elseif ($release && !$pending): ?><p class="mt-3 mb-0"><?= h(update_message('Kein neueres Release verfügbar.', 'No newer release available.')) ?></p><?php endif; ?>
<?php if (!empty($release['notes'])): ?><details class="mt-3"><summary><?= h(update_message('Release-Hinweise', 'Release notes')) ?></summary><pre class="mt-2" style="white-space:pre-wrap"><?= h($release['notes']) ?></pre></details><?php endif; ?>
</div></div>
<h2><?= h(update_message('Code-Backups', 'Code backups')) ?></h2>
<p><?= h(update_message('Rücksetzen stellt nur den vorherigen Programmcode wieder her. Daten-Backups findest du im Backup-Bereich. Alle Code-Backups lassen sich zur manuellen Wiederherstellung herunterladen.', 'Rollback restores only the previous application code. Data backups are available in the backup area. Download any code backup for manual recovery.')) ?> <a href="/admin/backup"><?= h(update_message('Daten-Backups öffnen', 'Open data backups')) ?></a></p>
<?php foreach ($backups as $backup): $name = basename($backup); ?>
<div class="card mb-2"><div class="card-body d-flex flex-wrap align-items-center gap-2"><span class="me-auto"><?= h($name) ?></span>
<form method="post"><?= csrf_field() ?><input type="hidden" name="backup" value="<?= h($name) ?>"><button name="action" value="download" class="btn btn-outline-secondary"><?= h(update_message('Herunterladen', 'Download')) ?></button></form>
<?php if ($name === $recoveryBackup): ?><form method="post" onsubmit="return confirm(<?= h(json_encode(update_message('Vorherigen Code wiederherstellen? Nutzdaten bleiben erhalten.', 'Restore previous code? User data will be preserved.'))) ?>)"><?= csrf_field() ?><input type="hidden" name="backup" value="<?= h($name) ?>"><button name="action" value="restore" class="btn btn-outline-danger"><?= h(update_message('Vorherigen Code wiederherstellen', 'Restore previous code')) ?></button></form><?php endif; ?>
</div></div><?php endforeach; ?>
<?php if (!$backups): ?><p><?= h(update_message('Noch keine Code-Backups vorhanden.', 'No code backups yet.')) ?></p><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
