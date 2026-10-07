<?php

/* ============================================================
   Output helpers
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Rebuild the current URL with some query parameters replaced (used by pagination). */
function query_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    return $path . ($params ? '?' . http_build_query($params) : '');
}

/* ============================================================
   Flash messages
   ============================================================ */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function get_flashes(): array
{
    $x = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $x;
}

function render_flashes(): void
{
    foreach (get_flashes() as $f) {
        printf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>',
            e($f['type']),
            e($f['message'])
        );
    }
}

/* ============================================================
   CSRF protection
   ============================================================ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

/* ============================================================
   Input sanitizing
   ============================================================ */
function valid_id($v)
{
    return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function clean_text($v): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $v));
}

function normalize_email($v): string
{
    return strtolower(trim((string) $v));
}

function valid_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ============================================================
   JSON helpers (API)
   ============================================================ */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============================================================
   Activity logging
   ============================================================ */
function log_activity(PDO $pdo, string $action): void
{
    try {
        $s = $pdo->prepare('INSERT INTO activity_logs(admin_id,student_id,action,ip_address) VALUES(?,?,?,?)');
        $s->execute([
            $_SESSION['admin_id'] ?? null,
            empty($_SESSION['admin_id']) ? ($_SESSION['student_id'] ?? null) : null,
            mb_substr($action, 0, 255),
            client_ip(),
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

/** Log an action performed by (or about) a specific student. */
function log_student_activity(PDO $pdo, ?int $studentId, string $action): void
{
    try {
        $s = $pdo->prepare('INSERT INTO activity_logs(admin_id,student_id,action,ip_address) VALUES(NULL,?,?,?)');
        $s->execute([$studentId, mb_substr($action, 0, 255), client_ip()]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

/* ============================================================
   Login throttling (brute-force protection)
   ============================================================ */
function login_throttled(PDO $pdo, string $identifier): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);

        // Per account / email: LOGIN_MAX_ATTEMPTS failures.
        $s = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?');
        $s->execute([$identifier, $since]);
        if ((int) $s->fetchColumn() >= LOGIN_MAX_ATTEMPTS) {
            return true;
        }

        // Per IP address: a higher ceiling, so one shared lab computer does not lock everyone out.
        $s = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?');
        $s->execute([client_ip(), $since]);
        return (int) $s->fetchColumn() >= LOGIN_MAX_ATTEMPTS * 4;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function record_login_failure(PDO $pdo, string $identifier): void
{
    try {
        $pdo->prepare('INSERT INTO login_attempts(identifier,ip_address,attempted_at) VALUES(?,?,?)')
            ->execute([mb_substr($identifier, 0, 120), client_ip(), date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

function clear_login_failures(PDO $pdo, string $identifier): void
{
    try {
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$identifier]);
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

/* ============================================================
   Lookup helpers
   ============================================================ */
function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM categories ORDER BY category_id')->fetchAll();
}

/** All programs, or only the IT programs when $itOnly is true. */
function get_programs(PDO $pdo, bool $itOnly = false): array
{
    $sql = 'SELECT * FROM programs ' . ($itOnly ? 'WHERE is_it_program = 1 ' : '') . 'ORDER BY is_it_program DESC, program_name';
    return $pdo->query($sql)->fetchAll();
}

/** Years that have at least one approved research record. */
function get_research_years(PDO $pdo, bool $approvedOnly = true): array
{
    $sql = 'SELECT DISTINCT year FROM research ' . ($approvedOnly ? "WHERE status='approved' " : '') . 'ORDER BY year DESC';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

function keyword_list(?string $k): array
{
    return $k
        ? array_values(array_filter(array_map('trim', explode(',', $k)), 'strlen'))
        : [];
}

/* ============================================================
   Formatting helpers
   ============================================================ */
function excerpt(?string $t, int $len = 180): string
{
    $t = trim((string) $t);

    if ($t === '') {
        return 'No abstract recorded.';
    }

    return mb_strlen($t) <= $len
        ? $t
        : rtrim(mb_substr($t, 0, $len), ' ,.;:-') . '...';
}

function format_date(?string $v): string
{
    return $v ? date('M j, Y', strtotime($v)) : '--';
}

function format_datetime(?string $v): string
{
    return $v ? date('M j, Y g:i A', strtotime($v)) : '--';
}

function format_price($amount): string
{
    return ((float) $amount) <= 0 ? '₱0' : '₱' . number_format((float) $amount, 0);
}

/** Bootstrap-friendly status badge. */
function status_badge(string $status): string
{
    $map = [
        'approved' => 'sv-badge-green',
        'active'   => 'sv-badge-green',
        'pending'  => 'sv-badge-amber',
        'rejected' => 'sv-badge-red',
        'cancelled'=> 'sv-badge-red',
        'expired'  => 'sv-badge-gray',
        'inactive' => 'sv-badge-gray',
    ];
    $cls = $map[$status] ?? 'sv-badge-gray';
    return '<span class="sv-badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

function user_type_label(?string $t): string
{
    return $t === 'alumni' ? 'IT Alumni' : 'Enrolled IT Student';
}

/* ============================================================
   Manuscript file handling (files live in storage/manuscripts/)
   ============================================================ */

/**
 * Resolve a stored file_path to an absolute path, but ONLY if it points to a real
 * file inside the manuscript storage folder (or the legacy upload folder).
 * Returns null otherwise. Prevents path traversal through tampered DB values.
 */
function resolve_manuscript_file(?string $stored): ?string
{
    if (!$stored) {
        return null;
    }
    $name = basename(str_replace('\\', '/', $stored));
    if (!preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $name)) {
        return null;
    }
    foreach ([MANUSCRIPT_DIR, UPLOAD_DIR] as $dir) {
        $full = realpath($dir . $name);
        $root = realpath($dir);
        if ($full !== false && $root !== false && is_file($full)
            && strpos($full, $root . DIRECTORY_SEPARATOR) === 0) {
            return $full;
        }
    }
    return null;
}

function research_file_exists(?string $p): bool
{
    return resolve_manuscript_file($p) !== null;
}

function delete_research_file(?string $p): void
{
    $full = resolve_manuscript_file($p);
    if ($full !== null) {
        @unlink($full);
    }
}

function handle_pdf_upload(array $file, ?string &$error)
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The PDF is larger than the server allows.';
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'The PDF could not be uploaded.';
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'The PDF exceeds the ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB limit.';
        return false;
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $error = 'Only PDF files are allowed.';
        return false;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
        $error = 'The uploaded file is not a valid PDF.';
        return false;
    }

    // Magic-number check: a real PDF starts with "%PDF-".
    $fh = fopen($file['tmp_name'], 'rb');
    $magic = $fh ? fread($fh, 5) : '';
    if ($fh) {
        fclose($fh);
    }
    if ($magic !== '%PDF-') {
        $error = 'The uploaded file is not a valid PDF.';
        return false;
    }

    if (!is_dir(MANUSCRIPT_DIR) && !mkdir(MANUSCRIPT_DIR, 0755, true)) {
        $error = 'Manuscript storage folder could not be created.';
        return false;
    }

    $name = 'research-' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.pdf';

    if (!move_uploaded_file($file['tmp_name'], MANUSCRIPT_DIR . $name)) {
        $error = 'The PDF could not be saved.';
        return false;
    }

    @chmod(MANUSCRIPT_DIR . $name, 0644);

    return MANUSCRIPT_PATH_PREFIX . $name;
}

/* ============================================================
   Author helpers
   ============================================================ */
function get_research_authors(PDO $pdo, int $id): array
{
    $s = $pdo->prepare('SELECT a.full_name FROM research_authors ra JOIN authors a ON a.author_id=ra.author_id WHERE ra.research_id=? ORDER BY a.full_name');
    $s->execute([$id]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

function find_or_create_author(PDO $pdo, string $name): int
{
    $name = clean_text($name);

    $s = $pdo->prepare('SELECT author_id FROM authors WHERE full_name=? LIMIT 1');
    $s->execute([$name]);
    $id = $s->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $s = $pdo->prepare('INSERT INTO authors(full_name) VALUES(?)');
    $s->execute([$name]);

    return (int) $pdo->lastInsertId();
}

function sync_researchers(PDO $pdo, int $researchId, string $names): void
{
    $pdo->prepare('DELETE FROM research_authors WHERE research_id=?')->execute([$researchId]);

    $seen = [];
    foreach (array_filter(array_map('clean_text', explode(',', $names))) as $name) {
        $aid = find_or_create_author($pdo, $name);
        if (isset($seen[$aid])) {
            continue;
        }
        $seen[$aid] = true;
        $s = $pdo->prepare('INSERT INTO research_authors(research_id,author_id) VALUES(?,?)');
        $s->execute([$researchId, $aid]);
    }
}

/* ============================================================
   Search suggestions (approved research only) - shared by
   suggestions.php and api/suggestions.php
   ============================================================ */
function fetch_suggestions(PDO $pdo, string $query): array
{
    if (mb_strlen($query) < 2) {
        return [];
    }

    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        "SELECT DISTINCT suggestion FROM (
            SELECT r.title AS suggestion FROM research r
             WHERE r.status = 'approved' AND r.title LIKE :title
            UNION
            SELECT a.full_name FROM authors a
              JOIN research_authors ra ON ra.author_id = a.author_id
              JOIN research r ON r.research_id = ra.research_id
             WHERE r.status = 'approved' AND a.full_name LIKE :author
            UNION
            SELECT c.category_name FROM categories c WHERE c.category_name LIKE :category
            UNION
            SELECT r.keywords FROM research r
             WHERE r.status = 'approved' AND r.keywords LIKE :keywords
        ) suggestions
        WHERE suggestion IS NOT NULL AND suggestion <> ''
        ORDER BY suggestion
        LIMIT 30"
    );
    $stmt->execute([':title' => $like, ':author' => $like, ':category' => $like, ':keywords' => $like]);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
        // Keyword columns hold comma-separated lists; split them and keep matching parts.
        $parts = (strpos($value, ',') !== false) ? keyword_list($value) : [$value];
        foreach ($parts as $part) {
            if (stripos($part, $query) !== false) {
                $out[] = $part;
            }
        }
    }
    return array_slice(array_values(array_unique($out)), 0, 8);
}

/* ============================================================
   Registration (shared by register.php and api/register.php)
   ============================================================ */
/**
 * Validate and create a PENDING student account.
 * Returns ['ok' => bool, 'errors' => [field => message], 'id' => ?int]
 */
function register_student(PDO $pdo, array $in): array
{
    $errors = [];

    $fullName = clean_text($in['full_name'] ?? '');
    $email    = normalize_email($in['email'] ?? '');
    $number   = clean_text($in['student_number'] ?? '');
    $password = (string) ($in['password'] ?? '');
    $confirm  = (string) ($in['confirm_password'] ?? '');
    $type     = (string) ($in['user_type'] ?? '');
    $program  = valid_id($in['program_id'] ?? null);

    if ($fullName === '' || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 160
        || !preg_match("/^[\p{L}][\p{L}\p{M}\s.'-]*$/u", $fullName)) {
        $errors['full_name'] = 'Enter your full name (letters, spaces, periods, apostrophes and hyphens only).';
    }

    if (!valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $number)) {
        $errors['student_number'] = 'Enter a valid Student ID number.';
    }

    if (!in_array($type, ['enrolled', 'alumni'], true)) {
        $errors['user_type'] = 'Choose Enrolled IT Student or IT Alumni.';
    }

    // IT-only registration: the chosen program must be flagged as an IT program.
    $programOk = false;
    if ($program) {
        $ps = $pdo->prepare('SELECT is_it_program FROM programs WHERE program_id = ?');
        $ps->execute([$program]);
        $row = $ps->fetch();
        $programOk = $row && (int) $row['is_it_program'] === 1;
    }
    if (!$programOk) {
        $errors['program_id'] = 'Study Vault is only open to Information Technology (IT) students and alumni.';
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $s = $pdo->prepare('SELECT 1 FROM students WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        if ($s->fetch()) {
            $errors['email'] = 'That email address is already registered.';
        }
        $s = $pdo->prepare('SELECT 1 FROM students WHERE student_number = ? LIMIT 1');
        $s->execute([$number]);
        if ($s->fetch()) {
            $errors['student_number'] = 'That Student ID is already registered.';
        }
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'id' => null];
    }

    try {
        $s = $pdo->prepare(
            "INSERT INTO students(student_number, full_name, email, password, user_type, program_id, status)
             VALUES(?,?,?,?,?,?, 'pending')"
        );
        $s->execute([$number, $fullName, $email, password_hash($password, PASSWORD_DEFAULT), $type, $program]);
        $id = (int) $pdo->lastInsertId();
        log_student_activity($pdo, $id, 'Registered a new account (pending approval): ' . $email);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') { // unique constraint raced
            return ['ok' => false, 'errors' => ['email' => 'That email address or Student ID is already registered.'], 'id' => null];
        }
        error_log('Registration failed: ' . $ex->getMessage());
        return ['ok' => false, 'errors' => ['general' => 'Your account could not be created. Please try again.'], 'id' => null];
    }
}
