<?php

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare("
    UPDATE quick_add_items
    SET sort_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    exit;
}

foreach ($data as $row) {
    $id = (int)($row['id'] ?? 0);
    $sort_order = (int)($row['sort_order'] ?? 0);

    $stmt->bind_param("ii", $sort_order, $id);
    $stmt->execute();
}

$stmt->close();

echo json_encode(['success' => true]);

?>