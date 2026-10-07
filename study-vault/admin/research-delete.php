<?php
/**
 * Delete a research record. GET shows the confirmation, POST performs it.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = valid_id($_GET['id'] ?? $_POST['research_id'] ?? null);
if (!$id) {
    set_flash('warning', 'That research record could not be found.');
    redirect(BASE_URL . 'admin/research-database.php');
}

try {
    $stmt = $pdo->prepare(
        'SELECT r.*, c.category_name,
                (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR \', \')
                   FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
                  WHERE ra.research_id = r.research_id) AS authors
           FROM research r LEFT JOIN categories c ON c.category_id = r.category_id
          WHERE r.research_id = ?'
    );
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} catch (PDOException $ex) {
    error_log('Load research for delete failed: ' . $ex->getMessage());
    set_flash('danger', 'Unable to load the research record.');
    redirect(BASE_URL . 'admin/research-database.php');
}

if (!$record) {
    set_flash('warning', 'That research record could not be found.');
    redirect(BASE_URL . 'admin/research-database.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('danger', 'Your session expired. Try the deletion again.');
        redirect(BASE_URL . 'admin/research-delete.php?id=' . $id);
    }

    try {
        // research_authors rows are removed by the ON DELETE CASCADE constraint.
        $stmt = $pdo->prepare('DELETE FROM research WHERE research_id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 1) {
            delete_research_file($record['file_path']);
            log_activity($pdo, 'Deleted research: ' . $record['title']);
            set_flash('success', 'Research record deleted.');
        } else {
            set_flash('warning', 'That research record was already removed.');
        }
    } catch (PDOException $ex) {
        error_log('Delete research failed: ' . $ex->getMessage());
        set_flash('danger', 'The research record could not be deleted.');
    }
    redirect(BASE_URL . 'admin/research-database.php');
}

$pageTitle = 'Delete research';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
  <div>
    <h1>Delete research</h1>
    <p>This cannot be undone.</p>
  </div>
</header>

<section class="panel panel-narrow">
  <h2 class="panel-title">Are you sure you want to delete this research record?</h2>
  <dl class="record-dl">
    <dt>Title</dt><dd><?= e($record['title']) ?></dd>
    <dt>Authors</dt><dd><?= e($record['authors'] ?? '--') ?></dd>
    <dt>Year</dt><dd><?= e($record['year']) ?></dd>
    <dt>Category</dt><dd><?= e($record['category_name'] ?? '--') ?></dd>
    <dt>Status</dt><dd><?= status_badge($record['status']) ?></dd>
    <dt>Document</dt><dd><?= research_file_exists($record['file_path']) ? 'A PDF is attached and will be deleted too.' : 'No document attached.' ?></dd>
  </dl>

  <form method="post" action="<?= BASE_URL ?>admin/research-delete.php">
    <?= csrf_field() ?>
    <input type="hidden" name="research_id" value="<?= (int) $id ?>">
    <div class="form-actions">
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/research-database.php">Keep the record</a>
      <button class="btn btn-danger" type="submit">Delete this record</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
