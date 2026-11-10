<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Activity Logs';
$qv = trim($_GET['q'] ?? ''); $w = '1=1'; $p = [];
if ($qv !== '') { $w = '(l.action LIKE ? OR l.details LIKE ? OR u.full_name LIKE ?)'; $p = ["%$qv%", "%$qv%", "%$qv%"]; }
$per = 15; $page = page_no();
$total = (int)val("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id WHERE $w", $p);
$logs = rows("SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id WHERE $w ORDER BY l.created_at DESC, l.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
include ROOT_PATH . '/includes/header.php';
?>
<form class="d-flex gap-2 mb-3"><input name="q" class="form-control" placeholder="Search activity, details or user" value="<?= e($qv) ?>"><button class="btn btn-outline-primary">Search</button></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td class="small text-nowrap"><?= date('M d, Y g:i A', strtotime($l['created_at'])) ?></td><td><?= e($l['full_name'] ?? 'Guest') ?></td><td class="fw-semibold"><?= e($l['action']) ?></td><td class="small"><?= e($l['details']) ?></td><td class="small text-muted"><?= e($l['ip_address']) ?></td></tr>
<?php endforeach; if (!$logs) echo '<tr><td colspan="5" class="text-center text-muted py-4">No activity found.</td></tr>'; ?></tbody></table></div></div>
<div class="mt-3"><?= pager($total, $per, $page) ?></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
