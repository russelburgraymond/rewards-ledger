<?php

$current_page = 'quick_adds';

$error = "";
$success = "";

/* -----------------------------
   Flash messages
----------------------------- */
if (isset($_GET['added'])) {
    $success = "Quick Entry item added successfully.";
}

if (isset($_GET['updated'])) {
    $success = "Quick Entry item updated successfully.";
}

if (isset($_GET['deleted'])) {
    $success = "Quick Entry item deleted successfully.";
}


if (isset($_GET['valuation_saved'])) {
    $success = "Valuation tool settings updated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_valuation_tools') {
    $enable_price_lookup = isset($_POST['enable_price_lookup']) ? '1' : '0';
    $coingecko_demo_api_key = trim((string)($_POST['coingecko_demo_api_key'] ?? ''));

    $ok = set_setting($conn, 'enable_price_lookup', $enable_price_lookup);
    $ok = set_setting($conn, 'coingecko_demo_api_key', $coingecko_demo_api_key) && $ok;

    if ($ok) {
        header("Location: index.php?page=settings&tab=quick_adds&valuation_saved=1");
        exit;
    }

    $error = "Could not save valuation tool settings.";
}

/* -----------------------------
   HANDLE DELETE
----------------------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)($_GET['delete'] ?? 0);

    if ($delete_id > 0) {
        $stmt = $conn->prepare("
            DELETE FROM quick_add_items
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();

            header("Location: index.php?page=settings&tab=quick_adds&deleted=1");
            exit;
        } else {
            $error = "Could not delete Quick Entry item: " . $conn->error;
        }
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
    SELECT id, asset_name, asset_symbol
    FROM assets
    WHERE is_active = 1
    ORDER BY asset_name ASC
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

$enable_price_lookup = get_setting($conn, 'enable_price_lookup', '1') === '1';
$coingecko_demo_api_key = get_setting($conn, 'coingecko_demo_api_key', '');

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_quick_add') {
    $id = (int)($_POST['id'] ?? 0);

    $app_id = (int)($_POST['app_id'] ?? 0);
    $quick_add_name = trim($_POST['quick_add_name'] ?? '');

    $miner_id = (int)($_POST['miner_id'] ?? 0);
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $referral_id = (int)($_POST['referral_id'] ?? 0);
    $from_account_id = (int)($_POST['from_account_id'] ?? 0);
    $to_account_id = (int)($_POST['to_account_id'] ?? 0);

    $amount_raw = trim($_POST['amount'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $show_miner = isset($_POST['show_miner']) ? 1 : 0;
    $show_asset = isset($_POST['show_asset']) ? 1 : 0;
    $show_category = isset($_POST['show_category']) ? 1 : 0;
    $show_referral = isset($_POST['show_referral']) ? 1 : 0;
    $show_amount = isset($_POST['show_amount']) ? 1 : 0;
    $show_notes = isset($_POST['show_notes']) ? 1 : 0;
    $show_received_time = isset($_POST['show_received_time']) ? 1 : 0;
    $show_value_at_receipt = isset($_POST['show_value_at_receipt']) ? 1 : 0;
    $show_from_account = isset($_POST['show_from_account']) ? 1 : 0;
    $show_to_account = isset($_POST['show_to_account']) ? 1 : 0;

    $is_multi_add = isset($_POST['is_multi_add']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($id <= 0 && $sort_order <= 0) {
        $resSort = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM quick_add_items");
        if ($resSort) {
            $rowSort = $resSort->fetch_assoc();
            $sort_order = (int)($rowSort['next_sort_order'] ?? 1);
            $resSort->free();
        } else {
            $sort_order = 1;
        }
    }
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($app_id <= 0) {
        $error = "Please select an app.";
    } elseif ($quick_add_name === '') {
        $error = "Quick Entry name is required.";
    } elseif ($amount_raw !== '' && !is_numeric($amount_raw)) {
        $error = "Please enter a valid amount.";
    } else {
        // duplicate check within same app
        if ($id > 0) {
            $stmtDup = $conn->prepare("
                SELECT id
                FROM quick_add_items
                WHERE app_id = ?
                  AND quick_add_name = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmtDup->bind_param("isi", $app_id, $quick_add_name, $id);
        } else {
            $stmtDup = $conn->prepare("
                SELECT id
                FROM quick_add_items
                WHERE app_id = ?
                  AND quick_add_name = ?
                LIMIT 1
            ");
            $stmtDup->bind_param("is", $app_id, $quick_add_name);
        }

        if (!$stmtDup) {
            $error = "Could not validate Quick Entry item: " . $conn->error;
        } else {
            $stmtDup->execute();
            $dupRes = $stmtDup->get_result();
            $duplicate = $dupRes ? $dupRes->fetch_assoc() : null;
            $stmtDup->close();

            if ($duplicate) {
                $error = "A Quick Entry item with that name already exists for the selected app.";
            } else {
                $amount = ($amount_raw === '') ? 0 : (float)$amount_raw;

                if ($id > 0) {
                    $stmt = $conn->prepare("
                        UPDATE quick_add_items
                        SET
                            app_id = ?,
                            quick_add_name = ?,
                            miner_id = ?,
                            asset_id = ?,
                            category_id = ?,
                            referral_id = ?,
                            from_account_id = ?,
                            to_account_id = ?,
                            amount = ?,
                            notes = ?,
                            show_miner = ?,
                            show_asset = ?,
                            show_category = ?,
                            show_referral = ?,
                            show_amount = ?,
                            show_notes = ?,
                            show_received_time = ?,
                            show_value_at_receipt = ?,
                            show_from_account = ?,
                            show_to_account = ?,
                            is_multi_add = ?,
                            sort_order = ?,
                            is_active = ?
                        WHERE id = ?
                    ");

                    if ($stmt) {
                        $stmt->bind_param(
                            "isiiiiiidsiiiiiiiiiiiiii",
                            $app_id,
                            $quick_add_name,
                            $miner_id,
                            $asset_id,
                            $category_id,
                            $referral_id,
                            $from_account_id,
                            $to_account_id,
                            $amount,
                            $notes,
                            $show_miner,
                            $show_asset,
                            $show_category,
                            $show_referral,
                            $show_amount,
                            $show_notes,
                            $show_received_time,
                            $show_value_at_receipt,
                            $show_from_account,
                            $show_to_account,
                            $is_multi_add,
                            $sort_order,
                            $is_active,
                            $id
                        );
                        $stmt->execute();
                        $stmt->close();

                        header("Location: index.php?page=settings&tab=quick_adds&updated=1");
                        exit;
                    } else {
                        $error = "Could not update Quick Entry item: " . $conn->error;
                    }
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO quick_add_items (
                            app_id,
                            quick_add_name,
                            miner_id,
                            asset_id,
                            category_id,
                            referral_id,
                            from_account_id,
                            to_account_id,
                            amount,
                            notes,
                            show_miner,
                            show_asset,
                            show_category,
                            show_referral,
                            show_amount,
                            show_notes,
                            show_received_time,
                            show_value_at_receipt,
                            show_from_account,
                            show_to_account,
                            is_multi_add,
                            sort_order,
                            is_active
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if ($stmt) {
                        $stmt->bind_param(
                            "isiiiiiidsiiiiiiiiiiiii",
                            $app_id,
                            $quick_add_name,
                            $miner_id,
                            $asset_id,
                            $category_id,
                            $referral_id,
                            $from_account_id,
                            $to_account_id,
                            $amount,
                            $notes,
                            $show_miner,
                            $show_asset,
                            $show_category,
                            $show_referral,
                            $show_amount,
                            $show_notes,
                            $show_received_time,
                            $show_value_at_receipt,
                            $show_from_account,
                            $show_to_account,
                            $is_multi_add,
                            $sort_order,
                            $is_active
                        );
                        $stmt->execute();
                        $stmt->close();

                        header("Location: index.php?page=settings&tab=quick_adds&added=1");
                        exit;
                    } else {
                        $error = "Could not add Quick Entry item: " . $conn->error;
                    }
                }
            }
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
$edit = [
    'id' => 0,
    'app_id' => !empty($apps) ? (int)$apps[0]['id'] : 0,
    'quick_add_name' => '',
    'miner_id' => 0,
    'asset_id' => 0,
    'category_id' => 0,
    'referral_id' => 0,
    'from_account_id' => 0,
    'to_account_id' => 0,
    'amount' => '0',
    'notes' => '',
    'show_miner' => 1,
    'show_asset' => 1,
    'show_category' => 1,
    'show_referral' => 0,
    'show_amount' => 1,
    'show_notes' => 1,
    'show_received_time' => 1,
    'show_value_at_receipt' => 1,
    'show_from_account' => 0,
    'show_to_account' => 0,
    'is_multi_add' => 0,
    'sort_order' => 0,
    'is_active' => 1,
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT
                id,
                app_id,
                quick_add_name,
                miner_id,
                asset_id,
                category_id,
                referral_id,
                from_account_id,
                to_account_id,
                amount,
                notes,
                show_miner,
                show_asset,
                show_category,
                show_referral,
                show_amount,
                show_notes,
                show_received_time,
                show_value_at_receipt,
                show_from_account,
                show_to_account,
                is_multi_add,
                sort_order,
                is_active
            FROM quick_add_items
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                $edit = $row;
            }
        } else {
            $error = "Could not load Quick Entry item for editing: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD LIST
----------------------------- */
$quick_adds = [];

