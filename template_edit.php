<?php

$current_page = 'templates';

$error = "";
$success = "";

$template_id = (int)($_GET['id'] ?? 0);
$from_context = trim((string)($_GET['from'] ?? $_POST['from'] ?? ''));
$from_settings_templates = ($from_context === 'settings_templates');
$back_href = $from_settings_templates ? 'index.php?page=quick_adds' : 'index.php?page=templates';

$ti_has_received_time = function_exists('rl_column_exists') ? rl_column_exists($conn, 'template_items', 'show_received_time') : false;
$ti_has_value_at_receipt = function_exists('rl_column_exists') ? rl_column_exists($conn, 'template_items', 'show_value_at_receipt') : false;

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
    ORDER BY sort_order ASC, id ASC
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
    $default_app_id = !empty($apps) ? (int)$apps[0]['id'] : 0;

    $stmt = $conn->prepare("
        INSERT INTO templates (app_id, template_name, notes)
        VALUES (?, 'New Template', '')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $default_app_id);
        $stmt->execute();
        $template_id = (int)$stmt->insert_id;
        $stmt->close();

        header("Location: index.php?page=template_edit&id=" . $template_id . ($from_settings_templates ? "&from=settings_templates" : ""));
        exit;
    } else {
        $error = "Could not create template: " . $conn->error;
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

            $template['app_id'] = $app_id;
            $template['template_name'] = $template_name;
            $template['notes'] = $notes;

            $success = "Template updated.";
        } else {
            $error = "Could not update template: " . $conn->error;
        }
    }
}

/* -----------------------------
   DEFAULT LINE FORM STATE
----------------------------- */

$line_form = [
    'id' => 0,
    'miner_id' => 0,
    'asset_id' => 0,
    'category_id' => 0,
    'referral_id' => 0,
    'from_account_id' => 0,
    'to_account_id' => 0,
    'amount' => '',
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
    'show_in_quick_add' => 0,
    'quick_add_name' => '',
    'is_multi_add' => 0,
    'sort_order' => 0,
];

$is_editing_line = false;

/* -----------------------------
   LOAD LINE ITEM FOR EDIT
----------------------------- */

if (isset($_GET['edit_line'])) {
    $edit_line_id = (int)($_GET['edit_line'] ?? 0);

    if ($edit_line_id > 0) {
        $stmt = $conn->prepare("
            SELECT *
            FROM template_items
            WHERE id = ? AND template_id = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $edit_line_id, $template_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                $line_form = $row;
                $is_editing_line = true;
            }
        }
    }
}

