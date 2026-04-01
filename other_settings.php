<?php

$current_page = 'other_settings';

$error = trim((string)($_GET['error'] ?? ''));
$success = trim((string)($_GET['success'] ?? ''));

$ledger_item_count = 0;
$ledger_batch_count = 0;
$backup_table_count = 0;
$backup_row_count = 0;

if (isset($conn) && $conn instanceof mysqli) {
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'wipe_ledger_entries') {
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
            $ledger_item_count = 0;
            $ledger_batch_count = 0;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
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

    <div class="card">
        <h3>Safety Checks</h3>
        <p class="subtext">Restore and wipe actions use two confirmations so they are harder to trigger by accident.</p>
        <ol style="margin:12px 0 0 18px; padding:0; display:grid; gap:8px;">
            <li>First, you must confirm that you want to continue.</li>
            <li>Second, you must type the requested word exactly to authorize the action.</li>
        </ol>
        <p class="subtext" style="margin-top:14px;">Restore requires <strong>RESTORE</strong>. Wipe requires <strong>WIPE</strong>.</p>
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
});
</script>
