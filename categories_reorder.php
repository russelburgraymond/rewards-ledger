<?php

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare("
    UPDATE categories
    SET sort_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    exit;
}

foreach ($data as $row) {
    $id = (int)$row['id'];
    $sort_order = (int)$row['sort_order'];

    $stmt->bind_param("ii", $sort_order, $id);
    $stmt->execute();
}

$stmt->close();

echo json_encode(['success' => true]);

?>