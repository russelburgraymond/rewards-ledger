<?php

$current_page = 'onboarding';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";
$success = "";

$step = (int)($_GET['step'] ?? 1);
if ($step < 1) $step = 1;
if ($step > 7) $step = 7;

/* -----------------------------
   HELPERS
----------------------------- */
if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('onboarding_write_config')) {
    function onboarding_write_config(
        string $config_path,
        string $db_host,
        string $db_user,
        string $db_pass,
        string $db_name,
        string $app_name,
        int $onboarding_complete
    ): bool {
        $export_host = var_export($db_host, true);
        $export_user = var_export($db_user, true);
        $export_pass = var_export($db_pass, true);
        $export_name = var_export($db_name, true);
        $export_app  = var_export($app_name, true);
        $export_done = (int)$onboarding_complete;

        $new_config = <<<PHP_CONFIG
<?php

////////////////////////////////////////////////////
//  DATABASE SETTINGS                             //
////////////////////////////////////////////////////

\$DB_HOST = {$export_host};
\$DB_USER = {$export_user};
\$DB_PASS = {$export_pass};
\$DB_NAME = {$export_name};

////////////////////////////////////////////////////
//  APP SETTINGS                                  //
////////////////////////////////////////////////////

\$APP_NAME = {$export_app};
\$ONBOARDING_COMPLETE = {$export_done};

////////////////////////////////////////////////////
//  Do not edit below this line.                  //
////////////////////////////////////////////////////

\$APP_VERSION = file_exists(__DIR__ . "/VERSION")
    ? trim(file_get_contents(__DIR__ . "/VERSION"))
    : "0.0.0";

?>
PHP_CONFIG;

        return file_put_contents($config_path, $new_config) !== false;
    }
}

if (!function_exists('onboarding_normalize_label')) {
    function onboarding_normalize_label(string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return mb_strtolower($value, 'UTF-8');
    }
}

if (!function_exists('onboarding_labels_match')) {
    function onboarding_labels_match(string $left, string $right): bool {
        return onboarding_normalize_label($left) === onboarding_normalize_label($right);
    }
}

if (!function_exists('onboarding_step_class')) {
    function onboarding_step_class(int $current_step, int $step_number): string {
        if ($step_number < $current_step) return 'completed';
        if ($step_number === $current_step) return 'current';
        return 'upcoming';
    }
}

/* -----------------------------
   DEFAULT FORM VALUES
----------------------------- */
$db_host = $DB_HOST ?? 'localhost';
$db_user = $DB_USER ?? 'root';
$db_pass = $DB_PASS ?? '';
$db_name = $DB_NAME ?? '007_rewards_tracker';
$app_name = $APP_NAME ?? 'RewardLedger';

$apps_text = '';
$miners_text = '';
$assets_text = '';
$accounts_text = '';
$categories_text = '';

/* -----------------------------
   LOAD DB DURING LATER STEPS
----------------------------- */
$conn = null;
$db_ready = false;

$allow_db_load = !empty($_SESSION['onboarding_db_ready']);

if ($allow_db_load && $step >= 2) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/schema.php';

    if (!empty($db_exists) && isset($conn) && $conn instanceof mysqli) {
        ensure_schema($conn);
        $db_ready = true;
    }
}

/* -----------------------------
   LOAD EXISTING APPS
----------------------------- */
$existing_apps = [];

if ($step >= 2 && $db_ready && $conn instanceof mysqli) {
    $resApps = $conn->query("\n        SELECT app_name\n        FROM apps\n        WHERE is_active = 1\n        ORDER BY sort_order ASC, app_name ASC\n    ");

    if ($resApps) {
        while ($row = $resApps->fetch_assoc()) {
            $existing_apps[] = $row;
        }
    }
}

/* -----------------------------
   LOAD EXISTING MINERS
----------------------------- */
$existing_miners = [];

