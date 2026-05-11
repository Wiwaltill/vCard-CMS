<?php
require_once '../includes/functions.php';
require_installed();
require_login();
$config = get_config();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['regen'])) $config['api_token'] = bin2hex(random_bytes(24));
    $config['api_enabled'] = isset($_POST['api_enabled']);
    save_json('config.json', $config);
    header('Location: /admin/api?saved=1'); exit;
}
$token = api_token($config);
include '../includes/header.php';
?>
<h1 class="mb-4">REST API</h1>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success"><?= h(admin_t('api_saved', $config)) ?></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
<form method="post">
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="api_enabled" id="api_enabled" <?= !empty($config['api_enabled'])?'checked':'' ?>><label class="form-check-label" for="api_enabled"><?= h(admin_t('api_enable', $config)) ?></label></div>
<label class="form-label"><?= h(admin_t('api_token', $config)) ?></label><input class="form-control font-monospace mb-3" value="<?= h($token) ?>" readonly>
<button class="btn btn-success"><?= h(admin_t('save', $config)) ?></button> <button class="btn btn-outline-danger" name="regen" value="1"><?= h(admin_t('regen_token', $config)) ?></button>
</form>
<hr><p class="mb-2"><?= h(admin_t('endpoints_with_header', $config)) ?> <code>X-API-Token</code>:</p>
<pre class="bg-body-tertiary border p-3 rounded text-body font-monospace">GET    /api/contacts
GET    /api/contacts/{id}
POST   /api/contacts
PUT    /api/contacts/{id}
DELETE /api/contacts/{id}</pre>
</div></div>
<?php include '../includes/footer.php'; ?>
