<?php
// user/campaign_batch_handler.php
// PRODUCTION FIX: Enable error display temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start session first
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');
ignore_user_abort(true);
set_time_limit(300);

// Read user_id BEFORE closing session
$user_id = $_SESSION['user_id'] ?? 0;

// Now unlock session for parallel requests
if (session_status() === PHP_SESSION_ACTIVE)
    session_write_close();

// PRODUCTION FIX: If no session, try to get from POST/campaign ownership
if (!$user_id) {
    $campaign_id = $_POST['campaign_id'] ?? 0;
    if ($campaign_id) {
        // Get user_id from campaign table instead
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT user_id FROM campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $user_id = $stmt->fetchColumn();
    }
}

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - No valid session or campaign']);
    exit;
}

$pdo = getDB();
$campaign_id = $_POST['campaign_id'] ?? 0;

// Verify ownership - try with batch_size first, fallback if column doesn't exist
try {
    $stmt = $pdo->prepare("SELECT status, waiting_interval, batch_size, retry_count, retry_delay FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$campaign_id, $user_id]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If batch_size column doesn't exist, query without it
    if (strpos($e->getMessage(), 'batch_size') !== false) {
        $stmt = $pdo->prepare("SELECT status, waiting_interval, retry_count, retry_delay FROM campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campaign_id, $user_id]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($camp) {
            $camp['batch_size'] = 1; // Default value
        }
    } else {
        throw $e;
    }
}

if (!$camp || $camp['status'] !== 'running') {
    echo json_encode(['status' => 'stopped', 'message' => 'Campaign not running']);
    exit;
}

$interval = (int) ($camp['waiting_interval'] ?? 30);
$batch_size = (int) ($camp['batch_size'] ?? 1);

// Safety Cap
if ($batch_size > 50)
    $batch_size = 50;
if ($batch_size < 1)
    $batch_size = 1;

$fb = new FacebookAPI();
$processed_results = [];

// Fetch Pending Items
$qStmt = $pdo->prepare("
    SELECT q.id as q_id, c.message_text, c.image_url, 
           l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id 
    FROM campaign_queue q
    JOIN campaigns c ON q.campaign_id = c.id
    JOIN fb_leads l ON q.lead_id = l.id
    JOIN fb_pages p ON c.page_id = p.id
    WHERE q.campaign_id = ? AND q.status = 'pending'
    ORDER BY q.id ASC
    LIMIT $batch_size
");
$qStmt->execute([$campaign_id]);
$items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    // Check completion
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = $campaign_id AND status IN ('pending', 'processing')")->fetchColumn();
    if ($pendingCount == 0) {
        $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
        echo json_encode(['status' => 'completed', 'message' => 'All messages sent']);
    } else {
        echo json_encode(['status' => 'no_pending', 'message' => 'No pending items in this batch']);
    }
    exit;
}

// Process batch
foreach ($items as $item) {
    $queue_id = $item['q_id'];
    $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $item['message_text']);

    // Mark as processing
    $pdo->prepare("UPDATE campaign_queue SET status = 'processing' WHERE id = ?")->execute([$queue_id]);

    $res = $fb->sendMessage($item['fb_page_id'], $item['page_access_token'], $item['fb_user_id'], $message, $item['image_url']);

    if (isset($res['error'])) {
        $upd = $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $upd->execute([$res['error'], $queue_id]);
        $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");
        $processed_results[] = ['id' => $queue_id, 'status' => 'failed', 'error' => $res['error']];
    } else {
        $upd = $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $upd->execute([$queue_id]);
        $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");
        $processed_results[] = ['id' => $queue_id, 'status' => 'sent'];
    }
}

echo json_encode([
    'status' => 'batch_processed',
    'processed' => count($processed_results),
    'results' => $processed_results
]);
