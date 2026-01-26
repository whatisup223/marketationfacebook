<?php
// includes/api/update_campaign_status.php
require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

function logDebug($msg)
{
    file_put_contents(__DIR__ . '/../../includes/settings_debug.log', date('Y-m-d H:i:s') . " [STATUS] " . $msg . "\n", FILE_APPEND);
}

logDebug("Hit Status API. POST: " . print_r($_POST, true));

$campaign_id = $_POST['campaign_id'] ?? 0;
$new_status = $_POST['status'] ?? '';

if (!$campaign_id || !$new_status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// Allow only specific statuses
$allowed_statuses = ['running', 'completed', 'paused', 'scheduled'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $GLOBALS['pdo'];

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Campaign not found or access denied']);
    exit;
}

// Update status
try {
    $updateStmt = $pdo->prepare("UPDATE campaigns SET status = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $campaign_id]);
    logDebug("Updated Status to $new_status for $campaign_id");
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    logDebug("Exception: " . $e->getMessage());
}
