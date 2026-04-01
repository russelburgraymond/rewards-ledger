<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RewardLedger Setup Export Utility
|--------------------------------------------------------------------------
| One-time helper script to export setup/config-style tables and schema
| without exporting actual reward ledger transaction history.
|
| USAGE:
| 1) Drop this file into your RewardLedger project root.
| 2) Open it in your browser.
| 3) Click "Export Selected Data".
| 4) Save the downloaded JSON file.
| 5) Delete this PHP file when done.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* -----------------------------
   DATABASE SETTINGS
----------------------------- */

/*
| You can either:
| - let it auto-load config.php if your project has it
| - or hardcode the values below
*/

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = '007i_rewardsledger';

$config_candidates = [
    __DIR__ . '/config.php',
    __DIR__ . '/app/config.php',
];

foreach ($config_candidates as $config_file) {
    if (is_file($config_file)) {
        require $config_file;
        break;
    }
}

/* -----------------------------
   TABLES TO EXPORT
----------------------------- */

/*
| These are the likely setup/config tables.
| Adjust this list if your project uses different names.
|
| IMPORTANT:
| We are intentionally NOT exporting transaction history tables like:
| - batches
| - batch_items
*/

$preferred_tables = [
    'apps',
    'assets',
    'categories',
    'accounts',
    'miners',
    'referrals',
    'templates',
    'template_lines',
    'quick_adds',
    'quick_add_items',
    'quick_entry_templates',
    'quick_entry_template_lines',
    'dashboard_notes',
    'settings',
];

/*
| If you want to export EVERY table except transaction tables, set this to true.
| Otherwise only existing tables from $preferred_tables will be exported.
*/
$export_all_except_rewards = false;

/* -----------------------------
   HELPERS
----------------------------- */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table_name): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table_name);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return ((int)$count > 0);
}

function fetch_all_assoc(mysqli_result $result): array
{
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function get_existing_tables(mysqli $conn): array
{
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_array()) {
            $tables[] = (string)$row[0];
        }
        $res->close();
    }
    sort($tables);
    return $tables;
}

function get_show_create_table(mysqli $conn, string $table_name): ?string
{
    $safe_table = '`' . str_replace('`', '``', $table_name) . '`';
    $sql = "SHOW CREATE TABLE {$safe_table}";
    $res = $conn->query($sql);
    if (!$res) {
        return null;
    }

    $row = $res->fetch_assoc();
    $res->close();

    if (!$row) {
        return null;
    }

    foreach ($row as $key => $value) {
        if (stripos((string)$key, 'Create Table') !== false) {
            return (string)$value;
        }
    }

    return null;
}

function get_table_columns(mysqli $conn, string $table_name): array
{
    $columns = [];
    $safe_table = '`' . str_replace('`', '``', $table_name) . '`';
    $res = $conn->query("SHOW COLUMNS FROM {$safe_table}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = $row;
        }
        $res->close();
    }
    return $columns;
}

function get_table_indexes(mysqli $conn, string $table_name): array
{
    $indexes = [];
    $safe_table = '`' . str_replace('`', '``', $table_name) . '`';
    $res = $conn->query("SHOW INDEX FROM {$safe_table}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $indexes[] = $row;
        }
        $res->close();
    }
    return $indexes;
}

function get_table_rows(mysqli $conn, string $table_name): array
{
    $rows = [];
    $safe_table = '`' . str_replace('`', '``', $table_name) . '`';
    $res = $conn->query("SELECT * FROM {$safe_table}");
    if ($res) {
        $rows = fetch_all_assoc($res);
        $res->close();
    }
    return $rows;
}

function guess_export_tables(
    mysqli $conn,
    array $preferred_tables,
    bool $export_all_except_rewards
): array {
    $exclude = [
        'batches',
        'batch_items',
    ];

    $all_tables = get_existing_tables($conn);

    if ($export_all_except_rewards) {
        return array_values(array_filter(
            $all_tables,
            fn(string $t): bool => !in_array($t, $exclude, true)
        ));
    }

    $out = [];
    foreach ($preferred_tables as $table) {
        if (in_array($table, $all_tables, true)) {
            $out[] = $table;
        }
    }
    return $out;
}

/* -----------------------------
   CONNECT
----------------------------- */

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo '<h2>Database connection failed</h2>';
    echo '<pre>' . h($conn->connect_error) . '</pre>';
    exit;
}

