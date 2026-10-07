<?php
// Command-line helper:  php make-password-hash.php "MyNewPassword1"
// Prints a bcrypt hash you can paste into the `password` column in phpMyAdmin.
// Delete this file before real deployment.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDERR, "Usage: php make-password-hash.php \"password\"\n");
    exit(1);
}
echo password_hash($pw, PASSWORD_DEFAULT), PHP_EOL;
