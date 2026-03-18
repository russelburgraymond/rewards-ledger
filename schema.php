<?php

function ensure_schema(mysqli $conn): void
{
    /*
    SETTINGS
    ------------------------------------------------
    Used for setup flags and app configuration.
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    APPS
    ------------------------------------------------
    Top-level tracked apps/platforms.
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS apps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_name VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    MINERS
    ------------------------------------------------
    Mining sources (GoMining miners, etc.)
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS miners (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            miner_name VARCHAR(120) NOT NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    ASSETS
    ------------------------------------------------
    Crypto assets (BTC, etc.)
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS assets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_name VARCHAR(50) NOT NULL,
            asset_symbol VARCHAR(20) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    CATEGORIES
    ------------------------------------------------
    Reward categories (mining reward, referral, etc.)
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            category_name VARCHAR(120) NOT NULL,
            behavior_type VARCHAR(40) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_app (app_id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
	
    /*
    ACCOUNTS
    ------------------------------------------------
    Wallets, exchanges, or payout destinations.
    */
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
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    REFERRALS
    ------------------------------------------------
    Tracks referral relationships and rewards.
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS referrals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_name VARCHAR(120) NOT NULL,
            referral_identifier VARCHAR(255) NULL,
            account_id INT UNSIGNED NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_account (account_id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    BATCHES
    ------------------------------------------------
    Saved entry batches generated from templates/use flows.
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS batches (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            batch_date DATE NOT NULL,
            title VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_app (app_id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    BATCH ITEMS
    ------------------------------------------------
    Individual reward entries inside batches.
    */
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
		PRIMARY KEY (id),
		KEY idx_batch (batch_id),
		KEY idx_miner (miner_id),
		KEY idx_asset (asset_id),
		KEY idx_category (category_id),
		CONSTRAINT fk_batch_items_batch
			FOREIGN KEY (batch_id) REFERENCES batches(id)
			ON DELETE CASCADE
	)
	ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    TEMPLATES
    ------------------------------------------------
    Saved reusable templates.
    */
    $conn->query("
        CREATE TABLE IF NOT EXISTS templates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            app_id INT UNSIGNED NULL,
            template_name VARCHAR(150) NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_app (app_id)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    TEMPLATE ITEMS
    ------------------------------------------------
    Lines inside a template.
    */
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
			show_in_quick_add TINYINT(1) NOT NULL DEFAULT 0,
			quick_add_name VARCHAR(150) NULL,
            PRIMARY KEY (id),
            KEY idx_template (template_id),
            CONSTRAINT fk_template_items_template
                FOREIGN KEY (template_id) REFERENCES templates(id)
                ON DELETE CASCADE
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    QUICK ADD ITEMS
    ------------------------------------------------
    Single-line shortcuts used by Quick Entry.
    */
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
            PRIMARY KEY (id),
            KEY idx_app (app_id),
            KEY idx_category (category_id),
            KEY idx_active (is_active)
        )
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
    UPGRADE-SAFE COLUMN REPAIRS
    ------------------------------------------------
    Add missing columns for older installs.
    */

    // quick_add_items
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS app_id INT UNSIGNED NOT NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS quick_add_name VARCHAR(150) NOT NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS miner_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS asset_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS referral_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS from_account_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS to_account_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS amount DECIMAL(20,8) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_miner TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_asset TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_category TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_referral TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_amount TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_notes TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_from_account TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS show_to_account TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE quick_add_items ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // apps
    $conn->query("ALTER TABLE apps ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE apps ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE apps ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // miners
    $conn->query("ALTER TABLE miners ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE miners ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE miners ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // assets
    $conn->query("ALTER TABLE assets ADD COLUMN IF NOT EXISTS asset_symbol VARCHAR(20) NULL");
    $conn->query("ALTER TABLE assets ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE assets ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // categories
	$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS app_id INT UNSIGNED NULL");
	$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS behavior_type VARCHAR(40) NULL");
	$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");
	$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
	$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
	@$conn->query("ALTER TABLE categories ADD INDEX idx_app (app_id)");

    // accounts
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS account_type VARCHAR(50) NULL");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS account_identifier VARCHAR(255) NULL");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // referrals
    $conn->query("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS referral_identifier VARCHAR(255) NULL");
    $conn->query("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS account_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // batches
    $conn->query("ALTER TABLE batches ADD COLUMN IF NOT EXISTS app_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE batches ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL");
    $conn->query("ALTER TABLE batches ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE batches ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // batch_items
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS miner_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS asset_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS amount DECIMAL(20,8) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
	$conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS referral_id INT UNSIGNED NULL");
	$conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS from_account_id INT UNSIGNED NULL");
	$conn->query("ALTER TABLE batch_items ADD COLUMN IF NOT EXISTS to_account_id INT UNSIGNED NULL");

    // templates
    $conn->query("ALTER TABLE templates ADD COLUMN IF NOT EXISTS app_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE templates ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE templates ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    // template_items
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS miner_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS asset_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS referral_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS from_account_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS to_account_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS amount DECIMAL(20,8) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS notes TEXT NULL");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_miner TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_asset TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_category TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_referral TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_amount TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_notes TINYINT(1) NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_from_account TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_to_account TINYINT(1) NOT NULL DEFAULT 0");
	$conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS show_in_quick_add TINYINT(1) NOT NULL DEFAULT 0");
	$conn->query("ALTER TABLE template_items ADD COLUMN IF NOT EXISTS quick_add_name VARCHAR(150) NULL");
	
    /*
    INDEX REPAIRS
    ------------------------------------------------
    Safe repeated index creation is awkward in MySQL,
    so we attempt and ignore failures if they already exist.
    */
    @$conn->query("ALTER TABLE referrals ADD INDEX idx_account (account_id)");
    @$conn->query("ALTER TABLE batches ADD INDEX idx_app (app_id)");
    @$conn->query("ALTER TABLE batch_items ADD INDEX idx_batch (batch_id)");
    @$conn->query("ALTER TABLE batch_items ADD INDEX idx_miner (miner_id)");
    @$conn->query("ALTER TABLE batch_items ADD INDEX idx_asset (asset_id)");
    @$conn->query("ALTER TABLE batch_items ADD INDEX idx_category (category_id)");
    @$conn->query("ALTER TABLE templates ADD INDEX idx_app (app_id)");
    @$conn->query("ALTER TABLE template_items ADD INDEX idx_template (template_id)");
    @$conn->query("ALTER TABLE quick_add_items ADD INDEX idx_app (app_id)");
    @$conn->query("ALTER TABLE quick_add_items ADD INDEX idx_category (category_id)");
    @$conn->query("ALTER TABLE quick_add_items ADD INDEX idx_active (is_active)");
	
    /*
    DEFAULT SETTINGS
    ------------------------------------------------
    Ensures setup flag exists.
    */
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");

    if ($stmt) {
        $key = 'setup_complete';
        $value = '0';
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }

    /*
    DEFAULT APPS
    ------------------------------------------------
    Insert only if apps table is empty.
    */
    $res = $conn->query("SELECT COUNT(*) AS c FROM apps");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {
        $defaults = [
            ['GoMining', 10],
            ['Atlas Earth', 20],
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
	
	/*
	BACKFILL EXISTING CATEGORIES TO GOMINING
	------------------------------------------------
	Older installs had global categories. Assign them to GoMining.
	*/
	$gomining_id = 0;
	$stmt = $conn->prepare("SELECT id FROM apps WHERE app_name = 'GoMining' LIMIT 1");
	if ($stmt) {
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res ? $res->fetch_assoc() : null;
		$stmt->close();

		if ($row) {
			$gomining_id = (int)$row['id'];
		}
	}

	if ($gomining_id > 0) {
		$stmt = $conn->prepare("UPDATE categories SET app_id = ? WHERE app_id IS NULL");
		if ($stmt) {
			$stmt->bind_param("i", $gomining_id);
			$stmt->execute();
			$stmt->close();
		}
	}	
	

    /*
    DEFAULT ASSETS
    ------------------------------------------------
    Insert only if table is empty.
    */
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

	/*
	DEFAULT CATEGORIES
	------------------------------------------------
	Insert only if categories table is empty.
	*/
	$res = $conn->query("SELECT COUNT(*) AS c FROM categories");
	$row = $res ? $res->fetch_assoc() : null;

	if (!$row || (int)$row['c'] === 0) {

		$app_ids = [];

		$res = $conn->query("SELECT id, app_name FROM apps");
		if ($res) {
			while ($r = $res->fetch_assoc()) {
				$app_ids[$r['app_name']] = (int)$r['id'];
			}
		}

		$defaults = [];

		// GoMining
		if (!empty($app_ids['GoMining'])) {
			$defaults[] = [$app_ids['GoMining'], 'Referral Bonus', 'income', 10];
			$defaults[] = [$app_ids['GoMining'], 'veGoMining Reward', 'income', 20];
			$defaults[] = [$app_ids['GoMining'], 'Daily Gross Rewards', 'income', 30];
			$defaults[] = [$app_ids['GoMining'], 'Daily Net Rewards', 'income', 40];
			$defaults[] = [$app_ids['GoMining'], 'Daily Maintenance', 'expense', 50];
			$defaults[] = [$app_ids['GoMining'], 'Daily Electricity', 'expense', 60];
			$defaults[] = [$app_ids['GoMining'], 'Bounty Rewards', 'income', 70];
		}

		// Atlas Earth
		if (!empty($app_ids['Atlas Earth'])) {
			$defaults[] = [$app_ids['Atlas Earth'], 'Explorer Club', 'expense', 10];
			$defaults[] = [$app_ids['Atlas Earth'], 'Monthly Reward Ladder Premium', 'expense', 20];
			$defaults[] = [$app_ids['Atlas Earth'], 'AB Purchase', 'expense', 30];
			$defaults[] = [$app_ids['Atlas Earth'], 'Cash Out', 'withdrawal', 40];
		}

		$stmt = $conn->prepare("
			INSERT INTO categories (app_id, category_name, behavior_type, sort_order, is_active)
			VALUES (?, ?, ?, ?, 1)
		");

		if ($stmt) {
			foreach ($defaults as $c) {
				$stmt->bind_param("issi", $c[0], $c[1], $c[2], $c[3]);
				$stmt->execute();
			}
			$stmt->close();
		}
	}

    /*
    DEFAULT ACCOUNTS / WALLETS
    ------------------------------------------------
    Insert only if accounts table is empty.
    */
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
    DEFAULT QUICK ADD ITEMS
    ------------------------------------------------
    Insert only if quick_add_items table is empty.
    */
    $res = $conn->query("SELECT COUNT(*) AS c FROM quick_add_items");
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || (int)$row['c'] === 0) {

        $lookup_id = function(mysqli $conn, string $table, string $name_col, string $value): int {
            $sql = "SELECT id FROM {$table} WHERE {$name_col} = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) return 0;
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            return $row ? (int)$row['id'] : 0;
        };

        $gomining_id = $lookup_id($conn, 'apps', 'app_name', 'GoMining');
        $atlas_id    = $lookup_id($conn, 'apps', 'app_name', 'Atlas Earth');

        $items = [
            // GoMining
            [$gomining_id, 'Daily Gross Rewards - BTC', 0, 'Bitcoin', 'Daily Gross Rewards', '', '', 'GoMining BTC', 0, '', 1,1,1,0,1,0,0,1,10],
            [$gomining_id, 'Daily Gross Rewards - GMT', 0, 'GoMining Token', 'Daily Gross Rewards', '', '', 'GoMining GMT', 0, '', 1,1,1,0,1,0,0,1,20],
            [$gomining_id, 'Daily Net Rewards - BTC', 0, 'Bitcoin', 'Daily Net Rewards', '', '', 'GoMining BTC', 0, '', 1,1,1,0,1,0,0,1,30],
            [$gomining_id, 'Daily Net Rewards - GMT', 0, 'GoMining Token', 'Daily Net Rewards', '', '', 'GoMining GMT', 0, '', 1,1,1,0,1,0,0,1,40],
            [$gomining_id, 'Daily Maintenance - BTC', 0, 'Bitcoin', 'Daily Maintenance', '', 'GoMining BTC', '', 0, '', 1,1,1,0,1,0,1,0,50],
            [$gomining_id, 'Daily Maintenance - GMT', 0, 'GoMining Token', 'Daily Maintenance', '', 'GoMining GMT', '', 0, '', 1,1,1,0,1,0,1,0,60],
            [$gomining_id, 'Daily Electricity - BTC', 0, 'Bitcoin', 'Daily Electricity', '', 'GoMining BTC', '', 0, '', 1,1,1,0,1,0,1,0,70],
            [$gomining_id, 'Daily Electricity - GMT', 0, 'GoMining Token', 'Daily Electricity', '', 'GoMining GMT', '', 0, '', 1,1,1,0,1,0,1,0,80],
            [$gomining_id, 'Referral Bonus - GMT', 0, 'GoMining Token', 'Referral Bonus', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,90],
            [$gomining_id, 'veGoMining Reward - GMT', 0, 'GoMining Token', 'veGoMining Reward', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,100],
            [$gomining_id, 'Bounty Reward - GMT', 0, 'GoMining Token', 'Bounty Rewards', '', '', 'GoMining GMT', 0, '', 0,1,1,1,1,0,0,1,110],

            // Atlas Earth
            [$atlas_id, 'Monthly Reward Ladder Premium', 0, 'Cash', 'Monthly Reward Ladder Premium', '', 'Cash', '', 14.99, '', 0,1,1,0,1,0,1,0,10],
            [$atlas_id, 'Explorer Club', 0, 'Cash', 'Explorer Club', '', 'Cash', '', 49.99, '', 0,1,1,0,1,0,1,0,20],
            [$atlas_id, 'Cash Out', 0, 'Cash', 'Cash Out', '', '', 'Cash', 0, '', 0,1,1,0,1,0,0,1,30],
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
                    $miner_name_unused,
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

                $miner_id = 0;
                $asset_id = $asset_name !== '' ? $lookup_id($conn, 'assets', 'asset_name', $asset_name) : 0;
                $category_id = $category_name !== '' ? $lookup_id($conn, 'categories', 'category_name', $category_name) : 0;
                $referral_id = $referral_name !== '' ? $lookup_id($conn, 'referrals', 'referral_name', $referral_name) : 0;
                $from_account_id = $from_account_name !== '' ? $lookup_id($conn, 'accounts', 'account_name', $from_account_name) : 0;
                $to_account_id = $to_account_name !== '' ? $lookup_id($conn, 'accounts', 'account_name', $to_account_name) : 0;

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