$res = $conn->query("
    SELECT
        qa.id,
        qa.app_id,
        qa.quick_add_name,
        qa.sort_order,
        qa.is_active,
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
        ap.app_name,
        c.category_name,
        a.asset_symbol
    FROM quick_add_items qa
    LEFT JOIN apps ap ON ap.id = qa.app_id
    LEFT JOIN categories c ON c.id = qa.category_id
    LEFT JOIN assets a ON a.id = qa.asset_id
    ORDER BY qa.is_active DESC, qa.sort_order ASC, qa.id ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $quick_adds[] = $row;
    }
} else {
    $error = "Could not load Quick Entry items: " . $conn->error;
}

$template_items = [];
$res_templates = $conn->query("
    SELECT
        t.id,
        t.template_name,
        t.app_id,
        t.sort_order,
        a.app_name,
        COUNT(ti.id) AS line_count
    FROM templates t
    LEFT JOIN apps a ON a.id = t.app_id
    LEFT JOIN template_items ti ON ti.template_id = t.id
    GROUP BY t.id, t.template_name, t.app_id, t.sort_order, a.app_name
    ORDER BY t.sort_order ASC, a.app_name ASC, t.template_name ASC, t.id ASC
");

if ($res_templates) {
    while ($row = $res_templates->fetch_assoc()) {
        $template_items[] = $row;
    }
} else {
    if ($error !== '') {
        $error .= " | ";
    }
    $error .= "Could not load templates: " . $conn->error;
}

$show_quick_add_form = ((int)$edit['id'] > 0) || (($_GET['action'] ?? '') === 'create_quick_add');
?>

<div class="page-head">
    <h2>Quick Entry</h2>
    <p class="subtext">Manage the dedicated Quick Entry items shown in the Quick Entry dropdown.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <?php if ($show_quick_add_form): ?>
            <h3><?= (int)$edit['id'] > 0 ? 'Edit Quick Entry Item' : 'Add Quick Entry Item' ?></h3>

            <form method="post">
                <input type="hidden" name="action" value="save_quick_add">
                <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

                <div class="form-row">
                    <label for="app_id">App</label>
                    <select id="app_id" name="app_id" required>
                        <option value="0">Select App</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?= (int)$app['id'] ?>" <?= (int)$edit['app_id'] === (int)$app['id'] ? 'selected' : '' ?>>
                                <?= h($app['app_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="quick_add_name">Quick Entry Name</label>
                    <input type="text" id="quick_add_name" name="quick_add_name" value="<?= h($edit['quick_add_name']) ?>" maxlength="150" required>
                </div>

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
                    <select id="asset_id" name="asset_id">
                        <option value="0">None</option>
                        <?php foreach ($assets as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$edit['asset_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= h($a['asset_name']) ?><?php if (!empty($a['asset_symbol'])): ?> (<?= h($a['asset_symbol']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="0">None</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-app-id="<?= (int)$c['app_id'] ?>" <?= (int)$edit['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['category_name']) ?><?php if (!empty($c['behavior_type'])): ?> (<?= h($c['behavior_type']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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

                <div class="form-row">
                    <label for="amount">
                        Amount
                        <span style="margin-left:12px; font-weight:normal;">
                            <input type="checkbox" name="is_multi_add" value="1" <?= !empty($edit['is_multi_add']) ? 'checked' : '' ?>>
                            Allow multiple inputs
                        </span>
                    </label>
                    <input type="text" id="amount" name="amount" value="<?= h((string)$edit['amount']) ?>">
                </div>

                <div class="form-row">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"><?= h($edit['notes']) ?></textarea>
                </div>

                <div class="form-row">
                    <label>Show This Field</label>
                    <div>
                        <label><input type="checkbox" name="show_miner" <?= !empty($edit['show_miner']) ? 'checked' : '' ?>> Miner</label><br>
                        <label><input type="checkbox" name="show_asset" <?= !empty($edit['show_asset']) ? 'checked' : '' ?>> Asset</label><br>
                        <label><input type="checkbox" name="show_category" <?= !empty($edit['show_category']) ? 'checked' : '' ?>> Category</label><br>
                        <label><input type="checkbox" name="show_referral" <?= !empty($edit['show_referral']) ? 'checked' : '' ?>> Referral</label><br>
                        <label><input type="checkbox" name="show_amount" <?= !empty($edit['show_amount']) ? 'checked' : '' ?>> Amount</label><br>
                        <label><input type="checkbox" name="show_notes" <?= !empty($edit['show_notes']) ? 'checked' : '' ?>> Notes</label><br>
                        <label><input type="checkbox" name="show_received_time" <?= !empty($edit['show_received_time']) ? 'checked' : '' ?>> Time Received</label><br>
                        <label><input type="checkbox" name="show_value_at_receipt" <?= !empty($edit['show_value_at_receipt']) ? 'checked' : '' ?>> Value at Receipt</label><br>
                        <label><input type="checkbox" name="show_from_account" <?= !empty($edit['show_from_account']) ? 'checked' : '' ?>> From Account</label><br>
                        <label><input type="checkbox" name="show_to_account" <?= !empty($edit['show_to_account']) ? 'checked' : '' ?>> To Account</label>
                    </div>
                </div>

                <div class="form-row">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)$edit['sort_order'] ?>">
                </div>

                <div class="form-row">
                    <label>
                        <input type="checkbox" name="is_active" <?= !empty($edit['is_active']) ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= (int)$edit['id'] > 0 ? 'Save Quick Entry Item' : 'Add Quick Entry Item' ?>
                </button>

                <a class="btn btn-secondary" href="index.php?page=settings&tab=quick_adds">Cancel</a>
            </form>
        <?php else: ?>
            <h3>Templates</h3>
            <p class="subtext">Create new items from here, or edit existing ones in the list on the right.</p>

            <p style="margin:0 0 10px 0;">
                <a class="btn btn-primary" href="index.php?page=template_edit&from=settings_templates">+ Add New Template</a>
            </p>

            <p style="margin:0 0 18px 0;">
                <a class="btn btn-secondary" href="index.php?page=settings&tab=quick_adds&action=create_quick_add">+ Add New Quick Add Item</a>
            </p>

            <div class="card" style="margin-top:16px; padding:16px;">
                <h3 style="margin-top:0;">Valuation Tools</h3>

                <form method="post">
                    <input type="hidden" name="action" value="save_valuation_tools">

                    <label style="display:block; margin-bottom:10px;">
                        <input type="checkbox" name="enable_price_lookup" <?= $enable_price_lookup ? 'checked' : '' ?>>
                        Enable manual price lookup buttons on Quick Entry and Template Use forms
                    </label>

                    <div class="form-row" style="margin-top:12px;">
                        <label for="coingecko_demo_api_key">CoinGecko Demo API Key (optional)</label>
                        <input type="text" id="coingecko_demo_api_key" name="coingecko_demo_api_key" value="<?= h($coingecko_demo_api_key) ?>" placeholder="Leave blank to try public access">
                        <div class="subtext" style="margin-top:6px;">Optional. If you add a Demo API key here, the lookup button will send it from your server.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Valuation Tools</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Template Items</h3>
        <p class="subtext">Edit or delete templates here. Quick Entry items are listed below.</p>

        <?php if (!$template_items): ?>
            <p class="subtext">No templates found.</p>
        <?php else: ?>
            <div class="table-wrap" style="margin-bottom:18px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>App</th>
                            <th>Name</th>
                            <th style="width:80px;">Lines</th>
                            <th style="width:80px;">Sort</th>
                            <th style="width:90px;">Edit</th>
                            <th style="width:90px;">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="template-sortable">
                        <?php foreach ($template_items as $tpl): ?>
                            <tr data-id="<?= (int)$tpl['id'] ?>">
                                <td class="drag-handle">☰</td>
                                <td><?= h($tpl['app_name'] ?? '') ?></td>
                                <td><?= h($tpl['template_name'] ?: 'Untitled') ?></td>
                                <td><span class="badge badge-blue"><?= (int)$tpl['line_count'] ?></span></td>
                                <td class="sort-order"><?= (int)$tpl['sort_order'] ?></td>
                                <td>
                                    <a class="table-link" href="index.php?page=template_edit&id=<?= (int)$tpl['id'] ?>&from=settings_templates">Edit</a>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=template_delete&id=<?= (int)$tpl['id'] ?>&from=settings_templates" onclick="return confirm('Delete this template and its template lines?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 style="margin-top:4px;">Quick Entry Items</h3>
        <p class="subtext">Drag rows to control Quick Entry dropdown order.</p>

        <?php if (!$quick_adds): ?>
            <p class="subtext">No Quick Entry items found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>App</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th style="width:80px;">Asset</th>
                            <th style="width:80px;">Multi</th>
                            <th style="width:80px;">Sort</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                            <th style="width:90px;">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="quick-add-sortable">
                        <?php foreach ($quick_adds as $qa): ?>
                            <tr data-id="<?= (int)$qa['id'] ?>">
                                <td class="drag-handle">☰</td>
                                <td><?= h($qa['app_name'] ?? '') ?></td>
                                <td><?= h($qa['quick_add_name']) ?></td>
                                <td><?= h($qa['category_name'] ?? '') ?></td>
                                <td><?= h($qa['asset_symbol'] ?? '') ?></td>
                                <td>
                                    <?php if ((int)$qa['is_multi_add'] === 1): ?>
                                        <span class="badge badge-green">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="sort-order"><?= (int)$qa['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$qa['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=quick_adds&edit=<?= (int)$qa['id'] ?>">Edit</a>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=quick_adds&delete=<?= (int)$qa['id'] ?>" onclick="return confirm('Delete this Quick Entry item?');">Delete</a>
                                </td>
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
    const appSelect = document.getElementById("app_id");
    const categorySelect = document.getElementById("category_id");

    function filterCategoriesByApp() {
        if (!appSelect || !categorySelect) return;

        const selectedAppId = appSelect.value;

        Array.from(categorySelect.options).forEach(function (opt, index) {
            if (index === 0) {
                opt.hidden = false;
                return;
            }

            const optionAppId = opt.getAttribute("data-app-id");
            opt.hidden = (optionAppId !== selectedAppId);
        });

        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            categorySelect.value = "0";
        }
    }

    if (appSelect && categorySelect) {
        appSelect.addEventListener("change", filterCategoriesByApp);
        filterCategoriesByApp();
    }

    const templateEl = document.getElementById('template-sortable');
    if (templateEl && typeof Sortable !== 'undefined') {
        new Sortable(templateEl, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const rows = templateEl.querySelectorAll('tr');
                const order = [];

                rows.forEach((row, index) => {
                    order.push({
                        id: row.dataset.id,
                        sort_order: index + 1
                    });

                    const sortCell = row.querySelector('.sort-order');
                    if (sortCell) {
                        sortCell.textContent = index + 1;
                    }
                });

                fetch('templates_reorder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(order)
                });
            }
        });
    }

    const el = document.getElementById('quick-add-sortable');
    if (!el || typeof Sortable === 'undefined') return;

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
                    sort_order: index + 1
                });

                const sortCell = row.querySelector('.sort-order');
                if (sortCell) {
                    sortCell.textContent = index + 1;
                }
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