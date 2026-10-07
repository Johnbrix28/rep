<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$tab = ($_GET['tab'] ?? '') === 'plans' ? 'plans' : 'subs';

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect(BASE_URL . 'admin/subscriptions.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_plan') {
            $planId = valid_id($_POST['plan_id'] ?? null);
            $plan   = $planId ? get_plan($pdo, $planId) : null;

            if (!$plan) {
                set_flash('warning', 'Plan not found.');
            } else {
                $name   = mb_substr(clean_text($_POST['plan_name'] ?? ''), 0, 80);
                $desc   = mb_substr(clean_text($_POST['description'] ?? ''), 0, 255);
                $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
                $price  = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
                $days   = filter_var($_POST['duration_days'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);

                if ((int) $plan['full_access'] === 0) {
                    // The free plan is always free and has no duration.
                    $price = 0.0;
                    $days  = 0;
                }

                if ($name === '') {
                    set_flash('danger', 'Plan name is required.');
                } elseif ($price === false || $price < 0 || $price > 1000000) {
                    set_flash('danger', 'Enter a valid price (0 or more).');
                } elseif ($days === false) {
                    set_flash('danger', 'Duration must be between 1 and 3650 days.');
                } else {
                    $pdo->prepare('UPDATE access_plans SET plan_name=?, description=?, price=?, duration_days=?, status=? WHERE plan_id=?')
                        ->execute([$name, $desc ?: null, $price, $days, $status, $planId]);
                    log_activity($pdo, 'Updated access plan: ' . $name);
                    set_flash('success', 'Plan updated. Existing subscriptions keep their original dates.');
                }
            }
            redirect(BASE_URL . 'admin/subscriptions.php?tab=plans');
        }

        if ($action === 'cancel') {
            $subId = valid_id($_POST['subscription_id'] ?? null);
            if ($subId) {
                $u = $pdo->prepare("UPDATE subscriptions SET status='cancelled' WHERE subscription_id=? AND status IN ('active','pending')");
                $u->execute([$subId]);
                if ($u->rowCount() === 1) {
                    log_activity($pdo, 'Cancelled subscription #' . $subId);
                    set_flash('success', 'Subscription cancelled. Full manuscript access ended immediately.');
                } else {
                    set_flash('warning', 'That subscription could not be cancelled.');
                }
            }
            redirect(BASE_URL . 'admin/subscriptions.php');
        }
    } catch (PDOException $ex) {
        error_log('Subscription admin action failed: ' . $ex->getMessage());
        set_flash('danger', 'The change could not be saved.');
        redirect(BASE_URL . 'admin/subscriptions.php?tab=' . $tab);
    }
}

/* ---------- Data ---------- */
$plans = get_plans($pdo, false);

$q      = clean_text($_GET['q'] ?? '');
$status = in_array($_GET['status'] ?? '', ['pending', 'active', 'expired', 'cancelled'], true) ? $_GET['status'] : '';
$page   = max(1, (int) ($_GET['page'] ?? 1));

$subs = [];
$totalPages = 1;
if ($tab === 'subs') {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(s.full_name LIKE :q1 OR s.email LIKE :q2 OR s.student_number LIKE :q3 OR sub.reference_code LIKE :q4)';
        $like = '%' . $q . '%';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
    }
    if ($status) {
        $where[] = 'sub.status = :status';
        $params[':status'] = $status;
    }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $base = 'FROM subscriptions sub JOIN students s ON s.student_id = sub.student_id JOIN access_plans p ON p.plan_id = sub.plan_id';

    $cnt = $pdo->prepare("SELECT COUNT(*) $base $ws");
    $cnt->execute($params);
    $totalPages = max(1, (int) ceil(((int) $cnt->fetchColumn()) / ADMIN_PER_PAGE));
    $page = min($page, $totalPages);

    $st = $pdo->prepare("SELECT sub.*, s.full_name, s.email, s.student_number, p.plan_name $base $ws
                          ORDER BY sub.created_at DESC, sub.subscription_id DESC
                          LIMIT " . (int) ADMIN_PER_PAGE . ' OFFSET ' . (int) (($page - 1) * ADMIN_PER_PAGE));
    $st->execute($params);
    $subs = $st->fetchAll();
}

