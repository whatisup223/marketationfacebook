<?php
// user/api_get_stats.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Release session lock to prevent blocking parallel requests (Vital for real-time stats)
session_write_close();

$pdo = getDB();
$campaign_id = $_GET['id'] ?? 0;

if (!$campaign_id) {
    echo json_encode(['status' => 'error']);
    exit;
}

// OPTIMIZED: Get stats directly from campaigns table (O(1)) instead of counting queue rows (O(N))
// This provides instant updates without heavy DB load
$stmt = $pdo->prepare("SELECT status, sent_count, failed_count, total_leads FROM campaigns WHERE id = ?");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    echo json_encode(['status' => 'error', 'message' => 'Campaign not found']);
    exit;
}

$sent = (int) $campaign['sent_count'];
$failed = (int) $campaign['failed_count'];
$total = (int) $campaign['total_leads'];
$pending = max(0, $total - ($sent + $failed)); // Calculated, or we could store it too

echo json_encode([
    'status' => $campaign['status'],
    'sent' => $sent,
    'failed' => $failed,
    'pending' => $pending
]);
