<?php
/**
 * Protected manuscript route:  secure-document.php?id=123
 *
 * The PDF is NEVER linked directly. This script re-checks every permission
 * (login, approved account, approved research, active unexpired paid plan)
 * on EVERY request and only then streams the file.
 */
require_once __DIR__ . '/includes/bootstrap.php';

function deny_document(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><meta charset="utf-8"><title>Access denied</title>'
       . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f8f5;color:#1d3524">'
       . '<div style="max-width:28rem;padding:1.5rem;text-align:center"><h1 style="font-size:1.25rem">Full Manuscript Access Required</h1>'
       . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div></body>';
    exit;
}

$id = valid_id($_GET['id'] ?? null);
if (!$id) {
    deny_document(400, 'Invalid document request.');
}

$isAdmin = is_logged_in();
$student = null;

if ($isAdmin) {
    require_admin();
} elseif (is_student_logged_in()) {
    $student = load_student($pdo, (int) $_SESSION['student_id']);
    if (!$student || $student['status'] !== 'approved') {
        deny_document(403, 'Your account is not approved.');
    }
} else {
    deny_document(401, 'Please log in to continue.');
}

$stmt = $pdo->prepare('SELECT research_id, status, file_path FROM research WHERE research_id = ?');
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record || (!$isAdmin && $record['status'] !== 'approved')) {
    deny_document(404, 'This document is not available.');
}

$access = check_manuscript_access($pdo, $record, $student, $isAdmin);
if (!$access['allowed']) {
    deny_document($access['code'] === 'no_file' ? 404 : 403, $access['reason']);
}

$path = resolve_manuscript_file($record['file_path']);
if ($path === null) {
    deny_document(404, 'This document is not available.');
}

// Release the session lock so the viewer can load pages while this streams.
session_write_close();

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="manuscript-' . (int) $id . '.pdf"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, noarchive');
header('X-Frame-Options: SAMEORIGIN');

readfile($path);
exit;
