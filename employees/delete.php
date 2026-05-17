<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin'], true);
require_once __DIR__ . '/../config/database.php';
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('DELETE FROM employees WHERE id = ?');
$stmt->execute([$id]);
header('Location: index.php?message=Employee deleted successfully');
exit;
?>
