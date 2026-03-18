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

?>