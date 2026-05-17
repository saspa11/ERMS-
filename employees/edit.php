<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email_verification.php';
$pageTitle = 'Edit Employee';
$currentPage = 'employees';
$basePath = '../';
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) redirect_to('index.php?message=Employee not found');
$currentEmployeeEmail = $employee['email'];
$userStmt = $pdo->prepare('SELECT * FROM users WHERE employee_id = ? AND role = "employee" LIMIT 1');
$userStmt->execute([$id]);
$employeeUser = $userStmt->fetch();
$pendingVerification = $employeeUser ? get_pending_email_verification($pdo, (int) $employeeUser['id'], 'employee') : null;
$errors = [];
$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$positions = $pdo->query('SELECT * FROM positions ORDER BY department_id, title')->fetchAll();
$statuses = $pdo->query('SELECT * FROM employee_statuses ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_employee';
    if ($action === 'verify_email') {
        if (!$employeeUser) {
            redirect_to('edit.php?id=' . $id . '&message=' . urlencode('No linked employee user account was found for email verification.'));
        }
        [$ok, $resultMessage] = verify_email_update($pdo, (int) $employeeUser['id'], 'employee', trim($_POST['otp'] ?? ''), $id);
        redirect_to('edit.php?id=' . $id . '&message=' . urlencode($resultMessage));
    }

    $employee = array_merge($employee, array_map('trim', $_POST));
    foreach (['employee_no','first_name','last_name','gender','birthdate','email','hire_date','department_id','position_id','status_id'] as $field) {
        if (($employee[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
    if (!empty($employee['email']) && !filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email format is invalid.';
    if (!$employeeUser && strcasecmp($employee['email'], $currentEmployeeEmail) !== 0) $errors[] = 'A linked employee user account is required before changing email.';
    if (!empty($employee['department_id']) && !empty($employee['position_id'])) {
        $positionDepartment = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE id = ? AND department_id = ?');
        $positionDepartment->execute([$employee['position_id'], $employee['department_id']]);
        if ($positionDepartment->fetchColumn() == 0) $errors[] = 'Selected position does not belong to the selected department.';
    }
    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE employee_no = ? AND id <> ?');
    $duplicate->execute([$employee['employee_no'] ?? '', $id]);
    if ($duplicate->fetchColumn() > 0) $errors[] = 'Employee ID already exists.';
    if ($employeeUser && strcasecmp($employee['email'], $employeeUser['email']) !== 0 && !email_is_available($pdo, $employee['email'], (int) $employeeUser['id'], $id)) $errors[] = 'Email address is already in use.';
    if (!$errors) {
        $emailChanged = $employeeUser && strcasecmp($employee['email'], $employeeUser['email']) !== 0;
        $storedEmail = $emailChanged ? $employeeUser['email'] : $employee['email'];
        $update = $pdo->prepare('UPDATE employees SET employee_no=?, first_name=?, last_name=?, gender=?, birthdate=?, email=?, phone=?, hire_date=?, department_id=?, position_id=?, status_id=?, address=?, monthly_salary=? WHERE id=?');
        $update->execute([$employee['employee_no'], $employee['first_name'], $employee['last_name'], $employee['gender'], $employee['birthdate'], $storedEmail, $employee['phone'], $employee['hire_date'], $employee['department_id'], $employee['position_id'], $employee['status_id'], $employee['address'], $employee['monthly_salary'] ?: 0, $id]);
        if ($emailChanged) {
            if (!start_email_update_verification($pdo, (int) $employeeUser['id'], 'employee', $employee['email'], full_name($employee))) {
                redirect_to('edit.php?id=' . $id . '&message=' . urlencode('Employee saved, but verification email could not be sent. Check SMTP settings.'));
            }
            redirect_to('edit.php?id=' . $id . '&message=' . urlencode('Employee saved. Enter the 6-digit OTP sent to the new email to complete the email change.'));
        }
        redirect_to('index.php?message=Employee updated successfully');
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main><div class="container-fluid px-4"><h1 class="mt-4">Edit Employee</h1><?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?><?php require __DIR__ . '/form.php'; ?><?php if ($pendingVerification): ?><form method="post" class="card mb-4"><div class="card-header">Verify New Email</div><div class="card-body row g-3"><input type="hidden" name="action" value="verify_email"><div class="col-12"><p class="text-muted mb-0">Enter the 6-digit OTP sent to <?= e($pendingVerification['new_email']) ?>. This code expires at <?= e($pendingVerification['expires_at']) ?>.</p></div><div class="col-md-4"><label class="form-label">Verification Code</label><input class="form-control" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></div></div><div class="card-footer form-actions"><button class="btn btn-primary btn-action" type="submit"><i class="fas fa-shield-check"></i><span>Verify Email</span></button></div></form><?php endif; ?></div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
