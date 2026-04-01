<?php

$current_page = 'apps';

$error = "";
$success = "";

/* -----------------------------
   Flash messages
----------------------------- */
if (isset($_GET['added'])) {
    $success = "App added successfully.";
}

if (isset($_GET['updated'])) {
    $success = "App updated successfully.";
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_app') {
        $id         = (int)($_POST['id'] ?? 0);
        $app_name   = trim($_POST['app_name'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($app_name === '') {
            $error = "App name is required.";
        } else {
            $duplicate_id = rl_find_duplicate_id($conn, 'apps', 'app_name', $app_name, $id);

            if ($duplicate_id > 0) {
                $error = "An app with that name already exists.";
            } else {
                if ($id > 0) {
                    $stmt = $conn->prepare("
                        UPDATE apps
                        SET app_name = ?, sort_order = ?, is_active = ?
                        WHERE id = ?
                    ");

                    if ($stmt) {
                        $stmt->bind_param("siii", $app_name, $sort_order, $is_active, $id);
                        $stmt->execute();
                        $stmt->close();

                        header("Location: index.php?page=settings&tab=apps&updated=1");
                        exit;
                    } else {
                        $error = "Could not update app: " . $conn->error;
                    }
                } else {
                    // ======================================================
                    // [SETTINGS] APPS NEXT SORT ORDER ON ADD
                    // ======================================================
                    // New apps should be added to the end of the current list
                    // so multiple new rows do not all start at sort order 0.
                    $next_sort_order = 1;
                    $next_sort_result = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM apps");

                    if ($next_sort_result) {
                        $next_sort_row = $next_sort_result->fetch_assoc();
                        $next_sort_order = (int)($next_sort_row['next_sort_order'] ?? 1);
                        $next_sort_result->close();
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO apps (app_name, sort_order, is_active)
                        VALUES (?, ?, ?)
                    ");

                    if ($stmt) {
                        $stmt->bind_param("sii", $app_name, $next_sort_order, $is_active);
                        $stmt->execute();
                        $stmt->close();

                        header("Location: index.php?page=settings&tab=apps&added=1");
                        exit;
                    } else {
                        $error = "Could not add app: " . $conn->error;
                    }
                }
            }
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
// ======================================================
// [SETTINGS] DEFAULT APP EDIT STATE
// ======================================================
// Default new apps to the end of the current list so the sort
// order field matches where the new row will actually be saved.
$next_app_sort_order = 1;
$next_app_sort_result = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM apps");

if ($next_app_sort_result) {
    $next_app_sort_row = $next_app_sort_result->fetch_assoc();
    $next_app_sort_order = (int)($next_app_sort_row['next_sort_order'] ?? 1);
    $next_app_sort_result->close();
}

$edit = [
    'id' => 0,
    'app_name' => '',
    'sort_order' => $next_app_sort_order,
    'is_active' => 1,
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, app_name, sort_order, is_active
            FROM apps
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
            $error = "Could not load app for editing: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD LIST
----------------------------- */
$apps = [];

$result = $conn->query("
    SELECT id, app_name, sort_order, is_active
    FROM apps
    ORDER BY is_active DESC, sort_order ASC, app_name ASC, id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $apps[] = $row;
    }
} else {
    $error = "Could not load apps: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Apps</h2>
    <p class="subtext">Manage the apps available throughout RewardLedger.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= (int)$edit['id'] > 0 ? 'Edit App' : 'Add App' ?></h3>

        <form method="post">
            <input type="hidden" name="action" value="save_app">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="app_name">App Name</label>
                <input
                    type="text"
                    id="app_name"
                    name="app_name"
                    value="<?= h($edit['app_name']) ?>"
                    maxlength="120"
                    required
                >
            </div>

            <div class="form-row">
                <label for="sort_order">Sort Order</label>
                <input
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    value="<?= (int)$edit['sort_order'] ?>"
                >
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="is_active" <?= !empty($edit['is_active']) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= (int)$edit['id'] > 0 ? 'Save App' : 'Add App' ?>
            </button>

            <?php if ((int)$edit['id'] > 0): ?>
                <a class="btn btn-secondary" href="index.php?page=settings&tab=apps">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>App List</h3>
        <p class="subtext">Activation is controlled from the edit form. Drag and drop rows to reorder apps quickly.</p>

        <?php if (!$apps): ?>
            <p class="subtext">No apps saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Name</th>
                            <th style="width:100px;">Sort</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                        </tr>
                    </thead>
                    <tbody id="app-sortable">
                        <?php foreach ($apps as $a): ?>
                            <tr data-id="<?= (int)$a['id'] ?>">
                                <td class="drag-handle">☰</td>
                                <td><?= h($a['app_name']) ?></td>
                                <td class="app-sort-order"><?= (int)$a['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=apps&edit=<?= (int)$a['id'] ?>">Edit</a>
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
document.addEventListener("DOMContentLoaded", function () {
    const el = document.getElementById('app-sortable');

    if (!el || typeof Sortable === 'undefined') return;

    new Sortable(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            const rows = el.querySelectorAll('tr');
            const order = [];

            rows.forEach((row, index) => {
                const sortCell = row.querySelector('.app-sort-order');
                const sortOrder = index + 1;

                if (sortCell) {
                    sortCell.textContent = sortOrder;
                }

                order.push({
                    id: row.dataset.id,
                    sort_order: sortOrder
                });
            });

            fetch('apps_reorder.php', {
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
