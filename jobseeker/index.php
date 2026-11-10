<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Home';
$stats = [
  ['Job Listings', (int)val("SELECT COUNT(*) FROM jobs WHERE status='open'")],
  ['Companies', (int)val('SELECT COUNT(*) FROM companies')],
  ['Registered Users', (int)val("SELECT COUNT(*) FROM users WHERE role='seeker'")],
];
$featured = rows(JOB_SELECT . " WHERE j.status='open' AND j.is_featured=1 ORDER BY j.created_at DESC LIMIT 6");
$cats = rows("SELECT cat.id, cat.name, COUNT(j.id) n FROM job_categories cat LEFT JOIN jobs j ON j.category_id=cat.id AND j.status='open' GROUP BY cat.id ORDER BY n DESC LIMIT 8");
$saved = saved_ids();

// ---- View helpers (display only) ----
$statIcons = ['bi-briefcase', 'bi-buildings', 'bi-people'];

function cat_icon(string $name): string {
    $map = [
        'customer' => 'bi-headset',
        'design'   => 'bi-palette',
        'educat'   => 'bi-mortarboard',
        'engineer' => 'bi-gear',
        'financ'   => 'bi-graph-up-arrow',
        'account'  => 'bi-calculator',
        'health'   => 'bi-heart-pulse',
        'it'       => 'bi-code-slash',
        'software' => 'bi-code-slash',
        'market'   => 'bi-megaphone',
        'sales'    => 'bi-bag-check',
        'admin'    => 'bi-clipboard-check',
    ];
    $n = strtolower($name);
    foreach ($map as $key => $icon) {
        if (strpos($n, $key) !== false) return $icon;
    }
    return 'bi-grid';
}

$loggedIn = (bool) user();

