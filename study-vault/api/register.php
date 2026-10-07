<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Registration now requires Student ID verification by email code (verify.php).
// A JSON endpoint that creates accounts without that check would bypass it, so it is closed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(['success' => false, 'message' => 'Use POST.'], 405);
}

json_out([
    'success' => false,
    'message' => 'Registration now requires Student ID verification. Open ' . BASE_URL . 'verify.php in a browser.',
], 410);
