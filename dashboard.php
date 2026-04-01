<?php

$current_page = 'dashboard';

$selected_ids_raw = get_setting($conn, 'dashboard_category_ids', '');
$selected_ids = array_filter(array_map('intval', explode(',', $selected_ids_raw)));

$summary_cards = [];
$recent_line_items = [];
$dashboard_error = '';
$dashboard_notes = get_setting($conn, 'dashboard_notes', "Tracked Category Totals only show the categories selected in Dashboard Settings.\nEach card groups totals by asset so mixed categories like BTC and GMT can be viewed together.");

// ======================================================
// [DASHBOARD] DASHBOARD DISPLAY SETTINGS
// ======================================================

// Controls whether dashboard tiles show receipt-value totals
$dashboard_show_receipt_value = get_setting($conn, 'dashboard_show_receipt_value', '0') === '1';

// Controls whether dashboard totals show all time or current year only
$dashboard_date_mode = get_setting($conn, 'dashboard_date_mode', 'all_time');

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

    // ======================================================
    // [DASHBOARD] DATE FILTER MODE
    // ======================================================
    // Default = All Time (no date filter).
    // If Current Year is selected, only include batches dated
    // from January 1 of the current year forward.
    $dashboard_date_sql = '';

    if ($dashboard_date_mode === 'current_year') {
        $year_start = date('Y-01-01');
        $dashboard_date_sql = " AND b.batch_date >= '" . $conn->real_escape_string($year_start) . "'";
    }

    // ======================================================
    // [DASHBOARD] MAIN DASHBOARD TOTALS QUERY
    // ======================================================
    // Builds dashboard tile totals for selected categories.
    // Categories still appear even if they have no matching entries yet.
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
            ON b.app_id = ap.id$dashboard_date_sql
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
                    'receipt_value_total' => 0,
                    'receipt_value_count' => 0,
                ];
            }

            $asset_id = (int)($row['asset_id'] ?? 0);
            $amount = (float)($row['total_amount'] ?? 0);

            $cards_by_key[$card_key]['receipt_value_total'] += (float)($row['total_value_at_receipt'] ?? 0);
            $cards_by_key[$card_key]['receipt_value_count'] += (int)($row['value_at_receipt_count'] ?? 0);

            if ($asset_id > 0 && abs($amount) > 0.0000000001) {
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

        foreach ($cards_by_key as &$card) {
            if (!empty($card['assets'])) {
                usort($card['assets'], function ($a, $b) {
                    return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
                });
            }
        }
        unset($card);

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


// ======================================================
// [DASHBOARD] CALCULATED TILE TOTALS
// ======================================================
$dashboard_batch_where = '';
if ($dashboard_date_mode === 'current_year') {
    $year_start = date('Y-01-01');
    $dashboard_batch_where = " WHERE b.batch_date >= '" . $conn->real_escape_string($year_start) . "'";
}

$custom_tile_result = $conn->query("
    SELECT
        t.id AS tile_id,
        t.tile_name,
        t.dashboard_row,
        t.dashboard_order,
        t.is_active,
        i.id AS tile_item_id,
        i.category_id,
        i.operation,
        i.amount_mode,
        i.sort_order AS tile_item_sort_order,
        agg.asset_id,
        agg.raw_total,
        agg.absolute_total,
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat
    FROM custom_dashboard_tiles t
    LEFT JOIN custom_dashboard_tile_items i
        ON i.tile_id = t.id
    LEFT JOIN (
        SELECT
            bi.category_id,
            bi.asset_id,
            SUM(bi.amount) AS raw_total,
            SUM(ABS(bi.amount)) AS absolute_total
        FROM batch_items bi
        INNER JOIN batches b
            ON b.id = bi.batch_id
        " . $dashboard_batch_where . "
        GROUP BY bi.category_id, bi.asset_id
    ) agg
        ON agg.category_id = i.category_id
    LEFT JOIN assets a
        ON a.id = agg.asset_id
    WHERE t.is_active = 1
    ORDER BY t.dashboard_row ASC, t.dashboard_order ASC, t.id ASC, i.sort_order ASC, i.id ASC
");

if ($custom_tile_result) {
    $custom_tiles_by_key = [];

    while ($row = $custom_tile_result->fetch_assoc()) {
        $tile_id = (int)($row['tile_id'] ?? 0);
        if ($tile_id <= 0) {
            continue;
        }

        $card_key = 'tile_' . $tile_id;

        if (!isset($custom_tiles_by_key[$card_key])) {
            $custom_tiles_by_key[$card_key] = [
                'category_id' => 0,
                'tile_id' => $tile_id,
                'item_type' => 'custom_tile',
                'is_custom_tile' => 1,
                'dashboard_row' => (int)($row['dashboard_row'] ?? 1),
                'dashboard_order' => (int)($row['dashboard_order'] ?? 0),
                'app_name' => 'Calculated Tile',
                'label' => $row['tile_name'] ?? 'Calculated Tile',
                'type' => 'adjustment',
                'assets' => [],
                'asset_map' => [],
                'receipt_value_total' => 0,
                'receipt_value_count' => 0,
            ];
        }

        $asset_id = (int)($row['asset_id'] ?? 0);
        if ($asset_id <= 0) {
            continue;
        }

        $amount_mode = (($row['amount_mode'] ?? 'absolute') === 'raw_signed') ? 'raw_signed' : 'absolute';
        $operation = (($row['operation'] ?? 'add') === 'subtract') ? 'subtract' : 'add';
        $base_amount = ($amount_mode === 'raw_signed')
            ? (float)($row['raw_total'] ?? 0)
            : (float)($row['absolute_total'] ?? 0);

        if (abs($base_amount) <= 0.0000000001) {
            continue;
        }

        $signed_amount = ($operation === 'subtract') ? -$base_amount : $base_amount;

        if (!isset($custom_tiles_by_key[$card_key]['asset_map'][$asset_id])) {
            $asset_label = $row['asset_name'] ?? '';
            if (!empty($row['asset_symbol'])) {
                $asset_label .= ' (' . $row['asset_symbol'] . ')';
            }

            $custom_tiles_by_key[$card_key]['asset_map'][$asset_id] = [
                'label' => $asset_label,
                'value' => 0,
                'currency_symbol' => $row['currency_symbol'] ?? '',
                'display_decimals' => (int)($row['display_decimals'] ?? 8),
                'is_fiat' => (int)($row['is_fiat'] ?? 0),
            ];
        }

        $custom_tiles_by_key[$card_key]['asset_map'][$asset_id]['value'] += $signed_amount;
    }

    foreach ($custom_tiles_by_key as &$card) {
        foreach ($card['asset_map'] as $asset_id => $asset_row) {
            if (abs((float)($asset_row['value'] ?? 0)) <= 0.0000000001) {
                continue;
            }
            $card['assets'][] = $asset_row;
        }

        if (!empty($card['assets'])) {
            usort($card['assets'], function ($a, $b) {
                return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
            });
        }

        unset($card['asset_map']);
    }
    unset($card);

    $summary_cards = array_merge($summary_cards, array_values($custom_tiles_by_key));

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
    $dashboard_error = 'Calculated tiles query failed: ' . $conn->error;
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
$max_rows = (int)get_setting($conn, 'dashboard_max_rows', '4');
if ($max_rows < 1) $max_rows = 4;
if ($max_rows > 12) $max_rows = 12;
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
            <?php $row_is_empty = empty($grouped_rows[$row_no]); ?>
            <div class="dashboard-row" data-row="<?= $row_no ?>" data-empty-row="<?= $row_is_empty ? '1' : '0' ?>"<?= $row_is_empty ? ' style="display:none;"' : '' ?>>
                <?php foreach ($grouped_rows[$row_no] as $card): ?>
                    <?php
						$card_type = $card['type'] ?? 'default';
                        if (strtolower((string)($card['type'] ?? '')) === 'adjustment') {
							$card_type = 'balance';
						}
					?>
                    <div
                        class="summary-card dashboard-card <?= !empty($card['assets']) ? 'type-' . h($card_type) : 'type-empty' ?>"
                        data-item-type="<?= h($card['item_type'] ?? 'category') ?>"
                        data-category-id="<?= (int)($card['category_id'] ?? 0) ?>"
                        data-tile-id="<?= (int)($card['tile_id'] ?? 0) ?>"
                    >
                        <div class="summary-label">
                            <div class="dashboard-card-app"><?= h($card['app_name']) ?></div>
                            <div class="dashboard-card-category"><?= h($card['label']) ?></div>
                            <?php if (!empty($card['is_custom_tile'])): ?>
                                <div class="dashboard-custom-tile-badge">Custom calculation</div>
                            <?php endif; ?>
                        </div>

                        <div class="dashboard-assets-wrap">
                            <div class="dashboard-assets-viewport">
                                <?php if (!empty($card['assets'])): ?>
                                    <div class="dashboard-assets-track">
                                        <?php foreach ($card['assets'] as $asset): ?>
                                            <div class="dashboard-asset-row">
                                                <div class="dashboard-asset-label">
                                                    <?= h($asset['label']) ?>
                                                </div>
                                                <div class="dashboard-asset-value">
                                                    <?= h(fmt_asset_value(
                                                        $asset['value'],
                                                        (string)($asset['currency_symbol'] ?? '') . ' ',
                                                        (int)($asset['display_decimals'] ?? 8),
                                                        (int)($asset['is_fiat'] ?? 0)
                                                    )) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dashboard-card-empty"><?= !empty($card['is_custom_tile']) ? 'No matching entries yet' : 'No entries yet' ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($dashboard_show_receipt_value && (int)($card['receipt_value_count'] ?? 0) > 0): ?>
                            <div class="dashboard-receipt-total" style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08);">
                                <div class="dashboard-asset-row">
                                    <div class="dashboard-asset-label">
                                        <?= h(((string)($card['type'] ?? '') === 'expense') ? 'Cost at Payment' : 'Value at Receipt') ?>
                                    </div>
                                    <div class="dashboard-asset-value">
                                        <?= h(fmt_asset_value((float)($card['receipt_value_total'] ?? 0), '$', 2, 1)) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($grouped_rows[$row_no])): ?>
                    <div class="dashboard-row-empty subtext">Drop cards here</div>
                <?php endif; ?>
            </div>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php if (!empty($summary_cards) || !empty($recent_line_items)): ?>
    <?php if (!empty($summary_cards)): ?>
        <div class="grid-2 mt-20">
            <div class="card">
                <div class="dashboard-notes-header">
					<h2>Dashboard Notes</h2>
					<button id="toggle-dashboard-notes-edit" class="edit-notes-btn">✏️</button>
				</div>

                <div id="dashboard-notes-view">
                    <?php $dashboard_note_lines = preg_split('/\r\n|\r|\n/', trim((string)$dashboard_notes)); ?>
                    <?php if (!empty(array_filter($dashboard_note_lines, fn($line) => trim($line) !== ''))): ?>
                        <?php foreach ($dashboard_note_lines as $note_line): ?>
                            <?php if (trim($note_line) !== ''): ?>
                                <p class="subtext"><?= h($note_line) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="subtext">No notes yet.</p>
                    <?php endif; ?>
                </div>

                <form id="dashboard-notes-form" class="dashboard-notes-form" style="display:none;">
                    <textarea
                        id="dashboard-notes-input"
                        name="dashboard_notes"
                        class="input"
                        rows="6"
                        placeholder="Add notes for your dashboard here..."
                    ><?= h($dashboard_notes) ?></textarea>
                    <div class="dashboard-notes-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" id="cancel-dashboard-notes-edit" class="btn btn-secondary btn-sm">Cancel</button>
                        <span id="dashboard-notes-status" class="subtext" aria-live="polite"></span>
                    </div>
                </form>
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

    const notesToggleBtn = document.getElementById('toggle-dashboard-notes-edit');
    const notesForm = document.getElementById('dashboard-notes-form');
    const notesView = document.getElementById('dashboard-notes-view');
    const notesInput = document.getElementById('dashboard-notes-input');
    const notesCancelBtn = document.getElementById('cancel-dashboard-notes-edit');
    const notesStatus = document.getElementById('dashboard-notes-status');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderDashboardNotes(value) {
        const lines = String(value || '').split(/\r?\n/).filter(function (line) {
            return line.trim() !== '';
        });

        if (!notesView) return;

        if (!lines.length) {
            notesView.innerHTML = '<p class="subtext">No notes yet.</p>';
            return;
        }

        notesView.innerHTML = lines.map(function (line) {
            return '<p class="subtext">' + escapeHtml(line) + '</p>';
        }).join('');
    }

    function setDashboardNotesEditing(editing) {
        if (!notesForm || !notesView || !notesToggleBtn) return;

        notesForm.style.display = editing ? '' : 'none';
        notesView.style.display = editing ? 'none' : '';
        notesToggleBtn.textContent = editing ? '✅' : '✏️';
        notesToggleBtn.classList.toggle('editing', editing);

        if (editing && notesInput) {
            notesInput.focus();
            notesInput.setSelectionRange(notesInput.value.length, notesInput.value.length);
        }

        if (!editing && notesStatus) {
            notesStatus.textContent = '';
        }
    }

    function refreshDashboardRowPlaceholders() {
        document.querySelectorAll('.dashboard-row').forEach(function (row) {
            const emptyState = row.querySelector('.dashboard-row-empty');
            const cardCount = row.querySelectorAll('.dashboard-card').length;
            const shouldShowRow = cardCount > 0 || !dashboardLocked;

            row.style.display = shouldShowRow ? '' : 'none';
            row.dataset.emptyRow = cardCount === 0 ? '1' : '0';

            if (!emptyState) return;
            emptyState.style.display = (cardCount === 0 && !dashboardLocked) ? '' : 'none';
        });
    }

    function saveDashboardLayout() {
        const layout = [];

        document.querySelectorAll('.dashboard-row').forEach(function (row) {
            const rowNo = parseInt(row.dataset.row, 10) || 1;

            row.querySelectorAll('.dashboard-card').forEach(function (card, positionIndex) {
                layout.push({
                    item_type: card.dataset.itemType || 'category',
                    category_id: parseInt(card.dataset.categoryId, 10) || 0,
                    tile_id: parseInt(card.dataset.tileId, 10) || 0,
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

    function debounce(fn, delay) {
        let timer = null;

        return function () {
            const context = this;
            const args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function setupDashboardCardScroller(card) {
        const viewport = card.querySelector('.dashboard-assets-viewport');
        const track = card.querySelector('.dashboard-assets-track');

        if (!viewport || !track) return;

        card.classList.remove('is-scrolling');
        card.style.removeProperty('--scroll-distance');
        card.style.removeProperty('--scroll-duration');
        track.style.animationDuration = '';

        if (track.dataset.originalHtml) {
            track.innerHTML = track.dataset.originalHtml;
        } else {
            track.dataset.originalHtml = track.innerHTML;
        }

        const originalHeight = track.scrollHeight;
        const viewportHeight = viewport.clientHeight;

        if (originalHeight <= viewportHeight + 2) {
            return;
        }

        const originalHtml = track.dataset.originalHtml.trim();
        if (!originalHtml) {
            return;
        }

        track.innerHTML = originalHtml + originalHtml;

        card.style.setProperty('--scroll-distance', originalHeight + 'px');

        const pixelsPerSecond = 22;
        const duration = Math.max(12, originalHeight / pixelsPerSecond);
        card.style.setProperty('--scroll-duration', duration + 's');
        track.style.animationDuration = duration + 's';

        card.classList.add('is-scrolling');
    }

    function initDashboardCardScrollers() {
        document.querySelectorAll('.dashboard-card').forEach(function (card) {
            setupDashboardCardScroller(card);
        });
    }

    document.querySelectorAll('.dashboard-row').forEach(function (row) {
        const sortable = new Sortable(row, {
            group: 'dashboardRows',
            animation: 150,
            draggable: '.dashboard-card',
            disabled: dashboardLocked,
            onEnd: function () {
                saveDashboardLayout();
                refreshDashboardRowPlaceholders();
                initDashboardCardScrollers();
            }
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

        refreshDashboardRowPlaceholders();

    if (toggleBtn) {
toggleBtn.textContent = dashboardLocked ? '🔒' : '🔓';
toggleBtn.classList.toggle('locked', dashboardLocked);
        }

        localStorage.setItem('dashboardLocked', dashboardLocked ? '1' : '0');
    }

    refreshDashboardRowPlaceholders();

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            dashboardLocked = !dashboardLocked;
            applyLockState();
        });
    }


    if (notesToggleBtn) {
        notesToggleBtn.addEventListener('click', function () {
            if (!notesForm || notesForm.style.display === 'none') {
                setDashboardNotesEditing(true);
            } else if (notesForm) {
                notesForm.requestSubmit();
            }
        });
    }

    if (notesCancelBtn) {
        notesCancelBtn.addEventListener('click', function () {
            if (notesInput) {
                notesInput.value = notesInput.defaultValue;
            }
            setDashboardNotesEditing(false);
        });
    }

    if (notesForm) {
        notesForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const payload = {
                dashboard_notes: notesInput ? notesInput.value : ''
            };

            if (notesStatus) {
                notesStatus.textContent = 'Saving...';
            }

            fetch('dashboard_save_notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not save dashboard notes.');
                }
                return response.json();
            })
            .then(function (data) {
                const savedNotes = data.dashboard_notes || '';
                if (notesInput) {
                    notesInput.value = savedNotes;
                    notesInput.defaultValue = savedNotes;
                }
                renderDashboardNotes(savedNotes);
                if (notesStatus) {
                    notesStatus.textContent = 'Saved.';
                }
                setTimeout(function () {
                    setDashboardNotesEditing(false);
                }, 350);
            })
            .catch(function (error) {
                if (notesStatus) {
                    notesStatus.textContent = error.message || 'Save failed.';
                }
            });
        });
    }

    renderDashboardNotes(notesInput ? notesInput.value : '');
    setDashboardNotesEditing(false);

    initDashboardCardScrollers();
    window.addEventListener('resize', debounce(function () {
        initDashboardCardScrollers();
    }, 150));

    applyLockState();
});
</script>