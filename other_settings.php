<?php

$current_page = 'other_settings';

$error = trim((string)($_GET['error'] ?? ''));
$success = trim((string)($_GET['success'] ?? ''));

$ledger_item_count = 0;
$ledger_batch_count = 0;
$backup_table_count = 0;
$backup_row_count = 0;
$maintenance_table_count = 0;
$maintenance_overhead_bytes = 0;

if (!isset($conn) || !($conn instanceof mysqli)) {
    $error = $error !== '' ? $error : 'Database connection is not available.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'wipe_ledger_entries') {
            $confirm_text = strtoupper(trim((string)($_POST['confirm_text'] ?? '')));
            $confirm_stage = (string)($_POST['confirm_stage'] ?? '');

            if ($confirm_stage !== 'confirmed' || $confirm_text !== 'WIPE') {
                $error = 'Ledger wipe canceled. Type WIPE in the final confirmation to continue.';
            } else {
                $conn->begin_transaction();

                try {
                    if (!$conn->query("DELETE FROM batch_items")) {
                        throw new RuntimeException('Could not delete ledger items: ' . $conn->error);
                    }

                    if (!$conn->query("DELETE FROM batches")) {
                        throw new RuntimeException('Could not clear ledger batches: ' . $conn->error);
                    }

                    $conn->commit();
                    $success = 'All ledger entries were wiped successfully.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        } elseif ($action === 'optimize_database' || $action === 'repair_database') {
            $table_names = [];
            $tablesRes = $conn->query('SHOW TABLES');
            if (!$tablesRes) {
                $error = 'Could not read table list: ' . $conn->error;
            } else {
                while ($row = $tablesRes->fetch_array(MYSQLI_NUM)) {
                    $table = (string)($row[0] ?? '');
                    if ($table !== '') {
                        $table_names[] = $table;
                    }
                }
                $tablesRes->close();

                if (!$table_names) {
                    $error = 'No database tables were found to process.';
                } else {
                    $sqlVerb = $action === 'optimize_database' ? 'OPTIMIZE' : 'REPAIR';
                    $processed = 0;
                    try {
                        foreach ($table_names as $table_name) {
                            $sql = $sqlVerb . " TABLE `" . str_replace('`', '``', $table_name) . "`";
                            $result = $conn->query($sql);
                            if (!$result) {
                                throw new RuntimeException('Could not ' . strtolower($sqlVerb) . ' table ' . $table_name . ': ' . $conn->error);
                            }
                            if ($result instanceof mysqli_result) {
                                $result->close();
                            }
                            $processed++;
                        }
                        if ($action === 'optimize_database') {
                            $success = 'Database optimized successfully. Processed ' . number_format($processed) . ' tables.';
                        } else {
                            $success = 'Database repair completed successfully. Processed ' . number_format($processed) . ' tables.';
                        }
                    } catch (Throwable $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }

    $res = $conn->query("SELECT COUNT(*) AS cnt FROM batch_items");
    if ($res) {
        $row = $res->fetch_assoc();
        $ledger_item_count = (int)($row['cnt'] ?? 0);
        $res->close();
    }

    $res = $conn->query("SELECT COUNT(*) AS cnt FROM batches");
    if ($res) {
        $row = $res->fetch_assoc();
        $ledger_batch_count = (int)($row['cnt'] ?? 0);
        $res->close();
    }

    $res = $conn->query('SHOW TABLES');
    if ($res) {
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            $table = (string)($row[0] ?? '');
            if ($table === '') {
                continue;
            }
            $backup_table_count++;
            $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM `" . str_replace('`', '``', $table) . "`");
            if ($countRes) {
                $countRow = $countRes->fetch_assoc();
                $backup_row_count += (int)($countRow['cnt'] ?? 0);
                $countRes->close();
            }
        }
        $res->close();
    }

    $statusRes = $conn->query('SHOW TABLE STATUS');
    if ($statusRes) {
        while ($status = $statusRes->fetch_assoc()) {
            $name = (string)($status['Name'] ?? '');
            if ($name === '') {
                continue;
            }
            $maintenance_table_count++;
            $maintenance_overhead_bytes += (int)($status['Data_free'] ?? 0);
        }
        $statusRes->close();
    }
}
?>

<div class="page-head">
    <h2>Other</h2>
    <p class="subtext">Advanced tools, backups, restores, and one-off maintenance options.</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Backup</h3>
        <p class="subtext">Download a full RewardLedger backup JSON file with apps, assets, accounts, categories, templates, ledger data, aliases, settings, and other saved app data.</p>

        <div style="margin-top:14px; display:grid; gap:8px;">
            <div><strong>Tables included:</strong> <?= number_format($backup_table_count) ?></div>
            <div><strong>Total rows included:</strong> <?= number_format($backup_row_count) ?></div>
        </div>

        <form method="post" action="backup_restore.php" style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="action" value="download_backup">
            <button type="submit" class="btn btn-primary">Download Full Backup</button>
        </form>
    </div>

    <div class="card">
        <h3>Restore</h3>
        <p class="subtext">Restore a full RewardLedger backup JSON file. This replaces your current saved app data with the selected backup.</p>

        <div class="alert alert-error" style="margin-top:12px;">
            <strong>Warning:</strong> Restore overwrites your current RewardLedger data, including apps, assets, accounts, categories, templates, aliases, settings, and ledger entries.
        </div>

        <form method="post" action="backup_restore.php" id="restore-backup-form" enctype="multipart/form-data" style="margin-top:18px; display:grid; gap:12px;">
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="confirm_stage" id="restore-confirm-stage" value="">
            <input type="hidden" name="confirm_text" id="restore-confirm-text" value="">

            <div>
                <label for="backup-file" style="display:block; margin-bottom:6px; font-weight:600;">Backup File</label>
                <input type="file" name="backup_file" id="backup-file" accept="application/json,.json" class="input">
            </div>

            <div>
                <button type="button" class="btn btn-secondary" id="restore-backup-button" style="background:#92400e; border-color:#92400e; color:#fff;">
                    Restore Backup
                </button>
            </div>
        </form>
    </div>
</div>

<div class="grid-2" style="margin-top:18px;">
    <div class="card">
        <h3>Database Maintenance</h3>
        <p class="subtext">Safe maintenance tools to clean table overhead and repair table structure without deleting your saved RewardLedger data.</p>

        <div style="margin-top:14px; display:grid; gap:8px;">
            <div><strong>Tables available:</strong> <?= number_format($maintenance_table_count) ?></div>
            <div><strong>Estimated free space:</strong> <?= number_format($maintenance_overhead_bytes) ?> bytes</div>
        </div>

        <div class="alert" style="margin-top:12px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a;">
            <strong>Optimize Database</strong> reclaims table overhead and tidies storage. <strong>Repair Database</strong> runs table repair checks. Neither option removes ledger entries or setup data.
        </div>

        <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <form method="post" id="optimize-database-form" style="margin:0;">
                <input type="hidden" name="action" value="optimize_database">
                <button type="button" class="btn btn-primary" id="optimize-database-button">Optimize Database</button>
            </form>

            <form method="post" id="repair-database-form" style="margin:0;">
                <input type="hidden" name="action" value="repair_database">
                <button type="button" class="btn btn-secondary" id="repair-database-button">Repair Database</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>Danger Zone</h3>
        <div class="alert alert-error" style="margin-top:12px;">
            <strong>Wipe All Ledger Entries</strong><br>
            This permanently deletes all saved ledger entries and their batches. It does <strong>not</strong> remove apps, assets, accounts, categories, templates, quick entry items, aliases, or settings.
        </div>

        <div style="margin-top:14px; display:grid; gap:8px;">
            <div><strong>Ledger line items:</strong> <?= number_format($ledger_item_count) ?></div>
            <div><strong>Ledger batches:</strong> <?= number_format($ledger_batch_count) ?></div>
        </div>

        <form method="post" id="wipe-ledger-form" style="margin-top:18px;">
            <input type="hidden" name="action" value="wipe_ledger_entries">
            <input type="hidden" name="confirm_stage" id="wipe-confirm-stage" value="">
            <input type="hidden" name="confirm_text" id="wipe-confirm-text" value="">

            <button type="button" class="btn btn-secondary" id="wipe-ledger-button" style="background:#7f1d1d; border-color:#7f1d1d; color:#fff;">
                Wipe All Ledger Entries
            </button>
        </form>
    </div>
</div>

<div class="grid-2" style="margin-top:18px;">
    <div class="card">
        <h3>Safety Checks</h3>
        <p class="subtext">Restore and wipe actions use two confirmations so they are harder to trigger by accident.</p>
        <ol style="margin:12px 0 0 18px; padding:0; display:grid; gap:8px;">
            <li>First, you must confirm that you want to continue.</li>
            <li>Second, you must type the requested word exactly to authorize the action.</li>
        </ol>
        <p class="subtext" style="margin-top:14px;">Restore requires <strong>RESTORE</strong>. Wipe requires <strong>WIPE</strong>.</p>
    </div>

    <div class="card">
        <h3>Maintenance Notes</h3>
        <p class="subtext">Optimization and repair are intended for normal maintenance and troubleshooting. They are safe to run after imports, restores, or larger batches of changes when you want to tidy the database tables.</p>
        <ul style="margin:12px 0 0 18px; padding:0; display:grid; gap:8px;">
            <li><strong>Optimize</strong> can reclaim storage overhead and refresh table statistics.</li>
            <li><strong>Repair</strong> is useful when a table looks damaged or a local environment shuts down badly.</li>
            <li>Neither action replaces the need for regular backups.</li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var wipeButton = document.getElementById('wipe-ledger-button');
    var wipeForm = document.getElementById('wipe-ledger-form');
    var stageInput = document.getElementById('wipe-confirm-stage');
    var textInput = document.getElementById('wipe-confirm-text');

    if (wipeButton && wipeForm && stageInput && textInput) {
        wipeButton.addEventListener('click', function () {
            var firstOk = window.confirm('This will permanently delete all ledger entries. This cannot be undone. Click OK to continue or Cancel to stop.');
            if (!firstOk) {
                return;
            }

            var secondText = window.prompt('Type WIPE to permanently delete all ledger entries.');
            if (secondText === null) {
                return;
            }

            stageInput.value = 'confirmed';
            textInput.value = secondText;
            wipeForm.submit();
        });
    }

    var restoreButton = document.getElementById('restore-backup-button');
    var restoreForm = document.getElementById('restore-backup-form');
    var restoreStageInput = document.getElementById('restore-confirm-stage');
    var restoreTextInput = document.getElementById('restore-confirm-text');
    var restoreFileInput = document.getElementById('backup-file');

    if (restoreButton && restoreForm && restoreStageInput && restoreTextInput && restoreFileInput) {
        restoreButton.addEventListener('click', function () {
            if (!restoreFileInput.files || !restoreFileInput.files.length) {
                window.alert('Choose a RewardLedger backup JSON file first.');
                return;
            }

            var firstOk = window.confirm('This will replace your current RewardLedger data with the selected backup file. Click OK to continue or Cancel to stop.');
            if (!firstOk) {
                return;
            }

            var secondText = window.prompt('Type RESTORE to replace your current RewardLedger data with this backup.');
            if (secondText === null) {
                return;
            }

            restoreStageInput.value = 'confirmed';
            restoreTextInput.value = secondText;
            restoreForm.submit();
        });
    }

    var optimizeButton = document.getElementById('optimize-database-button');
    var optimizeForm = document.getElementById('optimize-database-form');
    if (optimizeButton && optimizeForm) {
        optimizeButton.addEventListener('click', function () {
            var ok = window.confirm('Optimize all RewardLedger database tables now? This is a safe maintenance action and will not delete your saved data.');
            if (ok) {
                optimizeForm.submit();
            }
        });
    }

    var repairButton = document.getElementById('repair-database-button');
    var repairForm = document.getElementById('repair-database-form');
    if (repairButton && repairForm) {
        repairButton.addEventListener('click', function () {
            var ok = window.confirm('Run database repair checks on all RewardLedger tables now? This is a safe maintenance action and will not delete your saved data.');
            if (ok) {
                repairForm.submit();
            }
        });
    }
});
</script>
