<?php
/**
 * Self-declared registration was replaced by Student ID verification (email + 6-digit code).
 * Old links and bookmarks to register.php now lead to verify.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';

redirect(BASE_URL . (is_student_logged_in() ? 'index.php' : 'verify.php'));
