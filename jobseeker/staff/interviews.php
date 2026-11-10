<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash'; $pageTitle = 'Interview Schedule';
$cid = scope_company(); $cs = scope_sql($cid);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'schedule') {
        $a = row("SELECT a.*, j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? $cs", [(int)$_POST['application_id']]);
        $when = strtotime($_POST['scheduled_at'] ?? '');
        if (!$a || !$when || trim($_POST['location'] ?? '') === '') flash('danger', 'Choose an applicant, a valid date/time and a location.');
        else {
            q('INSERT INTO interviews (application_id, scheduled_at, location, notes, created_by) VALUES (?,?,?,?,?)', [$a['id'], date('Y-m-d H:i:s', $when), trim($_POST['location']), trim($_POST['notes'] ?? ''), $u['id']]);
            q("UPDATE applications SET status='Interview' WHERE id=?", [$a['id']]);
            notify($a['user_id'], 'Interview scheduled', "Interview for {$a['title']} on " . date('M d, Y g:i A', $when) . ' at ' . trim($_POST['location']) . '.', true);
            log_activity('Interview scheduled', $a['title']); flash('success', 'Interview scheduled and applicant notified.');
        }
    } elseif ($act === 'cancel') {
        $i = row("SELECT i.*, a.user_id FROM interviews i JOIN applications a ON a.id=i.application_id JOIN jobs j ON j.id=a.job_id WHERE i.id=? $cs", [(int)$_POST['id']]);
        if ($i) { q('DELETE FROM interviews WHERE id=?', [$i['id']]); notify($i['user_id'], 'Interview cancelled', 'Your interview on ' . date('M d, Y g:i A', strtotime($i['scheduled_at'])) . ' was cancelled.', true); flash('success', 'Interview cancelled.'); }
    }
    redirect($u['role'] . '/interviews.php');
}
$list = rows("SELECT i.*, u.full_name, j.title FROM interviews i JOIN applications a ON a.id=i.application_id JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id WHERE i.scheduled_at >= CURDATE() $cs ORDER BY i.scheduled_at");
$cands = rows("SELECT a.id, u.full_name, j.title FROM applications a JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id WHERE a.status IN ('Under Review','Shortlisted','Interview') $cs ORDER BY u.full_name");
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-8"><div class="card"><div class="card-header">Upcoming interviews</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>When</th><th>Applicant</th><th>Position</th><th>Where</th><th></th></tr></thead><tbody>
<?php foreach ($list as $i): ?><tr><td class="small text-nowrap"><?= date('M d, Y g:i A', strtotime($i['scheduled_at'])) ?></td><td><?= e($i['full_name']) ?></td><td><?= e($i['title']) ?></td><td class="small"><?= e($i['location']) ?></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $i['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Cancel this interview?"><i class="bi bi-x-lg"></i></button></form></td></tr>
<?php endforeach; if (!$list) echo '<tr><td colspan="5" class="text-center text-muted py-4">No upcoming interviews.</td></tr>'; ?></tbody></table></div></div></div>
<div class="col-lg-4"><div class="card p-3"><h6 class="fw-bold">Schedule an interview</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="schedule">
  <select name="application_id" class="form-select mb-2" required><option value="">Select applicant…</option><?php foreach ($cands as $c) echo '<option value="' . $c['id'] . '">' . e($c['full_name'] . ' — ' . $c['title']) . '</option>'; ?></select>
  <input type="datetime-local" name="scheduled_at" class="form-control mb-2" required><input name="location" class="form-control mb-2" placeholder="Location or meeting link" required><textarea name="notes" rows="2" class="form-control mb-2" placeholder="Notes"></textarea><button class="btn btn-primary w-100">Schedule</button></form></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
