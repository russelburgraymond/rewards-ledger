<?php

$current_page = 'quick_adds';

$error = "";
$success = "";

$quick_adds = [];

$res = $conn->query("
    SELECT
        qa.id,
        qa.quick_add_name,
        qa.sort_order,
        qa.is_active,
        ap.app_name,
        c.category_name
    FROM quick_add_items qa
    LEFT JOIN apps ap ON ap.id = qa.app_id
    LEFT JOIN categories c ON c.id = qa.category_id
    ORDER BY ap.app_name ASC, qa.sort_order ASC, qa.quick_add_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $quick_adds[] = $row;
    }
} else {
    $error = "Could not load Quick Adds: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Quick Adds</h2>
    <p class="subtext">These are the dedicated Quick Entry shortcuts.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <h3>Quick Add List</h3>

    <?php if (!$quick_adds): ?>
        <p class="subtext">No quick adds found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>App</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th style="width:90px;">Sort</th>
                        <th style="width:100px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quick_adds as $qa): ?>
                        <tr>
                            <td><?= (int)$qa['id'] ?></td>
                            <td><?= h($qa['app_name'] ?? '') ?></td>
                            <td><?= h($qa['quick_add_name']) ?></td>
                            <td><?= h($qa['category_name'] ?? '') ?></td>
                            <td><?= (int)$qa['sort_order'] ?></td>
                            <td>
                                <?php if ((int)$qa['is_active'] === 1): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>