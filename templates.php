<?php

$current_page = 'templates';

$error = "";
$success = "";
$msg = $_GET['msg'] ?? '';

if ($msg === 'template_deleted') {
    $success = "Template deleted.";
} elseif ($msg === 'invalid_template') {
    $error = "Invalid template id.";
}

/* -----------------------------
   LOAD TEMPLATE LIST
----------------------------- */

$templates = [];

$res = $conn->query("
    SELECT
        t.id,
        t.template_name,
        t.app_id,
        a.app_name,
        COUNT(ti.id) AS line_count
    FROM templates t
    LEFT JOIN apps a ON a.id = t.app_id
    LEFT JOIN template_items ti ON ti.template_id = t.id
    GROUP BY t.id, t.template_name, t.app_id, a.app_name
    ORDER BY a.app_name ASC, t.template_name ASC, t.id ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $templates[] = $row;
    }
} else {
    $error = "Could not load templates: " . $conn->error;
}
?>

<div class="page-head">
    <h2>Templates</h2>
    <p class="subtext">Create reusable templates and use them to enter real reward data.</p>
</div>

<div class="page-actions" style="margin-bottom:20px;">
    <a class="btn btn-primary" href="index.php?page=template_edit">Create New Template</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <h3>Templates</h3>

    <?php if (!$templates): ?>
        <p class="subtext">No templates have been created yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>App</th>
                        <th>Title</th>
                        <th style="width:100px;">Lines</th>
                        <th style="width:90px;">Edit</th>
                        <th style="width:90px;">Use</th>
						<th style="width:90px;">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><?= h($t['app_name'] ?? '') ?></td>
                            <td>
                                <?php if (trim((string)$t['template_name']) !== ''): ?>
                                    <?= h($t['template_name']) ?>
                                <?php else: ?>
                                    <span class="subtext">Untitled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-blue"><?= (int)$t['line_count'] ?></span>
                            </td>
                            <td>
                                <a class="table-link" href="index.php?page=template_edit&id=<?= (int)$t['id'] ?>">Edit</a>
                            </td>
                            <td>
                                <a class="table-link" href="index.php?page=template_use&id=<?= (int)$t['id'] ?>">Use</a>
                            </td>
							<td>
								<a href="index.php?page=template_delete&id=<?= (int)$t['id'] ?>"
								   onclick="return confirm('Delete this template and its template lines?');">
								   Delete
								</a>
							</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>