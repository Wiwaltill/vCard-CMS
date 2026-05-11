<?php
require_once '../includes/functions.php';
require_installed();
require_login();
$config = get_config();
$message = '';

if (isset($_GET['export'])) {
    $contacts = load_contacts();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contacts.csv"');
    $out = fopen('php://output', 'w');
    $cols = csv_columns($config);
    fputcsv($out, $cols, ';');
    foreach ($contacts as $contact) fputcsv($out, array_values(contact_to_csv_row($contact, $config)), ';');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($fh, 0, ';');
    $contacts = load_json('contacts.json', []);
    $byId = [];
    foreach ($contacts as $i => $c) if (!empty($c['id'])) $byId[$c['id']] = $i;
    $count = 0;
    if ($header) {
        while (($values = fgetcsv($fh, 0, ';')) !== false) {
            $row = array_combine($header, array_pad($values, count($header), ''));
            if (!$row) continue;
            $id = trim($row['id'] ?? '');
            $existing = ($id !== '' && isset($byId[$id])) ? $contacts[$byId[$id]] : [];
            $contact = csv_row_to_contact($row, $config, $existing);
            if ($id !== '' && isset($byId[$id])) $contacts[$byId[$id]] = $contact; else $contacts[] = $contact;
            $count++;
        }
    }
    save_contacts($contacts);
    $message = $count . ' Kontakte importiert/aktualisiert.';
}
include '../includes/header.php';
?>
<h1 class="mb-4">CSV Import/Export</h1>
<?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<div class="card shadow-sm mb-4"><div class="card-body">
<a href="/admin/import_export?export=1" class="btn btn-primary"><i class="bi bi-download"></i> Kontakte als CSV exportieren</a>
</div></div>
<div class="card shadow-sm"><div class="card-header">CSV importieren</div><div class="card-body">
<form method="post" enctype="multipart/form-data">
<input type="file" name="csv" accept=".csv,text/csv" class="form-control mb-3" required>
<p class="text-body-secondary small">Trennzeichen: Semikolon. Vorhandene Kontakte werden über die Spalte <code>id</code> aktualisiert.</p>
<button class="btn btn-success"><i class="bi bi-upload"></i> Import starten</button>
</form>
</div></div>
<?php include '../includes/footer.php'; ?>
