<?php

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE templates
    SET sort_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}

foreach ($payload as $row) {
    $id = (int)($row['id'] ?? 0);
    $sort_order = (int)($row['sort_order'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $stmt->bind_param('ii', $sort_order, $id);
    $stmt->execute();
}

$stmt->close();
echo json_encode(['ok' => true]);
