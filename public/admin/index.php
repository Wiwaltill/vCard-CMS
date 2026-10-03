<?php

require_once __DIR__ . '/../includes/functions.php';
require_installed();
require_login();

$config = get_config();
$allContacts = load_contacts();
$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$position = is_string($_GET['position'] ?? null) ? $_GET['position'] : '';
$perPage = filter_var($_GET['per_page'] ?? 20, FILTER_VALIDATE_INT);
$perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
$listing = contact_list_page($allContacts, $config, $query, $position, $page, $perPage);
$contacts = $listing['contacts'];
$positions = array_values(array_unique(array_filter(array_column($allContacts, 'position'), static fn($value) => $value !== '')));
natcasesort($positions);
$pageUrl = static fn(int $number): string => '/admin?' . http_build_query([
    'q' => $query, 'position' => $position, 'per_page' => $perPage, 'page' => $number,
]);

include __DIR__ . '/../includes/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h1><?= h(admin_t('contacts', $config)) ?></h1>

    <a href="/admin/new" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> <?= h(admin_t('new_contact', $config)) ?>
    </a>

</div>

<form method="get" action="/admin" class="card card-body shadow-sm mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="contactSearch" class="form-label"><?= h(admin_t('search', $config)) ?></label>
            <input type="search" id="contactSearch" name="q" value="<?= h($query) ?>" class="form-control" placeholder="<?= h(admin_t('search_placeholder', $config)) ?>">
        </div>
        <div class="col-md-3">
            <label for="positionFilter" class="form-label"><?= h(admin_t('position', $config)) ?></label>
            <select id="positionFilter" name="position" class="form-select">
                <option value=""><?= h(admin_t('all_positions', $config)) ?></option>
                <?php foreach ($positions as $option): ?>
                    <option value="<?= h($option) ?>" <?= $position === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="perPage" class="form-label"><?= h(admin_t('per_page', $config)) ?></label>
            <select id="perPage" name="per_page" class="form-select">
                <?php foreach ([20, 50, 100] as $amount): ?><option value="<?= $amount ?>" <?= $amount === $perPage ? 'selected' : '' ?>><?= $amount ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary"><?= h(admin_t('search', $config)) ?></button>
            <a href="/admin" class="btn btn-outline-secondary" title="<?= h(admin_t('reset_filters', $config)) ?>" aria-label="<?= h(admin_t('reset_filters', $config)) ?>"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </div>
</form>
<p class="small text-body-secondary"><?= $listing['total'] ?> / <?= count($allContacts) ?> <?= h(admin_t('contacts', $config)) ?></p>
<div class="table-responsive">
<table class="table table-bordered align-middle">

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

                <td><?= h($contact['nachname'] ?? '') ?></td>
                <td><?= h($contact['vorname'] ?? '') ?></td>

                <td>
                    <a href="mailto:<?= h(contact_email($contact, $config)) ?>">
                        <?= h(contact_email($contact, $config)) ?>
                    </a>
                    <?php if (!empty($contact['email_override'])): ?>
                        <span class="badge bg-secondary ms-1"><?= h(admin_t('manual', $config)) ?></span>
                    <?php endif; ?>
                </td>

                <td>
                    <a href="<?= h(contact_url($contact)) ?>" target="_blank" rel="noopener">
                        <?= h(contact_url($contact)) ?>
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


                </td>

            </tr>

        <?php endforeach; ?>
        <?php if (!$contacts): ?><tr><td colspan="5" class="text-center text-body-secondary py-4"><?= h(admin_t('no_contacts_found', $config)) ?></td></tr><?php endif; ?>

    </tbody>

</table>
</div>
<?php foreach ($contacts as $contact): ?>
                    <div class="modal fade" id="deleteModal<?= h($contact['id']) ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">

                        <div class="modal-dialog">

                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title"><?= h(admin_t('delete_contact', $config)) ?></h5>
                                </div>

                                <div class="modal-body">
                                    <?= h(admin_t('delete_contact_confirm', $config)) ?> <strong><?= h($contact['vorname'] ?? '') ?> <?= h($contact['nachname'] ?? '') ?></strong>
                                </div>

                                <div class="modal-footer">

                                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                                        <?= h(admin_t('cancel', $config)) ?>
                                    </button>

                                    <form method="post" action="/admin/delete">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= h($contact['id']) ?>">
                                        <button class="btn btn-danger"><?= h(admin_t('delete', $config)) ?></button>
                                    </form>

                                </div>

                            </div>

                        </div>

                    </div>

<?php endforeach; ?>

<?php if ($listing['pages'] > 1): ?>
<nav aria-label="<?= h(admin_t('pagination', $config)) ?>" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
    <p class="small text-body-secondary mb-0"><?= h(admin_t('page', $config)) ?> <?= $listing['page'] ?> / <?= $listing['pages'] ?></p>
    <ul class="pagination mb-0 flex-wrap">
        <li class="page-item <?= $listing['page'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl(1)) ?>" aria-label="<?= h(admin_t('first_page', $config)) ?>" <?= $listing['page'] === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>&laquo;</a></li>
        <li class="page-item <?= $listing['page'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl(max(1, $listing['page'] - 1))) ?>" aria-label="<?= h(admin_t('previous_page', $config)) ?>" <?= $listing['page'] === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>&lsaquo;</a></li>
        <?php for ($number = max(1, $listing['page'] - 2); $number <= min($listing['pages'], $listing['page'] + 2); $number++): ?>
            <li class="page-item <?= $number === $listing['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h($pageUrl($number)) ?>" <?= $number === $listing['page'] ? 'aria-current="page"' : '' ?>><?= $number ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $listing['page'] === $listing['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl(min($listing['pages'], $listing['page'] + 1))) ?>" aria-label="<?= h(admin_t('next_page', $config)) ?>" <?= $listing['page'] === $listing['pages'] ? 'tabindex="-1" aria-disabled="true"' : '' ?>>&rsaquo;</a></li>
        <li class="page-item <?= $listing['page'] === $listing['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl($listing['pages'])) ?>" aria-label="<?= h(admin_t('last_page', $config)) ?>" <?= $listing['page'] === $listing['pages'] ? 'tabindex="-1" aria-disabled="true"' : '' ?>>&raquo;</a></li>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>