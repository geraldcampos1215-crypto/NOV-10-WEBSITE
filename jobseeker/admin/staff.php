<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Staff Management';
$companies = rows('SELECT id, name FROM companies ORDER BY name');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($act === 'create') {
        [$nid, $e] = create_user($_POST['full_name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', 'staff', (int)$_POST['company_id'], trim($_POST['position'] ?? ''), trim($_POST['department'] ?? ''));
        if ($e) flash('danger', $e); else { log_activity('Staff account created', $_POST['full_name']); flash('success', 'Staff account created.'); }
    } elseif ($act === 'update') { q('UPDATE staff SET company_id=?, position=?, department=? WHERE user_id=?', [(int)$_POST['company_id'] ?: null, trim($_POST['position']), trim($_POST['department']), $id]); flash('success', 'Staff updated.'); }
    elseif ($act === 'toggle') { q("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=? AND role='staff'", [$id]); flash('success', 'Account status changed.'); }
    elseif ($act === 'delete') { q("DELETE FROM users WHERE id=? AND role='staff'", [$id]); log_activity('Staff deleted', '#' . $id); flash('success', 'Staff account deleted.'); }
    redirect('admin/staff.php');
}
$list = rows("SELECT u.id, u.full_name, u.email, u.status, s.company_id, s.position, s.department FROM users u LEFT JOIN staff s ON s.user_id=u.id WHERE u.role='staff' ORDER BY u.full_name");
$opts = fn($sel) => '<option value="">No company</option>' . implode('', array_map(fn($c) => '<option value="' . $c['id'] . '"' . ($sel == $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>', $companies));
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-4"><div class="card p-3"><h6 class="fw-bold">Create staff account</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <input name="full_name" class="form-control mb-2" placeholder="Full name" required><input type="email" name="email" class="form-control mb-2" placeholder="Email" required><input type="password" name="password" class="form-control mb-2" placeholder="Password (8+, letters & numbers)" required>
  <select name="company_id" class="form-select mb-2" required><?= $opts('') ?></select><input name="position" class="form-control mb-2" placeholder="Position"><input name="department" class="form-control mb-2" placeholder="Department"><button class="btn btn-primary w-100">Create</button></form></div></div>
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Staff</th><th>Company / position</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $s): ?><tr><td><div class="fw-semibold"><?= e($s['full_name']) ?></div><div class="small text-muted"><?= e($s['email']) ?></div></td>
  <td><form method="post" class="d-flex flex-wrap gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $s['id'] ?>"><select name="company_id" class="form-select form-select-sm w-auto"><?= $opts($s['company_id']) ?></select>
    <input name="position" class="form-control form-control-sm w-auto" value="<?= e($s['position']) ?>" placeholder="Position"><input type="hidden" name="department" value="<?= e($s['department']) ?>"><button class="btn btn-sm btn-outline-primary">Save</button></form></td>
  <td><span class="badge text-bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $s['status'] ?></span></td>
  <td class="text-end text-nowrap"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><button name="action" value="toggle" class="btn btn-sm btn-outline-warning"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button> <button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Delete this staff account?"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; if (!$list) echo '<tr><td colspan="4" class="text-center text-muted py-4">No staff accounts.</td></tr>'; ?></tbody></table></div></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
