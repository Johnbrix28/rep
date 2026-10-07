<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$q       = clean_text($_GET['q'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['enrolled', 'alumni', 'inactive'], true) ? $_GET['status'] : '';
$program = clean_text($_GET['program'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(r.student_id LIKE :q1 OR r.full_name LIKE :q2 OR r.email LIKE :q3)';
    $like = '%' . $q . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
if ($status !== '') {
    $where[] = 'r.status = :status';
    $params[':status'] = $status;
}
if ($program !== '') {
    $where[] = 'r.program = :program';
    $params[':program'] = $program;
}
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM school_student_records r $ws");
$cnt->execute($params);
$total      = (int) $cnt->fetchColumn();
$totalPages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
$page       = min($page, $totalPages);

// "Account" = a Study Vault account already linked to this record (by link or by Student ID).
$stmt = $pdo->prepare("
    SELECT r.*, s.student_id AS account_id, s.email AS account_email
      FROM school_student_records r
      LEFT JOIN students s ON s.school_record_id = r.id
      $ws
      ORDER BY r.student_id
      LIMIT " . (int) ADMIN_PER_PAGE . ' OFFSET ' . (int) (($page - 1) * ADMIN_PER_PAGE));
$stmt->execute($params);
$records = $stmt->fetchAll();

$counts   = $pdo->query('SELECT status, COUNT(*) n FROM school_student_records GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$allCount = array_sum($counts);
$programs = $pdo->query('SELECT DISTINCT program FROM school_student_records ORDER BY program')->fetchAll(PDO::FETCH_COLUMN);
$linked   = (int) $pdo->query('SELECT COUNT(*) FROM students WHERE school_record_id IS NOT NULL')->fetchColumn();

$pageTitle = 'School Records';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>School Records</h1>
        <p>Simulated school database used to verify Student IDs. <?= (int) $allCount ?> records, <?= $linked ?> linked to a Study Vault account. Read-only.</p>
    </div>
</header>

<ul class="status-tabs">
    <li><a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(query_url(['status' => null, 'page' => null])) ?>">All <span><?= (int) $allCount ?></span></a></li>
    <?php foreach (['enrolled' => 'Enrolled', 'alumni' => 'Alumni', 'inactive' => 'Inactive'] as $k => $label): ?>
        <li><a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(query_url(['status' => $k, 'page' => null])) ?>"><?= $label ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="panel filter-inline" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label" for="q">Search Student ID, name or email</label>
            <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Search school records...">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="program">Program</label>
            <select class="form-select" id="program" name="program">
                <option value="">All programs</option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= e($p) ?>" <?= $program === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary align-self-end" type="submit">Search</button>
        </div>
    </div>
</form>

<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle sv-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full name</th>
                    <th>Email</th>
                    <th>Program</th>
                    <th>School status</th>
                    <th>Year</th>
                    <th>Study Vault eligibility</th>
                    <th>Account</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                    <?php $elig = school_record_eligibility($r)['ok']; ?>
                    <tr>
                        <td class="text-nowrap"><?= e($r['student_id']) ?></td>
                        <td class="cell-title"><?= e($r['full_name']) ?></td>
                        <td><?= e($r['email'] ?: '--') ?></td>
                        <td><?= e($r['program']) ?></td>
                        <td><?= status_badge((string) $r['status']) ?></td>
                        <td class="text-nowrap"><?= e($r['status'] === 'alumni' && $r['graduation_year'] ? 'Grad ' . $r['graduation_year'] : ($r['academic_year'] ?: '--')) ?></td>
                        <td><?= $elig ? '<span class="sv-badge sv-badge-green">Eligible</span>' : '<span class="sv-badge sv-badge-red">Not eligible</span>' ?></td>
                        <td>
                            <?php if ($r['account_id']): ?>
                                <a href="<?= BASE_URL ?>admin/students.php?q=<?= urlencode($r['student_id']) ?>"><span class="sv-badge sv-badge-green">Linked</span></a>
                            <?php else: ?>
                                <span class="sv-badge sv-badge-gray">Not registered</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$records): ?><p class="empty-note">No school records found.</p><?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="School record pages">
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