if ($step >= 3 && $db_ready && $conn instanceof mysqli) {
    $resMiners = $conn->query("\n        SELECT miner_name\n        FROM miners\n        WHERE is_active = 1\n        ORDER BY sort_order ASC, miner_name ASC\n    ");

    if ($resMiners) {
        while ($row = $resMiners->fetch_assoc()) {
            $existing_miners[] = $row;
        }
    }
}

/* -----------------------------
   LOAD EXISTING ASSETS
----------------------------- */
$existing_assets = [];

if ($step >= 4 && $db_ready && $conn instanceof mysqli) {
    $resAssets = $conn->query("\n        SELECT asset_name, asset_symbol\n        FROM assets\n        WHERE is_active = 1\n        ORDER BY sort_order ASC, asset_name ASC\n    ");

    if ($resAssets) {
        while ($row = $resAssets->fetch_assoc()) {
            $existing_assets[] = $row;
        }
    }
}

/* -----------------------------
   LOAD EXISTING ACCOUNTS
----------------------------- */
$existing_accounts = [];

if ($step >= 5 && $db_ready && $conn instanceof mysqli) {
    $resAccounts = $conn->query("\n        SELECT account_name, account_type\n        FROM accounts\n        WHERE is_active = 1\n        ORDER BY sort_order ASC, account_name ASC\n    ");

    if ($resAccounts) {
        while ($row = $resAccounts->fetch_assoc()) {
            $existing_accounts[] = $row;
        }
    }
}

/* -----------------------------
   LOAD EXISTING CATEGORIES
----------------------------- */
$existing_categories = [];

if ($step >= 6 && $db_ready && $conn instanceof mysqli) {
    $resCategories = $conn->query("\n        SELECT category_name, behavior_type\n        FROM categories\n        WHERE is_active = 1\n        ORDER BY sort_order ASC, category_name ASC\n    ");

    if ($resCategories) {
        while ($row = $resCategories->fetch_assoc()) {
            $existing_categories[] = $row;
        }
    }
}

