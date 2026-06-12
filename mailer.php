<?php
require_once __DIR__ . "/config.php";

function send_email($toEmail, $toName, $subject, $htmlBody) {
    try {
        // Composer autoload (PHPMailer)
        if (!file_exists(__DIR__ . "/vendor/autoload.php")) {
            throw new Exception("PHPMailer not installed. Run: composer require phpmailer/phpmailer");
        }
        require_once __DIR__ . "/vendor/autoload.php";

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // DEBUG to file (turn off later by setting SMTPDebug=0)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents(MAIL_FALLBACK_LOG, "[SMTP-$level] $str\n", FILE_APPEND);
        };

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Some environments need this (if SSL certificates cause issues)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->CharSet = "UTF-8";
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;

    } catch (Throwable $e) {
        $log = "[" . date("Y-m-d H:i:s") . "] FAILED\n"
             . "TO: {$toEmail}\nSUBJECT: {$subject}\n"
             . "ERROR: " . $e->getMessage() . "\n"
             . "BODY:\n{$htmlBody}\n---\n";
        file_put_contents(MAIL_FALLBACK_LOG, $log, FILE_APPEND);
        return false;
    }
}