<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/verification.php';

if (user()) redirect(home_for(user()['role']));

$pageTitle = 'Register';
$layout = 'auth';
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw    = $_POST['password'] ?? '';
    $pw2   = $_POST['confirm'] ?? '';

    $dob   = trim($_POST['date_of_birth'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $sex   = trim($_POST['sex'] ?? '');
    $terms = isset($_POST['terms']);

    if (setting('allow_registration', '1') !== '1') {
        $err['form'] = 'Registration is currently closed.';
    }

    if (strlen($name) < 3) {
        $err['full_name'] = 'Enter your full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Enter a valid email address.';
    } elseif (val('SELECT id FROM users WHERE email=?', [$email])) {
        $err['email'] = 'This email is already registered.';
    }

    // Birthday validation
    $date = DateTime::createFromFormat('!Y-m-d', $dob);

    if (
        !$date ||
        $date->format('Y-m-d') !== $dob ||
        $dob > date('Y-m-d')
    ) {
        $err['date_of_birth'] = 'Enter a valid date of birth.';
    }

    // Phone validation
    $digits = preg_replace('/\D/', '', $phone);

    if (strlen($digits) < 7 || strlen($digits) > 15) {
        $err['phone'] = 'Enter a valid phone number.';
    }

    // Sex validation
    if (!in_array($sex, ['Male', 'Female', 'Prefer not to say'], true)) {
        $err['sex'] = 'Please select your sex.';
    }

    // Password validation
    if (
        strlen($pw) < 8 ||
        !preg_match('/[A-Z]/', $pw) ||
        !preg_match('/[a-z]/', $pw) ||
        !preg_match('/[0-9]/', $pw) ||
        !preg_match('/[^A-Za-z0-9]/', $pw)
    ) {
        $err['password'] = 'Password must contain at least 8 characters, uppercase and lowercase letters, a number, and a special character.';
    }

    if ($pw !== $pw2) {
        $err['confirm'] = 'Passwords do not match.';
    }

    if (!$terms) {
        $err['terms'] = 'You must accept the Terms and Conditions and Privacy Policy.';
    }

    if (!$err) {

        q('DELETE FROM pending_registrations WHERE email=?', [$email]);

        $acceptedAt = date('Y-m-d H:i:s');

        q(
            'INSERT INTO pending_registrations
            (full_name, email, password, date_of_birth, phone, sex,
             terms_accepted_at, code_hash, expires_at, last_sent)
            VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $name,
                $email,
                password_hash($pw, PASSWORD_DEFAULT),
                $dob,
                $phone,
                $sex,
                $acceptedAt,
                '',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]
        );

        $code = send_verification_code($email, $name);

        $_SESSION['pending_email'] = $email;

        if (defined('SHOW_CODE_ON_SCREEN') && SHOW_CODE_ON_SCREEN) {
            $_SESSION['dev_code'] = $code;
        }

        flash('success', 'We sent a 6-digit verification code to ' . $email . '.');

        redirect('verify-email.php');
    }
}

// ---- View helpers (display only) ----

// Floating-label field. $static = label always sits on top (for date inputs)
$f = function ($key, $label, $type, $icon, $placeholder = ' ', $attrs = '', $static = false) use ($err) {
    $value = $type === 'password' ? '' : e($_POST[$key] ?? '');
    $bad   = isset($err[$key]) ? ' has-error' : '';
    $eye   = '';
    if ($type === 'password') {
        $eye = '<button type="button" class="lg-eye" data-lg-eye data-target="' . $key . '" aria-label="Show password"><i class="bi bi-eye"></i></button>';
    }
    $auto = $type === 'password' ? ' autocomplete="new-password"' : '';
    return '
    <div class="lg-msg-wrap">
        <div class="lg-float' . $bad . ($static ? ' static' : '') . '">
            <input type="' . $type . '" id="' . $key . '" name="' . $key . '" value="' . $value . '"
                   placeholder="' . e($placeholder) . '"' . $auto . ' ' . $attrs . ' required>
            <label for="' . $key . '">' . $label . '</label>
            <i class="bi ' . $icon . ' ico"></i>' . $eye . '
        </div>
        <div class="lg-msg">' . e($err[$key] ?? '') . '</div>
    </div>';
};

