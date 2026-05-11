<?php

require_once '../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$contacts = load_contacts();

include '../includes/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h1><?= h(admin_t('contacts', $config)) ?></h1>

    <a href="/admin/new" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> <?= h(admin_t('new_contact', $config)) ?>
    </a>

</div>

<table class="table table-bordered bg-white align-middle">

    <thead>
        <tr>
            <th><?= h(admin_t('last_name', $config)) ?></th>
            <th><?= h(admin_t('first_name', $config)) ?></th>
            <th><?= h(admin_t('email', $config)) ?></th>
            <th><?= h(admin_t('url', $config)) ?></th>
            <th width="160"><?= h(admin_t('actions', $config)) ?></th>
        </tr>
    </thead>

    <tbody>

        <?php foreach ($contacts as $contact): ?>

            <tr>

                <td><?= h($contact['nachname']) ?></td>
                <td><?= h($contact['vorname']) ?></td>

                <td>
                    <a href="mailto:<?= h(contact_email($contact, $config)) ?>">
                        <?= h(contact_email($contact, $config)) ?>
                    </a>
                    <?php if (!empty($contact['email_override'])): ?>
                        <span class="badge bg-secondary ms-1"><?= h(admin_t('manual', $config)) ?></span>
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
                            data-bs-target="#deleteModal<?= h($contact['id']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>

                    </div>

                    <div class="modal fade" id="deleteModal<?= h($contact['id']) ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">

                        <div class="modal-dialog">

                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title"><?= h(admin_t('delete_contact', $config)) ?></h5>
                                </div>

                                <div class="modal-body">
                                    <?= h(admin_t('delete_contact_confirm', $config)) ?> <strong><?= h($contact['vorname']) ?> <?= h($contact['nachname']) ?></strong>
                                </div>

                                <div class="modal-footer">

                                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                                        <?= h(admin_t('cancel', $config)) ?>
                                    </button>

                                    <a href="/admin/delete?id=<?= h($contact['id']) ?>" class="btn btn-danger">
                                        <?= h(admin_t('delete', $config)) ?>
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