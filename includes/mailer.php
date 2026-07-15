<?php

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$GLOBALS['NOOR_LAST_SMTP_DEBUG'] = [];

function configure_smtp_diagnostics(PHPMailer\PHPMailer\PHPMailer $mail): void
{
    $GLOBALS['NOOR_LAST_SMTP_DEBUG'] = [];
    $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
    $mail->Debugoutput = static function (string $line, int $level): void {
        $password = (string) app_config('SMTP_PASSWORD', '');
        if ($password !== '') {
            $line = str_replace($password, '[hidden]', $line);
        }
        $GLOBALS['NOOR_LAST_SMTP_DEBUG'][] = 'L' . $level . ': ' . preg_replace('/[\r\n]+/', ' ', $line);
        if (count($GLOBALS['NOOR_LAST_SMTP_DEBUG']) > 12) {
            array_shift($GLOBALS['NOOR_LAST_SMTP_DEBUG']);
        }
    };
}

function mail_failure_diagnostic(string $context, Throwable $exception): string
{
    $diagnosticId = strtoupper(bin2hex(random_bytes(4)));
    $message = preg_replace('/[\r\n]+/', ' ', $exception->getMessage());
    $message = str_replace((string) app_config('SMTP_PASSWORD', ''), '[hidden]', $message);
    $smtpDebug = implode(' | ', array_slice($GLOBALS['NOOR_LAST_SMTP_DEBUG'] ?? [], -4));
    if ($smtpDebug !== '') {
        $message .= ' | SMTP: ' . $smtpDebug;
    }
    $logDirectory = __DIR__ . '/../storage/logs';
    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }
    $logLine = sprintf(
        "[%s] id=%s context=%s exception=%s message=%s host=%s port=%s encryption=%s\n",
        date('Y-m-d H:i:s'),
        $diagnosticId,
        preg_replace('/[^a-z0-9_-]/i', '', $context),
        get_class($exception),
        $message,
        (string) app_config('SMTP_HOST', 'missing'),
        (string) app_config('SMTP_PORT', 'missing'),
        (string) app_config('SMTP_ENCRYPTION', 'missing')
    );
    @file_put_contents($logDirectory . '/mail.log', $logLine, FILE_APPEND | LOCK_EX);
    error_log('Mail diagnostic ' . $diagnosticId . ': ' . $message);

    $lower = strtolower($message);
    if (str_contains($lower, 'authenticate') || str_contains($lower, '535')) {
        $summary = 'فشلت مصادقة SMTP. راجع البريد وApp Password.';
    } elseif (str_contains($lower, 'connect') || str_contains($lower, 'timed out') || str_contains($lower, 'connection')) {
        $summary = 'تعذر الاتصال بخادم SMTP. راجع الشبكة والمنفذ والتشفير.';
    } elseif (str_contains($lower, 'certificate') || str_contains($lower, 'ssl')) {
        $summary = 'حدث خطأ في اتصال SSL/TLS.';
    } elseif (str_contains($lower, 'recipient') || str_contains($lower, 'address')) {
        $summary = 'رفض خادم البريد عنوان المرسل أو المستلم.';
    } else {
        $summary = 'فشل إرسال البريد بسبب خطأ غير متوقع.';
    }

    if (app_debug_enabled()) {
        return $summary . ' التفاصيل: ' . $message . ' [مرجع: ' . $diagnosticId . ']';
    }
    return $summary . ' استخدم المرجع ' . $diagnosticId . ' عند مراجعة السجل.';
}

function send_verification_email(string $email, string $fullName, string $token): void
{
    $host = (string) app_config('SMTP_HOST', '');
    $username = (string) app_config('SMTP_USERNAME', '');
    $password = (string) app_config('SMTP_PASSWORD', '');
    $fromAddress = (string) app_config('MAIL_FROM_ADDRESS', $username);
    if ($host === '' || $username === '' || $password === '' || $fromAddress === '') {
        throw new RuntimeException('إعدادات البريد غير مكتملة على السيرفر.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    configure_smtp_diagnostics($mail);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $encryption = strtolower((string) app_config('SMTP_ENCRYPTION', 'ssl'));
    $mail->SMTPSecure = $encryption === 'tls'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) app_config('SMTP_PORT', $encryption === 'tls' ? 587 : 465);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromAddress, (string) app_config('MAIL_FROM_NAME', 'Noor Handmade'));
    $mail->addAddress($email, $fullName);

    $verificationLink = app_base_url() . '/verify.php?token=' . rawurlencode($token);
    $mail->isHTML(true);
    $mail->Subject = 'خطوة واحدة لتفعيل حسابك في Noor Handmade';
    $template = @file_get_contents(__DIR__ . '/../email_template.html');
    if ($template === false) {
        throw new RuntimeException('قالب رسالة التفعيل غير موجود.');
    }
    $mail->Body = str_replace(
        ['{{full_name}}', '{{verification_link}}'],
        [htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8')],
        $template
    );
    $mail->AltBody = "مرحبًا {$fullName}، فعّل حسابك من الرابط التالي: {$verificationLink}";
    $mail->send();
}

function send_password_reset_email(string $email, string $fullName, string $token): void
{
    $host = (string) app_config('SMTP_HOST', '');
    $username = (string) app_config('SMTP_USERNAME', '');
    $password = (string) app_config('SMTP_PASSWORD', '');
    $fromAddress = (string) app_config('MAIL_FROM_ADDRESS', $username);
    if ($host === '' || $username === '' || $password === '' || $fromAddress === '') {
        throw new RuntimeException('إعدادات البريد غير مكتملة على السيرفر.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    configure_smtp_diagnostics($mail);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $encryption = strtolower((string) app_config('SMTP_ENCRYPTION', 'ssl'));
    $mail->SMTPSecure = $encryption === 'tls'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) app_config('SMTP_PORT', $encryption === 'tls' ? 587 : 465);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromAddress, (string) app_config('MAIL_FROM_NAME', 'Noor Handmade'));
    $mail->addAddress($email, $fullName);
    $resetLink = app_base_url() . '/reset_password.php?token=' . rawurlencode($token);
    $mail->isHTML(true);
    $mail->Subject = 'استعادة كلمة المرور - Noor Handmade';
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $mail->Body = "<div dir=\"rtl\" style=\"font-family:Arial,sans-serif\"><h2>مرحبًا {$safeName}</h2><p>استخدم الرابط التالي لتعيين كلمة مرور جديدة. الرابط صالح لمدة ساعة واحدة.</p><p><a href=\"{$safeLink}\">تعيين كلمة مرور جديدة</a></p><p>إذا لم تطلب ذلك، تجاهل الرسالة.</p></div>";
    $mail->AltBody = "مرحبًا {$fullName}، استعد كلمة المرور خلال ساعة من الرابط: {$resetLink}";
    $mail->send();
}
