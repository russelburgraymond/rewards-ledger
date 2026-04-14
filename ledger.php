<?php

$current_page = 'ledger';

$error = "";
$success = "";

/* -----------------------------
   LOAD DROPDOWNS
----------------------------- */
$apps = [];
$miners = [];
$assets = [];
$categories = [];
$accounts = [];
$referrals = [];

$res = $conn->query("
    SELECT id, app_name
    FROM apps
    WHERE is_active = 1
    ORDER BY sort_order ASC, app_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $apps[] = $row;

$res = $conn->query("
    SELECT id, miner_name
    FROM miners
    WHERE is_active = 1
    ORDER BY miner_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $miners[] = $row;

$res = $conn->query("
    SELECT id, asset_name, asset_symbol, currency_symbol, display_decimals, is_fiat
    FROM assets
    WHERE is_active = 1
    ORDER BY sort_order ASC, asset_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;

$res = $conn->query("
    SELECT id, app_id, category_name, behavior_type
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;

$res = $conn->query("
    SELECT id, account_name
    FROM accounts
    WHERE is_active = 1
    ORDER BY account_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $accounts[] = $row;

$res = $conn->query("
    SELECT id, referral_name
    FROM referrals
    WHERE is_active = 1
    ORDER BY referral_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $referrals[] = $row;

/* -----------------------------
   FILTERS
----------------------------- */
$begin_date = trim($_GET['begin_date'] ?? date('Y-01-01'));
$end_date = trim($_GET['end_date'] ?? date('Y-m-d'));

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

$per_page_allowed = [25, 50, 100, 250];
$per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page, $per_page_allowed, true)) {
    $per_page = 25;
}

$ledger_page_num = max(1, (int)($_GET['ledger_page_num'] ?? 1));

/* -----------------------------
   HANDLE DELETE
----------------------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)($_GET['delete'] ?? 0);

    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM batch_items WHERE id = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();

			$qs = http_build_query([
				'page' => 'ledger',
				'begin_date' => $begin_date,
				'end_date' => $end_date,
				'app_ids' => $app_ids_filter,
				'category_ids' => $category_ids_filter,
				'asset_ids' => $asset_ids_filter,
				'per_page' => $per_page,
				'ledger_page_num' => $ledger_page_num,
				'deleted' => 1
			]);
            header("Location: index.php?{$qs}");
            exit;
        } else {
            $error = "Could not delete ledger item: " . $conn->error;
        }
    }
}

if (isset($_GET['deleted'])) {
    $success = "Ledger item deleted.";
}

if (isset($_GET['updated'])) {
    $success = "Ledger item updated.";
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
$edit = [
    'id' => 0,
    'batch_date' => date('Y-m-d'),
    'app_id' => 0,
    'miner_id' => 0,
    'asset_id' => 0,
    'category_id' => 0,
    'referral_id' => 0,
    'from_account_id' => 0,
    'to_account_id' => 0,
    'amount' => '0',
    'received_time' => '',
    'value_at_receipt' => '',
    'notes' => '',
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
			SELECT
				bi.id,
				b.batch_date,
				b.app_id,
				bi.miner_id,
				bi.asset_id,
				bi.category_id,
				bi.referral_id,
				bi.from_account_id,
				bi.to_account_id,
				bi.amount,
				bi.received_time,
				bi.value_at_receipt,
				bi.notes,
				(
					SELECT COUNT(*)
					FROM batch_items bi2
					WHERE bi2.batch_id = bi.batch_id
				) AS batch_item_count
			FROM batch_items bi
			INNER JOIN batches b ON b.id = bi.batch_id
			WHERE bi.id = ?
			LIMIT 1
		");

        if ($stmt) {
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
				$edit = $row;
				$is_batch_entry = ((int)($edit['batch_item_count'] ?? 1) > 1);
			}
        } else {
            $error = "Could not load ledger item for editing: " . $conn->error;
        }
    }
}

/* -----------------------------
   HANDLE SAVE EDIT
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ledger_item') {
    $id = (int)($_POST['id'] ?? 0);

    $batch_date = trim($_POST['batch_date'] ?? '');
    $app_id = (int)($_POST['app_id'] ?? 0);
    $miner_id = (int)($_POST['miner_id'] ?? 0);
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $referral_id = (int)($_POST['referral_id'] ?? 0);
    $from_account_id = (int)($_POST['from_account_id'] ?? 0);
    $to_account_id = (int)($_POST['to_account_id'] ?? 0);
    $amount_raw = trim($_POST['amount'] ?? '0');
    $received_time_raw = trim($_POST['received_time'] ?? '');
    $value_at_receipt_raw = trim($_POST['value_at_receipt'] ?? '');
    $use_sats = isset($_POST['use_sats']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    $edit = [
        'id' => $id,
        'batch_date' => $batch_date,
        'app_id' => $app_id,
        'miner_id' => $miner_id,
        'asset_id' => $asset_id,
        'category_id' => $category_id,
        'referral_id' => $referral_id,
        'from_account_id' => $from_account_id,
        'to_account_id' => $to_account_id,
        'amount' => $amount_raw,
        'received_time' => $received_time_raw,
        'value_at_receipt' => $value_at_receipt_raw,
        'notes' => $notes,
    ];
	
	$is_batch_entry = false;

    if ($id <= 0) {
        $error = "Invalid ledger item.";
    } elseif ($batch_date === '') {
        $error = "Date is required.";
    } elseif ($app_id <= 0) {
        $error = "App is required.";
    } elseif ($category_id <= 0) {
        $error = "Category is required.";
    } elseif ($asset_id <= 0) {
        $error = "Asset is required.";
    } elseif ($amount_raw === '' || !is_numeric($amount_raw)) {
        $error = "Please enter a valid amount.";
    } elseif ($received_time_raw !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $received_time_raw)) {
        $error = "Please enter a valid received time.";
    } elseif ($value_at_receipt_raw !== '' && !is_numeric($value_at_receipt_raw)) {
        $error = "Please enter a valid value at receipt.";
    } else {
        if ($use_sats === 1 && rl_is_btc_asset_id($conn, $asset_id)) {
            $amount_raw = (string)rl_sats_to_btc_float($amount_raw);
        }
        $amount = (float)$amount_raw;
        $received_time = ($received_time_raw !== '') ? $received_time_raw : null;
        $value_at_receipt = ($value_at_receipt_raw !== '') ? (float)$value_at_receipt_raw : null;

        $stmt = $conn->prepare("
            UPDATE batches b
            INNER JOIN batch_items bi ON bi.batch_id = b.id
            SET b.batch_date = ?, b.app_id = ?
            WHERE bi.id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("sii", $batch_date, $app_id, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $error = "Could not update batch info: " . $conn->error;
        }

        if ($error === '') {
            $stmt = $conn->prepare("
                UPDATE batch_items
                SET
                    miner_id = ?,
                    asset_id = ?,
                    category_id = ?,
                    referral_id = ?,
                    from_account_id = ?,
                    to_account_id = ?,
                    amount = ?,
                    received_time = ?,
                    value_at_receipt = ?,
                    notes = ?
                WHERE id = ?
            ");

            if ($stmt) {
				$stmt->bind_param(
					"iiiiiidsssi",
					$miner_id,
					$asset_id,
					$category_id,
					$referral_id,
					$from_account_id,
					$to_account_id,
					$amount,
					$received_time,
					$value_at_receipt,
					$notes,
					$id
				);
                $stmt->execute();
                $stmt->close();

                $qs = http_build_query([
                    'page' => 'ledger',
                    'begin_date' => $begin_date,
                    'end_date' => $end_date,
                    'app_ids' => $app_ids_filter,
                    'category_ids' => $category_ids_filter,
                    'asset_ids' => $asset_ids_filter,
                    'per_page' => $per_page,
                    'ledger_page_num' => $ledger_page_num,
                    'updated' => 1
                ]);
                header("Location: index.php?{$qs}");
                exit;
            } else {
                $error = "Could not update ledger item: " . $conn->error;
            }
        }
    }
}

/* -----------------------------
   BUILD FILTER SQL
----------------------------- */
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
   EXPORT LINKS
----------------------------- */
$export_query = http_build_query([
    'begin_date' => $begin_date,
    'end_date' => $end_date,
    'app_ids' => $app_ids_filter,
    'category_ids' => $category_ids_filter,
    'asset_ids' => $asset_ids_filter,
]);

/* -----------------------------
   LOAD TOTALS BY ASSET
----------------------------- */
$ledger_totals = [];

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
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ledger_totals[] = $row;
        }
    }

    $stmt->close();
} else {
    $error = "Could not load ledger totals: " . $conn->error;
}

/* -----------------------------
   COUNT LEDGER ROWS
----------------------------- */
$total_ledger_rows = 0;

$sql_count = "
    SELECT COUNT(*) AS total_rows
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    {$where_sql}
";

$stmt = $conn->prepare($sql_count);

if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $count_row = $res ? $res->fetch_assoc() : null;
    $total_ledger_rows = (int)($count_row['total_rows'] ?? 0);
    $stmt->close();
} else {
    $error = "Could not count ledger rows: " . $conn->error;
}

