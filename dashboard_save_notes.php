<?php
require_once 'config.php';
require_once 'db.php';
require_once 'helpers.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$dashboard_notes = trim((string)($data['dashboard_notes'] ?? ''));

if (!set_setting($conn, 'dashboard_notes', $dashboard_notes)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not save dashboard notes.'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'dashboard_notes' => $dashboard_notes
]);

?>
