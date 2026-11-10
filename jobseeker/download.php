<?php
/* Permission-checked resume download:  download.php?app=ID  (staff/admin)  or  download.php?me=1 (own profile resume) */
require_once __DIR__ . '/includes/auth.php';
$u = require_role();
$file = null;
if (isset($_GET['app']) && in_array($u['role'], ['staff', 'admin'])) {
    $a = row('SELECT a.resume, j.company_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=?', [(int)$_GET['app']]);
    if ($a && ($u['role'] === 'admin' || $a['company_id'] == staff_company())) { $file = $a['resume']; log_activity('Resume downloaded', 'application #' . (int)$_GET['app']); }
} elseif (isset($_GET['me'])) {
    $file = val('SELECT resume FROM user_profiles WHERE user_id=?', [$u['id']]);
}
$path = $file ? ROOT_PATH . '/assets/uploads/resumes/' . basename($file) : null;
if (!$path || !is_file($path)) { http_response_code(404); exit('Resume not found.'); }
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
header('Content-Disposition: attachment; filename="resume.' . $ext . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
