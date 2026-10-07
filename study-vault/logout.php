<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_student_logged_in()) {
    log_student_activity($pdo, (int) $_SESSION['student_id'], 'Student signed out');
}
logout_student();
set_flash('success', 'You have been logged out.');
redirect(BASE_URL . 'login.php');
