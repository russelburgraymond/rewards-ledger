<?php
require_once 'config.php';
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$layout = $data['layout'] ?? [];

if (!is_array($layout)) {
    http_response_code(400);
    exit('Invalid layout');
}

$stmt = $conn->prepare("
    UPDATE categories
    SET dashboard_row = ?, dashboard_order = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    exit('Prepare failed: ' . $conn->error);
}

foreach ($layout as $item) {
    $category_id = (int)($item['category_id'] ?? 0);
    $dashboard_row = (int)($item['dashboard_row'] ?? 1);
    $dashboard_order = (int)($item['dashboard_order'] ?? 1);

    if ($category_id <= 0) {
        continue;
    }

    $stmt->bind_param("iii", $dashboard_row, $dashboard_order, $category_id);
    $stmt->execute();
}

$stmt->close();
echo 'OK';