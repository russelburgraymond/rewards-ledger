<?php

$current_page = 'templates';

$error = "";
$success = "";

$template_id = (int)($_GET['id'] ?? 0);

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
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $apps[] = $row;
    }
}

$res = $conn->query("
    SELECT id, miner_name
    FROM miners
    WHERE is_active = 1
    ORDER BY miner_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $miners[] = $row;
    }
}

$res = $conn->query("
    SELECT id, asset_name, asset_symbol
    FROM assets
    WHERE is_active = 1
    ORDER BY asset_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $assets[] = $row;
    }
}

$res = $conn->query("
    SELECT id, app_id, category_name, behavior_type
    FROM categories
    WHERE is_active = 1
    ORDER BY app_id ASC, sort_order ASC, category_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

$res = $conn->query("
    SELECT id, account_name
    FROM accounts
    WHERE is_active = 1
    ORDER BY account_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
}

$res = $conn->query("
    SELECT id, referral_name
    FROM referrals
    WHERE is_active = 1
    ORDER BY referral_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $referrals[] = $row;
    }
}

/* -----------------------------
   CREATE NEW TEMPLATE IF NEEDED
----------------------------- */

if ($template_id <= 0) {
    $default_app_id = !empty($apps) ? (int)$apps[0]['id'] : null;

    $stmt = $conn->prepare("
        INSERT INTO templates (app_id, template_name, notes)
        VALUES (?, 'New Template', '')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $default_app_id);
        $stmt->execute();
        $template_id = (int)$stmt->insert_id;
        $stmt->close();

        header("Location: index.php?page=template_edit&id=" . $template_id);
        exit;
    } else {
        $error = "Could not create template: " . $conn->error;
    }
}

/* -----------------------------
   HANDLE TEMPLATE HEADER SAVE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_template') {
    $app_id = (int)($_POST['app_id'] ?? 0);
    $template_name = trim($_POST['template_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($app_id <= 0) {
        $error = "Please select an app.";
    } elseif ($template_name === '') {
        $error = "Template name is required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE templates
            SET app_id = ?, template_name = ?, notes = ?
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("issi", $app_id, $template_name, $notes, $template_id);
            $stmt->execute();
            $stmt->close();
            $success = "Template updated.";
        } else {
            $error = "Could not update template: " . $conn->error;
        }
    }
}

/* -----------------------------
   HANDLE NEW TEMPLATE LINE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_line') {
    $miner_id = (int)($_POST['miner_id'] ?? 0);
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $referral_id = (int)($_POST['referral_id'] ?? 0);
    $from_account_id = (int)($_POST['from_account_id'] ?? 0);
    $to_account_id = (int)($_POST['to_account_id'] ?? 0);
    $amount = trim($_POST['amount'] ?? '');
    $notes = trim($_POST['line_notes'] ?? '');

    $show_miner = isset($_POST['show_miner']) ? 1 : 0;
    $show_asset = isset($_POST['show_asset']) ? 1 : 0;
    $show_category = isset($_POST['show_category']) ? 1 : 0;
    $show_referral = isset($_POST['show_referral']) ? 1 : 0;
    $show_amount = isset($_POST['show_amount']) ? 1 : 0;
    $show_notes = isset($_POST['show_notes']) ? 1 : 0;
    $show_from_account = isset($_POST['show_from_account']) ? 1 : 0;
    $show_to_account = isset($_POST['show_to_account']) ? 1 : 0;

    $show_in_quick_add = isset($_POST['show_in_quick_add']) ? 1 : 0;
    $quick_add_name = trim($_POST['quick_add_name'] ?? '');

    if ($category_id <= 0) {
        $error = "Please select a category.";
    } elseif ($amount !== '' && !is_numeric($amount)) {
        $error = "Please enter a valid amount.";
    } elseif ($show_in_quick_add === 1 && $quick_add_name === '') {
        $error = "Please enter a Quick Add name.";
    } else {
        $amount_decimal = ($amount === '') ? 0 : (float)$amount;

        $stmt = $conn->prepare("
            INSERT INTO template_items (
                template_id,
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
                show_from_account,
                show_to_account,
                show_in_quick_add,
                quick_add_name
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "Could not add template line: " . $conn->error;
        } else {
            $stmt->bind_param(
                "iiiiiiidsiiiiiiiiis",
                $template_id,
                $miner_id,
                $asset_id,
                $category_id,
                $referral_id,
                $from_account_id,
                $to_account_id,
                $amount_decimal,
                $notes,
                $show_miner,
                $show_asset,
                $show_category,
                $show_referral,
                $show_amount,
                $show_notes,
                $show_from_account,
                $show_to_account,
                $show_in_quick_add,
                $quick_add_name
            );
if ($stmt->execute()) {
    $new_template_item_id = (int)$stmt->insert_id;
    $stmt->close();

    // If this line should also appear in Quick Entry, create a quick_add_items row
    if ($show_in_quick_add === 1) {
        $template_app_id = 0;

        $stmtApp = $conn->prepare("SELECT app_id FROM templates WHERE id = ? LIMIT 1");
        if ($stmtApp) {
            $stmtApp->bind_param("i", $template_id);
            $stmtApp->execute();
            $resApp = $stmtApp->get_result();
            $rowApp = $resApp ? $resApp->fetch_assoc() : null;
            $template_app_id = (int)($rowApp['app_id'] ?? 0);
            $stmtApp->close();
        }

        $stmtQa = $conn->prepare("
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
                show_from_account,
                show_to_account,
                sort_order,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        if ($stmtQa) {
            $sort_order = 0;

            $stmtQa->bind_param(
                "isiiiiiidsiiiiiiiii",
                $template_app_id,
                $quick_add_name,
                $miner_id,
                $asset_id,
                $category_id,
                $referral_id,
                $from_account_id,
                $to_account_id,
                $amount_decimal,
                $notes,
                $show_miner,
                $show_asset,
                $show_category,
                $show_referral,
                $show_amount,
                $show_notes,
                $show_from_account,
                $show_to_account,
                $sort_order
            );

            if (!$stmtQa->execute()) {
                $error = "Template line added, but Quick Entry item was not created: " . $stmtQa->error;
            }
            $stmtQa->close();
        } else {
            $error = "Template line added, but Quick Entry item could not be prepared: " . $conn->error;
        }
    }

    if ($error === "") {
        $success = "Template line added.";
    }
} else {
    $error = "Could not add template line: " . $stmt->error;
    $stmt->close();
}
        }
    }
}

/* -----------------------------
   HANDLE DELETE TEMPLATE LINE
----------------------------- */

