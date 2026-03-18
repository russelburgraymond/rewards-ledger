<?php

require_once "db.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE categories
    SET dashboard_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}

foreach ($data as $row) {
    $category_id = (int)($row['category_id'] ?? 0);
    $position = (int)($row['position'] ?? 0);

    if ($category_id <= 0) {
        continue;
    }

    $stmt->bind_param("ii", $position, $category_id);
    $stmt->execute();
}

$stmt->close();

echo json_encode(['ok' => true]);