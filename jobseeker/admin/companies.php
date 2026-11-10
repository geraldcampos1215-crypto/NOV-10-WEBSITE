<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Companies';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') flash('danger', 'Company name is required.');
        else {
            $v = [$name, trim($_POST['industry']), trim($_POST['location']), trim($_POST['website']), trim($_POST['description'])];
            if ($id) { q('UPDATE companies SET name=?, industry=?, location=?, website=?, description=? WHERE id=?', [...$v, $id]); flash('success', 'Company updated.'); }
            else { q('INSERT INTO companies (name, industry, location, website, description) VALUES (?,?,?,?,?)', $v); flash('success', 'Company added.'); }
            log_activity('Company saved', $name);
        }
    } elseif ($act === 'delete') { q('DELETE FROM companies WHERE id=?', [$id]); log_activity('Company deleted', '#' . $id); flash('success', 'Company deleted (its jobs were removed too).'); }
    redirect('admin/companies.php');
}
$edit = isset($_GET['edit']) ? row('SELECT * FROM companies WHERE id=?', [(int)$_GET['edit']]) : null;
$list = rows('SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id) AS jobs FROM companies c ORDER BY c.name');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-4"><div class="card p-3"><h6 class="fw-bold"><?= $edit ? 'Edit company' : 'Add company' ?></h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <input name="name" class="form-control mb-2" placeholder="Company name" value="<?= e($edit['name'] ?? '') ?>" required><input name="industry" class="form-control mb-2" placeholder="Industry" value="<?= e($edit['industry'] ?? '') ?>"><input name="location" class="form-control mb-2" placeholder="Location" value="<?= e($edit['location'] ?? '') ?>">
  <input name="website" class="form-control mb-2" placeholder="Website" value="<?= e($edit['website'] ?? '') ?>"><textarea name="description" rows="3" class="form-control mb-2" placeholder="Description"><?= e($edit['description'] ?? '') ?></textarea>
  <button class="btn btn-primary w-100"><?= $edit ? 'Save changes' : 'Add company' ?></button><?php if ($edit) echo '<a href="companies.php" class="btn btn-light w-100 mt-2">Cancel</a>'; ?></form></div></div>
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Company</th><th>Industry</th><th>Location</th><th>Jobs</th><th></th></tr></thead><tbody>
<?php foreach ($list as $c): ?><tr><td class="fw-semibold"><?= e($c['name']) ?></td><td><?= e($c['industry']) ?></td><td><?= e($c['location']) ?></td><td><?= $c['jobs'] ?></td>
  <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $c['id'] ?>"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Delete this company and all its jobs?"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
