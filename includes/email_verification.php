<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/mail.php';

function ensure_email_verification_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('manager','employee') NOT NULL,
            new_email VARCHAR(120) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_email_verifications_user (user_id, user_type),
            KEY idx_email_verifications_email (new_email),
            KEY idx_email_verifications_expires (expires_at)
        )
    ");
}

function email_is_available(PDO $pdo, string $email, ?int $ignoreUserId = null, ?int $ignoreEmployeeId = null): bool
{
    $userSql = 'SELECT COUNT(*) FROM users WHERE email = ?';
    $userParams = [$email];
    if ($ignoreUserId !== null) {
        $userSql .= ' AND id <> ?';
        $userParams[] = $ignoreUserId;
    }
    $stmt = $pdo->prepare($userSql);
    $stmt->execute($userParams);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }

    $employeeSql = 'SELECT COUNT(*) FROM employees WHERE email = ?';
    $employeeParams = [$email];
    if ($ignoreEmployeeId !== null) {
        $employeeSql .= ' AND id <> ?';
        $employeeParams[] = $ignoreEmployeeId;
    }
    $stmt = $pdo->prepare($employeeSql);
    $stmt->execute($employeeParams);

    return (int) $stmt->fetchColumn() === 0;
}

function send_email_change_otp(string $email, string $fullName, string $otp): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $localMailer = __DIR__ . '/../PHPMailer/PHPMailer.php';
    $localSmtp = __DIR__ . '/../PHPMailer/SMTP.php';
    $localException = __DIR__ . '/../PHPMailer/Exception.php';

    if (file_exists($localMailer) && file_exists($localSmtp) && file_exists($localException)) {
        require_once $localException;
        require_once $localMailer;
        require_once $localSmtp;
    } elseif (file_exists($autoload)) {
        require_once $autoload;
    } else {
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = ERMS_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = ERMS_SMTP_USERNAME;
        $mail->Password = ERMS_SMTP_PASSWORD;
        $mail->SMTPSecure = ERMS_SMTP_SECURE;
        $mail->Port = ERMS_SMTP_PORT;

        if ($mail->Username === '' || $mail->Password === '') {
            return false;
        }

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $mail->setFrom(ERMS_MAIL_FROM ?: $mail->Username, ERMS_MAIL_FROM_NAME);
        $mail->addAddress($email, $fullName);
        $mail->isHTML(true);
        $mail->Subject = 'Confirm your ERMS email change';
        $mail->Body = "<p>Hello {$safeName},</p><p>Your ERMS email change verification code is <strong>{$safeOtp}</strong>.</p><p>This code expires in 5 minutes.</p>";
        $mail->AltBody = "Hello {$fullName},\n\nYour ERMS email change verification code is {$otp}. This code expires in 5 minutes.";
        $mail->send();

        return true;
    } catch (Exception $e) {
        return false;
    }
}

function start_email_update_verification(PDO $pdo, int $userId, string $userType, string $newEmail, string $fullName): bool
{
    ensure_email_verification_table($pdo);
    $otp = (string) random_int(100000, 999999);
    $stmt = $pdo->prepare('REPLACE INTO email_verifications (user_id, user_type, new_email, token_hash, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
    $stmt->execute([$userId, $userType, $newEmail, password_hash($otp, PASSWORD_DEFAULT)]);

    if (!send_email_change_otp($newEmail, $fullName, $otp)) {
        $delete = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND user_type = ?');
        $delete->execute([$userId, $userType]);
        return false;
    }

    return true;
}

function get_pending_email_verification(PDO $pdo, int $userId, string $userType): ?array
{
    ensure_email_verification_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM email_verifications WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, $userType]);
    $verification = $stmt->fetch();

    return $verification ?: null;
}

function verify_email_update(PDO $pdo, int $userId, string $userType, string $otp, ?int $employeeId = null): array
{
    ensure_email_verification_table($pdo);
    $verification = get_pending_email_verification($pdo, $userId, $userType);
    if (!$verification) {
        return [false, 'No pending email verification was found.'];
    }

    if (strtotime($verification['expires_at']) < time()) {
        $delete = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND user_type = ?');
        $delete->execute([$userId, $userType]);
        return [false, 'The verification code has expired. Please request a new code.'];
    }

    if (!password_verify($otp, $verification['token_hash'])) {
        return [false, 'Invalid verification code.'];
    }

    if (!email_is_available($pdo, $verification['new_email'], $userId, $employeeId)) {
        return [false, 'Email address is already in use.'];
    }

    $pdo->beginTransaction();
    if ($userType === 'manager') {
        $update = $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ? AND role = "manager"');
        $update->execute([$verification['new_email'], $userId]);
    } else {
        $updateUser = $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ? AND role = "employee"');
        $updateUser->execute([$verification['new_email'], $userId]);
        if ($employeeId !== null) {
            $updateEmployee = $pdo->prepare('UPDATE employees SET email = ? WHERE id = ?');
            $updateEmployee->execute([$verification['new_email'], $employeeId]);
        }
    }
    $delete = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND user_type = ?');
    $delete->execute([$userId, $userType]);
    $pdo->commit();

    return [true, 'Email address verified and updated successfully.'];
}
?>
