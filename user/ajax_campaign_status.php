<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$campaign_id = $_GET['id'] ?? null;

if (!$campaign_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Campaign ID required']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT status, sent_count, failed_count, total_count FROM wa_campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    http_response_code(404);
    echo json_encode(['error' => 'Campaign not found']);
    exit;
}

echo json_encode($campaign);
