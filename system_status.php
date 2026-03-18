<?php

$page_title = 'System Status';
$current_page = 'system_status';

$php_version = PHP_VERSION;
$app_version = $APP_VERSION ?? '0.0.0';
$app_name = $APP_NAME ?? 'Application';

$mysql_version = 'Unknown';
$database_name = $DB_NAME ?? '';
$db_connected = false;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $db_connected = true;
    $mysql_version = $conn->server_info;
}

$storage_path = __DIR__ . '/storage';
$uploads_path = __DIR__ . '/storage/uploads';

$setup_complete = 'No';
if (!empty($db_exists)) {
    $setup_complete = get_setting($conn, 'setup_complete', '0') === '1' ? 'Yes' : 'No';
}
?>

<div class="card">
    <div class="card-header">
        <h2>System Status</h2>
        <p>Developer information for troubleshooting and setup verification.</p>
    </div>

    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:260px;">Item</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>App Name</strong></td><td><?= h($app_name) ?></td></tr>
                <tr><td><strong>App Version</strong></td><td><?= h($app_version) ?></td></tr>
                <tr><td><strong>PHP Version</strong></td><td><?= h($php_version) ?></td></tr>
                <tr><td><strong>MySQL Connected</strong></td><td><?= $db_connected ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>MySQL Version</strong></td><td><?= h($mysql_version) ?></td></tr>
                <tr><td><strong>Database Name</strong></td><td><?= h($database_name) ?></td></tr>
                <tr><td><strong>Database Exists</strong></td><td><?= !empty($db_exists) ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>Storage Exists</strong></td><td><?= is_dir($storage_path) ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>Uploads Exists</strong></td><td><?= is_dir($uploads_path) ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>Storage Writable</strong></td><td><?= is_writable($storage_path) ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>Uploads Writable</strong></td><td><?= is_writable($uploads_path) ? 'Yes' : 'No' ?></td></tr>
                <tr><td><strong>Setup Complete</strong></td><td><?= h($setup_complete) ?></td></tr>
            </tbody>
        </table>

        <div style="margin-top:16px;">
            <a href="index.php?page=setup" class="btn btn-secondary">Back to Setup</a>
            <a href="index.php?page=dashboard" class="btn btn-primary">Dashboard</a>
        </div>
    </div>
</div>