<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'System Settings';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'settings') {
        $vals = ['site_name' => trim($_POST['site_name']), 'contact_email' => trim($_POST['contact_email']), 'contact_phone' => trim($_POST['contact_phone']), 'contact_address' => trim($_POST['contact_address']),
                 'jobs_per_page' => (string)max(3, min(30, (int)$_POST['jobs_per_page'])), 'email_notifications' => !empty($_POST['email_notifications']) ? '1' : '0', 'allow_registration' => !empty($_POST['allow_registration']) ? '1' : '0'];
        foreach ($vals as $k => $v) q('INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$k, $v]);
        log_activity('Settings updated', ''); flash('success', 'Settings saved.');
    } elseif (($_POST['action'] ?? '') === 'password') {
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $u['password'])) flash('danger', 'Current password is incorrect.');
        elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('danger', 'New password needs 8+ characters with letters and numbers.');
        else { q('UPDATE users SET password=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]); log_activity('Admin password changed', ''); flash('success', 'Password changed.'); }
    }
    redirect('admin/settings.php');
}
$s = array_column(rows('SELECT setting_key, setting_value FROM system_settings'), 'setting_value', 'setting_key');
$perm = [['View jobs', 1, 1, 1], ['Apply for jobs', 1, 0, 0], ['Manage job postings', 0, 1, 1], ['Review applications', 0, 1, 1], ['Interviews & announcements', 0, 1, 1], ['User management', 0, 0, 1], ['Staff management', 0, 0, 1], ['Companies & categories', 0, 0, 1], ['Reports & analytics', 0, 'Own company', 1], ['System settings & logs', 0, 0, 1]];
$mark = fn($v) => $v === 1 ? '<i class="bi bi-check-lg text-success"></i>' : ($v === 0 ? '<span class="text-muted">—</span>' : '<small>' . e($v) . '</small>');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-7"><form method="post" class="card p-4"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><h5 class="fw-bold mb-3">General settings</h5><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Site name</label><input name="site_name" class="form-control" value="<?= e($s['site_name'] ?? '') ?>"></div>
  <div class="col-md-6"><label class="form-label">Jobs per page</label><input type="number" name="jobs_per_page" min="3" max="30" class="form-control" value="<?= e($s['jobs_per_page'] ?? 6) ?>"></div>
  <div class="col-md-6"><label class="form-label">Contact email</label><input name="contact_email" class="form-control" value="<?= e($s['contact_email'] ?? '') ?>"></div>
  <div class="col-md-6"><label class="form-label">Contact phone</label><input name="contact_phone" class="form-control" value="<?= e($s['contact_phone'] ?? '') ?>"></div>
  <div class="col-12"><label class="form-label">Address</label><input name="contact_address" class="form-control" value="<?= e($s['contact_address'] ?? '') ?>"></div>
  <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="email_notifications" id="en" <?= ($s['email_notifications'] ?? '') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="en">Send email notifications (requires mail server)</label></div>
  <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_registration" id="ar" <?= ($s['allow_registration'] ?? '') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="ar">Allow new job seeker registrations</label></div></div>
  </div><div class="mt-3"><button class="btn btn-primary">Save settings</button></div></form>
  <div class="card p-4 mt-4"><h6 class="fw-bold">Change your password</h6><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="password"><div class="col-md-5"><input type="password" name="current_password" class="form-control" placeholder="Current" required></div><div class="col-md-5"><input type="password" name="new_password" class="form-control" placeholder="New" required></div><div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Update</button></div></form></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Role-based access summary</div><div class="table-responsive"><table class="table table-sm align-middle mb-0 text-center"><thead><tr><th class="text-start">Feature</th><th>Seeker</th><th>Staff</th><th>Admin</th></tr></thead><tbody>
<?php foreach ($perm as [$f, $a, $b, $c]) echo '<tr><td class="text-start">' . e($f) . '</td><td>' . $mark($a) . '</td><td>' . $mark($b) . '</td><td>' . $mark($c) . '</td></tr>'; ?></tbody></table></div>
<div class="card-body small text-muted">Change a user's role from <a href="users.php">Users Management</a>; assign staff to companies in <a href="staff.php">Staff Management</a>.</div></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
