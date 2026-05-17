<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$search = trim($_GET['search'] ?? '');
$sql = employee_join_sql() . ' WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (employees.employee_no LIKE ? OR employees.first_name LIKE ? OR employees.last_name LIKE ? OR employees.email LIKE ? OR departments.name LIKE ? OR positions.title LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term, $term];
}
$sql .= ' ORDER BY employees.last_name, employees.first_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$row) {
    unset($row['monthly_salary']);
}
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
