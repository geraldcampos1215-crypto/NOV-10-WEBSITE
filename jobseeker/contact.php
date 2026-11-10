<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Contact Us';
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $msg = trim($_POST['message'] ?? '');
    if ($name === '') $err['name'] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
    if (strlen($msg) < 10) $err['message'] = 'Message must be at least 10 characters.';
    if (!$err) {
        foreach (rows("SELECT id FROM users WHERE role='admin' AND status='active'") as $a)
            notify($a['id'], 'Contact message from ' . $name, "$email: $msg");
        log_activity('Contact form', $email, null);
        flash('success', 'Thanks! Your message was sent. We will get back to you soon.'); redirect('contact.php');
    }
}
include __DIR__ . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Same palette as the login page */
    .ct {
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
        position: relative; overflow: hidden; background: var(--sky); min-height: 70vh;
    }
    .ct::before, .ct::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
    .ct::before {
        width: 1500px; height: 1500px; background: var(--deep);
        top: -1180px; left: 50%; transform: translateX(-30%);
    }
    .ct::after {
        width: 900px; height: 900px; background: var(--deeper); opacity: .55;
        bottom: -560px; left: -300px;
    }
    .ct > .container { position: relative; z-index: 1; }
    .ct h1, .ct h2, .ct h3 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
    }

    /* ---------- Left side ---------- */
    .ct-intro h1 {
        font-size: clamp(2.4rem, 5vw, 3.6rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.05;
        color: #fff; margin: 0 0 .9rem;
    }
    .ct-intro > p { color: rgba(255,255,255,.9); font-size: 1.08rem; line-height: 1.65; max-width: 44ch; margin: 0 0 2rem; }

    .ct-info { display: grid; gap: 1rem; }
    .ct-row { display: flex; align-items: center; gap: .9rem; color: #fff; font-weight: 500; }
    .ct-row .ico {
        width: 44px; height: 44px; border-radius: 50%; flex: none; display: grid; place-items: center;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.55); color: #fff; font-size: 1.1rem;
    }

    /* ---------- Form card (same as login card) ---------- */
    .ct-card {
        background: #fff; border: 1px solid var(--line); border-radius: 24px; padding: 2rem;
        box-shadow: 0 0 0 6px rgba(255,255,255,.18), 0 24px 50px rgba(10, 26, 68, .3);
    }
    .ct-card h2 { font-size: 1.5rem; font-weight: 700; margin: 0 0 .25rem; text-align: center; color: var(--ink); }
    .ct-card .sub { text-align: center; color: var(--ink-soft); font-size: .92rem; margin: 0 0 1.5rem; }

    .ct-card .form-label { font-size: .85rem; font-weight: 600; color: var(--ink); margin-bottom: .35rem; }
    .ct-card .form-control {
        border: 1px solid var(--line); border-radius: 12px; padding: .8rem 1rem; font-size: .98rem; color: var(--ink);
        font-family: inherit; transition: border-color .2s, box-shadow .2s;
    }
    .ct-card .form-control::placeholder { color: #8a97ad; }
    .ct-card .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(31, 95, 224, .18); }
    .ct-card .form-control.is-invalid { border-color: #dc3545; background-image: none; }
    .ct-card .form-control.is-invalid:focus { box-shadow: 0 0 0 3px rgba(220, 53, 69, .18); }
    .ct-card textarea.form-control { resize: vertical; min-height: 130px; }

    .ct-send {
        width: 100%; background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: .9rem;
        font-weight: 700; letter-spacing: .02em; font-family: inherit; font-size: 1rem;
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        transition: background .2s, transform .15s;
    }
    .ct-send i { transition: transform .2s ease; }
    .ct-send:hover { background: var(--brand-dark); }
    .ct-send:hover i { transform: translateX(4px); }
    .ct-send:active { transform: scale(.98); }
    .ct-send:focus-visible { outline: 3px solid rgba(31,95,224,.4); outline-offset: 2px; }

    @media (max-width: 991.98px) {
        .ct::before { width: 900px; height: 900px; top: -700px; }
        .ct-card { padding: 1.5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ct *, .ct *::after { transition: none !important; }
    }
</style>

<div class="ct">
<section class="container py-5">
  <div class="row g-5 align-items-center">

    <div class="col-lg-5 ct-intro">
      <h1>Contact us.</h1>
      <p>Questions about JobSeeker? Send us a message and we'll get back to you soon.</p>
      <div class="ct-info">
        <div class="ct-row"><span class="ico"><i class="bi bi-envelope"></i></span><span><?= e(setting('contact_email')) ?></span></div>
        <div class="ct-row"><span class="ico"><i class="bi bi-telephone"></i></span><span><?= e(setting('contact_phone')) ?></span></div>
        <div class="ct-row"><span class="ico"><i class="bi bi-geo-alt"></i></span><span><?= e(setting('contact_address')) ?></span></div>
      </div>
    </div>

    <div class="col-lg-7">
      <form method="post" class="ct-card" novalidate><?= csrf_field() ?>
        <h2>Send a message</h2>
        <p class="sub">Fill in the form and we'll reply by email.</p>

        <?php foreach (['name' => 'Your name', 'email' => 'Email address'] as $k => $l): ?>
          <div class="mb-3">
            <label class="form-label" for="f_<?= $k ?>"><?= $l ?></label>
            <input id="f_<?= $k ?>" name="<?= $k ?>" class="form-control <?= isset($err[$k]) ? 'is-invalid' : '' ?>" value="<?= e($_POST[$k] ?? '') ?>">
            <div class="invalid-feedback"><?= e($err[$k] ?? '') ?></div>
          </div>
        <?php endforeach; ?>

        <div class="mb-4">
          <label class="form-label" for="f_message">Message</label>
          <textarea id="f_message" name="message" rows="5" class="form-control <?= isset($err['message']) ? 'is-invalid' : '' ?>"><?= e($_POST['message'] ?? '') ?></textarea>
          <div class="invalid-feedback"><?= e($err['message'] ?? '') ?></div>
        </div>

        <button class="ct-send" type="submit">SEND MESSAGE <i class="bi bi-arrow-right"></i></button>
      </form>
    </div>

  </div>
</section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>