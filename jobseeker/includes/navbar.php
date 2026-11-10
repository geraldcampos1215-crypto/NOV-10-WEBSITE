<?php $self = basename($_SERVER['SCRIPT_NAME']); $nu = user(); ?>
<nav class="navbar navbar-expand-lg navbar-dark js-nav sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>"><i class="bi bi-briefcase-fill"></i> JobSeeker</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto">
        <?php foreach (['index.php' => 'Home', 'jobs.php' => 'Browse Jobs', 'about.php' => 'About', 'contact.php' => 'Contact'] as $f => $l): ?>
          <li class="nav-item"><a class="nav-link <?= $self === $f ? 'active' : '' ?>" href="<?= url($f) ?>"><?= $l ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex gap-2 pb-2 pb-lg-0">
        <?php if ($nu): ?>
          <a class="btn btn-light btn-sm" href="<?= url(home_for($nu['role'])) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
          <a class="btn btn-outline-light btn-sm" href="<?= url('logout.php') ?>">Logout</a>
        <?php else: ?>
          <a class="btn btn-outline-light btn-sm" href="<?= url('login.php') ?>">Login</a>
          <a class="btn btn-light btn-sm text-primary fw-semibold" href="<?= url('register.php') ?>">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
