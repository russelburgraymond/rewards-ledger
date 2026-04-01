<?php

$error = "";
$success = "";

// ======================================================
// [SETTINGS] HANDLE REFERRAL SAVE
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $referral_name = trim($_POST['referral_name'] ?? '');
    $referral_identifier = trim($_POST['referral_identifier'] ?? '');
    $account_id = (int)($_POST['account_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($referral_name === '') {
        $error = "Referral name is required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE referrals
                SET referral_name = ?, referral_identifier = ?, account_id = ?, notes = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssisii",
                $referral_name,
                $referral_identifier,
                $account_id,
                $notes,
                $is_active,
                $id
            );
            $stmt->execute();
            $stmt->close();

            $success = "Referral updated.";
        } else {
            // New referrals are added to the end of the current sort order list
            $next_sort_order = 1;
            $next_sort_result = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM referrals");
            if ($next_sort_result && ($next_sort_row = $next_sort_result->fetch_assoc())) {
                $next_sort_order = (int)($next_sort_row['next_sort_order'] ?? 1);
            }

            $stmt = $conn->prepare("
                INSERT INTO referrals (referral_name, referral_identifier, account_id, notes, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssisii",
                $referral_name,
                $referral_identifier,
                $account_id,
                $notes,
                $is_active,
                $next_sort_order
            );
            $stmt->execute();
            $stmt->close();

            $success = "Referral added.";
        }
    }
}

// ======================================================
// [SETTINGS] LOAD EDIT RECORD
// ======================================================
$edit = [
    'id' => 0,
    'referral_name' => '',
    'referral_identifier' => '',
    'account_id' => 0,
    'notes' => '',
    'is_active' => 1
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, referral_name, referral_identifier, account_id, notes, is_active
            FROM referrals
            WHERE id = ?
        ");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $edit = $row;
        }
    }
}

// ======================================================
// [SETTINGS] LOAD ACCOUNT OPTIONS
// ======================================================
$accounts = [];
$res = $conn->query("
    SELECT id, account_name
    FROM accounts
    WHERE is_active = 1
    ORDER BY sort_order ASC, account_name ASC, id ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
}

// ======================================================
// [SETTINGS] LOAD REFERRALS
// ======================================================
$referrals = [];

$result = $conn->query("
    SELECT r.*, a.account_name
    FROM referrals r
    LEFT JOIN accounts a ON r.account_id = a.id
    ORDER BY r.sort_order ASC, r.id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $referrals[] = $row;
    }
} else {
    $error = "Referral query failed: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Referrals</h2>
    <p class="subtext">Manage referral sources and connect them to accounts when needed.</p>
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= $edit['id'] ? "Edit Referral" : "Add Referral" ?></h3>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="referral_name">Referral Name</label>
                <input type="text" id="referral_name" name="referral_name" value="<?= h($edit['referral_name']) ?>" required>
            </div>

            <div class="form-row">
                <label for="referral_identifier">Identifier</label>
                <input type="text" id="referral_identifier" name="referral_identifier" value="<?= h($edit['referral_identifier']) ?>">
            </div>

            <div class="form-row">
                <label for="account_id">Account</label>
                <select id="account_id" name="account_id">
                    <option value="0">None</option>

                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$edit['account_id'] === (int)$a['id'] ? "selected" : "" ?>>
                            <?= h($a['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes"><?= h($edit['notes']) ?></textarea>
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="is_active" <?= $edit['is_active'] ? "checked" : "" ?>>
                    Active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Referral</button>
        </form>
    </div>

    <div class="card">
        <h3>Referral List</h3>
        <p class="subtext">Drag and drop rows to reorder referrals. New referrals are added to the end of the list automatically.</p>

        <?php if (!$referrals): ?>
            <p class="subtext">No referrals saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th style="width:70px;">ID</th>
                            <th>Name</th>
                            <th>Identifier</th>
                            <th>Account</th>
                            <th style="width:80px;">Sort</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                        </tr>
                    </thead>
                    <tbody id="referral-sortable">
                        <?php foreach ($referrals as $r): ?>
                            <tr data-id="<?= (int)$r['id'] ?>">
                                <td class="drag-handle" style="cursor:grab; text-align:center;">☰</td>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= h($r['referral_name']) ?></td>
                                <td><?= h($r['referral_identifier']) ?></td>
                                <td><?= h($r['account_name'] ?? '') ?></td>
                                <td class="referral-sort-order"><?= (int)$r['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$r['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=referrals&edit=<?= (int)$r['id'] ?>">Edit</a>
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
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('referral-sortable');

    if (!el || typeof Sortable === 'undefined') {
        return;
    }

    new Sortable(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            const rows = el.querySelectorAll('tr');
            const order = [];

            rows.forEach((row, index) => {
                const sortOrder = index + 1;
                const sortCell = row.querySelector('.referral-sort-order');

                if (sortCell) {
                    sortCell.textContent = sortOrder;
                }

                order.push({
                    id: row.dataset.id,
                    sort_order: sortOrder
                });
            });

            fetch('referrals_reorder.php', {
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