/* -----------------------------
   HANDLE ADD / EDIT TEMPLATE LINE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_line') {
    $line_id = (int)($_POST['line_id'] ?? 0);

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
    $show_received_time = isset($_POST['show_received_time']) ? 1 : 0;
    $show_value_at_receipt = isset($_POST['show_value_at_receipt']) ? 1 : 0;
    $show_from_account = isset($_POST['show_from_account']) ? 1 : 0;
    $show_to_account = isset($_POST['show_to_account']) ? 1 : 0;

    $show_in_quick_add = isset($_POST['show_in_quick_add']) ? 1 : 0;
    $quick_add_name = trim($_POST['quick_add_name'] ?? '');
    $is_multi_add = isset($_POST['is_multi_add']) ? 1 : 0;

    $line_form = [
        'id' => $line_id,
        'miner_id' => $miner_id,
        'asset_id' => $asset_id,
        'category_id' => $category_id,
        'referral_id' => $referral_id,
        'from_account_id' => $from_account_id,
        'to_account_id' => $to_account_id,
        'amount' => $amount,
        'notes' => $notes,
        'show_miner' => $show_miner,
        'show_asset' => $show_asset,
        'show_category' => $show_category,
        'show_referral' => $show_referral,
        'show_amount' => $show_amount,
        'show_notes' => $show_notes,
        'show_received_time' => $show_received_time,
        'show_value_at_receipt' => $show_value_at_receipt,
        'show_from_account' => $show_from_account,
        'show_to_account' => $show_to_account,
        'show_in_quick_add' => $show_in_quick_add,
        'quick_add_name' => $quick_add_name,
        'is_multi_add' => $is_multi_add,
        'sort_order' => 0,
    ];
    $is_editing_line = ($line_id > 0);

    if ($category_id <= 0) {
        $error = "Please select a category.";
    } elseif ($amount !== '' && !is_numeric($amount)) {
        $error = "Please enter a valid amount.";
    } elseif ($show_in_quick_add === 1 && $quick_add_name === '') {
        $error = "Please enter a Quick Add name.";
    } else {
        $amount_decimal = ($amount === '') ? 0 : (float)$amount;

        $old_line = null;
        if ($line_id > 0) {
            $stmtOld = $conn->prepare("
                SELECT show_in_quick_add, quick_add_name, is_multi_add
                FROM template_items
                WHERE id = ? AND template_id = ?
                LIMIT 1
            ");
            if ($stmtOld) {
                $stmtOld->bind_param("ii", $line_id, $template_id);
                $stmtOld->execute();
                $resOld = $stmtOld->get_result();
                $old_line = $resOld ? $resOld->fetch_assoc() : null;
                $stmtOld->close();
            }
        }

        if ($line_id > 0) {
            $stmt = $conn->prepare("
                UPDATE template_items
                SET
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
                    show_in_quick_add = ?,
                    quick_add_name = ?,
                    is_multi_add = ?
                WHERE id = ? AND template_id = ?
            ");

            if (!$stmt) {
                $error = "Could not update template line: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "iiiiiidsiiiiiiiiiiisiii",
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
                    $show_received_time,
                    $show_value_at_receipt,
                    $show_from_account,
                    $show_to_account,
                    $show_in_quick_add,
                    $quick_add_name,
                    $is_multi_add,
                    $line_id,
                    $template_id
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    $template_app_id = (int)$template['app_id'];

                    if ($old_line && (int)$old_line['show_in_quick_add'] === 1 && trim((string)$old_line['quick_add_name']) !== '') {
                        $old_name = trim((string)$old_line['quick_add_name']);
                        $old_multi = (int)($old_line['is_multi_add'] ?? 0);

                        $stmtQaDel = $conn->prepare("
                            DELETE FROM quick_add_items
                            WHERE app_id = ?
                              AND quick_add_name = ?
                              AND is_multi_add = ?
                            LIMIT 1
                        ");
                        if ($stmtQaDel) {
                            $stmtQaDel->bind_param("isi", $template_app_id, $old_name, $old_multi);
                            $stmtQaDel->execute();
                            $stmtQaDel->close();
                        }
                    }

                    if ($show_in_quick_add === 1) {
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
                                " . ($ti_has_received_time ? "show_received_time,
                                " : "") . "
                                " . ($ti_has_value_at_receipt ? "show_value_at_receipt,
                                " : "") . "
                                show_from_account,
                                show_to_account,
                                is_multi_add,
                                sort_order,
                                is_active
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        if ($stmtQa) {
                            $sort_order = 0;
                            $is_active = 1;

                            $stmtQa->bind_param(
                                "isiiiiiidsiiiiiiiiiiiii",
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
                                $show_received_time,
                                $show_value_at_receipt,
                                $show_from_account,
                                $show_to_account,
                                $is_multi_add,
                                $sort_order,
                                $is_active
                            );

                            if (!$stmtQa->execute()) {
                                $error = "Template line updated, but Quick Entry item was not synced: " . $stmtQa->error;
                            }
                            $stmtQa->close();
                        } else {
                            $error = "Template line updated, but Quick Entry item could not be prepared: " . $conn->error;
                        }
                    }

                    if ($error === '') {
                        header("Location: index.php?page=template_edit&id=" . $template_id . "&updated_line=1");
                        exit;
                    }
                } else {
                    $error = "Could not update template line: " . $stmt->error;
                    $stmt->close();
                }
            }
        } else {
            $sort_order = 0;
            $resCount = $conn->query("SELECT COUNT(*) AS c FROM template_items WHERE template_id = " . (int)$template_id);
            if ($resCount) {
                $rowCount = $resCount->fetch_assoc();
                $sort_order = (int)($rowCount['c'] ?? 0);
            }

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
                    show_received_time,
                    show_value_at_receipt,
                    show_from_account,
                    show_to_account,
                    show_in_quick_add,
                    quick_add_name,
                    is_multi_add,
                    sort_order
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $error = "Could not add template line: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "iiiiiiidsiiiiiiiiiiisii",
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
                    $show_received_time,
                    $show_value_at_receipt,
                    $show_from_account,
                    $show_to_account,
                    $show_in_quick_add,
                    $quick_add_name,
                    $is_multi_add,
                    $sort_order
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    if ($show_in_quick_add === 1) {
                        $template_app_id = (int)$template['app_id'];

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

                        if ($stmtQa) {
                            $qa_sort_order = 0;
                            $qa_active = 1;

                            $stmtQa->bind_param(
                                "isiiiiiidsiiiiiiiiiiiii",
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
                                $show_received_time,
                                $show_value_at_receipt,
                                $show_from_account,
                                $show_to_account,
                                $is_multi_add,
                                $qa_sort_order,
                                $qa_active
                            );

                            if (!$stmtQa->execute()) {
                                $error = "Template line added, but Quick Entry item was not created: " . $stmtQa->error;
                            }
                            $stmtQa->close();
                        } else {
                            $error = "Template line added, but Quick Entry item could not be prepared: " . $conn->error;
                        }
                    }

                    if ($error === '') {
                        header("Location: index.php?page=template_edit&id=" . $template_id . "&added_line=1");
                        exit;
                    }
                } else {
                    $error = "Could not add template line: " . $stmt->error;
                    $stmt->close();
                }
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
        $stmtInfo = $conn->prepare("
            SELECT show_in_quick_add, quick_add_name, is_multi_add
            FROM template_items
            WHERE id = ? AND template_id = ?
            LIMIT 1
        ");

        $lineInfo = null;
        if ($stmtInfo) {
            $stmtInfo->bind_param("ii", $delete_line_id, $template_id);
            $stmtInfo->execute();
            $resInfo = $stmtInfo->get_result();
            $lineInfo = $resInfo ? $resInfo->fetch_assoc() : null;
            $stmtInfo->close();
        }

        $stmt = $conn->prepare("
            DELETE FROM template_items
            WHERE id = ? AND template_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $delete_line_id, $template_id);
            $stmt->execute();
            $stmt->close();

            if ($lineInfo && (int)$lineInfo['show_in_quick_add'] === 1) {
                $quickAddName = trim((string)($lineInfo['quick_add_name'] ?? ''));
                $isMulti = (int)($lineInfo['is_multi_add'] ?? 0);

                if ($quickAddName !== '') {
                    $stmtQa = $conn->prepare("
                        DELETE FROM quick_add_items
                        WHERE app_id = ?
                          AND quick_add_name = ?
                          AND is_multi_add = ?
                        LIMIT 1
                    ");

                    if ($stmtQa) {
                        $template_app_id = (int)$template['app_id'];
                        $stmtQa->bind_param("isi", $template_app_id, $quickAddName, $isMulti);
                        $stmtQa->execute();
                        $stmtQa->close();
                    }
                }
            }

            header("Location: index.php?page=template_edit&id=" . $template_id . "&deleted_line=1");
            exit;
        } else {
            $error = "Could not delete template line: " . $conn->error;
        }
    }
}

/* -----------------------------
   FLASH AFTER REDIRECTS
----------------------------- */

