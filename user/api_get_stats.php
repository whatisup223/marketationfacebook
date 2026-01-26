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

$pdo = getDB();
$campaign_id = $_GET['id'] ?? 0;

if (!$campaign_id) {
    echo json_encode(['status' => 'error']);
    exit;
}

// Get basic campaign status
$stmt = $pdo->prepare("SELECT status FROM campaigns WHERE id = ?");
$stmt->execute([$campaign_id]);
$status = $stmt->fetchColumn();

// Get queue stats
$qCount = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status='sent' THEN 1 END) as sent,
    COUNT(CASE WHEN status='failed' THEN 1 END) as failed,
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending
    FROM campaign_queue WHERE campaign_id = ?");
$qCount->execute([$campaign_id]);
$stats = $qCount->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => $status,
    'sent' => $stats['sent'],
    'failed' => $stats['failed'],
    'pending' => $stats['pending']
]);
