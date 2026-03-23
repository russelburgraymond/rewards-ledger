<?php
// schema.php
// Safe to call on every request.
// Creates missing tables, columns, indexes, and upgrades older installs.

if (!function_exists('rl_table_exists')) {
    function rl_table_exists(mysqli $conn, string $table): bool {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('rl_column_exists')) {
    function rl_column_exists(mysqli $conn, string $table, string $column): bool {
        if (!rl_table_exists($conn, $table)) {
            return false;
        }

        $table  = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);

        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('rl_index_exists')) {
    function rl_index_exists(mysqli $conn, string $table, string $index): bool {
        if (!rl_table_exists($conn, $table)) {
            return false;
        }

        $table = $conn->real_escape_string($table);
        $index = $conn->real_escape_string($index);

        $res = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('rl_add_column')) {
    function rl_add_column(mysqli $conn, string $table, string $column, string $definition): void {
        if (!rl_column_exists($conn, $table, $column)) {
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
            if (!$conn->query($sql)) {
                die("Schema update failed while adding {$table}.{$column}: " . $conn->error);
            }
        }
    }
}

if (!function_exists('rl_add_index')) {
    function rl_add_index(mysqli $conn, string $table, string $index, string $sql): void {
        if (!rl_index_exists($conn, $table, $index)) {
            if (!$conn->query($sql)) {
                die("Schema update failed while adding index {$index} on {$table}: " . $conn->error);
            }
        }
    }
}

if (!function_exists('rl_exec')) {
    function rl_exec(mysqli $conn, string $sql): void {
        if (!$conn->query($sql)) {
            die("Schema query failed: " . $conn->error . "\nSQL: " . $sql);
        }
    }
}

if (!function_exists('rl_maybe_exec')) {
    function rl_maybe_exec(mysqli $conn, string $sql): void {
        $conn->query($sql);
    }
}

if (!function_exists('rl_backfill_if_empty')) {
    function rl_backfill_if_empty(mysqli $conn, string $table, string $newColumn, string $oldColumn): void {
        if (!rl_column_exists($conn, $table, $newColumn) || !rl_column_exists($conn, $table, $oldColumn)) {
            return;
        }

        $sql = "
            UPDATE `{$table}`
            SET `{$newColumn}` = `{$oldColumn}`
            WHERE
                (`{$newColumn}` IS NULL OR TRIM(CAST(`{$newColumn}` AS CHAR)) = '')
                AND `{$oldColumn}` IS NOT NULL
                AND TRIM(CAST(`{$oldColumn}` AS CHAR)) <> ''
        ";
        $conn->query($sql);
    }
}

if (!function_exists('rl_clean_blank_names')) {
    function rl_clean_blank_names(mysqli $conn, string $table, string $column, string $label): void {
        if (!rl_column_exists($conn, $table, $column)) {
            return;
        }

        $safeLabel = $conn->real_escape_string($label);

        $sql = "
            UPDATE `{$table}`
            SET `{$column}` = CONCAT('{$safeLabel} ', `id`)
            WHERE `{$column}` IS NULL OR TRIM(`{$column}`) = ''
        ";
        $conn->query($sql);
    }
}

if (!function_exists('ensure_schema')) {
    function ensure_schema(mysqli $conn): void {

        /*
        |--------------------------------------------------------------------------
        | SETTINGS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key`   VARCHAR(100) NOT NULL,
                `setting_value` TEXT NULL,
                `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'settings', 'setting_value', 'TEXT NULL');
        rl_add_column($conn, 'settings', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'settings', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        /*
        |--------------------------------------------------------------------------
        | APPS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `apps` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_name` VARCHAR(120) NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'apps', 'app_name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'apps', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'apps', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'apps', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'apps', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_clean_blank_names($conn, 'apps', 'app_name', 'App');

        rl_add_index($conn, 'apps', 'idx_apps_name', "ALTER TABLE `apps` ADD KEY `idx_apps_name` (`app_name`)");
        rl_add_index($conn, 'apps', 'idx_apps_sort', "ALTER TABLE `apps` ADD KEY `idx_apps_sort` (`sort_order`)");
        rl_add_index($conn, 'apps', 'idx_apps_active', "ALTER TABLE `apps` ADD KEY `idx_apps_active` (`is_active`)");

        /*
        |--------------------------------------------------------------------------
        | ACCOUNTS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `accounts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `account_name` VARCHAR(150) NOT NULL,
                `account_type` VARCHAR(100) NOT NULL DEFAULT '',
                `account_identifier` VARCHAR(150) NOT NULL DEFAULT '',
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'accounts', 'account_name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'accounts', 'account_type', 'VARCHAR(100) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'accounts', 'account_identifier', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'accounts', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'accounts', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'accounts', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'accounts', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'accounts', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_clean_blank_names($conn, 'accounts', 'account_name', 'Account');

        rl_add_index($conn, 'accounts', 'idx_accounts_name', "ALTER TABLE `accounts` ADD KEY `idx_accounts_name` (`account_name`)");
        rl_add_index($conn, 'accounts', 'idx_accounts_active', "ALTER TABLE `accounts` ADD KEY `idx_accounts_active` (`is_active`)");
        rl_add_index($conn, 'accounts', 'idx_accounts_sort', "ALTER TABLE `accounts` ADD KEY `idx_accounts_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | REFERRALS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `referrals` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `referral_name` VARCHAR(150) NOT NULL,
                `referral_identifier` VARCHAR(150) NOT NULL DEFAULT '',
                `account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'referrals', 'referral_name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'referrals', 'referral_identifier', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'referrals', 'account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'referrals', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'referrals', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'referrals', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'referrals', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'referrals', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_clean_blank_names($conn, 'referrals', 'referral_name', 'Referral');

        rl_add_index($conn, 'referrals', 'idx_referrals_name', "ALTER TABLE `referrals` ADD KEY `idx_referrals_name` (`referral_name`)");
        rl_add_index($conn, 'referrals', 'idx_referrals_account', "ALTER TABLE `referrals` ADD KEY `idx_referrals_account` (`account_id`)");
        rl_add_index($conn, 'referrals', 'idx_referrals_active', "ALTER TABLE `referrals` ADD KEY `idx_referrals_active` (`is_active`)");
        rl_add_index($conn, 'referrals', 'idx_referrals_sort', "ALTER TABLE `referrals` ADD KEY `idx_referrals_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | CATEGORIES
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `category_name` VARCHAR(120) NOT NULL DEFAULT '',
                `behavior_type` VARCHAR(30) NOT NULL DEFAULT 'income',
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `dashboard_order` INT NOT NULL DEFAULT 0,
				`dashboard_row` INT NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'categories', 'name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'categories', 'app_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'categories', 'category_name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'categories', 'behavior_type', 'VARCHAR(30) NOT NULL DEFAULT \'income\'');
        rl_add_column($conn, 'categories', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'categories', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'categories', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'categories', 'dashboard_order', 'INT NOT NULL DEFAULT 0');
		rl_add_column($conn, 'categories', 'dashboard_row', 'INT NOT NULL DEFAULT 1');
        rl_add_column($conn, 'categories', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'categories', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_backfill_if_empty($conn, 'categories', 'category_name', 'name');
        rl_backfill_if_empty($conn, 'categories', 'name', 'category_name');

        rl_clean_blank_names($conn, 'categories', 'category_name', 'Category');
        rl_backfill_if_empty($conn, 'categories', 'name', 'category_name');

        rl_maybe_exec($conn, "
            UPDATE `categories`
            SET `behavior_type` = 'income'
            WHERE `behavior_type` NOT IN ('income','expense','investment','withdrawal','transfer','adjustment')
               OR `behavior_type` IS NULL
               OR TRIM(`behavior_type`) = ''
        ");

        rl_add_index($conn, 'categories', 'idx_categories_app', "ALTER TABLE `categories` ADD KEY `idx_categories_app` (`app_id`)");
        rl_add_index($conn, 'categories', 'idx_categories_name', "ALTER TABLE `categories` ADD KEY `idx_categories_name` (`category_name`)");
        rl_add_index($conn, 'categories', 'idx_categories_active', "ALTER TABLE `categories` ADD KEY `idx_categories_active` (`is_active`)");
        rl_add_index($conn, 'categories', 'idx_categories_sort', "ALTER TABLE `categories` ADD KEY `idx_categories_sort` (`sort_order`)");
        rl_add_index($conn, 'categories', 'idx_categories_dashboard', "ALTER TABLE `categories` ADD KEY `idx_categories_dashboard` (`dashboard_order`)");
		rl_add_index($conn, 'categories', 'idx_categories_dashboard_row', "ALTER TABLE `categories` ADD KEY `idx_categories_dashboard_row` (`dashboard_row`)");

        /*
        |--------------------------------------------------------------------------
        | ASSETS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `assets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `asset_name` VARCHAR(120) NOT NULL DEFAULT '',
                `asset_symbol` VARCHAR(40) NOT NULL DEFAULT '',
                `currency_symbol` VARCHAR(10) NOT NULL DEFAULT '',
                `display_decimals` INT NOT NULL DEFAULT 8,
                `is_fiat` TINYINT(1) NOT NULL DEFAULT 0,
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'assets', 'name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'assets', 'symbol', 'VARCHAR(40) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'assets', 'asset_name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'assets', 'asset_symbol', 'VARCHAR(40) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'assets', 'currency_symbol', 'VARCHAR(10) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'assets', 'display_decimals', 'INT NOT NULL DEFAULT 8');
        rl_add_column($conn, 'assets', 'is_fiat', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'assets', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'assets', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'assets', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'assets', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'assets', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_backfill_if_empty($conn, 'assets', 'asset_name', 'name');
        rl_backfill_if_empty($conn, 'assets', 'name', 'asset_name');
        rl_backfill_if_empty($conn, 'assets', 'asset_symbol', 'symbol');
        rl_backfill_if_empty($conn, 'assets', 'symbol', 'asset_symbol');

        rl_clean_blank_names($conn, 'assets', 'asset_name', 'Asset');
        rl_backfill_if_empty($conn, 'assets', 'name', 'asset_name');

        rl_add_index($conn, 'assets', 'idx_assets_name', "ALTER TABLE `assets` ADD KEY `idx_assets_name` (`asset_name`)");
        rl_add_index($conn, 'assets', 'idx_assets_symbol', "ALTER TABLE `assets` ADD KEY `idx_assets_symbol` (`asset_symbol`)");
        rl_add_index($conn, 'assets', 'idx_assets_active', "ALTER TABLE `assets` ADD KEY `idx_assets_active` (`is_active`)");
        rl_add_index($conn, 'assets', 'idx_assets_sort', "ALTER TABLE `assets` ADD KEY `idx_assets_sort` (`sort_order`)");

        /* -----------------------------
           ASSET CURRENCY BACKFILL
        ----------------------------- */
        $conn->query("UPDATE assets SET currency_symbol='$', display_decimals=2, is_fiat=1 WHERE asset_symbol='USD' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='€', display_decimals=2, is_fiat=1 WHERE asset_symbol='EUR' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='£', display_decimals=2, is_fiat=1 WHERE asset_symbol='GBP' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='A$', display_decimals=2, is_fiat=1 WHERE asset_symbol='AUD' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='C$', display_decimals=2, is_fiat=1 WHERE asset_symbol='CAD' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='$', display_decimals=2, is_fiat=1 WHERE asset_symbol='USDT' AND (currency_symbol='' OR currency_symbol IS NULL)");
        $conn->query("UPDATE assets SET currency_symbol='', display_decimals=8, is_fiat=0 WHERE asset_symbol IN ('BTC','ETH','GMT','BNB') AND (currency_symbol='' OR currency_symbol IS NULL)");

        /*
        |--------------------------------------------------------------------------
        | MINERS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `miners` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `miner_name` VARCHAR(120) NOT NULL DEFAULT '',
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'miners', 'name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'miners', 'miner_name', 'VARCHAR(120) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'miners', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'miners', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'miners', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'miners', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'miners', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_backfill_if_empty($conn, 'miners', 'miner_name', 'name');
        rl_backfill_if_empty($conn, 'miners', 'name', 'miner_name');

        rl_clean_blank_names($conn, 'miners', 'miner_name', 'Miner');
        rl_backfill_if_empty($conn, 'miners', 'name', 'miner_name');

        rl_add_index($conn, 'miners', 'idx_miners_name', "ALTER TABLE `miners` ADD KEY `idx_miners_name` (`miner_name`)");
        rl_add_index($conn, 'miners', 'idx_miners_active', "ALTER TABLE `miners` ADD KEY `idx_miners_active` (`is_active`)");
        rl_add_index($conn, 'miners', 'idx_miners_sort', "ALTER TABLE `miners` ADD KEY `idx_miners_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | TEMPLATES
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `templates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `template_name` VARCHAR(150) NOT NULL DEFAULT '',
                `notes` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'templates', 'name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'templates', 'app_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'templates', 'template_name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'templates', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'templates', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'templates', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'templates', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'templates', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_backfill_if_empty($conn, 'templates', 'template_name', 'name');
        rl_backfill_if_empty($conn, 'templates', 'name', 'template_name');

        rl_clean_blank_names($conn, 'templates', 'template_name', 'Template');
        rl_backfill_if_empty($conn, 'templates', 'name', 'template_name');

        rl_add_index($conn, 'templates', 'idx_templates_app', "ALTER TABLE `templates` ADD KEY `idx_templates_app` (`app_id`)");
        rl_add_index($conn, 'templates', 'idx_templates_name', "ALTER TABLE `templates` ADD KEY `idx_templates_name` (`template_name`)");
        rl_add_index($conn, 'templates', 'idx_templates_active', "ALTER TABLE `templates` ADD KEY `idx_templates_active` (`is_active`)");
        rl_add_index($conn, 'templates', 'idx_templates_sort', "ALTER TABLE `templates` ADD KEY `idx_templates_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | TEMPLATE ITEMS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `template_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `template_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `line_label` VARCHAR(150) NOT NULL DEFAULT '',
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `miner_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `asset_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `referral_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `from_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `to_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `amount` DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
                `notes` TEXT NULL,
                `show_miner` TINYINT(1) NOT NULL DEFAULT 1,
                `show_asset` TINYINT(1) NOT NULL DEFAULT 1,
                `show_category` TINYINT(1) NOT NULL DEFAULT 1,
                `show_referral` TINYINT(1) NOT NULL DEFAULT 0,
                `show_amount` TINYINT(1) NOT NULL DEFAULT 1,
                `show_notes` TINYINT(1) NOT NULL DEFAULT 1,
                `show_from_account` TINYINT(1) NOT NULL DEFAULT 0,
                `show_to_account` TINYINT(1) NOT NULL DEFAULT 0,
                `show_in_quick_add` TINYINT(1) NOT NULL DEFAULT 0,
                `quick_add_name` VARCHAR(150) NOT NULL DEFAULT '',
                `is_multi_add` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'template_items', 'template_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'line_label', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'template_items', 'description', 'VARCHAR(255) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'template_items', 'miner_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'asset_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'category_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'referral_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'from_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'to_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'amount', 'DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        rl_add_column($conn, 'template_items', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'template_items', 'show_miner', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'template_items', 'show_asset', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'template_items', 'show_category', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'template_items', 'show_referral', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'show_amount', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'template_items', 'show_notes', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'template_items', 'show_from_account', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'show_to_account', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'show_in_quick_add', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'quick_add_name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'template_items', 'is_multi_add', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'template_items', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'template_items', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_add_index($conn, 'template_items', 'idx_ti_template', "ALTER TABLE `template_items` ADD KEY `idx_ti_template` (`template_id`)");
        rl_add_index($conn, 'template_items', 'idx_ti_category', "ALTER TABLE `template_items` ADD KEY `idx_ti_category` (`category_id`)");
        rl_add_index($conn, 'template_items', 'idx_ti_asset', "ALTER TABLE `template_items` ADD KEY `idx_ti_asset` (`asset_id`)");
        rl_add_index($conn, 'template_items', 'idx_ti_miner', "ALTER TABLE `template_items` ADD KEY `idx_ti_miner` (`miner_id`)");
        rl_add_index($conn, 'template_items', 'idx_ti_referral', "ALTER TABLE `template_items` ADD KEY `idx_ti_referral` (`referral_id`)");
        rl_add_index($conn, 'template_items', 'idx_ti_sort', "ALTER TABLE `template_items` ADD KEY `idx_ti_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | BATCHES
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `batches` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `template_id` INT UNSIGNED NULL,
                `title` VARCHAR(150) NOT NULL DEFAULT '',
                `batch_date` DATE NULL,
                `notes` TEXT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'open',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'batches', 'app_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batches', 'template_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batches', 'title', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'batches', 'batch_date', 'DATE NULL');
        rl_add_column($conn, 'batches', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'batches', 'status', 'VARCHAR(30) NOT NULL DEFAULT \'open\'');
        rl_add_column($conn, 'batches', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'batches', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_add_index($conn, 'batches', 'idx_batches_app', "ALTER TABLE `batches` ADD KEY `idx_batches_app` (`app_id`)");
        rl_add_index($conn, 'batches', 'idx_batches_template', "ALTER TABLE `batches` ADD KEY `idx_batches_template` (`template_id`)");
        rl_add_index($conn, 'batches', 'idx_batches_date', "ALTER TABLE `batches` ADD KEY `idx_batches_date` (`batch_date`)");
        rl_add_index($conn, 'batches', 'idx_batches_status', "ALTER TABLE `batches` ADD KEY `idx_batches_status` (`status`)");

        /*
        |--------------------------------------------------------------------------
        | BATCH ITEMS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `batch_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `batch_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `template_item_id` INT UNSIGNED NULL,
                `miner_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `asset_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `referral_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `from_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `to_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `amount` DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'batch_items', 'batch_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'template_item_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batch_items', 'miner_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'asset_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'category_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'referral_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'from_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'to_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_items', 'amount', 'DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        rl_add_column($conn, 'batch_items', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'batch_items', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'batch_items', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_add_index($conn, 'batch_items', 'idx_bi_batch', "ALTER TABLE `batch_items` ADD KEY `idx_bi_batch` (`batch_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_template_item', "ALTER TABLE `batch_items` ADD KEY `idx_bi_template_item` (`template_item_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_miner', "ALTER TABLE `batch_items` ADD KEY `idx_bi_miner` (`miner_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_asset', "ALTER TABLE `batch_items` ADD KEY `idx_bi_asset` (`asset_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_category', "ALTER TABLE `batch_items` ADD KEY `idx_bi_category` (`category_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_referral', "ALTER TABLE `batch_items` ADD KEY `idx_bi_referral` (`referral_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_from_account', "ALTER TABLE `batch_items` ADD KEY `idx_bi_from_account` (`from_account_id`)");
        rl_add_index($conn, 'batch_items', 'idx_bi_to_account', "ALTER TABLE `batch_items` ADD KEY `idx_bi_to_account` (`to_account_id`)");

        /*
        |--------------------------------------------------------------------------
        | QUICK ADD ITEMS
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `quick_add_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `quick_add_name` VARCHAR(150) NOT NULL DEFAULT '',
                `miner_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `asset_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `referral_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `from_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `to_account_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `amount` DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
                `notes` TEXT NULL,
                `show_miner` TINYINT(1) NOT NULL DEFAULT 1,
                `show_asset` TINYINT(1) NOT NULL DEFAULT 1,
                `show_category` TINYINT(1) NOT NULL DEFAULT 1,
                `show_referral` TINYINT(1) NOT NULL DEFAULT 0,
                `show_amount` TINYINT(1) NOT NULL DEFAULT 1,
                `show_notes` TINYINT(1) NOT NULL DEFAULT 1,
                `show_from_account` TINYINT(1) NOT NULL DEFAULT 0,
                `show_to_account` TINYINT(1) NOT NULL DEFAULT 0,
                `is_multi_add` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'quick_add_items', 'app_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'quick_add_name', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'quick_add_items', 'miner_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'asset_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'category_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'referral_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'from_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'to_account_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'amount', 'DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        rl_add_column($conn, 'quick_add_items', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'quick_add_items', 'show_miner', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'show_asset', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'show_category', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'show_referral', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'show_amount', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'show_notes', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'show_from_account', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'show_to_account', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'is_multi_add', 'TINYINT(1) NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'quick_add_items', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        rl_add_column($conn, 'quick_add_items', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'quick_add_items', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_add_index($conn, 'quick_add_items', 'idx_qa_app', "ALTER TABLE `quick_add_items` ADD KEY `idx_qa_app` (`app_id`)");
        rl_add_index($conn, 'quick_add_items', 'idx_qa_name', "ALTER TABLE `quick_add_items` ADD KEY `idx_qa_name` (`quick_add_name`)");
        rl_add_index($conn, 'quick_add_items', 'idx_qa_category', "ALTER TABLE `quick_add_items` ADD KEY `idx_qa_category` (`category_id`)");
        rl_add_index($conn, 'quick_add_items', 'idx_qa_sort', "ALTER TABLE `quick_add_items` ADD KEY `idx_qa_sort` (`sort_order`)");
        rl_add_index($conn, 'quick_add_items', 'idx_qa_active', "ALTER TABLE `quick_add_items` ADD KEY `idx_qa_active` (`is_active`)");

        /*
        |--------------------------------------------------------------------------
        | LEGACY BATCH LINES
        |--------------------------------------------------------------------------
        */
        rl_exec($conn, "
            CREATE TABLE IF NOT EXISTS `batch_lines` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `batch_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `template_item_id` INT UNSIGNED NULL,
                `category_id` INT UNSIGNED NULL,
                `asset_id` INT UNSIGNED NULL,
                `miner_id` INT UNSIGNED NULL,
                `line_label` VARCHAR(150) NOT NULL DEFAULT '',
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `amount` DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
                `notes` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        rl_add_column($conn, 'batch_lines', 'batch_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_lines', 'template_item_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batch_lines', 'category_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batch_lines', 'asset_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batch_lines', 'miner_id', 'INT UNSIGNED NULL');
        rl_add_column($conn, 'batch_lines', 'line_label', 'VARCHAR(150) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'batch_lines', 'description', 'VARCHAR(255) NOT NULL DEFAULT \'\'');
        rl_add_column($conn, 'batch_lines', 'amount', 'DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        rl_add_column($conn, 'batch_lines', 'notes', 'TEXT NULL');
        rl_add_column($conn, 'batch_lines', 'sort_order', 'INT NOT NULL DEFAULT 0');
        rl_add_column($conn, 'batch_lines', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        rl_add_column($conn, 'batch_lines', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        rl_add_index($conn, 'batch_lines', 'idx_bl_batch', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_batch` (`batch_id`)");
        rl_add_index($conn, 'batch_lines', 'idx_bl_template_item', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_template_item` (`template_item_id`)");
        rl_add_index($conn, 'batch_lines', 'idx_bl_category', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_category` (`category_id`)");
        rl_add_index($conn, 'batch_lines', 'idx_bl_asset', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_asset` (`asset_id`)");
        rl_add_index($conn, 'batch_lines', 'idx_bl_miner', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_miner` (`miner_id`)");
        rl_add_index($conn, 'batch_lines', 'idx_bl_sort', "ALTER TABLE `batch_lines` ADD KEY `idx_bl_sort` (`sort_order`)");

        /*
        |--------------------------------------------------------------------------
        | OPTIONAL MIGRATION: batch_lines -> batch_items
        |--------------------------------------------------------------------------
        */
        if (rl_table_exists($conn, 'batch_lines') && rl_table_exists($conn, 'batch_items')) {
            $resNew = $conn->query("SELECT COUNT(*) AS c FROM `batch_items`");
            $resOld = $conn->query("SELECT COUNT(*) AS c FROM `batch_lines`");

            $newCount = 0;
            $oldCount = 0;

            if ($resNew) {
                $row = $resNew->fetch_assoc();
                $newCount = (int)($row['c'] ?? 0);
            }

            if ($resOld) {
                $row = $resOld->fetch_assoc();
                $oldCount = (int)($row['c'] ?? 0);
            }

            if ($newCount === 0 && $oldCount > 0) {
                $conn->query("
                    INSERT INTO `batch_items` (
                        `batch_id`,
                        `template_item_id`,
                        `miner_id`,
                        `asset_id`,
                        `category_id`,
                        `amount`,
                        `notes`,
                        `created_at`,
                        `updated_at`
                    )
                    SELECT
                        `batch_id`,
                        `template_item_id`,
                        IFNULL(`miner_id`, 0),
                        IFNULL(`asset_id`, 0),
                        IFNULL(`category_id`, 0),
                        IFNULL(`amount`, 0),
                        `notes`,
                        `created_at`,
                        `updated_at`
                    FROM `batch_lines`
                ");
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ONE-TIME DEFAULT SEED DATA
        |--------------------------------------------------------------------------
        */
        $should_seed_defaults = false;

        $res = $conn->query("SELECT COUNT(*) AS c FROM `apps`");
        if ($res) {
            $row = $res->fetch_assoc();
            $should_seed_defaults = ((int)($row['c'] ?? 0) === 0);
        }

        if ($should_seed_defaults) {
            $conn->query("
                INSERT INTO `settings` (`setting_key`, `setting_value`)
                VALUES
                    ('dashboard_category_ids', '1,2,3,4,5,6,7'),
                    ('schema_version', '1.2.0'),
                    ('setup_complete', '1')
                ON DUPLICATE KEY UPDATE
                    `setting_value` = VALUES(`setting_value`)
            ");

            $conn->query("
                INSERT INTO `apps` (`id`, `app_name`, `sort_order`, `is_active`)
                VALUES
                    (1, 'GoMining', 10, 1),
                    (2, 'Atlas Earth', 20, 1)
                ON DUPLICATE KEY UPDATE
                    `app_name` = VALUES(`app_name`),
                    `sort_order` = VALUES(`sort_order`),
                    `is_active` = VALUES(`is_active`)
            ");

            $conn->query("
                INSERT INTO `accounts`
                    (`id`, `account_name`, `account_type`, `account_identifier`, `notes`, `is_active`, `sort_order`)
                VALUES
                    (1, 'Cold Wallet', 'Wallet', '', 'Long term cold storage wallet', 1, 10),
                    (2, 'GoMining BTC', 'Platform Wallet', '', 'BTC rewards held in GoMining', 1, 20),
                    (3, 'GoMining GMT', 'Platform Wallet', '', 'GMT rewards held in GoMining', 1, 30),
                    (4, 'Cash', 'Cash', '', 'Manual fiat or cash balance', 1, 40)
                ON DUPLICATE KEY UPDATE
                    `account_name` = VALUES(`account_name`),
                    `account_type` = VALUES(`account_type`),
                    `account_identifier` = VALUES(`account_identifier`),
                    `notes` = VALUES(`notes`),
                    `is_active` = VALUES(`is_active`),
                    `sort_order` = VALUES(`sort_order`)
            ");

            $conn->query("
                INSERT INTO `assets`
                    (`id`, `asset_name`, `asset_symbol`, `currency_symbol`, `display_decimals`, `is_fiat`, `is_active`, `sort_order`)
                VALUES
                    (1, 'Bitcoin', 'BTC', '', 8, 0, 1, 10),
                    (2, 'GoMining Token', 'GMT', '', 8, 0, 1, 20),
                    (3, 'Binance Coin', 'BNB', '', 8, 0, 1, 30),
                    (4, 'Ethereum', 'ETH', '', 8, 0, 1, 40),
                    (5, 'Tether USD', 'USDT', '$', 2, 1, 1, 50),
                    (6, 'US Dollar', 'USD', '$', 2, 1, 1, 60),
                    (8, 'Euro', 'EUR', '€', 2, 1, 1, 65),
                    (9, 'British Pound', 'GBP', '£', 2, 1, 1, 66),
                    (10, 'Australian Dollar', 'AUD', 'A$', 2, 1, 1, 67),
                    (11, 'Canadian Dollar', 'CAD', 'C$', 2, 1, 1, 68)
                ON DUPLICATE KEY UPDATE
                    `asset_name` = VALUES(`asset_name`),
                    `asset_symbol` = VALUES(`asset_symbol`),
                    `currency_symbol` = VALUES(`currency_symbol`),
                    `display_decimals` = VALUES(`display_decimals`),
                    `is_fiat` = VALUES(`is_fiat`),
                    `is_active` = VALUES(`is_active`),
                    `sort_order` = VALUES(`sort_order`)
            ");

            $conn->query("
                INSERT INTO `categories`
                    (`id`, `app_id`, `category_name`, `behavior_type`, `sort_order`, `dashboard_order`, `is_active`)
                VALUES
                    (1, 1, 'Referral Bonus', 'income', 10, 5, 1),
                    (2, 1, 'veGoMining Reward', 'income', 20, 4, 1),
                    (3, 1, 'Daily Gross Rewards', 'income', 30, 3, 1),
                    (4, 1, 'Daily Net Rewards', 'income', 40, 0, 1),
                    (5, 1, 'Daily Maintenance', 'expense', 50, 2, 1),
                    (6, 1, 'Daily Electricity', 'expense', 60, 1, 1),
                    (7, 1, 'Bounty Rewards', 'income', 70, 6, 1),
                    (8, 2, 'Explorer Club', 'expense', 10, 0, 1),
                    (9, 2, 'Monthly Reward Ladder Premium', 'expense', 20, 0, 1),
                    (10, 2, 'AB Purchase', 'expense', 30, 0, 1),
                    (11, 2, 'Cash Out', 'withdrawal', 40, 0, 1)
                ON DUPLICATE KEY UPDATE
                    `app_id` = VALUES(`app_id`),
                    `category_name` = VALUES(`category_name`),
                    `behavior_type` = VALUES(`behavior_type`),
                    `sort_order` = VALUES(`sort_order`),
                    `dashboard_order` = VALUES(`dashboard_order`),
                    `is_active` = VALUES(`is_active`)
            ");

            $conn->query("
                INSERT INTO `templates`
                    (`id`, `app_id`, `template_name`, `notes`, `is_active`, `sort_order`)
                VALUES
                    (1, 1, 'Daily Rewards (GMT)', '', 1, 10)
                ON DUPLICATE KEY UPDATE
                    `app_id` = VALUES(`app_id`),
                    `template_name` = VALUES(`template_name`),
                    `notes` = VALUES(`notes`),
                    `is_active` = VALUES(`is_active`),
                    `sort_order` = VALUES(`sort_order`)
            ");

            $conn->query("
                INSERT INTO `template_items`
                    (`id`, `template_id`, `miner_id`, `asset_id`, `category_id`, `referral_id`,
                     `from_account_id`, `to_account_id`, `amount`, `notes`,
                     `show_miner`, `show_asset`, `show_category`, `show_referral`,
                     `show_amount`, `show_notes`, `show_from_account`, `show_to_account`,
                     `show_in_quick_add`, `quick_add_name`, `is_multi_add`, `sort_order`)
                VALUES
                    (1, 1, 0, 2, 3, 0, 0, 3, 0.00000000, '', 1, 1, 1, 0, 1, 1, 0, 1, 0, '', 0, 10),
                    (2, 1, 0, 2, 5, 0, 3, 0, 0.00000000, '', 1, 1, 1, 0, 1, 1, 0, 1, 0, '', 0, 20),
                    (3, 1, 0, 2, 6, 0, 3, 0, 0.00000000, '', 1, 1, 1, 0, 1, 1, 0, 1, 0, '', 0, 30),
                    (5, 1, 0, 2, 4, 0, 0, 3, 0.00000000, '', 1, 1, 1, 0, 1, 1, 0, 1, 0, '', 0, 40)
                ON DUPLICATE KEY UPDATE
                    `template_id` = VALUES(`template_id`),
                    `miner_id` = VALUES(`miner_id`),
                    `asset_id` = VALUES(`asset_id`),
                    `category_id` = VALUES(`category_id`),
                    `referral_id` = VALUES(`referral_id`),
                    `from_account_id` = VALUES(`from_account_id`),
                    `to_account_id` = VALUES(`to_account_id`),
                    `amount` = VALUES(`amount`),
                    `notes` = VALUES(`notes`),
                    `show_miner` = VALUES(`show_miner`),
                    `show_asset` = VALUES(`show_asset`),
                    `show_category` = VALUES(`show_category`),
                    `show_referral` = VALUES(`show_referral`),
                    `show_amount` = VALUES(`show_amount`),
                    `show_notes` = VALUES(`show_notes`),
                    `show_from_account` = VALUES(`show_from_account`),
                    `show_to_account` = VALUES(`show_to_account`),
                    `show_in_quick_add` = VALUES(`show_in_quick_add`),
                    `quick_add_name` = VALUES(`quick_add_name`),
                    `is_multi_add` = VALUES(`is_multi_add`),
                    `sort_order` = VALUES(`sort_order`)
            ");

            $conn->query("
                INSERT INTO `quick_add_items`
                    (`id`, `app_id`, `quick_add_name`, `miner_id`, `asset_id`, `category_id`,
                     `referral_id`, `from_account_id`, `to_account_id`, `amount`, `notes`,
                     `show_miner`, `show_asset`, `show_category`, `show_referral`,
                     `show_amount`, `show_notes`, `show_from_account`, `show_to_account`,
                     `is_multi_add`, `sort_order`, `is_active`)
                VALUES
                    (1, 1, 'Daily Gross Rewards - BTC', 0, 1, 3, 0, 0, 2, 0.00000000, '', 1, 1, 1, 0, 1, 0, 0, 1, 0, 10, 1),
                    (2, 1, 'Daily Gross Rewards - GMT', 0, 2, 3, 0, 0, 3, 0.00000000, '', 1, 1, 1, 0, 1, 0, 0, 1, 0, 20, 1),
                    (3, 1, 'Daily Net Rewards - BTC', 0, 1, 4, 0, 0, 2, 0.00000000, '', 1, 1, 1, 0, 1, 0, 0, 1, 0, 30, 1),
                    (4, 1, 'Daily Net Rewards - GMT', 0, 2, 4, 0, 0, 3, 0.00000000, '', 1, 1, 1, 0, 1, 0, 0, 1, 0, 40, 1),
                    (5, 1, 'Daily Maintenance - BTC', 0, 1, 5, 0, 2, 0, 0.00000000, '', 1, 1, 1, 0, 1, 0, 1, 0, 0, 50, 1),
                    (6, 1, 'Daily Maintenance - GMT', 0, 2, 5, 0, 3, 0, 0.00000000, '', 1, 1, 1, 0, 1, 0, 1, 0, 0, 60, 1),
                    (7, 1, 'Daily Electricity - BTC', 0, 1, 6, 0, 2, 0, 0.00000000, '', 1, 1, 1, 0, 1, 0, 1, 0, 0, 70, 1),
                    (8, 1, 'Daily Electricity - GMT', 0, 2, 6, 0, 3, 0, 0.00000000, '', 1, 1, 1, 0, 1, 0, 1, 0, 0, 80, 1),
                    (9, 1, 'Referral Bonus - GMT', 0, 2, 1, 0, 0, 3, 0.00000000, '', 0, 1, 1, 1, 1, 0, 0, 1, 0, 90, 1),
                    (10, 1, 'veGoMining Reward - GMT', 0, 2, 2, 0, 0, 3, 0.00000000, '', 0, 1, 1, 1, 1, 0, 0, 1, 0, 100, 1),
                    (11, 1, 'Bounty Reward - GMT', 0, 2, 7, 0, 0, 3, 0.00000000, '', 0, 1, 1, 1, 1, 0, 0, 1, 0, 110, 1),
                    (12, 2, 'Monthly Reward Ladder Premium', 0, 6, 9, 0, 4, 0, 14.99000000, '', 0, 1, 1, 0, 1, 0, 1, 0, 0, 10, 1),
                    (13, 2, 'Explorer Club', 0, 6, 8, 0, 4, 0, 49.99000000, '', 0, 1, 1, 0, 1, 0, 1, 0, 0, 20, 1),
                    (14, 2, 'Cash Out', 0, 6, 11, 0, 0, 4, 0.00000000, '', 0, 1, 1, 0, 1, 0, 0, 1, 0, 30, 1)
                ON DUPLICATE KEY UPDATE
                    `app_id` = VALUES(`app_id`),
                    `quick_add_name` = VALUES(`quick_add_name`),
                    `miner_id` = VALUES(`miner_id`),
                    `asset_id` = VALUES(`asset_id`),
                    `category_id` = VALUES(`category_id`),
                    `referral_id` = VALUES(`referral_id`),
                    `from_account_id` = VALUES(`from_account_id`),
                    `to_account_id` = VALUES(`to_account_id`),
                    `amount` = VALUES(`amount`),
                    `notes` = VALUES(`notes`),
                    `show_miner` = VALUES(`show_miner`),
                    `show_asset` = VALUES(`show_asset`),
                    `show_category` = VALUES(`show_category`),
                    `show_referral` = VALUES(`show_referral`),
                    `show_amount` = VALUES(`show_amount`),
                    `show_notes` = VALUES(`show_notes`),
                    `show_from_account` = VALUES(`show_from_account`),
                    `show_to_account` = VALUES(`show_to_account`),
                    `is_multi_add` = VALUES(`is_multi_add`),
                    `sort_order` = VALUES(`sort_order`),
                    `is_active` = VALUES(`is_active`)
            ");

            $conn->query("ALTER TABLE `apps` AUTO_INCREMENT = 3");
            $conn->query("ALTER TABLE `accounts` AUTO_INCREMENT = 5");
            $conn->query("ALTER TABLE `assets` AUTO_INCREMENT = 12");
            $conn->query("ALTER TABLE `categories` AUTO_INCREMENT = 12");
            $conn->query("ALTER TABLE `templates` AUTO_INCREMENT = 2");
            $conn->query("ALTER TABLE `template_items` AUTO_INCREMENT = 6");
            $conn->query("ALTER TABLE `quick_add_items` AUTO_INCREMENT = 15");
        }
    }
}
?>