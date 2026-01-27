<?php
// user/ajax_campaign_actions.php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$action = $_POST['action'] ?? '';
$campaign_id = (int) ($_POST['campaign_id'] ?? 0);

if (!$campaign_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Campaign ID']);
    exit;
}

// Verify Ownership
$stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Campaign not found or access denied']);
    exit;
}

try {
    if ($action === 'delete') {
        // Delete queue items first (if needed, though foreign keys should handle it)
        $pdo->prepare("DELETE FROM campaign_queue WHERE campaign_id = ?")->execute([$campaign_id]);
        $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$campaign_id]);
        echo json_encode(['status' => 'success', 'message' => 'Campaign deleted successfully']);
    } elseif ($action === 'edit_message') {
        $new_message = trim($_POST['message'] ?? '');
        if (empty($new_message)) {
            echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
            exit;
        }
        $pdo->prepare("UPDATE campaigns SET message_text = ? WHERE id = ?")->execute([$new_message, $campaign_id]);
        echo json_encode(['status' => 'success', 'message' => 'Message updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
