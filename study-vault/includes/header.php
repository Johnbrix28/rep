<?php
$pageTitle   = $pageTitle ?? SITE_NAME;
$isStudent   = is_student_logged_in();
$isAdminView = !$isStudent && is_logged_in();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$activeArchive = in_array($currentPage, ['research.php', 'research-details.php'], true);
$activeCategories = $currentPage === 'categories.php';
$activePlans = $currentPage === 'plans.php';
$studentIdentity = $isStudent ? current_student() : [];
$studentName = trim((string) ($studentIdentity['name'] ?? ''));
$studentInitial = $studentName !== '' ? strtoupper(substr($studentName, 0, 1)) : 'S';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123b2b">
    <title><?= e($pageTitle) ?> &middot; <?= e(SITE_NAME) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="student-body">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="institution-strip">
        <div class="container">
            <span><span class="institution-dot" aria-hidden="true"></span> Isabela State University <span class="strip-divider">/</span> Ilagan Campus</span>
            <span class="institution-strip-note">Information Technology Research Repository</span>
        </div>
    </div>

    <header class="site-header">
        <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
            <div class="container">
                <a class="navbar-brand" href="<?= BASE_URL ?>index.php" aria-label="Study Vault home">
                    <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="Isabela State University seal" class="brand-mark">
                    <span class="brand-text">
                        <strong>Study Vault</strong>
                        <small>ISU-Ilagan Research Repository</small>
                    </span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation" aria-controls="siteNavigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="siteNavigation">
                    <ul class="navbar-nav mx-lg-auto">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeArchive ? 'active' : '' ?>" <?= $activeArchive ? 'aria-current="page"' : '' ?> href="<?= BASE_URL ?>research.php">Research archive</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeCategories ? 'active' : '' ?>" <?= $activeCategories ? 'aria-current="page"' : '' ?> href="<?= BASE_URL ?>categories.php">Browse subjects</a>
                        </li>
                        <?php if (!$isAdminView): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $activePlans ? 'active' : '' ?>" <?= $activePlans ? 'aria-current="page"' : '' ?> href="<?= BASE_URL ?>plans.php">Access plans</a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="site-actions">
                        <?php if ($isStudent): ?>
                            <a class="account-pill" href="<?= BASE_URL ?>profile.php" aria-label="View account profile">
                                <span class="account-avatar" aria-hidden="true"><?= e($studentInitial) ?></span>
                                <span class="account-copy"><small>Signed in as</small><strong><?= e($studentName !== '' ? $studentName : 'Student') ?></strong></span>
                            </a>
                            <a class="btn btn-site-quiet" href="<?= BASE_URL ?>logout.php">Sign out</a>
                        <?php elseif ($isAdminView): ?>
                            <a class="btn btn-site-quiet" href="<?= BASE_URL ?>admin/dashboard.php">Admin workspace</a>
                            <a class="btn btn-site-primary" href="<?= BASE_URL ?>admin/logout.php">Sign out</a>
                        <?php else: ?>
                            <a class="btn btn-site-quiet" href="<?= BASE_URL ?>verify.php">Verify Student ID</a>
                            <a class="btn btn-site-primary" href="<?= BASE_URL ?>login.php">Sign in <span aria-hidden="true">&rarr;</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main id="main">
        <?php ob_start(); render_flashes(); $flashMarkup = ob_get_clean(); ?>
        <?php if ($flashMarkup !== ''): ?><div class="container flash-area"><?= $flashMarkup ?></div><?php endif; ?>
