<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$form = [
    'title' => '', 'researchers' => '', 'abstract' => '', 'published_date' => '',
    'keywords' => '', 'category_id' => '', 'program_id' => '1',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Session expired. Please try again.';
    } else {
        $form['title']          = clean_text($_POST['title'] ?? '');
        $form['researchers']    = clean_text($_POST['researchers'] ?? '');
        $form['abstract']       = trim((string) ($_POST['abstract'] ?? ''));
        $form['published_date'] = (string) ($_POST['published_date'] ?? '');
        $form['keywords']       = clean_text($_POST['keywords'] ?? '');
        $form['category_id']    = (int) valid_id($_POST['category_id'] ?? null);
        $form['program_id']     = (int) valid_id($_POST['program_id'] ?? null);

        $ts   = strtotime($form['published_date']);
        $year = $ts ? (int) date('Y', $ts) : 0;

        if ($form['title'] === '' || $form['researchers'] === '' || !$form['category_id'] || !$form['published_date']) {
            $error = 'Complete the required fields.';
        } elseif (!$ts || $year < 2000 || $ts > time()) {
            $error = 'Enter a valid publication date (not in the future).';
        } else {
            $uploadError = null;
            $file = handle_pdf_upload($_FILES['document'] ?? [], $uploadError);

            if ($file === false) {
                $error = $uploadError;
            } else {
                try {
                    $pdo->beginTransaction();

                    // New uploads are ALWAYS pending. They are never published automatically.
                    $stmt = $pdo->prepare("INSERT INTO research
                        (title, abstract, year, published_date, keywords, category_id, program_id, file_path, status, uploaded_by_admin_id, submitted_at)
                        VALUES (?,?,?,?,?,?,?,?, 'pending', ?, ?)");
                    $stmt->execute([
                        $form['title'],
                        $form['abstract'] ?: null,
                        $year,
                        $form['published_date'],
                        $form['keywords'] ?: null,
                        $form['category_id'],
                        $form['program_id'] ?: null,
                        $file,
                        (int) $_SESSION['admin_id'],
                        date('Y-m-d H:i:s'),
                    ]);

                    $id = (int) $pdo->lastInsertId();
                    sync_researchers($pdo, $id, $form['researchers']);
                    $pdo->commit();

                    log_activity($pdo, 'Uploaded manuscript (Pending): ' . $form['title']);
                    set_flash('success', 'Manuscript uploaded. Its status is Pending until you approve it.');
                    redirect(BASE_URL . 'admin/research-database.php?status=pending');
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    delete_research_file($file);
                    $error = 'The research could not be saved.';
                    error_log($ex->getMessage());
                }
            }
        }
    }
}

$cats     = get_categories($pdo);
$programs = get_programs($pdo, true);
$isEdit   = false;
$record   = null;

$pageTitle = 'Upload Manuscript';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Upload manuscript</h1>
        <p>Workflow: Upload &rarr; Pending &rarr; Admin review &rarr; Approved &rarr; Available in the archive.</p>
    </div>
</header>

<section class="panel form-panel">
    <?php require __DIR__ . '/../includes/research-form.php'; ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
