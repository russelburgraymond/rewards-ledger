<?php

$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$from_context = trim((string)($_GET['from'] ?? ''));
$back_href = ($from_context === 'settings_templates') ? 'index.php?page=quick_adds' : 'index.php?page=templates';

if ($template_id <= 0) {
    echo "<script>window.location='" . $back_href . "&msg=invalid_template';</script>";
    exit;
}

$error = '';
$deleted = false;

$stmt = $conn->prepare("SELECT id, template_name FROM templates WHERE id = ? LIMIT 1");
if (!$stmt) {
    $error = "Prepare failed: " . $conn->error;
} else {
    $stmt->bind_param("i", $template_id);
    if (!$stmt->execute()) {
        $error = "Execute failed: " . $stmt->error;
    } else {
        $res = $stmt->get_result();
        $template = $res ? $res->fetch_assoc() : null;
        if (!$template) {
            $error = "Template not found.";
        }
    }
    $stmt->close();
}

if ($error === '') {
    $conn->begin_transaction();

    try {
        // If any batches point at this template, detach them first.
        // This keeps old saved batches safe.
        $stmt = $conn->prepare("UPDATE batches SET template_id = NULL WHERE template_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed (batches update): " . $conn->error);
        }
        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) {
            throw new Exception("Could not detach batches from template: " . $stmt->error);
        }
        $stmt->close();

        // Delete template items
        $stmt = $conn->prepare("DELETE FROM template_items WHERE template_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed (delete template items): " . $conn->error);
        }
        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) {
            throw new Exception("Could not delete template items: " . $stmt->error);
        }
        $stmt->close();

        // Delete template
        $stmt = $conn->prepare("DELETE FROM templates WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare failed (delete template): " . $conn->error);
        }
        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) {
            throw new Exception("Could not delete template: " . $stmt->error);
        }

        $deleted_rows = $stmt->affected_rows;
        $stmt->close();

        if ($deleted_rows < 1) {
            throw new Exception("Template row was not deleted.");
        }

        $conn->commit();
        $deleted = true;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

if ($deleted) {
    echo "<script>window.location='" . $back_href . "&msg=template_deleted';</script>";
    exit;
}

echo "<div class='card'>";
echo "<h2>Could not delete template</h2>";
echo "<p>" . h($error) . "</p>";
echo "<p><a class='btn btn-secondary' href='" . h($back_href) . "'>Back</a></p>";
echo "</div>";
exit;