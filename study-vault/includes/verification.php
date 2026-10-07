<?php
/**
 * Student ID verification (Email + 6-digit code).
 *
 * Flow: Student ID -> school record check -> code emailed to the address the SCHOOL has on
 * file -> code entered -> password created -> account stored in `students`.
 * Everything is checked on the server; the browser is never trusted.
 */

/* ============================================================
   Settings
   ============================================================ */
// Programs that may use Study Vault (case-insensitive, dots/extra spaces ignored). Edit freely.
if (!defined('VERIFY_ALLOWED_PROGRAMS')) {
    define('VERIFY_ALLOWED_PROGRAMS', [
        'BSIT',
        'BS Information Technology',
        'BSCS',
        'BS Computer Science',
    ]);
}
if (!defined('VERIFY_CODE_TTL_MINUTES'))   define('VERIFY_CODE_TTL_MINUTES', 10);   // code lifetime
if (!defined('VERIFY_MAX_ATTEMPTS'))       define('VERIFY_MAX_ATTEMPTS', 5);        // wrong guesses per code
if (!defined('VERIFY_RESEND_SECONDS'))     define('VERIFY_RESEND_SECONDS', 60);     // wait before another code
if (!defined('VERIFY_MAX_SENDS_PER_HOUR')) define('VERIFY_MAX_SENDS_PER_HOUR', 5);  // codes per Student ID / hour
if (!defined('VERIFY_LOOKUP_MAX_FAILS'))   define('VERIFY_LOOKUP_MAX_FAILS', 10);   // bad Student IDs per IP / window
if (!defined('VERIFY_PASSWORD_WINDOW'))    define('VERIFY_PASSWORD_WINDOW', 900);   // seconds to finish step 3

/* ============================================================
   Small helpers
   ============================================================ */
/** "gantalajohnbrix12@gmail.com" -> "ga***********2@gmail.com" */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at < 1) {
        return '***';
    }
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at);
    $len    = strlen($local);

    if ($len <= 2) {
        return substr($local, 0, 1) . '***' . $domain;
    }
    return substr($local, 0, 2) . str_repeat('*', max(3, $len - 3)) . substr($local, -1) . $domain;
}

function verify_normalize_program(string $program): string
{
    $p = strtolower(str_replace('.', '', $program));
    $p = preg_replace('/[^a-z0-9]+/', ' ', $p);
    return trim((string) $p);
}

function verify_program_allowed(string $program): bool
{
    $needle = verify_normalize_program($program);
    if ($needle === '') {
        return false;
    }
    foreach (VERIFY_ALLOWED_PROGRAMS as $allowed) {
        if ($needle === verify_normalize_program($allowed)) {
            return true;
        }
    }
    return false;
}

function normalize_student_id($v): string
{
    return trim(preg_replace('/\s+/', '', (string) $v));
}

function valid_student_id_format(string $id): bool
{
    return (bool) preg_match('/^[A-Za-z0-9._-]{3,50}$/', $id);
}

/* ============================================================
   School records
   ============================================================ */
function find_school_record(PDO $pdo, string $studentId): ?array
{
    $s = $pdo->prepare('SELECT * FROM school_student_records WHERE student_id = ? LIMIT 1');
    $s->execute([$studentId]);
    $row = $s->fetch();
    return $row ?: null;
}

function get_school_record(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT * FROM school_student_records WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    $row = $s->fetch();
    return $row ?: null;
}

