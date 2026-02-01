<?php
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;

    // Debug logging
    file_put_contents(__DIR__ . '/../../debug_mark_read.log', date('Y-m-d H:i:s') . " - User: {$_SESSION['user_id']} - ID: $id\n", FILE_APPEND);

    if ($id && markNotificationAsRead($id, $_SESSION['user_id'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark read']);
    }
}
?>