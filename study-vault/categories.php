<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_viewer();

$cats = $pdo->query("
    SELECT c.category_id, c.category_name, c.description, COUNT(r.research_id) total
      FROM categories c
      LEFT JOIN research r ON r.category_id = c.category_id AND r.status = 'approved'
     GROUP BY c.category_id, c.category_name, c.description
     ORDER BY c.category_name
")->fetchAll();

$pageTitle = 'Browse subjects';
require __DIR__ . '/includes/header.php';
?>

<div class="container section categories-section">
    <a class="back-link" href="<?= BASE_URL ?>research.php"><span aria-hidden="true">&larr;</span> Research archive</a>

    <header class="page-head categories-page-head">
        <p class="eyebrow">Organized for discovery</p>
        <h1>Browse by subject</h1>
        <p>Explore approved IT research through its subject areas. Choose a discipline to see related theses, abstracts, and manuscripts.</p>
    </header>

    <div class="category-grid row g-3 g-lg-4">
        <?php foreach ($cats as $index => $c): ?>
            <div class="col-md-6 col-xl-4">
                <a class="category-tile category-tile-lg" href="<?= BASE_URL ?>research.php?category=<?= (int) $c['category_id'] ?>">
                    <span class="category-index" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="category-copy">
                        <strong><?= e($c['category_name']) ?></strong>
                        <p><?= e($c['description'] ?: 'Approved studies and research works in this subject area.') ?></p>
                        <span class="category-count"><?= (int) $c['total'] ?> research record<?= (int) $c['total'] === 1 ? '' : 's' ?></span>
                    </span>
                    <span class="category-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (!$cats): ?>
            <div class="col-12"><div class="empty-state"><span class="empty-mark" aria-hidden="true">◇</span><h2>No categories yet</h2><p>Subject areas will appear here as research is added to the repository.</p></div></div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
