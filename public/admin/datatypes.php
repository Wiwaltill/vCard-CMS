<?php

require_once '../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$types = data_types($config);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $updated = [];

        foreach ($types as $type) {
            $key = $type['key'];

            if (isset($_POST['delete'][$key]) && empty($type['builtin'])) {
                continue;
            }

            $updated[] = [
                'key' => $key,
                'label' => trim($_POST['label'][$key] ?? $type['label']),
                'type' => $_POST['type'][$key] ?? $type['type'],
                'enabled' => isset($_POST['enabled'][$key]),
                'builtin' => !empty($type['builtin']),
                'sort' => (int)($_POST['sort'][$key] ?? $type['sort']),
                'vcard' => trim($_POST['vcard'][$key] ?? ($type['vcard'] ?? '')),
                'platform' => ($type['platform'] ?? '')
            ];
        }

        $config['data_types'] = normalize_data_types($updated);
        save_json('config.json', $config);

        header('Location: /admin/datatypes?saved=1');
        exit;
    }

    if ($action === 'add') {
        $kind = $_POST['new_type'] ?? 'text';
        $platform = $_POST['new_platform'] ?? '';

        $platformLabels = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'x' => 'X',
            'youtube' => 'YouTube',
            'xing' => 'Xing'
        ];

        if ($kind === 'social') {
            $label = $platformLabels[$platform] ?? '';
            $key = $platform;
            $vcardField = $label !== '' ? 'URL;TYPE=' . $label : '';
        } else {
            $label = trim($_POST['new_label'] ?? '');
            $key = unique_data_type_key($label, $types);
            $vcardField = '';
            $platform = '';
        }

        if ($label !== '' && $key !== '') {
            $existingKeys = array_map(function ($type) {
                return $type['key'] ?? '';
            }, $types);

            if (in_array($key, $existingKeys, true)) {
                $key = unique_data_type_key($label, $types);
            }

            $types[] = [
                'key' => $key,
                'label' => $label,
                'type' => $kind,
                'enabled' => true,
                'builtin' => false,
                'sort' => ((count($types) + 1) * 10),
                'vcard' => $vcardField,
                'platform' => $platform
            ];

            $config['data_types'] = normalize_data_types($types);
            save_json('config.json', $config);
        }

        header('Location: /admin/datatypes?saved=1');
        exit;
    }
}

$success = isset($_GET['saved']);
$config = get_config();
$types = data_types($config);

include '../includes/header.php';

?>

<h1 class="mb-4">Datentypen</h1>

<div class="alert alert-info">
<strong>Hinweis zu vCard-Feldern:</strong>
Mehr Informationen zu möglichen vCard-Feldern und deren Bedeutung findest du auf
<a href="https://de.wikipedia.org/wiki/VCard" target="_blank" rel="noopener">Wikipedia: vCard</a>.
</div>

<?php if ($success): ?>
<div class="alert alert-success">Datentypen gespeichert.</div>
<?php endif; ?>

<div class="card bg-white shadow-sm mb-4">
<div class="card-header">Datentypen sortieren und anzeigen</div>

<div class="card-body">

<form method="post">
<input type="hidden" name="action" value="save">

<div class="table-responsive">
<table class="table table-bordered align-middle">
<thead>
<tr>
<th style="width:90px;">Sort.</th>
<th>Bezeichnung</th>
<th style="width:170px;">Typ</th>
<th style="width:110px;">Anzeigen</th>
<th style="width:190px;">vCard-Feld optional</th>
<th style="width:90px;">Löschen</th>
</tr>
</thead>

<tbody>
<?php foreach ($types as $type): ?>
<tr>
<td>
<input type="number" name="sort[<?= h($type['key']) ?>]" value="<?= h((string)$type['sort']) ?>" class="form-control">
</td>

<td>
<input type="text" name="label[<?= h($type['key']) ?>]" value="<?= h($type['label']) ?>" class="form-control">
<div class="form-text"><?= h($type['key']) ?><?= !empty($type['builtin']) ? ' · Systemfeld' : '' ?></div>
</td>

