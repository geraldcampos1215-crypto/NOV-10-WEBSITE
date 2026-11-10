<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash'; $pageTitle = 'Job Listings';
$cid = scope_company(); $cs = scope_sql($cid);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $job = row("SELECT j.* FROM jobs j WHERE j.id=? $cs", [$id]);
    if ($job) {
        if ($act === 'delete') { q('DELETE FROM jobs WHERE id=?', [$id]); log_activity('Job deleted', $job['title']); flash('success', 'Job deleted.'); }
        elseif ($act === 'toggle') { $ns = $job['status'] === 'open' ? 'closed' : 'open'; q('UPDATE jobs SET status=? WHERE id=?', [$ns, $id]); log_activity('Job ' . $ns, $job['title']); flash('success', "Job is now $ns."); }
        elseif ($act === 'feature' && $u['role'] === 'admin') { q('UPDATE jobs SET is_featured=1-is_featured WHERE id=?', [$id]); flash('success', 'Featured status updated.'); }
    } else flash('danger', 'Job not found or access denied.');
    redirect($u['role'] . '/jobs.php');
}
$qv = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? '';
$w = "1=1 $cs"; $p = [];
if ($qv !== '') { $w .= ' AND (j.title LIKE ? OR c.name LIKE ?)'; array_push($p, "%$qv%", "%$qv%"); }
if (in_array($st, ['open', 'closed', 'draft'])) { $w .= ' AND j.status=?'; $p[] = $st; }
$per = 10; $page = page_no();
$total = (int)val("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id WHERE $w", $p);
$jobs = rows("SELECT j.*, c.name AS company_name, (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) AS apps FROM jobs j JOIN companies c ON c.id=j.company_id WHERE $w ORDER BY j.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
include ROOT_PATH . '/includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Search jobs" value="<?= e($qv) ?>"><select name="status" class="form-select"><option value="">All</option><?php foreach (['open', 'closed', 'draft'] as $s) echo '<option ' . ($st === $s ? 'selected' : '') . '>' . $s . '</option>'; ?></select><button class="btn btn-outline-primary">Filter</button></form>
  <a href="add-job.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Post New Job</a></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Title</th><?php if ($u['role'] === 'admin') echo '<th>Company</th>'; ?><th>Location</th><th>Type</th><th>Apps</th><th>Status</th><th>Deadline</th><th></th></tr></thead><tbody>
<?php foreach ($jobs as $j): ?><tr>
  <td class="fw-semibold"><?= e($j['title']) ?><?= $j['is_featured'] ? ' <i class="bi bi-star-fill text-warning"></i>' : '' ?></td><?php if ($u['role'] === 'admin') echo '<td>' . e($j['company_name']) . '</td>'; ?>
  <td><?= e($j['location']) ?></td><td><?= e($j['job_type']) ?></td><td><?= $j['apps'] ?></td>
  <td><span class="badge text-bg-<?= ['open' => 'success', 'closed' => 'secondary', 'draft' => 'warning'][$j['status']] ?>"><?= $j['status'] ?></span></td>
  <td class="small"><?= $j['deadline'] ? date('M d, Y', strtotime($j['deadline'])) : '-' ?></td>
  <td class="text-end text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $j['id'] ?>">
    <a href="edit-job.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
    <button name="action" value="toggle" class="btn btn-sm btn-outline-secondary" title="Open/Close"><i class="bi bi-toggle-<?= $j['status'] === 'open' ? 'on' : 'off' ?>"></i></button>
    <?php if ($u['role'] === 'admin'): ?><button name="action" value="feature" class="btn btn-sm btn-outline-warning" title="Feature"><i class="bi bi-star"></i></button><?php endif; ?>
    <button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Delete this job and its applications?"><i class="bi bi-trash"></i></button></form></td>
</tr><?php endforeach; if (!$jobs) echo '<tr><td colspan="8" class="text-center text-muted py-4">No jobs found.</td></tr>'; ?></tbody></table></div></div>
<div class="mt-3"><?= pager($total, $per, $page) ?></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
