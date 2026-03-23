<?php

$current_page = 'quick_entry';

$error = "";
$success = "";

/* -----------------------------
   LOAD QUICK ADD ITEMS
----------------------------- */

$quick_add_items = [];

$res = $conn->query("
    SELECT
        qa.id,
        qa.app_id,
        qa.quick_add_name,
        qa.miner_id,
        qa.asset_id,
        qa.category_id,
        qa.referral_id,
        qa.from_account_id,
        qa.to_account_id,
        qa.amount,
        qa.notes,
		qa.is_multi_add,
        qa.show_miner,
        qa.show_asset,
        qa.show_category,
        qa.show_referral,
        qa.show_amount,
        qa.show_notes,
        qa.show_from_account,
        qa.show_to_account,
		qa.is_multi_add,
        ap.app_name,
        m.miner_name,
        a.asset_name,
        a.asset_symbol,
        c.category_name,
        r.referral_name,
        fa.account_name AS from_account_name,
        ta.account_name AS to_account_name
    FROM quick_add_items qa
    LEFT JOIN apps ap ON ap.id = qa.app_id
    LEFT JOIN miners m ON m.id = qa.miner_id
    LEFT JOIN assets a ON a.id = qa.asset_id
    LEFT JOIN categories c ON c.id = qa.category_id
    LEFT JOIN referrals r ON r.id = qa.referral_id
    LEFT JOIN accounts fa ON fa.id = qa.from_account_id
    LEFT JOIN accounts ta ON ta.id = qa.to_account_id
    WHERE qa.is_active = 1
    ORDER BY qa.is_active DESC, qa.sort_order ASC, qa.id ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $quick_add_items[] = $row;
    }
}

/* -----------------------------
   LOAD DROPDOWNS
----------------------------- */

$miners = [];
$assets = [];
$categories = [];
$accounts = [];
$referrals = [];

