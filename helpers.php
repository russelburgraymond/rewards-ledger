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

function batch_entry_notice_html(): string {
    return '<div class="batch-note"><strong>Batch Entry:</strong> This item was entered together with other items. Changing shared fields like date will update all items in this batch.</div>';
}

?>