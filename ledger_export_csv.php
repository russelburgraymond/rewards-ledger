<?php

require 'db.php';
require 'helpers.php';

$begin_date = trim($_GET['begin_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$app_ids_filter = $_GET['app_ids'] ?? [];
if (!is_array($app_ids_filter)) {
    $app_ids_filter = [];
}
$app_ids_filter = array_values(array_filter(array_map('intval', $app_ids_filter), fn($v) => $v > 0));

$category_ids_filter = $_GET['category_ids'] ?? [];
if (!is_array($category_ids_filter)) {
    $category_ids_filter = [];
}
$category_ids_filter = array_values(array_filter(array_map('intval', $category_ids_filter), fn($v) => $v > 0));

$asset_ids_filter = $_GET['asset_ids'] ?? [];
if (!is_array($asset_ids_filter)) {
    $asset_ids_filter = [];
}
$asset_ids_filter = array_values(array_filter(array_map('intval', $asset_ids_filter), fn($v) => $v > 0));

$where_sql = " WHERE 1 = 1 ";
$params = [];
$types = "";

if ($begin_date !== '') {
    $where_sql .= " AND b.batch_date >= ?";
    $types .= "s";
    $params[] = $begin_date;
}

if ($end_date !== '') {
    $where_sql .= " AND b.batch_date <= ?";
    $types .= "s";
    $params[] = $end_date;
}

if (!empty($app_ids_filter)) {
    $placeholders = implode(',', array_fill(0, count($app_ids_filter), '?'));
    $where_sql .= " AND b.app_id IN ($placeholders)";
    $types .= str_repeat('i', count($app_ids_filter));
    foreach ($app_ids_filter as $id) {
        $params[] = $id;
    }
}

if (!empty($category_ids_filter)) {
    $placeholders = implode(',', array_fill(0, count($category_ids_filter), '?'));
    $where_sql .= " AND bi.category_id IN ($placeholders)";
    $types .= str_repeat('i', count($category_ids_filter));
    foreach ($category_ids_filter as $id) {
        $params[] = $id;
    }
}

if (!empty($asset_ids_filter)) {
    $placeholders = implode(',', array_fill(0, count($asset_ids_filter), '?'));
    $where_sql .= " AND bi.asset_id IN ($placeholders)";
    $types .= str_repeat('i', count($asset_ids_filter));
    foreach ($asset_ids_filter as $id) {
        $params[] = $id;
    }
}

/* -----------------------------
   LOAD TOTALS BY ASSET
----------------------------- */
$totals = [];

$sql_totals = "
    SELECT
        a.id AS asset_id,
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat,
        COALESCE(SUM(
            CASE
                WHEN c.behavior_type IN ('expense', 'withdrawal', 'investment') THEN -1 * bi.amount
                WHEN c.behavior_type IN ('transfer', 'neutral') THEN 0
                ELSE bi.amount
            END
        ), 0) AS total_amount
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    LEFT JOIN assets a ON a.id = bi.asset_id
    LEFT JOIN categories c ON c.id = bi.category_id
    {$where_sql}
    GROUP BY
        a.id,
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat
    ORDER BY a.sort_order ASC, a.asset_name ASC, a.id ASC
";

$stmt = $conn->prepare($sql_totals);
if (!$stmt) {
    die("Could not prepare totals export query: " . $conn->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $totals[] = $row;
    }
}
$stmt->close();

/* -----------------------------
   LOAD LEDGER ROWS
----------------------------- */
$sql = "
    SELECT
        bi.id,
        b.batch_date,
        ap.app_name,
        m.miner_name,
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat,
        c.category_name,
        c.behavior_type,
        r.referral_name,
        fa.account_name AS from_account_name,
        ta.account_name AS to_account_name,
        bi.amount,
        bi.notes
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    LEFT JOIN apps ap ON ap.id = b.app_id
    LEFT JOIN miners m ON m.id = bi.miner_id
    LEFT JOIN assets a ON a.id = bi.asset_id
    LEFT JOIN categories c ON c.id = bi.category_id
    LEFT JOIN referrals r ON r.id = bi.referral_id
    LEFT JOIN accounts fa ON fa.id = bi.from_account_id
    LEFT JOIN accounts ta ON ta.id = bi.to_account_id
    {$where_sql}
    ORDER BY b.batch_date DESC, bi.id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Could not prepare export query: " . $conn->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$filename = 'ledger_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

/* -----------------------------
   LOAD FILTER NAMES
----------------------------- */
function get_names_by_ids(mysqli $conn, string $table, string $id_col, string $name_col, array $ids): string {
    if (empty($ids)) return 'All';

    $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
    if (empty($ids)) return 'All';

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT {$name_col} FROM {$table} WHERE {$id_col} IN ($placeholders) ORDER BY {$name_col} ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 'Unknown';

    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $names = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $names[] = $row[$name_col];
        }
    }
    $stmt->close();

    return $names ? implode(', ', $names) : 'Unknown';
}

