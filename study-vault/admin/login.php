<?php
/**
 * Administrator sign-in.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect(BASE_URL . 'admin/dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Try signing in again.';
    } else {
        $username = clean_text($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Enter both your username and password.';
        } else {
            $key = 'admin:' . strtolower($username);
            try {
                if (login_throttled($pdo, $key)) {
                    $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
                } else {
                    $stmt = $pdo->prepare('SELECT * FROM administrators WHERE username = ? LIMIT 1');
                    $stmt->execute([$username]);
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($password, $admin['password'])) {
                        clear_login_failures($pdo, $key);
                        login_admin($admin);
                        log_activity($pdo, 'Signed in');
                        set_flash('success', 'Signed in as ' . $admin['name'] . '.');
                        redirect(BASE_URL . 'admin/dashboard.php');
                    }
                    // Same message for unknown user and wrong password.
                    record_login_failure($pdo, $key);
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $ex) {
                error_log('Admin login failed: ' . $ex->getMessage());
                $error = 'Sign-in is unavailable right now. Try again in a moment.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#123b2b">
<title>Administrator sign in &middot; <?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page auth-admin-page">
<div class="auth-wrap">
    <div class="auth-layout">
        <aside class="auth-story">
            <a class="auth-story-brand" href="<?= BASE_URL ?>admin/login.php">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="Isabela State University seal">
                <span><strong>Study Vault</strong><small>Repository administration</small></span>
            </a>
            <div class="auth-story-copy">
                <p class="auth-story-eyebrow"><span aria-hidden="true">✳</span> Authorized staff workspace</p>
                <h2>Steward the work.<br>Strengthen the record.</h2>
                <p>Manage the university research collection, review submissions, and help keep every record trustworthy.</p>
            </div>
            <div class="auth-story-footer"><span class="auth-story-rule"></span><span>Private access for authorized personnel.</span><small>ISABELA STATE UNIVERSITY <i aria-hidden="true">&middot;</i> ILAGAN CAMPUS</small></div>
            <span class="auth-story-watermark" aria-hidden="true">ISU</span>
        </aside>

        <section class="auth-card" aria-labelledby="admin-login-heading">
            <div class="login-brand">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="">
                <div><strong>Study Vault</strong><span>Administrator access</span></div>
            </div>
            <p class="auth-kicker">Secure workspace</p>
            <h1 id="admin-login-heading">Administrator sign in</h1>
            <p class="login-sub">Sign in with your administrator credentials to manage the repository.</p>

            <?php render_flashes(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>admin/login.php" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Administrator username</label>
                    <input class="form-control" type="text" id="username" name="username" required autofocus
                           autocomplete="username" value="<?= e($username) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn btn-primary w-100 auth-submit" type="submit">Enter workspace <span aria-hidden="true">&rarr;</span></button>
            </form>
            <a class="login-back" href="<?= BASE_URL ?>login.php"><span aria-hidden="true">&larr;</span> Return to student sign in</a>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
