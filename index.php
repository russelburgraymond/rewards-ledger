<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$page = $_GET['page'] ?? 'dashboard';
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

$allowed_pages = [
    'dashboard',
    'setup',
    'settings',
    'system_status',
    'changelog',
    'quick_entry',
    'ledger',
    'dashboard_settings',
    'quick_adds',
    'miners',
    'referrals',
    'apps',
	'wiki',
	'ai_import',
    'categories',
    'assets',
    'accounts',
    'templates',
    'template_edit',
    'template_use',
    'template_delete',
	'assets_reorder',
    'onboarding',
];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'dashboard';
}

/* -----------------------------
   ONBOARDING GATE
----------------------------- */
if ((int)$ONBOARDING_COMPLETE !== 1 && $page !== 'onboarding') {
    header("Location: index.php?page=onboarding");
    exit;
}

/* -----------------------------
   LOAD DB ONLY AFTER ONBOARDING
----------------------------- */
$conn = null;
$db_exists = false;

if ((int)$ONBOARDING_COMPLETE === 1 || $page !== 'onboarding') {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/schema.php';

    if (!empty($db_exists) && isset($conn) && $conn instanceof mysqli) {
        ensure_schema($conn);
    }
}

$is_setup_page = ($page === 'setup');
$is_status_page = ($page === 'system_status');

if ($page !== 'onboarding') {
    if (empty($db_exists) && !$is_setup_page && !$is_status_page) {
        $page = 'setup';
    }

    $setup_complete = '0';
    if (!empty($db_exists) && isset($conn) && $conn instanceof mysqli) {
        $setup_complete = get_setting($conn, 'setup_complete', '0');
    }

    if (!empty($db_exists) && $setup_complete !== '1' && !$is_setup_page && !$is_status_page) {
        $page = 'setup';
    }
}

$page_title = ucwords(str_replace('_', ' ', $page));
$current_page = $page;

require __DIR__ . '/header.php';

$file = __DIR__ . '/' . $page . '.php';

if (is_file($file)) {
    require $file;
} else {
    echo "<div class='card'><h2>Page not found</h2><p>The requested page could not be found.</p></div>";
}

require __DIR__ . '/footer.php';