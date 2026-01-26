<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$page_id = $_POST['page_id'] ?? 0;
$new_token = trim($_POST['token'] ?? '');

if (!$page_id || !$new_token) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

try {
    // Verify ownership and update
    $stmt = $pdo->prepare("UPDATE fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           SET p.page_access_token = ? 
                           WHERE p.id = ? AND a.user_id = ?");
    $result = $stmt->execute([$new_token, $page_id, $user_id]);

    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
