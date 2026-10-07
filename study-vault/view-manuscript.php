<?php
/**
 * Protected manuscript viewer. Shows the PDF (streamed by secure-document.php)
 * with a personalised watermark. Same permission checks as the document route.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$student = require_viewer();
$isAdmin = ($student === null);

$id = valid_id($_GET['id'] ?? null);
if (!$id) {
    redirect(BASE_URL . 'research.php');
}

$stmt = $pdo->prepare('SELECT r.research_id, r.title, r.status, r.file_path, r.year FROM research r WHERE r.research_id = ?');
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record || (!$isAdmin && $record['status'] !== 'approved')) {
    set_flash('warning', 'Research record not found.');
    redirect(BASE_URL . ($isAdmin ? 'admin/research-database.php' : 'research.php'));
}

$access = check_manuscript_access($pdo, $record, $student, $isAdmin);
if (!$access['allowed']) {
    if ($access['code'] === 'plan') {
        set_flash('warning', 'Full Manuscript Access Required. An active Research Access plan is needed to view the complete manuscript.');
        redirect(BASE_URL . 'research-details.php?id=' . $id);
    }
    set_flash('warning', $access['reason']);
    redirect(BASE_URL . ($isAdmin ? 'admin/research-database.php' : 'research-details.php?id=' . $id));
}

// Watermark lines (server-generated; the browser cannot choose them).
if ($isAdmin) {
    $admin = current_admin();
    $wm = ['STUDY VAULT', 'Administrator: ' . $admin['name'], 'CONFIDENTIAL'];
} else {
    $wm = ['STUDY VAULT', 'Authorized User: ' . $student['email'], 'Student ID: ' . $student['student_number'], 'CONFIDENTIAL'];
}
$wm[] = date('M j, Y g:i A');

log_activity($pdo, 'Opened protected manuscript #' . $id . ': ' . $record['title']);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, noarchive');
$backUrl = BASE_URL . 'research-details.php?id=' . $id;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <title>Protected viewer &middot; <?= e($record['title']) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="viewer-body">
    <header class="viewer-bar">
        <a class="btn btn-sm btn-outline-light" href="<?= e($backUrl) ?>"><span aria-hidden="true">&larr;</span> Record</a>
        <div class="viewer-title">
            <strong><?= e($record['title']) ?></strong>
            <span class="viewer-meta">Protected reader &middot; personalized watermark active</span>
        </div>
        <div class="viewer-controls" aria-label="Reader zoom controls">
            <button type="button" class="btn btn-sm btn-outline-light" id="zoomOut" aria-label="Zoom out">&minus;</button>
            <span id="zoomLabel" aria-live="polite">100%</span>
            <button type="button" class="btn btn-sm btn-outline-light" id="zoomIn" aria-label="Zoom in">+</button>
        </div>
    </header>

    <div id="viewer" class="viewer-stage"
         data-src="<?= e(secure_document_url($id)) ?>"
         data-watermark="<?= e(json_encode($wm, JSON_UNESCAPED_UNICODE)) ?>">
        <div id="pages" class="viewer-pages"></div>
        <div id="viewerStatus" class="viewer-status">Loading manuscript&hellip;</div>
        <div id="wmOverlay" class="wm-overlay" aria-hidden="true"></div>
    </div>

    <p class="viewer-foot">
        This copy is watermarked with your account details to discourage sharing. Watermarks deter redistribution but cannot physically prevent screenshots or copying.
    </p>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/viewer.js"></script>
</body>
</html>
