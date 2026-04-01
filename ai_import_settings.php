<?php

$current_page = 'ai_import_settings';

$error = '';
$success = '';

function ai_alias_normalize_local(string $value, bool $caseInsensitive = true): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $caseInsensitive ? mb_strtolower($value) : $value;
}

function ai_import_screen_label(string $screenType): string {
    return $screenType === 'wallet' ? 'Wallet' : 'Rewards Screen';
}

function ai_import_screen_options(?string $selected = null): string {
    $options = [
        'rewards_screen' => 'Rewards Screen',
        'wallet' => 'Wallet',
    ];
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . h($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    return $html;
}

function ai_import_default_category_names(string $screenType): array {
    if ($screenType === 'wallet') {
        return [
            'Daily Net Rewards',
            'Daily Maintenance',
            'Referral Bonus',
            'veGoMining Reward',
            'Reinvestment',
            'Miner Upgrade',
            'Transfer',
        ];
    }

    return [
        'Daily Gross Rewards',
        'Daily Electricity',
        'Daily Maintenance',
        'Daily Net Rewards',
    ];
}

function ai_import_setting_key_for_defaults(string $screenType): string {
    return $screenType === 'wallet'
        ? 'ai_import_wallet_default_categories'
        : 'ai_import_rewards_default_categories';
}

function ai_import_load_default_categories(mysqli $conn, string $screenType): array {
    $fallback = ai_import_default_category_names($screenType);
    $raw = get_setting($conn, ai_import_setting_key_for_defaults($screenType), json_encode($fallback));
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return $fallback;
    }
    $out = [];
    foreach ($decoded as $name) {
        $name = trim((string)$name);
        if ($name !== '') {
            $out[] = $name;
        }
    }
    return $out ?: $fallback;
}

function ai_import_save_default_categories(mysqli $conn, string $screenType, array $names): bool {
    $clean = [];
    foreach ($names as $name) {
        $name = trim((string)$name);
        if ($name !== '') {
            $clean[] = $name;
        }
    }
    return set_setting($conn, ai_import_setting_key_for_defaults($screenType), json_encode(array_values(array_unique($clean))));
}

function ai_alias_duplicate_exists(mysqli $conn, string $table, string $aliasText, int $excludeId, bool $caseInsensitive, string $screenType = ''): bool {
    $rows = [];
    $select = $table === 'ai_import_category_aliases'
        ? 'SELECT id, alias_text, screen_type FROM ai_import_category_aliases'
        : 'SELECT id, alias_text FROM ai_import_asset_aliases';
    $res = $conn->query($select);
    if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;

    $needle = ai_alias_normalize_local($aliasText, $caseInsensitive);
    foreach ($rows as $row) {
        $rowId = (int)($row['id'] ?? 0);
        if ($excludeId > 0 && $rowId === $excludeId) continue;
        $hay = ai_alias_normalize_local((string)($row['alias_text'] ?? ''), $caseInsensitive);
        if ($needle === '' || $needle !== $hay) continue;
        if ($table === 'ai_import_category_aliases') {
            $rowScreen = (string)($row['screen_type'] ?? 'rewards_screen');
            if ($rowScreen !== $screenType) {
                continue;
            }
        }
        return true;
    }
    return false;
}

