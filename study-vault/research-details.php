<?php
require_once __DIR__ . '/includes/bootstrap.php';

$student = require_viewer();          // approved student, or null for an administrator
$isAdmin = ($student === null);

$id = valid_id($_GET['id'] ?? null);
$backUrl = BASE_URL . ($isAdmin ? 'admin/research-database.php' : 'research.php');
if (!$id) {
    redirect($backUrl);
}

$stmt = $pdo->prepare('SELECT r.*, c.category_name, p.program_name
                         FROM research r
                         JOIN categories c ON c.category_id = r.category_id
                         LEFT JOIN programs p ON p.program_id = r.program_id
                        WHERE r.research_id = ?');
$stmt->execute([$id]);
$record = $stmt->fetch();

// Students can only ever see APPROVED research. Anything else looks like "not found".
if (!$record || (!$isAdmin && $record['status'] !== 'approved')) {
    set_flash('warning', 'Research record not found.');
    redirect($backUrl);
}

$authors = get_research_authors($pdo, $id);
$access  = check_manuscript_access($pdo, $record, $student, $isAdmin);
$hasFile = research_file_exists($record['file_path']);
$subscription = $student ? get_active_subscription($pdo, (int) $student['student_id']) : null;

if (!$isAdmin) {
    $pdo->prepare('UPDATE research SET view_count = view_count + 1 WHERE research_id = ?')->execute([$id]);
}

$relatedStmt = $pdo->prepare("SELECT research_id, title, year FROM research
                               WHERE category_id = ? AND research_id <> ? AND status = 'approved'
                               ORDER BY published_date DESC LIMIT 4");
$relatedStmt->execute([$record['category_id'], $id]);
$related = $relatedStmt->fetchAll();

$pageTitle = $record['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="container section record-section">
    <a class="back-link" href="<?= e($backUrl) ?>"><span aria-hidden="true">&larr;</span> Back to <?= $isAdmin ? 'repository management' : 'research archive' ?></a>

    <div class="record-layout">
        <article class="record-main">
            <header class="record-header">
                <p class="record-meta">
                    <a href="<?= BASE_URL ?>research.php?category=<?= (int) $record['category_id'] ?>"><?= e($record['category_name']) ?></a>
                    <span class="record-meta-divider" aria-hidden="true">/</span>
                    <span><?= e($record['year']) ?></span>
                    <?php if ($isAdmin): ?> <span class="record-meta-divider" aria-hidden="true">/</span> <?= status_badge($record['status']) ?><?php endif; ?>
                </p>
                <h1 class="record-title"><?= e($record['title']) ?></h1>
                <p class="record-deck">An approved research work from the Isabela State University Information Technology community.</p>
            </header>

            <dl class="info-grid">
                <div class="info-authors"><dt>Researchers</dt><dd><?= e($authors ? implode(', ', $authors) : 'Not recorded') ?></dd></div>
                <div><dt>Subject area</dt><dd><?= e($record['category_name']) ?></dd></div>
                <div><dt>Publication year</dt><dd><?= e($record['year']) ?></dd></div>
                <div><dt>Program</dt><dd><?= e($record['program_name'] ?? 'Information Technology') ?></dd></div>
                <div><dt>Published</dt><dd><?= e(format_date($record['published_date'])) ?></dd></div>
            </dl>

            <section class="record-content-block" aria-labelledby="abstract-heading">
                <p class="record-section-kicker">Study overview</p>
                <h2 class="record-heading" id="abstract-heading">Abstract</h2>
                <div class="record-abstract">
                    <?= trim((string) $record['abstract']) !== '' ? nl2br(e($record['abstract'])) : '<p class="text-muted">No abstract was recorded.</p>' ?>
                </div>
            </section>

            <?php if ($tags = keyword_list($record['keywords'])): ?>
                <section class="record-content-block record-keywords" aria-labelledby="keywords-heading">
                    <p class="record-section-kicker">Index terms</p>
                    <h2 class="record-heading" id="keywords-heading">Keywords</h2>
                    <ul class="tag-list">
                        <?php foreach ($tags as $tag): ?><li><?= e($tag) ?></li><?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </article>

        <aside class="access-panel" aria-labelledby="access-heading">
            <div class="access-panel-head"><span class="access-lock" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span><span>REPOSITORY ACCESS</span></div>
            <h2 class="record-heading mt-0" id="access-heading">Read this work</h2>
            <p class="access-intro">Abstracts can be read freely. Full manuscripts are protected and available to authorized readers.</p>

            <ul class="access-list">
                <li class="ok"><span class="dot"></span> Abstract available</li>
                <?php if (!$hasFile): ?>
                    <li class="off"><span class="dot"></span> No manuscript uploaded</li>
                <?php elseif ($access['allowed']): ?>
                    <li class="ok"><span class="dot"></span> Full manuscript available</li>
                    <li class="ok"><span class="dot"></span> Protected reader access</li>
                <?php else: ?>
                    <li class="off"><span class="dot"></span> Full manuscript locked</li>
                <?php endif; ?>
            </ul>

            <?php if ($hasFile && $access['allowed']): ?>
                <a class="btn btn-primary w-100" href="<?= e(manuscript_viewer_url($id)) ?>">Open protected reader <span aria-hidden="true">&rarr;</span></a>
                <p class="access-note">
                    <?= $isAdmin
                        ? 'Administrator access.'
                        : 'Your plan: <strong>' . e($subscription['plan_name'] ?? '') . '</strong> &middot; until ' . e(format_date($subscription['expiration_date'] ?? null)) ?>
                </p>
            <?php elseif ($hasFile): ?>
                <div class="lock-box">
                    <h3>Full-text access required</h3>
                    <p>Read the abstract and research information for free. An active Research Access plan is required for the complete manuscript.</p>
                    <a class="btn btn-primary w-100" href="<?= BASE_URL ?>index.php#access-plans">View access plans <span aria-hidden="true">&rarr;</span></a>
                </div>
            <?php else: ?>
                <p class="access-note">A full manuscript has not been uploaded for this record yet.</p>
            <?php endif; ?>
            <div class="access-footnote"><span aria-hidden="true">✳</span> Full-text reading is watermarked for responsible access.</div>
        </aside>
    </div>

    <?php if ($related): ?>
        <section class="related" aria-labelledby="related-heading">
            <div class="related-head"><div><p class="eyebrow">Continue exploring</p><h2 id="related-heading">More in this subject</h2></div><a href="<?= BASE_URL ?>research.php?category=<?= (int) $record['category_id'] ?>">View subject <span aria-hidden="true">&rarr;</span></a></div>
            <ul class="related-list">
                <?php foreach ($related as $item): ?>
                    <li>
                        <a href="<?= BASE_URL ?>research-details.php?id=<?= (int) $item['research_id'] ?>"><?= e($item['title']) ?></a>
                        <span><?= e($item['year']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
