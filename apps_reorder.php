<?php

require 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
    exit;
}

$stmt = $conn->prepare("\n    UPDATE apps\n    SET sort_order = ?\n    WHERE id = ?\n");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not prepare reorder statement.']);
    exit;
}

foreach ($data as $row) {
    $id = (int)($row['id'] ?? 0);
    $sort_order = (int)($row['sort_order'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    $stmt->bind_param("ii", $sort_order, $id);
    $stmt->execute();
}

$stmt->close();

echo json_encode(['success' => true]);
