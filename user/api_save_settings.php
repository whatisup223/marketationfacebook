<?php
// user/api_save_settings.php
session_start(); // Ensure session is started
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$campaign_id = $_POST['campaign_id'] ?? 0;
$waiting_interval = $_POST['interval'] ?? null;
$retry_count = $_POST['retry_count'] ?? null;
$retry_delay = $_POST['retry_delay'] ?? null;
$batch_size = $_POST['batch_size'] ?? null;

if (!$campaign_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing campaign ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

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
    $params[] = max(0, (int) $waiting_interval);
}
if ($retry_count !== null) {
    $fields[] = "retry_count = ?";
    $params[] = max(0, (int) $retry_count);
}
if ($retry_delay !== null) {
    $fields[] = "retry_delay = ?";
    $params[] = max(0, (int) $retry_delay);
}
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
