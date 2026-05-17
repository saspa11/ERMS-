<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/otp.php';

$data = ['role' => 'manager'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array_map('trim', $_POST);
    $role = 'manager';
    $data['role'] = $role;
    $submittedEmployeeId = $data['registration_employee_id'] ?? '';
    $data['manager_id'] = $submittedEmployeeId;

    foreach (['full_name', 'username', 'email', 'password', 'confirm_password'] as $field) {
        if (($data[$field] ?? '') === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email format is invalid.';
    }

    if (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    }

    if (strlen($data['password'] ?? '') < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
    $duplicate->execute([$data['username'] ?? '', $data['email'] ?? '']);
    if ($duplicate->fetchColumn() > 0) {
        $errors[] = 'Username or email is already registered.';
    }

    foreach (['manager_id', 'manager_gender', 'manager_birthdate', 'manager_address'] as $field) {
        if (($data[$field] ?? '') === '') {
            $label = $field === 'manager_id' ? 'Employee ID' : ucfirst(str_replace(['manager_', '_'], ['', ' '], $field));
            $errors[] = $label . ' is required for manager registration.';
        }
    }

    if (!empty($data['manager_id']) && !preg_match('/^[A-Za-z0-9-]+$/', $data['manager_id'])) {
        $errors[] = 'Employee ID must be alphanumeric and may include hyphens.';
    }

    if (!empty($data['manager_id'])) {
        $duplicateManager = $pdo->prepare('SELECT COUNT(*) FROM users WHERE manager_id = ?');
        $duplicateManager->execute([$data['manager_id']]);
        if ($duplicateManager->fetchColumn() > 0) {
            $errors[] = 'Employee ID is already registered.';
        }
    }

    if (!$errors) {
        try {
            $displayName = $data['full_name'];
            $pendingRegistration = [
                'role' => $role,
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'full_name' => $displayName,
            ];
            $pendingRegistration['manager'] = [
                'manager_id' => $data['manager_id'],
                'gender' => $data['manager_gender'],
                'address' => $data['manager_address'],
                'birthdate' => $data['manager_birthdate'],
            ];

            $otp = generate_otp();
            if (!send_otp_email($data['email'], $displayName, $otp)) {
                throw new RuntimeException('OTP email was not sent. Please configure Gmail SMTP in config/mail.php using a Gmail App Password.');
            }

            $_SESSION['pending_registration'] = $pendingRegistration;
            $_SESSION['registration_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['registration_otp_expires_at'] = time() + 600;
            header('Location: verify_otp.php');
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Register - ERMS</title>
        <link href="css/styles.css?v=20260515-auth-sync" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication" class="auth-screen">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center align-items-center min-vh-100 py-4">
                            <div class="col-xl-6 col-lg-7 col-md-9">
                                <div class="auth-brand text-center mb-3">
                                    <div class="auth-logo"><i class="fas fa-shield-halved"></i></div>
                                    <h1>ERMS</h1>
                                    <p>Employee Information Portal</p>
                                </div>
                                <div class="card auth-card auth-register-card shadow-lg border-0 rounded-lg">
                                    <div class="card-header auth-card-header">
                                        <h3>Create Account</h3>
                                        <p>Register as manager and verify your email to request access.</p>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                                        <form method="post" class="row g-3 auth-register-form">
                                            <input type="hidden" name="role" value="manager">
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold">Register As</label>
                                                <input class="form-control" value="Manager" disabled>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label fw-bold" for="full_name">Full Name</label>
                                                <input class="form-control" id="full_name" name="full_name" placeholder="Full Name" required value="<?= e($data['full_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold" for="registration_employee_id">Employee ID</label>
                                                <input class="form-control" id="registration_employee_id" name="registration_employee_id" placeholder="Employee ID" value="<?= e($data['manager_id'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold" for="username">Username</label>
                                                <input class="form-control" id="username" name="username" placeholder="Username" required value="<?= e($data['username'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold" for="email">Email Address</label>
                                                <input class="form-control" id="email" type="email" name="email" placeholder="Email Address" required value="<?= e($data['email'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold" for="password">Password</label>
                                                <input class="form-control" id="password" type="password" name="password" placeholder="Password" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold" for="confirm_password">Confirm Password</label>
                                                <input class="form-control" id="confirm_password" type="password" name="confirm_password" placeholder="Confirm Password" required>
                                            </div>
                                            <div class="manager-fields row g-3 m-0 p-0">
                                                <input type="hidden" id="manager_id" name="manager_id" value="<?= e($data['manager_id'] ?? '') ?>">
                                                <div class="col-md-4"><label class="form-label fw-bold">Gender</label><select class="form-select" name="manager_gender"><option value="">Select gender</option><?php foreach (['Female','Male','Other'] as $gender): ?><option value="<?= $gender ?>" <?= ($data['manager_gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?></select></div>
                                                <div class="col-md-4"><label class="form-label fw-bold">Birthdate</label><input class="form-control" type="date" name="manager_birthdate" value="<?= e($data['manager_birthdate'] ?? '') ?>"></div>
                                                <div class="col-12"><label class="form-label fw-bold">Address</label><textarea class="form-control" name="manager_address" rows="2"><?= e($data['manager_address'] ?? '') ?></textarea></div>
                                            </div>
                                            <div class="col-12 d-grid">
                                                <button class="btn btn-primary btn-action" type="submit"><i class="fas fa-user-check"></i><span>Register and Verify Email</span></button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer auth-footer text-center py-3">
                                        <a class="btn btn-outline-primary btn-action w-100" href="login.php"><i class="fas fa-arrow-left"></i><span>Back to Login</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <script>
        const registrationEmployeeId = document.getElementById('registration_employee_id');
        const managerId = document.getElementById('manager_id');
        function syncEmployeeId() {
            managerId.value = registrationEmployeeId.value;
        }
        registrationEmployeeId.addEventListener('input', syncEmployeeId);
        syncEmployeeId();
        </script>
    </body>
</html>
