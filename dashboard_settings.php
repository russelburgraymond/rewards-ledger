<?php

$current_page = 'dashboard_settings';

$error = "";
$success = "";

if (!function_exists('dashboard_fetch_next_custom_tile_order')) {
    function dashboard_fetch_next_custom_tile_order(mysqli $conn, int $dashboard_row = 1): int
    {
        $dashboard_row = max(1, $dashboard_row);
        $max_order = 0;

        $res = $conn->query("SELECT COALESCE(MAX(dashboard_order), 0) AS max_order FROM categories WHERE dashboard_row = " . (int)$dashboard_row);
        if ($res && ($row = $res->fetch_assoc())) {
            $max_order = max($max_order, (int)($row['max_order'] ?? 0));
        }

        $res = $conn->query("SELECT COALESCE(MAX(dashboard_order), 0) AS max_order FROM custom_dashboard_tiles WHERE dashboard_row = " . (int)$dashboard_row);
        if ($res && ($row = $res->fetch_assoc())) {
            $max_order = max($max_order, (int)($row['max_order'] ?? 0));
        }

        return $max_order + 1;
    }
}

if (!function_exists('dashboard_delete_custom_tile')) {
    function dashboard_delete_custom_tile(mysqli $conn, int $tile_id): void
    {
        if ($tile_id <= 0) {
            return;
        }

        $stmt = $conn->prepare("DELETE FROM custom_dashboard_tile_items WHERE tile_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $tile_id);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM custom_dashboard_tiles WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $tile_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}


if (!function_exists('dashboard_collect_error')) {
    function dashboard_collect_error(array &$messages, string $message): void
    {
        $message = trim($message);
        if ($message !== '') {
            $messages[] = $message;
        }
    }
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['category_ids'] ?? [];

    if (!is_array($selected)) {
        $selected = [];
    }

    $clean_ids = [];
    foreach ($selected as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $clean_ids[] = $id;
        }
    }

    $save_errors = [];

    // ======================================================
    // [SETTINGS] SAVE DASHBOARD SETTINGS
    // ======================================================
    $value = implode(',', $clean_ids);
    $show_receipt_value = isset($_POST['dashboard_show_receipt_value']) ? '1' : '0';

    // Only allow valid values for dashboard date range mode.
    $dashboard_date_mode = (($_POST['dashboard_date_mode'] ?? 'all_time') === 'current_year')
        ? 'current_year'
        : 'all_time';

    $enable_price_lookup = isset($_POST['enable_price_lookup']) ? '1' : '0';
    $coingecko_demo_api_key = trim((string)($_POST['coingecko_demo_api_key'] ?? ''));
    $dashboard_max_rows = (int)($_POST['dashboard_max_rows'] ?? 4);
    if ($dashboard_max_rows < 1) $dashboard_max_rows = 1;
    if ($dashboard_max_rows > 12) $dashboard_max_rows = 12;

    $ok = true;

    $ok = set_setting($conn, 'dashboard_category_ids', $value) && $ok;
    $ok = set_setting($conn, 'dashboard_show_receipt_value', $show_receipt_value) && $ok;
    $ok = set_setting($conn, 'dashboard_date_mode', $dashboard_date_mode) && $ok;
    $ok = set_setting($conn, 'enable_price_lookup', $enable_price_lookup) && $ok;
    $ok = set_setting($conn, 'coingecko_demo_api_key', $coingecko_demo_api_key) && $ok;
    $ok = set_setting($conn, 'dashboard_max_rows', (string)$dashboard_max_rows) && $ok;

    if (!$ok) {
        dashboard_collect_error($save_errors, 'One or more dashboard settings could not be saved.');
    }

    // ======================================================
    // [SETTINGS] SAVE CALCULATED TILES
    // ======================================================
    $custom_tiles_posted = $_POST['custom_tiles'] ?? [];

    if (is_array($custom_tiles_posted)) {
        $conn->begin_transaction();

        foreach ($custom_tiles_posted as $tile_key => $tile_data) {
            if (!is_array($tile_data)) {
                continue;
            }

            $tile_id = (is_numeric($tile_key) && (int)$tile_key > 0) ? (int)$tile_key : 0;
            $tile_name = trim((string)($tile_data['tile_name'] ?? ''));
            $is_active = isset($tile_data['is_active']) ? 1 : 0;
            $delete_tile = isset($tile_data['delete_tile']);
            $tile_items = $tile_data['items'] ?? [];
            $valid_items = [];
            $line_sort_order = 1;

            if (is_array($tile_items)) {
                foreach ($tile_items as $item_data) {
                    if (!is_array($item_data)) {
                        continue;
                    }

                    $category_id = (int)($item_data['category_id'] ?? 0);
                    if ($category_id <= 0) {
                        continue;
                    }

                    $operation = (($item_data['operation'] ?? 'add') === 'subtract') ? 'subtract' : 'add';
                    $amount_mode = (($item_data['amount_mode'] ?? 'absolute') === 'raw_signed') ? 'raw_signed' : 'absolute';

                    $valid_items[] = [
                        'category_id' => $category_id,
                        'operation' => $operation,
                        'amount_mode' => $amount_mode,
                        'sort_order' => $line_sort_order++,
                    ];
                }
            }

            if ($tile_id > 0 && $delete_tile) {
                dashboard_delete_custom_tile($conn, $tile_id);
                continue;
            }

            if ($tile_name === '' || empty($valid_items)) {
                continue;
            }

            if ($tile_id > 0) {
                $stmt = $conn->prepare("UPDATE custom_dashboard_tiles SET tile_name = ?, is_active = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('sii', $tile_name, $is_active, $tile_id);
                    if (!$stmt->execute()) {
                        dashboard_collect_error($save_errors, 'Could not update calculated tile "' . $tile_name . '": ' . $stmt->error);
                        $ok = false;
                    }
                    $stmt->close();
                } else {
                    dashboard_collect_error($save_errors, 'Could not prepare calculated tile update: ' . $conn->error);
                    $ok = false;
                }
            } else {
                $next_dashboard_order = dashboard_fetch_next_custom_tile_order($conn, 1);
                $stmt = $conn->prepare("INSERT INTO custom_dashboard_tiles (tile_name, dashboard_row, dashboard_order, is_active) VALUES (?, 1, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sii', $tile_name, $next_dashboard_order, $is_active);
                    if ($stmt->execute()) {
                        $tile_id = (int)$stmt->insert_id;
                    } else {
                        dashboard_collect_error($save_errors, 'Could not create calculated tile "' . $tile_name . '": ' . $stmt->error);
                        $ok = false;
                        $tile_id = 0;
                    }
                    $stmt->close();
                } else {
                    dashboard_collect_error($save_errors, 'Could not prepare calculated tile insert: ' . $conn->error);
                    $ok = false;
                    $tile_id = 0;
                }
            }

            if ($tile_id <= 0) {
                continue;
            }

            $stmt_delete = $conn->prepare("DELETE FROM custom_dashboard_tile_items WHERE tile_id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param('i', $tile_id);
                if (!$stmt_delete->execute()) {
                    dashboard_collect_error($save_errors, 'Could not clear existing calculated tile lines for "' . $tile_name . '": ' . $stmt_delete->error);
                    $ok = false;
                }
                $stmt_delete->close();
            } else {
                dashboard_collect_error($save_errors, 'Could not prepare calculated tile line cleanup: ' . $conn->error);
                $ok = false;
            }

            $stmt_insert = $conn->prepare("INSERT INTO custom_dashboard_tile_items (tile_id, category_id, operation, amount_mode, sort_order) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_insert) {
                foreach ($valid_items as $item) {
                    $category_id = (int)$item['category_id'];
                    $operation = $item['operation'];
                    $amount_mode = $item['amount_mode'];
                    $item_sort_order = (int)$item['sort_order'];
                    $stmt_insert->bind_param('iissi', $tile_id, $category_id, $operation, $amount_mode, $item_sort_order);
                    if (!$stmt_insert->execute()) {
                        dashboard_collect_error($save_errors, 'Could not save a calculated tile line for "' . $tile_name . '": ' . $stmt_insert->error);
                        $ok = false;
                        break;
                    }
                }
                $stmt_insert->close();
            } else {
                dashboard_collect_error($save_errors, 'Could not prepare calculated tile line insert: ' . $conn->error);
                $ok = false;
            }
        }

        if ($ok) {
            $conn->commit();
        } else {
            $conn->rollback();
        }
    }

    if ($ok) {
        $success = "Dashboard settings updated.";
    } else {
        $error = "Could not save dashboard settings.";
        if ($save_errors) {
            $error .= ' ' . implode(' ', array_unique($save_errors));
        }
    }
}

/* -----------------------------
   LOAD CURRENT SETTINGS
----------------------------- */
// ======================================================
// [SETTINGS] LOAD DASHBOARD SETTINGS
// ======================================================
$selected_ids_raw = get_setting($conn, 'dashboard_category_ids', '');
$selected_ids = array_filter(array_map('intval', explode(',', $selected_ids_raw)));
$dashboard_show_receipt_value = get_setting($conn, 'dashboard_show_receipt_value', '0') === '1';
$dashboard_date_mode = get_setting($conn, 'dashboard_date_mode', 'all_time');
$enable_price_lookup = get_setting($conn, 'enable_price_lookup', '1') === '1';
$coingecko_demo_api_key = get_setting($conn, 'coingecko_demo_api_key', '');
$dashboard_max_rows = (int)get_setting($conn, 'dashboard_max_rows', '4');
if ($dashboard_max_rows < 1) $dashboard_max_rows = 4;

/* -----------------------------
   LOAD APPS + CATEGORIES
----------------------------- */
$app_groups = [];
$category_options = [];
$custom_tiles = [];

$res = $conn->query("
    SELECT
        a.id AS app_id,
        a.app_name,
        a.sort_order,
        c.id AS category_id,
        c.category_name,
        c.behavior_type,
        c.sort_order AS category_sort_order
    FROM apps a
    LEFT JOIN categories c
        ON c.app_id = a.id
       AND c.is_active = 1
    WHERE a.is_active = 1
    ORDER BY a.sort_order ASC, a.app_name ASC, c.sort_order ASC, c.category_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $app_id = (int)$row['app_id'];

        if (!isset($app_groups[$app_id])) {
            $app_groups[$app_id] = [
                'app_id' => $app_id,
                'app_name' => $row['app_name'],
                'categories' => [],
            ];
        }

        if (!empty($row['category_id'])) {
            $category_row = [
                'id' => (int)$row['category_id'],
                'category_name' => $row['category_name'],
                'behavior_type' => $row['behavior_type'],
                'sort_order' => (int)$row['category_sort_order'],
                'app_name' => $row['app_name'],
            ];

            $app_groups[$app_id]['categories'][] = $category_row;
            $category_options[] = $category_row;
        }
    }
} else {
    $error = "Could not load dashboard categories: " . $conn->error;
}

// ======================================================
// [SETTINGS] LOAD CALCULATED TILES
// ======================================================
$custom_tiles_result = $conn->query("
    SELECT
        t.id,
        t.tile_name,
        t.dashboard_row,
        t.dashboard_order,
        t.is_active,
        i.id AS item_id,
        i.category_id,
        i.operation,
        i.amount_mode,
        i.sort_order,
        c.category_name,
        a.app_name
    FROM custom_dashboard_tiles t
    LEFT JOIN custom_dashboard_tile_items i
        ON i.tile_id = t.id
    LEFT JOIN categories c
        ON c.id = i.category_id
    LEFT JOIN apps a
        ON a.id = c.app_id
    ORDER BY t.dashboard_row ASC, t.dashboard_order ASC, t.id ASC, i.sort_order ASC, i.id ASC
");

if ($custom_tiles_result) {
    while ($row = $custom_tiles_result->fetch_assoc()) {
        $tile_id = (int)($row['id'] ?? 0);
        if ($tile_id <= 0) {
            continue;
        }

        if (!isset($custom_tiles[$tile_id])) {
            $custom_tiles[$tile_id] = [
                'id' => $tile_id,
                'tile_name' => $row['tile_name'] ?? '',
                'dashboard_row' => (int)($row['dashboard_row'] ?? 1),
                'dashboard_order' => (int)($row['dashboard_order'] ?? 0),
                'is_active' => (int)($row['is_active'] ?? 1),
                'items' => [],
            ];
        }

        $item_id = (int)($row['item_id'] ?? 0);
        if ($item_id > 0) {
            $custom_tiles[$tile_id]['items'][] = [
                'item_id' => $item_id,
                'category_id' => (int)($row['category_id'] ?? 0),
                'operation' => (($row['operation'] ?? 'add') === 'subtract') ? 'subtract' : 'add',
                'amount_mode' => (($row['amount_mode'] ?? 'absolute') === 'raw_signed') ? 'raw_signed' : 'absolute',
                'category_name' => $row['category_name'] ?? '',
                'app_name' => $row['app_name'] ?? '',
            ];
        }
    }
}

ob_start();
?>
<option value="0">Select category</option>
<?php foreach ($category_options as $option): ?>
    <option value="<?= (int)$option['id'] ?>"><?= h($option['app_name'] . ' — ' . $option['category_name']) ?></option>
<?php endforeach; ?>
<?php
$category_select_options_html = trim(ob_get_clean());
?>

<div class="page-head">
    <h2>Dashboard Settings</h2>
    <p class="subtext">Choose which category tiles should appear on the dashboard, and build custom calculated tiles.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<form method="post">
    <div class="grid-2">
        <div class="card">
            <h3>Select Dashboard Categories</h3>

            <?php if (!$app_groups): ?>
                <p class="subtext">No active apps or categories available.</p>
            <?php else: ?>
                <?php foreach ($app_groups as $group): ?>
                    <div class="card" style="margin-bottom:16px; padding:16px;">
                        <h3 style="margin-top:0;"><?= h($group['app_name']) ?></h3>

                        <?php if (!$group['categories']): ?>
                            <p class="subtext">No active categories for this app.</p>
                        <?php else: ?>
                            <div style="display:grid; gap:8px; margin-top:8px;">
                                <?php foreach ($group['categories'] as $c): ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="category_ids[]"
                                            value="<?= (int)$c['id'] ?>"
                                            <?= in_array((int)$c['id'], $selected_ids, true) ? 'checked' : '' ?>
                                        >
                                        <?= h($c['category_name']) ?>
                                        <?php if (trim((string)$c['behavior_type']) !== ''): ?>
                                            <span class="subtext">(<?= h($c['behavior_type']) ?>)</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="card" style="margin:16px 0; padding:16px;">
                    <h3 style="margin-top:0;">Dashboard Layout</h3>

                    <?php
                    // ======================================================
                    // [SETTINGS] DASHBOARD DATE RANGE MODE
                    // ======================================================
                    ?>
                    <div class="form-row" style="margin-bottom:12px;">
                        <label for="dashboard_date_mode">Dashboard date range</label>
                        <select id="dashboard_date_mode" name="dashboard_date_mode">
                            <option value="all_time" <?= $dashboard_date_mode === 'all_time' ? 'selected' : '' ?>>All Time</option>
                            <option value="current_year" <?= $dashboard_date_mode === 'current_year' ? 'selected' : '' ?>>Current Year</option>
                        </select>
                        <div class="subtext" style="margin-top:6px;">Controls whether dashboard tiles total all entries or only entries from the beginning of the current year.</div>
                    </div>

                    <?php
                    // ======================================================
                    // [SETTINGS] DASHBOARD ROW COUNT
                    // ======================================================
                    ?>
                    <div class="form-row" style="margin-bottom:12px;">
                        <label for="dashboard_max_rows">Number of dashboard tile rows</label>
                        <input type="number" min="1" max="12" id="dashboard_max_rows" name="dashboard_max_rows" value="<?= (int)$dashboard_max_rows ?>">
                        <div class="subtext" style="margin-top:6px;">Controls how many dotted dashboard drop rows are shown on the main dashboard.</div>
                    </div>
                </div>

                <div class="card" style="margin:16px 0; padding:16px;">
                    <h3 style="margin-top:0;">Valuation Tools</h3>

                    <label style="display:block; margin-bottom:10px;">
                        <input type="checkbox" name="dashboard_show_receipt_value" <?= $dashboard_show_receipt_value ? 'checked' : '' ?>>
                        Show Value at Receipt / Cost at Payment totals on dashboard tiles
                    </label>

                    <label style="display:block; margin-bottom:10px;">
                        <input type="checkbox" name="enable_price_lookup" <?= $enable_price_lookup ? 'checked' : '' ?>>
                        Enable manual price lookup buttons on Quick Entry and Template Use forms
                    </label>

                    <div class="form-row" style="margin-top:12px;">
                        <label for="coingecko_demo_api_key">CoinGecko Demo API Key (optional)</label>
                        <input type="text" id="coingecko_demo_api_key" name="coingecko_demo_api_key" value="<?= h($coingecko_demo_api_key) ?>" placeholder="Leave blank to try public access">
                        <div class="subtext" style="margin-top:6px;">Optional. If you add a Demo API key here, the lookup button will send it from your server.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; gap:16px;">
            <div class="card">
                <h3>Calculated Tiles</h3>
                <p class="subtext">Create custom dashboard tiles by choosing categories and deciding how each one should affect the total.</p>

                <div id="custom-tiles-list" style="display:grid; gap:16px; margin-top:12px;">
                    <?php foreach ($custom_tiles as $tile): ?>
                        <div class="card custom-tile-editor" data-tile-key="<?= (int)$tile['id'] ?>" style="padding:16px;">
                            <div class="form-row" style="margin-bottom:10px;">
                                <label>Tile Name</label>
                                <input type="text" name="custom_tiles[<?= (int)$tile['id'] ?>][tile_name]" value="<?= h($tile['tile_name']) ?>" placeholder="Example: Approximate Gross">
                            </div>

                            <label style="display:block; margin-bottom:10px;">
                                <input type="checkbox" name="custom_tiles[<?= (int)$tile['id'] ?>][is_active]" <?= !empty($tile['is_active']) ? 'checked' : '' ?>>
                                Active
                            </label>

                            <div class="subtext" style="margin-bottom:10px;">Dashboard placement is controlled by dragging tiles on the main Dashboard page.</div>

                            <div class="custom-tile-items" style="display:grid; gap:10px;">
                                <?php foreach ($tile['items'] as $item_index => $item): ?>
                                    <div class="custom-tile-item-row" style="border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px;">
                                        <div class="form-row" style="margin-bottom:8px;">
                                            <label>Category</label>
                                            <select name="custom_tiles[<?= (int)$tile['id'] ?>][items][<?= (int)$item_index ?>][category_id]">
                                                <option value="0">Select category</option>
                                                <?php foreach ($category_options as $option): ?>
                                                    <option value="<?= (int)$option['id'] ?>" <?= (int)$item['category_id'] === (int)$option['id'] ? 'selected' : '' ?>><?= h($option['app_name'] . ' — ' . $option['category_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="grid-2" style="gap:12px;">
                                            <div class="form-row">
                                                <label>Math Action</label>
                                                <select name="custom_tiles[<?= (int)$tile['id'] ?>][items][<?= (int)$item_index ?>][operation]">
                                                    <option value="add" <?= $item['operation'] === 'add' ? 'selected' : '' ?>>Add</option>
                                                    <option value="subtract" <?= $item['operation'] === 'subtract' ? 'selected' : '' ?>>Subtract</option>
                                                </select>
                                            </div>
                                            <div class="form-row">
                                                <label>Amount Mode</label>
                                                <select name="custom_tiles[<?= (int)$tile['id'] ?>][items][<?= (int)$item_index ?>][amount_mode]">
                                                    <option value="absolute" <?= $item['amount_mode'] === 'absolute' ? 'selected' : '' ?>>Absolute Amount</option>
                                                    <option value="raw_signed" <?= $item['amount_mode'] === 'raw_signed' ? 'selected' : '' ?>>Raw Signed Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div style="margin-top:10px;">
                                            <button type="button" class="btn btn-secondary btn-sm remove-custom-tile-item">Remove Line</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; align-items:center;">
                                <button type="button" class="btn btn-secondary btn-sm add-custom-tile-item">Add Line</button>
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="custom_tiles[<?= (int)$tile['id'] ?>][delete_tile]">
                                    Delete this tile
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:14px;">
                    <button type="button" id="add-custom-tile" class="btn btn-secondary">Add Calculated Tile</button>
                </div>
            </div>

            <div class="card">
                <h3>How Calculated Tiles Work</h3>
                <ul class="subtext" style="padding-left:18px; margin:0; display:grid; gap:8px;">
                    <li><strong>Add</strong> includes the selected category in the tile total.</li>
                    <li><strong>Subtract</strong> removes the selected category from the tile total.</li>
                    <li><strong>Absolute Amount</strong> ignores the sign stored in the ledger and uses the magnitude only.</li>
                    <li><strong>Raw Signed Amount</strong> keeps the ledger amount exactly as entered, which is useful for Balance-style categories.</li>
                </ul>
                <p class="subtext" style="margin-top:12px;">Example: An <strong>Approximate Gross</strong> tile can include Net Rewards, Daily Electricity, and Daily Service all set to <strong>Add</strong> with <strong>Absolute Amount</strong>.</p>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">Save Dashboard Settings</button>
    </div>
</form>

<template id="custom-tile-template">
    <div class="card custom-tile-editor" data-tile-key="__TILE_KEY__" style="padding:16px;">
        <div class="form-row" style="margin-bottom:10px;">
            <label>Tile Name</label>
            <input type="text" name="custom_tiles[__TILE_KEY__][tile_name]" value="" placeholder="Example: Approximate Gross">
        </div>

        <label style="display:block; margin-bottom:10px;">
            <input type="checkbox" name="custom_tiles[__TILE_KEY__][is_active]" checked>
            Active
        </label>

        <div class="subtext" style="margin-bottom:10px;">Dashboard placement is controlled by dragging tiles on the main Dashboard page.</div>

        <div class="custom-tile-items" style="display:grid; gap:10px;"></div>

        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; align-items:center;">
            <button type="button" class="btn btn-secondary btn-sm add-custom-tile-item">Add Line</button>
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="custom_tiles[__TILE_KEY__][delete_tile]">
                Delete this tile
            </label>
        </div>
    </div>
</template>

<template id="custom-tile-item-template">
    <div class="custom-tile-item-row" style="border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px;">
        <div class="form-row" style="margin-bottom:8px;">
            <label>Category</label>
            <select name="custom_tiles[__TILE_KEY__][items][__ITEM_INDEX__][category_id]">
                __CATEGORY_OPTIONS__
            </select>
        </div>
        <div class="grid-2" style="gap:12px;">
            <div class="form-row">
                <label>Math Action</label>
                <select name="custom_tiles[__TILE_KEY__][items][__ITEM_INDEX__][operation]">
                    <option value="add">Add</option>
                    <option value="subtract">Subtract</option>
                </select>
            </div>
            <div class="form-row">
                <label>Amount Mode</label>
                <select name="custom_tiles[__TILE_KEY__][items][__ITEM_INDEX__][amount_mode]">
                    <option value="absolute">Absolute Amount</option>
                    <option value="raw_signed">Raw Signed Amount</option>
                </select>
            </div>
        </div>
        <div style="margin-top:10px;">
            <button type="button" class="btn btn-secondary btn-sm remove-custom-tile-item">Remove Line</button>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const customTilesList = document.getElementById('custom-tiles-list');
    const addCustomTileBtn = document.getElementById('add-custom-tile');
    const tileTemplate = document.getElementById('custom-tile-template');
    const itemTemplate = document.getElementById('custom-tile-item-template');
    const categoryOptionsHtml = <?= json_encode($category_select_options_html) ?>;
    let newTileCounter = 1;

    function getNextItemIndex(tileEditor) {
        return tileEditor.querySelectorAll('.custom-tile-item-row').length;
    }

    function addItemRow(tileEditor) {
        const tileKey = tileEditor.getAttribute('data-tile-key') || ('new_' + newTileCounter);
        const itemIndex = getNextItemIndex(tileEditor);
        let html = itemTemplate.innerHTML
            .replaceAll('__TILE_KEY__', tileKey)
            .replaceAll('__ITEM_INDEX__', String(itemIndex))
            .replace('__CATEGORY_OPTIONS__', categoryOptionsHtml);

        tileEditor.querySelector('.custom-tile-items').insertAdjacentHTML('beforeend', html);
    }

    function addTile() {
        const tileKey = 'new_' + newTileCounter++;
        let html = tileTemplate.innerHTML.replaceAll('__TILE_KEY__', tileKey);
        customTilesList.insertAdjacentHTML('beforeend', html);
        const tileEditor = customTilesList.querySelector('.custom-tile-editor[data-tile-key="' + tileKey + '"]');
        if (tileEditor) {
            addItemRow(tileEditor);
        }
    }

    addCustomTileBtn?.addEventListener('click', function () {
        addTile();
    });

    customTilesList?.addEventListener('click', function (event) {
        const addBtn = event.target.closest('.add-custom-tile-item');
        if (addBtn) {
            const tileEditor = addBtn.closest('.custom-tile-editor');
            if (tileEditor) {
                addItemRow(tileEditor);
            }
            return;
        }

        const removeBtn = event.target.closest('.remove-custom-tile-item');
        if (removeBtn) {
            const itemRow = removeBtn.closest('.custom-tile-item-row');
            if (itemRow) {
                itemRow.remove();
            }
        }
    });
});
</script>
