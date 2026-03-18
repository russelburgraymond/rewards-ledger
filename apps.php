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

if (isset($_GET['toggled'])) {
    $success = "App status updated.";
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_app') {
        $id        = (int)($_POST['id'] ?? 0);
        $app_name  = trim($_POST['app_name'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($app_name === '') {
            $error = "App name is required.";
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

                    header("Location: index.php?page=apps&updated=1");
                    exit;
                } else {
                    $error = "Could not update app: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO apps (app_name, sort_order, is_active)
                    VALUES (?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param("sii", $app_name, $sort_order, $is_active);
                    $stmt->execute();
                    $stmt->close();

                    header("Location: index.php?page=apps&added=1");
                    exit;
                } else {
                    $error = "Could not add app: " . $conn->error;
                }
            }
        }
    }
}

/* -----------------------------
   HANDLE TOGGLE ACTIVE
----------------------------- */
if (isset($_GET['toggle'])) {
    $toggle_id = (int)($_GET['toggle'] ?? 0);

    if ($toggle_id > 0) {
        $stmt = $conn->prepare("
            UPDATE apps
            SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $toggle_id);
            $stmt->execute();
            $stmt->close();

            header("Location: index.php?page=apps&toggled=1");
            exit;
        } else {
            $error = "Could not change app status: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD EDIT RECORD
----------------------------- */
$edit = [
    'id' => 0,
    'app_name' => '',
    'sort_order' => 0,
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
    <p class="subtext">Manage the apps or platforms you want to track, such as GoMining, Atlas Earth, and Mode Earn.</p>
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
                <a class="btn btn-secondary" href="index.php?page=apps">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>App List</h3>

        <?php if (!$apps): ?>
            <p class="subtext">No apps saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Name</th>
                            <th style="width:100px;">Sort</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                            <th style="width:110px;">Toggle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apps as $a): ?>
                            <tr>
                                <td><?= (int)$a['id'] ?></td>
                                <td><?= h($a['app_name']) ?></td>
                                <td><?= (int)$a['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=apps&edit=<?= (int)$a['id'] ?>">Edit</a>
                                </td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <a class="table-link" href="index.php?page=apps&toggle=<?= (int)$a['id'] ?>">Deactivate</a>
                                    <?php else: ?>
                                        <a class="table-link" href="index.php?page=apps&toggle=<?= (int)$a['id'] ?>">Reactivate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>