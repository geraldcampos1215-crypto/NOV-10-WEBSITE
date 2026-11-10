<?php $layout = $layout ?? 'public'; ?>
<?php if ($layout === 'dash'): ?>
    </main>
  </div>
</div>
<?php elseif ($layout === 'public'): ?>
<footer class="site-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4"><h5 class="fw-bold"><i class="bi bi-briefcase-fill"></i> JobSeeker</h5><p class="opacity-75">Find your future. Connecting talented people with great companies across the Philippines.</p></div>
      <div class="col-6 col-lg-2"><h6>Quick Links</h6><ul class="list-unstyled small">
        <li><a href="<?= url('jobs.php') ?>">Browse Jobs</a></li><li><a href="<?= url('about.php') ?>">About Us</a></li><li><a href="<?= url('contact.php') ?>">Contact</a></li><li><a href="<?= url('register.php') ?>">Create Account</a></li></ul></div>
      <div class="col-6 col-lg-2"><h6>Categories</h6><ul class="list-unstyled small">
        <?php foreach (rows('SELECT id,name FROM job_categories ORDER BY name LIMIT 4') as $c): ?><li><a href="<?= url('jobs.php?category=' . $c['id']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?></ul></div>
      <div class="col-lg-4"><h6>Contact</h6><ul class="list-unstyled small opacity-75">
        <li><i class="bi bi-envelope"></i> <?= e(setting('contact_email')) ?></li><li><i class="bi bi-telephone"></i> <?= e(setting('contact_phone')) ?></li><li><i class="bi bi-geo-alt"></i> <?= e(setting('contact_address')) ?></li></ul></div>
    </div>
    <hr class="opacity-25"><div class="small opacity-75 text-center">&copy; <?= date('Y') ?> JobSeeker. All rights reserved.</div>
  </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>const BASE = '<?= BASE_URL ?>', ROLE = '<?= e($_SESSION['role'] ?? '') ?>';</script>
<script src="<?= url('assets/js/script.js') ?>"></script>
<?= $extraJs ?? '' ?>
</body>
</html>
