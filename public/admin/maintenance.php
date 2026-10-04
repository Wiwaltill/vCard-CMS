<?php
// Keep diagnostic output inside the authenticated JSON response.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();
$config = get_config();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $diagnostics = [];
    $responseSent = false;
    $errorReserve = str_repeat(' ', 128 * 1024);
    register_shutdown_function(static function () use (&$responseSent, &$errorReserve) {
        $error = error_get_last();
        if ($responseSent || !$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
        $errorReserve = null;
        http_response_code(500);
        echo json_encode(['error' => 'PHP: ' . $error['message'], 'details' => basename($error['file']) . ':' . $error['line']], JSON_INVALID_UTF8_SUBSTITUTE);
    });
    set_error_handler(static function ($severity, $message, $file, $line) use (&$diagnostics) {
        if (error_reporting() & $severity) $diagnostics[] = $message . ' (' . basename($file) . ':' . $line . ')';
        return false;
    });
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'status') {
            $job = load_json_file_path(data_path('image-optimization.json'), []);
            if (!$job || !is_string($_POST['job_id'] ?? null) || !hash_equals($job['id'], $_POST['job_id'])) throw new RuntimeException(maintenance_t('Dieser Durchlauf ist nicht mehr aktuell.', 'This run is no longer current.'));
        }
        elseif ($action === 'start') $job = start_image_optimization();
        elseif ($action === 'skip' && is_string($_POST['job_id'] ?? null) && filter_var($_POST['expected_done'] ?? null, FILTER_VALIDATE_INT) !== false) $job = skip_image_optimization($_POST['job_id'], (int)$_POST['expected_done']);
        elseif ($action === 'step' && is_string($_POST['job_id'] ?? null)) $job = step_image_optimization($_POST['job_id'], $_FILES['prepared_image'] ?? null, isset($_POST['expected_done']) && filter_var($_POST['expected_done'], FILTER_VALIDATE_INT) !== false ? (int)$_POST['expected_done'] : null);
        else throw new RuntimeException('Invalid action.');
        echo json_encode(['id' => $job['id'], 'done' => $job['done'], 'total' => count($job['items']), 'backup' => $job['backup'], 'report' => $job['report'], 'next' => image_optimization_next($job), 'details' => implode("\n", $diagnostics)], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $error) {
        http_response_code(422);
        error_log('vCard image maintenance: ' . $error->getMessage());
        echo json_encode(['error' => $error->getMessage(), 'details' => implode("\n", array_merge($diagnostics, [get_class($error) . ' (' . basename($error->getFile()) . ':' . $error->getLine() . ')']))], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    $responseSent = true;
    restore_error_handler();
    exit;
}
$job = load_json_file_path(data_path('image-optimization.json'), []);
include __DIR__ . '/../includes/header.php';
?>
<h1><?= h(maintenance_t('Bestehende Bilder optimieren', 'Optimize existing images')) ?></h1>
<p><?= h(maintenance_t('Vor Beginn wird ein ZIP-Backup mit Kontakten und Bildern erstellt. Profilbilder, Bilder im Papierkorb und das Logo werden einzeln verarbeitet. Nicht verarbeitbare Dateien bleiben unverändert. Einen unterbrochenen Durchlauf kannst du fortsetzen.', 'A ZIP backup of contacts and images is created first. Profile photos, photos in the trash and the logo are processed individually. Files that cannot be processed remain unchanged. Interrupted runs can be resumed.')) ?></p>
<p><a href="/admin/backup"><?= h(maintenance_t('Backups herunterladen oder wiederherstellen', 'Download or restore backups')) ?></a></p>
<form id="imageMaintenance" method="post" data-job-id="<?= h($job['id'] ?? '') ?>" data-job-done="<?= (int)($job['done'] ?? 0) ?>" data-job-total="<?= count($job['items'] ?? []) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= h(maintenance_t('Optimierung starten / fortsetzen', 'Start / resume optimization')) ?></button> <button class="btn btn-secondary" type="button" id="pauseOptimization" disabled><?= h(maintenance_t('Nach diesem Bild pausieren', 'Pause after this image')) ?></button> <button class="btn btn-outline-warning" type="button" id="skipOptimization" <?= !$job || $job['done'] >= count($job['items']) ? 'disabled' : '' ?>><?= h(maintenance_t('Aktuelles Bild überspringen', 'Skip current image')) ?></button></form>
<div class="mt-3" id="optimizationStatus" role="status" aria-live="polite"><?php if ($job): ?><?= (int)$job['done'] ?> / <?= count($job['items']) ?> — <?= h($job['done'] >= count($job['items']) ? maintenance_t('Abgeschlossen.', 'Completed.') : maintenance_t('Bereit zum Fortsetzen.', 'Ready to resume.')) ?><?php endif; ?></div>
<details id="optimizationDiagnostics" class="alert alert-warning mt-3" hidden><summary><?= h(maintenance_t('Fehlerdetails', 'Error details')) ?></summary><pre id="optimizationDiagnosticText" class="mt-2 mb-0" style="white-space: pre-wrap; overflow-wrap: anywhere"></pre></details>
<progress class="w-100 my-3" id="optimizationProgress" value="<?= (int)($job['done'] ?? 0) ?>" max="<?= max(1, count($job['items'] ?? [])) ?>"></progress>
<p id="optimizationBackup"><?= $job ? 'Backup: ' . h($job['backup']) : '' ?></p>
<ul id="optimizationReport" class="list-group">
<?php foreach ($job['report'] ?? [] as $entry): ?><li class="list-group-item"><?= h($entry['label']) ?>: <?= h($entry['status'] === 'optimized' ? maintenance_t('Optimiert', 'Optimized') : maintenance_t('Übersprungen', 'Skipped')) ?> — <?= h($entry['reason']) ?></li><?php endforeach; ?>
</ul>
<script src="/assets/js/image-maintenance.js" defer></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