$conn->set_charset('utf8mb4');

/* -----------------------------
   EXPORT
----------------------------- */

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $tables_to_export = guess_export_tables($conn, $preferred_tables, $export_all_except_rewards);

    $export = [
        'exported_at' => date('c'),
        'database' => $DB_NAME,
        'notes' => [
            'This export is intended for schema/setup review.',
            'Transaction history tables batches and batch_items are excluded by design.',
            'Sort orders and display orders are preserved because full table rows are exported.',
        ],
        'tables' => [],
    ];

    foreach ($tables_to_export as $table_name) {
        $export['tables'][$table_name] = [
            'show_create_table' => get_show_create_table($conn, $table_name),
            'columns' => get_table_columns($conn, $table_name),
            'indexes' => get_table_indexes($conn, $table_name),
            'row_count' => 0,
            'rows' => [],
        ];

        $rows = get_table_rows($conn, $table_name);
        $export['tables'][$table_name]['rows'] = $rows;
        $export['tables'][$table_name]['row_count'] = count($rows);
    }

    $filename = 'rewardledger_setup_export_' . date('Ymd_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* -----------------------------
   PREVIEW PAGE
----------------------------- */

$existing_tables = get_existing_tables($conn);
$tables_to_export = guess_export_tables($conn, $preferred_tables, $export_all_except_rewards);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RewardLedger Setup Export Utility</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#eef2f6;
            color:#2f3540;
        }
        .wrap{
            max-width:1100px;
            margin:30px auto;
            padding:0 20px;
        }
        .card{
            background:#fff;
            border:1px solid #d8dee8;
            border-radius:12px;
            padding:22px;
            box-shadow:0 2px 10px rgba(0,0,0,0.06);
            margin-bottom:20px;
        }
        h1,h2,h3{
            margin-top:0;
        }
        .sub{
            color:#66707f;
            line-height:1.6;
        }
        .btn{
            display:inline-block;
            padding:12px 18px;
            border-radius:8px;
            background:#5b6472;
            color:#fff;
            text-decoration:none;
            font-weight:700;
        }
        .btn:hover{
            background:#4d5561;
        }
        ul{
            margin:10px 0 0 20px;
            line-height:1.7;
        }
        code{
            background:#f4f6f8;
            padding:2px 6px;
            border-radius:6px;
        }
        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
        }
        .small{
            font-size:13px;
            color:#66707f;
        }
        @media (max-width: 800px){
            .grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>RewardLedger Setup Export Utility</h1>
        <p class="sub">
            This one-time helper exports your current setup/config tables and schema details
            without exporting your reward transaction history.
        </p>

        <a class="btn" href="?export=1">Export Selected Data</a>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Database Connection</h3>
            <p><strong>Host:</strong> <?= h($DB_HOST) ?></p>
            <p><strong>Database:</strong> <?= h($DB_NAME) ?></p>
            <p><strong>User:</strong> <?= h($DB_USER) ?></p>
            <p class="small">
                If this is wrong, edit the values at the top of this file.
            </p>
        </div>

        <div class="card">
            <h3>Export Mode</h3>
            <p><strong>Export all except rewards:</strong> <?= $export_all_except_rewards ? 'Yes' : 'No' ?></p>
            <p class="small">
                Current mode exports only the preferred setup/config tables that actually exist.
            </p>
        </div>
    </div>

    <div class="card">
        <h3>Tables That Will Be Exported</h3>
        <?php if (!$tables_to_export): ?>
            <p>No matching tables were found.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($tables_to_export as $table_name): ?>
                    <li><code><?= h($table_name) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>All Tables Found in Current Database</h3>
        <?php if (!$existing_tables): ?>
            <p>No tables found.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($existing_tables as $table_name): ?>
                    <li><code><?= h($table_name) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>What Gets Exported</h3>
        <ul>
            <li><strong>SHOW CREATE TABLE</strong> output</li>
            <li>columns</li>
            <li>indexes</li>
            <li>all rows from selected setup/config tables</li>
            <li>sort/display/order values because full rows are exported</li>
        </ul>
    </div>

    <div class="card">
        <h3>What Does Not Get Exported</h3>
        <ul>
            <li><code>batches</code></li>
            <li><code>batch_items</code></li>
        </ul>
        <p class="small">
            That keeps your reward history out of the export while preserving your current setup structure.
        </p>
    </div>

</div>
</body>
</html>