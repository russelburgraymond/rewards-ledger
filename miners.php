<?php

$error = "";
$success = "";

// ======================================================
// [SETTINGS] HANDLE BULK MINER ADD
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_miners'])) {
    $bulk_miners = trim($_POST['bulk_miners'] ?? '');
    $added = 0;
    $skipped = 0;

    if ($bulk_miners === '') {
        $error = "Please enter at least one miner name.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $bulk_miners);

        $next_sort_order = 1;
        $next_sort_result = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM miners");
        if ($next_sort_result && ($next_sort_row = $next_sort_result->fetch_assoc())) {
            $next_sort_order = (int)($next_sort_row['next_sort_order'] ?? 1);
        }

        $stmtInsert = $conn->prepare("
            INSERT INTO miners (miner_name, notes, is_active, sort_order)
            VALUES (?, '', 1, ?)
        ");

        if (!$stmtInsert) {
            $error = "Could not prepare bulk miner queries.";
        } else {
            foreach ($lines as $line) {
                $miner_name = trim($line);

                if ($miner_name === '') {
                    continue;
                }

                $existing_id = rl_find_duplicate_id($conn, 'miners', 'miner_name', $miner_name);

                if ($existing_id > 0) {
                    $skipped++;
                    continue;
                }

                $stmtInsert->bind_param("si", $miner_name, $next_sort_order);
                if ($stmtInsert->execute()) {
                    $added++;
                    $next_sort_order++;
                }
            }

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

// ======================================================
// [SETTINGS] HANDLE SINGLE MINER SAVE
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_add_miners'])) {
    $id = (int)($_POST['id'] ?? 0);
    $miner_name = trim($_POST['miner_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($miner_name === '') {
        $error = "Miner name is required.";
    } else {
        $duplicate_id = rl_find_duplicate_id($conn, 'miners', 'miner_name', $miner_name, $id);

        if ($duplicate_id > 0) {
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
                $next_sort_order = 1;
                $next_sort_result = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM miners");
                if ($next_sort_result && ($next_sort_row = $next_sort_result->fetch_assoc())) {
                    $next_sort_order = (int)($next_sort_row['next_sort_order'] ?? 1);
                }

                $stmt = $conn->prepare("
                    INSERT INTO miners (miner_name, notes, is_active, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("ssii", $miner_name, $notes, $is_active, $next_sort_order);
                $stmt->execute();
                $stmt->close();

                $success = "Miner added.";
            }
        }
    }
}

// ======================================================
// [SETTINGS] LOAD EDIT RECORD
// ======================================================
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

// ======================================================
// [SETTINGS] LOAD MINER LIST
// ======================================================
$miners = [];

$result = $conn->query("
    SELECT id, miner_name, notes, is_active, sort_order
    FROM miners
    ORDER BY sort_order ASC, id ASC
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
        <p class="subtext">Enter one miner per line. Blank lines are ignored and duplicates are skipped. New miners are added to the end of the list automatically.</p>

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
    <p class="subtext">Drag and drop rows to reorder miners. New miners are added to the end of the list automatically.</p>

    <?php if (!$miners): ?>
        <p class="subtext">No miners saved yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:70px;">ID</th>
                        <th>Name</th>
                        <th>Notes</th>
                        <th style="width:80px;">Sort</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:90px;">Edit</th>
                    </tr>
                </thead>
                <tbody id="miner-sortable">
                    <?php foreach ($miners as $m): ?>
                        <tr data-id="<?= (int)$m['id'] ?>">
                            <td class="drag-handle" style="cursor:grab; text-align:center;">☰</td>
                            <td><?= (int)$m['id'] ?></td>
                            <td><?= h($m['miner_name']) ?></td>
                            <td><?= h($m['notes']) ?></td>
                            <td class="miner-sort-order"><?= (int)$m['sort_order'] ?></td>
                            <td>
                                <?php if ((int)$m['is_active'] === 1): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php else: ?>
                                    <span class="badge">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="table-link" href="index.php?page=settings&tab=miners&edit=<?= (int)$m['id'] ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('miner-sortable');

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
                const sortCell = row.querySelector('.miner-sort-order');

                if (sortCell) {
                    sortCell.textContent = sortOrder;
                }

                order.push({
                    id: row.dataset.id,
                    sort_order: sortOrder
                });
            });

            fetch('miners_reorder.php', {
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
