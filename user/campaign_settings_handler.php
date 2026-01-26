<?php
// user/campaign_settings_handler.php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Disable error display in JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$campaign_id = $_POST['campaign_id'] ?? 0;
// Use null coalescing to allow 0 values
$waiting_interval = $_POST['interval'] ?? null;
$retry_count = $_POST['retry_count'] ?? null;
$retry_delay = $_POST['retry_delay'] ?? null;

if (!$campaign_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing campaign ID']);
    exit;
}

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
    if ((int) $waiting_interval < 0)
        $waiting_interval = 0;
    $fields[] = "waiting_interval = ?";
    $params[] = (int) $waiting_interval;
}
if ($retry_count !== null) {
    if ((int) $retry_count < 0)
        $retry_count = 0;
    $fields[] = "retry_count = ?";
    $params[] = (int) $retry_count;
}
if ($retry_delay !== null) {
    if ((int) $retry_delay < 0)
        $retry_delay = 0;
    $fields[] = "retry_delay = ?";
    $params[] = (int) $retry_delay;
}
$batch_size = $_POST['batch_size'] ?? null;
if ($batch_size !== null) {
    $bf = (int) $batch_size;
    if ($bf < 1)
        $bf = 1;
    if ($bf > 50)
        $bf = 50;
    $fields[] = "batch_size = ?";
    $params[] = $bf;
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
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
