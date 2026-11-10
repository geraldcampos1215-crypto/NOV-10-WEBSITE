<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Forgot Password'; $layout = 'auth'; $link = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('danger', 'Enter a valid email address.');
    else {
        if (val('SELECT id FROM users WHERE email=?', [$email])) {
            $token = bin2hex(random_bytes(32));
            q('DELETE FROM password_resets WHERE email=?', [$email]);
            q('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?,?,?)', [$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
            $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('reset-password.php?token=' . $token);
            @mail($email, 'Reset your JobSeeker password', "Open this link within 1 hour:\n$link", 'From: ' . MAIL_FROM);
        }
        flash('success', 'If that email exists, a reset link has been sent.');
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap"><div class="auth-form"><div class="auth-card">
  <h3 class="fw-bold">Forgot password?</h3><p class="text-muted">Enter your email and we'll send a reset link.</p><?php show_flash(); ?>
  <?php if ($link): ?><div class="alert alert-warning small"><b>Local XAMPP demo:</b> no mail server is configured, so use this link directly:<br><a href="<?= e($link) ?>">Reset my password</a></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email address" required></div><button class="btn btn-primary w-100">Send reset link</button></form>
  <p class="text-center small mt-3"><a href="<?= url('login.php') ?>">Back to login</a></p>
</div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
