<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'Add Employee';
$currentPage = 'employees';
$basePath = '../';
$errors = [];
$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$positions = $pdo->query('SELECT * FROM positions ORDER BY department_id, title')->fetchAll();
$statuses = $pdo->query('SELECT * FROM employee_statuses ORDER BY name')->fetchAll();
$employee = ['employee_no'=>'','first_name'=>'','last_name'=>'','gender'=>'','birthdate'=>'','email'=>'','phone'=>'','hire_date'=>'','department_id'=>'','position_id'=>'','status_id'=>'','address'=>'','monthly_salary'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee = array_map('trim', $_POST);
    foreach (['employee_no','first_name','last_name','gender','birthdate','email','hire_date','department_id','position_id','status_id'] as $field) {
        if (($employee[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
    if (!empty($employee['email']) && !filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email format is invalid.';
    if (!empty($employee['department_id']) && !empty($employee['position_id'])) {
        $positionDepartment = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE id = ? AND department_id = ?');
        $positionDepartment->execute([$employee['position_id'], $employee['department_id']]);
        if ($positionDepartment->fetchColumn() == 0) $errors[] = 'Selected position does not belong to the selected department.';
    }
    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE employee_no = ?');
    $duplicate->execute([$employee['employee_no'] ?? '']);
    if ($duplicate->fetchColumn() > 0) $errors[] = 'Employee ID already exists.';
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO employees (employee_no, first_name, last_name, gender, birthdate, email, phone, hire_date, department_id, position_id, status_id, address, monthly_salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$employee['employee_no'], $employee['first_name'], $employee['last_name'], $employee['gender'], $employee['birthdate'], $employee['email'], $employee['phone'], $employee['hire_date'], $employee['department_id'], $employee['position_id'], $employee['status_id'], $employee['address'], $employee['monthly_salary'] ?: 0]);
        redirect_to('index.php?message=Employee added successfully');
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main><div class="container-fluid px-4"><h1 class="mt-4">Add Employee</h1><?php require __DIR__ . '/form.php'; ?></div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