/* -----------------------------
   HANDLE STEP 1 (DB SETUP)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_db') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = (string)($_POST['db_pass'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '007_rewards_tracker');

    if ($db_host === '' || $db_user === '' || $db_name === '') {
        $error = "Host, User, and Database Name are required.";
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);

        $server_conn = @new mysqli($db_host, $db_user, $db_pass);

        if ($server_conn->connect_error) {
            $error = "Connection failed: " . $server_conn->connect_error;
        } else {
            $safe_db_name = $server_conn->real_escape_string($db_name);

            if (!$server_conn->query("CREATE DATABASE IF NOT EXISTS `{$safe_db_name}`")) {
                $error = "Could not create database: " . $server_conn->error;
            } else {
                $config_path = __DIR__ . "/config.php";

                if (!onboarding_write_config(
                    $config_path,
                    $db_host,
                    $db_user,
                    $db_pass,
                    $db_name,
                    $app_name,
                    0
                )) {
                    $error = "Failed to write config.php. Check file permissions.";
                } else {
                    $db_conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

                    if ($db_conn->connect_error) {
                        $error = "Database connection failed after saving config: " . $db_conn->connect_error;
                    } else {
                        require_once __DIR__ . '/schema.php';
                        ensure_schema($db_conn);

                        $db_conn->query("\n                            INSERT INTO settings (setting_key, setting_value)\n                            VALUES ('setup_complete', '1')\n                            ON DUPLICATE KEY UPDATE setting_value = '1'\n                        ");

                        $db_conn->query("\n                            INSERT INTO settings (setting_key, setting_value)\n                            VALUES ('onboarding_complete', '0')\n                            ON DUPLICATE KEY UPDATE setting_value = '0'\n                        ");

                        $_SESSION['onboarding_db_ready'] = true;

                        $db_conn->close();
                        $server_conn->close();

                        header("Location: index.php?page=onboarding&step=2");
                        exit;
                    }
                }
            }

            if ($server_conn instanceof mysqli) {
                $server_conn->close();
            }
        }
    }
}

/* -----------------------------
   HANDLE STEP 2 (APPS)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_apps') {
    $apps_text = trim($_POST['apps_text'] ?? '');

    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready yet. Please complete Step 1 first.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $apps_text);
        $clean = [];
        $seen = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name === '') continue;

            $key = onboarding_normalize_label($name);
            if (isset($seen[$key])) continue;

            $seen[$key] = true;
            $clean[] = $name;
        }

        if (!empty($clean)) {
            $sort_order = 0;
            $resMax = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM apps");
            if ($resMax && $rowMax = $resMax->fetch_assoc()) {
                $sort_order = (int)($rowMax['max_sort'] ?? 0);
            }

            $stmtCheck = $conn->prepare("\n                SELECT id\n                FROM apps\n                WHERE LOWER(app_name) = LOWER(?)\n                LIMIT 1\n            ");

            $stmtInsert = $conn->prepare("\n                INSERT INTO apps (app_name, is_active, sort_order)\n                VALUES (?, 1, ?)\n            ");

            if (!$stmtCheck || !$stmtInsert) {
                $error = "Could not prepare app statements: " . $conn->error;
            } else {
                foreach ($clean as $app_name_new) {
                    $existing_id = 0;

                    $stmtCheck->bind_param("s", $app_name_new);
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result();
                    if ($resCheck && $rowCheck = $resCheck->fetch_assoc()) {
                        $existing_id = (int)($rowCheck['id'] ?? 0);
                    }

                    if ($existing_id > 0) {
                        continue;
                    }

                    $sort_order += 10;
                    $stmtInsert->bind_param("si", $app_name_new, $sort_order);
                    $stmtInsert->execute();
                }

                $stmtCheck->close();
                $stmtInsert->close();
            }
        }

        if ($error === '') {
            header("Location: index.php?page=onboarding&step=3");
            exit;
        }
    }
}

/* -----------------------------
   HANDLE STEP 3 (MINERS)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_miners') {
    $miners_text = trim($_POST['miners_text'] ?? '');

    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready yet. Please complete Step 1 first.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $miners_text);
        $clean = [];
        $seen = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name === '') continue;

            $key = onboarding_normalize_label($name);
            if (isset($seen[$key])) continue;

            $seen[$key] = true;
            $clean[] = $name;
        }

        if (!empty($clean)) {
            $sort_order = 0;
            $resMax = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM miners");
            if ($resMax && $rowMax = $resMax->fetch_assoc()) {
                $sort_order = (int)($rowMax['max_sort'] ?? 0);
            }

            $stmtCheck = $conn->prepare("\n                SELECT id\n                FROM miners\n                WHERE LOWER(miner_name) = LOWER(?)\n                LIMIT 1\n            ");

            $stmtInsert = $conn->prepare("\n                INSERT INTO miners (miner_name, is_active, sort_order)\n                VALUES (?, 1, ?)\n            ");

            if (!$stmtCheck || !$stmtInsert) {
                $error = "Could not prepare miner statements: " . $conn->error;
            } else {
                foreach ($clean as $miner_name) {
                    $existing_id = 0;

                    $stmtCheck->bind_param("s", $miner_name);
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result();
                    if ($resCheck && $rowCheck = $resCheck->fetch_assoc()) {
                        $existing_id = (int)($rowCheck['id'] ?? 0);
                    }

                    if ($existing_id > 0) {
                        continue;
                    }

                    $sort_order += 10;
                    $stmtInsert->bind_param("si", $miner_name, $sort_order);
                    $stmtInsert->execute();
                }

                $stmtCheck->close();
                $stmtInsert->close();
            }
        }

        if ($error === '') {
            header("Location: index.php?page=onboarding&step=4");
            exit;
        }
    }
}

/* -----------------------------
   HANDLE STEP 4 (ASSETS)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_assets') {
    $assets_text = trim($_POST['assets_text'] ?? '');

    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready yet. Please complete Step 1 first.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $assets_text);
        $parsed_assets = [];
        $seen_codes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = array_map('trim', explode('|', $line, 2));

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                $error = "Each asset line must use this format: Name|CODE";
                break;
            }

            $asset_name = $parts[0];
            $asset_symbol = strtoupper($parts[1]);

            if ($asset_name === '' || $asset_symbol === '') {
                continue;
            }

            $seen_key = onboarding_normalize_label($asset_symbol);
            if (isset($seen_codes[$seen_key])) {
                continue;
            }

            $seen_codes[$seen_key] = true;
            $parsed_assets[] = [
                'asset_name' => $asset_name,
                'asset_symbol' => $asset_symbol,
            ];
        }

        if ($error === '' && !empty($parsed_assets)) {
            $sort_order = 0;
            $resMax = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM assets");
            if ($resMax && $rowMax = $resMax->fetch_assoc()) {
                $sort_order = (int)($rowMax['max_sort'] ?? 0);
            }

            $stmtInsert = $conn->prepare("\n                INSERT INTO assets (\n                    asset_name,\n                    asset_symbol,\n                    currency_symbol,\n                    display_decimals,\n                    is_fiat,\n                    is_active,\n                    sort_order\n                )\n                VALUES (?, ?, '', 8, 0, 1, ?)\n            ");

            if (!$stmtInsert) {
                $error = "Could not prepare asset statements: " . $conn->error;
            } else {
                foreach ($parsed_assets as $asset) {
                    $existing_id = 0;

                    $resExisting = $conn->query("SELECT id, asset_name, asset_symbol FROM assets");
                    if ($resExisting) {
                        while ($rowExisting = $resExisting->fetch_assoc()) {
                            $same_name = onboarding_labels_match((string)($rowExisting['asset_name'] ?? ''), $asset['asset_name']);
                            $same_symbol = onboarding_labels_match((string)($rowExisting['asset_symbol'] ?? ''), $asset['asset_symbol']);

                            if ($same_name || $same_symbol) {
                                $existing_id = (int)($rowExisting['id'] ?? 0);
                                break;
                            }
                        }
                    }

                    if ($existing_id > 0) {
                        continue;
                    }

                    $sort_order += 10;
                    $stmtInsert->bind_param("ssi", $asset['asset_name'], $asset['asset_symbol'], $sort_order);
                    $stmtInsert->execute();
                }

                $stmtInsert->close();
            }
        }

        if ($error === '') {
            header("Location: index.php?page=onboarding&step=5");
            exit;
        }
    }
}

/* -----------------------------
   HANDLE STEP 5 (ACCOUNTS)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_accounts') {
    $accounts_text = trim($_POST['accounts_text'] ?? '');

    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready yet. Please complete Step 1 first.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $accounts_text);
        $clean = [];
        $seen = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name === '') continue;

            $key = onboarding_normalize_label($name);
            if (isset($seen[$key])) continue;

            $seen[$key] = true;
            $clean[] = $name;
        }

        if (!empty($clean)) {
            $sort_order = 0;
            $resMax = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM accounts");
            if ($resMax && $rowMax = $resMax->fetch_assoc()) {
                $sort_order = (int)($rowMax['max_sort'] ?? 0);
            }

            $stmtCheck = $conn->prepare("\n                SELECT id\n                FROM accounts\n                WHERE LOWER(account_name) = LOWER(?)\n                LIMIT 1\n            ");

            $stmtInsert = $conn->prepare("\n                INSERT INTO accounts (account_name, account_type, account_identifier, notes, is_active, sort_order)\n                VALUES (?, '', '', '', 1, ?)\n            ");

            if (!$stmtCheck || !$stmtInsert) {
                $error = "Could not prepare account statements: " . $conn->error;
            } else {
                foreach ($clean as $account_name) {
                    $existing_id = 0;

                    $stmtCheck->bind_param("s", $account_name);
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result();
                    if ($resCheck && $rowCheck = $resCheck->fetch_assoc()) {
                        $existing_id = (int)($rowCheck['id'] ?? 0);
                    }

                    if ($existing_id > 0) {
                        continue;
                    }

                    $sort_order += 10;
                    $stmtInsert->bind_param("si", $account_name, $sort_order);
                    $stmtInsert->execute();
                }

                $stmtCheck->close();
                $stmtInsert->close();
            }
        }

        if ($error === '') {
            header("Location: index.php?page=onboarding&step=6");
            exit;
        }
    }
}

/* -----------------------------
   HANDLE STEP 6 (CATEGORIES)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_categories') {
    $categories_text = trim($_POST['categories_text'] ?? '');

    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready yet.";
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $categories_text);
        $clean = [];
        $seen = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name === '') continue;

            $key = onboarding_normalize_label($name);
            if (isset($seen[$key])) continue;

            $seen[$key] = true;
            $clean[] = $name;
        }

        if (!empty($clean)) {
            $sort_order = 0;
            $dashboard_order = 0;

            $resMax = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort, COALESCE(MAX(dashboard_order), 0) AS max_dashboard FROM categories");
            if ($resMax && $rowMax = $resMax->fetch_assoc()) {
                $sort_order = (int)($rowMax['max_sort'] ?? 0);
                $dashboard_order = (int)($rowMax['max_dashboard'] ?? 0);
            }

            $stmtCheck = $conn->prepare("\n                SELECT id\n                FROM categories\n                WHERE LOWER(category_name) = LOWER(?)\n                LIMIT 1\n            ");

            $stmtInsert = $conn->prepare("\n                INSERT INTO categories (app_id, category_name, behavior_type, is_active, sort_order, dashboard_order)\n                VALUES (0, ?, 'income', 1, ?, ?)\n            ");

            if (!$stmtCheck || !$stmtInsert) {
                $error = "Could not prepare category statements: " . $conn->error;
            } else {
                foreach ($clean as $category_name) {
                    $existing_id = 0;

                    $stmtCheck->bind_param("s", $category_name);
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result();
                    if ($resCheck && $rowCheck = $resCheck->fetch_assoc()) {
                        $existing_id = (int)($rowCheck['id'] ?? 0);
                    }

                    if ($existing_id > 0) {
                        continue;
                    }

                    $sort_order += 10;
                    $dashboard_order += 10;
                    $stmtInsert->bind_param("sii", $category_name, $sort_order, $dashboard_order);
                    $stmtInsert->execute();
                }

                $stmtCheck->close();
                $stmtInsert->close();
            }
        }

        if ($error === '') {
            header("Location: index.php?page=onboarding&step=7");
            exit;
        }
    }
}

/* -----------------------------
   HANDLE STEP 7 (FINISH)
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish_onboarding') {
    if (!$db_ready || !$conn instanceof mysqli) {
        $error = "Database connection is not ready.";
    } else {
        $config_path = __DIR__ . "/config.php";

        if (!onboarding_write_config(
            $config_path,
            $DB_HOST ?? 'localhost',
            $DB_USER ?? 'root',
            $DB_PASS ?? '',
            $DB_NAME ?? '007_rewards_tracker',
            $APP_NAME ?? 'RewardLedger',
            1
        )) {
            $error = "Could not finalize config.php.";
        } else {
            $conn->query("\n                INSERT INTO settings (setting_key, setting_value)\n                VALUES ('onboarding_complete', '1')\n                ON DUPLICATE KEY UPDATE setting_value = '1'\n            ");

            $conn->query("\n                INSERT INTO settings (setting_key, setting_value)\n                VALUES ('setup_complete', '1')\n                ON DUPLICATE KEY UPDATE setting_value = '1'\n            ");

            unset($_SESSION['onboarding_db_ready']);

            header("Location: index.php");
            exit;
        }
    }
}
?>

<style>
.onboarding-steps {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.onboarding-step {
    padding: 12px 14px;
    border-radius: 12px;
    border-left: 5px solid #d0d5dd;
    background: #f8fafc;
    font-weight: 700;
}

.onboarding-step.completed {
    border-left-color: #2e7d32;
    background: rgba(46, 125, 50, 0.06);
}

.onboarding-step.current {
    border-left-color: #1565c0;
    background: rgba(21, 101, 192, 0.08);
}

.onboarding-step.upcoming {
    border-left-color: #d0d5dd;
    background: #f8fafc;
    color: #667085;
}

.onboarding-chip-list {
    margin: 10px 0 16px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.onboarding-chip {
    padding: 6px 10px;
    border-radius: 8px;
    background: #f2f4f7;
    border: 1px solid #d0d5dd;
    font-size: 13px;
}

@media (max-width: 1200px) {
    .onboarding-steps {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    .onboarding-steps {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-head">
    <h2>Setup Wizard</h2>
    <p class="subtext">Set up RewardLedger step by step.</p>
</div>

<div class="onboarding-steps">
    <div class="onboarding-step <?= onboarding_step_class($step, 1) ?>">1. Database</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 2) ?>">2. Apps</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 3) ?>">3. Miners</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 4) ?>">4. Assets</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 5) ?>">5. Accounts</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 6) ?>">6. Categories</div>
    <div class="onboarding-step <?= onboarding_step_class($step, 7) ?>">7. Finish</div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
    <div class="card">
        <h3>Step 1 — Database Setup</h3>
        <p class="subtext">
            Enter your MySQL connection details below. The database will be created automatically if it does not already exist.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_db">

            <div class="grid-2">
                <div class="form-row">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= h($db_host) ?>" required>
                </div>

                <div class="form-row">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" value="<?= h($db_user) ?>" required>
                </div>

                <div class="form-row">
                    <label for="db_pass">Database Password</label>
                    <input type="text" id="db_pass" name="db_pass" value="<?= h($db_pass) ?>" placeholder="May be left blank">
                </div>

                <div class="form-row">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="<?= h($db_name) ?>" required>
                </div>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 2): ?>
    <div class="card">
        <h3>Step 2 — Add Extra Apps</h3>

        <p class="subtext">
            These default apps are already available:
        </p>

        <?php if (!empty($existing_apps)): ?>
            <div class="onboarding-chip-list">
                <?php foreach ($existing_apps as $app): ?>
                    <div class="onboarding-chip"><?= h($app['app_name']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="subtext">No default apps found.</p>
        <?php endif; ?>

        <p class="subtext">
            Add any additional apps below (optional). Enter one app per line.
            Blank lines and duplicates will be skipped.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_apps">

            <div class="form-row">
                <label for="apps_text">Additional Apps</label>
                <textarea
                    id="apps_text"
                    name="apps_text"
                    rows="10"
                    placeholder="Honeygain&#10;Ember Fund&#10;Mistplay"
                ><?= h($apps_text) ?></textarea>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
                <a class="btn btn-secondary" href="index.php?page=onboarding&step=3">Skip</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 3): ?>
    <div class="card">
        <h3>Step 3 — Add Extra Miners</h3>

        <p class="subtext">
            Existing miners already in your database:
        </p>

        <?php if (!empty($existing_miners)): ?>
            <div class="onboarding-chip-list">
                <?php foreach ($existing_miners as $miner): ?>
                    <div class="onboarding-chip"><?= h($miner['miner_name']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="subtext">No miners found yet.</p>
        <?php endif; ?>

        <p class="subtext">
            Add any miners you want now (optional). Enter one miner per line.
            Blank lines and duplicates will be skipped.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_miners">

            <div class="form-row">
                <label for="miners_text">Additional Miners</label>
                <textarea
                    id="miners_text"
                    name="miners_text"
                    rows="10"
                    placeholder="Miner A&#10;Miner B&#10;Miner C"
                ><?= h($miners_text) ?></textarea>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
                <a class="btn btn-secondary" href="index.php?page=onboarding&step=4">Skip</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 4): ?>
    <div class="card">
        <h3>Step 4 — Add Extra Assets</h3>

        <p class="subtext">
            These default assets are already available:
        </p>

        <?php if (!empty($existing_assets)): ?>
            <div class="onboarding-chip-list">
                <?php foreach ($existing_assets as $asset): ?>
                    <div class="onboarding-chip">
                        <?= h($asset['asset_name']) ?>
                        <?php if (!empty($asset['asset_symbol'])): ?>
                            (<?= h($asset['asset_symbol']) ?>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="subtext">No default assets found.</p>
        <?php endif; ?>

        <p class="subtext">
            Only add assets here if they are truly new. Do not re-add BTC, GoMining Token (GMT), USD, or other defaults you already see above.
        </p>

        <p class="subtext">
            Format: <strong>Name|CODE</strong>. Similar names or codes will be skipped to help prevent duplicates.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_assets">

            <div class="form-row">
                <label for="assets_text">Additional Assets</label>
                <textarea
                    id="assets_text"
                    name="assets_text"
                    rows="10"
                    placeholder="Euro|EUR&#10;Australian Dollar|AUD&#10;Silver|XAG"
                ><?= h($assets_text) ?></textarea>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
                <a class="btn btn-secondary" href="index.php?page=onboarding&step=5">Skip</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 5): ?>
    <div class="card">
        <h3>Step 5 — Add Extra Accounts</h3>

        <p class="subtext">
            These default accounts are already available:
        </p>

        <?php if (!empty($existing_accounts)): ?>
            <div class="onboarding-chip-list">
                <?php foreach ($existing_accounts as $account): ?>
                    <div class="onboarding-chip">
                        <?= h($account['account_name']) ?>
                        <?php if (!empty($account['account_type'])): ?>
                            (<?= h($account['account_type']) ?>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="subtext">No default accounts found.</p>
        <?php endif; ?>

        <p class="subtext">
            Add any additional accounts below (optional). Enter one account per line.
            Blank lines and duplicates will be skipped.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_accounts">

            <div class="form-row">
                <label for="accounts_text">Additional Accounts</label>
                <textarea
                    id="accounts_text"
                    name="accounts_text"
                    rows="10"
                    placeholder="Cash&#10;Main Wallet&#10;GoMining BTC"
                ><?= h($accounts_text) ?></textarea>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
                <a class="btn btn-secondary" href="index.php?page=onboarding&step=6">Skip</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 6): ?>
    <div class="card">
        <h3>Step 6 — Add Extra Categories</h3>

        <p class="subtext">
            These default categories are already available:
        </p>

        <?php if (!empty($existing_categories)): ?>
            <div class="onboarding-chip-list">
                <?php foreach ($existing_categories as $category): ?>
                    <div class="onboarding-chip">
                        <?= h($category['category_name']) ?>
                        <?php if (!empty($category['behavior_type'])): ?>
                            (<?= h($category['behavior_type']) ?>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="subtext">No default categories found.</p>
        <?php endif; ?>

        <p class="subtext">
            Add any additional categories below (optional). Enter one category per line.
            New categories added here will default to the <strong>income</strong> behavior type and can be edited later in the app.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="save_categories">

            <div class="form-row">
                <label for="categories_text">Additional Categories</label>
                <textarea
                    id="categories_text"
                    name="categories_text"
                    rows="10"
                    placeholder="Staking Rewards&#10;Airdrops&#10;Other Income"
                ><?= h($categories_text) ?></textarea>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save & Continue</button>
                <a class="btn btn-secondary" href="index.php?page=onboarding&step=7">Skip</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($step === 7): ?>
    <div class="card">
        <h3>Step 7 — Finish Setup</h3>
        <p class="subtext">
            Your database is configured, your default seeded data is in place, and any extras you added during onboarding have been saved.
        </p>
        <p class="subtext">
            Templates and Quick Add already have defaults from your schema, so you can fine-tune those later inside the app without making onboarding longer.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="finish_onboarding">
            <button type="submit" class="btn btn-primary">Finish Setup</button>
        </form>
    </div>
<?php endif; ?>
