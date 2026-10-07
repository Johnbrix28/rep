<?php
/** Renders one approved research entry. Expects $r (category_name, researchers, etc.). */
?>
<article class="research-card">
    <div class="card-meta">
        <span class="card-category"><?= e($r['category_name']) ?></span>
        <span class="card-year"><?= e($r['year']) ?></span>
    </div>
    <h3 class="card-title">
        <a href="<?= BASE_URL ?>research-details.php?id=<?= (int) $r['research_id'] ?>"><?= e($r['title']) ?></a>
    </h3>
    <p class="card-authors"><span aria-hidden="true">By</span> <?= e($r['researchers'] ?? 'Researchers not recorded') ?></p>
    <p class="card-abstract"><?= e(excerpt($r['abstract'])) ?></p>
    <div class="card-foot">
        <a class="card-link" href="<?= BASE_URL ?>research-details.php?id=<?= (int) $r['research_id'] ?>">Read abstract <span aria-hidden="true">&rarr;</span></a>
        <?php if (!empty($r['has_file'])): ?>
            <span class="doc-flag" title="Full manuscript requires an active Research Access plan"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> Manuscript</span>
        <?php endif; ?>
    </div>
</article>
