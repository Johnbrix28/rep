<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $s = $pdo->prepare('SELECT password FROM administrators WHERE admin_id = ?');
        $s->execute([(int) $_SESSION['admin_id']]);
        $hash = $s->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'The new password must be at least 10 characters and include a letter and a number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The new passwords do not match.';
        } else {
            $pdo->prepare('UPDATE administrators SET password = ? WHERE admin_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
            session_regenerate_id(true);
            log_activity($pdo, 'Changed administrator password');
            set_flash('success', 'Password updated.');
            redirect(BASE_URL . 'admin/settings.php');
        }
    }
}

$admin = current_admin();
$info = [
    'Application'      => SITE_NAME . ' - ' . SITE_TAGLINE,
    'PHP version'      => PHP_VERSION,
    'Payment mode'     => PAYMENT_MODE === 'demo' ? 'DEMO / prototype (no real payments)' : PAYMENT_MODE,
    'Max PDF size'     => (int) (MAX_UPLOAD_BYTES / 1048576) . ' MB (PHP upload_max_filesize: ' . ini_get('upload_max_filesize') . ')',
    'Manuscript store' => 'storage/manuscripts/ (blocked from direct web access)',
    'Student idle timeout' => (int) (STUDENT_IDLE_TIMEOUT / 60) . ' minutes',
    'Admin idle timeout'   => (int) (ADMIN_IDLE_TIMEOUT / 60) . ' minutes',
    'Debug mode'       => DEV_MODE ? 'ON (set DEV_MODE to false for production)' : 'Off',
];

$pageTitle = 'Settings';
require __DIR__ . '/../includes/admin-header.php';
?>

<header class="admin-head">
    <div>
        <h1>Settings</h1>
        <p>Your administrator account and system information.</p>
    </div>
</header>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="panel">
            <h2 class="panel-title">Change password</h2>
            <p class="text-muted">Signed in as <strong><?= e($admin['username']) ?></strong>.</p>
            <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <label class="form-label" for="current_password">Current password</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                <label class="form-label mt-3" for="new_password">New password</label>
                <input class="form-control" type="password" id="new_password" name="new_password" required autocomplete="new-password">
                <div class="form-text">At least 10 characters, with a letter and a number.</div>
                <label class="form-label mt-3" for="confirm_password">Confirm new password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                <button class="btn btn-primary mt-3" type="submit">Update password</button>
            </form>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="panel">
            <h2 class="panel-title">System information</h2>
            <dl class="record-dl">
                <?php foreach ($info as $k => $v): ?>
                    <dt><?= e($k) ?></dt><dd><?= e($v) ?></dd>
                <?php endforeach; ?>
            </dl>
            <div class="note-box">Plan prices and durations are edited under <a href="<?= BASE_URL ?>admin/subscriptions.php?tab=plans">Subscriptions &rarr; Access plans</a>.</div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
