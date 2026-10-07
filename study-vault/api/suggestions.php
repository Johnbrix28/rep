<?php
require_once __DIR__ . '/../includes/bootstrap.php';

api_require_student();
json_out(fetch_suggestions($pdo, clean_text($_GET['q'] ?? '')));
