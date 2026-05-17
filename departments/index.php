<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'Departments & Positions';
$currentPage = 'departments';
$basePath = '../';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_department') {
        $name = trim($_POST['department_name'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT IGNORE INTO departments (name) VALUES (?)');
            $stmt->execute([$name]);
        }
    } elseif ($action === 'add_position') {
        $title = trim($_POST['position_title'] ?? '');
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        if ($title !== '' && $departmentId) {
            $stmt = $pdo->prepare('INSERT INTO positions (department_id, title) VALUES (?, ?)');
            $stmt->execute([$departmentId, $title]);
        }
    } elseif ($action === 'delete_department') {
        if (!can_delete_operations()) {
            $errors[] = 'You are not authorized to delete departments.';
        } else {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            if ($departmentId <= 0) {
                $errors[] = 'Invalid department.';
            }
            if (!$errors) {
                $pdo->beginTransaction();
                $positionIds = $pdo->prepare('SELECT id FROM positions WHERE department_id = ?');
                $positionIds->execute([$departmentId]);
                $ids = array_map('intval', array_column($positionIds->fetchAll(), 'id'));
                if ($ids) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $clearPositions = $pdo->prepare("UPDATE employees SET position_id = NULL WHERE position_id IN ($placeholders)");
                    $clearPositions->execute($ids);
                }
                $clearDepartment = $pdo->prepare('UPDATE employees SET department_id = NULL WHERE department_id = ?');
                $clearDepartment->execute([$departmentId]);
                $deletePositions = $pdo->prepare('DELETE FROM positions WHERE department_id = ?');
                $deletePositions->execute([$departmentId]);
                $deleteDepartment = $pdo->prepare('DELETE FROM departments WHERE id = ?');
                $deleteDepartment->execute([$departmentId]);
                $pdo->commit();
                redirect_to('index.php?message=Department deleted and related employee assignments cleared');
            }
        }
    } elseif ($action === 'delete_position') {
        if (!can_delete_operations()) {
            $errors[] = 'You are not authorized to delete positions.';
        } else {
            $positionId = (int) ($_POST['position_id'] ?? 0);
            if ($positionId <= 0) {
                $errors[] = 'Invalid position.';
            }
            if (!$errors) {
                $pdo->beginTransaction();
                $clearPosition = $pdo->prepare('UPDATE employees SET position_id = NULL WHERE position_id = ?');
                $clearPosition->execute([$positionId]);
                $deletePosition = $pdo->prepare('DELETE FROM positions WHERE id = ?');
                $deletePosition->execute([$positionId]);
                $pdo->commit();
                redirect_to('index.php?message=Position deleted and related employee assignments cleared');
            }
        }
    }
}

$departments = $pdo->query('SELECT departments.id, departments.name, COUNT(employees.id) AS employee_count FROM departments LEFT JOIN employees ON departments.id = employees.department_id GROUP BY departments.id, departments.name ORDER BY departments.name')->fetchAll();
$positions = $pdo->query('SELECT positions.*, departments.name AS department_name FROM positions INNER JOIN departments ON positions.department_id = departments.id ORDER BY departments.name, positions.title')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4">Departments & Positions</h1>
        <div class="row">
            <div class="col-lg-6">
                <form method="post" class="card mb-4">
                    <div class="card-header">Add Department</div>
                    <div class="card-body d-flex gap-2">
                        <input type="hidden" name="action" value="add_department">
                        <input class="form-control" name="department_name" placeholder="Department name" required>
                        <button class="btn btn-primary" type="submit">Add</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-6">
                <form method="post" class="card mb-4">
                    <div class="card-header">Add Position</div>
                    <div class="card-body row g-2">
                        <input type="hidden" name="action" value="add_position">
                        <div class="col-md-5"><select class="form-select" name="department_id" required><option value="">Department</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-5"><input class="form-control" name="position_title" placeholder="Position title" required></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Add</button></div>
                    </div>
                </form>
            </div>
        </div>
        <?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <div class="row">
            <div class="col-lg-6"><div class="card mb-4"><div class="card-header">Department Summary</div><div class="card-body table-responsive"><table class="table table-bordered"><thead><tr><th>Department</th><th>Employee Count</th><?php if (can_delete_operations()): ?><th>Actions</th><?php endif; ?></tr></thead><tbody><?php foreach ($departments as $department): ?><tr><td><?= e($department['name']) ?></td><td><?= e($department['employee_count']) ?></td><?php if (can_delete_operations()): ?><td><form method="post" class="d-inline"><input type="hidden" name="action" value="delete_department"><input type="hidden" name="department_id" value="<?= $department['id'] ?>"><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this department? Related employee department and position assignments will be cleared.')"><i class="fas fa-trash"></i><span>Delete</span></button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></div></div>
            <div class="col-lg-6"><div class="card mb-4"><div class="card-header">Positions</div><div class="card-body table-responsive"><table class="table table-bordered"><thead><tr><th>Department</th><th>Position</th><?php if (can_delete_operations()): ?><th>Actions</th><?php endif; ?></tr></thead><tbody><?php foreach ($positions as $position): ?><tr><td><?= e($position['department_name']) ?></td><td><?= e($position['title']) ?></td><?php if (can_delete_operations()): ?><td><form method="post" class="d-inline"><input type="hidden" name="action" value="delete_position"><input type="hidden" name="position_id" value="<?= $position['id'] ?>"><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this position? Related employee position assignments will be cleared.')"><i class="fas fa-trash"></i><span>Delete</span></button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></div></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