// Slideshow: every image in assets/img/login/ becomes a background slide
$captions = [
    'Find work you’re proud of',
    'Hire the right people',
    'Every career starts with one step',
    'Teams are built here',
    'Your next opportunity awaits',
    'Work that fits your life',
];
$slideFiles = [];
foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
    $slideFiles = array_merge($slideFiles, glob(ROOT_PATH . '/assets/img/login/*.' . $ext) ?: []);
}
natcasesort($slideFiles);
$slideFiles = array_slice(array_values($slideFiles), 0, 6);
$pics = [];
foreach ($slideFiles as $fl) $pics[] = url('assets/img/login/' . rawurlencode(basename($fl)));
$count = max(1, count($pics));

include __DIR__ . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .lg {
        --ink: #14213d;
        --ink-soft: #5b6b85;
        --brand: #1f5fe0;          /* the one blue */
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --sky: #3b78f2;
        --bad: #c2362b;
        --bad-tint: #fdeceb;
        --slide-ms: 5500ms;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #fff;
        position: relative; overflow: hidden; isolation: isolate;
        min-height: 100vh; display: flex; flex-direction: column;
        background: var(--sky);
    }
    .lg h1, .lg h2, .lg h3 { font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif; letter-spacing: -.02em; }

    /* Background: photos (optional) + blue wash + circles */
    .lg-bg { position: absolute; inset: 0; z-index: -3; }
    .lg-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1.2s ease; }
    .lg-slide.is-active { opacity: 1; }
    .lg-slide .pic { position: absolute; inset: 0; background-size: cover; background-position: center; }
    .lg-slide.is-active .pic { animation: lg-zoom calc(var(--slide-ms) + 1800ms) ease-out forwards; }
    @keyframes lg-zoom { from { transform: scale(1); } to { transform: scale(1.07); } }
    .lg-wash { position: absolute; inset: 0; z-index: -2; background: rgba(59, 120, 242, .9); }
    .lg-wash.solid { background: var(--sky); }
    .lg-circle { position: absolute; border-radius: 50%; z-index: -1; pointer-events: none; }
    .lg-circle.c1 { width: 1500px; height: 1500px; left: 18%; top: -1060px; background: rgba(31, 95, 224, .78); }
    .lg-circle.c2 { width: 1050px; height: 1050px; left: 34%; top: -820px; background: rgba(26, 82, 201, .85); }
    .lg-circle.c3 { width: 640px;  height: 640px;  left: 48%; top: -560px; background: rgba(21, 70, 168, .9); }
    .lg-circle.c4 { width: 900px;  height: 900px;  left: -420px; bottom: -640px; background: rgba(31, 95, 224, .55); }
    @keyframes lg-drift { from { transform: translate3d(0,0,0); } to { transform: translate3d(0, 14px, 0); } }
    .lg-circle.c2 { animation: lg-drift 9s ease-in-out infinite alternate; }
    .lg-circle.c3 { animation: lg-drift 7s ease-in-out infinite alternate-reverse; }

    /* Top bar */
    .lg-nav { width: 100%; max-width: 1200px; margin: 0 auto; padding: 1.6rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
    .lg-brand { color: #fff; text-decoration: none; font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.7rem; letter-spacing: -.01em; }
    .lg-brand b { font-weight: 800; }
    .lg-brand span { font-weight: 500; opacity: .9; }
    .lg-brand:hover { color: #fff; }
    .lg-links { display: flex; align-items: center; gap: 2rem; }
    .lg-links a { position: relative; color: #fff; text-decoration: none; font-weight: 600; font-size: .98rem; padding: .3rem 0; }
    .lg-links a::after {
        content: ""; position: absolute; left: 0; right: 0; bottom: -2px; height: 2px; background: #fff; border-radius: 2px;
        transform: scaleX(0); transform-origin: left; transition: transform .25s ease;
    }
    .lg-links a:hover::after, .lg-links a:focus-visible::after { transform: scaleX(1); }
    .lg-links a:hover { color: #fff; }
    .lg-links .signup { padding: .5rem 1.15rem; border: 1.5px solid rgba(255,255,255,.7); border-radius: 999px; transition: background-color .2s ease, color .2s ease; }
    .lg-links .signup::after { display: none; }
    .lg-links .signup:hover { background: #fff; color: var(--brand); }

    /* Hero */
    .lg-hero {
        flex: 1; width: 100%; max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem 3rem; display: grid;
        grid-template-columns: 1fr 1fr; gap: 3rem; align-items: start;
    }
    .lg-copy { position: sticky; top: 2rem; padding-top: 3rem; }
    .lg-copy h1 { font-size: clamp(2.8rem, 6vw, 5rem); font-weight: 800; line-height: .98; margin: 0 0 .9rem; }
    .lg-caps-stack { display: grid; margin-bottom: 1.1rem; }
    .lg-cap {
        grid-area: 1 / 1; font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(1.25rem, 2.2vw, 1.7rem); font-weight: 700; line-height: 1.25;
        opacity: 0; transform: translateY(12px); transition: opacity .7s ease, transform .7s ease;
    }
    .lg-cap.is-active { opacity: 1; transform: none; transition-delay: .2s; }
    .lg-copy .para { max-width: 50ch; color: #e3ecff; font-size: 1.05rem; line-height: 1.65; margin: 0 0 1.75rem; }
    .lg-learn {
        display: inline-flex; align-items: center; gap: .55rem; color: #fff; text-decoration: none; font-weight: 600; font-size: .95rem;
        padding: .7rem 1.7rem; border: 2px solid #fff; border-radius: 999px; transition: background-color .2s ease, color .2s ease, transform .15s ease;
    }
    .lg-learn i { transition: transform .2s ease; }
    .lg-learn:hover { background: #fff; color: var(--brand); }
    .lg-learn:hover i { transform: translateX(4px); }
    .lg-learn:active { transform: scale(.97); }
    .lg-dots { display: flex; gap: .55rem; margin-top: 2rem; }
    .lg-dot { width: 12px; height: 12px; padding: 0; border-radius: 50%; border: 1.5px solid #fff; background: transparent; cursor: pointer; transition: background-color .25s ease, transform .25s ease; }
    .lg-dot:hover { transform: scale(1.2); }
    .lg-dot.is-active { background: #fff; }
    .lg-dot:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    .lg.single .lg-dots { display: none; }

    /* Card */
    .lg-cardwrap { display: flex; justify-content: center; }
    .lg-card {
        width: 100%; max-width: 540px; background: #fff; color: var(--ink); border-radius: 30px; padding: 2.2rem 2.1rem 1.9rem;
        box-shadow: 0 0 0 7px rgba(255, 255, 255, .22), 0 28px 60px rgba(8, 30, 100, .35);
    }
    .lg-card h2 { font-size: 1.65rem; font-weight: 800; margin: 0 0 .25rem; text-align: center; }
    .lg-card .sub { text-align: center; color: var(--ink-soft); margin: 0 0 1.5rem; font-size: .95rem; }
    .lg-logo { display: block; width: fit-content; margin: 0 auto 1rem; color: var(--brand); font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700; font-size: 1rem; text-decoration: none; }
    .lg-logo::after { content: ""; display: block; height: 2px; width: 100%; background: var(--brand); margin-top: .35rem; border-radius: 2px; }
    .lg-logo i { margin-right: .3rem; }

    @keyframes lg-rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
    @keyframes lg-shake { 0%, 100% { transform: none; } 20% { transform: translateX(-8px); } 40% { transform: translateX(7px); } 60% { transform: translateX(-5px); } 80% { transform: translateX(3px); } }
    .lg-nav, .lg-copy > * { animation: lg-rise .7s cubic-bezier(.2, .7, .2, 1) both; }
    .lg-copy > *:nth-child(2) { animation-delay: .08s; }
    .lg-copy > *:nth-child(3) { animation-delay: .16s; }
    .lg-copy > *:nth-child(4) { animation-delay: .24s; }
    .lg-copy > *:nth-child(5) { animation-delay: .32s; }
    .lg-card { animation: lg-rise .7s .15s cubic-bezier(.2, .7, .2, 1) both; }
    .lg-card.shake { animation: lg-rise .7s .15s cubic-bezier(.2,.7,.2,1) both, lg-shake .45s .9s; }

    .lg-alert { display: flex; gap: .6rem; align-items: flex-start; background: var(--bad-tint); color: #8c2a21; border: 1px solid #f3c6c1; border-radius: 12px; padding: .8rem .95rem; font-size: .92rem; margin-bottom: 1.1rem; }
    .lg-alert i { margin-top: .12rem; }

    /* Rows of two fields */
    .lg-two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 .9rem; }

    /* Floating-label fields */
    .lg-msg-wrap { margin-bottom: 1rem; }
    .lg-float { position: relative; }
    .lg-float input, .lg-float select {
        width: 100%; height: 58px; padding: 1.45rem 3rem .4rem 2.8rem; font-size: .98rem; color: var(--ink); font-family: inherit;
        background: #fff; border: 1.5px solid #c9d6ee; border-radius: 14px; outline: 0; appearance: none; -webkit-appearance: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .lg-float input::placeholder { color: transparent; transition: color .2s ease; }
    .lg-float input:focus::placeholder { color: #9aa7bd; }
    .lg-float input:hover, .lg-float select:hover { border-color: #9db9f2; }
    .lg-float input:focus, .lg-float select:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(31, 95, 224, .15); }
    .lg-float label {
        position: absolute; left: 2.8rem; top: 50%; transform: translateY(-50%); transform-origin: left top;
        color: #6b7a93; pointer-events: none; font-size: .98rem; max-width: calc(100% - 4rem); white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis; transition: top .2s ease, transform .2s ease, color .2s ease;
    }
    .lg-float input:focus + label,
    .lg-float input:not(:placeholder-shown) + label,
    .lg-float input:-webkit-autofill + label,
    .lg-float.static label,
    .lg-float select:focus + label { top: .55rem; transform: translateY(0) scale(.76); color: var(--brand); }
    .lg-float input:not(:focus):not(:placeholder-shown) + label,
    .lg-float.static input:not(:focus) + label,
    .lg-float.static select:not(:focus) + label { color: var(--ink-soft); }
    .lg-float .ico { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #8a97ad; font-size: 1.05rem; pointer-events: none; transition: color .2s ease; }
    .lg-float input:focus ~ .ico, .lg-float select:focus ~ .ico { color: var(--brand); }
    .lg-float .chev { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #8a97ad; pointer-events: none; font-size: .85rem; }
    .lg-eye {
        position: absolute; right: .45rem; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border: 0;
        background: transparent; color: #8a97ad; border-radius: 10px; display: grid; place-items: center; font-size: 1.05rem;
        transition: color .2s ease, background-color .2s ease;
    }
    .lg-eye:hover { color: var(--brand); background: var(--brand-tint); }
    .lg-eye:focus-visible { outline: 3px solid rgba(31, 95, 224, .4); }

    .lg-float.has-error input, .lg-float.has-error select { border-color: var(--bad); }
    .lg-float.has-error input:focus, .lg-float.has-error select:focus { box-shadow: 0 0 0 4px rgba(194, 54, 43, .15); }
    .lg-float.has-error label, .lg-float.has-error .ico { color: var(--bad) !important; }
    .lg-msg { display: none; margin: .4rem .2rem 0; font-size: .82rem; color: var(--bad); line-height: 1.4; }
    .lg-float.has-error + .lg-msg { display: block; }
    .lg-matchmsg { font-size: .82rem; margin: -.4rem .2rem 1rem; min-height: 1.1em; transition: color .2s ease; }
    .lg-matchmsg.ok { color: var(--brand); }
    .lg-matchmsg.no { color: var(--bad); }

    /* Password strength */
    .lg-strength { margin: -.2rem 0 1.1rem; }
    .lg-strength-top { display: flex; justify-content: space-between; align-items: center; font-size: .8rem; color: var(--ink-soft); margin-bottom: .4rem; }
    .lg-strength-top b { color: var(--ink); font-weight: 600; }
    .lg-bar { height: 6px; background: #e1e9f7; border-radius: 99px; overflow: hidden; }
    .lg-bar span { display: block; height: 100%; width: 0; background: var(--brand); border-radius: 99px; transition: width .3s ease; }
    .lg-reqs { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .65rem; }
    .lg-req {
        display: inline-flex; align-items: center; gap: .3rem; font-size: .76rem; padding: .22rem .6rem; border-radius: 999px;
        background: #eef2f9; color: var(--ink-soft); transition: background-color .25s ease, color .25s ease;
    }
    .lg-req i { font-size: .7rem; opacity: .6; }
    .lg-req.ok { background: var(--brand-tint); color: var(--brand-dark); font-weight: 600; }
    .lg-req.ok i { opacity: 1; }

    /* Terms */
    .lg-check { display: flex; align-items: flex-start; gap: .65rem; cursor: pointer; font-size: .88rem; color: var(--ink-soft); line-height: 1.45; user-select: none; }
    .lg-check input { position: absolute; opacity: 0; pointer-events: none; }
    .lg-check .box {
        width: 20px; height: 20px; flex: none; margin-top: .1rem; border-radius: 6px; border: 1.5px solid #b4c1d6; display: grid; place-items: center; color: transparent;
        font-size: .75rem; transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .15s ease;
    }
    .lg-check:hover .box { border-color: var(--brand); }
    .lg-check input:checked + .box { background: var(--brand); border-color: var(--brand); color: #fff; }
    .lg-check input:active + .box { transform: scale(.9); }
    .lg-check input:focus-visible + .box { outline: 3px solid rgba(31, 95, 224, .4); outline-offset: 2px; }
    .lg-check.has-error .box { border-color: var(--bad); }
    .lg-check a, .lg-link { color: var(--brand); font-weight: 600; text-decoration: none; }
    .lg-check a:hover, .lg-link:hover { color: var(--brand-dark); text-decoration: underline; }
    .lg-terms { margin: .3rem 0 1.4rem; }

    .lg-btn {
        width: 100%; height: 54px; border: 0; border-radius: 14px; background: var(--brand); color: #fff; font-weight: 700; font-size: 1.05rem;
        letter-spacing: .02em; display: inline-flex; align-items: center; justify-content: center; gap: .6rem;
        transition: background-color .2s ease, transform .15s ease, box-shadow .2s ease;
    }
    .lg-btn:hover { background: var(--brand-dark); box-shadow: 0 10px 22px rgba(31, 95, 224, .38); transform: translateY(-1px); }
    .lg-btn:active { transform: scale(.985); }
    .lg-btn:focus-visible { outline: 3px solid rgba(31, 95, 224, .45); outline-offset: 3px; }
    .lg-btn .arrow { transition: transform .2s ease; }
    .lg-btn:hover .arrow { transform: translateX(5px); }
    .lg-btn .spin { display: none; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: lg-spin .7s linear infinite; }
    .lg-btn.loading { pointer-events: none; background: var(--brand-dark); }
    .lg-btn.loading .spin { display: inline-block; }
    .lg-btn.loading .arrow { display: none; }
    @keyframes lg-spin { to { transform: rotate(360deg); } }
    .lg-signup { text-align: center; margin: 1.3rem 0 0; color: var(--ink-soft); font-size: .93rem; }

    /* Responsive */
    @media (max-width: 991.98px) {
        .lg-hero { grid-template-columns: 1fr; gap: 2rem; padding-bottom: 2.5rem; }
        .lg-copy { position: static; padding-top: 0; text-align: center; }
        .lg-copy .para { margin-left: auto; margin-right: auto; }
        .lg-dots { justify-content: center; }
        .lg-circle.c1 { left: -10%; } .lg-circle.c2 { left: 5%; } .lg-circle.c3 { left: 20%; }
    }
    @media (max-width: 767.98px) {
        .lg-links a:not(.signup) { display: none; }
        .lg-nav { padding: 1.2rem 1.1rem; }
        .lg-brand { font-size: 1.4rem; }
        .lg-copy .para, .lg-learn { display: none; }
        .lg-copy h1 { font-size: 2.6rem; }
        .lg-dots { margin-top: 1rem; }
        .lg-hero { padding: .5rem 1rem 2rem; }
        .lg-card { padding: 1.9rem 1.2rem 1.5rem; }
    }
    @media (max-width: 575.98px) { .lg-two { grid-template-columns: 1fr; } }
    @media (prefers-reduced-motion: reduce) { .lg *, .lg *::after { transition: none !important; animation: none !important; } }
</style>

<div class="lg <?= $count < 2 ? 'single' : '' ?>">

    <!-- Background -->
    <div class="lg-bg" aria-hidden="true">
        <?php foreach ($pics as $i => $src): ?>
            <div class="lg-slide <?= $i === 0 ? 'is-active' : '' ?>"><div class="pic" style="background-image:url('<?= e($src) ?>')"></div></div>
        <?php endforeach; ?>
    </div>
    <div class="lg-wash <?= $pics ? '' : 'solid' ?>" aria-hidden="true"></div>
    <span class="lg-circle c1" aria-hidden="true"></span>
    <span class="lg-circle c2" aria-hidden="true"></span>
    <span class="lg-circle c3" aria-hidden="true"></span>
    <span class="lg-circle c4" aria-hidden="true"></span>

    <!-- Top bar -->
    <header class="lg-nav">
        <a href="<?= url('index.php') ?>" class="lg-brand"><b>JOB</b><span>SEEKER</span></a>
        <nav class="lg-links" aria-label="Main">
            <a href="<?= url('index.php') ?>">Home</a>
            <a href="<?= url('jobs.php') ?>">Jobs</a>
            <a href="<?= url('about.php') ?>">About</a>
            <a href="<?= url('login.php') ?>" class="signup">Log in</a>
        </nav>
    </header>

    <!-- Hero -->
    <main class="lg-hero">

        <section class="lg-copy">
            <h1>Create your account.</h1>

            <div class="lg-caps-stack" aria-live="polite">
                <?php for ($i = 0; $i < $count; $i++): ?>
                    <div class="lg-cap <?= $i === 0 ? 'is-active' : '' ?>"><?= e($captions[$i % count($captions)]) ?></div>
                <?php endfor; ?>
            </div>

            <p class="para">Join our community. Apply to jobs in minutes and track every application in real time.</p>

            <a href="<?= url('jobs.php') ?>" class="lg-learn">Browse jobs <i class="bi bi-arrow-right"></i></a>

            <div class="lg-dots" role="tablist">
                <?php for ($i = 0; $i < $count; $i++): ?>
                    <button type="button" class="lg-dot <?= $i === 0 ? 'is-active' : '' ?>" role="tab" aria-label="Show slide <?= $i + 1 ?>"></button>
                <?php endfor; ?>
            </div>
        </section>

        <section class="lg-cardwrap">
            <div class="lg-card <?= $err ? 'shake' : '' ?>">

                <a href="<?= url('index.php') ?>" class="lg-logo"><i class="bi bi-briefcase-fill"></i>JobSeeker</a>
                <h2>Create an account</h2>
                <p class="sub">Fill in your details to get started.</p>

                <?php if (!empty($err['form'])): ?>
                    <div class="lg-alert" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div><?= e($err['form']) ?></div></div>
                <?php endif; ?>

                <form method="post" id="regForm" novalidate>
                    <?= csrf_field() ?>

                    <?= $f('full_name', 'Full name', 'text', 'bi-person', 'Enter your full name', 'autocomplete="name"') ?>

                    <?= $f('email', 'Email', 'email', 'bi-envelope', 'example@email.com', 'autocomplete="email"') ?>

                    <div class="lg-two">
                        <!-- Date of birth -->
                        <div class="lg-msg-wrap">
                            <div class="lg-float static <?= isset($err['date_of_birth']) ? 'has-error' : '' ?>">
                                <input type="date" id="date_of_birth" name="date_of_birth" placeholder=" "
                                       value="<?= e($_POST['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                                <label for="date_of_birth">Date of birth</label>
                                <i class="bi bi-calendar3 ico"></i>
                            </div>
                            <div class="lg-msg"><?= e($err['date_of_birth'] ?? '') ?></div>
                        </div>

                        <!-- Sex -->
                        <div class="lg-msg-wrap">
                            <div class="lg-float static <?= isset($err['sex']) ? 'has-error' : '' ?>">
                                <select name="sex" id="sex" required>
                                    <option value="">Select</option>
                                    <?php foreach (['Male', 'Female', 'Prefer not to say'] as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= ($_POST['sex'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="sex">Sex</label>
                                <i class="bi bi-gender-ambiguous ico"></i>
                                <i class="bi bi-chevron-down chev"></i>
                            </div>
                            <div class="lg-msg"><?= e($err['sex'] ?? '') ?></div>
                        </div>
                    </div>

                    <?= $f('phone', 'Phone number', 'tel', 'bi-telephone', '+63 9XX XXX XXXX', 'maxlength="20" autocomplete="tel"') ?>

                    <!-- Password + strength -->
                    <?= $f('password', 'Password', 'password', 'bi-lock', 'Create a strong password') ?>

                    <div class="lg-strength">
                        <div class="lg-strength-top"><span>Password strength</span><b id="strengthText">Not entered</b></div>
                        <div class="lg-bar"><span id="strengthFill"></span></div>
                        <div class="lg-reqs">
                            <span class="lg-req" id="reqLength"><i class="bi bi-circle"></i> 8+ characters</span>
                            <span class="lg-req" id="reqUpper"><i class="bi bi-circle"></i> Uppercase</span>
                            <span class="lg-req" id="reqLower"><i class="bi bi-circle"></i> Lowercase</span>
                            <span class="lg-req" id="reqNumber"><i class="bi bi-circle"></i> Number</span>
                            <span class="lg-req" id="reqSpecial"><i class="bi bi-circle"></i> Special character</span>
                        </div>
                    </div>

                    <!-- Confirm password -->
                    <?= $f('confirm', 'Confirm password', 'password', 'bi-shield-lock', 'Re-enter your password') ?>
                    <div id="matchMessage" class="lg-matchmsg" aria-live="polite"></div>

                    <!-- Terms -->
                    <div class="lg-terms">
                        <label class="lg-check <?= isset($err['terms']) ? 'has-error' : '' ?>" for="terms">
                            <input type="checkbox" name="terms" id="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                            <span class="box"><i class="bi bi-check-lg"></i></span>
                            <span>I agree to the
                                <a href="<?= url('terms.php') ?>" target="_blank">Terms and Conditions</a> and
                                <a href="<?= url('privacy.php') ?>" target="_blank">Privacy Policy</a>.</span>
                        </label>
                        <?php if (isset($err['terms'])): ?>
                            <div class="lg-msg" style="display:block"><?= e($err['terms']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="lg-btn" id="regBtn">
                        <span class="spin" aria-hidden="true"></span>
                        <span class="label">REGISTER</span>
                        <i class="bi bi-arrow-right arrow"></i>
                    </button>
                </form>

                <p class="lg-signup">Already have an account? <a class="lg-link" href="<?= url('login.php') ?>">Log in</a></p>
            </div>
        </section>

    </main>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var form = $('regForm'), btn = $('regBtn');

    /* Show / hide password (works for both password fields) */
    document.querySelectorAll('[data-lg-eye]').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = $(b.getAttribute('data-target'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            b.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    /* Password strength */
    var pw = $('password'), cf = $('confirm'),
        fill = $('strengthFill'), txt = $('strengthText'), match = $('matchMessage');
    var rules = {
        reqLength: function (v) { return v.length >= 8; },
        reqUpper:  function (v) { return /[A-Z]/.test(v); },
        reqLower:  function (v) { return /[a-z]/.test(v); },
        reqNumber: function (v) { return /[0-9]/.test(v); },
        reqSpecial: function (v) { return /[^A-Za-z0-9]/.test(v); }
    };
    var labels = ['Not entered', 'Weak', 'Weak', 'Fair', 'Good', 'Strong'];

    function checkMatch() {
        if (!cf.value) { match.textContent = ''; match.className = 'lg-matchmsg'; return; }
        var ok = pw.value === cf.value;
        match.textContent = ok ? 'Passwords match.' : 'Passwords do not match.';
        match.className = 'lg-matchmsg ' + (ok ? 'ok' : 'no');
    }
    function checkStrength() {
        var v = pw.value, met = 0;
        Object.keys(rules).forEach(function (id) {
            var ok = rules[id](v), el = $(id);
            el.classList.toggle('ok', ok);
            el.firstElementChild.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            if (ok) met++;
        });
        if (!v) met = 0;
        fill.style.width = (met * 20) + '%';
        txt.textContent = labels[met];
        checkMatch();
    }
    pw.addEventListener('input', checkStrength);
    cf.addEventListener('input', checkMatch);
    checkStrength();

    /* Clear a field's error once the person edits it */
    form.querySelectorAll('.lg-float input, .lg-float select').forEach(function (el) {
        ['input', 'change'].forEach(function (ev) {
            el.addEventListener(ev, function () { el.parentElement.classList.remove('has-error'); });
        });
    });
    $('terms').addEventListener('change', function () { this.closest('.lg-check').classList.remove('has-error'); });

    /* Loading state */
    form.addEventListener('submit', function () {
        btn.classList.add('loading');
        btn.querySelector('.label').textContent = 'CREATING ACCOUNT…';
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { btn.classList.remove('loading'); btn.querySelector('.label').textContent = 'REGISTER'; }
    });

    /* Bring the first error into view */
    var firstErr = form.querySelector('.has-error input, .has-error select, .lg-check.has-error');
    if (firstErr) setTimeout(function () { firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 400);

    /* Slideshow (background pictures + caption + dots) */
    var root = document.querySelector('.lg'),
        slides = root.querySelectorAll('.lg-slide'),
        caps = root.querySelectorAll('.lg-cap'),
        dots = root.querySelectorAll('.lg-dot'),
        DUR = 5500, cur = 0, timer = null;
    if (caps.length < 2) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) DUR = 8000;
    function go(i) {
        cur = (i + caps.length) % caps.length;
        slides.forEach(function (el, n) { el.classList.toggle('is-active', n === cur); });
        caps.forEach(function (el, n) { el.classList.toggle('is-active', n === cur); });
        dots.forEach(function (el, n) { el.classList.toggle('is-active', n === cur); });
        clearTimeout(timer);
        timer = setTimeout(function () { go(cur + 1); }, DUR);
    }
    dots.forEach(function (d, n) { d.addEventListener('click', function () { go(n); }); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) clearTimeout(timer); else go(cur); });
    go(0);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>