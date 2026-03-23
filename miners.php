<?php

$error = "";
$success = "";

/* -----------------------------
   HANDLE BULK ADD
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_miners'])) {
    $bulk_miners = trim($_POST['bulk_miners'] ?? '');
    $added = 0;
    $skipped = 0;

    if ($bulk_miners === '') {
        $error = "Please enter at least one miner name.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $bulk_miners);

        $stmtCheck = $conn->prepare("
            SELECT id
            FROM miners
            WHERE miner_name = ?
            LIMIT 1
        ");

        $stmtInsert = $conn->prepare("
            INSERT INTO miners (miner_name, notes, is_active)
            VALUES (?, '', 1)
        ");

        if (!$stmtCheck || !$stmtInsert) {
            $error = "Could not prepare bulk miner queries.";
        } else {
            foreach ($lines as $line) {
                $miner_name = trim($line);

                if ($miner_name === '') {
                    continue;
                }

                $stmtCheck->bind_param("s", $miner_name);
                $stmtCheck->execute();
                $result = $stmtCheck->get_result();
                $exists = $result ? $result->fetch_assoc() : null;

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $stmtInsert->bind_param("s", $miner_name);
                if ($stmtInsert->execute()) {
                    $added++;
                }
            }

            $stmtCheck->close();
            $stmtInsert->close();

            if ($added > 0) {
                $success = "Added {$added} miner(s).";
                if ($skipped > 0) {
                    $success .= " Skipped {$skipped} duplicate(s).";
                }
            } elseif ($skipped > 0) {
                $success = "No new miners added. Skipped {$skipped} duplicate(s).";
            } else {
                $error = "No valid miner names were found.";
            }
        }
    }
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_add_miners'])) {
    $id = (int)($_POST['id'] ?? 0);
    $miner_name = trim($_POST['miner_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($miner_name === '') {
        $error = "Miner name is required.";
    } else {
        // Check for duplicate name
        if ($id > 0) {
            $stmtDup = $conn->prepare("
                SELECT id
                FROM miners
                WHERE miner_name = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmtDup->bind_param("si", $miner_name, $id);
        } else {
            $stmtDup = $conn->prepare("
                SELECT id
                FROM miners
                WHERE miner_name = ?
                LIMIT 1
            ");
            $stmtDup->bind_param("s", $miner_name);
        }

        $stmtDup->execute();
        $dupResult = $stmtDup->get_result();
        $duplicate = $dupResult ? $dupResult->fetch_assoc() : null;
        $stmtDup->close();

        if ($duplicate) {
            $error = "A miner with that name already exists.";
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE miners
                    SET miner_name = ?, notes = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ssii", $miner_name, $notes, $is_active, $id);
                $stmt->execute();
                $stmt->close();

                $success = "Miner updated.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO miners (miner_name, notes, is_active)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("ssi", $miner_name, $notes, $is_active);
                $stmt->execute();
                $stmt->close();

                $success = "Miner added.";
            }
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
$edit = [
    'id' => 0,
    'miner_name' => '',
    'notes' => '',
    'is_active' => 1,
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, miner_name, notes, is_active
            FROM miners
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
   LOAD MINER LIST
----------------------------- */
$miners = [];

$result = $conn->query("
    SELECT id, miner_name, notes, is_active
    FROM miners
    ORDER BY is_active DESC, miner_name ASC, id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $miners[] = $row;
    }
} else {
    $error = "Could not load miners: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Miners</h2>
    <p class="subtext">Manage your GoMining miners and control which ones are active.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Bulk Add Miners</h3>
        <p class="subtext">Enter one miner per line. Blank lines are ignored and duplicates are skipped.</p>

        <form method="post">
            <div class="form-row">
                <label for="bulk_miners">Miner Names</label>
                <textarea
                    id="bulk_miners"
                    name="bulk_miners"
                    rows="10"
                    placeholder="Miner 1&#10;Miner 2&#10;Miner 3"
                ><?= h($_POST['bulk_miners'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="bulk_add_miners" value="1" class="btn btn-primary">Add Miners</button>
        </form>
    </div>

    <div class="card">
        <h3><?= (int)$edit['id'] > 0 ? 'Edit Miner' : 'Add Single Miner' ?></h3>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="miner_name">Miner Name</label>
                <input
                    type="text"
                    id="miner_name"
                    name="miner_name"
                    value="<?= h($edit['miner_name']) ?>"
                    maxlength="120"
                    required
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

            <button type="submit" class="btn btn-primary">Save Miner</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <h3>Miner List</h3>

    <?php if (!$miners): ?>
        <p class="subtext">No miners saved yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Name</th>
                        <th>Notes</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:90px;">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($miners as $m): ?>
                        <tr>
                            <td><?= (int)$m['id'] ?></td>
                            <td><?= h($m['miner_name']) ?></td>
                            <td><?= h($m['notes']) ?></td>
                            <td>
                                <?php if ((int)$m['is_active'] === 1): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php else: ?>
                                    <span class="badge">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="table-link" href="index.php?page=miners&edit=<?= (int)$m['id'] ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>