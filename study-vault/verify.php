<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_student_logged_in()) {
    redirect(BASE_URL . 'index.php');
}

$error     = '';
$errors    = [];
$showLogin = false;      // show a "Log in" button next to the error
$studentId = '';

// The current step lives on the SERVER (session). The browser cannot jump ahead.
$step   = verify_current_step();
$state  = verify_state();
$record = null;

if ($step > 1) {
    $record = get_school_record($pdo, (int) $state['record_id']);
    if (!$record) {
        verify_state_clear();
        $step = 1;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($action === 'restart') {
        verify_state_clear();
        redirect(BASE_URL . 'verify.php');

    /* ---------- STEP 1: Student ID -> send code ---------- */
    } elseif ($action === 'send_code') {
        $studentId = normalize_student_id($_POST['student_id'] ?? '');

        if (verify_lookup_throttled($pdo)) {
            $error = 'Too many attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
        } elseif (!valid_student_id_format($studentId)) {
            $error = 'Enter a valid Student ID (for example 24-01048).';
        } else {
            $rec = find_school_record($pdo, $studentId);

            if (!$rec) {
                verify_lookup_failed($pdo);
                $error = 'Student ID not found in the school database.';
            } elseif (!school_record_eligibility($rec)['ok']) {
                verify_lookup_failed($pdo);
                $error = 'Not eligible for Study Vault. Only enrolled students and alumni of the IT programs can register.';
            } else {
                $acct = school_record_account_state($pdo, $rec);

                if ($acct['state'] === 'linked') {
                    $error = 'This Student ID is already linked to an account. Please log in.';
                    $showLogin = true;
                } elseif ($acct['state'] === 'email_taken') {
                    $error = 'The school email address for this Student ID is already used by another account. Please contact the administrator.';
                } elseif (verification_hourly_limit_reached($pdo, (int) $rec['id'])) {
                    $error = 'Too many codes were requested for this Student ID. Please try again in an hour.';
                } elseif (($wait = verification_resend_wait($pdo, (int) $rec['id'])) > 0) {
                    $error = 'A code was just sent. Please wait ' . $wait . ' seconds before requesting another one.';
                } else {
                    $sent = issue_and_send_code($pdo, $rec);
                    if ($sent['ok']) {
                        verify_state_set(['record_id' => (int) $rec['id'], 'sent_at' => time()]);
                        log_student_activity($pdo, null, 'Verification code sent for Student ID ' . $rec['student_id']);
                        set_flash('success', 'A 6-digit verification code was sent to ' . mask_email((string) $rec['email']) . '. It expires in ' . VERIFY_CODE_TTL_MINUTES . ' minutes.');
                        redirect(BASE_URL . 'verify.php');
                    }
                    $error = $sent['error'];
                }
            }
        }

    /* ---------- STEP 2: resend / verify code ---------- */
    } elseif ($action === 'resend' && $step === 2 && $record) {
        if (verification_hourly_limit_reached($pdo, (int) $record['id'])) {
            $error = 'Too many codes were requested for this Student ID. Please try again in an hour.';
        } elseif (($wait = verification_resend_wait($pdo, (int) $record['id'])) > 0) {
            $error = 'Please wait ' . $wait . ' seconds before requesting another code.';
        } else {
            $sent = issue_and_send_code($pdo, $record);
            if ($sent['ok']) {
                verify_state_set(['record_id' => (int) $record['id'], 'sent_at' => time()]);
                set_flash('success', 'A new code was sent to ' . mask_email((string) $record['email']) . '. Earlier codes no longer work.');
                redirect(BASE_URL . 'verify.php');
            }
            $error = $sent['error'];
        }

    } elseif ($action === 'verify_code' && $step === 2 && $record) {
        $result = check_verification_code($pdo, (int) $record['id'], (string) ($_POST['code'] ?? ''));

        if ($result['ok']) {
            session_regenerate_id(true);
            verify_state_set(['record_id' => (int) $record['id'], 'verified_at' => time()]);
            set_flash('success', 'Email verified. Now create your password.');
            redirect(BASE_URL . 'verify.php');
        }
        $error = $result['error'];

    /* ---------- STEP 3: create password ---------- */
    } elseif ($action === 'create_account' && $step === 3 && $record) {
        $result = create_verified_account(
            $pdo,
            (int) $record['id'],
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );

        if ($result['ok']) {
            verify_state_clear();
            set_flash('success', 'Your account was created. Log in with ' . mask_email((string) $record['email']) . ' and your new password.');
            redirect(BASE_URL . 'login.php');
        }
        $errors = $result['errors'];
        if (!empty($errors['general'])) {
            $error = $errors['general'];
            $showLogin = stripos($error, 'log in') !== false;
        }
    } else {
        $error = 'That action is not available right now.';
    }

    // The state may have changed while handling the request (e.g. a code was voided).
    $step = verify_current_step();
    if ($step > 1 && !$record) {
        $record = get_school_record($pdo, (int) (verify_state()['record_id'] ?? 0));
    }
}

$resendWait = ($step === 2 && $record) ? verification_resend_wait($pdo, (int) $record['id']) : 0;
$pageTitle  = 'Verify your Student ID';

function verify_field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123b2b">
    <title>Verify your Student ID &middot; Study Vault</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="auth-layout auth-layout-wide">
        <aside class="auth-story">
            <a class="auth-story-brand" href="<?= BASE_URL ?>login.php">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="Isabela State University seal">
                <span><strong>Study Vault</strong><small>ISU-Ilagan Research Repository</small></span>
            </a>
            <div class="auth-story-copy">
                <p class="auth-story-eyebrow"><span aria-hidden="true">✳</span> Student &amp; alumni access</p>
                <h2>Your university ID<br>opens the archive.</h2>
                <p>Verify your identity using the school record on file. A one-time code will be sent to your university email address.</p>
            </div>
            <div class="auth-story-footer"><span class="auth-story-rule"></span><span>Secure access begins with verification.</span><small>ISABELA STATE UNIVERSITY <i aria-hidden="true">&middot;</i> ILAGAN CAMPUS</small></div>
            <span class="auth-story-watermark" aria-hidden="true">ISU</span>
        </aside>

        <section class="auth-card auth-card-wide" aria-labelledby="verify-heading">
            <div class="login-brand">
                <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="">
                <div><strong>Study Vault</strong><span>Student identity verification</span></div>
            </div>

            <p class="auth-kicker">Secure account setup <span class="auth-kicker-separator">/</span> Step <?= (int) $step ?> of 3</p>
            <ol class="verify-steps" aria-label="Verification progress">
                <li class="<?= $step > 1 ? 'done' : 'active' ?>" <?= $step === 1 ? 'aria-current="step"' : '' ?>><span>01</span><small>Student ID</small></li>
                <li class="<?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>" <?= $step === 2 ? 'aria-current="step"' : '' ?>><span>02</span><small>Email code</small></li>
                <li class="<?= $step === 3 ? 'active' : '' ?>" <?= $step === 3 ? 'aria-current="step"' : '' ?>><span>03</span><small>Password</small></li>
            </ol>

            <?php render_flashes(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                    <?php if ($showLogin): ?>
                        <div class="mt-2"><a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>login.php">Go to sign in</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <p class="auth-kicker">First, locate your school record</p>
                <h1 id="verify-heading">Verify your Student ID</h1>
                <p class="login-sub">Enter your Student ID. We’ll send a 6-digit code to the email address the school has on file.</p>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_code">
                    <div class="mb-3">
                        <label class="form-label" for="student_id">Student ID</label>
                        <input class="form-control" id="student_id" name="student_id" required autofocus maxlength="50"
                               autocomplete="off" placeholder="e.g. 24-01048" value="<?= e($studentId) ?>">
                    </div>
                    <p class="verify-privacy"><span aria-hidden="true">▣</span> Your school email is never changed or exposed in this step.</p>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">Send verification code <span aria-hidden="true">&rarr;</span></button>
                </form>

            <?php elseif ($step === 2 && $record): ?>
                <p class="auth-kicker">Check your inbox</p>
                <h1 id="verify-heading">Enter your code</h1>
                <p class="login-sub">A 6-digit code was sent to <strong><?= e(mask_email((string) $record['email'])) ?></strong>. It expires in <?= (int) VERIFY_CODE_TTL_MINUTES ?> minutes.</p>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_code">
                    <div class="mb-3">
                        <label class="form-label" for="code">6-digit verification code</label>
                        <input class="form-control code-input" id="code" name="code" required autofocus maxlength="6"
                               inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000">
                    </div>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">Verify email <span aria-hidden="true">&rarr;</span></button>
                </form>

                <div class="verify-secondary-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resend">
                        <button class="btn btn-link p-0" id="resendBtn" type="submit" <?= $resendWait > 0 ? 'disabled' : '' ?>
                                data-wait="<?= (int) $resendWait ?>">Resend code<?= $resendWait > 0 ? ' (' . (int) $resendWait . 's)' : '' ?></button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restart">
                        <button class="btn btn-link p-0" type="submit">Use a different Student ID</button>
                    </form>
                </div>

            <?php elseif ($step === 3 && $record): ?>
                <p class="auth-kicker">Identity confirmed</p>
                <h1 id="verify-heading">Welcome, <?= e($record['full_name']) ?>.</h1>
                <p class="login-sub">Create a password for Study Vault. You’ll sign in with <strong><?= e(mask_email((string) $record['email'])) ?></strong>.</p>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_account">
                    <div class="mb-3">
                        <label class="form-label" for="password">Create password</label>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" id="password" name="password"
                               required autofocus autocomplete="new-password" minlength="<?= (int) PASSWORD_MIN_LENGTH ?>">
                        <div class="form-text">At least <?= (int) PASSWORD_MIN_LENGTH ?> characters, including a letter and a number.</div>
                        <?= verify_field_error($errors, 'password') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input class="form-control<?= isset($errors['confirm_password']) ? ' is-invalid' : '' ?>" type="password" id="confirm_password" name="confirm_password"
                               required autocomplete="new-password">
                        <?= verify_field_error($errors, 'confirm_password') ?>
                    </div>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">Create account <span aria-hidden="true">&rarr;</span></button>
                </form>
            <?php endif; ?>

            <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>login.php">Sign in</a></p>
        </section>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('resendBtn');
    if (!b) return;
    var w = parseInt(b.getAttribute('data-wait'), 10) || 0;
    if (w <= 0) return;
    var t = setInterval(function () {
        w--;
        if (w <= 0) { clearInterval(t); b.disabled = false; b.textContent = 'Resend code'; }
        else { b.textContent = 'Resend code (' + w + 's)'; }
    }, 1000);
})();
</script>
</body>
</html>
