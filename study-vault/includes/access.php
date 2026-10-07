<?php
/**
 * Access rules: subscriptions, plans and manuscript permissions.
 * Every page and the protected document route use these same functions.
 */

/* ============================================================
   Subscriptions
   ============================================================ */

/** Mark every active subscription whose end date has passed as expired. */
function expire_subscriptions(PDO $pdo): void
{
    try {
        $s = $pdo->prepare("UPDATE subscriptions SET status='expired' WHERE status='active' AND expiration_date <= ?");
        $s->execute([date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        error_log('expire_subscriptions failed: ' . $e->getMessage());
    }
}

/**
 * The subscription that grants full-manuscript access RIGHT NOW (or null).
 * Dates are compared directly, so access is never granted on a stale status flag.
 */
function get_active_subscription(PDO $pdo, int $studentId): ?array
{
    $now = date('Y-m-d H:i:s');
    $s = $pdo->prepare(
        "SELECT sub.*, p.plan_name, p.price, p.duration_days, p.full_access
           FROM subscriptions sub
           JOIN access_plans p ON p.plan_id = sub.plan_id
          WHERE sub.student_id = :sid
            AND sub.status = 'active'
            AND p.full_access = 1
            AND sub.start_date <= :now1
            AND sub.expiration_date > :now2
          ORDER BY sub.expiration_date DESC
          LIMIT 1"
    );
    $s->execute([':sid' => $studentId, ':now1' => $now, ':now2' => $now]);
    $row = $s->fetch();
    return $row ?: null;
}

function student_has_full_access(PDO $pdo, int $studentId): bool
{
    return get_active_subscription($pdo, $studentId) !== null;
}

function get_student_subscriptions(PDO $pdo, int $studentId): array
{
    $s = $pdo->prepare(
        'SELECT sub.*, p.plan_name FROM subscriptions sub
           JOIN access_plans p ON p.plan_id = sub.plan_id
          WHERE sub.student_id = ? ORDER BY sub.created_at DESC, sub.subscription_id DESC'
    );
    $s->execute([$studentId]);
    return $s->fetchAll();
}

function get_plans(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM access_plans ' . ($activeOnly ? "WHERE status='active' " : '') . 'ORDER BY sort_order, plan_id';
    return $pdo->query($sql)->fetchAll();
}

function get_plan(PDO $pdo, int $planId): ?array
{
    $s = $pdo->prepare('SELECT * FROM access_plans WHERE plan_id = ?');
    $s->execute([$planId]);
    return $s->fetch() ?: null;
}

/** Unique, human-readable reference code, e.g. SV-261002-9F3A71C2. */
function generate_reference_code(): string
{
    return 'SV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * DEMO checkout: records and activates a plan without charging anyone.
 * If the student already has an active paid plan, the new period starts when
 * the current one ends (renewals stack instead of overlapping).
 * Returns the new subscription row.
 */
function create_demo_subscription(PDO $pdo, int $studentId, array $plan): array
{
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare(
            "SELECT MAX(sub.expiration_date) FROM subscriptions sub
               JOIN access_plans p ON p.plan_id = sub.plan_id
              WHERE sub.student_id = ? AND sub.status = 'active' AND p.full_access = 1 AND sub.expiration_date > ?"
        );
        $s->execute([$studentId, $now]);
        $currentEnd = $s->fetchColumn();

        $start = ($currentEnd && $currentEnd > $now) ? $currentEnd : $now;
        $end   = date('Y-m-d H:i:s', strtotime($start . ' +' . (int) $plan['duration_days'] . ' days'));

        // Retry on the (very unlikely) chance of a duplicate reference code.
        $ref = null;
        for ($i = 0; $i < 5 && $ref === null; $i++) {
            $candidate = generate_reference_code();
            $c = $pdo->prepare('SELECT 1 FROM subscriptions WHERE reference_code = ?');
            $c->execute([$candidate]);
            if (!$c->fetch()) {
                $ref = $candidate;
            }
        }
        if ($ref === null) {
            throw new RuntimeException('Could not generate a reference code.');
        }

        $ins = $pdo->prepare(
            "INSERT INTO subscriptions(student_id, plan_id, status, reference_code, amount_paid, payment_mode, start_date, expiration_date)
             VALUES(?,?, 'active', ?, ?, ?, ?, ?)"
        );
        $ins->execute([$studentId, (int) $plan['plan_id'], $ref, $plan['price'], PAYMENT_MODE, $start, $end]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        $row = $pdo->prepare('SELECT * FROM subscriptions WHERE subscription_id = ?');
        $row->execute([$id]);
        return $row->fetch();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ============================================================
   Manuscript permissions
   ============================================================ */

/**
 * Single source of truth for "may this person read this manuscript?".
 *
 * $student  = fresh students row for a signed-in student, or null
 * $isAdmin  = true when a signed-in administrator is asking
 *
 * Returns ['allowed' => bool, 'reason' => string, 'code' => string]
 * code: ok | login | account | unpublished | no_file | plan
 */
function check_manuscript_access(PDO $pdo, array $research, ?array $student, bool $isAdmin): array
{
    $deny = fn(string $code, string $reason) => ['allowed' => false, 'reason' => $reason, 'code' => $code];

    // Administrators may open any manuscript for management purposes.
    if ($isAdmin) {
        return research_file_exists($research['file_path'] ?? null)
            ? ['allowed' => true, 'reason' => 'Administrator access.', 'code' => 'ok']
            : $deny('no_file', 'No manuscript has been uploaded for this record.');
    }

    // 1. Logged in
    if (!$student) {
        return $deny('login', 'Please log in to continue.');
    }
    // 2. Account approved
    if (($student['status'] ?? '') !== 'approved') {
        return $deny('account', 'Your account is not approved.');
    }
    // 3. Research approved
    if (($research['status'] ?? '') !== 'approved') {
        return $deny('unpublished', 'This research is not available.');
    }
    // 4 + 5. Active, unexpired, paid subscription
    if (!student_has_full_access($pdo, (int) $student['student_id'])) {
        return $deny('plan', 'An active Research Access plan is required to view the complete manuscript.');
    }
    // A file must actually exist
    if (!research_file_exists($research['file_path'] ?? null)) {
        return $deny('no_file', 'No manuscript has been uploaded for this record.');
    }

    return ['allowed' => true, 'reason' => 'Active subscription.', 'code' => 'ok'];
}

/** URL of the protected viewer page for a research record (never the raw file). */
function manuscript_viewer_url(int $researchId): string
{
    return BASE_URL . 'view-manuscript.php?id=' . $researchId;
}

/** Route that streams the PDF after permission checks. */
function secure_document_url(int $researchId): string
{
    return BASE_URL . 'secure-document.php?id=' . $researchId;
}
