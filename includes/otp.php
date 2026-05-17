<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/mail.php';

function generate_otp(): string
{
    return (string) random_int(100000, 999999);
}

function set_user_otp(PDO $pdo, int $userId, string $otp): void
{
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET email_otp_hash = ?, email_otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?');
    $stmt->execute([$hash, $userId]);
}

function send_otp_email(string $email, string $fullName, string $otp): bool
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

        $mail->setFrom(ERMS_MAIL_FROM ?: $mail->Username, ERMS_MAIL_FROM_NAME);
        $mail->addAddress($email, $fullName);
        $mail->isHTML(true);
        $mail->Subject = 'ERMS email verification OTP';
        $mail->Body = '<p>Hello ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',</p><p>Your ERMS verification code is <strong>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>This code expires in 10 minutes.</p>';
        $mail->AltBody = "Hello {$fullName},\n\nYour ERMS verification code is {$otp}. It expires in 10 minutes.";
        $mail->send();

        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
