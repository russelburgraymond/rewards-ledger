<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($APP_NAME) ?> v<?= h($APP_VERSION) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode($APP_VERSION) ?>">
</head>
<body>

<?php
$current_page = $current_page ?? 'dashboard';

$settings_pages = [
    'dashboard_settings',
	'apps',
    'miners',
    'referrals',
    'categories',
    'assets',
    'accounts',
    'system_status',
    'changelog',
];

$is_settings_active = in_array($current_page, $settings_pages, true);
?>

<div class="topbar">
    <div class="title">
        <?= h($APP_NAME) ?>
        <span class="version-badge">v<?= h($APP_VERSION) ?></span>
    </div>

    <div class="menu">
        <a href="index.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="index.php?page=quick_entry" class="<?= $current_page === 'quick_entry' ? 'active' : '' ?>">Quick Entry</a>
        <a href="index.php?page=templates" class="<?= in_array($current_page, ['templates', 'template_edit', 'template_use'], true) ? 'active' : '' ?>">Templates</a>

        <div class="menu-dropdown">
            <button type="button" class="menu-dropbtn <?= $is_settings_active ? 'active' : '' ?>">⚙ Settings ▾</button>
            <div class="menu-dropdown-content">
                <a href="index.php?page=dashboard_settings" class="<?= $current_page === 'dashboard_settings' ? 'active' : '' ?>">Dashboard Settings</a>
				<a href="index.php?page=quick_adds" class="<?= $current_page === 'quick_adds' ? 'active' : '' ?>">Quick Adds</a>
				<a href="index.php?page=apps" class="<?= $current_page === 'apps' ? 'active' : '' ?>">Apps</a>
                <a href="index.php?page=miners" class="<?= $current_page === 'miners' ? 'active' : '' ?>">Miners</a>
                <a href="index.php?page=referrals" class="<?= $current_page === 'referrals' ? 'active' : '' ?>">Referrals</a>
                <a href="index.php?page=categories" class="<?= $current_page === 'categories' ? 'active' : '' ?>">Categories</a>
                <a href="index.php?page=assets" class="<?= $current_page === 'assets' ? 'active' : '' ?>">Assets</a>
                <a href="index.php?page=accounts" class="<?= $current_page === 'accounts' ? 'active' : '' ?>">Accounts</a>
                <a href="index.php?page=system_status" class="<?= $current_page === 'system_status' ? 'active' : '' ?>">System Status</a>
                <a href="index.php?page=changelog" class="<?= $current_page === 'changelog' ? 'active' : '' ?>">Changelog</a>
            </div>
        </div>
    </div>
</div>

<div class="container">

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.querySelector(".menu-dropbtn");
    const menu = document.querySelector(".menu-dropdown-content");

    if (!btn || !menu) return;

    btn.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.style.display = (menu.style.display === "block") ? "none" : "block";
    });

    document.addEventListener("click", function () {
        menu.style.display = "none";
    });
});
</script>