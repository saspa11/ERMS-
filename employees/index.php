<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'Employees';
$currentPage = 'employees';
$basePath = '../';
$search = trim($_GET['search'] ?? '');
$departmentId = $_GET['department_id'] ?? '';
$statusId = $_GET['status_id'] ?? '';
$sql = employee_join_sql() . ' WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (employees.employee_no LIKE ? OR employees.first_name LIKE ? OR employees.last_name LIKE ? OR employees.email LIKE ? OR employees.phone LIKE ? OR departments.name LIKE ? OR positions.title LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
}
if ($departmentId !== '') {
    $sql .= ' AND employees.department_id = ?';
    $params[] = $departmentId;
}
if ($statusId !== '') {
    $sql .= ' AND employees.status_id = ?';
    $params[] = $statusId;
}
$sql .= ' ORDER BY employees.last_name, employees.first_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$statuses = $pdo->query('SELECT * FROM employee_statuses ORDER BY name')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <div class="page-heading page-heading-actions">
            <div>
                <p class="eyebrow mb-1">Management</p>
                <h1>Employees</h1>
                <p class="page-subtitle">Search, update, and manage employee information.</p>
            </div>
            <a class="btn btn-primary btn-action" href="create.php"><i class="fas fa-user-plus"></i><span>Add Employee</span></a>
        </div>
        <?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?>
        <div class="card mb-4 filter-card">
            <div class="card-body">
                <form class="row g-3" method="get">
                    <div class="col-md-5"><label class="form-label"><i class="fas fa-magnifying-glass me-1"></i>Search</label><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Name, employee ID, email, department, position"></div>
                    <div class="col-md-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>" <?= (string)$departmentId === (string)$department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status_id"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= $status['id'] ?>" <?= (string)$statusId === (string)$status['id'] ? 'selected' : '' ?>><?= e($status['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100 btn-action" type="submit"><i class="fas fa-filter"></i><span>Filter</span></button></div>
                </form>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-table me-1"></i>Employee Records</div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Employee ID</th><th>Full Name</th><th>Gender</th><th>Age</th><th>Email</th><th>Department</th><th>Position</th><th>Status</th><th>Salary</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td data-label="Employee ID"><?= e($employee['employee_no']) ?></td>
                                <td data-label="Full Name"><strong><?= e(full_name($employee)) ?></strong></td>
                                <td data-label="Gender"><?= e($employee['gender']) ?></td>
                                <td data-label="Age"><?= e(calculate_age($employee['birthdate'])) ?></td>
                                <td data-label="Email"><?= e($employee['email']) ?></td>
                                <td data-label="Department"><?= e($employee['department_name']) ?></td>
                                <td data-label="Position"><?= e($employee['position_title']) ?></td>
                                <td data-label="Status"><span class="status-pill"><?= e($employee['status_name']) ?></span></td>
                                <td data-label="Salary"><?= e(money_format_php($employee['monthly_salary'])) ?></td>
                                <td data-label="Actions">
                                    <div class="table-actions">
                                        <a class="btn btn-sm btn-info text-white" href="view.php?id=<?= $employee['id'] ?>"><i class="fas fa-eye"></i><span>View</span></a>
                                        <a class="btn btn-sm btn-warning" href="edit.php?id=<?= $employee['id'] ?>"><i class="fas fa-pen"></i><span>Edit</span></a>
                                        <?php if (can_delete_employee()): ?><a class="btn btn-sm btn-danger" href="delete.php?id=<?= $employee['id'] ?>" onclick="return confirm('Delete this employee?')"><i class="fas fa-trash"></i><span>Delete</span></a><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$employees): ?><tr><td colspan="10" class="text-center text-muted">No employee records found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
