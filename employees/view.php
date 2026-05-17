<?php
require_once __DIR__ . '/../includes/auth.php';
require_login_from_subdir();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'View Employee';
$currentPage = 'employees';
$basePath = '../';
$id = (int) ($_GET['id'] ?? 0);
if (is_employee() && current_employee_id() !== $id) {
    redirect_to('../dashboard.php');
}
$stmt = $pdo->prepare(employee_join_sql() . ' WHERE employees.id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) redirect_to('index.php?message=Employee not found');
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4">Employee Profile</h1>
        <div class="card mb-4">
            <div class="card-header"><?= e(full_name($employee)) ?></div>
            <div class="card-body">
                <h5>Basic Personal Details</h5>
                <dl class="row">
                    <dt class="col-sm-3">Employee ID</dt><dd class="col-sm-9"><?= e($employee['employee_no']) ?></dd>
                    <dt class="col-sm-3">Full Name</dt><dd class="col-sm-9"><?= e(full_name($employee)) ?></dd>
                    <dt class="col-sm-3">Gender</dt><dd class="col-sm-9"><?= e($employee['gender']) ?></dd>
                    <dt class="col-sm-3">Birthdate</dt><dd class="col-sm-9"><?= e($employee['birthdate']) ?></dd>
                    <dt class="col-sm-3">Age</dt><dd class="col-sm-9"><?= e(calculate_age($employee['birthdate'])) ?></dd>
                    <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($employee['address']) ?></dd>
                    <dt class="col-sm-3">Contact Number</dt><dd class="col-sm-9"><?= e($employee['phone']) ?></dd>
                    <dt class="col-sm-3">Email Address</dt><dd class="col-sm-9"><?= e($employee['email']) ?></dd>
                </dl>
                <h5>Employment Information</h5>
                <dl class="row">
                    <dt class="col-sm-3">Department</dt><dd class="col-sm-9"><?= e($employee['department_name']) ?></dd>
                    <dt class="col-sm-3">Position</dt><dd class="col-sm-9"><?= e($employee['position_title']) ?></dd>
                    <dt class="col-sm-3">Employment Status</dt><dd class="col-sm-9"><?= e($employee['status_name']) ?></dd>
                    <dt class="col-sm-3">Date Hired</dt><dd class="col-sm-9"><?= e($employee['hire_date']) ?></dd>
                    <dt class="col-sm-3">Monthly Salary</dt><dd class="col-sm-9"><?= e(money_format_php($employee['monthly_salary'])) ?></dd>
                </dl>
                <?php if (can_manage_employees()): ?><a class="btn btn-secondary" href="index.php">Back</a> <a class="btn btn-warning" href="edit.php?id=<?= $employee['id'] ?>">Edit</a><?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
