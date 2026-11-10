<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Dashboard';
$c = fn($sql) => (int)val($sql);

// Main numbers (same four as before) + two extra that are useful at a glance
$stats = [
    ['Total Users',     $c("SELECT COUNT(*) FROM users WHERE role='seeker'"),  'people',            'Job seekers registered'],
    ['Job Postings',    $c('SELECT COUNT(*) FROM jobs'),                       'briefcase',         'All jobs ever posted'],
    ['Applications',    $c('SELECT COUNT(*) FROM applications'),               'file-earmark-text', 'Submitted by seekers'],
    ['Staff Accounts',  $c("SELECT COUNT(*) FROM users WHERE role='staff'"),   'person-badge',      'Recruitment team members'],
    ['Open Jobs',       $c("SELECT COUNT(*) FROM jobs WHERE status='open'"),   'door-open',         'Accepting applications now'],
    ['Companies',       $c('SELECT COUNT(*) FROM companies'),                  'buildings',         'Hiring on the platform'],
];

$growth = rows("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, DATE_FORMAT(created_at,'%b') m, COUNT(*) n FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym, m ORDER BY ym");
$base = $c("SELECT COUNT(*) FROM users WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 11 MONTH)"); $cum = [];
foreach ($growth as $g) { $base += $g['n']; $cum[] = $base; }

$byCat = rows("SELECT c.name, COUNT(j.id) n FROM job_categories c JOIN jobs j ON j.category_id=c.id WHERE j.status='open' GROUP BY c.id, c.name ORDER BY n DESC LIMIT 6");

$logs = rows('SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 6');

// One script block for both charts, styled with the same blue palette and font
$js  = 'Chart.defaults.font.family="Figtree, system-ui, sans-serif";Chart.defaults.color="#566680";';
$js .= 'new Chart(document.getElementById("ug"),{type:"line",data:{labels:' . json_encode(array_column($growth, 'm')) . ',datasets:[{label:"Total users",data:' . json_encode($cum) . ',borderColor:"#1f5fe0",backgroundColor:"rgba(31,95,224,.12)",fill:true,tension:.35,borderWidth:3,pointRadius:3,pointBackgroundColor:"#1f5fe0"}]},options:{plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:"#e1e7f0"},ticks:{precision:0}}}}});';
if ($byCat) {
    $js .= 'new Chart(document.getElementById("cg"),{type:"doughnut",data:{labels:' . json_encode(array_column($byCat, 'name')) . ',datasets:[{data:' . json_encode(array_map('intval', array_column($byCat, 'n'))) . ',backgroundColor:["#1f5fe0","#f59e0b","#10b981","#8b5cf6","#ef4444","#06b6d4"],hoverOffset:8,borderWidth:3,borderColor:"#fff"}]},options:{cutout:"62%",plugins:{legend:{position:"bottom",labels:{usePointStyle:true,boxWidth:8}}}}});';
}
chart_script($js);

