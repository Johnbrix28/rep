<?php
/**
 * Administrator activity log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminFilter = valid_id($_GET['admin'] ?? null);
$page        = max(1, (int) ($_GET['page'] ?? 1));
$perPage     = 20;

$where  = '';
$params = [];
if ($adminFilter) {
    $where = 'WHERE l.admin_id = :admin';
    $params[':admin'] = $adminFilter;
}

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs l ' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        'SELECT l.log_id, l.action, l.created_at, l.ip_address, a.name, a.username, st.full_name AS student_name, st.email AS student_email
           FROM activity_logs l
           LEFT JOIN administrators a ON a.admin_id = l.admin_id
           LEFT JOIN students st ON st.student_id = l.student_id '
        . $where .
        ' ORDER BY l.created_at DESC, l.log_id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $admins = $pdo->query('SELECT admin_id, name FROM administrators ORDER BY name')->fetchAll();
} catch (PDOException $ex) {
    error_log('Activity log query failed: ' . $ex->getMessage());
    set_flash('danger', 'Unable to load the activity log.');
    $logs = $admins = [];
    $total = 0;
    $totalPages = 1;
}

$pageTitle = 'Activity logs';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
  <div>
    <h1>Activity logs</h1>
    <p><?= (int) $total ?> recorded action<?= $total === 1 ? '' : 's' ?>. Sign-ins, record changes, approvals, checkouts and protected-manuscript views are logged.</p>
  </div>
</header>

<section class="panel">
  <form class="panel-head" method="get" action="<?= BASE_URL ?>admin/activity-logs.php">
    <h2 class="panel-title">Log entries</h2>
    <div class="d-flex gap-2">
      <label class="visually-hidden" for="admin">Filter by administrator</label>
      <select class="form-select form-select-sm" id="admin" name="admin">
        <option value="">All administrators</option>
        <?php foreach ($admins as $a): ?>
          <option value="<?= (int) $a['admin_id'] ?>" <?= $adminFilter === (int) $a['admin_id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-primary" type="submit">Filter</button>
    </div>
  </form>

  <?php if (!$logs): ?>
    <p class="empty-note">No activity has been recorded yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle admin-table">
        <thead><tr><th>#</th><th>Action</th><th>User</th><th>IP</th><th>Date and time</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= (int) $log['log_id'] ?></td>
            <td class="cell-title"><?= e($log['action']) ?></td>
            <td>
              <?php if ($log['name']): ?>
                <?= e($log['name']) ?> <span class="sv-badge sv-badge-gray">Admin</span>
              <?php elseif ($log['student_name']): ?>
                <?= e($log['student_name']) ?> <small class="text-muted"><?= e($log['student_email'] ?? '') ?></small>
              <?php else: ?>
                <span class="text-muted">System / removed account</span>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= e($log['ip_address'] ?? '') ?></td>
            <td class="text-nowrap"><?= e(format_datetime($log['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="pagination-nav" aria-label="Log pages">
        <ul class="pagination pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page - 1])) ?>">Previous</a></li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $i])) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_url(['page' => $page + 1])) ?>">Next</a></li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
