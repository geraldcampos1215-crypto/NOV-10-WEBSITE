<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash'; $pageTitle = 'Announcements';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $t = trim($_POST['title'] ?? ''); $b = trim($_POST['body'] ?? '');
        if ($t === '' || $b === '') flash('danger', 'Title and message are required.');
        else {
            q('INSERT INTO announcements (title, body, created_by) VALUES (?,?,?)', [$t, $b, $u['id']]);
            foreach (rows("SELECT id FROM users WHERE role='seeker' AND status='active'") as $s) notify($s['id'], 'Announcement: ' . $t, $b);
            log_activity('Announcement created', $t); flash('success', 'Announcement published.');
        }
    } elseif ($act === 'delete') { q('DELETE FROM announcements WHERE id=?' . ($u['role'] === 'staff' ? ' AND created_by=' . (int)$u['id'] : ''), [(int)$_POST['id']]); flash('success', 'Announcement deleted.'); }
    redirect('staff/announcements.php');
}
$list = rows('SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-5"><div class="card p-3"><h6 class="fw-bold">New announcement</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <input name="title" class="form-control mb-2" placeholder="Title" required><textarea name="body" rows="4" class="form-control mb-2" placeholder="Message" required></textarea><button class="btn btn-primary w-100">Publish</button></form></div></div>
<div class="col-lg-7"><div class="card"><?php foreach ($list as $a): ?><div class="notif-item d-flex justify-content-between"><div><div class="fw-semibold"><?= e($a['title']) ?></div><div class="small"><?= nl2br(e($a['body'])) ?></div><div class="small text-muted">By <?= e($a['full_name'] ?? 'System') ?> · <?= time_ago($a['created_at']) ?></div></div>
  <?php if ($u['role'] === 'admin' || $a['created_by'] == $u['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-light text-danger" data-confirm="Delete announcement?"><i class="bi bi-trash"></i></button></form><?php endif; ?></div><?php endforeach; if (!$list) echo '<div class="p-4 text-center text-muted">No announcements.</div>'; ?></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
