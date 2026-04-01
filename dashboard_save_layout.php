<?php
require_once 'config.php';
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$layout = $data['layout'] ?? [];

if (!is_array($layout)) {
    http_response_code(400);
    exit('Invalid layout');
}

$stmt_category = $conn->prepare("
    UPDATE categories
    SET dashboard_row = ?, dashboard_order = ?
    WHERE id = ?
");

$stmt_tile = $conn->prepare("
    UPDATE custom_dashboard_tiles
    SET dashboard_row = ?, dashboard_order = ?
    WHERE id = ?
");

if (!$stmt_category || !$stmt_tile) {
    http_response_code(500);
    exit('Prepare failed: ' . $conn->error);
}

foreach ($layout as $item) {
    $item_type = (string)($item['item_type'] ?? 'category');
    $category_id = (int)($item['category_id'] ?? 0);
    $tile_id = (int)($item['tile_id'] ?? 0);
    $dashboard_row = (int)($item['dashboard_row'] ?? 1);
    $dashboard_order = (int)($item['dashboard_order'] ?? 1);

    if ($item_type === 'custom_tile') {
        if ($tile_id <= 0) {
            continue;
        }

        $stmt_tile->bind_param('iii', $dashboard_row, $dashboard_order, $tile_id);
        $stmt_tile->execute();
        continue;
    }

    if ($category_id <= 0) {
        continue;
    }

    $stmt_category->bind_param('iii', $dashboard_row, $dashboard_order, $category_id);
    $stmt_category->execute();
}

$stmt_category->close();
$stmt_tile->close();
echo 'OK';
