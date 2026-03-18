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
            c.sort_order,
            c.dashboard_order,
            ap.id AS app_id,
            ap.app_name,
            a.id AS asset_id,
            a.asset_name,
            a.asset_symbol,
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
            c.dashboard_order ASC,
            ap.app_name ASC,
            c.sort_order ASC,
            c.category_name ASC,
            a.asset_name ASC
    ");

    if ($res) {
        $cards_by_key = [];

        while ($row = $res->fetch_assoc()) {
            $card_key = $row['app_id'] . '_' . $row['category_id'];

            if (!isset($cards_by_key[$card_key])) {
                $cards_by_key[$card_key] = [
                    'category_id' => (int)$row['category_id'],
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
                ];
            }
        }

        $summary_cards = array_values($cards_by_key);

        usort($summary_cards, function ($a, $b) {
            if ($a['dashboard_order'] === $b['dashboard_order']) {
                return strcmp($a['app_name'] . $a['label'], $b['app_name'] . $b['label']);
            }
            return $a['dashboard_order'] <=> $b['dashboard_order'];
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
        <h3>Tracked Category Totals</h3>
        <p class="subtext">These cards are based on Dashboard Settings and grouped by app. Drag to rearrange.</p>

        <div id="dashboard-cards" class="summary-grid" style="margin-top:18px;">
            <?php foreach ($summary_cards as $card): ?>
                <div
                    class="summary-card type-<?= h($card['type']) ?>"
                    data-category-id="<?= (int)$card['category_id'] ?>"
                    style="cursor:move;"
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
                                    <?= h(number_format($asset['value'], 8, '.', ',')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="subtext" style="margin-top:12px;">No entries yet</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
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

<script src="assets/js/sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.getElementById('dashboard-cards');
    if (!cards) return;

    new Sortable(cards, {
        animation: 150,
        onEnd: function () {
            const payload = [];

            cards.querySelectorAll('.summary-card').forEach(function (card, index) {
                payload.push({
                    category_id: parseInt(card.dataset.categoryId, 10),
                    position: index
                });
            });

            fetch('save_dashboard_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            }).catch(function (err) {
                console.error('Failed to save dashboard order', err);
            });
        }
    });
});
</script>