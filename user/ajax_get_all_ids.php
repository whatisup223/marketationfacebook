<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$page_id = $_POST['page_id'] ?? 0;
if (!$page_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Page ID']);
    exit;
}

try {
    $pdo = getDB();

    // Check ownership
    $stmt = $pdo->prepare("SELECT p.id FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE p.id = ? AND a.user_id = ?");
    $stmt->execute([$page_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // Fetch All IDs
    $stmt = $pdo->prepare("SELECT id FROM fb_leads WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'status' => 'success',
        'ids' => $ids,
        'count' => count($ids)
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Error']);
}
