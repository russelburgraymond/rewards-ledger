<?php

$current_page = 'settings';

$settings_tabs = [
    'dashboard'     => ['label' => 'Dashboard',        'file' => 'dashboard_settings.php'],
    'quick_adds'    => ['label' => 'Quick Entry',      'file' => 'quick_adds.php'],
    'apps'          => ['label' => 'Apps',             'file' => 'apps.php'],
    'miners'        => ['label' => 'Miners',           'file' => 'miners.php'],
    'referrals'     => ['label' => 'Referrals',        'file' => 'referrals.php'],
    'categories'    => ['label' => 'Categories',       'file' => 'categories.php'],
    'assets'        => ['label' => 'Assets',           'file' => 'assets.php'],
    'accounts'      => ['label' => 'Accounts',         'file' => 'accounts.php'],
    'system_status' => ['label' => 'System Status',    'file' => 'system_status.php'],
    'changelog'     => ['label' => 'Changelog',        'file' => 'changelog.php'],
];

$tab = $_GET['tab'] ?? 'dashboard';
$tab = preg_replace('/[^a-zA-Z0-9_-]/', '', $tab);

if (!isset($settings_tabs[$tab])) {
    $tab = 'dashboard';
}

$tab_file = __DIR__ . '/' . $settings_tabs[$tab]['file'];
?>

<div class="page-head">
    <h2>Settings</h2>
    <p class="subtext">Manage app setup, lists, and configuration from one place.</p>
</div>

<div class="settings-tabs-wrap">
    <div class="settings-tabs">
        <?php foreach ($settings_tabs as $tab_key => $tab_info): ?>
            <a
                href="index.php?page=settings&tab=<?= h($tab_key) ?>"
                class="settings-tab <?= $tab === $tab_key ? 'active' : '' ?>"
            >
                <?= h($tab_info['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="settings-panel">
    <?php
    if (is_file($tab_file)) {
        require $tab_file;
    } else {
        echo "<div class='card'><h3>Tab not found</h3><p class='subtext'>The requested settings tab could not be loaded.</p></div>";
    }
    ?>
</div>