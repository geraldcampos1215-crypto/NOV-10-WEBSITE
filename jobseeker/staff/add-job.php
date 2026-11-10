<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash';
$cid = scope_company(); $cs = scope_sql($cid);
$id = (int)($_GET['id'] ?? 0);
$job = $id ? row("SELECT j.* FROM jobs j WHERE j.id=? $cs", [$id]) : null;
if ($id && !$job) { flash('danger', 'Job not found.'); redirect($u['role'] . '/jobs.php'); }
$pageTitle = $job ? 'Edit Job' : 'Post New Job';
$cats = rows('SELECT id, name FROM job_categories ORDER BY name');
$companies = $u['role'] === 'admin' ? rows('SELECT id, name FROM companies ORDER BY name') : [];
$err = [];
$d = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($job ?: ['status' => 'open', 'job_type' => 'Full Time']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($d['title'] ?? ''); $desc = trim($d['description'] ?? ''); $loc = trim($d['location'] ?? '');
    $min = $d['salary_min'] !== '' ? (int)$d['salary_min'] : null; $max = $d['salary_max'] !== '' ? (int)$d['salary_max'] : null;
    $company = $u['role'] === 'staff' ? $cid : (int)($d['company_id'] ?? 0);
    if ($title === '') $err['title'] = 'Title is required.';
    if ($desc === '') $err['description'] = 'Description is required.';
    if ($loc === '') $err['location'] = 'Location is required.';
    if (!in_array($d['job_type'], JOB_TYPES)) $err['job_type'] = 'Choose a job type.';
    if ($min !== null && $max !== null && $min > $max) $err['salary_max'] = 'Maximum salary must be higher than minimum.';
    if ($company <= 0 || !val('SELECT id FROM companies WHERE id=?', [$company])) $err['company_id'] = 'A company is required (ask the admin to assign you to one).';
    if (!in_array($d['status'], ['open', 'closed', 'draft'])) $d['status'] = 'draft';
    if (!$err) {
        $vals = [$company, (int)$d['category_id'] ?: null, $title, $desc, trim($d['requirements'] ?? ''), $loc, $d['job_type'], $min, $max, $d['status'], !empty($d['is_featured']) && $u['role'] === 'admin' ? 1 : ($job['is_featured'] ?? 0), $d['deadline'] ?: null];
        if ($job) { q('UPDATE jobs SET company_id=?, category_id=?, title=?, description=?, requirements=?, location=?, job_type=?, salary_min=?, salary_max=?, status=?, is_featured=?, deadline=? WHERE id=?', [...$vals, $id]); log_activity('Job updated', $title); flash('success', 'Job updated.'); }
        else { q('INSERT INTO jobs (company_id, category_id, title, description, requirements, location, job_type, salary_min, salary_max, status, is_featured, deadline, posted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$vals, $u['id']]); log_activity('Job posted', $title); flash('success', 'Job posted.'); }
        redirect($u['role'] . '/jobs.php');
    }
}
$in = fn($k, $l, $t = 'text', $cls = 'col-md-6') => '<div class="' . $cls . '"><label class="form-label">' . $l . '</label><input type="' . $t . '" name="' . $k . '" class="form-control ' . (isset($err[$k]) ? 'is-invalid' : '') . '" value="' . e($d[$k] ?? '') . '"><div class="invalid-feedback">' . e($err[$k] ?? '') . '</div></div>';
include ROOT_PATH . '/includes/header.php';
?>
<div class="card p-4"><form method="post" novalidate><?= csrf_field() ?><div class="row g-3">
  <?= $in('title', 'Job title', 'text', 'col-12') ?>
  <?php if ($companies): ?><div class="col-md-6"><label class="form-label">Company</label><select name="company_id" class="form-select <?= isset($err['company_id']) ? 'is-invalid' : '' ?>"><option value="">Select…</option><?php foreach ($companies as $c) echo '<option value="' . $c['id'] . '"' . (($d['company_id'] ?? '') == $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>'; ?></select><div class="invalid-feedback"><?= e($err['company_id'] ?? '') ?></div></div>
  <?php elseif (isset($err['company_id'])): ?><div class="col-12"><div class="alert alert-danger"><?= e($err['company_id']) ?></div></div><?php endif; ?>
  <div class="col-md-6"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">None</option><?php foreach ($cats as $c) echo '<option value="' . $c['id'] . '"' . (($d['category_id'] ?? '') == $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>'; ?></select></div>
  <?= $in('location', 'Location') ?>
  <div class="col-md-6"><label class="form-label">Job type</label><select name="job_type" class="form-select"><?php foreach (JOB_TYPES as $t) echo '<option' . (($d['job_type'] ?? '') === $t ? ' selected' : '') . '>' . $t . '</option>'; ?></select></div>
  <?= $in('salary_min', 'Minimum salary (₱)', 'number') . $in('salary_max', 'Maximum salary (₱)', 'number') . $in('deadline', 'Application deadline', 'date') ?>
  <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['open', 'draft', 'closed'] as $s) echo '<option' . (($d['status'] ?? '') === $s ? ' selected' : '') . '>' . $s . '</option>'; ?></select></div>
  <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="5" class="form-control <?= isset($err['description']) ? 'is-invalid' : '' ?>"><?= e($d['description'] ?? '') ?></textarea><div class="invalid-feedback"><?= e($err['description'] ?? '') ?></div></div>
  <div class="col-12"><label class="form-label">Requirements (one per line)</label><textarea name="requirements" rows="4" class="form-control"><?= e($d['requirements'] ?? '') ?></textarea></div>
  <?php if ($u['role'] === 'admin'): ?><div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_featured" value="1" id="ft" <?= !empty($d['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="ft">Featured job</label></div></div><?php endif; ?>
</div><div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><?= $job ? 'Save changes' : 'Post job' ?></button><a href="jobs.php" class="btn btn-light">Cancel</a></div></form></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
