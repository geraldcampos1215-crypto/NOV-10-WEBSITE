<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('staff', 'admin'); $layout = 'dash'; $pageTitle = 'Reports';
$cid = scope_company(); $cs = scope_sql($cid);
$byStatus = array_column(rows("SELECT a.status, COUNT(*) n FROM applications a JOIN jobs j ON j.id=a.job_id WHERE 1=1 $cs GROUP BY a.status"), 'n', 'status');
$byJob = rows("SELECT j.title, COUNT(a.id) n FROM jobs j LEFT JOIN applications a ON a.job_id=j.id WHERE 1=1 $cs GROUP BY j.id ORDER BY n DESC LIMIT 8");
$byMonth = rows("SELECT DATE_FORMAT(a.created_at,'%b %Y') m, COUNT(*) n FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $cs GROUP BY DATE_FORMAT(a.created_at,'%Y-%m'), m ORDER BY DATE_FORMAT(a.created_at,'%Y-%m')");
$byCat = rows("SELECT COALESCE(cat.name,'Uncategorized') AS cname, COUNT(*) n FROM jobs j LEFT JOIN job_categories cat ON cat.id=j.category_id WHERE 1=1 $cs GROUP BY cname ORDER BY n DESC");
$sv = array_map(fn($s) => (int)($byStatus[$s] ?? 0), STATUSES);
$hireRate = array_sum($sv) ? round(($byStatus['Hired'] ?? 0) / array_sum($sv) * 100, 1) : 0;
chart_script('const C=["#9CA3AF","#2563EB","#10B981","#F59E0B","#EF4444","#8B5CF6"];
new Chart(document.getElementById("c1"),{type:"doughnut",data:{labels:' . json_encode(STATUSES) . ',datasets:[{data:' . json_encode($sv) . ',backgroundColor:C}]}});
new Chart(document.getElementById("c2"),{type:"bar",data:{labels:' . json_encode(array_column($byJob, 'title')) . ',datasets:[{label:"Applications",data:' . json_encode(array_map('intval', array_column($byJob, 'n'))) . ',backgroundColor:"#2563EB"}]},options:{indexAxis:"y",plugins:{legend:{display:false}}}});
new Chart(document.getElementById("c3"),{type:"line",data:{labels:' . json_encode(array_column($byMonth, 'm')) . ',datasets:[{label:"Applications",data:' . json_encode(array_map('intval', array_column($byMonth, 'n'))) . ',borderColor:"#2563EB",tension:.3,fill:false}]}});');
include ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3 mb-3"><div class="col-6 col-lg-3"><div class="card stat-card"><div><div class="n"><?= array_sum($sv) ?></div><div class="small text-muted">Total applications</div></div></div></div>
<div class="col-6 col-lg-3"><div class="card stat-card"><div><div class="n"><?= $byStatus['Hired'] ?? 0 ?></div><div class="small text-muted">Hired</div></div></div></div>
<div class="col-6 col-lg-3"><div class="card stat-card"><div><div class="n"><?= $hireRate ?>%</div><div class="small text-muted">Hire rate</div></div></div></div>
<div class="col-6 col-lg-3"><div class="card stat-card"><div><div class="n"><?= count($byJob) ?></div><div class="small text-muted">Jobs with data</div></div></div></div></div>
<div class="row g-4"><div class="col-md-5"><div class="card p-3"><h6 class="fw-bold">Applications by status</h6><canvas id="c1"></canvas></div></div>
<div class="col-md-7"><div class="card p-3"><h6 class="fw-bold">Applications per month</h6><canvas id="c3"></canvas></div></div>
<div class="col-md-7"><div class="card p-3"><h6 class="fw-bold">Top jobs by applications</h6><canvas id="c2"></canvas></div></div>
<div class="col-md-5"><div class="card p-3"><h6 class="fw-bold">Jobs by category</h6><?php foreach ($byCat as $c) echo '<div class="d-flex justify-content-between border-bottom py-1"><span>' . e($c['cname']) . '</span><b>' . $c['n'] . '</b></div>'; ?></div></div></div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
