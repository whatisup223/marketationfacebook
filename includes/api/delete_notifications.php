<?php
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if it's a single ID or array of IDs
    $ids = [];
    if (isset($data['id'])) {
        $ids[] = (int) $data['id'];
    } elseif (isset($data['ids']) && is_array($data['ids'])) {
        $ids = array_map('intval', $data['ids']);
    }

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No notifications specified']);
        exit;
    }

    $pdo = getDB();
    $user_id = $_SESSION['user_id'];

    // Create placeholders for prepared statement (e.g., ?, ?, ?)
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';

    // Prepare params: user_id first, then the list of IDs
    $params = array_merge([$user_id], $ids);

    $sql = "DELETE FROM notifications WHERE user_id = ? AND id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notifications']);
    }
}
?>