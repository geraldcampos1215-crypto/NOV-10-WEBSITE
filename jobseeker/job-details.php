<?php
require_once __DIR__ . '/includes/auth.php';
$id = (int)($_GET['id'] ?? 0);
$job = row(JOB_SELECT . ' WHERE j.id=?', [$id]);
$u = user();
$canManage = $u && in_array($u['role'], ['staff', 'admin']);
if (!$job || ($job['status'] === 'draft' && !$canManage)) { http_response_code(404); flash('danger', 'Job not found.'); redirect('jobs.php'); }
$pageTitle = $job['title'];
$isOpen = $job['status'] === 'open' && (!$job['deadline'] || $job['deadline'] >= date('Y-m-d'));
$app = $u && $u['role'] === 'seeker' ? row('SELECT * FROM applications WHERE job_id=? AND user_id=?', [$id, $u['id']]) : null;
$profile = $u && $u['role'] === 'seeker' ? row('SELECT * FROM user_profiles WHERE user_id=?', [$u['id']]) : null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    if (!$u || $u['role'] !== 'seeker') redirect('login.php');
    if (!$isOpen) $err = 'This job is no longer accepting applications.';
    elseif ($app) $err = 'You already applied for this job.';
    else {
        [$resume, $uerr] = upload_file('resume', 'resumes', ['pdf', 'docx'], 5);
        $resume = $resume ?: ($profile['resume'] ?? null);
        if ($uerr) $err = $uerr;
        elseif (!$resume) $err = 'Please upload your resume (PDF or DOCX) or add one to your profile.';
        else {
            q('INSERT INTO applications (job_id, user_id, cover_letter, resume) VALUES (?,?,?,?)', [$id, $u['id'], trim($_POST['cover_letter'] ?? ''), $resume]);
            notify($u['id'], 'Application submitted', 'You applied for ' . $job['title'] . ' at ' . $job['company_name'] . '.', true);
            foreach (rows("SELECT u.id FROM users u LEFT JOIN staff s ON s.user_id=u.id WHERE u.status='active' AND (u.id=? OR (u.role='staff' AND s.company_id=?))", [$job['posted_by'] ?? 0, $job['company_id']]) as $s)
                notify($s['id'], 'New application', $u['full_name'] . ' applied for ' . $job['title'] . '.');
            log_activity('Application submitted', $job['title']);
            flash('success', 'Application submitted successfully!'); redirect('job-details.php?id=' . $id);
        }
    }
}
$saved = saved_ids();
$related = rows(JOB_SELECT . " WHERE j.status='open' AND j.category_id <=> ? AND j.id<>? ORDER BY j.created_at DESC LIMIT 3", [$job['category_id'], $id]);

// ---- View helpers (display only) ----
$daysLeft = null;
if (!empty($job['deadline'])) {
    $daysLeft = (int) floor((strtotime($job['deadline']) - strtotime(date('Y-m-d'))) / 86400);
}
$postedAt = !empty($job['created_at']) ? date('M d, Y', strtotime($job['created_at'])) : null;
$showForm = !$canManage && $isOpen && $u && !$app;           // seeker who can apply now
$reqs = [];
if (!empty($job['requirements'])) {
    foreach (preg_split('/\R/', $job['requirements']) as $r) {
        $r = trim($r);
        if ($r !== '') $reqs[] = $r;
    }
}

