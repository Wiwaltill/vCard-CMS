<?php
require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();
$config = get_config();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    $action = $_POST['action'] ?? '';
    $ok = $action === 'restore' ? restore_trashed_contact($id) : ($action === 'purge' ? purge_trashed_contact($id) : false);
    $_SESSION['trash_message'] = $ok ? maintenance_t('Aktion erfolgreich.', 'Action completed.') : maintenance_t('Kontakt nicht gefunden.', 'Contact not found.');
    header('Location: /admin/trash');
    exit;
}
$message = $_SESSION['trash_message'] ?? '';
unset($_SESSION['trash_message']);
$contacts = trashed_contacts();
include __DIR__ . '/../includes/header.php';
?>
<h1><?= h(maintenance_t('Papierkorb', 'Trash')) ?></h1>
<p><?= h(maintenance_t('Gelöschte Kontakte sind öffentlich nicht erreichbar. Beim Wiederherstellen bleiben ID, Link und Foto erhalten. Es gibt keine automatische Löschung. Nach endgültiger Löschung bleibt nur die ID reserviert, damit alte QR-Codes nicht auf andere Kontakte zeigen.', 'Deleted contacts are not publicly accessible. Restoring preserves the ID, link and photo. There is no automatic deletion. After permanent deletion, only the ID stays reserved so old QR codes cannot point to other contacts.')) ?></p>
<?php if ($message): ?><div class="alert alert-info" role="status"><?= h($message) ?></div><?php endif; ?>
<?php if (!$contacts): ?><p><?= h(maintenance_t('Der Papierkorb ist leer.', 'The trash is empty.')) ?></p><?php endif; ?>
<?php foreach ($contacts as $contact): ?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
<div class="me-auto"><strong><?= h(trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''))) ?></strong><div class="small text-body-secondary"><?= h($contact['id']) ?> · <?= h($contact['_deleted_at']) ?></div></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($contact['id']) ?>"><button name="action" value="restore" class="btn btn-success"><?= h(maintenance_t('Wiederherstellen', 'Restore')) ?></button></form>
<form method="post" onsubmit="return confirm(<?= h(json_encode(maintenance_t('Kontakt und nicht mehr verwendetes Foto endgültig löschen? Das kann nicht rückgängig gemacht werden.', 'Permanently delete the contact and unused photo? This cannot be undone.'))) ?>)"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($contact['id']) ?>"><button name="action" value="purge" class="btn btn-outline-danger"><?= h(maintenance_t('Endgültig löschen', 'Delete permanently')) ?></button></form>
</div></div>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
