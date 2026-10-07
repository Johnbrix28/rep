<?php
require_once __DIR__ . '/includes/bootstrap.php';
$student = require_student();

$subscription = get_active_subscription($pdo, (int) $student['student_id']);
$history      = get_student_subscriptions($pdo, (int) $student['student_id']);
$profileInitial = strtoupper(substr((string) $student['full_name'], 0, 1));

$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>

<div class="container section profile-section">
    <a class="back-link" href="<?= BASE_URL ?>index.php"><span aria-hidden="true">&larr;</span> Repository home</a>

    <header class="page-head profile-page-head">
        <div>
            <p class="eyebrow">Account &amp; reading access</p>
            <h1>My profile</h1>
            <p>Review your verified identity and the access currently linked to your account.</p>
        </div>
        <span class="verified-account"><span aria-hidden="true">✓</span> Verified ISU account</span>
    </header>

    <div class="profile-hero-card">
        <span class="profile-avatar" aria-hidden="true"><?= e($profileInitial) ?></span>
        <div class="profile-hero-copy">
            <strong><?= e($student['full_name']) ?></strong>
            <span><?= e($student['program_name'] ?? 'Information Technology') ?> <i aria-hidden="true">&middot;</i> <?= e(user_type_label($student['user_type'])) ?></span>
        </div>
        <div class="profile-id"><small>STUDENT ID</small><strong><?= e($student['student_number']) ?></strong></div>
    </div>

    <div class="row g-4 profile-cards">
        <div class="col-lg-7">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Account details</h2><span class="profile-secure-label">On file with Study Vault</span></div>
                <dl class="profile-grid">
                    <div><dt>Full name</dt><dd><?= e($student['full_name']) ?></dd></div>
                    <div><dt>Email address</dt><dd><?= e($student['email']) ?></dd></div>
                    <div><dt>Student ID</dt><dd><?= e($student['student_number']) ?></dd></div>
                    <div><dt>User type</dt><dd><?= e(user_type_label($student['user_type'])) ?></dd></div>
                    <div><dt>Program</dt><dd><?= e($student['program_name'] ?? 'Information Technology') ?></dd></div>
                    <div><dt>Account status</dt><dd><?= status_badge($student['status']) ?></dd></div>
                </dl>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel profile-access-card">
                <div class="panel-head"><h2 class="panel-title">Manuscript access</h2><span class="access-status-dot" aria-hidden="true"></span></div>
                <?php if ($subscription): ?>
                    <p class="plan-now"><?= e($subscription['plan_name']) ?> <?= status_badge('active') ?></p>
                    <p class="text-muted mb-1">Protected full-text reading is enabled.</p>
                    <p class="profile-expiry"><strong>Access through</strong> <?= e(format_datetime($subscription['expiration_date'])) ?></p>
                    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>index.php#access-plans">Extend access <span aria-hidden="true">&rarr;</span></a>
                <?php else: ?>
                    <p class="plan-now">Abstract access</p>
                    <p class="text-muted">You can search the archive and read research abstracts. Choose an access plan to view complete manuscripts.</p>
                    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>index.php#access-plans">Explore access plans <span aria-hidden="true">&rarr;</span></a>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <section class="panel profile-history-panel">
        <div class="panel-head"><div><p class="eyebrow">Your access activity</p><h2 class="panel-title">Subscription history</h2></div><span class="history-count"><?= count($history) ?> record<?= count($history) === 1 ? '' : 's' ?></span></div>
        <?php if (!$history): ?>
            <p class="empty-note">No subscriptions yet. Your access history will appear here.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle sv-table">
                    <thead><tr><th>Plan</th><th>Reference</th><th>Status</th><th>Start</th><th>Expires</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= e($h['plan_name']) ?></td>
                            <td class="mono"><?= e($h['reference_code']) ?></td>
                            <td><?= status_badge($h['status']) ?></td>
                            <td class="text-nowrap"><?= e(format_date($h['start_date'])) ?></td>
                            <td class="text-nowrap"><?= e(format_date($h['expiration_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
