<?php
/**
 * Approve or reject a research record (POST only, CSRF protected).
 * Pending -> Approved -> visible in the archive
 * Pending -> Rejected -> hidden from students
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$back = BASE_URL . 'admin/research-database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    set_flash('danger', 'The request could not be verified. Please try again.');
    redirect($back);
}

$id     = valid_id($_POST['research_id'] ?? null);
$action = (string) ($_POST['action'] ?? '');
$note   = mb_substr(clean_text($_POST['note'] ?? ''), 0, 255);

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    set_flash('warning', 'Invalid review request.');
    redirect($back);
}

// Only return to the admin research list (never to a user-supplied URL).
if (($_POST['return'] ?? '') === 'edit') {
    $back = BASE_URL . 'admin/research-edit.php?id=' . $id;
} elseif (!empty($_POST['return_status']) && in_array($_POST['return_status'], ['pending', 'approved', 'rejected'], true)) {
    $back .= '?status=' . $_POST['return_status'];
}

try {
    $s = $pdo->prepare('SELECT title, status FROM research WHERE research_id = ?');
    $s->execute([$id]);
    $row = $s->fetch();

    if (!$row) {
        set_flash('warning', 'Research record not found.');
        redirect(BASE_URL . 'admin/research-database.php');
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    $u = $pdo->prepare('UPDATE research SET status=?, reviewed_by=?, reviewed_at=?, review_note=? WHERE research_id=?');
    $u->execute([$newStatus, (int) $_SESSION['admin_id'], date('Y-m-d H:i:s'), $note !== '' ? $note : null, $id]);

    log_activity($pdo, ucfirst($newStatus) . ' research: ' . $row['title']);
    set_flash('success', 'Research ' . $newStatus . ': ' . $row['title']);
} catch (PDOException $ex) {
    error_log('Review failed: ' . $ex->getMessage());
    set_flash('danger', 'The status could not be updated.');
}

redirect($back);
