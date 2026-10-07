<?php
/**
 * Database connection and global application settings.
 * Study Vault: Thesis and Research System
 */

// ---- Database credentials (XAMPP defaults) --------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'study_vault');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application settings -------------------------------------------------
define('SITE_NAME', 'Study Vault');
define('SITE_TAGLINE', 'Thesis and Research System');

// Root URL of the application. Change this if the folder name changes.
define('BASE_URL', '/study-vault/');

// Absolute path to the project root (no trailing slash).
define('BASE_PATH', dirname(__DIR__));

// Upload settings
// Manuscripts live in storage/manuscripts/ and are NEVER served directly by Apache
// (see the .htaccess in that folder). They are streamed by secure-document.php only.
define('MANUSCRIPT_DIR', BASE_PATH . '/storage/manuscripts/');
define('MANUSCRIPT_PATH_PREFIX', 'storage/manuscripts/');
// Legacy folder used by the first version of Study Vault (now locked down as well).
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads/');
define('MAX_UPLOAD_BYTES', 25 * 1024 * 1024); // 25 MB

// Session / security settings
define('STUDENT_IDLE_TIMEOUT', 3600);  // seconds
define('LOGIN_MAX_ATTEMPTS', 5);       // failed logins allowed ...
define('LOGIN_WINDOW_MINUTES', 15);    // ... within this many minutes
define('PASSWORD_MIN_LENGTH', 8);

// Payment mode. 'demo' = prototype checkout only (no real money moves).
// A real payment gateway MUST be integrated before production use.
define('PAYMENT_MODE', 'demo');

// Records per page
define('PER_PAGE', 9);
define('ADMIN_PER_PAGE', 10);

// ---- Development mode -----------------------------------------------------
// Set to false before handing the project over / deploying.
define('DEV_MODE', true);

if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');   // never show raw errors to the user
    ini_set('log_errors', '1');
}

date_default_timezone_set('Asia/Manila');

// ---- PDO connection -------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // Keep MySQL's NOW() and PHP's date() on the same clock.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('Study Vault database connection failed: ' . $e->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Study Vault is unavailable</title>'
       . '<style>body{font-family:system-ui,sans-serif;margin:0;display:grid;place-items:center;'
       . 'min-height:100vh;background:#f2f7f3;color:#1d3524;padding:1.5rem}'
       . 'div{max-width:32rem}h1{font-size:1.4rem}code{background:#e3ede5;padding:.15rem .35rem;border-radius:.2rem}</style>'
       . '</head><body><div><h1>Study Vault cannot reach the database</h1>'
       . '<p>Start Apache and MySQL in the XAMPP control panel, then import '
       . '<code>database/study_vault.sql</code> in phpMyAdmin.</p></div></body></html>';
    exit;
}
