<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect(BASE_URL . 'admin/categories.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    $catId  = valid_id($_POST['category_id'] ?? null);
    $name   = mb_substr(clean_text($_POST['category_name'] ?? ''), 0, 120);
    $desc   = mb_substr(clean_text($_POST['description'] ?? ''), 0, 255);

    try {
        if ($action === 'add' || $action === 'update') {
            if ($name === '') {
                set_flash('danger', 'Category name is required.');
            } else {
                $dup = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
                $dup->execute([$name]);
                $existing = $dup->fetchColumn();

                if ($existing && (!$catId || (int) $existing !== $catId || $action === 'add')) {
                    set_flash('danger', 'A category with that name already exists.');
                } elseif ($action === 'add') {
                    $pdo->prepare('INSERT INTO categories(category_name, description) VALUES(?,?)')->execute([$name, $desc ?: null]);
                    log_activity($pdo, 'Added category: ' . $name);
                    set_flash('success', 'Category added.');
                } elseif ($catId) {
                    $pdo->prepare('UPDATE categories SET category_name=?, description=? WHERE category_id=?')->execute([$name, $desc ?: null, $catId]);
                    log_activity($pdo, 'Updated category: ' . $name);
                    set_flash('success', 'Category updated.');
                }
            }
        } elseif ($action === 'delete' && $catId) {
            $n = $pdo->prepare('SELECT COUNT(*) FROM research WHERE category_id = ?');
            $n->execute([$catId]);
            if ((int) $n->fetchColumn() > 0) {
                set_flash('warning', 'This category still has research records and cannot be deleted.');
            } else {
                $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$catId]);
                log_activity($pdo, 'Deleted category #' . $catId);
                set_flash('success', 'Category deleted.');
            }
        }
    } catch (PDOException $ex) {
        error_log('Category action failed: ' . $ex->getMessage());
        set_flash('danger', 'The category could not be saved.');
    }
    redirect(BASE_URL . 'admin/categories.php');
}

$cats = $pdo->query("
    SELECT c.category_id, c.category_name, c.description,
           COUNT(r.research_id) total,
           SUM(r.status = 'approved') approved
      FROM categories c LEFT JOIN research r ON r.category_id = c.category_id
     GROUP BY c.category_id, c.category_name, c.description
     ORDER BY c.category_id")->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Categories</h1>
        <p>Organize research by IT major area.</p>
    </div>
</header>

<div class="row g-3">
    <div class="col-lg-8">
        <?php foreach ($cats as $c): ?>
            <section class="panel category-major">
                <form method="post" class="cat-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">Name</label>
                            <input class="form-control" name="category_name" required maxlength="120" value="<?= e($c['category_name']) ?>">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Description</label>
                            <input class="form-control" name="description" maxlength="255" value="<?= e($c['description'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="cat-foot">
                        <span><?= (int) $c['total'] ?> record<?= (int) $c['total'] === 1 ? '' : 's' ?> (<?= (int) $c['approved'] ?> approved)</span>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" name="action" value="update" type="submit">Save</button>
                            <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit"
                                    onclick="return confirm('Delete this category?');" <?= (int) $c['total'] > 0 ? 'disabled title="Has research records"' : '' ?>>Delete</button>
                        </div>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-4">
        <section class="panel">
            <h2 class="panel-title">Add category</h2>
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label class="form-label" for="new_name">Name</label>
                <input class="form-control" id="new_name" name="category_name" required maxlength="120">
                <label class="form-label mt-3" for="new_desc">Description</label>
                <input class="form-control" id="new_desc" name="description" maxlength="255">
                <button class="btn btn-primary w-100 mt-3" type="submit">Add category</button>
            </form>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
