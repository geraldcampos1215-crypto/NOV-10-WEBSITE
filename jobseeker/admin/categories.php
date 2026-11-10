<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin'); $layout = 'dash'; $pageTitle = 'Categories';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
    if ($act === 'save') {
        if ($name === '') flash('danger', 'Category name is required.');
        elseif (val('SELECT id FROM job_categories WHERE name=? AND id<>?', [$name, $id])) flash('danger', 'That category already exists.');
        else { if ($id) q('UPDATE job_categories SET name=? WHERE id=?', [$name, $id]); else q('INSERT INTO job_categories (name) VALUES (?)', [$name]); log_activity('Category saved', $name); flash('success', 'Category saved.'); }
    } elseif ($act === 'delete') { q('DELETE FROM job_categories WHERE id=?', [$id]); flash('success', 'Category deleted.'); }
    redirect('admin/categories.php');
}
$edit = isset($_GET['edit']) ? row('SELECT * FROM job_categories WHERE id=?', [(int)$_GET['edit']]) : null;
$list = rows('SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.category_id=c.id) AS jobs FROM job_categories c ORDER BY c.name');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-4"><div class="card p-3"><h6 class="fw-bold"><?= $edit ? 'Edit category' : 'Add category' ?></h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <input name="name" class="form-control mb-2" placeholder="Category name" value="<?= e($edit['name'] ?? '') ?>" required><button class="btn btn-primary w-100"><?= $edit ? 'Save' : 'Add' ?></button></form></div></div>
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Jobs</th><th></th></tr></thead><tbody>
<?php foreach ($list as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= $c['jobs'] ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $c['id'] ?>"><i class="bi bi-pencil"></i></a>
  <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Delete this category?"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
