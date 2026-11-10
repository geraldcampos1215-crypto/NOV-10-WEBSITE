
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/verification.php';

if (user()) redirect(home_for(user()['role']));

$pageTitle = 'Verify Email';
$layout = 'auth';

$email = $_SESSION['pending_email'] ?? '';

$p = $email
    ? row('SELECT * FROM pending_registrations WHERE email=?', [$email])
    : null;

if (!$p) {
    flash('danger', 'No registration in progress. Please register again.');
    redirect('register.php');
}

$expiresTs = strtotime($p['expires_at']);
$expired = $expiresTs < time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'verify';

    // Cancel registration
    if ($action === 'cancel') {

        q('DELETE FROM pending_registrations WHERE email=?', [$email]);

        unset($_SESSION['pending_email'], $_SESSION['dev_code']);

        redirect('register.php');
    }

    // Resend verification code
    if ($action === 'resend') {

        $wait = VERIFY_RESEND_SECONDS - (
            time() - strtotime($p['last_sent'])
        );

        if ($wait > 0) {

            flash('danger', "Please wait $wait second(s) before requesting a new code.");

        } else {

            $code = send_verification_code($email, $p['full_name']);

            if (defined('SHOW_CODE_ON_SCREEN') && SHOW_CODE_ON_SCREEN) {
                $_SESSION['dev_code'] = $code;
            }

            flash(
                'success',
                'A new code has been sent. It is valid for ' .
                VERIFY_MINUTES . ' minutes.'
            );
        }

        redirect('verify-email.php');
    }

    // Verify code
    $input = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if ($expired) {

        flash('danger', 'This code has expired. Click "Resend code" to get a new one.');

    } elseif ($p['attempts'] >= VERIFY_MAX_TRIES) {

        flash('danger', 'Too many wrong attempts. Click "Resend code" to get a new one.');

    } elseif (
        strlen($input) !== 6 ||
        !hash_equals($p['code_hash'], hash('sha256', $input))
    ) {

        q(
            'UPDATE pending_registrations SET attempts=attempts+1 WHERE id=?',
            [$p['id']]
        );

        $left = VERIFY_MAX_TRIES - ($p['attempts'] + 1);

        flash(
            'danger',
            'Incorrect code. ' .
            ($left > 0 ? "$left attempt(s) left." : 'Request a new code.')
        );

    } elseif (val('SELECT id FROM users WHERE email=?', [$email])) {

        q('DELETE FROM pending_registrations WHERE email=?', [$email]);

        flash('danger', 'This email is already registered. Please log in.');

        redirect('login.php');

    } else {

        // Create verified user account
        $username = preg_replace(
            '/[^a-z0-9]/',
            '',
            strtolower(strstr($email, '@', true))
        ) ?: 'user';

        if (val('SELECT id FROM users WHERE username=?', [$username])) {
            $username .= random_int(100, 999);
        }

        q(
            'INSERT INTO users
            (full_name, username, email, password, role)
            VALUES (?,?,?,?,?)',
            [
                $p['full_name'],
                $username,
                $email,
                $p['password'],
                'seeker'
            ]
        );

        $uid = last_id();

        // Save additional registration information
        q(
            'INSERT INTO user_profiles
            (user_id, date_of_birth, phone, sex, terms_accepted_at)
            VALUES (?,?,?,?,?)',
            [
                $uid,
                $p['date_of_birth'],
                $p['phone'],
                $p['sex'],
                $p['terms_accepted_at']
            ]
        );

        // Welcome notification
        q(
            'DELETE FROM pending_registrations WHERE email=?',
            [$email]
        );

        notify(
            $uid,
            'Welcome to JobSeeker',
            'Complete your profile and upload your resume to start applying.'
        );

        log_activity(
            'User registered (email verified)',
            $email,
            $uid
        );

        unset($_SESSION['pending_email'], $_SESSION['dev_code']);

        session_regenerate_id(true);

        $_SESSION['uid'] = $uid;
        $_SESSION['role'] = 'seeker';

        flash('success', 'Email verified! Welcome to JobSeeker.');

        redirect('seeker/dashboard.php');
    }

    redirect('verify-email.php');
}

include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">

    <div class="auth-form">
        <div class="auth-card">

            <h3 class="fw-bold">Verify your email</h3>

            <p class="text-muted">
                Enter the 6-digit code we sent to
                <b><?= e($email) ?></b>.
            </p>

            <?php show_flash(); ?>

            <?php if (!empty($_SESSION['dev_code'])): ?>

                <div class="alert alert-warning small">
                    <b>Local XAMPP demo:</b>
                    Your verification code is
                    <b class="fs-5"><?= e($_SESSION['dev_code']) ?></b>
                </div>

            <?php endif; ?>

            <form method="post">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="verify">

                <div class="mb-3">

                    <input
                        name="code"
                        class="form-control form-control-lg text-center"
                        style="letter-spacing:.6em"
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        required
                        autofocus>

                </div>

                <div class="small text-center mb-3 <?= $expired ? 'text-danger' : 'text-muted' ?>"
                     id="timer">

                    <?= $expired ? 'Code expired.' : '' ?>

                </div>

                <button class="btn btn-primary w-100">
                    Verify &amp; create account
                </button>

            </form>

            <div class="d-flex justify-content-between mt-3 small">

                <form method="post">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="resend">

                    <button class="btn btn-link p-0 small">
                        Resend code
                    </button>

                </form>

                <form method="post">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="cancel">

                    <button class="btn btn-link p-0 small text-muted">
                        Use a different email
                    </button>

                </form>

            </div>

        </div>
    </div>

</div>

<script>
(function () {

    var end = <?= (int)$expiresTs ?> * 1000;

    var el = document.getElementById('timer');

    if (end <= Date.now()) return;

    (function tick() {

        var s = Math.max(
            0,
            Math.floor((end - Date.now()) / 1000)
        );

        el.textContent = s
            ? 'Code expires in ' +
              Math.floor(s / 60) + ':' +
              String(s % 60).padStart(2, '0')
            : 'Code expired.';

        if (!s) {
            el.className = 'small text-center mb-3 text-danger';
        } else {
            setTimeout(tick, 1000);
        }

    })();

})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
