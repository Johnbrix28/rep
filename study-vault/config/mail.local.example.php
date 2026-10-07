<?php
/**
 * Copy this file to mail.local.php and set your own Gmail SMTP credentials.
 * mail.local.php is ignored by Git; never commit a real App Password.
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-address@gmail.com');
define('MAIL_PASSWORD', 'YOUR_16_CHARACTER_GMAIL_APP_PASSWORD');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', MAIL_USERNAME);
define('MAIL_FROM_NAME', 'Study Vault');

define('MAIL_SMTP_DEBUG', false);
define('MAIL_DEV_LOG_CODE', false);
define('MAIL_ALLOW_SELF_SIGNED', false);
