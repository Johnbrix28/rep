<?php
/**
 * PROTOTYPE checkout. No money moves and no payment gateway is connected.
 * A real gateway must be integrated before production use.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$student = require_student();

$planId = valid_id($_GET['plan'] ?? $_POST['plan_id'] ?? null);
$plan   = $planId ? get_plan($pdo, $planId) : null;

if (!$plan || $plan['status'] !== 'active' || (int) $plan['full_access'] !== 1 || (float) $plan['price'] <= 0) {
    set_flash('warning', 'That access plan is not available.');
    redirect(BASE_URL . 'index.php#access-plans');
}

$completed = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect(BASE_URL . 'checkout.php?plan=' . $planId);
    }
    if (empty($_POST['confirm_demo'])) {
        set_flash('warning', 'Please confirm that you understand this is a demo checkout.');
        redirect(BASE_URL . 'checkout.php?plan=' . $planId);
    }

    try {
        $completed = create_demo_subscription($pdo, (int) $student['student_id'], $plan);
        log_student_activity($pdo, (int) $student['student_id'],
            'Demo checkout: activated "' . $plan['plan_name'] . '" (' . $completed['reference_code'] . ')');
    } catch (Throwable $ex) {
        error_log('Demo checkout failed: ' . $ex->getMessage());
        set_flash('danger', 'The plan could not be activated. Please try again.');
        redirect(BASE_URL . 'checkout.php?plan=' . $planId);
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <a class="back-link" href="<?= BASE_URL ?>index.php">&larr; Back to home</a>

    <div class="checkout-wrap">
        <?php if ($completed): ?>
            <div class="checkout-card text-center">
                <div class="check-icon">&#10003;</div>
                <h1>Plan activated (demo)</h1>
                <p>Your <strong><?= e($plan['plan_name']) ?></strong> plan is now active. No real payment was taken.</p>
                <dl class="receipt">
                    <div><dt>Reference code</dt><dd class="mono"><?= e($completed['reference_code']) ?></dd></div>
                    <div><dt>Starts</dt><dd><?= e(format_datetime($completed['start_date'])) ?></dd></div>
                    <div><dt>Expires</dt><dd><?= e(format_datetime($completed['expiration_date'])) ?></dd></div>
                </dl>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-primary" href="<?= BASE_URL ?>research.php">Browse research</a>
                    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>profile.php">View profile</a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-card">
                <p class="eyebrow">Checkout</p>
                <h1><?= e($plan['plan_name']) ?></h1>

                <div class="demo-banner">
                    <strong>Prototype only.</strong> This is a demonstration checkout. No card or e-wallet is charged and no payment gateway is connected. A real payment provider must be integrated before production use.
                </div>

                <dl class="receipt">
                    <div><dt>Plan</dt><dd><?= e($plan['plan_name']) ?></dd></div>
                    <div><dt>Access period</dt><dd><?= (int) $plan['duration_days'] ?> days</dd></div>
                    <div><dt>Includes</dt><dd>Abstracts + full manuscript viewing</dd></div>
                    <div class="total"><dt>Price</dt><dd><?= e(format_price($plan['price'])) ?></dd></div>
                </dl>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['plan_id'] ?>">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm_demo" value="1" id="confirm_demo" required>
                        <label class="form-check-label" for="confirm_demo">I understand this is a demo checkout and no real payment is made.</label>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>index.php">Back to home</a>
                        <button class="btn btn-primary flex-grow-1" type="submit">Activate plan (demo)</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
