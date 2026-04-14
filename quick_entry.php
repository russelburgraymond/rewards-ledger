<?php

$current_page = 'quick_entry';

$price_lookup_enabled = get_setting($conn, 'enable_price_lookup', '1') === '1';

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
        qa.show_received_time,
        qa.show_value_at_receipt,
        qa.show_from_account,
        qa.show_to_account,
		qa.is_multi_add,
        qa.use_sats,
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

$apps = [];
$miners = [];
$assets = [];
$categories = [];
$accounts = [];
$referrals = [];

$res = $conn->query("SELECT id, app_name FROM apps WHERE is_active = 1 ORDER BY app_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $apps[] = $row;

$res = $conn->query("SELECT id, miner_name FROM miners WHERE is_active = 1 ORDER BY miner_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $miners[] = $row;

$res = $conn->query("SELECT id, asset_name, asset_symbol FROM assets WHERE is_active = 1 ORDER BY asset_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;

$btc_asset_id = 0;
foreach ($assets as $asset_row) {
    $symbol = strtoupper(trim((string)($asset_row['asset_symbol'] ?? '')));
    $name = strtoupper(trim((string)($asset_row['asset_name'] ?? '')));
    if ($symbol === 'BTC' || $name === 'BITCOIN' || $name === 'BTC') {
        $btc_asset_id = (int)$asset_row['id'];
        break;
    }
}

function rl_entry_uses_sats($asset_id, $use_sats, $btc_asset_id) {
    return (int)$btc_asset_id > 0 && (int)$asset_id === (int)$btc_asset_id && (int)$use_sats === 1;
}

function rl_sats_to_btc_string($value) {
    if ($value === null || $value === '') return '';
    return number_format(((float)$value) / 100000000, 8, '.', '');
}

$res = $conn->query("SELECT id, app_id, category_name, behavior_type FROM categories WHERE is_active = 1 ORDER BY app_id ASC, sort_order ASC, category_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;

$res = $conn->query("SELECT id, account_name FROM accounts WHERE is_active = 1 ORDER BY account_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $accounts[] = $row;

$res = $conn->query("SELECT id, referral_name FROM referrals WHERE is_active = 1 ORDER BY referral_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $referrals[] = $row;

/* -----------------------------
   LOAD SELECTED QUICK ADD
----------------------------- */

$quick_add_key = trim((string)($_GET['quick_add_id'] ?? $_POST['quick_add_id'] ?? 'generic'));
if ($quick_add_key === '') {
    $quick_add_key = 'generic';
}
$quick_add = null;
$selected_app_id = 0;

if ($quick_add_key !== 'generic') {
    $quick_add_id = (int)$quick_add_key;
    foreach ($quick_add_items as $item) {
        if ((int)$item['id'] === $quick_add_id) {
            $quick_add = $item;
            $selected_app_id = (int)($item['app_id'] ?? 0);
            break;
        }
    }
} else {
    $selected_app_id = (int)($_POST['app_id'] ?? $_GET['app_id'] ?? 0);
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_quick_add_entry') {
    $quick_add_key = trim((string)($_POST['quick_add_id'] ?? 'generic'));
    if ($quick_add_key === '') {
        $quick_add_key = 'generic';
    }
    $line_date = trim($_POST['line_date'] ?? date('Y-m-d'));
    $received_time = trim($_POST['received_time'] ?? '');
    $value_at_receipt_raw = trim($_POST['value_at_receipt'] ?? '');
    $value_at_receipt_lines_raw = trim($_POST['value_at_receipt_lines'] ?? '');
    $posted_use_sats = isset($_POST['use_sats']) ? 1 : 0;

    $quick_add = null;
    if ($quick_add_key !== 'generic') {
        $quick_add_id = (int)$quick_add_key;
        foreach ($quick_add_items as $item) {
            if ((int)$item['id'] === $quick_add_id) {
                $quick_add = $item;
                break;
            }
        }
    }

    if ($quick_add_key !== 'generic' && !$quick_add) {
        $error = "Please select a Quick Add item.";
    } elseif ($line_date === '') {
        $error = "Please enter a line date.";
    } else {
        if ($quick_add_key === 'generic') {
            $app_id = (int)($_POST['app_id'] ?? 0);
            $miner_id = (int)($_POST['miner_id'] ?? 0);
            $asset_id = (int)($_POST['asset_id'] ?? 0);
            $category_id = (int)($_POST['category_id'] ?? 0);
            $referral_id = (int)($_POST['referral_id'] ?? 0);
            $from_account_id = (int)($_POST['from_account_id'] ?? 0);
            $to_account_id = (int)($_POST['to_account_id'] ?? 0);
            $is_multi_add = 1;
            $amount_raw = '';
            $amount_lines_raw = trim($_POST['amount_lines'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $show_received_time = 1;
            $show_value_at_receipt = 1;
            $use_sats = $posted_use_sats;
        } else {
            $app_id = (int)($quick_add['app_id'] ?? 0);
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

            $show_received_time = (int)($quick_add['show_received_time'] ?? 0);
            $show_value_at_receipt = (int)($quick_add['show_value_at_receipt'] ?? 0);
            $use_sats = ((int)($quick_add['use_sats'] ?? 0) === 1) ? 1 : $posted_use_sats;
        }

        if ($quick_add_key === 'generic' && $app_id <= 0) {
            $error = "Please choose an app.";
        } elseif ($category_id <= 0) {
            $error = "Please choose a category.";
        } elseif ($received_time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $received_time)) {
            $error = "Please enter a valid time received.";
        } elseif ($is_multi_add !== 1 && $value_at_receipt_raw !== '' && !is_numeric($value_at_receipt_raw)) {
            $error = "Please enter a valid value at receipt.";
        } else {
            $value_at_receipt = ($show_value_at_receipt === 1 && $value_at_receipt_raw !== '' && $is_multi_add !== 1)
                ? (float)$value_at_receipt_raw
                : null;
            $received_time_db = ($show_received_time === 1 && $received_time !== '') ? $received_time : null;
            $amounts_to_save = [];
            $values_to_save = [];

            if ($is_multi_add === 1) {
                if ($amount_lines_raw === '') {
                    $error = "Please enter at least one amount.";
                } else {
                    $amount_lines = preg_split('/\r\n|\r|\n/', $amount_lines_raw);
                    $value_lines = preg_split('/\r\n|\r|\n/', $value_at_receipt_lines_raw);

                    foreach ($amount_lines as $idx => $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        if (!is_numeric($line)) {
                            $error = "One or more multi-add amounts are invalid.";
                            break;
                        }

                        if (rl_entry_uses_sats($asset_id, $use_sats, $btc_asset_id)) {
                            $amounts_to_save[] = (float)rl_sats_to_btc_string($line);
                        } else {
                            $amounts_to_save[] = (float)$line;
                        }

                        $value_line = trim((string)($value_lines[$idx] ?? ''));
                        if ($show_value_at_receipt === 1 && $value_line !== '') {
                            if (!is_numeric($value_line)) {
                                $error = "One or more value at receipt lines are invalid.";
                                break;
                            }
                            $values_to_save[] = (float)$value_line;
                        } else {
                            $values_to_save[] = null;
                        }
                    }

                    if ($error === '' && empty($amounts_to_save)) {
                        $error = "Please enter at least one valid amount.";
                    }
                }
            } else {
                if ($amount_raw !== '' && !is_numeric($amount_raw)) {
                    $error = "Please enter a valid amount.";
                } else {
                    if ($amount_raw === '') {
                        $amounts_to_save[] = 0;
                    } elseif (rl_entry_uses_sats($asset_id, $use_sats, $btc_asset_id)) {
                        $amounts_to_save[] = (float)rl_sats_to_btc_string($amount_raw);
                    } else {
                        $amounts_to_save[] = (float)$amount_raw;
                    }
                    $values_to_save[] = $value_at_receipt;
                }
            }

            if ($error === '') {
                $stmt = $conn->prepare("
                    INSERT INTO batches (app_id, batch_date, title, notes)
                    VALUES (?, ?, '', '')
                ");

                if ($stmt) {
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
                        received_time,
                        value_at_receipt,
                        notes,
                        import_source_type
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    foreach ($amounts_to_save as $idx => $amount) {
                        $row_value_at_receipt = $values_to_save[$idx] ?? null;
                        $value_at_receipt_db = ($row_value_at_receipt === null || $row_value_at_receipt === '')
                            ? null
                            : number_format((float)$row_value_at_receipt, 8, '.', '');
						$import_source_type = 'quick_entry';
						$stmt->bind_param(
							"iiiiiiidssss",
							$batch_id,
							$miner_id,
							$asset_id,
							$category_id,
							$referral_id,
							$from_account_id,
							$to_account_id,
							$amount,
							$received_time_db,
							$value_at_receipt_db,
							$notes,
							$import_source_type
						);
                        $stmt->execute();
                    }

                    $stmt->close();

                    header("Location: index.php?page=quick_entry&quick_add_id=" . urlencode($quick_add_key) . "&saved=1");
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

            <form method="get" style="margin-bottom:18px;">
                <input type="hidden" name="page" value="quick_entry">

                <div class="form-row">
                    <label for="quick_add_id">Quick Add Item</label>
                    <select id="quick_add_id" name="quick_add_id" onchange="this.form.submit()">
                        <option value="generic" <?= $quick_add_key === 'generic' ? 'selected' : '' ?>>Generic</option>
                        <?php foreach ($quick_add_items as $item): ?>
                            <option value="<?= (int)$item['id'] ?>" <?= $quick_add_key === (string)(int)$item['id'] ? 'selected' : '' ?>>
                                <?= h($item['app_name'] ?? '') ?> - <?= h($item['quick_add_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($quick_add_key === 'generic' || $quick_add): ?>
                <div class="card" style="margin-bottom:18px; padding:16px;">
                    <?php if ($quick_add_key === 'generic'): ?>
                        <strong>Quick Add:</strong> Generic<br>
                        <span class="subtext">Full-entry mode with all fields shown and amount set for multi-add.</span>
                    <?php else: ?>
                        <strong>App:</strong> <?= h($quick_add['app_name'] ?? 'Unassigned') ?><br>
                        <strong>Quick Add:</strong> <?= h($quick_add['quick_add_name']) ?>
                    <?php endif; ?>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="save_quick_add_entry">
                    <input type="hidden" name="quick_add_id" value="<?= h($quick_add_key) ?>">

                    <div class="form-row">
                        <label for="line_date">Line Date</label>
                        <input type="date" id="line_date" name="line_date" value="<?= h($_POST['line_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <?php if ($quick_add_key === 'generic'): ?>
                        <div class="form-row">
                            <label for="received_time">Time Received</label>
                            <input type="time" id="received_time" name="received_time" value="<?= h($_POST['received_time'] ?? '') ?>">
                        </div>

                        <div class="form-row">
                            <label for="app_id">App</label>
                            <select id="app_id" name="app_id" required>
                                <option value="0">Select App</option>
                                <?php foreach ($apps as $app): ?>
                                    <option value="<?= (int)$app['id'] ?>" <?= (int)($_POST['app_id'] ?? $selected_app_id) === (int)$app['id'] ? 'selected' : '' ?>>
                                        <?= h($app['app_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="miner_id">Miner</label>
                            <select id="miner_id" name="miner_id">
                                <option value="0">None</option>
                                <?php foreach ($miners as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)($_POST['miner_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                                        <?= h($m['miner_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="asset_id">Asset</label>
                            <select id="asset_id" name="asset_id">
                                <option value="0">None</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['asset_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['asset_name']) ?><?php if (trim((string)$a['asset_symbol']) !== ''): ?> (<?= h($a['asset_symbol']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row js-sats-toggle-row" data-btc-asset-id="<?= (int)$btc_asset_id ?>" data-default-checked="1" style="display:none;">
                            <label>
                                <input type="checkbox" id="use_sats" name="use_sats" value="1" <?= isset($_POST['use_sats']) ? 'checked' : '' ?>>
                                Sats
                            </label>
                            <div class="subtext">When BTC is selected, enter sats instead of full BTC.</div>
                        </div>

                        <div class="form-row">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" required>
                                <option value="0">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" data-app-id="<?= (int)$c['app_id'] ?>" <?= (int)($_POST['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= h($c['category_name']) ?><?php if (trim((string)$c['behavior_type']) !== ''): ?> (<?= h($c['behavior_type']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="referral_id">Referral</label>
                            <select id="referral_id" name="referral_id">
                                <option value="0">None</option>
                                <?php foreach ($referrals as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)($_POST['referral_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['from_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)($_POST['to_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="amount_lines">Amount <span style="font-weight:normal;">(one per line)</span></label>
                            <textarea id="amount_lines" name="amount_lines" rows="6" placeholder="12345&#10;789&#10;25000"><?= h($_POST['amount_lines'] ?? ((string)($quick_add['amount'] ?? ''))) ?></textarea>
                            <div class="batch-note" style="margin-top:6px;">
                                <strong>Batch Entry:</strong> If you enter more than one amount here, they will be saved together as one batch. Later changes to shared fields like date or app will affect the whole batch.
                            </div>
                        </div>

                        <div class="form-row">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Optional notes"><?= h($_POST['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row">
                            <label for="value_at_receipt_lines">Value at Receipt <span style="font-weight:normal;">(one per line)</span></label>
                            <textarea id="value_at_receipt_lines" name="value_at_receipt_lines" rows="6" placeholder="8.64&#10;21.60&#10;25.92"><?= h($_POST['value_at_receipt_lines'] ?? '') ?></textarea>
                            <?php if ($price_lookup_enabled): ?>
                                <div style="margin-top:8px;">
                                    <button type="button" class="btn btn-secondary js-price-lookup-multi">Lookup Values</button>
                                </div>
                            <?php endif; ?>
                            <div class="subtext" id="value_lookup_status" style="margin-top:6px;"></div>
                        </div>
                    <?php else: ?>

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

                        <div class="form-row js-sats-toggle-row" data-btc-asset-id="<?= (int)$btc_asset_id ?>" data-default-checked="<?= !empty($quick_add['use_sats']) ? '1' : '0' ?>" style="display:none;">
                            <label>
                                <input type="checkbox" id="use_sats" name="use_sats" value="1" <?= (!isset($_POST['use_sats']) && !empty($quick_add['use_sats'])) || isset($_POST['use_sats']) ? 'checked' : '' ?>>
                                Sats
                            </label>
                            <div class="subtext">When BTC is selected, enter sats instead of full BTC.</div>
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
<input type="text" name="amount"
       value="<?= h((string)($_POST['amount'] ?? ($quick_add['amount'] ?? ''))) ?>"
       placeholder="0.000000"
       onfocus="this.select()">
        <?php endif; ?>
    </div>
<?php endif; ?>


                    <?php if ((int)$quick_add['show_received_time'] === 1): ?>
                        <div class="form-row">
                            <label for="received_time">Time Received</label>
                            <input type="time" id="received_time" name="received_time" value="<?= h($_POST['received_time'] ?? '') ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_value_at_receipt'] === 1 && (int)($quick_add['is_multi_add'] ?? 0) === 1): ?>
                        <div class="form-row">
                            <label for="value_at_receipt_lines">Value at Receipt <span style="font-weight:normal;">(one per line)</span></label>
                            <textarea id="value_at_receipt_lines" name="value_at_receipt_lines" rows="6" placeholder="8.64&#10;21.60&#10;25.92"><?= h($_POST['value_at_receipt_lines'] ?? '') ?></textarea>
                            <?php if ($price_lookup_enabled): ?>
                                <div style="margin-top:8px;">
                                    <button type="button" class="btn btn-secondary js-price-lookup-multi">Lookup Values</button>
                                </div>
                            <?php endif; ?>
                            <div class="subtext" id="value_lookup_status" style="margin-top:6px;"></div>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_value_at_receipt'] === 1 && (int)($quick_add['is_multi_add'] ?? 0) !== 1): ?>
                        <div class="form-row">
                            <label for="value_at_receipt"><?= ((int)($quick_add['is_multi_add'] ?? 0) === 1) ? 'Value at Receipt' : 'Value at Receipt' ?></label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" id="value_at_receipt" name="value_at_receipt" value="<?= h($_POST['value_at_receipt'] ?? '') ?>" placeholder="0.00" style="flex:1;">
                                <?php if ($price_lookup_enabled): ?>
                                    <button type="button" class="btn btn-secondary js-price-lookup-single">Lookup</button>
                                <?php endif; ?>
                            </div>
                            <div class="subtext" id="value_lookup_status" style="margin-top:6px;"></div>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$quick_add['show_notes'] === 1): ?>
                        <div class="form-row">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Optional notes"><?= h($_POST['notes'] ?? (string)($quick_add['notes'] ?? '')) ?></textarea>
                        </div>
                    <?php endif; ?>

                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">Save Quick Entry</button>
                </form>
            <?php else: ?>
                <p class="subtext">Select a Quick Add item to continue.</p>
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
    const appSelect = document.getElementById('app_id');
    const categorySelect = document.getElementById('category_id');

    function filterGenericCategories() {
        if (!appSelect || !categorySelect) return;
        const selectedAppId = appSelect.value || '0';
        Array.from(categorySelect.options).forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const optionAppId = option.getAttribute('data-app-id') || '0';
            option.hidden = (selectedAppId !== '0' && optionAppId !== selectedAppId);
        });
        if (categorySelect.selectedOptions.length && categorySelect.selectedOptions[0].hidden) {
            categorySelect.value = '0';
        }
    }

    if (appSelect && categorySelect) {
        appSelect.addEventListener('change', filterGenericCategories);
        filterGenericCategories();
    }


    const formEl = document.querySelector('form[method="post"]');
    const satsRow = formEl ? formEl.querySelector('.js-sats-toggle-row') : null;
    const assetInput = formEl ? formEl.querySelector('select[name="asset_id"], input[name="asset_id"]') : null;
    const satsCheckbox = satsRow ? satsRow.querySelector('input[name="use_sats"]') : null;
    const amountInput = formEl ? formEl.querySelector('input[name="amount"]') : null;
    const amountLinesInput = formEl ? formEl.querySelector('textarea[name="amount_lines"]') : null;

    function setAmountPlaceholder() {
        if (amountInput) {
            amountInput.placeholder = (satsCheckbox && satsCheckbox.checked) ? '12345' : '0.000000';
        }
        if (amountLinesInput) {
            amountLinesInput.placeholder = (satsCheckbox && satsCheckbox.checked)
                ? '12345\n789\n25000'
                : '0.00012345\n0.00006789\n0.00025000';
        }
    }

    function syncSatsUi(forceDefault) {
        if (!satsRow || !assetInput || !satsCheckbox) return;
        const btcAssetId = String(satsRow.getAttribute('data-btc-asset-id') || '0');
        const isBtc = btcAssetId !== '0' && String(assetInput.value || '0') === btcAssetId;
        if (isBtc) {
            satsRow.style.display = '';
            if (forceDefault) {
                satsCheckbox.checked = String(satsRow.getAttribute('data-default-checked') || '0') === '1';
            }
        } else {
            satsRow.style.display = 'none';
            satsCheckbox.checked = false;
        }
        setAmountPlaceholder();
    }

    if (assetInput && satsCheckbox && satsRow) {
        assetInput.addEventListener('change', function () { syncSatsUi(true); });
        satsCheckbox.addEventListener('change', setAmountPlaceholder);
        syncSatsUi(true);
    }

    const statusEl = document.getElementById('value_lookup_status');

    function setLookupStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.style.color = isError ? '#d9534f' : '';
    }

    async function fetchLookupValue(payload) {
        const response = await fetch('ajax/get_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        return { response, data };
    }

    const singleLookupBtn = document.querySelector('.js-price-lookup-single');
    if (singleLookupBtn) {
        singleLookupBtn.addEventListener('click', async function () {
            const amountEl = document.querySelector('input[name="amount"]');
            const assetEl = document.querySelector('select[name="asset_id"], input[name="asset_id"]');
            const timeEl = document.querySelector('input[name="received_time"]');
            const valueEl = document.querySelector('input[name="value_at_receipt"]');
            const dateEl = document.getElementById('line_date');

            if (!amountEl || !assetEl || !timeEl || !valueEl || !dateEl) {
                return;
            }

            const satsEl = formEl ? formEl.querySelector('input[name="use_sats"]') : null;
            const payload = {
                asset_id: assetEl.value,
                amount: (satsEl && satsEl.checked) ? (parseFloat(amountEl.value || '0') / 100000000).toFixed(8) : amountEl.value,
                date: dateEl.value,
                time: timeEl.value
            };

            if (!payload.asset_id || payload.asset_id === '0' || !payload.amount || !payload.date || !payload.time) {
                setLookupStatus('Enter asset, amount, date, and time first.', true);
                return;
            }

            setLookupStatus('Looking up price...', false);

            try {
                const { response, data } = await fetchLookupValue(payload);

                if (!response.ok || !data.success) {
                    const rawError = (data && data.error) ? String(data.error) : 'Price lookup failed.';
                    const message = rawError.toLowerCase().includes('mapped for price lookup')
                        ? 'Lookup not available for this asset. Enter value manually if needed.'
                        : rawError;
                    setLookupStatus(message, true);
                    return;
                }

                valueEl.value = data.total_value_formatted || data.total_value || '';
                setLookupStatus(data.message || 'Price loaded.', false);
            } catch (error) {
                setLookupStatus('Price lookup failed.', true);
            }
        });
    }

    const multiLookupBtn = document.querySelector('.js-price-lookup-multi');
    if (multiLookupBtn) {
        multiLookupBtn.addEventListener('click', async function () {
            const amountsEl = document.querySelector('textarea[name="amount_lines"]');
            const valuesEl = document.querySelector('textarea[name="value_at_receipt_lines"]');
            const assetEl = document.querySelector('select[name="asset_id"], input[name="asset_id"]');
            const timeEl = document.querySelector('input[name="received_time"]');
            const dateEl = document.getElementById('line_date');

            if (!amountsEl || !valuesEl || !assetEl || !timeEl || !dateEl) {
                return;
            }

            const assetId = assetEl.value;
            const date = dateEl.value;
            const time = timeEl.value;
            const amountLines = amountsEl.value.split(/\r?\n/);

            if (!assetId || assetId === '0' || !date || !time) {
                setLookupStatus('Enter asset, date, and time first.', true);
                return;
            }

            if (!amountLines.some(line => line.trim() !== '')) {
                setLookupStatus('Enter at least one amount first.', true);
                return;
            }

            setLookupStatus('Looking up prices...', false);

            try {
                const results = [];
                for (const line of amountLines) {
                    const amount = line.trim();
                    if (amount === '') {
                        results.push('');
                        continue;
                    }

                    const satsEl = formEl ? formEl.querySelector('input[name="use_sats"]') : null;
                    const lookupAmount = (satsEl && satsEl.checked) ? (parseFloat(amount || '0') / 100000000).toFixed(8) : amount;
                    const { response, data } = await fetchLookupValue({
                        asset_id: assetId,
                        amount: lookupAmount,
                        date,
                        time
                    });

                    if (!response.ok || !data.success) {
                        const rawError = (data && data.error) ? String(data.error) : 'Price lookup failed.';
                        const unsupported = rawError.toLowerCase().includes('mapped for price lookup');
                        setLookupStatus(
                            unsupported
                                ? 'Lookup not available for this asset. You can still enter one value per line manually.'
                                : rawError,
                            true
                        );
                        return;
                    }

                    results.push(data.total_value_formatted || data.total_value || '');
                }

                valuesEl.value = results.join('\n');
                setLookupStatus('Values loaded.', false);
            } catch (error) {
                setLookupStatus('Price lookup failed.', true);
            }
        });
    }

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