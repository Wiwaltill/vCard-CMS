<aside class="col-lg-5">
    <div class="card shadow-sm position-sticky" style="top:1rem">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h5 mb-0"><?= h(admin_t('live_preview', $config)) ?></h2>
                <span class="badge text-bg-secondary"><?= h(admin_t('draft', $config)) ?></span>
            </div>
            <p class="small text-body-secondary mb-3"><?= h(admin_t('preview_help', $config)) ?></p>
            <p class="small text-body-secondary" id="previewStatus" role="status" aria-live="polite"></p>
            <iframe id="contactPreview" title="<?= h(admin_t('live_preview', $config)) ?>" sandbox="allow-same-origin" class="w-100 border rounded" style="height:640px;background:var(--bs-body-bg)"></iframe>
            <?php if (!empty($current['id'])): ?>
                <p class="small mt-3 mb-0"><?= h(admin_t('stable_link', $config)) ?><br>
                    <a href="<?= h(contact_url($current)) ?>" target="_blank" rel="noopener"><?= h(contact_url($current)) ?></a>
                </p>
            <?php endif; ?>
            <noscript><p class="small mt-2"><?= h(admin_t('preview_requires_js', $config)) ?></p></noscript>
        </div>
    </div>
</aside>
<script src="/assets/js/contact-editor.js" defer></script>
