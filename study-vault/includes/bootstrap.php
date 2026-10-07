<?php
/**
 * Single entry point for every page: database, session, helpers, auth, access rules.
 * Public pages:  require_once __DIR__ . '/includes/bootstrap.php';
 * Admin pages:   require_once __DIR__ . '/../includes/bootstrap.php';
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/verification.php';
require_once __DIR__ . '/email-verification.php';

// Subscriptions are recognised as expired automatically on every request.
expire_subscriptions($pdo);
