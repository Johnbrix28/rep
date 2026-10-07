<?php

/* ============================================================
   Auth state checks
   ============================================================ */
function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function is_student_logged_in(): bool
{
    return !empty($_SESSION['student_id']);
}

/* ============================================================
   Current user accessors (values cached in the session)
   ============================================================ */
function current_admin(): array
{
    return [
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'name'     => $_SESSION['admin_name'] ?? '',
        'username' => $_SESSION['admin_username'] ?? '',
        'role'     => $_SESSION['admin_role'] ?? '',
    ];
}

function current_student(): array
{
    return [
        'student_id'     => $_SESSION['student_id'] ?? null,
        'student_number' => $_SESSION['student_number'] ?? '',
        'name'           => $_SESSION['student_name'] ?? '',
        'email'          => $_SESSION['student_email'] ?? '',
    ];
}

/** Fresh student row from the database (never trust the session for status). */
function load_student(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare(
        'SELECT s.*, p.program_name
           FROM students s LEFT JOIN programs p ON p.program_id = s.program_id
          WHERE s.student_id = ? LIMIT 1'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    return $row ?: null;
}

/* ============================================================
   Login handlers
   ============================================================ */
function login_admin(array $a): void
{
    session_regenerate_id(true);

    $_SESSION['admin_id']       = (int) $a['admin_id'];
    $_SESSION['admin_name']     = $a['name'];
    $_SESSION['admin_username'] = $a['username'];
    $_SESSION['admin_role']     = $a['role'];
    $_SESSION['last_activity']  = time();
}

function login_student(array $s): void
{
    session_regenerate_id(true);

    $_SESSION['student_id']            = (int) $s['student_id'];
    $_SESSION['student_number']        = $s['student_number'];
    $_SESSION['student_name']          = $s['full_name'];
    $_SESSION['student_email']         = $s['email'];
    $_SESSION['student_last_activity'] = time();
}

/**
 * Verify email + password for a student.
 * Returns ['ok' => bool, 'student' => ?array, 'error' => string].
 * Pending / rejected accounts are refused AFTER the password is verified, so
 * the account status is not revealed to someone who does not know the password.
 */
function authenticate_student(PDO $pdo, string $emailInput, string $password): array
{
    $email = normalize_email($emailInput);

    if ($email === '' || $password === '') {
        return ['ok' => false, 'student' => null, 'error' => 'Enter your email address and password.'];
    }

    if (login_throttled($pdo, $email)) {
        return ['ok' => false, 'student' => null,
            'error' => 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.'];
    }

    $student = null;
    if (valid_email($email)) {
        $s = $pdo->prepare('SELECT * FROM students WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $student = $s->fetch() ?: null;
    }

    // Always run one password check so response time does not reveal unknown emails.
    $hash = $student['password'] ?? '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe';
    $passwordOk = password_verify($password, $hash);

    if (!$student || !$passwordOk) {
        record_login_failure($pdo, $email);
        return ['ok' => false, 'student' => null, 'error' => 'Invalid email or password.'];
    }

    if ($student['status'] === 'pending') {
        return ['ok' => false, 'student' => null,
            'error' => 'Your account is still pending administrator approval. You will be able to log in once it is approved.'];
    }
    if ($student['status'] !== 'approved') {
        return ['ok' => false, 'student' => null,
            'error' => 'Your registration was not approved. Please contact the Study Vault administrator.'];
    }

    clear_login_failures($pdo, $email);
    return ['ok' => true, 'student' => $student, 'error' => ''];
}

/* ============================================================
   Logout handlers
   ============================================================ */
function logout_admin(): void
{
    foreach (['admin_id', 'admin_name', 'admin_username', 'admin_role', 'last_activity'] as $k) {
        unset($_SESSION[$k]);
    }
}

function logout_student(): void
{
    foreach (['student_id', 'student_number', 'student_name', 'student_email', 'student_last_activity'] as $k) {
        unset($_SESSION[$k]);
    }
}

/* ============================================================
   Route guards
   ============================================================ */
function require_admin(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Sign in as administrator to continue.');
        redirect(BASE_URL . 'admin/login.php');
    }

    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > ADMIN_IDLE_TIMEOUT) {
        logout_admin();
        set_flash('warning', 'Your administrator session expired. Please sign in again.');
        redirect(BASE_URL . 'admin/login.php');
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Require a signed-in, APPROVED student. Status is re-checked in the database
 * on every request, so rejecting or deleting an account takes effect immediately.
 * Returns the fresh student row.
 */
function require_student(): array
{
    global $pdo;

    if (!is_student_logged_in()) {
        set_flash('warning', 'Please sign in to access Study Vault.');
        redirect(BASE_URL . 'login.php');
    }

    $idle = time() - (int) ($_SESSION['student_last_activity'] ?? 0);
    if ($idle > STUDENT_IDLE_TIMEOUT) {
        logout_student();
        set_flash('warning', 'Your session expired. Please sign in again.');
        redirect(BASE_URL . 'login.php');
    }

    $student = load_student($pdo, (int) $_SESSION['student_id']);
    if (!$student || $student['status'] !== 'approved') {
        logout_student();
        set_flash('warning', 'Your account is not active. Please contact the administrator.');
        redirect(BASE_URL . 'login.php');
    }

    $_SESSION['student_last_activity'] = time();
    return $student;
}

/**
 * Pages that browsing students AND administrators may open.
 * Returns the student row, or null when an administrator is browsing.
 */
function require_viewer(): ?array
{
    if (is_logged_in() && !is_student_logged_in()) {
        require_admin();
        return null;
    }
    return require_student();
}


/**
 * API guard: returns the approved student row or answers 401/403 JSON.
 */
function api_require_student(): array
{
    global $pdo;

    if (!is_student_logged_in()) {
        json_out(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    $student = load_student($pdo, (int) $_SESSION['student_id']);
    if (!$student || $student['status'] !== 'approved') {
        logout_student();
        json_out(['success' => false, 'message' => 'Your account is not active.'], 403);
    }
    return $student;
}
