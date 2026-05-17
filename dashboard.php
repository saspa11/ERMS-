<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$basePath = '';

if (is_employee()) {
    $employeeId = current_employee_id();
    $stmt = $pdo->prepare(employee_join_sql() . ' WHERE employees.id = ?');
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/sidebar.php';
    ?>
    <main>
        <div class="container-fluid px-4">
            <div class="page-heading">
                <div>
                    <p class="eyebrow mb-1">Employee Portal</p>
                    <h1>Employee Dashboard</h1>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12"><div class="card mb-4"><div class="card-header">My Information</div><div class="card-body">
                    <?php if ($employee): ?>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Employee ID</dt><dd class="col-sm-8"><?= e($employee['employee_no']) ?></dd>
                            <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= e(full_name($employee)) ?></dd>
                            <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?= e($employee['department_name']) ?></dd>
                            <dt class="col-sm-4">Position</dt><dd class="col-sm-8"><?= e($employee['position_title']) ?></dd>
                            <dt class="col-sm-4">Monthly Salary</dt><dd class="col-sm-8"><?= e(money_format_php($employee['monthly_salary'])) ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted mb-0">No employee profile is linked to this account.</p>
                    <?php endif; ?>
                </div></div></div>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}

$totalEmployees = (int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$pendingUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'")->fetchColumn();
$activeEmployees = (int) $pdo->query("SELECT COUNT(*) FROM employees INNER JOIN employee_statuses ON employees.status_id = employee_statuses.id WHERE employee_statuses.name = 'Active'")->fetchColumn();
$departmentCount = (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn();
$recentStmt = $pdo->query(employee_join_sql() . ' ORDER BY employees.created_at DESC LIMIT 5');
$recentEmployees = $recentStmt->fetchAll();
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <div class="page-heading">
            <div>
                <p class="eyebrow mb-1">Overview</p>
                <h1>Dashboard</h1>
                <p class="page-subtitle">Employee Information Management</p>
            </div>
        </div>
        <div class="row g-3 stat-grid">
            <div class="col-xl-3 col-md-6"><div class="stat-card stat-card-blue"><div class="stat-icon"><i class="fas fa-users"></i></div><div><span>Total Employees</span><h2><?= $totalEmployees ?></h2></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card stat-card-green"><div class="stat-icon"><i class="fas fa-user-check"></i></div><div><span>Active Employees</span><h2><?= $activeEmployees ?></h2></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card stat-card-amber"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><div><span>Pending Registrations</span><h2><?= $pendingUsers ?></h2></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card stat-card-cyan"><div class="stat-icon"><i class="fas fa-building"></i></div><div><span>Departments</span><h2><?= $departmentCount ?></h2></div></div></div>
        </div>
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-users me-1"></i> Recent Employee Records</div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Position</th><th>Status</th><th>Salary</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentEmployees as $employee): ?>
                            <tr>
                                <td data-label="Employee ID"><?= e($employee['employee_no']) ?></td>
                                <td data-label="Name"><?= e(full_name($employee)) ?></td>
                                <td data-label="Department"><?= e($employee['department_name']) ?></td>
                                <td data-label="Position"><?= e($employee['position_title']) ?></td>
                                <td data-label="Status"><span class="status-pill"><?= e($employee['status_name']) ?></span></td>
                                <td data-label="Salary"><?= e(money_format_php($employee['monthly_salary'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
