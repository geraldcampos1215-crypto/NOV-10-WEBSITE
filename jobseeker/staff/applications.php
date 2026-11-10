<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash'; $pageTitle = 'Applications';
$cid = scope_company(); $cs = scope_sql($cid);
$me = $u['role'] . '/applications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $a = row("SELECT a.*, j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? $cs", [$id]);
    if (!$a) { flash('danger', 'Application not found or access denied.'); redirect($me); }
    if ($act === 'status') {
        $s = $_POST['status'] ?? '';
        if (in_array($s, STATUSES)) {
            q('UPDATE applications SET status=?, staff_notes=COALESCE(?, staff_notes) WHERE id=?', [$s, isset($_POST['notes']) ? trim($_POST['notes']) : null, $id]);
            notify($a['user_id'], 'Application update', "Your application for {$a['title']} is now: $s.", true);
            log_activity('Application ' . $s, $a['title'] . ' #' . $id); flash('success', "Application marked as $s.");
        }
    } elseif ($act === 'delete' && $u['role'] === 'admin') { q('DELETE FROM applications WHERE id=?', [$id]); log_activity('Application deleted', '#' . $id); flash('success', 'Application deleted.'); }
    redirect(!empty($_POST['back']) ? $me . '?view=' . $id : $me);
}

/* ---- detail view ---- */
if (isset($_GET['view'])) {
    $a = row("SELECT a.*, j.title, j.id AS jid, c.name AS company_name, u.full_name, u.email, p.*, a.id AS id, a.resume AS resume, a.created_at AS applied_at FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id JOIN users u ON u.id=a.user_id LEFT JOIN user_profiles p ON p.user_id=u.id WHERE a.id=? $cs", [(int)$_GET['view']]);
    if (!$a) { flash('danger', 'Application not found.'); redirect($me); }
    $ints = rows('SELECT * FROM interviews WHERE application_id=? ORDER BY scheduled_at DESC', [$a['id']]);
    include ROOT_PATH . '/includes/header.php'; ?>
    <a href="<?= basename($me) ?>" class="btn btn-light btn-sm mb-3"><i class="bi bi-arrow-left"></i> Back</a>
    <div class="row g-4"><div class="col-lg-8"><div class="card p-4">
      <div class="d-flex gap-3 align-items-center mb-3"><?= avatar($a, 64) ?><div><h4 class="fw-bold mb-0"><?= e($a['full_name']) ?></h4><div class="text-muted"><?= e($a['headline'] ?? '') ?></div><div class="small"><?= e($a['email']) ?> · <?= e($a['phone'] ?? '') ?> · <?= e($a['location'] ?? '') ?></div></div></div>
      <p class="mb-1 text-muted small">Applied for</p><p class="fw-semibold"><?= e($a['title']) ?> — <?= e($a['company_name']) ?> <?= status_badge($a['status']) ?></p>
      <h6 class="fw-bold">Skills</h6><p><?= e($a['skills'] ?: '—') ?></p><h6 class="fw-bold">About</h6><p><?= nl2br(e($a['bio'] ?: '—')) ?></p>
      <h6 class="fw-bold">Cover letter</h6><p><?= nl2br(e($a['cover_letter'] ?: '—')) ?></p>
      <?php if ($a['resume']): ?><a class="btn btn-outline-primary" href="<?= url('download.php?app=' . $a['id']) ?>"><i class="bi bi-download"></i> Download resume</a><?php else: ?><span class="text-muted small">No resume uploaded.</span><?php endif; ?>
    </div></div>
    <div class="col-lg-4"><div class="card p-3 mb-3"><h6 class="fw-bold">Update status</h6>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="back" value="1">
        <select name="status" class="form-select mb-2"><?php foreach (STATUSES as $s) echo '<option' . ($a['status'] === $s ? ' selected' : '') . '>' . $s . '</option>'; ?></select>
        <textarea name="notes" rows="3" class="form-control mb-2" placeholder="Internal notes"><?= e($a['staff_notes']) ?></textarea><button class="btn btn-primary w-100">Save</button></form></div>
      <div class="card p-3"><h6 class="fw-bold">Schedule interview</h6>
      <form method="post" action="<?= url('staff/interviews.php') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="schedule"><input type="hidden" name="application_id" value="<?= $a['id'] ?>">
        <input type="datetime-local" name="scheduled_at" class="form-control mb-2" required><input name="location" class="form-control mb-2" placeholder="Location or meeting link" required><textarea name="notes" rows="2" class="form-control mb-2" placeholder="Notes"></textarea><button class="btn btn-success w-100">Schedule</button></form>
      <?php foreach ($ints as $i): ?><div class="small mt-2 border-top pt-2"><i class="bi bi-calendar-event"></i> <?= date('M d, Y g:i A', strtotime($i['scheduled_at'])) ?> — <?= e($i['location']) ?></div><?php endforeach; ?></div></div></div>
    <?php include ROOT_PATH . '/includes/footer.php'; exit;
}

