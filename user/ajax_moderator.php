<?php
// user/ajax_moderator.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$action = $_GET['action'] ?? '';

// 1. Get Rules
if ($action === 'get_rules') {
    $page_id = $_GET['page_id'] ?? '';
    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE page_id = ? AND user_id = ?");
    $stmt->execute([$page_id, $user_id]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rules) {
        // Return default empty rules
        $rules = [
            'page_id' => $page_id,
            'banned_keywords' => '',
            'hide_phones' => 0,
            'hide_links' => 0,
            'action_type' => 'hide',
            'is_active' => 0
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $rules]);
    exit;
}

// 2. Save Rules
if ($action === 'save_rules' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = $_POST['page_id'] ?? '';
    $banned_keywords = $_POST['banned_keywords'] ?? '';
    $hide_phones = isset($_POST['hide_phones']) ? 1 : 0;
    $hide_links = isset($_POST['hide_links']) ? 1 : 0;
    $action_type = $_POST['action_type'] ?? 'hide';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID required']);
        exit;
    }

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM fb_moderation_rules WHERE page_id = ? AND user_id = ?");
    $stmt->execute([$page_id, $user_id]);
    $existing_id = $stmt->fetchColumn();

    if ($existing_id) {
        $stmt = $pdo->prepare("UPDATE fb_moderation_rules SET banned_keywords = ?, hide_phones = ?, hide_links = ?, action_type = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$banned_keywords, $hide_phones, $hide_links, $action_type, $is_active, $existing_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO fb_moderation_rules (user_id, page_id, banned_keywords, hide_phones, hide_links, action_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $page_id, $banned_keywords, $hide_phones, $hide_links, $action_type, $is_active]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Rules saved successfully']);
    exit;
}

// 3. Get Logs
if ($action === 'get_logs') {
    $page_id = $_GET['page_id'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_logs WHERE user_id = ? " . ($page_id ? "AND page_id = ?" : "") . " ORDER BY created_at DESC LIMIT 50");
    if ($page_id) {
        $stmt->execute([$user_id, $page_id]);
    } else {
        $stmt->execute([$user_id]);
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $logs]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
