<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Users Management';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $t = $id ? row('SELECT * FROM users WHERE id=?', [$id]) : null;
    if ($act === 'create') {
        [$nid, $e] = create_user($_POST['full_name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', $_POST['role'] ?? 'seeker');
        if ($e) flash('danger', $e); else { log_activity('User created', $_POST['email']); flash('success', 'User created.'); }
    } elseif ($t && $t['id'] == $u['id']) flash('warning', 'You cannot modify your own account here.');
    elseif ($t) {
        if ($act === 'toggle') { $ns = $t['status'] === 'active' ? 'inactive' : 'active'; q('UPDATE users SET status=? WHERE id=?', [$ns, $id]); log_activity("Account $ns", $t['email']); flash('success', "Account is now $ns."); }
        elseif ($act === 'role' && in_array($_POST['role'], ['seeker', 'staff', 'admin'])) {
            q('UPDATE users SET role=? WHERE id=?', [$_POST['role'], $id]);
            if ($_POST['role'] === 'staff') q('INSERT IGNORE INTO staff (user_id) VALUES (?)', [$id]); else q('DELETE FROM staff WHERE user_id=?', [$id]);
            log_activity('Role changed', $t['email'] . ' → ' . $_POST['role']); flash('success', 'Role updated.');
        } elseif ($act === 'delete') { q('DELETE FROM users WHERE id=?', [$id]); log_activity('User deleted', $t['email']); flash('success', 'User deleted.'); }
    }
    redirect('admin/users.php');
}
$qv = trim($_GET['q'] ?? ''); $rl = $_GET['role'] ?? ''; $st = $_GET['status'] ?? '';
$w = '1=1'; $p = [];
if ($qv !== '') { $w .= ' AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)'; array_push($p, "%$qv%", "%$qv%", "%$qv%"); }
if (in_array($rl, ['seeker', 'staff', 'admin'])) { $w .= ' AND role=?'; $p[] = $rl; }
if (in_array($st, ['active', 'inactive'])) { $w .= ' AND status=?'; $p[] = $st; }
$per = 10; $page = page_no(); $total = (int)val("SELECT COUNT(*) FROM users WHERE $w", $p);
$users = rows("SELECT * FROM users WHERE $w ORDER BY created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
$hasFilter = ($qv !== '' || $rl !== '' || $st !== '');
include ROOT_PATH . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .us {
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
    .us h1, .us h2, .us h3, .us h4, .us h5, .us h6 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif; letter-spacing: -.01em;
    }

    /* ---------- Header banner (same look as the login page) ---------- */
    .us-hero {
        position: relative; overflow: hidden; border-radius: 22px; padding: 1.75rem 2.25rem; margin-bottom: 1.25rem;
        background: var(--sky); color: #fff; display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; flex-wrap: wrap;
    }
    .us-hero::before, .us-hero::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
    .us-hero::before { width: 480px; height: 480px; background: var(--deep); top: -290px; right: -70px; }
    .us-hero::after  { width: 320px; height: 320px; background: var(--deeper); opacity: .55; bottom: -220px; left: -80px; }
    .us-hero > * { position: relative; z-index: 1; }
    .us-hero h1 { font-size: clamp(1.7rem, 3.2vw, 2.3rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.08; margin: 0 0 .3rem; color: #fff; }
    .us-hero p { margin: 0; color: rgba(255,255,255,.9); }
    .us-add {
        display: inline-flex; align-items: center; gap: .5rem; background: #fff; color: var(--brand); border: 0; border-radius: 12px;
        padding: .75rem 1.3rem; font-weight: 700; font-family: inherit; transition: background .2s, transform .15s;
    }
    .us-add:hover { background: var(--brand-tint); color: var(--brand-dark); }
    .us-add:active { transform: scale(.97); }

    /* ---------- Add user form ---------- */
    .us-new { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.25rem; margin-bottom: 1.25rem; }
    .us-new h6 { font-size: 1rem; font-weight: 700; margin: 0 0 .9rem; }

    /* ---------- Inputs (same as login) ---------- */
    .us .form-control, .us .form-select {
        border: 1px solid var(--line); border-radius: 12px; padding: .7rem .95rem; font-size: .95rem; color: var(--ink);
        font-family: inherit; transition: border-color .2s, box-shadow .2s;
    }
    .us .form-control:focus, .us .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(31,95,224,.18); }
    .us .form-control::placeholder { color: #8a97ad; }

    .us-create {
        width: 100%; background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: .7rem;
        font-weight: 700; letter-spacing: .02em; font-family: inherit; transition: background .2s;
    }
    .us-create:hover { background: var(--brand-dark); }

    /* ---------- Toolbar ---------- */
    .us-bar {
        background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: .6rem; margin-bottom: 1.25rem;
        display: flex; gap: .5rem; align-items: stretch; flex-wrap: wrap;
    }
    .us-field {
        flex: 1 1 220px; display: flex; align-items: center; gap: .6rem; padding: 0 .9rem; border-radius: 12px; min-width: 0;
        background: #fff; border: 2px solid #aebfdc; transition: border-color .2s, box-shadow .2s;
    }
    .us-field:hover { border-color: #7f9bd0; }
    .us-field:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(31,95,224,.18); }
    .us-field i { color: var(--brand); }
    .us-field input { border: 0; outline: 0; width: 100%; padding: .75rem 0; font-size: .95rem; background: transparent; color: var(--ink); font-family: inherit; }
    .us-field input::placeholder { color: #8a97ad; }
    .us-bar .form-select { flex: 0 1 160px; border: 2px solid #aebfdc; background-color: #fff; }
    .us-bar .form-select:hover { border-color: #7f9bd0; }
    .us-bar .form-select:focus { border-color: var(--brand); }
    .us-go {
        background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: 0 1.4rem; font-weight: 700;
        font-family: inherit; letter-spacing: .02em; display: inline-flex; align-items: center; gap: .45rem; transition: background .2s;
    }
    .us-go:hover { background: var(--brand-dark); }
    .us-reset { align-self: center; color: var(--ink-soft); font-size: .85rem; font-weight: 600; text-decoration: none; padding: 0 .5rem; }
    .us-reset:hover { color: var(--brand); text-decoration: underline; }

    /* ---------- Table card ---------- */
    .us-count { color: var(--ink-soft); font-size: .92rem; margin-bottom: .75rem; }
    .us-count b { color: var(--ink); font-size: 1.05rem; }
    .us-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
    .us-table { width: 100%; margin: 0; border-collapse: collapse; }
    .us-table th {
        background: var(--bg); color: var(--ink-soft); font-size: .76rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; padding: .85rem 1.1rem; border-bottom: 1px solid var(--line); white-space: nowrap;
    }
    .us-table td { padding: 1rem 1.1rem; border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .92rem; }
    .us-table tbody tr:last-child td { border-bottom: 0; }
    .us-table tbody tr { transition: background-color .2s; }
    .us-table tbody tr:hover { background: #f8faff; }

    .us-user { display: flex; align-items: center; gap: .85rem; min-width: 220px; }
    .us-av {
        width: 42px; height: 42px; border-radius: 50%; background: var(--brand-tint); color: var(--brand);
        display: grid; place-items: center; font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; flex: none;
    }
    .us-user .nm { font-weight: 700; }
    .us-user .em { color: var(--ink-soft); font-size: .82rem; word-break: break-all; }
    .us-you { background: var(--brand); color: #fff; font-size: .68rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; margin-left: .35rem; vertical-align: middle; }

    /* Role select styled as a pill */
    .us-role {
        border: 1px solid transparent; border-radius: 999px; padding: .35rem 1.9rem .35rem .85rem; font-size: .85rem; font-weight: 600;
        font-family: inherit; cursor: pointer; width: auto;
    }
    .us-role.admin  { background-color: var(--brand-dark); color: #fff; }
    .us-role.staff  { background-color: var(--brand-tint); color: var(--brand-dark); }
    .us-role.seeker { background-color: #eef1f6; color: #3b4a66; }
    .us-role:disabled { opacity: .85; cursor: not-allowed; }
    .us-role.admin { --bs-form-select-bg-img: none; }

    .us-status { display: inline-flex; align-items: center; gap: .4rem; font-weight: 600; font-size: .85rem; padding: .25rem .7rem; border-radius: 999px; }
    .us-status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .us-status.active { background: #e3f6ec; color: #13804a; }
    .us-status.inactive { background: #eef1f6; color: #66738c; }

    .us-muted { color: var(--ink-soft); font-size: .88rem; white-space: nowrap; }

    .us-act { display: inline-flex; gap: .4rem; }
    .us-btn {
        display: inline-flex; align-items: center; gap: .35rem; border-radius: 10px; padding: .4rem .8rem; font-size: .84rem; font-weight: 600;
        font-family: inherit; background: #fff; border: 1px solid #cbd5e3; color: var(--ink); transition: background .2s, border-color .2s, color .2s;
    }
    .us-btn:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-tint); }
    .us-btn.danger { color: #c62f3d; border-color: #f0c4c9; padding: .4rem .6rem; }
    .us-btn.danger:hover { background: #c62f3d; border-color: #c62f3d; color: #fff; }

    .us-empty { text-align: center; padding: 3.5rem 1rem; color: var(--ink-soft); }
    .us-empty i { font-size: 2.2rem; color: var(--brand); display: block; margin-bottom: .5rem; }
    .us-empty h5 { color: var(--ink); font-weight: 700; margin-bottom: .25rem; }

    @media (max-width: 767.98px) {
        .us-hero { padding: 1.4rem; }
        .us-bar .form-select { flex: 1 1 140px; }
        .us-go { padding: .75rem 1.4rem; justify-content: center; flex: 1 1 100%; }
    }
    @media (prefers-reduced-motion: reduce) { .us *, .us *::after { transition: none !important; } }
</style>

<div class="us">

    <!-- Header -->
    <div class="us-hero">
        <div>
            <h1>Users.</h1>
            <p>Manage accounts, roles and access.</p>
        </div>
        <button class="us-add" type="button" data-bs-toggle="collapse" data-bs-target="#newUser"><i class="bi bi-person-plus"></i> Add user</button>
    </div>

    <!-- Add user -->
    <div class="collapse" id="newUser">
        <form method="post" class="us-new"><?= csrf_field() ?><input type="hidden" name="action" value="create">
            <h6>Create a new account</h6>
            <div class="row g-2">
                <div class="col-md-3"><input name="full_name" class="form-control" placeholder="Full name" required></div>
                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                <div class="col-md-2"><select name="role" class="form-select"><option>seeker</option><option>staff</option><option>admin</option></select></div>
                <div class="col-md-2"><button class="us-create" type="submit">CREATE</button></div>
            </div>
        </form>
    </div>

    <!-- Search + filters -->
    <form class="us-bar" method="get">
        <label class="us-field">
            <i class="bi bi-search"></i>
            <input type="search" name="q" placeholder="Search name, email or username" value="<?= e($qv) ?>" aria-label="Search users">
        </label>
        <select name="role" class="form-select" aria-label="Role">
            <option value="">All roles</option>
            <?php foreach (['seeker', 'staff', 'admin'] as $r) echo '<option ' . ($rl === $r ? 'selected' : '') . '>' . $r . '</option>'; ?>
        </select>
        <select name="status" class="form-select" aria-label="Status">
            <option value="">Any status</option>
            <option <?= $st === 'active' ? 'selected' : '' ?>>active</option>
            <option <?= $st === 'inactive' ? 'selected' : '' ?>>inactive</option>
        </select>
        <button class="us-go" type="submit"><i class="bi bi-funnel"></i> FILTER</button>
        <?php if ($hasFilter): ?><a class="us-reset" href="users.php">Clear</a><?php endif; ?>
    </form>

    <div class="us-count"><b><?= $total ?></b> <?= $total === 1 ? 'user' : 'users' ?> found</div>

    <!-- Table -->
    <div class="us-card">
        <div class="table-responsive">
            <table class="us-table">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Last login</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $r): $self = $r['id'] == $u['id']; ?>
                    <tr>
                        <td>
                            <div class="us-user">
                                <div class="us-av"><?= e(mb_strtoupper(mb_substr(trim($r['full_name']) ?: '?', 0, 1))) ?></div>
                                <div>
                                    <div class="nm"><?= e($r['full_name']) ?><?php if ($self): ?><span class="us-you">You</span><?php endif; ?></div>
                                    <div class="em"><?= e($r['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <select name="role" class="form-select form-select-sm us-role <?= e($r['role']) ?>" <?= $self ? 'disabled' : '' ?> onchange="this.form.submit()" aria-label="Role">
                                    <?php foreach (['seeker', 'staff', 'admin'] as $o) echo '<option ' . ($r['role'] === $o ? 'selected' : '') . '>' . $o . '</option>'; ?>
                                </select>
                            </form>
                        </td>
                        <td><span class="us-status <?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td class="us-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        <td class="us-muted"><?= $r['last_login'] ? time_ago($r['last_login']) : 'Never' ?></td>
                        <td class="text-end">
                            <?php if (!$self): ?>
                                <form method="post" class="us-act"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button name="action" value="toggle" class="us-btn"><i class="bi bi-<?= $r['status'] === 'active' ? 'slash-circle' : 'check-circle' ?>"></i> <?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                    <button name="action" value="delete" class="us-btn danger" data-confirm="Delete this user permanently?" aria-label="Delete user"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$users): ?>
            <div class="us-empty"><i class="bi bi-people"></i><h5>No users found</h5><div>Try a different search or clear the filters.</div></div>
        <?php endif; ?>
    </div>

    <div class="mt-3"><?= pager($total, $per, $page) ?></div>

</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>