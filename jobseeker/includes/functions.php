<?php
require_once __DIR__ . '/../config/config.php';

/* ---------- basics ---------- */
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url($p = '') { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect($p) { header('Location: ' . (str_starts_with($p, 'http') ? $p : url($p))); exit; }
function flash($type, $msg) { $_SESSION['flash'][] = [$type, $msg]; }
function show_flash() {
    foreach ($_SESSION['flash'] ?? [] as [$t, $m])
        echo '<div class="alert alert-' . e($t) . ' alert-dismissible fade show">' . e($m) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['flash']);
}
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Invalid or expired form token. Go back, refresh and try again.');
    }
}
csrf_check();   // every POST request in the app is CSRF-protected

/* ---------- database helpers (prepared statements only) ---------- */
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s; }
function row($sql, $p = []) { return q($sql, $p)->fetch(); }
function rows($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function val($sql, $p = []) { return q($sql, $p)->fetchColumn(); }
function last_id() { global $pdo; return (int)$pdo->lastInsertId(); }

/* ---------- logging / notifications / mail ---------- */
function log_activity($action, $details = '', $uid = null) {
    q('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)',
      [$uid ?? ($_SESSION['uid'] ?? null), $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}
function setting($key, $default = '') { $v = val('SELECT setting_value FROM system_settings WHERE setting_key=?', [$key]); return $v === false ? $default : $v; }
function notify($uid, $title, $msg, $email = false) {
    q('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)', [$uid, $title, $msg]);
    if ($email && setting('email_notifications') === '1') {
        $to = val('SELECT email FROM users WHERE id=?', [$uid]);
        if ($to) @mail($to, $title, $msg, 'From: ' . MAIL_FROM);   // needs an SMTP server; silently ignored on plain XAMPP
    }
}

/* ---------- constants / formatting ---------- */
const STATUSES = ['Pending', 'Under Review', 'Shortlisted', 'Interview', 'Rejected', 'Hired'];
const JOB_TYPES = ['Full Time', 'Part Time', 'Contract', 'Internship'];
function status_badge($s, $id = null) {
    $c = strtolower(str_replace(' ', '-', $s));
    return '<span class="badge-status s-' . $c . '"' . ($id ? ' data-app-status="' . (int)$id . '"' : '') . '>' . e($s) . '</span>';
}
function money($n) { return '₱' . number_format((float)$n); }
function salary($j) { return $j['salary_min'] ? money($j['salary_min']) . ' - ' . money($j['salary_max']) : 'Negotiable'; }
function time_ago($d) {
    $t = time() - strtotime($d);
    if ($t < 60) return 'just now';
    if ($t < 3600) return floor($t / 60) . ' min ago';
    if ($t < 86400) return floor($t / 3600) . ' hours ago';
    if ($t < 2592000) return floor($t / 86400) . ' days ago';
    return date('M d, Y', strtotime($d));
}
function initials($n) { $p = preg_split('/\s+/', trim($n)); return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : '')); }
function avatar($u, $size = 40) {
    if (!empty($u['profile_picture']))
        return '<img class="avatar" style="width:' . $size . 'px;height:' . $size . 'px" src="' . url('assets/uploads/profiles/' . e($u['profile_picture'])) . '" alt="">';
    return '<span class="avatar avatar-i" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size / 2.6) . 'px">' . e(initials($u['full_name'])) . '</span>';
}
function company_logo($j, $size = 44) {
    $colors = ['#2563EB', '#10B981', '#F59E0B', '#8B5CF6', '#EF4444', '#0EA5E9'];
    return '<span class="co-logo" style="width:' . $size . 'px;height:' . $size . 'px;background:' . $colors[($j['company_id'] ?? 0) % 6] . '">' . e(strtoupper(substr($j['company_name'], 0, 1))) . '</span>';
}

/* ---------- pagination ---------- */
function pager($total, $per, $page) {
    $pages = max(1, (int)ceil($total / $per));
    if ($pages <= 1) return '';
    $qs = $_GET; unset($qs['ajax']);
    $h = '<nav><ul class="pagination justify-content-center flex-wrap">';
    for ($i = 1; $i <= $pages; $i++) { $qs['page'] = $i; $h .= '<li class="page-item' . ($i == $page ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($qs)) . '">' . $i . '</a></li>'; }
    return $h . '</ul></nav>';
}
function page_no() { return max(1, (int)($_GET['page'] ?? 1)); }

/* ---------- job card ---------- */
function saved_ids() {
    if (($_SESSION['role'] ?? '') !== 'seeker') return [];
    return array_column(rows('SELECT job_id FROM saved_jobs WHERE user_id=?', [$_SESSION['uid']]), 'job_id');
}
function job_card($j, $saved = []) {
    $isSaved = in_array($j['id'], $saved);
    ob_start(); ?>
    <div class="job-card">
      <?= company_logo($j) ?>
      <div class="flex-grow-1 min-w-0">
        <a href="<?= url('job-details.php?id=' . $j['id']) ?>" class="job-title"><?= e($j['title']) ?></a>
        <div class="text-muted small"><?= e($j['company_name']) ?></div>
        <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($j['location']) ?> &nbsp;<i class="bi bi-clock"></i> <?= e($j['job_type']) ?></div>
      </div>
      <div class="text-end">
        <div class="fw-semibold small text-primary mb-2"><?= salary($j) ?></div>
        <?php if (($_SESSION['role'] ?? '') === 'seeker'): ?>
          <button class="btn btn-sm btn-light btn-save" data-id="<?= $j['id'] ?>" title="Save job"><i class="bi <?= $isSaved ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i></button>
        <?php endif; ?>
        <a href="<?= url('job-details.php?id=' . $j['id']) ?>" class="btn btn-sm btn-primary">Apply Now</a>
      </div>
    </div>
    <?php return ob_get_clean();
}
const JOB_SELECT = 'SELECT j.*, c.name AS company_name, c.location AS company_location, c.website AS company_website, cat.name AS category_name FROM jobs j JOIN companies c ON c.id=j.company_id LEFT JOIN job_categories cat ON cat.id=j.category_id';

/* ---------- uploads ---------- */
function upload_file($key, $sub, $exts, $maxMb = 2) {
    if (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    $f = $_FILES[$key];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Upload failed. Please try again.'];
    if ($f['size'] > $maxMb * 1048576) return [null, "File is larger than {$maxMb}MB."];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mimes = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'pdf' => ['application/pdf'],
              'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']];
    if (!in_array($ext, $exts, true) || !isset($mimes[$ext])) return [null, 'Invalid file type. Allowed: ' . implode(', ', $exts) . '.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, $mimes[$ext], true)) return [null, 'File content does not match its extension.'];
    $dir = ROOT_PATH . '/assets/uploads/' . $sub;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], "$dir/$name");
    return [$name, null];
}