/** ['ok' => bool, 'error' => string] : IT program AND enrolled/alumni. */
function school_record_eligibility(array $record): array
{
    if (!verify_program_allowed((string) $record['program'])
        || !in_array($record['status'], ['enrolled', 'alumni'], true)) {
        return ['ok' => false, 'error' => 'Not eligible for Study Vault.'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Is there already an account for this school record?
 * Returns:
 *   ['state' => 'linked']                      - already registered, must log in
 *   ['state' => 'claimable', 'student' => row] - old admin-created account with the same Student ID and no
 *                                                email yet; it can be attached to this record
 *   ['state' => 'email_taken']                 - the school email belongs to a different account
 *   ['state' => 'none']
 */
function school_record_account_state(PDO $pdo, array $record): array
{
    $s = $pdo->prepare('SELECT * FROM students WHERE school_record_id = ? LIMIT 1');
    $s->execute([(int) $record['id']]);
    if ($s->fetch()) {
        return ['state' => 'linked'];
    }

    $s = $pdo->prepare('SELECT * FROM students WHERE student_number = ? LIMIT 1');
    $s->execute([$record['student_id']]);
    $byNumber = $s->fetch();

    if ($byNumber) {
        if (!empty($byNumber['email'])) {
            return ['state' => 'linked'];
        }
        return ['state' => 'claimable', 'student' => $byNumber];
    }

    $email = normalize_email($record['email'] ?? '');
    if ($email !== '') {
        $s = $pdo->prepare('SELECT 1 FROM students WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        if ($s->fetch()) {
            return ['state' => 'email_taken'];
        }
    }
    return ['state' => 'none'];
}

/* ============================================================
   Throttling of Student ID guesses (per IP, reuses login_attempts)
   ============================================================ */
function verify_lookup_throttled(PDO $pdo): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
        $s = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?');
        $s->execute(['verify-id:' . client_ip(), $since]);
        return (int) $s->fetchColumn() >= VERIFY_LOOKUP_MAX_FAILS;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function verify_lookup_failed(PDO $pdo): void
{
    record_login_failure($pdo, 'verify-id:' . client_ip());
}

/* ============================================================
   Code storage and checking
   ============================================================ */
/** Seconds the person must still wait before another code may be sent (0 = may send now). */
function verification_resend_wait(PDO $pdo, int $recordId): int
{
    $s = $pdo->prepare('SELECT created_at FROM verification_codes WHERE school_record_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$recordId]);
    $last = $s->fetchColumn();
    if (!$last) {
        return 0;
    }
    return max(0, VERIFY_RESEND_SECONDS - (time() - strtotime((string) $last)));
}

function verification_hourly_limit_reached(PDO $pdo, int $recordId): bool
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM verification_codes WHERE school_record_id = ? AND created_at > ?');
    $s->execute([$recordId, date('Y-m-d H:i:s', time() - 3600)]);
    return (int) $s->fetchColumn() >= VERIFY_MAX_SENDS_PER_HOUR;
}

/** Invalidate every old code for the record, store a new one, return its row id. */
function store_verification_code(PDO $pdo, array $record, string $code): int
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE school_record_id = ? AND used = 0')
            ->execute([(int) $record['id']]);

        $pdo->prepare(
            'INSERT INTO verification_codes (school_record_id, student_id, email, code, attempts, used, expires_at, created_at)
             VALUES (?,?,?,?,0,0,?,?)'
        )->execute([
            (int) $record['id'],
            $record['student_id'],
            normalize_email($record['email']),
            $code,
            date('Y-m-d H:i:s', time() + VERIFY_CODE_TTL_MINUTES * 60),
            date('Y-m-d H:i:s'),
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Remove a code that was never delivered, so a mail failure does not trigger the resend cooldown. */
function delete_verification_code(PDO $pdo, int $codeId): void
{
    $pdo->prepare('DELETE FROM verification_codes WHERE id = ?')->execute([$codeId]);
}

function void_verification_code(PDO $pdo, int $codeId): void
{
    $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE id = ?')->execute([$codeId]);
}

/**
 * Check a code typed by the user. Returns ['ok' => bool, 'error' => string, 'restart' => bool]
 * (restart = a new code must be requested).
 */
function check_verification_code(PDO $pdo, int $recordId, string $input): array
{
    $input = trim($input);
    if (!preg_match('/^\d{6}$/', $input)) {
        return ['ok' => false, 'error' => 'Enter the 6-digit code from your email.', 'restart' => false];
    }

    $s = $pdo->prepare('SELECT * FROM verification_codes WHERE school_record_id = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $s->execute([$recordId]);
    $row = $s->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'No active code. Please request a new one.', 'restart' => true];
    }

    if (strtotime((string) $row['expires_at']) <= time()) {
        void_verification_code($pdo, (int) $row['id']);
        return ['ok' => false, 'error' => 'That code has expired. Please request a new one.', 'restart' => true];
    }

    if ((int) $row['attempts'] >= VERIFY_MAX_ATTEMPTS) {
        void_verification_code($pdo, (int) $row['id']);
        return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.', 'restart' => true];
    }

    if (hash_equals((string) $row['code'], $input)) {
        // Single-use: only one request can flip used 0 -> 1.
        $u = $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE id = ? AND used = 0');
        $u->execute([(int) $row['id']]);
        if ($u->rowCount() === 1) {
            return ['ok' => true, 'error' => '', 'restart' => false];
        }
        return ['ok' => false, 'error' => 'That code was already used. Please request a new one.', 'restart' => true];
    }

    $pdo->prepare('UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?')->execute([(int) $row['id']]);
    $left = VERIFY_MAX_ATTEMPTS - ((int) $row['attempts'] + 1);

    if ($left <= 0) {
        void_verification_code($pdo, (int) $row['id']);
        return ['ok' => false, 'error' => 'Incorrect code. No attempts left, please request a new code.', 'restart' => true];
    }
    return ['ok' => false,
        'error' => 'Incorrect code. You have ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.',
        'restart' => false];
}

/* ============================================================
   Verification state kept in the session (server side)
   ============================================================ */
function verify_state(): array
{
    return is_array($_SESSION['verify'] ?? null) ? $_SESSION['verify'] : [];
}

function verify_state_set(array $state): void
{
    $_SESSION['verify'] = $state;
}

function verify_state_clear(): void
{
    unset($_SESSION['verify']);
}

/** 1 = Student ID, 2 = enter code, 3 = create password. */
function verify_current_step(): int
{
    $st = verify_state();
    if (empty($st['record_id'])) {
        return 1;
    }
    if (!empty($st['verified_at'])) {
        return (time() - (int) $st['verified_at'] <= VERIFY_PASSWORD_WINDOW) ? 3 : 1;
    }
    return 2;
}

/* ============================================================
   Account creation (step 3)
   ============================================================ */
function verify_password_errors(string $password, string $confirm): array
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    return $errors;
}

/**
 * Create (or claim) the account for a verified school record.
 * Returns ['ok' => bool, 'errors' => [field => message], 'student_id' => ?int]
 */
function create_verified_account(PDO $pdo, int $recordId, string $password, string $confirm): array
{
    $errors = verify_password_errors($password, $confirm);
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'student_id' => null];
    }

    $fail = fn(string $msg) => ['ok' => false, 'errors' => ['general' => $msg], 'student_id' => null];

    try {
        $pdo->beginTransaction();

        // Lock the school row so two requests cannot create the same account twice.
        $s = $pdo->prepare('SELECT * FROM school_student_records WHERE id = ? FOR UPDATE');
        $s->execute([$recordId]);
        $record = $s->fetch();

        if (!$record) {
            $pdo->rollBack();
            return $fail('The school record could not be found. Please start again.');
        }
        if (!school_record_eligibility($record)['ok']) {
            $pdo->rollBack();
            return $fail('Not eligible for Study Vault.');
        }
        $email = normalize_email($record['email'] ?? '');
        if (!valid_email($email)) {
            $pdo->rollBack();
            return $fail('The school has no valid email address on file for this Student ID.');
        }

        $acct = school_record_account_state($pdo, $record);
        if ($acct['state'] === 'linked') {
            $pdo->rollBack();
            return $fail('This Student ID is already linked to an account. Please log in.');
        }
        if ($acct['state'] === 'email_taken') {
            $pdo->rollBack();
            return $fail('The school email address is already used by another account. Please contact the administrator.');
        }

        $itProgram = $pdo->query('SELECT program_id FROM programs WHERE is_it_program = 1 ORDER BY program_id LIMIT 1')->fetchColumn();
        $programId = $itProgram ? (int) $itProgram : null;
        $hash      = password_hash($password, PASSWORD_DEFAULT);
        $now       = date('Y-m-d H:i:s');
        $userType  = $record['status'] === 'alumni' ? 'alumni' : 'enrolled';
        $gradYear  = $record['graduation_year'] !== null ? (int) $record['graduation_year'] : null;

        if ($acct['state'] === 'claimable') {
            // Older account created before email login existed: attach the verified email + new password.
            $studentId = (int) $acct['student']['student_id'];
            $pdo->prepare(
                "UPDATE students
                    SET email = ?, password = ?, user_type = ?, school_record_id = ?, status = 'approved',
                        verification_status = 'verified', graduation_year = ?,
                        program_id = COALESCE(program_id, ?), approved_at = COALESCE(approved_at, ?)
                  WHERE student_id = ?"
            )->execute([$email, $hash, $userType, (int) $record['id'], $gradYear, $programId, $now, $studentId]);
        } else {
            $pdo->prepare(
                "INSERT INTO students
                   (student_number, full_name, email, password, user_type, program_id, school_record_id,
                    access_level, status, verification_status, graduation_year, approved_at, created_at)
                 VALUES (?,?,?,?,?,?,?, 'free', 'approved', 'verified', ?, ?, ?)"
            )->execute([
                $record['student_id'], $record['full_name'], $email, $hash, $userType, $programId,
                (int) $record['id'], $gradYear, $now, $now,
            ]);
            $studentId = (int) $pdo->lastInsertId();
        }

        $pdo->commit();
        log_student_activity($pdo, $studentId, 'Verified Student ID ' . $record['student_id'] . ' by email and created account');
        return ['ok' => true, 'errors' => [], 'student_id' => $studentId];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            return $fail('This Student ID or email is already linked to an account. Please log in.');
        }
        error_log('Verified account creation failed: ' . $ex->getMessage());
        return $fail('Your account could not be created. Please try again.');
    }
}