include __DIR__ . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .hm {
        --ink: #0f1f3d;
        --ink-soft: #566680;
        --line: #e1e7f0;
        --bg: #f4f7fb;
        --brand: #1f5fe0;
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --navy: #0b1b45;
        --gold: #ffc94d;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--ink);
    }
    .hm h1, .hm h2, .hm h3, .hm h4, .hm h5, .hm .hm-display {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.015em;
    }

    /* ---------- HERO ----------
       Put your photo at: assets/img/hero.jpg
       (If the file is missing, the navy colour shows instead.) */
    .hm-hero {
        position: relative;
        color: #fff;
        padding: 6.5rem 0 8rem;
        background-color: var(--navy);
        background-image:
            linear-gradient(95deg, rgba(8, 20, 56, .94) 0%, rgba(8, 20, 56, .82) 42%, rgba(8, 20, 56, .35) 100%),
            url('<?= e(url('assets/img/hero.jpg')) ?>');
        background-size: cover;
        background-position: center;
    }
    .hm-hero .inner { max-width: 660px; }
    .hm-kicker {
        display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600;
        padding: .35rem .9rem; border-radius: 999px; background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.2); margin-bottom: 1.25rem;
    }
    .hm-kicker i { color: var(--gold); }
    .hm-hero h1 {
        font-size: clamp(2.3rem, 5.4vw, 3.9rem); font-weight: 800; line-height: 1.05; margin: 0 0 1rem;
    }
    .hm-hero .lead { font-size: 1.15rem; color: #cbd8f5; max-width: 52ch; margin-bottom: 2rem; line-height: 1.55; }

    /* Search bar */
    .hm-search {
        background: #fff; border-radius: 18px; padding: .6rem; display: flex; gap: .5rem; align-items: stretch;
        box-shadow: 0 18px 40px rgba(4, 12, 36, .35);
    }
    .hm-field { flex: 1; display: flex; align-items: center; gap: .65rem; padding: 0 .9rem; min-width: 0; }
    .hm-field + .hm-field { border-left: 1px solid var(--line); }
    .hm-field i { color: var(--brand); font-size: 1.05rem; }
    .hm-field input {
        border: 0; outline: 0; width: 100%; padding: .85rem 0; font-size: 1rem; color: var(--ink); background: transparent;
    }
    .hm-field input::placeholder { color: #8a97ad; }
    .hm-field:focus-within { background: var(--bg); border-radius: 12px; }
    .hm-go {
        background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: 0 1.6rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: .5rem; white-space: nowrap; transition: background .15s;
    }
    .hm-go:hover { background: var(--brand-dark); }
    .hm-go:focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

    .hm-popular { margin-top: 1.1rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; font-size: .875rem; color: #b6c6ea; }
    .hm-popular a {
        color: #fff; text-decoration: none; padding: .25rem .75rem; border-radius: 999px;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); transition: background .15s;
    }
    .hm-popular a:hover { background: rgba(255,255,255,.24); }

    /* ---------- STATS (overlap the hero) ---------- */
    .hm-stats { margin-top: -3.2rem; position: relative; z-index: 2; }
    .hm-stats-card {
        background: #fff; border: 1px solid var(--line); border-radius: 20px; display: grid;
        grid-template-columns: repeat(3, 1fr); box-shadow: 0 14px 34px rgba(15, 31, 61, .12);
    }
    .hm-stat { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.75rem; }
    .hm-stat + .hm-stat { border-left: 1px solid var(--line); }
    .hm-stat .ico {
        width: 52px; height: 52px; border-radius: 14px; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-size: 1.4rem; flex: none;
    }
    .hm-stat .n { font-family: 'Bricolage Grotesque', 'Figtree', sans-serif; font-size: 1.9rem; font-weight: 800; line-height: 1; }
    .hm-stat .l { color: var(--ink-soft); font-size: .9rem; margin-top: .2rem; }

    /* ---------- SECTION HEADINGS ---------- */
    .hm-sec { padding-top: 4rem; }
    .hm-sec-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.5rem; }
    .hm-sec-head h2 { font-size: 1.7rem; font-weight: 700; margin: 0; }
    .hm-sec-head p { margin: .25rem 0 0; color: var(--ink-soft); }
    .hm-link { color: var(--brand); font-weight: 600; text-decoration: none; white-space: nowrap; }
    .hm-link:hover { color: var(--brand-dark); text-decoration: underline; }

    /* ---------- CATEGORIES ---------- */
    .hm-cats { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.25rem; }
    .hm-cats h3 { font-size: 1.1rem; font-weight: 700; margin: .1rem 0 .9rem; }
    .hm-cat {
        display: flex; align-items: center; gap: .8rem; padding: .65rem .7rem; margin: 0 -.7rem; border-radius: 12px;
        color: var(--ink); text-decoration: none; transition: background .15s;
    }
    .hm-cat:hover { background: var(--bg); color: var(--ink); }
    .hm-cat .ico {
        width: 38px; height: 38px; border-radius: 10px; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; flex: none;
    }
    .hm-cat .nm { flex: 1; font-weight: 500; min-width: 0; }
    .hm-cat .ct { font-size: .8rem; font-weight: 600; color: var(--brand); background: var(--brand-tint); padding: .15rem .6rem; border-radius: 999px; }

    .hm-empty {
        text-align: center; padding: 2.5rem 1rem; background: #fff; border: 1px dashed #c3d0e4; border-radius: 18px; color: var(--ink-soft);
    }
    .hm-empty i { font-size: 2rem; color: var(--brand); display: block; margin-bottom: .4rem; }

    /* ---------- HOW IT WORKS (a real sequence, so numbered) ---------- */
    .hm-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
    .hm-step { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.6rem; }
    .hm-step .no {
        width: 36px; height: 36px; border-radius: 50%; background: var(--navy); color: #fff; font-weight: 700;
        display: grid; place-items: center; margin-bottom: 1rem; font-family: 'Bricolage Grotesque', sans-serif;
    }
    .hm-step h3 { font-size: 1.1rem; font-weight: 700; margin: 0 0 .4rem; }
    .hm-step p { margin: 0; color: var(--ink-soft); line-height: 1.6; }

    /* ---------- CTA ---------- */
    .hm-cta {
        margin: 4rem 0 3rem; border-radius: 24px; padding: 3rem 2.5rem; color: #fff; display: flex; align-items: center;
        justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;
        background-color: var(--navy);
        background-image:
            linear-gradient(95deg, rgba(8, 20, 56, .96) 0%, rgba(8, 20, 56, .86) 100%),
            url('<?= e(url('assets/img/hero.jpg')) ?>');
        background-size: cover; background-position: center;
    }
    .hm-cta h2 { font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 700; margin: 0 0 .4rem; }
    .hm-cta p { margin: 0; color: #c3d2f3; max-width: 48ch; }
    .hm-cta .btns { display: flex; gap: .75rem; flex-wrap: wrap; }
    .hm-btn {
        display: inline-flex; align-items: center; gap: .5rem; padding: .85rem 1.5rem; border-radius: 12px; font-weight: 600;
        text-decoration: none; transition: background .15s, color .15s;
    }
    .hm-btn.solid { background: var(--gold); color: #3d2d00; }
    .hm-btn.solid:hover { background: #ffd777; color: #3d2d00; }
    .hm-btn.line { border: 1px solid rgba(255,255,255,.4); color: #fff; }
    .hm-btn.line:hover { background: rgba(255,255,255,.14); color: #fff; }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 767.98px) {
        .hm-hero { padding: 4rem 0 6.5rem; background-position: 65% center; }
        .hm-search { flex-direction: column; padding: .5rem; }
        .hm-field + .hm-field { border-left: 0; border-top: 1px solid var(--line); }
        .hm-go { padding: .9rem; justify-content: center; }
        .hm-stats-card { grid-template-columns: 1fr; }
        .hm-stat + .hm-stat { border-left: 0; border-top: 1px solid var(--line); }
        .hm-steps { grid-template-columns: 1fr; }
        .hm-cta { padding: 2rem 1.5rem; }
        .hm-sec-head { flex-direction: column; align-items: flex-start; }
    }
    @media (prefers-reduced-motion: reduce) { .hm * { transition: none !important; } }
</style>

<div class="hm">

    <!-- HERO -->
    <section class="hm-hero">
        <div class="container">
            <div class="inner">
                <span class="hm-kicker"><i class="bi bi-stars"></i> <?= number_format($stats[0][1]) ?> jobs open right now</span>
                <h1>Your Next Opportunity Awaits</h1>
                <p class="lead">Find the right job, build your career, and achieve your dreams with us.</p>

                <form action="<?= url('jobs.php') ?>" class="hm-search" role="search">
                    <label class="hm-field">
                        <i class="bi bi-search"></i>
                        <input name="q" placeholder="Job title, skills, or company" aria-label="Job title, skills, or company">
                    </label>
                    <label class="hm-field">
                        <i class="bi bi-geo-alt"></i>
                        <input name="location" placeholder="Location" aria-label="Location">
                    </label>
                    <button class="hm-go" type="submit">Search jobs</button>
                </form>

                <?php if ($cats): ?>
                    <div class="hm-popular">
                        <span>Popular:</span>
                        <?php foreach (array_slice($cats, 0, 4) as $c): ?>
                            <a href="<?= url('jobs.php?category=' . $c['id']) ?>"><?= e($c['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <div class="container hm-stats">
        <div class="hm-stats-card">
            <?php foreach ($stats as $i => [$l, $n]): ?>
                <div class="hm-stat">
                    <div class="ico"><i class="bi <?= $statIcons[$i] ?? 'bi-bar-chart' ?>"></i></div>
                    <div>
                        <div class="n"><?= number_format($n) ?></div>
                        <div class="l"><?= e($l) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FEATURED + CATEGORIES -->
    <section class="container hm-sec">
        <div class="hm-sec-head">
            <div>
                <h2>Featured jobs</h2>
                <p>Hand-picked openings from employers who are hiring now.</p>
            </div>
            <a class="hm-link" href="<?= url('jobs.php') ?>">View all jobs <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <?php foreach ($featured as $j) echo job_card($j, $saved); ?>
                <?php if (!$featured): ?>
                    <div class="hm-empty">
                        <i class="bi bi-briefcase"></i>
                        No featured jobs yet. <a class="hm-link" href="<?= url('jobs.php') ?>">Browse all open jobs</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="hm-cats">
                    <h3>Browse by category</h3>
                    <?php foreach ($cats as $c): ?>
                        <a class="hm-cat" href="<?= url('jobs.php?category=' . $c['id']) ?>">
                            <span class="ico"><i class="bi <?= cat_icon($c['name']) ?>"></i></span>
                            <span class="nm"><?= e($c['name']) ?></span>
                            <span class="ct"><?= (int)$c['n'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="container hm-sec">
        <div class="hm-sec-head">
            <div>
                <h2>How it works</h2>
                <p>From sign-up to your first interview in three steps.</p>
            </div>
        </div>
        <div class="hm-steps">
            <div class="hm-step">
                <div class="no">1</div>
                <h3>Build your profile</h3>
                <p>Add your skills, a short bio, and your resume so employers can see what you offer.</p>
            </div>
            <div class="hm-step">
                <div class="no">2</div>
                <h3>Find a job you want</h3>
                <p>Search by title, location, or category, then save the ones you like for later.</p>
            </div>
            <div class="hm-step">
                <div class="no">3</div>
                <h3>Apply and track</h3>
                <p>Send your application in one click and follow its status from your dashboard.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <div class="container">
        <section class="hm-cta">
            <div>
                <h2><?= $loggedIn ? 'Keep the momentum going' : 'Create your free account today' ?></h2>
                <p><?= $loggedIn
                    ? 'New jobs are posted every day. Browse the latest openings and apply while they are fresh.'
                    : 'Save jobs, apply faster, and get updates when employers respond.' ?></p>
            </div>
            <div class="btns">
                <?php if ($loggedIn): ?>
                    <a class="hm-btn solid" href="<?= url('jobs.php') ?>">Browse jobs <i class="bi bi-arrow-right"></i></a>
                <?php else: ?>
                    <a class="hm-btn solid" href="<?= url('register.php') ?>">Create account</a>
                    <a class="hm-btn line" href="<?= url('login.php') ?>">Log in</a>
                <?php endif; ?>
            </div>
        </section>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>