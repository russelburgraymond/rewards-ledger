<?php

require 'db.php';
require 'helpers.php';

$begin_date = trim($_GET['begin_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$app_id_filter = (int)($_GET['app_id'] ?? 0);
$category_id_filter = (int)($_GET['category_id'] ?? 0);
$asset_id_filter = (int)($_GET['asset_id'] ?? 0);

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

if ($app_id_filter > 0) {
    $where_sql .= " AND b.app_id = ?";
    $types .= "i";
    $params[] = $app_id_filter;
}

if ($category_id_filter > 0) {
    $where_sql .= " AND bi.category_id = ?";
    $types .= "i";
    $params[] = $category_id_filter;
}

if ($asset_id_filter > 0) {
    $where_sql .= " AND bi.asset_id = ?";
    $types .= "i";
    $params[] = $asset_id_filter;
}

/* Totals */
$totals = [];
$sql_totals = "
    SELECT
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat,
        COALESCE(SUM(
            CASE
                WHEN c.behavior_type IN ('expense', 'withdrawal', 'investment') THEN -1 * bi.amount
                ELSE bi.amount
            END
        ), 0) AS total_amount
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    LEFT JOIN assets a ON a.id = bi.asset_id
    LEFT JOIN categories c ON c.id = bi.category_id
    {$where_sql}
    GROUP BY
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat
    ORDER BY a.asset_name ASC
";

$stmt = $conn->prepare($sql_totals);
if ($stmt) {
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
}

/* Rows */
$rows = [];
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
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
}

function filter_label(array $items, int $id, string $idKey, string $nameKey): string {
    foreach ($items as $item) {
        if ((int)$item[$idKey] === $id) {
            return (string)$item[$nameKey];
        }
    }
    return 'All';
}

$apps = [];
$res = $conn->query("SELECT id, app_name FROM apps ORDER BY app_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $apps[] = $row;

$categories = [];
$res = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;

$assets = [];
$res = $conn->query("SELECT id, asset_name FROM assets ORDER BY asset_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger Export</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 24px;
        color: #1f2937;
    }
    h1, h2 {
        margin: 0 0 12px 0;
    }
    .meta {
        margin-bottom: 20px;
        font-size: 14px;
    }
    .totals {
        margin: 18px 0 24px 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 12px;
    }
    .total-card {
        border: 1px solid #d0d5dd;
        border-left: 5px solid #98a2b3;
        border-radius: 8px;
        padding: 10px 12px;
        background: #f8fafc;
    }
    .total-label {
        font-size: 12px;
        color: #475467;
        margin-bottom: 6px;
        text-transform: uppercase;
    }
    .total-value {
        font-size: 22px;
        font-weight: bold;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    th, td {
        border: 1px solid #d0d5dd;
        padding: 6px 8px;
        text-align: left;
        vertical-align: top;
    }
    th {
        background: #f2f4f7;
    }
    .no-print {
        margin-bottom: 16px;
    }
    @media print {
        .no-print {
            display: none;
        }
        body {
            margin: 12px;
        }
    }
</style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <h1>Ledger Export</h1>

    <div class="meta">
        <div><strong>Begin Date:</strong> <?= h($begin_date !== '' ? $begin_date : 'All') ?></div>
        <div><strong>End Date:</strong> <?= h($end_date !== '' ? $end_date : 'All') ?></div>
        <div><strong>App:</strong> <?= h($app_id_filter > 0 ? filter_label($apps, $app_id_filter, 'id', 'app_name') : 'All') ?></div>
        <div><strong>Category:</strong> <?= h($category_id_filter > 0 ? filter_label($categories, $category_id_filter, 'id', 'category_name') : 'All') ?></div>
        <div><strong>Asset:</strong> <?= h($asset_id_filter > 0 ? filter_label($assets, $asset_id_filter, 'id', 'asset_name') : 'All') ?></div>
        <div><strong>Generated:</strong> <?= h(date('Y-m-d H:i:s')) ?></div>
    </div>

    <h2>Totals</h2>
    <div class="totals">
        <?php foreach ($totals as $total): ?>
            <?php
            $label = trim((string)$total['asset_name']);
            if (!empty($total['asset_symbol'])) {
                $label .= ' (' . $total['asset_symbol'] . ')';
            }
            ?>
            <div class="total-card">
                <div class="total-label"><?= h($label) ?></div>
                <div class="total-value">
                    <?= h(fmt_asset_value(
                        $total['total_amount'],
                        (string)($total['currency_symbol'] ?? ''),
                        (int)($total['display_decimals'] ?? 8),
                        (int)($total['is_fiat'] ?? 0)
                    )) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Ledger Entries</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>App</th>
                <th>Miner</th>
                <th>Asset</th>
                <th>Category</th>
                <th>Behavior</th>
                <th>Referral</th>
                <th>From</th>
                <th>To</th>
                <th>Amount</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="12">No ledger entries found for the selected filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= h($row['batch_date']) ?></td>
                        <td><?= h($row['app_name'] ?? '') ?></td>
                        <td><?= h($row['miner_name'] ?? '') ?></td>
                        <td>
                            <?= h($row['asset_name'] ?? '') ?>
                            <?php if (!empty($row['asset_symbol'])): ?>
                                (<?= h($row['asset_symbol']) ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= h($row['category_name'] ?? '') ?></td>
                        <td><?= h($row['behavior_type'] ?? '') ?></td>
                        <td><?= h($row['referral_name'] ?? '') ?></td>
                        <td><?= h($row['from_account_name'] ?? '') ?></td>
                        <td><?= h($row['to_account_name'] ?? '') ?></td>
                        <td><?= h(fmt_asset_value(
                            $row['amount'],
                            (string)($row['currency_symbol'] ?? ''),
                            (int)($row['display_decimals'] ?? 8),
                            (int)($row['is_fiat'] ?? 0)
                        )) ?></td>
                        <td><?= h($row['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>