<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/otp.php';

$userId = (int) ($_SESSION['verify_user_id'] ?? 0);
$pendingRegistration = $_SESSION['pending_registration'] ?? null;
$isPendingRegistration = is_array($pendingRegistration);
if (!$userId && !$isPendingRegistration) {
    redirect_to('login.php');
}

$user = null;
if ($userId) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        redirect_to('login.php');
    }
}

function finalize_pending_registration(PDO $pdo, array $registration): int
{
    $pdo->beginTransaction();
    try {
        $employeeId = null;
        if (($registration['role'] ?? '') === 'employee') {
            $employee = $registration['employee'];
            $stmt = $pdo->prepare('INSERT INTO employees (employee_no, first_name, last_name, gender, birthdate, email, phone, hire_date, department_id, position_id, status_id, address, monthly_salary) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, 0)');
            $stmt->execute([
                $employee['employee_no'],
                $employee['first_name'],
                $employee['last_name'],
                $employee['gender'],
                $employee['birthdate'],
                $employee['email'],
                $employee['phone'],
                $employee['department_id'],
                $employee['position_id'],
                $employee['status_id'],
                $employee['address'],
            ]);
            $employeeId = (int) $pdo->lastInsertId();
        }

        $manager = $registration['manager'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO users (full_name, username, email, password_hash, role, approval_status, employee_id, manager_id, gender, address, birthdate, email_verified_at) VALUES (?, ?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $registration['full_name'],
            $registration['username'],
            $registration['email'],
            $registration['password_hash'],
            $registration['role'],
            $employeeId,
            $manager['manager_id'] ?? null,
            $manager['gender'] ?? null,
            $manager['address'] ?? null,
            $manager['birthdate'] ?? null,
        ]);
        $createdUserId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $createdUserId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $otp = generate_otp();
        $recipientEmail = $isPendingRegistration ? $pendingRegistration['email'] : $user['email'];
        $recipientName = $isPendingRegistration ? $pendingRegistration['full_name'] : $user['full_name'];
        if (!send_otp_email($recipientEmail, $recipientName, $otp)) {
            $error = 'OTP email was not sent. Please configure Gmail SMTP in config/mail.php using a Gmail App Password.';
        } else {
            if ($isPendingRegistration) {
                $_SESSION['registration_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['registration_otp_expires_at'] = time() + 600;
            } else {
                set_user_otp($pdo, $userId, $otp);
            }
            $message = 'A new OTP has been sent to your email.';
        }
    } else {
        $otp = trim($_POST['otp'] ?? '');
        if ($isPendingRegistration) {
            $notExpired = (int) ($_SESSION['registration_otp_expires_at'] ?? 0) >= time();
            if ($notExpired && password_verify($otp, $_SESSION['registration_otp_hash'] ?? '')) {
                try {
                    finalize_pending_registration($pdo, $pendingRegistration);
                    unset($_SESSION['pending_registration'], $_SESSION['registration_otp_hash'], $_SESSION['registration_otp_expires_at']);
                    redirect_to('login.php?message=Email verified. Please wait for admin or manager approval before logging in.');
                } catch (Throwable $exception) {
                    $error = 'Registration could not be completed: ' . $exception->getMessage();
                }
            } else {
                $error = 'Invalid or expired OTP.';
            }
        } else {
            $notExpired = !empty($user['email_otp_expires_at']) && strtotime($user['email_otp_expires_at']) >= time();
            if ($notExpired && password_verify($otp, $user['email_otp_hash'] ?? '')) {
            $update = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_otp_hash = NULL, email_otp_expires_at = NULL WHERE id = ?');
            $update->execute([$userId]);
            unset($_SESSION['verify_user_id']);
            redirect_to('login.php?message=Email verified. Please wait for admin or manager approval before logging in.');
            }
            $error = 'Invalid or expired OTP.';
        }
    }
}
$recipientEmail = $isPendingRegistration ? $pendingRegistration['email'] : $user['email'];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Verify Email - ERMS</title>
        <link href="css/styles.css?v=20260516-otp-sync" rel="stylesheet" />
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
                                <div class="card auth-card auth-register-card auth-verify-card shadow-lg border-0 rounded-lg">
                                    <div class="card-header auth-card-header">
                                        <h3>Email OTP Verification</h3>
                                        <p>Enter the 6-digit code sent to <?= e($recipientEmail) ?>.</p>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
                                        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                                        <form method="post" class="row g-3 auth-register-form">
                                            <div class="col-12">
                                                <label class="form-label fw-bold" for="otp">OTP Code</label>
                                                <input class="form-control auth-otp-input" id="otp" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit OTP" required autocomplete="one-time-code" autofocus />
                                            </div>
                                            <div class="col-12 d-grid gap-2">
                                                <button class="btn btn-primary btn-action" type="submit"><i class="fas fa-shield-check"></i><span>Verify Email</span></button>
                                                <button class="btn btn-outline-primary btn-action" type="submit" name="resend" value="1"><i class="fas fa-paper-plane"></i><span>Resend OTP</span></button>
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
    </body>
</html>
