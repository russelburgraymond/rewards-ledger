<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

if (empty($db_exists) || !isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database is not available.';
    exit;
}

ensure_schema($conn);

function rl_backup_list_tables(mysqli $conn): array
{
    $tables = [];
    $res = $conn->query('SHOW TABLES');
    if ($res) {
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            if (!empty($row[0])) {
                $tables[] = (string)$row[0];
            }
        }
        $res->close();
    }
    sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
    return $tables;
}

function rl_backup_fetch_rows(mysqli $conn, string $table): array
{
    $rows = [];
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $res = $conn->query("SELECT * FROM {$safeTable}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
    return $rows;
}

function rl_backup_get_columns(mysqli $conn, string $table): array
{
    $columns = [];
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $res = $conn->query("SHOW COLUMNS FROM {$safeTable}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['Field'])) {
                $columns[] = (string)$row['Field'];
            }
        }
        $res->close();
    }
    return $columns;
}

function rl_restore_delete_all(mysqli $conn, array $tables): void
{
    foreach ($tables as $table) {
        $safeTable = '`' . str_replace('`', '``', $table) . '`';
        if (!$conn->query("DELETE FROM {$safeTable}")) {
            throw new RuntimeException('Could not clear table ' . $table . ': ' . $conn->error);
        }
    }
}

function rl_restore_insert_rows(mysqli $conn, string $table, array $rows): void
{
    if (empty($rows)) {
        return;
    }

    $tableColumns = rl_backup_get_columns($conn, $table);
    if (empty($tableColumns)) {
        throw new RuntimeException('Could not inspect table columns for ' . $table . '.');
    }

    $safeTable = '`' . str_replace('`', '``', $table) . '`';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $filtered = [];
        foreach ($tableColumns as $col) {
            if (array_key_exists($col, $row)) {
                $filtered[$col] = $row[$col];
            }
        }

        if (empty($filtered)) {
            continue;
        }

        $columnsSql = implode(', ', array_map(static fn(string $col): string => '`' . str_replace('`', '``', $col) . '`', array_keys($filtered)));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));
        $sql = "INSERT INTO {$safeTable} ({$columnsSql}) VALUES ({$placeholders})";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Could not prepare restore insert for ' . $table . ': ' . $conn->error);
        }

        $types = '';
        $values = [];
        foreach ($filtered as $value) {
            $types .= 's';
            $values[] = $value === null ? null : (string)$value;
        }

        $bind = [$types];
        foreach ($values as $i => $value) {
            $bind[] = &$values[$i];
        }

        if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
            $stmt->close();
            throw new RuntimeException('Could not bind restore values for ' . $table . '.');
        }

        if (!$stmt->execute()) {
            $message = $stmt->error ?: $conn->error;
            $stmt->close();
            throw new RuntimeException('Could not restore row into ' . $table . ': ' . $message);
        }

        $stmt->close();
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'download_backup') {
    $tables = rl_backup_list_tables($conn);
    $payload = [
        'app' => 'RewardLedger',
        'backup_version' => 1,
        'generated_at' => gmdate('c'),
        'database' => $DB_NAME,
        'tables' => [],
    ];

    $versionFile = __DIR__ . '/VERSION';
    if (is_file($versionFile)) {
        $payload['app_version'] = trim((string)file_get_contents($versionFile));
    }

    foreach ($tables as $table) {
        $payload['tables'][$table] = rl_backup_fetch_rows($conn, $table);
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo 'Could not generate backup JSON.';
        exit;
    }

    $filename = 'rewardledger_backup_' . gmdate('Y-m-d_H-i-s') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

if ($action === 'restore_backup') {
    $confirmText = strtoupper(trim((string)($_POST['confirm_text'] ?? '')));
    $confirmStage = (string)($_POST['confirm_stage'] ?? '');

    if ($confirmStage !== 'confirmed' || $confirmText !== 'RESTORE') {
        header('Location: index.php?page=settings&tab=other&error=' . rawurlencode('Restore canceled. Type RESTORE in the final confirmation to continue.'));
        exit;
    }

    if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file']) || (int)($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        header('Location: index.php?page=settings&tab=other&error=' . rawurlencode('Please choose a valid RewardLedger backup file to restore.'));
        exit;
    }

    $raw = (string)file_get_contents((string)$_FILES['backup_file']['tmp_name']);
    $decoded = json_decode($raw, true);

    if (!is_array($decoded) || empty($decoded['tables']) || !is_array($decoded['tables'])) {
        header('Location: index.php?page=settings&tab=other&error=' . rawurlencode('The selected file is not a valid RewardLedger backup JSON file.'));
        exit;
    }

    $existingTables = rl_backup_list_tables($conn);
    $backupTables = array_keys($decoded['tables']);
    $tablesToRestore = array_values(array_intersect($existingTables, $backupTables));

    if (empty($tablesToRestore)) {
        header('Location: index.php?page=settings&tab=other&error=' . rawurlencode('No matching tables were found in the backup file.'));
        exit;
    }

    try {
        $conn->begin_transaction();
        $conn->query('SET FOREIGN_KEY_CHECKS=0');

        rl_restore_delete_all($conn, array_reverse($existingTables));

        foreach ($tablesToRestore as $table) {
            $rows = $decoded['tables'][$table] ?? [];
            if (!is_array($rows)) {
                $rows = [];
            }
            rl_restore_insert_rows($conn, $table, $rows);
        }

        $conn->query('SET FOREIGN_KEY_CHECKS=1');
        $conn->commit();
        header('Location: index.php?page=settings&tab=other&success=' . rawurlencode('Backup restored successfully.'));
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->query('SET FOREIGN_KEY_CHECKS=1');
        header('Location: index.php?page=settings&tab=other&error=' . rawurlencode('Restore failed: ' . $e->getMessage()));
        exit;
    }
}

http_response_code(400);
echo 'Invalid backup/restore action.';
