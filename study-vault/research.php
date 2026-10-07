<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_viewer();

$q        = clean_text($_GET['q'] ?? '');
$category = valid_id($_GET['category'] ?? null);
$year     = filter_var($_GET['year'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1990, 'max_range' => 2100]]);
$page     = max(1, (int) ($_GET['page'] ?? 1));

// Only APPROVED research is ever listed here.
$where  = ["r.status = 'approved'"];
$params = [];

if ($q !== '') {
    $where[] = '(r.title LIKE :q1 OR r.abstract LIKE :q2 OR r.keywords LIKE :q3 OR c.category_name LIKE :q4
                 OR CAST(r.year AS CHAR) = :q5
                 OR EXISTS(SELECT 1 FROM research_authors ra JOIN authors a ON a.author_id=ra.author_id
                            WHERE ra.research_id=r.research_id AND a.full_name LIKE :q6))';
    $like = '%' . $q . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $q, ':q6' => $like];
}
if ($category) { $where[] = 'r.category_id = :category'; $params[':category'] = $category; }
if ($year)     { $where[] = 'r.year = :year';            $params[':year']     = $year; }

$ws   = 'WHERE ' . implode(' AND ', $where);
$base = 'FROM research r JOIN categories c ON c.category_id=r.category_id';

$count = $pdo->prepare('SELECT COUNT(*) ' . $base . ' ' . $ws);
$count->execute($params);
$total      = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($total / PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * PER_PAGE;

$s = $pdo->prepare("
    SELECT r.research_id, r.title, r.abstract, r.year, r.published_date, r.keywords,
           (r.file_path IS NOT NULL AND r.file_path <> '') AS has_file,
           c.category_name,
           (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR ', ')
              FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
             WHERE ra.research_id = r.research_id) researchers
    $base $ws
    ORDER BY r.published_date DESC, r.research_id DESC
    LIMIT " . (int) PER_PAGE . ' OFFSET ' . (int) $offset);
$s->execute($params);
$rows = $s->fetchAll();

$cats     = get_categories($pdo);
$years    = get_research_years($pdo);
$filtered = ($q !== '' || $category || $year);

$pageTitle = 'Research archive';
require __DIR__ . '/includes/header.php';
?>

<div class="container section archive-section">
    <a class="back-link" href="<?= BASE_URL ?>index.php"><span aria-hidden="true">&larr;</span> Repository home</a>

    <header class="page-head archive-page-head">
        <div>
            <p class="eyebrow">The collection</p>
            <h1>Research archive</h1>
            <p>Search approved Information Technology theses and research works from the ISU-Ilagan community.</p>
        </div>
        <div class="archive-total"><strong><?= (int) $total ?></strong><span>matching<br>work<?= $total === 1 ? '' : 's' ?></span></div>
    </header>

    <form class="filter-panel live-search-form" method="get" autocomplete="off" role="search">
        <div class="filter-panel-heading">
            <span class="filter-symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg></span>
            <div><h2>Find a research work</h2><p>Search by title, author, topic, keyword, or year.</p></div>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-6">
                <label class="form-label" for="q">Search the collection</label>
                <div class="suggest-wrap">
                    <input class="form-control live-search" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. mobile learning, data mining..." data-suggest-url="<?= BASE_URL ?>suggestions.php">
                    <div class="suggestions" hidden></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="category">Subject area</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All subjects</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>" <?= $category === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="year">Publication year</label>
                <select class="form-select" id="year" name="year">
                    <option value="">Any year</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y ?>" <?= $year === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button class="btn btn-primary" type="submit">Search archive <span aria-hidden="true">&rarr;</span></button>
            </div>
        </div>
        <div class="filter-actions">
            <span class="result-count"><?= $filtered ? 'Showing filtered results' : 'Browse all approved works' ?></span>
            <?php if ($filtered): ?><a class="filter-clear" href="<?= BASE_URL ?>research.php">Clear filters <span aria-hidden="true">&times;</span></a><?php endif; ?>
        </div>
    </form>

    <div class="results-heading">
        <div><p class="eyebrow">Curated collection</p><h2><?= $filtered ? 'Search results' : 'All research' ?></h2></div>
        <span><?= (int) $total ?> record<?= $total === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state">
            <span class="empty-mark" aria-hidden="true">⌕</span>
            <h2>No research found</h2>
            <p>Try broadening your search or removing one of the selected filters.</p>
            <?php if ($filtered): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>research.php">Clear all filters</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-3 g-xl-4">
            <?php foreach ($rows as $r): ?>
                <div class="col-md-6 col-lg-4"><?php include __DIR__ . '/includes/research-card.php'; ?></div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination-nav" aria-label="Research pages">
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page - 1])) ?>">Previous</a></li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $i])) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page + 1])) ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
