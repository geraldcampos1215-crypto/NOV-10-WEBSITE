<?php
require_once __DIR__ . '/functions.php';

function user() {
    static $u = null;
    if ($u === null && !empty($_SESSION['uid']))
        $u = row('SELECT u.*, p.profile_picture FROM users u LEFT JOIN user_profiles p ON p.user_id=u.id WHERE u.id=?', [$_SESSION['uid']]) ?: false;
    return $u ?: null;
}
function home_for($role) { return ['admin' => 'admin/dashboard.php', 'staff' => 'staff/dashboard.php', 'seeker' => 'seeker/dashboard.php'][$role] ?? 'index.php'; }
function require_role(...$roles) {
    $u = user();
    if (!$u || $u['status'] !== 'active') {
        session_unset(); flash('warning', 'Please log in to continue.'); redirect('login.php');
    }
    $_SESSION['role'] = $u['role'];
    if ($roles && !in_array($u['role'], $roles, true)) { flash('danger', 'You do not have permission to open that page.'); redirect(home_for($u['role'])); }
    return $u;
}
function staff_company() { return (int)val('SELECT company_id FROM staff WHERE user_id=?', [$_SESSION['uid']]); }
function unread_count() { return (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$_SESSION['uid'] ?? 0]); }

/* Company scope: staff only see their own company's data (-1 = no company assigned); admins see everything (0) */
function scope_company() { return ($_SESSION['role'] ?? '') === 'staff' ? (staff_company() ?: -1) : 0; }
function scope_sql($cid, $alias = 'j') { return $cid ? " AND $alias.company_id=" . (int)$cid : ''; }
