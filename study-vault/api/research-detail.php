<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$student = api_require_student();

$id = valid_id($_GET['id'] ?? null);
if (!$id) {
    json_out(['success' => false, 'message' => 'Missing or invalid ID.'], 400);
}

$stmt = $pdo->prepare("
    SELECT r.research_id, r.title, r.abstract, r.year, r.published_date, r.keywords, r.category_id,
           r.status, r.file_path, c.category_name, p.program_name,
           (SELECT GROUP_CONCAT(a.full_name ORDER BY a.full_name SEPARATOR ', ')
              FROM research_authors ra JOIN authors a ON a.author_id = ra.author_id
             WHERE ra.research_id = r.research_id) AS researchers
      FROM research r
      JOIN categories c ON c.category_id = r.category_id
      LEFT JOIN programs p ON p.program_id = r.program_id
     WHERE r.research_id = ? AND r.status = 'approved'
     LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    json_out(['success' => false, 'message' => 'Not found.'], 404);
}

$access = check_manuscript_access($pdo, $row, $student, false);

$rel = $pdo->prepare("
    SELECT r.research_id, r.title, r.year, c.category_name
      FROM research r JOIN categories c ON c.category_id = r.category_id
     WHERE r.category_id = ? AND r.research_id <> ? AND r.status = 'approved'
     ORDER BY r.published_date DESC LIMIT 3");
$rel->execute([$row['category_id'], $id]);

$data = [
    'research_id'         => (int) $row['research_id'],
    'title'               => $row['title'],
    'abstract'            => $row['abstract'],
    'year'                => (int) $row['year'],
    'published_date'      => $row['published_date'],
    'keywords'            => $row['keywords'],
    'category_id'         => (int) $row['category_id'],
    'category_name'       => $row['category_name'],
    'program_name'        => $row['program_name'],
    'researchers'         => $row['researchers'],
    'has_manuscript'      => research_file_exists($row['file_path']),
    'can_view_manuscript' => $access['allowed'],
    'manuscript_url'      => $access['allowed'] ? manuscript_viewer_url((int) $row['research_id']) : null,
    'related'             => $rel->fetchAll(),
];
// Deliberately NOT returned: file_path, status internals, other users' data.

json_out(['success' => true, 'data' => $data]);
