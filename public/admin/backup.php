<?php
require_once '../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $zip = make_backup_zip();
        if ($zip) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zip) . '"');
            header('Content-Length: ' . filesize($zip));
            readfile($zip);
            exit;
        }
        $message = admin_t('backup_restore_failed', $config);
    }

    if ($action === 'download_stored') {
        $path = backup_file_path((string)($_POST['file'] ?? ''));
        if ($path && is_file($path)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
        $message = admin_t('backup_restore_failed', $config);
    }

    if ($action === 'restore' && !empty($_FILES['backup']['tmp_name'])) {
        $message = restore_backup_zip($_FILES['backup']['tmp_name']) ? admin_t('backup_restored', $config) : admin_t('backup_restore_failed', $config);
    }

    if ($action === 'restore_stored') {
        $path = backup_file_path((string)($_POST['file'] ?? ''));
        $message = ($path && restore_backup_zip($path)) ? admin_t('backup_restored', $config) : admin_t('backup_restore_failed', $config);
    }

    if ($action === 'delete_stored') {
        $message = delete_backup_zip((string)($_POST['file'] ?? '')) ? admin_t('backup_deleted', $config) : admin_t('backup_delete_failed', $config);
    }
}

$backups = list_backup_zips();
include '../includes/header.php';
?>
<h1 class="mb-4"><?= h(admin_t('backup_restore', $config)) ?></h1>
<?php if ($message): ?><div class="alert alert-info"><?= h($message) ?></div><?php endif; ?>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><?= h(admin_t('create_backup', $config)) ?></div>
            <div class="card-body">
                <form method="post" target="backupDownloadFrame" data-reload-after-download="1"><input type="hidden" name="action" value="create">
                    <p><?= h(admin_t('backup_export_help', $config)) ?></p><button class="btn btn-primary"><i class="bi bi-archive"></i> <?= h(admin_t('download_backup', $config)) ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><?= h(admin_t('restore', $config)) ?></div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" onsubmit="return confirm('<?= h(admin_t('restore_confirm', $config)) ?>');"><input type="hidden" name="action" value="restore"><input type="file" name="backup" accept=".zip,application/zip" class="form-control mb-3" required><button class="btn btn-warning"><i class="bi bi-arrow-counterclockwise"></i> <?= h(admin_t('restore_backup', $config)) ?></button></form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><?= h(admin_t('stored_backups', $config)) ?></div>
    <div class="card-body">
        <?php if (empty($backups)): ?>
            <p class="text-body-secondary mb-0"><?= h(admin_t('no_backups', $config)) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= h(admin_t('backup_file', $config)) ?></th>
                            <th><?= h(admin_t('created_at', $config)) ?></th>
                            <th><?= h(admin_t('file_size', $config)) ?></th>
                            <th width="240"><?= h(admin_t('actions', $config)) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><code><?= h($backup['name']) ?></code></td>
                                <td><?= h(date('d.m.Y H:i:s', $backup['created'])) ?></td>
                                <td><?= h(format_bytes((int)$backup['size'])) ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="post" target="backupDownloadFrame">
                                            <input type="hidden" name="action" value="download_stored">
                                            <input type="hidden" name="file" value="<?= h($backup['name']) ?>">
                                            <button class="btn btn-primary btn-sm" title="<?= h(admin_t('download_backup', $config)) ?>"><i class="bi bi-download"></i></button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('<?= h(admin_t('restore_confirm', $config)) ?>');">
                                            <input type="hidden" name="action" value="restore_stored">
                                            <input type="hidden" name="file" value="<?= h($backup['name']) ?>">
                                            <button class="btn btn-warning btn-sm" title="<?= h(admin_t('restore_backup', $config)) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('<?= h(admin_t('delete_backup_confirm', $config)) ?>');">
                                            <input type="hidden" name="action" value="delete_stored">
                                            <input type="hidden" name="file" value="<?= h($backup['name']) ?>">
                                            <button class="btn btn-danger btn-sm" title="<?= h(admin_t('delete', $config)) ?>"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<iframe name="backupDownloadFrame" class="d-none" id="backupDownloadFrame"></iframe>
<script>
(function () {
    const frame = document.getElementById('backupDownloadFrame');
    if (!frame) return;

    let shouldReload = false;
    document.querySelectorAll('form[data-reload-after-download="1"]').forEach((form) => {
        form.addEventListener('submit', () => {
            shouldReload = true;
        });
    });

    frame.addEventListener('load', () => {
        if (!shouldReload) return;
        shouldReload = false;
        window.location.reload();
    });
})();
</script>
<?php include '../includes/footer.php'; ?>
