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

        $typesByKey = [];
        foreach ($types as $type) {
            $typesByKey[$type['key']] = $type;
        }

        $orderedKeys = $_POST['order'] ?? array_keys($typesByKey);
        $position = 10;

        foreach ($orderedKeys as $key) {
            if (!isset($typesByKey[$key])) {
                continue;
            }

            $type = $typesByKey[$key];

            if (isset($_POST['delete'][$key]) && empty($type['builtin'])) {
                continue;
            }

            $updated[] = [
                'key' => $key,
                'label' => trim($_POST['label'][$key] ?? $type['label']),
                'type' => $_POST['type'][$key] ?? $type['type'],
                'enabled' => isset($_POST['enabled'][$key]),
                'builtin' => !empty($type['builtin']),
                'sort' => $position,
                'vcard' => trim($_POST['vcard'][$key] ?? ($type['vcard'] ?? '')),
                'platform' => ($type['platform'] ?? '')
            ];

            $position += 10;
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

<h1 class="mb-4"><?= h(admin_t('data_types', $config)) ?></h1>

<div class="alert alert-info">
    <strong><?= h(admin_t('vcard_fields_note', $config)) ?></strong>
    <?= h(admin_t('vcard_fields_help', $config)) ?>
    <a href="https://de.wikipedia.org/wiki/VCard" target="_blank" rel="noopener">Wikipedia: vCard</a>.
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= h(admin_t('datatypes_saved', $config)) ?></div>
<?php endif; ?>

<div class="card bg-white shadow-sm mb-4">
    <div class="card-header"><?= h(admin_t('sort_and_show_datatypes', $config)) ?></div>

    <div class="card-body">

        <form method="post">
            <input type="hidden" name="action" value="save">

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th style="width:70px;"><?= h(admin_t('order', $config)) ?></th>
                            <th><?= h(admin_t('label', $config)) ?></th>
                            <th style="width:170px;"><?= h(admin_t('type', $config)) ?></th>
                            <th style="width:110px;"><?= h(admin_t('show', $config)) ?></th>
                            <th style="width:190px;"><?= h(admin_t('vcard_field_optional', $config)) ?></th>
                            <th style="width:90px;"><?= h(admin_t('delete', $config)) ?></th>
                        </tr>
                    </thead>

                    <tbody id="datatypeRows">
                        <?php foreach ($types as $type): ?>
                            <tr draggable="true" data-key="<?= h($type['key']) ?>">
                                <td>
                                    <span class="btn btn-light btn-sm drag-handle" title="Drag & Drop"><i class="bi bi-grip-vertical"></i></span>
                                    <input type="hidden" name="order[]" value="<?= h($type['key']) ?>" class="order-input">
                                </td>

                                <td>
                                    <input type="text" name="label[<?= h($type['key']) ?>]" value="<?= h($type['label']) ?>" class="form-control">
                                    <div class="form-text"><?= h($type['key']) ?><?= !empty($type['builtin']) ? ' · ' . h(admin_t('system_field', $config)) : '' ?></div>
                                </td>

                                <td>
                                    <select name="type[<?= h($type['key']) ?>]" class="form-select" <?= !empty($type['builtin']) ? 'disabled' : '' ?>>
                                        <option value="text" <?= $type['type'] === 'text' ? 'selected' : '' ?>><?= h(admin_t('text', $config)) ?></option>
                                        <option value="tel" <?= $type['type'] === 'tel' ? 'selected' : '' ?>><?= h(admin_t('phone', $config)) ?></option>
                                        <option value="email" <?= $type['type'] === 'email' ? 'selected' : '' ?>>E-Mail</option>
                                        <option value="url" <?= $type['type'] === 'url' ? 'selected' : '' ?>>URL</option>
                                        <option value="social" <?= $type['type'] === 'social' ? 'selected' : '' ?>><?= h(admin_t('social_media', $config)) ?></option>
                                    </select>
                                    <?php if (!empty($type['builtin'])): ?>
                                        <input type="hidden" name="type[<?= h($type['key']) ?>]" value="<?= h($type['type']) ?>">
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <input type="checkbox" name="enabled[<?= h($type['key']) ?>]" class="form-check-input" <?= !empty($type['enabled']) ? 'checked' : '' ?>>
                                </td>

                                <td>
                                    <input type="text" name="vcard[<?= h($type['key']) ?>]" value="<?= h($type['vcard'] ?? '') ?>" class="form-control" placeholder="TEL;TYPE=WORK">
                                </td>

                                <td class="text-center">
                                    <?php if (empty($type['builtin'])): ?>
                                        <input type="checkbox" name="delete[<?= h($type['key']) ?>]" class="form-check-input">
                                    <?php else: ?>
                                        <span class="text-body-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-text mb-3">
                Die <?= h(admin_t('order', $config)) ?> kann per Drag & Drop geändert werden. Systemfelder können nicht gelöscht, aber ausgeblendet werden.
            </div>

            <button class="btn btn-success"><?= h(admin_t('save', $config)) ?></button>
        </form>

    </div>
</div>

<div class="card bg-white shadow-sm">
    <div class="card-header"><?= h(admin_t('add_datatype', $config)) ?></div>

    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="add">

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?= h(admin_t('type', $config)) ?></label>
                    <select name="new_type" id="new_type" class="form-select">
                        <option value="text"><?= h(admin_t('text', $config)) ?></option>
                        <option value="tel"><?= h(admin_t('phone', $config)) ?></option>
                        <option value="email">E-Mail</option>
                        <option value="url">URL</option>
                        <option value="social"><?= h(admin_t('social_media', $config)) ?></option>
                    </select>
                </div>

                <div class="col-md-8 mb-3" id="label_group">
                    <label class="form-label"><?= h(admin_t('label', $config)) ?></label>
                    <input type="text" name="new_label" id="new_label" class="form-control" placeholder="Fax, Phone, Website">
                </div>

                <div class="col-md-8 mb-3 d-none" id="platform_group">
                    <label class="form-label"><?= h(admin_t('social_platform', $config)) ?></label>
                    <select name="new_platform" id="new_platform" class="form-select">
                        <option value=""><?= h(admin_t('please_choose', $config)) ?></option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="tiktok">TikTok</option>
                        <option value="x">X</option>
                        <option value="youtube">YouTube</option>
                        <option value="xing">Xing</option>
                    </select>
                    <div class="form-text">
                        <?= h(admin_t('social_auto_help', $config)) ?> <code>URL;TYPE=Instagram</code> <br/>
                        <?= h(admin_t('social_username_help', $config)) ?> <code>max.mustermann</code>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary"><?= h(admin_t('add_datatype_button', $config)) ?></button>
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


<script>
    const tbody = document.getElementById('datatypeRows');
    let dragged = null;

    function syncOrder() {
        [...tbody.querySelectorAll('tr')].forEach(tr => {
            const input = tr.querySelector('.order-input');
            if (input) input.value = tr.dataset.key;
        });
    }
    tbody?.addEventListener('dragstart', e => {
        dragged = e.target.closest('tr');
        e.dataTransfer.effectAllowed = 'move';
    });
    tbody?.addEventListener('dragover', e => {
        e.preventDefault();
        const tr = e.target.closest('tr');
        if (!tr || tr === dragged) return;
        const box = tr.getBoundingClientRect();
        tbody.insertBefore(dragged, e.clientY < box.top + box.height / 2 ? tr : tr.nextSibling);
        syncOrder();
    });
    tbody?.addEventListener('drop', e => {
        e.preventDefault();
        syncOrder();
    });
</script>
<?php include '../includes/footer.php'; ?>