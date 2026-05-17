<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email_verification.php';

$pageTitle = 'Managers';
$currentPage = 'managers';
$basePath = '../';
$errors = [];
$message = $_GET['message'] ?? '';
$genders = ['Female', 'Male', 'Other'];
$emptyManager = [
    'id' => '',
    'full_name' => '',
    'username' => '',
    'email' => '',
    'manager_id' => '',
    'gender' => '',
    'birthdate' => '',
    'address' => '',
    'approval_status' => '',
];
$manager = $emptyManager;
$editing = false;
$pendingVerification = null;

function validate_manager_payload(PDO $pdo, array $data, int $ignoreUserId = 0, ?string $currentEmail = null): array
{
    $errors = [];
    foreach (['full_name', 'email', 'manager_id', 'gender', 'birthdate', 'address'] as $field) {
        if (($data[$field] ?? '') === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email format is invalid.';
    }

    if (!empty($data['manager_id']) && !preg_match('/^[A-Za-z0-9-]+$/', $data['manager_id'])) {
        $errors[] = 'Manager ID must be alphanumeric and may include hyphens.';
    }

    if (!in_array($data['gender'] ?? '', ['Female', 'Male', 'Other'], true)) {
        $errors[] = 'Gender is invalid.';
    }

    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users WHERE manager_id = ? AND id <> ?');
    $duplicate->execute([$data['manager_id'] ?? '', $ignoreUserId]);
    if ($duplicate->fetchColumn() > 0) {
        $errors[] = 'Manager ID is already in use.';
    }

    if (($data['email'] ?? '') !== '' && strcasecmp($data['email'], (string) $currentEmail) !== 0 && !email_is_available($pdo, $data['email'], $ignoreUserId, null)) {
        $errors[] = 'Email address is already in use.';
    }

    return $errors;
}

if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "manager"');
    $stmt->execute([(int) $_GET['edit']]);
    $manager = $stmt->fetch() ?: $emptyManager;
    if (!$manager['id']) {
        $editing = false;
        $message = 'Manager not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $managerId = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "manager"');
        $stmt->execute([$managerId]);
        redirect_to('managers.php?message=Manager deleted successfully');
    }

    if ($action === 'verify_email') {
        $managerId = (int) ($_POST['id'] ?? 0);
        [$ok, $resultMessage] = verify_email_update($pdo, $managerId, 'manager', trim($_POST['otp'] ?? ''));
        redirect_to('managers.php?edit=' . $managerId . '&message=' . urlencode($resultMessage));
    }

    if ($action === 'update') {
        $data = array_map('trim', $_POST);
        $userId = (int) ($data['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "manager"');
        $stmt->execute([$userId]);
        $existingManager = $stmt->fetch();
        if (!$existingManager) {
            $errors[] = 'Manager not found.';
        }

        if (!$errors) {
            $errors = validate_manager_payload($pdo, $data, $userId, $existingManager['email']);
        }

        if (!$errors) {
            $update = $pdo->prepare('UPDATE users SET full_name=?, manager_id=?, gender=?, address=?, birthdate=? WHERE id=? AND role="manager"');
            $update->execute([$data['full_name'], $data['manager_id'], $data['gender'], $data['address'], $data['birthdate'], $userId]);

            if (strcasecmp($data['email'], $existingManager['email']) !== 0) {
                if (!start_email_update_verification($pdo, $userId, 'manager', $data['email'], $data['full_name'])) {
                    redirect_to('managers.php?edit=' . $userId . '&message=' . urlencode('Profile saved, but verification email could not be sent. Check SMTP settings.'));
                }
                redirect_to('managers.php?edit=' . $userId . '&message=' . urlencode('Profile saved. Enter the 6-digit OTP sent to the new email to complete the email change.'));
            }
            redirect_to('managers.php?message=Manager updated successfully');
        }

        $editing = true;
        $manager = array_merge($existingManager ?: $emptyManager, $data);
    }
}