include ROOT_PATH . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .db {
        --ink: #0f1f3d;
        --ink-soft: #566680;
        --line: #e1e7f0;
        --bg: #f4f7fb;
        --brand: #1f5fe0;
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --sky: #3b7af0;
        --deep: #2563eb;
        --deeper: #1c4fc4;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--ink);
    }
    .db h1, .db h2, .db h3, .db h4, .db h5, .db h6 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.01em;
    }

    /* ---------- Welcome banner (same look as the login page) ---------- */
    .db-hero {
        position: relative; overflow: hidden; border-radius: 22px; padding: 2rem 2.25rem; margin-bottom: 1.5rem;
        background: var(--sky); color: #fff; display: flex; align-items: center; justify-content: space-between;
        gap: 1.25rem; flex-wrap: wrap;
    }
    .db-hero::before, .db-hero::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
    .db-hero::before { width: 520px; height: 520px; background: var(--deep); top: -300px; right: -80px; }
    .db-hero::after  { width: 360px; height: 360px; background: var(--deeper); opacity: .55; bottom: -240px; left: -90px; }
    .db-hero > * { position: relative; z-index: 1; }
    .db-hero h4 { font-size: clamp(1.7rem, 3.4vw, 2.4rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.08; margin: 0 0 .35rem; color: #fff; }
    .db-hero p { margin: 0; color: rgba(255,255,255,.9); }
    .db-hero .date { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 600; opacity: .9; margin-bottom: .6rem; }
    .db-pill {
        display: inline-flex; align-items: center; gap: .5rem; padding: .7rem 1.3rem; border-radius: 999px; font-weight: 600;
        color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,.7); transition: background .2s, transform .15s;
    }
    .db-pill:hover { background: rgba(255,255,255,.16); color: #fff; }
    .db-pill:active { transform: scale(.97); }
    .db-pill i { transition: transform .2s ease; }
    .db-pill:hover i { transform: translateX(4px); }

    /* ---------- Stat cards ---------- */
    .db-stat {
        height: 100%; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.15rem 1.25rem;
        display: flex; align-items: center; gap: 1rem;
        transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }
    .db-stat:hover { transform: translateY(-3px); border-color: #9db9f2; box-shadow: 0 14px 28px rgba(31, 95, 224, .14); }
    .db-stat .ico {
        width: 52px; height: 52px; border-radius: 14px; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-size: 1.4rem; flex: none; transition: background .22s, color .22s;
    }
    .db-stat:hover .ico { background: var(--brand); color: #fff; }
    .db-stat .n { font-family: 'Bricolage Grotesque', 'Figtree', sans-serif; font-size: 2rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
    .db-stat .l { font-weight: 600; font-size: .92rem; margin-top: .3rem; }
    .db-stat .h { color: var(--ink-soft); font-size: .78rem; }

    /* ---------- Panels ---------- */
    .db-card { height: 100%; background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
    .db-card-h {
        display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: 1.1rem 1.25rem .6rem;
    }
    .db-card-h h6 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .db-card-h a { font-size: .85rem; font-weight: 600; color: var(--brand); text-decoration: none; }
    .db-card-h a:hover { color: var(--brand-dark); text-decoration: underline; }
    .db-card-b { padding: .25rem 1.25rem 1.25rem; }

    .db-empty { text-align: center; color: var(--ink-soft); padding: 2rem 0; font-size: .92rem; }

    /* ---------- Activity list ---------- */
    .db-act { display: flex; gap: .85rem; padding: .85rem 1.25rem; border-top: 1px solid var(--line); }
    .db-act:first-of-type { border-top: 0; }
    .db-act .dot {
        width: 36px; height: 36px; border-radius: 50%; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; flex: none; font-size: .95rem;
    }
    .db-act .t { font-weight: 600; font-size: .92rem; }
    .db-act .d { color: var(--ink-soft); font-size: .82rem; margin-top: .1rem; word-break: break-word; }

    @media (max-width: 767.98px) {
        .db-hero { padding: 1.5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .db *, .db *::after { transition: none !important; }
        .db-stat:hover { transform: none; }
    }
</style>

<div class="db">

    <!-- Welcome -->
    <div class="db-hero">
        <div>
            <div class="date"><i class="bi bi-calendar3"></i> <?= e(date('l, F j')) ?></div>
            <h4>Welcome back, <?= e(explode(' ', $u['full_name'])[0]) ?>.</h4>
            <p>Here's what's happening in your system.</p>
        </div>
        <a class="db-pill" href="logs.php">View activity logs <i class="bi bi-arrow-right"></i></a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php foreach ($stats as [$l, $n, $i, $hint]): ?>
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="db-stat">
                    <div class="ico"><i class="bi bi-<?= $i ?>"></i></div>
                    <div>
                        <div class="n"><?= number_format($n) ?></div>
                        <div class="l"><?= e($l) ?></div>
                        <div class="h"><?= e($hint) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="db-card">
                <div class="db-card-h"><h6>User Growth</h6><span class="small text-muted">Last 12 months</span></div>
                <div class="db-card-b"><canvas id="ug" height="150"></canvas></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="db-card">
                <div class="db-card-h"><h6>Open Jobs by Category</h6></div>
                <div class="db-card-b">
                    <?php if ($byCat): ?>
                        <canvas id="cg" height="190"></canvas>
                    <?php else: ?>
                        <div class="db-empty"><i class="bi bi-pie-chart fs-3 d-block mb-2"></i>No open jobs yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="db-card">
        <div class="db-card-h"><h6>Recent Activities</h6><a href="logs.php">View all</a></div>
        <?php if ($logs): foreach ($logs as $l): ?>
            <div class="db-act">
                <div class="dot"><i class="bi bi-lightning-charge"></i></div>
                <div>
                    <div class="t"><?= e($l['action']) ?></div>
                    <div class="d"><?= e($l['details']) ?> · <?= e($l['full_name'] ?? 'Guest') ?> · <?= time_ago($l['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="db-empty">No activity yet.</div>
        <?php endif; ?>
    </div>

</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>