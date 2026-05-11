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
(function(){
  const key = 'vcard-admin-theme';
  const html = document.documentElement;
  const defaultTheme = html.getAttribute('data-bs-theme') || 'light';
  const apply = function(theme) {
    html.setAttribute('data-bs-theme', theme);
    const icon = document.querySelector('#darkToggle i');
    if (icon) {
      icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
  };

  apply(localStorage.getItem(key) || defaultTheme);

  const btn = document.getElementById('darkToggle');
  if (btn) {
    btn.addEventListener('click', function(){
      const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem(key, next);
      apply(next);
    });
  }
})();
</script>
</body>
</html>
