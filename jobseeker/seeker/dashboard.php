<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('seeker'); $layout = 'dash'; $pageTitle = 'Dashboard';
$cnt = fn($w) => (int)val("SELECT COUNT(*) FROM applications WHERE user_id=? $w", [$u['id']]);
$stats = [['Applications', $cnt(''), 'file-earmark-text', 'ic-blue'], ['In Review', $cnt("AND status='Under Review'"), 'hourglass-split', 'ic-orange'], ['Shortlisted', $cnt("AND status IN ('Shortlisted','Interview')"), 'check2-circle', 'ic-green'], ['Hired', $cnt("AND status='Hired'"), 'award', 'ic-purple']];
$prof = row('SELECT * FROM user_profiles WHERE user_id=?', [$u['id']]) ?: [];
$recs = rows(JOB_SELECT . " WHERE j.status='open' AND (j.deadline IS NULL OR j.deadline>=CURDATE()) AND j.id NOT IN (SELECT job_id FROM applications WHERE user_id=?) ORDER BY (j.location = ?) DESC, j.is_featured DESC, j.created_at DESC LIMIT 3", [$u['id'], $prof['location'] ?? '']);
$acts = rows('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 4', [$u['id']]);
$ann = rows('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 2');
$interviews = rows("SELECT i.*, j.title FROM interviews i JOIN applications a ON a.id=i.application_id JOIN jobs j ON j.id=a.job_id WHERE a.user_id=? AND i.scheduled_at>=NOW() ORDER BY i.scheduled_at LIMIT 3", [$u['id']]);
$pc = profile_completion($u['id']); $saved = saved_ids();
$hr = (int)date('G'); $greet = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');
include ROOT_PATH . '/includes/header.php';
?>
<h4 class="fw-bold mb-0"><?= $greet ?>, <?= e(explode(' ', $u['full_name'])[0]) ?>!</h4>
<p class="text-muted">Here's what's happening with your job search.</p>
<div class="row g-3 mb-4"><?php foreach ($stats as [$l, $n, $i, $c]): ?>
  <div class="col-6 col-lg-3"><div class="card stat-card"><div class="stat-ic <?= $c ?>"><i class="bi bi-<?= $i ?>"></i></div><div><div class="n"><?= $n ?></div><div class="small text-muted"><?= $l ?></div></div></div></div>
<?php endforeach; ?></div>
<?php if ($pc < 100): ?><div class="card p-3 mb-4"><div class="d-flex justify-content-between small mb-1"><span class="fw-semibold">Profile completion</span><span><?= $pc ?>%</span></div>
  <div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div><a href="profile.php" class="small mt-2">Complete your profile</a></div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between">Recommended Jobs <a class="small fw-normal" href="browse-jobs.php">View All</a></div><div class="card-body">
    <?php foreach ($recs as $j) echo job_card($j, $saved); if (!$recs) echo '<p class="text-muted mb-0">No recommendations right now.</p>'; ?></div></div></div>
  <div class="col-lg-4">
    <?php if ($interviews): ?><div class="card mb-3"><div class="card-header">Upcoming Interviews</div><div class="card-body small">
      <?php foreach ($interviews as $i): ?><div class="mb-2"><b><?= e($i['title']) ?></b><br><i class="bi bi-calendar-event"></i> <?= date('M d, Y g:i A', strtotime($i['scheduled_at'])) ?><br><i class="bi bi-geo-alt"></i> <?= e($i['location']) ?></div><?php endforeach; ?></div></div><?php endif; ?>
    <div class="card"><div class="card-header">Recent Activity</div><div>
      <?php foreach ($acts as $a): ?><div class="notif-item <?= $a['is_read'] ? '' : 'unread' ?>"><div class="small fw-semibold"><?= e($a['title']) ?></div><div class="small text-muted"><?= e($a['message']) ?></div><div class="small text-muted"><?= time_ago($a['created_at']) ?></div></div><?php endforeach; ?>
      <?php foreach ($ann as $a): ?><div class="notif-item"><div class="small fw-semibold"><i class="bi bi-megaphone text-primary"></i> <?= e($a['title']) ?></div><div class="small text-muted"><?= e($a['body']) ?></div></div><?php endforeach; ?></div></div>
  </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
