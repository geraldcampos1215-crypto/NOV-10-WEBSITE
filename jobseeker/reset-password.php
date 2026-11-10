<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Reset Password'; $layout = 'auth';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$r = $token ? row('SELECT * FROM password_resets WHERE token_hash=? AND expires_at > NOW()', [hash('sha256', $token)]) : null;
if (!$r) { flash('danger', 'This reset link is invalid or has expired.'); redirect('forgot-password.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) flash('danger', 'Use at least 8 characters with letters and numbers.');
    elseif ($pw !== ($_POST['confirm'] ?? '')) flash('danger', 'Passwords do not match.');
    else {
        q('UPDATE users SET password=?, failed_attempts=0, locked_until=NULL WHERE email=?', [password_hash($pw, PASSWORD_DEFAULT), $r['email']]);
        q('DELETE FROM password_resets WHERE email=?', [$r['email']]);
        log_activity('Password reset', $r['email']);
        flash('success', 'Password updated. You can now log in.'); redirect('login.php');
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap"><div class="auth-form"><div class="auth-card">
  <h3 class="fw-bold">Set a new password</h3><?php show_flash(); ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="New password" required></div>
    <div class="mb-3"><input type="password" name="confirm" class="form-control" placeholder="Confirm password" required></div>
    <button class="btn btn-primary w-100">Update password</button></form>
</div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