$managers = $pdo->query('SELECT * FROM users WHERE role = "manager" ORDER BY full_name')->fetchAll();
$selectedId = (int) ($_GET['view'] ?? ($managers[0]['id'] ?? 0));
$selectedManager = null;
if ($selectedId) {
    foreach ($managers as $row) {
        if ((int) $row['id'] === $selectedId) {
            $selectedManager = $row;
            break;
        }
    }
}
if ($editing && !empty($manager['id'])) {
    $pendingVerification = get_pending_email_verification($pdo, (int) $manager['id'], 'manager');
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <div class="page-heading">
            <div>
                <p class="eyebrow mb-1">Admin</p>
                <h1>Managers</h1>
                <p class="page-subtitle">Review, update, and remove manager accounts.</p>
            </div>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <div class="row">
            <?php if ($editing): ?>
            <div class="col-xl-5">
                <form method="post" class="card mb-4">
                    <div class="card-header">Edit Manager</div>
                    <div class="card-body row g-3">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= e((string) ($manager['id'] ?? '')) ?>">
                        <div class="col-md-6"><label class="form-label">Manager ID</label><input class="form-control" name="manager_id" required value="<?= e($manager['manager_id'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" value="<?= e($manager['username'] ?? '') ?>" disabled></div>
                        <div class="col-md-12"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required value="<?= e($manager['full_name'] ?? '') ?>"></div>
                        <div class="col-md-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="<?= e($manager['email'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select class="form-select" name="gender" required><option value="">Select gender</option><?php foreach ($genders as $gender): ?><option value="<?= $gender ?>" <?= ($manager['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Birthdate</label><input class="form-control" type="date" name="birthdate" required value="<?= e($manager['birthdate'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" required><?= e($manager['address'] ?? '') ?></textarea></div>
                    </div>
                    <div class="card-footer form-actions">
                        <a class="btn btn-secondary btn-action" href="managers.php"><i class="fas fa-xmark"></i><span>Cancel</span></a>
                        <button class="btn btn-primary btn-action" type="submit"><i class="fas fa-floppy-disk"></i><span>Save Manager</span></button>
                    </div>
                </form>

                <?php if ($pendingVerification): ?>
                    <form method="post" class="card mb-4">
                        <div class="card-header">Verify New Email</div>
                        <div class="card-body row g-3">
                            <input type="hidden" name="action" value="verify_email">
                            <input type="hidden" name="id" value="<?= e((string) $manager['id']) ?>">
                            <div class="col-12"><p class="text-muted mb-0">Enter the 6-digit OTP sent to <?= e($pendingVerification['new_email']) ?>. This code expires at <?= e($pendingVerification['expires_at']) ?>.</p></div>
                            <div class="col-12"><label class="form-label">Verification Code</label><input class="form-control" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></div>
                        </div>
                        <div class="card-footer form-actions"><button class="btn btn-primary btn-action" type="submit"><i class="fas fa-shield-check"></i><span>Verify Email</span></button></div>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="<?= $editing ? 'col-xl-7' : 'col-12' ?>">
                <div class="card mb-4">
                    <div class="card-header">Manager Accounts</div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Manager ID</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($managers as $row): ?>
                                <tr>
                                    <td data-label="Manager ID"><?= e($row['manager_id']) ?></td>
                                    <td data-label="Name"><strong><?= e($row['full_name']) ?></strong></td>
                                    <td data-label="Email"><?= e($row['email']) ?></td>
                                    <td data-label="Status"><span class="status-pill"><?= e($row['approval_status']) ?></span></td>
                                    <td data-label="Actions">
                                        <div class="table-actions">
                                            <a class="btn btn-sm btn-info text-white" href="managers.php?view=<?= $row['id'] ?>"><i class="fas fa-eye"></i><span>View</span></a>
                                            <a class="btn btn-sm btn-warning" href="managers.php?edit=<?= $row['id'] ?>"><i class="fas fa-pen"></i><span>Edit</span></a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this manager account permanently?')"><i class="fas fa-trash"></i><span>Delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$managers): ?><tr><td colspan="5" class="text-center text-muted">No manager accounts found.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Manager Profile</div>
                    <div class="card-body">
                        <?php if ($selectedManager): ?>
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Manager ID</dt><dd class="col-sm-8"><?= e($selectedManager['manager_id']) ?></dd>
                                <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= e($selectedManager['full_name']) ?></dd>
                                <dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?= e($selectedManager['username']) ?></dd>
                                <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($selectedManager['email']) ?></dd>
                                <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= e($selectedManager['gender']) ?></dd>
                                <dt class="col-sm-4">Birthdate</dt><dd class="col-sm-8"><?= e($selectedManager['birthdate']) ?><?= $selectedManager['birthdate'] ? ' (' . e(calculate_age($selectedManager['birthdate'])) . ')' : '' ?></dd>
                                <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= e($selectedManager['address']) ?></dd>
                                <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="status-pill"><?= e($selectedManager['approval_status']) ?></span></dd>
                                <dt class="col-sm-4">Email Verified</dt><dd class="col-sm-8"><?= $selectedManager['email_verified_at'] ? '<span class="status-pill status-good">Yes</span>' : '<span class="status-pill status-warn">No</span>' ?></dd>
                            </dl>
                        <?php else: ?>
                            <p class="text-muted mb-0">Select a manager to view profile details.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
