<?php

$current_page = 'dashboard';

$selected_ids_raw = get_setting($conn, 'dashboard_category_ids', '');
$selected_ids = array_filter(array_map('intval', explode(',', $selected_ids_raw)));

$net_cards = [];
$summary_cards = [];
$recent_line_items = [];
$dashboard_error = '';

/* --------------------------------
   DASHBOARD REQUIREMENTS CHECK
-------------------------------- */

$asset_count = 0;
$category_count = 0;

$res = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE is_active = 1");
if ($res) {
    $row = $res->fetch_assoc();
    $asset_count = (int)$row['c'];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM categories WHERE is_active = 1");
if ($res) {
    $row = $res->fetch_assoc();
    $category_count = (int)$row['c'];
}

$setup_needed = ($asset_count === 0 || $category_count === 0);

/*
|--------------------------------------------------------------------------
| Per-app Net Profit cards
|--------------------------------------------------------------------------
|
| expense    = money spent
| withdrawal = money received
| net profit = withdrawal - expense
|
*/
$res = $conn->query("
    SELECT
        a.id AS app_id,
        a.app_name,
        SUM(CASE WHEN c.behavior_type = 'withdrawal' THEN bi.amount ELSE 0 END) AS cash_out_total,
        SUM(CASE WHEN c.behavior_type = 'expense' THEN bi.amount ELSE 0 END) AS cash_in_total
    FROM apps a
    LEFT JOIN batches b
        ON b.app_id = a.id
    LEFT JOIN batch_items bi
        ON bi.batch_id = b.id
    LEFT JOIN categories c
        ON c.id = bi.category_id
    WHERE a.is_active = 1
    GROUP BY a.id, a.app_name
    ORDER BY a.app_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cash_out = (float)($row['cash_out_total'] ?? 0);
        $cash_in  = (float)($row['cash_in_total'] ?? 0);

        if ($cash_out == 0.0 && $cash_in == 0.0) {
            continue;
        }

        $net = $cash_out - $cash_in;

        $type = 'transfer';
        if ($net > 0) {
            $type = 'income';
        } elseif ($net < 0) {
            $type = 'expense';
        }

        $net_cards[] = [
            'app_name' => $row['app_name'] ?: 'Unassigned',
            'cash_out' => $cash_out,
            'cash_in' => $cash_in,
            'net' => $net,
            'type' => $type,
        ];
    }
} else {
    $dashboard_error = 'Net profit query failed: ' . $conn->error;
}

/*
|--------------------------------------------------------------------------
| Selected dashboard category totals
|--------------------------------------------------------------------------
|
| Show selected categories even if there is no data yet.
|
*/
if ($selected_ids) {
    $id_list = implode(',', $selected_ids);

    $res = $conn->query("
        SELECT
            a.id AS app_id,
            a.app_name,
            c.id AS category_id,
            c.category_name,
            c.behavior_type,
            COALESCE(SUM(bi.amount), 0) AS total_amount
        FROM categories c
        INNER JOIN apps a
            ON a.id = c.app_id
        LEFT JOIN batches b
            ON b.app_id = a.id
        LEFT JOIN batch_items bi
            ON bi.batch_id = b.id
           AND bi.category_id = c.id
        WHERE c.id IN ($id_list)
        GROUP BY a.id, a.app_name, c.id, c.category_name, c.behavior_type, c.sort_order
        ORDER BY a.app_name ASC, c.sort_order ASC, c.category_name ASC
    ");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $summary_cards[] = [
                'app_name' => $row['app_name'] ?: 'Unassigned',
                'label' => $row['category_name'],
                'value' => (float)($row['total_amount'] ?? 0),
                'type'  => $row['behavior_type'] ?? 'neutral',
            ];
        }
    } elseif ($dashboard_error === '') {
        $dashboard_error = 'Tracked totals query failed: ' . $conn->error;
    }
}

/* -----------------------------
   Recent line items
----------------------------- */
$res = $conn->query("
    SELECT
        bi.id,
        b.batch_date,
        ap.app_name,
        m.miner_name,
        a.asset_name,
        a.asset_symbol,
        c.category_name,
        bi.amount,
        bi.notes
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    LEFT JOIN apps ap ON ap.id = b.app_id
    LEFT JOIN miners m ON m.id = bi.miner_id
    LEFT JOIN assets a ON a.id = bi.asset_id
    LEFT JOIN categories c ON c.id = bi.category_id
    ORDER BY bi.id DESC
    LIMIT 10
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_line_items[] = $row;
    }
}
?>

<div class="page-head">
    <h2>Dashboard</h2>
    <p class="subtext">Overview of your tracked reward and profit data.</p>
</div>

