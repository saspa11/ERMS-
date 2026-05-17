<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['employee', 'manager'], true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email_verification.php';
$pageTitle = 'My Profile';
$currentPage = 'profile';
$basePath = '../';

if (is_manager()) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "manager"');
    $stmt->execute([$userId]);
    $manager = $stmt->fetch();
    if (!$manager) redirect_to('../dashboard.php');

    $pendingVerification = get_pending_email_verification($pdo, $userId, 'manager');
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'update_profile';
        if ($action === 'verify_email') {
            [$ok, $resultMessage] = verify_email_update($pdo, $userId, 'manager', trim($_POST['otp'] ?? ''));
            redirect_to('profile.php?message=' . urlencode($resultMessage));
        }

        $email = trim($_POST['email'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email format is invalid.';
        }
        if (!in_array($gender, ['Female', 'Male', 'Other'], true)) {
            $errors[] = 'Gender is invalid.';
        }
        if (strcasecmp($email, $manager['email']) !== 0 && !email_is_available($pdo, $email, $userId, null)) {
            $errors[] = 'Email address is already in use.';
        }
        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $params = [$gender, $address, $userId];
            $sql = 'UPDATE users SET gender = ?, address = ? WHERE id = ? AND role = "manager"';
            if ($password !== '') {
                $sql = 'UPDATE users SET gender = ?, address = ?, password_hash = ? WHERE id = ? AND role = "manager"';
                $params = [$gender, $address, password_hash($password, PASSWORD_DEFAULT), $userId];
            }
            $update = $pdo->prepare($sql);
            $update->execute($params);

            if (strcasecmp($email, $manager['email']) !== 0) {
                if (!start_email_update_verification($pdo, $userId, 'manager', $email, $manager['full_name'])) {
                    redirect_to('profile.php?message=' . urlencode('Profile saved, but verification email could not be sent. Check SMTP settings.'));
                }
                redirect_to('profile.php?message=' . urlencode('Profile saved. Enter the 6-digit OTP sent to the new email to complete the email change.'));
            }
            redirect_to('profile.php?message=Profile updated successfully');
        }
    }

    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/sidebar.php';
    ?>
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">My Profile</h1>
            <?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
            <div class="card mb-4">
                <div class="card-header">My Manager Information</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Employee ID</dt><dd class="col-sm-9"><?= e($manager['manager_id']) ?></dd>
                        <dt class="col-sm-3">Username</dt><dd class="col-sm-9"><?= e($manager['username']) ?></dd>
                        <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= e($manager['email']) ?></dd>
                        <dt class="col-sm-3">Gender</dt><dd class="col-sm-9"><?= e($manager['gender']) ?></dd>
                        <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($manager['address']) ?></dd>
                    </dl>
                </div>
            </div>
            <form method="post" class="card mb-4">
                <div class="card-header">Update Personal Info</div>
                <div class="card-body row g-3">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="col-md-4"><label class="form-label">Email Address</label><input class="form-control" type="email" name="email" value="<?= e($manager['email']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender" required><option value="">Select gender</option><?php foreach (['Female','Male','Other'] as $gender): ?><option value="<?= $gender ?>" <?= ($manager['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="password" autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?= e($manager['address']) ?></textarea></div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Update Personal Info</button></div>
            </form>
            <?php if ($pendingVerification): ?>
                <form method="post" class="card mb-4">
                    <div class="card-header">Verify New Email</div>
                    <div class="card-body row g-3">
                        <input type="hidden" name="action" value="verify_email">
                        <div class="col-12"><p class="text-muted mb-0">Enter the 6-digit OTP sent to <?= e($pendingVerification['new_email']) ?>. This code expires at <?= e($pendingVerification['expires_at']) ?>.</p></div>
                        <div class="col-md-4"><label class="form-label">Verification Code</label><input class="form-control" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Verify Email</button></div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/footer.php'; exit; ?>
<?php }
$id = current_employee_id();
$stmt = $pdo->prepare(employee_join_sql() . ' WHERE employees.id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) redirect_to('../dashboard.php');
$userId = (int) $_SESSION['user_id'];
$pendingVerification = get_pending_email_verification($pdo, $userId, 'employee');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    if ($action === 'verify_email') {
        [$ok, $resultMessage] = verify_email_update($pdo, $userId, 'employee', trim($_POST['otp'] ?? ''), $id);
        redirect_to('profile.php?message=' . urlencode($resultMessage));
    }

    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email format is invalid.';
    }
    if (!in_array($gender, ['Female', 'Male', 'Other'], true)) {
        $errors[] = 'Gender is invalid.';
    }
    if (strcasecmp($email, $employee['email']) !== 0 && !email_is_available($pdo, $email, $userId, $id)) {
        $errors[] = 'Email address is already in use.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$errors) {
        $emailChanged = strcasecmp($email, $employee['email']) !== 0;
        $update = $pdo->prepare('UPDATE employees SET phone = ?, gender = ?, address = ? WHERE id = ?');
        $update->execute([$phone, $gender, $address, $id]);
        if ($password !== '') {
            $updatePassword = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role = "employee"');
            $updatePassword->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        }
        if ($emailChanged) {
            if (!start_email_update_verification($pdo, $userId, 'employee', $email, full_name($employee))) {
                redirect_to('profile.php?message=' . urlencode('Profile saved, but verification email could not be sent. Check SMTP settings.'));
            }
            redirect_to('profile.php?message=' . urlencode('Profile saved. Enter the 6-digit OTP sent to the new email to complete the email change.'));
        }
        redirect_to('profile.php?message=Profile updated successfully');
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4">My Profile</h1>
        <?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <div class="card mb-4">
            <div class="card-header">My Employee Information</div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Employee ID</dt><dd class="col-sm-9"><?= e($employee['employee_no']) ?></dd>
                    <dt class="col-sm-3">Full Name</dt><dd class="col-sm-9"><?= e(full_name($employee)) ?></dd>
                    <dt class="col-sm-3">Gender</dt><dd class="col-sm-9"><?= e($employee['gender']) ?></dd>
                    <dt class="col-sm-3">Birthdate / Age</dt><dd class="col-sm-9"><?= e($employee['birthdate']) ?> / <?= e(calculate_age($employee['birthdate'])) ?></dd>
                    <dt class="col-sm-3">Department</dt><dd class="col-sm-9"><?= e($employee['department_name']) ?></dd>
                    <dt class="col-sm-3">Position</dt><dd class="col-sm-9"><?= e($employee['position_title']) ?></dd>
                    <dt class="col-sm-3">Employment Status</dt><dd class="col-sm-9"><?= e($employee['status_name']) ?></dd>
                    <dt class="col-sm-3">Date Hired</dt><dd class="col-sm-9"><?= e($employee['hire_date']) ?></dd>
                    <dt class="col-sm-3">Monthly Salary</dt><dd class="col-sm-9"><?= e(money_format_php($employee['monthly_salary'])) ?></dd>
                </dl>
            </div>
        </div>
        <form method="post" class="card mb-4">
            <div class="card-header">Update Personal Info</div>
            <div class="card-body row g-3">
                <input type="hidden" name="action" value="update_profile">
                <div class="col-md-4"><label class="form-label">Contact Number</label><input class="form-control" name="phone" value="<?= e($employee['phone']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Email Address</label><input class="form-control" type="email" name="email" value="<?= e($employee['email']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender" required><option value="">Select gender</option><?php foreach (['Female','Male','Other'] as $gender): ?><option value="<?= $gender ?>" <?= ($employee['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="password" autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"></div>
                <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?= e($employee['address']) ?></textarea></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Update Personal Info</button></div>
        </form>
        <?php if ($pendingVerification): ?>
            <form method="post" class="card mb-4">
                <div class="card-header">Verify New Email</div>
                <div class="card-body row g-3">
                    <input type="hidden" name="action" value="verify_email">
                    <div class="col-12"><p class="text-muted mb-0">Enter the 6-digit OTP sent to <?= e($pendingVerification['new_email']) ?>. This code expires at <?= e($pendingVerification['expires_at']) ?>.</p></div>
                    <div class="col-md-4"><label class="form-label">Verification Code</label><input class="form-control" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Verify Email</button></div>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
