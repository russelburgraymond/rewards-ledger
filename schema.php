<?php
// schema.php
// Rebuilt from the user's phpMyAdmin SQL dump (test_007_rewardsledger, exported 2026-03-24).
// Safe, additive-only schema installer/upgrader for RewardLedger.

if (!function_exists('rl_exec')) {
    function rl_exec(mysqli $conn, string $sql): void {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Schema query failed: ' . $conn->error . "\nSQL: " . $sql);
        }
    }
}

if (!function_exists('rl_maybe_exec')) {
    function rl_maybe_exec(mysqli $conn, string $sql): void {
        $conn->query($sql);
    }
}

if (!function_exists('rl_table_exists')) {
    function rl_table_exists(mysqli $conn, string $table): bool {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return ($res instanceof mysqli_result) && ($res->num_rows > 0);
    }
}

if (!function_exists('rl_column_exists')) {
    function rl_column_exists(mysqli $conn, string $table, string $column): bool {
        if (!rl_table_exists($conn, $table)) {
            return false;
        }
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return ($res instanceof mysqli_result) && ($res->num_rows > 0);
    }
}

if (!function_exists('rl_index_exists')) {
    function rl_index_exists(mysqli $conn, string $table, string $indexName): bool {
        if (!rl_table_exists($conn, $table)) {
            return false;
        }
        $safeTable = $conn->real_escape_string($table);
        $safeIndex = $conn->real_escape_string($indexName);
        $res = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        return ($res instanceof mysqli_result) && ($res->num_rows > 0);
    }
}

