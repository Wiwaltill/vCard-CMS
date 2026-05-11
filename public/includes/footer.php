</div>
</main>

<?php $config = get_config(); ?>

<footer class="bg-body-tertiary border-top py-3 mt-auto">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">

    <div>
      &copy; <?= date('Y') ?> <?= h($config['company_name']) ?>
    </div>

    <?php if (!empty($config['github_url'])): ?>
      <div>
        <a href="<?= h($config['github_url']) ?>" target="_blank" rel="noopener">
          <i class="bi bi-github"></i> GitHub
        </a>
      </div>
    <?php endif; ?>

  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
  (function() {
    const key = 'vcard-admin-theme-mode';
    const html = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const defaultMode = html.getAttribute('data-bs-theme-mode') || 'auto';
    const label = document.querySelector('[data-theme-mode-label]');

    function resolvedTheme(mode) {
      return mode === 'auto' ? (system.matches ? 'dark' : 'light') : mode;
    }

    function apply(mode, persist) {
      html.setAttribute('data-bs-theme-mode', mode);
      html.setAttribute('data-bs-theme', resolvedTheme(mode));
      if (label) label.textContent = mode.charAt(0).toUpperCase() + mode.slice(1);
      document.querySelectorAll('[data-theme-value]').forEach(function(item) {
        item.classList.toggle('active', item.dataset.themeValue === mode);
      });
      if (persist) localStorage.setItem(key, mode);
    }

    apply(localStorage.getItem(key) || defaultMode, false);
    system.addEventListener('change', function() {
      if ((localStorage.getItem(key) || defaultMode) === 'auto') apply('auto', false);
    });
    document.querySelectorAll('[data-theme-value]').forEach(function(item) {
      item.addEventListener('click', function() {
        apply(item.dataset.themeValue, true);
      });
    });
  })();
</script>
</body>

</html>