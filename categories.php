<?php

$current_page = 'categories';

$error = "";
$success = "";

/* -----------------------------
   Flash messages
----------------------------- */
if (isset($_GET['added'])) {
    $success = "Category added successfully.";
}

if (isset($_GET['updated'])) {
    $success = "Category updated successfully.";
}

/* -----------------------------
   LOAD APPS
----------------------------- */
$apps = [];

$res = $conn->query("
    SELECT id, app_name
    FROM apps
    WHERE is_active = 1
    ORDER BY sort_order ASC, app_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $apps[] = $row;
    }
}

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_category') {
        $id            = (int)($_POST['id'] ?? 0);
        $app_id        = (int)($_POST['app_id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $behavior_type = trim($_POST['behavior_type'] ?? 'income');
        $sort_order    = (int)($_POST['sort_order'] ?? 0);
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        $allowed_behaviors = ['income', 'expense', 'investment', 'withdrawal', 'transfer', 'adjustment'];
        if (!in_array($behavior_type, $allowed_behaviors, true)) {
            $behavior_type = 'income';
        }

        if ($app_id <= 0) {
            $error = "Please select an app.";
        } elseif ($category_name === '') {
            $error = "Category name is required.";
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE categories
                    SET app_id = ?, category_name = ?, behavior_type = ?, sort_order = ?, is_active = ?
                    WHERE id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param("issiii", $app_id, $category_name, $behavior_type, $sort_order, $is_active, $id);
                    $stmt->execute();
                    $stmt->close();

                    header("Location: index.php?page=settings&tab=categories&updated=1");
                    exit;
                } else {
                    $error = "Could not update category: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO categories (app_id, category_name, behavior_type, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param("issii", $app_id, $category_name, $behavior_type, $sort_order, $is_active);
                    $stmt->execute();
                    $stmt->close();

                    header("Location: index.php?page=settings&tab=categories&added=1");
                    exit;
                } else {
                    $error = "Could not add category: " . $conn->error;
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
    'app_id' => !empty($apps) ? (int)$apps[0]['id'] : 0,
    'category_name' => '',
    'behavior_type' => 'income',
    'sort_order' => 0,
    'is_active' => 1,
];

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, app_id, category_name, behavior_type, sort_order, is_active
            FROM categories
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
            $error = "Could not load category for editing: " . $conn->error;
        }
    }
}

/* -----------------------------
   LOAD LIST
----------------------------- */
$categories = [];

$result = $conn->query("
    SELECT
        c.id,
        c.app_id,
        c.category_name,
        c.behavior_type,
        c.sort_order,
        c.is_active,
        a.app_name
    FROM categories c
    LEFT JOIN apps a ON a.id = c.app_id
    ORDER BY c.is_active DESC, c.sort_order ASC, c.id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    $error = "Could not load categories: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Categories</h2>
    <p class="subtext">Manage the categories you want to track for each app.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= (int)$edit['id'] > 0 ? 'Edit Category' : 'Add Category' ?></h3>

        <form method="post">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

            <div class="form-row">
                <label for="app_id">App</label>
                <select id="app_id" name="app_id" required>
                    <option value="0">Select App</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= (int)$app['id'] ?>" <?= (int)$edit['app_id'] === (int)$app['id'] ? 'selected' : '' ?>>
                            <?= h($app['app_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="category_name">Category Name</label>
                <input
                    type="text"
                    id="category_name"
                    name="category_name"
                    value="<?= h($edit['category_name']) ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-row">
                <label for="behavior_type">Behavior Type</label>
                <select id="behavior_type" name="behavior_type">
                    <option value="income" <?= $edit['behavior_type'] === 'income' ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= $edit['behavior_type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                    <option value="investment" <?= $edit['behavior_type'] === 'investment' ? 'selected' : '' ?>>Investment</option>
                    <option value="withdrawal" <?= $edit['behavior_type'] === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                    <option value="transfer" <?= $edit['behavior_type'] === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                    <option value="adjustment" <?= $edit['behavior_type'] === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
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
                <?= (int)$edit['id'] > 0 ? 'Save Category' : 'Add Category' ?>
            </button>

            <?php if ((int)$edit['id'] > 0): ?>
                <a class="btn btn-secondary" href="index.php?page=settings&tab=categories">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>Category List</h3>
        <p class="subtext">Sort Order controls how categories appear in dropdowns. Drag-and-drop reorder can be added next.</p>

        <?php if (!$categories): ?>
            <p class="subtext">No categories saved yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
					    <tr>
							<th style="width:40px;"></th>
                            <th>App</th>
                            <th>Name</th>
                            <th style="width:140px;">Behavior</th>
                            <th style="width:100px;">Sort</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Edit</th>
                        </tr>
                    </thead>
                    <tbody id="category-sortable">
                        <?php foreach ($categories as $c): ?>
                            <tr data-id="<?= (int)$c['id'] ?>">
								<td class="drag-handle">☰</td>
                                <td><?= h($c['app_name'] ?? '') ?></td>
                                <td><?= h($c['category_name']) ?></td>
                                <td><?= h($c['behavior_type']) ?></td>
                                <td><?= (int)$c['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$c['is_active'] === 1): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="table-link" href="index.php?page=settings&tab=categories&edit=<?= (int)$c['id'] ?>">Edit</a>
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
    const el = document.getElementById('category-sortable');

    if (!el) return;

    new Sortable(el, {
        handle: '.drag-handle',
        animation: 150,
		ghostClass: 'sortable-ghost',
        onEnd: function () {
            const rows = el.querySelectorAll('tr');
            const order = [];

            rows.forEach((row, index) => {
                order.push({
                    id: row.dataset.id,
                    sort_order: index
                });
            });

            fetch('categories_reorder.php', {
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