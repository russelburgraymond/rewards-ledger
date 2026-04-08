<?php

$current_page = 'assets';

$error = "";
$success = "";

/* -----------------------------
   Flash messages
----------------------------- */
if (isset($_GET['added'])) {
    $success = "Asset added successfully.";
}

if (isset($_GET['updated'])) {
    $success = "Asset updated successfully.";
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $asset_name = trim($_POST['asset_name'] ?? '');
    $asset_symbol = strtoupper(trim($_POST['asset_symbol'] ?? ''));
    $currency_symbol = trim($_POST['currency_symbol'] ?? '');
    $display_decimals = (int)($_POST['display_decimals'] ?? 8);
    $is_fiat = isset($_POST['is_fiat']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $display_decimals = max(0, min(8, $display_decimals));

    if ($asset_name === '') {
        $error = "Asset name is required.";
    } elseif ($asset_symbol === '') {
        $error = "Asset code is required.";
    } else {
        $duplicate_by_symbol = rl_find_duplicate_id($conn, 'assets', 'asset_symbol', $asset_symbol, $id);
        $duplicate_by_name = rl_find_duplicate_id($conn, 'assets', 'asset_name', $asset_name, $id);

        if ($duplicate_by_symbol > 0) {
            $error = "An asset with that code already exists.";
        } elseif ($duplicate_by_name > 0) {
            $error = "An asset with that name already exists.";
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE assets
                    SET
                        asset_name = ?,
                        asset_symbol = ?,
                        currency_symbol = ?,
                        display_decimals = ?,
                        is_fiat = ?,
                        is_active = ?
                    WHERE id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssiiii",
                        $asset_name,
                        $asset_symbol,
                        $currency_symbol,
                        $display_decimals,
                        $is_fiat,
                        $is_active,
                        $id
                    );
                    $stmt->execute();
                    $stmt->close();

                    header("Location: index.php?page=settings&tab=assets&updated=1");
                    exit;
                } else {
                    $error = "Could not update asset: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO assets (
                        asset_name,
                        asset_symbol,
                        currency_symbol,
                        display_decimals,
                        is_fiat,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssiii",
                        $asset_name,
                        $asset_symbol,
                        $currency_symbol,
                        $display_decimals,
                        $is_fiat,
                        $is_active
                    );
                    $stmt->execute();
                    $stmt->close();

                    header("Location: index.php?page=settings&tab=assets&added=1");
                    exit;
                } else {
                    $error = "Could not add asset: " . $conn->error;
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
    'asset_name' => '',
    'asset_symbol' => '',
    'currency_symbol' => '',
    'display_decimals' => 8,
    'is_fiat' => 0,
    'is_active' => 1
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT
                id,
                asset_name,
                asset_symbol,
                currency_symbol,
                display_decimals,
                is_fiat,
                is_active
            FROM assets
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
            $error = "Could not load asset for editing: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD ASSET LIST
----------------------------- */
$assets = [];

$result = $conn->query("
    SELECT
        id,
        asset_name,
        asset_symbol,
        currency_symbol,
        display_decimals,
        is_fiat,
        is_active,
        sort_order
    FROM assets
    ORDER BY sort_order ASC, asset_name ASC, id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assets[] = $row;
    }
} else {
    $error = "Could not load assets: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Assets</h2>
    <p class="subtext">Manage crypto and fiat assets, including display symbols and decimal formatting.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= (int)$edit['id'] > 0 ? 'Edit Asset' : 'Add Asset' ?></h3>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="asset_name">Asset Name</label>
                <input
                    type="text"
                    id="asset_name"
                    name="asset_name"
                    value="<?= h($edit['asset_name']) ?>"
                    maxlength="120"
                    required
                >
            </div>

            <div class="form-row">
                <label for="asset_symbol">Asset Code</label>
                <input
                    type="text"
                    id="asset_symbol"
                    name="asset_symbol"
                    value="<?= h($edit['asset_symbol']) ?>"
                    maxlength="40"
                    required
                >
            </div>

            <div class="form-row">
                <label for="currency_symbol">Currency Symbol</label>
                <input
                    type="text"
                    id="currency_symbol"
                    name="currency_symbol"
                    value="<?= h($edit['currency_symbol']) ?>"
                    maxlength="10"
                    placeholder="Example: $, €, £, A$"
                >
            </div>

            <div class="form-row">
                <label for="display_decimals">Display Decimals</label>
                <input
                    type="number"
                    id="display_decimals"
                    name="display_decimals"
                    min="0"
                    max="8"
                    value="<?= (int)$edit['display_decimals'] ?>"
                >
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="is_fiat" <?= !empty($edit['is_fiat']) ? 'checked' : '' ?>>
                    Fiat Currency
                </label>
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="is_active" <?= !empty($edit['is_active']) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= (int)$edit['id'] > 0 ? 'Save Asset' : 'Add Asset' ?>
            </button>

            <?php if ((int)$edit['id'] > 0): ?>
                <a class="btn btn-secondary" href="index.php?page=settings&tab=assets">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>Asset List</h3>

        <?php if (!$assets): ?>
            <p class="subtext">No assets saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
					
                    <thead>
                        <tr>
							<th style="width:40px;"></th>
                            <th style="width:70px;">ID</th>
                            <th>Name</th>
                            <th style="width:90px;">Code</th>
                            <th style="width:100px;">Symbol</th>
                            <th style="width:90px;">Decimals</th>
                            <th style="width:80px;">Sort</th>
                            <th style="width:80px;">Fiat</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                        </tr>
                    </thead>
                    <tbody id="asset-sortable">
                        <?php foreach ($assets as $a): ?>
                            <tr data-id="<?= (int)$a['id'] ?>">
								<td class="drag-handle" style="cursor:grab; text-align:center;">☰</td>
                                <td><?= (int)$a['id'] ?></td>
                                <td><?= h($a['asset_name']) ?></td>
                                <td><?= h($a['asset_symbol']) ?></td>
                                <td><?= h($a['currency_symbol']) ?></td>
                                <td><?= (int)$a['display_decimals'] ?></td>
                                <td class="asset-sort-order"><?= (int)$a['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$a['is_fiat'] === 1): ?>
                                        <span class="badge badge-green">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=assets&edit=<?= (int)$a['id'] ?>">Edit</a>
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
    const el = document.getElementById('asset-sortable');

    if (!el) return;

    new Sortable(el, {
        animation: 150,
        ghostClass: 'dragging',
		handle: '.drag-handle',

        onEnd: function () {
            const rows = el.querySelectorAll('tr');
            const order = [];

            rows.forEach((row, index) => {
                const sortOrder = index + 1;
                const sortCell = row.querySelector('.asset-sort-order');

                if (sortCell) {
                    sortCell.textContent = sortOrder;
                }

                order.push({
                    id: row.dataset.id,
                    sort_order: sortOrder
                });
            });

            fetch('index.php?page=assets_reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(order)
            });
        }
    });
});
</script>