<?php
/**
 * Student account actions (POST only, CSRF protected):
 * approve | reject | delete | set_email
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$back = BASE_URL . 'admin/students.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    set_flash('danger', 'The request could not be verified. Please try again.');
    redirect($back);
}

$id     = valid_id($_POST['student_id'] ?? null);
$action = (string) ($_POST['action'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject', 'delete', 'set_email'], true)) {
    set_flash('warning', 'Invalid request.');
    redirect($back);
}

try {
    $s = $pdo->prepare('SELECT student_id, full_name, email FROM students WHERE student_id = ?');
    $s->execute([$id]);
    $student = $s->fetch();

    if (!$student) {
        set_flash('warning', 'Student account was not found.');
        redirect($back);
    }

    switch ($action) {
        case 'approve':
            $pdo->prepare("UPDATE students SET status='approved', approved_by=?, approved_at=? WHERE student_id=?")
                ->execute([(int) $_SESSION['admin_id'], date('Y-m-d H:i:s'), $id]);
            log_activity($pdo, 'Approved student account: ' . $student['full_name']);
            set_flash('success', $student['full_name'] . ' was approved and can now log in.');
            break;

        case 'reject':
            $pdo->prepare("UPDATE students SET status='rejected', approved_by=?, approved_at=? WHERE student_id=?")
                ->execute([(int) $_SESSION['admin_id'], date('Y-m-d H:i:s'), $id]);
            log_activity($pdo, 'Rejected student account: ' . $student['full_name']);
            set_flash('success', $student['full_name'] . ' was rejected.');
            break;

        case 'delete':
            $pdo->prepare('DELETE FROM students WHERE student_id = ?')->execute([$id]);
            log_activity($pdo, 'Deleted student account: ' . $student['full_name']);
            set_flash('success', 'Student account deleted.');
            break;

        case 'set_email':
            $email = normalize_email($_POST['email'] ?? '');
            if (!valid_email($email)) {
                set_flash('danger', 'Enter a valid email address.');
                break;
            }
            $dup = $pdo->prepare('SELECT 1 FROM students WHERE email = ? AND student_id <> ?');
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                set_flash('danger', 'That email address is already used by another account.');
                break;
            }
            $pdo->prepare('UPDATE students SET email = ? WHERE student_id = ?')->execute([$email, $id]);
            log_activity($pdo, 'Set login email for student: ' . $student['full_name']);
            set_flash('success', 'Login email saved for ' . $student['full_name'] . '.');
            break;
    }
} catch (PDOException $ex) {
    error_log('Student action failed: ' . $ex->getMessage());
    set_flash('danger', 'The action could not be completed.');
}

redirect($back);
