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
$begin_date = trim($_GET['begin_date'] ?? date('Y-m-01'));
$end_date = trim($_GET['end_date'] ?? date('Y-m-d'));
$app_id_filter = (int)($_GET['app_id'] ?? 0);
$category_id_filter = (int)($_GET['category_id'] ?? 0);
$asset_id_filter = (int)($_GET['asset_id'] ?? 0);

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
                'app_id' => $app_id_filter,
                'category_id' => $category_id_filter,
                'asset_id' => $asset_id_filter,
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
    } else {
        $amount = (float)$amount_raw;

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
                    notes = ?
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "iiiiiidsi",
                    $miner_id,
                    $asset_id,
                    $category_id,
                    $referral_id,
                    $from_account_id,
                    $to_account_id,
                    $amount,
                    $notes,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                $qs = http_build_query([
                    'page' => 'ledger',
                    'begin_date' => $begin_date,
                    'end_date' => $end_date,
                    'app_id' => $app_id_filter,
                    'category_id' => $category_id_filter,
                    'asset_id' => $asset_id_filter,
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

/* -----------------------------
   EXPORT LINKS
----------------------------- */
$export_query = http_build_query([
    'begin_date' => $begin_date,
    'end_date' => $end_date,
    'app_id' => $app_id_filter,
    'category_id' => $category_id_filter,
    'asset_id' => $asset_id_filter,
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

<div class="card">
    <h3>Filters</h3>

    <form method="get" class="ledger-filter-form">
        <input type="hidden" name="page" value="ledger">

        <div class="ledger-filter-grid">
            <div class="form-row">
                <label for="begin_date">Begin Date</label>
                <input type="date" id="begin_date" name="begin_date" value="<?= h($begin_date) ?>">
            </div>

            <div class="form-row">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= h($end_date) ?>">
            </div>

            <div class="form-row">
                <label for="app_id">App</label>
                <select id="app_id" name="app_id">
                    <option value="0">All Apps</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= (int)$app['id'] ?>" <?= $app_id_filter === (int)$app['id'] ? 'selected' : '' ?>>
                            <?= h($app['app_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int)$category['id'] ?>"
                            data-app-id="<?= (int)$category['app_id'] ?>"
                            <?= $category_id_filter === (int)$category['id'] ? 'selected' : '' ?>
                        >
                            <?= h($category['category_name']) ?>
                            <?php if (!empty($category['behavior_type'])): ?>
                                (<?= h($category['behavior_type']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="asset_id">Asset</label>
                <select id="asset_id" name="asset_id">
                    <option value="0">All Assets</option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?= (int)$asset['id'] ?>" <?= $asset_id_filter === (int)$asset['id'] ? 'selected' : '' ?>>
                            <?= h($asset['asset_name']) ?>
                            <?php if (!empty($asset['asset_symbol'])): ?>
                                (<?= h($asset['asset_symbol']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
            <a class="btn btn-secondary" href="index.php?page=ledger&begin_date=<?= urlencode($begin_date) ?>&end_date=<?= urlencode($end_date) ?>&app_id=<?= (int)$app_id_filter ?>&category_id=<?= (int)$category_id_filter ?>&asset_id=<?= (int)$asset_id_filter ?>">Cancel Edit</a>
        </form>
    </div>
<?php endif; ?>

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
                                <a class="table-link" href="index.php?page=ledger&begin_date=<?= urlencode($begin_date) ?>&end_date=<?= urlencode($end_date) ?>&app_id=<?= (int)$app_id_filter ?>&category_id=<?= (int)$category_id_filter ?>&asset_id=<?= (int)$asset_id_filter ?>&edit=<?= (int)$row['id'] ?>">
                                    Edit
                                </a>
                            </td>
                            <td>
                                <a class="table-link"
                                   href="index.php?page=ledger&begin_date=<?= urlencode($begin_date) ?>&end_date=<?= urlencode($end_date) ?>&app_id=<?= (int)$app_id_filter ?>&category_id=<?= (int)$category_id_filter ?>&asset_id=<?= (int)$asset_id_filter ?>&delete=<?= (int)$row['id'] ?>"
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
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const filterAppSelect = document.getElementById("app_id");
    const filterCategorySelect = document.getElementById("category_id");

    function filterFilterCategoriesByApp() {
        if (!filterAppSelect || !filterCategorySelect) return;

        const selectedAppId = filterAppSelect.value;

        Array.from(filterCategorySelect.options).forEach(function (opt, index) {
            if (index === 0) {
                opt.hidden = false;
                return;
            }

            const optionAppId = opt.getAttribute("data-app-id");
            opt.hidden = (selectedAppId !== "0" && optionAppId !== selectedAppId);
        });

        const selectedOption = filterCategorySelect.options[filterCategorySelect.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            filterCategorySelect.value = "0";
        }
    }

    if (filterAppSelect && filterCategorySelect) {
        filterAppSelect.addEventListener("change", filterFilterCategoriesByApp);
        filterFilterCategoriesByApp();
    }

    const editAppSelect = document.getElementById("edit_app_id");
    const editCategorySelect = document.getElementById("edit_category_id");

    function filterEditCategoriesByApp() {
        if (!editAppSelect || !editCategorySelect) return;

        const selectedAppId = editAppSelect.value;

        Array.from(editCategorySelect.options).forEach(function (opt, index) {
            if (index === 0) {
                opt.hidden = false;
                return;
            }

            const optionAppId = opt.getAttribute("data-app-id");
            opt.hidden = (optionAppId !== selectedAppId);
        });

        const selectedOption = editCategorySelect.options[editCategorySelect.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            editCategorySelect.value = "0";
        }
    }

    if (editAppSelect && editCategorySelect) {
        editAppSelect.addEventListener("change", filterEditCategoriesByApp);
        filterEditCategoriesByApp();
    }
});
</script>