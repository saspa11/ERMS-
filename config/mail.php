<?php
// Set these as Windows/Apache environment variables. For Gmail SMTP, use a
// Google App Password, not your normal account password.
$smtpHost = getenv('ERMS_SMTP_HOST') ?: 'smtp.gmail.com';
$smtpPort = (int) (getenv('ERMS_SMTP_PORT') ?: 587);
$smtpSecure = getenv('ERMS_SMTP_SECURE') ?: 'tls';
$smtpUsername = getenv('ERMS_SMTP_USERNAME') ?: 'albertobelacassaspa@gmail.com';
$smtpPassword = getenv('ERMS_SMTP_PASSWORD') ?: 'wbfw iuhz vrxe joke';
$mailFrom = getenv('ERMS_MAIL_FROM') ?: $smtpUsername;
$mailFromName = getenv('ERMS_MAIL_FROM_NAME') ?: 'ERMS';

define('ERMS_SMTP_HOST', $smtpHost);
define('ERMS_SMTP_PORT', $smtpPort);
define('ERMS_SMTP_SECURE', $smtpSecure);
define('ERMS_SMTP_USERNAME', $smtpUsername);
define('ERMS_SMTP_PASSWORD', $smtpPassword);
define('ERMS_MAIL_FROM', $mailFrom);
define('ERMS_MAIL_FROM_NAME', $mailFromName);
?>
