<?php
function sendMailWithPHPMailer($to_email, $subject, $html_body, &$error = null) {
    $error = null;
    $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
    if ($autoload && file_exists($autoload)) {
        require_once $autoload;
    } else {
        $localPaths = [
            realpath(__DIR__ . '/PHPMailer/src/PHPMailer.php'),
            realpath(__DIR__ . '/../PHPMailer/src/PHPMailer.php'),
            realpath(__DIR__ . '/../../PHPMailer/src/PHPMailer.php'),
            realpath(__DIR__ . '/../../PHPMailer-7.0.2/PHPMailer-7.0.2/src/PHPMailer.php'),
            realpath(__DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php')
        ];
        $phpmailerFile = null;
        foreach ($localPaths as $path) {
            if ($path && file_exists($path)) { $phpmailerFile = $path; break; }
        }
        if (!$phpmailerFile) {
            $error = 'phpmailer_files_missing';
            return false;
        }
        require_once $phpmailerFile;
        require_once dirname($phpmailerFile) . '/SMTP.php';
        require_once dirname($phpmailerFile) . '/Exception.php';
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $error = 'phpmailer_missing';
        return false;
    }

    $smtp_host = getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com';
    $smtp_port = (int)(getenv('MAIL_SMTP_PORT') ?: 587);
    $smtp_user = getenv('MAIL_USERNAME') ?: '';
    $smtp_pass = getenv('MAIL_PASSWORD') ?: '';
    $smtp_secure = strtolower(getenv('MAIL_SMTP_SECURE') ?: 'tls');
    $mail_from = getenv('MAIL_FROM') ?: $smtp_user;
    $mail_from_name = getenv('MAIL_FROM_NAME') ?: 'LYINGIN';

    if (!$smtp_host || !$smtp_user || !$smtp_pass) {
        $error = 'smtp_not_configured';
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->Port = $smtp_port;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = ($smtp_secure === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $environment = strtolower((string)(getenv('ENVIRONMENT') ?: 'production'));
        if ($environment !== 'production') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->setFrom($mail_from, $mail_from_name);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = strip_tags($html_body);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return false;
    }
}
