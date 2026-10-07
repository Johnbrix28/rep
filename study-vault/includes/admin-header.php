<?php
$pageTitle  = $pageTitle ?? 'Administrator';
$currentNav = basename($_SERVER['PHP_SELF'] ?? '');
$admin      = current_admin();
$adminName  = trim((string) ($admin['name'] ?: 'Administrator'));
$adminInitial = strtoupper(substr($adminName, 0, 1));

$adminGroups = [
    'Workspace' => [
        ['file' => 'dashboard.php', 'label' => 'Overview', 'icon' => 'grid', 'matches' => ['dashboard.php']],
    ],
    'Repository' => [
        ['file' => 'research-database.php', 'label' => 'Research works', 'icon' => 'book', 'matches' => ['research-database.php', 'research-add.php', 'research-edit.php', 'research-delete.php']],
        ['file' => 'categories.php', 'label' => 'Subject categories', 'icon' => 'layers', 'matches' => ['categories.php']],
    ],
    'People & access' => [
        ['file' => 'students.php', 'label' => 'Student accounts', 'icon' => 'users', 'matches' => ['students.php']],
        ['file' => 'school-records.php', 'label' => 'School records', 'icon' => 'id', 'matches' => ['school-records.php']],
        ['file' => 'subscriptions.php', 'label' => 'Access & plans', 'icon' => 'key', 'matches' => ['subscriptions.php']],
    ],
    'System' => [
        ['file' => 'activity-logs.php', 'label' => 'Activity log', 'icon' => 'activity', 'matches' => ['activity-logs.php']],
        ['file' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings', 'matches' => ['settings.php']],
    ],
];
$adminIcons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.4"/><rect x="14" y="3" width="7" height="7" rx="1.4"/><rect x="3" y="14" width="7" height="7" rx="1.4"/><rect x="14" y="14" width="7" height="7" rx="1.4"/>',
    'book' => '<path d="M5 4.75A2.75 2.75 0 0 1 7.75 2H20v17H7.75A2.75 2.75 0 0 0 5 21.75z"/><path d="M5 4.75v17M9 6h7M9 10h7"/>',
    'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
    'users' => '<path d="M16 21v-1.7a4.3 4.3 0 0 0-4.3-4.3H7.3A4.3 4.3 0 0 0 3 19.3V21"/><circle cx="9.5" cy="7" r="4"/><path d="M17 11a4 4 0 1 0-1.1-7.85M21 21v-1.7a4.3 4.3 0 0 0-3.2-4.15"/>',
    'id' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="10" r="2.1"/><path d="M5.5 16a3 3 0 0 1 6 0M14 9h4M14 13h4M14 17h3"/>',
    'key' => '<circle cx="8" cy="15" r="5"/><path d="m11.5 11.5 8-8 2 2-2 2 2 2-2 2-2-2-4.5 4.5"/>',
    'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1a1.8 1.8 0 0 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3 1.3v.2a1.8 1.8 0 0 1-3.6 0v-.2a1.8 1.8 0 0 0-3-1.3l-.1.1a1.8 1.8 0 0 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-1.3-3H3.4a1.8 1.8 0 0 1 0-3.6h.2a1.8 1.8 0 0 0 1.3-3l-.1-.1a1.8 1.8 0 0 1 2.5-2.5l.1.1a1.8 1.8 0 0 0 3-1.3v-.2a1.8 1.8 0 0 1 3.6 0v.2a1.8 1.8 0 0 0 3 1.3l.1-.1a1.8 1.8 0 0 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 1.3 3h.2a1.8 1.8 0 0 1 0 3.6h-.2a1.8 1.8 0 0 0-1.3 3Z"/>',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123b2b">
    <title><?= e($pageTitle) ?> &middot; Study Vault Admin</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <a class="skip-link" href="#admin-main">Skip to content</a>
    <header class="admin-topbar">
        <div class="admin-topbar-start">
            <button class="btn btn-menu d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open administrator navigation">
                <span class="menu-lines" aria-hidden="true"></span>
            </button>
            <a class="admin-brand" href="<?= BASE_URL ?>admin/dashboard.php">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="" class="admin-brand-mark">
                <span class="admin-brand-name">Study Vault <small>ADMIN</small></span>
            </a>
        </div>
        <div class="admin-topbar-context d-none d-md-flex">
            <span class="context-indicator" aria-hidden="true"></span>
            <span>Repository management</span>
            <span class="context-divider">/</span>
            <span class="context-campus">ISU-Ilagan Campus</span>
        </div>
        <div class="admin-topbar-actions">
            <a class="admin-site-link" href="<?= BASE_URL ?>index.php" aria-label="View the student research archive" title="View student site"><span aria-hidden="true">↗</span> <span class="d-none d-sm-inline">View student site</span><span class="d-sm-none">Archive</span></a>
            <span class="admin-topbar-separator" aria-hidden="true"></span>
            <span class="admin-user-avatar" aria-hidden="true"><?= e($adminInitial) ?></span>
            <span class="admin-who d-none d-md-inline"><?= e($adminName) ?><small>Administrator</small></span>
            <a class="admin-signout" href="<?= BASE_URL ?>admin/logout.php" aria-label="Sign out" title="Sign out">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
            </a>
        </div>
    </header>

    <div class="admin-shell">
        <aside class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="adminSidebar" aria-label="Administrator navigation">
            <div class="offcanvas-header">
                <div class="sidebar-mobile-brand">
                    <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="" class="admin-brand-mark">
                    <span>Admin menu</span>
                </div>
                <button class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
            </div>
            <div class="offcanvas-body">
                <div class="sidebar-caption"><span>ADMINISTRATION</span><span class="sidebar-version">01</span></div>
                <nav aria-label="Workspace sections">
                    <?php foreach ($adminGroups as $groupName => $items): ?>
                        <div class="admin-nav-group">
                            <p class="admin-nav-label"><?= e($groupName) ?></p>
                            <ul class="admin-menu">
                                <?php foreach ($items as $item): ?>
                                    <?php $isActive = in_array($currentNav, $item['matches'], true); ?>
                                    <li>
                                        <a class="<?= $isActive ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/<?= e($item['file']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $adminIcons[$item['icon']] ?></svg>
                                            <span><?= e($item['label']) ?></span>
                                            <?php if ($isActive): ?><span class="nav-active-mark" aria-hidden="true"></span><?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-bottom">
                    <div class="sidebar-secure-mark"><span aria-hidden="true">●</span> Private workspace</div>
                    <span>For authorized ISU personnel</span>
                </div>
            </div>
        </aside>

        <main class="admin-main" id="admin-main">
            <div class="admin-main-inner">
                <?php ob_start(); render_flashes(); $flashMarkup = ob_get_clean(); ?>
                <?php if ($flashMarkup !== ''): ?><div class="admin-flashes"><?= $flashMarkup ?></div><?php endif; ?>
