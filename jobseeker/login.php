<?php
require_once __DIR__ . '/includes/auth.php';
if (user()) redirect(home_for(user()['role']));
$pageTitle = 'Login'; $layout = 'auth'; $err = [];
$id = trim($_POST['identifier'] ?? ($_COOKIE['remember_id'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($id === '') $err['identifier'] = 'Enter your email or username.';
    if ($pw === '') $err['password'] = 'Enter your password.';
    if (!$err) {
        $u = row('SELECT * FROM users WHERE email=? OR username=?', [$id, $id]);
        if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $err['form'] = 'Too many failed attempts. Try again in ' . ceil((strtotime($u['locked_until']) - time()) / 60) . ' minute(s).';
        } elseif (!$u || !password_verify($pw, $u['password'])) {
            if ($u) {
                $n = $u['failed_attempts'] + 1;
                q('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?', [$n >= MAX_LOGIN_ATTEMPTS ? 0 : $n, $n >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null, $u['id']]);
                log_activity('Failed login', $id, $u['id']);
            }
            $err['form'] = 'Invalid email/username or password.';
        } elseif ($u['status'] !== 'active') {
            $err['form'] = 'Your account has been deactivated. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id']; $_SESSION['role'] = $u['role'];
            q('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?', [$u['id']]);
            log_activity('User login', $u['email'], $u['id']);
            if (!empty($_POST['remember'])) setcookie('remember_id', $id, ['expires' => time() + 2592000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            else setcookie('remember_id', '', time() - 3600, '/');
            redirect(home_for($u['role']));
        }
    }
}

// ---- Slideshow: every image in assets/img/login/ becomes a background slide ----
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
foreach ($slideFiles as $f) $pics[] = url('assets/img/login/' . rawurlencode(basename($f)));
$count = max(1, count($pics));          // with no pictures there is still one caption on plain blue

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
        --sky: #3b78f2;            /* lighter shade of the same blue (page base) */
        --deep: #1a52c9;           /* darker shades for the circles */
        --deeper: #1546a8;
        --bad: #c2362b;
        --bad-tint: #fdeceb;
        --slide-ms: 5500ms;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #fff;
        position: relative; overflow: hidden; isolation: isolate;
        min-height: 100vh; display: flex; flex-direction: column;
        background: var(--sky);
    }
    .lg h1, .lg h2, .lg h3 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.02em;
    }

    /* ---------- Background: photos (optional) + blue wash + circles ---------- */
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

    /* ---------- Top bar ---------- */
    .lg-nav {
        width: 100%; max-width: 1200px; margin: 0 auto; padding: 1.6rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
    }
    .lg-brand { color: #fff; text-decoration: none; font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.7rem; letter-spacing: -.01em; display: inline-flex; align-items: center; gap: .55rem; }
    .lg-brand b { font-weight: 800; }
    .lg-brand span { font-weight: 500; opacity: .9; }
    .lg-brand:hover { color: #fff; }
    .lg-links { display: flex; align-items: center; gap: 2rem; }
    .lg-links a {
        position: relative; color: #fff; text-decoration: none; font-weight: 600; font-size: .98rem; padding: .3rem 0;
    }
    .lg-links a::after {
        content: ""; position: absolute; left: 0; right: 0; bottom: -2px; height: 2px; background: #fff; border-radius: 2px;
        transform: scaleX(0); transform-origin: left; transition: transform .25s ease;
    }
    .lg-links a:hover::after, .lg-links a:focus-visible::after { transform: scaleX(1); }
    .lg-links a:hover { color: #fff; }
    .lg-links .signup {
        padding: .5rem 1.15rem; border: 1.5px solid rgba(255,255,255,.7); border-radius: 999px; transition: background-color .2s ease, color .2s ease;
    }
    .lg-links .signup::after { display: none; }
    .lg-links .signup:hover { background: #fff; color: var(--brand); }

    /* ---------- Hero: copy left, card right ---------- */
    .lg-hero {
        flex: 1; width: 100%; max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem 3rem; display: grid;
        grid-template-columns: 1.15fr .85fr; gap: 3rem; align-items: center;
    }
    .lg-copy h1 { font-size: clamp(3rem, 7vw, 5.6rem); font-weight: 800; line-height: .98; margin: 0 0 .9rem; }
    .lg-caps-stack { display: grid; margin-bottom: 1.1rem; }
    .lg-cap {
        grid-area: 1 / 1; font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(1.25rem, 2.2vw, 1.7rem); font-weight: 700; line-height: 1.25;
        opacity: 0; transform: translateY(12px); transition: opacity .7s ease, transform .7s ease;
    }
    .lg-cap.is-active { opacity: 1; transform: none; transition-delay: .2s; }
    .lg-copy .para { max-width: 52ch; color: #e3ecff; font-size: 1.05rem; line-height: 1.65; margin: 0 0 1.75rem; }

    .lg-learn {
        display: inline-flex; align-items: center; gap: .55rem; color: #fff; text-decoration: none; font-weight: 600; font-size: .95rem;
        padding: .7rem 1.7rem; border: 2px solid #fff; border-radius: 999px;
        transition: background-color .2s ease, color .2s ease, transform .15s ease;
    }
    .lg-learn i { transition: transform .2s ease; }
    .lg-learn:hover { background: #fff; color: var(--brand); }
    .lg-learn:hover i { transform: translateX(4px); }
    .lg-learn:active { transform: scale(.97); }

    .lg-dots { display: flex; gap: .55rem; margin-top: 2rem; }
    .lg-dot {
        width: 12px; height: 12px; padding: 0; border-radius: 50%; border: 1.5px solid #fff; background: transparent; cursor: pointer;
        transition: background-color .25s ease, transform .25s ease;
    }
    .lg-dot:hover { transform: scale(1.2); }
    .lg-dot.is-active { background: #fff; }
    .lg-dot:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    .lg.single .lg-dots { display: none; }

    /* ---------- Login card ---------- */
    .lg-cardwrap { display: flex; justify-content: center; }
    .lg-card {
        width: 100%; max-width: 430px; background: #fff; color: var(--ink); border-radius: 30px; padding: 2.3rem 2.1rem 2rem;
        box-shadow: 0 0 0 7px rgba(255, 255, 255, .22), 0 28px 60px rgba(8, 30, 100, .35);
    }
    .lg-card h2 { font-size: 1.65rem; font-weight: 800; margin: 0 0 .25rem; text-align: center; }
    .lg-card .sub { text-align: center; color: var(--ink-soft); margin: 0 0 1.6rem; font-size: .95rem; }
    .lg-logo { display: block; width: fit-content; margin: 0 auto 1rem; color: var(--brand); font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700; font-size: 1rem; text-decoration: none; text-align: center; }
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

    .lg-alert {
        display: flex; gap: .6rem; align-items: flex-start; background: var(--bad-tint); color: #8c2a21;
        border: 1px solid #f3c6c1; border-radius: 12px; padding: .8rem .95rem; font-size: .92rem; margin-bottom: 1.1rem;
    }
    .lg-alert i { margin-top: .12rem; }
    .lg-card .alert { border-radius: 12px; }

    /* Floating-label fields */
    .lg-float { position: relative; margin-bottom: 1rem; }
    .lg-float input[type=text], .lg-float input[type=password] {
        width: 100%; height: 60px; padding: 1.5rem 3rem .45rem 2.9rem; font-size: 1rem; color: var(--ink);
        background: #fff; border: 1.5px solid #c9d6ee; border-radius: 14px; outline: 0;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .lg-float input:hover { border-color: #9db9f2; }
    .lg-float input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(31, 95, 224, .15); }
    .lg-float label {
        position: absolute; left: 2.9rem; top: 50%; transform: translateY(-50%); transform-origin: left top;
        color: #6b7a93; pointer-events: none; font-size: 1rem; max-width: calc(100% - 4rem); white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis; transition: top .2s ease, transform .2s ease, color .2s ease;
    }
    .lg-float input:focus + label,
    .lg-float input:not(:placeholder-shown) + label,
    .lg-float input:-webkit-autofill + label { top: .6rem; transform: translateY(0) scale(.76); color: var(--brand); }
    .lg-float input:not(:focus):not(:placeholder-shown) + label { color: var(--ink-soft); }
    .lg-float .ico {
        position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #8a97ad; font-size: 1.1rem;
        pointer-events: none; transition: color .2s ease;
    }
    .lg-float input:focus ~ .ico { color: var(--brand); }
    .lg-eye {
        position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border: 0;
        background: transparent; color: #8a97ad; border-radius: 10px; display: grid; place-items: center; font-size: 1.1rem;
        transition: color .2s ease, background-color .2s ease;
    }
    .lg-eye:hover { color: var(--brand); background: var(--brand-tint); }
    .lg-eye:focus-visible { outline: 3px solid rgba(31, 95, 224, .4); }

    .lg-float.has-error input { border-color: var(--bad); }
    .lg-float.has-error input:focus { box-shadow: 0 0 0 4px rgba(194, 54, 43, .15); }
    .lg-float.has-error label, .lg-float.has-error .ico { color: var(--bad) !important; }
    .lg-msg { display: none; margin: .4rem .2rem 0; font-size: .84rem; color: var(--bad); }
    .lg-msg-wrap { margin-bottom: 1rem; }
    .lg-msg-wrap .lg-float { margin-bottom: 0; }
    .lg-float.has-error + .lg-msg { display: block; }
    .lg-capslock { display: none; margin: .4rem .2rem 0; font-size: .82rem; color: var(--ink-soft); align-items: center; gap: .35rem; }
    .lg-capslock i { color: var(--brand); }
    .lg-capslock.on { display: flex; }

    .lg-row { display: flex; justify-content: space-between; align-items: center; margin: .25rem 0 1.4rem; gap: 1rem; }
    .lg-check { display: inline-flex; align-items: center; gap: .6rem; cursor: pointer; font-size: .9rem; color: var(--ink-soft); user-select: none; }
    .lg-check input { position: absolute; opacity: 0; pointer-events: none; }
    .lg-check .box {
        width: 20px; height: 20px; border-radius: 6px; border: 1.5px solid #b4c1d6; display: grid; place-items: center; color: transparent;
        font-size: .75rem; transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .15s ease;
    }
    .lg-check:hover .box { border-color: var(--brand); }
    .lg-check input:checked + .box { background: var(--brand); border-color: var(--brand); color: #fff; }
    .lg-check input:active + .box { transform: scale(.9); }
    .lg-check input:focus-visible + .box { outline: 3px solid rgba(31, 95, 224, .4); outline-offset: 2px; }
    .lg-link { color: var(--brand); font-weight: 600; font-size: .9rem; text-decoration: none; }
    .lg-link:hover { color: var(--brand-dark); text-decoration: underline; }

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
    .lg-btn .spin {
        display: none; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%;
        animation: lg-spin .7s linear infinite;
    }
    .lg-btn.loading { pointer-events: none; background: var(--brand-dark); }
    .lg-btn.loading .spin { display: inline-block; }
    .lg-btn.loading .arrow { display: none; }
    @keyframes lg-spin { to { transform: rotate(360deg); } }
    .lg-signup { text-align: center; margin: 1.4rem 0 0; color: var(--ink-soft); font-size: .93rem; }

    /* ---------- Responsive ---------- */
    @media (max-width: 991.98px) {
        .lg-hero { grid-template-columns: 1fr; gap: 2rem; padding-bottom: 2.5rem; }
        .lg-copy { text-align: center; }
        .lg-copy .para { margin-left: auto; margin-right: auto; }
        .lg-dots { justify-content: center; }
        .lg-circle.c1 { left: -10%; } .lg-circle.c2 { left: 5%; } .lg-circle.c3 { left: 20%; }
    }
    @media (max-width: 767.98px) {
        .lg-links a:not(.signup) { display: none; }
        .lg-nav { padding: 1.2rem 1.1rem; }
        .lg-brand { font-size: 1.4rem; }
        .lg-copy .para, .lg-learn { display: none; }
        .lg-copy h1 { font-size: 2.8rem; }
        .lg-dots { margin-top: 1rem; }
        .lg-hero { padding: .5rem 1rem 2rem; }
        .lg-card { padding: 2rem 1.35rem 1.6rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .lg *, .lg *::after { transition: none !important; animation: none !important; }
    }
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
            <a href="<?= url('register.php') ?>" class="signup">Sign up</a>
        </nav>
    </header>

    <!-- Hero -->
    <main class="lg-hero">

        <section class="lg-copy">
            <h1>Welcome back.</h1>

            <div class="lg-caps-stack" aria-live="polite">
                <?php for ($i = 0; $i < $count; $i++): ?>
                    <div class="lg-cap <?= $i === 0 ? 'is-active' : '' ?>"><?= e($captions[$i % count($captions)]) ?></div>
                <?php endfor; ?>
            </div>

            <p class="para">Log in to continue your job search, manage recruitment, or administer the platform.</p>

            <a href="<?= url('jobs.php') ?>" class="lg-learn">Browse jobs <i class="bi bi-arrow-right"></i></a>

            <div class="lg-dots" role="tablist">
                <?php for ($i = 0; $i < $count; $i++): ?>
                    <button type="button" class="lg-dot <?= $i === 0 ? 'is-active' : '' ?>" role="tab" aria-label="Show slide <?= $i + 1 ?>"></button>
                <?php endfor; ?>
            </div>
        </section>

        <section class="lg-cardwrap">
            <div class="lg-card <?= !empty($err['form']) ? 'shake' : '' ?>">

                <a href="<?= url('index.php') ?>" class="lg-logo"><i class="bi bi-briefcase-fill"></i>JobSeeker</a>
                <h2>Log in</h2>
                <p class="sub">Enter your details to access your account.</p>

                <?php show_flash(); ?>
                <?php if (!empty($err['form'])): ?>
                    <div class="lg-alert" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div><?= e($err['form']) ?></div></div>
                <?php endif; ?>

                <form method="post" id="loginForm" novalidate>
                    <?= csrf_field() ?>

                    <div class="lg-msg-wrap">
                        <div class="lg-float <?= isset($err['identifier']) ? 'has-error' : '' ?>">
                            <input type="text" id="identifier" name="identifier" placeholder=" " value="<?= e($id) ?>"
                                   autocomplete="username" autofocus>
                            <label for="identifier">Email or username</label>
                            <i class="bi bi-person ico"></i>
                        </div>
                        <div class="lg-msg"><?= e($err['identifier'] ?? '') ?></div>
                    </div>

                    <div class="lg-msg-wrap">
                        <div class="lg-float <?= isset($err['password']) ? 'has-error' : '' ?>">
                            <input type="password" id="pw" name="password" placeholder=" " autocomplete="current-password">
                            <label for="pw">Password</label>
                            <i class="bi bi-lock ico"></i>
                            <button type="button" class="lg-eye" data-lg-eye aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="lg-msg"><?= e($err['password'] ?? '') ?></div>
                        <div class="lg-capslock" id="caps"><i class="bi bi-exclamation-triangle-fill"></i> Caps Lock is on</div>
                    </div>

                    <div class="lg-row">
                        <label class="lg-check" for="rm">
                            <input type="checkbox" name="remember" id="rm" <?= isset($_COOKIE['remember_id']) ? 'checked' : '' ?>>
                            <span class="box"><i class="bi bi-check-lg"></i></span>
                            Remember me
                        </label>
                        <a class="lg-link" href="<?= url('forgot-password.php') ?>">Forgot password?</a>
                    </div>

                    <button class="lg-btn" type="submit" id="loginBtn">
                        <span class="spin" aria-hidden="true"></span>
                        <span class="label">LOGIN</span>
                        <i class="bi bi-arrow-right arrow"></i>
                    </button>
                </form>

                <p class="lg-signup">Don’t have an account? <a class="lg-link" href="<?= url('register.php') ?>">Sign up</a></p>
            </div>
        </section>

    </main>
</div>

<script>
(function () {
    /* ---------- Form behaviour ---------- */
    var form = document.getElementById('loginForm'),
        btn = document.getElementById('loginBtn'),
        pw = document.getElementById('pw'),
        capsEl = document.getElementById('caps');

    document.querySelector('[data-lg-eye]').addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        pw.focus();
    });

    function checkCaps(e) {
        if (e.getModifierState) capsEl.classList.toggle('on', e.getModifierState('CapsLock'));
    }
    pw.addEventListener('keydown', checkCaps);
    pw.addEventListener('keyup', checkCaps);
    pw.addEventListener('blur', function () { capsEl.classList.remove('on'); });

    form.querySelectorAll('.lg-float input').forEach(function (inp) {
        inp.addEventListener('input', function () { inp.parentElement.classList.remove('has-error'); });
    });

    form.addEventListener('submit', function () {
        btn.classList.add('loading');
        btn.querySelector('.label').textContent = 'LOGGING IN…';
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            btn.classList.remove('loading');
            btn.querySelector('.label').textContent = 'LOGIN';
        }
    });

    /* ---------- Slideshow (background pictures + caption + dots) ---------- */
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
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) clearTimeout(timer); else go(cur);
    });

    go(0);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>