<?php if ($setup_needed): ?>
    <div class="card" style="margin-bottom:20px;">
        <h3>Tracker Setup Needed</h3>
        <p class="subtext">
            Before you can record entries you need at least one
            <strong>Asset</strong> and one <strong>Category</strong>.
        </p>

        <div style="margin-top:15px;">
            <?php if ($asset_count === 0): ?>
                <a class="btn btn-primary" href="index.php?page=assets">Add Asset</a>
            <?php endif; ?>

            <?php if ($category_count === 0): ?>
                <a class="btn btn-primary" href="index.php?page=categories">Add Category</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($dashboard_error !== ''): ?>
    <div class="card" style="margin-bottom:20px;">
        <h3>Dashboard Notice</h3>
        <p class="subtext">The dashboard could not fully load because a database query failed.</p>
        <p><strong>MySQL Error:</strong> <?= h($dashboard_error) ?></p>
        <p>
            <a class="btn btn-secondary" href="index.php?page=system_status">Open System Status</a>
        </p>
    </div>
<?php endif; ?>

<?php if (!empty($net_cards)): ?>
    <div class="card mt-20">
        <h3>Net Profit by App</h3>
        <p class="subtext">Calculated as Cash Out (withdrawal) minus Cash In (expense).</p>

        <div class="summary-grid" style="margin-top:18px;">
            <?php foreach ($net_cards as $card): ?>
                <div class="summary-card type-<?= h($card['type']) ?>">
                    <div class="summary-label">
                        <?= h($card['app_name']) ?> · Net Profit
                    </div>

                    <div class="summary-value">
                        <?= h(number_format($card['net'], 8, '.', ',')) ?>
                    </div>

                    <div class="subtext" style="margin-top:10px;">
                        Cash Out: <?= h(number_format($card['cash_out'], 8, '.', ',')) ?><br>
                        Cash In: <?= h(number_format($card['cash_in'], 8, '.', ',')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($summary_cards)): ?>
    <div class="card mt-20">
        <h3>Tracked Category Totals</h3>
        <p class="subtext">These cards are based on Dashboard Settings and grouped by app.</p>

        <div class="summary-grid" style="margin-top:18px;">
            <?php foreach ($summary_cards as $card): ?>
                <div class="summary-card type-<?= h($card['type']) ?>">
                    <div class="summary-label">
                        <?= h($card['app_name']) ?> · <?= h($card['label']) ?>
                    </div>

                    <div class="summary-value">
                        <?= h(number_format($card['value'], 8, '.', ',')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($net_cards) || !empty($summary_cards) || !empty($recent_line_items)): ?>
    <?php if (!empty($net_cards) || !empty($summary_cards)): ?>
        <div class="grid-2 mt-20">
            <div class="card">
                <h3>Dashboard Notes</h3>
                <p class="subtext">
                    Net Profit tiles currently use
                    <strong>expense</strong> as cash in/spent and
                    <strong>withdrawal</strong> as cash out/received.
                </p>
                <p class="subtext">
                    Tracked Category Totals only show the categories you selected in Dashboard Settings.
                </p>
            </div>

            <div class="card">
                <h3>Recent Line Items</h3>

                <?php if (!$recent_line_items): ?>
                    <p class="subtext">No line items saved yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:90px;">ID</th>
                                    <th style="width:140px;">Date</th>
                                    <th>App</th>
                                    <th>Category</th>
                                    <th>Asset</th>
                                    <th>Miner</th>
                                    <th style="width:140px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_line_items as $item): ?>
                                    <tr>
                                        <td><?= (int)$item['id'] ?></td>
                                        <td><?= h($item['batch_date']) ?></td>
                                        <td><?= h($item['app_name'] ?? '') ?></td>
                                        <td><?= h($item['category_name'] ?? '') ?></td>
                                        <td>
                                            <?= h($item['asset_name'] ?? '') ?>
                                            <?php if (!empty($item['asset_symbol'])): ?>
                                                (<?= h($item['asset_symbol']) ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($item['miner_name'] ?? '') ?></td>
                                        <td><?= h(number_format((float)$item['amount'], 8, '.', ',')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card mt-20">
            <h3>Recent Line Items</h3>

            <?php if (!$recent_line_items): ?>
                <p class="subtext">No line items saved yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:90px;">ID</th>
                                <th style="width:140px;">Date</th>
                                <th>App</th>
                                <th>Category</th>
                                <th>Asset</th>
                                <th>Miner</th>
                                <th style="width:140px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_line_items as $item): ?>
                                <tr>
                                    <td><?= (int)$item['id'] ?></td>
                                    <td><?= h($item['batch_date']) ?></td>
                                    <td><?= h($item['app_name'] ?? '') ?></td>
                                    <td><?= h($item['category_name'] ?? '') ?></td>
                                    <td>
                                        <?= h($item['asset_name'] ?? '') ?>
                                        <?php if (!empty($item['asset_symbol'])): ?>
                                            (<?= h($item['asset_symbol']) ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($item['miner_name'] ?? '') ?></td>
                                    <td><?= h(number_format((float)$item['amount'], 8, '.', ',')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>