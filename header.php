<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($APP_NAME) ?> v<?= h($APP_VERSION) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode($APP_VERSION) ?>">
</head>
<body>
<script src="assets/js/Sortable.min.js"></script>
<?php
$current_page = $current_page ?? 'dashboard';
?>

<div class="topbar">
    <div class="title">
        <?= h($APP_NAME) ?>
        <span class="version-badge">v<?= h($APP_VERSION) ?></span>
    </div>

<div class="menu">
    <a href="index.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="index.php?page=quick_entry" class="<?= $current_page === 'quick_entry' ? 'active' : '' ?>">Quick Entry</a>
    <a href="index.php?page=ledger" class="<?= $current_page === 'ledger' ? 'active' : '' ?>">Ledger</a>
    <a href="index.php?page=templates" class="<?= in_array($current_page, ['templates', 'template_edit', 'template_use'], true) ? 'active' : '' ?>">Templates</a>
    <a href="index.php?page=settings" class="<?= $current_page === 'settings' ? 'active' : '' ?>">Settings</a>
</div>
</div>