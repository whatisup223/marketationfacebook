<?php
// user/api_sync_rows.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

session_write_close(); // Non-blocking

$pdo = getDB();
$ids_raw = $_POST['ids'] ?? '';

if (!$ids_raw) {
    echo json_encode(['status' => 'ok', 'data' => []]);
    exit;
}

$ids_array = explode(',', $ids_raw);
$ids_array = array_map('intval', $ids_array);
$ids_valid = array_filter($ids_array);

if (empty($ids_valid)) {
    echo json_encode(['status' => 'ok', 'data' => []]);
    exit;
}

// Create placeholders for IN clause
$placeholders = implode(',', array_fill(0, count($ids_valid), '?'));

$stmt = $pdo->prepare("SELECT id, status, sent_at, error_message FROM campaign_queue WHERE id IN ($placeholders)");
$stmt->execute($ids_valid);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['status' => 'ok', 'data' => $rows]);
