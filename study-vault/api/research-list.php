<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$student = api_require_student();
$fullAccess = student_has_full_access($pdo, (int) $student['student_id']);

$q        = clean_text($_GET['q'] ?? '');
$category = valid_id($_GET['category'] ?? null);
$year     = valid_id($_GET['year'] ?? null);
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

// Approved research only.
$where  = ["r.status = 'approved'"];
$params = [];

if ($q !== '') {
    $where[] = '(r.title LIKE :q1 OR r.abstract LIKE :q2 OR r.keywords LIKE :q3 OR c.category_name LIKE :q4
                 OR EXISTS(SELECT 1 FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
                            WHERE ra.research_id = r.research_id AND a.full_name LIKE :q5))';
    $like = '%' . $q . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];
}
if ($category) { $where[] = 'r.category_id = :cat'; $params[':cat'] = $category; }
if ($year)     { $where[] = 'r.year = :yr';        $params[':yr']  = $year; }

$ws = 'WHERE ' . implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM research r JOIN categories c ON c.category_id = r.category_id $ws");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT r.research_id, r.title, r.abstract, r.year, r.published_date, r.keywords,
           (r.file_path IS NOT NULL AND r.file_path <> '') AS has_manuscript,
           c.category_id, c.category_name,
           (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR ', ')
              FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
             WHERE ra.research_id = r.research_id) AS researchers
      FROM research r
      JOIN categories c ON c.category_id = r.category_id
      $ws
      ORDER BY r.published_date DESC, r.research_id DESC
      LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage));
$stmt->execute($params);

$rows = [];
foreach ($stmt->fetchAll() as $row) {
    $has = (bool) $row['has_manuscript'];
    $row['research_id']       = (int) $row['research_id'];
    $row['category_id']       = (int) $row['category_id'];
    $row['year']              = (int) $row['year'];
    $row['has_manuscript']    = $has;
    $row['can_view_manuscript'] = $has && $fullAccess;
    // No file_path. The protected route is given ONLY to users who may use it.
    $row['manuscript_url']    = ($has && $fullAccess) ? manuscript_viewer_url($row['research_id']) : null;
    $rows[] = $row;
}

json_out([
    'success' => true,
    'data'    => $rows,
    'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
]);
