<?php

const SCHEMA_VERSION = '1.2.0';

function table_exists(mysqli $conn, string $table): bool
{
    $table_esc = $conn->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '{$table_esc}'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $table_esc = $conn->real_escape_string($table);
    $column_esc = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$table_esc}` LIKE '{$column_esc}'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function index_exists(mysqli $conn, string $table, string $index): bool
{
    $table_esc = $conn->real_escape_string($table);
    $index_esc = $conn->real_escape_string($index);
    $sql = "SHOW INDEX FROM `{$table_esc}` WHERE Key_name = '{$index_esc}'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function add_column_if_missing(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!table_exists($conn, $table)) {
        return;
    }

    if (!column_exists($conn, $table, $column)) {
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        if (!$conn->query($sql)) {
            throw new Exception("Failed adding column {$table}.{$column}: " . $conn->error);
        }
    }
}

function add_index_if_missing(mysqli $conn, string $table, string $index, string $index_sql): void
{
    if (!table_exists($conn, $table)) {
        return;
    }

    if (!index_exists($conn, $table, $index)) {
        if (!$conn->query($index_sql)) {
            throw new Exception("Failed adding index {$index} on {$table}: " . $conn->error);
        }
    }
}

function get_schema_version(mysqli $conn): string
{
    if (!table_exists($conn, 'settings')) {
        return '0.0.0';
    }

    $stmt = $conn->prepare("
        SELECT setting_value
        FROM settings
        WHERE setting_key = 'schema_version'
        LIMIT 1
    ");

    if (!$stmt) {
        return '0.0.0';
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? (string)$row['setting_value'] : '0.0.0';
}

function set_schema_version(mysqli $conn, string $version): void
{
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('schema_version', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    if (!$stmt) {
        throw new Exception("Failed setting schema_version: " . $conn->error);
    }

    $stmt->bind_param("s", $version);
    $stmt->execute();
    $stmt->close();
}

function lookup_id(mysqli $conn, string $table, string $name_col, string $value): int
{
    $sql = "SELECT id FROM `{$table}` WHERE `{$name_col}` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("s", $value);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['id'] : 0;
}

function ensure_schema(mysqli $conn): void
{
    /*
    ------------------------------------------------
    CREATE TABLES IF MISSING
    ------------------------------------------------
    */

    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $current_version = get_schema_version($conn);

    $conn->query("
        CREATE TABLE IF NOT EXISTS apps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_name VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS miners (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            miner_name VARCHAR(120) NOT NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS assets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_name VARCHAR(50) NOT NULL,
            asset_symbol VARCHAR(20) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            category_name VARCHAR(120) NOT NULL,
            behavior_type VARCHAR(40) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            dashboard_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            account_name VARCHAR(120) NOT NULL,
            account_type VARCHAR(50) NULL,
            account_identifier VARCHAR(255) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS referrals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_name VARCHAR(120) NOT NULL,
            referral_identifier VARCHAR(255) NULL,
            account_id INT UNSIGNED NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS batches (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            batch_date DATE NOT NULL,
            title VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS batch_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id INT UNSIGNED NOT NULL,
            miner_id INT UNSIGNED NULL,
            asset_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            referral_id INT UNSIGNED NULL,
            from_account_id INT UNSIGNED NULL,
            to_account_id INT UNSIGNED NULL,
            amount DECIMAL(20,8) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS templates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            template_name VARCHAR(150) NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS template_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id INT UNSIGNED NOT NULL,
            miner_id INT UNSIGNED NULL,
            asset_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            referral_id INT UNSIGNED NULL,
            from_account_id INT UNSIGNED NULL,
            to_account_id INT UNSIGNED NULL,
            amount DECIMAL(20,8) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            show_miner TINYINT(1) NOT NULL DEFAULT 1,
            show_asset TINYINT(1) NOT NULL DEFAULT 1,
            show_category TINYINT(1) NOT NULL DEFAULT 1,
            show_referral TINYINT(1) NOT NULL DEFAULT 0,
            show_amount TINYINT(1) NOT NULL DEFAULT 1,
            show_notes TINYINT(1) NOT NULL DEFAULT 1,
            show_from_account TINYINT(1) NOT NULL DEFAULT 0,
            show_to_account TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS quick_add_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NOT NULL,
            quick_add_name VARCHAR(150) NOT NULL,
            miner_id INT UNSIGNED NULL,
            asset_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            referral_id INT UNSIGNED NULL,
            from_account_id INT UNSIGNED NULL,
            to_account_id INT UNSIGNED NULL,
            amount DECIMAL(20,8) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            show_miner TINYINT(1) NOT NULL DEFAULT 0,
            show_asset TINYINT(1) NOT NULL DEFAULT 1,
            show_category TINYINT(1) NOT NULL DEFAULT 1,
            show_referral TINYINT(1) NOT NULL DEFAULT 0,
            show_amount TINYINT(1) NOT NULL DEFAULT 1,
            show_notes TINYINT(1) NOT NULL DEFAULT 0,
            show_from_account TINYINT(1) NOT NULL DEFAULT 0,
            show_to_account TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    ------------------------------------------------
    COLUMN REPAIRS
    ------------------------------------------------
    */

    add_column_if_missing($conn, 'apps', 'sort_order', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'apps', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'apps', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'miners', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'miners', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'miners', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'assets', 'asset_symbol', 'VARCHAR(20) NULL');
    add_column_if_missing($conn, 'assets', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'assets', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'categories', 'app_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'categories', 'behavior_type', 'VARCHAR(40) NULL');
    add_column_if_missing($conn, 'categories', 'sort_order', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'categories', 'dashboard_order', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'categories', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'categories', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'accounts', 'account_type', 'VARCHAR(50) NULL');
    add_column_if_missing($conn, 'accounts', 'account_identifier', 'VARCHAR(255) NULL');
    add_column_if_missing($conn, 'accounts', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'accounts', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'accounts', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'referrals', 'referral_identifier', 'VARCHAR(255) NULL');
    add_column_if_missing($conn, 'referrals', 'account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'referrals', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'referrals', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'referrals', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'batches', 'app_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batches', 'title', 'VARCHAR(255) NULL');
    add_column_if_missing($conn, 'batches', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'batches', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'batch_items', 'miner_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'asset_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'category_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'referral_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'from_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'to_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'batch_items', 'amount', 'DECIMAL(20,8) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'batch_items', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'batch_items', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'templates', 'app_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'templates', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'templates', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    add_column_if_missing($conn, 'template_items', 'miner_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'asset_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'category_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'referral_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'from_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'to_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'template_items', 'amount', 'DECIMAL(20,8) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'template_items', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'template_items', 'show_miner', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'template_items', 'show_asset', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'template_items', 'show_category', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'template_items', 'show_referral', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'template_items', 'show_amount', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'template_items', 'show_notes', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'template_items', 'show_from_account', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'template_items', 'show_to_account', 'TINYINT(1) NOT NULL DEFAULT 0');

    add_column_if_missing($conn, 'quick_add_items', 'app_id', 'INT UNSIGNED NOT NULL');
    add_column_if_missing($conn, 'quick_add_items', 'quick_add_name', 'VARCHAR(150) NOT NULL');
    add_column_if_missing($conn, 'quick_add_items', 'miner_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'asset_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'category_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'referral_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'from_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'to_account_id', 'INT UNSIGNED NULL');
    add_column_if_missing($conn, 'quick_add_items', 'amount', 'DECIMAL(20,8) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'notes', 'TEXT NULL');
    add_column_if_missing($conn, 'quick_add_items', 'show_miner', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'show_asset', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'quick_add_items', 'show_category', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'quick_add_items', 'show_referral', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'show_amount', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'quick_add_items', 'show_notes', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'show_from_account', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'show_to_account', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'sort_order', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($conn, 'quick_add_items', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($conn, 'quick_add_items', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    /*
    ------------------------------------------------
    INDEX REPAIRS
    ------------------------------------------------
    */

    add_index_if_missing($conn, 'categories', 'idx_app', "ALTER TABLE `categories` ADD INDEX `idx_app` (`app_id`)");
    add_index_if_missing($conn, 'referrals', 'idx_account', "ALTER TABLE `referrals` ADD INDEX `idx_account` (`account_id`)");
    add_index_if_missing($conn, 'batches', 'idx_app', "ALTER TABLE `batches` ADD INDEX `idx_app` (`app_id`)");
    add_index_if_missing($conn, 'batch_items', 'idx_batch', "ALTER TABLE `batch_items` ADD INDEX `idx_batch` (`batch_id`)");
    add_index_if_missing($conn, 'batch_items', 'idx_miner', "ALTER TABLE `batch_items` ADD INDEX `idx_miner` (`miner_id`)");
    add_index_if_missing($conn, 'batch_items', 'idx_asset', "ALTER TABLE `batch_items` ADD INDEX `idx_asset` (`asset_id`)");
    add_index_if_missing($conn, 'batch_items', 'idx_category', "ALTER TABLE `batch_items` ADD INDEX `idx_category` (`category_id`)");
    add_index_if_missing($conn, 'templates', 'idx_app', "ALTER TABLE `templates` ADD INDEX `idx_app` (`app_id`)");
    add_index_if_missing($conn, 'template_items', 'idx_template', "ALTER TABLE `template_items` ADD INDEX `idx_template` (`template_id`)");
    add_index_if_missing($conn, 'quick_add_items', 'idx_app', "ALTER TABLE `quick_add_items` ADD INDEX `idx_app` (`app_id`)");
    add_index_if_missing($conn, 'quick_add_items', 'idx_category', "ALTER TABLE `quick_add_items` ADD INDEX `idx_category` (`category_id`)");
    add_index_if_missing($conn, 'quick_add_items', 'idx_active', "ALTER TABLE `quick_add_items` ADD INDEX `idx_active` (`is_active`)");

    /*
    ------------------------------------------------
    MIGRATIONS
    ------------------------------------------------
    */

    if (version_compare($current_version, '1.2.0', '<')) {
        add_column_if_missing($conn, 'categories', 'dashboard_order', 'INT NOT NULL DEFAULT 0');

        $conn->query("
            CREATE TABLE IF NOT EXISTS quick_add_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                app_id INT UNSIGNED NOT NULL,
                quick_add_name VARCHAR(150) NOT NULL,
                miner_id INT UNSIGNED NULL,
                asset_id INT UNSIGNED NULL,
                category_id INT UNSIGNED NULL,
                referral_id INT UNSIGNED NULL,
                from_account_id INT UNSIGNED NULL,
                to_account_id INT UNSIGNED NULL,
                amount DECIMAL(20,8) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                show_miner TINYINT(1) NOT NULL DEFAULT 0,
                show_asset TINYINT(1) NOT NULL DEFAULT 1,
                show_category TINYINT(1) NOT NULL DEFAULT 1,
                show_referral TINYINT(1) NOT NULL DEFAULT 0,
                show_amount TINYINT(1) NOT NULL DEFAULT 1,
                show_notes TINYINT(1) NOT NULL DEFAULT 0,
                show_from_account TINYINT(1) NOT NULL DEFAULT 0,
                show_to_account TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        set_schema_version($conn, '1.2.0');
    }

    /*
    ------------------------------------------------
    DEFAULT SETTINGS
    ------------------------------------------------
    */

	if (get_setting($conn, 'setup_complete', '') === '') {
		set_setting($conn, 'setup_complete', '0');
	}

    /*
    ------------------------------------------------
    DEFAULT DATA
    ------------------------------------------------
    Only seed when table is empty.
    ------------------------------------------------
    */

    $res = $conn->query("SELECT COUNT(*) AS c FROM apps");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {
        $defaults = [
            ['GoMining', 10],
            ['Atlas Earth', 20],
            ['Mode Earn', 30],
        ];

        $stmt = $conn->prepare("
            INSERT INTO apps (app_name, sort_order, is_active)
            VALUES (?, ?, 1)
        ");

        if ($stmt) {
            foreach ($defaults as $a) {
                $stmt->bind_param("si", $a[0], $a[1]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $gomining_id = lookup_id($conn, 'apps', 'app_name', 'GoMining');

    $res = $conn->query("SELECT COUNT(*) AS c FROM assets");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {
        $defaults = [
            ['Bitcoin', 'BTC'],
            ['GoMining Token', 'GMT'],
            ['Binance Coin', 'BNB'],
            ['Ethereum', 'ETH'],
            ['Tether USD', 'USDT'],
            ['USD Dollar', 'USD'],
            ['Cash', 'CASH'],
        ];

        $stmt = $conn->prepare("
            INSERT INTO assets (asset_name, asset_symbol, is_active)
            VALUES (?, ?, 1)
        ");

        if ($stmt) {
            foreach ($defaults as $a) {
                $stmt->bind_param("ss", $a[0], $a[1]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $res = $conn->query("SELECT COUNT(*) AS c FROM categories");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {
        $app_ids = [];
        $res_apps = $conn->query("SELECT id, app_name FROM apps");

        if ($res_apps) {
            while ($r = $res_apps->fetch_assoc()) {
                $app_ids[$r['app_name']] = (int)$r['id'];
            }
        }

        $defaults = [];

        if (!empty($app_ids['GoMining'])) {
            $defaults[] = [$app_ids['GoMining'], 'Referral Bonus', 'income', 10];
            $defaults[] = [$app_ids['GoMining'], 'veGoMining Reward', 'income', 20];
            $defaults[] = [$app_ids['GoMining'], 'Daily Gross Rewards', 'income', 30];
            $defaults[] = [$app_ids['GoMining'], 'Daily Net Rewards', 'income', 40];
            $defaults[] = [$app_ids['GoMining'], 'Daily Maintenance', 'expense', 50];
            $defaults[] = [$app_ids['GoMining'], 'Daily Electricity', 'expense', 60];
            $defaults[] = [$app_ids['GoMining'], 'Bounty Rewards', 'income', 70];
        }

        if (!empty($app_ids['Atlas Earth'])) {
            $defaults[] = [$app_ids['Atlas Earth'], 'Explorer Club', 'expense', 10];
            $defaults[] = [$app_ids['Atlas Earth'], 'Monthly Reward Ladder Premium', 'expense', 20];
            $defaults[] = [$app_ids['Atlas Earth'], 'AB Purchase', 'expense', 30];
            $defaults[] = [$app_ids['Atlas Earth'], 'Cash Out', 'withdrawal', 40];
        }

        $stmt = $conn->prepare("
            INSERT INTO categories (app_id, category_name, behavior_type, sort_order, dashboard_order, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        if ($stmt) {
            foreach ($defaults as $i => $c) {
                $dashboard_order = ($i + 1) * 10;
                $stmt->bind_param("issii", $c[0], $c[1], $c[2], $c[3], $dashboard_order);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    if ($gomining_id > 0) {
        $stmt = $conn->prepare("
            UPDATE categories
            SET app_id = ?
            WHERE app_id IS NULL
        ");

        if ($stmt) {
            $stmt->bind_param("i", $gomining_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $res = $conn->query("SELECT COUNT(*) AS c FROM accounts");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {
        $defaults = [
            ['Cold Wallet', 'Wallet', '', 'Long term cold storage wallet'],
            ['GoMining BTC', 'Platform Wallet', '', 'BTC rewards held in GoMining'],
            ['GoMining GMT', 'Platform Wallet', '', 'GMT rewards held in GoMining'],
            ['Cash', 'Cash', '', 'Manual fiat or cash balance'],
        ];

        $stmt = $conn->prepare("
            INSERT INTO accounts (account_name, account_type, account_identifier, notes, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");

        if ($stmt) {
            foreach ($defaults as $a) {
                $stmt->bind_param("ssss", $a[0], $a[1], $a[2], $a[3]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
	    /*
    ------------------------------------------------
    DEFAULT QUICK ADD ITEMS
    ------------------------------------------------
    Insert only if table is empty.
    ------------------------------------------------
    */
    $res = $conn->query("SELECT COUNT(*) AS c FROM quick_add_items");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {

        $gomining_id = lookup_id($conn, 'apps', 'app_name', 'GoMining');
        $atlas_id    = lookup_id($conn, 'apps', 'app_name', 'Atlas Earth');

        $items = [
            // GoMining
            [$gomining_id, 'Daily Gross Rewards - BTC', '', 'Bitcoin', 'Daily Gross Rewards', '', '', 'GoMining BTC', 0, '', 1,1,1,0,1,0,0,1,10],
            [$gomining_id, 'Daily Gross Rewards - GMT', '', 'GoMining Token', 'Daily Gross Rewards', '', '', 'GoMining GMT', 0, '', 1,1,1,0,1,0,0,1,20],
            [$gomining_id, 'Daily Net Rewards - BTC', '', 'Bitcoin', 'Daily Net Rewards', '', '', 'GoMining BTC', 0, '', 1,1,1,0,1,0,0,1,30],
            [$gomining_id, 'Daily Net Rewards - GMT', '', 'GoMining Token', 'Daily Net Rewards', '', '', 'GoMining GMT', 0, '', 1,1,1,0,1,0,0,1,40],
            [$gomining_id, 'Daily Maintenance - BTC', '', 'Bitcoin', 'Daily Maintenance', '', 'GoMining BTC', '', 0, '', 1,1,1,0,1,0,1,0,50],
            [$gomining_id, 'Daily Maintenance - GMT', '', 'GoMining Token', 'Daily Maintenance', '', 'GoMining GMT', '', 0, '', 1,1,1,0,1,0,1,0,60],
            [$gomining_id, 'Daily Electricity - BTC', '', 'Bitcoin', 'Daily Electricity', '', 'GoMining BTC', '', 0, '', 1,1,1,0,1,0,1,0,70],
            [$gomining_id, 'Daily Electricity - GMT', '', 'GoMining Token', 'Daily Electricity', '', 'GoMining GMT', '', 0, '', 1,1,1,0,1,0,1,0,80],
            [$gomining_id, 'Referral Bonus - GMT', '', 'GoMining Token', 'Referral Bonus', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,90],
            [$gomining_id, 'veGoMining Reward - GMT', '', 'GoMining Token', 'veGoMining Reward', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,100],
            [$gomining_id, 'Bounty Reward - GMT', '', 'GoMining Token', 'Bounty Rewards', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,110],

            // Atlas Earth
            [$atlas_id, 'Monthly Reward Ladder Premium', '', 'Cash', 'Monthly Reward Ladder Premium', '', 'Cash', '', 14.99, '', 0,1,1,0,1,0,1,0,10],
            [$atlas_id, 'Explorer Club', '', 'Cash', 'Explorer Club', '', 'Cash', '', 49.99, '', 0,1,1,0,1,0,1,0,20],
            [$atlas_id, 'Cash Out', '', 'Cash', 'Cash Out', '', '', 'Cash', 0, '', 0,1,1,0,1,0,0,1,30],
        ];

        $stmt = $conn->prepare("
            INSERT INTO quick_add_items (
                app_id,
                quick_add_name,
                miner_id,
                asset_id,
                category_id,
                referral_id,
                from_account_id,
                to_account_id,
                amount,
                notes,
                show_miner,
                show_asset,
                show_category,
                show_referral,
                show_amount,
                show_notes,
                show_from_account,
                show_to_account,
                sort_order,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        if ($stmt) {
            foreach ($items as $item) {
                [
                    $app_id,
                    $quick_add_name,
                    $miner_name,
                    $asset_name,
                    $category_name,
                    $referral_name,
                    $from_account_name,
                    $to_account_name,
                    $amount,
                    $notes,
                    $show_miner,
                    $show_asset,
                    $show_category,
                    $show_referral,
                    $show_amount,
                    $show_notes,
                    $show_from_account,
                    $show_to_account,
                    $sort_order
                ] = $item;

                $miner_id = $miner_name !== '' ? lookup_id($conn, 'miners', 'miner_name', $miner_name) : 0;
                $asset_id = $asset_name !== '' ? lookup_id($conn, 'assets', 'asset_name', $asset_name) : 0;
                $category_id = $category_name !== '' ? lookup_id($conn, 'categories', 'category_name', $category_name) : 0;
                $referral_id = $referral_name !== '' ? lookup_id($conn, 'referrals', 'referral_name', $referral_name) : 0;
                $from_account_id = $from_account_name !== '' ? lookup_id($conn, 'accounts', 'account_name', $from_account_name) : 0;
                $to_account_id = $to_account_name !== '' ? lookup_id($conn, 'accounts', 'account_name', $to_account_name) : 0;

                $stmt->bind_param(
                    "isiiiiiidsiiiiiiiii",
                    $app_id,
                    $quick_add_name,
                    $miner_id,
                    $asset_id,
                    $category_id,
                    $referral_id,
                    $from_account_id,
                    $to_account_id,
                    $amount,
                    $notes,
                    $show_miner,
                    $show_asset,
                    $show_category,
                    $show_referral,
                    $show_amount,
                    $show_notes,
                    $show_from_account,
                    $show_to_account,
                    $sort_order
                );
                $stmt->execute();
            }

            $stmt->close();
        }
    }
}