if (isset($_GET['added_line'])) {
    $success = "Template line added.";
}
if (isset($_GET['updated_line'])) {
    $success = "Template line updated.";
}
if (isset($_GET['deleted_line'])) {
    $success = "Template line deleted.";
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
        " . ($ti_has_received_time ? "ti.show_received_time" : "1") . " AS show_received_time,
        " . ($ti_has_value_at_receipt ? "ti.show_value_at_receipt" : "1") . " AS show_value_at_receipt,
        ti.show_from_account,
        ti.show_to_account,
        ti.show_in_quick_add,
        ti.quick_add_name,
        ti.is_multi_add,
        ti.sort_order,
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
    ORDER BY ti.sort_order ASC, ti.id ASC
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
            <input type="hidden" name="from" value="<?= $from_settings_templates ? 'settings_templates' : '' ?>">
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
        <h3><?= $is_editing_line ? 'Edit Template Line' : 'Add Template Line' ?></h3>

        <form method="post">
            <input type="hidden" name="action" value="save_line">
            <input type="hidden" name="line_id" value="<?= (int)$line_form['id'] ?>">

            <div class="form-row">
                <label for="miner_id">Miner</label>
                <select id="miner_id" name="miner_id">
                    <option value="0">None</option>
                    <?php foreach ($miners as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= (int)$line_form['miner_id'] === (int)$m['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$line_form['asset_id'] === (int)$a['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$c['id'] ?>" data-app-id="<?= (int)$c['app_id'] ?>" <?= (int)$line_form['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$r['id'] ?>" <?= (int)$line_form['referral_id'] === (int)$r['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$line_form['from_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$line_form['to_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= h($a['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="amount">
                    Amount
                    <span style="margin-left:12px; font-weight:normal;">
                        <input type="checkbox" name="is_multi_add" value="1" <?= !empty($line_form['is_multi_add']) ? 'checked' : '' ?>>
                        Allow multiple inputs
                    </span>
                </label>
                <input type="text" id="amount" name="amount" value="<?= h((string)$line_form['amount']) ?>" placeholder="Optional">
            </div>

            <div class="form-row">
                <label for="line_notes">Notes</label>
                <textarea id="line_notes" name="line_notes" rows="3"><?= h($line_form['notes']) ?></textarea>
            </div>

            <div class="form-row">
                <label>Show This Field</label>
                <div>
                    <label><input type="checkbox" name="show_miner" <?= !empty($line_form['show_miner']) ? 'checked' : '' ?>> Miner</label><br>
                    <label><input type="checkbox" name="show_asset" <?= !empty($line_form['show_asset']) ? 'checked' : '' ?>> Asset</label><br>
                    <label><input type="checkbox" name="show_category" <?= !empty($line_form['show_category']) ? 'checked' : '' ?>> Category</label><br>
                    <label><input type="checkbox" name="show_referral" <?= !empty($line_form['show_referral']) ? 'checked' : '' ?>> Referral</label><br>
                    <label><input type="checkbox" name="show_amount" <?= !empty($line_form['show_amount']) ? 'checked' : '' ?>> Amount</label><br>
                    <label><input type="checkbox" name="show_notes" <?= !empty($line_form['show_notes']) ? 'checked' : '' ?>> Notes</label><br>
                    <label><input type="checkbox" name="show_received_time" <?= !empty($line_form['show_received_time']) ? 'checked' : '' ?>> Time Received</label><br>
                    <label><input type="checkbox" name="show_value_at_receipt" <?= !empty($line_form['show_value_at_receipt']) ? 'checked' : '' ?>> Value at Receipt</label><br>
                    <label><input type="checkbox" name="show_from_account" <?= !empty($line_form['show_from_account']) ? 'checked' : '' ?>> From Account</label><br>
                    <label><input type="checkbox" name="show_to_account" <?= !empty($line_form['show_to_account']) ? 'checked' : '' ?>> To Account</label>
                </div>
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="show_in_quick_add" <?= !empty($line_form['show_in_quick_add']) ? 'checked' : '' ?>>
                    Add to Quick Entry
                </label>
            </div>

            <div class="form-row">
                <label for="quick_add_name">Quick Add Name</label>
                <input type="text" id="quick_add_name" name="quick_add_name" value="<?= h($line_form['quick_add_name']) ?>" placeholder="Example: Referral Bonus">
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $is_editing_line ? 'Save Line Changes' : 'Add Line' ?>
            </button>

            <?php if ($is_editing_line): ?>
                <a class="btn btn-secondary" href="index.php?page=template_edit&id=<?= (int)$template_id ?>">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card mt-20">
    <h3>Template Line Items</h3>

    <p class="subtext">
        Total lines: <strong><?= count($lines) ?></strong>
        &nbsp; | &nbsp;
        Template total: <strong><?= h(rtrim(rtrim(number_format($template_total, 8, '.', ','), '0'), '.')) ?></strong>
    </p>

    <?php if (!$lines): ?>
        <p class="subtext">No line items have been added to this template yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:70px;">ID</th>
                        <th>Miner</th>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Referral</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Quick Add</th>
                        <th style="width:80px;">Multi</th>
                        <th style="width:140px;">Amount</th>
                        <th>Notes</th>
                        <th style="width:90px;">Edit</th>
                        <th style="width:90px;">Delete</th>
                    </tr>
                </thead>
                <tbody id="template-line-sortable">
                    <?php foreach ($lines as $line): ?>
                        <tr data-id="<?= (int)$line['id'] ?>">
                            <td class="drag-handle">☰</td>
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
                            <td>
                                <?php if ((int)($line['is_multi_add'] ?? 0) === 1): ?>
                                    <span class="badge badge-green">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h(rtrim(rtrim(number_format((float)$line['amount'], 8, '.', ','), '0'), '.')) ?></td>
                            <td><?= h($line['notes'] ?? '') ?></td>
                            <td>
                                <a class="table-link"
                                   href="index.php?page=template_edit&id=<?= (int)$template_id ?>&edit_line=<?= (int)$line['id'] ?>">
                                    Edit
                                </a>
                            </td>
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

    const el = document.getElementById('template-line-sortable');
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
                    sort_order: index
                });
            });

            fetch('template_lines_reorder.php', {
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