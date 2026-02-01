<?php
require_once 'includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$log = [];
$log[] = "Starting debug...";

// Get first admin user
$pdo = getDB();
$stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    $log[] = "No admin user found.";
    file_put_contents('debug_log.txt', implode("\n", $log));
    exit;
}

$log[] = "Testing with User ID: $user_id";

// 1. Add Notification
$title = "Test Notification";
$msg = json_encode(['key' => 'handover_notification_msg', 'params' => ['Page A', 'Section B'], 'param_keys' => [1]]);
$link = "test_link.php";

$log[] = "Adding notification...";
$res = addNotification($user_id, $title, $msg, $link);

if ($res) {
    $log[] = "Notification added successfully.";
    $notif_id = $pdo->lastInsertId();
    $log[] = "Notification ID: $notif_id";

    // Check if it exists and is unread
    $check = $pdo->prepare("SELECT is_read, message FROM notifications WHERE id = ?");
    $check->execute([$notif_id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    $log[] = "Current Status: is_read = " . $row['is_read'];
    $log[] = "Message Content: " . $row['message'];

    // 2. Mark as Read
    $log[] = "Marking as read...";
    $markRes = markNotificationAsRead($notif_id, $user_id);

    if ($markRes) {
        $log[] = "Mark function returned true.";

        // Verify in DB
        $check->execute([$notif_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $log[] = "New Status: is_read = " . $row['is_read'];

        if ($row['is_read'] == 1) {
            $log[] = "SUCCESS: Notification marked as read.";
        } else {
            $log[] = "FAILURE: Function returned true but DB not updated.";
        }
    } else {
        $log[] = "FAILURE: Mark function returned false.";
    }

    // 3. Test Translation Logic
    $log[] = "Testing Translation Logic...";
    $msgText = $row['message'];
    $decoded = json_decode($msgText, true);

    if ($decoded && is_array($decoded) && isset($decoded['key'])) {
        $log[] = "JSON Decode Success. Key: " . $decoded['key'];
        if (isset($decoded['params'])) {
            $log[] = "Params: " . implode(", ", $decoded['params']);
        }
    } else {
        $log[] = "JSON Decode Failed.";
    }

} else {
    $log[] = "Failed to add notification.";
}

file_put_contents('debug_log.txt', implode("\n", $log));
?>