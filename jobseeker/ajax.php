<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');
$u = user();
if (!$u) { echo json_encode(['ok' => false]); exit; }
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'toggle_save' && $u['role'] === 'seeker') {
    $jid = (int)($_POST['job_id'] ?? 0);
    if (!val('SELECT id FROM jobs WHERE id=?', [$jid])) { echo json_encode(['ok' => false]); exit; }
    if (val('SELECT id FROM saved_jobs WHERE user_id=? AND job_id=?', [$u['id'], $jid])) {
        q('DELETE FROM saved_jobs WHERE user_id=? AND job_id=?', [$u['id'], $jid]); $saved = false;
    } else { q('INSERT INTO saved_jobs (user_id, job_id) VALUES (?,?)', [$u['id'], $jid]); $saved = true; }
    echo json_encode(['ok' => true, 'saved' => $saved]); exit;
}
if ($action === 'poll') {
    $apps = [];
    if ($u['role'] === 'seeker') foreach (rows('SELECT id, status FROM applications WHERE user_id=?', [$u['id']]) as $a) $apps[$a['id']] = $a['status'];
    echo json_encode(['ok' => true, 'unread' => unread_count(), 'apps' => $apps]); exit;
}
echo json_encode(['ok' => false]);