if (isset($_GET['delete_line'])) {
    $delete_line_id = (int)($_GET['delete_line'] ?? 0);

    if ($delete_line_id > 0) {
        $stmt = $conn->prepare("
            DELETE FROM template_items
            WHERE id = ? AND template_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $delete_line_id, $template_id);
            $stmt->execute();
            $stmt->close();

            header("Location: index.php?page=template_edit&id=" . $template_id);
            exit;
        } else {
            $error = "Could not delete template line: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD TEMPLATE HEADER
----------------------------- */

$template = null;

$stmt = $conn->prepare("
    SELECT id, app_id, template_name, notes
    FROM templates
    WHERE id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result ? $result->fetch_assoc() : null;
    $stmt->close();
} else {
    $error = "Could not load template: " . $conn->error;
}

if (!$template) {
    echo "<div class='card'><h2>Template not found</h2><p class='subtext'>This template does not exist.</p></div>";
    return;
}

/* -----------------------------
   LOAD TEMPLATE LINES
----------------------------- */

$lines = [];
$template_total = 0.0;

$stmt = $conn->prepare("
    SELECT
        ti.id,
        ti.template_id,
        ti.miner_id,
        ti.asset_id,
        ti.category_id,
        ti.referral_id,
        ti.from_account_id,
        ti.to_account_id,
        ti.amount,
        ti.notes,
        ti.show_miner,
        ti.show_asset,
        ti.show_category,
        ti.show_referral,
        ti.show_amount,
        ti.show_notes,
        ti.show_from_account,
        ti.show_to_account,
        ti.show_in_quick_add,
        ti.quick_add_name,
        m.miner_name,
        a.asset_name,
        a.asset_symbol,
        c.category_name,
        r.referral_name,
        fa.account_name AS from_account_name,
        ta.account_name AS to_account_name
    FROM template_items ti
    LEFT JOIN miners m ON m.id = ti.miner_id
    LEFT JOIN assets a ON a.id = ti.asset_id
    LEFT JOIN categories c ON c.id = ti.category_id
    LEFT JOIN referrals r ON r.id = ti.referral_id
    LEFT JOIN accounts fa ON fa.id = ti.from_account_id
    LEFT JOIN accounts ta ON ta.id = ti.to_account_id
    WHERE ti.template_id = ?
    ORDER BY ti.id ASC
");

if ($stmt) {
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lines[] = $row;
            $template_total += (float)$row['amount'];
        }
    }

    $stmt->close();
} else {
    $error = "Could not load template lines: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Edit Template #<?= (int)$template['id'] ?></h2>
    <p class="subtext">Build a reusable template for repeated reward entry.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Template Details</h3>

        <form method="post">
            <input type="hidden" name="action" value="save_template">

            <div class="form-row">
                <label for="app_id">App</label>
                <select id="app_id" name="app_id" required>
                    <option value="0">Select App</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= (int)$app['id'] ?>" <?= (int)$template['app_id'] === (int)$app['id'] ? 'selected' : '' ?>>
                            <?= h($app['app_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="template_name">Template Name</label>
                <input type="text" id="template_name" name="template_name" value="<?= h($template['template_name']) ?>" required>
            </div>

            <div class="form-row">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="4"><?= h($template['notes']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Template</button>
        </form>
    </div>

    <div class="card">
        <h3>Add Template Line</h3>

        <form method="post">
            <input type="hidden" name="action" value="add_line">

            <div class="form-row">
                <label for="miner_id">Miner</label>
                <select id="miner_id" name="miner_id">
                    <option value="0">None</option>
                    <?php foreach ($miners as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= h($m['miner_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="asset_id">Asset</label>
                <select id="asset_id" name="asset_id">
                    <option value="0">None</option>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?= (int)$a['id'] ?>">
                            <?= h($a['asset_name']) ?>
                            <?php if (trim((string)$a['asset_symbol']) !== ''): ?>
                                (<?= h($a['asset_symbol']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="0">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-app-id="<?= (int)$c['app_id'] ?>">
                            <?= h($c['category_name']) ?>
                            <?php if (trim((string)$c['behavior_type']) !== ''): ?>
                                (<?= h($c['behavior_type']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="referral_id">Referral</label>
                <select id="referral_id" name="referral_id">
                    <option value="0">None</option>
                    <?php foreach ($referrals as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= h($r['referral_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="from_account_id">From Account</label>
                <select id="from_account_id" name="from_account_id">
                    <option value="0">None</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= h($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="to_account_id">To Account</label>
                <select id="to_account_id" name="to_account_id">
                    <option value="0">None</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= h($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="amount">Amount</label>
                <input type="text" id="amount" name="amount" placeholder="Optional">
            </div>

            <div class="form-row">
                <label for="line_notes">Notes</label>
                <textarea id="line_notes" name="line_notes" rows="3"></textarea>
            </div>

            <div class="form-row">
                <label>Show This Field</label>
                <div>
                    <label><input type="checkbox" name="show_miner" checked> Miner</label><br>
                    <label><input type="checkbox" name="show_asset" checked> Asset</label><br>
                    <label><input type="checkbox" name="show_category" checked> Category</label><br>
                    <label><input type="checkbox" name="show_referral"> Referral</label><br>
                    <label><input type="checkbox" name="show_amount" checked> Amount</label><br>
                    <label><input type="checkbox" name="show_notes" checked> Notes</label><br>
                    <label><input type="checkbox" name="show_from_account"> From Account</label><br>
                    <label><input type="checkbox" name="show_to_account"> To Account</label>
                </div>
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="show_in_quick_add">
                    Add to Quick Entry
                </label>
            </div>

            <div class="form-row">
                <label for="quick_add_name">Quick Add Name</label>
                <input type="text" id="quick_add_name" name="quick_add_name" placeholder="Example: Referral Bonus">
            </div>

            <button type="submit" class="btn btn-primary">Add Line</button>
        </form>
    </div>
</div>

<div class="card mt-20">
    <h3>Template Line Items</h3>

    <p class="subtext">
        Total lines: <strong><?= count($lines) ?></strong>
        &nbsp; | &nbsp;
        Template total: <strong><?= h(number_format($template_total, 8, '.', ',')) ?></strong>
    </p>

    <?php if (!$lines): ?>
        <p class="subtext">No line items have been added to this template yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Miner</th>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Referral</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Quick Add</th>
                        <th style="width:140px;">Amount</th>
                        <th>Notes</th>
                        <th style="width:90px;">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td><?= (int)$line['id'] ?></td>
                            <td><?= h($line['miner_name'] ?? '') ?></td>
                            <td>
                                <?= h($line['asset_name'] ?? '') ?>
                                <?php if (!empty($line['asset_symbol'])): ?>
                                    (<?= h($line['asset_symbol']) ?>)
                                <?php endif; ?>
                            </td>
                            <td><?= h($line['category_name'] ?? '') ?></td>
                            <td><?= h($line['referral_name'] ?? '') ?></td>
                            <td><?= h($line['from_account_name'] ?? '') ?></td>
                            <td><?= h($line['to_account_name'] ?? '') ?></td>
                            <td>
                                <?php if ((int)$line['show_in_quick_add'] === 1): ?>
                                    <span class="badge badge-blue"><?= h($line['quick_add_name'] ?: 'Quick Add') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h(number_format((float)$line['amount'], 8, '.', ',')) ?></td>
                            <td><?= h($line['notes'] ?? '') ?></td>
                            <td>
                                <a class="table-link"
                                   href="index.php?page=template_edit&id=<?= (int)$template_id ?>&delete_line=<?= (int)$line['id'] ?>"
                                   onclick="return confirm('Delete this template line?');">
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
    const appSelect = document.getElementById("app_id");
    const categorySelect = document.getElementById("category_id");

    if (!appSelect || !categorySelect) return;

    function filterCategoriesByApp() {
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

    appSelect.addEventListener("change", filterCategoriesByApp);
    filterCategoriesByApp();
});
</script>