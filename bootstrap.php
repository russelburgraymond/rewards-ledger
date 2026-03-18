<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

if (!empty($db_exists) && isset($conn) && $conn instanceof mysqli) {
    ensure_schema($conn);
}

$page = $_GET['page'] ?? 'dashboard';

$is_setup_page  = ($page === 'setup');
$is_status_page = ($page === 'system_status');

if (empty($db_exists) && !$is_setup_page && !$is_status_page) {
    $_GET['page'] = 'setup';
    return;
}

$setup_complete = '0';

if (!empty($db_exists) && isset($conn) && $conn instanceof mysqli) {
    $setup_complete = get_setting($conn, 'setup_complete', '0');
}

if (!empty($db_exists) && $setup_complete !== '1' && !$is_setup_page && !$is_status_page) {
    $_GET['page'] = 'setup';
    return;
}