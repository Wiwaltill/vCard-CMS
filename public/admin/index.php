<?php

require_once '../includes/functions.php';
require_login();

$config = get_config();
$contacts = load_contacts();

include '../includes/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

<h1>Kontakte</h1>

<a href="/admin/new" class="btn btn-primary">
<i class="bi bi-plus-lg"></i> Neuer Kontakt
</a>

</div>

<table class="table table-bordered bg-white align-middle">

<thead>
<tr>
<th>Nachname</th>
<th>Vorname</th>
<th>E-Mail</th>
<th>URL</th>
<th width="160">Aktionen</th>
</tr>
</thead>

<tbody>

<?php foreach ($contacts as $contact): ?>

<tr>

<td><?= h($contact['nachname']) ?></td>
<td><?= h($contact['vorname']) ?></td>

<td>
<?= h(contact_email($contact, $config)) ?>
<?php if (!empty($contact['email_override'])): ?>
<span class="badge bg-secondary ms-1">manuell</span>
<?php endif; ?>
</td>

<td>
<a href="https://vc.kb-events.eu/<?= h($contact['id']) ?>" target="_blank" rel="noopener">
https://vc.kb-events.eu/<?= h($contact['id']) ?>
</a>
</td>

<td>

<div class="d-flex gap-2">

<a href="/admin/edit?id=<?= h($contact['id']) ?>" class="btn btn-success btn-sm">
<i class="bi bi-pencil"></i>
</a>

<button
class="btn btn-danger btn-sm"
data-bs-toggle="modal"
data-bs-target="#deleteModal<?= h($contact['id']) ?>"
>
<i class="bi bi-trash"></i>
</button>

</div>

<div class="modal fade" id="deleteModal<?= h($contact['id']) ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Kontakt löschen</h5>
</div>

<div class="modal-body">
Soll der Kontakt <strong><?= h($contact['vorname']) ?> <?= h($contact['nachname']) ?></strong> wirklich gelöscht werden?
</div>

<div class="modal-footer">

<button class="btn btn-secondary" data-bs-dismiss="modal">
Abbrechen
</button>

<a href="/admin/delete?id=<?= h($contact['id']) ?>" class="btn btn-danger">
Löschen
</a>

</div>

</div>

</div>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php include '../includes/footer.php'; ?>
