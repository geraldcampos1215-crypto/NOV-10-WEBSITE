<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff'); $layout = 'dash'; $pageTitle = 'Dashboard';
$cid = scope_company(); $cs = scope_sql($cid);
$c = fn($sql) => (int)val($sql);
$stats = [
 ['Total Jobs', $c("SELECT COUNT(*) FROM jobs j WHERE 1=1 $cs"), 'briefcase', 'ic-blue'],
 ['Total Applications', $c("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE 1=1 $cs"), 'file-earmark-text', 'ic-orange'],
 ['For Review', $c("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.status IN ('Pending','Under Review') $cs"), 'hourglass-split', 'ic-orange'],
 ['Interviews', $c("SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id=i.application_id JOIN jobs j ON j.id=a.job_id WHERE i.scheduled_at>=NOW() $cs"), 'calendar-event', 'ic-purple'],
];
$recent = rows("SELECT a.id, a.status, a.created_at, u.full_name, j.title FROM applications a JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id WHERE 1=1 $cs ORDER BY a.created_at DESC LIMIT 6");
$ann = rows('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3');
include ROOT_PATH . '/includes/header.php';
?>
<h4 class="fw-bold mb-0">Hello, <?= e(explode(' ', $u['full_name'])[0]) ?>!</h4><p class="text-muted">Here's an overview of your operations.</p>
<div class="row g-3 mb-4"><?php foreach ($stats as [$l, $n, $i, $ic]): ?>
  <div class="col-6 col-lg-3"><div class="card stat-card"><div class="stat-ic <?= $ic ?>"><i class="bi bi-<?= $i ?>"></i></div><div><div class="n"><?= $n ?></div><div class="small text-muted"><?= $l ?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-4">
  <div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between">Recent Applications <a class="small fw-normal" href="applications.php">View All</a></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Applicant</th><th>Position</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr><td><a class="text-decoration-none" href="applications.php?view=<?= $r['id'] ?>"><?= e($r['full_name']) ?></a></td><td><?= e($r['title']) ?></td><td><?= status_badge($r['status']) ?></td><td class="small"><?= date('M d, Y', strtotime($r['created_at'])) ?></td></tr><?php endforeach;
    if (!$recent) echo '<tr><td colspan="4" class="text-center text-muted py-4">No applications yet.</td></tr>'; ?></tbody></table></div></div></div>
  <div class="col-lg-4"><div class="card mb-3"><div class="card-header">Quick Actions</div><div class="card-body">
    <a class="quick-btn" href="add-job.php"><i class="bi bi-plus-circle"></i> Post New Job</a><a class="quick-btn" href="applications.php"><i class="bi bi-people"></i> Review Applications</a>
    <a class="quick-btn" href="interviews.php"><i class="bi bi-calendar-event"></i> Schedule Interview</a><a class="quick-btn mb-0" href="announcements.php"><i class="bi bi-megaphone"></i> Create Announcement</a></div></div>
    <div class="card"><div class="card-header">Announcements</div><?php foreach ($ann as $a): ?><div class="notif-item"><div class="small fw-semibold"><?= e($a['title']) ?></div><div class="small text-muted"><?= time_ago($a['created_at']) ?></div></div><?php endforeach; ?></div></div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
