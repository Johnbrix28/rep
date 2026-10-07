<?php
/**
 * Verification e-mail: code generation, HTML template, PHPMailer + Gmail SMTP sending.
 */
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/** Cryptographically secure 6-digit code (leading zeros kept). */
function generate_verification_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function verification_email_html(string $name, string $code, int $minutes): string
{
    $n = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $c = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $s = htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8');

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f2f7f3;font-family:Arial,Helvetica,sans-serif;color:#1d3524;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f7f3;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border:1px solid #dbe8de;border-radius:12px;">'
        . '<tr><td style="padding:24px 28px 8px;font-size:20px;font-weight:bold;color:#1f6b3a;">' . $s . '</td></tr>'
        . '<tr><td style="padding:8px 28px;font-size:15px;line-height:1.5;">Hello ' . $n . ',<br><br>'
        . 'Use this code to verify your Student ID and create your ' . $s . ' account:</td></tr>'
        . '<tr><td align="center" style="padding:12px 28px;">'
        . '<div style="display:inline-block;background:#e6f4ea;border:1px solid #bfdcc7;border-radius:10px;padding:14px 28px;'
        . 'font-size:34px;letter-spacing:10px;font-weight:bold;color:#14532d;font-family:Consolas,Menlo,monospace;">' . $c . '</div></td></tr>'
        . '<tr><td style="padding:8px 28px 24px;font-size:13px;line-height:1.5;color:#51655a;">'
        . 'This code expires in ' . (int) $minutes . ' minutes and can be used once. '
        . 'If you did not request it, you can safely ignore this email. Never share this code with anyone.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Send the code. Returns ['ok' => bool, 'error' => string]. */
function send_verification_email(string $toEmail, string $toName, string $code): array
{
    if (MAIL_DEV_LOG_CODE) {
        error_log('[Study Vault DEV] Verification code for ' . $toEmail . ': ' . $code);
    }

    if (MAIL_USERNAME === '' || in_array(strtolower(MAIL_USERNAME), ['your-gmail@gmail.com', 'your-address@gmail.com'], true)
        || MAIL_PASSWORD === '' || in_array(MAIL_PASSWORD, ['xxxxxxxxxxxxxxxx', 'YOUR_16_CHARACTER_GMAIL_APP_PASSWORD'], true)) {
        error_log('Study Vault: config/mail.php has no Gmail address / App Password yet.');
        return ['ok' => false, 'error' => 'Email sending is not configured yet. Please contact the administrator.'];
    }

    $dir = BASE_PATH . '/lib/PHPMailer/';
    if (!is_file($dir . 'PHPMailer.php')) {
        error_log('Study Vault: PHPMailer is missing from lib/PHPMailer/.');
        return ['ok' => false, 'error' => 'The email library is not installed. Please contact the administrator.'];
    }
    require_once $dir . 'Exception.php';
    require_once $dir . 'PHPMailer.php';
    require_once $dir . 'SMTP.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = strtolower(MAIL_ENCRYPTION) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';

        if (MAIL_SMTP_DEBUG) {
            $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = static function ($str) { error_log('SMTP: ' . trim($str)); };
        }
        if (MAIL_ALLOW_SELF_SIGNED) {
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        }

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Study Vault — Your Verification Code';
        $mail->Body    = verification_email_html($toName, $code, VERIFY_CODE_TTL_MINUTES);
        $mail->AltBody = "Your Study Vault verification code is: $code\n\n"
                       . 'It expires in ' . VERIFY_CODE_TTL_MINUTES . " minutes. If you did not request it, ignore this email.";

        $mail->send();
        return ['ok' => true, 'error' => ''];
    } catch (MailException $ex) {
        error_log('Study Vault mail error: ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => 'The verification email could not be sent. Please try again in a moment.'];
    }
}

/**
 * Create a fresh code (old ones are invalidated) for a school record and email it
 * to the address the school has on file. Returns ['ok' => bool, 'error' => string].
 */
function issue_and_send_code(PDO $pdo, array $record): array
{
    $email = normalize_email($record['email'] ?? '');
    if (!valid_email($email)) {
        return ['ok' => false, 'error' => 'The school has no valid email address on file for this Student ID.'];
    }

    $code   = generate_verification_code();
    $codeId = store_verification_code($pdo, $record, $code);

    $sent = send_verification_email($email, (string) $record['full_name'], $code);
    if (!$sent['ok']) {
        delete_verification_code($pdo, $codeId); // never leave a usable code that was not delivered
    }
    return $sent;
}