$pageTitle = 'Subscriptions';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Subscriptions</h1>
        <p>Manage access plans and see who currently has full-manuscript access.</p>
    </div>
</header>

<div class="demo-banner" role="note"><strong>Prototype payments.</strong> Subscriptions are created by a demo checkout. No real money is collected until a payment gateway is integrated.</div>

<ul class="status-tabs">
    <li><a class="<?= $tab === 'subs' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/subscriptions.php">Subscriptions</a></li>
    <li><a class="<?= $tab === 'plans' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/subscriptions.php?tab=plans">Access plans</a></li>
</ul>

<?php if ($tab === 'plans'): ?>
    <div class="row g-3">
        <?php foreach ($plans as $p): ?>
            <?php $free = (int) $p['full_access'] === 0; ?>
            <div class="col-lg-4">
                <section class="panel h-100">
                    <div class="panel-head">
                        <h2 class="panel-title"><?= e($p['plan_name']) ?></h2>
                        <?= status_badge($p['status']) ?>
                    </div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_plan">
                        <input type="hidden" name="plan_id" value="<?= (int) $p['plan_id'] ?>">

                        <label class="form-label">Plan name</label>
                        <input class="form-control" name="plan_name" required maxlength="80" value="<?= e($p['plan_name']) ?>">

                        <label class="form-label mt-3">Description</label>
                        <input class="form-control" name="description" maxlength="255" value="<?= e($p['description'] ?? '') ?>">

                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label">Price (&#8369;)</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= e(number_format((float) $p['price'], 2, '.', '')) ?>" <?= $free ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Duration (days)</label>
                                <input class="form-control" type="number" min="<?= $free ? 0 : 1 ?>" name="duration_days" value="<?= (int) $p['duration_days'] ?>" <?= $free ? 'readonly' : '' ?>>
                            </div>
                        </div>

                        <label class="form-label mt-3">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active (shown to students)</option>
                            <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option>
                        </select>

                        <p class="form-text mt-2"><?= $free ? 'Free Access: browsing and abstracts only.' : 'Includes full manuscript viewing.' ?></p>
                        <button class="btn btn-primary w-100 mt-2" type="submit">Save plan</button>
                    </form>
                </section>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <form class="panel filter-inline" method="get">
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label" for="q">Search student or reference</label>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, Student ID or SV-...">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['pending', 'active', 'expired', 'cancelled'] as $sv): ?>
                        <option value="<?= $sv ?>" <?= $status === $sv ? 'selected' : '' ?>><?= ucfirst($sv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary align-self-end" type="submit">Filter</button></div>
        </div>
    </form>

    <section class="panel">
        <div class="table-responsive">
            <table class="table align-middle sv-table">
                <thead><tr><th>Student</th><th>Plan</th><th>Reference</th><th>Status</th><th>Start</th><th>Expires</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($subs as $sub): ?>
                    <tr>
                        <td class="cell-title"><?= e($sub['full_name']) ?><small class="d-block text-muted"><?= e($sub['email'] ?? '') ?> &middot; <?= e($sub['student_number']) ?></small></td>
                        <td><?= e($sub['plan_name']) ?></td>
                        <td class="mono"><?= e($sub['reference_code']) ?></td>
                        <td><?= status_badge($sub['status']) ?></td>
                        <td class="text-nowrap"><?= e(format_date($sub['start_date'])) ?></td>
                        <td class="text-nowrap"><?= e(format_date($sub['expiration_date'])) ?></td>
                        <td class="text-end">
                            <?php if (in_array($sub['status'], ['active', 'pending'], true)): ?>
                                <form method="post" class="d-inline" data-confirm="Cancel this subscription? The user loses full-manuscript access immediately.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="subscription_id" value="<?= (int) $sub['subscription_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$subs): ?><p class="empty-note">No subscriptions found.</p><?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination-nav" aria-label="Subscription pages">
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
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
