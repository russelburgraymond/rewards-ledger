<?php

$current_page = 'templates';

$error = "";
$success = "";

$template_id = (int)($_GET['id'] ?? 0);

if ($template_id <= 0) {
    echo "<div class='card'><h2>Template not found</h2><p class='subtext'>A valid template ID was not provided.</p></div>";
    return;
}

/* -----------------------------
   LOAD DROPDOWNS
----------------------------- */

$miners = [];
$assets = [];
$categories = [];
$accounts = [];
$referrals = [];

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
   LOAD TEMPLATE HEADER
----------------------------- */

$template = null;

$stmt = $conn->prepare("
    SELECT
        t.id,
        t.app_id,
        t.template_name,
        t.notes,
        a.app_name
    FROM templates t
    LEFT JOIN apps a ON a.id = t.app_id
    WHERE t.id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$template) {
    echo "<div class='card'><h2>Template not found</h2><p class='subtext'>This template does not exist.</p></div>";
    return;
}

/* -----------------------------
   LOAD TEMPLATE LINE ITEMS
----------------------------- */

$lines = [];

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
        }
    }

    $stmt->close();
} else {
    $error = "Could not load template lines: " . $conn->error;
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_template_use') {
    $batch_date = trim($_POST['batch_date'] ?? date('Y-m-d'));
    $title = trim($_POST['title'] ?? $template['template_name']);
    $notes = trim($_POST['notes'] ?? $template['notes']);

    if ($batch_date === '') {
        $error = "Batch date is required.";
    }

    $batch_id = 0;

    if ($error === "") {
        $stmt = $conn->prepare("
            INSERT INTO batches (app_id, batch_date, title, notes)
            VALUES (?, ?, ?, ?)
        ");

        if ($stmt) {
            $app_id = (int)($template['app_id'] ?? 0);
            $stmt->bind_param("isss", $app_id, $batch_date, $title, $notes);
            $stmt->execute();
            $batch_id = (int)$stmt->insert_id;
            $stmt->close();
        } else {
            $error = "Could not create batch: " . $conn->error;
        }
    }

    if ($error === "" && isset($_POST['line_template_id']) && is_array($_POST['line_template_id'])) {
        $miner_ids = $_POST['miner_id'] ?? [];
        $asset_ids = $_POST['asset_id'] ?? [];
        $category_ids = $_POST['category_id'] ?? [];
        $referral_ids = $_POST['referral_id'] ?? [];
        $from_account_ids = $_POST['from_account_id'] ?? [];
        $to_account_ids = $_POST['to_account_id'] ?? [];
        $amounts = $_POST['amount'] ?? [];
        $line_notes = $_POST['line_notes'] ?? [];

        foreach ($_POST['line_template_id'] as $idx => $template_line_id) {
            $miner_id = (int)($miner_ids[$idx] ?? 0);
            $asset_id = (int)($asset_ids[$idx] ?? 0);
            $category_id = (int)($category_ids[$idx] ?? 0);
            $referral_id = (int)($referral_ids[$idx] ?? 0);
            $from_account_id = (int)($from_account_ids[$idx] ?? 0);
            $to_account_id = (int)($to_account_ids[$idx] ?? 0);
            $amount_raw = trim((string)($amounts[$idx] ?? '0'));
            $notes_value = trim((string)($line_notes[$idx] ?? ''));

            if ($amount_raw === '' || !is_numeric($amount_raw)) {
                $amount = 0;
            } else {
                $amount = (float)$amount_raw;
            }

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

            if (!$stmt) {
                $error = "Could not save batch items: " . $conn->error;
                break;
            }

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
                $notes_value
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($error === "") {
        header("Location: index.php?page=dashboard");
        exit;
    }
}
?>

<div class="page-head">
    <h2>Use Template: <?= h($template['template_name']) ?></h2>
    <p class="subtext">
        App:
        <strong><?= h($template['app_name'] ?? 'Unassigned') ?></strong><br>
        Enter your data using this template. Saving will create a new batch automatically.
    </p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <?php if (!$lines): ?>
        <p class="subtext">This template has no line items yet.</p>
        <p>
            <a class="btn btn-primary" href="index.php?page=template_edit&id=<?= (int)$template_id ?>">
                Edit Template
            </a>
        </p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="save_template_use">

            <div class="grid-2">
                <div class="form-row">
                    <label for="batch_date">Date</label>
                    <input
                        type="date"
                        id="batch_date"
                        name="batch_date"
                        value="<?= h($_POST['batch_date'] ?? date('Y-m-d')) ?>"
                        required
                    >
                </div>

                <div class="form-row">
                    <label for="title">Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= h($_POST['title'] ?? $template['template_name']) ?>"
                    >
                </div>
            </div>

            <div class="form-row">
                <label for="notes">Batch Notes</label>
                <textarea id="notes" name="notes" rows="3"><?= h($_POST['notes'] ?? $template['notes']) ?></textarea>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Miner</th>
                            <th>Asset</th>
                            <th>Category</th>
                            <th>Referral</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $index => $line): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="line_template_id[]" value="<?= (int)$line['id'] ?>">

                                    <?php if ((int)$line['show_miner'] === 1): ?>
                                        <select name="miner_id[]">
                                            <option value="0">None</option>
                                            <?php foreach ($miners as $m): ?>
                                                <option value="<?= (int)$m['id'] ?>" <?= (int)$line['miner_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                                    <?= h($m['miner_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['miner_name'] ?? '') ?>
                                        <input type="hidden" name="miner_id[]" value="<?= (int)$line['miner_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_asset'] === 1): ?>
                                        <select name="asset_id[]">
                                            <option value="0">None</option>
                                            <?php foreach ($assets as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>" <?= (int)$line['asset_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                                    <?= h($a['asset_name']) ?>
                                                    <?php if (trim((string)$a['asset_symbol']) !== ''): ?>
                                                        (<?= h($a['asset_symbol']) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['asset_name'] ?? '') ?>
                                        <input type="hidden" name="asset_id[]" value="<?= (int)$line['asset_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_category'] === 1): ?>
                                        <select name="category_id[]">
                                            <option value="0">Select Category</option>
                                            <?php foreach ($categories as $c): ?>
                                                <?php if ((int)$c['app_id'] !== (int)$template['app_id']) continue; ?>
                                                <option value="<?= (int)$c['id'] ?>" <?= (int)$line['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                                    <?= h($c['category_name']) ?>
                                                    <?php if (trim((string)$c['behavior_type']) !== ''): ?>
                                                        (<?= h($c['behavior_type']) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['category_name'] ?? '') ?>
                                        <input type="hidden" name="category_id[]" value="<?= (int)$line['category_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_referral'] === 1): ?>
                                        <select name="referral_id[]">
                                            <option value="0">None</option>
                                            <?php foreach ($referrals as $r): ?>
                                                <option value="<?= (int)$r['id'] ?>" <?= (int)$line['referral_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                                                    <?= h($r['referral_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['referral_name'] ?? '') ?>
                                        <input type="hidden" name="referral_id[]" value="<?= (int)$line['referral_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_from_account'] === 1): ?>
                                        <select name="from_account_id[]">
                                            <option value="0">None</option>
                                            <?php foreach ($accounts as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>" <?= (int)$line['from_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                                    <?= h($a['account_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['from_account_name'] ?? '') ?>
                                        <input type="hidden" name="from_account_id[]" value="<?= (int)$line['from_account_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_to_account'] === 1): ?>
                                        <select name="to_account_id[]">
                                            <option value="0">None</option>
                                            <?php foreach ($accounts as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>" <?= (int)$line['to_account_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                                                    <?= h($a['account_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?= h($line['to_account_name'] ?? '') ?>
                                        <input type="hidden" name="to_account_id[]" value="<?= (int)$line['to_account_id'] ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_amount'] === 1): ?>
                                        <input
                                            type="text"
                                            name="amount[]"
                                            value="<?= h($_POST['amount'][$index] ?? (string)$line['amount']) ?>"
                                            placeholder="Optional"
                                        >
                                    <?php else: ?>
                                        <?= h((string)$line['amount']) ?>
                                        <input type="hidden" name="amount[]" value="<?= h((string)$line['amount']) ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)$line['show_notes'] === 1): ?>
                                        <input
                                            type="text"
                                            name="line_notes[]"
                                            value="<?= h($_POST['line_notes'][$index] ?? ($line['notes'] ?? '')) ?>"
                                        >
                                    <?php else: ?>
                                        <?= h($line['notes'] ?? '') ?>
                                        <input type="hidden" name="line_notes[]" value="<?= h($line['notes'] ?? '') ?>">
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Save Template Entry</button>
                <a class="btn btn-secondary" href="index.php?page=template_edit&id=<?= (int)$template_id ?>">Edit Template</a>
            </div>
        </form>
    <?php endif; ?>
</div>