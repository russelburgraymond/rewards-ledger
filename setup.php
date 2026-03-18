<?php

$page_title = 'Setup';
$current_page = 'setup';

$checks = [];
$setup_saved = false;
$create_db_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_database'])) {
        $db_name_escaped = str_replace('`', '``', $DB_NAME);

        if ($conn->query("CREATE DATABASE IF NOT EXISTS `{$db_name_escaped}`")) {
            $conn->select_db($DB_NAME);
            $conn->set_charset('utf8mb4');
            $db_exists = true;
            ensure_schema($conn);
            $create_db_result = ['ok' => true, 'message' => 'Database was created successfully.'];
        } else {
            $create_db_result = ['ok' => false, 'message' => 'Database could not be created: ' . $conn->error];
        }
    }

}

if (empty($db_exists)) {
    $db_name_escaped = $conn->real_escape_string($DB_NAME);
    $db_check = $conn->query("SHOW DATABASES LIKE '{$db_name_escaped}'");

    if ($db_check && $db_check->num_rows > 0) {
        $db_exists = true;
        $conn->select_db($DB_NAME);
        $conn->set_charset('utf8mb4');
    }
}

if (!empty($db_exists)) {
    ensure_schema($conn);
}

$db_connected = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;

$checks[] = [
    'ok' => $db_connected,
    'label' => 'MySQL Connection',
    'message' => $db_connected ? 'Connected successfully.' : 'Could not connect to MySQL.',
];

$checks[] = [
    'ok' => !empty($db_exists),
    'label' => 'Database Exists',
    'message' => !empty($db_exists)
        ? 'Database "' . $DB_NAME . '" exists.'
        : 'Database "' . $DB_NAME . '" was not found.',
];

$required_tables = [
    'settings',
    'miners',
    'assets',
    'categories',
    'batches',
    'batch_items',
    'templates',
];

$all_tables_ok = true;

foreach ($required_tables as $table) {
    $table_ok = false;

    if (!empty($db_exists)) {
        $table_escaped = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$table_escaped}'");
        $table_ok = ($res && $res->num_rows > 0);
    }

    if (!$table_ok) {
        $all_tables_ok = false;
    }

    $checks[] = [
        'ok' => $table_ok,
        'label' => 'Table: ' . $table,
        'message' => $table_ok ? 'Table exists.' : 'Table is missing.',
    ];
}

$storage_ok = is_dir(__DIR__ . '/storage');
$uploads_ok = is_dir(__DIR__ . '/storage/uploads');

$checks[] = [
    'ok' => $storage_ok,
    'label' => 'Storage Folder',
    'message' => $storage_ok ? 'Storage folder exists.' : 'Storage folder is missing.',
];

$checks[] = [
    'ok' => $uploads_ok,
    'label' => 'Uploads Folder',
    'message' => $uploads_ok ? 'Uploads folder exists.' : 'Uploads folder is missing.',
];

$ready_to_finish = $db_connected && !empty($db_exists) && $all_tables_ok && $storage_ok && $uploads_ok;

$setup_complete = '0';
if (!empty($db_exists)) {
    $setup_complete = get_setting($conn, 'setup_complete', '0');
}

/*
Auto-complete setup once everything is ready
*/
if ($ready_to_finish && $setup_complete !== '1') {
    set_setting($conn, 'setup_complete', '1');
    $setup_complete = '1';
    $setup_saved = true;
}

$checks[] = [
    'ok' => ($setup_complete === '1'),
    'label' => 'Setup Status',
    'message' => ($setup_complete === '1')
        ? 'All setup requirements are complete. Setup has been marked complete automatically.'
        : 'Complete the remaining items above. Once all checks are green, setup will be marked complete automatically.',
];
?>

<div class="card">
    <div class="card-header">
        <h2>Setup & Onboarding</h2>
        <p>Use this page to prepare the application before entering the dashboard.</p>
    </div>

    <div class="card-body">

        <?php if ($create_db_result): ?>
            <div class="alert <?= $create_db_result['ok'] ? 'alert-success' : 'alert-warning' ?>">
                <?= h($create_db_result['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($setup_saved): ?>
            <div class="alert alert-success">
                Setup has been marked complete.
            </div>
        <?php endif; ?>

        <div class="setup-grid">
            <?php foreach ($checks as $check): ?>
                <div class="setup-check <?= $check['ok'] ? 'ok' : 'fail' ?>">
                    <div class="setup-check-icon"><?= $check['ok'] ? '✔' : '✖' ?></div>
                    <div class="setup-check-content">
                        <div class="setup-check-label"><?= h($check['label']) ?></div>
                        <div class="setup-check-message"><?= h($check['message']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr>

        <?php if (empty($db_exists)): ?>
            <div class="alert alert-warning">
                The database does not exist yet. You can create it from here or create it manually in phpMyAdmin.
            </div>

            <form method="post" style="margin-top:16px;">
                <button type="submit" name="create_database" value="1" class="btn btn-primary">
                    Create Database
                </button>
            </form>
		<?php elseif ($ready_to_finish): ?>
			<div class="alert alert-success">
				All required checks passed. You can finish setup now.
			</div>

			<form method="post" style="margin-top:16px;">
				<a href="index.php?page=dashboard" class="btn btn-secondary">Go to Dashboard</a>
			</form>
        <?php else: ?>
            <div class="alert alert-warning">
                Setup is not ready yet. Fix the failed items above, then reload this page.
            </div>
        <?php endif; ?>

        <div style="margin-top:16px;">
            <a href="index.php?page=setup" class="btn btn-secondary">Reload Checks</a>
            <a href="index.php?page=system_status" class="btn btn-secondary">Open System Status</a>
        </div>
    </div>
</div>

<style>
.setup-grid {
    display: grid;
    gap: 12px;
    margin-top: 16px;
}
.setup-check {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid #ddd;
    background: #fff;
}
.setup-check.ok {
    border-color: #b7ebc6;
    background: #f3fff7;
}
.setup-check.fail {
    border-color: #f0c2c2;
    background: #fff6f6;
}
.setup-check-icon {
    font-size: 20px;
    line-height: 1;
    width: 24px;
    text-align: center;
    margin-top: 2px;
}
.setup-check-label {
    font-weight: 700;
    margin-bottom: 4px;
}
.setup-check-message {
    opacity: 0.9;
}
</style>