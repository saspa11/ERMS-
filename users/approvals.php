<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$pageTitle = 'Registration Approvals';
$currentPage = 'approvals';
$basePath = '../';
$message = '';

function request_wants_json(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function delete_manager_registration(PDO $pdo, int $userId): void
{
    $pdo->beginTransaction();
    try {
        $deleteVerifications = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND user_type = "manager"');
        $deleteVerifications->execute([$userId]);

        $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "manager"');
        $deleteUser->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && can_approve_role($user['role'])) {
        if ($action === 'approve') {
            $update = $pdo->prepare('UPDATE users SET approval_status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?');
            $update->execute([$_SESSION['user_id'], $userId]);
            $message = 'Registration approved.';
        } elseif ($action === 'reject') {
            if ($user['role'] === 'manager') {
                delete_manager_registration($pdo, $userId);
                $message = 'Manager registration rejected and all related records were permanently deleted.';
                if (request_wants_json()) {
                    respond_json(['ok' => true, 'message' => $message, 'deleted' => true]);
                }
            } else {
                $update = $pdo->prepare('UPDATE users SET approval_status = "rejected", approved_by = ?, approved_at = NOW() WHERE id = ?');
                $update->execute([$_SESSION['user_id'], $userId]);
                $message = 'Registration rejected.';
            }
        } elseif ($action === 'delete' && is_admin() && in_array($user['role'], ['manager', 'employee'], true)) {
            $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $delete->execute([$userId]);
            $message = 'Account deleted successfully.';
        }
    } elseif (request_wants_json()) {
        respond_json(['ok' => false, 'message' => 'Approval request was not found or you do not have permission to change it.'], 404);
    }

    if (request_wants_json()) {
        respond_json(['ok' => true, 'message' => $message ?: 'Action completed.']);
    }
}

$stmt = $pdo->query('SELECT users.*, employees.employee_no FROM users LEFT JOIN employees ON users.employee_id = employees.id WHERE users.role IN ("manager", "employee") ORDER BY users.created_at DESC');
$users = array_filter($stmt->fetchAll(), fn($user) => can_approve_role($user['role']));
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <div class="page-heading">
            <div>
                <p class="eyebrow mb-1">Accounts</p>
                <h1>Registration Approvals</h1>
                <p class="page-subtitle">Review manager and employee registrations.</p>
            </div>
        </div>
        <div id="approvalMessage" class="alert alert-success <?= $message ? '' : 'd-none' ?>"><?= e($message) ?></div>
        <div class="card mb-4">
            <div class="card-header">Manager and Employee Registrations</div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Employee ID</th><th>Email Verified</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-user-row="<?= (int) $user['id'] ?>">
                            <td data-label="Name"><strong><?= e($user['full_name']) ?></strong></td>
                            <td data-label="Username"><?= e($user['username']) ?></td>
                            <td data-label="Email"><?= e($user['email']) ?></td>
                            <td data-label="Role"><span class="status-pill"><?= e(role_label($user['role'])) ?></span></td>
                            <td data-label="Employee ID"><?= e($user['employee_no']) ?></td>
                            <td data-label="Email Verified"><?= $user['email_verified_at'] ? '<span class="status-pill status-good">Yes</span>' : '<span class="status-pill status-warn">No</span>' ?></td>
                            <td data-label="Status"><span class="status-pill"><?= e($user['approval_status']) ?></span></td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                <?php if ($user['approval_status'] === 'pending'): ?>
                                    <form method="post" class="d-inline approval-action-form">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-success" name="action" value="approve" type="submit"><i class="fas fa-check"></i><span>Approve</span></button>
                                        <button class="btn btn-sm btn-danger" name="action" value="reject" type="submit" data-role="<?= e($user['role']) ?>"><i class="fas fa-xmark"></i><span>Reject</span></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (is_admin()): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit" onclick="return confirm('Delete this login account? The employee record will remain.')"><i class="fas fa-trash"></i><span>Delete Account</span></button>
                                    </form>
                                <?php elseif ($user['approval_status'] !== 'pending'): ?>
                                    <span class="text-muted">No action</span>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?><tr><td colspan="8" class="text-center text-muted">No registrations found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('.approval-action-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        const submitter = event.submitter;
        if (!submitter || submitter.value !== 'reject' || submitter.dataset.role !== 'manager') {
            return;
        }

        event.preventDefault();
        if (!confirm('Reject this manager registration and permanently delete all related records? The manager can register again after rejection.')) {
            return;
        }

        const messageBox = document.getElementById('approvalMessage');
        const row = form.closest('tr');
        const formData = new FormData(form);
        formData.set('action', 'reject');
        submitter.disabled = true;

        try {
            const response = await fetch('approvals.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.message || 'Rejection failed.');
            }

            if (row) {
                row.remove();
            }
            messageBox.textContent = result.message;
            messageBox.classList.remove('d-none', 'alert-danger');
            messageBox.classList.add('alert-success');

            const tbody = document.querySelector('table tbody');
            if (tbody && !tbody.querySelector('tr[data-user-row]')) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No registrations found.</td></tr>';
            }
        } catch (error) {
            messageBox.textContent = error.message;
            messageBox.classList.remove('d-none', 'alert-success');
            messageBox.classList.add('alert-danger');
            submitter.disabled = false;
        }
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
