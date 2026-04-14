<?php

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_setting(mysqli $conn, string $key, string $default = ''): string
{
    $sql = "SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row['setting_value'] ?? $default;
}

function set_setting(mysqli $conn, string $key, string $value): bool
{
    $sql = "
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Generic formatter (non-currency)
 */
function fmt($v, $decimals = 8): string
{
    return rtrim(rtrim(number_format((float)$v, $decimals, '.', ','), '0'), '.');
}

/**
 * Currency-aware formatter
 */
function fmt_asset_value($value, string $currency_symbol = '', int $display_decimals = 8, int $is_fiat = 0): string
{
    $display_decimals = max(0, min(8, $display_decimals));
    $formatted = number_format((float)$value, $display_decimals, '.', ',');

    // Trim trailing zeros ONLY for crypto
    if ((int)$is_fiat !== 1) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }

    if ($formatted === '') {
        $formatted = '0';
    }

    if ($currency_symbol !== '') {
        return $currency_symbol . $formatted;
    }

    return $formatted;
}


function rl_normalize_label(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return mb_strtolower($value, 'UTF-8');
}

function rl_labels_match(string $left, string $right): bool
{
    return rl_normalize_label($left) === rl_normalize_label($right);
}

function rl_find_duplicate_id(mysqli $conn, string $table, string $column, string $value, int $exclude_id = 0, array $extra_where = []): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return 0;
    }

    $sql = "SELECT id, `" . $column . "` AS compare_value FROM `" . $table . "` WHERE 1=1";

    if ($exclude_id > 0) {
        $sql .= " AND id <> " . (int)$exclude_id;
    }

    foreach ($extra_where as $where_col => $where_val) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$where_col)) {
            continue;
        }

        if (is_int($where_val) || ctype_digit((string)$where_val)) {
            $sql .= " AND `" . $where_col . "` = " . (int)$where_val;
        } else {
            $sql .= " AND `" . $where_col . "` = '" . $conn->real_escape_string((string)$where_val) . "'";
        }
    }

    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    while ($row = $result->fetch_assoc()) {
        if (rl_labels_match((string)($row['compare_value'] ?? ''), $value)) {
            return (int)($row['id'] ?? 0);
        }
    }

    return 0;
}

function batch_entry_notice_html(): string {
    return '<div class="batch-note"><strong>Batch Entry:</strong> This item was entered together with other items. Changing shared fields like date will update all items in this batch.</div>';
}


function rl_is_btc_asset_row(array $asset): bool
{
    $symbol = strtoupper(trim((string)($asset['asset_symbol'] ?? '')));
    $name = strtolower(trim((string)($asset['asset_name'] ?? '')));

    return $symbol === 'BTC' || $name === 'bitcoin';
}

function rl_is_btc_asset_id(mysqli $conn, int $asset_id): bool
{
    if ($asset_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT asset_name, asset_symbol FROM assets WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $asset_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $asset = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return is_array($asset) ? rl_is_btc_asset_row($asset) : false;
}

function rl_sats_to_btc_float($value): float
{
    return ((float)$value) / 100000000;
}

?>