/* ---- list ---- */
$qv = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? ''; $jb = (int)($_GET['job'] ?? 0);
$w = "1=1 $cs"; $p = [];
if ($qv !== '') { $w .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)'; array_push($p, "%$qv%", "%$qv%"); }
if (in_array($st, STATUSES)) { $w .= ' AND a.status=?'; $p[] = $st; }
if ($jb) { $w .= ' AND a.job_id=?'; $p[] = $jb; }
$per = 10; $page = page_no();
$total = (int)val("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.user_id WHERE $w", $p);
$apps = rows("SELECT a.id, a.status, a.created_at, a.resume, u.full_name, j.title FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.user_id WHERE $w ORDER BY a.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
$jobsList = rows("SELECT j.id, j.title FROM jobs j WHERE 1=1 $cs ORDER BY j.title");
include ROOT_PATH . '/includes/header.php';
?>
<form class="row g-2 mb-3"><div class="col-md-4"><input name="q" class="form-control" placeholder="Search applicant" value="<?= e($qv) ?>"></div>
  <div class="col-md-3"><select name="job" class="form-select"><option value="0">All jobs</option><?php foreach ($jobsList as $j) echo '<option value="' . $j['id'] . '"' . ($jb == $j['id'] ? ' selected' : '') . '>' . e($j['title']) . '</option>'; ?></select></div>
  <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option><?php foreach (STATUSES as $s) echo '<option' . ($st === $s ? ' selected' : '') . '>' . $s . '</option>'; ?></select></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filter</button></div></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Applicant</th><th>Position</th><th>Status</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach ($apps as $a): ?><tr><td class="fw-semibold"><?= e($a['full_name']) ?></td><td><?= e($a['title']) ?></td><td><?= status_badge($a['status']) ?></td><td class="small"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
  <td class="text-end text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="status">
    <a class="btn btn-sm btn-outline-primary" href="?view=<?= $a['id'] ?>" title="View"><i class="bi bi-eye"></i></a>
    <?php if ($a['resume']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('download.php?app=' . $a['id']) ?>" title="Download resume"><i class="bi bi-download"></i></a><?php endif; ?>
    <button name="status" value="Shortlisted" class="btn btn-sm btn-outline-success" title="Shortlist"><i class="bi bi-star"></i></button>
    <button name="status" value="Hired" class="btn btn-sm btn-outline-success" title="Approve / Hire"><i class="bi bi-check-lg"></i></button>
    <button name="status" value="Rejected" class="btn btn-sm btn-outline-danger" title="Reject" data-confirm="Reject this applicant?"><i class="bi bi-x-lg"></i></button></form>
    <?php if ($u['role'] === 'admin'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-danger" data-confirm="Delete this application?"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$apps) echo '<tr><td colspan="5" class="text-center text-muted py-4">No applications found.</td></tr>'; ?></tbody></table></div></div>
<div class="mt-3"><?= pager($total, $per, $page) ?></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
