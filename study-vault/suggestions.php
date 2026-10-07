<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Suggestions come from approved research only, and only for signed-in users.
if (!is_student_logged_in() && !is_logged_in()) {
    json_out([], 401);
}

json_out(fetch_suggestions($pdo, clean_text($_GET['q'] ?? '')));
