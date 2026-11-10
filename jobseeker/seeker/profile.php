<?php
require_once __DIR__ . '/../includes/auth.php';

$u = require_role('seeker');
$layout = 'dash';
$pageTitle = 'Profile';

$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // UPDATE PERSONAL INFORMATION
    if (($_POST['action'] ?? '') === 'profile') {

        $name = trim($_POST['full_name'] ?? '');
        $dob  = trim($_POST['date_of_birth'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $sex = trim($_POST['sex'] ?? '');

        $location = trim($_POST['location'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (strlen($name) < 3) {
            $err[] = 'Please enter your full name.';
        }

        // Validate birthday
        $date = DateTime::createFromFormat('!Y-m-d', $dob);

        if (
            !$date ||
            $date->format('Y-m-d') !== $dob ||
            $dob > date('Y-m-d')
        ) {
            $err[] = 'Please enter a valid date of birth.';
        }

        // Validate phone
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $err[] = 'Please enter a valid phone number.';
        }

        // Validate sex
        if (!in_array($sex, [
            'Male',
            'Female',
            'Prefer not to say'
        ], true)) {
            $err[] = 'Please select your sex.';
        }

        // Upload files
        [$pic, $e1] = upload_file(
            'picture',
            'profiles',
            ['jpg', 'jpeg', 'png'],
            2
        );

        [$cv, $e2] = upload_file(
            'resume',
            'resumes',
            ['pdf', 'docx'],
            5
        );

        foreach ([$e1, $e2] as $x) {
            if ($x) $err[] = $x;
        }

        if (!$err) {

            // Update user name
            q(
                'UPDATE users SET full_name=? WHERE id=?',
                [$name, $u['id']]
            );

            // Ensure profile record exists
            q(
                'INSERT INTO user_profiles (user_id)
                 VALUES (?)
                 ON DUPLICATE KEY UPDATE user_id=user_id',
                [$u['id']]
            );

            // Update all profile information
            q(
                'UPDATE user_profiles SET
                    date_of_birth=?,
                    phone=?,
                    sex=?,
                    location=?,
                    headline=?,
                    skills=?,
                    bio=?,
                    profile_picture=COALESCE(?, profile_picture),
                    resume=COALESCE(?, resume)
                 WHERE user_id=?',
                [
                    $dob,
                    $phone,
                    $sex,
                    $location,
                    $headline,
                    $skills,
                    $bio,
                    $pic,
                    $cv,
                    $u['id']
                ]
            );

            log_activity('Profile updated', '');

            flash('success', 'Profile updated successfully.');

            redirect('seeker/profile.php');
        }

    }

    // CHANGE PASSWORD
    elseif (($_POST['action'] ?? '') === 'password') {

        $new = $_POST['new_password'] ?? '';

        if (!password_verify(
            $_POST['current_password'] ?? '',
            $u['password']
        )) {

            $err[] = 'Current password is incorrect.';

        } elseif (
            strlen($new) < 8 ||
            !preg_match('/[A-Za-z]/', $new) ||
            !preg_match('/\d/', $new)
        ) {

            $err[] = 'New password needs 8+ characters with letters and numbers.';

        } elseif (
            $new !== ($_POST['confirm_password'] ?? '')
        ) {

            $err[] = 'New passwords do not match.';

        } else {

            q(
                'UPDATE users SET password=? WHERE id=?',
                [
                    password_hash($new, PASSWORD_DEFAULT),
                    $u['id']
                ]
            );

            log_activity('Password changed', '');

            flash('success', 'Password changed successfully.');

            redirect('seeker/profile.php');
        }
    }
}

// GET PROFILE INFORMATION
$p = row(
    'SELECT * FROM user_profiles WHERE user_id=?',
    [$u['id']]
) ?: [];

// Keep what the person typed if validation failed
$formName = $u['full_name'];
if ($err && ($_POST['action'] ?? '') === 'profile') {
    $formName = $name;
    $p = array_merge($p, [
        'date_of_birth' => $dob,
        'phone'         => $phone,
        'sex'           => $sex,
        'location'      => $location,
        'headline'      => $headline,
        'skills'        => $skills,
        'bio'           => $bio,
    ]);
}

$pc = (int) profile_completion($u['id']);

// Ring geometry for the completion indicator
$ringR = 56;
$ringC = 2 * M_PI * $ringR;
$ringOffset = $ringC * (1 - max(0, min(100, $pc)) / 100);

// What is still missing (drives the checklist)
$checks = [
    ['Add a profile photo',  !empty($p['profile_picture']), '#picture'],
    ['Write a headline',     !empty($p['headline']),        '#headline'],
    ['Add your location',    !empty($p['location']),        '#location'],
    ['List your skills',     !empty($p['skills']),          '#skills'],
    ['Write your bio',       !empty($p['bio']),             '#bio'],
    ['Upload your resume',   !empty($p['resume']),          '#resume'],
];
$todo = array_filter($checks, fn($c) => !$c[1]);

$skillList = array_filter(array_map('trim', explode(',', $p['skills'] ?? '')));

include ROOT_PATH . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .pf {
        --pf-ink: #12263a;
        --pf-ink-soft: #52667a;
        --pf-line: #dfe6ec;
        --pf-surface: #ffffff;
        --pf-bg: #f3f6f9;
        --pf-brand: #0b6e8a;
        --pf-brand-dark: #08566d;
        --pf-brand-tint: #e3f2f6;
        --pf-good: #1f8a5b;
        --pf-warn: #b9771a;
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--pf-ink);
    }
    .pf h1, .pf h2, .pf h3, .pf h4, .pf h5, .pf h6, .pf .pf-display {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.01em;
    }

    /* Page intro */
    .pf-intro { margin-bottom: 1.5rem; }
    .pf-intro h1 { font-size: 1.75rem; font-weight: 700; margin: 0 0 .25rem; }
    .pf-intro p  { margin: 0; color: var(--pf-ink-soft); max-width: 60ch; }

    /* Cards */
    .pf-card {
        background: var(--pf-surface);
        border: 1px solid var(--pf-line);
        border-radius: 16px;
        overflow: hidden;
    }
    .pf-card + .pf-card { margin-top: 1.25rem; }
    .pf-card-body { padding: 1.5rem; }

    /* Identity card: solid band, avatar sits on a completion ring */
    .pf-band { background: var(--pf-ink); height: 88px; }
    .pf-identity { padding: 0 1.5rem 1.5rem; text-align: center; margin-top: -66px; }
    .pf-ring { position: relative; width: 132px; height: 132px; margin: 0 auto .75rem; }
    .pf-ring svg { position: absolute; inset: 0; transform: rotate(-90deg); }
    .pf-ring .track { fill: var(--pf-surface); stroke: var(--pf-line); stroke-width: 7; }
    .pf-ring .meter { fill: none; stroke: var(--pf-brand); stroke-width: 7; stroke-linecap: round; }
    .pf-ring .pf-avatar-slot {
        position: absolute; inset: 18px; border-radius: 50%; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
    }
    .pf-ring .pf-avatar-slot > * { width: 100% !important; height: 100% !important; object-fit: cover; }
    .pf-pct {
        position: absolute; left: 50%; bottom: -6px; transform: translateX(-50%);
        background: var(--pf-brand); color: #fff; font-weight: 600; font-size: .75rem;
        padding: .15rem .6rem; border-radius: 999px; border: 3px solid var(--pf-surface);
    }
    .pf-name { font-size: 1.3rem; font-weight: 700; margin: .9rem 0 .15rem; }
    .pf-headline { color: var(--pf-ink-soft); margin: 0 0 .75rem; min-height: 1.4em; }
    .pf-meta { display: flex; flex-direction: column; gap: .35rem; align-items: center; font-size: .875rem; color: var(--pf-ink-soft); }
    .pf-meta i { color: var(--pf-brand); margin-right: .4rem; }

    /* Checklist */
    .pf-title { font-size: 1rem; font-weight: 700; margin: 0 0 .25rem; }
    .pf-sub { font-size: .85rem; color: var(--pf-ink-soft); margin: 0 0 1rem; }
    .pf-todo { list-style: none; margin: 0; padding: 0; }
    .pf-todo li a {
        display: flex; align-items: center; gap: .65rem; padding: .55rem .65rem; margin: 0 -.65rem;
        border-radius: 10px; color: var(--pf-ink); text-decoration: none; font-size: .925rem;
    }
    .pf-todo li a:hover { background: var(--pf-bg); }
    .pf-todo .dot {
        width: 22px; height: 22px; border-radius: 50%; flex: none; display: grid; place-items: center;
        border: 2px solid var(--pf-line); font-size: .7rem;
    }
    .pf-todo li.done a { color: var(--pf-ink-soft); }
    .pf-todo li.done .dot { background: var(--pf-good); border-color: var(--pf-good); color: #fff; }
    .pf-todo li.done span.t { text-decoration: line-through; text-decoration-color: #b4c0cb; }
    .pf-allset { display: flex; gap: .6rem; align-items: center; color: var(--pf-good); font-weight: 600; }

    /* Resume on file */
    .pf-file {
        display: flex; align-items: center; gap: .85rem; padding: .85rem;
        border: 1px solid var(--pf-line); border-radius: 12px; background: var(--pf-bg);
    }
    .pf-file .ico {
        width: 42px; height: 42px; border-radius: 10px; background: var(--pf-brand-tint);
        color: var(--pf-brand); display: grid; place-items: center; font-size: 1.2rem; flex: none;
    }
    .pf-file .txt { flex: 1; min-width: 0; line-height: 1.25; }
    .pf-file .txt b { display: block; font-size: .925rem; }
    .pf-file .txt small { color: var(--pf-ink-soft); }

    /* Form sections */
    .pf-section { padding: 1.75rem 1.5rem; border-bottom: 1px solid var(--pf-line); scroll-margin-top: 90px; }
    .pf-section:last-of-type { border-bottom: 0; }
    .pf-section-head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.25rem; }
    .pf-section-head .ico {
        width: 40px; height: 40px; border-radius: 12px; background: var(--pf-brand-tint);
        color: var(--pf-brand); display: grid; place-items: center; font-size: 1.1rem; flex: none;
    }
    .pf-section-head h2 { font-size: 1.15rem; font-weight: 700; margin: 0; }
    .pf-section-head p  { margin: .1rem 0 0; font-size: .875rem; color: var(--pf-ink-soft); }

    .pf .form-label { font-weight: 600; font-size: .875rem; margin-bottom: .35rem; }
    .pf .form-control, .pf .form-select {
        border-color: #cdd7e0; border-radius: 10px; padding: .6rem .85rem; color: var(--pf-ink);
    }
    .pf .form-control:focus, .pf .form-select:focus {
        border-color: var(--pf-brand); box-shadow: 0 0 0 .22rem rgba(11, 110, 138, .18);
    }
    .pf .form-control[readonly] { background: var(--pf-bg); color: var(--pf-ink-soft); }
    .pf .form-text, .pf small.hint { color: var(--pf-ink-soft); font-size: .8rem; }
    .pf .input-group-text { background: var(--pf-bg); border-color: #cdd7e0; color: var(--pf-ink-soft); border-radius: 10px 0 0 10px; }
    .pf .input-group .form-control { border-radius: 0 10px 10px 0; }

    /* Skill chips (live preview) */
    .pf-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .65rem; min-height: 1.9rem; }
    .pf-chip {
        background: var(--pf-brand-tint); color: var(--pf-brand-dark); font-size: .8rem; font-weight: 600;
        padding: .25rem .7rem; border-radius: 999px;
    }
    .pf-chips-empty { color: var(--pf-ink-soft); font-size: .8rem; }

    /* Upload tiles */
    .pf-drop {
        position: relative; display: flex; align-items: center; gap: .9rem; padding: 1rem;
        border: 2px dashed #bccad6; border-radius: 14px; background: var(--pf-bg);
        cursor: pointer; transition: border-color .15s, background .15s; height: 100%;
    }
    .pf-drop:hover, .pf-drop:focus-within { border-color: var(--pf-brand); background: var(--pf-brand-tint); }
    .pf-drop input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
    .pf-drop .ico {
        width: 48px; height: 48px; border-radius: 12px; background: #fff; border: 1px solid var(--pf-line);
        color: var(--pf-brand); display: grid; place-items: center; font-size: 1.25rem; flex: none; overflow: hidden;
    }
    .pf-drop .ico img { width: 100%; height: 100%; object-fit: cover; }
    .pf-drop .lbl b { display: block; font-size: .925rem; }
    .pf-drop .lbl small { color: var(--pf-ink-soft); word-break: break-all; }

    /* Bio counter */
    .pf-count { text-align: right; font-size: .8rem; color: var(--pf-ink-soft); margin-top: .3rem; }

    /* Sticky save bar */
    .pf-savebar {
        position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; padding: .9rem 1.5rem; background: rgba(255, 255, 255, .94); backdrop-filter: blur(6px);
        border-top: 1px solid var(--pf-line);
    }
    .pf-savebar .note { font-size: .85rem; color: var(--pf-ink-soft); }
    .pf-savebar .note.dirty { color: var(--pf-warn); font-weight: 600; }

    /* Buttons */
    .pf .btn-pf {
        background: var(--pf-brand); border: 0; color: #fff; font-weight: 600;
        border-radius: 10px; padding: .6rem 1.25rem;
    }
    .pf .btn-pf:hover { background: var(--pf-brand-dark); color: #fff; }
    .pf .btn-pf:focus-visible { box-shadow: 0 0 0 .22rem rgba(11, 110, 138, .35); }
    .pf .btn-ghost {
        background: #fff; border: 1px solid #cdd7e0; color: var(--pf-ink); font-weight: 600;
        border-radius: 10px; padding: .45rem .9rem; font-size: .875rem;
    }
    .pf .btn-ghost:hover { border-color: var(--pf-brand); color: var(--pf-brand); }

    /* Password */
    .pf-meter { height: 5px; border-radius: 99px; background: var(--pf-line); overflow: hidden; margin-top: .45rem; }
    .pf-meter span { display: block; height: 100%; width: 0; background: #c0392b; transition: width .2s, background .2s; }
    .pf-meter-label { font-size: .78rem; color: var(--pf-ink-soft); margin-top: .25rem; min-height: 1.1em; }
    .pf .pw-wrap { position: relative; }
    .pf .pw-wrap .form-control { padding-right: 2.6rem; }
    .pf .pw-toggle {
        position: absolute; right: .35rem; top: 50%; transform: translateY(-50%); border: 0; background: none;
        color: var(--pf-ink-soft); padding: .3rem .5rem; border-radius: 8px;
    }
    .pf .pw-toggle:hover { color: var(--pf-brand); }

    /* Alerts */
    .pf .alert-pf {
        border: 1px solid #f0c9c4; background: #fdf1ef; color: #8c2f23; border-radius: 12px;
        padding: .85rem 1rem; display: flex; gap: .65rem; align-items: flex-start; margin-bottom: .6rem;
    }
    .pf .alert-pf i { margin-top: .1rem; }

    @media (min-width: 992px) {
        .pf-side { position: sticky; top: 84px; }
    }
    @media (max-width: 575.98px) {
        .pf-section { padding: 1.4rem 1.1rem; }
        .pf-savebar { padding: .8rem 1.1rem; }
        .pf-savebar .note { display: none; }
        .pf-savebar .btn { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .pf * { transition: none !important; }
    }
</style>

<div class="pf">

    <header class="pf-intro">
        <h1>Your profile</h1>
        <p>This is what employers see when you apply. A complete profile gets noticed faster.</p>
    </header>

    <?php foreach ($err as $x): ?>
        <div class="alert-pf" role="alert">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div><?= e($x) ?></div>
        </div>
    <?php endforeach; ?>

    <div class="row g-4">

        <!-- LEFT SIDE -->
        <aside class="col-lg-4">
            <div class="pf-side">

                <!-- IDENTITY CARD -->
                <div class="pf-card">
                    <div class="pf-band"></div>
                    <div class="pf-identity">

                        <div class="pf-ring" role="img" aria-label="Profile <?= $pc ?>% complete">
                            <svg viewBox="0 0 132 132" aria-hidden="true">
                                <circle class="track" cx="66" cy="66" r="<?= $ringR ?>"></circle>
                                <circle class="meter" cx="66" cy="66" r="<?= $ringR ?>"
                                        stroke-dasharray="<?= round($ringC, 2) ?>"
                                        stroke-dashoffset="<?= round($ringOffset, 2) ?>"></circle>
                            </svg>
                            <div class="pf-avatar-slot"><?= avatar($u, 96) ?></div>
                            <span class="pf-pct"><?= $pc ?>%</span>
                        </div>

                        <h2 class="pf-name"><?= e($u['full_name']) ?></h2>
                        <p class="pf-headline">
                            <?= e($p['headline'] ?? '') ?: 'Add a headline so employers know what you do' ?>
                        </p>

                        <div class="pf-meta">
                            <span><i class="bi bi-envelope"></i><?= e($u['email']) ?></span>
                            <?php if (!empty($p['location'])): ?>
                                <span><i class="bi bi-geo-alt"></i><?= e($p['location']) ?></span>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- COMPLETION CHECKLIST -->
                <div class="pf-card">
                    <div class="pf-card-body">
                        <?php if ($todo): ?>
                            <h3 class="pf-title"><?= count($todo) ?> <?= count($todo) === 1 ? 'step' : 'steps' ?> to a complete profile</h3>
                            <p class="pf-sub">Tap an item to jump to that field.</p>
                        <?php else: ?>
                            <div class="pf-allset">
                                <i class="bi bi-patch-check-fill fs-4"></i>
                                <span>Your profile is complete.</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($todo): ?>
                            <ul class="pf-todo mt-2">
                                <?php foreach ($checks as [$label, $done, $anchor]): ?>
                                    <li class="<?= $done ? 'done' : '' ?>">
                                        <a href="<?= e($anchor) ?>">
                                            <span class="dot"><?= $done ? '<i class="bi bi-check-lg"></i>' : '' ?></span>
                                            <span class="t"><?= e($label) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RESUME ON FILE -->
                <?php if (!empty($p['resume'])): ?>
                    <div class="pf-card">
                        <div class="pf-card-body">
                            <h3 class="pf-title">Resume on file</h3>
                            <div class="pf-file mt-3">
                                <div class="ico"><i class="bi bi-file-earmark-text"></i></div>
                                <div class="txt">
                                    <b>My resume</b>
                                    <small>Shared when you apply</small>
                                </div>
                                <a class="btn-ghost text-decoration-none" href="<?= url('download.php?me=1') ?>">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- CHANGE PASSWORD -->
                <div class="pf-card">
                    <div class="pf-card-body">

                        <h3 class="pf-title">Change password</h3>
                        <p class="pf-sub">Use 8+ characters with letters and numbers.</p>

                        <form method="post" id="pwForm" novalidate>

                            <?= csrf_field() ?>

                            <input type="hidden" name="action" value="password">

                            <div class="pw-wrap mb-3">
                                <label class="form-label" for="current_password">Current password</label>
                                <input type="password" id="current_password" name="current_password"
                                       class="form-control" autocomplete="current-password" required>
                                <button type="button" class="pw-toggle" aria-label="Show password" style="top:calc(50% + .85rem)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <div class="mb-3">
                                <div class="pw-wrap">
                                    <label class="form-label" for="new_password">New password</label>
                                    <input type="password" id="new_password" name="new_password"
                                           class="form-control" autocomplete="new-password" required>
                                    <button type="button" class="pw-toggle" aria-label="Show password" style="top:calc(50% + .85rem)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="pf-meter"><span id="pwBar"></span></div>
                                <div class="pf-meter-label" id="pwText"></div>
                            </div>

                            <div class="pw-wrap mb-3">
                                <label class="form-label" for="confirm_password">Confirm new password</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       class="form-control" autocomplete="new-password" required>
                                <button type="button" class="pw-toggle" aria-label="Show password" style="top:calc(50% + .85rem)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <button class="btn btn-pf w-100">Update password</button>

                        </form>

                    </div>
                </div>

            </div>
        </aside>

        <!-- RIGHT SIDE -->
        <div class="col-lg-8">

            <form method="post" enctype="multipart/form-data" class="pf-card" id="profileForm">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="profile">

                <!-- BASICS -->
                <section class="pf-section" id="sec-basics">
                    <div class="pf-section-head">
                        <div class="ico"><i class="bi bi-person"></i></div>
                        <div>
                            <h2>Basic details</h2>
                            <p>Employers use these to identify and contact you.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                   autocomplete="name" value="<?= e($formName) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="email" id="email" class="form-control" value="<?= e($u['email']) ?>" readonly>
                            </div>
                            <div class="form-text">Your email can't be changed here.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="date_of_birth">Date of birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                                   value="<?= e($p['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                       placeholder="+63 9XX XXX XXXX" autocomplete="tel"
                                       value="<?= e($p['phone'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sex">Sex</label>
                            <select id="sex" name="sex" class="form-select" required>
                                <option value="">Select one</option>
                                <?php foreach (['Male', 'Female', 'Prefer not to say'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= ($p['sex'] ?? '') === $opt ? 'selected' : '' ?>>
                                        <?= e($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="location">Location</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" id="location" name="location" class="form-control"
                                       placeholder="e.g. Angeles City, Pampanga" value="<?= e($p['location'] ?? '') ?>">
                            </div>
                        </div>

                    </div>
                </section>

                <!-- PROFESSIONAL -->
                <section class="pf-section" id="sec-pro">
                    <div class="pf-section-head">
                        <div class="ico"><i class="bi bi-briefcase"></i></div>
                        <div>
                            <h2>Your professional story</h2>
                            <p>Show what you do and what you're good at.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label" for="headline">Professional headline</label>
                            <input type="text" id="headline" name="headline" class="form-control" maxlength="120"
                                   placeholder="e.g. Junior web developer who loves clean interfaces"
                                   value="<?= e($p['headline'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="skills">Skills</label>
                            <input type="text" id="skills" name="skills" class="form-control"
                                   placeholder="HTML, CSS, PHP, JavaScript"
                                   value="<?= e($p['skills'] ?? '') ?>">
                            <div class="form-text">Separate each skill with a comma.</div>
                            <div class="pf-chips" id="skillChips" aria-live="polite">
                                <?php foreach ($skillList as $s): ?>
                                    <span class="pf-chip"><?= e($s) ?></span>
                                <?php endforeach; ?>
                                <?php if (!$skillList): ?>
                                    <span class="pf-chips-empty">Your skills will appear here.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="bio">About me</label>
                            <textarea id="bio" name="bio" rows="5" maxlength="1000" class="form-control"
                                      placeholder="Introduce yourself in a few sentences: your experience, what you're looking for, and what you'd bring to a team."><?= e($p['bio'] ?? '') ?></textarea>
                            <div class="pf-count"><span id="bioCount">0</span> / 1000</div>
                        </div>

                    </div>
                </section>

                <!-- FILES -->
                <section class="pf-section" id="sec-files">
                    <div class="pf-section-head">
                        <div class="ico"><i class="bi bi-paperclip"></i></div>
                        <div>
                            <h2>Photo and resume</h2>
                            <p>Leave these empty to keep your current files.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6" id="picture">
                            <label class="pf-drop">
                                <span class="ico" id="picPreview"><i class="bi bi-image"></i></span>
                                <span class="lbl">
                                    <b>Profile photo</b>
                                    <small id="picName">JPG or PNG, up to 2 MB</small>
                                </span>
                                <input type="file" name="picture" accept=".jpg,.jpeg,.png" aria-label="Profile photo">
                            </label>
                        </div>

                        <div class="col-md-6" id="resume">
                            <label class="pf-drop">
                                <span class="ico"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                <span class="lbl">
                                    <b>Resume or CV</b>
                                    <small id="cvName">PDF or DOCX, up to 5 MB</small>
                                </span>
                                <input type="file" name="resume" accept=".pdf,.docx" aria-label="Resume or CV">
                            </label>
                        </div>

                    </div>
                </section>

                <!-- SAVE BAR -->
                <div class="pf-savebar">
                    <span class="note" id="saveNote">All changes saved</span>
                    <button type="submit" class="btn btn-pf">
                        <i class="bi bi-check2-circle me-1"></i> Save changes
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>

<script>
(function () {
    var $ = function (s, r) { return (r || document).querySelector(s); };

    // Unsaved-changes hint
    var form = $('#profileForm'), note = $('#saveNote');
    form.addEventListener('input', function () {
        note.textContent = 'You have unsaved changes';
        note.classList.add('dirty');
    });
    form.addEventListener('submit', function () { window.onbeforeunload = null; });
    form.addEventListener('change', function () {
        window.onbeforeunload = function () { return ''; };
    });

    // Live skill chips
    var skills = $('#skills'), chips = $('#skillChips');
    function renderChips() {
        var list = skills.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        chips.innerHTML = '';
        if (!list.length) {
            var em = document.createElement('span');
            em.className = 'pf-chips-empty';
            em.textContent = 'Your skills will appear here.';
            chips.appendChild(em);
            return;
        }
        list.forEach(function (s) {
            var c = document.createElement('span');
            c.className = 'pf-chip';
            c.textContent = s;
            chips.appendChild(c);
        });
    }
    skills.addEventListener('input', renderChips);

    // Bio counter
    var bio = $('#bio'), count = $('#bioCount');
    function updateCount() { count.textContent = bio.value.length; }
    bio.addEventListener('input', updateCount);
    updateCount();

    // File pickers: show name, preview photo
    $('input[name=picture]').addEventListener('change', function () {
        var f = this.files[0];
        if (!f) return;
        $('#picName').textContent = f.name;
        var box = $('#picPreview');
        var img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(f);
        box.innerHTML = '';
        box.appendChild(img);
    });
    $('input[name=resume]').addEventListener('change', function () {
        if (this.files[0]) $('#cvName').textContent = this.files[0].name;
    });

    // Show/hide password
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Password strength
    var np = $('#new_password'), bar = $('#pwBar'), txt = $('#pwText');
    np.addEventListener('input', function () {
        var v = np.value, score = 0;
        if (v.length >= 8) score++;
        if (/[A-Za-z]/.test(v) && /\d/.test(v)) score++;
        if (v.length >= 12) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        var levels = [
            ['0%', '#c0392b', ''],
            ['25%', '#c0392b', 'Too weak'],
            ['50%', '#d68910', 'Okay'],
            ['75%', '#1f8a5b', 'Good'],
            ['100%', '#1f8a5b', 'Strong']
        ];
        if (!v) score = 0;
        bar.style.width = levels[score][0];
        bar.style.background = levels[score][1];
        txt.textContent = levels[score][2];
    });
})();
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>