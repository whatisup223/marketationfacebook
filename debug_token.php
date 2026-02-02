<?php
require_once 'includes/db_config.php';
require_once 'includes/facebook_api.php';

$stmt = $pdo->prepare("SELECT p.page_access_token FROM fb_pages p WHERE p.id = 193");
$stmt->execute();
$token = $stmt->fetchColumn();

$fb = new FacebookAPI();
// We need an APP Access Token to debug a Page Token usually, 
// but we can try to just get /me with it
$res = $fb->makeRequest('me', ['fields' => 'id,name,permissions'], $token);
print_r($res);

// Also try to get a specific comment that failed
$comment_id = "1419970046803450_2030931314332242";
$res_c = $fb->makeRequest($comment_id, ['fields' => 'from,message'], $token);
echo "--- Comment Debug ---\n";
print_r($res_c);
