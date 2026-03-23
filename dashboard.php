<?php

$current_page = 'dashboard';

$selected_ids_raw = get_setting($conn, 'dashboard_category_ids', '');
$selected_ids = array_filter(array_map('intval', explode(',', $selected_ids_raw)));

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
| Selected dashboard category totals by asset
|--------------------------------------------------------------------------
|
| Show selected categories even if there is no data yet.
| Inside each card, show asset totals > 0 only.
|
*/
if ($selected_ids) {
    $id_list = implode(',', $selected_ids);

    $res = $conn->query("
		SELECT
					c.id AS category_id,
					c.category_name,
					c.behavior_type,
					c.dashboard_order,
					c.dashboard_row,
					c.sort_order,
					c.dashboard_order,
					ap.id AS app_id,
					ap.app_name,
					a.id AS asset_id,
					a.asset_name,
					a.asset_symbol,
					a.currency_symbol,
					a.display_decimals,
					a.is_fiat,
					COALESCE(SUM(bi.amount), 0) AS total_amount
        FROM categories c
        INNER JOIN apps ap
            ON ap.id = c.app_id
        LEFT JOIN batches b
            ON b.app_id = ap.id
        LEFT JOIN batch_items bi
            ON bi.batch_id = b.id
           AND bi.category_id = c.id
        LEFT JOIN assets a
            ON a.id = bi.asset_id
        WHERE c.id IN ($id_list)
        GROUP BY
            c.id, c.category_name, c.behavior_type, c.sort_order, c.dashboard_order,
            ap.id, ap.app_name,
            a.id, a.asset_name, a.asset_symbol
        ORDER BY 
			c.dashboard_row ASC, 
			c.dashboard_order ASC, 
			c.id ASC
    ");

    if ($res) {
        $cards_by_key = [];

        while ($row = $res->fetch_assoc()) {
            $card_key = $row['app_id'] . '_' . $row['category_id'];

            if (!isset($cards_by_key[$card_key])) {
                $cards_by_key[$card_key] = [
                    'category_id' => (int)$row['category_id'],
                    'dashboard_row' => (int)$row['dashboard_row'],
                    'dashboard_order' => (int)$row['dashboard_order'],
                    'app_name' => $row['app_name'] ?: 'Unassigned',
                    'label' => $row['category_name'],
                    'type' => $row['behavior_type'] ?? 'neutral',
                    'assets' => [],
                ];
            }

            $asset_id = (int)($row['asset_id'] ?? 0);
            $amount = (float)($row['total_amount'] ?? 0);

            if ($asset_id > 0 && $amount > 0) {
                $asset_label = $row['asset_name'] ?? '';
                if (!empty($row['asset_symbol'])) {
                    $asset_label .= ' (' . $row['asset_symbol'] . ')';
                }

                    $cards_by_key[$card_key]['assets'][] = [
						'label' => $asset_label,
						'value' => $amount,
						'currency_symbol' => $row['currency_symbol'] ?? '',
						'display_decimals' => (int)($row['display_decimals'] ?? 8),
						'is_fiat' => (int)($row['is_fiat'] ?? 0),
                ];
            }
        }

        $summary_cards = array_values($cards_by_key);

        usort($summary_cards, function ($a, $b) {
            if ($a['dashboard_row'] === $b['dashboard_row']) {
                if ($a['dashboard_order'] === $b['dashboard_order']) {
                    return strcmp($a['app_name'] . $a['label'], $b['app_name'] . $b['label']);
                }
                return $a['dashboard_order'] <=> $b['dashboard_order'];
            }
            return $a['dashboard_row'] <=> $b['dashboard_row'];
        });
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
		a.currency_symbol,
		a.display_decimals,
		a.is_fiat,
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
    <p class="subtext">Overview of your tracked reward data.</p>
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
    </div>
<?php endif; ?>

<?php if (!empty($summary_cards)): ?>
    <div class="card mt-20">
<div style="display:flex; align-items:center; gap:10px;">
    <h2 style="margin:0;">Tracked Category Totals</h2>

    <button 
        type="button" 
        id="toggle-dashboard-lock" 
        class="dashboard-lock-btn"
        title="Lock / Unlock dashboard layout"
    >
        🔒
    </button>
</div>
        <p class="subtext">These cards are based on Dashboard Settings and grouped by app. Drag to rearrange.</p>

<?php
$max_rows = 4; // change if you want more rows
$grouped_rows = [];

for ($i = 1; $i <= $max_rows; $i++) {
    $grouped_rows[$i] = [];
}

foreach ($summary_cards as $card) {
    $row_no = (int)($card['dashboard_row'] ?? 1);
    if ($row_no < 1) $row_no = 1;
    if ($row_no > $max_rows) $row_no = $max_rows;
    $grouped_rows[$row_no][] = $card;
}
?>
<div id="dashboard-rows" class="dashboard-rows" style="margin-top:18px;">
    <?php for ($row_no = 1; $row_no <= $max_rows; $row_no++): ?>
        <?php if (!empty($grouped_rows[$row_no])): ?>
            <div class="dashboard-row" data-row="<?= $row_no ?>">
                <?php foreach ($grouped_rows[$row_no] as $card): ?>
                    <div
                        class="summary-card dashboard-card <?= !empty($card['assets']) ? 'type-' . h($card['type']) : 'type-empty' ?>"
                        data-category-id="<?= (int)$card['category_id'] ?>"
                    >
                        <div class="summary-label">
                            <?= h($card['app_name']) ?> · <?= h($card['label']) ?>
                        </div>

                        <?php if (!empty($card['assets'])): ?>
                            <?php foreach ($card['assets'] as $asset): ?>
                                <div style="margin-top:10px;">
                                    <div class="subtext" style="margin-bottom:4px;">
                                        <?= h($asset['label']) ?>
                                    </div>
                                    <div class="summary-value" style="font-size:20px;">
                                        <?= h(fmt_asset_value(
                                            $asset['value'],
                                            (string)($asset['currency_symbol'] ?? ''),
                                            (int)($asset['display_decimals'] ?? 8),
                                            (int)($asset['is_fiat'] ?? 0)
                                        )) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="subtext" style="margin-top:12px;">No entries yet</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php if (!empty($summary_cards) || !empty($recent_line_items)): ?>
    <?php if (!empty($summary_cards)): ?>
        <div class="grid-2 mt-20">
            <div class="card">
                <h3>Dashboard Notes</h3>
                <p class="subtext">
                    Tracked Category Totals only show the categories selected in Dashboard Settings.
                </p>
                <p class="subtext">
                    Each card groups totals by asset so mixed categories like BTC and GMT can be viewed together.
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
                                        <td><?= h(fmt_asset_value(
											$item['amount'],
											(string)($item['currency_symbol'] ?? ''),
											(int)($item['display_decimals'] ?? 8),
											(int)($item['is_fiat'] ?? 0)
										)) ?></td>
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

<script src="assets/js/sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Sortable === 'undefined') return;

    let dashboardLocked = localStorage.getItem('dashboardLocked') !== '0';
    const toggleBtn = document.getElementById('toggle-dashboard-lock');
    const sortableInstances = [];

    function saveDashboardLayout() {
        const layout = [];

        document.querySelectorAll('.dashboard-row').forEach(function (row) {
            const rowNo = parseInt(row.dataset.row, 10) || 1;

            row.querySelectorAll('.dashboard-card').forEach(function (card, positionIndex) {
                layout.push({
                    category_id: parseInt(card.dataset.categoryId, 10),
                    dashboard_row: rowNo,
                    dashboard_order: positionIndex + 1
                });
            });
        });

        fetch('dashboard_save_layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ layout: layout })
        });
    }

    document.querySelectorAll('.dashboard-row').forEach(function (row) {
        const sortable = new Sortable(row, {
            group: 'dashboardRows',
            animation: 150,
            draggable: '.dashboard-card',
            disabled: dashboardLocked,
            onEnd: saveDashboardLayout
        });
        sortableInstances.push(sortable);
    });

    function applyLockState() {
        sortableInstances.forEach(function (instance) {
            instance.option('disabled', dashboardLocked);
        });

        document.querySelectorAll('.dashboard-card').forEach(function (card) {
            card.style.cursor = dashboardLocked ? 'default' : 'move';
        });

        if (toggleBtn) {
toggleBtn.textContent = dashboardLocked ? '🔒' : '🔓';
toggleBtn.classList.toggle('locked', dashboardLocked);
        }

        localStorage.setItem('dashboardLocked', dashboardLocked ? '1' : '0');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            dashboardLocked = !dashboardLocked;
            applyLockState();
        });
    }

    applyLockState();
});
</script>