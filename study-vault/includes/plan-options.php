<div class="row g-4 justify-content-center">
    <?php foreach ($plans as $plan): ?>
        <?php
        $isFree    = (int) $plan['full_access'] === 0 || (float) $plan['price'] <= 0;
        $isCurrent = $subscription && (int) $subscription['plan_id'] === (int) $plan['plan_id'];
        $isFeatured = (int) $plan['sort_order'] === 2;
        ?>
        <div class="col-md-6 col-lg-4">
            <section class="plan-card <?= $isCurrent ? 'is-current' : '' ?> <?= $isFeatured ? 'is-featured' : '' ?>" aria-label="<?= e($plan['plan_name']) ?> access plan">
                <?php if ($isCurrent): ?>
                    <span class="plan-ribbon">Current plan</span>
                <?php elseif (!$isFree): ?>
                    <span class="plan-ribbon">Full-text access</span>
                <?php endif; ?>
                <h2><?= e($plan['plan_name']) ?></h2>
                <p class="plan-price">
                    <?= e(format_price($plan['price'])) ?>
                    <?php if (!$isFree): ?><small>/ <?= (int) $plan['duration_days'] ?> days</small><?php endif; ?>
                </p>
                <p class="plan-desc"><?= e($plan['description']) ?></p>
                <ul class="plan-features">
                    <li>Browse approved research</li>
                    <li>Search by title, author, keyword and more</li>
                    <li>Read abstracts and research details</li>
                    <?php if (!$isFree): ?>
                        <li class="strong">Protected full manuscript viewing</li>
                        <li class="strong"><?= (int) $plan['duration_days'] ?> days of access</li>
                    <?php else: ?>
                        <li class="muted">Full manuscripts not included</li>
                    <?php endif; ?>
                </ul>
                <?php if ($isFree): ?>
                    <span class="btn btn-outline-secondary w-100 disabled"><?= $subscription ? 'Included with every account' : 'Your current plan' ?></span>
                <?php else: ?>
                    <a class="btn btn-primary w-100" href="<?= BASE_URL ?>checkout.php?plan=<?= (int) $plan['plan_id'] ?>">
                        <?= $subscription ? 'Extend with this plan' : 'Get this plan' ?> <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endif; ?>
            </section>
        </div>
    <?php endforeach; ?>
</div>
