<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(['success' => false, 'message' => 'Use POST.'], 405);
}

$result = authenticate_student($pdo, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));

if (!$result['ok']) {
    json_out(['success' => false, 'message' => $result['error']], 401);
}

$s = $result['student'];
login_student($s);
log_student_activity($pdo, (int) $s['student_id'], 'Student signed in (API)');

// Only the signed-in user's OWN basic profile is returned. No password, no file paths.
json_out([
    'success' => true,
    'message' => 'Login successful',
    'data'    => [
        'student_id'     => (int) $s['student_id'],
        'full_name'      => $s['full_name'],
        'email'          => $s['email'],
        'student_number' => $s['student_number'],
        'user_type'      => $s['user_type'],
    ],
]);