$total_pages = max(1, (int)ceil($total_ledger_rows / $per_page));
if ($ledger_page_num > $total_pages) {
    $ledger_page_num = $total_pages;
}
$offset = ($ledger_page_num - 1) * $per_page;

/* -----------------------------
   LOAD LEDGER LIST
----------------------------- */
$ledger_rows = [];

$sql = "
    SELECT
        bi.id,
        b.batch_date,
        b.app_id,
        ap.app_name,
        bi.miner_id,
        m.miner_name,
        bi.asset_id,
        a.asset_name,
        a.asset_symbol,
        a.currency_symbol,
        a.display_decimals,
        a.is_fiat,
        bi.category_id,
        c.category_name,
        c.behavior_type,
        bi.referral_id,
        r.referral_name,
        bi.from_account_id,
        fa.account_name AS from_account_name,
        bi.to_account_id,
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
	LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $list_types = $types . "ii";
    $list_params = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;

    $stmt->bind_param($list_types, ...$list_params);
    $stmt->execute();
	
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ledger_rows[] = $row;
        }
    }

    $stmt->close();
} else {
    $error = "Could not load ledger: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Ledger</h2>
    <p class="subtext">View, filter, edit, delete, and export saved ledger entries.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php
$selected_app_names = [];
foreach ($apps as $app) {
    if (in_array((int)$app['id'], $app_ids_filter, true)) {
        $selected_app_names[] = $app['app_name'];
    }
}

$selected_category_names = [];
foreach ($categories as $category) {
    if (in_array((int)$category['id'], $category_ids_filter, true)) {
        $selected_category_names[] = $category['category_name'];
    }
}

$selected_asset_names = [];
foreach ($assets as $asset) {
    if (in_array((int)$asset['id'], $asset_ids_filter, true)) {
        $selected_asset_names[] = $asset['asset_name'];
    }
}
?>

<div class="card">
    <div class="card-toggle-head">
        <h3 style="margin:0;">Filters</h3>
        <button type="button" class="btn btn-secondary btn-sm" id="toggle-ledger-filters">Show Filters</button>
    </div>

    <?php
    $filter_summary_base = [
        'page' => 'ledger',
        'begin_date' => $begin_date,
        'end_date' => $end_date,
        'app_ids' => $app_ids_filter,
        'category_ids' => $category_ids_filter,
        'asset_ids' => $asset_ids_filter,
        'per_page' => $per_page,
    ];

    $clear_begin_query = $filter_summary_base;
    $clear_begin_query['begin_date'] = '';

    $clear_end_query = $filter_summary_base;
    $clear_end_query['end_date'] = '';

    $clear_apps_query = $filter_summary_base;
    $clear_apps_query['app_ids'] = [];

    $clear_categories_query = $filter_summary_base;
    $clear_categories_query['category_ids'] = [];

    $clear_assets_query = $filter_summary_base;
    $clear_assets_query['asset_ids'] = [];
    ?>

    <div class="filter-summary">
        <strong>Active Filters:</strong>

        <?php if ($begin_date !== ''): ?>
            <a class="filter-pill" href="index.php?<?= h(http_build_query($clear_begin_query)) ?>" title="Clear begin date filter">
                From: <?= h($begin_date) ?> ×
            </a>
        <?php else: ?>
            <span class="filter-pill">From: All</span>
        <?php endif; ?>

        <?php if ($end_date !== ''): ?>
            <a class="filter-pill" href="index.php?<?= h(http_build_query($clear_end_query)) ?>" title="Clear end date filter">
                To: <?= h($end_date) ?> ×
            </a>
        <?php else: ?>
            <span class="filter-pill">To: All</span>
        <?php endif; ?>

        <?php if (!empty($selected_app_names)): ?>
            <a class="filter-pill" href="index.php?<?= h(http_build_query($clear_apps_query)) ?>" title="Clear app filters">
                Apps: <?= h(implode(', ', $selected_app_names)) ?> ×
            </a>
        <?php else: ?>
            <span class="filter-pill">Apps: All</span>
        <?php endif; ?>

        <?php if (!empty($selected_category_names)): ?>
            <a class="filter-pill" href="index.php?<?= h(http_build_query($clear_categories_query)) ?>" title="Clear category filters">
                Categories: <?= h(implode(', ', $selected_category_names)) ?> ×
            </a>
        <?php else: ?>
            <span class="filter-pill">Categories: All</span>
        <?php endif; ?>

        <?php if (!empty($selected_asset_names)): ?>
            <a class="filter-pill" href="index.php?<?= h(http_build_query($clear_assets_query)) ?>" title="Clear asset filters">
                Assets: <?= h(implode(', ', $selected_asset_names)) ?> ×
            </a>
        <?php else: ?>
            <span class="filter-pill">Assets: All</span>
        <?php endif; ?>
    </div>

    <div id="ledger-filters-panel">
        <form method="get" class="ledger-filter-form">
            <input type="hidden" name="page" value="ledger">

            <div class="ledger-filter-grid">

                <div class="form-row ledger-filter-dates">
                    <label>Dates</label>

                    <div class="filter-topline"></div>

                    <div class="filter-date-box">
                        <div>
                            <label for="begin_date">Begin Date</label>
                            <input type="date" id="begin_date" name="begin_date" value="<?= h($begin_date) ?>">
                        </div>

                        <div>
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= h($end_date) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row ledger-filter-apps">
                    <label>Apps</label>

                    <div class="filter-topline">
                        <label class="filter-checklist-item">
                            <input type="checkbox" class="js-apps-all" <?= empty($app_ids_filter) ? 'checked' : '' ?>>
                            <span>All Apps</span>
                        </label>
                    </div>

                    <div class="filter-checklist-box">
                        <?php foreach ($apps as $app): ?>
                            <label class="filter-checklist-item">
                                <input
                                    type="checkbox"
                                    name="app_ids[]"
                                    value="<?= (int)$app['id'] ?>"
                                    <?= in_array((int)$app['id'], $app_ids_filter, true) ? 'checked' : '' ?>
                                >
                                <span><?= h($app['app_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row ledger-filter-categories">
                    <label>Categories</label>

                    <div class="filter-topline">
                        <label class="filter-checklist-item">
                            <input type="checkbox" class="js-categories-all" <?= empty($category_ids_filter) ? 'checked' : '' ?>>
                            <span>All Categories</span>
                        </label>
                    </div>

                    <div class="filter-checklist-box">
                        <?php foreach ($categories as $category): ?>
                            <label
                                class="filter-checklist-item category-filter-item"
                                data-app-id="<?= (int)$category['app_id'] ?>"
                            >
                                <input
                                    type="checkbox"
                                    name="category_ids[]"
                                    value="<?= (int)$category['id'] ?>"
                                    <?= in_array((int)$category['id'], $category_ids_filter, true) ? 'checked' : '' ?>
                                >
                                <span><?= h($category['category_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row ledger-filter-assets">
                    <label>Assets</label>

                    <div class="filter-topline">
                        <label class="filter-checklist-item">
                            <input type="checkbox" class="js-assets-all" <?= empty($asset_ids_filter) ? 'checked' : '' ?>>
                            <span>All Assets</span>
                        </label>
                    </div>

                    <div class="filter-checklist-box">
                        <?php foreach ($assets as $asset): ?>
                            <label class="filter-checklist-item">
                                <input
                                    type="checkbox"
                                    name="asset_ids[]"
                                    value="<?= (int)$asset['id'] ?>"
                                    <?= in_array((int)$asset['id'], $asset_ids_filter, true) ? 'checked' : '' ?>
                                >
                                <span><?= h($asset['asset_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row ledger-filter-per-page">
                    <label>Display</label>

                    <div class="filter-topline"></div>

                    <div class="filter-display-box">
                        <label for="per_page">Rows Per Page</label>
                        <select id="per_page" name="per_page">
                            <?php foreach ($per_page_allowed as $pp): ?>
                                <option value="<?= (int)$pp ?>" <?= $per_page === (int)$pp ? 'selected' : '' ?>>
                                    <?= (int)$pp ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row ledger-filter-actions">
                    <label>&nbsp;</label>
                    <div class="ledger-filter-buttons">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a class="btn btn-secondary" href="index.php?page=ledger">Reset</a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<div class="card mt-20">
    <h3>Totals</h3>

    <?php if (!$ledger_totals): ?>
        <p class="subtext">No totals found for the selected filters.</p>
    <?php else: ?>
        <div class="summary-grid">
            <?php foreach ($ledger_totals as $total): ?>
                <?php
                $label = trim((string)($total['asset_name'] ?? ''));
                if (!empty($total['asset_symbol'])) {
                    $label .= ' (' . $total['asset_symbol'] . ')';
                }
                $total_value = (float)($total['total_amount'] ?? 0);
                $total_class = $total_value < 0 ? 'ledger-card-negative' : 'ledger-card-positive';
                ?>
                <div class="summary-card <?= h($total_class) ?>">
                    <div class="summary-label"><?= h($label) ?></div>
                    <div class="summary-value" style="font-size:20px;">
                        <?= h(fmt_asset_value(
                            $total_value,
                            (string)($total['currency_symbol'] ?? ''),
                            (int)($total['display_decimals'] ?? 8),
                            (int)($total['is_fiat'] ?? 0)
                        )) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>



<?php
$base_filter_query = [
    'page' => 'ledger',
    'begin_date' => $begin_date,
    'end_date' => $end_date,
    'app_ids' => $app_ids_filter,
    'category_ids' => $category_ids_filter,
    'asset_ids' => $asset_ids_filter,
    'per_page' => $per_page,
    'ledger_page_num' => $ledger_page_num,
];
?>

<?php if ((int)$edit['id'] > 0): ?>
    <div class="card mt-20">
        <h3>Edit Ledger Item</h3>

        <form method="post">
            <input type="hidden" name="action" value="save_ledger_item">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="grid-3">
                <div class="form-row">
                    <label for="batch_date">Date</label>
                    <input type="date" id="batch_date" name="batch_date" value="<?= h($edit['batch_date']) ?>" required>
					<?php if ($is_batch_entry): ?>
    <div class="batch-note">
        <strong>Batch Entry:</strong> Changing shared fields like date or app will update all items entered together in this batch.
    </div>
<?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="edit_app_id">App</label>
                    <select id="edit_app_id" name="app_id" required>
                        <option value="0">Select App</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?= (int)$app['id'] ?>" <?= (int)$edit['app_id'] === (int)$app['id'] ? 'selected' : '' ?>>
                                <?= h($app['app_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="amount">Amount</label>
                    <input type="text" id="amount" name="amount" value="<?= h((string)$edit['amount']) ?>" required>
                </div>

                <div class="form-row js-ledger-sats-row" style="display:none;">
                    <label>
                        <input type="checkbox" name="use_sats" value="1" <?= !empty($_POST['use_sats']) ? 'checked' : '' ?>>
                        Sats
                    </label>
                    <div class="subtext">When BTC is selected, check this to enter sats instead of full BTC.</div>
                </div>

                <div class="form-row">
                    <label for="received_time">Time Received</label>
                    <input type="time" id="received_time" name="received_time" value="<?= h((string)($edit['received_time'] ?? '')) ?>">
                </div>

                <div class="form-row">
                    <label for="value_at_receipt">Value at Time Received</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="value_at_receipt" name="value_at_receipt" value="<?= h((string)($edit['value_at_receipt'] ?? '')) ?>" placeholder="0.00" style="flex:1;">
                        <button type="button" class="btn btn-secondary js-price-lookup-single">Look up value at time of receipt</button>
                    </div>
                    <div class="subtext" id="value_lookup_status" style="margin-top:6px;"></div>
                </div>
            </div>

            <div class="grid-3">
                <div class="form-row">
                    <label for="miner_id">Miner</label>
                    <select id="miner_id" name="miner_id">
                        <option value="0">None</option>
                        <?php foreach ($miners as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" <?= (int)$edit['miner_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= h($m['miner_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="asset_id">Asset</label>
                    <select id="asset_id" name="asset_id" required>
                        <option value="0">Select Asset</option>
                        <?php foreach ($assets as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$edit['asset_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= h($a['asset_name']) ?><?php if (!empty($a['asset_symbol'])): ?> (<?= h($a['asset_symbol']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="edit_category_id">Category</label>
                    <select id="edit_category_id" name="category_id" required>
                        <option value="0">Select Category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-app-id="<?= (int)$c['app_id'] ?>" <?= (int)$edit['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['category_name']) ?><?php if (!empty($c['behavior_type'])): ?> (<?= h($c['behavior_type']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-3">
                <div class="form-row">
                    <label for="referral_id">Referral</label>
                    <select id="referral_id" name="referral_id">
                        <option value="0">None</option>
                        <?php foreach ($referrals as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= (int)$edit['referral_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                                <?= h($r['referral_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="from_account_id">From Account</label>
                    <select id="from_account_id" name="from_account_id">
                        <option value="0">None</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$edit['from_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= h($a['account_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="to_account_id">To Account</label>
                    <select id="to_account_id" name="to_account_id">
                        <option value="0">None</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$edit['to_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= h($a['account_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?= h($edit['notes']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Ledger Item</button>
            <a class="btn btn-secondary" href="index.php?<?= h(http_build_query($base_filter_query)) ?>">Cancel Edit</a>
        </form>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btcAssetIds = <?= json_encode(array_values(array_map('intval', array_column(array_filter($assets, 'rl_is_btc_asset_row'), 'id')))) ?>;
    const assetEl = document.getElementById('asset_id');
    const amountEl = document.getElementById('amount');
    const satsRow = document.querySelector('.js-ledger-sats-row');
    const satsCheckbox = satsRow ? satsRow.querySelector('input[name="use_sats"]') : null;
    const statusEl = document.getElementById('value_lookup_status');
    const lookupBtn = document.querySelector('.js-price-lookup-single');

    function isBtcAsset(value) { return btcAssetIds.indexOf(parseInt(value || '0', 10)) !== -1; }
    function trimNumeric(value) { return String(value).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1'); }
    function btcToSatsString(value) { if (value === '' || isNaN(Number(value))) return value; return String(Math.round(Number(value) * 100000000)); }
    function satsToBtcString(value) { if (value === '' || isNaN(Number(value))) return value; return trimNumeric((Number(value) / 100000000).toFixed(8)); }
    function setLookupStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.style.color = isError ? '#d9534f' : '';
    }
    function refreshSats() {
        if (!satsRow || !satsCheckbox || !amountEl || !assetEl) return;
        const show = isBtcAsset(assetEl.value);
        satsRow.style.display = show ? '' : 'none';
        if (!show && satsCheckbox.checked) {
            amountEl.value = satsToBtcString(amountEl.value);
            satsCheckbox.checked = false;
        }
    }
    if (assetEl) assetEl.addEventListener('change', refreshSats);
    if (satsCheckbox && amountEl) {
        satsCheckbox.addEventListener('change', function () {
            amountEl.value = satsCheckbox.checked ? btcToSatsString(amountEl.value) : satsToBtcString(amountEl.value);
        });
        const form = satsCheckbox.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (satsCheckbox.checked) {
                    amountEl.value = satsToBtcString(amountEl.value);
                }
            });
        }
    }
    refreshSats();

    if (lookupBtn) {
        lookupBtn.addEventListener('click', async function () {
            const timeEl = document.getElementById('received_time');
            const valueEl = document.getElementById('value_at_receipt');
            const dateEl = document.getElementById('batch_date');
            if (!assetEl || !amountEl || !timeEl || !valueEl || !dateEl) return;
            const payload = {
                asset_id: assetEl.value,
                amount: (satsCheckbox && satsCheckbox.checked) ? satsToBtcString(amountEl.value) : amountEl.value,
                date: dateEl.value,
                time: timeEl.value
            };
            if (!payload.asset_id || payload.asset_id === '0' || !payload.amount || !payload.date || !payload.time) {
                setLookupStatus('Enter asset, amount, date, and time first.', true);
                return;
            }
            setLookupStatus('Looking up price...', false);
            try {
                const response = await fetch('ajax/get_price.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    setLookupStatus((data && data.error) ? data.error : 'Price lookup failed.', true);
                    return;
                }
                valueEl.value = data.total_value_formatted || data.total_value || '';
                setLookupStatus(data.message || 'Price loaded.', false);
            } catch (error) {
                setLookupStatus('Price lookup failed.', true);
            }
        });
    }
});
</script>

<div class="card mt-20">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h3 style="margin:0;">Ledger Entries</h3>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn btn-secondary" href="ledger_export_csv.php?<?= h($export_query) ?>">Export CSV</a>
            <a class="btn btn-secondary" href="ledger_export_pdf.php?<?= h($export_query) ?>" target="_blank">Export PDF</a>
        </div>
    </div>

    <?php if (!$ledger_rows): ?>
        <p class="subtext">No ledger entries found for the selected filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:120px;">Date</th>
                        <th>App</th>
                        <th>Miner</th>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Referral</th>
                        <th>From</th>
                        <th>To</th>
                        <th style="width:140px;">Amount</th>
                        <th>Notes</th>
                        <th style="width:90px;">Edit</th>
                        <th style="width:90px;">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledger_rows as $row): ?>
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
                            <td>
                                <a class="table-link" href="index.php?<?= h(http_build_query($base_filter_query + ['edit' => (int)$row['id']])) ?>">
                                    Edit
                                </a>
                            </td>
                            <td>
                                <a class="table-link"
                                   href="index.php?<?= h(http_build_query($base_filter_query + ['delete' => (int)$row['id']])) ?>"
                                   onclick="return confirm('Delete this ledger item?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
	<?php if ($total_ledger_rows > 0): ?>
    <?php
    $base_paging = [
        'page' => 'ledger',
        'begin_date' => $begin_date,
        'end_date' => $end_date,
        'app_ids' => $app_ids_filter,
        'category_ids' => $category_ids_filter,
        'asset_ids' => $asset_ids_filter,
        'per_page' => $per_page,
    ];

    $from_row = $offset + 1;
    $to_row = min($offset + $per_page, $total_ledger_rows);
    ?>
<div class="ledger-pagination">
    <div class="ledger-pagination-info">
        Showing <?= (int)$from_row ?>–<?= (int)$to_row ?> of <?= (int)$total_ledger_rows ?> entries
    </div>

    <div class="ledger-pagination-links">
        <?php if ($ledger_page_num > 1): ?>
            <a class="btn btn-secondary"
               href="index.php?<?= h(http_build_query($base_paging + ['ledger_page_num' => 1])) ?>">
                First
            </a>

            <a class="btn btn-secondary"
               href="index.php?<?= h(http_build_query($base_paging + ['ledger_page_num' => $ledger_page_num - 1])) ?>">
                Prev
            </a>
        <?php else: ?>
            <span class="btn btn-secondary disabled">First</span>
            <span class="btn btn-secondary disabled">Prev</span>
        <?php endif; ?>

        <span class="subtext">Page <?= (int)$ledger_page_num ?> of <?= (int)$total_pages ?></span>

        <?php if ($ledger_page_num < $total_pages): ?>
            <a class="btn btn-secondary"
               href="index.php?<?= h(http_build_query($base_paging + ['ledger_page_num' => $ledger_page_num + 1])) ?>">
                Next
            </a>

            <a class="btn btn-secondary"
               href="index.php?<?= h(http_build_query($base_paging + ['ledger_page_num' => $total_pages])) ?>">
                Last
            </a>
        <?php else: ?>
            <span class="btn btn-secondary disabled">Next</span>
            <span class="btn btn-secondary disabled">Last</span>
        <?php endif; ?>
    </div>
</div>
	
	
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterPanel = document.getElementById('ledger-filters-panel');
    const filterToggleBtn = document.getElementById('toggle-ledger-filters');
    const storageKey = 'ledger_filters_collapsed';

    function setFilterState(collapsed) {
        if (!filterPanel || !filterToggleBtn) return;

        if (collapsed) {
            filterPanel.classList.add('filters-collapsed');
            filterToggleBtn.textContent = 'Show Filters';
        } else {
            filterPanel.classList.remove('filters-collapsed');
            filterToggleBtn.textContent = 'Hide Filters';
        }
    }

    if (filterPanel && filterToggleBtn) {
        const savedValue = localStorage.getItem(storageKey);
        const shouldStartCollapsed = (savedValue === null) ? true : (savedValue === '1');
        setFilterState(shouldStartCollapsed);

        filterToggleBtn.addEventListener('click', function () {
            const isCollapsed = filterPanel.classList.contains('filters-collapsed');
            const next = !isCollapsed;
            setFilterState(next);
            localStorage.setItem(storageKey, next ? '1' : '0');
        });
    }

    function wireAllToggle(allSelector, itemSelector) {
        const allBox = document.querySelector(allSelector);
        const boxes = Array.from(document.querySelectorAll(itemSelector));

        function syncAll() {
            const checkedCount = boxes.filter(cb => cb.checked).length;
            if (allBox) {
                allBox.checked = checkedCount === 0;
            }
        }

        if (allBox) {
            allBox.addEventListener('change', function () {
                if (allBox.checked) {
                    boxes.forEach(cb => cb.checked = false);
                }
            });
        }

        boxes.forEach(cb => {
            cb.addEventListener('change', function () {
                if (cb.checked && allBox) {
                    allBox.checked = false;
                }
                syncAll();
            });
        });

        syncAll();
    }

    wireAllToggle('.js-apps-all', 'input[name="app_ids[]"]');
    wireAllToggle('.js-categories-all', 'input[name="category_ids[]"]');
    wireAllToggle('.js-assets-all', 'input[name="asset_ids[]"]');

    const appBoxes = Array.from(document.querySelectorAll('input[name="app_ids[]"]'));
    const categoryLabels = Array.from(document.querySelectorAll('.category-filter-item'));

    function filterCategoriesByApps() {
        const selectedApps = appBoxes.filter(cb => cb.checked).map(cb => parseInt(cb.value, 10));

        categoryLabels.forEach(label => {
            const catAppId = parseInt(label.getAttribute('data-app-id') || '0', 10);
            label.style.display = (selectedApps.length === 0 || selectedApps.includes(catAppId)) ? '' : 'none';
        });
    }

    appBoxes.forEach(cb => cb.addEventListener('change', filterCategoriesByApps));
    filterCategoriesByApps();

    const editAppSelect = document.getElementById('edit_app_id');
    const editCategorySelect = document.getElementById('edit_category_id');

    function filterEditCategoriesByApp() {
        if (!editAppSelect || !editCategorySelect) return;

        const selectedAppId = editAppSelect.value;

        Array.from(editCategorySelect.options).forEach((opt, index) => {
            if (index === 0) {
                opt.hidden = false;
                return;
            }

            const optionAppId = opt.getAttribute('data-app-id');
            opt.hidden = (optionAppId !== selectedAppId);
        });

        const selectedOption = editCategorySelect.options[editCategorySelect.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            editCategorySelect.value = '0';
        }
    }

    if (editAppSelect && editCategorySelect) {
        editAppSelect.addEventListener('change', filterEditCategoriesByApp);
        filterEditCategoriesByApp();
    }
});
</script>