function ai_render_target_options(array $items, string $type, int $selectedId = 0): string {
    $html = '<option value="0">Select ' . ($type === 'asset' ? 'asset' : 'category') . '...</option>';
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $label = $type === 'asset'
            ? $item['asset_name'] . (trim((string)($item['asset_symbol'] ?? '')) !== '' ? ' (' . $item['asset_symbol'] . ')' : '')
            : $item['category_name'] . (trim((string)($item['behavior_type'] ?? '')) !== '' ? ' (' . $item['behavior_type'] . ')' : '');
        $html .= '<option value="' . $id . '"' . ($selectedId === $id ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    return $html;
}

$case_insensitive = get_setting($conn, 'ai_import_case_insensitive', '1') === '1';
$tracking_mode = get_setting($conn, 'ai_import_tracking_mode', 'wallet');
if (!in_array($tracking_mode, ['wallet', 'rewards_screen'], true)) {
    $tracking_mode = 'wallet';
}

$categories = [];
$assets = [];
$res = $conn->query("
    SELECT 
        c.id, 
        c.category_name, 
        c.behavior_type,
        COALESCE(a.app_name, 'Other') AS app_name
    FROM categories c
    LEFT JOIN apps a ON a.id = c.app_id
    WHERE c.is_active = 1
    ORDER BY a.app_name ASC, c.sort_order ASC, c.category_name ASC
");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;
$res = $conn->query("SELECT id, asset_name, asset_symbol FROM assets WHERE is_active = 1 ORDER BY sort_order ASC, asset_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_ai_import_options') {
        $case_insensitive = isset($_POST['ai_import_case_insensitive']);
        $tracking_mode = (string)($_POST['ai_import_tracking_mode'] ?? 'wallet');
        if (!in_array($tracking_mode, ['wallet', 'rewards_screen'], true)) {
            $tracking_mode = 'wallet';
        }
        $ok = set_setting($conn, 'ai_import_case_insensitive', $case_insensitive ? '1' : '0');
        $ok = set_setting($conn, 'ai_import_tracking_mode', $tracking_mode) && $ok;
        if ($ok) {
            $success = 'AI Import options saved.';
        } else {
            $error = 'Could not save AI Import options.';
        }
    }

    if ($action === 'save_default_categories') {
        $screenType = (string)($_POST['screen_type'] ?? 'rewards_screen');
        if (!in_array($screenType, ['rewards_screen', 'wallet'], true)) {
            $screenType = 'rewards_screen';
        }
        $selected = $_POST['default_category_names'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        if (ai_import_save_default_categories($conn, $screenType, $selected)) {
            $success = ai_import_screen_label($screenType) . ' defaults saved.';
        } else {
            $error = 'Could not save default categories.';
        }
    }

    if (in_array($action, ['save_category_alias', 'save_asset_alias'], true)) {
        $isAsset = $action === 'save_asset_alias';
        $table = $isAsset ? 'ai_import_asset_aliases' : 'ai_import_category_aliases';
        $targetColumn = $isAsset ? 'asset_id' : 'category_id';
        $targetId = (int)($_POST[$targetColumn] ?? 0);
        $aliasText = trim((string)($_POST['alias_text'] ?? ''));
        $aliasId = (int)($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $screenType = (string)($_POST['screen_type'] ?? 'rewards_screen');
        if (!in_array($screenType, ['rewards_screen', 'wallet'], true)) {
            $screenType = 'rewards_screen';
        }
        $enabledByDefault = isset($_POST['enabled_by_default']) ? 1 : 0;

        if ($aliasText === '') {
            $error = $isAsset ? 'Asset alias text is required.' : 'Category alias text is required.';
        } elseif ($targetId <= 0) {
            $error = $isAsset ? 'Please choose an asset for this alias.' : 'Please choose a category for this alias.';
        } elseif (ai_alias_duplicate_exists($conn, $table, $aliasText, $aliasId, $case_insensitive, $screenType)) {
            $error = $isAsset ? 'That asset alias already exists.' : 'That category alias already exists for that screen type.';
        } else {
            if ($sortOrder === 0) {
                $res = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM {$table}");
                $sortOrder = 10;
                if ($res && ($row = $res->fetch_assoc())) {
                    $sortOrder = max(10, ((int)($row['max_sort'] ?? 0)) + 10);
                }
            }

            if ($isAsset) {
                if ($aliasId > 0) {
                    $stmt = $conn->prepare("UPDATE ai_import_asset_aliases SET alias_text = ?, asset_id = ?, sort_order = ?, is_active = ? WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('siiii', $aliasText, $targetId, $sortOrder, $isActive, $aliasId);
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO ai_import_asset_aliases (alias_text, asset_id, sort_order, is_active) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('siii', $aliasText, $targetId, $sortOrder, $isActive);
                    }
                }
            } else {
                if ($aliasId > 0) {
                    $stmt = $conn->prepare("UPDATE ai_import_category_aliases SET alias_text = ?, category_id = ?, screen_type = ?, enabled_by_default = ?, sort_order = ?, is_active = ? WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('sisiiii', $aliasText, $targetId, $screenType, $enabledByDefault, $sortOrder, $isActive, $aliasId);
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO ai_import_category_aliases (alias_text, category_id, screen_type, enabled_by_default, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('sisiii', $aliasText, $targetId, $screenType, $enabledByDefault, $sortOrder, $isActive);
                    }
                }
            }

            if (empty($stmt)) {
                $error = 'Could not save alias: ' . $conn->error;
            } else {
                if ($stmt->execute()) {
                    $success = 'Alias saved.';
                } else {
                    $error = 'Could not save alias: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    if (in_array($action, ['delete_category_alias', 'delete_asset_alias'], true)) {
        $table = $action === 'delete_asset_alias' ? 'ai_import_asset_aliases' : 'ai_import_category_aliases';
        $aliasId = (int)($_POST['id'] ?? 0);
        if ($aliasId > 0) {
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $aliasId);
                if ($stmt->execute()) {
                    $success = 'Alias deleted.';
                } else {
                    $error = 'Could not delete alias.';
                }
                $stmt->close();
            }
        }
    }

    if (in_array($action, ['reorder_category_aliases', 'reorder_asset_aliases'], true)) {
        $table = $action === 'reorder_asset_aliases' ? 'ai_import_asset_aliases' : 'ai_import_category_aliases';
        $idsRaw = trim((string)($_POST['ordered_ids'] ?? ''));
        $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw))));
        if ($ids) {
            $sort = 10;
            $stmt = $conn->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ? LIMIT 1");
            if ($stmt) {
                foreach ($ids as $id) {
                    $stmt->bind_param('ii', $sort, $id);
                    $stmt->execute();
                    $sort += 10;
                }
                $stmt->close();
            }
        }
        $success = 'Alias order saved.';
    }
}

$category_aliases = [];
$asset_aliases = [];
$res = $conn->query("SELECT a.id, a.alias_text, a.category_id, a.screen_type, a.enabled_by_default, a.sort_order, a.is_active, c.category_name, c.behavior_type
    FROM ai_import_category_aliases a
    LEFT JOIN categories c ON c.id = a.category_id
    ORDER BY a.screen_type ASC, a.sort_order ASC, a.alias_text ASC, a.id ASC");
if ($res) while ($row = $res->fetch_assoc()) $category_aliases[] = $row;
$res = $conn->query("SELECT a.id, a.alias_text, a.asset_id, a.sort_order, a.is_active, s.asset_name, s.asset_symbol
    FROM ai_import_asset_aliases a
    LEFT JOIN assets s ON s.id = a.asset_id
    ORDER BY a.sort_order ASC, a.alias_text ASC, a.id ASC");
if ($res) while ($row = $res->fetch_assoc()) $asset_aliases[] = $row;

$category_alias_groups = ['rewards_screen' => [], 'wallet' => []];
foreach ($category_aliases as $row) {
    $screenType = (string)($row['screen_type'] ?? 'rewards_screen');
    if (!isset($category_alias_groups[$screenType])) {
        $category_alias_groups[$screenType] = [];
    }
    $category_alias_groups[$screenType][] = $row;
}

$rewards_default_names = ai_import_load_default_categories($conn, 'rewards_screen');
$wallet_default_names = ai_import_load_default_categories($conn, 'wallet');
?>

<div class="page-head">
    <h2>AI Import Settings</h2>
    <p class="subtext">Set your tracking mode, choose default categories by screenshot type, and manage aliases for Rewards Screen and Wallet imports separately.</p>
</div>

<?php if ($error !== ''): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<style>
.ai-grid { display:grid; gap:18px; }
.ai-grid-2 { display:grid; grid-template-columns:1.05fr 1fr; gap:18px; align-items:start; }
.ai-settings-card { padding:18px; }
.ai-alias-table { width:100%; border-collapse:separate; border-spacing:0 8px; }
.ai-alias-table th { text-align:left; font-size:12px; color:#63708a; padding:0 10px; }
.ai-alias-row td { background:#f6f8fb; padding:10px; vertical-align:middle; border-top:1px solid #d9dfeb; border-bottom:1px solid #d9dfeb; }
.ai-alias-row td:first-child { border-left:1px solid #d9dfeb; border-top-left-radius:10px; border-bottom-left-radius:10px; }
.ai-alias-row td:last-child { border-right:1px solid #d9dfeb; border-top-right-radius:10px; border-bottom-right-radius:10px; }
.ai-alias-row input[type="text"], .ai-alias-row select, .ai-alias-row input[type="number"] { margin:0; }
.ai-drag-handle { cursor:move; user-select:none; color:#7c879d; font-size:18px; line-height:1; text-align:center; width:24px; }
.ai-row-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.ai-mini-note { font-size:12px; color:#63708a; margin-top:4px; }
.ai-check-grid { display:grid; grid-template-columns:repeat(2, minmax(160px, 1fr)); gap:8px 14px; }
.ai-panel-split { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
.ai-stack { display:grid; gap:12px; }
.ai-new-form { background:#f6f8fb; border:1px solid #d9dfeb; border-radius:12px; padding:14px; }
.ai-toggle-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; background:#f6f8fb; border:1px solid #d9dfeb; border-radius:12px; }
.ai-screen-title { margin:0 0 8px; }
@media (max-width: 1080px) {
    .ai-grid-2, .ai-panel-split { grid-template-columns:1fr; }
    .ai-check-grid { grid-template-columns:1fr; }
}
</style>

<div class="ai-grid">
    <div class="ai-grid-2">
        <div class="card ai-settings-card">
            <h3 style="margin-top:0;">Tracking & Matching</h3>
            <form method="post" class="ai-stack">
                <input type="hidden" name="action" value="save_ai_import_options">
                <div class="form-row">
                    <label for="ai_import_tracking_mode">Preferred Tracking Mode</label>
                    <select id="ai_import_tracking_mode" name="ai_import_tracking_mode">
                        <option value="wallet"<?= $tracking_mode === 'wallet' ? ' selected' : '' ?>>Wallet (detailed / transaction-based)</option>
                        <option value="rewards_screen"<?= $tracking_mode === 'rewards_screen' ? ' selected' : '' ?>>Rewards Screen (daily summary totals)</option>
                    </select>
                    <div class="ai-mini-note">This sets the default screenshot type on the AI Import page. Users can still switch per import, but overlap protection will still apply.</div>
                </div>
                <div class="ai-toggle-row">
                    <div>
                        <strong>Match aliases case-insensitive</strong>
                        <div class="ai-mini-note">Recommended on. Treats PR and pr as the same alias.</div>
                    </div>
                    <label style="white-space:nowrap;"><input type="checkbox" name="ai_import_case_insensitive" <?= $case_insensitive ? 'checked' : '' ?>> On</label>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Save AI Import Options</button>
                </div>
            </form>
        </div>

        <div class="card ai-settings-card">
            <h3 style="margin-top:0;">Default Categories by Screenshot Type</h3>
            <div class="ai-panel-split">
                <?php foreach (['rewards_screen' => $rewards_default_names, 'wallet' => $wallet_default_names] as $screenType => $selectedNames): ?>
                    <form method="post" class="ai-new-form">
                        <input type="hidden" name="action" value="save_default_categories">
                        <input type="hidden" name="screen_type" value="<?= h($screenType) ?>">
                        <h4 class="ai-screen-title"><?= h(ai_import_screen_label($screenType)) ?></h4>
                        <div class="ai-mini-note" style="margin-bottom:10px;">These categories start checked on the AI Import page for this screenshot type.</div>
						<div class="ai-check-grid">
							<?php 
							$current_app = null;
							foreach ($categories as $category): 
								$app_name = $category['app_name'] ?? 'Other';

								if ($app_name !== $current_app):
									$current_app = $app_name;
							?>
								<div style="grid-column: 1 / -1; font-weight:600; margin-top:8px; color:#2d3748;">
									<?= h($current_app) ?>
								</div>
							<?php endif; ?>

								<label>
									<input type="checkbox" name="default_category_names[]" value="<?= h($category['category_name']) ?>"<?= in_array($category['category_name'], $selectedNames, true) ? ' checked' : '' ?>>
									<?= h($category['category_name']) ?>
								</label>

							<?php endforeach; ?>
						</div>
                        <div style="margin-top:12px;">
                            <button type="submit" class="btn btn-secondary">Save <?= h(ai_import_screen_label($screenType)) ?> Defaults</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card ai-settings-card">
        <h3 style="margin-top:0;">Category Aliases</h3>
        <p class="subtext">Category aliases are now separated by screenshot type so Rewards Screen and Wallet imports can be managed independently.</p>

        <div class="ai-panel-split">
            <?php foreach (['rewards_screen', 'wallet'] as $screenType): ?>
                <div>
                    <h4 class="ai-screen-title"><?= h(ai_import_screen_label($screenType)) ?></h4>
                    <form method="post" class="ai-new-form" style="margin-bottom:14px;">
                        <input type="hidden" name="action" value="save_category_alias">
                        <input type="hidden" name="id" value="0">
                        <input type="hidden" name="sort_order" value="0">
                        <input type="hidden" name="screen_type" value="<?= h($screenType) ?>">
                        <div class="grid-2" style="gap:12px; align-items:end;">
                            <div class="form-row">
                                <label>Alias Text</label>
                                <input type="text" name="alias_text" value="" placeholder="Example: <?= $screenType === 'wallet' ? 'Mining reward' : 'PR' ?>">
                            </div>
                            <div class="form-row">
                                <label>Maps To Category</label>
                                <select name="category_id"><?= ai_render_target_options($categories, 'category') ?></select>
                            </div>
                        </div>
                        <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap; margin-top:10px;">
                            <label><input type="checkbox" name="enabled_by_default" checked> Enabled for this screen type by default</label>
                            <label><input type="checkbox" name="is_active" checked> Active</label>
                            <button type="submit" class="btn btn-primary">Add Alias</button>
                        </div>
                    </form>

                    <table class="ai-alias-table">
                        <thead>
                            <tr>
                                <th style="width:34px;"></th>
                                <th>Alias Text</th>
                                <th>Maps To Category</th>
                                <th style="width:160px;">Defaults</th>
                                <th style="width:80px;">Active</th>
                                <th style="width:155px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="js-sortable-body" data-reorder-action="reorder_category_aliases">
                            <?php foreach (($category_alias_groups[$screenType] ?? []) as $row): ?>
                                <tr class="ai-alias-row js-alias-row" draggable="true" data-id="<?= (int)$row['id'] ?>">
                                    <td class="ai-drag-handle">⋮⋮</td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="action" value="save_category_alias">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="sort_order" value="<?= (int)$row['sort_order'] ?>">
                                            <input type="hidden" name="screen_type" value="<?= h($screenType) ?>">
                                            <input type="text" name="alias_text" value="<?= h($row['alias_text']) ?>">
                                    </td>
                                    <td>
                                            <select name="category_id"><?= ai_render_target_options($categories, 'category', (int)$row['category_id']) ?></select>
                                    </td>
                                    <td>
                                            <label><input type="checkbox" name="enabled_by_default"<?= (int)$row['enabled_by_default'] === 1 ? ' checked' : '' ?>> Default</label>
                                    </td>
                                    <td>
                                            <label><input type="checkbox" name="is_active"<?= (int)$row['is_active'] === 1 ? ' checked' : '' ?>> Active</label>
                                    </td>
                                    <td>
                                            <div class="ai-row-actions">
                                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                                <button type="submit" name="action" value="delete_category_alias" class="btn btn-secondary btn-sm" onclick="return confirm('Delete this alias?');">Delete</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card ai-settings-card">
        <h3 style="margin-top:0;">Asset Aliases</h3>
        <p class="subtext">Asset aliases stay shared because BTC, GMT, USD, and other asset labels are the same no matter which screenshot type is used.</p>

        <form method="post" class="ai-new-form" style="margin-bottom:14px;">
            <input type="hidden" name="action" value="save_asset_alias">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="sort_order" value="0">
            <div class="grid-2" style="gap:12px; align-items:end;">
                <div class="form-row">
                    <label>Alias Text</label>
                    <input type="text" name="alias_text" value="" placeholder="Example: GMT">
                </div>
                <div class="form-row">
                    <label>Maps To Asset</label>
                    <select name="asset_id"><?= ai_render_target_options($assets, 'asset') ?></select>
                </div>
            </div>
            <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap; margin-top:10px;">
                <label><input type="checkbox" name="is_active" checked> Active</label>
                <button type="submit" class="btn btn-primary">Add Asset Alias</button>
            </div>
        </form>

        <table class="ai-alias-table">
            <thead>
                <tr>
                    <th style="width:34px;"></th>
                    <th>Alias Text</th>
                    <th>Maps To Asset</th>
                    <th style="width:80px;">Active</th>
                    <th style="width:155px;">Actions</th>
                </tr>
            </thead>
            <tbody class="js-sortable-body" data-reorder-action="reorder_asset_aliases">
                <?php foreach ($asset_aliases as $row): ?>
                    <tr class="ai-alias-row js-alias-row" draggable="true" data-id="<?= (int)$row['id'] ?>">
                        <td class="ai-drag-handle">⋮⋮</td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="action" value="save_asset_alias">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="sort_order" value="<?= (int)$row['sort_order'] ?>">
                                <input type="text" name="alias_text" value="<?= h($row['alias_text']) ?>">
                        </td>
                        <td>
                                <select name="asset_id"><?= ai_render_target_options($assets, 'asset', (int)$row['asset_id']) ?></select>
                        </td>
                        <td>
                                <label><input type="checkbox" name="is_active"<?= (int)$row['is_active'] === 1 ? ' checked' : '' ?>> Active</label>
                        </td>
                        <td>
                                <div class="ai-row-actions">
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    <button type="submit" name="action" value="delete_asset_alias" class="btn btn-secondary btn-sm" onclick="return confirm('Delete this alias?');">Delete</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.js-sortable-body').forEach(function(tbody) {
        let dragRow = null;
        tbody.querySelectorAll('.js-alias-row').forEach(function(row) {
            row.addEventListener('dragstart', function() {
                dragRow = row;
                row.classList.add('ai-row-dragging');
            });
            row.addEventListener('dragend', function() {
                row.classList.remove('ai-row-dragging');
                dragRow = null;
                const ids = Array.from(tbody.querySelectorAll('.js-alias-row')).map(function(r) { return r.dataset.id; }).join(',');
                const fd = new FormData();
                fd.append('action', tbody.dataset.reorderAction);
                fd.append('ordered_ids', ids);
                fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function() { window.location.reload(); })
                    .catch(function() { window.location.reload(); });
            });
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!dragRow || dragRow === row) return;
                const rect = row.getBoundingClientRect();
                const before = (e.clientY - rect.top) < (rect.height / 2);
                if (before) {
                    tbody.insertBefore(dragRow, row);
                } else {
                    tbody.insertBefore(dragRow, row.nextSibling);
                }
            });
        });
    });
})();
</script>
