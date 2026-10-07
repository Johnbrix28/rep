<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$one = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
$now = date('Y-m-d H:i:s');

$stats = [
    'research_total'    => $one('SELECT COUNT(*) FROM research'),
    'research_pending'  => $one("SELECT COUNT(*) FROM research WHERE status='pending'"),
    'research_approved' => $one("SELECT COUNT(*) FROM research WHERE status='approved'"),
    'research_rejected' => $one("SELECT COUNT(*) FROM research WHERE status='rejected'"),
    'students_total'    => $one('SELECT COUNT(*) FROM students'),
    'accounts_pending'  => $one("SELECT COUNT(*) FROM students WHERE status='pending'"),
    'accounts_approved' => $one("SELECT COUNT(*) FROM students WHERE status='approved'"),
];
$activeSubs = $pdo->prepare("SELECT COUNT(DISTINCT sub.student_id) FROM subscriptions sub JOIN access_plans p ON p.plan_id=sub.plan_id
                              WHERE sub.status='active' AND p.full_access=1 AND sub.start_date <= ? AND sub.expiration_date > ?");
$activeSubs->execute([$now, $now]);
$stats['active_subs'] = (int) $activeSubs->fetchColumn();

$pendingResearch = $pdo->query("
    SELECT r.research_id, r.title, r.submitted_at, c.category_name
      FROM research r JOIN categories c ON c.category_id = r.category_id
     WHERE r.status = 'pending' ORDER BY r.submitted_at DESC LIMIT 5")->fetchAll();

$pendingAccounts = $pdo->query("
    SELECT student_id, full_name, email, student_number, user_type, created_at
      FROM students WHERE status='pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();

$byCategory = $pdo->query("
    SELECT c.category_name, COUNT(r.research_id) total
      FROM categories c LEFT JOIN research r ON r.category_id = c.category_id AND r.status='approved'
     GROUP BY c.category_id, c.category_name ORDER BY total DESC")->fetchAll();
$maxCat = max(1, ...array_map(fn($c) => (int) $c['total'], $byCategory ?: [['total' => 1]]));

$cards = [
    ['Total Research',        $stats['research_total'],    '',        'research-database.php'],
    ['Pending Research',      $stats['research_pending'],  'warn',    'research-database.php?status=pending'],
    ['Approved Research',     $stats['research_approved'], 'good',    'research-database.php?status=approved'],
    ['Rejected Research',     $stats['research_rejected'], 'bad',     'research-database.php?status=rejected'],
    ['Total Students',        $stats['students_total'],    '',        'students.php'],
    ['Pending Accounts',      $stats['accounts_pending'],  'warn',    'students.php?status=pending'],
    ['Approved Accounts',     $stats['accounts_approved'], 'good',    'students.php?status=approved'],
    ['Active Subscriptions',  $stats['active_subs'],       'good',    'subscriptions.php'],
];

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Dashboard</h1>
        <p>Overview of the Study Vault repository, accounts and subscriptions.</p>
    </div>
    <a class="btn btn-primary" href="<?= BASE_URL ?>admin/research-add.php">Upload manuscript</a>
</header>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $tone, $link]): ?>
        <div class="col-6 col-lg-3">
            <a class="stat-card <?= e($tone) ?>" href="<?= BASE_URL ?>admin/<?= e($link) ?>">
                <p class="stat-label"><?= e($label) ?></p>
                <p class="stat-value"><?= (int) $value ?></p>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="panel h-100">
            <div class="panel-head">
                <h2 class="panel-title">Research awaiting review</h2>
                <a href="<?= BASE_URL ?>admin/research-database.php?status=pending">See all</a>
            </div>
            <?php if (!$pendingResearch): ?>
                <p class="empty-note">Nothing is waiting for review.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($pendingResearch as $r): ?>
                        <li>
                            <div>
                                <strong><?= e($r['title']) ?></strong>
                                <span><?= e($r['category_name']) ?> &middot; uploaded <?= e(format_date($r['submitted_at'])) ?></span>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>admin/research-edit.php?id=<?= (int) $r['research_id'] ?>">Review</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="panel h-100">
            <div class="panel-head">
                <h2 class="panel-title">Accounts awaiting approval</h2>
                <a href="<?= BASE_URL ?>admin/students.php?status=pending">See all</a>
            </div>
            <?php if (!$pendingAccounts): ?>
                <p class="empty-note">No pending accounts.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($pendingAccounts as $a): ?>
                        <li>
                            <div>
                                <strong><?= e($a['full_name']) ?></strong>
                                <span><?= e($a['email'] ?? 'no email') ?> &middot; ID <?= e($a['student_number']) ?> &middot; <?= e(user_type_label($a['user_type'])) ?></span>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>admin/students.php?status=pending">Review</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-12">
        <section class="panel">
            <h2 class="panel-title">Approved research by category</h2>
            <?php foreach ($byCategory as $c): ?>
                <div class="bar-row">
                    <span class="bar-label"><?= e($c['category_name']) ?></span>
                    <div class="bar-track"><div class="bar-fill" style="width: <?= (int) round(((int) $c['total'] / $maxCat) * 100) ?>%"></div></div>
                    <span class="bar-value"><?= (int) $c['total'] ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$byCategory): ?><p class="empty-note">No categories yet.</p><?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
