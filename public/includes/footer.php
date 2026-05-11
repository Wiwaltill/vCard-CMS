</div>
</main>

<?php $config = get_config(); ?>

<footer class="bg-light border-top py-3 mt-auto">
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

</body>
</html>
