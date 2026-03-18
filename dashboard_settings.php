<?php

$current_page = 'dashboard_settings';

$error = "";
$success = "";

/* -----------------------------
   HANDLE SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['category_ids'] ?? [];

    if (!is_array($selected)) {
        $selected = [];
    }

    $clean_ids = [];
    foreach ($selected as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $clean_ids[] = $id;
        }
    }

    $value = implode(',', $clean_ids);

    if (set_setting($conn, 'dashboard_category_ids', $value)) {
        $success = "Dashboard categories updated.";
    } else {
        $error = "Could not save dashboard settings.";
    }
}

/* -----------------------------
   LOAD CURRENT SETTING
----------------------------- */
$selected_ids_raw = get_setting($conn, 'dashboard_category_ids', '');
$selected_ids = array_filter(array_map('intval', explode(',', $selected_ids_raw)));

/* -----------------------------
   LOAD APPS + CATEGORIES
----------------------------- */
$app_groups = [];

$res = $conn->query("
    SELECT
        a.id AS app_id,
        a.app_name,
        a.sort_order,
        c.id AS category_id,
        c.category_name,
        c.behavior_type,
        c.sort_order AS category_sort_order
    FROM apps a
    LEFT JOIN categories c
        ON c.app_id = a.id
       AND c.is_active = 1
    WHERE a.is_active = 1
    ORDER BY a.sort_order ASC, a.app_name ASC, c.sort_order ASC, c.category_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $app_id = (int)$row['app_id'];

        if (!isset($app_groups[$app_id])) {
            $app_groups[$app_id] = [
                'app_id' => $app_id,
                'app_name' => $row['app_name'],
                'categories' => [],
            ];
        }

        if (!empty($row['category_id'])) {
            $app_groups[$app_id]['categories'][] = [
                'id' => (int)$row['category_id'],
                'category_name' => $row['category_name'],
                'behavior_type' => $row['behavior_type'],
                'sort_order' => (int)$row['category_sort_order'],
            ];
        }
    }
} else {
    $error = "Could not load dashboard categories: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Dashboard Settings</h2>
    <p class="subtext">Choose which categories should appear on the dashboard, grouped by app.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Select Dashboard Categories</h3>

        <?php if (!$app_groups): ?>
            <p class="subtext">No active apps or categories available.</p>
        <?php else: ?>
            <form method="post">
                <?php foreach ($app_groups as $group): ?>
                    <div class="card" style="margin-bottom:16px; padding:16px;">
                        <h3 style="margin-top:0;"><?= h($group['app_name']) ?></h3>

                        <?php if (!$group['categories']): ?>
                            <p class="subtext">No active categories for this app.</p>
                        <?php else: ?>
                            <div style="display:grid; gap:8px; margin-top:8px;">
                                <?php foreach ($group['categories'] as $c): ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="category_ids[]"
                                            value="<?= (int)$c['id'] ?>"
                                            <?= in_array((int)$c['id'], $selected_ids, true) ? 'checked' : '' ?>
                                        >
                                        <?= h($c['category_name']) ?>
                                        <?php if (trim((string)$c['behavior_type']) !== ''): ?>
                                            <span class="subtext">(<?= h($c['behavior_type']) ?>)</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">Save Dashboard Settings</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>How It Works</h3>
        <p class="subtext">
            Categories are now app-specific, so dashboard totals are selected by app automatically.
        </p>
        <p class="subtext">
            Net profit tiles are calculated separately by app using that app’s expense and withdrawal categories.
        </p>
        <p style="margin-top:16px;">
            <a class="btn btn-secondary" href="index.php?page=dashboard">Back to Dashboard</a>
        </p>
    </div>
</div>