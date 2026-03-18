<?php

$error = "";
$success = "";

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');
    $account_identifier = trim($_POST['account_identifier'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($account_name === '') {
        $error = "Account name is required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE accounts
                SET account_name = ?, account_type = ?, account_identifier = ?, notes = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssii", $account_name, $account_type, $account_identifier, $notes, $is_active, $id);
            $stmt->execute();
            $stmt->close();

            $success = "Account updated.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO accounts (account_name, account_type, account_identifier, notes, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssi", $account_name, $account_type, $account_identifier, $notes, $is_active);
            $stmt->execute();
            $stmt->close();

            $success = "Account added.";
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
$edit = [
    'id' => 0,
    'account_name' => '',
    'account_type' => '',
    'account_identifier' => '',
    'notes' => '',
    'is_active' => 1,
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, account_name, account_type, account_identifier, notes, is_active
            FROM accounts
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

/* -----------------------------
   LOAD LIST
----------------------------- */
$accounts = [];
$result = $conn->query("
    SELECT id, account_name, account_type, account_identifier, notes, is_active
    FROM accounts
    ORDER BY is_active DESC, account_name ASC, id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
} else {
    $error = "Could not load accounts: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Accounts</h2>
    <p class="subtext">Manage wallets, exchanges, and other payout destinations.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= (int)$edit['id'] > 0 ? 'Edit Account' : 'Add Account' ?></h3>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="account_name">Account Name</label>
                <input
                    type="text"
                    id="account_name"
                    name="account_name"
                    value="<?= h($edit['account_name']) ?>"
                    maxlength="120"
                    required
                >
            </div>

            <div class="form-row">
                <label for="account_type">Account Type</label>
                <input
                    type="text"
                    id="account_type"
                    name="account_type"
                    value="<?= h($edit['account_type']) ?>"
                    maxlength="50"
                    placeholder="Wallet, Exchange, Bank, etc."
                >
            </div>

            <div class="form-row">
                <label for="account_identifier">Identifier / Address</label>
                <input
                    type="text"
                    id="account_identifier"
                    name="account_identifier"
                    value="<?= h($edit['account_identifier']) ?>"
                    maxlength="255"
                    placeholder="Wallet address, username, account reference..."
                >
            </div>

            <div class="form-row">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="4"><?= h($edit['notes']) ?></textarea>
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="is_active" <?= !empty($edit['is_active']) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Account</button>
        </form>
    </div>

    <div class="card">
        <h3>Accounts List</h3>

        <?php if (!$accounts): ?>
            <p class="subtext">No accounts saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Name</th>
                            <th style="width:140px;">Type</th>
                            <th>Identifier</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td><?= (int)$a['id'] ?></td>
                                <td><?= h($a['account_name']) ?></td>
                                <td><?= h($a['account_type']) ?></td>
                                <td><?= h($a['account_identifier']) ?></td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=accounts&edit=<?= (int)$a['id'] ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>