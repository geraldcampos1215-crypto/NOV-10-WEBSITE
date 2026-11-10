<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About Us';

// Real numbers for the stats band
$stats = [
  ['Open jobs', (int)val("SELECT COUNT(*) FROM jobs WHERE status='open'"), 'bi-briefcase'],
  ['Companies', (int)val('SELECT COUNT(*) FROM companies'), 'bi-buildings'],
  ['Job seekers', (int)val("SELECT COUNT(*) FROM users WHERE role='seeker'"), 'bi-people'],
];

$features = [
  ['bi-search', 'Smart search', 'Filter by category, location and job type to find the right fit fast.'],
  ['bi-file-earmark-person', 'Easy applications', 'Apply with the resume saved in your profile in just a few clicks.'],
  ['bi-graph-up', 'Track progress', 'See the live status of every application from your dashboard.'],
  ['bi-shield-check', 'Secure by design', 'Role-based access keeps your data protected.'],
];

$seekerPoints = [
  'Search and apply in minutes',
  'Save jobs you like for later',
  'Track every application in one place',
];
$teamPoints = [
  'Post and manage jobs',
  'Review candidates and applications',
  'Schedule and follow interviews',
];

$loggedIn = (bool) user();

include __DIR__ . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Same palette as the login page */
    .ab {
        --ink: #0f1f3d;
        --ink-soft: #566680;
        --line: #e1e7f0;
        --bg: #f4f7fb;
        --brand: #1f5fe0;
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --sky: #3b7af0;      /* login page background */
        --deep: #2563eb;     /* large circle */
        --deeper: #1c4fc4;   /* darker circle */
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--ink);
        position: relative; overflow: hidden; background: var(--sky);
    }
    .ab::before, .ab::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
    .ab::before {
        width: 1500px; height: 1500px; background: var(--deep);
        top: -1180px; left: 50%; transform: translateX(-30%);
    }
    .ab::after {
        width: 900px; height: 900px; background: var(--deeper); opacity: .55;
        bottom: -560px; left: -300px;
    }
    .ab > * { position: relative; z-index: 1; }
    .ab h1, .ab h2, .ab h3, .ab h4 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.015em;
    }

    /* ---------- HERO ---------- */
    .ab-hero { color: #fff; padding: 5.5rem 0 7.5rem; }
    .ab-hero .inner { max-width: 680px; }
    .ab-kicker {
        display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600; padding: .35rem .9rem;
        border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.55); margin-bottom: 1.25rem;
    }
    .ab-hero h1 { font-size: clamp(2.3rem, 5vw, 3.6rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.05; margin: 0 0 1.1rem; color: #fff; }
    .ab-hero p { font-size: 1.12rem; color: rgba(255,255,255,.9); line-height: 1.65; max-width: 56ch; margin: 0; }

    @keyframes ab-rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
    .ab-hero .inner > * { animation: ab-rise .7s cubic-bezier(.2, .7, .2, 1) both; }
    .ab-hero .inner > *:nth-child(2) { animation-delay: .1s; }
    .ab-hero .inner > *:nth-child(3) { animation-delay: .2s; }

    /* ---------- STATS (overlap the hero) ---------- */
    .ab-stats { margin-top: -3.2rem; position: relative; z-index: 2; }
    .ab-stats-card {
        background: #fff; border: 1px solid var(--line); border-radius: 20px; display: grid;
        grid-template-columns: repeat(3, 1fr); box-shadow: 0 18px 40px rgba(10, 26, 68, .22);
    }
    .ab-stat { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.75rem; }
    .ab-stat + .ab-stat { border-left: 1px solid var(--line); }
    .ab-stat .ico {
        width: 52px; height: 52px; border-radius: 14px; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-size: 1.4rem; flex: none;
    }
    .ab-stat .n { font-family: 'Bricolage Grotesque', 'Figtree', sans-serif; font-size: 1.9rem; font-weight: 800; line-height: 1; }
    .ab-stat .l { color: var(--ink-soft); font-size: .9rem; margin-top: .2rem; }

    /* ---------- MISSION ---------- */
    .ab-sec { padding-top: 4.5rem; }
    .ab-mission h2 { font-size: clamp(1.7rem, 3vw, 2.3rem); font-weight: 800; letter-spacing: -.03em; margin: 0 0 1rem; line-height: 1.12; color: #fff; }
    .ab-mission p { color: rgba(255,255,255,.9); font-size: 1.05rem; line-height: 1.75; max-width: 58ch; }
    .ab-mission p + p { margin-top: 1rem; }

    .ab-feature {
        height: 100%; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.4rem;
        box-shadow: 0 10px 28px rgba(10, 26, 68, .16);
        transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .ab-feature .ico {
        width: 48px; height: 48px; border-radius: 14px; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-size: 1.3rem; margin-bottom: 1rem;
        transition: background-color .25s ease, color .25s ease, transform .25s ease;
    }
    .ab-feature h3 { font-size: 1.05rem; font-weight: 700; margin: 0 0 .35rem; color: var(--ink); }
    .ab-feature p { margin: 0; color: var(--ink-soft); font-size: .92rem; line-height: 1.55; }
    .ab-feature:hover { transform: translateY(-4px); border-color: #9db9f2; box-shadow: 0 16px 34px rgba(10, 26, 68, .26); }
    .ab-feature:hover .ico { background: var(--brand); color: #fff; transform: rotate(-6deg) scale(1.06); }

    /* ---------- TWO AUDIENCES ---------- */
    .ab-sec-head { margin-bottom: 1.75rem; }
    .ab-sec-head h2 { font-size: 1.8rem; font-weight: 800; letter-spacing: -.03em; margin: 0 0 .3rem; color: #fff; }
    .ab-sec-head p { margin: 0; color: rgba(255,255,255,.88); }

    .ab-panel {
        height: 100%; border-radius: 22px; padding: 2rem; border: 1px solid var(--line); background: #fff;
        box-shadow: 0 18px 40px rgba(10, 26, 68, .18);
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .ab-panel:hover { transform: translateY(-4px); box-shadow: 0 22px 44px rgba(10, 26, 68, .28); }
    .ab-panel.dark { background: var(--brand-dark); border-color: rgba(255,255,255,.35); color: #fff; }
    .ab-panel .tag {
        display: inline-flex; align-items: center; gap: .45rem; font-size: .85rem; font-weight: 600; padding: .3rem .8rem;
        border-radius: 999px; background: var(--brand-tint); color: var(--brand-dark); margin-bottom: 1rem;
    }
    .ab-panel.dark .tag { background: rgba(255,255,255,.16); color: #fff; }
    .ab-panel h3 { font-size: 1.4rem; font-weight: 700; margin: 0 0 1.1rem; color: var(--ink); }
    .ab-panel.dark h3 { color: #fff; }
    .ab-panel ul { list-style: none; margin: 0 0 1.5rem; padding: 0; display: grid; gap: .8rem; }
    .ab-panel li { display: flex; gap: .7rem; align-items: center; color: #2a3a58; }
    .ab-panel.dark li { color: rgba(255,255,255,.92); }
    .ab-panel li i {
        width: 24px; height: 24px; border-radius: 50%; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-size: .8rem; flex: none;
    }
    .ab-panel.dark li i { background: rgba(255,255,255,.2); color: #fff; }
    .ab-link {
        display: inline-flex; align-items: center; gap: .45rem; font-weight: 600; color: var(--brand); text-decoration: none;
    }
    .ab-link i { transition: transform .2s ease; }
    .ab-link:hover { color: var(--brand-dark); }
    .ab-link:hover i { transform: translateX(5px); }
    .ab-panel.dark .ab-link { color: #fff; }
    .ab-panel.dark .ab-link:hover { color: #fff; opacity: .85; }

    /* ---------- CTA ---------- */
    .ab-cta {
        margin: 4.5rem 0 3rem; border-radius: 24px; padding: 3rem 2.5rem; color: #fff; display: flex; align-items: center;
        justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;
        background: linear-gradient(120deg, var(--brand-dark) 0%, var(--brand) 100%);
        border: 1px solid rgba(255,255,255,.35);
        box-shadow: 0 18px 40px rgba(10, 26, 68, .25);
    }
    .ab-cta h2 { font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 800; letter-spacing: -.03em; margin: 0 0 .4rem; color: #fff; }
    .ab-cta p { margin: 0; color: rgba(255,255,255,.9); max-width: 48ch; }
    .ab-cta .btns { display: flex; gap: .75rem; flex-wrap: wrap; }
    .ab-btn {
        display: inline-flex; align-items: center; gap: .5rem; padding: .85rem 1.5rem; border-radius: 12px; font-weight: 700;
        text-decoration: none; transition: background .2s, color .2s, transform .15s;
    }
    .ab-btn:active { transform: scale(.97); }
    .ab-btn.solid { background: #fff; color: var(--brand); }
    .ab-btn.solid:hover { background: var(--brand-tint); color: var(--brand-dark); }
    .ab-btn.line { border: 1px solid rgba(255,255,255,.7); color: #fff; border-radius: 999px; }
    .ab-btn.line:hover { background: rgba(255,255,255,.16); color: #fff; }
    .ab-btn:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 767.98px) {
        .ab::before { width: 900px; height: 900px; top: -700px; }
        .ab-hero { padding: 3.75rem 0 6.5rem; }
        .ab-stats-card { grid-template-columns: 1fr; }
        .ab-stat + .ab-stat { border-left: 0; border-top: 1px solid var(--line); }
        .ab-sec { padding-top: 3.25rem; }
        .ab-cta { padding: 2rem 1.5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ab *, .ab *::after { transition: none !important; animation: none !important; }
        .ab-feature:hover, .ab-panel:hover { transform: none; }
    }
</style>

<div class="ab">

    <!-- HERO -->
    <section class="ab-hero">
        <div class="container">
            <div class="inner">
                <span class="ab-kicker"><i class="bi bi-briefcase-fill"></i> About JobSeeker</span>
                <h1>Connecting people with the right work.</h1>
                <p>JobSeeker is an online job portal and recruitment management system that connects job seekers with employers, and gives recruitment teams the tools to manage hiring from posting to onboarding.</p>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <div class="container ab-stats">
        <div class="ab-stats-card">
            <?php foreach ($stats as [$l, $n, $icon]): ?>
                <div class="ab-stat">
                    <div class="ico"><i class="bi <?= $icon ?>"></i></div>
                    <div>
                        <div class="n" data-count="<?= $n ?>"><?= number_format($n) ?></div>
                        <div class="l"><?= e($l) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MISSION + FEATURES -->
    <section class="container ab-sec">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 ab-mission">
                <h2>Everyone deserves a clear path to the right career.</h2>
                <p>Job seekers can search and apply in minutes and track every application, while staff and administrators manage jobs, candidates and interviews in one place.</p>
                <p>No more scattered spreadsheets or lost emails. One platform keeps both sides informed from the first search to the final offer.</p>
            </div>

            <div class="col-lg-6">
                <div class="row g-3">
                    <?php foreach ($features as [$i, $t, $d]): ?>
                        <div class="col-sm-6">
                            <div class="ab-feature">
                                <div class="ico"><i class="bi <?= $i ?>"></i></div>
                                <h3><?= e($t) ?></h3>
                                <p><?= e($d) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- TWO AUDIENCES -->
    <section class="container ab-sec">
        <div class="ab-sec-head">
            <h2>Built for both sides of hiring.</h2>
            <p>One platform, two experiences.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="ab-panel">
                    <span class="tag"><i class="bi bi-person"></i> For job seekers</span>
                    <h3>Find work and follow it through</h3>
                    <ul>
                        <?php foreach ($seekerPoints as $pt): ?>
                            <li><i class="bi bi-check-lg"></i><span><?= e($pt) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="ab-link" href="<?= url('jobs.php') ?>">Browse jobs <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="ab-panel dark">
                    <span class="tag"><i class="bi bi-buildings"></i> For staff and administrators</span>
                    <h3>Run hiring from one place</h3>
                    <ul>
                        <?php foreach ($teamPoints as $pt): ?>
                            <li><i class="bi bi-check-lg"></i><span><?= e($pt) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="ab-link" href="<?= url('login.php') ?>">Log in to your workspace <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <div class="container">
        <section class="ab-cta">
            <div>
                <h2><?= $loggedIn ? 'Ready for your next step?' : 'Start your journey today.' ?></h2>
                <p><?= $loggedIn
                    ? 'New jobs are posted every day. Browse the latest openings and apply while they are fresh.'
                    : 'Create a free account to apply faster, save jobs, and follow every application.' ?></p>
            </div>
            <div class="btns">
                <?php if ($loggedIn): ?>
                    <a class="ab-btn solid" href="<?= url('jobs.php') ?>">Browse jobs <i class="bi bi-arrow-right"></i></a>
                <?php else: ?>
                    <a class="ab-btn solid" href="<?= url('register.php') ?>">Create account</a>
                    <a class="ab-btn line" href="<?= url('login.php') ?>">Log in</a>
                <?php endif; ?>
            </div>
        </section>
    </div>

</div>

<script>
(function () {
    // Count the stats up once when they scroll into view
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var nums = document.querySelectorAll('.ab-stat .n[data-count]');
    if (reduce || !('IntersectionObserver' in window)) return;

    function run(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (end === 0) return;
        var dur = 1100, start = null;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(end * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(tick);
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
        });
    }, { threshold: .6 });
    nums.forEach(function (n) { io.observe(n); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>