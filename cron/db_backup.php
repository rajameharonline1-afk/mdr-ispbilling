<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../app/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../app/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// XAMPP mysqldump path
// $mysqldumpPath = 'C:/xampp/mysql/bin/mysqldump.exe';

// সার্ভার (Linux) এর জন্য সঠিক mysqldump বাইনারি পথ
$mysqldumpPath = '/usr/bin/mysqldump';

// Backup ফাইল লোকেশন
$backupDir  = __DIR__ . '/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupFile = $backupDir . DB_NAME . '_backup_' . date('Y-m-d_H-i-s') . '.sql';

// ডাটাবেস ডাম্প কমান্ড
$command = "\"$mysqldumpPath\" --user=" . escapeshellarg(DB_USER) .
           " --password=" . escapeshellarg(DB_PASS) .
           " --host=" . escapeshellarg(DB_HOST) .
           " " . escapeshellarg(DB_NAME) .
           " > \"" . $backupFile . "\"";

exec($command, $output, $return_var);

if ($return_var === 0) {
    echo "✅ Database backup successful! Sending email...<br>";

    // Email পাঠানো
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rajameharonline1@gmail.com'; // তোমার Gmail
        $mail->Password   = 'hugq uppy kdre axkw';   // Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('rajameharonline1@gmail.com', 'Auto Backup');
        $mail->addAddress('rajameharonline1@gmail.com', 'Admin');

        // Attach backup file
        $mail->addAttachment($backupFile);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Daily Database Backup';
        $mail->Body    = 'Here is your latest database backup file.';

        $mail->send();
        echo "📧 Backup email sent successfully!";
    } catch (Exception $e) {
        echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
    }

} else {
    echo "❌ Database backup failed!";
}
