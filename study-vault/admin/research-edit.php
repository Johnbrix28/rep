<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = valid_id($_GET['id'] ?? null);
if (!$id) {
    redirect(BASE_URL . 'admin/research-database.php');
}

$s = $pdo->prepare('SELECT * FROM research WHERE research_id=?');
$s->execute([$id]);
$record = $s->fetch();

if (!$record) {
    set_flash('warning', 'Research record not found.');
    redirect(BASE_URL . 'admin/research-database.php');
}

$form = [
    'title'          => $record['title'],
    'researchers'    => implode(', ', get_research_authors($pdo, $id)),
    'abstract'       => $record['abstract'] ?? '',
    'keywords'       => $record['keywords'] ?? '',
    'category_id'    => $record['category_id'],
    'program_id'     => $record['program_id'],
    'published_date' => $record['published_date'],
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Session expired. Please try again.';
    } else {
        $form['title']          = clean_text($_POST['title'] ?? '');
        $form['researchers']    = clean_text($_POST['researchers'] ?? '');
        $form['abstract']       = trim((string) ($_POST['abstract'] ?? ''));
        $form['keywords']       = clean_text($_POST['keywords'] ?? '');
        $form['category_id']    = (int) valid_id($_POST['category_id'] ?? null);
        $form['program_id']     = (int) valid_id($_POST['program_id'] ?? null);
        $form['published_date'] = (string) ($_POST['published_date'] ?? '');

        $ts   = strtotime($form['published_date']);
        $year = $ts ? (int) date('Y', $ts) : 0;

        if ($form['title'] === '' || $form['researchers'] === '' || !$form['category_id'] || !$form['published_date']) {
            $error = 'Complete the required fields.';
        } elseif (!$ts || $year < 2000 || $ts > time()) {
            $error = 'Enter a valid publication date (not in the future).';
        } else {
            $uploadError = null;
            $newFile = handle_pdf_upload($_FILES['document'] ?? [], $uploadError);

            if ($newFile === false) {
                $error = $uploadError;
            } else {
                try {
                    $pdo->beginTransaction();

                    $path = $newFile ?: $record['file_path'];
                    if (!$newFile && !empty($_POST['remove_file'])) {
                        $path = null;
                    }

                    $u = $pdo->prepare('UPDATE research SET title=?,abstract=?,year=?,published_date=?,keywords=?,category_id=?,program_id=?,file_path=? WHERE research_id=?');
                    $u->execute([
                        $form['title'],
                        $form['abstract'] ?: null,
                        $year,
                        $form['published_date'],
                        $form['keywords'] ?: null,
                        $form['category_id'],
                        $form['program_id'] ?: null,
                        $path,
                        $id,
                    ]);

                    sync_researchers($pdo, $id, $form['researchers']);
                    $pdo->commit();

                    if ($record['file_path'] && $record['file_path'] !== $path) {
                        delete_research_file($record['file_path']);
                    }

                    log_activity($pdo, 'Edited research: ' . $form['title']);
                    set_flash('success', 'Research record updated.');
                    redirect(BASE_URL . 'admin/research-database.php');
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    delete_research_file($newFile ?: null);
                    $error = 'The changes could not be saved.';
                    error_log($e->getMessage());
                }
            }
        }
    }
}

$cats     = get_categories($pdo);
$programs = get_programs($pdo, true);
$isEdit   = true;

$pageTitle = 'Edit Research';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <p class="eyebrow">Research database</p>
        <h1>Edit research <?= status_badge($record['status']) ?></h1>
        <p>Update details, replace the manuscript, or review this record.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>research-details.php?id=<?= $id ?>">View</a>
        <a class="btn btn-outline-danger" href="<?= BASE_URL ?>admin/research-delete.php?id=<?= $id ?>">Delete</a>
    </div>
</header>

<section class="panel review-panel">
    <div class="review-meta">
        <div><span>Submitted</span><strong><?= e(format_datetime($record['submitted_at'])) ?></strong></div>
        <div><span>Reviewed</span><strong><?= e(format_datetime($record['reviewed_at'])) ?></strong></div>
        <?php if (!empty($record['review_note'])): ?>
            <div><span>Review note</span><strong><?= e($record['review_note']) ?></strong></div>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= BASE_URL ?>admin/research-review.php" class="review-actions">
        <?= csrf_field() ?>
        <input type="hidden" name="research_id" value="<?= $id ?>">
        <input type="hidden" name="return" value="edit">
        <input class="form-control" name="note" maxlength="255" placeholder="Optional note for the record (e.g. reason for rejection)">
        <?php if ($record['status'] !== 'approved'): ?>
            <button class="btn btn-success" name="action" value="approve" type="submit">Approve</button>
        <?php endif; ?>
        <?php if ($record['status'] !== 'rejected'): ?>
            <button class="btn btn-outline-danger" name="action" value="reject" type="submit">Reject</button>
        <?php endif; ?>
    </form>
</section>

<section class="panel form-panel">
    <?php require __DIR__ . '/../includes/research-form.php'; ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