if (!function_exists('rl_add_column')) {
    function rl_add_column(mysqli $conn, string $table, string $column, string $definition): void {
        if (!rl_column_exists($conn, $table, $column)) {
            rl_exec($conn, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

if (!function_exists('rl_add_index')) {
    function rl_add_index(mysqli $conn, string $table, string $indexName, string $definitionSql): void {
        if (!rl_index_exists($conn, $table, $indexName)) {
            rl_exec($conn, "ALTER TABLE `{$table}` ADD {$definitionSql}");
        }
    }
}

if (!function_exists('rl_table_row_count')) {
    function rl_table_row_count(mysqli $conn, string $table): int {
        if (!rl_table_exists($conn, $table)) {
            return 0;
        }
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM `{$table}`");
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('rl_setting_exists')) {
    function rl_setting_exists(mysqli $conn, string $key): bool {
        if (!rl_table_exists($conn, 'settings')) {
            return false;
        }
        $stmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('rl_insert_setting_if_missing')) {
    function rl_insert_setting_if_missing(mysqli $conn, string $key, string $value): void {
        if (!rl_setting_exists($conn, $key)) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Failed preparing settings insert: ' . $conn->error);
            }
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('rl_upsert_schema_version')) {
    function rl_upsert_schema_version(mysqli $conn, string $version): void {
        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
        );
        if (!$stmt) {
            throw new RuntimeException('Failed preparing schema_version upsert: ' . $conn->error);
        }
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ensure_schema')) {
    function ensure_schema(mysqli $conn): void {
        $schemaVersion = '2.1.3';

        // accounts
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `accounts` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `account_name` varchar(150) NOT NULL,
          `account_type` varchar(100) NOT NULL DEFAULT '',
          `account_identifier` varchar(150) NOT NULL DEFAULT '',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        rl_add_column($conn, 'accounts', 'account_name', "varchar(150) NOT NULL DEFAULT ''");
        rl_add_column($conn, 'accounts', 'account_type', "varchar(100) NOT NULL DEFAULT ''");
        rl_add_column($conn, 'accounts', 'account_identifier', "varchar(150) NOT NULL DEFAULT ''");
        rl_add_column($conn, 'accounts', 'notes', 'text DEFAULT NULL');
        rl_add_column($conn, 'accounts', 'is_active', "tinyint(1) NOT NULL DEFAULT '1'");
        rl_add_column($conn, 'accounts', 'sort_order', "int NOT NULL DEFAULT '0'");
        rl_add_column($conn, 'accounts', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'accounts', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        rl_add_index($conn, 'accounts', 'idx_accounts_name', 'KEY `idx_accounts_name` (`account_name`)');
        rl_add_index($conn, 'accounts', 'idx_accounts_active', 'KEY `idx_accounts_active` (`is_active`)');
        rl_add_index($conn, 'accounts', 'idx_accounts_sort', 'KEY `idx_accounts_sort` (`sort_order`)');

        // apps
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `apps` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `app_name` varchar(120) NOT NULL,
          `sort_order` int NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        rl_add_column($conn, 'apps', 'app_name', 'varchar(120) NOT NULL');
        rl_add_column($conn, 'apps', 'sort_order', "int NOT NULL DEFAULT '0'");
        rl_add_column($conn, 'apps', 'is_active', "tinyint(1) NOT NULL DEFAULT '1'");
        rl_add_column($conn, 'apps', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'apps', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        rl_add_index($conn, 'apps', 'idx_apps_name', 'KEY `idx_apps_name` (`app_name`)');
        rl_add_index($conn, 'apps', 'idx_apps_sort', 'KEY `idx_apps_sort` (`sort_order`)');
        rl_add_index($conn, 'apps', 'idx_apps_active', 'KEY `idx_apps_active` (`is_active`)');

        // assets
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `assets` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `asset_name` varchar(120) NOT NULL DEFAULT '',
          `asset_symbol` varchar(40) NOT NULL DEFAULT '',
          `currency_symbol` varchar(10) NOT NULL DEFAULT '',
          `display_decimals` int NOT NULL DEFAULT '8',
          `is_fiat` tinyint(1) NOT NULL DEFAULT '0',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `name` varchar(120) NOT NULL DEFAULT '',
          `symbol` varchar(40) NOT NULL DEFAULT '',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'asset_name' => "varchar(120) NOT NULL DEFAULT ''",
            'asset_symbol' => "varchar(40) NOT NULL DEFAULT ''",
            'currency_symbol' => "varchar(10) NOT NULL DEFAULT ''",
            'display_decimals' => "int NOT NULL DEFAULT '8'",
            'is_fiat' => "tinyint(1) NOT NULL DEFAULT '0'",
            'notes' => 'text DEFAULT NULL',
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'name' => "varchar(120) NOT NULL DEFAULT ''",
            'symbol' => "varchar(40) NOT NULL DEFAULT ''",
        ] as $col => $def) {
            rl_add_column($conn, 'assets', $col, $def);
        }
        rl_add_index($conn, 'assets', 'idx_assets_name', 'KEY `idx_assets_name` (`asset_name`)');
        rl_add_index($conn, 'assets', 'idx_assets_symbol', 'KEY `idx_assets_symbol` (`asset_symbol`)');
        rl_add_index($conn, 'assets', 'idx_assets_active', 'KEY `idx_assets_active` (`is_active`)');
        rl_add_index($conn, 'assets', 'idx_assets_sort', 'KEY `idx_assets_sort` (`sort_order`)');

        // batches
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `batches` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `app_id` int UNSIGNED NOT NULL DEFAULT '0',
          `template_id` int UNSIGNED DEFAULT NULL,
          `title` varchar(150) NOT NULL DEFAULT '',
          `batch_date` date DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `status` varchar(30) NOT NULL DEFAULT 'open',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'app_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'template_id' => 'int UNSIGNED DEFAULT NULL',
            'title' => "varchar(150) NOT NULL DEFAULT ''",
            'batch_date' => 'date DEFAULT NULL',
            'notes' => 'text DEFAULT NULL',
            'status' => "varchar(30) NOT NULL DEFAULT 'open'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'batches', $col, $def);
        }
        rl_add_index($conn, 'batches', 'idx_batches_app', 'KEY `idx_batches_app` (`app_id`)');
        rl_add_index($conn, 'batches', 'idx_batches_template', 'KEY `idx_batches_template` (`template_id`)');
        rl_add_index($conn, 'batches', 'idx_batches_date', 'KEY `idx_batches_date` (`batch_date`)');
        rl_add_index($conn, 'batches', 'idx_batches_status', 'KEY `idx_batches_status` (`status`)');

        // batch_items
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `batch_items` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `batch_id` int UNSIGNED NOT NULL DEFAULT '0',
          `template_item_id` int UNSIGNED DEFAULT NULL,
          `miner_id` int UNSIGNED NOT NULL DEFAULT '0',
          `asset_id` int UNSIGNED NOT NULL DEFAULT '0',
          `category_id` int UNSIGNED NOT NULL DEFAULT '0',
          `referral_id` int UNSIGNED NOT NULL DEFAULT '0',
          `from_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `to_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
          `received_time` time DEFAULT NULL,
          `value_at_receipt` decimal(18,8) DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `import_source_type` varchar(50) NOT NULL DEFAULT '',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'batch_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'template_item_id' => 'int UNSIGNED DEFAULT NULL',
            'miner_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'asset_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'category_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'referral_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'from_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'to_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'amount' => "decimal(18,8) NOT NULL DEFAULT '0.00000000'",
            'received_time' => 'time DEFAULT NULL',
            'value_at_receipt' => "decimal(18,8) DEFAULT NULL",
            'notes' => 'text DEFAULT NULL',
            'import_source_type' => "varchar(50) NOT NULL DEFAULT ''",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'batch_items', $col, $def);
        }
        foreach ([
            'idx_bi_batch' => 'KEY `idx_bi_batch` (`batch_id`)',
            'idx_bi_template_item' => 'KEY `idx_bi_template_item` (`template_item_id`)',
            'idx_bi_miner' => 'KEY `idx_bi_miner` (`miner_id`)',
            'idx_bi_asset' => 'KEY `idx_bi_asset` (`asset_id`)',
            'idx_bi_category' => 'KEY `idx_bi_category` (`category_id`)',
            'idx_bi_referral' => 'KEY `idx_bi_referral` (`referral_id`)',
            'idx_bi_from_account' => 'KEY `idx_bi_from_account` (`from_account_id`)',
            'idx_bi_to_account' => 'KEY `idx_bi_to_account` (`to_account_id`)',
            'idx_bi_import_source_type' => 'KEY `idx_bi_import_source_type` (`import_source_type`)',
        ] as $name => $def) {
            rl_add_index($conn, 'batch_items', $name, $def);
        }

        // batch_lines
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `batch_lines` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `batch_id` int UNSIGNED NOT NULL DEFAULT '0',
          `template_item_id` int UNSIGNED DEFAULT NULL,
          `category_id` int UNSIGNED DEFAULT NULL,
          `asset_id` int UNSIGNED DEFAULT NULL,
          `miner_id` int UNSIGNED DEFAULT NULL,
          `line_label` varchar(150) NOT NULL DEFAULT '',
          `description` varchar(255) NOT NULL DEFAULT '',
          `amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
          `notes` text DEFAULT NULL,
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'batch_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'template_item_id' => 'int UNSIGNED DEFAULT NULL',
            'category_id' => 'int UNSIGNED DEFAULT NULL',
            'asset_id' => 'int UNSIGNED DEFAULT NULL',
            'miner_id' => 'int UNSIGNED DEFAULT NULL',
            'line_label' => "varchar(150) NOT NULL DEFAULT ''",
            'description' => "varchar(255) NOT NULL DEFAULT ''",
            'amount' => "decimal(18,8) NOT NULL DEFAULT '0.00000000'",
            'notes' => 'text DEFAULT NULL',
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'batch_lines', $col, $def);
        }
        foreach ([
            'idx_bl_batch' => 'KEY `idx_bl_batch` (`batch_id`)',
            'idx_bl_template_item' => 'KEY `idx_bl_template_item` (`template_item_id`)',
            'idx_bl_category' => 'KEY `idx_bl_category` (`category_id`)',
            'idx_bl_asset' => 'KEY `idx_bl_asset` (`asset_id`)',
            'idx_bl_miner' => 'KEY `idx_bl_miner` (`miner_id`)',
            'idx_bl_sort' => 'KEY `idx_bl_sort` (`sort_order`)',
        ] as $name => $def) {
            rl_add_index($conn, 'batch_lines', $name, $def);
        }

        // categories
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `categories` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `app_id` int UNSIGNED NOT NULL DEFAULT '0',
          `category_name` varchar(120) NOT NULL DEFAULT '',
          `behavior_type` varchar(30) NOT NULL DEFAULT 'income',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `dashboard_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `name` varchar(120) NOT NULL DEFAULT '',
          `dashboard_row` int NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'app_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'category_name' => "varchar(120) NOT NULL DEFAULT ''",
            'behavior_type' => "varchar(30) NOT NULL DEFAULT 'income'",
            'notes' => 'text DEFAULT NULL',
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'dashboard_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'name' => "varchar(120) NOT NULL DEFAULT ''",
            'dashboard_row' => "int NOT NULL DEFAULT '1'",
        ] as $col => $def) {
            rl_add_column($conn, 'categories', $col, $def);
        }
        foreach ([
            'idx_categories_app' => 'KEY `idx_categories_app` (`app_id`)',
            'idx_categories_name' => 'KEY `idx_categories_name` (`category_name`)',
            'idx_categories_active' => 'KEY `idx_categories_active` (`is_active`)',
            'idx_categories_sort' => 'KEY `idx_categories_sort` (`sort_order`)',
            'idx_categories_dashboard' => 'KEY `idx_categories_dashboard` (`dashboard_order`)',
            'idx_categories_dashboard_row' => 'KEY `idx_categories_dashboard_row` (`dashboard_row`)',
        ] as $name => $def) {
            rl_add_index($conn, 'categories', $name, $def);
        }

        // miners
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `miners` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `miner_name` varchar(120) NOT NULL DEFAULT '',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `name` varchar(120) NOT NULL DEFAULT '',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'miner_name' => "varchar(120) NOT NULL DEFAULT ''",
            'notes' => 'text DEFAULT NULL',
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'name' => "varchar(120) NOT NULL DEFAULT ''",
        ] as $col => $def) {
            rl_add_column($conn, 'miners', $col, $def);
        }
        rl_add_index($conn, 'miners', 'idx_miners_name', 'KEY `idx_miners_name` (`miner_name`)');
        rl_add_index($conn, 'miners', 'idx_miners_active', 'KEY `idx_miners_active` (`is_active`)');
        rl_add_index($conn, 'miners', 'idx_miners_sort', 'KEY `idx_miners_sort` (`sort_order`)');

        // quick_add_items
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `quick_add_items` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `app_id` int UNSIGNED NOT NULL DEFAULT '0',
          `quick_add_name` varchar(150) NOT NULL DEFAULT '',
          `miner_id` int UNSIGNED NOT NULL DEFAULT '0',
          `asset_id` int UNSIGNED NOT NULL DEFAULT '0',
          `category_id` int UNSIGNED NOT NULL DEFAULT '0',
          `referral_id` int UNSIGNED NOT NULL DEFAULT '0',
          `from_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `to_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
          `notes` text DEFAULT NULL,
          `show_miner` tinyint(1) NOT NULL DEFAULT '1',
          `show_asset` tinyint(1) NOT NULL DEFAULT '1',
          `show_category` tinyint(1) NOT NULL DEFAULT '1',
          `show_referral` tinyint(1) NOT NULL DEFAULT '0',
          `show_amount` tinyint(1) NOT NULL DEFAULT '1',
          `show_notes` tinyint(1) NOT NULL DEFAULT '1',
          `show_received_time` tinyint(1) NOT NULL DEFAULT '1',
          `show_value_at_receipt` tinyint(1) NOT NULL DEFAULT '1',
          `show_from_account` tinyint(1) NOT NULL DEFAULT '0',
          `show_to_account` tinyint(1) NOT NULL DEFAULT '0',
          `is_multi_add` tinyint(1) NOT NULL DEFAULT '0',
          `sort_order` int NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'app_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'quick_add_name' => "varchar(150) NOT NULL DEFAULT ''",
            'miner_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'asset_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'category_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'referral_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'from_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'to_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'amount' => "decimal(18,8) NOT NULL DEFAULT '0.00000000'",
            'notes' => 'text DEFAULT NULL',
            'show_miner' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_asset' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_category' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_referral' => "tinyint(1) NOT NULL DEFAULT '0'",
            'show_amount' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_notes' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_received_time' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_value_at_receipt' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_from_account' => "tinyint(1) NOT NULL DEFAULT '0'",
            'show_to_account' => "tinyint(1) NOT NULL DEFAULT '0'",
            'is_multi_add' => "tinyint(1) NOT NULL DEFAULT '0'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'quick_add_items', $col, $def);
        }
        foreach ([
            'idx_qa_app' => 'KEY `idx_qa_app` (`app_id`)',
            'idx_qa_name' => 'KEY `idx_qa_name` (`quick_add_name`)',
            'idx_qa_category' => 'KEY `idx_qa_category` (`category_id`)',
            'idx_qa_sort' => 'KEY `idx_qa_sort` (`sort_order`)',
            'idx_qa_active' => 'KEY `idx_qa_active` (`is_active`)',
        ] as $name => $def) {
            rl_add_index($conn, 'quick_add_items', $name, $def);
        }

        // referrals
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `referrals` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `referral_name` varchar(150) NOT NULL,
          `referral_identifier` varchar(150) NOT NULL DEFAULT '',
          `account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'referral_name' => 'varchar(150) NOT NULL',
            'referral_identifier' => "varchar(150) NOT NULL DEFAULT ''",
            'account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'notes' => 'text DEFAULT NULL',
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'referrals', $col, $def);
        }
        foreach ([
            'idx_referrals_name' => 'KEY `idx_referrals_name` (`referral_name`)',
            'idx_referrals_account' => 'KEY `idx_referrals_account` (`account_id`)',
            'idx_referrals_active' => 'KEY `idx_referrals_active` (`is_active`)',
            'idx_referrals_sort' => 'KEY `idx_referrals_sort` (`sort_order`)',
        ] as $name => $def) {
            rl_add_index($conn, 'referrals', $name, $def);
        }

        // settings
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `settings` (
          `setting_key` varchar(100) NOT NULL,
          `setting_value` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        rl_add_column($conn, 'settings', 'setting_value', 'text DEFAULT NULL');
        rl_add_column($conn, 'settings', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'settings', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        // ======================================================
        // [SCHEMA] CUSTOM DASHBOARD TILES
        // ======================================================
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `custom_dashboard_tiles` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `tile_name` varchar(150) NOT NULL,
          `dashboard_row` int NOT NULL DEFAULT '1',
          `dashboard_order` int NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'tile_name' => 'varchar(150) NOT NULL',
            'dashboard_row' => "int NOT NULL DEFAULT '1'",
            'dashboard_order' => "int NOT NULL DEFAULT '0'",
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'custom_dashboard_tiles', $col, $def);
        }
        foreach ([
            'idx_custom_dashboard_tiles_order' => 'KEY `idx_custom_dashboard_tiles_order` (`dashboard_row`,`dashboard_order`)',
            'idx_custom_dashboard_tiles_active' => 'KEY `idx_custom_dashboard_tiles_active` (`is_active`)',
        ] as $name => $def) {
            rl_add_index($conn, 'custom_dashboard_tiles', $name, $def);
        }

        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `custom_dashboard_tile_items` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `tile_id` int UNSIGNED NOT NULL,
          `category_id` int UNSIGNED NOT NULL,
          `operation` varchar(20) NOT NULL DEFAULT 'add',
          `amount_mode` varchar(20) NOT NULL DEFAULT 'absolute',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'tile_id' => 'int UNSIGNED NOT NULL',
            'category_id' => 'int UNSIGNED NOT NULL',
            'operation' => "varchar(20) NOT NULL DEFAULT 'add'",
            'amount_mode' => "varchar(20) NOT NULL DEFAULT 'absolute'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'custom_dashboard_tile_items', $col, $def);
        }
        foreach ([
            'idx_custom_dashboard_tile_items_tile' => 'KEY `idx_custom_dashboard_tile_items_tile` (`tile_id`)',
            'idx_custom_dashboard_tile_items_category' => 'KEY `idx_custom_dashboard_tile_items_category` (`category_id`)',
            'idx_custom_dashboard_tile_items_sort' => 'KEY `idx_custom_dashboard_tile_items_sort` (`tile_id`,`sort_order`)',
        ] as $name => $def) {
            rl_add_index($conn, 'custom_dashboard_tile_items', $name, $def);
        }

        // templates
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `templates` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `app_id` int UNSIGNED NOT NULL DEFAULT '0',
          `template_name` varchar(150) NOT NULL DEFAULT '',
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `name` varchar(150) NOT NULL DEFAULT '',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'app_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'template_name' => "varchar(150) NOT NULL DEFAULT ''",
            'notes' => 'text DEFAULT NULL',
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'name' => "varchar(150) NOT NULL DEFAULT ''",
        ] as $col => $def) {
            rl_add_column($conn, 'templates', $col, $def);
        }
        foreach ([
            'idx_templates_app' => 'KEY `idx_templates_app` (`app_id`)',
            'idx_templates_name' => 'KEY `idx_templates_name` (`template_name`)',
            'idx_templates_active' => 'KEY `idx_templates_active` (`is_active`)',
            'idx_templates_sort' => 'KEY `idx_templates_sort` (`sort_order`)',
        ] as $name => $def) {
            rl_add_index($conn, 'templates', $name, $def);
        }

        // template_items
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `template_items` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `template_id` int UNSIGNED NOT NULL DEFAULT '0',
          `line_label` varchar(150) NOT NULL DEFAULT '',
          `description` varchar(255) NOT NULL DEFAULT '',
          `miner_id` int UNSIGNED NOT NULL DEFAULT '0',
          `asset_id` int UNSIGNED NOT NULL DEFAULT '0',
          `category_id` int UNSIGNED NOT NULL DEFAULT '0',
          `referral_id` int UNSIGNED NOT NULL DEFAULT '0',
          `from_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `to_account_id` int UNSIGNED NOT NULL DEFAULT '0',
          `amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
          `notes` text DEFAULT NULL,
          `show_miner` tinyint(1) NOT NULL DEFAULT '1',
          `show_asset` tinyint(1) NOT NULL DEFAULT '1',
          `show_category` tinyint(1) NOT NULL DEFAULT '1',
          `show_referral` tinyint(1) NOT NULL DEFAULT '0',
          `show_amount` tinyint(1) NOT NULL DEFAULT '1',
          `show_notes` tinyint(1) NOT NULL DEFAULT '1',
          `show_received_time` tinyint(1) NOT NULL DEFAULT '1',
          `show_value_at_receipt` tinyint(1) NOT NULL DEFAULT '1',
          `show_from_account` tinyint(1) NOT NULL DEFAULT '0',
          `show_to_account` tinyint(1) NOT NULL DEFAULT '0',
          `show_in_quick_add` tinyint(1) NOT NULL DEFAULT '0',
          `quick_add_name` varchar(150) NOT NULL DEFAULT '',
          `is_multi_add` tinyint(1) NOT NULL DEFAULT '0',
          `sort_order` int NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'template_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'line_label' => "varchar(150) NOT NULL DEFAULT ''",
            'description' => "varchar(255) NOT NULL DEFAULT ''",
            'miner_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'asset_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'category_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'referral_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'from_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'to_account_id' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'amount' => "decimal(18,8) NOT NULL DEFAULT '0.00000000'",
            'notes' => 'text DEFAULT NULL',
            'show_miner' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_asset' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_category' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_referral' => "tinyint(1) NOT NULL DEFAULT '0'",
            'show_amount' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_notes' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_received_time' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_value_at_receipt' => "tinyint(1) NOT NULL DEFAULT '1'",
            'show_from_account' => "tinyint(1) NOT NULL DEFAULT '0'",
            'show_to_account' => "tinyint(1) NOT NULL DEFAULT '0'",
            'show_in_quick_add' => "tinyint(1) NOT NULL DEFAULT '0'",
            'quick_add_name' => "varchar(150) NOT NULL DEFAULT ''",
            'is_multi_add' => "tinyint(1) NOT NULL DEFAULT '0'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'template_items', $col, $def);
        }
        foreach ([
            'idx_ti_template' => 'KEY `idx_ti_template` (`template_id`)',
            'idx_ti_category' => 'KEY `idx_ti_category` (`category_id`)',
            'idx_ti_asset' => 'KEY `idx_ti_asset` (`asset_id`)',
            'idx_ti_miner' => 'KEY `idx_ti_miner` (`miner_id`)',
            'idx_ti_referral' => 'KEY `idx_ti_referral` (`referral_id`)',
            'idx_ti_sort' => 'KEY `idx_ti_sort` (`sort_order`)',
        ] as $name => $def) {
            rl_add_index($conn, 'template_items', $name, $def);
        }

        // wiki_pages
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `wiki_pages` (
          `id` int NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `slug` varchar(255) NOT NULL,
          `content` mediumtext NOT NULL,
          `category` varchar(100) DEFAULT 'General',
          `sort_order` int DEFAULT '0',
          `is_published` tinyint(1) DEFAULT '1',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'title' => 'varchar(255) NOT NULL',
            'slug' => 'varchar(255) NOT NULL',
            'content' => 'mediumtext NOT NULL',
            'category' => "varchar(100) DEFAULT 'General'",
            'sort_order' => "int DEFAULT '0'",
            'is_published' => "tinyint(1) DEFAULT '1'",
            'created_at' => 'timestamp NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'wiki_pages', $col, $def);
        }
        rl_add_index($conn, 'wiki_pages', 'slug', 'UNIQUE KEY `slug` (`slug`)');

        // AI import alias tables
        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `ai_import_category_aliases` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `alias_text` varchar(150) NOT NULL,
          `category_id` int UNSIGNED NOT NULL,
          `sort_order` int NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'alias_text' => 'varchar(150) NOT NULL',
            'category_id' => 'int UNSIGNED NOT NULL',
            'screen_type' => "varchar(30) NOT NULL DEFAULT 'rewards_screen'",
            'enabled_by_default' => "tinyint(1) NOT NULL DEFAULT '1'",
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'ai_import_category_aliases', $col, $def);
        }
        rl_add_index($conn, 'ai_import_category_aliases', 'idx_ai_import_category_aliases_alias', 'KEY `idx_ai_import_category_aliases_alias` (`alias_text`)');
        rl_add_index($conn, 'ai_import_category_aliases', 'idx_ai_import_category_aliases_screen', 'KEY `idx_ai_import_category_aliases_screen` (`screen_type`)');
        rl_add_index($conn, 'ai_import_category_aliases', 'idx_ai_import_category_aliases_sort', 'KEY `idx_ai_import_category_aliases_sort` (`sort_order`)');

        rl_exec($conn, "CREATE TABLE IF NOT EXISTS `ai_import_asset_aliases` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `alias_text` varchar(150) NOT NULL,
          `asset_id` int UNSIGNED NOT NULL,
          `sort_order` int NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
        foreach ([
            'alias_text' => 'varchar(150) NOT NULL',
            'asset_id' => 'int UNSIGNED NOT NULL',
            'sort_order' => "int NOT NULL DEFAULT '0'",
            'is_active' => "tinyint(1) NOT NULL DEFAULT '1'",
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $col => $def) {
            rl_add_column($conn, 'ai_import_asset_aliases', $col, $def);
        }
        rl_add_index($conn, 'ai_import_asset_aliases', 'idx_ai_import_asset_aliases_alias', 'KEY `idx_ai_import_asset_aliases_alias` (`alias_text`)');
        rl_add_index($conn, 'ai_import_asset_aliases', 'idx_ai_import_asset_aliases_sort', 'KEY `idx_ai_import_asset_aliases_sort` (`sort_order`)');

        // Seeds: only when each table is empty.
        // Default data seeding intentionally disabled in this schema build.
        // This lets you fully rebuild categories, aliases, templates, quick entries,
        // apps, assets, accounts, and wiki content from scratch without auto-reseeding.

        // ======================================================
        // [SCHEMA] DEFAULT SETTINGS SEED
        // ======================================================
        rl_insert_setting_if_missing($conn, 'onboarding_complete', '1');
        rl_insert_setting_if_missing($conn, 'setup_complete', '1');
        rl_insert_setting_if_missing($conn, 'enable_price_lookup', '1');
        rl_insert_setting_if_missing($conn, 'coingecko_demo_api_key', '');
        rl_insert_setting_if_missing($conn, 'dashboard_show_receipt_value', '0');
        rl_insert_setting_if_missing($conn, 'dashboard_date_mode', 'all_time');
        rl_upsert_schema_version($conn, $schemaVersion);
    }
}
