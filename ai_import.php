<?php

$current_page = 'ai_import';

$error = '';
$success = '';
$preview_rows = [];
$raw_import_text = trim($_POST['import_text'] ?? '');
$selected_screen_type = (string)($_POST['screen_type'] ?? get_setting($conn, 'ai_import_tracking_mode', 'wallet'));
if (!in_array($selected_screen_type, ['wallet', 'rewards_screen'], true)) {
    $selected_screen_type = 'wallet';
}
$allow_overlap = isset($_POST['allow_overlap']);

function ai_import_screen_label(string $screenType): string {
    return $screenType === 'wallet' ? 'Wallet' : 'Rewards Screen';
}

function ai_import_default_category_names(string $screenType): array {
    if ($screenType === 'wallet') {
        return ['Daily Net Rewards', 'Daily Maintenance', 'Referral Bonus', 'veGoMining Reward', 'Reinvestment', 'Miner Upgrade', 'Transfer'];
    }
    return ['Daily Gross Rewards', 'Daily Electricity', 'Daily Maintenance', 'Daily Net Rewards'];
}

function ai_import_default_setting_key(string $screenType): string {
    return $screenType === 'wallet' ? 'ai_import_wallet_default_categories' : 'ai_import_rewards_default_categories';
}

function ai_import_load_default_categories(mysqli $conn, string $screenType): array {
    $fallback = ai_import_default_category_names($screenType);
    $raw = get_setting($conn, ai_import_default_setting_key($screenType), json_encode($fallback));
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

function ai_import_prompt_for_screen(string $screenType): string {
    if ($screenType === 'wallet') {
        return <<<PROMPT
Read this GoMining wallet history screenshot and convert it into RewardLedger import lines.

Use this exact format:
YYYY-MM-DD|GoMining|Category Name|Asset|Amount

Wallet mapping:
Mining reward = Daily Net Rewards
Miner Maintenance = Daily Maintenance
NFT Reinvestment = Reinvestment
Referral bonus = Referral Bonus
veGoMining = veGoMining Reward
Efficiency Upgrade = Miner Upgrade
Power Upgrade = Miner Upgrade
Withdraw / Withdrawal = Transfer

Important:
- Return ONLY the import lines
- Do NOT add commentary
- Put the answer in a SINGLE plain-text code block so I can use the Copy button / Copy all option
- One ledger line per history row
- Use the correct asset shown for each line (for example BTC or GoMining Token)
- Do NOT combine multiple assets into one line
- Do NOT make Daily Maintenance, Reinvestment, or Miner Upgrade negative
- Keep the values exactly as shown in the screenshot
- Ignore rows that are not visible in the screenshot

Example:
2026-03-28|GoMining|Daily Net Rewards|Bitcoin|0.00000521
2026-03-28|GoMining|Referral Bonus|GoMining Token|0.08
2026-03-28|GoMining|Daily Maintenance|GoMining Token|0.31
PROMPT;
    }

    return <<<PROMPT
Read this GoMining rewards screen screenshot and convert it into RewardLedger import lines.

Use this exact format:
YYYY-MM-DD|GoMining|Category Name|Asset|Amount

Rewards screen mapping:
PR = Daily Gross Rewards
Electricity = Daily Electricity
Service = Daily Maintenance
Reward = Daily Net Rewards

Important:
- Return ONLY the import lines
- Do NOT add commentary
- Put the answer in a SINGLE plain-text code block so I can use the Copy button / Copy all option
- One ledger line per row
- Do NOT make electricity or maintenance negative
- Use the values exactly as shown in the screenshot
- RewardLedger will handle whether a category is treated as income or expense during import processing
- If the screenshot shows both GMT and BTC for the same date/category, output one line for each asset
- Use the correct asset shown for each line (for example GoMining Token or BTC)
- Do NOT combine multiple assets into one line
- Ignore wallet-only rows such as Mining reward, Miner Maintenance, NFT Reinvestment, Referral bonus, and veGoMining

Example:
2026-03-22|GoMining|Daily Gross Rewards|GoMining Token|6.48
2026-03-22|GoMining|Daily Electricity|GoMining Token|3.10
2026-03-22|GoMining|Daily Maintenance|GoMining Token|1.53
2026-03-22|GoMining|Daily Net Rewards|GoMining Token|1.83
PROMPT;
}

/* -----------------------------
   LOAD LOOKUP DATA
----------------------------- */
$apps = [];
$assets = [];
$categories = [];
$miners = [];
$referrals = [];
$accounts = [];

$res = $conn->query("SELECT id, app_name FROM apps WHERE is_active = 1 ORDER BY sort_order ASC, app_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $apps[] = $row;
$res = $conn->query("SELECT id, asset_name, asset_symbol FROM assets WHERE is_active = 1 ORDER BY sort_order ASC, asset_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $assets[] = $row;
$res = $conn->query("SELECT id, category_name, behavior_type FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, category_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;
$res = $conn->query("SELECT id, miner_name FROM miners WHERE is_active = 1 ORDER BY miner_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $miners[] = $row;
$res = $conn->query("SELECT id, referral_name FROM referrals WHERE is_active = 1 ORDER BY referral_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $referrals[] = $row;
$res = $conn->query("SELECT id, account_name FROM accounts WHERE is_active = 1 ORDER BY account_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $accounts[] = $row;

$category_name_lookup = [];
foreach ($categories as $row) {
    $category_name_lookup[(string)$row['category_name']] = (int)$row['id'];
}

$selected_category_names = $_POST['selected_categories'] ?? ai_import_load_default_categories($conn, $selected_screen_type);
if (!is_array($selected_category_names)) {
    $selected_category_names = ai_import_load_default_categories($conn, $selected_screen_type);
}
$selected_category_names = array_values(array_unique(array_filter(array_map('trim', $selected_category_names), fn($v) => $v !== '')));
$selected_category_ids = [];
foreach ($selected_category_names as $name) {
    if (isset($category_name_lookup[$name])) {
        $selected_category_ids[] = $category_name_lookup[$name];
    }
}

/* -----------------------------
   LOOKUP MAPS
----------------------------- */
$AI_IMPORT_CASE_INSENSITIVE = get_setting($conn, 'ai_import_case_insensitive', '1') === '1';

function ai_import_normalize_label(string $value): string {
    global $AI_IMPORT_CASE_INSENSITIVE;
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $AI_IMPORT_CASE_INSENSITIVE ? mb_strtolower($value ?? '') : ($value ?? '');
}

$app_map = [];
foreach ($apps as $row) $app_map[ai_import_normalize_label((string)$row['app_name'])] = $row;
$asset_map = [];
foreach ($assets as $row) {
    $asset_map[ai_import_normalize_label((string)$row['asset_name'])] = $row;
    if (!empty($row['asset_symbol'])) $asset_map[ai_import_normalize_label((string)$row['asset_symbol'])] = $row;
}
$category_map = [];
foreach ($categories as $row) $category_map[ai_import_normalize_label((string)$row['category_name'])] = $row;
$miner_map = [];
foreach ($miners as $row) $miner_map[ai_import_normalize_label((string)$row['miner_name'])] = $row;
$referral_map = [];
foreach ($referrals as $row) $referral_map[ai_import_normalize_label((string)$row['referral_name'])] = $row;
$account_map = [];
foreach ($accounts as $row) $account_map[ai_import_normalize_label((string)$row['account_name'])] = $row;

$category_alias_rows = [];
$category_alias_default_by_screen = [];
$screenSafe = $conn->real_escape_string($selected_screen_type);
if ($res = $conn->query("SELECT a.alias_text, c.category_name, a.enabled_by_default, a.screen_type
    FROM ai_import_category_aliases a
    INNER JOIN categories c ON c.id = a.category_id
    WHERE a.is_active = 1 AND a.screen_type = '{$screenSafe}'
    ORDER BY a.sort_order ASC, a.alias_text ASC")) {
    while ($row = $res->fetch_assoc()) {
        $alias_key = ai_import_normalize_label((string)($row['alias_text'] ?? ''));
        $target_name = trim((string)($row['category_name'] ?? ''));
        if ($alias_key !== '' && $target_name !== '') {
            $category_alias_rows[$alias_key] = $target_name;
            $category_alias_default_by_screen[$selected_screen_type][$alias_key] = ((int)($row['enabled_by_default'] ?? 0) === 1);
        }
    }
}

$asset_alias_rows = [];
if ($res = $conn->query("SELECT a.alias_text, s.asset_name
    FROM ai_import_asset_aliases a
    INNER JOIN assets s ON s.id = a.asset_id
    WHERE a.is_active = 1
    ORDER BY a.sort_order ASC, a.alias_text ASC")) {
    while ($row = $res->fetch_assoc()) {
        $alias_key = ai_import_normalize_label((string)($row['alias_text'] ?? ''));
        $target_name = trim((string)($row['asset_name'] ?? ''));
        if ($alias_key !== '' && $target_name !== '') {
            $asset_alias_rows[$alias_key] = $target_name;
        }
    }
}

function ai_import_category_alias(string $value, array $db_aliases = []): string {
    $raw = trim($value);
    $key = ai_import_normalize_label($raw);
    if ($key !== '' && isset($db_aliases[$key])) return (string)$db_aliases[$key];
    return $raw;
}

function ai_import_asset_alias(string $value, array $db_aliases = []): string {
    $raw = trim($value);
    $key = ai_import_normalize_label($raw);
    if ($key !== '' && isset($db_aliases[$key])) return (string)$db_aliases[$key];

    $aliases = [
        'gmt' => 'GoMining Token',
        'gomining token' => 'GoMining Token',
        'gomining' => 'GoMining Token',
        'btc' => 'Bitcoin',
        'bitcoin' => 'Bitcoin',
        'usd' => 'US Dollar',
        'us dollar' => 'US Dollar',
        'usdt' => 'Tether USD',
        'tether usd' => 'Tether USD',
        'eth' => 'Ethereum',
        'ethereum' => 'Ethereum',
        'bnb' => 'Binance Coin',
        'binance coin' => 'Binance Coin',
    ];

    return $aliases[$key] ?? $raw;
}

function ai_import_find_wallet_id(array $asset_row, array $account_map): int {
    $asset_name = ai_import_normalize_label((string)($asset_row['asset_name'] ?? ''));
    $asset_symbol = ai_import_normalize_label((string)($asset_row['asset_symbol'] ?? ''));

    foreach ($account_map as $account_key => $account_row) {
        if ((str_contains($asset_name, 'bitcoin') || $asset_symbol === 'btc') && str_contains($account_key, 'gomining btc')) {
            return (int)$account_row['id'];
        }
        if ((str_contains($asset_name, 'gomining token') || $asset_symbol === 'gmt') && str_contains($account_key, 'gomining gmt')) {
            return (int)$account_row['id'];
        }
    }
    return 0;
}

function ai_import_should_route_from_wallet(string $behavior): bool {
    $behavior = ai_import_normalize_label($behavior);
    return in_array($behavior, ['expense', 'investment', 'withdrawal'], true);
}

function ai_import_should_route_to_wallet(string $behavior): bool {
    $behavior = ai_import_normalize_label($behavior);
    return in_array($behavior, ['income'], true);
}

function ai_import_normalize_amount_for_behavior(float $amount, string $behavior): float {
    $behavior = ai_import_normalize_label($behavior);
    if (in_array($behavior, ['expense', 'investment', 'withdrawal'], true)) {
        return abs($amount);
    }
    return $amount;
}

function ai_import_decimal_string(float $amount, int $scale = 8): string {
    return sprintf('%.' . $scale . 'F', $amount);
}

function ai_import_overlap_mode_category_names(string $screenType): array {
    return $screenType === 'wallet'
        ? ['Daily Net Rewards', 'Daily Maintenance']
        : ['Daily Gross Rewards', 'Daily Electricity', 'Daily Maintenance', 'Daily Net Rewards'];
}

function ai_import_parse_lines(
    string $text,
    array $app_map,
    array $category_map,
    array $asset_map,
    array $miner_map,
    array $referral_map,
    array $account_map,
    array $category_alias_rows = [],
    array $asset_alias_rows = [],
    array $selected_category_ids = []
): array {
    $rows = [];
    $errors = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    if (!$lines) return ['rows' => [], 'errors' => ['No import lines found.']];

    foreach ($lines as $line_no => $line) {
        $line = trim($line);
        if ($line === '' || $line === '```' || str_starts_with($line, '```')) continue;
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 5) {
            $errors[] = 'Line ' . ($line_no + 1) . ': expected at least 5 pipe-separated fields.';
            continue;
        }

        $date_raw = $parts[0] ?? '';
        $app_raw = $parts[1] ?? '';
        $category_raw = $parts[2] ?? '';
        $asset_raw = $parts[3] ?? '';
        $amount_raw = $parts[4] ?? '';
        $miner_raw = $parts[5] ?? '';
        $referral_raw = $parts[6] ?? '';
        $from_account_raw = $parts[7] ?? '';
        $to_account_raw = $parts[8] ?? '';
        $notes_raw = $parts[9] ?? '';

        $date_obj = DateTime::createFromFormat('Y-m-d', $date_raw);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date_raw) {
            $errors[] = 'Line ' . ($line_no + 1) . ': invalid date "' . $date_raw . '". Use YYYY-MM-DD.';
            continue;
        }

        $category_name = ai_import_category_alias($category_raw, $category_alias_rows);
        $asset_name = ai_import_asset_alias($asset_raw, $asset_alias_rows);

        $app_key = ai_import_normalize_label($app_raw);
        $category_key = ai_import_normalize_label($category_name);
        $asset_key = ai_import_normalize_label($asset_name);
        $miner_key = ai_import_normalize_label($miner_raw);
        $referral_key = ai_import_normalize_label($referral_raw);
        $from_account_key = ai_import_normalize_label($from_account_raw);
        $to_account_key = ai_import_normalize_label($to_account_raw);

        if (!isset($app_map[$app_key])) {
            $errors[] = 'Line ' . ($line_no + 1) . ': app not found "' . $app_raw . '".';
            continue;
        }
        if (!isset($category_map[$category_key])) {
            $errors[] = 'Line ' . ($line_no + 1) . ': category not found "' . $category_name . '".';
            continue;
        }
        if (!isset($asset_map[$asset_key])) {
            $errors[] = 'Line ' . ($line_no + 1) . ': asset not found "' . $asset_name . '".';
            continue;
        }

        $category_id = (int)$category_map[$category_key]['id'];
        if ($selected_category_ids && !in_array($category_id, $selected_category_ids, true)) {
            continue;
        }

        $amount_clean = str_replace([',', '$'], '', $amount_raw);
        if ($amount_clean === '' || !is_numeric($amount_clean)) {
            $errors[] = 'Line ' . ($line_no + 1) . ': invalid amount "' . $amount_raw . '".';
            continue;
        }

        $miner_id = 0;
        if ($miner_key !== '') {
            if (!isset($miner_map[$miner_key])) {
                $errors[] = 'Line ' . ($line_no + 1) . ': miner not found "' . $miner_raw . '".';
                continue;
            }
            $miner_id = (int)$miner_map[$miner_key]['id'];
        }

        $referral_id = 0;
        if ($referral_key !== '') {
            if (!isset($referral_map[$referral_key])) {
                $errors[] = 'Line ' . ($line_no + 1) . ': referral not found "' . $referral_raw . '".';
                continue;
            }
            $referral_id = (int)$referral_map[$referral_key]['id'];
        }

        $from_account_id = 0;
        $to_account_id = 0;
        $from_account_name = '';
        $to_account_name = '';

        if ($from_account_key !== '') {
            if (!isset($account_map[$from_account_key])) {
                $errors[] = 'Line ' . ($line_no + 1) . ': from account not found "' . $from_account_raw . '".';
                continue;
            }
            $from_account_id = (int)$account_map[$from_account_key]['id'];
            $from_account_name = (string)$account_map[$from_account_key]['account_name'];
        }
        if ($to_account_key !== '') {
            if (!isset($account_map[$to_account_key])) {
                $errors[] = 'Line ' . ($line_no + 1) . ': to account not found "' . $to_account_raw . '".';
                continue;
            }
            $to_account_id = (int)$account_map[$to_account_key]['id'];
            $to_account_name = (string)$account_map[$to_account_key]['account_name'];
        }

        if ($from_account_id === 0 && $to_account_id === 0) {
            $category_row = $category_map[$category_key];
            $asset_row = $asset_map[$asset_key];
            $behavior = (string)($category_row['behavior_type'] ?? '');
            $wallet_id = ai_import_find_wallet_id($asset_row, $account_map);
            if ($wallet_id > 0) {
                foreach ($account_map as $account_row) {
                    if ((int)$account_row['id'] === $wallet_id) {
                        $wallet_name = (string)$account_row['account_name'];
                        if (ai_import_should_route_from_wallet($behavior)) {
                            $from_account_id = $wallet_id;
                            $from_account_name = $wallet_name;
                        } elseif (ai_import_should_route_to_wallet($behavior)) {
                            $to_account_id = $wallet_id;
                            $to_account_name = $wallet_name;
                        }
                        break;
                    }
                }
            }
        }

        $normalized_amount = ai_import_normalize_amount_for_behavior((float)$amount_clean, (string)($category_map[$category_key]['behavior_type'] ?? ''));
        $normalized_amount_string = ai_import_decimal_string($normalized_amount, 8);
        $raw_amount_string = ai_import_decimal_string((float)$amount_clean, 8);

        $rows[] = [
            'line_no' => $line_no + 1,
            'batch_date' => $date_raw,
            'app_id' => (int)$app_map[$app_key]['id'],
            'app_name' => $app_map[$app_key]['app_name'],
            'category_id' => $category_id,
            'category_name' => $category_map[$category_key]['category_name'],
            'asset_id' => (int)$asset_map[$asset_key]['id'],
            'asset_name' => $asset_map[$asset_key]['asset_name'],
            'amount' => $normalized_amount_string,
            'raw_amount' => $raw_amount_string,
            'miner_id' => $miner_id,
            'miner_name' => $miner_raw,
            'referral_id' => $referral_id,
            'referral_name' => $referral_raw,
            'from_account_id' => $from_account_id,
            'from_account_name' => $from_account_name !== '' ? $from_account_name : $from_account_raw,
            'to_account_id' => $to_account_id,
            'to_account_name' => $to_account_name !== '' ? $to_account_name : $to_account_raw,
            'notes' => $notes_raw,
            'raw_line' => $line,
        ];
    }

    return ['rows' => $rows, 'errors' => $errors];
}

function ai_import_find_overlaps(mysqli $conn, array $rows, string $screenType): array {
    $conflicts = [];
    if (!$rows || !rl_table_exists($conn, 'batch_items') || !rl_column_exists($conn, 'batch_items', 'import_source_type')) {
        return $conflicts;
    }

    $existingSource = $screenType === 'wallet' ? 'ai_rewards' : 'ai_wallet';
    $compareCategoryNames = ai_import_overlap_mode_category_names($screenType === 'wallet' ? 'rewards_screen' : 'wallet');
    $compareIds = [];
    $res = $conn->query("SELECT id, category_name FROM categories");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (in_array((string)$row['category_name'], $compareCategoryNames, true)) {
                $compareIds[] = (int)$row['id'];
            }
        }
    }
    if (!$compareIds) return $conflicts;

    $seen = [];
    $sql = "SELECT bi.id
        FROM batch_items bi
        INNER JOIN batches b ON b.id = bi.batch_id
        WHERE b.batch_date = ?
          AND b.app_id = ?
          AND bi.asset_id = ?
          AND bi.import_source_type = ?
          AND bi.category_id IN (" . implode(',', array_map('intval', $compareIds)) . ")
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $conflicts;

    foreach ($rows as $idx => $row) {
        $selfNames = ai_import_overlap_mode_category_names($screenType);
        if (!in_array((string)$row['category_name'], $selfNames, true)) {
            continue;
        }
        $key = $row['batch_date'] . '|' . $row['app_id'] . '|' . $row['asset_id'] . '|' . $row['category_id'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $stmt->bind_param('siis', $row['batch_date'], $row['app_id'], $row['asset_id'], $existingSource);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->fetch_assoc()) {
            $conflicts[$idx] = true;
        }
    }
    $stmt->close();

    return $conflicts;
}

/* -----------------------------
   HANDLE PREVIEW / SAVE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($raw_import_text === '') {
        $error = 'Please paste the import lines first.';
    } elseif (!$selected_category_ids) {
        $error = 'Please leave at least one category checked for this import.';
    } else {
        $parsed = ai_import_parse_lines(
            $raw_import_text,
            $app_map,
            $category_map,
            $asset_map,
            $miner_map,
            $referral_map,
            $account_map,
            $category_alias_rows,
            $asset_alias_rows,
            $selected_category_ids
        );

        $preview_rows = $parsed['rows'];
        if (!empty($parsed['errors'])) {
            $error = implode(' ', $parsed['errors']);
        } elseif (!$preview_rows) {
            $error = 'No valid rows were found to import after applying the current category filters.';
        } else {
            $overlap_indexes = ai_import_find_overlaps($conn, $preview_rows, $selected_screen_type);
            foreach ($preview_rows as $idx => &$row) {
                $row['is_overlap'] = isset($overlap_indexes[$idx]);
            }
            unset($row);

            if ($action === 'save_import') {
                $rows_to_save = [];
                foreach ($preview_rows as $row) {
                    if (!$allow_overlap && !empty($row['is_overlap'])) {
                        continue;
                    }
                    $rows_to_save[] = $row;
                }

                if (!$rows_to_save) {
                    $error = 'All preview rows were skipped because matching entries from the other screenshot model already exist for those dates/assets. Uncheck overlap prevention only if you intentionally want to mix models.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $batch_cache = [];
                        $stmt_batch = $conn->prepare("INSERT INTO batches (batch_date, app_id) VALUES (?, ?)");
                        $stmt_item = $conn->prepare("INSERT INTO batch_items (
                            batch_id,
                            miner_id,
                            asset_id,
                            category_id,
                            referral_id,
                            from_account_id,
                            to_account_id,
                            amount,
                            notes,
                            import_source_type
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt_batch || !$stmt_item) {
                            throw new Exception('Could not prepare import statements.');
                        }

                        $sourceType = $selected_screen_type === 'wallet' ? 'ai_wallet' : 'ai_rewards';
                        foreach ($rows_to_save as $row) {
                            $batch_key = $row['batch_date'] . '|' . $row['app_id'];
                            if (!isset($batch_cache[$batch_key])) {
                                $stmt_batch->bind_param('si', $row['batch_date'], $row['app_id']);
                                $stmt_batch->execute();
                                $batch_cache[$batch_key] = (int)$conn->insert_id;
                            }
                            $batch_id = $batch_cache[$batch_key];
                            $notes = (string)$row['notes'];
                            $amount = (string)$row['amount'];
                            $stmt_item->bind_param('iiiiiiisss', $batch_id, $row['miner_id'], $row['asset_id'], $row['category_id'], $row['referral_id'], $row['from_account_id'], $row['to_account_id'], $amount, $notes, $sourceType);
                            $stmt_item->execute();
                        }
                        $stmt_batch->close();
                        $stmt_item->close();
                        $conn->commit();

                        $skipped = count($preview_rows) - count($rows_to_save);
                        $success = count($rows_to_save) . ' row(s) imported successfully.' . ($skipped > 0 ? ' ' . $skipped . ' overlapping row(s) were skipped.' : '');
                        $raw_import_text = '';
                        $preview_rows = [];
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = 'Import failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$chatgpt_prompt = ai_import_prompt_for_screen($selected_screen_type);
?>

<div class="page-head">
    <h2>AI Import</h2>
    <p class="subtext">Choose the screenshot type first, then use the matching prompt and category checklist for that import.</p>
</div>

<?php if ($error !== ''): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<div class="card">
    <h3>Step 1: Choose Screenshot Type</h3>
    <form method="post" id="ai-import-config-form">
        <div class="grid-2" style="gap:14px; align-items:start;">
            <div class="form-row">
                <label for="screen_type">Screenshot Type</label>
                <select id="screen_type" name="screen_type" onchange="document.getElementById('ai-import-config-form').submit();">
                    <option value="rewards_screen"<?= $selected_screen_type === 'rewards_screen' ? ' selected' : '' ?>>Rewards Screen</option>
                    <option value="wallet"<?= $selected_screen_type === 'wallet' ? ' selected' : '' ?>>Wallet</option>
                </select>
                <div class="subtext" style="margin-top:6px;">
                    <?= $selected_screen_type === 'wallet'
                        ? 'Wallet imports are detailed and can include Daily Net Rewards, Miner Maintenance, Referral Bonus, veGoMining, Reinvestment, upgrades, and transfers.'
                        : 'Rewards Screen imports are daily summary totals and only look for PR, Electricity, Service, and Reward.' ?>
                </div>
            </div>
            <div class="form-row">
                <label>Categories to Detect This Time</label>
                <div class="ai-category-grid">
                    <?php foreach ($categories as $category): ?>
                        <label>
                            <input type="checkbox" name="selected_categories[]" value="<?= h($category['category_name']) ?>"<?= in_array($category['category_name'], $selected_category_names, true) ? ' checked' : '' ?>>
                            <?= h($category['category_name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="subtext" style="margin-top:6px;">These defaults come from Settings → AI Import and can be adjusted per import.</div>
            </div>
        </div>
        <?php if ($raw_import_text !== ''): ?>
            <input type="hidden" name="import_text" value="<?= h($raw_import_text) ?>">
        <?php endif; ?>
        <div style="margin-top:12px;">
            <button type="submit" class="btn btn-secondary">Apply Screenshot Type / Categories</button>
        </div>
    </form>
</div>

<div class="card mt-20">
    <h3>Step 2: Copy This Prompt for ChatGPT</h3>
    <p class="subtext">Copy this prompt, paste it into ChatGPT with your screenshot, then copy the results ChatGPT gives you.</p>

    <div class="form-row">
        <textarea id="ai-import-prompt" readonly rows="18"><?= h($chatgpt_prompt) ?></textarea>
    </div>

    <div class="form-actions">
        <div class="copy-wrapper">
            <button type="button" class="btn btn-primary" onclick="copyAiImportPrompt(this)">Copy Prompt for ChatGPT</button>
            <span class="copy-inline-toast" aria-live="polite">Copied!</span>
        </div>
    </div>
</div>

<div class="card mt-20">
    <h3>Step 3: Paste the Import Lines Here</h3>
    <p class="subtext">After ChatGPT returns the results, paste them below, preview them, then save.</p>

    <form method="post">
        <input type="hidden" name="screen_type" value="<?= h($selected_screen_type) ?>">
        <?php foreach ($selected_category_names as $name): ?>
            <input type="hidden" name="selected_categories[]" value="<?= h($name) ?>">
        <?php endforeach; ?>
        <div class="form-row">
            <label for="import_text">Import Lines</label>
            <textarea id="import_text" name="import_text" rows="14" placeholder="Paste ChatGPT output here..."><?= h($raw_import_text) ?></textarea>
        </div>
        <div class="form-row" style="margin-top:10px;">
            <label><input type="checkbox" name="allow_overlap"<?= $allow_overlap ? ' checked' : '' ?>> Allow overlapping entries from the other screenshot model</label>
            <div class="subtext" style="margin-top:6px;">Leave this off to protect against mixing Wallet-based and Rewards Screen-based entries for the same dates and assets.</div>
        </div>
        <div class="form-actions">
            <button type="submit" name="action" value="preview_import" class="btn btn-secondary">Preview Import</button>
            <button type="submit" name="action" value="save_import" class="btn btn-primary">Save Import</button>
        </div>
    </form>
</div>

<div class="card mt-20">
    <h3>Accepted Format</h3>
    <p class="subtext">Required fields are the first 5. Optional fields can be left blank, but keep the separators if you use later fields.</p>
    <div class="form-row">
        <textarea readonly rows="4">YYYY-MM-DD|App|Category|Asset|Amount|Miner|Referral|From Account|To Account|Notes</textarea>
    </div>
</div>

<?php if ($preview_rows): ?>
    <div class="card mt-20">
        <h3>Preview</h3>
        <p class="subtext">Rows marked “Overlap” will be skipped on save unless you explicitly allow overlap.</p>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Line</th>
                        <th style="width:120px;">Date</th>
                        <th>App</th>
                        <th>Category</th>
                        <th>Asset</th>
                        <th style="width:130px;">Amount</th>
                        <th>From</th>
                        <th>To</th>
                        <th style="width:120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_rows as $row): ?>
                        <tr>
                            <td><?= (int)$row['line_no'] ?></td>
                            <td><?= h($row['batch_date']) ?></td>
                            <td><?= h($row['app_name']) ?></td>
                            <td><?= h($row['category_name']) ?></td>
                            <td><?= h($row['asset_name']) ?></td>
                            <td><?= h((string)$row['amount']) ?></td>
                            <td><?= h((string)$row['from_account_name']) ?></td>
                            <td><?= h((string)$row['to_account_name']) ?></td>
                            <td><?= !empty($row['is_overlap']) ? '<span style="color:#b84a00; font-weight:600;">Overlap</span>' : '<span style="color:#167c2f; font-weight:600;">Ready</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post" class="mt-20">
            <input type="hidden" name="action" value="save_import">
            <input type="hidden" name="screen_type" value="<?= h($selected_screen_type) ?>">
            <input type="hidden" name="import_text" value="<?= h($raw_import_text) ?>">
            <?php if ($allow_overlap): ?><input type="hidden" name="allow_overlap" value="1"><?php endif; ?>
            <?php foreach ($selected_category_names as $name): ?>
                <input type="hidden" name="selected_categories[]" value="<?= h($name) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Confirm Save Import</button>
        </form>
    </div>
<?php endif; ?>

<style>
.ai-category-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(180px, 1fr));
    gap:8px 14px;
    padding:12px;
    border:1px solid #d9dfeb;
    border-radius:12px;
    background:#f6f8fb;
}
.copy-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.copy-inline-toast {
    background: #2f3640;
    color: #fff;
    font-size: 12px;
    line-height: 1;
    padding: 6px 9px;
    border-radius: 6px;
    opacity: 0;
    transform: translateY(4px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
    white-space: nowrap;
}
.copy-inline-toast.show { opacity: 1; transform: translateY(0); }
@media (max-width: 900px) {
    .ai-category-grid { grid-template-columns:1fr; }
}
</style>

<script>
function showAiImportCopyToast(button, message) {
    const wrapper = button && button.closest('.copy-wrapper');
    const toast = wrapper ? wrapper.querySelector('.copy-inline-toast') : null;
    if (!toast) return;
    toast.textContent = message || 'Copied!';
    toast.classList.add('show');
    clearTimeout(toast.hideTimer);
    toast.hideTimer = setTimeout(function () { toast.classList.remove('show'); }, 2000);
}
function fallbackCopyText(text, button) {
    const helper = document.createElement('textarea');
    helper.value = text;
    helper.setAttribute('readonly', 'readonly');
    helper.style.position = 'absolute';
    helper.style.left = '-9999px';
    document.body.appendChild(helper);
    helper.select();
    helper.setSelectionRange(0, helper.value.length);
    try { document.execCommand('copy'); showAiImportCopyToast(button, 'Copied!'); }
    catch (err) { showAiImportCopyToast(button, 'Copy failed'); }
    document.body.removeChild(helper);
}
function copyAiImportPrompt(button) {
    const textarea = document.getElementById('ai-import-prompt');
    if (!textarea) return;
    const text = textarea.value || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { showAiImportCopyToast(button, 'Copied!'); }).catch(function () { fallbackCopyText(text, button); });
        return;
    }
    fallbackCopyText(text, button);
}
</script>
