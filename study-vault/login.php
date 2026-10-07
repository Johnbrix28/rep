<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_student_logged_in()) {
    redirect(BASE_URL . 'index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email    = normalize_email($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $result = authenticate_student($pdo, $email, $password);

        if ($result['ok']) {
            login_student($result['student']);
            log_student_activity($pdo, (int) $result['student']['student_id'], 'Student signed in');
            set_flash('success', 'Welcome to Study Vault, ' . $result['student']['full_name'] . '!');
            redirect(BASE_URL . 'index.php');
        }
        $error = $result['error'];
    }
}
$pageTitle = 'Student sign in';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123b2b">
    <title>Student sign in &middot; Study Vault</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="auth-layout">
        <aside class="auth-story">
            <a class="auth-story-brand" href="<?= BASE_URL ?>login.php">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="Isabela State University seal">
                <span><strong>Study Vault</strong><small>ISU-Ilagan Research Repository</small></span>
            </a>
            <div class="auth-story-copy">
                <p class="auth-story-eyebrow"><span aria-hidden="true">✳</span> A scholarly home for ISU-Ilagan</p>
                <h2>Ideas with impact<br>begin in research.</h2>
                <p>Sign in to explore the work of your university community and find the spark for what you’ll study next.</p>
            </div>
            <div class="auth-story-footer"><span class="auth-story-rule"></span><span>Knowledge grows when it is shared.</span><small>ISABELA STATE UNIVERSITY <i aria-hidden="true">&middot;</i> EST. 1978</small></div>
            <span class="auth-story-watermark" aria-hidden="true">ISU</span>
        </aside>

        <section class="auth-card" aria-labelledby="login-heading">
            <div class="login-brand">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="">
                <div><strong>Study Vault</strong><span>Student &amp; alumni access</span></div>
            </div>

            <p class="auth-kicker">Welcome back</p>
            <h1 id="login-heading">Sign in to your account</h1>
            <p class="login-sub">Use your verified email address to continue to the repository.</p>

            <?php render_flashes(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" type="email" id="email" name="email" required autofocus
                           autocomplete="username" placeholder="you@isu.edu.ph" value="<?= e($email) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn btn-primary w-100 auth-submit" type="submit">Sign in <span aria-hidden="true">&rarr;</span></button>
            </form>

            <div class="auth-divider"><span>NEW TO STUDY VAULT?</span></div>
            <a class="btn btn-outline-secondary w-100" href="<?= BASE_URL ?>verify.php">Verify your Student ID</a>
            <p class="auth-help">Verification uses the email address on file with the university.</p>
            <a class="login-back" href="<?= BASE_URL ?>admin/login.php">Administrator sign in <span aria-hidden="true">&rarr;</span></a>
        </section>
    </div>
</div>
</body>
</html>