include __DIR__ . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .jd {
        --ink: #0f1f3d;
        --ink-soft: #566680;
        --line: #e1e7f0;
        --bg: #f4f7fb;
        --brand: #1f5fe0;
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --navy: #0d2150;
        --good: #17855a;
        --good-tint: #e6f6ee;
        --warn: #b4690e;
        --warn-tint: #fff3e0;
        --bad: #c2362b;
        --bad-tint: #fdeceb;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--ink);
    }
    .jd h1, .jd h2, .jd h3, .jd h4, .jd h5 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.01em;
    }

    /* Back link */
    .jd-back { display: inline-flex; align-items: center; gap: .4rem; color: var(--ink-soft); text-decoration: none; font-size: .9rem; font-weight: 500; margin-bottom: 1rem; }
    .jd-back:hover { color: var(--brand); }

    /* Panels */
    .jd-panel { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
    .jd-panel + .jd-panel { margin-top: 1.25rem; }
    .jd-pad { padding: 1.75rem; }

    /* Hero */
    .jd-hero { background: var(--navy); color: #fff; padding: 1.75rem; display: flex; gap: 1.1rem; align-items: flex-start; }
    .jd-hero .logo-wrap { flex: none; background: #fff; border-radius: 16px; padding: 6px; line-height: 0; }
    .jd-hero .logo-wrap > * { border-radius: 12px; }
    .jd-hero h1 { font-size: clamp(1.4rem, 2.6vw, 1.9rem); font-weight: 700; margin: 0 0 .25rem; line-height: 1.2; }
    .jd-company { color: #c3d2f3; font-weight: 500; }
    .jd-badges { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: .85rem; }
    .jd-pill {
        display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 600;
        padding: .28rem .75rem; border-radius: 999px; background: rgba(255,255,255,.12); color: #fff;
    }
    .jd-pill.featured { background: #ffc94d; color: #4a3500; }
    .jd-actions { margin-left: auto; display: flex; gap: .5rem; flex: none; }
    .jd-icon-btn {
        width: 42px; height: 42px; border-radius: 12px; border: 1px solid rgba(255,255,255,.25);
        background: rgba(255,255,255,.1); color: #fff; display: grid; place-items: center; font-size: 1.05rem;
        transition: background .15s;
    }
    .jd-icon-btn:hover { background: rgba(255,255,255,.22); color: #fff; }
    .jd-icon-btn .text-danger { color: #ff8a80 !important; }

    /* Fact strip */
    .jd-facts { display: grid; grid-template-columns: repeat(4, 1fr); border-bottom: 1px solid var(--line); }
    .jd-fact { padding: 1.1rem 1.5rem; border-right: 1px solid var(--line); min-width: 0; }
    .jd-fact:last-child { border-right: 0; }
    .jd-fact small { display: flex; align-items: center; gap: .4rem; color: var(--ink-soft); font-size: .8rem; margin-bottom: .2rem; }
    .jd-fact small i { color: var(--brand); }
    .jd-fact b { font-size: .98rem; display: block; overflow-wrap: anywhere; }

    /* Content */
    .jd-h { font-size: 1.15rem; font-weight: 700; margin: 0 0 .85rem; }
    .jd-desc { color: #2a3a58; line-height: 1.75; max-width: 72ch; margin: 0; }
    .jd-section + .jd-section { margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--line); }
    .jd-reqs { list-style: none; margin: 0; padding: 0; display: grid; gap: .65rem; max-width: 72ch; }
    .jd-reqs li { display: flex; gap: .7rem; align-items: flex-start; line-height: 1.55; color: #2a3a58; }
    .jd-reqs li i { color: var(--brand); background: var(--brand-tint); border-radius: 50%; width: 22px; height: 22px; display: grid; place-items: center; font-size: .75rem; flex: none; margin-top: .1rem; }

    /* Apply card */
    .jd-side { position: sticky; top: 20px; }
    .jd-apply-top { padding: 1.4rem 1.5rem 1.1rem; border-bottom: 1px solid var(--line); }
    .jd-salary { font-family: 'Bricolage Grotesque', 'Figtree', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--brand); line-height: 1.2; }
    .jd-salary-sub { color: var(--ink-soft); font-size: .85rem; }
    .jd-deadline {
        display: inline-flex; align-items: center; gap: .4rem; margin-top: .8rem; font-size: .8rem; font-weight: 600;
        padding: .3rem .7rem; border-radius: 999px; background: var(--good-tint); color: var(--good);
    }
    .jd-deadline.soon { background: var(--warn-tint); color: var(--warn); }
    .jd-deadline.closed { background: var(--bad-tint); color: var(--bad); }
    .jd-apply-body { padding: 1.4rem 1.5rem 1.5rem; }
    .jd-apply-body h2 { font-size: 1.05rem; font-weight: 700; margin: 0 0 1rem; }

    .jd .form-label { font-weight: 600; font-size: .85rem; margin-bottom: .35rem; }
    .jd .form-control { border-color: #cbd5e3; border-radius: 12px; padding: .65rem .9rem; }
    .jd .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 .22rem rgba(31,95,224,.18); }
    .jd .form-text { font-size: .8rem; color: var(--ink-soft); }

    .jd-drop {
        position: relative; display: flex; align-items: center; gap: .85rem; padding: .9rem;
        border: 2px dashed #b9c6da; border-radius: 14px; background: var(--bg); cursor: pointer;
        transition: border-color .15s, background .15s;
    }
    .jd-drop:hover, .jd-drop:focus-within { border-color: var(--brand); background: var(--brand-tint); }
    .jd-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
    .jd-drop .ico { width: 44px; height: 44px; border-radius: 12px; background: #fff; border: 1px solid var(--line); color: var(--brand); display: grid; place-items: center; font-size: 1.2rem; flex: none; }
    .jd-drop .lbl { min-width: 0; line-height: 1.3; }
    .jd-drop .lbl b { display: block; font-size: .9rem; }
    .jd-drop .lbl small { color: var(--ink-soft); word-break: break-all; }
    .jd-note { display: flex; gap: .45rem; align-items: center; margin-top: .55rem; font-size: .8rem; color: var(--ink-soft); }
    .jd-note.ok { color: var(--good); }

    .jd .btn-jd {
        background: var(--brand); border: 0; color: #fff; font-weight: 600; border-radius: 12px; padding: .8rem 1.25rem;
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem; text-decoration: none;
    }
    .jd .btn-jd:hover { background: var(--brand-dark); color: #fff; }
    .jd .btn-jd:focus-visible { box-shadow: 0 0 0 .22rem rgba(31,95,224,.35); }
    .jd .btn-jd-ghost {
        background: #fff; border: 1px solid #c3d0e4; color: var(--ink); font-weight: 600; border-radius: 12px; padding: .75rem 1.25rem;
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem; text-decoration: none;
    }
    .jd .btn-jd-ghost:hover { border-color: var(--brand); color: var(--brand); }

    /* Applied state */
    .jd-applied { text-align: center; }
    .jd-applied .check { width: 56px; height: 56px; border-radius: 50%; background: var(--good-tint); color: var(--good); display: grid; place-items: center; font-size: 1.6rem; margin: 0 auto .8rem; }
    .jd-applied p { margin: 0 0 .6rem; color: var(--ink-soft); }

    /* Alerts */
    .jd .alert-jd { display: flex; gap: .6rem; align-items: flex-start; background: var(--bad-tint); color: #8c2a21; border: 1px solid #f3c6c1; border-radius: 12px; padding: .75rem .9rem; font-size: .9rem; margin-bottom: 1rem; }
    .jd .alert-jd i { margin-top: .1rem; }
    .jd-closed { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 1rem; text-align: center; color: var(--ink-soft); }
    .jd-closed i { font-size: 1.4rem; display: block; margin-bottom: .3rem; }

    /* Similar */
    .jd-similar-title { font-size: 1rem; font-weight: 700; margin: 1.75rem 0 .75rem; }

    /* Mobile apply bar */
    .jd-mobilebar {
        display: none; position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; background: rgba(255,255,255,.96);
        backdrop-filter: blur(6px); border-top: 1px solid var(--line); padding: .75rem 1rem; gap: .75rem; align-items: center;
    }
    .jd-mobilebar .s { flex: 1; min-width: 0; line-height: 1.2; }
    .jd-mobilebar .s b { display: block; color: var(--brand); font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .jd-mobilebar .s small { color: var(--ink-soft); }

    @media (max-width: 991.98px) {
        .jd-side { position: static; }
        .jd-facts { grid-template-columns: repeat(2, 1fr); }
        .jd-fact:nth-child(2) { border-right: 0; }
        .jd-fact:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        .jd-mobilebar.on { display: flex; }
        .jd { padding-bottom: 5rem; }
    }
    @media (max-width: 575.98px) {
        .jd-hero { flex-wrap: wrap; padding: 1.35rem; }
        .jd-actions { margin-left: 0; width: 100%; }
        .jd-pad { padding: 1.35rem; }
        .jd-fact { padding: .95rem 1.1rem; }
    }
    @media (prefers-reduced-motion: reduce) { .jd * { transition: none !important; } }
</style>

<div class="container py-4 jd">

    <a href="<?= url('jobs.php') ?>" class="jd-back"><i class="bi bi-arrow-left"></i> Back to jobs</a>

    <div class="row g-4">

        <!-- MAIN -->
        <div class="col-lg-8">
            <article class="jd-panel">

                <!-- Hero -->
                <header class="jd-hero">
                    <div class="logo-wrap"><?= company_logo($job, 56) ?></div>

                    <div class="min-w-0">
                        <h1><?= e($job['title']) ?></h1>
                        <div class="jd-company"><?= e($job['company_name']) ?></div>

                        <div class="jd-badges">
                            <?php if (!empty($job['is_featured'])): ?>
                                <span class="jd-pill featured"><i class="bi bi-star-fill"></i> Featured</span>
                            <?php endif; ?>
                            <span class="jd-pill"><i class="bi bi-clock"></i> <?= e($job['job_type']) ?></span>
                            <?php if ($job['category_name']): ?>
                                <span class="jd-pill"><i class="bi bi-tag"></i> <?= e($job['category_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($postedAt): ?>
                                <span class="jd-pill"><i class="bi bi-calendar3"></i> Posted <?= e($postedAt) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="jd-actions">
                        <button type="button" class="jd-icon-btn" id="shareBtn" aria-label="Copy job link" title="Copy link">
                            <i class="bi bi-link-45deg"></i>
                        </button>
                        <?php if ($u && $u['role'] === 'seeker'): ?>
                            <button type="button" class="jd-icon-btn btn-save" data-id="<?= $id ?>" aria-label="Save job" title="Save job">
                                <i class="bi <?= in_array($id, $saved) ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Quick facts -->
                <div class="jd-facts">
                    <div class="jd-fact">
                        <small><i class="bi bi-cash-stack"></i> Salary</small>
                        <b><?= salary($job) ?></b>
                    </div>
                    <div class="jd-fact">
                        <small><i class="bi bi-geo-alt"></i> Location</small>
                        <b><?= e($job['location']) ?></b>
                    </div>
                    <div class="jd-fact">
                        <small><i class="bi bi-briefcase"></i> Job type</small>
                        <b><?= e($job['job_type']) ?></b>
                    </div>
                    <div class="jd-fact">
                        <small><i class="bi bi-calendar-x"></i> Apply by</small>
                        <b><?= $job['deadline'] ? e(date('M d, Y', strtotime($job['deadline']))) : 'Open until filled' ?></b>
                    </div>
                </div>

                <!-- Body -->
                <div class="jd-pad">
                    <section class="jd-section">
                        <h2 class="jd-h">About the role</h2>
                        <p class="jd-desc"><?= nl2br(e($job['description'])) ?></p>
                    </section>

                    <?php if ($reqs): ?>
                        <section class="jd-section">
                            <h2 class="jd-h">What you'll need</h2>
                            <ul class="jd-reqs">
                                <?php foreach ($reqs as $r): ?>
                                    <li><i class="bi bi-check-lg"></i><span><?= e($r) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </div>

            </article>
        </div>

        <!-- SIDEBAR -->
        <aside class="col-lg-4">
            <div class="jd-side">

                <div class="jd-panel" id="apply">

                    <div class="jd-apply-top">
                        <div class="jd-salary"><?= salary($job) ?></div>
                        <div class="jd-salary-sub">at <?= e($job['company_name']) ?></div>

                        <?php if (!$isOpen): ?>
                            <span class="jd-deadline closed"><i class="bi bi-x-circle"></i> Applications closed</span>
                        <?php elseif ($daysLeft !== null): ?>
                            <span class="jd-deadline <?= $daysLeft <= 3 ? 'soon' : '' ?>">
                                <i class="bi bi-hourglass-split"></i>
                                <?= $daysLeft === 0 ? 'Last day to apply' : ($daysLeft === 1 ? '1 day left to apply' : $daysLeft . ' days left to apply') ?>
                            </span>
                        <?php else: ?>
                            <span class="jd-deadline"><i class="bi bi-check-circle"></i> Accepting applications</span>
                        <?php endif; ?>
                    </div>

                    <div class="jd-apply-body">

                        <?php if ($err): ?>
                            <div class="alert-jd" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div><?= e($err) ?></div></div>
                        <?php endif; ?>

                        <?php if ($canManage): ?>
                            <p class="text-muted small mb-0">You are logged in as <?= e($u['role']) ?>. Only job seekers can apply.</p>

                        <?php elseif (!$isOpen): ?>
                            <div class="jd-closed"><i class="bi bi-lock"></i>This job is closed.</div>

                        <?php elseif (!$u): ?>
                            <h2>Ready to apply?</h2>
                            <p class="text-muted small">Log in as a job seeker to send your application.</p>
                            <a href="<?= url('login.php') ?>" class="btn-jd w-100 mb-2">Log in to apply</a>
                            <a href="<?= url('register.php') ?>" class="btn-jd-ghost w-100">Create account</a>

                        <?php elseif ($app): ?>
                            <div class="jd-applied">
                                <div class="check"><i class="bi bi-check-lg"></i></div>
                                <h2 class="mb-1">Application sent</h2>
                                <p>You applied on <?= e(date('M d, Y', strtotime($app['created_at']))) ?></p>
                                <?= status_badge($app['status'], $app['id']) ?>
                                <a href="<?= url('seeker/my-applications.php') ?>" class="btn-jd-ghost w-100 mt-3">Track application</a>
                            </div>

                        <?php else: ?>
                            <h2>Apply for this job</h2>
                            <form method="post" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="apply">

                                <div class="mb-3">
                                    <label class="form-label" for="cover_letter">Cover letter <span class="text-muted fw-normal">(optional)</span></label>
                                    <textarea id="cover_letter" name="cover_letter" rows="4" class="form-control"
                                              placeholder="Tell them why you're a good fit."><?= e($_POST['cover_letter'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="resumeInput">Resume</label>
                                    <label class="jd-drop">
                                        <span class="ico"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                        <span class="lbl">
                                            <b>Choose a file</b>
                                            <small id="resumeName">PDF or DOCX, up to 5 MB</small>
                                        </span>
                                        <input type="file" id="resumeInput" name="resume" accept=".pdf,.docx">
                                    </label>
                                    <?php if (!empty($profile['resume'])): ?>
                                        <div class="jd-note ok"><i class="bi bi-check-circle-fill"></i> Leave empty to use the resume saved in your profile.</div>
                                    <?php else: ?>
                                        <div class="jd-note"><i class="bi bi-info-circle"></i> No resume in your profile yet. Upload one here.</div>
                                    <?php endif; ?>
                                </div>

                                <button class="btn-jd w-100" type="submit"><i class="bi bi-send"></i> Apply now</button>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

                <?php if ($related): ?>
                    <h3 class="jd-similar-title">Similar jobs</h3>
                    <?php foreach ($related as $r) echo job_card($r, $saved); ?>
                <?php endif; ?>

            </div>
        </aside>

    </div>

    <?php if ($showForm): ?>
        <div class="jd-mobilebar on" id="mobileBar">
            <div class="s">
                <b><?= salary($job) ?></b>
                <small><?= $daysLeft !== null ? ($daysLeft === 0 ? 'Last day to apply' : $daysLeft . ' day(s) left') : 'Accepting applications' ?></small>
            </div>
            <a href="#apply" class="btn-jd">Apply now</a>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    // Show chosen resume name
    var input = document.getElementById('resumeInput'), nameEl = document.getElementById('resumeName');
    if (input) {
        input.addEventListener('change', function () {
            if (this.files[0]) nameEl.textContent = this.files[0].name;
        });
    }

    // Copy job link
    var share = document.getElementById('shareBtn');
    if (share) {
        share.addEventListener('click', function () {
            var icon = share.firstElementChild, old = icon.className;
            function done() {
                icon.className = 'bi bi-check-lg';
                share.title = 'Link copied';
                setTimeout(function () { icon.className = old; share.title = 'Copy link'; }, 1600);
            }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).then(done);
            } else {
                var t = document.createElement('textarea');
                t.value = window.location.href;
                document.body.appendChild(t); t.select();
                document.execCommand('copy'); document.body.removeChild(t);
                done();
            }
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>