<td>
<select name="type[<?= h($type['key']) ?>]" class="form-select" <?= !empty($type['builtin']) ? 'disabled' : '' ?>>
<option value="text" <?= $type['type'] === 'text' ? 'selected' : '' ?>>Text</option>
<option value="tel" <?= $type['type'] === 'tel' ? 'selected' : '' ?>>Telefon</option>
<option value="email" <?= $type['type'] === 'email' ? 'selected' : '' ?>>E-Mail</option>
<option value="url" <?= $type['type'] === 'url' ? 'selected' : '' ?>>URL</option>
<option value="social" <?= $type['type'] === 'social' ? 'selected' : '' ?>>Social Media</option>
</select>
<?php if (!empty($type['builtin'])): ?>
<input type="hidden" name="type[<?= h($type['key']) ?>]" value="<?= h($type['type']) ?>">
<?php endif; ?>
</td>

<td class="text-center">
<input type="checkbox" name="enabled[<?= h($type['key']) ?>]" class="form-check-input" <?= !empty($type['enabled']) ? 'checked' : '' ?>>
</td>

<td>
<input type="text" name="vcard[<?= h($type['key']) ?>]" value="<?= h($type['vcard'] ?? '') ?>" class="form-control" placeholder="z.B. TEL;TYPE=WORK">
</td>

<td class="text-center">
<?php if (empty($type['builtin'])): ?>
<input type="checkbox" name="delete[<?= h($type['key']) ?>]" class="form-check-input">
<?php else: ?>
<span class="text-muted">—</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="form-text mb-3">
Die Reihenfolge wird über die Sortierung bestimmt. Systemfelder können nicht gelöscht, aber ausgeblendet werden.
</div>

<button class="btn btn-success">Speichern</button>
</form>

</div>
</div>

<div class="card bg-white shadow-sm">
<div class="card-header">Neuen Datentyp hinzufügen</div>

<div class="card-body">
<form method="post">
<input type="hidden" name="action" value="add">

<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">Typ</label>
<select name="new_type" id="new_type" class="form-select">
<option value="text">Text</option>
<option value="tel">Telefon</option>
<option value="email">E-Mail</option>
<option value="url">URL</option>
<option value="social">Social Media</option>
</select>
</div>

<div class="col-md-8 mb-3" id="label_group">
<label class="form-label">Bezeichnung</label>
<input type="text" name="new_label" id="new_label" class="form-control" placeholder="z.B. Fax, Festnetz, Website">
</div>

<div class="col-md-8 mb-3 d-none" id="platform_group">
<label class="form-label">Social-Media-Plattform</label>
<select name="new_platform" id="new_platform" class="form-select">
<option value="">Bitte wählen</option>
<option value="facebook">Facebook</option>
<option value="instagram">Instagram</option>
<option value="linkedin">LinkedIn</option>
<option value="tiktok">TikTok</option>
<option value="x">X</option>
<option value="youtube">YouTube</option>
<option value="xing">Xing</option>
</select>
<div class="form-text">
Bei Social Media werden Bezeichnung, Key und vCard-Feld automatisch gesetzt, z.B. <code>URL;TYPE=Instagram</code>.
</div>
</div>
</div>

<div class="form-text mb-3">
Bei Social Media reicht im Kontaktformular später der Username. Beispiel: <code>max.mustermann</code>
</div>

<button class="btn btn-primary">Datentyp hinzufügen</button>
</form>
</div>
</div>

<script>
const typeSelect = document.getElementById('new_type');
const platformGroup = document.getElementById('platform_group');
const platformSelect = document.getElementById('new_platform');
const labelGroup = document.getElementById('label_group');
const labelInput = document.getElementById('new_label');

function toggleNewFieldMode() {
    const isSocial = typeSelect.value === 'social';

    platformGroup.classList.toggle('d-none', !isSocial);
    labelGroup.classList.toggle('d-none', isSocial);

    platformSelect.disabled = !isSocial;
    labelInput.disabled = isSocial;

    if (isSocial) {
        labelInput.value = '';
    } else {
        platformSelect.value = '';
    }
}

typeSelect.addEventListener('change', toggleNewFieldMode);
toggleNewFieldMode();
</script>


<?php include '../includes/footer.php'; ?>
