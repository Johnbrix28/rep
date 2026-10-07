<?php
/**
 * Safe SMTP defaults for Study Vault.
 *
 * Keep credentials out of version control. For a local XAMPP setup, copy
 * config/mail.local.example.php to config/mail.local.php and fill in your
 * Gmail address and App Password. The local file is ignored by Git.
 * Alternatively set STUDY_VAULT_MAIL_USERNAME and STUDY_VAULT_MAIL_PASSWORD
 * in the PHP process environment.
 */
$localMailConfig = __DIR__ . '/mail.local.php';
if (is_file($localMailConfig)) {
    require $localMailConfig;
}

$mailHost = getenv('STUDY_VAULT_MAIL_HOST');
$mailPort = getenv('STUDY_VAULT_MAIL_PORT');
$mailUsername = getenv('STUDY_VAULT_MAIL_USERNAME');
$mailPassword = getenv('STUDY_VAULT_MAIL_PASSWORD');
$mailEncryption = getenv('STUDY_VAULT_MAIL_ENCRYPTION');
$mailFromAddress = getenv('STUDY_VAULT_MAIL_FROM_ADDRESS');
$mailFromName = getenv('STUDY_VAULT_MAIL_FROM_NAME');

if (!defined('MAIL_HOST')) define('MAIL_HOST', $mailHost !== false && $mailHost !== '' ? $mailHost : 'smtp.gmail.com');
if (!defined('MAIL_PORT')) define('MAIL_PORT', $mailPort !== false && ctype_digit($mailPort) ? (int) $mailPort : 587);
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', $mailUsername !== false ? $mailUsername : '');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', $mailPassword !== false ? $mailPassword : '');
if (!defined('MAIL_ENCRYPTION')) define('MAIL_ENCRYPTION', $mailEncryption !== false && $mailEncryption !== '' ? $mailEncryption : 'tls');
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', $mailFromAddress !== false && $mailFromAddress !== '' ? $mailFromAddress : MAIL_USERNAME);
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $mailFromName !== false && $mailFromName !== '' ? $mailFromName : 'Study Vault');

// Troubleshooting only (leave false normally). Override in mail.local.php if needed.
if (!defined('MAIL_SMTP_DEBUG')) define('MAIL_SMTP_DEBUG', false);
if (!defined('MAIL_DEV_LOG_CODE')) define('MAIL_DEV_LOG_CODE', false);
if (!defined('MAIL_ALLOW_SELF_SIGNED')) define('MAIL_ALLOW_SELF_SIGNED', false);
