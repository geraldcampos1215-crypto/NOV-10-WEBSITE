<?php
$self = basename($_SERVER['SCRIPT_NAME']);
$su = user();
$menus = [
 'seeker' => [['dashboard.php','speedometer2','Dashboard'],['browse-jobs.php','search','Find Jobs'],['my-applications.php','file-earmark-text','My Applications'],['profile.php','person','Profile'],['saved-jobs.php','heart','Saved Jobs'],['notifications.php','bell','Notifications']],
 'staff'  => [['dashboard.php','speedometer2','Dashboard'],['jobs.php','briefcase','Job Listings'],['add-job.php','plus-circle','Post New Job'],['applications.php','people','Applications'],['interviews.php','calendar-event','Interview Schedule'],['announcements.php','megaphone','Announcements'],['reports.php','bar-chart','Reports'],['../notifications.php','bell','Notifications']],
 'admin'  => [['dashboard.php','speedometer2','Dashboard'],['users.php','people','Users Management'],['staff.php','person-badge','Staff Management'],['jobs.php','briefcase','Job Listings'],['applications.php','file-earmark-text','Applications'],['companies.php','building','Companies'],['categories.php','tags','Categories'],['announcements.php','megaphone','Announcements'],['reports.php','bar-chart','Reports'],['logs.php','clock-history','Activity Logs'],['settings.php','gear','System Settings'],['../notifications.php','bell','Notifications']],
];
$role = $su['role'];
$base = url($role . '/');
$roleLabel = ['seeker' => 'Job Seeker', 'staff' => 'Staff', 'admin' => 'Administrator'][$role];
$unread = unread_count();
?>
<div class="dash-wrap">
  <div class="sidebar-backdrop" id="sbBackdrop"></div>
  <aside class="sidebar" id="sidebar">
    <a href="<?= url('index.php') ?>" class="side-brand"><i class="bi bi-briefcase-fill"></i> JobSeeker</a>
    <div class="side-user">
      <?= avatar($su, 42) ?>
      <div class="min-w-0"><div class="fw-semibold text-truncate"><?= e($su['full_name']) ?></div><div class="small opacity-75"><?= $roleLabel ?></div></div>
    </div>
    <nav class="side-nav">
      <?php foreach ($menus[$role] as [$mf, $mi, $ml]):
        $active = ($self === basename($mf)) || ($self === 'edit-job.php' && $mf === 'jobs.php'); ?>
        <a href="<?= $base . $mf ?>" class="<?= $active ? 'active' : '' ?>"><i class="bi bi-<?= $mi ?>"></i> <?= $ml ?>
          <?php if ($mf === '../notifications.php' || $mf === 'notifications.php'): ?><span class="badge bg-danger rounded-pill ms-auto js-notif-badge" <?= $unread ? '' : 'hidden' ?>><?= $unread ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <a href="<?= url('logout.php') ?>" class="side-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </aside>
  <div class="dash-main">
    <header class="topbar">
      <button class="btn btn-light d-lg-none" id="sbToggle"><i class="bi bi-list fs-5"></i></button>
      <h5 class="mb-0 fw-semibold"><?= e($pageTitle ?? 'Dashboard') ?></h5>
      <div class="ms-auto d-flex align-items-center gap-3">
        <a href="<?= url($role === 'seeker' ? 'seeker/notifications.php' : 'notifications.php') ?>" class="position-relative text-dark fs-5"><i class="bi bi-bell"></i>
          <span class="badge bg-danger rounded-pill notif-dot js-notif-badge" <?= $unread ? '' : 'hidden' ?>><?= $unread ?></span></a>
        <?= avatar($su, 36) ?>
      </div>
    </header>
    <main class="dash-content">
