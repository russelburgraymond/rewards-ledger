<?php

require 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid reorder payload.'
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE template_items
    SET sort_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

foreach ($data as $row) {
    $id = (int)($row['id'] ?? 0);
    $sort_order = (int)($row['sort_order'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    $stmt->bind_param("ii", $sort_order, $id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Execute failed: ' . $stmt->error,
            'id' => $id,
            'sort_order' => $sort_order
        ]);
        $stmt->close();
        exit;
    }
}

$stmt->close();

echo json_encode([
    'success' => true
]);