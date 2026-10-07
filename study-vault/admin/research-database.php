<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$q        = clean_text($_GET['q'] ?? '');
$category = valid_id($_GET['category'] ?? null);
$year     = valid_id($_GET['year'] ?? null);
$status   = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$page     = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(r.title LIKE :q1 OR r.abstract LIKE :q2 OR r.keywords LIKE :q3 OR c.category_name LIKE :q4
                 OR CAST(r.year AS CHAR) = :q5
                 OR EXISTS(SELECT 1 FROM research_authors ra JOIN authors a ON a.author_id=ra.author_id
                            WHERE ra.research_id=r.research_id AND a.full_name LIKE :q6))';
    $like = '%' . $q . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $q, ':q6' => $like];
}
if ($category) { $where[] = 'r.category_id = :cat';  $params[':cat']    = $category; }
if ($year)     { $where[] = 'r.year = :year';        $params[':year']   = $year; }
if ($status)   { $where[] = 'r.status = :status';    $params[':status'] = $status; }

$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM research r JOIN categories c ON c.category_id = r.category_id $ws");
$cnt->execute($params);
$total      = (int) $cnt->fetchColumn();
$totalPages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
$page       = min($page, $totalPages);

$s = $pdo->prepare("
    SELECT r.*, c.category_name,
           (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR ', ')
              FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
             WHERE ra.research_id = r.research_id) researchers
      FROM research r
      JOIN categories c ON c.category_id = r.category_id
      $ws
      ORDER BY FIELD(r.status,'pending','approved','rejected'), r.published_date DESC, r.research_id DESC
      LIMIT " . (int) ADMIN_PER_PAGE . ' OFFSET ' . (int) (($page - 1) * ADMIN_PER_PAGE));
$s->execute($params);
$rows = $s->fetchAll();

$cats  = get_categories($pdo);
$years = get_research_years($pdo, false);

$counts = $pdo->query("SELECT status, COUNT(*) n FROM research GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$allCount = array_sum($counts);

$pageTitle = 'Research';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Research database</h1>
        <p>Search, review and manage every manuscript. Only <strong>Approved</strong> records are visible to students.</p>
    </div>
    <a class="btn btn-primary" href="<?= BASE_URL ?>admin/research-add.php">Upload manuscript</a>
</header>

<ul class="status-tabs">
    <li><a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(query_url(['status' => null, 'page' => null])) ?>">All <span><?= (int) $allCount ?></span></a></li>
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label): ?>
        <li><a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(query_url(['status' => $k, 'page' => null])) ?>"><?= $label ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="panel filter-inline" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="row g-2">
        <div class="col-lg-5">
            <label class="form-label" for="q">Search</label>
            <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Title, abstract, author, keyword, category, year">
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="category">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>" <?= $category === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="year">Year</label>
            <select class="form-select" id="year" name="year">
                <option value="">All</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int) $y ?>" <?= $year === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 d-grid">
            <button class="btn btn-primary align-self-end" type="submit">Search</button>
        </div>
    </div>
</form>

<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle sv-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Authors</th>
                    <th>Category</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="cell-title">
                            <?= e($r['title']) ?>
                            <?php if (!research_file_exists($r['file_path'])): ?><small class="d-block text-muted">No PDF attached</small><?php endif; ?>
                        </td>
                        <td><?= e($r['researchers'] ?? '--') ?></td>
                        <td><?= e($r['category_name']) ?></td>
                        <td><?= e($r['year']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end">
                            <div class="action-group">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>research-details.php?id=<?= (int) $r['research_id'] ?>">View</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>admin/research-edit.php?id=<?= (int) $r['research_id'] ?>">Edit</a>
                                <?php foreach (['approve' => ['approved', 'btn-success', 'Approve'], 'reject' => ['rejected', 'btn-outline-danger', 'Reject']] as $act => [$target, $cls, $label]): ?>
                                    <?php if ($r['status'] !== $target): ?>
                                        <form method="post" action="<?= BASE_URL ?>admin/research-review.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="research_id" value="<?= (int) $r['research_id'] ?>">
                                            <input type="hidden" name="action" value="<?= $act ?>">
                                            <?php if ($status): ?><input type="hidden" name="return_status" value="<?= e($status) ?>"><?php endif; ?>
                                            <button class="btn btn-sm <?= $cls ?>" type="submit"><?= $label ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a class="btn btn-sm btn-outline-danger" href="<?= BASE_URL ?>admin/research-delete.php?id=<?= (int) $r['research_id'] ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$rows): ?><p class="empty-note">No research records found.</p><?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Research pages">
            <ul class="pagination pagination-sm">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page - 1])) ?>">Previous</a></li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page + 1])) ?>">Next</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
