<?php
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'];

    // Get current prefs
    $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $prefs = json_decode($stmt->fetchColumn() ?: '{}', true);

    // Toggle
    $current = $prefs['notifications_muted'] ?? false;
    $prefs['notifications_muted'] = !$current;

    // Save
    $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");

    if ($stmt->execute([json_encode($prefs), $user_id])) {
        echo json_encode(['success' => true, 'muted' => $prefs['notifications_muted']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update preferences']);
    }
}
?>