$res = $conn->query("SELECT id, miner_name FROM miners WHERE is_active = 1 ORDER BY miner_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $miners[] = $row;

$res = $conn->query("SELECT id, asset_name, asset_symbol FROM assets WHERE is_active = 1 ORDER BY asset_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;

$res = $conn->query("SELECT id, app_id, category_name, behavior_type FROM categories WHERE is_active = 1 ORDER BY app_id ASC, sort_order ASC, category_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;

$res = $conn->query("SELECT id, account_name FROM accounts WHERE is_active = 1 ORDER BY account_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $accounts[] = $row;

$res = $conn->query("SELECT id, referral_name FROM referrals WHERE is_active = 1 ORDER BY referral_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $referrals[] = $row;

/* -----------------------------
   LOAD SELECTED QUICK ADD
----------------------------- */

$quick_add_id = (int)($_GET['quick_add_id'] ?? $_POST['quick_add_id'] ?? 0);
$quick_add = null;

foreach ($quick_add_items as $item) {
    if ((int)$item['id'] === $quick_add_id) {
        $quick_add = $item;
        break;
    }
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_quick_add_entry') {
    $quick_add_id = (int)($_POST['quick_add_id'] ?? 0);
    $line_date = trim($_POST['line_date'] ?? date('Y-m-d'));

    $quick_add = null;
    foreach ($quick_add_items as $item) {
        if ((int)$item['id'] === $quick_add_id) {
            $quick_add = $item;
            break;
        }
    }

    if (!$quick_add) {
        $error = "Please select a Quick Add item.";
    } elseif ($line_date === '') {
        $error = "Please enter a line date.";
    } else {
        $miner_id = ((int)$quick_add['show_miner'] === 1)
            ? (int)($_POST['miner_id'] ?? 0)
            : (int)($quick_add['miner_id'] ?? 0);

        $asset_id = ((int)$quick_add['show_asset'] === 1)
            ? (int)($_POST['asset_id'] ?? 0)
            : (int)($quick_add['asset_id'] ?? 0);

        $category_id = ((int)$quick_add['show_category'] === 1)
            ? (int)($_POST['category_id'] ?? 0)
            : (int)($quick_add['category_id'] ?? 0);

        $referral_id = ((int)$quick_add['show_referral'] === 1)
            ? (int)($_POST['referral_id'] ?? 0)
            : (int)($quick_add['referral_id'] ?? 0);

        $from_account_id = ((int)$quick_add['show_from_account'] === 1)
            ? (int)($_POST['from_account_id'] ?? 0)
            : (int)($quick_add['from_account_id'] ?? 0);

        $to_account_id = ((int)$quick_add['show_to_account'] === 1)
            ? (int)($_POST['to_account_id'] ?? 0)
            : (int)($quick_add['to_account_id'] ?? 0);

        $is_multi_add = (int)($quick_add['is_multi_add'] ?? 0);

        $amount_raw = ((int)$quick_add['show_amount'] === 1 && $is_multi_add !== 1)
            ? trim($_POST['amount'] ?? '')
            : (string)($quick_add['amount'] ?? '0');

        $amount_lines_raw = ((int)$quick_add['show_amount'] === 1 && $is_multi_add === 1)
            ? trim($_POST['amount_lines'] ?? '')
            : '';

        $notes = ((int)$quick_add['show_notes'] === 1)
            ? trim($_POST['notes'] ?? '')
            : trim((string)($quick_add['notes'] ?? ''));

        if ($category_id <= 0) {
            $error = "Please choose a category.";
        } else {
            $amounts_to_save = [];

            if ($is_multi_add === 1) {
                if ($amount_lines_raw === '') {
                    $error = "Please enter at least one amount.";
                } else {
                    $amount_lines = preg_split('/\r\n|\r|\n/', $amount_lines_raw);

                    foreach ($amount_lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        if (!is_numeric($line)) {
                            $error = "One or more multi-add amounts are invalid.";
                            break;
                        }

                        $amounts_to_save[] = (float)$line;
                    }

                    if ($error === '' && empty($amounts_to_save)) {
                        $error = "Please enter at least one valid amount.";
                    }
                }
            } else {
                if ($amount_raw !== '' && !is_numeric($amount_raw)) {
                    $error = "Please enter a valid amount.";
                } else {
                    $amounts_to_save[] = ($amount_raw === '') ? 0 : (float)$amount_raw;
                }
            }

            if ($error === '') {
                $stmt = $conn->prepare("
                    INSERT INTO batches (app_id, batch_date, title, notes)
                    VALUES (?, ?, '', '')
                ");

                if ($stmt) {
                    $app_id = (int)$quick_add['app_id'];
                    $stmt->bind_param("is", $app_id, $line_date);
                    $stmt->execute();
                    $batch_id = (int)$stmt->insert_id;
                    $stmt->close();
                } else {
                    $error = "Could not create batch: " . $conn->error;
                }
            }

            if ($error === '') {
                $stmt = $conn->prepare("
                    INSERT INTO batch_items (
                        batch_id,
                        miner_id,
                        asset_id,
                        category_id,
                        referral_id,
                        from_account_id,
                        to_account_id,
                        amount,
                        notes
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    foreach ($amounts_to_save as $amount) {
                        $stmt->bind_param(
                            "iiiiiiids",
                            $batch_id,
                            $miner_id,
                            $asset_id,
                            $category_id,
                            $referral_id,
                            $from_account_id,
                            $to_account_id,
                            $amount,
                            $notes
                        );
                        $stmt->execute();
                    }

                    $stmt->close();

                    header("Location: index.php?page=quick_entry&saved=1");
                    exit;
                } else {
                    $error = "Could not save quick entry: " . $conn->error;
                }
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $success = "Quick entry saved.";
}

/* -----------------------------
   RECENT ENTRIES
----------------------------- */

$recent_entries = [];

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
    ORDER BY b.id DESC, bi.id DESC
    LIMIT 10
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_entries[] = $row;
    }
}
?>

<div class="page-head">
    <h2>Quick Entry</h2>
    <p class="subtext">Use dedicated Quick Add items.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Quick Add</h3>

        <?php if (!$quick_add_items): ?>
            <p class="subtext">No Quick Add items are available yet.</p>
        <?php else: ?>
            <form method="get" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="quick_entry">

                <div class="form-row">
                    <label for="quick_add_id">Quick Add Item</label>
                    <select id="quick_add_id" name="quick_add_id" onchange="this.form.submit()">
                        <option value="0">Select Quick Add Item</option>
                        <?php foreach ($quick_add_items as $item): ?>
                            <option value="<?= (int)$item['id'] ?>" <?= $quick_add_id === (int)$item['id'] ? 'selected' : '' ?>>
                                <?= h($item['app_name'] ?? '') ?> - <?= h($item['quick_add_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($quick_add): ?>
                <div class="card" style="margin-bottom:18px; padding:16px;">
                    <strong>App:</strong> <?= h($quick_add['app_name'] ?? 'Unassigned') ?><br>
                    <strong>Quick Add:</strong> <?= h($quick_add['quick_add_name']) ?>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="save_quick_add_entry">
                    <input type="hidden" name="quick_add_id" value="<?= (int)$quick_add['id'] ?>">

                    <div class="form-row">
                        <label for="line_date">Line Date</label>
                        <input type="date" id="line_date" name="line_date" value="<?= h($_POST['line_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <?php if ((int)$quick_add['show_miner'] === 1): ?>
                        <div class="form-row">
                            <label for="miner_id">Miner</label>
                            <select id="miner_id" name="miner_id">
                                <option value="0">None</option>
                                <?php foreach ($miners as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)($_POST['miner_id'] ?? (int)$quick_add['miner_id']) === (int)$m['id'] ? 'selected' : '' ?>>
                                        <?= h($m['miner_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_asset'] === 1): ?>
                        <div class="form-row">
                            <label for="asset_id">Asset</label>
                            <select id="asset_id" name="asset_id">
                                <option value="0">None</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['asset_id'] ?? (int)$quick_add['asset_id']) === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['asset_name']) ?>
                                        <?php if (trim((string)$a['asset_symbol']) !== ''): ?>
                                            (<?= h($a['asset_symbol']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_category'] === 1): ?>
                        <div class="form-row">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" required>
                                <option value="0">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <?php if ((int)$c['app_id'] !== (int)$quick_add['app_id']) continue; ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)($_POST['category_id'] ?? (int)$quick_add['category_id']) === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= h($c['category_name']) ?>
                                        <?php if (trim((string)$c['behavior_type']) !== ''): ?>
                                            (<?= h($c['behavior_type']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_referral'] === 1): ?>
                        <div class="form-row">
                            <label for="referral_id">Referral</label>
                            <select id="referral_id" name="referral_id">
                                <option value="0">None</option>
                                <?php foreach ($referrals as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)($_POST['referral_id'] ?? (int)$quick_add['referral_id']) === (int)$r['id'] ? 'selected' : '' ?>>
                                        <?= h($r['referral_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_from_account'] === 1): ?>
                        <div class="form-row">
                            <label for="from_account_id">From Account</label>
                            <select id="from_account_id" name="from_account_id">
                                <option value="0">None</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['from_account_id'] ?? (int)$quick_add['from_account_id']) === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_to_account'] === 1): ?>
                        <div class="form-row">
                            <label for="to_account_id">To Account</label>
                            <select id="to_account_id" name="to_account_id">
                                <option value="0">None</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['to_account_id'] ?? (int)$quick_add['to_account_id']) === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

<?php if ((int)$quick_add['show_amount'] === 1): ?>
    <div class="form-row">
        <?php if ((int)($quick_add['is_multi_add'] ?? 0) === 1): ?>
            <label for="amount_lines">Amount <span style="font-weight:normal;">(one per line)</span></label>
            <textarea id="amount_lines" name="amount_lines" rows="6" placeholder="0.00012345&#10;0.00006789&#10;0.00025000"><?= h($_POST['amount_lines'] ?? '') ?></textarea>

            <div class="batch-note" style="margin-top:6px;">
                <strong>Batch Entry:</strong> If you enter more than one amount here, they will be saved together as one batch. Later changes to shared fields like date or app will affect the whole batch.
            </div>
        <?php else: ?>
            <label for="amount">Amount</label>
            <input type="text" id="amount" name="amount" value="<?= h($_POST['amount'] ?? (string)$quick_add['amount']) ?>" placeholder="Optional">
        <?php endif; ?>
    </div>
<?php endif; ?>

                    <button type="submit" class="btn btn-primary">Save Quick Entry</button>
                </form>
            <?php else: ?>
                <p class="subtext">Select a Quick Add item to continue.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Quick Entries</h3>

        <?php if (!$recent_entries): ?>
            <p class="subtext">No entries saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
							<th style="width:40px;"></th>
                            <th style="width:70px;">ID</th>
                            <th style="width:120px;">Date</th>
                            <th>App</th>
                            <th>Miner</th>
                            <th>Asset</th>
                            <th>Category</th>
                            <th style="width:130px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="quick-add-sortable">
                        <?php foreach ($recent_entries as $e): ?>
                            <tr data-id="<?= (int)$e['id'] ?>">
								<td class="drag-handle">☰</td>
                                <td><?= (int)$e['id'] ?></td>
                                <td><?= h($e['batch_date']) ?></td>
                                <td><?= h($e['app_name'] ?? '') ?></td>
                                <td><?= h($e['miner_name'] ?? '') ?></td>
                                <td>
                                    <?= h($e['asset_name'] ?? '') ?>
                                    <?php if (!empty($e['asset_symbol'])): ?>
                                        (<?= h($e['asset_symbol']) ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?= h($e['category_name'] ?? '') ?></td>
                                <td><?= h(fmt_asset_value(
									$e['amount'],
									(string)($e['currency_symbol'] ?? ''),
									(int)($e['display_decimals'] ?? 8),
									(int)($e['is_fiat'] ?? 0)
								)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const el = document.getElementById('quick-add-sortable');
    if (!el) return;

    new Sortable(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            const rows = el.querySelectorAll('tr');
            const order = [];

            rows.forEach((row, index) => {
                order.push({
                    id: row.dataset.id,
                    sort_order: index
                });
            });

            fetch('quick_adds_reorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(order)
            });
        }
    });
});
</script>