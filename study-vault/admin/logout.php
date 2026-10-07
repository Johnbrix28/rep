<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    log_activity($pdo, 'Signed out');
}
logout_admin();
session_regenerate_id(true);
set_flash('success', 'You have been signed out.');
redirect(BASE_URL . 'admin/login.php');
