<?php

$error = "";
$success = "";

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);
    $asset_name = trim($_POST['asset_name'] ?? '');
    $asset_symbol = trim($_POST['asset_symbol'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($asset_name === '') {
        $error = "Asset name is required.";
    } else {

        if ($id > 0) {

            $stmt = $conn->prepare("
                UPDATE assets
                SET asset_name=?, asset_symbol=?, is_active=?
                WHERE id=?
            ");
            $stmt->bind_param("ssii", $asset_name, $asset_symbol, $is_active, $id);
            $stmt->execute();
            $stmt->close();

            $success = "Asset updated.";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO assets (asset_name, asset_symbol, is_active)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("ssi", $asset_name, $asset_symbol, $is_active);
            $stmt->execute();
            $stmt->close();

            $success = "Asset added.";
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
    'is_active' => 1
];

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT id, asset_name, asset_symbol, is_active
        FROM assets
        WHERE id=?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        if ($row) {
            $edit = $row;
        }
    }

    $stmt->close();
}

/* -----------------------------
   LOAD ASSET LIST
----------------------------- */

$assets = [];

$result = $conn->query("
    SELECT id, asset_name, asset_symbol, is_active
    FROM assets
    ORDER BY is_active DESC, asset_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assets[] = $row;
    }
} else {
    $error = "Asset query failed: " . $conn->error;
}

?>

<div class="page-head">
    <h2>Assets</h2>
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?=h($error)?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?=h($success)?></div>
<?php endif; ?>

<div class="grid-2">

<div class="card">

<h3><?= $edit['id'] ? "Edit Asset" : "Add Asset" ?></h3>

<form method="post">

<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

<div class="form-row">
<label>Asset Name</label>
<input type="text" name="asset_name" value="<?=h($edit['asset_name'])?>" required>
</div>

<div class="form-row">
<label>Symbol</label>
<input type="text" name="asset_symbol" value="<?=h($edit['asset_symbol'])?>">
</div>

<div class="form-row">
<label>
<input type="checkbox" name="is_active" <?= $edit['is_active'] ? "checked" : "" ?>>
Active
</label>
</div>

<button class="btn btn-primary">Save</button>

</form>

</div>

<div class="card">

<h3>Asset List</h3>

<table class="data-table">

<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Symbol</th>
<th>Status</th>
<th>Edit</th>
</tr>
</thead>

<tbody>

<?php foreach ($assets as $a): ?>

<tr>

<td><?= (int)$a['id'] ?></td>

<td><?=h($a['asset_name'])?></td>

<td><?=h($a['asset_symbol'])?></td>

<td>
<?= $a['is_active'] ? "Active" : "Inactive" ?>
</td>

<td>
<a href="?page=assets&edit=<?=(int)$a['id']?>">Edit</a>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>