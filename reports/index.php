<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'Reports';
$currentPage = 'reports';
$basePath = '../';
$statusReports = $pdo->query('SELECT employee_statuses.name, COUNT(employees.id) AS employee_count FROM employee_statuses LEFT JOIN employees ON employee_statuses.id = employees.status_id GROUP BY employee_statuses.id, employee_statuses.name ORDER BY employee_statuses.name')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main><div class="container-fluid px-4"><h1 class="mt-4">Reports</h1><div class="row"><div class="col-lg-6"><div class="card mb-4"><div class="card-header">Employees by Status</div><div class="card-body"><?php foreach ($statusReports as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($row['name']) ?></span><strong><?= e($row['employee_count']) ?></strong></div><?php endforeach; ?></div></div></div><div class="col-lg-6"><div class="card mb-4"><div class="card-header">Employees by Department API Report</div><div class="card-body" id="apiReportDepartments">Loading API report...</div></div></div></div></div></main><script>fetch('../api/departments.php').then(r=>r.json()).then(data=>{document.getElementById('apiReportDepartments').innerHTML=data.map(item=>`<div class="d-flex justify-content-between border-bottom py-2"><span>${item.department}</span><strong>${item.employee_count}</strong></div>`).join('') || 'No data';}).catch(()=>{document.getElementById('apiReportDepartments').textContent='Unable to load API data.';});</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
