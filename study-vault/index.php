<?php
require_once __DIR__ . '/includes/bootstrap.php';
$student = require_viewer();

$total = (int) $pdo->query("SELECT COUNT(*) FROM research WHERE status='approved'")->fetchColumn();

$categories = $pdo->query("
    SELECT c.category_id, c.category_name, c.description, COUNT(r.research_id) AS total
      FROM categories c
      LEFT JOIN research r ON r.category_id = c.category_id AND r.status = 'approved'
     GROUP BY c.category_id, c.category_name, c.description
     ORDER BY c.category_name
")->fetchAll();

$recent = $pdo->query("
    SELECT r.research_id, r.title, r.year, r.published_date, r.abstract,
           (r.file_path IS NOT NULL AND r.file_path <> '') AS has_file,
           c.category_name,
           (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR ', ')
              FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
             WHERE ra.research_id = r.research_id) researchers
      FROM research r
      JOIN categories c ON c.category_id = r.category_id
     WHERE r.status = 'approved'
     ORDER BY r.published_date DESC, r.research_id DESC
     LIMIT 6
")->fetchAll();

$subscription = $student ? get_active_subscription($pdo, (int) $student['student_id']) : null;
$plans = $student ? get_plans($pdo) : [];

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="student-hero">
    <div class="container hero-layout">
        <div class="hero-copy">
            <p class="hero-eyebrow"><span class="hero-kicker-symbol" aria-hidden="true">✳</span> Isabela State University <span class="hero-divider">/</span> Ilagan Campus</p>
            <h1>Research has a home.<br><em>Your next idea starts here.</em></h1>
            <p>Discover theses and research produced by the ISU-Ilagan Information Technology community—reviewed, catalogued, and ready to inform what comes next.</p>

            <form class="search-bar live-search-form" action="<?= BASE_URL ?>research.php" method="get" autocomplete="off" role="search">
                <div class="suggest-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                    <label class="visually-hidden" for="home-search">Search the research archive</label>
                    <input class="form-control live-search" id="home-search" type="search" name="q" placeholder="Search titles, authors, keywords..." aria-label="Search research" data-suggest-url="<?= BASE_URL ?>suggestions.php">
                    <div class="suggestions" hidden></div>
                </div>
                <button class="btn btn-primary" type="submit">Search archive <span aria-hidden="true">&rarr;</span></button>
            </form>

            <div class="hero-actions">
                <a href="<?= BASE_URL ?>research.php">Explore all research <span aria-hidden="true">&rarr;</span></a>
                <span class="hero-small">Abstracts are open to the ISU community.</span>
            </div>
        </div>

        <figure class="hero-visual">
            <img src="<?= BASE_URL ?>assets/images/isu.jpg" alt="Isabela State University Ilagan campus sign">
            <figcaption>
                <span class="hero-visual-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4.5 4.5L19 7"/></svg></span>
                <span><strong>Rooted in knowledge</strong><small>ISU research, preserved for what’s next</small></span>
                <span class="hero-visual-edition">EST. 1978</span>
            </figcaption>
            <span class="hero-image-caption">ISABELA STATE UNIVERSITY <i aria-hidden="true"></i> ILAGAN</span>
        </figure>
    </div>
</section>

<section class="archive-proof" aria-label="Repository overview">
    <div class="container proof-grid">
        <div class="proof-count"><span class="proof-number"><?= (int) $total ?></span><span><strong>Approved works</strong><small>In the research archive</small></span></div>
        <div class="proof-item"><span class="proof-mark" aria-hidden="true">01</span><span><strong>Discover</strong><small>Search across student research</small></span></div>
        <div class="proof-item"><span class="proof-mark" aria-hidden="true">02</span><span><strong>Understand</strong><small>Read abstracts and keywords</small></span></div>
        <div class="proof-item"><span class="proof-mark" aria-hidden="true">03</span><span><strong>Go deeper</strong><small>Protected full-text access</small></span></div>
    </div>
</section>

<section class="container section recent-section">
    <div class="section-head">
        <div>
            <p class="eyebrow">New to the collection</p>
            <h2>Recently added research</h2>
            <p>Explore the latest approved work from the ISU-Ilagan IT community.</p>
        </div>
        <a class="section-link" href="<?= BASE_URL ?>research.php">View the full archive <span aria-hidden="true">&rarr;</span></a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty-state"><span class="empty-mark" aria-hidden="true">∅</span><h2>No approved research yet</h2><p>New works will appear here once they have been reviewed.</p></div>
    <?php else: ?>
        <div class="row g-3 g-xl-4">
            <?php foreach ($recent as $r): ?>
                <div class="col-md-6 col-lg-4"><?php include __DIR__ . '/includes/research-card.php'; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="subject-section">
    <div class="container section">
        <div class="section-head">
            <div>
                <p class="eyebrow">Explore by discipline</p>
                <h2>Browse research subjects</h2>
                <p>Start with a topic area and find work related to your question.</p>
            </div>
            <a class="section-link" href="<?= BASE_URL ?>categories.php">All subjects <span aria-hidden="true">&rarr;</span></a>
        </div>
        <?php if ($categories): ?>
            <div class="row g-3">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-6">
                        <a class="category-tile" href="<?= BASE_URL ?>research.php?category=<?= (int) $category['category_id'] ?>">
                            <span class="category-index" aria-hidden="true"><?= str_pad((string) ((int) $category['category_id']), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="category-copy">
                                <strong><?= e($category['category_name']) ?></strong>
                                <?php if (!empty($category['description'])): ?><p><?= e($category['description']) ?></p><?php endif; ?>
                                <span class="category-count"><?= (int) $category['total'] ?> research record<?= (int) $category['total'] === 1 ? '' : 's' ?></span>
                            </span>
                            <span class="category-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No research categories are available yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($student): ?>
<section class="container section plans-section" id="access-plans">
    <div class="section-head">
        <div>
            <p class="eyebrow">Read beyond the abstract</p>
            <h2>Choose your research access</h2>
            <p>Explore openly, then unlock complete manuscripts when a project calls for a closer look.</p>
        </div>
        <span class="access-assurance"><span aria-hidden="true">◈</span> Protected, watermarked reading</span>
    </div>
    <?php require __DIR__ . '/includes/plan-options.php'; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
