<?php
// includes/api/save_campaign_settings.php
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
    file_put_contents(__DIR__ . '/../../includes/settings_debug.log', date('Y-m-d H:i:s') . " [SAVE] " . $msg . "\n", FILE_APPEND);
}

logDebug("Hit Save API. POST: " . print_r($_POST, true));

$campaign_id = $_POST['campaign_id'] ?? 0;
// Use null coalescing to allow 0 values, but filter_var checks might be safer
$waiting_interval = $_POST['interval'] ?? null;
$retry_count = $_POST['retry_count'] ?? null;
$retry_delay = $_POST['retry_delay'] ?? null;

if (!$campaign_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing campaign ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $GLOBALS['pdo']; // db_config.php creates $pdo

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Campaign not found or access denied']);
    exit;
}

// Build Update Query
$fields = [];
$params = [];

if ($waiting_interval !== null) {
    $fields[] = "waiting_interval = ?";
    $params[] = (int) $waiting_interval;
}
if ($retry_count !== null) {
    $fields[] = "retry_count = ?";
    $params[] = (int) $retry_count;
}
if ($retry_delay !== null) {
    $fields[] = "retry_delay = ?";
    $params[] = (int) $retry_delay;
}

if (empty($fields)) {
    echo json_encode(['status' => 'success', 'message' => 'No changes']);
    exit;
}

$params[] = $campaign_id;
$sql = "UPDATE campaigns SET " . implode(', ', $fields) . " WHERE id = ?";

try {
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);
    logDebug("Executed Update. Params: " . print_r($params, true));
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    logDebug("Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