$app_name = get_names_by_ids($conn, 'apps', 'id', 'app_name', $app_ids_filter);
$category_name = get_names_by_ids($conn, 'categories', 'id', 'category_name', $category_ids_filter);
$asset_name = get_names_by_ids($conn, 'assets', 'id', 'asset_name', $asset_ids_filter);

/* -----------------------------
   FILTER SUMMARY
----------------------------- */
fputcsv($output, ['Ledger Export']);
fputcsv($output, ['Begin Date', $begin_date !== '' ? $begin_date : 'All']);
fputcsv($output, ['End Date', $end_date !== '' ? $end_date : 'All']);
fputcsv($output, ['App', $app_name]);
fputcsv($output, ['Category', $category_name]);
fputcsv($output, ['Asset', $asset_name]);
fputcsv($output, []);

/* -----------------------------
   TOTALS SECTION
----------------------------- */
fputcsv($output, ['Totals']);
fputcsv($output, ['Asset', 'Total']);

if (!$totals) {
    fputcsv($output, ['No totals found', '']);
} else {
    foreach ($totals as $row) {
        $asset_label = trim((string)($row['asset_name'] ?? ''));
        if (!empty($row['asset_symbol'])) {
            $asset_label .= ' (' . $row['asset_symbol'] . ')';
        }

        fputcsv($output, [
            $asset_label,
            fmt_asset_value(
                $row['total_amount'],
                (string)($row['currency_symbol'] ?? ''),
                (int)($row['display_decimals'] ?? 8),
                (int)($row['is_fiat'] ?? 0)
            )
        ]);
    }
}

fputcsv($output, []);
fputcsv($output, ['Ledger Entries']);

/* -----------------------------
   DETAIL SECTION
----------------------------- */
fputcsv($output, [
    'ID',
    'Date',
    'App',
    'Miner',
    'Asset',
    'Category',
    'Behavior',
    'Referral',
    'From Account',
    'To Account',
    'Amount',
    'Notes'
]);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $asset_label = trim((string)($row['asset_name'] ?? ''));
        if (!empty($row['asset_symbol'])) {
            $asset_label .= ' (' . $row['asset_symbol'] . ')';
        }

        fputcsv($output, [
            $row['id'],
            $row['batch_date'],
            $row['app_name'] ?? '',
            $row['miner_name'] ?? '',
            $asset_label,
            $row['category_name'] ?? '',
            $row['behavior_type'] ?? '',
            $row['referral_name'] ?? '',
            $row['from_account_name'] ?? '',
            $row['to_account_name'] ?? '',
            fmt_asset_value(
                $row['amount'],
                (string)($row['currency_symbol'] ?? ''),
                (int)($row['display_decimals'] ?? 8),
                (int)($row['is_fiat'] ?? 0)
            ),
            $row['notes'] ?? ''
        ]);
    }
}

fclose($output);
$stmt->close();
exit;