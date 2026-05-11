<?php
require_once '../includes/functions.php';
require_installed();
require_login();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $zip = make_backup_zip();
    if ($zip) { header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.basename($zip).'"'); readfile($zip); exit; }
    $message = 'Backup konnte nicht erstellt werden.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore' && !empty($_FILES['backup']['tmp_name'])) {
    $message = restore_backup_zip($_FILES['backup']['tmp_name']) ? 'Backup wurde wiederhergestellt.' : 'Backup konnte nicht wiederhergestellt werden.';
}
include '../includes/header.php';
?>
<h1 class="mb-4">Backup / Restore</h1>
<?php if ($message): ?><div class="alert alert-info"><?= h($message) ?></div><?php endif; ?>
<div class="row g-4">
<div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Backup erstellen</div><div class="card-body">
<form method="post"><input type="hidden" name="action" value="create"><p>Exportiert Konfiguration, Kontakte und Uploads als ZIP.</p><button class="btn btn-primary"><i class="bi bi-archive"></i> Backup herunterladen</button></form>
</div></div></div>
<div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Restore</div><div class="card-body">
<form method="post" enctype="multipart/form-data" onsubmit="return confirm('Backup wirklich einspielen? Bestehende Daten werden überschrieben.');"><input type="hidden" name="action" value="restore"><input type="file" name="backup" accept=".zip,application/zip" class="form-control mb-3" required><button class="btn btn-warning"><i class="bi bi-arrow-counterclockwise"></i> Backup wiederherstellen</button></form>
</div></div></div>
</div>
<?php include '../includes/footer.php'; ?>