/* ---------- job expiration (runs at most every 10 minutes) ---------- */
if (($_SESSION['expire_check'] ?? 0) < time() - 600) {
    q("UPDATE jobs SET status='closed' WHERE status='open' AND deadline IS NOT NULL AND deadline < CURDATE()");
    $_SESSION['expire_check'] = time();
}

/* ---------- Chart.js helper ---------- */
function chart_script($js) { global $extraJs; $extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script><script>' . $js . '</script>'; }

/* ---------- profile completion (percent) ---------- */
function profile_completion($uid) {
    $p = row('SELECT * FROM user_profiles WHERE user_id=?', [$uid]) ?: [];
    $f = ['phone', 'location', 'headline', 'skills', 'bio', 'profile_picture', 'resume']; $n = 0;
    foreach ($f as $k) if (!empty($p[$k])) $n++;
    return (int)round($n / count($f) * 100);
}

/* ---------- admin helper: create a user (returns [id, error]) ---------- */
function create_user($name, $email, $pw, $role, $companyId = null, $position = '', $dept = '') {
    $email = strtolower(trim($email)); $name = trim($name);
    if (strlen($name) < 3) return [0, 'Enter a full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [0, 'Enter a valid email.'];
    if (val('SELECT id FROM users WHERE email=?', [$email])) return [0, 'That email is already registered.'];
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return [0, 'Password needs 8+ characters with letters and numbers.'];
    if (!in_array($role, ['seeker', 'staff', 'admin'])) return [0, 'Invalid role.'];
    $username = preg_replace('/[^a-z0-9]/', '', strtolower(strstr($email, '@', true))) ?: 'user';
    if (val('SELECT id FROM users WHERE username=?', [$username])) $username .= random_int(100, 999);
    q('INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)', [$name, $username, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
    $id = last_id();
    q('INSERT INTO user_profiles (user_id) VALUES (?)', [$id]);
    if ($role === 'staff') q('INSERT INTO staff (user_id, company_id, position, department) VALUES (?,?,?,?)', [$id, $companyId ?: null, $position, $dept]);
    return [$id, null];
}
