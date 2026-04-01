<?php

require_once 'config.php'; // adjust if needed

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    exit;
}

$stmt = $conn->prepare("UPDATE assets SET sort_order = ? WHERE id = ?");

if (!$stmt) {
    exit;
}

foreach ($data as $row) {
    $sort = (int)$row['sort_order'];
    $id = (int)$row['id'];

    $stmt->bind_param("ii", $sort, $id);
    $stmt->execute();
}

$stmt->close();

?>