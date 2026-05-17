<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query('SELECT departments.name AS department, COUNT(employees.id) AS employee_count FROM departments LEFT JOIN employees ON departments.id = employees.department_id GROUP BY departments.id, departments.name ORDER BY departments.name');
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
?>
