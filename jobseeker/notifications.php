<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(); $layout = 'dash'; $pageTitle = 'Notifications';
$self = $u['role'] === 'seeker' ? 'seeker/notifications.php' : 'notifications.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'read_all') q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);
    elseif ($a === 'read') q('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [(int)$_POST['id'], $u['id']]);
    elseif ($a === 'delete') q('DELETE FROM notifications WHERE id=? AND user_id=?', [(int)$_POST['id'], $u['id']]);
    elseif ($a === 'send' && in_array($u['role'], ['staff', 'admin'])) {
        $title = trim($_POST['title'] ?? ''); $msg = trim($_POST['message'] ?? ''); $target = $_POST['target'] ?? 'all';
        if ($title === '' || $msg === '') flash('danger', 'Title and message are required.');
        else {
            if ($target === 'all') $ids = array_column(rows("SELECT id FROM users WHERE role='seeker' AND status='active'"), 'id');
            else $ids = array_column(rows('SELECT DISTINCT a.user_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.job_id=?' . ($u['role'] === 'staff' ? ' AND j.company_id=' . staff_company() : ''), [(int)$target]), 'user_id');
            foreach ($ids as $id) notify($id, $title, $msg, true);
            log_activity('Notification sent', $title . ' (' . count($ids) . ' users)'); flash('success', 'Notification sent to ' . count($ids) . ' user(s).');
        }
    }
    redirect($self);
}
$per = 10; $page = page_no();
$total = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=?', [$u['id']]);
$list = rows('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), [$u['id']]);
$jobsForSend = [];
if ($u['role'] !== 'seeker') $jobsForSend = rows('SELECT id, title FROM jobs' . ($u['role'] === 'staff' ? ' WHERE company_id=' . staff_company() : '') . ' ORDER BY title');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-<?= $jobsForSend || $u['role'] !== 'seeker' ? '8' : '12' ?>">
  <div class="card"><div class="card-header d-flex justify-content-between align-items-center">Your notifications
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-sm btn-outline-primary">Mark all as read</button></form></div>
  <?php foreach ($list as $n): ?><div class="notif-item d-flex justify-content-between gap-2 <?= $n['is_read'] ? '' : 'unread' ?>">
    <div><div class="fw-semibold"><?= e($n['title']) ?></div><div class="small text-muted"><?= e($n['message']) ?></div><div class="small text-muted"><?= time_ago($n['created_at']) ?></div></div>
    <form method="post" class="text-nowrap"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $n['id'] ?>">
      <?php if (!$n['is_read']): ?><button name="action" value="read" class="btn btn-sm btn-light" title="Mark read"><i class="bi bi-check2"></i></button><?php endif; ?>
      <button name="action" value="delete" class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button></form></div>
  <?php endforeach; if (!$list) echo '<div class="p-4 text-center text-muted">No notifications.</div>'; ?></div>
  <div class="mt-3"><?= pager($total, $per, $page) ?></div></div>
<?php if ($u['role'] !== 'seeker'): ?><div class="col-lg-4"><div class="card p-3"><h6 class="fw-bold">Send a notification</h6>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send">
    <select name="target" class="form-select mb-2"><option value="all">All job seekers</option><?php foreach ($jobsForSend as $j) echo '<option value="' . $j['id'] . '">Applicants: ' . e($j['title']) . '</option>'; ?></select>
    <input name="title" class="form-control mb-2" placeholder="Title" required><textarea name="message" rows="3" class="form-control mb-2" placeholder="Message" required></textarea>
    <button class="btn btn-primary w-100">Send</button></form></div></div><?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
