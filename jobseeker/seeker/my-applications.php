<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('seeker'); $layout = 'dash'; $pageTitle = 'My Applications';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    $n = q("DELETE FROM applications WHERE id=? AND user_id=? AND status='Pending'", [(int)$_POST['id'], $u['id']])->rowCount();
    flash($n ? 'success' : 'warning', $n ? 'Application withdrawn.' : 'Only pending applications can be withdrawn.'); redirect('seeker/my-applications.php');
}
$f = $_GET['status'] ?? '';
$w = 'a.user_id=?'; $p = [$u['id']];
if (in_array($f, STATUSES)) { $w .= ' AND a.status=?'; $p[] = $f; }
$per = 8; $page = page_no();
$total = (int)val("SELECT COUNT(*) FROM applications a WHERE $w", $p);
$apps = rows("SELECT a.*, j.title, j.location, j.id AS jid, c.name AS company_name, c.id AS company_id,
  (SELECT MIN(scheduled_at) FROM interviews i WHERE i.application_id=a.id AND i.scheduled_at>=NOW()) AS interview_at
  FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE $w ORDER BY a.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
include ROOT_PATH . '/includes/header.php';
?>
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
  <li class="nav-item"><a class="nav-link <?= $f === '' ? 'active' : '' ?>" href="?">All</a></li>
  <?php foreach (STATUSES as $s): ?><li class="nav-item"><a class="nav-link text-nowrap <?= $f === $s ? 'active' : '' ?>" href="?status=<?= urlencode($s) ?>"><?= $s ?></a></li><?php endforeach; ?>
</ul>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Position</th><th>Company</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($apps as $a): ?><tr>
    <td><a class="fw-semibold text-decoration-none" href="<?= url('job-details.php?id=' . $a['jid']) ?>"><?= e($a['title']) ?></a><div class="small text-muted"><?= e($a['location']) ?></div></td>
    <td><?= e($a['company_name']) ?></td><td class="small"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
    <td><?= status_badge($a['status'], $a['id']) ?><?php if ($a['interview_at']): ?><div class="small text-muted mt-1"><i class="bi bi-calendar-event"></i> <?= date('M d, g:i A', strtotime($a['interview_at'])) ?></div><?php endif; ?></td>
    <td class="text-end"><?php if ($a['status'] === 'Pending'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Withdraw this application?">Withdraw</button></form><?php endif; ?></td>
  </tr><?php endforeach; if (!$apps) echo '<tr><td colspan="5" class="text-center text-muted py-4">No applications found.</td></tr>'; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pager($total, $per, $page) ?></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
