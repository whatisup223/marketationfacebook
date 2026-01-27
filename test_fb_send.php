<?php
/**
 * Facebook Messaging Diagnostic Tool
 * استخدم هذا الملف لتشخيص مشاكل الإرسال في الإنتاج
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/facebook_api.php';

header('Content-Type: application/json; charset=utf-8');

// معلومات الاختبار
$page_id = $_GET['page_id'] ?? '';
$access_token = $_GET['token'] ?? '';
$recipient_id = $_GET['recipient'] ?? '';

if (empty($page_id) || empty($access_token) || empty($recipient_id)) {
    echo json_encode([
        'error' => 'Missing parameters',
        'usage' => 'test_fb_send.php?page_id=XXX&token=YYY&recipient=ZZZ',
        'note' => 'هذا الملف للتشخيص فقط - احذفه بعد الانتهاء'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$fb = new FacebookAPI();

// 1. اختبار Token
echo "=== Testing Access Token ===\n";
$token_test = $fb->getObject('me', $access_token, ['id', 'name']);
echo json_encode($token_test, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// 2. اختبار إرسال رسالة نصية
echo "=== Testing Text Message ===\n";
$result = $fb->sendMessage($page_id, $access_token, $recipient_id, 'Test message from diagnostic tool', null);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// 3. فحص ملف الـ Log
echo "=== Recent Log Entries ===\n";
$log_file = __DIR__ . '/includes/fb_debug.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent = array_slice($lines, -10); // آخر 10 سطور
    echo implode("\n", $recent);
} else {
    echo "No log file found\n";
}

// 4. معلومات السيرفر
echo "\n\n=== Server Info ===\n";
echo json_encode([
    'PHP Version' => PHP_VERSION,
    'CURL Version' => curl_version()['version'] ?? 'N/A',
    'SSL Version' => curl_version()['ssl_version'] ?? 'N/A',
    'Host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
], JSON_PRETTY_PRINT);
?>