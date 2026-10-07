<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$q      = clean_text($_GET['q'] ?? '');
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$page   = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.student_number LIKE :q1 OR s.full_name LIKE :q2 OR s.email LIKE :q3)';
    $like = '%' . $q . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
if ($status) {
    $where[] = 's.status = :status';
    $params[':status'] = $status;
}
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM students s $ws");
$cnt->execute($params);
$total      = (int) $cnt->fetchColumn();
$totalPages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
$page       = min($page, $totalPages);

$stmt = $pdo->prepare("
    SELECT s.*, p.program_name
      FROM students s LEFT JOIN programs p ON p.program_id = s.program_id
      $ws
      ORDER BY FIELD(s.status,'pending','approved','rejected'), s.created_at DESC
      LIMIT " . (int) ADMIN_PER_PAGE . ' OFFSET ' . (int) (($page - 1) * ADMIN_PER_PAGE));
$stmt->execute($params);
$students = $stmt->fetchAll();

$counts   = $pdo->query('SELECT status, COUNT(*) n FROM students GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$allCount = array_sum($counts);

$pageTitle = 'Students';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Students</h1>
        <p>Approve or reject IT student and alumni registrations. Only approved users can log in.</p>
    </div>
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
        <div class="col-md-9">
            <label class="form-label" for="q">Search name, email or Student ID</label>
            <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Search students...">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary align-self-end" type="submit">Search</button>
        </div>
    </div>
</form>

<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle sv-table">
            <thead>
                <tr>
                    <th>Full name</th>
                    <th>Email</th>
                    <th>Student ID</th>
                    <th>User type</th>
                    <th>Registered</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $st): ?>
                    <tr>
                        <td class="cell-title"><?= e($st['full_name']) ?></td>
                        <td>
                            <?php if ($st['email']): ?>
                                <?= e($st['email']) ?>
                            <?php else: ?>
                                <form method="post" action="<?= BASE_URL ?>admin/student-action.php" class="email-fix">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="student_id" value="<?= (int) $st['student_id'] ?>">
                                    <input type="hidden" name="action" value="set_email">
                                    <input class="form-control form-control-sm" type="email" name="email" placeholder="Set login email" required>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                                <small class="text-muted">Legacy account: needs an email to log in.</small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($st['student_number']) ?></td>
                        <td><?= e(user_type_label($st['user_type'])) ?></td>
                        <td class="text-nowrap"><?= e(format_date($st['created_at'])) ?></td>
                        <td><?= status_badge($st['status']) ?></td>
                        <td class="text-end">
                            <div class="action-group">
                                <?php foreach (['approve' => ['approved', 'btn-success', 'Approve'], 'reject' => ['rejected', 'btn-outline-danger', 'Reject']] as $act => [$target, $cls, $label]): ?>
                                    <?php if ($st['status'] !== $target): ?>
                                        <form method="post" action="<?= BASE_URL ?>admin/student-action.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="student_id" value="<?= (int) $st['student_id'] ?>">
                                            <input type="hidden" name="action" value="<?= $act ?>">
                                            <button class="btn btn-sm <?= $cls ?>" type="submit"><?= $label ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <form method="post" action="<?= BASE_URL ?>admin/student-action.php" class="d-inline" data-confirm="Delete this student account? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="student_id" value="<?= (int) $st['student_id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$students): ?><p class="empty-note">No students found.</p><?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Student pages">
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
