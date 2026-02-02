<?php
require_once 'includes/db_config.php';
require_once 'includes/facebook_api.php';

// Get the page the user is likely testing with (e.g., page_id from the log was 114759763777160)
$page_id = '114759763777160';
$stmt = $pdo->prepare("SELECT p.page_name, p.page_access_token FROM fb_pages p WHERE p.page_id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if ($page) {
    echo "Checking Token for Page: " . $page['page_name'] . "\n";
    $fb = new FacebookAPI();
    $res = $fb->makeRequest('debug_token', [
        'input_token' => $page['page_access_token']
    ], $page['page_access_token']); // Note: debug_token usually needs an APP token, but let's see /me

    // A simpler way: just call /me with the page token
    $me = $fb->makeRequest('me', ['fields' => 'id,name'], $page['page_access_token']);
    echo "API /me Response: " . json_encode($me) . "\n";
} else {
    echo "Page not found in DB with ID: $page_id\n";
    // List all pages to be sure
    $all = $pdo->query("SELECT id, page_id, page_name FROM fb_pages")->fetchAll(PDO::FETCH_ASSOC);
    print_r($all);
}
