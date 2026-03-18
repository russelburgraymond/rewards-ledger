<?php

$error = "";
$success = "";

/* -----------------------------
   HANDLE SAVE
----------------------------- */
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
                SET referral_name=?, referral_identifier=?, account_id=?, notes=?, is_active=?
                WHERE id=?
            ");

            $stmt->bind_param(
                "ssiiii",
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

            $stmt = $conn->prepare("
                INSERT INTO referrals
                (referral_name, referral_identifier, account_id, notes, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssisi",
                $referral_name,
                $referral_identifier,
                $account_id,
                $notes,
                $is_active
            );

            $stmt->execute();
            $stmt->close();

            $success = "Referral added.";
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */

$edit = [
    'id' => 0,
    'referral_name' => '',
    'referral_identifier' => '',
    'account_id' => 0,
    'notes' => '',
    'is_active' => 1
];

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT id, referral_name, referral_identifier, account_id, notes, is_active
        FROM referrals
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
   LOAD ACCOUNT OPTIONS
----------------------------- */

$accounts = [];
$res = $conn->query("
    SELECT id, account_name
    FROM accounts
    WHERE is_active=1
    ORDER BY account_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
}

/* -----------------------------
   LOAD REFERRALS
----------------------------- */

$referrals = [];

$result = $conn->query("
    SELECT r.*, a.account_name
    FROM referrals r
    LEFT JOIN accounts a ON r.account_id=a.id
    ORDER BY r.is_active DESC, r.referral_name ASC
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
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?=h($error)?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?=h($success)?></div>
<?php endif; ?>

<div class="grid-2">

<div class="card">

<h3><?= $edit['id'] ? "Edit Referral" : "Add Referral" ?></h3>

<form method="post">

<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

<div class="form-row">
<label>Referral Name</label>
<input type="text" name="referral_name" value="<?=h($edit['referral_name'])?>" required>
</div>

<div class="form-row">
<label>Identifier</label>
<input type="text" name="referral_identifier" value="<?=h($edit['referral_identifier'])?>">
</div>

<div class="form-row">
<label>Account</label>
<select name="account_id">

<option value="0">None</option>

<?php foreach ($accounts as $a): ?>

<option value="<?=$a['id']?>"
<?= $edit['account_id']==$a['id'] ? "selected":"" ?>>
<?=h($a['account_name'])?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="form-row">
<label>Notes</label>
<textarea name="notes"><?=h($edit['notes'])?></textarea>
</div>

<div class="form-row">
<label>
<input type="checkbox" name="is_active"
<?= $edit['is_active'] ? "checked":"" ?>>
Active
</label>
</div>

<button class="btn btn-primary">Save</button>

</form>

</div>

<div class="card">

<h3>Referral List</h3>

<table class="data-table">

<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Identifier</th>
<th>Account</th>
<th>Status</th>
<th>Edit</th>
</tr>
</thead>

<tbody>

<?php foreach ($referrals as $r): ?>

<tr>

<td><?=$r['id']?></td>

<td><?=h($r['referral_name'])?></td>

<td><?=h($r['referral_identifier'])?></td>

<td><?=h($r['account_name'] ?? '')?></td>

<td><?= $r['is_active'] ? "Active":"Inactive" ?></td>

<td>
<a href="?page=referrals&edit=<?=$r['id']?>">Edit</a>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>